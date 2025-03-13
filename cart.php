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

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate cart items count
$cartCount = 0;
$subtotal = 0;

foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
    $subtotal += $item['price'] * $item['quantity'];
}

// Get service details for each item in cart
$cartItems = [];
if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $index => $item) {
        // Get the latest service information from the database
        $stmt = $db->prepare("SELECT id, name, price, image FROM services WHERE id = :id AND is_available = 1");
        $stmt->bindParam(':id', $item['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            // Use the current price from the database (it might have changed)
            $cartItems[] = [
                'index' => $index,
                'service_id' => $item['service_id'],
                'name' => $service['name'],
                'price' => $service['price'],
                'image' => $service['image'],
                'quantity' => $item['quantity'],
                'subtotal' => $service['price'] * $item['quantity']
            ];
        }
    }
}

// Calculate tax (let's assume a 10% tax rate)
$taxRate = 0.10;
$tax = $subtotal * $taxRate;

// Calculate total
$total = $subtotal + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - EVENTO</title>
    
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
                        <?php if ($cartCount > 0): ?>
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
                <span class="text-gray-500">Shopping Cart</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Shopping Cart</h1>
        
        <?php if (count($cartItems) > 0): ?>
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
                <!-- Cart Items -->
                <div class="lg:col-span-2">
                    <div class="bg-white rounded-lg shadow-md overflow-hidden mb-6">
                        <table class="w-full">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="py-4 px-6 text-left text-sm font-medium text-gray-500">Service</th>
                                    <th class="py-4 px-6 text-center text-sm font-medium text-gray-500">Price</th>
                                    <th class="py-4 px-6 text-center text-sm font-medium text-gray-500">Quantity</th>
                                    <th class="py-4 px-6 text-right text-sm font-medium text-gray-500">Subtotal</th>
                                    <th class="py-4 px-6 text-center text-sm font-medium text-gray-500">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($cartItems as $item): ?>
                                    <tr class="hover:bg-gray-50">
                                        <td class="py-4 px-6">
                                            <div class="flex items-center">
                                                <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($item['name']); ?>" 
                                                     class="w-16 h-16 object-cover rounded">
                                                <div class="ml-4">
                                                    <h3 class="text-sm font-medium text-gray-800"><?php echo htmlspecialchars($item['name']); ?></h3>
                                                    <p class="text-xs text-gray-500">Service ID: <?php echo $item['service_id']; ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-center text-gray-800">
                                            $<?php echo number_format($item['price'], 2); ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <div class="flex items-center justify-center">
                                                <button data-index="<?php echo $item['index']; ?>" class="decrement-btn px-2 py-1 border border-gray-300 rounded-l-md text-gray-600 hover:bg-gray-100">
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" value="<?php echo $item['quantity']; ?>" 
                                                       class="quantity-input w-12 text-center py-1 border-t border-b border-gray-300 focus:outline-none"
                                                       data-index="<?php echo $item['index']; ?>"
                                                       min="1" max="99">
                                                <button data-index="<?php echo $item['index']; ?>" class="increment-btn px-2 py-1 border border-gray-300 rounded-r-md text-gray-600 hover:bg-gray-100">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </td>
                                        <td class="py-4 px-6 text-right text-gray-800 font-medium">
                                            $<?php echo number_format($item['subtotal'], 2); ?>
                                        </td>
                                        <td class="py-4 px-6 text-center">
                                            <button data-index="<?php echo $item['index']; ?>" class="remove-btn text-red-500 hover:text-red-700">
                                                <i class="fas fa-trash-alt"></i>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="flex justify-between items-center">
                        <a href="user_index.php" class="flex items-center text-primary-600 hover:text-primary-700">
                            <i class="fas fa-arrow-left mr-2"></i> Continue Shopping
                        </a>
                        <button id="update-cart-btn" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                            Update Cart
                        </button>
                    </div>
                </div>
                
                <!-- Order Summary -->
                <div class="lg:col-span-1">
                    <div class="bg-white rounded-lg shadow-md p-6">
                        <h2 class="text-lg font-bold text-gray-800 mb-4">Order Summary</h2>
                        <div class="space-y-3 border-b border-gray-200 pb-4 mb-4">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal</span>
                                <span id="cart-subtotal" class="text-gray-800">$<?php echo number_format($subtotal, 2); ?></span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Tax (10%)</span>
                                <span id="cart-tax" class="text-gray-800">$<?php echo number_format($tax, 2); ?></span>
                            </div>
                        </div>
                        <div class="flex justify-between font-bold text-lg">
                            <span>Total</span>
                            <span id="cart-total" class="text-primary-600">$<?php echo number_format($total, 2); ?></span>
                        </div>
                        
                        <div class="mt-6 space-y-3">
                            <form action="book_cart.php" method="POST">
                                <input type="hidden" name="book_all" value="1">
                                <button type="submit" class="w-full py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300 inline-block text-center">
                                    <i class="fas fa-calendar-check mr-2"></i>Proceed to Booking
                                </button>
                            </form>
                        </div>
                        
                        <!-- Services Summary -->
                        <div class="mt-6">
                            <h3 class="text-md font-semibold text-gray-700 mb-2">Services in Cart</h3>
                            <div class="space-y-3 max-h-64 overflow-y-auto">
                                <?php foreach ($cartItems as $item): ?>
                                    <div class="flex items-center border-b border-gray-100 pb-2">
                                        <img src="<?php echo !empty($item['image']) ? 'uploads/services/' . htmlspecialchars($item['image']) : 'assets/img/default-service.jpg'; ?>" 
                                             class="w-10 h-10 object-cover rounded mr-3">
                                        <div class="flex-1">
                                            <p class="text-sm font-medium text-gray-800 truncate"><?php echo htmlspecialchars($item['name']); ?></p>
                                            <p class="text-xs text-gray-500"><?php echo $item['quantity']; ?> x $<?php echo number_format($item['price'], 2); ?></p>
                                        </div>
                                        <span class="text-primary-600 font-medium text-sm">$<?php echo number_format($item['subtotal'], 2); ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center text-sm text-gray-500">
                            <p>Secure checkout with</p>
                            <div class="flex justify-center space-x-4 mt-2">
                                <i class="fab fa-cc-visa text-blue-800 text-2xl"></i>
                                <i class="fab fa-cc-mastercard text-red-500 text-2xl"></i>
                                <i class="fab fa-cc-paypal text-blue-600 text-2xl"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Empty Cart -->
            <div class="bg-white rounded-lg shadow-md p-8 text-center">
                <div class="text-6xl text-gray-300 mb-4">
                    <i class="fas fa-shopping-cart"></i>
                </div>
                <h2 class="text-2xl font-bold text-gray-800 mb-2">Your cart is empty</h2>
                <p class="text-gray-600 mb-6">Looks like you haven't added any services to your cart yet.</p>
                <a href="user_index.php" class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300 inline-block">
                    Start Shopping
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Success Toast Message -->
    <div id="toast-message" class="fixed bottom-4 right-4 bg-white shadow-lg rounded-lg p-4 hidden max-w-xs z-50">
        <!-- Toast content will be inserted here dynamically -->
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
    
    <!-- jQuery (needed for AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Custom JavaScript -->
    <script>
        $(document).ready(function() {
            // Increment quantity
            $('.increment-btn').on('click', function() {
                var index = $(this).data('index');
                var input = $(this).siblings('.quantity-input');
                var currentValue = parseInt(input.val());
                
                if (currentValue < 10) {
                    input.val(currentValue + 1);
                } else {
                    showToast('<div class="flex items-center"><i class="fas fa-exclamation-circle text-yellow-500 mr-2"></i><div><p class="font-medium">Maximum quantity reached</p><p class="text-sm">You can\'t add more than 10 of this item</p></div></div>');
                }
            });
            
            // Decrement quantity
            $('.decrement-btn').on('click', function() {
                var index = $(this).data('index');
                var input = $(this).siblings('.quantity-input');
                var currentValue = parseInt(input.val());
                
                if (currentValue > 1) {
                    input.val(currentValue - 1);
                }
            });
            
            // Update cart
            $('#update-cart-btn').on('click', function() {
                var updates = [];
                
                // Collect all quantity updates
                $('.quantity-input').each(function() {
                    updates.push({
                        index: $(this).data('index'),
                        quantity: parseInt($(this).val())
                    });
                });
                
                // Send AJAX request to update cart
                $.ajax({
                    url: 'update_cart.php',
                    method: 'POST',
                    data: { updates: updates },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Show success message
                            showToast('<div class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i><div><p class="font-medium">Cart Updated</p><p class="text-sm">Your cart has been updated successfully</p></div></div>');
                            
                            // Reload page to reflect updated cart
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('<div class="flex items-center"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i><div><p class="font-medium">Error</p><p class="text-sm">' + response.message + '</p></div></div>');
                        }
                    },
                    error: function() {
                        showToast('<div class="flex items-center"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i><div><p class="font-medium">Error</p><p class="text-sm">Error updating cart. Please try again.</p></div></div>');
                    }
                });
            });
            
            // Remove item from cart
            $('.remove-btn').on('click', function() {
                var index = $(this).data('index');
                
                $.ajax({
                    url: 'remove_from_cart.php',
                    method: 'POST',
                    data: { index: index },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Show success message
                            showToast('<div class="flex items-center"><i class="fas fa-check-circle text-green-500 mr-2"></i><div><p class="font-medium">Item Removed</p><p class="text-sm">The item has been removed from your cart</p></div></div>');
                            
                            // Reload page to reflect updated cart
                            setTimeout(function() {
                                location.reload();
                            }, 1500);
                        } else {
                            showToast('<div class="flex items-center"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i><div><p class="font-medium">Error</p><p class="text-sm">' + response.message + '</p></div></div>');
                        }
                    },
                    error: function() {
                        showToast('<div class="flex items-center"><i class="fas fa-exclamation-circle text-red-500 mr-2"></i><div><p class="font-medium">Error</p><p class="text-sm">Error removing item. Please try again.</p></div></div>');
                    }
                });
            });
            
            // Show toast message function
            function showToast(message) {
                var toast = $('#toast-message');
                toast.html(message);
                toast.removeClass('hidden');
                
                // Hide toast after 3 seconds
                setTimeout(function() {
                    toast.addClass('hidden');
                }, 3000);
            }
        });
    </script>
</body>
</html>