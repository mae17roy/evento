<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include the file containing the requireAdminLogin function
require_once 'functions.php';

// Include database configuration
require_once 'config.php';

// Check if user is logged in and has owner role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
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

// Process reservation status updates
if (isset($_POST['update_booking_status'])) {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $newStatus = isset($_POST['status']) ? htmlspecialchars(trim($_POST['status'])) : '';
    
    // Validate status value
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: owner_index.php");
        exit();
    }
    
    try {
        // First check if this booking is for a service owned by this admin
        $checkOwnerStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM bookings b
            JOIN booking_items bi ON bi.booking_id = b.id
            JOIN services s ON bi.service_id = s.id
            WHERE b.id = ? AND s.owner_id = ?
        ");
        $checkOwnerStmt->execute([$bookingId, $_SESSION['user_id']]);
        $isOwner = $checkOwnerStmt->fetchColumn() > 0;
        
        if (!$isOwner) {
            $_SESSION['error_message'] = "You don't have permission to update this booking.";
            header("Location: owner_index.php");
            exit();
        }
        
        // Begin transaction
        $db->beginTransaction();
        
        // Update booking status
        $stmt = $db->prepare("UPDATE bookings SET status = :status WHERE id = :id");
        $stmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
        $stmt->bindParam(':id', $bookingId, PDO::PARAM_INT);
        $result = $stmt->execute();
        
        if ($result) {
            // Add status history record
            $historyStmt = $db->prepare("
                INSERT INTO booking_status_history (
                    booking_id,
                    status,
                    notes,
                    created_at
                ) VALUES (
                    :booking_id,
                    :status,
                    :notes,
                    NOW()
                )
            ");
            
            $notes = "Status updated to " . $newStatus . " by owner.";
            $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $historyStmt->bindParam(':status', $newStatus, PDO::PARAM_STR);
            $historyStmt->bindParam(':notes', $notes, PDO::PARAM_STR);
            $historyStmt->execute();
            
            // Get customer ID for notification
            $customerStmt = $db->prepare("SELECT user_id FROM bookings WHERE id = ?");
            $customerStmt->execute([$bookingId]);
            $customerId = $customerStmt->fetchColumn();
            
            if ($customerId) {
                // Create notification for customer with explicit message (no NULL values)
                $notificationStmt = $db->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        related_id,
                        created_at,
                        is_read
                    ) VALUES (
                        :user_id,
                        'booking',
                        :title,
                        :message,
                        :related_id,
                        NOW(),
                        0
                    )
                ");
                
                // Create more descriptive notifications based on status
                if ($newStatus === 'confirmed') {
                    $notificationTitle = "Booking #" . $bookingId . " has been Confirmed!";
                    $notificationMessage = "Good news! The service provider has confirmed your booking #" . $bookingId . 
                                          ". Your service is now scheduled as requested. You can view the details in My Bookings.";
                } else if ($newStatus === 'cancelled') {
                    $notificationTitle = "Booking #" . $bookingId . " has been Cancelled";
                    $notificationMessage = "We're sorry, but your booking #" . $bookingId . 
                                          " has been cancelled by the service provider. Please contact support if you have any questions.";
                } else if ($newStatus === 'completed') {
                    $notificationTitle = "Booking #" . $bookingId . " Marked as Completed";
                    $notificationMessage = "Your booking #" . $bookingId . 
                                          " has been marked as completed. Thank you for using our services!";
                } else {
                    $notificationTitle = "Booking #" . $bookingId . " Status Updated";
                    $notificationMessage = "Your booking status has been updated to: " . ucfirst($newStatus);
                }
                
                $notificationStmt->bindParam(':user_id', $customerId, PDO::PARAM_INT);
                $notificationStmt->bindParam(':title', $notificationTitle, PDO::PARAM_STR);
                $notificationStmt->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
                $notificationStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
                $notificationStmt->execute();
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success_message'] = "Booking status updated successfully!";
        } else {
            // Rollback on error
            $db->rollBack();
            $_SESSION['error_message'] = "Failed to update booking status.";
        }
    } catch (PDOException $e) {
        // Rollback on exception
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Database error updating booking status: " . $e->getMessage());
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("Location: owner_index.php");
    exit();
}

// Get data for the dashboard - Using enhanced functions with error checking
try {
    $metrics = getOwnerDashboardMetrics($db, $_SESSION['user_id']);
    if (!is_array($metrics)) {
        $metrics = [
            'total_services' => 0,
            'active_services' => 0,
            'total_categories' => 0,
            'active_services_percent' => 0,
            'pending_reservations' => 0,
            'pending_percent' => 0,
            'confirmed_bookings' => 0,
            'confirmed_percent' => 0,
            'completed_bookings' => 0,
            'total_bookings' => 0,
            'total_revenue' => 0,
            'completed_revenue' => 0,
            'customer_count' => 0,
            'recent_activity' => []
        ];
    }
} catch (Exception $e) {
    error_log("Error getting dashboard metrics: " . $e->getMessage());
    $metrics = [
        'total_services' => 0,
        'active_services' => 0,
        'total_categories' => 0,
        'active_services_percent' => 0,
        'pending_reservations' => 0,
        'pending_percent' => 0,
        'confirmed_bookings' => 0,
        'confirmed_percent' => 0,
        'completed_bookings' => 0,
        'total_bookings' => 0,
        'total_revenue' => 0,
        'completed_revenue' => 0,
        'customer_count' => 0,
        'recent_activity' => []
    ];
}

// Get pending bookings that need attention
try {
    $pendingStmt = $db->prepare("
        SELECT DISTINCT b.id, b.booking_date, b.booking_time, b.total_amount, b.created_at,
               b.status, u.name as customer_name, u.email as customer_email
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON b.user_id = u.id
        WHERE s.owner_id = :owner_id
        AND b.status = 'pending'
        ORDER BY b.created_at DESC
        LIMIT 5
    ");
    
    $pendingStmt->bindParam(':owner_id', $_SESSION['user_id'], PDO::PARAM_INT);
    $pendingStmt->execute();
    $pendingBookings = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting pending bookings: " . $e->getMessage());
    $pendingBookings = [];
}

try {
    $reservations = getRecentReservations($db, $_SESSION['user_id']);
    if (!is_array($reservations)) {
        $reservations = [];
    }
} catch (Exception $e) {
    error_log("Error getting reservations: " . $e->getMessage());
    $reservations = [];
}

try {
    $upcomingBookings = getUpcomingBookings($db, $_SESSION['user_id']);
    if (!is_array($upcomingBookings)) {
        $upcomingBookings = [];
    }
} catch (Exception $e) {
    error_log("Error getting upcoming bookings: " . $e->getMessage());
    $upcomingBookings = [];
}

try {
    $topServices = getTopServices($db, $_SESSION['user_id']);
    if (!is_array($topServices)) {
        $topServices = [];
    }
} catch (Exception $e) {
    error_log("Error getting top services: " . $e->getMessage());
    $topServices = [];
}

try {
    $recentReviews = getRecentReviews($db, $_SESSION['user_id']);
    if (!is_array($recentReviews)) {
        $recentReviews = [];
    }
} catch (Exception $e) {
    error_log("Error getting recent reviews: " . $e->getMessage());
    $recentReviews = [];
}

// Process owner notifications with more detailed information
foreach ($ownerNotifications as $ownerInfo) {
    $ownerId = $ownerInfo['owner_id'];
    $businessName = !empty($ownerInfo['business_name']) ? $ownerInfo['business_name'] : 'Service Provider';
    $services = $ownerInfo['services'];
    
    $ownerNotifStmt = $db->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            related_id,
            created_at,
            is_read
        ) VALUES (
            :user_id,
            'booking',
            :title,
            :message,
            :related_id,
            NOW(),
            0
        )
    ");
    
    // Create a more detailed notification message with fallbacks for empty values
    $servicesText = "service";
    if (!empty($services)) {
        $servicesText = count($services) > 1 
            ? "multiple services including " . htmlspecialchars($services[0])
            : htmlspecialchars($services[0]);
    }
    
    $formattedDate = date('l, F j, Y', strtotime($bookingDate));
    $formattedTime = date('g:i A', strtotime($bookingTime));
    $customerName = !empty($name) ? htmlspecialchars($name) : "Customer";
    
    $ownerTitle = "⚠️ ACTION REQUIRED: New Booking #" . $bookingId;
    $ownerMessage = "You have received a new booking for " . $servicesText . ".\n\n" .
                    "Customer: " . $customerName . "\n" .
                    "Date: " . $formattedDate . "\n" .
                    "Time: " . $formattedTime . "\n" .
                    "Amount: $" . number_format($total, 2) . "\n\n" .
                    "This booking requires your confirmation. Please go to the Booking Management page to confirm or decline this booking.";
    
    // Double-check that message is never NULL
    if (empty($ownerMessage)) {
        $ownerMessage = "New booking #" . $bookingId . " requires your confirmation. Please check the Booking Management page.";
    }
    
    $ownerNotifStmt->bindParam(':user_id', $ownerId, PDO::PARAM_INT);
    $ownerNotifStmt->bindParam(':title', $ownerTitle, PDO::PARAM_STR);
    $ownerNotifStmt->bindParam(':message', $ownerMessage, PDO::PARAM_STR);
    $ownerNotifStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
    $ownerNotifStmt->execute();
}
// Get more detailed analytics data for charts
$ownerId = $_SESSION['user_id'];
$today = date('Y-m-d');
$start_date = date('Y-m-d', strtotime('-30 days'));
$end_date = $today;

// Get monthly booking statistics
if (function_exists('getMonthlyBookingStats')) {
    $analyticsData = getMonthlyBookingStats($db, $ownerId, $start_date, $end_date);
} else {
    // Fallback if function doesn't exist
    $analyticsData = [
        'total_bookings' => $metrics['total_bookings'] ?? 0,
        'pending' => $metrics['pending_reservations'] ?? 0,
        'confirmed' => $metrics['confirmed_bookings'] ?? 0,
        'completed' => $metrics['completed_bookings'] ?? 0,
        'cancelled' => 0,
        'total_revenue' => $metrics['total_revenue'] ?? 0,
        'confirmed_revenue' => $metrics['completed_revenue'] ?? 0,
        'bookings_by_day' => []
    ];
}

// Get revenue data for last 30 days
$dateLabels = [];
$revenueValues = [];

try {
    $stmt = $db->prepare("
        SELECT 
            DATE(b.booking_date) as day,
            SUM(b.total_amount) as daily_revenue
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        WHERE s.owner_id = :owner_id
        AND b.booking_date BETWEEN :start_date AND :end_date
        GROUP BY DATE(b.booking_date)
        ORDER BY day
    ");
    
    $stmt->execute([
        'owner_id' => $ownerId,
        'start_date' => $start_date,
        'end_date' => $end_date
    ]);
    
    $rawRevenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Initialize array for all dates in range
    $period = new DatePeriod(
        new DateTime($start_date),
        new DateInterval('P1D'),
        new DateTime($end_date . ' +1 day')
    );
    
    foreach ($period as $date) {
        $dateStr = $date->format('Y-m-d');
        $dateLabels[] = $date->format('M d');
        $revenueValues[] = 0; // Default to 0
    }
    
    // Fill in actual revenue data
    foreach ($rawRevenueData as $item) {
        $index = array_search(date('M d', strtotime($item['day'])), $dateLabels);
        if ($index !== false) {
            $revenueValues[$index] = floatval($item['daily_revenue']);
        }
    }
    
} catch (PDOException $e) {
    error_log("Error getting revenue data: " . $e->getMessage());
    $dateLabels = [];
    $revenueValues = [];
}

// Get top services with revenue
$serviceLabels = [];
$serviceData = [];
$serviceColors = ['#4F46E5', '#7C3AED', '#DB2777', '#EC4899', '#F59E0B'];
$topServicesByRevenue = [];
$totalRevenue = 0;

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
    
    $topServicesByRevenue = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate percentage for top services
    foreach ($topServicesByRevenue as $service) {
        $totalRevenue += floatval($service['revenue'] ?? 0);
    }
    
    foreach ($topServicesByRevenue as $index => $service) {
        $serviceLabels[] = $service['name'];
        $serviceData[] = floatval($service['revenue']);
    }
    
} catch (PDOException $e) {
    error_log("Error getting top services data: " . $e->getMessage());
    $serviceLabels = [];
    $serviceData = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo !empty($user['business_name']) ? htmlspecialchars($user['business_name']) . ' - ' : ''; ?>
        Owner Dashboard
    </title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Metro UI CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro-icons.min.css">
    <!-- Chart.js for statistics -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Custom CSS -->
    <style>
        .sidebar-item.active {
            background-color: #f3f4f6;
        }
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-confirmed {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-completed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #b91c1c;
        }
        .notification-panel {
            max-height: 280px;
            overflow-y: auto;
        }
        .metric-card {
            transition: all 0.3s ease;
        }
        .metric-card:hover {
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        .rating {
            display: inline-flex;
        }
        .rating .star {
            color: #d1d5db; /* gray-300 */
        }
        .rating .star.filled {
            color: #facc15; /* yellow-400 */
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
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        .animate-pulse {
            animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
    </style>
</head>
<body class="bg-gray-50">
    <!-- Success/Error Messages -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div id="success-alert" class="fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-md shadow-md z-50">
            <?php echo htmlspecialchars($_SESSION['success_message']); ?>
            <button class="ml-4 font-bold" onclick="document.getElementById('success-alert').style.display='none'">×</button>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['error_message'])): ?>
        <div id="error-alert" class="fixed top-4 right-4 bg-red-500 text-white px-6 py-3 rounded-md shadow-md z-50">
            <?php echo htmlspecialchars($_SESSION['error_message']); ?>
            <button class="ml-4 font-bold" onclick="document.getElementById('error-alert').style.display='none'">×</button>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <div class="flex h-screen">
        <!-- Mobile Menu Button (visible on small screens) -->
        <div class="mobile-only fixed top-4 left-4 z-40">
            <button id="mobile-menu-button" class="bg-white p-2 rounded-md shadow-md">
                <i class="fas fa-bars text-2xl"></i>
            </button>
        </div>
        
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white border-r border-gray-200 flex flex-col fixed h-full z-30 transform transition-transform duration-300 ease-in-out md:translate-x-0">
            <div class="p-4 border-b border-gray-200">
                <h1 class="text-xl font-bold">EVENTO</h1>
            </div>
            
            <div class="flex items-center p-4 border-b border-gray-200">
                <img src="https://i.pravatar.cc/150?img=44" alt="User Avatar" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h2 class="font-medium"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <?php if(!empty($user['business_name'])): ?>
                    <p class="text-sm text-blue-600"><?php echo htmlspecialchars($user['business_name']); ?></p>
                    <?php endif; ?>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
            </div>
            
            <nav class="flex-1 py-4">
                <a href="owner_index.php" class="flex items-center px-4 py-3 sidebar-item active">
                    <i class="fas fa-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="service_management.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-cogs mr-3"></i>
                    <span>Service Management</span>
                </a>
                <a href="booking_management.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-calendar-check mr-3"></i>
                    <span>Booking Management</span>
                    <?php if (isset($metrics['pending_reservations']) && $metrics['pending_reservations'] > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded-full text-xs">
                        <?php echo $metrics['pending_reservations']; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="calendar.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-calendar mr-3"></i>
                    <span>Calendar</span>
                </a>
                <a href="customers.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-users mr-3"></i>
                    <span>Customers</span>
                </a>
                <a href="analytics.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-chart-bar mr-3"></i>
                    <span>Analytics</span>
                </a>
                <a href="notifications.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-bell mr-3"></i>
                    <span>Notifications</span>
                    <span class="ml-auto bg-gray-200 text-gray-700 px-2 py-1 rounded-full text-xs">
                        <?php echo $unreadNotificationsCount; ?>
                    </span>
                </a>
            </nav>
            
            <div class="mt-auto">
                <a href="settings.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-cog mr-3"></i>
                    <span>Settings</span>
                </a>
                <a href="support.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-question mr-3"></i>
                    <span>Help & Support</span>
                </a>
                <a href="logout.php" id="logout-link" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-sign-out-alt mr-3"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 flex flex-col overflow-hidden md:ml-64">
            <!-- Top Navigation -->
            <header class="bg-white border-b border-gray-200 flex items-center justify-between p-4">
                <div class="flex-1">
                    <?php if(!empty($user['business_name'])): ?>
                    <h1 class="text-2xl font-bold"><?php echo htmlspecialchars($user['business_name']); ?></h1>
                    <?php else: ?>
                    <h1 class="text-2xl font-bold">EVENTO</h1>
                    <?php endif; ?>
                </div>
                
                <div class="w-1/3">
                    <div class="relative">
                        <input type="text" id="search-input" placeholder="Search reservations, services..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md">
                        <i class="fas fa-search absolute left-3 top-2.5 text-gray-400"></i>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <div class="relative mr-4">
                        <button id="notification-toggle" class="focus:outline-none">
                            <i class="fas fa-bell text-xl cursor-pointer hover:text-gray-700"></i>
                            <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                                <?php echo $unreadNotificationsCount; ?>
                            </span>
                        </button>
                    </div>
                    <img src="https://i.pravatar.cc/150?img=44" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <!-- Dashboard Content -->
            <div class="flex-1 overflow-y-auto p-4">
                <!-- Key Metrics Section -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
                    <div class="bg-white rounded-lg shadow-sm p-6 metric-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Total Bookings</h3>
                            <div class="bg-blue-100 p-2 rounded-full">
                                <i class="fas fa-calendar-check text-blue-600"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $metrics['total_bookings']; ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-sm text-gray-500">
                                Last 30 days
                            </span>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 metric-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Pending Approvals</h3>
                            <div class="bg-yellow-100 p-2 rounded-full">
                                <i class="fas fa-clock text-yellow-600"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $metrics['pending_reservations']; ?></p>
                        <div class="flex items-center mt-2">
                            <?php if ($metrics['pending_reservations'] > 0): ?>
                            <a href="booking_management.php" class="text-sm text-yellow-600 font-medium hover:underline">
                                Action required <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                            <?php else: ?>
                            <span class="text-sm text-green-500">
                                <i class="fas fa-check mr-1"></i> All caught up!
                            </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 metric-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Revenue</h3>
                            <div class="bg-green-100 p-2 rounded-full">
                                <i class="fas fa-dollar-sign text-green-600"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-800">$<?php echo number_format($metrics['total_revenue'], 2); ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-sm text-gray-500">
                                Last 30 days
                            </span>
                        </div>
                    </div>
                    
                    <div class="bg-white rounded-lg shadow-sm p-6 metric-card">
                        <div class="flex justify-between items-center mb-4">
                            <h3 class="text-lg font-semibold text-gray-700">Active Services</h3>
                            <div class="bg-purple-100 p-2 rounded-full">
                                <i class="fas fa-cog text-purple-600"></i>
                            </div>
                        </div>
                        <p class="text-3xl font-bold text-gray-800"><?php echo $metrics['active_services']; ?></p>
                        <div class="flex items-center mt-2">
                            <span class="text-sm text-gray-500">
                                Total: <?php echo $metrics['total_services']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Pending Bookings Alert Section (New) -->
                <?php if (count($pendingBookings) > 0): ?>
                <div class="bg-yellow-50 border-l-4 border-yellow-500 p-4 rounded-md mb-6">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-exclamation-circle text-yellow-500"></i>
                        </div>
                        <div class="ml-3">
                            <h3 class="text-sm font-medium text-yellow-800">
                                You have <?php echo count($pendingBookings); ?> pending booking<?php echo count($pendingBookings) > 1 ? 's' : ''; ?> that require your attention
                            </h3>
                            <div class="mt-2 text-sm text-yellow-700">
                                <p>Please review and confirm or decline these bookings as soon as possible. Your customers are waiting for your response.</p>
                            </div>
                            <div class="mt-3">
                                <a href="booking_management.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-yellow-600 hover:bg-yellow-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500">
                                    Manage Pending Bookings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Main Dashboard Sections -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Pending Bookings Section (New) -->
                    <div class="lg:col-span-2 bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 p-4 flex justify-between items-center">
                            <h2 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-clock text-yellow-500 mr-2"></i> Bookings Requiring Approval
                            </h2>
                            <?php if (count($pendingBookings) > 0): ?>
                            <a href="booking_management.php" class="text-sm text-primary-600 hover:text-primary-700">
                                View All <i class="fas fa-arrow-right ml-1"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                        
                        <div class="p-4">
                            <?php if (count($pendingBookings) > 0): ?>
                                <div class="overflow-hidden">
                                    <ul class="divide-y divide-gray-200">
                                        <?php foreach ($pendingBookings as $booking): ?>
                                            <li class="py-3">
                                                <div class="flex items-center justify-between">
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0">
                                                            <div class="w-10 h-10 rounded-full bg-yellow-100 flex items-center justify-center">
                                                                <i class="fas fa-calendar-alt text-yellow-600"></i>
                                                            </div>
                                                        </div>
                                                        <div class="ml-4">
                                                            <h4 class="text-sm font-medium text-gray-900">Booking #<?php echo $booking['id']; ?></h4>
                                                            <p class="text-xs text-gray-500">
                                                                <?php echo htmlspecialchars($booking['customer_name']); ?> •
                                                                <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?> at
                                                                <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div class="flex space-x-2">
                                                        <form action="" method="POST" class="inline">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" name="update_booking_status" class="px-3 py-1 bg-green-500 text-white text-xs rounded-md hover:bg-green-600">
                                                                <i class="fas fa-check mr-1"></i> Confirm
                                                            </button>
                                                        </form>
                                                        <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to decline this booking?');">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" name="update_booking_status" class="px-3 py-1 bg-red-500 text-white text-xs rounded-md hover:bg-red-600">
                                                                <i class="fas fa-times mr-1"></i> Decline
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-6">
                                    <div class="mx-auto w-16 h-16 bg-green-100 rounded-full flex items-center justify-center mb-4">
                                        <i class="fas fa-check text-green-500 text-2xl"></i>
                                    </div>
                                    <h3 class="text-lg font-medium text-gray-900 mb-1">All caught up!</h3>
                                    <p class="text-gray-500">No pending bookings require your approval at this time.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Quick Stats Card -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 p-4">
                            <h2 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-chart-pie text-blue-500 mr-2"></i> Booking Statistics
                            </h2>
                        </div>
                        
                        <div class="p-4">
                            <div class="space-y-4">
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Confirmed</span>
                                        <span class="text-sm font-medium text-gray-700"><?php echo $metrics['confirmed_percent']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-green-500 h-2 rounded-full" style="width: <?php echo $metrics['confirmed_percent']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Pending</span>
                                        <span class="text-sm font-medium text-gray-700"><?php echo $metrics['pending_percent']; ?>%</span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-yellow-500 h-2 rounded-full" style="width: <?php echo $metrics['pending_percent']; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex justify-between mb-1">
                                        <span class="text-sm font-medium text-gray-700">Completed</span>
                                        <span class="text-sm font-medium text-gray-700">
                                            <?php 
                                                $completedPercent = $metrics['total_bookings'] > 0 
                                                    ? round(($metrics['completed_bookings'] / $metrics['total_bookings']) * 100, 1) 
                                                    : 0;
                                                echo $completedPercent;
                                            ?>%
                                        </span>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-2">
                                        <div class="bg-blue-500 h-2 rounded-full" style="width: <?php echo $completedPercent; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-gray-200">
                            
                            <div>
                                <h3 class="text-sm font-medium text-gray-700 mb-3">Revenue Breakdown</h3>
                                <div class="grid grid-cols-2 gap-4">
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-sm text-gray-500">Total Revenue</div>
                                        <div class="text-lg font-semibold text-gray-800">$<?php echo number_format($metrics['total_revenue'], 2); ?></div>
                                    </div>
                                    <div class="bg-gray-50 p-3 rounded-lg">
                                        <div class="text-sm text-gray-500">Completed Revenue</div>
                                        <div class="text-lg font-semibold text-gray-800">$<?php echo number_format($metrics['completed_revenue'], 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Upcoming Bookings Section -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden mb-6">
                    <div class="border-b border-gray-200 p-4 flex justify-between items-center">
                        <h2 class="text-lg font-semibold text-gray-800">
                            <i class="fas fa-calendar-alt text-primary-600 mr-2"></i> Upcoming Bookings
                        </h2>
                        <a href="calendar.php" class="text-sm text-primary-600 hover:text-primary-700">
                            View Calendar <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>
                    
                    <div class="overflow-x-auto">
                        <?php if (!empty($upcomingBookings)): ?>
                            <table class="min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Booking #
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Customer
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Service
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Date & Time
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Status
                                        </th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($upcomingBookings as $booking): ?>
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="text-sm font-medium text-gray-900">#<?php echo $booking['id']; ?></span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($booking['service_name']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900">
                                                    <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                                </div>
                                                <div class="text-sm text-gray-500">
                                                    <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                    <?php echo ucfirst($booking['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="text-primary-600 hover:text-primary-900 mr-3">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <?php if ($booking['status'] === 'confirmed'): ?>
                                                    <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to mark this booking as completed?');">
                                                        <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_booking_status" class="text-green-600 hover:text-green-900">
                                                            <i class="fas fa-check"></i> Complete
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <p>No upcoming bookings found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Additional Dashboard Sections -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Top Services Section -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 p-4">
                            <h2 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-award text-yellow-500 mr-2"></i> Top Services
                            </h2>
                        </div>
                        
                        <div class="p-4">
                            <?php if (!empty($topServices)): ?>
                                <ul class="divide-y divide-gray-200">
                                    <?php foreach ($topServices as $service): ?>
                                        <li class="py-3 flex items-center justify-between">
                                            <div class="flex items-center">
                                                <div class="flex-shrink-0">
                                                    <img class="h-10 w-10 rounded-md object-cover" 
                                                         src="<?php echo !empty($service['image']) ? 'uploads/services/' . htmlspecialchars($service['image']) : 'assets/img/default-service.jpg'; ?>" 
                                                         alt="<?php echo htmlspecialchars($service['name']); ?>">
                                                </div>
                                                <div class="ml-3">
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($service['name']); ?></p>
                                                    <p class="text-sm text-gray-500">
                                                        <?php echo $service['bookings_count']; ?> booking<?php echo $service['bookings_count'] !== 1 ? 's' : ''; ?>
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="text-right">
                                                <span class="text-sm font-medium text-gray-900">$<?php echo number_format($service['revenue'], 2); ?></span>
                                                <div class="text-xs text-gray-500">Revenue</div>
                                            </div>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                                <div class="mt-4 text-center">
                                    <a href="service_management.php" class="text-sm text-primary-600 hover:text-primary-700">
                                        Manage Services <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-6">
                                    <p class="text-gray-500">No service data available.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Recent Reviews Section -->
                    <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                        <div class="border-b border-gray-200 p-4">
                            <h2 class="text-lg font-semibold text-gray-800">
                                <i class="fas fa-star text-yellow-400 mr-2"></i> Recent Reviews
                            </h2>
                        </div>
                        
                        <div class="p-4">
                            <?php if (!empty($recentReviews)): ?>
                                <div class="space-y-4">
                                    <?php foreach ($recentReviews as $review): ?>
                                        <div class="border-b border-gray-100 pb-4 last:border-b-0 last:pb-0">
                                            <div class="flex justify-between mb-1">
                                                <h4 class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($review['customer_name']); ?></h4>
                                                <span class="text-xs text-gray-500"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></span>
                                            </div>
                                            <div class="flex mb-1">
                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                    <?php if ($i <= $review['rating']): ?>
                                                        <i class="fas fa-star text-yellow-400"></i>
                                                    <?php else: ?>
                                                        <i class="far fa-star text-yellow-400"></i>
                                                    <?php endif; ?>
                                                <?php endfor; ?>
                                            </div>
                                            <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['comment']); ?></p>
                                            <div class="mt-1 text-xs text-gray-500">
                                                Service: <?php echo htmlspecialchars($review['service_name']); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="mt-4 text-center">
                                    <a href="reviews.php" class="text-sm text-primary-600 hover:text-primary-700">
                                        View All Reviews <i class="fas fa-arrow-right ml-1"></i>
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="text-center py-6">
                                    <p class="text-gray-500">No reviews yet.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Notifications Sidebar (hidden by default) -->
        <div id="notifications-sidebar" class="w-80 bg-white border-l border-gray-200 fixed right-0 top-0 h-full transform translate-x-full transition-transform duration-300 ease-in-out z-30 overflow-hidden flex flex-col">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-medium">Notifications</h2>
                <button id="close-notifications" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="notification-panel flex-1 overflow-y-auto">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-gray-500">
                        No new notifications
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="p-4 border-b border-gray-100 <?php echo (isset($notification['is_read']) && !$notification['is_read'] ? 'bg-blue-50' : ''); ?>">
                        <div class="flex justify-between items-start">
                            <h3 class="font-medium mb-1"><?php echo isset($notification['title']) ? htmlspecialchars($notification['title']) : 'Notification'; ?></h3>
                            <span class="text-xs text-gray-500"><?php echo isset($notification['time']) ? htmlspecialchars($notification['time']) : ''; ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2"><?php echo isset($notification['message']) ? htmlspecialchars($notification['message']) : ''; ?></p>
                        <div class="flex justify-between items-center">
                            <?php if (isset($notification['related_id']) && isset($notification['type']) && $notification['type'] === 'booking'): ?>
                                <a href="view_booking.php?id=<?php echo (int)$notification['related_id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                            <?php elseif (isset($notification['related_id']) && isset($notification['type']) && $notification['type'] === 'service'): ?>
                                <a href="service_management.php" class="text-xs text-blue-600 hover:text-blue-800">View services</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            
                            <?php if (isset($notification['is_read']) && !$notification['is_read']): ?>
                                <form action="mark_notification_read.php" method="post" class="inline">
                                    <input type="hidden" name="notification_id" value="<?php echo (int)$notification['id']; ?>">
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
            sidebar.classList.toggle('-translate-x-full');
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
        
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // Logout functionality
        document.getElementById('logout-link').addEventListener('click', function(e) {
            e.preventDefault();
            
            // Show a notification before redirecting
            const alertDiv = document.createElement('div');
            alertDiv.className = 'fixed top-4 right-4 bg-green-500 text-white px-6 py-3 rounded-md shadow-md z-50';
            alertDiv.innerHTML = 'Logging out...';
            document.body.appendChild(alertDiv);
            
            // Redirect to logout.php after a short delay
            setTimeout(function() {
                window.location.href = 'logout.php';
            }, 1000);
        });
    </script>
</body>
</html>