<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../pages/login');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Handle CRUD operations via POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    switch ($action) {
        case 'create_item':
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $location = $_POST['location'] ?? '';
            $date = $_POST['date'] ?? '';
            $type = $_POST['type'] ?? 'lost';
            
            if ($title && $description && $category && $location && $date) {
                try {
                    // Handle file upload
                    $image_url = '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/items/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            $image_url = 'uploads/items/' . $filename;
                        }
                    }
                    
                    // Insert item
                    $stmt = $pdo->prepare("
                        INSERT INTO items (user_id, title, description, category, location, date, type, image_url, status, created_at, updated_at) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', NOW(), NOW())
                    ");
                    $stmt->execute([$user_id, $title, $description, $category, $location, $date, $type, $image_url]);
                    
                    echo json_encode(['success' => true, 'message' => 'Item created successfully!']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error creating item: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
            }
            exit;

        case 'update_item':
            $item_id = $_POST['item_id'] ?? '';
            $title = $_POST['title'] ?? '';
            $description = $_POST['description'] ?? '';
            $category = $_POST['category'] ?? '';
            $location = $_POST['location'] ?? '';
            $date = $_POST['date'] ?? '';
            
            if ($item_id && $title && $description && $category && $location && $date) {
                try {
                    // Handle file upload if new image provided
                    $image_url = $_POST['current_image'] ?? '';
                    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
                        $upload_dir = '../uploads/items/';
                        if (!is_dir($upload_dir)) {
                            mkdir($upload_dir, 0777, true);
                        }
                        
                        $file_extension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
                        $filename = uniqid() . '.' . $file_extension;
                        $target_path = $upload_dir . $filename;
                        
                        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
                            // Delete old image if exists
                            if ($image_url && file_exists('../' . $image_url)) {
                                unlink('../' . $image_url);
                            }
                            $image_url = 'uploads/items/' . $filename;
                        }
                    }
                    
                    // Update item
                    $stmt = $pdo->prepare("
                        UPDATE items 
                        SET title = ?, description = ?, category = ?, location = ?, date = ?, image_url = ?, updated_at = NOW() 
                        WHERE id = ? AND user_id = ?
                    ");
                    $stmt->execute([$title, $description, $category, $location, $date, $image_url, $item_id, $user_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Item updated successfully!']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating item: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'All fields are required']);
            }
            exit;

        case 'delete_item':
            $item_id = $_POST['item_id'] ?? '';
            
            if ($item_id) {
                try {
                    // Get item details to delete image
                    $itemStmt = $pdo->prepare("SELECT image_url FROM items WHERE id = ? AND user_id = ?");
                    $itemStmt->execute([$item_id, $user_id]);
                    $item = $itemStmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Delete image file if exists
                    if ($item && $item['image_url'] && file_exists('../' . $item['image_url'])) {
                        unlink('../' . $item['image_url']);
                    }
                    
                    // Delete related claims first
                    $deleteClaimsStmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ?");
                    $deleteClaimsStmt->execute([$item_id]);
                    
                    // Delete item
                    $deleteStmt = $pdo->prepare("DELETE FROM items WHERE id = ? AND user_id = ?");
                    $deleteStmt->execute([$item_id, $user_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Item deleted successfully!']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error deleting item: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
            }
            exit;

        case 'update_profile':
            $name = $_POST['name'] ?? '';
            $phone = $_POST['phone'] ?? '';
            
            if ($name) {
                try {
                    $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                    $stmt->execute([$name, $phone, $user_id]);
                    
                    // Update session
                    $_SESSION['user_name'] = $name;
                    
                    echo json_encode(['success' => true, 'message' => 'Profile updated successfully!']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error updating profile: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Name is required']);
            }
            exit;

        case 'change_password':
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';
            
            if ($current_password && $new_password && $confirm_password) {
                if ($new_password !== $confirm_password) {
                    echo json_encode(['success' => false, 'message' => 'New passwords do not match']);
                    exit;
                }
                
                try {
                    // Verify current password
                    $userStmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                    $userStmt->execute([$user_id]);
                    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($user && password_verify($current_password, $user['password'])) {
                        // Update password
                        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                        $updateStmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                        $updateStmt->execute([$hashed_password, $user_id]);
                        
                        echo json_encode(['success' => true, 'message' => 'Password changed successfully!']);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Current password is incorrect']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error changing password: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'All password fields are required']);
            }
            exit;

        case 'cancel_claim':
            $claim_id = $_POST['claim_id'] ?? '';
            
            if ($claim_id) {
                try {
                    $stmt = $pdo->prepare("DELETE FROM claims WHERE id = ? AND claimer_id = ?");
                    $stmt->execute([$claim_id, $user_id]);
                    
                    echo json_encode(['success' => true, 'message' => 'Claim cancelled successfully!']);
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Error cancelling claim: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid claim ID']);
            }
            exit;

        case 'get_item_details':
            $item_id = $_POST['item_id'] ?? '';
            
            if ($item_id) {
                try {
                    $stmt = $pdo->prepare("SELECT * FROM items WHERE id = ? AND user_id = ?");
                    $stmt->execute([$item_id, $user_id]);
                    $item = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($item) {
                        echo json_encode(['success' => true, 'item' => $item]);
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Item not found']);
                    }
                } catch (Exception $e) {
                    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
                }
            } else {
                echo json_encode(['success' => false, 'message' => 'Item ID is required']);
            }
            exit;
    }
}

// Get claimed status for user's items
$claimedItemsStmt = $pdo->prepare("
    SELECT i.id, COUNT(c.id) as claim_count 
    FROM items i 
    LEFT JOIN claims c ON i.id = c.item_id AND c.status = 'approved'
    WHERE i.user_id = ? 
    GROUP BY i.id
");
$claimedItemsStmt->execute([$user_id]);
$claimedItems = $claimedItemsStmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Get notifications for user
$notificationsStmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? AND is_read = 0 
    ORDER BY created_at DESC 
    LIMIT 10
");
$notificationsStmt->execute([$user_id]);
$notifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Get unread notification count
$unreadCountStmt = $pdo->prepare("
    SELECT COUNT(*) as unread_count FROM notifications 
    WHERE user_id = ? AND is_read = 0
");
$unreadCountStmt->execute([$user_id]);
$unreadCount = $unreadCountStmt->fetch(PDO::FETCH_ASSOC);

// Add this function before the HTML
function time_elapsed_string($datetime, $full = false)
{
    $now = new DateTime;
    $ago = new DateTime($datetime);
    $diff = $now->diff($ago);

    $diff->w = floor($diff->d / 7);
    $diff->d -= $diff->w * 7;

    $string = array(
        'y' => 'year',
        'm' => 'month',
        'w' => 'week',
        'd' => 'day',
        'h' => 'hour',
        'i' => 'minute',
        's' => 'second',
    );

    foreach ($string as $k => &$v) {
        if ($diff->$k) {
            $v = $diff->$k . ' ' . $v . ($diff->$k > 1 ? 's' : '');
        } else {
            unset($string[$k]);
        }
    }

    if (!$full)
        $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
// Get user profile information
$profileStmt = $pdo->prepare("SELECT phone, created_at FROM users WHERE id = ?");
$profileStmt->execute([$user_id]);
$profile = $profileStmt->fetch(PDO::FETCH_ASSOC);

// Get dashboard statistics
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_items,
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active_items,
        SUM(CASE WHEN status = 'resolved' THEN 1 ELSE 0 END) as resolved_items
    FROM items 
    WHERE user_id = ?
");
$statsStmt->execute([$user_id]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

// Get pending claims count
$claimsStmt = $pdo->prepare("
    SELECT COUNT(*) as pending_claims 
    FROM claims 
    WHERE claimer_id = ? AND status = 'pending'
");
$claimsStmt->execute([$user_id]);
$pendingClaims = $claimsStmt->fetch(PDO::FETCH_ASSOC);

// Get lost items
$lostStmt = $pdo->prepare("
    SELECT * FROM items 
    WHERE user_id = ? AND type = 'lost' 
    ORDER BY created_at DESC
");
$lostStmt->execute([$user_id]);
$lostItems = $lostStmt->fetchAll(PDO::FETCH_ASSOC);

// Get found items
$foundStmt = $pdo->prepare("
    SELECT * FROM items 
    WHERE user_id = ? AND type = 'found' 
    ORDER BY created_at DESC
");
$foundStmt->execute([$user_id]);
$foundItems = $foundStmt->fetchAll(PDO::FETCH_ASSOC);

// Get user claims
$userClaimsStmt = $pdo->prepare("
    SELECT c.*, i.title, i.type, i.image_url, u.name as owner_name, u.email as owner_email, u.phone as owner_phone
    FROM claims c
    JOIN items i ON c.item_id = i.id
    JOIN users u ON i.user_id = u.id
    WHERE c.claimer_id = ?
    ORDER BY c.created_at DESC
");
$userClaimsStmt->execute([$user_id]);
$userClaims = $userClaimsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Campus Lost & Found</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
        }

        .nav-tabs .nav-link {
            border: none;
            color: #6c757d;
            font-weight: 500;
            padding: 1rem 1.5rem;
        }

        .nav-tabs .nav-link.active {
            color: #0d6efd;
            border-bottom: 3px solid #0d6efd;
            background: transparent;
        }

        .table th {
            border-top: none;
            font-weight: 600;
            color: #495057;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge {
            font-size: 0.75rem;
            padding: 0.35em 0.65em;
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
            transition: all 0.3s ease;
        }

        .dark-mode-toggle:hover {
            transform: scale(1.1);
            box-shadow: 0 6px 20px rgba(13, 110, 253, 0.4);
        }

        .form-control-static {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 0.75rem;
            border: 1px solid #e9ecef;
        }

        .notification-badge {
            font-size: 0.6rem;
        }
    </style>
</head>

<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm sticky-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../">
                <i class="bi bi-search-heart-fill text-primary fs-3 me-2"></i>
                <span class="fw-bold">Campus Lost & Found</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a href="../pages/search" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="bi bi-search"></i> Browse Items
                        </a>
                    </li>
                    <!-- Notification Bell -->
                    <li class="nav-item dropdown me-2">
                        <a class="nav-link position-relative" href="#" id="notificationDropdown" role="button"
                            data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-bell fs-5 text-dark"></i>
                            <?php if ($unreadCount['unread_count'] > 0): ?>
                                <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger notification-badge">
                                    <?php echo $unreadCount['unread_count']; ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end shadow" aria-labelledby="notificationDropdown"
                            style="min-width: 300px; max-height: 400px; overflow-y: auto;">
                            <li>
                                <h6 class="dropdown-header fw-bold">Notifications</h6>
                            </li>
                            <?php if (empty($notifications)): ?>
                                <li><a class="dropdown-item text-muted text-center py-3">No new notifications</a></li>
                            <?php else: ?>
                                <?php foreach ($notifications as $notification): ?>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex w-100 justify-content-between">
                                                <small class="text-primary fw-semibold"><?php echo htmlspecialchars($notification['title']); ?></small>
                                                <small class="text-muted"><?php echo time_elapsed_string($notification['created_at']); ?></small>
                                            </div>
                                            <small class="text-muted"><?php echo htmlspecialchars($notification['message']); ?></small>
                                        </a>
                                    </li>
                                    <li>
                                        <hr class="dropdown-divider">
                                    </li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <li><a class="dropdown-item text-center text-primary small"
                                    href="../pages/notifications">View All</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a href="../logout" class="btn btn-outline-primary btn-sm ms-2">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Dashboard Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="fw-bold mb-2">My Dashboard</h1>
                    <p class="text-muted mb-0">Welcome back, <span
                            id="userName"><?php echo htmlspecialchars($user_name); ?></span>! Here's your activity
                        overview.</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <button class="btn btn-primary" onclick="addNewItem()">
                        <i class="bi bi-plus-circle me-2"></i>Post New Item
                    </button>
                </div>
            </div>
        </div>
    </section>

    <!-- Stats Section -->
    <section class="py-4">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-4">
                            <div class="bg-primary bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-clock-history text-primary fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-primary mb-1" id="totalItems">
                                <?php echo $stats['total_items'] ?? 0; ?>
                            </h3>
                            <p class="text-muted mb-0">Total Items</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-4">
                            <div class="bg-success bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-check-circle text-success fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-success mb-1" id="activeItems">
                                <?php echo $stats['active_items'] ?? 0; ?>
                            </h3>
                            <p class="text-muted mb-0">Active Items</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-4">
                            <div class="bg-warning bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-hand-thumbs-up text-warning fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-warning mb-1" id="pendingClaims">
                                <?php echo $pendingClaims['pending_claims'] ?? 0; ?>
                            </h3>
                            <p class="text-muted mb-0">Pending Claims</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center">
                        <div class="card-body py-4">
                            <div class="bg-info bg-opacity-10 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-arrow-left-right text-info fs-4"></i>
                            </div>
                            <h3 class="fw-bold text-info mb-1" id="resolvedItems">
                                <?php echo $stats['resolved_items'] ?? 0; ?>
                            </h3>
                            <p class="text-muted mb-0">Resolved Items</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Main Content -->
    <section class="py-4">
        <div class="container">
            <div class="row">
                <!-- Tabs Navigation -->
                <div class="col-12">
                    <ul class="nav nav-tabs" id="dashboardTabs" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="lost-tab" data-bs-toggle="tab" data-bs-target="#lost"
                                type="button" role="tab">
                                <i class="bi bi-search me-2"></i>My Lost Items
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="found-tab" data-bs-toggle="tab" data-bs-target="#found"
                                type="button" role="tab">
                                <i class="bi bi-plus-circle me-2"></i>My Found Items
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="claims-tab" data-bs-toggle="tab" data-bs-target="#claims"
                                type="button" role="tab">
                                <i class="bi bi-hand-thumbs-up me-2"></i>My Claims
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile"
                                type="button" role="tab">
                                <i class="bi bi-person me-2"></i>Profile
                            </button>
                        </li>
                    </ul>

                    <div class="tab-content" id="dashboardTabsContent">
                        <!-- Lost Items Tab -->
                        <div class="tab-pane fade show active" id="lost" role="tabpanel">
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="card-title mb-0">My Lost Items</h5>
                                        <span class="badge bg-primary"
                                            id="lostItemsCount"><?php echo count($lostItems); ?> items</span>
                                    </div>

                                    <?php if (empty($lostItems)): ?>
                                        <div id="noLostItems" class="text-center py-5">
                                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3 text-muted">No Lost Items</h5>
                                            <p class="text-muted">You haven't reported any lost items yet.</p>
                                            <button class="btn btn-primary" onclick="addNewItem()">
                                                <i class="bi bi-plus-circle me-2"></i>Report Lost Item
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Category</th>
                                                        <th>Location</th>
                                                        <th>Date Lost</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="lostItemsTable">
                                                    <?php foreach ($lostItems as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php if ($item['image_url']): ?>
                                                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>"
                                                                            alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                                            class="rounded me-3"
                                                                            style="width: 50px; height: 50px; object-fit: cover;"
                                                                            onerror="this.src='../images/home.jpg'">
                                                                    <?php else: ?>
                                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-3"
                                                                            style="width: 50px; height: 50px;">
                                                                            <i class="bi bi-image text-muted"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                                        <br>
                                                                        <small
                                                                            class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>
                                                            <td>
                                                                <span
                                                                    class="badge <?php echo $item['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <?php echo ucfirst($item['status']); ?>
                                                                </span>
                                                                <?php if (isset($claimedItems[$item['id']]) && $claimedItems[$item['id']] > 0): ?>
                                                                    <br>
                                                                    <small class="text-success mt-1">
                                                                        <i class="bi bi-check-circle-fill"></i> Claimed
                                                                    </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>

                                                                <button class="btn btn-sm btn-outline-warning"
                                                                    onclick="editItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger"
                                                                    onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Found Items Tab -->
                        <div class="tab-pane fade" id="found" role="tabpanel">
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="card-title mb-0">My Found Items</h5>
                                        <span class="badge bg-success"
                                            id="foundItemsCount"><?php echo count($foundItems); ?> items</span>
                                    </div>

                                    <?php if (empty($foundItems)): ?>
                                        <div id="noFoundItems" class="text-center py-5">
                                            <i class="bi bi-inbox text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3 text-muted">No Found Items</h5>
                                            <p class="text-muted">You haven't posted any found items yet.</p>
                                            <button class="btn btn-primary" onclick="addNewItem()">
                                                <i class="bi bi-plus-circle me-2"></i>Post Found Item
                                            </button>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Category</th>
                                                        <th>Location</th>
                                                        <th>Date Found</th>
                                                        <th>Status</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="foundItemsTable">
                                                    <?php foreach ($foundItems as $item): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php if ($item['image_url']): ?>
                                                                        <img src="../<?php echo htmlspecialchars($item['image_url']); ?>"
                                                                            alt="<?php echo htmlspecialchars($item['title']); ?>"
                                                                            class="rounded me-3"
                                                                            style="width: 50px; height: 50px; object-fit: cover;"
                                                                            onerror="this.src='../images/home.jpg'">
                                                                    <?php else: ?>
                                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-3"
                                                                            style="width: 50px; height: 50px;">
                                                                            <i class="bi bi-image text-muted"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($item['title']); ?></strong>
                                                                        <br>
                                                                        <small
                                                                            class="text-muted"><?php echo substr(htmlspecialchars($item['description']), 0, 50); ?>...</small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                            <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                            <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>
                                                            <td>
                                                                <span
                                                                    class="badge <?php echo $item['status'] === 'active' ? 'bg-success' : 'bg-secondary'; ?>">
                                                                    <?php echo ucfirst($item['status']); ?>
                                                                </span>
                                                                <?php if (isset($claimedItems[$item['id']]) && $claimedItems[$item['id']] > 0): ?>
                                                                    <br>
                                                                    <small class="text-success mt-1">
                                                                        <i class="bi bi-check-circle-fill"></i> Claimed
                                                                    </small>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td>

                                                                <button class="btn btn-sm btn-outline-warning"
                                                                    onclick="editItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-pencil"></i>
                                                                </button>
                                                                <button class="btn btn-sm btn-outline-danger"
                                                                    onclick="deleteItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-trash"></i>
                                                                </button>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Claims Tab -->
                        <div class="tab-pane fade" id="claims" role="tabpanel">
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center mb-4">
                                        <h5 class="card-title mb-0">My Claims</h5>
                                        <span class="badge bg-warning"
                                            id="claimsCount"><?php echo count($userClaims); ?> claims</span>
                                    </div>

                                    <?php if (empty($userClaims)): ?>
                                        <div id="noClaims" class="text-center py-5">
                                            <i class="bi bi-hand-thumbs-up text-muted" style="font-size: 3rem;"></i>
                                            <h5 class="mt-3 text-muted">No Claims</h5>
                                            <p class="text-muted">You haven't made any claims yet.</p>
                                            <a href="../pages/search" class="btn btn-primary">
                                                <i class="bi bi-search me-2"></i>Browse Items
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="table-responsive">
                                            <table class="table table-hover">
                                                <thead>
                                                    <tr>
                                                        <th>Item</th>
                                                        <th>Claim Date</th>
                                                        <th>Status</th>
                                                        <th>Contact Info</th>
                                                        <th>Actions</th>
                                                    </tr>
                                                </thead>
                                                <tbody id="claimsTable">
                                                    <?php foreach ($userClaims as $claim): ?>
                                                        <tr>
                                                            <td>
                                                                <div class="d-flex align-items-center">
                                                                    <?php if ($claim['image_url']): ?>
                                                                        <img src="../<?php echo htmlspecialchars($claim['image_url']); ?>"
                                                                            alt="<?php echo htmlspecialchars($claim['title']); ?>"
                                                                            class="rounded me-3"
                                                                            style="width: 50px; height: 50px; object-fit: cover;"
                                                                            onerror="this.src='../images/home.jpg'">
                                                                    <?php else: ?>
                                                                        <div class="bg-light rounded d-flex align-items-center justify-content-center me-3"
                                                                            style="width: 50px; height: 50px;">
                                                                            <i class="bi bi-image text-muted"></i>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                    <div>
                                                                        <strong><?php echo htmlspecialchars($claim['title']); ?></strong>
                                                                        <br>
                                                                        <small class="text-muted">Type:
                                                                            <?php echo ucfirst($claim['type']); ?></small>
                                                                    </div>
                                                                </div>
                                                            </td>
                                                            <td><?php echo date('M j, Y', strtotime($claim['created_at'])); ?>
                                                            </td>
                                                            <td>
                                                                <span class="badge 
                                                                    <?php
                                                                    switch ($claim['status']) {
                                                                        case 'pending':
                                                                            echo 'bg-warning';
                                                                            break;
                                                                        case 'approved':
                                                                            echo 'bg-success';
                                                                            break;
                                                                        case 'rejected':
                                                                            echo 'bg-danger';
                                                                            break;
                                                                        default:
                                                                            echo 'bg-secondary';
                                                                    }
                                                                    ?>">
                                                                    <?php echo ucfirst($claim['status']); ?>
                                                                </span>
                                                            </td>
                                                            <td>
                                                                <small>
                                                                    <strong><?php echo htmlspecialchars($claim['owner_name']); ?></strong><br>
                                                                    <?php echo htmlspecialchars($claim['owner_email']); ?><br>
                                                                    <?php echo $claim['owner_phone'] ? htmlspecialchars($claim['owner_phone']) : 'No phone'; ?>
                                                                </small>
                                                            </td>
                                                            <td>

                                                                <button class="btn btn-sm btn-outline-info"
                                                                    onclick="contactOwner('<?php echo htmlspecialchars($claim['owner_email']); ?>', '<?php echo htmlspecialchars($claim['owner_phone']); ?>')">
                                                                    <i class="bi bi-telephone"></i>
                                                                </button>
                                                                <?php if ($claim['status'] === 'pending'): ?>
                                                                    <button class="btn btn-sm btn-outline-danger"
                                                                        onclick="cancelClaim(<?php echo $claim['id']; ?>)">
                                                                        <i class="bi bi-x-circle"></i>
                                                                    </button>
                                                                <?php endif; ?>
                                                            </td>
                                                        </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Profile Tab -->
                        <div class="tab-pane fade" id="profile" role="tabpanel">
                            <div class="card border-0 shadow-sm mt-3">
                                <div class="card-body">
                                    <h5 class="card-title mb-4">Profile Information</h5>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Full Name</label>
                                                <p class="form-control-static" id="profileName">
                                                    <?php echo htmlspecialchars($user_name); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Email Address</label>
                                                <p class="form-control-static" id="profileEmail">
                                                    <?php echo htmlspecialchars($user_email); ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Phone Number</label>
                                                <p class="form-control-static" id="profilePhone">
                                                    <?php echo $profile['phone'] ? htmlspecialchars($profile['phone']) : 'Not provided'; ?>
                                                </p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Member Since</label>
                                                <p class="form-control-static" id="profileSince">
                                                    <?php echo date('F j, Y', strtotime($profile['created_at'])); ?>
                                                </p>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="mt-4">
                                        <button class="btn btn-outline-primary" onclick="editProfile()">
                                            <i class="bi bi-pencil me-2"></i>Edit Profile
                                        </button>
                                        <button class="btn btn-outline-secondary ms-2" onclick="changePassword()">
                                            <i class="bi bi-key me-2"></i>Change Password
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row g-4">
                <div class="col-md-4">
                    <h5 class="fw-bold mb-3">Campus Lost & Found</h5>
                    <p class="text-white-50">Helping campus communities reunite with their belongings since 2024.</p>
                    <div class="d-flex gap-3">
                        <a href="#" class="text-white-50"><i class="bi bi-facebook fs-5"></i></a>
                        <a href="#" class="text-white-50"><i class="bi bi-twitter fs-5"></i></a>
                        <a href="#" class="text-white-50"><i class="bi bi-instagram fs-5"></i></a>
                    </div>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-bold mb-3">Quick Links</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="../pages/search" class="text-white-50 text-decoration-none">Browse Items</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none" onclick="addNewItem()">Post Item</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Dashboard</a></li>
                    </ul>
                </div>
                <div class="col-md-2">
                    <h6 class="fw-bold mb-3">Support</h6>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Help Center</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">Contact Us</a></li>
                        <li class="mb-2"><a href="#" class="text-white-50 text-decoration-none">FAQs</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 class="fw-bold mb-3">Campus Partnerships</h6>
                    <p class="text-white-50 small">Trusted by universities nationwide</p>
                    <div class="d-flex flex-wrap gap-2">
                        <span class="badge bg-secondary">University A</span>
                        <span class="badge bg-secondary">College B</span>
                        <span class="badge bg-secondary">Institute C</span>
                    </div>
                </div>
            </div>
            <hr class="my-4 bg-white opacity-25">
            <div class="text-center text-white-50 small">
                <p class="mb-0">&copy; 2025 Campus Lost & Found. All rights reserved. | <a href="#"
                        class="text-white-50">Privacy Policy</a> | <a href="#" class="text-white-50">Terms of
                        Service</a></p>
            </div>
        </div>
    </footer>

    <!-- Modals -->
    <!-- Create/Edit Item Modal -->
    <div class="modal fade" id="itemModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="itemModalTitle">Add New Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="itemForm" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" id="itemId" name="item_id">
                        <input type="hidden" id="currentImage" name="current_image">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Title *</label>
                                    <input type="text" class="form-control" id="itemTitle" name="title" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Category *</label>
                                    <select class="form-select" id="itemCategory" name="category" required>
                                        <option value="">Select Category</option>
                                        <option value="Electronics">Electronics</option>
                                        <option value="Books">Books</option>
                                        <option value="Clothing">Clothing</option>
                                        <option value="Accessories">Accessories</option>
                                        <option value="Documents">Documents</option>
                                        <option value="Keys">Keys</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Description *</label>
                            <textarea class="form-control" id="itemDescription" name="description" rows="3" required></textarea>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Location *</label>
                                    <input type="text" class="form-control" id="itemLocation" name="location" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Date *</label>
                                    <input type="date" class="form-control" id="itemDate" name="date" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Type</label>
                            <div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="type" id="typeLost" value="lost" checked>
                                    <label class="form-check-label" for="typeLost">Lost Item</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="type" id="typeFound" value="found">
                                    <label class="form-check-label" for="typeFound">Found Item</label>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Image</label>
                            <input type="file" class="form-control" id="itemImage" name="image" accept="image/*">
                            <div class="form-text">Upload a clear photo of the item (optional)</div>
                            <div id="imagePreview" class="mt-2"></div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Item</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="profileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="profileForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Full Name *</label>
                            <input type="text" class="form-control" id="editName" name="name" value="<?php echo htmlspecialchars($user_name); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" value="<?php echo htmlspecialchars($user_email); ?>" disabled>
                            <div class="form-text">Email cannot be changed</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="editPhone" name="phone" value="<?php echo $profile['phone'] ? htmlspecialchars($profile['phone']) : ''; ?>">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Profile</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="passwordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form id="passwordForm">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Password *</label>
                            <input type="password" class="form-control" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Password *</label>
                            <input type="password" class="form-control" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password *</label>
                            <input type="password" class="form-control" name="confirm_password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this item? This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDelete">Delete</button>
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
        // JavaScript functions for dashboard interactions
        let currentItemId = null;
        let currentClaimId = null;

        function editItem(itemId) {
            // Fetch item details and populate form
            fetch('', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_item_details&item_id=${itemId}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const item = data.item;
                    document.getElementById('itemModalTitle').textContent = 'Edit Item';
                    document.getElementById('itemId').value = item.id;
                    document.getElementById('itemTitle').value = item.title;
                    document.getElementById('itemDescription').value = item.description;
                    document.getElementById('itemCategory').value = item.category;
                    document.getElementById('itemLocation').value = item.location;
                    document.getElementById('itemDate').value = item.date;
                    document.getElementById('currentImage').value = item.image_url || '';
                    
                    if (item.type === 'found') {
                        document.getElementById('typeFound').checked = true;
                    } else {
                        document.getElementById('typeLost').checked = true;
                    }
                    
                    // Show image preview
                    const imagePreview = document.getElementById('imagePreview');
                    if (item.image_url) {
                        imagePreview.innerHTML = `<img src="../${item.image_url}" alt="Current image" style="max-width: 200px; max-height: 150px;" class="rounded">`;
                    } else {
                        imagePreview.innerHTML = '';
                    }
                    
                    const modal = new bootstrap.Modal(document.getElementById('itemModal'));
                    modal.show();
                } else {
                    showAlert('Error loading item details', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error loading item details', 'error');
            });
        }

        function deleteItem(itemId) {
            currentItemId = itemId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function viewClaim(claimId) {
            // Show claim details modal or page
            alert('View claim details for ID: ' + claimId);
        }

        function contactOwner(email, phone) {
            let contactInfo = `Email: ${email}`;
            if (phone) {
                contactInfo += `\nPhone: ${phone}`;
            }
            alert('Contact Information:\n' + contactInfo);
        }

        function cancelClaim(claimId) {
            if (confirm('Are you sure you want to cancel this claim?')) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=cancel_claim&claim_id=${claimId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showAlert(data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error cancelling claim', 'error');
                });
            }
        }

        function editProfile() {
            const modal = new bootstrap.Modal(document.getElementById('profileModal'));
            modal.show();
        }

        function changePassword() {
            const modal = new bootstrap.Modal(document.getElementById('passwordModal'));
            modal.show();
        }

        function addNewItem() {
            document.getElementById('itemModalTitle').textContent = 'Add New Item';
            document.getElementById('itemForm').reset();
            document.getElementById('itemId').value = '';
            document.getElementById('currentImage').value = '';
            document.getElementById('imagePreview').innerHTML = '';
            document.getElementById('typeLost').checked = true;
            
            const modal = new bootstrap.Modal(document.getElementById('itemModal'));
            modal.show();
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

        // Form Submissions
        document.getElementById('itemForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', this.querySelector('#itemId').value ? 'update_item' : 'create_item');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('itemModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error saving item', 'error');
            });
        });

        document.getElementById('profileForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'update_profile');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('profileModal')).hide();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error updating profile', 'error');
            });
        });

        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'change_password');
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showAlert(data.message, 'success');
                    bootstrap.Modal.getInstance(document.getElementById('passwordModal')).hide();
                    this.reset();
                } else {
                    showAlert(data.message, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('Error changing password', 'error');
            });
        });

        // Delete confirmation
        document.getElementById('confirmDelete').addEventListener('click', function() {
            if (currentItemId) {
                fetch('', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=delete_item&item_id=${currentItemId}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showAlert(data.message, 'success');
                        bootstrap.Modal.getInstance(document.getElementById('deleteModal')).hide();
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
        });

        // Image preview
        document.getElementById('itemImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('imagePreview');
            
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview" style="max-width: 200px; max-height: 150px;" class="rounded">`;
                };
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '';
            }
        });

        // Show alert function
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
                if (alertDiv.parentNode) {
                    alertDiv.remove();
                }
            }, 5000);
        }

        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            // You can add AJAX calls here to refresh specific data
            console.log('Dashboard auto-refresh');
        }, 30000);

        // Mark notification as read when clicked
        document.addEventListener('click', function(e) {
            if (e.target.closest('.dropdown-item') && !e.target.closest('.dropdown-item').getAttribute('href').includes('notifications')) {
                const notificationId = e.target.closest('.dropdown-item').dataset.notificationId;
                if (notificationId) {
                    markNotificationAsRead(notificationId);
                }
            }
        });

        function markNotificationAsRead(notificationId) {
            fetch('../actions/mark-notification-read.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ notification_id: notificationId })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    updateNotificationBadge();
                }
            })
            .catch(error => console.error('Error:', error));
        }

        function updateNotificationBadge() {
            fetch('../actions/get-notification-count.php')
            .then(response => response.json())
            .then(data => {
                const badge = document.querySelector('.notification-badge');
                if (data.unread_count > 0) {
                    badge.textContent = data.unread_count;
                    badge.style.display = 'inline';
                } else {
                    badge.style.display = 'none';
                }
            });
        }

        // Auto-refresh notifications every 30 seconds
        setInterval(updateNotificationBadge, 30000);
    </script>
</body>
</html>