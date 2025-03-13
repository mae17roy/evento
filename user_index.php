<?php
// Start session to manage user state and cart
session_start();

// Include database configuration
require_once 'config.php';

// Check if user is logged in, redirect to index.php if not
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit();
}

$isLoggedIn = true; // User must be logged in to access this page
$userId = $_SESSION['user_id'];

// Count items in the cart (if cart exists in session)
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}

// Get categories for filtering
$categoriesQuery = $db->query("SELECT * FROM categories WHERE owner_id IS NULL OR owner_id = 0 ORDER BY name");
$categories = $categoriesQuery->fetchAll(PDO::FETCH_ASSOC);

// Get services with their details
$servicesQuery = "SELECT * FROM vw_services_with_details WHERE is_available = 1";

// Apply category filter if specified
if (isset($_GET['category']) && !empty($_GET['category'])) {
    $categoryId = (int)$_GET['category'];
    $servicesQuery .= " AND category_id = :categoryId";
}

// Apply price filter if specified
if (isset($_GET['price_range']) && !empty($_GET['price_range'])) {
    $priceRange = explode('-', $_GET['price_range']);
    if (count($priceRange) == 2) {
        $minPrice = (float)$priceRange[0];
        $maxPrice = (float)$priceRange[1];
        $servicesQuery .= " AND price BETWEEN :minPrice AND :maxPrice";
    }
}

// Apply search filter if specified
if (isset($_GET['search']) && !empty($_GET['search'])) {
    $search = $_GET['search'];
    $servicesQuery .= " AND (name LIKE :search OR description LIKE :search)";
}

// Add sorting
$servicesQuery .= " ORDER BY featured DESC, created_at DESC";

// Prepare and execute the query
$stmt = $db->prepare($servicesQuery);

if (isset($_GET['category']) && !empty($_GET['category'])) {
    $stmt->bindParam(':categoryId', $categoryId, PDO::PARAM_INT);
}

if (isset($_GET['price_range']) && !empty($_GET['price_range'])) {
    $priceRange = explode('-', $_GET['price_range']);
    if (count($priceRange) == 2) {
        $stmt->bindParam(':minPrice', $minPrice, PDO::PARAM_STR);
        $stmt->bindParam(':maxPrice', $maxPrice, PDO::PARAM_STR);
    }
}

if (isset($_GET['search']) && !empty($_GET['search'])) {
    $searchParam = "%$search%";
    $stmt->bindParam(':search', $searchParam, PDO::PARAM_STR);
}

$stmt->execute();
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Event Management System</title>
    
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
                
                <div class="hidden md:block flex-grow mx-10">
                    <form class="relative max-w-md mx-auto" id="search-form">
                        <div class="flex">
                            <input class="w-full px-4 py-2 rounded-l-lg text-gray-800 focus:outline-none" 
                                type="search" placeholder="Search services..." 
                                aria-label="Search" id="search-input" name="search">
                            <button class="bg-white px-4 rounded-r-lg border-l border-gray-200 text-gray-600 hover:bg-gray-100" type="submit">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                        <div class="search-results hidden absolute z-10 mt-1 w-full bg-white rounded-md shadow-lg" id="search-results"></div>
                    </form>
                </div>
                
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

    <!-- Hero Section -->
    <div class="bg-gradient-to-r from-primary-600 to-primary-800 text-white py-16 relative">
        <div class="absolute inset-0 bg-black opacity-20"></div>
        <div class="container mx-auto px-4 relative z-10 text-center">
            <h1 class="text-4xl md:text-5xl font-bold mb-4">Find the Perfect Services for Your Event</h1>
            <p class="text-xl mb-8 max-w-3xl mx-auto">One-stop platform for all your event planning needs</p>
            <div class="flex justify-center space-x-4">
                <a href="#services" class="px-6 py-3 bg-white text-primary-600 font-semibold rounded-lg hover:bg-gray-100 transition duration-300">
                    Browse Services
                </a>
                <a href="#categories" class="px-6 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-primary-600 transition duration-300">
                    View Categories
                </a>
            </div>
        </div>
    </div>

    <!-- Categories Section -->
    <section class="py-12 bg-white" id="categories">
        <div class="container mx-auto px-4">
            <div class="text-center mb-8">
                <h2 class="text-3xl font-bold mb-2">Browse by Category</h2>
            </div>
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">
                <?php foreach ($categories as $category): ?>
                    <a href="?category=<?php echo $category['id']; ?>" class="group">
                        <div class="bg-white rounded-lg shadow-md p-6 text-center h-full transition-all duration-300 transform group-hover:-translate-y-1 group-hover:shadow-lg">
                            <div class="text-primary-500 mb-3 text-3xl">
                                <i class="fas fa-<?php echo getCategoryIcon($category['name']); ?>"></i>
                            </div>
                            <h5 class="font-semibold text-gray-800"><?php echo htmlspecialchars($category['name']); ?></h5>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Filters Section -->
    <section class="py-4 bg-gray-50 border-t border-b border-gray-200">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <form action="" method="GET" class="flex flex-col md:flex-row space-y-3 md:space-y-0 md:space-x-3 w-full md:w-auto">
                    <select name="category" class="px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?php echo $category['id']; ?>" 
                                <?php echo (isset($_GET['category']) && $_GET['category'] == $category['id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <select name="price_range" class="px-4 py-2 rounded-lg border border-gray-300 focus:outline-none focus:ring-2 focus:ring-primary-500">
                        <option value="">All Prices</option>
                        <option value="0-1000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '0-1000') ? 'selected' : ''; ?>>$0 - $1,000</option>
                        <option value="1000-5000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '1000-5000') ? 'selected' : ''; ?>>$1,000 - $5,000</option>
                        <option value="5000-10000" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '5000-10000') ? 'selected' : ''; ?>>$5,000 - $10,000</option>
                        <option value="10000-999999" <?php echo (isset($_GET['price_range']) && $_GET['price_range'] == '10000-999999') ? 'selected' : ''; ?>>$10,000+</option>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                        Filter
                    </button>
                </form>
                <div class="text-gray-500 mt-3 md:mt-0">
                    <span><?php echo count($services); ?> services found</span>
                </div>
            </div>
        </div>
    </section>

    <!-- Services Section -->
    <section class="py-12" id="services">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-8">Available Services</h2>
            <?php if (count($services) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($services as $service): ?>
                        <div class="bg-white rounded-lg shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1">
                            <img src="<?php echo !empty($service['image']) ? 'uploads/services/' . htmlspecialchars($service['image']) : 'assets/img/default-service.jpg'; ?>" 
                                class="w-full h-48 object-cover" 
                                alt="<?php echo htmlspecialchars($service['name']); ?>">
                            <div class="p-6">
                                <h5 class="text-xl font-bold mb-2 text-gray-800"><?php echo htmlspecialchars($service['name']); ?></h5>
                                
                                <!-- Rating stars -->
                                <div class="flex items-center mb-3">
                                    <?php 
                                    $rating = $service['avg_rating'] ? round($service['avg_rating']) : 0;
                                    for ($i = 1; $i <= 5; $i++) {
                                        if ($i <= $rating) {
                                            echo '<i class="fas fa-star text-yellow-400"></i>';
                                        } else {
                                            echo '<i class="far fa-star text-yellow-400"></i>';
                                        }
                                    }
                                    ?>
                                    <span class="text-gray-500 ml-2">(<?php echo $service['review_count'] ? $service['review_count'] : '0'; ?> reviews)</span>
                                </div>
                                
                                <p class="text-gray-600 mb-4 truncate"><?php echo htmlspecialchars($service['description']); ?></p>
                                <p class="text-primary-600 font-bold text-lg mb-4">
                                    $<?php echo number_format($service['price'], 2); ?>
                                </p>
                                <div class="flex justify-between mt-4">
                                    <button class="px-4 py-2 border border-primary-600 text-primary-600 rounded-lg hover:bg-primary-50 transition duration-300 add-to-cart-btn"
                                            data-service-id="<?php echo $service['id']; ?>">
                                        <i class="fas fa-cart-plus mr-2"></i> Add to Cart
                                    </button>
                                    <a href="service_details.php?id=<?php echo $service['id']; ?>" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                                        <i class="fas fa-info-circle mr-2"></i> Details
                                    </a>
                                </div>
                            </div>
                            <div class="px-6 py-3 bg-gray-50 border-t border-gray-100">
                                <div class="flex items-center text-gray-500 text-sm">
                                    <i class="fas fa-user mr-2"></i>
                                    <span>By: <?php echo htmlspecialchars($service['business_name'] ? $service['business_name'] : $service['owner_name']); ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded">
                    <div class="flex">
                        <div class="flex-shrink-0">
                            <i class="fas fa-info-circle text-xl"></i>
                        </div>
                        <div class="ml-3">
                            <p class="text-sm">No services found matching your criteria.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Featured Reviews Section -->
    <section class="py-12 bg-gray-50">
        <div class="container mx-auto px-4">
            <h2 class="text-3xl font-bold text-center mb-8">What Our Customers Say</h2>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php
                // Get recent reviews with service and user details
                $reviewsQuery = $db->query("
                    SELECT r.*, s.name as service_name, u.name as user_name 
                    FROM reviews r 
                    JOIN services s ON r.service_id = s.id 
                    JOIN users u ON r.user_id = u.id 
                    ORDER BY r.created_at DESC 
                    LIMIT 3
                ");
                $reviews = $reviewsQuery->fetchAll(PDO::FETCH_ASSOC);
                
                if (count($reviews) > 0) {
                    foreach ($reviews as $review) {
                ?>
                    <div class="bg-white rounded-lg shadow-md p-6 h-full">
                        <div class="flex justify-between mb-4">
                            <div>
                                <h5 class="text-lg font-bold"><?php echo htmlspecialchars($review['user_name']); ?></h5>
                                <p class="text-gray-500"><?php echo htmlspecialchars($review['service_name']); ?></p>
                            </div>
                            <div class="flex">
                                <?php 
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $review['rating']) {
                                        echo '<i class="fas fa-star text-yellow-400"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-yellow-400"></i>';
                                    }
                                }
                                ?>
                            </div>
                        </div>
                        <p class="text-gray-600 mb-4"><?php echo htmlspecialchars($review['comment']); ?></p>
                        <p class="text-gray-400 text-sm">
                            <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                        </p>
                    </div>
                <?php 
                    }
                } else {
                ?>
                    <div class="col-span-3">
                        <div class="bg-blue-50 border-l-4 border-blue-500 text-blue-700 p-4 rounded">
                            <p>No reviews available yet.</p>
                        </div>
                    </div>
                <?php } ?>
            </div>
        </div>
    </section>

    <!-- Booking Modal -->
    <div id="bookingModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
            <div class="border-b px-6 py-4 flex items-center justify-between">
                <h5 class="text-lg font-bold">Book Service</h5>
                <button id="closeBookingModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="booking-form">
                    <input type="hidden" id="service-id" name="service_id">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label for="booking-date" class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                            <input type="date" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                   id="booking-date" name="booking_date" required>
                        </div>
                        <div>
                            <label for="booking-time" class="block text-sm font-medium text-gray-700 mb-1">Time</label>
                            <input type="time" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                   id="booking-time" name="booking_time" required>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="special-requests" class="block text-sm font-medium text-gray-700 mb-1">Special Requests</label>
                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                  id="special-requests" name="special_requests" rows="3"></textarea>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Payment Method</label>
                        <div class="space-y-2">
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="cash" checked 
                                       class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                <span>Cash on Delivery</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="payment_method" value="card" 
                                       class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                <span>Credit/Debit Card</span>
                            </label>
                        </div>
                    </div>
                </form>
            </div>
            <div class="border-t px-6 py-4 flex justify-end space-x-3">
                <button id="cancelBookingBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-300">
                    Close
                </button>
                <button id="submit-booking" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                    Book Now
                </button>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-10 mt-auto">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
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
                    <h5 class="text-lg font-bold mb-4">Categories</h5>
                    <ul class="space-y-2">
                        <?php
                        // Display first 4 categories in footer
                        $footerCategories = array_slice($categories, 0, 4);
                        foreach ($footerCategories as $category) {
                            echo '<li><a href="?category=' . $category['id'] . '" class="text-gray-400 hover:text-white transition duration-300">' . htmlspecialchars($category['name']) . '</a></li>';
                        }
                        ?>
                    </ul>
                </div>
                <div>
                    <h5 class="text-lg font-bold mb-4">Follow Us</h5>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white transition duration-300"><i class="fab fa-linkedin-in"></i></a>
                    </div>
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
            // AJAX Search
            $('#search-input').on('keyup', function() {
                var query = $(this).val();
                if (query.length > 2) {
                    $.ajax({
                        url: 'ajax_search.php',
                        method: 'POST',
                        data: { query: query },
                        success: function(data) {
                            $('#search-results').html(data);
                            $('#search-results').removeClass('hidden');
                        }
                    });
                } else {
                    $('#search-results').addClass('hidden');
                }
            });

            // Hide search results when clicking outside
            $(document).on('click', function(e) {
                if (!$(e.target).closest('#search-input, #search-results').length) {
                    $('#search-results').addClass('hidden');
                }
            });

            // Add to Cart with AJAX
            $('.add-to-cart-btn').on('click', function() {
                var serviceId = $(this).data('service-id');
                
                $.ajax({
                    url: 'add_to_cart.php',
                    method: 'POST',
                    data: { service_id: serviceId, quantity: 1 },
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            // Reload page to update cart count
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error adding to cart. Please try again.');
                    }
                });
            });

            // Booking Modal Controls
            $('.book-now-btn').on('click', function() {
                var serviceId = $(this).data('service-id');
                $('#service-id').val(serviceId);
                
                // Set minimum date to today
                var today = new Date();
                var dd = String(today.getDate()).padStart(2, '0');
                var mm = String(today.getMonth() + 1).padStart(2, '0');
                var yyyy = today.getFullYear();
                today = yyyy + '-' + mm + '-' + dd;
                $('#booking-date').attr('min', today);
                
                $('#bookingModal').removeClass('hidden');
            });
            
            $('#closeBookingModal, #cancelBookingBtn').on('click', function() {
                $('#bookingModal').addClass('hidden');
            });
            
            // Submit booking form
            $('#submit-booking').on('click', function() {
                if (!$('#booking-form')[0].checkValidity()) {
                    $('#booking-form')[0].reportValidity();
                    return;
                }
                
                var formData = $('#booking-form').serialize();
                
                $.ajax({
                    url: 'process_booking.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            $('#bookingModal').addClass('hidden');
                            alert('Booking successful! Your booking ID is: ' + response.booking_id);
                            
                            // Redirect to bookings page
                            window.location.href = 'bookings.php';
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error processing booking. Please try again.');
                    }
                });
            });
        });
    </script>

    <?php
    // Helper function to get category icon class based on category name
    function getCategoryIcon($categoryName) {
        $icons = [
            'Catering' => 'utensils',
            'Decoration' => 'paint-brush',
            'Photo Studio' => 'camera',
            'Sound System' => 'music',
            'Souvenir' => 'gift'
        ];
        
        return isset($icons[$categoryName]) ? $icons[$categoryName] : 'tag';
    }
    ?>
</body>
</html>