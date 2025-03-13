<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in and is a service owner
requireOwnerLogin();

// Get owner ID
$ownerId = $_SESSION['user_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query based on filter
$query = "
    SELECT * 
    FROM notifications
    WHERE owner_id = ?
";

$params = [$ownerId];

if ($filter === 'unread') {
    $query .= " AND is_read = 0";
} elseif ($filter === 'bookings') {
    $query .= " AND type = 'booking'";
} elseif ($filter === 'services') {
    $query .= " AND type = 'service'";
} elseif ($filter === 'system') {
    $query .= " AND type = 'system'";
}

// Get total count
$countStmt = $db->prepare("SELECT COUNT(*) FROM ($query) as notification_count");
$countStmt->execute($params);
$total = $countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Add ordering and pagination
$query .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Execute query
$stmt = $db->prepare($query);
$stmt->execute($params);
$notifications = $stmt->fetchAll();

// Format notifications for display
$formattedNotifications = [];
foreach ($notifications as $notification) {
    $timeAgo = formatTimeAgo($notification['created_at']);
    
    $formattedNotifications[] = [
        'id' => $notification['id'],
        'type' => $notification['type'],
        'title' => $notification['title'],
        'message' => $notification['message'],
        'related_id' => $notification['related_id'],
        'is_read' => $notification['is_read'],
        'time' => $timeAgo,
        'created_at' => $notification['created_at']
    ];
}

// Get notification counts
$unreadCount = getOwnerNotificationCount($db, $ownerId);
$bookingCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE owner_id = ? AND type = 'booking'")->execute([$ownerId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
$serviceCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE owner_id = ? AND type = 'service'")->execute([$ownerId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
$systemCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE owner_id = ? AND type = 'system'")->execute([$ownerId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

// Handle mark as read action
if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];
    markNotificationAsRead($db, $notificationId);
    $_SESSION['success_message'] = "Notification marked as read.";
    header("Location: owner_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $markAllStmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE owner_id = ?");
    $markAllStmt->execute([$ownerId]);
    $_SESSION['success_message'] = "All notifications marked as read.";
    header("Location: owner_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Handle booking actions (confirm/complete/cancel)
if (isset($_POST['booking_action']) && isset($_POST['booking_id']) && isset($_POST['action'])) {
    $bookingId = $_POST['booking_id'];
    $action = $_POST['action'];
    
    // Verify the booking is for a service owned by this owner
    if (canAccessBooking($db, $ownerId, $bookingId, 'owner')) {
        // Update booking status
        updateBookingStatus($db, $bookingId, $action, $ownerId);
        
        // Create notification for client
        $bookingData = getBookingById($db, $bookingId);
        $bookingItems = getBookingItems($db, $bookingId);
        
        if ($bookingData && !empty($bookingItems)) {
            $serviceName = $bookingItems[0]['service_name'];
            
            $title = "Booking #$bookingId " . ucfirst($action);
            $message = "Your booking for $serviceName on " . formatDate($bookingData['booking_date']) . " at " . formatTime($bookingData['booking_time']) . " has been $action.";
            
            $notifyStmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, related_id, created_at
                ) VALUES (
                    ?, 'booking', ?, ?, ?, NOW()
                )
            ");
            $notifyStmt->execute([$bookingData['user_id'], $title, $message, $bookingId]);
        }
        
        $_SESSION['success_message'] = "Booking has been " . ucfirst($action) . ".";
        header("Location: owner_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
        exit();
    } else {
        $_SESSION['error_message'] = "You don't have permission to update this booking.";
        header("Location: owner_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
        exit();
    }
}

// Get owner info
$ownerStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$ownerStmt->execute([$ownerId]);
$owner = $ownerStmt->fetch();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Owner Notifications - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .notification-item {
            transition: all 0.3s ease;
        }
        .notification-item:hover {
            background-color: #f9fafb;
        }
        .notification-item.unread {
            border-left: 4px solid #8b5cf6;
        }
        .notification-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .icon-booking {
            background-color: #e0f2fe;
            color: #0284c7;
        }
        .icon-service {
            background-color: #f0fdf4;
            color: #16a34a;
        }
        .icon-system {
            background-color: #fef3c7;
            color: #d97706;
        }
        .filter-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        .filter-badge.active {
            background-color: #8b5cf6;
            color: white;
        }
        .filter-badge:not(.active) {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .filter-badge:not(.active):hover {
            background-color: #e5e7eb;
        }
        .sidebar {
            width: 250px;
            transition: all 0.3s ease;
        }
        .sidebar-item {
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
            border-radius: 0.375rem;
            transition: all 0.3s ease;
        }
        .sidebar-item:hover {
            background-color: #f3f4f6;
        }
        .sidebar-item.active {
            background-color: #f3f4f6;
            font-weight: 600;
        }
        .sidebar-item i {
            margin-right: 0.75rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                position: fixed;
                left: -250px;
                top: 0;
                bottom: 0;
                z-index: 40;
                background-color: white;
                box-shadow: 4px 0 10px rgba(0, 0, 0, 0.1);
            }
            .sidebar.show {
                left: 0;
            }
            .content {
                margin-left: 0;
            }
            .overlay {
                display: none;
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 30;
            }
            .overlay.show {
                display: block;
            }
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Mobile Menu Overlay -->
    <div id="overlay" class="overlay"></div>

    <div class="flex min-h-screen">
        <!-- Sidebar -->
        <div id="sidebar" class="sidebar bg-white shadow-md flex flex-col h-screen fixed md:sticky top-0">
            <div class="p-4 border-b border-gray-200 flex justify-between items-center">
                <div class="flex items-center">
                    <div class="text-purple-600 mr-2">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold">EVENTO</span>
                </div>
                <button id="close-sidebar" class="md:hidden text-gray-500">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <div class="p-4 border-b border-gray-200">
                <div class="flex items-center">
                    <img src="https://i.pravatar.cc/150?img=<?php echo ($ownerId % 70); ?>" alt="User" 
                         class="w-10 h-10 rounded-full object-cover mr-3">
                    <div>
                        <h2 class="font-medium"><?php echo htmlspecialchars($owner['name']); ?></h2>
                        <p class="text-sm text-gray-500"><?php echo ucfirst($owner['role']); ?></p>
                    </div>
                </div>
            </div>
            
            <nav class="flex-1 p-4 overflow-y-auto">
                <a href="owner_dashboard.php" class="sidebar-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="owner_services.php" class="sidebar-item">
                    <i class="fas fa-concierge-bell"></i>
                    <span>My Services</span>
                </a>
                <a href="owner_bookings.php" class="sidebar-item">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Bookings</span>
                </a>
                <a href="owner_customers.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <a href="owner_notifications.php" class="sidebar-item active">
                    <i class="fas fa-bell"></i>
                    <span>Notifications</span>
                    <?php if ($unreadCount > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center">
                        <?php echo $unreadCount; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <a href="owner_settings.php" class="sidebar-item">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <div class="p-4 border-t border-gray-200">
                <a href="logout.php" class="sidebar-item text-red-600">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Log Out</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 content">
            <!-- Top Navigation -->
            <header class="bg-white shadow-sm p-4 flex justify-between items-center md:hidden">
                <button id="menu-button" class="text-gray-500">
                    <i class="fas fa-bars text-xl"></i>
                </button>
                <div class="text-xl font-bold">Notifications</div>
                <div></div>
            </header>
            
            <!-- Page Content -->
            <main class="p-6">
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                        <p><?php echo $_SESSION['success_message']; ?></p>
                        <?php unset($_SESSION['success_message']); ?>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                        <p><?php echo $_SESSION['error_message']; ?></p>
                        <?php unset($_SESSION['error_message']); ?>
                    </div>
                <?php endif; ?>
                
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl font-bold">Notifications</h1>
                    
                    <?php if ($unreadCount > 0): ?>
                    <form method="post" action="">
                        <button type="submit" name="mark_all_read" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            Mark All as Read
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                
                <!-- Notification Filters -->
                <div class="mb-6 flex flex-wrap gap-2">
                    <a href="owner_notifications.php" class="filter-badge <?php echo $filter === 'all' ? 'active' : ''; ?>">
                        <i class="fas fa-inbox mr-2"></i>
                        All
                        <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                            <?php echo $total; ?>
                        </span>
                    </a>
                    <a href="owner_notifications.php?filter=unread" class="filter-badge <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                        <i class="fas fa-envelope mr-2"></i>
                        Unread
                        <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                            <?php echo $unreadCount; ?>
                        </span>
                    </a>
                    <a href="owner_notifications.php?filter=bookings" class="filter-badge <?php echo $filter === 'bookings' ? 'active' : ''; ?>">
                        <i class="fas fa-calendar-check mr-2"></i>
                        Bookings
                        <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                            <?php echo $bookingCount; ?>
                        </span>
                    </a>
                    <a href="owner_notifications.php?filter=services" class="filter-badge <?php echo $filter === 'services' ? 'active' : ''; ?>">
                        <i class="fas fa-concierge-bell mr-2"></i>
                        Services
                        <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                            <?php echo $serviceCount; ?>
                        </span>
                    </a>
                    <a href="owner_notifications.php?filter=system" class="filter-badge <?php echo $filter === 'system' ? 'active' : ''; ?>">
                        <i class="fas fa-cog mr-2"></i>
                        System
                        <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                            <?php echo $systemCount; ?>
                        </span>
                    </a>
                </div>
                
                <!-- Notifications List -->
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <?php if (empty($formattedNotifications)): ?>
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-bell-slash text-gray-400 text-xl"></i>
                        </div>
                        <h2 class="text-xl font-medium mb-2">No notifications</h2>
                        <p class="text-gray-500">
                            <?php if ($filter !== 'all'): ?>
                            You don't have any <?php echo $filter; ?> notifications.
                            <?php else: ?>
                            You don't have any notifications yet.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php else: ?>
                    <div class="divide-y divide-gray-200">
                        <?php foreach ($formattedNotifications as $notification): ?>
                        <div class="notification-item p-4 <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                            <div class="flex">
                                <!-- Notification Icon -->
                                <div class="notification-icon icon-<?php echo $notification['type']; ?> mr-4">
                                    <?php if ($notification['type'] === 'booking'): ?>
                                    <i class="fas fa-calendar-check"></i>
                                    <?php elseif ($notification['type'] === 'service'): ?>
                                    <i class="fas fa-concierge-bell"></i>
                                    <?php else: ?>
                                    <i class="fas fa-info-circle"></i>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <h3 class="font-medium"><?php echo htmlspecialchars($notification['title']); ?></h3>
                                        <span class="text-xs text-gray-500"><?php echo $notification['time']; ?></span>
                                    </div>
                                    
                                    <p class="text-gray-600 text-sm mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    
                                    <!-- Action Buttons -->
                                    <div class="mt-3 flex flex-wrap gap-2">
                                        <?php if (!$notification['is_read']): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" name="mark_read" class="px-3 py-1 bg-gray-100 text-gray-700 rounded-md text-sm hover:bg-gray-200">
                                                Mark as read
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        
                                        <?php if ($notification['type'] === 'booking' && !empty($notification['related_id'])): ?>
                                        <a href="owner_view_booking.php?id=<?php echo $notification['related_id']; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                                            View details
                                        </a>
                                        
                                        <?php 
                                        // Get booking status to show appropriate action buttons
                                        $bookingStatusStmt = $db->prepare("SELECT status FROM bookings WHERE id = ?");
                                        $bookingStatusStmt->execute([$notification['related_id']]);
                                        $bookingStatus = $bookingStatusStmt->fetchColumn();
                                        
                                        if ($bookingStatus === 'pending'): 
                                        ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $notification['related_id']; ?>">
                                            <input type="hidden" name="action" value="confirmed">
                                            <button type="submit" name="booking_action" class="px-3 py-1 bg-green-100 text-green-700 rounded-md text-sm hover:bg-green-200">
                                                <i class="fas fa-check mr-1"></i> Confirm
                                            </button>
                                        </form>
                                        
                                        <form method="post" class="inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $notification['related_id']; ?>">
                                            <input type="hidden" name="action" value="cancelled">
                                            <button type="submit" name="booking_action" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200">
                                                <i class="fas fa-times mr-1"></i> Cancel
                                            </button>
                                        </form>
                                        <?php elseif ($bookingStatus === 'confirmed'): ?>
                                        <form method="post" class="inline">
                                            <input type="hidden" name="booking_id" value="<?php echo $notification['related_id']; ?>">
                                            <input type="hidden" name="action" value="completed">
                                            <button type="submit" name="booking_action" class="px-3 py-1 bg-purple-100 text-purple-700 rounded-md text-sm hover:bg-purple-200">
                                                <i class="fas fa-check-double mr-1"></i> Mark as Completed
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($notification['type'] === 'service' && !empty($notification['related_id'])): ?>
                                        <a href="owner_service_details.php?id=<?php echo $notification['related_id']; ?>" class="px-3 py-1 bg-blue-100 text-blue-700 rounded-md text-sm hover:bg-blue-200">
                                            View service
                                        </a>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                <div class="mt-6 flex justify-center">
                    <div class="flex space-x-1">
                        <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?><?php echo !empty($filter) && $filter !== 'all' ? '&filter=' . $filter : ''; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">
                            <i class="fas fa-chevron-left"></i>
                        </a>
                        <?php endif; ?>
                        
                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo !empty($filter) && $filter !== 'all' ? '&filter=' . $filter : ''; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-md <?php echo ($i == $page) ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                            <?php echo $i; ?>
                        </a>
                        <?php endfor; ?>
                        
                        <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo !empty($filter) && $filter !== 'all' ? '&filter=' . $filter : ''; ?>" 
                           class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">
                            <i class="fas fa-chevron-right"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Mobile menu toggle
        const menuButton = document.getElementById('menu-button');
        const closeSidebarButton = document.getElementById('close-sidebar');
        const overlay = document.getElementById('overlay');
        const sidebar = document.getElementById('sidebar');
        
        menuButton.addEventListener('click', function() {
            sidebar.classList.add('show');
            overlay.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent scrolling
        });
        
        closeSidebarButton.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = ''; // Re-enable scrolling
        });
        
        overlay.addEventListener('click', function() {
            sidebar.classList.remove('show');
            overlay.classList.remove('show');
            document.body.style.overflow = ''; // Re-enable scrolling
        });
    </script>
</body>
</html>