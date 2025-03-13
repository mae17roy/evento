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

// Check if booking ID is provided
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
        SELECT bi.*, s.name as service_name, s.image, s.description, u.name as provider_name, u.business_name
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
        SELECT h.*, u.name as changed_by_name
        FROM booking_status_history h
        LEFT JOIN users u ON h.changed_by = u.id
        WHERE h.booking_id = :booking_id
        ORDER BY h.created_at DESC
    ");
    $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $historyStmt->execute();
    $statusHistory = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if review is possible
    $reviewStmt = $db->prepare("
        SELECT COUNT(*) as review_count FROM reviews
        WHERE booking_id = :booking_id AND user_id = :user_id
    ");
    $reviewStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $reviewStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $reviewStmt->execute();
    $reviewResult = $reviewStmt->fetch(PDO::FETCH_ASSOC);
    
    $canReview = ($booking['status'] === 'completed' && $reviewResult['review_count'] == 0);
    
    // Calculate subtotal and tax
    $subtotal = 0;
    foreach ($bookingItems as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    // Assume 10% tax rate
    $taxRate = 0.10;
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;
    
    // Format dates
    $bookingDate = date('F j, Y', strtotime($booking['booking_date']));
    $bookingTime = date('g:i A', strtotime($booking['booking_time']));
    $createdDate = date('F j, Y, g:i A', strtotime($booking['created_at']));
    $updatedDate = date('F j, Y, g:i A', strtotime($booking['updated_at']));
    
    // Set status color
    switch ($booking['status']) {
        case 'pending':
            $statusColor = 'yellow';
            break;
        case 'confirmed':
            $statusColor = 'blue';
            break;
        case 'completed':
            $statusColor = 'green';
            break;
        case 'cancelled':
            $statusColor = 'red';
            break;
        default:
            $statusColor = 'gray';
    }
    
} catch (Exception $e) {
    // Log error and redirect
    error_log("Error retrieving booking details: " . $e->getMessage());
    header("Location: bookings.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking #<?php echo $bookingId; ?> - EVENTO</title>
    
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
                            <a href="bookings.php" class="block px-4 py-2 hover:bg-gray-100 bg-gray-100">
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
                <span class="text-gray-500">Booking #<?php echo $bookingId; ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Booking Header -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-800">Booking #<?php echo $bookingId; ?></h1>
                        <p class="text-gray-500">Ordered on <?php echo $createdDate; ?></p>
                    </div>
                    <div class="mt-4 md:mt-0">
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium bg-<?php echo $statusColor; ?>-100 text-<?php echo $statusColor; ?>-800">
                            <?php echo ucfirst($booking['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-2">Booking Information</h2>
                        <div class="space-y-2">
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
                                <span class="text-<?php echo $statusColor; ?>-600"><?php echo ucfirst($booking['status']); ?></span>
                            </div>
                            <div class="flex">
                                <span class="w-32 text-gray-500">Last Updated:</span>
                                <span class="text-gray-800"><?php echo $updatedDate; ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div>
                        <h2 class="text-lg font-semibold text-gray-800 mb-2">Customer Information</h2>
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
                
                <?php if (!empty($booking['special_requests'])): ?>
                <div class="mt-6">
                    <h2 class="text-lg font-semibold text-gray-800 mb-2">Special Requests</h2>
                    <div class="p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-700 whitespace-pre-line"><?php echo htmlspecialchars($booking['special_requests']); ?></p>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="flex flex-wrap gap-3 mt-6">
                    <a href="bookings.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300 transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i>Back to Bookings
                    </a>
                    
                    <?php if ($booking['status'] === 'pending' || $booking['status'] === 'confirmed'): ?>
                        <form method="POST" action="bookings.php" onsubmit="return confirm('Are you sure you want to cancel this booking?');">
                            <input type="hidden" name="booking_id" value="<?php echo $bookingId; ?>">
                            <button type="submit" name="cancel_booking" class="px-4 py-2 bg-red-100 text-red-600 rounded-lg hover:bg-red-200 transition duration-300">
                                <i class="fas fa-times-circle mr-2"></i>Cancel Booking
                            </button>
                        </form>
                    <?php endif; ?>
                    
                    <?php if ($canReview): ?>
                        <a href="write_review.php?booking_id=<?php echo $bookingId; ?>" class="px-4 py-2 bg-yellow-100 text-yellow-700 rounded-lg hover:bg-yellow-200 transition duration-300">
                            <i class="fas fa-star mr-2"></i>Write Review
                        </a>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Booking Items -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Ordered Services</h2>
                </div>
                
                <div class="divide-y divide-gray-200">
                    <?php foreach ($bookingItems as $item): ?>
                        <div class="p-6">
                            <div class="flex flex-col md:flex-row">
                                <div class="md:w-24 md:h-24 h-32 w-full mb-4 md:mb-0 flex-shrink-0">
                                    <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['service_name']); ?>" 
                                         class="w-full h-full object-cover rounded">
                                </div>
                                <div class="md:ml-6 flex-grow">
                                    <h3 class="text-lg font-semibold text-gray-800 mb-1">
                                        <?php echo htmlspecialchars($item['service_name']); ?>
                                    </h3>
                                    <p class="text-gray-600 mb-2">
                                        <?php echo htmlspecialchars(substr($item['description'], 0, 100) . (strlen($item['description']) > 100 ? '...' : '')); ?>
                                    </p>
                                    <p class="text-gray-500 mb-2">
                                        <i class="fas fa-user mr-1"></i> Provider: 
                                        <?php echo htmlspecialchars($item['business_name'] ? $item['business_name'] : $item['provider_name']); ?>
                                    </p>
                                    <div class="flex justify-between items-center">
                                        <div class="text-gray-600">
                                            $<?php echo number_format($item['price'], 2); ?> x <?php echo $item['quantity']; ?>
                                        </div>
                                        <div class="text-primary-600 font-semibold">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="px-6 py-4 bg-gray-50">
                    <div class="ml-auto md:w-64">
                        <div class="space-y-1">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span class="text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax (10%)</span>
                                <span class="text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="flex justify-between pt-2 border-t border-gray-200 text-lg font-semibold">
                                <span>Total</span>
                                <span class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Status History -->
            <?php if (!empty($statusHistory)): ?>
            <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h2 class="text-lg font-semibold text-gray-800">Status History</h2>
                </div>
                <div class="p-6">
                    <div class="space-y-6">
                        <?php foreach ($statusHistory as $index => $history): ?>
                            <div class="flex">
                                <div class="flex flex-col items-center mr-4">
                                    <div class="w-3 h-3 bg-<?php echo $index === 0 ? 'primary' : 'gray'; ?>-500 rounded-full"></div>
                                    <?php if ($index < count($statusHistory) - 1): ?>
                                        <div class="w-px h-full bg-gray-300 mt-1"></div>
                                    <?php endif; ?>
                                </div>
                                <div>
                                    <div class="flex items-center">
                                        <span class="font-medium text-<?php echo $index === 0 ? 'primary' : 'gray'; ?>-600">
                                            <?php echo ucfirst($history['status']); ?>
                                        </span>
                                        <span class="ml-2 text-sm text-gray-500">
                                            <?php echo date('F j, Y, g:i A', strtotime($history['created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($history['changed_by_name'])): ?>
                                        <p class="text-sm text-gray-500">Changed by: <?php echo htmlspecialchars($history['changed_by_name']); ?></p>
                                    <?php endif; ?>
                                    <?php if (!empty($history['notes'])): ?>
                                        <p class="text-gray-600 mt-1"><?php echo htmlspecialchars($history['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>
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