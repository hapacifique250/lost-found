<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login');
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];

// Get all notifications
$notificationsStmt = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE user_id = ? 
    ORDER BY created_at DESC
");
$notificationsStmt->execute([$user_id]);
$allNotifications = $notificationsStmt->fetchAll(PDO::FETCH_ASSOC);

// Mark all as read when page loads
$markReadStmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
$markReadStmt->execute([$user_id]);

// Time helper function
function time_elapsed_string($datetime, $full = false) {
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

    if (!$full) $string = array_slice($string, 0, 1);
    return $string ? implode(', ', $string) . ' ago' : 'just now';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Campus Lost & Found</title>
    <link rel="icon" type="image/x-icon" href="../assets/favicon.ico">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
            transition: all 0.3s ease;
        }

        .notification-item {
            border-left: 4px solid transparent;
            transition: all 0.3s ease;
        }

        .notification-item:hover {
            background-color: #f8f9fa;
            transform: translateX(5px);
        }

        .notification-info {
            border-left-color: #0dcaf0;
        }

        .notification-success {
            border-left-color: #198754;
        }

        .notification-warning {
            border-left-color: #ffc107;
        }

        .notification-danger {
            border-left-color: #dc3545;
        }

        .badge-type {
            font-size: 0.7rem;
            padding: 0.3em 0.6em;
        }

        .empty-state {
            padding: 3rem 1rem;
        }

        .empty-state i {
            font-size: 4rem;
            opacity: 0.5;
        }

        .mark-all-read {
            cursor: pointer;
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
                        <a href="../user/dashboard" class="btn btn-outline-primary btn-sm me-2">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../logout" class="btn btn-outline-danger btn-sm">
                            <i class="bi bi-box-arrow-right"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Notifications Header -->
    <section class="py-4 bg-light">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="fw-bold mb-2">Notifications</h1>
                    <p class="text-muted mb-0">Stay updated with your item claims and activities</p>
                </div>
                <div class="col-md-4 text-md-end">
                    <?php if (!empty($allNotifications)): ?>
                        <button class="btn btn-outline-primary" onclick="clearAllNotifications()">
                            <i class="bi bi-check-all me-2"></i>Mark All as Read
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Notifications Content -->
    <section class="py-4">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <?php if (empty($allNotifications)): ?>
                                <!-- Empty State -->
                                <div class="empty-state text-center">
                                    <i class="bi bi-bell-slash text-muted"></i>
                                    <h4 class="text-muted mt-3">No Notifications</h4>
                                    <p class="text-muted">You're all caught up! You'll see notifications here when you have new activity.</p>
                                    <a href="../pages/dashboard" class="btn btn-primary mt-3">
                                        <i class="bi bi-speedometer2 me-2"></i>Go to Dashboard
                                    </a>
                                </div>
                            <?php else: ?>
                                <!-- Notifications List -->
                                <div class="d-flex justify-content-between align-items-center mb-4">
                                    <h5 class="card-title mb-0">All Notifications</h5>
                                    <span class="badge bg-primary"><?php echo count($allNotifications); ?> total</span>
                                </div>

                                <div class="list-group list-group-flush">
                                    <?php foreach ($allNotifications as $notification): ?>
                                        <?php
                                        $type_class = '';
                                        $type_badge = '';
                                        switch ($notification['type']) {
                                            case 'success':
                                                $type_class = 'notification-success';
                                                $type_badge = 'bg-success';
                                                break;
                                            case 'warning':
                                                $type_class = 'notification-warning';
                                                $type_badge = 'bg-warning';
                                                break;
                                            case 'danger':
                                                $type_class = 'notification-danger';
                                                $type_badge = 'bg-danger';
                                                break;
                                            default:
                                                $type_class = 'notification-info';
                                                $type_badge = 'bg-info';
                                        }
                                        ?>
                                        <a href="<?php echo $notification['action_url'] ? htmlspecialchars($notification['action_url']) : '#'; ?>" 
                                           class="list-group-item list-group-item-action notification-item <?php echo $type_class; ?> p-4">
                                            <div class="d-flex justify-content-between align-items-start mb-2">
                                                <div class="d-flex align-items-center">
                                                    <span class="badge <?php echo $type_badge; ?> badge-type me-2">
                                                        <?php echo ucfirst($notification['type']); ?>
                                                    </span>
                                                    <h6 class="mb-0 fw-bold"><?php echo htmlspecialchars($notification['title']); ?></h6>
                                                </div>
                                                <small class="text-muted"><?php echo time_elapsed_string($notification['created_at']); ?></small>
                                            </div>
                                            <p class="mb-0 text-muted"><?php echo htmlspecialchars($notification['message']); ?></p>
                                        </a>
                                    <?php endforeach; ?>
                                </div>

                                <!-- Clear All Button -->
                                <div class="text-center mt-4">
                                    <button class="btn btn-outline-danger btn-sm" onclick="clearAllNotifications()">
                                        <i class="bi bi-trash me-2"></i>Clear All Notifications
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Help Section -->
                    <div class="card border-0 shadow-sm mt-4">
                        <div class="card-body p-4">
                            <h6 class="fw-bold mb-3">
                                <i class="bi bi-info-circle text-primary me-2"></i>About Notifications
                            </h6>
                            <div class="row">
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-2"></i>
                                                You'll be notified when someone claims your item
                                            </small>
                                        </li>
                                        <li class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-2"></i>
                                                Get updates on your claim requests
                                            </small>
                                        </li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <ul class="list-unstyled">
                                        <li class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-2"></i>
                                                Receive important system announcements
                                            </small>
                                        </li>
                                        <li class="mb-2">
                                            <small class="text-muted">
                                                <i class="bi bi-check-circle text-success me-2"></i>
                                                Stay informed about item status changes
                                            </small>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5 mt-5">
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
                        <li class="mb-2"><a href="../pages/post" class="text-white-50 text-decoration-none">Post Item</a></li>
                        <li class="mb-2"><a href="../pages/dashboard" class="text-white-50 text-decoration-none">Dashboard</a></li>
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
                <p class="mb-0">&copy; 2025 Campus Lost & Found. All rights reserved. | 
                   <a href="#" class="text-white-50">Privacy Policy</a> | 
                   <a href="#" class="text-white-50">Terms of Service</a>
                </p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Clear all notifications
        function clearAllNotifications() {
            if (confirm('Are you sure you want to clear all notifications? This action cannot be undone.')) {
                fetch('../actions/clear-notifications', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Show success message
                        showAlert('All notifications cleared successfully!', 'success');
                        // Reload page after a short delay
                        setTimeout(() => {
                            window.location.reload();
                        }, 1500);
                    } else {
                        showAlert('Error clearing notifications: ' + data.message, 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showAlert('Error clearing notifications', 'error');
                });
            }
        }

        // Show alert message
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

        // Auto-refresh notification count in navbar (if user navigates back)
        function updateNotificationBadge() {
            fetch('../actions/get-notification-count')
            .then(response => response.json())
            .then(data => {
                // This would update the badge count if user goes back to dashboard
                console.log('Unread notifications:', data.unread_count);
            });
        }

        // Update badge when page loads
        updateNotificationBadge();
    </script>
</body>

</html>