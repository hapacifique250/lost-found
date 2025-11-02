<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Get current user data
$stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$error = '';
$success = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    // Validation
    if (empty($name)) {
        $error = 'Full name is required.';
    } elseif (empty($email)) {
        $error = 'Email address is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        try {
            // Check if email already exists (excluding current user)
            $emailCheckStmt = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $emailCheckStmt->execute([$email, $user_id]);
            
            if ($emailCheckStmt->fetch()) {
                $error = 'This email address is already registered.';
            } else {
                // Update user profile
                $updateStmt = $pdo->prepare("UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?");
                $updateStmt->execute([$name, $email, $phone, $user_id]);
                
                // Update session data
                $_SESSION['user_name'] = $name;
                $_SESSION['user_email'] = $email;
                
                $success = 'Profile updated successfully!';
                
                // Refresh user data
                $user['name'] = $name;
                $user['email'] = $email;
                $user['phone'] = $phone;
            }
        } catch (PDOException $e) {
            $error = 'Error updating profile: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Campus Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .profile-card {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 2rem;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        .btn-primary:hover {
            background: linear-gradient(135deg, #764ba2 0%, #667eea 100%);
            transform: translateY(-1px);
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../">
                <i class="bi bi-search-heart-fill text-primary fs-3 me-2"></i>
                <span class="fw-bold">Campus Lost & Found</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a href="../user/dashboard" class="btn btn-outline-secondary btn-sm me-2">
                    <i class="bi bi-arrow-left me-1"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <section class="py-5 bg-light min-vh-100">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-8 col-lg-6">
                    <div class="card profile-card">
                        <div class="profile-header text-center">
                            <div class="bg-white bg-opacity-20 rounded-circle d-inline-flex p-3 mb-3">
                                <i class="bi bi-person-gear fs-1"></i>
                            </div>
                            <h3 class="fw-bold mb-2">Edit Profile</h3>
                            <p class="mb-0 opacity-75">Update your personal information</p>
                        </div>
                        
                        <div class="card-body p-4">
                            <?php if ($error): ?>
                                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                    <i class="bi bi-exclamation-triangle me-2"></i><?php echo $error; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($success): ?>
                                <div class="alert alert-success alert-dismissible fade show" role="alert">
                                    <i class="bi bi-check-circle me-2"></i><?php echo $success; ?>
                                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                                </div>
                            <?php endif; ?>

                            <form method="POST" id="profileForm">
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <label for="name" class="form-label fw-semibold">Full Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control form-control-lg" id="name" name="name" 
                                               value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        <div class="form-text">Your full name as you'd like it to appear.</div>
                                    </div>
                                    
                                    <div class="col-12 mb-3">
                                        <label for="email" class="form-label fw-semibold">Email Address <span class="text-danger">*</span></label>
                                        <input type="email" class="form-control form-control-lg" id="email" name="email" 
                                               value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        <div class="form-text">We'll never share your email with anyone else.</div>
                                    </div>
                                    
                                    <div class="col-12 mb-4">
                                        <label for="phone" class="form-label fw-semibold">Phone Number</label>
                                        <input type="tel" class="form-control form-control-lg" id="phone" name="phone" 
                                               value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" 
                                               placeholder="Enter your phone number">
                                        <div class="form-text">Optional - for contact purposes only.</div>
                                    </div>
                                    
                                    <div class="col-12">
                                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                            <a href="../user/dashboard" class="btn btn-outline-secondary btn-lg me-md-2">
                                                <i class="bi bi-x-circle me-2"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-primary btn-lg">
                                                <i class="bi bi-check-circle me-2"></i> Update Profile
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Security Note -->
                    <div class="text-center mt-4">
                        <p class="text-muted">
                            <i class="bi bi-shield-check me-1"></i>
                            Your information is secure and protected.
                            <a href="change-password" class="text-decoration-none">Change your password</a> for additional security.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
    <script>
        // Form validation
        document.getElementById('profileForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            
            if (!name) {
                e.preventDefault();
                alert('Please enter your full name.');
                document.getElementById('name').focus();
                return;
            }
            
            if (!email) {
                e.preventDefault();
                alert('Please enter your email address.');
                document.getElementById('email').focus();
                return;
            }
            
            if (!this.checkValidity()) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>