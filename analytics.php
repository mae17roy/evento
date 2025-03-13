<?php
// Include database configuration
require_once 'config.php';
require_once 'functions.php';
// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Get current user data
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
            'role' => $_SESSION['role'] ?? 'owner'
        ];
    }
}
$userRole = $_SESSION['role'] ?? 'client';

// Check user permissions
if ($userRole !== 'owner' && $userRole !== 'admin') {
    $_SESSION['error_message'] = "You don't have permission to access analytics.";
    header('Location: ' . ($userRole === 'client' ? 'user_index.php' : 'login.php'));
    exit;
}

// Handle date range filters
$period = isset($_GET['period']) ? $_GET['period'] : '30days';
$today = date('Y-m-d');
$start_date = $today;
$end_date = $today;

switch ($period) {
    case '7days':
        $start_date = date('Y-m-d', strtotime('-7 days'));
        break;
    case '30days':
        $start_date = date('Y-m-d', strtotime('-30 days'));
        break;
    case '90days':
        $start_date = date('Y-m-d', strtotime('-90 days'));
        break;
    case 'year':
        $start_date = date('Y-01-01'); // First day of current year
        break;
    case 'custom':
        $start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d', strtotime('-30 days'));
        $end_date = isset($_GET['end_date']) ? $_GET['end_date'] : $today;
        break;
    default:
        $start_date = date('Y-m-d', strtotime('-30 days'));
}

// Fix for the getOwnerBookingsByDateRange function
// If this function doesn't exist, create it
if (!function_exists('getOwnerBookingsByDateRange')) {
    function getOwnerBookingsByDateRange($db, $ownerId, $start_date, $end_date) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    b.id,
                    b.booking_date,
                    b.booking_time,
                    b.status,
                    b.total_amount,
                    u.name as customer_name,
                    u.email as customer_email,
                    s.name as service_name
                FROM bookings b
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                JOIN users u ON b.user_id = u.id
                WHERE s.owner_id = :owner_id
                AND b.booking_date BETWEEN :start_date AND :end_date
                ORDER BY b.booking_date
            ");
            
            $stmt->execute([
                'owner_id' => $ownerId,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error getting owner bookings by date range: " . $e->getMessage());
            return [];
        }
    }
}

// Fix for the getMonthlyBookingStats function if it's not defined properly
if (!function_exists('getMonthlyBookingStats')) {
    function getMonthlyBookingStats($db, $owner_id, $start_date, $end_date) {
        try {
            $stats = [
                'total_bookings' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'total_revenue' => 0,
                'confirmed_revenue' => 0,
                'bookings_by_day' => []
            ];
    
            // Get booking statistics
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_bookings,
                    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(b.total_amount) as total_revenue,
                    SUM(CASE WHEN b.status IN ('confirmed', 'completed') THEN b.total_amount ELSE 0 END) as confirmed_revenue
                FROM bookings b
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE s.owner_id = :owner_id
                AND b.booking_date BETWEEN :start_date AND :end_date
            ");
            
            $stmt->execute([
                'owner_id' => $owner_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                $stats['total_bookings'] = $result['total_bookings'] ?? 0;
                $stats['pending'] = $result['pending'] ?? 0;
                $stats['confirmed'] = $result['confirmed'] ?? 0;
                $stats['completed'] = $result['completed'] ?? 0;
                $stats['cancelled'] = $result['cancelled'] ?? 0;
                $stats['total_revenue'] = $result['total_revenue'] ?? 0;
                $stats['confirmed_revenue'] = $result['confirmed_revenue'] ?? 0;
            }
            
            // Get bookings by day
            $stmt = $db->prepare("
                SELECT 
                    DATE(b.booking_date) as day,
                    COUNT(*) as count,
                    SUM(b.total_amount) as revenue
                FROM bookings b
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE s.owner_id = :owner_id
                AND b.booking_date BETWEEN :start_date AND :end_date
                GROUP BY DATE(b.booking_date)
            ");
            
            $stmt->execute([
                'owner_id' => $owner_id,
                'start_date' => $start_date,
                'end_date' => $end_date
            ]);
            
            $bookingsByDay = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($bookingsByDay as $day) {
                $stats['bookings_by_day'][$day['day']] = [
                    'count' => $day['count'],
                    'revenue' => $day['revenue']
                ];
            }
            
            return $stats;
        } catch (PDOException $e) {
            error_log("Monthly booking stats error: " . $e->getMessage());
            return [
                'total_bookings' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'total_revenue' => 0,
                'confirmed_revenue' => 0,
                'bookings_by_day' => []
            ];
        }
    }
}

// Get analytics data
$ownerId = $_SESSION['user_id'];

// Get monthly booking statistics
$analytics = getMonthlyBookingStats($db, $ownerId, $start_date, $end_date);

// Get top services with revenue
try {
    $stmt = $db->prepare("
        SELECT 
            s.id,
            s.name,
            COUNT(DISTINCT b.id) as booking_count,
            SUM(b.total_amount) as revenue
        FROM services s
        LEFT JOIN booking_items bi ON s.id = bi.service_id
        LEFT JOIN bookings b ON bi.booking_id = b.id
        WHERE s.owner_id = :owner_id
        AND b.booking_date BETWEEN :start_date AND :end_date
        GROUP BY s.id
        ORDER BY revenue DESC
        LIMIT 5
    ");
    
    $stmt->execute([
        'owner_id' => $ownerId,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    $topServicesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentage for top services
    $totalRevenue = 0;
    foreach ($topServicesData as $service) {
        $totalRevenue += $service['revenue'] ?? 0;
    }
    
    $topServices = [];
    if ($totalRevenue > 0) {
        foreach ($topServicesData as $service) {
            $service['percentage'] = ($service['revenue'] / $totalRevenue) * 100;
            $topServices[] = $service;
        }
    }
} catch (PDOException $e) {
    error_log("Error getting top services data: " . $e->getMessage());
    $topServicesData = [];
    $topServices = [];
    $totalRevenue = 0;
}

// Get revenue data for chart
$revenueData = [];
$interval = '+1 day';
$dateFormat = 'M d';

// Adjust interval and format based on date range
$dateRange = strtotime($end_date) - strtotime($start_date);
if ($dateRange > 60 * 86400) { // More than 60 days
    $interval = '+1 week';
    $dateFormat = 'M d';
} elseif ($dateRange > 365 * 86400) { // More than a year
    $interval = '+1 month';
    $dateFormat = 'M Y';
}

// Get owner bookings by date range
$bookings = getOwnerBookingsByDateRange($db, $ownerId, $start_date, $end_date);

// Prepare data for charts
$dateLabels = [];
$revenueByDate = [];

// Initialize date range
$current = strtotime($start_date);
$end = strtotime($end_date);

while ($current <= $end) {
    $dateKey = date('Y-m-d', $current);
    $dateLabels[date($dateFormat, $current)] = $dateKey;
    $revenueByDate[$dateKey] = 0;
    $current = strtotime($interval, $current);
}

// Fill in revenue data
foreach ($bookings as $booking) {
    if (isset($revenueByDate[$booking['booking_date']])) {
        $revenueByDate[$booking['booking_date']] += $booking['total_amount'];
    }
}

// Format for chart
foreach ($dateLabels as $label => $dateKey) {
    $revenueData[] = [
        'label' => $label,
        'value' => $revenueByDate[$dateKey]
    ];
}

// Get recent customers (simplified version)
$recentCustomers = [];
$customerIds = [];

foreach ($bookings as $booking) {
    if (!in_array($booking['customer_name'], $customerIds)) {
        $customerIds[] = $booking['customer_name'];
        $recentCustomers[] = [
            'name' => $booking['customer_name'],
            'email' => $booking['customer_email'],
            'total_spent' => $booking['total_amount'],
            'booking_count' => 1
        ];
        
        if (count($recentCustomers) >= 5) {
            break;
        }
    }
}

// Get unread notifications count
$unreadNotificationsCount = function_exists('countUnreadNotificationsMultiOwner') 
    ? countUnreadNotificationsMultiOwner($db) 
    : countUnreadNotifications($db, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics Dashboard - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Metro UI CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro-icons.min.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Custom CSS -->
    <style>
        .sidebar-item.active {
            background-color: #f3f4f6;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .desktop-only {
                display: none;
            }
            .responsive-grid {
                grid-template-columns: repeat(1, minmax(0, 1fr));
            }
        }
        @media (min-width: 1025px) {
            .mobile-only {
                display: none;
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

    <div class="flex h-screen">
        <!-- Mobile Menu Button (visible on small screens) -->
        <div class="mobile-only fixed top-4 left-4 z-40">
            <button id="mobile-menu-button" class="bg-white p-2 rounded-md shadow-md">
                <i class="mif-menu text-2xl"></i>
            </button>
        </div>
        
    <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-30 transform transition-transform duration-300 ease-in-out md:translate-x-0">
            <div class="p-4 border-b border-gray-200">
                <h1 class="text-xl font-bold">EVENTO</h1>
            </div>
            
            <div class="flex items-center p-4 border-b border-gray-200">
                <img src="https://i.pravatar.cc/150?img=<?php echo $user['id'] ?? 1; ?>" alt="User Avatar" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h2 class="font-medium"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <?php if(!empty($user['business_name'])): ?>
                    <p class="text-sm text-blue-600"><?php echo htmlspecialchars($user['business_name']); ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
            </div>
            
            <nav class="flex-1 py-4">
                <?php if ($userRole === 'admin'): ?>
                <a href="admin_dashboard.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <?php elseif ($userRole === 'owner'): ?>
                <a href="owner_index.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <?php else: ?>
                <a href="user_index.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <?php endif; ?>
                
                <a href="service_management.php" class="flex items-center px-4 py-3 sidebar-item">
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
                
                <a href="analytics.php" class="flex items-center px-4 py-3 sidebar-item active">
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
        <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 flex items-center justify-between p-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold">Analytics</h1>
                </div>
                
                <div class="flex items-center">
                    <div class="relative mr-4">
                        <a href="notifications.php">
                            <i class="mif-bell text-xl cursor-pointer hover:text-gray-700"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $unreadNotificationsCount; ?>
                            </span>
                        </a>
                    </div>
                    <img src="https://i.pravatar.cc/150?img=<?php echo $user['id'] ?? 1; ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <!-- Analytics Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Date Range Selector -->
                <div class="bg-white p-4 rounded-lg shadow-md mb-6">
                    <form method="GET" action="" class="flex flex-col md:flex-row items-center space-y-4 md:space-y-0 md:space-x-4">
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                            <div class="flex space-x-2">
                                <button type="submit" name="period" value="7days" class="px-3 py-2 border rounded-md text-sm <?php echo $period === '7days' ? 'bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                    Last 7 Days
                                </button>
                                <button type="submit" name="period" value="30days" class="px-3 py-2 border rounded-md text-sm <?php echo $period === '30days' ? 'bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                    Last 30 Days
                                </button>
                                <button type="submit" name="period" value="90days" class="px-3 py-2 border rounded-md text-sm <?php echo $period === '90days' ? 'bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                    Last 90 Days
                                </button>
                                <button type="submit" name="period" value="year" class="px-3 py-2 border rounded-md text-sm <?php echo $period === 'year' ? 'bg-blue-50 border-blue-500 text-blue-600' : 'border-gray-300 text-gray-700 hover:bg-gray-50'; ?>">
                                    This Year
                                </button>
                            </div>
                        </div>
                        
                        <div class="flex-1">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Custom Range</label>
                            <div class="flex space-x-2">
                                <input type="date" name="start_date" value="<?php echo $period === 'custom' ? $start_date : ''; ?>" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <span class="flex items-center text-gray-500">to</span>
                                <input type="date" name="end_date" value="<?php echo $period === 'custom' ? $end_date : ''; ?>" class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm">
                                <button type="submit" name="period" value="custom" class="px-3 py-2 bg-blue-600 text-white rounded-md text-sm hover:bg-blue-700">
                                    Apply
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
                
                <!-- Analytics Overview -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-bold mb-4">Overview</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm text-gray-500 mb-1">Total Bookings</h3>
                            <p class="text-2xl font-bold"><?php echo $analytics['total_bookings']; ?></p>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php 
                                    if ($period === 'custom') {
                                        echo date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
                                    } elseif ($period === 'year') {
                                        echo date('Y');
                                    } else {
                                        echo 'Last ' . str_replace('days', ' days', $period);
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm text-gray-500 mb-1">Total Revenue</h3>
                            <p class="text-2xl font-bold">$<?php echo number_format($analytics['total_revenue'], 2); ?></p>
                            <div class="text-xs text-gray-500 mt-1">
                                <?php 
                                    if ($period === 'custom') {
                                        echo date('M j, Y', strtotime($start_date)) . ' - ' . date('M j, Y', strtotime($end_date));
                                    } elseif ($period === 'year') {
                                        echo date('Y');
                                    } else {
                                        echo 'Last ' . str_replace('days', ' days', $period);
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm text-gray-500 mb-1">Confirmed Revenue</h3>
                            <p class="text-2xl font-bold">$<?php echo number_format($analytics['confirmed_revenue'], 2); ?></p>
                            <div class="text-xs text-gray-500 mt-1">Confirmed & Completed Bookings</div>
                        </div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-sm text-gray-500 mb-1">Confirmation Rate</h3>
                            <p class="text-2xl font-bold">
                                <?php 
                                    $confirmationRate = $analytics['total_bookings'] > 0 
                                        ? round((($analytics['confirmed'] + $analytics['completed']) / $analytics['total_bookings']) * 100, 1) 
                                        : 0;
                                    echo $confirmationRate . '%'; 
                                ?>
                            </p>
                            <div class="text-xs text-gray-500 mt-1">Confirmed/Completed vs. Total</div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Revenue Chart -->
                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                            <h3 class="text-md font-semibold mb-4">Revenue Trend</h3>
                            <canvas id="revenueChart" height="300"></canvas>
                        </div>
                        
                        <!-- Booking Status Chart -->
                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                            <h3 class="text-md font-semibold mb-4">Booking Status Distribution</h3>
                            <canvas id="bookingStatusChart" height="300"></canvas>
                        </div>
                    </div>
                </div>
                
                <!-- Top Services -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-bold mb-4">Top Services by Revenue</h2>
                    
                    <?php if (empty($topServices)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <p>No service data available for the selected period.</p>
                    </div>
                    <?php else: ?>
                    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                        <!-- Top Services Chart -->
                        <div class="bg-white p-4 rounded-lg border border-gray-200">
                            <canvas id="topServicesChart" height="300"></canvas>
                        </div>
                        
                        <!-- Top Services Table -->
                        <div class="bg-white p-4 rounded-lg border border-gray-200 overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-gray-50">
                                        <th class="py-3 px-4 text-left">Service</th>
                                        <th class="py-3 px-4 text-right">Bookings</th>
                                        <th class="py-3 px-4 text-right">Revenue</th>
                                        <th class="py-3 px-4 text-right">% of Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($topServices as $service): ?>
                                    <tr class="border-t border-gray-100">
                                        <td class="py-3 px-4"><?php echo htmlspecialchars($service['name'] ?? 'Unnamed Service'); ?></td>
                                        <td class="py-3 px-4 text-right"><?php echo $service['booking_count'] ?? 0; ?></td>
                                        <td class="py-3 px-4 text-right">$<?php echo number_format($service['revenue'] ?? 0, 2); ?></td>
                                        <td class="py-3 px-4 text-right">
                                            <?php 
                                                $percentage = round($service['percentage'] ?? 0, 1); 
                                                echo $percentage . '%'; 
                                            ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Recent Customers -->
                <div class="bg-white p-6 rounded-lg shadow-md mb-6">
                    <h2 class="text-xl font-bold mb-4">Recent Customers</h2>
                    
                    <?php if (empty($recentCustomers)): ?>
                    <div class="text-center text-gray-500 py-8">
                        <p>No customer data available for the selected period.</p>
                    </div>
                    <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="bg-gray-50">
                                    <th class="py-3 px-4 text-left">Customer</th>
                                    <th class="py-3 px-4 text-left">Email</th>
                                    <th class="py-3 px-4 text-right">Total Spent</th>
                                    <th class="py-3 px-4 text-right">Bookings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentCustomers as $customer): ?>
                                <tr class="border-t border-gray-100">
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($customer['name']); ?></td>
                                    <td class="py-3 px-4"><?php echo htmlspecialchars($customer['email']); ?></td>
                                    <td class="py-3 px-4 text-right">$<?php echo number_format($customer['total_spent'], 2); ?></td>
                                    <td class="py-3 px-4 text-right"><?php echo $customer['booking_count']; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
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
            sidebar.classList.toggle('-translate-x-full');
        });

        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Initialize charts
        document.addEventListener('DOMContentLoaded', function() {
            // Check if Chart object exists
            if (typeof Chart !== 'undefined') {
                // Set default font family and size
                Chart.defaults.font = {
                    family: 'system-ui, -apple-system, "Segoe UI", Roboto, sans-serif',
                    size: 12
                };
                
                // Revenue chart
                const revenueLabels = <?php echo json_encode(array_column($revenueData, 'label')); ?>;
                const revenueValues = <?php echo json_encode(array_column($revenueData, 'value')); ?>;
                
                const revenueCtx = document.getElementById('revenueChart').getContext('2d');
                new Chart(revenueCtx, {
                    type: 'line',
                    data: {
                        labels: revenueLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: revenueValues,
                            backgroundColor: 'rgba(79, 70, 229, 0.2)',
                            borderColor: 'rgba(79, 70, 229, 1)',
                            borderWidth: 2,
                            pointBackgroundColor: 'rgba(79, 70, 229, 1)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.y.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
                
                // Booking Status chart
                const statusCtx = document.getElementById('bookingStatusChart').getContext('2d');
                new Chart(statusCtx, {
                    type: 'doughnut',
                    data: {
                        labels: ['Pending', 'Confirmed', 'Completed', 'Cancelled'],
                        datasets: [{
                            data: [
                                <?php echo $analytics['pending']; ?>,
                                <?php echo $analytics['confirmed']; ?>,
                                <?php echo $analytics['completed']; ?>,
                                <?php echo $analytics['cancelled']; ?>
                            ],
                            backgroundColor: [
                                'rgba(251, 191, 36, 0.8)',   // amber-400
                                'rgba(59, 130, 246, 0.8)',   // blue-500
                                'rgba(16, 185, 129, 0.8)',   // green-500
                                'rgba(239, 68, 68, 0.8)'     // red-500
                            ],
                            borderColor: [
                                'rgba(251, 191, 36, 1)',
                                'rgba(59, 130, 246, 1)',
                                'rgba(16, 185, 129, 1)',
                                'rgba(239, 68, 68, 1)'
                            ],
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'right'
                            }
                        }
                    }
                });
                
                <?php if (!empty($topServices)): ?>
                // Top Services chart
                const serviceLabels = <?php echo json_encode(array_column($topServices, 'name')); ?>;
                const serviceValues = <?php echo json_encode(array_column($topServices, 'revenue')); ?>;
                const serviceColors = ['#4F46E5', '#7C3AED', '#DB2777', '#EC4899', '#F59E0B'];
                
                const servicesCtx = document.getElementById('topServicesChart').getContext('2d');
                new Chart(servicesCtx, {
                    type: 'bar',
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            label: 'Revenue',
                            data: serviceValues,
                            backgroundColor: serviceColors.slice(0, serviceLabels.length),
                            borderWidth: 0
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        scales: {
                            x: {
                                beginAtZero: true,
                                ticks: {
                                    callback: function(value) {
                                        return '$' + value;
                                    }
                                }
                            }
                        },
                        plugins: {
                            legend: {
                                display: false
                            },
                            tooltip: {
                                callbacks: {
                                    label: function(context) {
                                        return '$' + context.parsed.x.toFixed(2);
                                    }
                                }
                            }
                        }
                    }
                });
                <?php endif; ?>
            }
        });
    </script>
</body>
</html>