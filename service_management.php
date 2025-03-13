<?php
// Include database configuration
require_once 'config.php';
require_once 'functions.php';
require_once 'handle_image_upload.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Manual login check if requireOwnerLogin() is not defined
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
    // Store the attempted page for potential redirect after login
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
    
    // Store an error message to show on login page
    $_SESSION['error_message'] = "You must be a service provider to access this page.";
    
    // Redirect to login page
    header("Location: index.php");
    exit();
}

// Get current user data with improved error handling
$user = getCurrentUser($db);
if (!$user) {
    // Try to fetch user data directly as a fallback
    try {
        if (isset($_SESSION['user_id'])) {
            $stmt = $db->prepare("SELECT id, name, email, role, phone, address, business_name FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        error_log("Error fetching user data: " . $e->getMessage());
    }
    
    // If still no user data, use default values
    if (!$user) {
        $user = [
            'name' => $_SESSION['user_name'] ?? 'Unknown User',
            'business_name' => '',
            'role' => $_SESSION['user_role'] ?? 'owner'
        ];
    }
}

// Rest of the existing service_management.php code follows...
// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_service'])) {
        // Handle add service form
        $categoryId = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $availabilityStatus = $_POST['availability_status'];
        
        // UPDATED: Set is_available based on availability status
        $isAvailable = (in_array($availabilityStatus, ['Available', 'Limited', 'Coming Soon'])) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Service name is required";
        }
        
        if ($price <= 0) {
            $errors[] = "Price must be greater than zero";
        }
        
        if (empty($availabilityStatus)) {
            $errors[] = "Availability status is required";
        }
        
        if (empty($errors)) {
            // Handle image upload
            $image = 'default-service.jpg'; // Default image
            
            if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadedImage = processImageUpload($_FILES['service_image']);
                if ($uploadedImage) {
                    $image = $uploadedImage;
                } else {
                    // If image upload failed but we don't have other errors, add this one
                    $errors[] = "Failed to upload image. Please check file format and size.";
                }
            }
        }
        
        if (empty($errors)) {
            try {
                // Add owner_id to the INSERT query
                $stmt = $db->prepare("INSERT INTO services (owner_id, category_id, name, description, price, image, is_available, availability_status) 
                                      VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $result = $stmt->execute([$_SESSION['user_id'], $categoryId, $name, $description, $price, $image, $isAvailable, $availabilityStatus]);
                
                if ($result) {
                    $serviceId = $db->lastInsertId();
                    $ownerId = $_SESSION['user_id'];
                    
                    // Create notifications for the new service
                    if (function_exists('createServiceNotificationMultiOwner')) {
                        createServiceNotificationMultiOwner($db, $serviceId, $ownerId);
                    } else {
                        // Fallback if the multi-owner function doesn't exist
                        // Get service information
                        $serviceStmt = $db->prepare("SELECT name, price FROM services WHERE id = ?");
                        $serviceStmt->execute([$serviceId]);
                        $service = $serviceStmt->fetch();
                        
                        // Create notification for the owner
                        createNotification(
                            $db,
                            $ownerId, 
                            'service',
                            'New Service Added',
                            "Your service '{$service['name']}' has been added at price " . number_format($service['price'], 2),
                            $serviceId,
                            $ownerId
                        );
                                  
                        // Create notification for admins
                        $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
                        $adminStmt->execute();
                        $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
                        
                        foreach ($adminUsers as $adminId) {
                            createNotification(
                                $db, 
                                $adminId, 
                                'service', 
                                'New Service Added', 
                                "A new service '{$service['name']}' has been added at price " . number_format($service['price'], 2), 
                                $serviceId
                            );
                        }
                    }
                    
                    $_SESSION['success_message'] = "Service added successfully!";
                    header("Location: service_management.php");
                    exit();
                } else {
                    $_SESSION['error_message'] = "Error adding service. Please try again.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        // Redirect to avoid form resubmission
        header("Location: service_management.php");
        exit();
    }
    
    if (isset($_POST['update_service'])) {
        // Handle update service form
        $serviceId = $_POST['service_id'];
        $categoryId = $_POST['category_id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $price = floatval($_POST['price']);
        $availabilityStatus = $_POST['availability_status'];
        
        // UPDATED: Set is_available based on availability status
        $isAvailable = (in_array($availabilityStatus, ['Available', 'Limited', 'Coming Soon'])) ? 1 : 0;
        
        // Validate input
        $errors = [];
        
        if (empty($name)) {
            $errors[] = "Service name is required";
        }
        
        if ($price <= 0) {
            $errors[] = "Price must be greater than zero";
        }
        
        if (empty($availabilityStatus)) {
            $errors[] = "Availability status is required";
        }

        // Check if service belongs to current user
        if (empty($errors)) {
            $serviceCheckStmt = $db->prepare("SELECT COUNT(*) FROM services WHERE id = ? AND owner_id = ?");
            $serviceCheckStmt->execute([$serviceId, $_SESSION['user_id']]);
            if ($serviceCheckStmt->fetchColumn() == 0) {
                $errors[] = "You don't have permission to edit this service";
                $_SESSION['error_message'] = implode("<br>", $errors);
                header("Location: service_management.php");
                exit();
            }
        }
        
        if (empty($errors)) {
            try {
              // Get current image
$imageStmt = $db->prepare("SELECT image FROM services WHERE id = ? AND owner_id = ?");
$imageStmt->execute([$serviceId, $_SESSION['user_id']]);
$currentImage = $imageStmt->fetch(PDO::FETCH_ASSOC);

if (!$currentImage) {
    $_SESSION['error_message'] = "Service not found or you don't have permission to edit it.";
    header("Location: service_management.php");
    exit();
}

// Check if the image key exists before accessing it
$imageName = isset($currentImage['image']) ? $currentImage['image'] : 'default-service.jpg';

// Handle image upload
$image = $imageName; // Use the validated image name instead of direct array access

if (isset($_FILES['service_image']) && $_FILES['service_image']['error'] !== UPLOAD_ERR_NO_FILE) {
    $uploadedImage = processImageUpload($_FILES['service_image']);
    if ($uploadedImage) {
        $image = $uploadedImage;
        
        // Delete old image if it's not the default - FIXED: use $imageName instead of $currentImage
        if ($imageName !== 'default-service.jpg' && file_exists('images/' . $imageName)) {
            unlink('images/' . $imageName);
        }
    } else {
        // If image upload failed but we don't have other errors, add this one
        $errors[] = "Failed to upload image. Please check file format and size.";
    }
}
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
                header("Location: service_management.php");
                exit();
            }
        }
        
        if (empty($errors)) {
            try {
                // Add owner_id check to the UPDATE query
                $stmt = $db->prepare("UPDATE services SET 
                                      category_id = ?, 
                                      name = ?, 
                                      description = ?, 
                                      price = ?, 
                                      image = ?,
                                      is_available = ?,
                                      availability_status = ?,
                                      updated_at = NOW()
                                      WHERE id = ? AND owner_id = ?");
                $result = $stmt->execute([
                    $categoryId, $name, $description, $price, $image, 
                    $isAvailable, $availabilityStatus, $serviceId, $_SESSION['user_id']
                ]);
                
                if ($result) {
                    // Create notification for other admin users about updated service
                    // Get all admin users except current one
                    $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' AND id != ?");
                    $adminStmt->execute([$_SESSION['user_id']]);
                    $admins = $adminStmt->fetchAll();
                    
                    foreach ($admins as $admin) {
                        createNotification(
                            $db,
                            $admin['id'],
                            'service',
                            'Service Updated',
                            "Service \"$name\" has been updated",
                            $serviceId
                        );
                    }
                    
                    $_SESSION['success_message'] = "Service updated successfully!";
                } else {
                    $_SESSION['error_message'] = "Failed to update service.";
                }
            } catch (PDOException $e) {
                $_SESSION['error_message'] = "Database error: " . $e->getMessage();
            }
        } else {
            $_SESSION['error_message'] = implode("<br>", $errors);
        }
        
        // Redirect to avoid form resubmission
        header("Location: service_management.php");
        exit();
    }
    
    if (isset($_POST['delete_service'])) {
        // Handle delete service form
        $serviceId = $_POST['service_id'];
        
        try {
            // Get the service details before deleting, checking owner
            $serviceStmt = $db->prepare("SELECT name, image FROM services WHERE id = ? AND owner_id = ?");
            $serviceStmt->execute([$serviceId, $_SESSION['user_id']]);
            $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$service) {
                $_SESSION['error_message'] = "Service not found or you don't have permission to delete it.";
                header("Location: service_management.php");
                exit();
            }
            
            $serviceName = $service['name'];
            $imageFile = $service['image'];
            
            // Check if service is used in any bookings
            $bookingCheckStmt = $db->prepare("SELECT COUNT(*) FROM booking_items WHERE service_id = ?");
            $bookingCheckStmt->execute([$serviceId]);
            $bookingsCount = $bookingCheckStmt->fetchColumn();
            
            if ($bookingsCount > 0) {
                $_SESSION['error_message'] = "Cannot delete service. It is used in $bookingsCount booking(s).";
                header("Location: service_management.php");
                exit();
            }
            
            // Delete the service
            $stmt = $db->prepare("DELETE FROM services WHERE id = ? AND owner_id = ?");
            $result = $stmt->execute([$serviceId, $_SESSION['user_id']]);
            
            if ($result) {
                // Delete the image file if it's not the default
                if ($imageFile !== 'default-service.jpg' && file_exists('images/' . $imageFile)) {
                    unlink('images/' . $imageFile);
                }
                
                // Create notification for all admin users about deleted service
                // Get all admin users except current one
                $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin' AND id != ?");
                $adminStmt->execute([$_SESSION['user_id']]);
                $admins = $adminStmt->fetchAll();
                
                foreach ($admins as $admin) {
                    createNotification(
                        $db,
                        $admin['id'],
                        'service',
                        'Service Deleted',
                        "Service \"$serviceName\" has been deleted from the system",
                        null
                    );
                }
                
                $_SESSION['success_message'] = "Service deleted successfully!";
            } else {
                $_SESSION['error_message'] = "Failed to delete service.";
            }
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "Database error: " . $e->getMessage();
        }
        
        // Redirect to avoid form resubmission
        header("Location: service_management.php");
        exit();
    }
}

// Get services data with pagination - UPDATED to filter by owner_id
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$limit = 9; // Number of services per page
$offset = ($page - 1) * $limit;

// Search functionality
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$searchCondition = 'WHERE s.owner_id = ?'; // Always filter by owner
$searchParams = [$_SESSION['user_id']];

if (!empty($search)) {
    $searchCondition .= " AND (s.name LIKE ? OR s.description LIKE ? OR c.name LIKE ?)";
    $searchTerm = "%$search%";
    $searchParams[] = $searchTerm;
    $searchParams[] = $searchTerm;
    $searchParams[] = $searchTerm;
}

// Get total number of services (for pagination)
$countQuery = "SELECT COUNT(*) FROM services s JOIN categories c ON s.category_id = c.id $searchCondition";
$countStmt = $db->prepare($countQuery);
$countStmt->execute($searchParams);
$totalServices = $countStmt->fetchColumn();
$totalPages = ceil($totalServices / $limit);

// Get services for current page
$query = "
    SELECT s.*, c.name as category_name 
    FROM services s
    JOIN categories c ON s.category_id = c.id
    $searchCondition
    ORDER BY s.updated_at DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($searchParams);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all categories for dropdown - UPDATED to filter by owner_id and prevent duplicates
$categoriesStmt = $db->prepare("
    SELECT c.id, c.name 
    FROM categories c
    WHERE c.owner_id = ?
    GROUP BY c.name  -- Group by name to prevent duplicates
    ORDER BY c.name
");
$categoriesStmt->execute([$_SESSION['user_id']]);
$categories = $categoriesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications data using functions from config.php
$notifications = getRecentNotifications($db, $_SESSION['user_id']);
$unreadNotificationsCount = countUnreadNotifications($db, $_SESSION['user_id']);

// Fetch service data for AJAX requests
if (isset($_GET['action']) && $_GET['action'] === 'get_service' && isset($_GET['id'])) {
    $serviceId = $_GET['id'];
    
    try {
        // UPDATED to include owner_id check
        $stmt = $db->prepare("SELECT * FROM services WHERE id = ? AND owner_id = ?");
        $stmt->execute([$serviceId, $_SESSION['user_id']]);
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            // Return the service data as JSON
            header('Content-Type: application/json');
            echo json_encode($service);
            exit;
        } else {
            header('HTTP/1.1 404 Not Found');
            echo json_encode(['error' => 'Service not found']);
            exit;
        }
    } catch (PDOException $e) {
        header('HTTP/1.1 500 Internal Server Error');
        echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Service Management - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Metro UI CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro-icons.min.css">
    <!-- Custom CSS -->
    <style>
        .sidebar-item.active {
            background-color: #f3f4f6;
        }
        .notification-panel {
            max-height: 280px;
            overflow-y: auto;
        }
        .service-card {
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }
        .service-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }
        .card-body {
            flex: 1 1 auto;
            display: flex;
            flex-direction: column;
        }
        .card-footer {
            margin-top: auto;
        }
        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        /* Availability status badges */
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.025em;
        }
        .status-available {
            background-color: #10B981;
            color: white;
        }
        .status-unavailable {
            background-color: #EF4444;
            color: white;
        }
        .status-limited {
            background-color: #F59E0B;
            color: white;
        }
        .status-coming-soon {
            background-color: #6366F1;
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.show {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-md shadow-md z-50">
            <?php echo $_SESSION['success_message']; ?>
            <button class="ml-4 font-bold" onclick="document.getElementById('success-alert').style.display='none'">×</button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="error-alert" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-md shadow-md z-50">
            <?php echo $_SESSION['error_message']; ?>
            <button class="ml-4 font-bold" onclick="document.getElementById('error-alert').style.display='none'">×</button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="flex h-screen overflow-hidden">
        <!-- Mobile Menu Button -->
        <div class="fixed top-4 left-4 z-40 md:hidden">
            <button id="mobile-menu-button" class="bg-white p-2 rounded-md shadow-md">
                <i class="mif-menu text-2xl"></i>
            </button>
        </div>
        
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-30 transition-transform duration-300 ease-in-out md:translate-x-0">
            <div class="p-4 border-b border-gray-200">
                <h1 class="text-xl font-bold"><?php echo getSystemSetting($db, 'company_name', 'EVENTO'); ?></h1>
            </div>
            
            <div class="flex items-center p-4 border-b border-gray-200">
                <img src="https://i.pravatar.cc/150?img=44" alt="User Avatar" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h2 class="font-medium"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
            </div>
            
            <nav class="flex-1 py-4 overflow-y-auto">
                <a href="owner_index.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="service_management.php" class="flex items-center px-4 py-3 sidebar-item active">
                    <i class="mif-cogs mr-3"></i>
                    <span>Service Management</span>
                </a>
                <a href="reservations.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-calendar mr-3"></i>
                    <span>Reservations</span>
                </a>
                <a href="calendar.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-calendar mr-3"></i>
                    <span>Calendar</span>
                </a>
                <a href="customers.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-chart-bars mr-3"></i>
                    <span>Analytics</span>
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-bell mr-3"></i>
                    <span>Notifications</span>
                    <span class="ml-auto bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-xs">
                        <?php echo $unreadNotificationsCount; ?>
                    </span>
                </a>
            </nav>
            
            <div class="mt-auto">
                <a href="settings.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-cog mr-3"></i>
                    <span>Settings</span>
                </a>
                <a href="support.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-question mr-3"></i>
                    <span>Help & Support</span>
                </a>
                <a href="logout.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-exit mr-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content flex-1 md:ml-64 overflow-hidden flex flex-col">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 flex items-center justify-between p-4">
                <div class="flex-1 ml-12 md:ml-0">
                    <h1 class="text-xl md:text-2xl font-bold">Service Management</h1>
                </div>
                
                <div class="hidden md:block w-1/3">
                    <form action="service_management.php" method="GET" class="relative">
                        <input type="text" name="search" id="search-input" placeholder="Search services..." 
                               value="<?php echo htmlspecialchars($search); ?>"
                               class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md">
                        <i class="mif-search absolute left-3 top-2.5 text-gray-400"></i>
                        <button type="submit" class="absolute right-2 top-2 text-blue-600">
                            <i class="mif-search"></i>
                        </button>
                    </form>
                </div>
                
                <div class="flex items-center">
                    <div class="relative mr-4">
                        <button id="notification-toggle" class="focus:outline-none">
                            <i class="mif-bell text-xl cursor-pointer hover:text-gray-700"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $unreadNotificationsCount; ?>
                            </span>
                        </button>
                    </div>
                    <img src="https://i.pravatar.cc/150?img=44" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <!-- Mobile Search (visible only on small screens) -->
            <div class="md:hidden p-4 bg-white border-b border-gray-200">
                <form action="service_management.php" method="GET" class="relative">
                    <input type="text" name="search" placeholder="Search services..." 
                           value="<?php echo htmlspecialchars($search); ?>"
                           class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md">
                    <i class="mif-search absolute left-3 top-2.5 text-gray-400"></i>
                    <button type="submit" class="absolute right-2 top-2 text-blue-600">
                        <i class="mif-search"></i>
                    </button>
                </form>
            </div>
            
            <!-- Include the content file -->
            <?php include_once 'service_management_content.php'; ?>
        </div>
        
        <!-- Notifications Sidebar (hidden by default) -->
        <div id="notifications-sidebar" class="w-80 bg-white border-l border-gray-200 fixed right-0 top-0 h-full transform translate-x-full transition-transform duration-300 ease-in-out z-30 overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-medium">Notifications</h2>
                <button id="close-notifications" class="text-gray-400 hover:text-gray-600">
                    <i class="mif-cross"></i>
                </button>
            </div>
            
            <div class="notification-panel flex-1 overflow-y-auto">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-gray-500">
                        No new notifications
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                        <?php 
                        // More robust check - skip if not an array or missing required keys
                        if (!is_array($notification)) {
                            continue;
                        }
                        ?>
                        <div class="p-4 border-b border-gray-100 <?php echo (isset($notification['is_read']) && $notification['is_read'] === false) ? 'bg-blue-50' : ''; ?>">
                            <div class="flex justify-between items-start">
                                <h3 class="font-medium mb-1"><?php echo isset($notification['title']) ? htmlspecialchars($notification['title']) : 'Notification'; ?></h3>
                                <span class="text-xs text-gray-500"><?php echo isset($notification['time']) ? $notification['time'] : ''; ?></span>
                            </div>
                            <p class="text-sm text-gray-600 mb-2"><?php echo isset($notification['message']) ? htmlspecialchars($notification['message']) : ''; ?></p>
                            <div class="flex justify-between items-center">
                                <?php if (isset($notification['related_id']) && isset($notification['type'])): ?>
                                    <?php if ($notification['type'] === 'booking'): ?>
                                        <a href="view_booking.php?id=<?php echo $notification['related_id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                                    <?php elseif ($notification['type'] === 'service'): ?>
                                        <a href="service_management.php" class="text-xs text-blue-600 hover:text-blue-800">View services</a>
                                    <?php else: ?>
                                        <span></span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span></span>
                                <?php endif; ?>
                                
                                <?php if (isset($notification['is_read']) && $notification['is_read'] === false && isset($notification['id'])): ?>
                                    <form action="mark_notification_read.php" method="post" class="inline">
                                        <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                        <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Mark as read</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            
            <div class="p-4 text-center border-t border-gray-200">
                <a href="notifications.php" class="inline-block text-sm text-blue-600 hover:text-blue-800">View all notifications</a>
                <?php if ($unreadNotificationsCount > 0): ?>
                    <form action="mark_all_notifications_read.php" method="post" class="mt-2">
                        <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Mark all as read</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/js/metro.min.js"></script>
    <script>
        // Sidebar navigation active state toggle
        document.querySelectorAll('.sidebar-item').forEach(item => {
            item.addEventListener('click', function(e) {
                if (this.getAttribute('href') === '#') {
                    e.preventDefault();
                }
                document.querySelectorAll('.sidebar-item').forEach(el => {
                    el.classList.remove('active');
                });
                this.classList.add('active');
            });
        });
        
        // Mobile menu toggle
        document.getElementById('mobile-menu-button').addEventListener('click', function() {
            const sidebar = document.getElementById('sidebar');
            sidebar.classList.toggle('show');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(e) {
            const sidebar = document.getElementById('sidebar');
            const mobileMenuButton = document.getElementById('mobile-menu-button');
            
            if (window.innerWidth < 768 && 
                !sidebar.contains(e.target) && 
                !mobileMenuButton.contains(e.target) &&
                sidebar.classList.contains('show')) {
                sidebar.classList.remove('show');
            }
        });
        
        // Notification toggle
        document.getElementById('notification-toggle').addEventListener('click', function() {
            const notificationsSidebar = document.getElementById('notifications-sidebar');
            notificationsSidebar.classList.toggle('translate-x-full');
        });
        
        // Close notifications
        document.getElementById('close-notifications').addEventListener('click', function() {
            const notificationsSidebar = document.getElementById('notifications-sidebar');
            notificationsSidebar.classList.add('translate-x-full');
        });
        
        // Add service modal
        document.getElementById('add-service-btn').addEventListener('click', function() {
            document.getElementById('add-service-modal').classList.remove('hidden');
        });
        
        // If there's an "add first service" button
        const addFirstServiceBtn = document.getElementById('add-first-service-btn');
        if (addFirstServiceBtn) {
            addFirstServiceBtn.addEventListener('click', function() {
                document.getElementById('add-service-modal').classList.remove('hidden');
            });
        }
        
        document.getElementById('close-add-modal').addEventListener('click', function() {
            document.getElementById('add-service-modal').classList.add('hidden');
        });
        
        document.getElementById('cancel-add').addEventListener('click', function() {
            document.getElementById('add-service-modal').classList.add('hidden');
        });
        
        // Form validation for add service
        document.getElementById('add-service-form').addEventListener('submit', function(e) {
            const nameField = document.getElementById('name');
            const priceField = document.getElementById('price');
            const availabilityField = document.getElementById('availability_status');
            
            let isValid = true;
            
            if (!nameField.value.trim()) {
                nameField.classList.add('border-red-500');
                isValid = false;
            } else {
                nameField.classList.remove('border-red-500');
            }
            
            if (priceField.value <= 0) {
                priceField.classList.add('border-red-500');
                isValid = false;
            } else {
                priceField.classList.remove('border-red-500');
            }
            
            if (!availabilityField.value) {
                availabilityField.classList.add('border-red-500');
                isValid = false;
            } else {
                availabilityField.classList.remove('border-red-500');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
        
        // Edit service modal
        const editButtons = document.querySelectorAll('.edit-service-btn');
        editButtons.forEach(button => {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-service-id');
                
                // Fetch the service data via AJAX
                fetch(`service_management.php?action=get_service&id=${serviceId}`)
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(service => {
                        // Populate the form
                        document.getElementById('edit_service_id').value = service.id;
                        document.getElementById('edit_name').value = service.name;
                        document.getElementById('edit_category_id').value = service.category_id;
                        document.getElementById('edit_price').value = parseFloat(service.price).toFixed(2);
                        
                        // Set availability status
                        const availabilityDropdown = document.getElementById('edit_availability_status');
                        if (service.availability_status) {
                            // Find the option with the matching value
                            for (let i = 0; i < availabilityDropdown.options.length; i++) {
                                if (availabilityDropdown.options[i].value === service.availability_status) {
                                    availabilityDropdown.selectedIndex = i;
                                    break;
                                }
                            }
                        } else {
                            // Default to "Unavailable" if the field doesn't exist
                            availabilityDropdown.value = service.is_available == 1 ? 'Available' : 'Unavailable';
                        }
                        
                        document.getElementById('edit_description').value = service.description || '';
                        
                        // Update the current image display
                        const imageContainer = document.getElementById('current_image_container');
                        const imageName = document.getElementById('current_image_name');
                        
                        imageContainer.innerHTML = `<img src="images/${service.image}" alt="${service.name}" class="w-full h-full object-cover">`;
                        imageName.textContent = service.image;
                        
                        // Show the modal
                        document.getElementById('edit-service-modal').classList.remove('hidden');
                    })
                    .catch(error => {
                        console.error('Error fetching service data:', error);
                        alert('There was a problem loading the service data. Please try again.');
                    });
            });
        });
        
        document.getElementById('close-edit-modal').addEventListener('click', function() {
            document.getElementById('edit-service-modal').classList.add('hidden');
        });
        
        document.getElementById('cancel-edit').addEventListener('click', function() {
            document.getElementById('edit-service-modal').classList.add('hidden');
        });
        
        // Form validation for edit service
        document.getElementById('edit-service-form').addEventListener('submit', function(e) {
            const nameField = document.getElementById('edit_name');
            const priceField = document.getElementById('edit_price');
            const availabilityField = document.getElementById('edit_availability_status');
            
            let isValid = true;
            
            if (!nameField.value.trim()) {
                nameField.classList.add('border-red-500');
                isValid = false;
            } else {
                nameField.classList.remove('border-red-500');
            }
            
            if (priceField.value <= 0) {
                priceField.classList.add('border-red-500');
                isValid = false;
            } else {
                priceField.classList.remove('border-red-500');
            }
            
            if (!availabilityField.value) {
                availabilityField.classList.add('border-red-500');
                isValid = false;
            } else {
                availabilityField.classList.remove('border-red-500');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields correctly.');
            }
        });
        
        // Delete service modal
        const deleteButtons = document.querySelectorAll('.delete-service-btn');
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const serviceId = this.getAttribute('data-service-id');
                const serviceName = this.getAttribute('data-service-name');
                
                document.getElementById('delete_service_id').value = serviceId;
                document.getElementById('delete-service-message').textContent = `Are you sure you want to delete "${serviceName}"?`;
                
                // Show the modal
                document.getElementById('delete-service-modal').classList.remove('hidden');
            });
        });
        
        document.getElementById('cancel-delete').addEventListener('click', function() {
            document.getElementById('delete-service-modal').classList.add('hidden');
        });
        
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Close modals when clicking outside
        window.addEventListener('click', function(e) {
            const addModal = document.getElementById('add-service-modal');
            const editModal = document.getElementById('edit-service-modal');
            const deleteModal = document.getElementById('delete-service-modal');
            
            if (e.target === addModal) {
                addModal.classList.add('hidden');
            }
            
            if (e.target === editModal) {
                editModal.classList.add('hidden');
            }
            
            if (e.target === deleteModal) {
                deleteModal.classList.add('hidden');
            }
        });
        
        // Preview image upload - for add form
        document.getElementById('service_image').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const previewDiv = document.createElement('div');
                    previewDiv.id = 'image-preview';
                    previewDiv.className = 'w-full h-40 bg-gray-200 rounded-md mt-2 overflow-hidden';
                    previewDiv.innerHTML = `<img src="${e.target.result}" alt="Image Preview" class="w-full h-full object-cover">`;
                    
                    const existingPreview = document.getElementById('image-preview');
                    if (existingPreview) {
                        existingPreview.remove();
                    }
                    
                    document.getElementById('service_image').parentNode.appendChild(previewDiv);
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
        
        // Preview image upload - for edit form
        document.getElementById('edit_service_image').addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const container = document.getElementById('current_image_container');
                    const imageName = document.getElementById('current_image_name');
                    
                    container.innerHTML = `<img src="${e.target.result}" alt="Image Preview" class="w-full h-full object-cover">`;
                    imageName.textContent = 'New image selected';
                }
                reader.readAsDataURL(this.files[0]);
            }
        });
    </script>
</body>
</html>