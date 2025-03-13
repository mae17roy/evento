<?php
// Ensure no extra whitespace before PHP tag
// Explicitly include necessary files with comprehensive error checking
$requiredFiles = [
    'config.php' => 'Database configuration file',
    'functions.php' => 'Core application functions'
];

// Error handling for file inclusion
foreach ($requiredFiles as $file => $description) {
    $filePath = __DIR__ . DIRECTORY_SEPARATOR . $file;
    
    if (!file_exists($filePath)) {
        error_log("Missing required file: $file");
        die("Fatal Error: Required $description ($file) is missing!");
    }
    
    require_once $filePath;
}

// Ensure session is started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fallback function for admin login check if not defined
if (!function_exists('requireAdminLogin')) {
    function requireAdminLogin() {
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
            $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
            $_SESSION['error_message'] = "You must be an admin to access this page.";
            header("Location: index.php");
            exit();
        }
    }
}

// Require admin login
requireAdminLogin();

// Fallback for getCurrentUser if not defined
if (!function_exists('getCurrentUser')) {
    function getCurrentUser($db) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        try {
            $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Error fetching current user: " . $e->getMessage());
            return false;
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
            'role' => $_SESSION['user_role'] ?? 'owner'
        ];
    }
}

// Fallback functions for notifications if not defined
if (!function_exists('getRecentNotificationsMultiOwner')) {
    function getRecentNotificationsMultiOwner($db) {
        try {
            $stmt = $db->prepare("
                SELECT 
                    id,
                    title,
                    message,
                    type,
                    related_id,
                    is_read,
                    created_at,
                    DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as time
                FROM notifications
                WHERE user_id = ? OR owner_id = ?
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Notifications error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('countUnreadNotificationsMultiOwner')) {
    function countUnreadNotificationsMultiOwner($db) {
        try {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM notifications 
                WHERE (user_id = ? OR owner_id = ?) AND is_read = 0
            ");
            $stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
            return $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log("Unread notifications count error: " . $e->getMessage());
            return 0;
        }
    }
}

// Fallback functions for customer-related operations
if (!function_exists('getOwnerCustomers')) {
    function getOwnerCustomers($db, $owner_id) {
        try {
            $stmt = $db->prepare("
                SELECT DISTINCT u.id, u.name, u.email, u.phone, 
                       COUNT(b.id) as total_bookings,
                       MAX(b.created_at) as last_booking
                FROM users u
                JOIN bookings b ON u.id = b.user_id
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE s.owner_id = ?
                GROUP BY u.id
                ORDER BY last_booking DESC
            ");
            $stmt->execute([$owner_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Owner customers error: " . $e->getMessage());
            return [];
        }
    }
}

if (!function_exists('getCustomerDetails')) {
    function getCustomerDetails($db, $client_id, $owner_id) {
        try {
            $stmt = $db->prepare("
                SELECT u.*, 
                       COUNT(DISTINCT b.id) as total_bookings,
                       SUM(b.total_amount) as total_spent
                FROM users u
                JOIN bookings b ON u.id = b.user_id
                JOIN booking_items bi ON b.id = bi.booking_id
                JOIN services s ON bi.service_id = s.id
                WHERE u.id = ? AND s.owner_id = ?
                GROUP BY u.id
            ");
            $stmt->execute([$client_id, $owner_id]);
            return $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Customer details error: " . $e->getMessage());
            return null;
        }
    }
}

if (!function_exists('getMessageHistory')) {
    function getMessageHistory($db, $sender_id, $receiver_id) {
        try {
            $stmt = $db->prepare("
                SELECT * 
                FROM messages 
                WHERE (sender_id = ? AND receiver_id = ?) 
                   OR (sender_id = ? AND receiver_id = ?) 
                ORDER BY created_at
            ");
            $stmt->execute([$sender_id, $receiver_id, $receiver_id, $sender_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log("Message history error: " . $e->getMessage());
            return [];
        }
    }
}

// Process message sending
if (isset($_POST['send_message'])) {
    $clientId = $_POST['client_id'];
    $message = $_POST['message'];
    
    try {
        // Insert message into messages table
        $stmt = $db->prepare("
            INSERT INTO messages (sender_id, receiver_id, message, created_at)
            VALUES (?, ?, ?, NOW())
        ");
        $result = $stmt->execute([$_SESSION['user_id'], $clientId, $message]);
        
        if ($result) {
            // Create notification for the client
            createNotification(
                $db, 
                $clientId, 
                'message', 
                'New Message from ' . $user['name'], 
                substr($message, 0, 50) . (strlen($message) > 50 ? '...' : ''),
                null,
                $_SESSION['user_id']
            );
            
            $_SESSION['success_message'] = "Message sent successfully!";
        } else {
            $_SESSION['error_message'] = "Failed to send message.";
        }
    } catch (PDOException $e) {
        $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    }
    
    // Redirect to avoid form resubmission
    header("Location: customers.php" . (isset($_GET['client_id']) ? "?client_id=" . $_GET['client_id'] : ""));
    exit();
}

// Fetch customers for the owner
$customers = getOwnerCustomers($db, $_SESSION['user_id']);

// Fetch customer details if a specific client is selected
$selectedCustomer = null;
$messageHistory = [];
if (isset($_GET['client_id'])) {
    $selectedCustomer = getCustomerDetails($db, $_GET['client_id'], $_SESSION['user_id']);
    $messageHistory = getMessageHistory($db, $_SESSION['user_id'], $_GET['client_id']);
}

// Get notifications for current user
$notifications = getRecentNotificationsMultiOwner($db);
$unreadNotificationsCount = countUnreadNotificationsMultiOwner($db);

// Include the HTML template
include_once 'customers_content.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?php echo !empty($user['business_name']) ? htmlspecialchars($user['business_name']) . ' - ' : ''; ?>
        Customer Management
    </title>
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
        .status-badge {
            padding: 4px 10px;
            border-radius: 4px;
            font-size: 12px;
            font-weight: 500;
        }
        .status-confirmed {
            background-color: #1a2e46;
            color: white;
        }
        .status-pending {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .status-completed {
            background-color: #10b981;
            color: white;
        }
        .status-cancelled {
            background-color: #ef4444;
            color: white;
        }
        .notification-panel {
            max-height: 280px;
            overflow-y: auto;
        }
        .message-container {
            height: 300px;
            overflow-y: auto;
        }
        .message-bubble {
            border-radius: 1rem;
            padding: 0.75rem 1rem;
            max-width: 75%;
            margin-bottom: 0.5rem;
        }
        .message-sent {
            background-color: #1a2e46;
            color: white;
            margin-left: auto;
        }
        .message-received {
            background-color: #f3f4f6;
            color: #1f2937;
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
                <a href="calendar.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="mif-calendar mr-3"></i>
                    <span>Calendar</span>
                </a>
                <a href="customers.php" class="flex items-center px-4 py-3 sidebar-item active">
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
                        <input type="text" id="search-input" placeholder="Search customers by name, email..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md">
                        <i class="mif-search absolute left-3 top-2.5 text-gray-400"></i>
                    </div>
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
            
            <!-- Include the customers content file -->
            <?php include_once 'customers_content.php'; ?>
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
                    <div class="p-4 border-b border-gray-100 <?php echo (isset($notification['is_read']) && !$notification['is_read']) ? 'bg-blue-50' : ''; ?>">
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
                            
                            <?php if (isset($notification['is_read']) && !$notification['is_read']): ?>
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
        
        // Customer search
        const customerSearch = document.getElementById('customer-search');
        if (customerSearch) {
            customerSearch.addEventListener('keyup', function() {
                const searchTerm = this.value.toLowerCase();
                const customerItems = document.querySelectorAll('#customer-list li');
                
                customerItems.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    if (text.includes(searchTerm)) {
                        item.style.display = '';
                    } else {
                        item.style.display = 'none';
                    }
                });
            });
        }
        
        // Scroll messages to bottom
        const messageContainer = document.getElementById('message-container');
        if (messageContainer) {
            messageContainer.scrollTop = messageContainer.scrollHeight;
        }
        
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                if (alert) {
                    alert.style.display = 'none';
                }
            });
        }, 5000);
        
        // Global search
        const searchInput = document.getElementById('search-input');
        if (searchInput) {
            searchInput.addEventListener('keyup', function(e) {
                if (e.key === 'Enter') {
                    window.location.href = `search_results.php?query=${encodeURIComponent(this.value)}`;
                }
            });
        }
        
        // Add customer note
        function addNote(clientId) {
            document.getElementById('note-client-id').value = clientId;
            document.getElementById('add-note-modal').classList.remove('hidden');
        }
        
        function closeNoteModal() {
            document.getElementById('add-note-modal').classList.add('hidden');
            document.getElementById('note').value = '';
        }
    </script>
</body>
</html>