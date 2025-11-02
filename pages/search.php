<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;

if (!$is_logged_in) {
  // Allow viewing but don't redirect - unlogged users can browse items
  $user_id = null;
  $user_name = '';
  $user_email = '';
} else {
  $user_id = $_SESSION['user_id'];
  $user_name = $_SESSION['user_name'];
  $user_email = $_SESSION['user_email'];
}

// Handle claim submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_claim'])) {
  if (!$is_logged_in) {
    $claim_error = "You must be logged in to submit a claim.";
  } else {
    $item_id = $_POST['item_id'];
    $claimer_name = $_POST['claimer_name'];
    $claimer_email = $_POST['claimer_email'];
    $proof = $_POST['proof'];

    try {
      // Check if item exists, is active, and get owner info
      $itemStmt = $pdo->prepare("
                SELECT items.*, users.id as owner_id 
                FROM items 
                JOIN users ON items.user_id = users.id 
                WHERE items.id = ? AND items.status = 'active'
            ");
      $itemStmt->execute([$item_id]);
      $item = $itemStmt->fetch(PDO::FETCH_ASSOC);

      if (!$item) {
        $claim_error = "Item not found or not available for claiming.";
      } else {
        // Check if user is trying to claim their own item
        if ($item['owner_id'] == $user_id) {
          $claim_error = "You cannot claim your own item. Please contact admin if you need assistance.";
        } else {
          // Check if user already claimed this item
          $existingClaimStmt = $pdo->prepare("SELECT id FROM claims WHERE item_id = ? AND claimer_id = ?");
          $existingClaimStmt->execute([$item_id, $user_id]);
          $existingClaim = $existingClaimStmt->fetch(PDO::FETCH_ASSOC);

          if ($existingClaim) {
            $claim_error = "You have already submitted a claim for this item.";
          } else {
            // Insert claim
            $stmt = $pdo->prepare("
                            INSERT INTO claims (item_id, claimer_id, message, status, created_at, updated_at) 
                            VALUES (?, ?, ?, 'pending', NOW(), NOW())
                        ");

            $stmt->execute([
              $item_id,
              $user_id,
              $proof
            ]);

            $claim_success = "Claim submitted successfully! We'll contact you soon.";
          }
        }
      }
    } catch (Exception $error) {
      $claim_error = "Error submitting claim: " . $error->getMessage();
    }
  }
}

// Fetch items from database with filters - ALL ITEMS regardless of type
$category = $_GET['category'] ?? '';
$search = $_GET['search'] ?? '';
$location = $_GET['location'] ?? '';
$sort = $_GET['sort'] ?? 'newest';
$item_type = $_GET['type'] ?? ''; // Optional type filter

// Build query - REMOVED type condition to show all items
$query = "
    SELECT 
        items.*,
        users.name as user_name,
        users.email as user_email,
        users.phone as user_phone
    FROM items
    JOIN users ON items.user_id = users.id
    WHERE items.status = 'active'
";

$params = [];

// Apply filters
if ($item_type) {
  $query .= " AND items.type = ?";
  $params[] = $item_type;
}

if ($category) {
  $query .= " AND items.category = ?";
  $params[] = $category;
}

if ($search) {
  $query .= " AND (items.title LIKE ? OR items.description LIKE ? OR items.location LIKE ?)";
  $searchTerm = "%$search%";
  $params[] = $searchTerm;
  $params[] = $searchTerm;
  $params[] = $searchTerm;
}

if ($location) {
  $query .= " AND items.location LIKE ?";
  $locationTerm = "%$location%";
  $params[] = $locationTerm;
}

// Apply sorting
if ($sort === 'oldest') {
  $query .= " ORDER BY items.date ASC, items.created_at ASC";
} else {
  $query .= " ORDER BY items.date DESC, items.created_at DESC";
}

// Execute query
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get categories for filter - ALL categories
$categoryStmt = $pdo->prepare("SELECT DISTINCT category FROM items WHERE status = 'active' ORDER BY category");
$categoryStmt->execute();
$categories = $categoryStmt->fetchAll(PDO::FETCH_COLUMN);

// Get locations for filter - ALL locations
$locationStmt = $pdo->prepare("SELECT DISTINCT location FROM items WHERE status = 'active' ORDER BY location");
$locationStmt->execute();
$locations = $locationStmt->fetchAll(PDO::FETCH_COLUMN);

// Get types for filter
$typeStmt = $pdo->prepare("SELECT DISTINCT type FROM items WHERE status = 'active' ORDER BY type");
$typeStmt->execute();
$types = $typeStmt->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Browse Items - Campus Lost & Found</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../css/style.css">
  <style>
    .search-container {
      min-height: 100vh;
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      padding: 2rem 0;
    }

    .search-header {
      background: linear-gradient(135deg, #2c5aa0 0%, #1e3a6f 100%);
      color: white;
      padding: 3rem 0;
      margin-bottom: 2rem;
    }

    .search-card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
      margin-bottom: 2rem;
      background: white;
    }

    .item-card {
      border-radius: 12px;
      border: none;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
      transition: all 0.3s ease;
      background: white;
      height: 100%;
      cursor: pointer;
    }

    .item-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
    }

    .item-image {
      height: 200px;
      object-fit: cover;
      border-radius: 12px 12px 0 0;
    }

    .user-dropdown {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border: none;
      color: white;
    }

    .user-dropdown:hover {
      background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
      color: white;
    }

    .user-avatar {
      width: 32px;
      height: 32px;
      background: rgba(255, 255, 255, 0.2);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 14px;
    }

    .login-prompt {
      background: linear-gradient(135deg, #fff3cd 0%, #ffeaa7 100%);
      border: 1px solid #ffecb5;
      border-radius: 8px;
      padding: 1rem;
      text-align: center;
      margin-top: 1rem;
    }

    .search-form {
      background: white;
      border-radius: 12px;
      padding: 2rem;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
    }

    .type-badge-lost {
      background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
      color: white;
    }

    .type-badge-found {
      background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
      color: white;
    }
  </style>
</head>

<body>
  <!-- Navigation -->
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
    <div class="container">
      <a class="navbar-brand d-flex align-items-center" href="../">
        <i class="bi bi-search-heart-fill text-primary fs-3 me-2"></i>
        <span class="fw-bold" style="font-family: 'Poppins', sans-serif;">Campus Lost & Found</span>
      </a>

      <div class="navbar-nav ms-auto align-items-center">
        <div class="d-flex align-items-center">
          <a href="../" class="nav-link me-3">
            <i class="bi bi-arrow-left me-1"></i>Back to Home
          </a>

          <?php if ($is_logged_in): ?>
            <a href="post" class="nav-link me-3">
              <i class="bi bi-plus-circle me-2"></i>Report Item
            </a>

            <!-- User Dropdown -->
            <div class="nav-item dropdown">
              <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown px-3 rounded-pill" href="#"
                role="button" data-bs-toggle="dropdown">
                <div class="user-avatar me-2">
                  <i class="bi bi-person-fill"></i>
                </div>
                <span><?php echo htmlspecialchars($user_name); ?></span>
              </a>
              <ul class="dropdown-menu dropdown-menu-end">
                <li>
                  <span class="dropdown-item-text fw-bold text-primary">
                    <i class="bi bi-person me-2"></i><?php echo htmlspecialchars($user_name); ?>
                  </span>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <a class="dropdown-item"
                    href="<?php echo ($_SESSION['role'] ?? 'user') == 'admin' ? '../admin/admin' : '../user/dashboard'; ?>">
                    <i class="bi bi-grid me-2"></i>Dashboard
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="../user/profile">
                    <i class="bi bi-person-circle me-2"></i>My Profile
                  </a>
                </li>
                <li>
                  <a class="dropdown-item" href="../user/my-items">
                    <i class="bi bi-bag me-2"></i>My Items
                  </a>
                </li>
                <li>
                  <hr class="dropdown-divider">
                </li>
                <li>
                  <a class="dropdown-item text-danger" href="../logout">
                    <i class="bi bi-box-arrow-right me-2"></i>Logout
                  </a>
                </li>
              </ul>
            </div>
          <?php else: ?>
            <!-- Show login/signup when not logged in -->
            <a href="login" class="nav-link me-3">
              <i class="bi bi-box-arrow-in-right me-1"></i>Login
            </a>
            <a href="register" class="btn btn-primary btn-sm px-3">
              <i class="bi bi-person-plus me-1"></i>Sign Up
            </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
  </nav>

  <!-- Header -->
  <div class="search-header">
    <div class="container">
      <div class="row align-items-center">
        <div class="col-md-8">
          <h1 class="display-5 fw-bold mb-3" style="font-family: 'Poppins', sans-serif;">
            <i class="bi bi-grid me-3"></i>
            Browse All Items
          </h1>
          <p class="lead mb-0" style="font-family: 'Poppins', sans-serif;">
            View all lost and found items in one place
          </p>
        </div>
        <div class="col-md-4 text-md-end">
          <div class="bg-white bg-opacity-20 rounded-pill px-4 py-2 d-inline-block text-primary">
            <small style="font-family: 'Poppins', sans-serif;">
              <i class="bi bi-info-circle me-1"></i>Showing all active items
            </small>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Search Section -->
  <section class="py-5 bg-light">
    <div class="container">
      <div class="text-center mb-4">
        <h2 class="fw-bold">Browse Items</h2>
        <p class="text-muted">Search through all lost and found items</p>
      </div>

      <!-- Search Form -->
      <div class="row justify-content-center mb-4">
        <div class="col-lg-10">
          <div class="search-form">
            <div class="row g-3">
              <div class="col-md-3">
                <label for="type" class="form-label fw-semibold">Item Type</label>
                <select class="form-select" id="type" name="type">
                  <option value="">All Types</option>
                  <?php foreach ($types as $type): ?>
                    <option value="<?php echo htmlspecialchars($type); ?>">
                      <?php echo htmlspecialchars(ucfirst($type)); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="category" class="form-label fw-semibold">Category</label>
                <select class="form-select" id="category" name="category">
                  <option value="">All Categories</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat); ?>">
                      <?php echo htmlspecialchars($cat); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="location" class="form-label fw-semibold">Location</label>
                <select class="form-select" id="location" name="location">
                  <option value="">All Locations</option>
                  <?php foreach ($locations as $loc): ?>
                    <option value="<?php echo htmlspecialchars($loc); ?>">
                      <?php echo htmlspecialchars($loc); ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-md-3">
                <label for="sort" class="form-label fw-semibold">Sort By</label>
                <select class="form-select" id="sort" name="sort">
                  <option value="newest">Newest First</option>
                  <option value="oldest">Oldest First</option>
                </select>
              </div>
              <div class="col-12">
                <label for="search" class="form-label fw-semibold">Search</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="search" name="search"
                    placeholder="Search by item name, description, or location...">
                  <button class="btn btn-outline-secondary" type="button" onclick="clearFilters()">
                    <i class="bi bi-arrow-clockwise"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Loading Spinner -->
      <div id="loadingSpinner" class="text-center d-none">
        <div class="spinner-border text-primary" role="status"></div>
        <p class="mt-2 text-muted">Searching items...</p>
      </div>

      <!-- Results -->
      <p class="text-muted" id="resultsCount">Showing <span class="fw-bold"><?php echo count($items); ?></span> items
      </p>

      <div class="row g-4" id="itemsGrid">
        <?php if (empty($items)): ?>
          <div class="col-12 text-center py-5">
            <i class="bi bi-inbox text-muted" style="font-size: 5rem;"></i>
            <h4 class="mt-3 text-muted">No items found</h4>
            <p class="text-muted">Try adjusting your search or filters</p>
          </div>
        <?php else: ?>
          <?php foreach ($items as $item): ?>
            <div class="col-md-4 mb-4">
              <div class="card item-card h-100" onclick="viewItemDetails(<?php echo $item['id']; ?>)">
                <img
                  src="<?php echo $item['image_url'] ? '..' . htmlspecialchars($item['image_url']) : '../images/home.jpg'; ?>"
                  class="card-img-top item-image" alt="<?php echo htmlspecialchars($item['title']); ?>"
                  onerror="this.src='../images/home.jpg'">
                <div class="card-body">
                  <div class="d-flex justify-content-between align-items-start mb-2">
                    <h5 class="card-title fw-bold"><?php echo htmlspecialchars($item['title']); ?></h5>
                    <span class="badge <?php echo $item['type'] === 'lost' ? 'type-badge-lost' : 'type-badge-found'; ?>">
                      <?php echo htmlspecialchars(ucfirst($item['type'])); ?>
                    </span>
                  </div>
                  <p class="card-text text-muted"><?php echo htmlspecialchars(substr($item['description'], 0, 100)); ?>...
                  </p>
                  <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="badge bg-primary"><?php echo htmlspecialchars($item['category']); ?></span>
                    <small class="text-muted"><?php echo date('M j, Y', strtotime($item['date'])); ?></small>
                  </div>
                  <p class="card-text"><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($item['location']); ?>
                  </p>
                  <p class="card-text small text-muted">
                    <i class="bi bi-person me-1"></i>Reported by: <?php echo htmlspecialchars($item['user_name']); ?>
                  </p>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div id="noResults" class="text-center py-5 d-none">
        <i class="bi bi-inbox text-muted" style="font-size: 5rem;"></i>
        <h4 class="mt-3 text-muted">No items found</h4>
        <p class="text-muted">Try adjusting your search or filters</p>
      </div>
    </div>
  </section>
  <!-- Item Modal -->
  <div class="modal fade" id="itemModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Item Details</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <div class="row">
            <div class="col-md-6 mb-3">
              <img id="modalImage" src="" alt="Item" class="img-fluid rounded-3 w-100"
                onerror="this.src='../images/home.jpg'">
            </div>
            <div class="col-md-6">
              <div class="d-flex justify-content-between align-items-start mb-3">
                <h4 class="fw-bold mb-0" id="modalTitle"></h4>
                <span class="badge" id="modalType"></span>
              </div>
              <div class="mb-3"><span class="badge bg-primary" id="modalCategory"></span></div>
              <p class="text-muted" id="modalDescription"></p>
              <hr>
              <p><i class="bi bi-geo-alt text-primary me-2"></i><strong>Location:</strong> <span
                  id="modalLocation"></span></p>
              <p><i class="bi bi-calendar text-primary me-2"></i><strong>Date:</strong> <span id="modalDate"></span></p>
              <p><i class="bi bi-person text-primary me-2"></i><strong>Reported by:</strong> <span
                  id="modalReporter"></span></p>
              <p><i class="bi bi-envelope text-primary me-2"></i><strong>Contact:</strong> <span
                  id="modalContact"></span></p>

              <?php if ($is_logged_in): ?>
                <!-- Debug info -->
                <div class="alert alert-warning d-none" id="debugInfo">
                  Debug: User ID: <?php echo $user_id; ?>
                </div>

                <!-- Claim Button - Always visible but behavior changes -->
                <button class="btn btn-primary w-100 mt-3" id="claimButton" onclick="openClaimModal()">
                  <i class="bi bi-hand-thumbs-up me-2"></i>Claim This Item
                </button>

                <!-- Admin Contact Message for Own Items -->
                <div class="alert alert-info mt-3 d-none" id="ownItemMessage">
                  <i class="bi bi-telephone me-2"></i>
                  <strong>Contact Admin</strong>
                  <p class="mb-1 mt-2">This is your own item. If you need assistance, please contact the admin.</p>
                  <div class="mt-2">
                    <a href="mailto:admin@campus.edu" class="btn btn-sm btn-outline-info me-2">
                      <i class="bi bi-envelope me-1"></i>Email Admin
                    </a>
                    <a href="tel:+1234567890" class="btn btn-sm btn-outline-info">
                      <i class="bi bi-telephone me-1"></i>Call Admin
                    </a>
                  </div>
                </div>
              <?php else: ?>
                <!-- Show login prompt for unlogged users -->
                <div class="login-prompt">
                  <i class="bi bi-info-circle me-2"></i>
                  <strong>Login Required</strong>
                  <p class="mb-2 mt-2">You need to be logged in to claim items.</p>
                  <a href="login" class="btn btn-primary btn-sm">
                    <i class="bi bi-box-arrow-in-right me-1"></i>Login Now
                  </a>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Claim Modal -->
  <div class="modal fade" id="claimModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header border-0">
          <h5 class="modal-title fw-bold">Submit Claim</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body p-4">
          <?php if (isset($claim_error)): ?>
            <div class="alert alert-danger"><?php echo $claim_error; ?></div>
          <?php endif; ?>
          <?php if (isset($claim_success)): ?>
            <div class="alert alert-success"><?php echo $claim_success; ?></div>
          <?php endif; ?>

          <form method="POST" id="claimForm">
            <input type="hidden" name="submit_claim" value="1">
            <input type="hidden" id="claimItemId" name="item_id">
            <div class="mb-3">
              <label for="claimName" class="form-label">Your Name</label>
              <input type="text" class="form-control" id="claimName" name="claimer_name" 
                value="<?php echo $is_logged_in ? htmlspecialchars($user_name) : ''; ?>" 
                <?php echo !$is_logged_in ? 'disabled' : ''; ?> required>
            </div>
            <div class="mb-3">
              <label for="claimEmail" class="form-label">Email Address</label>
              <input type="email" class="form-control" id="claimEmail" name="claimer_email" 
                value="<?php echo $is_logged_in ? htmlspecialchars($user_email) : ''; ?>" 
                <?php echo !$is_logged_in ? 'disabled' : ''; ?> required>
            </div>
            <div class="mb-3">
              <label for="claimProof" class="form-label">Proof of Ownership</label>
              <textarea class="form-control" id="claimProof" name="proof" rows="3"
                placeholder="Describe how you can prove this item belongs to you..." required></textarea>
            </div>
            <button type="submit" class="btn btn-primary w-100">Submit Claim</button>
          </form>
        </div>
      </div>
    </div>
  </div>

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
                        <li class="mb-2"><a href="#"
                                class="text-white-50 text-decoration-none">Dashboard</a></li>
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

  <!-- Bootstrap JS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    // Store all items data for client-side filtering
    const allItems = <?php echo json_encode($items); ?>;

    // Initialize when page loads
    document.addEventListener('DOMContentLoaded', function () {
      console.log('Page loaded, initializing filters...');
      initializeEventListeners();
      performSearch(); // Initial search with all items
    });

    function initializeEventListeners() {
      console.log('Setting up event listeners...');

      // Automatic filter listeners
      document.getElementById('type').addEventListener('change', performSearch);
      document.getElementById('category').addEventListener('change', performSearch);
      document.getElementById('location').addEventListener('change', performSearch);
      document.getElementById('sort').addEventListener('change', performSearch);
      document.getElementById('search').addEventListener('input', debounce(performSearch, 300));

      // Claim form handling
      const claimForm = document.getElementById('claimForm');
      if (claimForm) {
        claimForm.addEventListener('submit', function (e) {
          e.preventDefault();

          const itemId = document.getElementById('claimItemId').value;
          const name = document.getElementById('claimName').value;
          const email = document.getElementById('claimEmail').value;
          const proof = document.getElementById('claimProof').value;

          if (!name || !email || !proof) {
            alert('Please fill in all fields');
            return;
          }

          submitClaim(itemId, name, email, proof);
        });
      }
    }

    // Debounce function to prevent too many rapid searches
    function debounce(func, wait) {
      let timeout;
      return function executedFunction(...args) {
        const later = () => {
          clearTimeout(timeout);
          func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
      };
    }

    function performSearch() {
      console.log('Performing search...');

      const typeFilter = document.getElementById('type').value;
      const categoryFilter = document.getElementById('category').value;
      const locationFilter = document.getElementById('location').value;
      const sortFilter = document.getElementById('sort').value;
      const searchTerm = document.getElementById('search').value.toLowerCase();
      const loadingSpinner = document.getElementById('loadingSpinner');

      // Show loading
      loadingSpinner.classList.remove('d-none');

      // Simulate loading for better UX (remove in production)
      setTimeout(() => {
        let filteredItems = allItems.filter(item => {
          // Type filter
          const matchesType = !typeFilter || item.type === typeFilter;

          // Category filter
          const matchesCategory = !categoryFilter || item.category === categoryFilter;

          // Location filter
          const matchesLocation = !locationFilter || item.location === locationFilter;

          // Search filter
          const matchesSearch = !searchTerm ||
            item.title.toLowerCase().includes(searchTerm) ||
            item.description.toLowerCase().includes(searchTerm) ||
            item.location.toLowerCase().includes(searchTerm);

          return matchesType && matchesCategory && matchesLocation && matchesSearch;
        });

        // Sort items
        if (sortFilter === 'oldest') {
          filteredItems.sort((a, b) => new Date(a.date) - new Date(b.date));
        } else {
          filteredItems.sort((a, b) => new Date(b.date) - new Date(a.date));
        }

        loadingSpinner.classList.add('d-none');
        loadSearchItems(filteredItems);
      }, 500);
    }

    function loadSearchItems(items) {
      console.log('Loading items:', items.length);
      const itemsGrid = document.getElementById('itemsGrid');
      const resultsCount = document.getElementById('resultsCount');
      const noResults = document.getElementById('noResults');

      if (resultsCount) {
        resultsCount.innerHTML = `Showing <span class="fw-bold">${items.length}</span> items`;
      }

      if (items.length === 0) {
        if (itemsGrid) itemsGrid.innerHTML = '';
        if (noResults) noResults.classList.remove('d-none');
        return;
      }

      if (noResults) noResults.classList.add('d-none');

      if (itemsGrid) {
        itemsGrid.innerHTML = items.map(item => `
                <div class="col-md-4 mb-4">
                    <div class="card item-card h-100" onclick="viewItemDetails(${item.id})">
                        <img src="${item.image_url ? '..' + item.image_url : '../images/home.jpg'}" 
                             class="card-img-top item-image" 
                             alt="${item.title}"
                             onerror="this.src='../images/home.jpg'">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold">${escapeHtml(item.title)}</h5>
                                <span class="badge ${item.type === 'lost' ? 'type-badge-lost' : 'type-badge-found'}">
                                    ${escapeHtml(item.type.charAt(0).toUpperCase() + item.type.slice(1))}
                                </span>
                            </div>
                            <p class="card-text text-muted">${escapeHtml(item.description.substring(0, 100))}...</p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="badge bg-primary">${escapeHtml(item.category)}</span>
                                <small class="text-muted">${formatDate(item.date)}</small>
                            </div>
                            <p class="card-text"><i class="bi bi-geo-alt me-1"></i>${escapeHtml(item.location)}</p>
                            <p class="card-text small text-muted">
                                <i class="bi bi-person me-1"></i>Reported by: ${escapeHtml(item.user_name)}
                            </p>
                        </div>
                    </div>
                </div>
            `).join('');
      }
    }

    function clearFilters() {
      document.getElementById('type').value = '';
      document.getElementById('category').value = '';
      document.getElementById('location').value = '';
      document.getElementById('sort').value = 'newest';
      document.getElementById('search').value = '';
      performSearch();
    }

    function viewItemDetails(itemId) {
      const item = allItems.find(i => i.id == itemId);
      if (!item) {
        console.error('Item not found:', itemId);
        return;
      }

      console.log('Viewing item:', item);
      console.log('Current user ID:', <?php echo $user_id ?? 'null'; ?>);
      console.log('Item user ID:', item.user_id);

      document.getElementById('modalTitle').textContent = item.title;
      document.getElementById('modalCategory').textContent = item.category;
      document.getElementById('modalDescription').textContent = item.description;
      document.getElementById('modalLocation').textContent = item.location;
      document.getElementById('modalDate').textContent = formatDate(item.date);
      document.getElementById('modalReporter').textContent = item.user_name;
      document.getElementById('modalContact').textContent = item.user_email;

      // Set type badge
      const typeBadge = document.getElementById('modalType');
      typeBadge.textContent = item.type.charAt(0).toUpperCase() + item.type.slice(1);
      typeBadge.className = `badge ${item.type === 'lost' ? 'type-badge-lost' : 'type-badge-found'}`;

      // Set image
      const modalImage = document.getElementById('modalImage');
      if (item.image_url) {
        modalImage.src = '..' + item.image_url;
      } else {
        modalImage.src = '../images/home.jpg';
      }
      modalImage.alt = item.title;

      // Store item ID for claiming
      document.getElementById('claimItemId').value = item.id;

      // Show/hide admin message based on ownership
      const claimButton = document.getElementById('claimButton');
      const ownItemMessage = document.getElementById('ownItemMessage');
      const debugInfo = document.getElementById('debugInfo');

      <?php if ($is_logged_in): ?>
        // Check if user owns this item - FIXED: Parse both as integers for comparison
        const currentUserId = <?php echo $user_id; ?>;
        const itemUserId = parseInt(item.user_id);
        const userOwnsItem = currentUserId === itemUserId;

        console.log('Ownership check:', {
          currentUserId: currentUserId,
          itemUserId: itemUserId,
          userOwnsItem: userOwnsItem
        });

        if (debugInfo) {
          debugInfo.innerHTML = `Debug: Current User ID: ${currentUserId}, Item User ID: ${itemUserId}, Owns Item: ${userOwnsItem}`;
          debugInfo.classList.remove('d-none');
        }

        if (userOwnsItem) {
          // User owns the item - show admin contact message
          console.log('Showing admin message - user owns item');
          if (claimButton) claimButton.style.display = 'none';
          if (ownItemMessage) {
            ownItemMessage.classList.remove('d-none');
            console.log('Admin message should be visible now');
          }
        } else {
          // User doesn't own the item - show claim button
          console.log('Showing claim button - user does not own item');
          if (claimButton) claimButton.style.display = 'block';
          if (ownItemMessage) ownItemMessage.classList.add('d-none');

          // Only enable claim for found items
          if (item.type !== 'found') {
            claimButton.disabled = true;
            claimButton.innerHTML = '<i class="bi bi-x-circle me-2"></i>Can Only Claim Found Items';
            claimButton.classList.add('btn-secondary');
            claimButton.classList.remove('btn-primary');
          } else {
            claimButton.disabled = false;
            claimButton.innerHTML = '<i class="bi bi-hand-thumbs-up me-2"></i>Claim This Item';
            claimButton.classList.add('btn-primary');
            claimButton.classList.remove('btn-secondary');
          }
        }
      <?php endif; ?>

      const itemModal = new bootstrap.Modal(document.getElementById('itemModal'));
      itemModal.show();
    }

    async function submitClaim(itemId, name, email, proof) {
      try {
        // Create form data
        const formData = new FormData();
        formData.append('submit_claim', '1');
        formData.append('item_id', itemId);
        formData.append('claimer_name', name);
        formData.append('claimer_email', email);
        formData.append('proof', proof);

        const response = await fetch('', {
          method: 'POST',
          body: formData
        });

        // Reload the page to show success message
        window.location.reload();

      } catch (error) {
        console.error('Error submitting claim:', error);
        alert('Error submitting claim. Please try again.');
      }
    }

    function openClaimModal() {
      <?php if (!$is_logged_in): ?>
        window.location.href = 'login?redirect=' + encodeURIComponent(window.location.href);
        return;
      <?php endif; ?>

      // Get the current item being viewed
      const itemId = document.getElementById('claimItemId').value;
      const item = allItems.find(i => i.id == itemId);

      <?php if ($is_logged_in): ?>
        // Check if user owns this item
        const userOwnsItem = <?php echo $user_id; ?> === parseInt(item.user_id);

        if (userOwnsItem) {
          // Show alert and don't open claim modal
          alert("You cannot claim your own item. Please contact admin if you need assistance.");
          return;
        }
      <?php endif; ?>

      // Only open claim modal for items user doesn't own
      const claimModal = new bootstrap.Modal(document.getElementById('claimModal'));
      claimModal.show();
    }

    function formatDate(dateString) {
      const date = new Date(dateString);
      const options = { year: 'numeric', month: 'short', day: 'numeric' };
      return date.toLocaleDateString('en-US', options);
    }

    function escapeHtml(unsafe) {
      return unsafe
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function () {
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(alert => {
        setTimeout(() => {
          alert.style.display = 'none';
        }, 5000);
      });
    });
  </script>
</body>

</html>