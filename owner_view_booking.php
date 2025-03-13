<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in and is a service owner
requireOwnerLogin();

// Get owner ID
$ownerId = $_SESSION['user_id'];

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Booking ID is required.";
    header("Location: owner_bookings.php");
    exit();
}

$bookingId = $_GET['id'];

// Check if the booking is for a service owned by this owner
if (!canAccessBooking($db, $ownerId, $bookingId, 'owner')) {
    $_SESSION['error_message'] = "You don't have permission to view this booking.";
    header("Location: owner_bookings.php");
    exit();
}

// Handle booking status update
if (isset($_POST['update_status']) && !empty($_POST['status'])) {
    $newStatus = $_POST['status'];
    $notes = isset($_POST['notes']) ? $_POST['notes'] : '';
    
    // Begin transaction
    $db->beginTransaction();
    
    try {
        // Set admin user ID for trigger
        $db->query("SET @admin_user_id = " . $ownerId);
        
        // Update booking status
        $updateStmt = $db->prepare("
            UPDATE bookings 
            SET status = ?, updated_at = NOW() 
            WHERE id = ?
        ");
        $updateStmt->execute([$newStatus, $bookingId]);
        
        // Add status change history with notes if provided
        if (!empty($notes)) {
            $historyStmt = $db->prepare("
                INSERT INTO booking_status_history (
                    booking_id, status, notes, changed_by, created_at
                ) VALUES (
                    ?, ?, ?, ?, NOW()
                )
            ");
            $historyStmt->execute([$bookingId, $newStatus, $notes, $ownerId]);
        }
        
        // Create notification for client
        $bookingData = getBookingById($db, $bookingId);
        $bookingItems = getBookingItems($db, $bookingId);
        
        if ($bookingData && !empty($bookingItems)) {
            $serviceName = $bookingItems[0]['service_name'];
            
            $title = "Booking #$bookingId " . ucfirst($newStatus);
            $message = "Your booking for $serviceName on " . formatDate($bookingData['booking_date']) . " at " . formatTime($bookingData['booking_time']) . " has been $newStatus.";
            
            if (!empty($notes)) {
                $message .= " Note: $notes";
            }
            
            $notifyStmt = $db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, related_id, created_at
                ) VALUES (
                    ?, 'booking', ?, ?, ?, NOW()
                )
            ");
            $notifyStmt->execute([$bookingData['user_id'], $title, $message, $bookingId]);
        }
        
        // Commit transaction
        $db->commit();
        
        $_SESSION['success_message'] = "Booking status has been updated to " . ucfirst($newStatus) . ".";
        header("Location: owner_view_booking.php?id=" . $bookingId);
        exit();
    } catch (PDOException $e) {
        // Rollback transaction on error
        $db->rollBack();
        $_SESSION['error_message'] = "Error updating booking status: " . $e->getMessage();
    }
}

// Get booking details
$booking = getBookingById($db, $bookingId);
$bookingItems = getBookingItems($db, $bookingId);

// Get customer details
$customerStmt = $db->prepare("
    SELECT * FROM users 
    WHERE id = ?
");
$customerStmt->execute([$booking['user_id']]);
$customer = $customerStmt->fetch();

// Get booking history
$historyStmt = $db->prepare("
    SELECT h.*, u.name as changed_by_name
    FROM booking_status_history h
    LEFT JOIN users u ON h.changed_by = u.id
    WHERE h.booking_id = ?
    ORDER BY h.created_at DESC
");
$historyStmt->execute([$bookingId]);
$history = $historyStmt->fetchAll();

// Check if client has other bookings with this owner
$otherBookingsStmt = $db->prepare("
    SELECT COUNT(*) 
    FROM bookings b
    JOIN booking_items bi ON b.id = bi.booking_id
    JOIN services s ON bi.service_id = s.id
    WHERE b.user_id = ? AND s.owner_id = ? AND b.id != ?
");
$otherBookingsStmt->execute([$booking['user_id'], $ownerId, $bookingId]);
$hasOtherBookings = $otherBookingsStmt->fetchColumn() > 0;

// Get owner info
$ownerStmt = $db->prepare("SELECT * FROM users WHERE id = ?");
$ownerStmt->execute([$ownerId]);
$owner = $ownerStmt->fetch();

// Get unread notification count
$unreadCount = getOwnerNotificationCount($db, $ownerId);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?php echo $bookingId; ?> Details - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            font-size: 0.875rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .status-confirmed {
            background-color: #1a2e46;
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
        .timeline-item {
            position: relative;
            padding-left: 1.5rem;
            padding-bottom: 1.5rem;
            border-left: 2px solid #e5e7eb;
        }
        .timeline-item:last-child {
            border-left-color: transparent;
        }
        .timeline-dot {
            position: absolute;
            left: -0.5rem;
            top: 0;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
        }
        .timeline-dot.pending {
            background-color: #9ca3af;
        }
        .timeline-dot.confirmed {
            background-color: #1a2e46;
        }
        .timeline-dot.completed {
            background-color: #10b981;
        }
        .timeline-dot.cancelled {
            background-color: #ef4444;
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
                <a href="owner_bookings.php" class="sidebar-item active">
                    <i class="fas fa-calendar-alt"></i>
                    <span>Bookings</span>
                </a>
                <a href="owner_customers.php" class="sidebar-item">
                    <i class="fas fa-users"></i>
                    <span>Customers</span>
                </a>
                <a href="owner_notifications.php" class="sidebar-item">
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
                <div class="text-xl font-bold">Booking Details</div>
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
                
                <!-- Back Button -->
                <div class="mb-4">
                    <a href="owner_bookings.php" class="inline-flex items-center text-gray-600 hover:text-gray-800">
                        <i class="fas fa-arrow-left mr-2"></i>
                        <span>Back to Bookings</span>
                    </a>
                </div>
                
                <!-- Booking Header -->
                <div class="bg-white rounded-lg shadow-sm p-6 mb-6">
                    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                        <div>
                            <h1 class="text-2xl font-bold mb-2">Booking #<?php echo $bookingId; ?></h1>
                            <div class="flex items-center">
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?> mr-3">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                                <span class="text-gray-500">
                                    Created on <?php echo date('F j, Y', strtotime($booking['created_at'])); ?> at <?php echo date('g:i A', strtotime($booking['created_at'])); ?>
                                </span>
                            </div>
                        </div>
                        
                        <div class="mt-4 md:mt-0">
                            <button id="update-status-btn" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Update Status
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Main Content Grid -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                    <!-- Booking Details - 2 columns -->
                    <div class="md:col-span-2 grid grid-cols-1 gap-6">
                        <!-- Service Details -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold mb-4">Service Details</h2>
                            
                            <?php foreach ($bookingItems as $item): ?>
                            <div class="flex border-b border-gray-100 pb-4 mb-4 last:border-b-0 last:pb-0 last:mb-0">
                                <img src="../images/<?php echo $item['image'] ?? 'default-service.jpg'; ?>" alt="<?php echo htmlspecialchars($item['service_name']); ?>" 
                                     class="w-20 h-20 object-cover rounded-md mr-4">
                                <div>
                                    <h3 class="font-medium"><?php echo htmlspecialchars($item['service_name']); ?></h3>
                                    <div class="text-sm text-gray-500 mt-1">
                                        <div>Quantity: <?php echo $item['quantity']; ?></div>
                                        <div>Price: $<?php echo number_format($item['price'], 2); ?></div>
                                    </div>
                                    
                                    <a href="owner_service_details.php?id=<?php echo $item['service_id']; ?>" class="text-sm text-purple-600 hover:text-purple-800 mt-2 inline-block">
                                        View Service
                                    </a>
                                </div>
                            </div>
                            <?php endforeach; ?>
                            
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <div class="flex justify-between font-semibold">
                                    <span>Total Amount:</span>
                                    <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Booking Information -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold mb-4">Booking Information</h2>
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <p class="text-sm text-gray-500">Booking Date</p>
                                    <p class="font-medium"><?php echo formatDate($booking['booking_date']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Booking Time</p>
                                    <p class="font-medium"><?php echo formatTime($booking['booking_time']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Status</p>
                                    <p class="font-medium"><?php echo ucfirst($booking['status']); ?></p>
                                </div>
                                <div>
                                    <p class="text-sm text-gray-500">Last Updated</p>
                                    <p class="font-medium"><?php echo formatDate(date('Y-m-d', strtotime($booking['updated_at']))); ?> at <?php echo formatTime(date('H:i:s', strtotime($booking['updated_at']))); ?></p>
                                </div>
                            </div>
                            
                            <?php if (!empty($booking['special_requests'])): ?>
                            <div class="mt-4 pt-4 border-t border-gray-200">
                                <p class="text-sm text-gray-500">Special Requests</p>
                                <div class="bg-gray-50 p-3 rounded-md mt-2">
                                    <?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Status Timeline -->
                        <div class="bg-white rounded-lg shadow-sm p-6">
                            <h2 class="text-lg font-semibold mb-4">Status Timeline</h2>
                            
                            <div class="ml-4">
                                <!-- Created status -->
                                <div class="timeline-item">
                                    <div class="timeline-dot pending"></div>
                                    <div>
                                        <p class="font-medium">Booking Created</p>
                                        <p class="text-sm text-gray-500"><?php echo formatDate(date('Y-m-d', strtotime($booking['created_at']))); ?> at <?php echo formatTime(date('H:i:s', strtotime($booking['created_at']))); ?></p>
                                    </div>
                                </div>
                                
                                <!-- Status history -->
                                <?php foreach ($history as $event): ?>
                                <div class="timeline-item">
                                    <div class="timeline-dot <?php echo strtolower($event['status']); ?>"></div>
                                    <div>
                                        <p class="font-medium">Status changed to <?php echo ucfirst($event['status']); ?></p>
                                        <p class="text-sm text-gray-500"><?php echo formatDate(date('Y-m-d', strtotime($event['created_at']))); ?> at <?php echo formatTime(date('H:i:s', strtotime($event['created_at']))); ?></p>
                                        <?php if (!empty($event['changed_by_name'])): ?>
                                        <p class="text-sm text-gray-600">by <?php echo htmlspecialchars($event['changed_by_name']); ?></p>
                                        <?php endif; ?>
                                        <?php if (!empty($event['notes'])): ?>
                                        <div class="bg-gray-50 p-3 rounded-md mt-2 text-sm">
                                            <?php echo nl2br(htmlspecialchars($event['notes'])); ?>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Customer Information - 1 column -->
                    <div class="md:col-span-1">
                        <div class="bg-white rounded-lg shadow-sm p-6 sticky top-6">
                            <h2 class="text-lg font-semibold mb-4">Customer Information</h2>
                            
                            <div class="flex items-center mb-4">
                                <div class="w-12 h-12 flex items-center justify-center bg-gray-200 rounded-full mr-3 text-gray-700 font-semibold">
                                    <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <h3 class="font-medium"><?php echo htmlspecialchars($customer['name']); ?></h3>
                                    <p class="text-sm text-gray-500">Client since <?php echo date('F Y', strtotime($customer['created_at'])); ?></p>
                                </div>
                            </div>
                            
                            <div class="space-y-3 mb-6">
                                <div>
                                    <p class="text-sm text-gray-500">Email</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($customer['email']); ?></p>
                                </div>
                                <?php if (!empty($customer['phone'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500">Phone</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($customer['phone']); ?></p>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($customer['address'])): ?>
                                <div>
                                    <p class="text-sm text-gray-500">Address</p>
                                    <p class="font-medium"><?php echo htmlspecialchars($customer['address']); ?></p>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="border-t border-gray-200 pt-4">
                                <h3 class="font-medium mb-3">Quick Actions</h3>
                                <div class="space-y-2">
                                    <a href="owner_contact_customer.php?id=<?php echo $customer['id']; ?>" class="flex items-center text-gray-700 hover:text-purple-600">
                                        <i class="fas fa-envelope mr-2"></i>
                                        <span>Contact Customer</span>
                                    </a>
                                    
                                    <?php if ($hasOtherBookings): ?>
                                    <a href="owner_customer_bookings.php?id=<?php echo $customer['id']; ?>" class="flex items-center text-gray-700 hover:text-purple-600">
                                        <i class="fas fa-history mr-2"></i>
                                        <span>View Past Bookings</span>
                                    </a>
                                    <?php endif; ?>
                                    
                                    <a href="owner_customers.php?id=<?php echo $customer['id']; ?>" class="flex items-center text-gray-700 hover:text-purple-600">
                                        <i class="fas fa-user mr-2"></i>
                                        <span>Customer Profile</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Update Status Modal -->
    <div id="update-status-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg max-w-lg w-full p-6">
            <div class="flex justify-between items-center mb-4">
                <h2 class="text-xl font-bold">Update Booking Status</h2>
                <button id="close-modal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            
            <form method="post" action="">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-2">Current Status:</label>
                    <div class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                        <?php echo ucfirst($booking['status']); ?>
                    </div>
                </div>
                
                <div class="mb-4">
                    <label for="status" class="block text-sm font-medium text-gray-700 mb-2">New Status:</label>
                    <select id="status" name="status" class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="">Select Status</option>
                        <?php if ($booking['status'] !== 'confirmed'): ?>
                        <option value="confirmed">Confirmed</option>
                        <?php endif; ?>
                        <?php if ($booking['status'] !== 'completed' && $booking['status'] !== 'cancelled'): ?>
                        <option value="completed">Completed</option>
                        <?php endif; ?>
                        <?php if ($booking['status'] !== 'cancelled'): ?>
                        <option value="cancelled">Cancelled</option>
                        <?php endif; ?>
                    </select>
                </div>
                
                <div class="mb-6">
                    <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">Notes (optional):</label>
                    <textarea id="notes" name="notes" rows="3" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-update" class="px-4 py-2 bg-gray-200 text-gray-800 rounded-md hover:bg-gray-300">
                        Cancel
                    </button>
                    <button type="submit" name="update_status" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700">
                        Update Status
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Scripts -->
    <script>
        // Mobile menu toggle
        const menuButton = document.getElementById('menu-button');
        const closeSidebarButton = document.getElementById('close-sidebar');
        const overlay = document.getElementById('overlay');
        const sidebar = document.getElementById('sidebar');
        
        if (menuButton) {
            menuButton.addEventListener('click', function() {
                sidebar.classList.add('show');
                overlay.classList.add('show');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            });
        }
        
        if (closeSidebarButton) {
            closeSidebarButton.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = ''; // Re-enable scrolling
            });
        }
        
        if (overlay) {
            overlay.addEventListener('click', function() {
                sidebar.classList.remove('show');
                overlay.classList.remove('show');
                document.body.style.overflow = ''; // Re-enable scrolling
            });
        }
        
        // Update status modal
        const updateStatusBtn = document.getElementById('update-status-btn');
        const updateStatusModal = document.getElementById('update-status-modal');
        const closeModalBtn = document.getElementById('close-modal');
        const cancelUpdateBtn = document.getElementById('cancel-update');
        
        if (updateStatusBtn) {
            updateStatusBtn.addEventListener('click', function() {
                updateStatusModal.classList.remove('hidden');
                document.body.style.overflow = 'hidden'; // Prevent scrolling
            });
        }
        
        if (closeModalBtn) {
            closeModalBtn.addEventListener('click', function() {
                updateStatusModal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable scrolling
            });
        }
        
        if (cancelUpdateBtn) {
            cancelUpdateBtn.addEventListener('click', function() {
                updateStatusModal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable scrolling
            });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === updateStatusModal) {
                updateStatusModal.classList.add('hidden');
                document.body.style.overflow = ''; // Re-enable scrolling
            }
        });
    </script>
</body>
</html>