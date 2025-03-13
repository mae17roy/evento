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

// Check if service_id is provided
if (!isset($_GET['service_id']) || empty($_GET['service_id'])) {
    header("Location: user_index.php");
    exit();
}

$serviceId = (int)$_GET['service_id'];

// Get service details
try {
    $serviceStmt = $db->prepare("
        SELECT s.*, c.name as category_name, u.name as provider_name, u.business_name 
        FROM services s
        LEFT JOIN categories c ON s.category_id = c.id
        LEFT JOIN users u ON s.owner_id = u.id
        WHERE s.id = :service_id AND s.is_available = 1
    ");
    $serviceStmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
    $serviceStmt->execute();
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        header("Location: user_index.php");
        exit();
    }
} catch (Exception $e) {
    error_log("Error fetching service: " . $e->getMessage());
    header("Location: user_index.php");
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

// Initialize quantity (default to 1)
$quantity = isset($_GET['quantity']) ? (int)$_GET['quantity'] : 1;
if ($quantity < 1) $quantity = 1;

// Calculate totals
$subtotal = $service['price'] * $quantity;
$taxRate = 0.10; // 10% tax
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

// Handle booking submission via AJAX in place_booking.php
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book Now - <?php echo htmlspecialchars($service['name']); ?> - EVENTO</title>
    
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
                <a href="service_details.php?id=<?php echo $serviceId; ?>" class="text-primary-600 hover:text-primary-700">
                    <?php echo htmlspecialchars($service['name']); ?>
                </a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Book Now</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">Book Service</h1>
        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
            <!-- Booking Form -->
            <div class="lg:col-span-2">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Booking Details</h2>
                    
                    <div id="booking-error-message" class="hidden bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <i class="fas fa-exclamation-circle"></i>
                            </div>
                            <div class="ml-3" id="error-text">
                                <p></p>
                            </div>
                        </div>
                    </div>
                    
                    <form id="booking-form">
                        <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                        
                        <!-- Booking Date and Time -->
                        <div class="mb-6">
                            <h3 class="text-gray-700 font-medium mb-3">When would you like to book this service?</h3>
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
                        
                        <!-- Quantity -->
                        <div class="mb-6">
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-1">Quantity *</label>
                            <div class="flex items-center">
                                <button type="button" id="decrease-quantity" class="px-3 py-1 border border-gray-300 rounded-l-lg text-gray-600 hover:bg-gray-100">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="quantity" name="quantity" 
                                       class="w-20 text-center py-1 border-t border-b border-gray-300 focus:outline-none"
                                       value="<?php echo $quantity; ?>" min="1" max="10">
                                <button type="button" id="increase-quantity" class="px-3 py-1 border border-gray-300 rounded-r-lg text-gray-600 hover:bg-gray-100">
                                    <i class="fas fa-plus"></i>
                                </button>
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
                            <button type="button" id="place-booking-btn" class="w-full py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                                Complete Booking
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Service Summary -->
            <div class="lg:col-span-1">
                <div class="bg-white rounded-lg shadow-md p-6 sticky top-24">
                    <h2 class="text-lg font-bold text-gray-800 mb-4">Service Summary</h2>
                    
                    <!-- Service Details -->
                    <div class="flex items-start mb-4">
                        <img src="<?php echo !empty($service['image']) ? 'uploads/services/' . htmlspecialchars($service['image']) : 'assets/img/default-service.jpg'; ?>" 
                             class="w-20 h-20 object-cover rounded mr-3 flex-shrink-0" 
                             alt="<?php echo htmlspecialchars($service['name']); ?>">
                        <div>
                            <h3 class="font-medium text-gray-800"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($service['category_name']); ?></p>
                            <p class="text-sm text-gray-500 mt-1">
                                <i class="fas fa-user mr-1"></i> 
                                <?php echo htmlspecialchars($service['business_name'] ? $service['business_name'] : $service['provider_name']); ?>
                            </p>
                        </div>
                    </div>
                    
                    <!-- Price Breakdown -->
                    <div class="space-y-3 border-t border-b border-gray-200 py-4 my-4">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Price:</span>
                            <span class="text-gray-800">$<?php echo number_format($service['price'], 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Quantity:</span>
                            <span class="text-gray-800" id="summary-quantity"><?php echo $quantity; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Subtotal:</span>
                            <span class="text-gray-800" id="summary-subtotal">$<?php echo number_format($subtotal, 2); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Tax (10%):</span>
                            <span class="text-gray-800" id="summary-tax">$<?php echo number_format($tax, 2); ?></span>
                        </div>
                    </div>
                    
                    <!-- Total -->
                    <div class="flex justify-between font-bold text-lg mb-6">
                        <span>Total:</span>
                        <span class="text-primary-600" id="summary-total">$<?php echo number_format($total, 2); ?></span>
                    </div>
                    
                    <div class="text-xs text-gray-500 mb-4">
                        <p>By placing your booking, you agree to EVENTO's terms and conditions.</p>
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
    
    <!-- jQuery (needed for AJAX) -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Booking Processing Script -->
    <script>
        $(document).ready(function() {
            // Price details
            const servicePrice = <?php echo $service['price']; ?>;
            const taxRate = 0.10; // 10%
            
            // Quantity controls
            $('#decrease-quantity').on('click', function() {
                let currentQty = parseInt($('#quantity').val());
                if (currentQty > 1) {
                    $('#quantity').val(currentQty - 1);
                    updatePrices();
                }
            });
            
            $('#increase-quantity').on('click', function() {
                let currentQty = parseInt($('#quantity').val());
                if (currentQty < 10) {
                    $('#quantity').val(currentQty + 1);
                    updatePrices();
                }
            });
            
            $('#quantity').on('change', function() {
                updatePrices();
            });
            
            function updatePrices() {
                let quantity = parseInt($('#quantity').val());
                if (isNaN(quantity) || quantity < 1) {
                    quantity = 1;
                    $('#quantity').val(1);
                }
                if (quantity > 10) {
                    quantity = 10;
                    $('#quantity').val(10);
                }
                
                let subtotal = servicePrice * quantity;
                let tax = subtotal * taxRate;
                let total = subtotal + tax;
                
                $('#summary-quantity').text(quantity);
                $('#summary-subtotal').text('$' + subtotal.toFixed(2));
                $('#summary-tax').text('$' + tax.toFixed(2));
                $('#summary-total').text('$' + total.toFixed(2));
            }
            
            // Handle booking submission
            $('#place-booking-btn').on('click', function() {
                // Validate form
                if (!$('#booking-form')[0].checkValidity()) {
                    $('#booking-form')[0].reportValidity();
                    return;
                }
                
                // Disable button to prevent double submission
                $(this).prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-2"></i> Processing...');
                
                // Hide previous error message if any
                $('#booking-error-message').addClass('hidden');
                
                // Get form data
                var formData = $('#booking-form').serialize();
                
                // Submit booking via AJAX
                $.ajax({
                    url: 'place_booking.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Redirect to booking confirmation page
                            window.location.href = response.redirect;
                        } else {
                            // Show error message
                            $('#error-text').text(response.message);
                            $('#booking-error-message').removeClass('hidden');
                            
                            // Re-enable the button
                            $('#place-booking-btn').prop('disabled', false).html('Complete Booking');
                        }
                    },
                    error: function() {
                        // Show error message
                        $('#error-text').text('An error occurred while processing your booking. Please try again.');
                        $('#booking-error-message').removeClass('hidden');
                        
                        // Re-enable the button
                        $('#place-booking-btn').prop('disabled', false).html('Complete Booking');
                    }
                });
            });
        });
    </script>
</body>
</html>