<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to view bookings.";
    header("Location: login.php");
    exit();
}

// Check if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error_message'] = "Booking ID is required.";
    header("Location: my_bookings.php");
    exit();
}

$bookingId = $_GET['id'];
$userId = $_SESSION['user_id'];

// Get booking details
try {
    // First check if the booking belongs to the current user
    $checkStmt = $db->prepare("
        SELECT user_id FROM bookings WHERE id = ?
    ");
    $checkStmt->execute([$bookingId]);
    $bookingOwner = $checkStmt->fetch(PDO::FETCH_COLUMN);
    
    // If booking doesn't exist or doesn't belong to the current user
    if (!$bookingOwner || $bookingOwner != $userId) {
        $_SESSION['error_message'] = "You don't have permission to view this booking.";
        header("Location: my_bookings.php");
        exit();
    }
    
    // Get booking details
    $stmt = $db->prepare("
        SELECT b.*, u.name as customer_name, u.email as customer_email
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = ?
    ");
    $stmt->execute([$bookingId]);
    $booking = $stmt->fetch();
    
    // Get booking items
    $itemsStmt = $db->prepare("
        SELECT bi.*, s.name as service_name, s.image, 
               s.owner_id, u.name as owner_name, u.business_name
        FROM booking_items bi
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON s.owner_id = u.id
        WHERE bi.booking_id = ?
    ");
    $itemsStmt->execute([$bookingId]);
    $bookingItems = $itemsStmt->fetchAll();
    
    // Get status history
    $historyStmt = $db->prepare("
        SELECT bsh.*, u.name as changed_by_name
        FROM booking_status_history bsh
        LEFT JOIN users u ON bsh.changed_by = u.id
        WHERE bsh.booking_id = ?
        ORDER BY bsh.created_at DESC
    ");
    $historyStmt->execute([$bookingId]);
    $statusHistory = $historyStmt->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: my_bookings.php");
    exit();
}

// Helper function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Helper function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Details - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
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
                <a href="notifications.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-bell text-xl"></i>
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
        
        <div class="mb-6">
            <a href="my_bookings.php" class="flex items-center text-purple-600 hover:text-purple-800 mb-2">
                <i class="fas fa-arrow-left mr-2"></i> Back to My Bookings
            </a>
            <h1 class="text-3xl font-bold">Booking #<?php echo $booking['id']; ?></h1>
        </div>
        
        <!-- Booking Status -->
        <div class="bg-white rounded-lg shadow-md p-6 mb-6">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center">
                <div>
                    <div class="flex items-center mb-2">
                        <span class="status-badge status-<?php echo strtolower($booking['status']); ?> mr-3">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                        <span class="text-gray-500">
                            Booked on <?php echo date('F j, Y', strtotime($booking['created_at'])); ?>
                        </span>
                    </div>
                    
                    <div class="mb-2">
                        <span class="font-medium">Booking Date:</span> 
                        <span><?php echo formatDate($booking['booking_date']); ?> at <?php echo formatTime($booking['booking_time']); ?></span>
                    </div>
                </div>
                
                <?php if ($booking['status'] === 'pending'): ?>
                <div>
                    <button id="cancel-booking-btn" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Cancel Booking
                    </button>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Booking Details -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
            <!-- Booked Service -->
            <div class="md:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="p-6">
                        <h2 class="text-xl font-bold mb-4">Booked Service</h2>
                        
                        <?php foreach ($bookingItems as $item): ?>
                        <div class="flex flex-col md:flex-row items-start border-b border-gray-200 pb-4 mb-4">
                            <img src="../images/<?php echo $item['image'] ?? 'default-service.jpg'; ?>" alt="<?php echo htmlspecialchars($item['service_name']); ?>" 
                                 class="w-full md:w-32 h-32 object-cover rounded-md mb-4 md:mb-0 md:mr-4">
                            
                            <div class="flex-1">
                                <h3 class="text-lg font-bold mb-2"><?php echo htmlspecialchars($item['service_name']); ?></h3>
                                
                                <div class="flex items-center mb-2">
                                    <span class="mr-2 font-medium">Provider:</span>
                                    <span><?php echo htmlspecialchars($item['business_name'] ?? $item['owner_name']); ?></span>
                                </div>
                                
                                <div class="flex items-center mb-4">
                                    <span class="mr-2 font-medium">Price:</span>
                                    <span>$<?php echo number_format($item['price'], 2); ?></span>
                                </div>
                                
                                <a href="service_details.php?id=<?php echo $item['service_id']; ?>" class="text-purple-600 hover:text-purple-800">
                                    View Service Details
                                </a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        
                        <!-- Special Requests -->
                        <?php if (!empty($booking['special_requests'])): ?>
                        <div class="mt-4">
                            <h3 class="text-lg font-medium mb-2">Special Requests</h3>
                            <div class="bg-gray-50 p-4 rounded-md">
                                <p><?php echo nl2br(htmlspecialchars($booking['special_requests'])); ?></p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Booking Summary -->
            <div>
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Booking Summary</h2>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between pb-2 border-b border-gray-200">
                            <span>Subtotal</span>
                            <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                        </div>
                        
                        <div class="flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span>$<?php echo number_format($booking['total_amount'], 2); ?></span>
                        </div>
                    </div>
                    
                    <?php if ($booking['status'] === 'confirmed'): ?>
                    <div class="mt-6">
                        <div class="bg-blue-50 text-blue-800 p-4 rounded-md">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-info-circle mr-2"></i>
                                <span class="font-medium">Booking Confirmed</span>
                            </div>
                            <p class="text-sm">Your booking has been confirmed by the service provider. Please arrive on time for your scheduled service.</p>
                        </div>
                    </div>
                    <?php elseif ($booking['status'] === 'pending'): ?>
                    <div class="mt-6">
                        <div class="bg-yellow-50 text-yellow-800 p-4 rounded-md">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-clock mr-2"></i>
                                <span class="font-medium">Awaiting Confirmation</span>
                            </div>
                            <p class="text-sm">Your booking is waiting for confirmation from the service provider. You'll receive a notification once it's confirmed.</p>
                        </div>
                    </div>
                    <?php elseif ($booking['status'] === 'completed'): ?>
                    <div class="mt-6">
                        <div class="bg-green-50 text-green-800 p-4 rounded-md">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-check-circle mr-2"></i>
                                <span class="font-medium">Service Completed</span>
                            </div>
                            <p class="text-sm">This service has been completed. We hope you enjoyed it!</p>
                        </div>
                        
                        <a href="submit_review.php?booking_id=<?php echo $booking['id']; ?>" class="block w-full text-center mt-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                            Leave a Review
                        </a>
                    </div>
                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                    <div class="mt-6">
                        <div class="bg-red-50 text-red-800 p-4 rounded-md">
                            <div class="flex items-center mb-2">
                                <i class="fas fa-times-circle mr-2"></i>
                                <span class="font-medium">Booking Cancelled</span>
                            </div>
                            <p class="text-sm">This booking has been cancelled. If you have any questions, please contact the service provider.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Contact Provider -->
                <div class="bg-white rounded-lg shadow-md p-6 mt-6">
                    <h2 class="text-xl font-bold mb-4">Need Help?</h2>
                    
                    <p class="text-gray-600 mb-4">If you have any questions about your booking, please contact the service provider directly.</p>
                    
                    <a href="contact_provider.php?id=<?php echo $bookingItems[0]['owner_id']; ?>" class="block w-full text-center py-2 bg-gray-800 text-white rounded-lg hover:bg-gray-900">
                        Contact Provider
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Booking Timeline -->
        <?php if (!empty($statusHistory)): ?>
        <div class="bg-white rounded-lg shadow-md p-6">
            <h2 class="text-xl font-bold mb-6">Booking Timeline</h2>
            
            <div class="relative pl-8 border-l-2 border-gray-200">
                <!-- Created -->
                <div class="mb-8 relative">
                    <div class="absolute -left-10 w-6 h-6 bg-purple-600 rounded-full flex items-center justify-center">
                        <i class="fas fa-plus text-white text-xs"></i>
                    </div>
                    <div>
                        <h3 class="font-medium">Booking Created</h3>
                        <p class="text-sm text-gray-500"><?php echo date('F j, Y g:i A', strtotime($booking['created_at'])); ?></p>
                    </div>
                </div>
                
                <!-- Status History -->
                <?php foreach ($statusHistory as $history): ?>
                <div class="mb-8 relative">
                    <div class="absolute -left-10 w-6 h-6 
                        <?php 
                        if ($history['status'] === 'confirmed') echo 'bg-blue-600';
                        elseif ($history['status'] === 'completed') echo 'bg-green-600';
                        elseif ($history['status'] === 'cancelled') echo 'bg-red-600';
                        else echo 'bg-gray-600';
                        ?> 
                        rounded-full flex items-center justify-center">
                        <?php 
                        if ($history['status'] === 'confirmed') echo '<i class="fas fa-check text-white text-xs"></i>';
                        elseif ($history['status'] === 'completed') echo '<i class="fas fa-flag-checkered text-white text-xs"></i>';
                        elseif ($history['status'] === 'cancelled') echo '<i class="fas fa-times text-white text-xs"></i>';
                        else echo '<i class="fas fa-circle text-white text-xs"></i>';
                        ?>
                    </div>
                    <div>
                        <h3 class="font-medium">Status changed to <?php echo ucfirst($history['status']); ?></h3>
                        <p class="text-sm text-gray-500"><?php echo date('F j, Y g:i A', strtotime($history['created_at'])); ?></p>
                        <?php if (!empty($history['changed_by_name'])): ?>
                        <p class="text-sm text-gray-600">by <?php echo htmlspecialchars($history['changed_by_name']); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($history['notes'])): ?>
                        <p class="mt-2 text-sm bg-gray-50 p-2 rounded"><?php echo htmlspecialchars($history['notes']); ?></p>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </main>

    <!-- Cancel Booking Modal -->
    <div id="cancel-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <h2 class="text-xl font-bold mb-4">Cancel Booking</h2>
            <p class="mb-4">Are you sure you want to cancel this booking? This action cannot be undone.</p>
            
            <form action="cancel_booking.php" method="post">
                <input type="hidden" name="booking_id" value="<?php echo $booking['id']; ?>">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for cancellation (optional)</label>
                    <textarea name="cancel_reason" rows="3" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-modal-close" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        No, Keep Booking
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Yes, Cancel Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

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
        
        // Cancel booking modal
        const cancelBtn = document.getElementById('cancel-booking-btn');
        const cancelModal = document.getElementById('cancel-modal');
        const cancelModalClose = document.getElementById('cancel-modal-close');
        
        if (cancelBtn) {
            cancelBtn.addEventListener('click', function() {
                cancelModal.classList.remove('hidden');
            });
        }
        
        if (cancelModalClose) {
            cancelModalClose.addEventListener('click', function() {
                cancelModal.classList.add('hidden');
            });
        }
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === cancelModal) {
                cancelModal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>