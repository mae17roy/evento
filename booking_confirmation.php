<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in, redirect to index.php if not
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Verify if booking ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: bookings.php");
    exit();
}

$bookingId = (int)$_GET['id'];

// Get booking details
try {
    // Verify the booking belongs to the current user
    $bookingStmt = $db->prepare("
        SELECT b.*, u.name as customer_name, u.email, u.phone, u.address
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = :booking_id AND b.user_id = :user_id
    ");
    $bookingStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $bookingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingStmt->execute();
    $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    // If booking not found or doesn't belong to current user, redirect
    if (!$booking) {
        header("Location: bookings.php");
        exit();
    }
    
    // Get booking items
    $itemsStmt = $db->prepare("
        SELECT bi.*, s.name as service_name, s.image, u.name as provider_name, u.business_name
        FROM booking_items bi
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON s.owner_id = u.id
        WHERE bi.booking_id = :booking_id
    ");
    $itemsStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $bookingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get booking status history
    $historyStmt = $db->prepare("
        SELECT * FROM booking_status_history
        WHERE booking_id = :booking_id
        ORDER BY created_at DESC
    ");
    $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $historyStmt->execute();
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Extract payment method from special requests
    $paymentMethod = '';
    if (preg_match('/Payment Method: (.*?)(?:\n|$)/', $booking['special_requests'], $matches)) {
        $paymentMethod = $matches[1];
    }
    
    // Extract special requests (if any)
    $specialRequests = '';
    if (preg_match('/Special Requests: (.*?)(?:\n|$)/s', $booking['special_requests'], $matches)) {
        $specialRequests = $matches[1];
    }
    
    // Calculate subtotal
    $subtotal = 0;
    foreach ($bookingItems as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Calculate tax (10%)
    $taxRate = 0.10;
    $tax = $subtotal * $taxRate;
    
    // Total should match booking's total_amount
    $total = $subtotal + $tax;
    
    // Format dates
    $bookingDate = date('l, F j, Y', strtotime($booking['booking_date']));
    $bookingTime = date('g:i A', strtotime($booking['booking_time']));
    $orderDate = date('F j, Y, g:i A', strtotime($booking['created_at']));
    
} catch (Exception $e) {
    // Log error and redirect
    error_log("Error retrieving booking confirmation: " . $e->getMessage());
    header("Location: bookings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmed - EVENTO</title>
    
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
                    },
                    animation: {
                        'pulse-slow': 'pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite',
                    }
                }
            }
        }
    </script>
    <style>
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
        .animate-pulse-slow {
            animation: pulse 3s cubic-bezier(0.4, 0, 0.6, 1) infinite;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
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
        
        .timeline-item {
            position: relative;
            padding-left: 2rem;
            padding-bottom: 1rem;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: 0;
            top: 0.5rem;
            height: 100%;
            width: 2px;
            background-color: #e5e7eb;
        }
        .timeline-item:last-child:before {
            height: 0;
        }
        .timeline-dot {
            position: absolute;
            left: -0.375rem;
            top: 0.5rem;
            height: 0.75rem;
            width: 0.75rem;
            border-radius: 9999px;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col">
    <!-- Navigation -->
    <nav class="bg-primary-600 text-white shadow-md sticky top-0 z-50">
        <div class="container mx-auto px-4">
            <div class="flex justify-between items-center py-3">
                <a href="user_index.php" class="text-2xl font-bold">EVENTO</a>
                
                <div class="flex items-center space-x-4">
                    <a href="cart.php" class="relative">
                        <i class="fas fa-shopping-cart text-xl"></i>
                        <?php if (isset($_SESSION['cart']) && !empty($_SESSION['cart'])): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full">
                                <?php echo count($_SESSION['cart']); ?>
                            </span>
                        <?php endif; ?>
                    </a>
                    
                    <div class="relative" x-data="{ open: false }">
                        <button @click="open = !open" class="flex items-center space-x-1 focus:outline-none">
                            <i class="fas fa-user-circle text-xl"></i>
                            <span class="hidden md:inline-block"><?php echo $_SESSION['user_name']; ?></span>
                            <i class="fas fa-chevron-down text-xs"></i>
                        </button>
                        
                        <div x-show="open" @click.away="open = false" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg py-1 z-50 text-gray-800">
                            <a href="profile.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-user mr-2"></i>My Profile
                            </a>
                            <a href="bookings.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-calendar-check mr-2"></i>My Bookings
                            </a>
                            <div class="border-t border-gray-100 my-1"></div>
                            <a href="logout.php" class="block px-4 py-2 hover:bg-gray-100">
                                <i class="fas fa-sign-out-alt mr-2"></i>Logout
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Breadcrumb -->
    <div class="bg-white py-4 shadow-sm">
        <div class="container mx-auto px-4">
            <div class="flex items-center text-sm">
                <a href="user_index.php" class="text-primary-600 hover:text-primary-700">Home</a>
                <span class="mx-2">/</span>
                <a href="bookings.php" class="text-primary-600 hover:text-primary-700">My Bookings</a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Booking Confirmation</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-3xl mx-auto">
            <!-- Confirmation Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 text-center">
                <?php if ($booking['status'] === 'pending'): ?>
                    <div class="text-6xl text-yellow-500 mb-4">
                        <i class="fas fa-clock animate-pulse-slow"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Your Booking is Received!</h1>
                    <p class="text-gray-600 mb-2">Thank you for booking with EVENTO. Your booking request has been received and is awaiting confirmation.</p>
                <?php elseif ($booking['status'] === 'confirmed'): ?>
                    <div class="text-6xl text-green-500 mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Your Booking is Confirmed!</h1>
                    <p class="text-gray-600 mb-2">Great news! The service provider has confirmed your booking. Your services are scheduled as requested.</p>
                <?php elseif ($booking['status'] === 'completed'): ?>
                    <div class="text-6xl text-blue-500 mb-4">
                        <i class="fas fa-flag-checkered"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Booking Completed!</h1>
                    <p class="text-gray-600 mb-2">Your booking has been successfully completed. Thank you for using EVENTO!</p>
                <?php elseif ($booking['status'] === 'cancelled'): ?>
                    <div class="text-6xl text-red-500 mb-4">
                        <i class="fas fa-times-circle"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Booking Cancelled</h1>
                    <p class="text-gray-600 mb-2">This booking has been cancelled. Please contact support if you have any questions.</p>
                <?php endif; ?>
                <p class="text-gray-500 mb-4">A confirmation email has been sent to <?php echo htmlspecialchars($booking['email']); ?></p>
                
                <div class="inline-block bg-gray-100 rounded-lg px-4 py-2 mb-6">
                    <div class="flex items-center">
                        <span class="text-gray-600 mr-2">Booking Number:</span>
                        <span class="font-bold text-gray-800">#<?php echo $bookingId; ?></span>
                    </div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-center gap-4">
                    <a href="bookings.php" class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                        View My Bookings
                    </a>
                    <a href="user_index.php" class="px-6 py-3 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition duration-300">
                        Continue Browsing
                    </a>
                </div>
            </div>
            
            <!-- Booking Status Tracker -->
            <?php if ($booking['status'] !== 'cancelled'): ?>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-lg font-bold text-gray-800 mb-4">Booking Status</h2>
                
                <div class="relative">
                    <div class="absolute left-8 inset-0 h-full w-1 bg-gray-200"></div>
                    
                    <div class="relative z-10">
                        <!-- Booking Placed Step -->
                        <div class="flex items-start mb-6">
                            <div class="flex items-center justify-center bg-primary-600 text-white w-16 h-16 rounded-full flex-shrink-0">
                                <i class="fas fa-clipboard-check text-2xl"></i>
                            </div>
                            <div class="ml-6">
                                <h3 class="font-bold text-gray-800">Booking Placed</h3>
                                <p class="text-sm text-gray-600"><?php echo $orderDate; ?></p>
                                <p class="mt-1">Your booking has been received and is being processed.</p>
                            </div>
                        </div>
                        
                        <!-- Awaiting Confirmation Step -->
                        <div class="flex items-start mb-6">
                            <?php if ($booking['status'] === 'pending'): ?>
                                <div class="flex items-center justify-center bg-yellow-500 text-white w-16 h-16 rounded-full flex-shrink-0 animate-pulse">
                                    <i class="fas fa-clock text-2xl"></i>
                                </div>
                            <?php elseif (in_array($booking['status'], ['confirmed', 'completed'])): ?>
                                <div class="flex items-center justify-center bg-green-500 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-check text-2xl"></i>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-center bg-gray-300 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-clock text-2xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="ml-6">
                                <h3 class="font-bold text-gray-800">Awaiting Confirmation</h3>
                                <?php if ($booking['status'] === 'pending'): ?>
                                    <p class="text-sm text-yellow-600 font-medium">Current Status</p>
                                    <p class="mt-1">Your booking is awaiting confirmation from the service provider.</p>
                                <?php elseif (in_array($booking['status'], ['confirmed', 'completed'])): ?>
                                    <p class="text-sm text-green-600 font-medium">Completed</p>
                                    <p class="mt-1">Your booking has been confirmed by the service provider.</p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Skipped</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Booking Confirmed Step -->
                        <div class="flex items-start mb-6">
                            <?php if ($booking['status'] === 'confirmed'): ?>
                                <div class="flex items-center justify-center bg-green-500 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-calendar-check text-2xl"></i>
                                </div>
                            <?php elseif ($booking['status'] === 'completed'): ?>
                                <div class="flex items-center justify-center bg-blue-500 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-check text-2xl"></i>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-center bg-gray-300 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-calendar-check text-2xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="ml-6">
                                <h3 class="font-bold text-gray-800">Booking Confirmed</h3>
                                <?php if ($booking['status'] === 'confirmed'): ?>
                                    <p class="text-sm text-green-600 font-medium">Current Status</p>
                                    <p class="mt-1">Your booking has been confirmed. The service will be provided on <?php echo $bookingDate; ?> at <?php echo $bookingTime; ?>.</p>
                                <?php elseif ($booking['status'] === 'completed'): ?>
                                    <p class="text-sm text-blue-600 font-medium">Completed</p>
                                    <p class="mt-1">Your service was confirmed and scheduled as requested.</p>
                                <?php elseif ($booking['status'] === 'pending'): ?>
                                    <p class="text-sm text-gray-500">Waiting</p>
                                    <p class="mt-1">This step will be completed once the service provider confirms your booking.</p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Skipped</p>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <!-- Service Completed Step -->
                        <div class="flex items-start">
                            <?php if ($booking['status'] === 'completed'): ?>
                                <div class="flex items-center justify-center bg-blue-500 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-medal text-2xl"></i>
                                </div>
                            <?php else: ?>
                                <div class="flex items-center justify-center bg-gray-300 text-white w-16 h-16 rounded-full flex-shrink-0">
                                    <i class="fas fa-medal text-2xl"></i>
                                </div>
                            <?php endif; ?>
                            <div class="ml-6">
                                <h3 class="font-bold text-gray-800">Service Completed</h3>
                                <?php if ($booking['status'] === 'completed'): ?>
                                    <p class="text-sm text-blue-600 font-medium">Current Status</p>
                                    <p class="mt-1">The service has been successfully completed. Thank you for your business!</p>
                                <?php else: ?>
                                    <p class="text-sm text-gray-500">Waiting</p>
                                    <p class="mt-1">This step will be updated after your service is completed.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Booking Details -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="bg-primary-600 text-white px-6 py-4">
                    <h2 class="text-xl font-semibold">Booking Details</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Booking Summary</h3>
                            <div class="space-y-2">
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Booking #:</span>
                                    <span class="text-gray-800 font-medium"><?php echo $bookingId; ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Date:</span>
                                    <span class="text-gray-800"><?php echo $bookingDate; ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Time:</span>
                                    <span class="text-gray-800"><?php echo $bookingTime; ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Status:</span>
                                    <?php if ($booking['status'] === 'pending'): ?>
                                        <span class="status-badge status-pending">
                                            <div class="w-2 h-2 bg-yellow-500 rounded-full animate-pulse mr-1.5"></div>
                                            Awaiting Confirmation
                                        </span>
                                    <?php elseif ($booking['status'] === 'confirmed'): ?>
                                        <span class="status-badge status-confirmed">
                                            <div class="w-2 h-2 bg-green-500 rounded-full mr-1.5"></div>
                                            Confirmed
                                        </span>
                                    <?php elseif ($booking['status'] === 'completed'): ?>
                                        <span class="status-badge status-completed">
                                            <div class="w-2 h-2 bg-blue-500 rounded-full mr-1.5"></div>
                                            Completed
                                        </span>
                                    <?php elseif ($booking['status'] === 'cancelled'): ?>
                                        <span class="status-badge status-cancelled">
                                            <div class="w-2 h-2 bg-red-500 rounded-full mr-1.5"></div>
                                            Cancelled
                                        </span>
                                    <?php else: ?>
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800">
                                            <?php echo ucfirst($booking['status']); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Order Date:</span>
                                    <span class="text-gray-800"><?php echo $orderDate; ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Payment:</span>
                                    <span class="text-gray-800"><?php echo $paymentMethod; ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-800 mb-3">Customer Information</h3>
                            <div class="space-y-2">
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Name:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($booking['customer_name']); ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Email:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($booking['email']); ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Phone:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($booking['phone']); ?></span>
                                </div>
                                <div class="flex">
                                    <span class="w-32 text-gray-500">Address:</span>
                                    <span class="text-gray-800"><?php echo htmlspecialchars($booking['address']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (!empty($specialRequests)): ?>
                    <div class="mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Special Requests</h3>
                        <div class="p-4 bg-gray-50 rounded-lg text-gray-700">
                            <?php echo nl2br(htmlspecialchars($specialRequests)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Booked Services -->
                    <h3 class="text-lg font-semibold text-gray-800 mb-3">Booked Services</h3>
                    <div class="space-y-4 mb-6">
                        <?php foreach ($bookingItems as $item): ?>
                            <div class="flex flex-col md:flex-row border rounded-lg p-4">
                                <div class="flex-shrink-0 w-full md:w-24 h-24 mb-3 md:mb-0">
                                    <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                         class="w-full h-full object-cover rounded" 
                                         alt="<?php echo htmlspecialchars($item['service_name']); ?>">
                                </div>
                                <div class="md:ml-4 flex-grow">
                                    <h4 class="font-medium text-gray-800"><?php echo htmlspecialchars($item['service_name']); ?></h4>
                                    <p class="text-sm text-gray-500">
                                        Provider: <?php echo htmlspecialchars($item['business_name'] ? $item['business_name'] : $item['provider_name']); ?>
                                    </p>
                                    <div class="flex justify-between mt-2">
                                        <div>
                                            <span class="text-gray-600">$<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?></span>
                                        </div>
                                        <div class="font-medium">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price Summary -->
                    <div class="border-t border-gray-200 pt-4">
                        <div class="ml-auto md:w-64">
                            <div class="space-y-1">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Subtotal:</span>
                                    <span class="text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Tax (10%):</span>
                                    <span class="text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                                </div>
                                <div class="flex justify-between pt-2 border-t border-gray-200 text-lg font-semibold">
                                    <span>Total:</span>
                                    <span class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Status History Section (New) -->
                <?php if (count($statusHistory) > 0): ?>
                <div class="border-t border-gray-200">
                    <div class="p-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-4">Status History</h3>
                        <div class="space-y-4">
                            <?php foreach ($statusHistory as $history): ?>
                                <div class="timeline-item">
                                    <?php 
                                    $dotClass = '';
                                    switch ($history['status']) {
                                        case 'pending':
                                            $dotClass = 'bg-yellow-500 border-2 border-yellow-100';
                                            break;
                                        case 'confirmed':
                                            $dotClass = 'bg-green-500 border-2 border-green-100';
                                            break;
                                        case 'completed':
                                            $dotClass = 'bg-blue-500 border-2 border-blue-100';
                                            break;
                                        case 'cancelled':
                                            $dotClass = 'bg-red-500 border-2 border-red-100';
                                            break;
                                        default:
                                            $dotClass = 'bg-gray-500 border-2 border-gray-100';
                                    }
                                    ?>
                                    <div class="timeline-dot <?php echo $dotClass; ?>"></div>
                                    <div class="bg-gray-50 rounded-md p-3">
                                        <div class="flex justify-between mb-1">
                                            <span class="font-medium text-gray-800">Status: <?php echo ucfirst($history['status']); ?></span>
                                            <span class="text-sm text-gray-500"><?php echo date('M j, Y, g:i A', strtotime($history['created_at'])); ?></span>
                                        </div>
                                        <p class="text-sm text-gray-600"><?php echo htmlspecialchars($history['notes']); ?></p>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="bg-gray-50 p-6 border-t border-gray-200">
                    <div class="max-w-3xl mx-auto">
                        <?php if ($booking['status'] === 'pending'): ?>
                            <div class="text-center">
                                <div class="flex justify-center mb-4">
                                    <div class="bg-yellow-100 rounded-full p-3">
                                        <svg class="h-8 w-8 text-yellow-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <h3 class="font-medium text-gray-800 mb-2">Waiting for Service Provider Confirmation</h3>
                                <p class="text-gray-600 mb-4">Your booking has been successfully submitted and is currently <span class="font-medium">awaiting confirmation</span> from the service provider. You'll receive a notification once your booking is confirmed.</p>
                                <div class="flex items-center justify-center mb-4">
                                    <div class="h-2 w-2 rounded-full bg-yellow-400 animate-pulse mr-2"></div>
                                    <span class="text-sm font-medium text-yellow-800">Status: Awaiting Confirmation</span>
                                </div>
                            </div>
                        <?php elseif ($booking['status'] === 'confirmed'): ?>
                            <div class="text-center">
                                <div class="flex justify-center mb-4">
                                    <div class="bg-green-100 rounded-full p-3">
                                        <svg class="h-8 w-8 text-green-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                        </svg>
                                    </div>
                                </div>
                                <h3 class="font-medium text-gray-800 mb-2">Your Booking is Confirmed!</h3>
                                <p class="text-gray-600 mb-4">The service provider has confirmed your booking. Please prepare for your scheduled service on the date and time shown above.</p>
                                <div class="flex items-center justify-center mb-4">
                                    <div class="h-2 w-2 rounded-full bg-green-500 mr-2"></div>
                                    <span class="text-sm font-medium text-green-800">Status: Confirmed</span>
                                </div>
                            </div>
                        <?php elseif ($booking['status'] === 'completed'): ?>
                            <div class="text-center">
                                <div class="flex justify-center mb-4">
                                    <div class="bg-blue-100 rounded-full p-3">
                                        <svg class="h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                        </svg>
                                    </div>
                                </div>
                                <h3 class="font-medium text-gray-800 mb-2">Service Successfully Completed</h3>
                                <p class="text-gray-600 mb-4">Your service has been completed. Thank you for choosing EVENTO for your event needs!</p>
                                <div class="flex items-center justify-center mb-4">
                                    <div class="h-2 w-2 rounded-full bg-blue-500 mr-2"></div>
                                    <span class="text-sm font-medium text-blue-800">Status: Completed</span>
                                </div>
                            </div>
                        <?php elseif ($booking['status'] === 'cancelled'): ?>
                            <div class="text-center">
                                <div class="flex justify-center mb-4">
                                    <div class="bg-red-100 rounded-full p-3">
                                        <svg class="h-8 w-8 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                        </svg>
                                    </div>
                                </div>
                                <h3 class="font-medium text-gray-800 mb-2">This Booking Has Been Cancelled</h3>
                                <p class="text-gray-600 mb-4">We're sorry, but this booking has been cancelled. Please contact customer support for more information.</p>
                                <div class="flex items-center justify-center mb-4">
                                    <div class="h-2 w-2 rounded-full bg-red-500 mr-2"></div>
                                    <span class="text-sm font-medium text-red-800">Status: Cancelled</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center">
                                <h3 class="font-medium text-gray-800 mb-2">What's Next?</h3>
                                <p class="text-gray-600 mb-4">Thank you for your booking with EVENTO. The current status of your booking is: <span class="font-medium"><?php echo ucfirst($booking['status']); ?></span>.</p>
                            </div>
                        <?php endif; ?>
                        
                        <p class="text-sm text-gray-500 text-center mt-4">
                            For any questions or changes to your booking, please contact us at 
                            <a href="mailto:support@evento.com" class="text-primary-600 hover:text-primary-700">support@evento.com</a> 
                            or call <span class="font-medium">(123) 456-7890</span>
                        </p>
                    </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="flex justify-center space-x-4 mb-8">
                <button onclick="window.print()" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition duration-300">
                    <i class="fas fa-print mr-2"></i> Print Confirmation
                </button>
                <a href="bookings.php" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                    <i class="fas fa-calendar-check mr-2"></i> View My Bookings
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-10 mt-auto">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div>
                    <h5 class="text-lg font-bold mb-4">EVENTO</h5>
                    <p class="text-gray-400">Your one-stop solution for all event management needs.</p>
                </div>
                <div>
                    <h5 class="text-lg font-bold mb-4">Quick Links</h5>
                    <ul class="space-y-2">
                        <li><a href="user_index.php" class="text-gray-400 hover:text-white transition duration-300">Home</a></li>
                        <li><a href="services.php" class="text-gray-400 hover:text-white transition duration-300">Services</a></li>
                        <li><a href="about.php" class="text-gray-400 hover:text-white transition duration-300">About Us</a></li>
                        <li><a href="contact.php" class="text-gray-400 hover:text-white transition duration-300">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-bold mb-4">Contact Us</h5>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-2"></i>
                            <span>123 Event Street, Service City, SC 12345</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>(123) 456-7890</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <span>info@evento.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            <hr class="border-gray-700 my-6">
            <div class="text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Alpine.js (for dropdown functionality) -->
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</body>
</html>