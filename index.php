<?php
session_start();


// Check if user is logged in
$is_logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true;
$user_name = $is_logged_in ? $_SESSION['user_name'] : '';
$user_id = $is_logged_in ? $_SESSION['user_id'] : '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Lost & Found - Reunite with Your Belongings</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <link rel="stylesheet" href="css/style.css">
    <style>
        .img-fluid {
            width: 80px;
            height: 80px;
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
    </style>
</head>

<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="index">
                <i class="bi bi-search-heart-fill text-primary fs-3 me-2"></i>
                <span class="fw-bold">Campus Lost & Found</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto align-items-center">
                    <li class="nav-item">
                        <a class="nav-link" href="pages/search">
                            <i class="bi bi-search me-1"></i>Browse Items
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#how-it-works">How It Works</a>
                    </li>

                    <?php if ($is_logged_in): ?>
                        <!-- Show these when user is logged in -->

                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle d-flex align-items-center user-dropdown px-3 rounded-pill"
                                href="#" role="button" data-bs-toggle="dropdown">
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
                                        href="<?php echo $_SESSION['role'] == 'admin' ? './admin/admin' : './user/dashboard'; ?>">
                                        <i class="bi bi-grid me-2"></i>Dashboard
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="pages/profile">
                                        <i class="bi bi-person-circle me-2"></i>My Profile
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item" href="pages/my-items">
                                        <i class="bi bi-bag me-2"></i>My Items
                                    </a>
                                </li>
                                <li>
                                    <hr class="dropdown-divider">
                                </li>
                                <li>
                                    <a class="dropdown-item text-danger" href="logout">
                                        <i class="bi bi-box-arrow-right me-2"></i>Logout
                                    </a>
                                </li>
                            </ul>
                        </li>
                    <?php else: ?>
                        <!-- Show these when user is NOT logged in -->
                        <li class="nav-item">
                            <a class="nav-link" href="pages/login">
                                <i class="bi bi-box-arrow-in-right me-1"></i>Login
                            </a>
                        </li>
                        <li class="nav-item ms-lg-2">
                            <a class="btn btn-primary btn-sm px-4" href="pages/register">
                                <i class="bi bi-person-plus me-1"></i>Sign Up
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-overlay"></div>
        <div class="container position-relative">
            <div class="row align-items-center min-vh-100 py-5">
                <div class="col-lg-8 mx-auto text-center text-white">
                    <h1 class="display-3 fw-bold mb-4 text-balance">Lost Something? We'll Help You Find It</h1>
                    <p class="lead mb-5 text-pretty">Connect with your campus community to recover lost items and return
                        found belongings. Simple, secure, and effective.</p>
                    <div class="d-flex flex-column flex-sm-row gap-3 justify-content-center">
                        <a href="pages/post" class="btn btn-primary btn-lg px-5 py-3">
                            <i class="bi bi-exclamation-circle me-2"></i>Report Lost Item
                        </a>
                        <a href="pages/search" class="btn btn-success btn-lg px-5 py-3">
                            <i class="bi bi-check-circle me-2"></i>Report Found Item
                        </a>
                    </div>

                    <!-- Stats -->
                    <div class="row mt-5 pt-4">
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="stat-card">
                                <h3 class="display-5 fw-bold mb-0" data-count="1247">0</h3>
                                <p class="mb-0">Items Reunited</p>
                            </div>
                        </div>
                        <div class="col-md-4 mb-3 mb-md-0">
                            <div class="stat-card">
                                <h3 class="display-5 fw-bold mb-0" data-count="3500">0</h3>
                                <p class="mb-0">Active Users</p>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="stat-card">
                                <h3 class="display-5 fw-bold mb-0" data-count="89">0</h3>
                                <p class="mb-0">Success Rate %</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section id="how-it-works" class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">How It Works</h2>
                <p class="lead text-muted">Three simple steps to reunite with your belongings</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div
                                class="feature-icon bg-primary bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4">
                                <i class="bi bi-pencil-square text-primary fs-1"></i>
                            </div>
                            <h4 class="fw-bold mb-3">1. Report Your Item</h4>
                            <p class="text-muted">Describe what you lost or found with details and photos. The more
                                information, the better!</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div
                                class="feature-icon bg-success bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4">
                                <i class="bi bi-search text-success fs-1"></i>
                            </div>
                            <h4 class="fw-bold mb-3">2. Search & Match</h4>
                            <p class="text-muted">Browse through reported items or get notified when a match is found
                                automatically.</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100 text-center p-4">
                        <div class="card-body">
                            <div
                                class="feature-icon bg-warning bg-opacity-10 rounded-circle d-inline-flex align-items-center justify-content-center mb-4">
                                <i class="bi bi-hand-thumbs-up text-warning fs-1"></i>
                            </div>
                            <h4 class="fw-bold mb-3">3. Claim & Reunite</h4>
                            <p class="text-muted">Verify ownership through our secure claim process and arrange a safe
                                pickup.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Success Stories -->
    <section class="py-5">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Success Stories</h2>
                <p class="lead text-muted">Real students, real reunions</p>
            </div>
            <div class="row g-4">
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="./images/user.jpg" alt="Student" class="rounded-circle me-3 img-fluid">
                                <div>
                                    <h6 class="mb-0 fw-bold">Sarah Johnson</h6>
                                    <small class="text-muted">Computer Science</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"Lost my laptop in the library during finals week. Found it
                                within 2 hours thanks to this platform! Absolutely lifesaving."</p>
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="./images/user.jpg" alt="Student" class="rounded-circle me-3 img-fluid">
                                <div>
                                    <h6 class="mb-0 fw-bold">Michael Chen</h6>
                                    <small class="text-muted">Business Administration</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"Found someone's wallet with their ID. Posted it here and the
                                owner contacted me the same day. Great system!"</p>
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <div class="d-flex align-items-center mb-3">
                                <img src="./images/user.jpg" alt="Student" class="rounded-circle me-3 img-fluid">
                                <div>
                                    <h6 class="mb-0 fw-bold">Emily Rodriguez</h6>
                                    <small class="text-muted">Engineering</small>
                                </div>
                            </div>
                            <p class="text-muted mb-3">"My textbooks were returned to me before I even realized they
                                were missing. This community is amazing!"</p>
                            <div class="text-warning">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories -->
    <section class="py-5 bg-light">
        <div class="container py-5">
            <div class="text-center mb-5">
                <h2 class="display-5 fw-bold mb-3">Popular Categories</h2>
                <p class="lead text-muted">Browse by item type</p>
            </div>
            <div class="row g-3">
                <div class="col-6 col-md-3">
                    <a href="search.html?category=electronics" class="text-decoration-none">
                        <div class="card border-0 shadow-sm text-center p-4 category-card">
                            <i class="bi bi-laptop fs-1 text-primary mb-3"></i>
                            <h6 class="fw-bold mb-0">Electronics</h6>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="search.html?category=accessories" class="text-decoration-none">
                        <div class="card border-0 shadow-sm text-center p-4 category-card">
                            <i class="bi bi-bag fs-1 text-success mb-3"></i>
                            <h6 class="fw-bold mb-0">Accessories</h6>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="search.html?category=documents" class="text-decoration-none">
                        <div class="card border-0 shadow-sm text-center p-4 category-card">
                            <i class="bi bi-file-text fs-1 text-warning mb-3"></i>
                            <h6 class="fw-bold mb-0">Documents</h6>
                        </div>
                    </a>
                </div>
                <div class="col-6 col-md-3">
                    <a href="search.html?category=clothing" class="text-decoration-none">
                        <div class="card border-0 shadow-sm text-center p-4 category-card">
                            <i class="bi bi-bag-check fs-1 text-danger mb-3"></i>
                            <h6 class="fw-bold mb-0">Clothing</h6>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="py-5 bg-primary text-white">
        <div class="container py-5 text-center">
            <h2 class="display-5 fw-bold mb-4">Ready to Get Started?</h2>
            <p class="lead mb-4">Join thousands of students helping each other every day</p>
            <a href="pages/register" class="btn btn-light btn-lg px-5 py-3">Create Free Account</a>
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="js/script2.js"></script>
</body>

</html>