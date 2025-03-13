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

// Check if cart exists and is not empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    $_SESSION['error_message'] = "Your cart is empty. Please add services before booking.";
    header("Location: cart.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Get cart items with latest service information
    $cartItems = [];
    foreach ($_SESSION['cart'] as $index => $item) {
        // Get the latest service information from the database
        $stmt = $db->prepare("SELECT id, name, price FROM services WHERE id = :id AND is_available = 1");
        $stmt->bindParam(':id', $item['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            $cartItems[] = [
                'service_id' => $service['id'],
                'name' => $service['name'],
                'price' => $service['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $service['price'] * $item['quantity']
            ];
        }
    }
    
    // If no valid cart items found, redirect back
    if (empty($cartItems)) {
        $_SESSION['error_message'] = "No valid services found in your cart.";
        header("Location: cart.php");
        exit();
    }
    
    // Calculate totals
    $subtotal = 0;
    foreach ($cartItems as $item) {
        $subtotal += $item['subtotal'];
    }
    
    $taxRate = 0.10; // 10% tax
    $tax = $subtotal * $taxRate;
    $total = $subtotal + $tax;
    
    // If this is the first step, show booking form
    if (!isset($_POST['booking_date'])) {
        // We'll directly output the HTML without including header.php
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Complete Booking - EVENTO</title>
    
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
                        <?php 
                        $cartCount = 0;
                        foreach ($_SESSION['cart'] as $item) {
                            $cartCount += $item['quantity'];
                        }
                        if ($cartCount > 0): 
                        ?>
                            <span class="absolute -top-2 -right-2 bg-red-500 text-white text-xs w-5 h-5 flex items-center justify-center rounded-full">
                                <?php echo $cartCount; ?>
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
                <a href="cart.php" class="text-primary-600 hover:text-primary-700">Shopping Cart</a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Complete Booking</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Complete Your Booking</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                    <div class="p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">Booking Details</h2>
                        
                        <form action="book_cart.php" method="POST" id="booking-form">
                            <input type="hidden" name="book_all" value="1">
                            
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                                <div>
                                    <label for="booking_date" class="block text-sm font-medium text-gray-700 mb-1">Event Date</label>
                                    <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           id="booking_date" name="booking_date" required>
                                </div>
                                <div>
                                    <label for="booking_time" class="block text-sm font-medium text-gray-700 mb-1">Event Time</label>
                                    <input type="time" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                           id="booking_time" name="booking_time" required>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label for="event_address" class="block text-sm font-medium text-gray-700 mb-1">Event Address</label>
                                <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                          id="event_address" name="event_address" rows="3" required></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label for="special_requests" class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                                <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                          id="special_requests" name="special_requests" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-4">
                                <label class="block text-sm font-medium text-gray-700 mb-2">Payment Method</label>
                                <div class="space-y-2">
                                    <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="payment_method" value="cash" checked 
                                               class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                        <div>
                                            <span class="block font-medium">Cash on Delivery</span>
                                            <span class="text-sm text-gray-500">Pay when the service is delivered</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="payment_method" value="card" 
                                               class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                        <div>
                                            <span class="block font-medium">Credit/Debit Card</span>
                                            <span class="text-sm text-gray-500">Secure online payment</span>
                                        </div>
                                    </label>
                                    <label class="flex items-center p-3 border rounded-lg hover:bg-gray-50 cursor-pointer">
                                        <input type="radio" name="payment_method" value="bank" 
                                               class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                        <div>
                                            <span class="block font-medium">Bank Transfer</span>
                                            <span class="text-sm text-gray-500">Pay directly to our bank account</span>
                                        </div>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="w-full py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                                Confirm Booking
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Order Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Order Summary</h2>
                    
                    <div class="max-h-64 overflow-y-auto mb-4">
                        <?php foreach ($cartItems as $item): ?>
                            <div class="flex justify-between items-center mb-3 pb-3 border-b">
                                <div>
                                    <p class="font-medium"><?php echo htmlspecialchars($item['name']); ?></p>
                                    <p class="text-sm text-gray-500">Qty: <?php echo $item['quantity']; ?></p>
                                </div>
                                <p class="font-medium">$<?php echo number_format($item['subtotal'], 2); ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="space-y-3 border-b border-gray-200 pb-4 mb-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal</span>
                            <span class="text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (10%)</span>
                            <span class="text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                    </div>
                    
                    <div class="flex justify-between font-bold text-lg">
                        <span>Total</span>
                        <span class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <div class="mt-6 text-center text-sm text-gray-500">
                        <p>By confirming your booking, you agree to our</p>
                        <div class="space-x-1">
                            <a href="#" class="text-primary-600 hover:underline">Terms of Service</a>
                            <span>and</span>
                            <a href="#" class="text-primary-600 hover:underline">Privacy Policy</a>
                        </div>
                    </div>
                </div>
                
                <div class="mt-4">
                    <a href="cart.php" class="flex items-center text-primary-600 hover:text-primary-700">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Cart
                    </a>
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
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set minimum date to today
            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0');
            var yyyy = today.getFullYear();
            today = yyyy + '-' + mm + '-' + dd;
            document.getElementById('booking_date').setAttribute('min', today);
        });
    </script>
</body>
</html>

<?php
        exit();
    } else {
        // Process the booking form submission
        $bookingDate = $_POST['booking_date'];
        $bookingTime = $_POST['booking_time'];
        $eventAddress = $_POST['event_address'];
        $specialRequests = $_POST['special_requests'] ?? '';
        $paymentMethod = $_POST['payment_method'];
        
        // Validate date and time
        $bookingDateTime = new DateTime($bookingDate . ' ' . $bookingTime);
        $now = new DateTime();
        
        if ($bookingDateTime < $now) {
            $_SESSION['error_message'] = "Booking date and time must be in the future.";
            header("Location: book_cart.php");
            exit();
        }
        
        try {
            // Start transaction
            $db->beginTransaction();
            
            // Create the main booking record
            $bookingStatus = 'pending';
            $orderDate = date('Y-m-d H:i:s');
            
            $stmt = $db->prepare("INSERT INTO bookings (user_id, booking_date, booking_time, event_address, special_requests, payment_method, status, total_amount, created_at) 
                                  VALUES (:user_id, :booking_date, :booking_time, :event_address, :special_requests, :payment_method, :status, :total_amount, :created_at)");
            
            $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $stmt->bindParam(':booking_date', $bookingDate);
            $stmt->bindParam(':booking_time', $bookingTime);
            $stmt->bindParam(':event_address', $eventAddress);
            $stmt->bindParam(':special_requests', $specialRequests);
            $stmt->bindParam(':payment_method', $paymentMethod);
            $stmt->bindParam(':status', $bookingStatus);
            $stmt->bindParam(':total_amount', $total);
            $stmt->bindParam(':created_at', $orderDate);
            
            $stmt->execute();
            $bookingId = $db->lastInsertId();
            
            // Insert booking details for each service
            foreach ($cartItems as $item) {
                $stmt = $db->prepare("INSERT INTO booking_details (booking_id, service_id, quantity, price, subtotal) 
                                      VALUES (:booking_id, :service_id, :quantity, :price, :subtotal)");
                
                $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
                $stmt->bindParam(':service_id', $item['service_id'], PDO::PARAM_INT);
                $stmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
                $stmt->bindParam(':price', $item['price']);
                $stmt->bindParam(':subtotal', $item['subtotal']);
                
                $stmt->execute();
            }
            
            // Create invoice record
            $invoiceNumber = 'INV-' . date('Ymd') . '-' . $bookingId;
            $invoiceStatus = 'unpaid';
            
            $stmt = $db->prepare("INSERT INTO invoices (booking_id, invoice_number, amount, status, due_date, created_at) 
                                  VALUES (:booking_id, :invoice_number, :amount, :status, DATE_ADD(:created_at, INTERVAL 7 DAY), :created_at)");
            
            $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $stmt->bindParam(':invoice_number', $invoiceNumber);
            $stmt->bindParam(':amount', $total);
            $stmt->bindParam(':status', $invoiceStatus);
            $stmt->bindParam(':created_at', $orderDate);
            
            $stmt->execute();
            
            // Commit transaction
            $db->commit();
            
            // Clear the cart
            $_SESSION['cart'] = [];
            
            // Set success message and redirect to booking details page
            $_SESSION['success_message'] = "Your booking has been successfully placed! Booking ID: " . $bookingId;
            header("Location: booking_success.php?id=" . $bookingId);
            exit();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $db->rollback();
            
            $_SESSION['error_message'] = "Error processing your booking: " . $e->getMessage();
            header("Location: cart.php");
            exit();
        }
    }
} else {
    // If accessed directly without POST, redirect to cart
    header("Location: cart.php");
    exit();
}
?>