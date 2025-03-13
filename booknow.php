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

// Check if the cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    header("Location: cart.php");
    exit();
}

// Get user information
try {
    $userStmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
    $userStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $userStmt->execute();
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error fetching user data: " . $e->getMessage());
    $user = [
        'name' => '',
        'email' => '',
        'phone' => '',
        'address' => ''
    ];
}

// Get cart items with service details
$cartItems = [];
$subtotal = 0;

foreach ($_SESSION['cart'] as $index => $item) {
    // Get the latest service information from the database
    $stmt = $db->prepare("
        SELECT s.*, c.name as category_name, u.name as provider_name, u.business_name
        FROM services s
        LEFT JOIN categories c ON s.category_id = c.id
        LEFT JOIN users u ON s.owner_id = u.id
        WHERE s.id = :id AND s.is_available = 1
    ");
    $stmt->bindParam(':id', $item['service_id'], PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service) {
        // Use the current price from the database (it might have changed)
        $itemSubtotal = $service['price'] * $item['quantity'];
        $subtotal += $itemSubtotal;
        
        $cartItems[] = [
            'index' => $index,
            'service_id' => $item['service_id'],
            'name' => $service['name'],
            'price' => $service['price'],
            'image' => $service['image'],
            'category_name' => $service['category_name'],
            'provider_name' => $service['provider_name'],
            'business_name' => $service['business_name'],
            'quantity' => $item['quantity'],
            'subtotal' => $itemSubtotal
        ];
    }
}

// Calculate tax and total
$taxRate = 0.10;
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

// Get available dates (next 30 days excluding past dates)
$availableDates = [];
$today = new DateTime();
$endDate = (new DateTime())->modify('+30 days');

while ($today <= $endDate) {
    $availableDates[] = $today->format('Y-m-d');
    $today->modify('+1 day');
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_booking'])) {
    // Validate required fields
    $requiredFields = ['booking_date', 'booking_time', 'name', 'email', 'phone', 'address', 'payment_method'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        $error = "Please fill in all required fields.";
    } else {
        // Get form data
        $bookingDate = $_POST['booking_date'];
        $bookingTime = $_POST['booking_time'];
        $name = trim($_POST['name']);
        $email = trim($_POST['email']);
        $phone = trim($_POST['phone']);
        $address = trim($_POST['address']);
        $paymentMethod = $_POST['payment_method'];
        $specialRequests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';
        
        try {
            // Begin transaction
            $db->beginTransaction();
            
            // Create booking record
            $bookingStmt = $db->prepare("
                INSERT INTO bookings (
                    user_id, 
                    total_amount, 
                    status, 
                    booking_date, 
                    booking_time, 
                    special_requests, 
                    created_at
                ) VALUES (
                    :user_id,
                    :total_amount,
                    'pending',
                    :booking_date,
                    :booking_time,
                    :special_requests,
                    NOW()
                )
            ");
            
            // Add customer details and payment method to special requests
            $fullSpecialRequests = "Payment Method: $paymentMethod\n";
            if (!empty($specialRequests)) {
                $fullSpecialRequests .= "Special Requests: $specialRequests\n";
            }
            
            $bookingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $bookingStmt->bindParam(':total_amount', $total, PDO::PARAM_STR);
            $bookingStmt->bindParam(':booking_date', $bookingDate, PDO::PARAM_STR);
            $bookingStmt->bindParam(':booking_time', $bookingTime, PDO::PARAM_STR);
            $bookingStmt->bindParam(':special_requests', $fullSpecialRequests, PDO::PARAM_STR);
            $bookingStmt->execute();
            
            // Get the booking ID
            $bookingId = $db->lastInsertId();
            
            // Add all cart items to booking_items
            foreach ($cartItems as $item) {
                $bookingItemStmt = $db->prepare("
                    INSERT INTO booking_items (
                        booking_id,
                        service_id,
                        quantity,
                        price,
                        created_at
                    ) VALUES (
                        :booking_id,
                        :service_id,
                        :quantity,
                        :price,
                        NOW()
                    )
                ");
                
                $bookingItemStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
                $bookingItemStmt->bindParam(':service_id', $item['service_id'], PDO::PARAM_INT);
                $bookingItemStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $bookingItemStmt->bindParam(':price', $item['price'], PDO::PARAM_STR);
                $bookingItemStmt->execute();
            }
            
            // Record booking status history
            $historyStmt = $db->prepare("
                INSERT INTO booking_status_history (
                    booking_id,
                    status,
                    notes,
                    created_at
                ) VALUES (
                    :booking_id,
                    'pending',
                    :notes,
                    NOW()
                )
            ");
            
            $historyNotes = "Booking created from cart. Payment method: $paymentMethod";
            $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $historyStmt->bindParam(':notes', $historyNotes, PDO::PARAM_STR);
            $historyStmt->execute();
            
            // Update user profile information if it has changed
            $updateUserStmt = $db->prepare("
                UPDATE users 
                SET name = :name, email = :email, phone = :phone, address = :address 
                WHERE id = :user_id
            ");
            
            $updateUserStmt->bindParam(':name', $name, PDO::PARAM_STR);
            $updateUserStmt->bindParam(':email', $email, PDO::PARAM_STR);
            $updateUserStmt->bindParam(':phone', $phone, PDO::PARAM_STR);
            $updateUserStmt->bindParam(':address', $address, PDO::PARAM_STR);
            $updateUserStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $updateUserStmt->execute();
            
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
            
            $notificationTitle = "Booking Confirmation #$bookingId";
            $notificationMessage = "Your booking has been placed successfully and is pending confirmation. Total amount: $" . number_format($total, 2);
            
            $notificationStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $notificationStmt->bindParam(':title', $notificationTitle, PDO::PARAM_STR);
            $notificationStmt->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
            $notificationStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
            $notificationStmt->execute();
            
            // Create notifications for service providers
            $providerNotifications = [];
            
            foreach ($cartItems as $item) {
                $serviceQuery = $db->prepare("SELECT owner_id FROM services WHERE id = :service_id");
                $serviceQuery->bindParam(':service_id', $item['service_id'], PDO::PARAM_INT);
                $serviceQuery->execute();
                $serviceData = $serviceQuery->fetch(PDO::FETCH_ASSOC);
                
                if ($serviceData && $serviceData['owner_id']) {
                    // If we haven't notified this provider yet for this booking
                    if (!isset($providerNotifications[$serviceData['owner_id']])) {
                        $ownerNotifStmt = $db->prepare("
                            INSERT INTO notifications (
                                owner_id,
                                type,
                                title,
                                message,
                                related_id,
                                created_at
                            ) VALUES (
                                :owner_id,
                                'booking',
                                :title,
                                :message,
                                :related_id,
                                NOW()
                            )
                        ");
                        
                        $ownerTitle = "New Booking #$bookingId";
                        $ownerMessage = "You have received a new booking for your services on " . date('F j, Y', strtotime($bookingDate)) . " at " . date('g:i A', strtotime($bookingTime)) . ".";
                        
                        $ownerNotifStmt->bindParam(':owner_id', $serviceData['owner_id'], PDO::PARAM_INT);
                        $ownerNotifStmt->bindParam(':title', $ownerTitle, PDO::PARAM_STR);
                        $ownerNotifStmt->bindParam(':message', $ownerMessage, PDO::PARAM_STR);
                        $ownerNotifStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
                        $ownerNotifStmt->execute();
                        
                        // Mark as notified
                        $providerNotifications[$serviceData['owner_id']] = true;
                    }
                }
            }
            
            // Commit transaction
            $db->commit();
            
            // Clear the cart
            $_SESSION['cart'] = [];
            
            // Redirect to booking confirmation page
            header("Location: booking_confirmation.php?id=$bookingId");
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollBack();
            
            error_log("Error processing booking: " . $e->getMessage());
            $error = "An error occurred while processing your booking. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Cart Services - EVENTO</title>
    
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
                        <?php if (count($cartItems) > 0): ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full">
                                <?php echo count($cartItems); ?>
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
                <a href="cart.php" class="text-primary-600 hover:text-primary-700">Cart</a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Book Cart Services</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Complete Your Booking</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Booking Details</h2>
                    
                    <?php if (isset($error)): ?>
                        <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                                <div class="ml-3">
                                    <p><?php echo $error; ?></p>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="">
                        <!-- Booking Date and Time -->
                        <div class="mb-6">
                            <h3 class="text-gray-700 font-medium mb-3">When would you like to book these services?</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-1">Date *</label>
                                    <input type="date" id="booking_date" name="booking_date" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           min="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div>
                                    <label for="booking_time" class="block text-sm font-medium text-gray-700 mb-1">Time *</label>
                                    <input type="time" id="booking_time" name="booking_time" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           required>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Customer Information -->
                        <div class="mb-6">
                            <h3 class="text-gray-700 font-medium mb-3">Your Information</h3>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Full Name *</label>
                                    <input type="text" id="name" name="name" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" required>
                                </div>
                                <div>
                                    <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email Address *</label>
                                    <input type="email" id="email" name="email" 
                                           class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number *</label>
                                <input type="tel" id="phone" name="phone" 
                                       class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-4">
                                <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address *</label>
                                <textarea id="address" name="address" rows="3" 
                                          class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                          required><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                        </div>
                        
                        <!-- Special Requests -->
                        <div class="mb-6">
                            <label for="special_requests" class="block text-sm font-medium text-gray-700 mb-1">Special Requests (Optional)</label>
                            <textarea id="special_requests" name="special_requests" rows="3" 
                                      class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
                                      placeholder="Any special instructions or requirements for your booking?"></textarea>
                        </div>
                        
                        <!-- Payment Method -->
                        <div class="mb-6">
                            <h3 class="text-gray-700 font-medium mb-3">Payment Method</h3>
                            <div class="space-y-3">
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" name="payment_method" value="cash" checked 
                                           class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                    <span class="flex-1">
                                        <span class="font-medium block">Cash on Delivery</span>
                                        <span class="text-sm text-gray-500">Pay when you receive the service</span>
                                    </span>
                                    <i class="fas fa-money-bill-wave text-green-500 text-xl"></i>
                                </label>
                                
                                <label class="flex items-center p-3 border border-gray-200 rounded-lg hover:bg-gray-50 cursor-pointer">
                                    <input type="radio" name="payment_method" value="card" 
                                           class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                    <span class="flex-1">
                                        <span class="font-medium block">Credit/Debit Card</span>
                                        <span class="text-sm text-gray-500">Secure payment via credit or debit card</span>
                                    </span>
                                    <div class="flex space-x-1">
                                        <i class="fab fa-cc-visa text-blue-800 text-xl"></i>
                                        <i class="fab fa-cc-mastercard text-red-500 text-xl"></i>
                                    </div>
                                </label>
                            </div>
                        </div>
                        
                        <div class="pt-4 border-t border-gray-200">
                            <button type="submit" name="submit_booking" class="w-full py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                                Complete Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Order Summary</h2>
                    
                    <!-- Services List -->
                    <div class="max-h-64 overflow-y-auto mb-4">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="flex items-center border-b border-gray-100 py-3">
                                <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                     class="w-12 h-12 object-cover rounded mr-3 flex-shrink-0" 
                                     alt="<?php echo htmlspecialchars($item['name']); ?>">
                                <div class="flex-1">
                                    <h3 class="font-medium text-gray-800 text-sm"><?php echo htmlspecialchars($item['name']); ?></h3>
                                    <p class="text-xs text-gray-500">Qty: <?php echo $item['quantity']; ?> x $<?php echo number_format($item['price'], 2); ?></p>
                                </div>
                                <div class="text-right">
                                    <p class="font-medium text-primary-600">$<?php echo number_format($item['subtotal'], 2); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div class="space-y-3 border-t border-b border-gray-200 py-4 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (10%):</span>
                            <span class="text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Total -->
                    <div class="flex justify-between font-bold text-lg mb-6">
                        <span>Total:</span>
                        <span class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <div class="text-xs text-gray-500 mb-4">
                        <p>By placing your booking, you agree to EVENTO's terms and conditions.</p>
                    </div>
                    
                    <div class="flex justify-center items-center">
                        <a href="cart.php" class="text-primary-600 hover:text-primary-700">
                            <i class="fas fa-arrow-left mr-1"></i> Back to Cart
                        </a>
                    </div>
                </div>
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