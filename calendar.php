<?php
// Include functions file first
require_once 'functions.php';

// Include database configuration
require_once 'config.php';

// Require admin/owner login
requireAdminOwnerLogin();

// Define the missing function if it doesn't already exist
if (!function_exists('getMonthlyBookingStats')) {
    function getMonthlyBookingStats($db, $ownerId, $startDate, $endDate) {
        try {
            // Get total bookings
            $totalStmt = $db->prepare("
                SELECT 
                    COUNT(DISTINCT b.id) as total_bookings,
                    SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
                    SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                    SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
                    SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                    SUM(b.total_amount) as total_revenue,
                    SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as completed_revenue
                FROM bookings b
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE s.owner_id = :owner_id
                AND b.booking_date BETWEEN :start_date AND :end_date
            ");
            
            $totalStmt->execute([
                'owner_id' => $ownerId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            $stats = $totalStmt->fetch(PDO::FETCH_ASSOC);
            
            // If no stats found, provide defaults
            if (!$stats) {
                $stats = [
                    'total_bookings' => 0,
                    'pending' => 0,
                    'confirmed' => 0,
                    'completed' => 0,
                    'cancelled' => 0,
                    'total_revenue' => 0,
                    'completed_revenue' => 0
                ];
            }
            
            // Get bookings by day
            $dayStmt = $db->prepare("
                SELECT 
                    DATE(b.booking_date) as day,
                    COUNT(DISTINCT b.id) as num_bookings
                FROM bookings b
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE s.owner_id = :owner_id
                AND b.booking_date BETWEEN :start_date AND :end_date
                GROUP BY DATE(b.booking_date)
            ");
            
            $dayStmt->execute([
                'owner_id' => $ownerId,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]);
            
            $bookingsByDay = [];
            while ($day = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
                $bookingsByDay[$day['day']] = $day['num_bookings'];
            }
            
            $stats['bookings_by_day'] = $bookingsByDay;
            
            return $stats;
        } catch (PDOException $e) {
            // Log the error for debugging
            error_log("Error in getMonthlyBookingStats: " . $e->getMessage());
            
            // Return empty stats array on error
            return [
                'total_bookings' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0, 
                'cancelled' => 0,
                'total_revenue' => 0,
                'completed_revenue' => 0,
                'bookings_by_day' => []
            ];
        }
    }
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
            'role' => $_SESSION['role'] ?? 'owner'
        ];
    }
}

// Rest of your calendar.php file remains unchanged

// Get current date if not provided
$currentDate = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');
$currentMonth = date('m', strtotime($currentDate));
$currentYear = date('Y', strtotime($currentDate));

// Get first and last day of the month
$firstDayOfMonth = date('Y-m-01', strtotime($currentDate));
$lastDayOfMonth = date('Y-m-t', strtotime($currentDate));

// Get total days in month
$totalDays = date('t', strtotime($currentDate));

// Get the day of the week of the first day (0 for Sunday, 6 for Saturday)
$firstDayOfWeek = date('w', strtotime($firstDayOfMonth));

// Get booking statistics for this month
$monthlyStats = getMonthlyBookingStats($db, $_SESSION['user_id'], $firstDayOfMonth, $lastDayOfMonth);

// Get bookings for the current month - UPDATED to filter by owner_id
$stmt = $db->prepare("
    SELECT b.id, b.booking_date, b.booking_time, b.status, 
           b.total_amount, u.name as customer_name, u.email as customer_email,
           s.name as service_name, s.id as service_id
    FROM bookings b
    JOIN users u ON b.user_id = u.id
    JOIN booking_items bi ON bi.booking_id = b.id
    JOIN services s ON bi.service_id = s.id
    WHERE b.booking_date BETWEEN ? AND ?
    AND s.owner_id = ?  -- Filter to only show this owner's bookings
    ORDER BY b.booking_date, b.booking_time
");
$stmt->execute([$firstDayOfMonth, $lastDayOfMonth, $_SESSION['user_id']]);
$bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Organize bookings by date
$bookingsByDate = [];
foreach ($bookings as $booking) {
    $date = $booking['booking_date'];
    if (!isset($bookingsByDate[$date])) {
        $bookingsByDate[$date] = [];
    }
    $bookingsByDate[$date][] = $booking;
}

// Get services for this owner
$servicesStmt = $db->prepare("SELECT id, name FROM services WHERE owner_id = ? ORDER BY name");
$servicesStmt->execute([$_SESSION['user_id']]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$notifications = getRecentNotifications($db, $_SESSION['user_id']);
$unreadNotificationsCount = countUnreadNotifications($db, $_SESSION['user_id']);

// Get the previous and next month links
$prevMonth = date('Y-m-d', strtotime($firstDayOfMonth . ' -1 month'));
$nextMonth = date('Y-m-d', strtotime($firstDayOfMonth . ' +1 month'));

// AJAX handler for getting day details
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') {
    if (isset($_GET['action']) && $_GET['action'] === 'get_day_bookings' && isset($_GET['day'])) {
        $dayDate = $_GET['day'];
        
        // Validate date format
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayDate)) {
            echo json_encode(['success' => false, 'message' => 'Invalid date format']);
            exit;
        }
        
        // Get bookings for this day
        $dayBookingsStmt = $db->prepare("
            SELECT b.id, b.booking_date, b.booking_time, b.status, 
                   b.total_amount, u.name as customer_name, u.email as customer_email,
                   s.name as service_name, s.id as service_id
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN booking_items bi ON bi.booking_id = b.id
            JOIN services s ON bi.service_id = s.id
            WHERE b.booking_date = ?
            AND s.owner_id = ?
            ORDER BY b.booking_time
        ");
        $dayBookingsStmt->execute([$dayDate, $_SESSION['user_id']]);
        $dayBookings = $dayBookingsStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        $formattedBookings = [];
        foreach ($dayBookings as $booking) {
            $formattedBookings[] = [
                'id' => $booking['id'],
                'time' => formatTime($booking['booking_time']),
                'customer' => $booking['customer_name'],
                'email' => $booking['customer_email'],
                'service' => $booking['service_name'],
                'status' => $booking['status'],
                'amount' => number_format($booking['total_amount'], 2)
            ];
        }
        
        echo json_encode([
            'success' => true,
            'date' => formatDate($dayDate),
            'bookings' => $formattedBookings
        ]);
        exit;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Calendar - EVENTO</title>
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
        .calendar-day {
            min-height: 120px;
            transition: all 0.2s ease;
            position: relative;
        }
        .calendar-day:hover {
            background-color: #f9fafb;
        }
        .calendar-day.has-events {
            background-color: #f0f9ff;
        }
        .calendar-day.today {
            background-color: #f3f4f6;
            border: 2px solid #1a2e46;
        }
        .event-pending {
            background-color: #fff7ed;
            border-left: 3px solid #f59e0b;
        }
        .event-confirmed {
            background-color: #ecfdf5;
            border-left: 3px solid #10b981;
        }
        .event-completed {
            background-color: #eff6ff;
            border-left: 3px solid #3b82f6;
        }
        .event-cancelled {
            background-color: #fef2f2;
            border-left: 3px solid #ef4444;
        }
        .notification-panel {
            max-height: 280px;
            overflow-y: auto;
        }
        .event-count {
            position: absolute;
            top: 2px;
            right: 2px;
            background-color: #1a2e46;
            color: white;
            border-radius: 9999px;
            padding: 0.1rem 0.4rem;
            font-size: 0.75rem;
        }
        .day-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 100;
            align-items: center;
            justify-content: center;
        }
        .day-modal-content {
            background-color: white;
            border-radius: 0.5rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            width: 90%;
            max-width: 700px;
            max-height: 90vh;
            overflow-y: auto;
        }
        
        /* Toast notifications */
        #toast-container {
            position: fixed;
            top: 1rem;
            right: 1rem;
            z-index: 50;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }
        .toast {
            padding: 0.75rem 1.5rem;
            border-radius: 0.375rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            display: flex;
            align-items: flex-start;
        }
        .toast-success {
            background-color: #10b981;
            color: white;
        }
        .toast-error {
            background-color: #ef4444;
            color: white;
        }
        
        /* Responsive adjustments */
        @media (max-width: 1024px) {
            .desktop-only {
                display: none;
            }
            .calendar-grid {
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
    <!-- Toast Container -->
    <div id="toast-container"></div>
    
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
                <img src="https://i.pravatar.cc/150?img=<?php echo $user['id'] % 70; ?>" alt="User Avatar" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h2 class="font-medium"><?php echo htmlspecialchars($user['name']); ?></h2>
                    <p class="text-sm text-gray-500"><?php echo htmlspecialchars($user['role']); ?></p>
                </div>
            </div>
            
            <nav class="flex-1 py-4">
                <a href="owner_index.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="service_management.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-cogs mr-3"></i>
                    <span>Service Management</span>
                </a>
                <a href="reservations.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-calendar mr-3"></i>
                    <span>Reservations</span>
                </a>
                <a href="calendar.php" class="flex items-center px-4 py-3 sidebar-item active">
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
                    <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded-full text-xs">
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
                    <h1 class="text-2xl font-bold">Calendar</h1>
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
                    <img src="https://i.pravatar.cc/150?img=<?php echo $user['id'] % 70; ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <!-- Include the calendar content -->
            <?php include_once 'calendar_content.php'; ?>
        </div>
        
        <!-- Notifications Sidebar (hidden on mobile by default) -->
        <div id="notifications-sidebar" class="w-80 bg-white border-l border-gray-200 fixed right-0 top-0 h-full transform translate-x-full transition-transform duration-300 ease-in-out z-30">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <h2 class="text-lg font-medium">Notifications</h2>
                <button id="close-notifications" class="text-gray-400 hover:text-gray-600">
                    <i class="mif-cross"></i>
                </button>
            </div>
            
            <div class="notification-panel">
                <?php if (empty($notifications)): ?>
                    <div class="p-4 text-center text-gray-500">
                        No new notifications
                    </div>
                <?php else: ?>
                    <?php foreach ($notifications as $notification): ?>
                    <div class="p-4 border-b border-gray-100 <?php echo ($notification['is_read'] ? '' : 'bg-blue-50'); ?>">
                        <div class="flex justify-between items-start">
                            <h3 class="font-medium mb-1"><?php echo htmlspecialchars($notification['title']); ?></h3>
                            <span class="text-xs text-gray-500"><?php echo $notification['time']; ?></span>
                        </div>
                        <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                        <div class="flex justify-between items-center">
                            <?php if (!empty($notification['related_id']) && $notification['type'] === 'booking'): ?>
                                <a href="view_booking.php?id=<?php echo $notification['related_id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                            <?php elseif (!empty($notification['related_id']) && $notification['type'] === 'service'): ?>
                                <a href="service_management.php" class="text-xs text-blue-600 hover:text-blue-800">View services</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            
                            <?php if (!$notification['is_read']): ?>
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
                <a href="notifications.php" class="text-sm text-blue-600 hover:text-blue-800">View all notifications</a>
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
        // Toast notification function
        function showToast(title, message, type = 'success') {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast toast-${type} flex items-start`;
            
            toast.innerHTML = `
                <div class="flex-shrink-0 mr-2">
                    <i class="${type === 'success' ? 'mif-checkmark' : 'mif-cross'}"></i>
                </div>
                <div class="flex-1">
                    <h4 class="font-bold">${title}</h4>
                    <p>${message}</p>
                </div>
                <button class="ml-4 text-white hover:text-gray-200 focus:outline-none" onclick="this.parentNode.remove()">
                    ×
                </button>
            `;
            
            toastContainer.appendChild(toast);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                toast.classList.add('opacity-0', 'transition-opacity', 'duration-500');
                setTimeout(() => {
                    toast.remove();
                }, 500);
            }, 5000);
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'fixed top-4 right-4 z-50 flex flex-col space-y-2';
            document.body.appendChild(container);
            return container;
        }
        
        // Update booking status function
        function updateBookingStatus(bookingId, newStatus) {
            // Create form data
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', newStatus);
            formData.append('update_booking_status', 1);
            
            // Send request
            fetch('update_booking_status.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    showToast('Success', `Booking #${bookingId} has been ${newStatus}`, 'success');
                    
                    // Update UI in the day modal
                    const statusBadge = document.querySelector(`.booking-status-badge[data-booking-id="${bookingId}"]`);
                    if (statusBadge) {
                        statusBadge.className = `booking-status-badge inline-block px-2 py-1 text-xs rounded status-${newStatus}`;
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                    
                    // Update action buttons
                    const actionButtons = document.querySelectorAll(`.booking-action[data-booking-id="${bookingId}"]`);
                    actionButtons.forEach(button => {
                        if (newStatus === 'completed' || newStatus === 'cancelled') {
                            button.classList.add('hidden');
                        } else if (newStatus === 'confirmed') {
                            if (button.getAttribute('data-action') === 'confirmed') {
                                button.classList.add('hidden');
                            } else {
                                button.classList.remove('hidden');
                            }
                        }
                    });
                } else {
                    // Show error message
                    showToast('Error', data.message || 'Failed to update booking status', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Error', 'Failed to update booking status', 'error');
            });
        }
        
        // Get day bookings via AJAX
        function getDayBookings(date) {
            // Show loading state in modal
            document.getElementById('modal-day-title').textContent = 'Loading...';
            document.getElementById('modal-day-content').innerHTML = `
                <div class="flex justify-center p-6">
                    <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900"></div>
                </div>
            `;
            
            // Show the modal
            document.getElementById('day-modal').style.display = 'flex';
            
            // Fetch the bookings for this day
            fetch(`calendar.php?action=get_day_bookings&day=${date}`, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update modal title
                    document.getElementById('modal-day-title').textContent = data.date;
                    
                    // Generate content based on bookings
                    let content = '';
                    
                    if (data.bookings.length === 0) {
                        content = `
                            <div class="p-6 text-center text-gray-500">
                                <i class="mif-calendar-empty text-4xl mb-2"></i>
                                <p>No bookings for this day.</p>
                                <a href="reservations.php?date=${date}" class="text-blue-600 mt-2 inline-block">View reservations</a>
                            </div>
                        `;
                    } else {
                        content = `
                            <div class="overflow-x-auto">
                                <table class="min-w-full divide-y divide-gray-200">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-3 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
										</tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                        `;
                        
                        data.bookings.forEach(booking => {
                            let statusClass = '';
                            switch(booking.status) {
                                case 'pending': statusClass = 'bg-yellow-100 text-yellow-800'; break;
                                case 'confirmed': statusClass = 'bg-green-100 text-green-800'; break;
                                case 'completed': statusClass = 'bg-blue-100 text-blue-800'; break;
                                case 'cancelled': statusClass = 'bg-red-100 text-red-800'; break;
                            }
                            
                            content += `
                                <tr>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">${booking.time}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">${booking.customer}</div>
                                        <div class="text-xs text-gray-500">${booking.email}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">${booking.service}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="text-sm text-gray-900">$${booking.amount}</div>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <span class="booking-status-badge inline-block px-2 py-1 text-xs rounded ${statusClass}" data-booking-id="${booking.id}">
                                            ${booking.status.charAt(0).toUpperCase() + booking.status.slice(1)}
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 whitespace-nowrap">
                                        <div class="flex space-x-2">
                                            <a href="view_booking.php?id=${booking.id}" class="text-blue-600 hover:text-blue-800">
                                                <i class="mif-eye"></i>
                                            </a>
                            `;
                            
                            // Add action buttons based on status
                            if (booking.status === 'pending') {
                                content += `
                                    <button class="booking-action text-green-600 hover:text-green-800" 
                                            data-booking-id="${booking.id}" 
                                            data-action="confirmed">
                                        <i class="mif-checkmark"></i>
                                    </button>
                                `;
                            }
                            
                            if (booking.status === 'confirmed') {
                                content += `
                                    <button class="booking-action text-blue-600 hover:text-blue-800" 
                                            data-booking-id="${booking.id}" 
                                            data-action="completed">
                                        <i class="mif-check-all"></i>
                                    </button>
                                `;
                            }
                            
                            if (booking.status !== 'cancelled' && booking.status !== 'completed') {
                                content += `
                                    <button class="booking-action text-red-600 hover:text-red-800" 
                                            data-booking-id="${booking.id}" 
                                            data-action="cancelled">
                                        <i class="mif-cross"></i>
                                    </button>
                                `;
                            }
                            
                            content += `
                                        </div>
                                    </td>
                                </tr>
                            `;
                        });
                        
                        content += `
                                    </tbody>
                                </table>
                            </div>
                            <div class="p-4 mt-4 border-t text-center">
                                <a href="reservations.php?date=${date}" class="text-blue-600 hover:text-blue-800">View all reservations for this date</a>
                            </div>
                        `;
                    }
                    
                    // Update modal content
                    document.getElementById('modal-day-content').innerHTML = content;
                    
                    // Add event listeners to the action buttons
                    document.querySelectorAll('.booking-action').forEach(button => {
                        button.addEventListener('click', function() {
                            const bookingId = this.getAttribute('data-booking-id');
                            const action = this.getAttribute('data-action');
                            updateBookingStatus(bookingId, action);
                        });
                    });
                    
                } else {
                    // Show error in modal
                    document.getElementById('modal-day-title').textContent = 'Error';
                    document.getElementById('modal-day-content').innerHTML = `
                        <div class="p-6 text-center text-red-500">
                            <p>${data.message || 'Failed to load bookings for this day.'}</p>
                        </div>
                    `;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('modal-day-title').textContent = 'Error';
                document.getElementById('modal-day-content').innerHTML = `
                    <div class="p-6 text-center text-red-500">
                        <p>Failed to load bookings for this day.</p>
                    </div>
                `;
            });
        }
        
        // Process month input
        document.querySelector('form[action="calendar.php"]').addEventListener('submit', function(e) {
            e.preventDefault();
            const monthValue = document.querySelector('input[name="month"]').value;
            if (monthValue) {
                window.location.href = 'calendar.php?date=' + monthValue + '-01';
            }
        });
        
        // Day click event
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('click', function() {
                const date = this.getAttribute('data-date');
                if (date) {
                    getDayBookings(date);
                }
            });
        });
        
        // Close day modal
        document.getElementById('close-day-modal').addEventListener('click', function() {
            document.getElementById('day-modal').style.display = 'none';
        });
        
        // Close day modal when clicking outside
        document.getElementById('day-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.style.display = 'none';
            }
        });
        
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
    </script>
</body>
</html>