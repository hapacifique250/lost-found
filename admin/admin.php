<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../pages/login');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../pages/dashboard');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get admin statistics
$statsStmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM items) as total_items,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM claims WHERE status = 'pending') as total_claims,
        (SELECT COUNT(*) FROM items WHERE status = 'resolved') as resolved_items
");
$statsStmt->execute();
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get recent activity (last 10 items)
$activityStmt = $pdo->prepare("
    SELECT i.*, u.name as user_name 
    FROM items i 
    JOIN users u ON i.user_id = u.id 
    ORDER BY i.created_at DESC 
    LIMIT 10
");
$activityStmt->execute();
$recentActivity = $activityStmt->fetchAll(PDO::FETCH_ASSOC);

// Get pending claims
$pendingClaimsStmt = $pdo->prepare("
    SELECT c.*, i.title as item_title, u.name as claimant_name 
    FROM claims c 
    JOIN items i ON c.item_id = i.id 
    JOIN users u ON c.claimer_id = u.id 
    WHERE c.status = 'pending' 
    ORDER BY c.created_at DESC 
    LIMIT 5
");
$pendingClaimsStmt->execute();
$pendingClaims = $pendingClaimsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all items for management
$itemsStmt = $pdo->prepare("
    SELECT i.*, u.name as user_name 
    FROM items i 
    JOIN users u ON i.user_id = u.id 
    ORDER BY i.created_at DESC
");
$itemsStmt->execute();
$allItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all users for management
$usersStmt = $pdo->prepare("
    SELECT u.*, 
           (SELECT COUNT(*) FROM items WHERE user_id = u.id) as items_count 
    FROM users u 
    ORDER BY u.created_at DESC
");
$usersStmt->execute();
$allUsers = $usersStmt->fetchAll(PDO::FETCH_ASSOC);

// Get all claims for management
$claimsStmt = $pdo->prepare("
    SELECT c.*, i.title as item_title, i.status as item_status, i.id as item_id, 
           u.name as claimant_name, u.email as claimant_email 
    FROM claims c 
    JOIN items i ON c.item_id = i.id 
    JOIN users u ON c.claimer_id = u.id 
    ORDER BY c.created_at DESC
");
$claimsStmt->execute();
$allClaims = $claimsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle actions via POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'update_claim_status':
            $claim_id = $_POST['claim_id'] ?? '';
            $status = $_POST['status'] ?? '';

            if ($claim_id && in_array($status, ['pending', 'approved', 'rejected','resolved'])) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get claim details with item and user info
                    $claimStmt = $pdo->prepare("
                        SELECT 
                            c.*,
                            i.title as item_title,
                            i.user_id as item_owner_id,
                            i.status as item_status,
                            claimant.id as claimant_id,
                            claimant.name as claimant_name,
                            owner.name as owner_name
                        FROM claims c
                        JOIN items i ON c.item_id = i.id
                        JOIN users claimant ON c.claimer_id = claimant.id
                        JOIN users owner ON i.user_id = owner.id
                        WHERE c.id = ?
                    ");
                    $claimStmt->execute([$claim_id]);
                    $claimData = $claimStmt->fetch(PDO::FETCH_ASSOC);

                    // Update claim status
                    $updateStmt = $pdo->prepare("UPDATE claims SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$status, $claim_id]);

                    $item_id = $claimData['item_id'] ?? null;

                    // Update item status based on claim status
                    if ($status === 'pending' && $item_id) {
                        $updateItemStmt = $pdo->prepare("UPDATE items SET status = 'claimed', updated_at = NOW() WHERE id = ?");
                        $updateItemStmt->execute([$item_id]);
                    } elseif ($status === 'resolved' && $item_id) {
                        $updateItemStmt = $pdo->prepare("UPDATE items SET status = 'resolved', updated_at = NOW() WHERE id = ?");
                        $updateItemStmt->execute([$item_id]);
                    } elseif ($status === 'rejected' && $item_id) {
                        $updateItemStmt = $pdo->prepare("UPDATE items SET status = 'active', updated_at = NOW() WHERE id = ?");
                        $updateItemStmt->execute([$item_id]);
                    }

                    // Create notifications for both parties
                    if ($claimData) {
                        $notificationStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        // Notification for claimant
                        $claimantTitle = "📋 Claim Status Updated";
                        $claimantMessage = "Your claim for \"{$claimData['item_title']}\" has been {$status} by admin.";
                        $claimantType = $status === 'approved' ? 'success' : ($status === 'rejected' ? 'danger' : 'info');
                        $claimantActionUrl = "../pages/dashboard";
                        
                        $notificationStmt->execute([
                            $claimData['claimer_id'],
                            $claimantTitle,
                            $claimantMessage,
                            $claimantType,
                            $claimantActionUrl
                        ]);

                        // Notification for item owner
                        $ownerTitle = "📋 Claim on Your Item Updated";
                        $ownerMessage = "The claim by {$claimData['claimant_name']} on your item \"{$claimData['item_title']}\" has been {$status} by admin.";
                        $ownerType = 'info';
                        $ownerActionUrl = "../pages/dashboard";
                        
                        $notificationStmt->execute([
                            $claimData['item_owner_id'],
                            $ownerTitle,
                            $ownerMessage,
                            $ownerType,
                            $ownerActionUrl
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Claim status updated successfully']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'update_user_status':
            $user_id_action = $_POST['user_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $reason = $_POST['reason'] ?? 'No reason provided';

            if ($user_id_action && in_array($status, ['active', 'suspended'])) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get user details before updating
                    $userStmt = $pdo->prepare("SELECT name, email FROM users WHERE id = ?");
                    $userStmt->execute([$user_id_action]);
                    $userData = $userStmt->fetch(PDO::FETCH_ASSOC);

                    // Update user status
                    $updateStmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$status, $user_id_action]);

                    // Create notification for user
                    if ($userData) {
                        $notificationStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $notificationTitle = "👤 Account Status Updated";
                        $notificationMessage = "Your account has been {$status}. Reason: {$reason}";
                        $notificationType = $status === 'suspended' ? 'danger' : 'success';
                        $actionUrl = "../pages/support";
                        
                        $notificationStmt->execute([
                            $user_id_action,
                            $notificationTitle,
                            $notificationMessage,
                            $notificationType,
                            $actionUrl
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'User status updated successfully']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'delete_item':
            $item_id = $_POST['item_id'] ?? '';
            $reason = $_POST['reason'] ?? 'No reason provided';

            if ($item_id) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get item details and owner info before deleting
                    $itemStmt = $pdo->prepare("
                        SELECT i.*, u.name as owner_name, u.id as owner_id 
                        FROM items i 
                        JOIN users u ON i.user_id = u.id 
                        WHERE i.id = ?
                    ");
                    $itemStmt->execute([$item_id]);
                    $itemData = $itemStmt->fetch(PDO::FETCH_ASSOC);

                    // First delete related claims
                    $deleteClaimsStmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ?");
                    $deleteClaimsStmt->execute([$item_id]);

                    // Then delete the item
                    $deleteStmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
                    $deleteStmt->execute([$item_id]);

                    // Create notification for item owner
                    if ($itemData) {
                        $notificationStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $notificationTitle = "🗑️ Item Removed by Admin";
                        $notificationMessage = "Your item \"{$itemData['title']}\" has been removed by admin. Reason: {$reason}";
                        $notificationType = 'danger';
                        $actionUrl = "../pages/support";
                        
                        $notificationStmt->execute([
                            $itemData['owner_id'],
                            $notificationTitle,
                            $notificationMessage,
                            $notificationType,
                            $actionUrl
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            }
            exit;

        case 'update_item_status':
            $item_id = $_POST['item_id'] ?? '';
            $status = $_POST['status'] ?? '';
            $reason = $_POST['reason'] ?? 'No reason provided';

            if ($item_id && in_array($status, ['active', 'resolved', 'claimed'])) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get item details and owner info before updating
                    $itemStmt = $pdo->prepare("
                        SELECT i.*, u.name as owner_name, u.id as owner_id 
                        FROM items i 
                        JOIN users u ON i.user_id = u.id 
                        WHERE i.id = ?
                    ");
                    $itemStmt->execute([$item_id]);
                    $itemData = $itemStmt->fetch(PDO::FETCH_ASSOC);

                    // Get old status for comparison
                    $old_status = $itemData['status'] ?? 'unknown';

                    // Update item status
                    $updateStmt = $pdo->prepare("UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?");
                    $updateStmt->execute([$status, $item_id]);

                    // Create notification for item owner
                    if ($itemData) {
                        $notificationStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $notificationTitle = "📋 Item Status Updated";
                        
                        if ($status === 'resolved') {
                            $notificationMessage = "Your item \"{$itemData['title']}\" has been marked as resolved by admin. Reason: {$reason}";
                            $notificationType = 'success';
                        } elseif ($status === 'claimed') {
                            $notificationMessage = "Your item \"{$itemData['title']}\" has been marked as claimed by admin. Reason: {$reason}";
                            $notificationType = 'warning';
                        } else {
                            $notificationMessage = "Your item \"{$itemData['title']}\" has been re-activated by admin. Reason: {$reason}";
                            $notificationType = 'info';
                        }
                        
                        $actionUrl = "../pages/item-details?id={$item_id}";
                        
                        $notificationStmt->execute([
                            $itemData['owner_id'],
                            $notificationTitle,
                            $notificationMessage,
                            $notificationType,
                            $actionUrl
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Item status updated successfully']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;

        case 'update_item_details':
            $item_id = $_POST['item_id'] ?? '';
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $location = $_POST['location'] ?? '';
            $reason = $_POST['reason'] ?? 'No reason provided';

            if ($item_id) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get item details and owner info before updating
                    $itemStmt = $pdo->prepare("
                        SELECT i.*, u.name as owner_name, u.id as owner_id 
                        FROM items i 
                        JOIN users u ON i.user_id = u.id 
                        WHERE i.id = ?
                    ");
                    $itemStmt->execute([$item_id]);
                    $itemData = $itemStmt->fetch(PDO::FETCH_ASSOC);

                    // Update item details
                    $updateStmt = $pdo->prepare("
                        UPDATE items 
                        SET title = ?, description = ?, category = ?, location = ?, updated_at = NOW() 
                        WHERE id = ?
                    ");
                    $updateStmt->execute([$title, $description, $category, $location, $item_id]);

                    // Create notification for item owner
                    if ($itemData) {
                        $notificationStmt = $pdo->prepare("
                            INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                            VALUES (?, ?, ?, ?, ?, NOW())
                        ");
                        
                        $notificationTitle = "✏️ Item Details Updated by Admin";
                        $notificationMessage = "Admin has updated the details of your item \"{$itemData['title']}\". Reason: {$reason}";
                        $notificationType = 'info';
                        $actionUrl = "../pages/item-details?id={$item_id}";
                        
                        $notificationStmt->execute([
                            $itemData['owner_id'],
                            $notificationTitle,
                            $notificationMessage,
                            $notificationType,
                            $actionUrl
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Item details updated successfully']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            }
            exit;

        case 'send_broadcast_notification':
            $title = $_POST['title'] ?? '';
            $message = $_POST['message'] ?? '';
            $type = $_POST['type'] ?? 'info';

            if ($title && $message) {
                try {
                    // Start transaction
                    $pdo->beginTransaction();

                    // Get all user IDs
                    $usersStmt = $pdo->prepare("SELECT id FROM users WHERE status = 'active'");
                    $usersStmt->execute();
                    $allUsers = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

                    // Insert notification for each user
                    $notificationStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, action_url, created_at) 
                        VALUES (?, ?, ?, ?, ?, NOW())
                    ");

                    foreach ($allUsers as $user_id_broadcast) {
                        $notificationStmt->execute([
                            $user_id_broadcast,
                            "📢 " . $title,
                            $message,
                            $type,
                            "../pages/dashboard"
                        ]);
                    }

                    $pdo->commit();
                    echo json_encode(['success' => true, 'message' => 'Broadcast notification sent to all users']);

                } catch (Exception $e) {
                    $pdo->rollBack();
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Title and message are required']);
            }
            exit;

        case 'get_claim_details':
            $claim_id = $_POST['claim_id'] ?? '';

            if ($claim_id) {
                try {
                    $stmt = $pdo->prepare("
                        SELECT 
                            c.*,
                            u.name as claimant_name,
                            u.email as claimant_email,
                            u.phone as claimant_phone,
                            i.title as item_title,
                            i.description as item_description,
                            i.category as item_category,
                            i.location as item_location,
                            i.date as item_date,
                            i.type as item_type,
                            i.image_url as item_image_url,
                            i.id as item_id,
                            owner.name as owner_name,
                            owner.email as owner_email,
                            owner.phone as owner_phone
                        FROM claims c
                        JOIN users u ON c.claimer_id = u.id
                        JOIN items i ON c.item_id = i.id
                        JOIN users owner ON i.user_id = owner.id
                        WHERE c.id = ?
                    ");

                    $stmt->execute([$claim_id]);
                    $claim = $stmt->fetch(PDO::FETCH_ASSOC);

                    if ($claim) {
                        echo json_encode(['success' => true, 'claim' => $claim]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Claim not found']);
                    }
                } catch (PDOException $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Claim ID is required']);
            }
            exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Campus Lost & Found</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .admin-sidebar {
            width: 280px;
            min-height: 100vh;
            transition: all 0.3s;
        }

        .admin-sidebar.collapsed {
            margin-left: -280px;
        }

        .admin-content {
            transition: all 0.3s;
        }

        .admin-content.expanded {
            margin-left: 0;
        }

        .nav-link.active {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
        }

        .nav-link:hover {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            background: #f8f9fa;
        }

        .dark-mode-toggle {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #0d6efd;
            color: white;
            border: none;
            box-shadow: 0 4px 15px rgba(13, 110, 253, 0.3);
        }

        .status-badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
        }

        .action-buttons .btn {
            margin: 0 2px;
        }

        .modal-lg {
            max-width: 900px;
        }
    </style>
</head>

<body>
    <div class="d-flex">
        <!-- Sidebar -->
        <div class="admin-sidebar bg-dark text-white" id="sidebar">
            <div class="p-4">
                <div class="d-flex align-items-center mb-4">
                    <i class="bi bi-shield-check text-primary fs-2 me-2"></i>
                    <h5 class="mb-0 fw-bold">Admin Panel</h5>
                </div>
                <hr class="bg-secondary">
                <ul class="nav flex-column">
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white active" href="admin" data-section="dashboard">
                            <i class="bi bi-speedometer2 me-2"></i>Dashboard
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="#" data-section="items">
                            <i class="bi bi-box-seam me-2"></i>Manage Items
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="#" data-section="users">
                            <i class="bi bi-people me-2"></i>Manage Users
                        </a>
                    </li>
                    <li class="nav-item mb-2">
                        <a class="nav-link text-white" href="#" data-section="claims">
                            <i class="bi bi-clipboard-check me-2"></i>View Claims
                        </a>
                    </li>
                </ul>
                <hr class="bg-secondary">
                <a href="../" class="btn btn-outline-light w-100 mb-2">
                    <i class="bi bi-house me-2"></i>Back to Site
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="flex-grow-1">
            <!-- Top Navbar -->
            <nav class="navbar navbar-light bg-white shadow-sm">
                <div class="container-fluid px-4">
                    <button class="btn btn-outline-secondary" id="sidebarToggle">
                        <i class="bi bi-list"></i>
                    </button>
                    <div class="d-flex align-items-center">
                        <span class="me-3">Welcome, <?php echo htmlspecialchars($user_name); ?> (Admin)</span>
                        <a href="../logout" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right me-1"></i>Logout
                        </a>
                    </div>
                </div>
            </nav>

            <!-- Dashboard Section -->
            <div class="admin-content p-4" id="dashboardSection">
                <h2 class="fw-bold mb-4">Dashboard Overview</h2>

                <div class="row g-4 mb-4">
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-box-seam text-primary fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminTotalItems"><?php echo $stats['total_items'] ?? 0; ?>
                                </h3>
                                <p class="text-muted mb-0">Total Items</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-people text-success fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminTotalUsers"><?php echo $stats['total_users'] ?? 0; ?>
                                </h3>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-clipboard-check text-warning fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminTotalClaims">
                                    <?php echo $stats['total_claims'] ?? 0; ?>
                                </h3>
                                <p class="text-muted mb-0">Total Claims</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-check-circle text-info fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminResolvedItems">
                                    <?php echo $stats['resolved_items'] ?? 0; ?>
                                </h3>
                                <p class="text-muted mb-0">Resolved Items</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Recent Activity</h5>
                                <ul class="list-group list-group-flush" id="recentActivity">
                                    <?php if (empty($recentActivity)): ?>
                                        <li class="list-group-item text-center text-muted py-4">
                                            <i class="bi bi-inbox fs-1"></i>
                                            <p class="mt-2 mb-0">No recent activity</p>
                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($recentActivity as $activity): ?>
                                            <li class="list-group-item d-flex justify-content-between align-items-center">
                                                <div>
                                                    <strong><?php echo htmlspecialchars($activity['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">
                                                        Posted by <?php echo htmlspecialchars($activity['user_name']); ?>
                                                        •
                                                        <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <span
                                                    class="badge <?php echo $activity['type'] === 'lost' ? 'bg-danger' : 'bg-success'; ?> status-badge">
                                                    <?php echo ucfirst($activity['type']); ?>
                                                </span>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4">
                                <h5 class="fw-bold mb-3">Pending Claims</h5>
                                <ul class="list-group list-group-flush" id="pendingClaimsList">
                                    <?php if (empty($pendingClaims)): ?>
                                        <li class="list-group-item text-center text-muted py-4">
                                            <i class="bi bi-check-circle fs-1"></i>
                                            <p class="mt-2 mb-0">No pending claims</p>
                                        </li>
                                    <?php else: ?>
                                        <?php foreach ($pendingClaims as $claim): ?>
                                            <li class="list-group-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <strong><?php echo htmlspecialchars($claim['item_title']); ?></strong>
                                                        <br>
                                                        <small class="text-muted">
                                                            Claim by <?php echo htmlspecialchars($claim['claimant_name']); ?>
                                                            • <?php echo date('M j, Y', strtotime($claim['created_at'])); ?>
                                                        </small>
                                                    </div>
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewClaimDetails(<?php echo $claim['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Items Section -->
            <div class="admin-content p-4 d-none" id="itemsSection">
                <h2 class="fw-bold mb-4">Manage Items</h2>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Image</th>
                                        <th>Item Name</th>
                                        <th>Category</th>
                                        <th>Location</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminItemsTable">
                                    <?php if (empty($allItems)): ?>
                                        <tr>
                                            <td colspan="8" class="text-center py-4 text-muted">
                                                <i class="bi bi-inbox fs-1"></i>
                                                <p class="mt-2 mb-0">No items found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($allItems as $item): ?>
                                            <tr>
                                                <td><?php echo $item['id']; ?></td>
                                                <td>
                                                    <?php if ($item['image_url']): ?>
                                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>"
                                                            alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                            style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                                            onerror="this.src='../images/home.jpg'">
                                                    <?php else: ?>
                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center"
                                                            style="width: 50px; height: 50px;">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                    <br>
                                                    <small class="text-muted">by
                                                        <?php echo htmlspecialchars($item['user_name']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>
                                                <td>
                                                    <select class="form-select form-select-sm status-select"
                                                        data-item-id="<?php echo $item['id']; ?>" style="width: 120px;">
                                                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="resolved" <?php echo $item['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewItem(<?php echo $item['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger"
                                                        onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Manage Users Section -->
            <div class="admin-content p-4 d-none" id="usersSection">
                <h2 class="fw-bold mb-4">Manage Users</h2>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Name</th>
                                        <th>Email</th>
                                        <th>Joined Date</th>
                                        <th>Items Posted</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminUsersTable">
                                    <?php if (empty($allUsers)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-people fs-1"></i>
                                                <p class="mt-2 mb-0">No users found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($allUsers as $user): ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                                    <?php if ($user['role'] === 'admin'): ?>
                                                        <span class="badge bg-primary status-badge ms-1">Admin</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                                <td><?php echo $user['items_count']; ?></td>
                                                <td>
                                                    <select class="form-select form-select-sm user-status-select"
                                                        data-user-id="<?php echo $user['id']; ?>" style="width: 120px;">
                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewUser(<?php echo $user['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info"
                                                        onclick="sendMessage('<?php echo htmlspecialchars($user['email']); ?>')">
                                                        <i class="bi bi-envelope"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- View Claims Section -->
            <div class="admin-content p-4 d-none" id="claimsSection">
                <h2 class="fw-bold mb-4">View Claims</h2>
                <div class="card border-0 shadow-sm">
                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>No</sup></th>
                                        <th>Item Name</th>
                                        <th>Claimant</th>
                                        <th>Email</th>
                                        <th>Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="adminClaimsTable">
                                    <?php if (empty($allClaims)): ?>
                                        <tr>
                                            <td colspan="7" class="text-center py-4 text-muted">
                                                <i class="bi bi-clipboard-check fs-1"></i>
                                                <p class="mt-2 mb-0">No claims found</p>
                                            </td>
                                        </tr>
                                    <?php else: ?>
                                        <?php $counter = 1; ?>
                                        <?php foreach ($allClaims as $claim): ?>
                                            <tr>
                                                <td><?php echo $counter; ?></td>
                                                <td><?php echo htmlspecialchars($claim['item_title']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['claimant_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['claimant_email']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($claim['created_at'])); ?></td>
                                                <td>
                                                    <select class="form-select form-select-sm claim-status-select"
                                                        data-claim-id="<?php echo $claim['id']; ?>" style="width: 120px;">
                                                        <option value="pending" <?php echo $claim['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo $claim['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="resolved" <?php echo $claim['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                        <option value="rejected" <?php echo $claim['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary"
                                                        onclick="viewClaimDetails(<?php echo $claim['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info"
                                                        onclick="contactClaimant('<?php echo htmlspecialchars($claim['claimant_email']); ?>')">
                                                        <i class="bi bi-envelope"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php $counter++; ?>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Claim Details Modal -->
    <div class="modal fade" id="claimDetailsModal" tabindex="-1" aria-labelledby="claimDetailsModalLabel"
        aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title fw-bold" id="claimDetailsModalLabel">
                        <i class="bi bi-clipboard-check me-2"></i>Claim Details
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"
                        aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <!-- Claim Information -->
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-info-circle me-2"></i>Claim Information
                                    </h6>

                                    <div class="mb-3" hidden>
                                        <label class="form-label fw-semibold small text-muted mb-1">Claim ID</label>
                                        <p class="mb-0 fw-bold" id="modalClaimId">-</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Claim Status</label>
                                        <div>
                                            <span class="badge bg-warning" id="modalClaimStatus">Pending</span>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Claim Date</label>
                                        <p class="mb-0" id="modalClaimDate">-</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Last Updated</label>
                                        <p class="mb-0" id="modalClaimUpdated">-</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Claimant Information -->
                        <div class="col-md-6">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-person me-2"></i>Claimant Information
                                    </h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Full Name</label>
                                        <p class="mb-0 fw-bold" id="modalClaimantName">-</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Email
                                            Address</label>
                                        <p class="mb-0" id="modalClaimantEmail">-</p>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Contact</label>
                                        <p class="mb-0" id="modalClaimantPhone">-</p>
                                    </div>

                                    <div class="mt-3">
                                        <button class="btn btn-outline-primary btn-sm me-2"
                                            onclick="contactClaimantModal()">
                                            <i class="bi bi-envelope me-1"></i>Email
                                        </button>
                                        <button class="btn btn-outline-success btn-sm" onclick="callClaimant()">
                                            <i class="bi bi-telephone me-1"></i>Call
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Item Information -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-box me-2"></i>Item Information
                                    </h6>

                                    <div class="row">
                                        <div class="col-md-3 text-center">
                                            <img id="modalItemImage" src="../images/home.jpg" alt="Item Image"
                                                class="img-fluid rounded-3 mb-3"
                                                style="max-height: 150px; object-fit: cover;">
                                            <div>
                                                <span class="badge bg-info" id="modalItemType">Found</span>
                                            </div>
                                        </div>
                                        <div class="col-md-9">
                                            <div class="row">
                                                <div class="col-md-6 mb-2">
                                                    <label class="form-label fw-semibold small text-muted mb-1">Item
                                                        Name</label>
                                                    <p class="mb-0 fw-bold" id="modalItemTitle">-</p>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label
                                                        class="form-label fw-semibold small text-muted mb-1">Category</label>
                                                    <p class="mb-0" id="modalItemCategory">-</p>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label
                                                        class="form-label fw-semibold small text-muted mb-1">Location</label>
                                                    <p class="mb-0" id="modalItemLocation">-</p>
                                                </div>
                                                <div class="col-md-6 mb-2">
                                                    <label
                                                        class="form-label fw-semibold small text-muted mb-1">Date</label>
                                                    <p class="mb-0" id="modalItemDate">-</p>
                                                </div>
                                                <div class="col-12 mb-2">
                                                    <label
                                                        class="form-label fw-semibold small text-muted mb-1">Description</label>
                                                    <p class="mb-0" id="modalItemDescription">-</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Proof of Ownership -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-0 bg-light">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-shield-check me-2"></i>Proof of Ownership
                                    </h6>

                                    <div class="mb-3">
                                        <label class="form-label fw-semibold small text-muted mb-1">Claim
                                            Message</label>
                                        <div class="bg-white rounded p-3 border">
                                            <p class="mb-0" id="modalClaimMessage">-</p>
                                        </div>
                                    </div>

                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle me-2"></i>
                                        <small>
                                            The claimant has provided this information to prove ownership of the item.
                                            Please verify the details before approving or rejecting the claim.
                                        </small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Quick Actions -->
                    <div class="row mt-4">
                        <div class="col-12">
                            <div class="card border-0">
                                <div class="card-body">
                                    <h6 class="fw-bold text-primary mb-3">
                                        <i class="bi bi-lightning me-2"></i>Quick Actions
                                    </h6>

                                    <div class="d-flex gap-2 flex-wrap">
                                        <button class="btn btn-success" onclick="approveClaim()" id="approveBtn">
                                            <i class="bi bi-check-circle me-2"></i>Approve Claim
                                        </button>
                                        <button class="btn btn-danger" onclick="rejectClaim()" id="rejectBtn">
                                            <i class="bi bi-x-circle me-2"></i>Reject Claim
                                        </button>
                                        <button class="btn btn-outline-secondary" onclick="contactItemOwner()">
                                            <i class="bi bi-telephone-outbound me-2"></i>Contact Item Owner
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="bi bi-x-circle me-2"></i>Close
                    </button>
                    <button type="button" class="btn btn-primary" onclick="saveClaimChanges()">
                        <i class="bi bi-save me-2"></i>Save Changes
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="bi bi-moon-fill"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function () {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        });

        // Section navigation
        document.querySelectorAll('[data-section]').forEach(link => {
            link.addEventListener('click', function (e) {
                e.preventDefault();

                // Remove active class from all links
                document.querySelectorAll('[data-section]').forEach(l => {
                    l.classList.remove('active');
                });

                // Add active class to clicked link
                this.classList.add('active');

                // Hide all sections
                document.querySelectorAll('.admin-content').forEach(section => {
                    section.classList.add('d-none');
                });

                // Show selected section
                const sectionId = this.getAttribute('data-section') + 'Section';
                document.getElementById(sectionId).classList.remove('d-none');
            });
        });

        // Item status update
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function () {
                const itemId = this.getAttribute('data-item-id');
                const status = this.value;

                updateItemStatus(itemId, status);
            });
        });

        // User status update
        document.querySelectorAll('.user-status-select').forEach(select => {
            select.addEventListener('change', function () {
                const userId = this.getAttribute('data-user-id');
                const status = this.value;

                updateUserStatus(userId, status);
            });
        });

        // Claim status update
        document.querySelectorAll('.claim-status-select').forEach(select => {
            select.addEventListener('change', function () {
                const claimId = this.getAttribute('data-claim-id');
                const status = this.value;

                updateClaimStatus(claimId, status);
            });
        });

        // AJAX functions
        // AJAX functions
        function updateItemStatus(itemId, status) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_item_status&item_id=${itemId}&status=${status}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Item status updated successfully', 'success');
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating item status', 'error');
                });
        }
        function updateUserStatus(userId, status) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_user_status&user_id=${userId}&status=${status}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('User status updated successfully', 'success');
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating user status', 'error');
                });
        }

        function updateClaimStatus(claimId, status) {
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_claim_status&claim_id=${claimId}&status=${status}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert('Claim status updated successfully', 'success');
                        // Refresh the page to update the tables
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error updating claim status', 'error');
                });
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_item&item_id=${itemId}`
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            showAlert('Item deleted successfully', 'success');
                            setTimeout(() => location.reload(), 1000);
                        } else {
                            showAlert(data.message, 'error');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showAlert('Error deleting item', 'error');
                    });
            }
        }

        // Claim Details Modal Functions
        function viewClaimDetails(claimId) {
            // Show loading state
            const modal = new bootstrap.Modal(document.getElementById('claimDetailsModal'));
            modal.show();

            // Fetch claim details via AJAX
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_claim_details&claim_id=${claimId}`
            })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        populateClaimModal(data.claim);
                    } else {
                        showAlert('Error loading claim details: ' + data.message, 'error');
                        modal.hide();
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error loading claim details', 'error');
                    modal.hide();
                });
        }

        // Function to populate modal with claim data
        function populateClaimModal(claim) {
            // Claim Information
            document.getElementById('modalClaimId').textContent = claim.id;
            document.getElementById('modalClaimStatus').textContent = claim.status.charAt(0).toUpperCase() + claim.status.slice(1);
            document.getElementById('modalClaimStatus').className = `badge ${getStatusBadgeClass(claim.status)}`;
            document.getElementById('modalClaimDate').textContent = formatDate(claim.created_at);
            document.getElementById('modalClaimUpdated').textContent = formatDate(claim.updated_at);

            // Claimant Information
            document.getElementById('modalClaimantName').textContent = claim.claimant_name;
            document.getElementById('modalClaimantEmail').textContent = claim.claimant_email;
            document.getElementById('modalClaimantPhone').textContent = claim.claimant_phone || 'Not provided';

            // Item Information
            document.getElementById('modalItemTitle').textContent = claim.item_title;
            document.getElementById('modalItemCategory').textContent = claim.item_category;
            document.getElementById('modalItemLocation').textContent = claim.item_location;
            document.getElementById('modalItemDate').textContent = formatDate(claim.item_date);
            document.getElementById('modalItemDescription').textContent = claim.item_description;
            document.getElementById('modalItemType').textContent = claim.item_type.charAt(0).toUpperCase() + claim.item_type.slice(1);
            document.getElementById('modalItemType').className = `badge ${claim.item_type === 'lost' ? 'bg-danger' : 'bg-success'}`;

            // Item Image
            const itemImage = document.getElementById('modalItemImage');
            if (claim.item_image_url) {
                itemImage.src = '../' + claim.item_image_url;
            } else {
                itemImage.src = '../images/home.jpg';
            }

            // Proof of Ownership
            document.getElementById('modalClaimMessage').textContent = claim.message || 'No additional proof provided.';

            // Store current claim ID for actions
            document.getElementById('claimDetailsModal').setAttribute('data-current-claim-id', claim.id);
            document.getElementById('claimDetailsModal').setAttribute('data-current-claimant-email', claim.claimant_email);
            document.getElementById('claimDetailsModal').setAttribute('data-current-item-id', claim.item_id);
            document.getElementById('claimDetailsModal').setAttribute('data-current-claimant-phone', claim.claimant_phone || '');

            // Update action buttons based on status
            updateActionButtons(claim.status);
        }

        // Helper function to get badge class based on status
        function getStatusBadgeClass(status) {
            switch (status) {
                case 'pending': return 'bg-warning';
                case 'approved': return 'bg-success';
                case 'rejected': return 'bg-danger';
                default: return 'bg-secondary';
            }
        }

        // Helper function to format date
        function formatDate(dateString) {
            const date = new Date(dateString);
            return date.toLocaleDateString('en-US', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
        }

        // Update action buttons based on claim status
        function updateActionButtons(status) {
            const approveBtn = document.getElementById('approveBtn');
            const rejectBtn = document.getElementById('rejectBtn');

            if (status === 'approved') {
                approveBtn.disabled = true;
                approveBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Approved';
                approveBtn.className = 'btn btn-success';
            } else if (status === 'rejected') {
                rejectBtn.disabled = true;
                rejectBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Rejected';
                rejectBtn.className = 'btn btn-danger';
            } else {
                approveBtn.disabled = false;
                approveBtn.innerHTML = '<i class="bi bi-check-circle me-2"></i>Approve Claim';
                approveBtn.className = 'btn btn-success';

                rejectBtn.disabled = false;
                rejectBtn.innerHTML = '<i class="bi bi-x-circle me-2"></i>Reject Claim';
                rejectBtn.className = 'btn btn-danger';
            }
        }

        // Action functions for claim modal
        function approveClaim() {
            const claimId = document.getElementById('claimDetailsModal').getAttribute('data-current-claim-id');
            if (confirm('Are you sure you want to approve this claim? This will notify the claimant.')) {
                updateClaimStatus(claimId, 'approved');
                const modal = bootstrap.Modal.getInstance(document.getElementById('claimDetailsModal'));
                modal.hide();
            }
        }

        function rejectClaim() {
            const claimId = document.getElementById('claimDetailsModal').getAttribute('data-current-claim-id');
            if (confirm('Are you sure you want to reject this claim? This will notify the claimant.')) {
                updateClaimStatus(claimId, 'rejected');
                const modal = bootstrap.Modal.getInstance(document.getElementById('claimDetailsModal'));
                modal.hide();
            }
        }

        function contactClaimantModal() {
            const email = document.getElementById('claimDetailsModal').getAttribute('data-current-claimant-email');
            window.location.href = `mailto:${email}?subject=Regarding Your Claim&body=Dear Claimant,%0D%0A%0D%0A`;
        }

        function callClaimant() {
            const phone = document.getElementById('claimDetailsModal').getAttribute('data-current-claimant-phone');
            if (phone && phone !== 'Not provided' && phone !== '') {
                window.location.href = `tel:${phone}`;
            } else {
                alert('Phone number not available for this claimant.');
            }
        }

        function contactItemOwner() {
            // You would need to fetch item owner details here
            alert('Feature to contact item owner - would open email composer');
        }

        function viewFullItemDetails() {
            const itemId = document.getElementById('claimDetailsModal').getAttribute('data-current-item-id');
            window.open(`../pages/item-details?id=${itemId}`, '_blank');
        }

        function saveClaimChanges() {
            // This could be used if you add editable fields in the modal
            alert('Changes saved successfully!');
            const modal = bootstrap.Modal.getInstance(document.getElementById('claimDetailsModal'));
            modal.hide();
        }

        // Utility functions
        function viewItem(itemId) {
            window.open(`../pages/item-details?id=${itemId}`, '_blank');
        }

        function viewUser(userId) {
            alert(`View user details for ID: ${userId}`);
        }

        function sendMessage(email) {
            window.location.href = `mailto:${email}`;
        }

        function contactClaimant(email) {
            window.location.href = `mailto:${email}`;
        }

        function showAlert(message, type) {
            const alertClass = type === 'success' ? 'alert-success' : 'alert-danger';
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            alertDiv.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            alertDiv.innerHTML = `
                <i class="bi ${type === 'success' ? 'bi-check-circle' : 'bi-exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;

            document.body.appendChild(alertDiv);

            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }

        // Dark mode toggle
        document.getElementById('darkModeToggle').addEventListener('click', function () {
            document.body.classList.toggle('dark-mode');
            const icon = this.querySelector('i');
            if (document.body.classList.contains('dark-mode')) {
                icon.className = 'bi bi-sun-fill';
            } else {
                icon.className = 'bi bi-moon-fill';
            }
        });

        // Auto-refresh dashboard every 30 seconds
        setInterval(() => {
            if (!document.getElementById('dashboardSection').classList.contains('d-none')) {
                // You can add AJAX call here to refresh dashboard data
                console.log('Dashboard auto-refresh');
            }
        }, 30000);
    </script>
</body>

</html>