<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in and has owner role
if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
    // Redirect to login page
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Process booking status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_booking_status'])) {
    $bookingId = isset($_POST['booking_id']) ? (int)$_POST['booking_id'] : 0;
    $newStatus = isset($_POST['status']) ? htmlspecialchars(trim($_POST['status'])) : '';
    
    // Validate status value
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array($newStatus, $validStatuses)) {
        $_SESSION['error_message'] = "Invalid status value.";
        header("Location: booking_management.php");
        exit();
    }
    
    try {
        // First check if this booking is for a service owned by this owner
        $checkOwnerStmt = $db->prepare("
            SELECT COUNT(*) 
            FROM bookings b
            JOIN booking_items bi ON bi.booking_id = b.id
            JOIN services s ON bi.service_id = s.id
            WHERE b.id = ? AND s.owner_id = ?
        ");
        $checkOwnerStmt->execute([$bookingId, $userId]);
        $isOwner = $checkOwnerStmt->fetchColumn() > 0;
        
        if (!$isOwner) {
            $_SESSION['error_message'] = "You don't have permission to update this booking.";
            header("Location: booking_management.php");
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
                // Create notification for customer
                $notificationStmt = $db->prepare("
                    INSERT INTO notifications (
                        user_id,
                        type,
                        title,
                        message,
                        related_id,
                        created_at
                    ) VALUES (
                        :user_id,
                        'booking',
                        :title,
                        :message,
                        :related_id,
                        NOW()
                    )
                ");
                
                $statusText = ucfirst($newStatus);
                $notificationTitle = "Booking #" . $bookingId . " has been " . $statusText;
                $notificationMessage = "Your booking #" . $bookingId . " has been " . $statusText . " by the service provider.";
                
                $notificationStmt->bindParam(':user_id', $customerId, PDO::PARAM_INT);
                $notificationStmt->bindParam(':title', $notificationTitle, PDO::PARAM_STR);
                $notificationStmt->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
                $notificationStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
                $notificationStmt->execute();
            }
            
            // Commit transaction
            $db->commit();
            
            $_SESSION['success_message'] = "Booking status updated to " . ucfirst($newStatus) . " successfully!";
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
    header("Location: booking_management.php");
    exit();
}

// Get pending bookings
try {
    $pendingStmt = $db->prepare("
        SELECT DISTINCT b.id, b.booking_date, b.booking_time, b.total_amount, b.created_at,
               b.status, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON b.user_id = u.id
        WHERE s.owner_id = :owner_id
        AND b.status = 'pending'
        ORDER BY b.created_at DESC
    ");
    
    $pendingStmt->bindParam(':owner_id', $userId, PDO::PARAM_INT);
    $pendingStmt->execute();
    $pendingBookings = $pendingStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get recent bookings (confirmed, completed, cancelled)
    $recentStmt = $db->prepare("
        SELECT DISTINCT b.id, b.booking_date, b.booking_time, b.total_amount, b.created_at,
               b.status, u.name as customer_name, u.email as customer_email, u.phone as customer_phone
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON b.user_id = u.id
        WHERE s.owner_id = :owner_id
        AND b.status != 'pending'
        ORDER BY b.created_at DESC
        LIMIT 10
    ");
    
    $recentStmt->bindParam(':owner_id', $userId, PDO::PARAM_INT);
    $recentStmt->execute();
    $recentBookings = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $pendingBookings = [];
    $recentBookings = [];
    $_SESSION['error_message'] = "Error loading bookings: " . $e->getMessage();
}

// Function to get booking items
function getBookingItems($db, $bookingId) {
    try {
        $stmt = $db->prepare("
            SELECT bi.*, s.name as service_name, s.image
            FROM booking_items bi
            JOIN services s ON bi.service_id = s.id
            WHERE bi.booking_id = :booking_id
        ");
        $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching booking items: " . $e->getMessage());
        return [];
    }
}

// Get user information
try {
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = [
        'name' => $_SESSION['user_name'] ?? 'Unknown User',
        'business_name' => '',
        'role' => $_SESSION['user_role'] ?? 'owner'
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Management - EVENTO</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: {
                            50: '#f0f9ff',
                            100: '#e0f2fe',
                            200: '#bae6fd',
                            300: '#7dd3fc',
                            400: '#38bdf8',
                            500: '#0ea5e9',
                            600: '#0284c7',
                            700: '#0369a1',
                            800: '#075985',
                            900: '#0c4a6e',
                        }
                    }
                }
            }
        }
    </script>
    <style>
        .sidebar-item.active {
            background-color: #f3f4f6;
        }
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-block;
        }
        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .status-confirmed {
            background-color: #dcfce7;
            color: #166534;
        }
        .status-completed {
            background-color: #dbeafe;
            color: #1e40af;
        }
        .status-cancelled {
            background-color: #fee2e2;
            color: #b91c1c;
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
        <!-- Sidebar -->
        <div id="sidebar" class="w-64 bg-white border-r border-gray-200 h-screen sticky top-0">
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
                    <i class="fas fa-home mr-3"></i>
                    <span>Dashboard</span>
                </a>
                <a href="service_management.php" class="flex items-center px-4 py-3 sidebar-item">
                    <i class="fas fa-cogs mr-3"></i>
                    <span>Service Management</span>
                </a>
                <a href="booking_management.php" class="flex items-center px-4 py-3 sidebar-item active">
                    <i class="fas fa-calendar-check mr-3"></i>
                    <span>Booking Management</span>
                    <?php if(count($pendingBookings) > 0): ?>
                    <span class="ml-auto bg-red-500 text-white px-2 py-1 rounded-full text-xs">
                        <?php echo count($pendingBookings); ?>
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
                </a>
            </nav>
            
            <div class="mt-auto p-4 border-t border-gray-200">
                <a href="logout.php" class="flex items-center text-red-600 hover:text-red-800">
                    <i class="fas fa-sign-out-alt mr-2"></i>
                    <span>Logout</span>
                </a>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="flex-1 overflow-auto">
            <header class="bg-white border-b border-gray-200 p-4 flex justify-between items-center sticky top-0 z-10">
                <h1 class="text-2xl font-bold">Booking Management</h1>
                <div class="flex items-center space-x-4">
                    <a href="owner_index.php" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-home text-xl"></i>
                    </a>
                </div>
            </header>
            
            <div class="container mx-auto p-4">
                <!-- Pending Bookings Section -->
                <section class="mb-8">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-yellow-50 px-6 py-4 border-b border-yellow-100">
                            <h2 class="text-lg font-bold text-yellow-800">
                                <i class="fas fa-clock mr-2"></i> Pending Bookings
                                <span class="ml-2 text-yellow-600 bg-yellow-100 px-2 py-1 rounded-full text-xs">
                                    <?php echo count($pendingBookings); ?>
                                </span>
                            </h2>
                        </div>
                        
                        <?php if (count($pendingBookings) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($pendingBookings as $booking): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="#" class="text-primary-600 hover:text-primary-800 font-medium view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                        #<?php echo $booking['id']; ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        $<?php echo number_format($booking['total_amount'], 2); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge status-pending">
                                                        Pending
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <div class="flex space-x-2">
                                                        <form action="" method="POST" class="inline">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="confirmed">
                                                            <button type="submit" name="update_booking_status" class="bg-green-500 hover:bg-green-600 text-white py-1 px-3 rounded-md text-sm">
                                                                <i class="fas fa-check mr-1"></i> Confirm
                                                            </button>
                                                        </form>
                                                        <form action="" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to decline this booking?');">
                                                            <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                            <input type="hidden" name="status" value="cancelled">
                                                            <button type="submit" name="update_booking_status" class="bg-red-500 hover:bg-red-600 text-white py-1 px-3 rounded-md text-sm">
                                                                <i class="fas fa-times mr-1"></i> Decline
                                                            </button>
                                                        </form>
                                                        <button class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md text-sm view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-eye mr-1"></i> View
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <i class="fas fa-check-circle text-green-500 text-4xl mb-4"></i>
                                <p>No pending bookings at the moment.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
                
                <!-- Recent Bookings Section -->
                <section>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden">
                        <div class="bg-gray-50 px-6 py-4 border-b border-gray-100">
                            <h2 class="text-lg font-bold text-gray-800">
                                <i class="fas fa-history mr-2"></i> Recent Bookings
                            </h2>
                        </div>
                        
                        <?php if (count($recentBookings) > 0): ?>
                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead class="bg-gray-50">
                                        <tr>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Booking ID</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody class="bg-white divide-y divide-gray-200">
                                        <?php foreach ($recentBookings as $booking): ?>
                                            <tr class="hover:bg-gray-50">
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <a href="#" class="text-primary-600 hover:text-primary-800 font-medium view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                        #<?php echo $booking['id']; ?>
                                                    </a>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($booking['customer_name']); ?></div>
                                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($booking['customer_email']); ?></div>
                                                </td>
                                                <td class="px-6 py-4">
                                                    <div class="text-sm text-gray-900">
                                                        <?php echo date('F j, Y', strtotime($booking['booking_date'])); ?>
                                                    </div>
                                                    <div class="text-sm text-gray-500">
                                                        <?php echo date('g:i A', strtotime($booking['booking_time'])); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <div class="text-sm font-medium text-gray-900">
                                                        $<?php echo number_format($booking['total_amount'], 2); ?>
                                                    </div>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap">
                                                    <span class="status-badge status-<?php echo $booking['status']; ?>">
                                                        <?php echo ucfirst($booking['status']); ?>
                                                    </span>
                                                </td>
                                                <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                    <?php if($booking['status'] === 'confirmed'): ?>
                                                        <div class="flex space-x-2">
                                                            <form action="" method="POST" class="inline">
                                                                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                                                                <input type="hidden" name="status" value="completed">
                                                                <button type="submit" name="update_booking_status" class="bg-blue-500 hover:bg-blue-600 text-white py-1 px-3 rounded-md text-sm">
                                                                    <i class="fas fa-check-double mr-1"></i> Mark Complete
                                                                </button>
                                                            </form>
                                                            <button class="bg-gray-500 hover:bg-gray-600 text-white py-1 px-3 rounded-md text-sm view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                                <i class="fas fa-eye mr-1"></i> View
                                                            </button>
                                                        </div>
                                                    <?php else: ?>
                                                        <button class="bg-gray-500 hover:bg-gray-600 text-white py-1 px-3 rounded-md text-sm view-booking" data-booking-id="<?php echo $booking['id']; ?>">
                                                            <i class="fas fa-eye mr-1"></i> View
                                                        </button>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <div class="p-6 text-center text-gray-500">
                                <p>No recent bookings found.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
        </div>
    </div>
    
    <!-- Booking Details Modal -->
    <div id="booking-modal" class="fixed inset-0 bg-gray-900 bg-opacity-50 flex justify-center items-center z-50 hidden">
        <div class="bg-white rounded-lg shadow-lg w-full max-w-4xl mx-4 overflow-hidden">
            <div class="flex justify-between items-center px-6 py-4 bg-primary-600 text-white">
                <h3 class="text-lg font-semibold">
                    Booking Details <span id="modal-booking-id"></span>
                </h3>
                <button id="close-modal" class="text-white hover:text-gray-200">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div id="modal-content" class="p-6 max-h-[80vh] overflow-y-auto">
                <div class="flex justify-center">
                    <div class="animate-spin rounded-full h-12 w-12 border-t-2 border-b-2 border-primary-600"></div>
                </div>
                <p class="text-center text-gray-500 mt-4">Loading booking details...</p>
            </div>
        </div>
    </div>

    <!-- JavaScript -->
    <script>
        // Auto hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('#success-alert, #error-alert');
            alerts.forEach(alert => {
                alert.style.display = 'none';
            });
        }, 5000);
        
        // View booking details
        document.querySelectorAll('.view-booking').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                const bookingId = this.getAttribute('data-booking-id');
                
                // Show modal
                document.getElementById('booking-modal').classList.remove('hidden');
                document.getElementById('modal-booking-id').textContent = '#' + bookingId;
                
                // Fetch booking details
                fetch('get_booking_details.php?id=' + bookingId)
                    .then(response => response.json())
                    .then(data => {
                        if (data.status === 'success') {
                            document.getElementById('modal-content').innerHTML = data.html;
                        } else {
                            document.getElementById('modal-content').innerHTML = `
                                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                                    <p>${data.message}</p>
                                </div>
                            `;
                        }
                    })
                    .catch(error => {
                        document.getElementById('modal-content').innerHTML = `
                            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4">
                                <p>Error loading booking details: ${error.message}</p>
                            </div>
                        `;
                    });
            });
        });
        
        // Close modal
        document.getElementById('close-modal').addEventListener('click', function() {
            document.getElementById('booking-modal').classList.add('hidden');
        });
        
        // Close modal when clicking outside
        document.getElementById('booking-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                this.classList.add('hidden');
            }
        });
    </script>
</body>
</html>