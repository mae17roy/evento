<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
requireLogin();

// Get user ID
$userId = $_SESSION['user_id'];

// Get filter parameters
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 10;
$offset = ($page - 1) * $limit;

// Build query based on filter
$query = "
    SELECT * 
    FROM notifications
    WHERE user_id = ?
";

$params = [$userId];

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
$unreadCount = getUserNotificationCount($db, $userId);
$bookingCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'booking'")->execute([$userId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
$serviceCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'service'")->execute([$userId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
$systemCount = $db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'system'")->execute([$userId]) ? $db->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;

// Handle mark as read action
if (isset($_POST['mark_read']) && !empty($_POST['notification_id'])) {
    $notificationId = $_POST['notification_id'];
    markNotificationAsRead($db, $notificationId);
    $_SESSION['success_message'] = "Notification marked as read.";
    header("Location: client_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}

// Handle mark all as read action
if (isset($_POST['mark_all_read'])) {
    $markAllStmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
    $markAllStmt->execute([$userId]);
    $_SESSION['success_message'] = "All notifications marked as read.";
    header("Location: client_notifications.php" . (isset($_GET['filter']) ? "?filter=" . $_GET['filter'] : ""));
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Notifications - EVENTO</title>
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
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md p-4 fixed top-0 left-0 right-0 z-50">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center mb-4 md:mb-0">
                <div class="text-purple-600 mr-2">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <a href="user_index.php" class="text-xl font-bold text-gray-800">EVENTO</a>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-6">
                <a href="my_bookings.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-calendar-alt text-xl"></i>
                </a>
                <a href="client_notifications.php" class="text-purple-600 hover:text-gray-800">
                    <i class="fas fa-bell text-xl"></i>
                    <?php if ($unreadCount > 0): ?>
                    <span class="bg-red-500 text-white text-xs rounded-full w-5 h-5 flex items-center justify-center absolute -mt-8 ml-4">
                        <?php echo $unreadCount; ?>
                    </span>
                    <?php endif; ?>
                </a>
                <div class="relative">
                    <button id="userMenuButton" class="flex items-center focus:outline-none">
                        <img src="https://i.pravatar.cc/150?img=<?php echo ($_SESSION['user_id'] % 70); ?>" alt="User" 
                             class="w-10 h-10 rounded-full object-cover border-2 border-purple-500">
                    </button>
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden z-10">
                        <div class="py-1">
                            <div class="px-4 py-2 font-semibold border-b"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Bookings</a>
                            <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Log out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content with top padding -->
    <main class="container mx-auto py-8 px-4 mt-16 mb-10">
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
            <h1 class="text-3xl font-bold">My Notifications</h1>
            
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
            <a href="client_notifications.php" class="filter-badge <?php echo $filter === 'all' ? 'active' : ''; ?>">
                <i class="fas fa-inbox mr-2"></i>
                All
                <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                    <?php echo $total; ?>
                </span>
            </a>
            <a href="client_notifications.php?filter=unread" class="filter-badge <?php echo $filter === 'unread' ? 'active' : ''; ?>">
                <i class="fas fa-envelope mr-2"></i>
                Unread
                <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                    <?php echo $unreadCount; ?>
                </span>
            </a>
            <a href="client_notifications.php?filter=bookings" class="filter-badge <?php echo $filter === 'bookings' ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check mr-2"></i>
                Bookings
                <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                    <?php echo $bookingCount; ?>
                </span>
            </a>
            <a href="client_notifications.php?filter=services" class="filter-badge <?php echo $filter === 'services' ? 'active' : ''; ?>">
                <i class="fas fa-concierge-bell mr-2"></i>
                Services
                <span class="ml-2 bg-gray-200 text-gray-800 rounded-full px-2 py-1 text-xs">
                    <?php echo $serviceCount; ?>
                </span>
            </a>
            <a href="client_notifications.php?filter=system" class="filter-badge <?php echo $filter === 'system' ? 'active' : ''; ?>">
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
                            
                            <div class="mt-2 flex space-x-4">
                                <?php if (!$notification['is_read']): ?>
                                <form method="post">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" name="mark_read" class="text-sm text-purple-600 hover:text-purple-800">
                                        Mark as read
                                    </button>
                                </form>
                                <?php endif; ?>
                                
                                <?php if ($notification['type'] === 'booking' && !empty($notification['related_id'])): ?>
                                <a href="view_booking.php?id=<?php echo $notification['related_id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                    View booking
                                </a>
                                <?php endif; ?>
                                
                                <?php if ($notification['type'] === 'service' && !empty($notification['related_id'])): ?>
                                <a href="service_details.php?id=<?php echo $notification['related_id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
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

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center">
                        <div class="text-purple-400 mr-2">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold">EVENTO</span>
                    </div>
                    <p class="text-gray-400 mt-2">Your one-stop platform for all event services.</p>
                </div>
                
                <div>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <p class="text-gray-400 mt-2">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle user dropdown
        document.getElementById('userMenuButton').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            if (!event.target.closest('#userMenuButton') && !event.target.closest('#userDropdown')) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>