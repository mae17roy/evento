<?php
// Include database configuration
require_once 'config.php';
require_once 'notifications_functions.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
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
$userRole = $_SESSION['user_role'] ?? 'client';

// Check if page number is set
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 20;

// Get notifications with pagination
// Use existing function if available, otherwise use the new multi-owner function
if (function_exists('getAllNotificationsMultiOwner')) {
    $notificationsData = getAllNotificationsMultiOwner($db, $page, $perPage);
} else {
    $notificationsData = getAllNotifications($db, $page, $perPage);
}

$notifications = $notificationsData['notifications'];
$totalPages = $notificationsData['total_pages'];
$currentPage = $notificationsData['current_page'];
$total = $notificationsData['total'];

// Get unread count
// Use existing function if available, otherwise use the new multi-owner function
if (function_exists('countUnreadNotificationsMultiOwner')) {
    $unreadNotificationsCount = countUnreadNotificationsMultiOwner($db);
} else {
    $unreadNotificationsCount = countUnreadNotifications($db);
}

// Mark all as read action
if (isset($_POST['mark_all_read'])) {
    $success = markAllNotificationsAsRead($db);
    
    if ($success) {
        $_SESSION['success_message'] = "All notifications marked as read!";
    } else {
        $_SESSION['error_message'] = "Failed to mark notifications as read.";
    }
    
    header("Location: notifications.php");
    exit();
}

// Filter notifications
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$filteredNotifications = [];

if ($filter === 'all') {
    $filteredNotifications = $notifications;
} elseif ($filter === 'unread') {
    $filteredNotifications = array_filter($notifications, function($notification) {
        return !$notification['is_read'];
    });
} elseif ($filter === 'bookings') {
    $filteredNotifications = array_filter($notifications, function($notification) {
        return $notification['type'] === 'booking';
    });
} elseif ($filter === 'services') {
    $filteredNotifications = array_filter($notifications, function($notification) {
        return $notification['type'] === 'service';
    });
} elseif ($filter === 'owner' && $userRole === 'owner') {
    $filteredNotifications = array_filter($notifications, function($notification) {
        return isset($notification['notification_for']) && $notification['notification_for'] === 'owner';
    });
} elseif ($filter === 'personal') {
    $filteredNotifications = array_filter($notifications, function($notification) {
        return !isset($notification['notification_for']) || $notification['notification_for'] === 'user';
    });
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications EVENTO </title>
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
        .notification-type-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .type-new {
            background-color: #1a2e46;
            color: white;
        }
        .type-confirmed {
            background-color: #10b981;
            color: white;
        }
        .type-cancelled {
            background-color: #ef4444;
            color: white;
        }
        .type-service {
            background-color: #6366f1;
            color: white;
        }
        .type-owner {
            background-color: #f59e0b;
            color: white;
        }
        .notification-item {
            border-left: 3px solid transparent;
            transition: all 0.2s ease;
        }
        .notification-item.unread {
            border-left-color: #1a2e46;
            background-color: #f9fafb;
        }
        .notification-item:hover {
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
                <img src="https://i.pravatar.cc/150?img=<?php echo $user['id']; ?>" alt="User Avatar" class="w-10 h-10 rounded-full mr-3">
                <div>
                    <h2 class="font-medium"><?php echo htmlspecialchars($user['name']); ?></h2>
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
                <a href="owner_index.php" class="flex items-center px-4 py-3 sidebar-item">
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
                
                <a href="analytics.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-chart-bars mr-3"></i>
                    <span>Analytics</span>
                </a>
                
                <a href="notifications.php" class="flex items-center px-4 py-3 sidebar-item active">
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
                    <h1 class="text-2xl font-bold">Notifications</h1>
                </div>
                
                <div class="w-1/3">
                    <form action="notifications.php" method="get" class="w-full">
                        <div class="relative">
                            <input type="text" name="search" placeholder="Search notifications..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md" value="<?php echo isset($_GET['search']) ? htmlspecialchars($_GET['search']) : ''; ?>">
                            <i class="mif-search absolute left-3 top-2.5 text-gray-400"></i>
                            <button type="submit" class="absolute right-2 top-2 text-gray-400 hover:text-gray-600">
                                <i class="mif-arrow-right"></i>
                            </button>
                        </div>
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
                    <img src="https://i.pravatar.cc/150?img=<?php echo $user['id']; ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <!-- Include Notifications Content -->
            <?php include_once 'notifications_content.php'; ?>
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
            if (notificationsSidebar) {
                notificationsSidebar.classList.toggle('translate-x-full');
            }
        });

        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);

        // Search functionality
        const searchInput = document.querySelector('input[name="search"]');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const notifications = document.querySelectorAll('.notification-item');
                
                notifications.forEach(notification => {
                    const text = notification.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        notification.style.display = '';
                    } else {
                        notification.style.display = 'none';
                    }
                });
            });
        }
    </script>
</body>
</html>