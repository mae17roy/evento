<?php
// Include functions file first
require_once 'functions.php';

// Include database configuration
require_once 'config.php';

// Require admin/owner login
requireAdminOwnerLogin();

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

// Process reservation status updates (for non-AJAX requests)
if (isset($_POST['update_booking_status']) && !isset($_SERVER['HTTP_X_REQUESTED_WITH'])) {
    $bookingId = $_POST['booking_id'];
    $newStatus = $_POST['status'];
    
    if (updateBookingStatus($db, $bookingId, $newStatus)) {
        $_SESSION['success_message'] = "Booking status updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update booking status.";
    }
    
    // Redirect to avoid form resubmission
    header("Location: reservations.php");
    exit();
}

// AJAX booking status update
if (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && 
    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest' && 
    isset($_POST['update_booking_status'])) {
    
    $bookingId = isset($_POST['booking_id']) ? intval($_POST['booking_id']) : 0;
    $newStatus = isset($_POST['status']) ? $_POST['status'] : '';
    
    $result = [
        'success' => false,
        'message' => 'Missing required parameters'
    ];
    
    if ($bookingId && $newStatus) {
        if (updateBookingStatus($db, $bookingId, $newStatus)) {
            // Get updated booking details
            $bookingStmt = $db->prepare("
                SELECT b.*, u.name as customer_name, s.name as service_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN booking_items bi ON bi.booking_id = b.id
                JOIN services s ON bi.service_id = s.id
                WHERE b.id = ? AND s.owner_id = ?
                LIMIT 1
            ");
            
            $bookingStmt->execute([$bookingId, $_SESSION['user_id']]);
            $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
            
            $result = [
                'success' => true,
                'message' => "Booking status updated successfully!",
                'booking' => [
                    'id' => $bookingId,
                    'status' => $newStatus,
                    'customer' => $booking['customer_name'] ?? '',
                    'service' => $booking['service_name'] ?? '',
                ]
            ];
        } else {
            $result = [
                'success' => false,
                'message' => "Failed to update booking status."
            ];
        }
    }
    
    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Initialize default filter values
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$serviceFilter = isset($_GET['service']) ? $_GET['service'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';
$pageNumber = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$itemsPerPage = 10;

// Build filter parameters for our enhanced function
$filters = [
    'status' => $statusFilter,
    'service_id' => $serviceFilter,
    'date' => $dateFilter,
    'search' => $searchFilter,
];

// Get reservations with filtering and pagination
$reservationsData = getOwnerReservations($db, $_SESSION['user_id'], $filters, $pageNumber, $itemsPerPage);
$reservations = $reservationsData['reservations'];
$pagination = $reservationsData['pagination'];

// Get booking metrics
function getOwnerBookingMetrics($db, $ownerId) {
    $query = "
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(b.total_amount) as total_revenue
        FROM bookings b
        JOIN booking_items bi ON bi.booking_id = b.id
        JOIN services s ON bi.service_id = s.id
        WHERE s.owner_id = ?
    ";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$ownerId]);
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$bookingMetrics = getOwnerBookingMetrics($db, $_SESSION['user_id']);

// Get services for filter dropdown
$servicesStmt = $db->prepare("SELECT id, name FROM services WHERE owner_id = ? ORDER BY name");
$servicesStmt->execute([$_SESSION['user_id']]);
$services = $servicesStmt->fetchAll(PDO::FETCH_ASSOC);

// Get notifications
$notifications = getRecentNotifications($db, $_SESSION['user_id']);
$unreadNotificationsCount = countUnreadNotifications($db, $_SESSION['user_id']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reservations - EVENTO</title>
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
            background-color: #f59e0b;
            color: white;
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
        
        /* Toast notification */
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
                <h1 class="text-xl font-bold"><?php echo getSystemSetting($db, 'EVENTO', 'EVENTO'); ?></h1>
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
                <a href="reservations.php" class="flex items-center px-4 py-3 sidebar-item active">
                    <i class="mif-calendar mr-3"></i>
                    <span>Reservations</span>
                    <span class="ml-auto bg-blue-600 text-white px-2 py-1 rounded-full text-xs">
                        <?php echo $bookingMetrics['pending'] ?? 0; ?>
                    </span>
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
                    <h1 class="text-2xl font-bold">Reservations</h1>
                </div>
                
                <div class="w-1/3">
                    <form method="get" action="reservations.php">
                        <div class="relative">
                            <input type="text" name="search" id="search-input" placeholder="Search reservations..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($searchFilter); ?>">
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
                    <img src="https://i.pravatar.cc/150?img=<?php echo $user['id'] % 70; ?>" alt="User Avatar" class="w-8 h-8 rounded-full">
                </div>
            </header>
            
            <?php include_once 'reservations_content.php'; ?>
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
                            <?php if (isset($notification['related_id']) && $notification['type'] === 'booking'): ?>
                            <a href="view_booking.php?id=<?php echo $notification['related_id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                            <?php elseif (isset($notification['related_id']) && $notification['type'] === 'service'): ?>
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
        
        // Update booking status via AJAX
        function updateBookingStatus(bookingId, newStatus) {
            // Create form data
            const formData = new FormData();
            formData.append('booking_id', bookingId);
            formData.append('status', newStatus);
            formData.append('update_booking_status', 1);
            
            // Send request
            fetch('reservations.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Update UI
                    const statusBadge = document.querySelector(`.booking-status[data-booking-id="${bookingId}"]`);
                    if (statusBadge) {
                        statusBadge.classList.remove('status-pending', 'status-confirmed', 'status-completed', 'status-cancelled');
                        statusBadge.classList.add(`status-${newStatus.toLowerCase()}`);
                        statusBadge.textContent = newStatus.charAt(0).toUpperCase() + newStatus.slice(1);
                    }
                    
                    // Hide inappropriate action buttons
                    const row = document.querySelector(`tr[data-booking-id="${bookingId}"]`);
                    if (row) {
                        const dropdownMenu = row.querySelector('.dropdown-menu');
                        if (dropdownMenu) {
                            // First hide the dropdown
                            dropdownMenu.classList.add('hidden');
                            
                            // Then update its contents
                            const actionButtons = dropdownMenu.querySelectorAll('.booking-action');
                            actionButtons.forEach(button => {
                                if (newStatus === 'cancelled' || newStatus === 'completed') {
                                    button.style.display = 'none';
                                } else if (newStatus === 'confirmed') {
                                    if (button.getAttribute('data-action') === 'confirmed') {
                                        button.style.display = 'none';
                                    } else {
                                        button.style.display = 'block';
                                    }
                                }
                            });
                        }
                    }
                    
                    // Show success message
                    showToast('Success', `Booking #${bookingId} has been ${newStatus}`, 'success');
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
        
        // Dropdown menus
        document.querySelectorAll('.dropdown-toggle').forEach(button => {
            button.addEventListener('click', function(e) {
                e.stopPropagation();
                const menu = this.nextElementSibling;
                
                // Close all other open dropdowns
                document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                    if (dropdown !== menu) {
                        dropdown.classList.add('hidden');
                    }
                });
                
                menu.classList.toggle('hidden');
            });
        });
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function() {
            document.querySelectorAll('.dropdown-menu').forEach(dropdown => {
                dropdown.classList.add('hidden');
            });
        });
        
        // Booking action buttons
        document.querySelectorAll('.booking-action').forEach(button => {
            button.addEventListener('click', function() {
                const bookingId = this.getAttribute('data-booking-id');
                const action = this.getAttribute('data-action');
                
                if (bookingId && action) {
                    updateBookingStatus(bookingId, action);
                }
            });
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