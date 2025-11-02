<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: ../pages/login.php');
    exit();
}

if ($_SESSION['role'] !== 'admin') {
    header('Location: ../pages/dashboard.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get admin statistics
$statsStmt = $pdo->prepare("
    SELECT 
        (SELECT COUNT(*) FROM items) as total_items,
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM claims) as total_claims,
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
    SELECT c.*, i.title as item_title, u.name as claimant_name, u.email as claimant_email 
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
        case 'update_item_status':
            $item_id = $_POST['item_id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if ($item_id && in_array($status, ['active', 'inactive', 'resolved'])) {
                $updateStmt = $pdo->prepare("UPDATE items SET status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $item_id]);
                echo json_encode(['success' => true, 'message' => 'Item status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;
            
        case 'update_user_status':
            $user_id_action = $_POST['user_id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if ($user_id_action && in_array($status, ['active', 'suspended'])) {
                $updateStmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $user_id_action]);
                echo json_encode(['success' => true, 'message' => 'User status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;
            
        case 'update_claim_status':
            $claim_id = $_POST['claim_id'] ?? '';
            $status = $_POST['status'] ?? '';
            
            if ($claim_id && in_array($status, ['pending', 'approved', 'rejected'])) {
                $updateStmt = $pdo->prepare("UPDATE claims SET status = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$status, $claim_id]);
                echo json_encode(['success' => true, 'message' => 'Claim status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
            }
            exit;
            
        case 'delete_item':
            $item_id = $_POST['item_id'] ?? '';
            
            if ($item_id) {
                // First delete related claims
                $deleteClaimsStmt = $pdo->prepare("DELETE FROM claims WHERE item_id = ?");
                $deleteClaimsStmt->execute([$item_id]);
                
                // Then delete the item
                $deleteStmt = $pdo->prepare("DELETE FROM items WHERE id = ?");
                $deleteStmt->execute([$item_id]);
                
                echo json_encode(['success' => true, 'message' => 'Item deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Invalid item ID']);
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
                        <a class="nav-link text-white active" href="#" data-section="dashboard">
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
                <a href="../pages/dashboard" class="btn btn-outline-info w-100">
                    <i class="bi bi-person-circle me-2"></i>User Dashboard
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
                                <h3 class="fw-bold mt-3" id="adminTotalItems"><?php echo $stats['total_items'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Total Items</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-people text-success fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminTotalUsers"><?php echo $stats['total_users'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Total Users</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-clipboard-check text-warning fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminTotalClaims"><?php echo $stats['total_claims'] ?? 0; ?></h3>
                                <p class="text-muted mb-0">Total Claims</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card border-0 shadow-sm">
                            <div class="card-body p-4 text-center">
                                <i class="bi bi-check-circle text-info fs-1"></i>
                                <h3 class="fw-bold mt-3" id="adminResolvedItems"><?php echo $stats['resolved_items'] ?? 0; ?></h3>
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
                                                        • <?php echo date('M j, Y g:i A', strtotime($activity['created_at'])); ?>
                                                    </small>
                                                </div>
                                                <span class="badge <?php echo $activity['type'] === 'lost' ? 'bg-danger' : 'bg-success'; ?> status-badge">
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
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewClaimDetails(<?php echo $claim['id']; ?>)">
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
                                                    <small class="text-muted">by <?php echo htmlspecialchars($item['user_name']); ?></small>
                                                </td>
                                                <td><?php echo htmlspecialchars($item['category']); ?></td>
                                                <td><?php echo htmlspecialchars($item['location']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($item['date'])); ?></td>
                                                <td>
                                                    <select class="form-select form-select-sm status-select" 
                                                            data-item-id="<?php echo $item['id']; ?>" 
                                                            style="width: 120px;">
                                                        <option value="active" <?php echo $item['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="inactive" <?php echo $item['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                                                        <option value="resolved" <?php echo $item['status'] === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewItem(<?php echo $item['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteItem(<?php echo $item['id']; ?>)">
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
                                                            data-user-id="<?php echo $user['id']; ?>" 
                                                            style="width: 120px;">
                                                        <option value="active" <?php echo $user['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                                                        <option value="suspended" <?php echo $user['status'] === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewUser(<?php echo $user['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="sendMessage('<?php echo htmlspecialchars($user['email']); ?>')">
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
                                        <th>Claim ID</th>
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
                                        <?php foreach ($allClaims as $claim): ?>
                                            <tr>
                                                <td><?php echo $claim['id']; ?></td>
                                                <td><?php echo htmlspecialchars($claim['item_title']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['claimant_name']); ?></td>
                                                <td><?php echo htmlspecialchars($claim['claimant_email']); ?></td>
                                                <td><?php echo date('M j, Y', strtotime($claim['created_at'])); ?></td>
                                                <td>
                                                    <select class="form-select form-select-sm claim-status-select" 
                                                            data-claim-id="<?php echo $claim['id']; ?>" 
                                                            style="width: 120px;">
                                                        <option value="pending" <?php echo $claim['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="approved" <?php echo $claim['status'] === 'approved' ? 'selected' : ''; ?>>Approved</option>
                                                        <option value="rejected" <?php echo $claim['status'] === 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </td>
                                                <td class="action-buttons">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="viewClaimDetails(<?php echo $claim['id']; ?>)">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-info" onclick="contactClaimant('<?php echo htmlspecialchars($claim['claimant_email']); ?>')">
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
        </div>
    </div>

    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="bi bi-moon-fill"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('collapsed');
        });

        // Section navigation
        document.querySelectorAll('[data-section]').forEach(link => {
            link.addEventListener('click', function(e) {
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
            select.addEventListener('change', function() {
                const itemId = this.getAttribute('data-item-id');
                const status = this.value;
                
                updateItemStatus(itemId, status);
            });
        });

        // User status update
        document.querySelectorAll('.user-status-select').forEach(select => {
            select.addEventListener('change', function() {
                const userId = this.getAttribute('data-user-id');
                const status = this.value;
                
                updateUserStatus(userId, status);
            });
        });

        // Claim status update
        document.querySelectorAll('.claim-status-select').forEach(select => {
            select.addEventListener('change', function() {
                const claimId = this.getAttribute('data-claim-id');
                const status = this.value;
                
                updateClaimStatus(claimId, status);
            });
        });

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

        // Utility functions
        function viewItem(itemId) {
            window.open(`../pages/item-details.php?id=${itemId}`, '_blank');
        }

        function viewUser(userId) {
            alert(`View user details for ID: ${userId}`);
        }

        function viewClaimDetails(claimId) {
            alert(`View claim details for ID: ${claimId}`);
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
        document.getElementById('darkModeToggle').addEventListener('click', function() {
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