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
                    <a href="../pages/post" class="btn btn-primary">
                        <i class="bi bi-plus-circle me-2"></i>Post New Item
                    </a>
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
                                <?php echo $stats['total_items'] ?? 0; ?></h3>
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
                                <?php echo $stats['active_items'] ?? 0; ?></h3>
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
                                <?php echo $pendingClaims['pending_claims'] ?? 0; ?></h3>
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
                                <?php echo $stats['resolved_items'] ?? 0; ?></h3>
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
                                            <a href="../pages/post?type=lost" class="btn btn-primary">
                                                <i class="bi bi-plus-circle me-2"></i>Report Lost Item
                                            </a>
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
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="viewItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
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
                                            <a href="../pages/post?type=found" class="btn btn-primary">
                                                <i class="bi bi-plus-circle me-2"></i>Post Found Item
                                            </a>
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
                                                            </td>
                                                            <td>
                                                                <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="viewItem(<?php echo $item['id']; ?>)">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
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
                                                                <button class="btn btn-sm btn-outline-primary"
                                                                    onclick="viewClaim(<?php echo $claim['id']; ?>)">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
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
                                                    <?php echo htmlspecialchars($user_name); ?></p>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-3">
                                                <label class="form-label fw-semibold">Email Address</label>
                                                <p class="form-control-static" id="profileEmail">
                                                    <?php echo htmlspecialchars($user_email); ?></p>
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
                                                    <?php echo date('F j, Y', strtotime($profile['created_at'])); ?></p>
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
                        <li class="mb-2"><a href="pages/search" class="text-white-50 text-decoration-none">Browse
                                Items</a></li>
                        <li class="mb-2"><a href="pages/post" class="text-white-50 text-decoration-none">Post Item</a>
                        </li>
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

    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="bi bi-moon-fill"></i>
    </button>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // JavaScript functions for dashboard interactions
        function viewItem(itemId) {
            window.location.href = `../pages/item-details?id=${itemId}`;
        }

        function editItem(itemId) {
            window.location.href = `../pages/edit-item?id=${itemId}`;
        }

        function deleteItem(itemId) {
            if (confirm('Are you sure you want to delete this item? This action cannot be undone.')) {
                // AJAX call to delete item
                fetch(`../actions/delete-item?id=${itemId}`, {
                    method: 'DELETE',
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error deleting item: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error deleting item');
                    });
            }
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
                // AJAX call to cancel claim
                fetch(`../actions/cancel-claim?id=${claimId}`, {
                    method: 'POST',
                })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Error canceling claim: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Error canceling claim');
                    });
            }
        }

        function editProfile() {
            window.location.href = '../pages/edit-profile';
        }

        function changePassword() {
            window.location.href = '../pages/change-password';
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

        // Auto-refresh dashboard data every 30 seconds
        setInterval(() => {
            // You can add AJAX calls here to refresh specific data
            console.log('Dashboard auto-refresh');
        }, 30000);
    </script>
</body>

</html>