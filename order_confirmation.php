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

// Verify if order ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: user_index.php");
    exit();
}

$orderId = (int)$_GET['id'];

// Get booking details
try {
    // Get the booking
    $bookingStmt = $db->prepare("
        SELECT b.*, u.name as customer_name, u.email, u.phone
        FROM bookings b
        JOIN users u ON b.user_id = u.id
        WHERE b.id = :order_id AND b.user_id = :user_id
    ");
    $bookingStmt->bindParam(':order_id', $orderId, PDO::PARAM_INT);
    $bookingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingStmt->execute();
    $order = $bookingStmt->fetch(PDO::FETCH_ASSOC);
    
    // If booking not found or doesn't belong to current user, redirect
    if (!$order) {
        header("Location: bookings.php");
        exit();
    }
    
    // Get booking items
    $itemsStmt = $db->prepare("
        SELECT bi.*, s.name as service_name, s.image
        FROM booking_items bi
        JOIN services s ON bi.service_id = s.id
        WHERE bi.booking_id = :booking_id
    ");
    $itemsStmt->bindParam(':booking_id', $orderId, PDO::PARAM_INT);
    $itemsStmt->execute();
    $orderItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Parse special requests to extract payment method and other details
    $specialRequests = $order['special_requests'] ?? '';
    $paymentMethod = '';
    $billingAddress = '';
    $customerNotes = '';
    
    if (preg_match('/Payment Method: (.*?)(?:\n|$)/', $specialRequests, $matches)) {
        $paymentMethod = $matches[1];
    }
    
    if (preg_match('/Billing Address: (.*?)(?:\n|$)/', $specialRequests, $matches)) {
        $billingAddress = $matches[1];
    }
    
    if (preg_match('/Customer Notes: (.*?)(?:\n|$)/s, $specialRequests, $matches)) {
        $customerNotes = $matches[1];
    }
    
} catch (Exception $e) {
    // Log error and redirect
    error_log("Error retrieving order details: " . $e->getMessage());
    header("Location: bookings.php");
    exit();
}

// Calculate totals
$subtotal = 0;
foreach ($orderItems as $item) {
    $subtotal += $item['price'] * $item['quantity'];
}

// Calculate tax (10%)
$taxRate = 0.10;
$tax = $subtotal * $taxRate;

// Calculate total (should match the booking total_amount)
$total = $subtotal + $tax;

// Format order date
$orderDate = date('F j, Y, g:i a', strtotime($order['created_at']));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - EVENTO</title>
    
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
                <span class="text-gray-500">Order #<?php echo $orderId; ?></span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Order Success Message -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-6 text-center">
                <div class="text-5xl text-green-500 mb-4">
                    <i class="fas fa-check-circle"></i>
                </div>
                <h1 class="text-2xl font-bold text-gray-800 mb-2">Order Confirmed!</h1>
                <p class="text-gray-600 mb-2">Thank you for your order. We have received your booking request.</p>
                <p class="text-gray-500 mb-4">A confirmation email has been sent to <?php echo htmlspecialchars($order['email']); ?></p>
                
                <div class="inline-block bg-gray-100 rounded-lg px-4 py-2 mb-6">
                    <div class="flex items-center">
                        <span class="text-gray-600 mr-2">Order Number:</span>
                        <span class="font-bold text-gray-800">#<?php echo $orderId; ?></span>
                    </div>
                </div>
                
                <div class="flex justify-center space-x-4">
                    <a href="bookings.php" class="px-6 py-2 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                        View All Bookings
                    </a>
                    <a href="user_index.php" class="px-6 py-2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition duration-300">
                        Continue Shopping
                    </a>
                </div>
            </div>
            
            <!-- Order Details -->
            <div class="bg-white rounded-lg shadow-md overflow-hidden">
                <div class="border-b border-gray-200 px-6 py-4">
                    <h2 class="text-xl font-bold text-gray-800">Order Details</h2>
                </div>
                
                <div class="p-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-gray-600 font-medium mb-2">Order Information</h3>
                            <table class="w-full text-sm">
                                <tr>
                                    <td class="py-2 text-gray-500">Order Number:</td>
                                    <td class="py-2 font-medium text-gray-800">#<?php echo $orderId; ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Date:</td>
                                    <td class="py-2 text-gray-800"><?php echo $orderDate; ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Status:</td>
                                    <td class="py-2">
                                        <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-yellow-100 text-yellow-800">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Payment Method:</td>
                                    <td class="py-2 text-gray-800"><?php echo htmlspecialchars($paymentMethod); ?></td>
                                </tr>
                            </table>
                        </div>
                        
                        <div>
                            <h3 class="text-gray-600 font-medium mb-2">Customer Information</h3>
                            <table class="w-full text-sm">
                                <tr>
                                    <td class="py-2 text-gray-500">Name:</td>
                                    <td class="py-2 text-gray-800"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Email:</td>
                                    <td class="py-2 text-gray-800"><?php echo htmlspecialchars($order['email']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Phone:</td>
                                    <td class="py-2 text-gray-800"><?php echo htmlspecialchars($order['phone']); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-2 text-gray-500">Address:</td>
                                    <td class="py-2 text-gray-800"><?php echo htmlspecialchars($billingAddress); ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                    
                    <?php if (!empty($customerNotes)): ?>
                    <div class="mb-6">
                        <h3 class="text-gray-600 font-medium mb-2">Special Notes</h3>
                        <div class="bg-gray-50 rounded p-3 text-gray-700">
                            <?php echo nl2br(htmlspecialchars($customerNotes)); ?>
                        </div>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Order Items -->
                    <div class="mb-6">
                        <h3 class="text-gray-600 font-medium mb-3">Ordered Services</h3>
                        <div class="border rounded-lg overflow-hidden">
                            <table class="w-full">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                        <th class="px-4 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Quantity</th>
                                        <th class="px-4 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($orderItems as $item): ?>
                                    <tr>
                                        <td class="px-4 py-4">
                                            <div class="flex items-center">
                                                <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['service_name']); ?>" 
                                                     class="w-12 h-12 object-cover rounded mr-3">
                                                <div>
                                                    <div class="font-medium text-gray-800"><?php echo htmlspecialchars($item['service_name']); ?></div>
                                                    <div class="text-xs text-gray-500">ID: <?php echo $item['service_id']; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="px-4 py-4 text-center text-gray-600">
                                            $<?php echo number_format($item['price'], 2); ?>
                                        </td>
                                        <td class="px-4 py-4 text-center text-gray-600">
                                            <?php echo $item['quantity']; ?>
                                        </td>
                                        <td class="px-4 py-4 text-right text-gray-800 font-medium">
                                            $<?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="max-w-md ml-auto">
                        <div class="space-y-3">
                            <div class="flex justify-between text-gray-600">
                                <span>Subtotal</span>
                                <span>$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-gray-600">
                                <span>Tax (10%)</span>
                                <span>$<?php echo number_format($tax, 2); ?></span>
                            </div>
                            <div class="flex justify-between text-lg font-bold pt-3 border-t">
                                <span>Total</span>
                                <span class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-gray-50 px-6 py-4 text-center">
                    <p class="text-sm text-gray-600">If you have any questions about this order, please contact us at <a href="mailto:support@evento.com" class="text-primary-600 hover:text-primary-700">support@evento.com</a></p>
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