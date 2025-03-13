<?php
session_start();

// Include necessary configuration and database connection
require_once 'config.php';

// Check if user is logged in and is an admin
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    // Store an error message to show on login page
    $_SESSION['error_message'] = "You must be an admin to access this page.";
    header('Location: index.php');
    exit();
}

// Additional security: Regenerate session ID to prevent session fixation
session_regenerate_id(true);

// Handle actions if any
$action_message = '';
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $id = intval($_GET['id']);
    
    try {
        // Feature toggle for services
        if ($action === 'toggle_featured') {
            $service = $db->query("SELECT featured FROM services WHERE id = $id")->fetch(PDO::FETCH_ASSOC);
            $new_status = $service['featured'] ? 0 : 1;
            $db->exec("UPDATE services SET featured = $new_status WHERE id = $id");
            $action_message = "Service feature status updated successfully";
        }
        
        // Approve/reject booking
        if ($action === 'approve_booking') {
            // Set admin user id for the trigger
            $db->exec("SET @admin_user_id = " . $_SESSION['user_id']);
            $db->exec("UPDATE bookings SET status = 'confirmed' WHERE id = $id");
            $action_message = "Booking #$id approved successfully";
        }
        
        if ($action === 'reject_booking') {
            // Set admin user id for the trigger
            $db->exec("SET @admin_user_id = " . $_SESSION['user_id']);
            $db->exec("UPDATE bookings SET status = 'cancelled' WHERE id = $id");
            $action_message = "Booking #$id rejected";
        }
        
        // Mark notification as read
        if ($action === 'mark_read') {
            $db->exec("UPDATE notifications SET is_read = 1 WHERE id = $id");
            $action_message = "Notification marked as read";
        }
    } catch (PDOException $e) {
        $action_message = "Error: " . $e->getMessage();
    }
}

// Run maintenance procedures
try {
    $db->exec("CALL complete_old_bookings()");
    $db->exec("CALL refresh_services_data()");
} catch (PDOException $e) {
    error_log("Maintenance procedure error: " . $e->getMessage());
}

// Advanced statistics and analytics
try {
    // Comprehensive user analytics
    $users_query = "SELECT 
        COUNT(*) as total_users,
        SUM(CASE WHEN role = 'client' THEN 1 ELSE 0 END) as client_count,
        SUM(CASE WHEN role = 'owner' THEN 1 ELSE 0 END) as owner_count,
        SUM(CASE WHEN created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as new_users_last_30_days
    FROM users";
    $users_result = $db->query($users_query);
    $users_stats = $users_result->fetch(PDO::FETCH_ASSOC);

    // Advanced bookings overview
    $bookings_query = "SELECT 
        COUNT(*) as total_bookings,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_bookings,
        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
        SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled_bookings,
        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
        SUM(total_amount) as total_revenue,
        AVG(total_amount) as avg_booking_value,
        SUM(CASE WHEN booking_date >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as revenue_last_30_days
    FROM bookings";
    $bookings_result = $db->query($bookings_query);
    $bookings_stats = $bookings_result->fetch(PDO::FETCH_ASSOC);

    // Advanced services overview
    $services_query = "SELECT 
        COUNT(*) as total_services,
        SUM(CASE WHEN availability_status = 'Available' THEN 1 ELSE 0 END) as available_services,
        SUM(CASE WHEN featured = 1 THEN 1 ELSE 0 END) as featured_services,
        MAX(price) as highest_priced_service,
        MIN(price) as lowest_priced_service,
        AVG(price) as avg_service_price
    FROM services";
    $services_result = $db->query($services_query);
    $services_stats = $services_result->fetch(PDO::FETCH_ASSOC);

    // New: Revenue by month for charting
    $monthly_revenue_query = "SELECT 
        DATE_FORMAT(booking_date, '%Y-%m') as month,
        SUM(total_amount) as revenue
    FROM bookings
    WHERE booking_date > DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
    ORDER BY month";
    $monthly_revenue_result = $db->query($monthly_revenue_query);
    $monthly_revenue = [];
    while ($row = $monthly_revenue_result->fetch(PDO::FETCH_ASSOC)) {
        $monthly_revenue[] = $row;
    }

    // Recent activities tracking
    $activities_query = "
    (SELECT 'booking' as type, b.id, u.name, b.status, b.total_amount, b.created_at 
     FROM bookings b
     JOIN users u ON b.user_id = u.id
     ORDER BY b.created_at DESC
     LIMIT 5)
    UNION
    (SELECT 'service' as type, s.id, u.name, s.availability_status, s.price, s.created_at 
     FROM services s
     JOIN users u ON s.owner_id = u.id
     ORDER BY s.created_at DESC
     LIMIT 5)
    UNION
    (SELECT 'user' as type, u.id, u.name, u.role, NULL, u.created_at 
     FROM users u
     ORDER BY u.created_at DESC
     LIMIT 5)
    ORDER BY created_at DESC
    LIMIT 15";
    $activities_result = $db->query($activities_query);

    // Categories distribution
    $categories_query = "
    SELECT 
        gc.name as category_name, 
        COUNT(s.id) as service_count,
        ROUND(COUNT(s.id) * 100.0 / (SELECT COUNT(*) FROM services), 2) as percentage
    FROM global_categories gc
    LEFT JOIN services s ON gc.id = s.category_id
    GROUP BY gc.id, gc.name
    ORDER BY service_count DESC";
    $categories_result = $db->query($categories_query);
    
    // New: Get pending bookings that need approval
    $pending_bookings_query = "
    SELECT 
        b.id, 
        u.name as client_name, 
        b.booking_date, 
        b.booking_time, 
        b.total_amount,
        b.created_at,
        GROUP_CONCAT(s.name SEPARATOR ', ') as service_names
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN booking_items bi ON b.id = bi.booking_id
    JOIN services s ON bi.service_id = s.id
    WHERE b.status = 'pending'
    GROUP BY b.id
    ORDER BY b.created_at DESC
    LIMIT 10";
    $pending_bookings_result = $db->query($pending_bookings_query);
    
    // New: Get unread notifications count
    $unread_notif_query = "SELECT COUNT(*) as count FROM notifications WHERE is_read = 0";
    $unread_notif_result = $db->query($unread_notif_query);
    $unread_notif_count = $unread_notif_result->fetchColumn();
    
    // New: Get recent system notifications
    $notifications_query = "
    SELECT id, type, title, message, created_at, is_read
    FROM notifications
    ORDER BY created_at DESC
    LIMIT 10";
    $notifications_result = $db->query($notifications_query);
    
    // New: Get top service providers based on bookings
    $top_providers_query = "
    SELECT 
        u.id,
        u.name,
        u.business_name,
        COUNT(DISTINCT b.id) as booking_count,
        SUM(b.total_amount) as total_revenue
    FROM users u
    JOIN services s ON u.id = s.owner_id
    JOIN booking_items bi ON s.id = bi.service_id
    JOIN bookings b ON bi.booking_id = b.id
    WHERE u.role = 'owner'
    GROUP BY u.id
    ORDER BY total_revenue DESC
    LIMIT 5";
    $top_providers_result = $db->query($top_providers_query);

    // New: Bookings by Status for Pie Chart
    $bookings_by_status_query = "
    SELECT 
        status,
        COUNT(*) as count,
        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM bookings), 2) as percentage
    FROM bookings
    GROUP BY status";
    $bookings_by_status_result = $db->query($bookings_by_status_query);
    $bookings_by_status = [];
    while ($row = $bookings_by_status_result->fetch(PDO::FETCH_ASSOC)) {
        $bookings_by_status[] = $row;
    }

} catch (PDOException $e) {
    // Log error
    error_log("Dashboard query error: " . $e->getMessage());
    // Set error message
    $error_message = "Unable to fetch dashboard statistics. Please try again later.";
}

// Function to get color class based on percentage
function getPercentageColor($percentage) {
    if ($percentage < 20) return 'bg-red-500';
    if ($percentage < 40) return 'bg-orange-500';
    if ($percentage < 60) return 'bg-yellow-500';
    if ($percentage < 80) return 'bg-green-500';
    return 'bg-blue-500';
}

// Function to format dates
function formatDate($date) {
    return date('M d, Y', strtotime($date));
}

// Function to format time
function formatTime($time) {
    return date('h:i A', strtotime($time));
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO Admin Dashboard</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Heroicons -->
    <script src="https://unpkg.com/heroicons@2.0.18/dist/heroicons.min.js"></script>
    <!-- Alpine.js for interactivity -->
    <script src="https://unpkg.com/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100" x-data="{ sidebarOpen: false }">
    <?php if (!empty($action_message)): ?>
    <div id="notification" class="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded shadow-lg">
        <?php echo htmlspecialchars($action_message); ?>
    </div>
    <script>
        setTimeout(() => {
            document.getElementById('notification').style.display = 'none';
        }, 3000);
    </script>
    <?php endif; ?>

    <div class="flex min-h-screen">
        <!-- Mobile sidebar backdrop -->
        <div 
            x-show="sidebarOpen" 
            @click="sidebarOpen = false" 
            class="fixed inset-0 z-20 bg-black bg-opacity-50 lg:hidden"
        ></div>

        <!-- Sidebar -->
        <div 
            :class="{'translate-x-0': sidebarOpen, '-translate-x-full': !sidebarOpen}"
            class="fixed inset-y-0 left-0 z-30 w-64 bg-white dark:bg-gray-800 shadow-xl transform transition-transform duration-300 lg:translate-x-0 lg:static lg:inset-0"
        >
            <div class="p-6 border-b dark:border-gray-700">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-200">EVENTO</h1>
                <p class="text-sm text-gray-600 dark:text-gray-400">Admin Dashboard</p>
            </div>
            <nav class="p-4">
                <div class="mb-4">
                    <span class="px-3 text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Main</span>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="#dashboard" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10.707 2.293a1 1 0 00-1.414 0l-7 7a1 1 0 001.414 1.414L4 10.414V17a1 1 0 001 1h2a1 1 0 001-1v-2a1 1 0 011-1h2a1 1 0 011 1v2a1 1 0 001 1h2a1 1 0 001-1v-6.586l.293.293a1 1 0 001.414-1.414l-7-7z" />
                            </svg>
                            Dashboard
                        </a>
                    </li>
                    <li>
						<a href="javascript:void(0);" onclick="loadUserManagement()" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
							<svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
								<path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM12.93 17c.046-.327.07-.66.07-1a6.97 6.97 0 00-1.5-4.33A5 5 0 0119 16v1h-6.07zM6 11a5 5 0 015 5v1H1v-1a5 5 0 015-5z" />
							</svg>
							Users
						</a>
					</li>
                    <li>
                        <a href="#bookings" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                            </svg>
                            Bookings
                        </a>
                    </li>
                    <li>
                        <a href="#services" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M6 6V5a3 3 0 013-3h2a3 3 0 013 3v1h2a2 2 0 012 2v3.57A22.952 22.952 0 0110 13a22.95 22.95 0 01-8-1.43V8a2 2 0 012-2h2zm2-1a1 1 0 011-1h2a1 1 0 011 1v1H8V5zm1 5a1 1 0 011-1h.01a1 1 0 110 2H10a1 1 0 01-1-1z" clip-rule="evenodd" />
                                <path d="M2 13.692V16a2 2 0 002 2h12a2 2 0 002-2v-2.308A24.974 24.974 0 0110 15c-2.796 0-5.487-.46-8-1.308z" />
                            </svg>
                            Services
                        </a>
                    </li>
                    <li>
                        <a href="#notifications" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                            </svg>
                            Notifications
                            <?php if ($unread_notif_count > 0): ?>
                            <span class="ml-2 px-2 py-1 text-xs bg-red-500 text-white rounded-full">
                                <?php echo $unread_notif_count; ?>
                            </span>
                            <?php endif; ?>
                        </a>
                    </li>
                </ul>
                
                <div class="mt-8 mb-4">
                    <span class="px-3 text-xs text-gray-500 dark:text-gray-400 uppercase font-semibold">Settings</span>
                </div>
                <ul class="space-y-2">
                    <li>
                        <a href="#settings" class="flex items-center p-2 text-gray-600 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M11.49 3.17c-.38-1.56-2.6-1.56-2.98 0a1.532 1.532 0 01-2.286.948c-1.372-.836-2.942.734-2.106 2.106.54.886.061 2.042-.947 2.287-1.561.379-1.561 2.6 0 2.978a1.532 1.532 0 01.947 2.287c-.836 1.372.734 2.942 2.106 2.106a1.532 1.532 0 012.287.947c.379 1.561 2.6 1.561 2.978 0a1.533 1.533 0 012.287-.947c1.372.836 2.942-.734 2.106-2.106a1.533 1.533 0 01.947-2.287c1.561-.379 1.561-2.6 0-2.978a1.532 1.532 0 01-.947-2.287c.836-1.372-.734-2.942-2.106-2.106a1.532 1.532 0 01-2.287-.947zM10 13a3 3 0 100-6 3 3 0 000 6z" clip-rule="evenodd" />
                            </svg>
                            System Settings
                        </a>
                    </li>
                    <li>
                        <a href="logout.php" class="flex items-center p-2 text-red-500 hover:bg-red-100 dark:hover:bg-red-900 rounded">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M3 3a1 1 0 00-1 1v12a1 1 0 001 1h12a1 1 0 001-1V4a1 1 0 00-1-1H3zm9 12V9.414l1.293 1.293a1 1 0 001.414-1.414l-3-3a1 1 0 00-1.414 0l-3 3a1 1 0 001.414 1.414L9 9.414V15H6a1 1 0 110-2h3V9.414l-1.293 1.293a1 1 0 11-1.414-1.414l3-3a1 1 0 011.414 0l3 3a1 1 0 01-1.414 1.414L12 9.414V13h3a1 1 0 110 2h-3z" clip-rule="evenodd" />
                            </svg>
                            Logout
                        </a>
                    </li>
                </ul>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden">
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 shadow-md p-4">
    <div class="flex justify-between items-center">
        <!-- Left side with mobile menu button and/or logo -->
        <div class="flex items-center">
            <!-- Mobile menu button -->
            <button 
                @click="sidebarOpen = !sidebarOpen" 
                class="lg:hidden text-gray-500 dark:text-gray-200 focus:outline-none mr-2"
            >
                <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
            
        </div>
        
        <!-- Right side elements - will be pushed to the right -->
        <div class="flex items-center space-x-4">
            <button id="theme-toggle-btn" class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700">
  <!-- Moon icon (for light mode) -->
  <svg id="moon-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
    <path d="M17.293 13.293A8 8 0 016.707 2.707a8.001 8.001 0 1010.586 10.586z" />
  </svg>
  <!-- Sun icon (for dark mode) -->
  <svg id="sun-icon" xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 hidden" viewBox="0 0 20 20" fill="currentColor">
    <path fill-rule="evenodd" d="M10 2a1 1 0 011 1v1a1 1 0 11-2 0V3a1 1 0 011-1zm4 8a4 4 0 11-8 0 4 4 0 018 0zm-.464 4.95l.707.707a1 1 0 001.414-1.414l-.707-.707a1 1 0 00-1.414 1.414zm2.12-10.607a1 1 0 010 1.414l-.706.707a1 1 0 11-1.414-1.414l.707-.707a1 1 0 011.414 0zM17 11a1 1 0 100-2h-1a1 1 0 100 2h1zm-7 4a1 1 0 011 1v1a1 1 0 11-2 0v-1a1 1 0 011-1zM5.05 6.464A1 1 0 106.465 5.05l-.708-.707a1 1 0 00-1.414 1.414l.707.707zm1.414 8.486l-.707.707a1 1 0 01-1.414-1.414l.707-.707a1 1 0 011.414 1.414zM4 11a1 1 0 100-2H3a1 1 0 000 2h1z" clip-rule="evenodd" />
  </svg>
</button>

            <!-- Notifications -->
            <div class="relative">
                <button class="p-2 rounded-full hover:bg-gray-200 dark:hover:bg-gray-700 relative">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                        <path d="M10 2a6 6 0 00-6 6v3.586l-.707.707A1 1 0 004 14h12a1 1 0 00.707-1.707L16 11.586V8a6 6 0 00-6-6zM10 18a3 3 0 01-3-3h6a3 3 0 01-3 3z" />
                    </svg>
                    <?php if ($unread_notif_count > 0): ?>
                    <span class="absolute top-0 right-0 w-2 h-2 bg-red-500 rounded-full"></span>
                    <?php endif; ?>
                </button>
            </div>

            <!-- Admin profile -->
            <div class="flex items-center">
                <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center text-white mr-2">
                    AD
                </div>
                <span class="text-sm font-medium dark:text-gray-300 mr-2">Admin</span>
            </div>

            <!-- Logout button -->
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-3 py-2 rounded-md text-sm transition-colors">
                Logout
            </a>
        </div>
    </div>
</header>

            <!-- Content area -->
            <main class="flex-1 overflow-y-auto p-6">
                <div id="dashboard" class="space-y-8">
                    <!-- Welcome message -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-l-4 border-blue-500">
                        <h2 class="text-xl font-semibold mb-2 text-gray-800 dark:text-gray-200">Welcome to EVENTO Admin Dashboard</h2>
                        <p class="text-gray-600 dark:text-gray-400">Here's what's happening with your event platform today.</p>
                    </div>

                    <!-- Statistics Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                        <!-- Users Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-t-4 border-blue-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Users</h3>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo number_format($users_stats['total_users']); ?>
                                    </p>
                                </div>
                                <span class="p-2 bg-blue-100 dark:bg-blue-900 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-blue-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-4 flex space-x-4 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Clients</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($users_stats['client_count']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Owners</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($users_stats['owner_count']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">New (30d)</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($users_stats['new_users_last_30_days']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Bookings Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-t-4 border-green-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Bookings</h3>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo number_format($bookings_stats['total_bookings']); ?>
                                    </p>
                                </div>
                                <span class="p-2 bg-green-100 dark:bg-green-900 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-green-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-4 grid grid-cols-2 gap-2 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Pending</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($bookings_stats['pending_bookings']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Confirmed</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($bookings_stats['confirmed_bookings']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Completed</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($bookings_stats['completed_bookings']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Cancelled</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($bookings_stats['cancelled_bookings']); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Revenue Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-t-4 border-purple-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Revenue</h3>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        $<?php echo number_format($bookings_stats['total_revenue'], 2); ?>
                                    </p>
                                </div>
                                <span class="p-2 bg-purple-100 dark:bg-purple-900 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-purple-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-4 flex space-x-4 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Last 30 Days</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">$<?php echo number_format($bookings_stats['revenue_last_30_days'], 2); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Avg Booking</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">$<?php echo number_format($bookings_stats['avg_booking_value'], 2); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Services Card -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6 border-t-4 border-amber-500">
                            <div class="flex justify-between items-start">
                                <div>
                                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Total Services</h3>
                                    <p class="text-2xl font-bold text-gray-900 dark:text-gray-100">
                                        <?php echo number_format($services_stats['total_services']); ?>
                                    </p>
                                </div>
                                <span class="p-2 bg-amber-100 dark:bg-amber-900 rounded-lg">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 text-amber-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z" />
                                    </svg>
                                </span>
                            </div>
                            <div class="mt-4 flex space-x-4 text-sm">
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Available</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($services_stats['available_services']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Featured</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo number_format($services_stats['featured_services']); ?></span>
                                </div>
                                <div class="flex flex-col">
                                    <span class="text-gray-500 dark:text-gray-400">Avg Price</span>
                                    <span class="font-medium text-gray-900 dark:text-gray-100">$<?php echo number_format($services_stats['avg_service_price'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Charts Grid -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Revenue Chart -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Monthly Revenue</h3>
                            <div class="w-full h-64">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>

                        <!-- Booking Status Chart -->
                        <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                            <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Bookings by Status</h3>
                            <div class="w-full h-64">
                                <canvas id="bookingStatusChart"></canvas>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Categories Distribution -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Service Categories Distribution</h3>
                        <div class="w-full h-64">
                            <canvas id="categoriesChart"></canvas>
                        </div>
                    </div>

                    <!-- Pending Bookings Section -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Pending Bookings</h3>
                            <a href="#bookings" class="text-sm text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300">View All</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Booking ID</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Client</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Service</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Date & Time</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Amount</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php while ($booking = $pending_bookings_result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">#<?php echo $booking['id']; ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($booking['client_name']); ?></td>
                                        <td class="px-6 py-4 text-sm text-gray-700 dark:text-gray-200">
                                            <?php echo htmlspecialchars($booking['service_names']); ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">
                                            <?php echo formatDate($booking['booking_date']); ?><br>
                                            <span class="text-xs text-gray-500 dark:text-gray-400"><?php echo formatTime($booking['booking_time']); ?></span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">$<?php echo number_format($booking['total_amount'], 2); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium space-x-2">
                                            <a href="?action=approve_booking&id=<?php echo $booking['id']; ?>" class="text-green-500 hover:text-green-600 dark:text-green-400 dark:hover:text-green-300">
                                                Approve
                                            </a>
                                            <a href="?action=reject_booking&id=<?php echo $booking['id']; ?>" class="text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300">
                                                Reject
                                            </a>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($pending_bookings_result->rowCount() == 0): ?>
                                    <tr>
                                        <td colspan="6" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No pending bookings found</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Top Service Providers -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-800 dark:text-gray-200">Top Service Providers</h3>
                            <a href="#users" class="text-sm text-blue-500 hover:text-blue-600 dark:text-blue-400 dark:hover:text-blue-300">View All Providers</a>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Provider</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Business Name</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Bookings</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Total Revenue</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php while ($provider = $top_providers_result->fetch(PDO::FETCH_ASSOC)): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($provider['name']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200"><?php echo htmlspecialchars($provider['business_name'] ?? 'N/A'); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200"><?php echo number_format($provider['booking_count']); ?></td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">$<?php echo number_format($provider['total_revenue'], 2); ?></td>
                                    </tr>
                                    <?php endwhile; ?>
                                    <?php if ($top_providers_result->rowCount() == 0): ?>
                                    <tr>
                                        <td colspan="4" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">No data available</td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- Recent Activities -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Recent Activities</h3>
                        <div class="divide-y divide-gray-200 dark:divide-gray-700">
                            <?php while($activity = $activities_result->fetch(PDO::FETCH_ASSOC)): ?>
                                <div class="py-3">
                                    <div class="flex items-center">
                                        <?php if ($activity['type'] == 'booking'): ?>
                                            <span class="w-8 h-8 rounded-full bg-green-100 dark:bg-green-900 flex items-center justify-center mr-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-green-500" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M6 2a1 1 0 00-1 1v1H4a2 2 0 00-2 2v10a2 2 0 002 2h12a2 2 0 002-2V6a2 2 0 00-2-2h-1V3a1 1 0 10-2 0v1H7V3a1 1 0 00-1-1zm0 5a1 1 0 000 2h8a1 1 0 100-2H6z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        <?php elseif ($activity['type'] == 'service'): ?>
                                            <span class="w-8 h-8 rounded-full bg-blue-100 dark:bg-blue-900 flex items-center justify-center mr-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-blue-500" viewBox="0 0 20 20" fill="currentColor">
                                                    <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                                </svg>
                                            </span>
                                        <?php else: ?>
                                            <span class="w-8 h-8 rounded-full bg-purple-100 dark:bg-purple-900 flex items-center justify-center mr-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 text-purple-500" viewBox="0 0 20 20" fill="currentColor">
                                                    <path d="M13 6a3 3 0 11-6 0 3 3 0 016 0zM18 8a2 2 0 11-4 0 2 2 0 014 0zM14 15a4 4 0 00-8 0v3h8v-3zM6 8a2 2 0 11-4 0 2 2 0 014 0zM16 18v-3a5.972 5.972 0 00-.75-2.906A3.005 3.005 0 0119 15v3h-3zM4.75 12.094A5.973 5.973 0 004 15v3H1v-3a3 3 0 013.75-2.906z" />
                                                </svg>
                                            </span>
                                        <?php endif; ?>
                                        <div>
                                            <p class="text-sm">
                                                <span class="font-medium text-gray-900 dark:text-gray-100"><?php echo htmlspecialchars($activity['name']); ?></span>
                                                <span class="text-gray-600 dark:text-gray-300">
                                                    <?php
                                                    switch ($activity['type']) {
                                                        case 'booking':
                                                            echo ' created a new booking';
                                                            if (!empty($activity['status'])) {
                                                                echo ' (' . $activity['status'] . ')';
                                                            }
                                                           if (!empty($activity['total_amount'])) {
															echo ' for $' . number_format($activity['total_amount'], 2);
															}
                                                            break;
                                                        case 'service':
                                                            echo ' added a new service';
                                                            if (!empty($activity['price'])) {
															echo ' priced at $' . number_format($activity['price'], 2);
															}
                                                            break;
                                                        case 'user':
                                                            echo ' joined as a ' . $activity['status'];
                                                            break;
                                                    }
                                                    ?>
                                                </span>
                                            </p>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                <?php echo date('M d, h:i A', strtotime($activity['created_at'])); ?>
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Access Tools -->
                    <div class="bg-white dark:bg-gray-800 rounded-lg shadow-md p-6">
                        <h3 class="text-lg font-semibold mb-4 text-gray-800 dark:text-gray-200">Quick Access Tools</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                            <a href="#" class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg flex flex-col items-center justify-center hover:bg-blue-100 dark:hover:bg-blue-900/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-blue-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Add New Service</span>
                            </a>
                            <a href="#" class="p-4 bg-green-50 dark:bg-green-900/20 rounded-lg flex flex-col items-center justify-center hover:bg-green-100 dark:hover:bg-green-900/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-green-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Export Report</span>
                            </a>
                            <a href="#" class="p-4 bg-purple-50 dark:bg-purple-900/20 rounded-lg flex flex-col items-center justify-center hover:bg-purple-100 dark:hover:bg-purple-900/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-purple-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">View All Services</span>
                            </a>
                            <a href="#" class="p-4 bg-amber-50 dark:bg-amber-900/20 rounded-lg flex flex-col items-center justify-center hover:bg-amber-100 dark:hover:bg-amber-900/30 transition-colors">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-8 w-8 text-amber-500 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z" />
                                </svg>
                                <span class="text-sm font-medium text-gray-900 dark:text-gray-100">Edit System Settings</span>
                            </a>
                        </div>
                    </div>
                </div>
            </main>

            <!-- Footer -->
            <footer class="bg-white dark:bg-gray-800 p-6 border-t dark:border-gray-700">
                <div class="flex flex-col md:flex-row justify-between items-center">
                    <p class="text-sm text-gray-600 dark:text-gray-400">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
                    <div class="mt-4 md:mt-0">
                        <p class="text-xs text-gray-500 dark:text-gray-500">Database Version: <?php echo htmlspecialchars($db->query("SELECT setting_value FROM system_settings WHERE setting_key = 'db_version'")->fetchColumn()); ?></p>
                    </div>
                </div>
            </footer>

            <!-- Scripts for Charts -->
           <script>
document.addEventListener('DOMContentLoaded', function() {
   (function() {
  console.log("Dark mode script loaded");
  
  // Get DOM elements
  const toggleBtn = document.getElementById('theme-toggle-btn');
  const moonIcon = document.getElementById('moon-icon');
  const sunIcon = document.getElementById('sun-icon');
  
  if (!toggleBtn) {
    console.error("Toggle button not found! Make sure the ID 'theme-toggle-btn' exists in your HTML.");
    return;
  }
  
  // Check if dark mode is enabled
  function isDarkMode() {
    return document.documentElement.classList.contains('dark');
  }
  
  // Update icon visibility based on current mode
  function updateIcons() {
    if (isDarkMode()) {
      moonIcon.classList.add('hidden');
      sunIcon.classList.remove('hidden');
    } else {
      moonIcon.classList.remove('hidden');
      sunIcon.classList.add('hidden');
    }
  }
  
  // Set the theme
  function setTheme(darkMode) {
    console.log("Setting theme: " + (darkMode ? "dark" : "light"));
    
    if (darkMode) {
      document.documentElement.classList.add('dark');
    } else {
      document.documentElement.classList.remove('dark');
    }
    
    // Store the preference
    localStorage.setItem('darkMode', darkMode ? 'enabled' : 'disabled');
    
    // Update icons
    updateIcons();
  }
  
  // Initialize theme from saved preference or system preference
  function initializeTheme() {
    const savedTheme = localStorage.getItem('darkMode');
    
    if (savedTheme === 'enabled') {
      setTheme(true);
    } else if (savedTheme === 'disabled') {
      setTheme(false);
    } else {
      // Use system preference as fallback
      const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
      setTheme(prefersDark);
    }
    
    console.log("Theme initialized: " + (isDarkMode() ? "dark" : "light"));
  }
  
  // Toggle theme when button is clicked
  toggleBtn.addEventListener('click', function() {
    console.log("Toggle button clicked");
    setTheme(!isDarkMode());
  });
  
  // Run initialization
  initializeTheme();
})();
    
    // Function to update charts for the current theme
    function updateChartsForTheme() {
        // Only run this if Chart is defined (Chart.js is loaded)
        if (typeof Chart !== 'undefined') {
            // Update global Chart.js settings for the current theme
            Chart.defaults.color = document.documentElement.classList.contains('dark') 
                ? '#e5e7eb'  // Light gray for dark mode
                : '#374151'; // Dark gray for light mode
            
            // Update all charts on the page
            Chart.instances.forEach(chart => {
                // Update legend text color
                if (chart.options.plugins && chart.options.plugins.legend) {
                    chart.options.plugins.legend.labels = chart.options.plugins.legend.labels || {};
                    chart.options.plugins.legend.labels.color = document.documentElement.classList.contains('dark') 
                        ? '#e5e7eb' 
                        : '#374151';
                }
                
                // Update any other theme-specific settings
                if (chart.options.scales && chart.options.scales.y) {
                    chart.options.scales.y.ticks = chart.options.scales.y.ticks || {};
                    chart.options.scales.y.ticks.color = document.documentElement.classList.contains('dark') 
                        ? '#9ca3af' 
                        : '#6b7280';
                }
                
                if (chart.options.scales && chart.options.scales.x) {
                    chart.options.scales.x.ticks = chart.options.scales.x.ticks || {};
                    chart.options.scales.x.ticks.color = document.documentElement.classList.contains('dark') 
                        ? '#9ca3af' 
                        : '#6b7280';
                }
                
                // Apply the updates
                chart.update();
            });
        }
    }
    
    // Check for saved theme preference or use the system preference
    const isDarkMode = localStorage.getItem('color-theme') === 'dark' || 
        (!localStorage.getItem('color-theme') && window.matchMedia('(prefers-color-scheme: dark)').matches);
    
    // Apply the initial theme
    setTheme(isDarkMode);
    
    // Add click event listener to toggle button
    themeToggleBtn.addEventListener('click', function() {
        // Toggle dark class
        const isDarkMode = document.documentElement.classList.contains('dark');
        setTheme(!isDarkMode);
        
        // Update charts for the new theme
        updateChartsForTheme();
    });
    
    // Listen for system theme changes
    window.matchMedia('(prefers-color-scheme: dark)').addEventListener('change', function(e) {
        // Only change theme if user hasn't explicitly set a preference
        if (!localStorage.getItem('color-theme')) {
            setTheme(e.matches);
            updateChartsForTheme();
        }
    });
    
    // Run chart update once on page load to ensure charts match initial theme
    updateChartsForTheme();
});
	function loadUserManagement() {
        // Create an iframe to load the user management page
        const mainContent = document.querySelector('main');
        mainContent.innerHTML = '<iframe src="user_management.php" style="width:100%;height:100vh;border:none;"></iframe>';
        
        // Optionally update page title or highlight active sidebar item
        const sidebarItems = document.querySelectorAll('nav ul li a');
        sidebarItems.forEach(item => {
            item.classList.remove('bg-gray-100', 'dark:bg-gray-700');
        });
        event.currentTarget.classList.add('bg-gray-100', 'dark:bg-gray-700');
    }
</script>
        </div>
    </div>
</body>
</html>