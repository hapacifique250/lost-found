<?php
session_start();
require_once '../config/connection.php';

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: ./login");
    exit();
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'];
$user_email = $_SESSION['user_email'];

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Get form data
        $title = trim($_POST['item_name']);
        $category = trim($_POST['category']);
        $description = trim($_POST['description']);
        $location = trim($_POST['location_lost']);
        $date_lost = trim($_POST['date_lost']);
        $item_type = trim($_POST['item_type']);


        // Validate required fields
        if (empty($title) || empty($category) || empty($description) || empty($location) || empty($date_lost) || empty($item_type)) {
            throw new Exception("All required fields must be filled");
        }

        // Handle file upload
        $image_url = null;
        if (isset($_FILES['item_images']) && !empty($_FILES['item_images']['name'][0])) {
            $upload_dir = '../uploads/';

            // Create uploads directory if it doesn't exist
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }

            $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
            $max_size = 5 * 1024 * 1024; // 5MB

            // Process the first file only
            $file = $_FILES['item_images'];
            if ($file['error'][0] === UPLOAD_ERR_OK) {
                $file_type = $file['type'][0];
                $file_size = $file['size'][0];

                // Validate file type and size
                if (!in_array($file_type, $allowed_types)) {
                    throw new Exception("Invalid file type. Only JPG, PNG, GIF, and WebP are allowed.");
                }

                if ($file_size > $max_size) {
                    throw new Exception("File size too large. Maximum size is 5MB.");
                }

                // Generate unique filename
                $file_extension = pathinfo($file['name'][0], PATHINFO_EXTENSION);
                $filename = uniqid() . '_' . time() . '.' . $file_extension;
                $file_path = $upload_dir . $filename;

                // Move uploaded file
                if (move_uploaded_file($file['tmp_name'][0], $file_path)) {
                    $image_url = '/uploads/' . $filename;
                } else {
                    throw new Exception("Failed to upload image.");
                }
            }
        }

        // Insert into database - CORRECTED VERSION
        $stmt = $pdo->prepare("
            INSERT INTO items 
            (user_id, title, category, description, location, date, image_url, type, status) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')
        ");

        // Execute the query with CORRECT ORDER and NUMBER of parameters
        $stmt->execute([
            $user_id,      // user_id
            $title,        // title
            $category,     // category
            $description,  // description
            $location,     // location
            $date_lost,    // date
            $image_url,    // image_url
            $item_type,     // type
        ]);

        $item_id = $pdo->lastInsertId();

        $success_message = "Your lost item has been reported successfully!";
        header("Location:./search");

        // Clear form data
        $_POST = array();

    } catch (Exception $e) {
        $error_message = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Report Lost Item - Campus Lost & Found</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .post-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            padding: 2rem 0;
        }

        .post-header {
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a6f 100%);
            color: white;
            padding: 3rem 0;
            margin-bottom: 2rem;
        }

        .post-card {
            border-radius: 12px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            margin-bottom: 2rem;
            background: white;
        }

        .form-section {
            padding: 2rem;
        }

        .section-title {
            border-bottom: 2px solid #e9ecef;
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            font-weight: 600;
            color: #2c5aa0;
            font-family: 'Poppins', sans-serif;
        }

        .image-upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: #f8f9fa;
        }

        .image-upload-area:hover {
            border-color: #2c5aa0;
            background: #e3f2fd;
        }

        .image-upload-area.dragover {
            border-color: #2c5aa0;
            background: #bbdefb;
        }

        .image-preview {
            display: none;
            margin-top: 1rem;
        }

        .preview-image {
            max-width: 150px;
            max-height: 150px;
            border-radius: 8px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
        }

        .progress-bar {
            height: 6px;
            background: #e9ecef;
            border-radius: 3px;
            margin-top: 0.5rem;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #2c5aa0 0%, #1e3a6f 100%);
            width: 0%;
            transition: width 0.3s ease;
        }

        .character-count {
            font-size: 0.875rem;
            color: #6c757d;
            font-family: 'Poppins', sans-serif;
        }

        .character-count.warning {
            color: #ffc107;
        }

        .character-count.danger {
            color: #dc3545;
        }

        .required-field::after {
            content: " *";
            color: #dc3545;
        }

        .user-info-badge {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
        }

        body.dark-mode .post-container {
            background: linear-gradient(135deg, #1a1a1a 0%, #2d2d2d 100%);
        }

        body.dark-mode .post-card {
            background: #2d2d2d;
        }

        body.dark-mode .image-upload-area {
            background: #2d2d2d;
            border-color: #444;
        }

        body.dark-mode .image-upload-area:hover {
            background: #3a3a3a;
            border-color: #2c5aa0;
        }

        body.dark-mode .section-title {
            border-bottom-color: #444;
            color: #4a7bc8;
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
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
        <div class="container">
            <a class="navbar-brand d-flex align-items-center" href="../">
                <i class="bi bi-search-heart-fill text-primary fs-3 me-2"></i>
                <span class="fw-bold" style="font-family: 'Poppins', sans-serif;">Campus Lost & Found</span>
            </a>

            <div class="navbar-nav ms-auto align-items-center">
                <!-- Left side navigation items -->
                <div class="d-flex align-items-center">
                    <a href="../" class="nav-link me-3">
                        <i class="bi bi-arrow-left me-1"></i>Back to Home
                    </a>
                    <a href="search" class="nav-link me-3">
                        <i class="bi bi-search me-1"></i>Browse Items
                    </a>

                    <!-- User Dropdown -->
                    <div class="nav-item dropdown">
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
                                    href="<?php echo $_SESSION['role'] == 'admin' ? '../admin/admin' : '../user/dashboard'; ?>">
                                    <i class="bi bi-grid me-2"></i>Dashboard
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
                                    <i class="bi bi-person-circle me-2"></i>My Profile
                                </a>
                            </li>
                            <li>
                                <a class="dropdown-item" href="#">
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
                </div>
            </div>
        </div>
    </nav>

    <!-- Header -->
    <div class="post-header">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="display-5 fw-bold mb-3" style="font-family: 'Poppins', sans-serif;">
                        <i class="bi bi-exclamation-triangle-fill me-3 text-danger"></i>
                        Report Lost Item
                    </h1>
                    <p class="lead mb-0" style="font-family: 'Poppins', sans-serif;">
                        Help us help you find your lost belongings. Provide as much detail as possible.
                    </p>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="bg-white bg-opacity-20 rounded-pill px-4 py-2 d-inline-block text-primary">
                        <small style="font-family: 'Poppins', sans-serif;">
                            <i class="bi bi-lightning-fill me-1 text-primary"></i>Quick & Secure Process
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <section class="post-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-10">
                    <!-- Alert Container -->
                    <div id="postAlert">
                        <?php if ($success_message): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="bi bi-check-circle-fill me-2"></i><?php echo $success_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>

                        <?php if ($error_message): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="bi bi-exclamation-triangle-fill me-2"></i><?php echo $error_message; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Main Form -->
                    <div class="card post-card">
                        <form id="postItemForm" method="POST" action="" enctype="multipart/form-data" novalidate>
                            <!-- Item Details Section -->
                            <div class="form-section">
                                <h4 class="section-title" style="font-family: 'Poppins', sans-serif;">
                                    <i class="bi bi-tag me-2"></i>Item Details
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="itemName" class="form-label fw-semibold required-field"
                                            style="font-family: 'Poppins', sans-serif;">Item Name</label>
                                        <input type="text" class="form-control" id="itemName" name="item_name"
                                            placeholder="e.g., Black iPhone 13, Calculus Textbook" required
                                            value="<?php echo isset($_POST['item_name']) ? htmlspecialchars($_POST['item_name']) : ''; ?>"
                                            style="font-family: 'Poppins', sans-serif;">
                                        <div class="form-text" style="font-family: 'Poppins', sans-serif;">Be specific
                                            about the item name and brand</div>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="itemCategory" class="form-label fw-semibold required-field"
                                            style="font-family: 'Poppins', sans-serif;">Category</label>
                                        <select class="form-select" id="itemCategory" name="category" required
                                            style="font-family: 'Poppins', sans-serif;">
                                            <option value="">Select a category</option>
                                            <option value="Electronics" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Electronics') ? 'selected' : ''; ?>>📱 Electronics
                                            </option>
                                            <option value="Books" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Books') ? 'selected' : ''; ?>>📚 Books & Stationery
                                            </option>
                                            <option value="Bags" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Bags') ? 'selected' : ''; ?>>🎒 Bags & Backpacks
                                            </option>
                                            <option value="Clothing" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Clothing') ? 'selected' : ''; ?>>👕 Clothing
                                            </option>
                                            <option value="Accessories" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Accessories') ? 'selected' : ''; ?>>🕶️ Accessories
                                            </option>
                                            <option value="ID Cards" <?php echo (isset($_POST['category']) && $_POST['category'] == 'ID Cards') ? 'selected' : ''; ?>>🪪 ID Cards &
                                                Documents</option>
                                            <option value="Keys" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Keys') ? 'selected' : ''; ?>>🔑 Keys</option>
                                            <option value="Jewelry" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Jewelry') ? 'selected' : ''; ?>>💍 Jewelry</option>
                                            <option value="Sports" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Sports') ? 'selected' : ''; ?>>⚽ Sports Equipment
                                            </option>
                                            <option value="Other" <?php echo (isset($_POST['category']) && $_POST['category'] == 'Other') ? 'selected' : ''; ?>>❓ Other</option>
                                        </select>
                                    </div>

                                    <div class="col-12">
                                        <label for="itemDescription" class="form-label fw-semibold required-field"
                                            style="font-family: 'Poppins', sans-serif;">Description</label>
                                        <textarea class="form-control" id="itemDescription" name="description" rows="4"
                                            placeholder="Describe your item in detail. Include brand, color, size, unique features, contents, etc."
                                            maxlength="500" required
                                            style="font-family: 'Poppins', sans-serif;"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                                        <div class="d-flex justify-content-between mt-1">
                                            <div class="form-text" style="font-family: 'Poppins', sans-serif;">Include
                                                any identifying marks or damage</div>
                                            <div class="character-count" id="descriptionCount">0/500</div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Loss Details Section -->
                            <div class="form-section border-top">
                                <h4 class="section-title" style="font-family: 'Poppins', sans-serif;">
                                    <i class="bi bi-geo-alt me-2"></i>Loss or Found Details
                                </h4>

                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label for="locationLost" class="form-label fw-semibold required-field"
                                            style="font-family: 'Poppins', sans-serif;">Where did you lose/found
                                            it?</label>
                                        <select class="form-select" id="locationLost" name="location_lost" required
                                            style="font-family: 'Poppins', sans-serif;">
                                            <option value="">Select location</option>
                                            <option value="Library" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Library') ? 'selected' : ''; ?>>🏛️ Library
                                            </option>
                                            <option value="Cafeteria" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Cafeteria') ? 'selected' : ''; ?>>🍽️
                                                Cafeteria</option>
                                            <option value="Student Center" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Student Center') ? 'selected' : ''; ?>>🏢
                                                Student Center</option>
                                            <option value="Classroom" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Classroom') ? 'selected' : ''; ?>>📚 Classroom
                                            </option>
                                            <option value="Gym" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Gym') ? 'selected' : ''; ?>>🏀 Gym</option>
                                            <option value="Parking Lot" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Parking Lot') ? 'selected' : ''; ?>>🚗 Parking
                                                Lot</option>
                                            <option value="Dormitory" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Dormitory') ? 'selected' : ''; ?>>🏠 Dormitory
                                            </option>
                                            <option value="Sports Field" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Sports Field') ? 'selected' : ''; ?>>⚽ Sports
                                                Field</option>
                                            <option value="Auditorium" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Auditorium') ? 'selected' : ''; ?>>🎭
                                                Auditorium</option>
                                            <option value="Computer Lab" <?php echo (isset($_POST['location_lost']) && $_POST['location_lost'] == 'Computer Lab') ? 'selected' : ''; ?>>💻
                                                Computer Lab</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="dateLost" class="form-label fw-semibold required-field"
                                            style="font-family: 'Poppins', sans-serif;">When did you lose/find
                                            it?</label>
                                        <input type="datetime-local" class="form-control" id="dateLost" name="date_lost"
                                            required
                                            value="<?php echo isset($_POST['date_lost']) ? htmlspecialchars($_POST['date_lost']) : ''; ?>"
                                            style="font-family: 'Poppins', sans-serif;">
                                    </div>

                                    <div class="col-12">
                                        <label for="itemType" class="form-label fw-semibold"
                                            style="font-family: 'Poppins', sans-serif;">
                                            Item Type <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="itemType" name="item_type" required
                                            style="font-family: 'Poppins', sans-serif;">
                                            <option value="">Select Item Type</option>
                                            <option value="lost" <?php echo (isset($_POST['item_type']) && $_POST['item_type'] == 'lost') ? 'selected' : ''; ?>>
                                                🏷️ Lost Item
                                            </option>
                                            <option value="found" <?php echo (isset($_POST['item_type']) && $_POST['item_type'] == 'found') ? 'selected' : ''; ?>>
                                                🔍 Found Item
                                            </option>
                                        </select>
                                    </div>

                                </div>
                            </div>

                            <!-- Image Upload Section -->
                            <div class="form-section border-top">
                                <h4 class="section-title" style="font-family: 'Poppins', sans-serif;">
                                    <i class="bi bi-image me-2"></i>Item Photos
                                </h4>

                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="image-upload-area" id="imageUploadArea">
                                            <i class="bi bi-cloud-arrow-up fs-1 text-muted mb-3"></i>
                                            <h5 class="mb-2" style="font-family: 'Poppins', sans-serif;">Upload Item
                                                Photos</h5>
                                            <p class="text-muted mb-3" style="font-family: 'Poppins', sans-serif;">Drag
                                                & drop images here or click to browse</p>
                                            <input type="file" id="itemImage" name="item_images[]" accept="image/*"
                                                multiple class="d-none">
                                            <button type="button" class="btn btn-outline-primary"
                                                onclick="document.getElementById('itemImage').click()"
                                                style="font-family: 'Poppins', sans-serif;">
                                                <i class="bi bi-folder2-open me-2"></i>Choose Files
                                            </button>
                                            <div class="form-text mt-2" style="font-family: 'Poppins', sans-serif;">Max
                                                5 images • JPG, PNG • 5MB each</div>
                                        </div>

                                        <div class="image-preview" id="imagePreview">
                                            <div class="d-flex flex-wrap gap-3" id="previewContainer"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Actions -->
                            <div class="form-section border-top bg-light">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <div class="form-text" style="font-family: 'Poppins', sans-serif;">
                                            <i class="bi bi-shield-check me-1 text-warning"></i>
                                            Your information is secure and will only be shared with potential finders
                                        </div>
                                    </div>
                                    <div class="col-md-6 text-md-end">
                                        <button type="button" class="btn btn-outline-secondary me-2"
                                            onclick="resetForm()" style="font-family: 'Poppins', sans-serif;">
                                            <i class="bi bi-arrow-clockwise me-1"></i>Reset
                                        </button>
                                        <button type="submit" class="btn btn-primary px-4" id="submitBtn"
                                            style="font-family: 'Poppins', sans-serif;">
                                            <i class="bi bi-send me-2"></i>Report Lost Item
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Your existing footer and scripts -->
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
</body>

</html>