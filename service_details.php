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

// Check if service ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: user_index.php');
    exit;
}

$serviceId = (int)$_GET['id'];

// Get service details
$serviceQuery = $db->prepare("
    SELECT * FROM vw_services_with_details
    WHERE id = :id AND is_available = 1
");
$serviceQuery->bindParam(':id', $serviceId, PDO::PARAM_INT);
$serviceQuery->execute();
$service = $serviceQuery->fetch(PDO::FETCH_ASSOC);

// Redirect if service not found
if (!$service) {
    header('Location: user_index.php');
    exit;
}

$isLoggedIn = true; // User must be logged in to access this page
$userId = $_SESSION['user_id'];

// Get reviews for this service
$reviewsQuery = $db->prepare("
    SELECT r.*, u.name as user_name 
    FROM reviews r 
    JOIN users u ON r.user_id = u.id 
    WHERE r.service_id = :service_id 
    ORDER BY r.created_at DESC
");
$reviewsQuery->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
$reviewsQuery->execute();
$reviews = $reviewsQuery->fetchAll(PDO::FETCH_ASSOC);

// Check if the logged-in user has booked this service
$canReview = false;
if ($isLoggedIn) {
    $bookingQuery = $db->prepare("
        SELECT COUNT(*) as booking_count
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        WHERE b.user_id = :user_id
        AND bi.service_id = :service_id
        AND b.status IN ('confirmed', 'completed')
    ");
    $bookingQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingQuery->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
    $bookingQuery->execute();
    $bookingResult = $bookingQuery->fetch(PDO::FETCH_ASSOC);
    
    $canReview = $bookingResult['booking_count'] > 0;
    
    // Check if user has already reviewed this service
    if ($canReview) {
        $reviewCheckQuery = $db->prepare("
            SELECT COUNT(*) as review_count
            FROM reviews
            WHERE user_id = :user_id
            AND service_id = :service_id
        ");
        $reviewCheckQuery->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $reviewCheckQuery->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
        $reviewCheckQuery->execute();
        $reviewCheckResult = $reviewCheckQuery->fetch(PDO::FETCH_ASSOC);
        
        if ($reviewCheckResult['review_count'] > 0) {
            $canReview = false;
        }
    }
}

// Count items in the cart (if cart exists in session)
$cartCount = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $item) {
        $cartCount += $item['quantity'];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($service['name']); ?> - EVENTO</title>
    
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
                <a href="?category=<?php echo $service['category_id']; ?>" class="text-primary-600 hover:text-primary-700">
                    <?php echo htmlspecialchars($service['category_name']); ?>
                </a>
                <span class="mx-2">/</span>
                <span class="text-gray-500"><?php echo htmlspecialchars($service['name']); ?></span>
            </div>
        </div>
    </div>

    <!-- Service Details Section -->
    <section class="py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                <div>
                    <img src="<?php echo !empty($service['image']) ? 'uploads/services/' . htmlspecialchars($service['image']) : 'assets/img/default-service.jpg'; ?>" 
                         class="w-full h-auto object-cover rounded-lg shadow-md" 
                         alt="<?php echo htmlspecialchars($service['name']); ?>">
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-800 mb-4"><?php echo htmlspecialchars($service['name']); ?></h1>
                    
                    <div class="flex items-center mb-4">
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
                        <span class="ml-2"><?php echo $service['avg_rating'] ? number_format($service['avg_rating'], 1) : '0.0'; ?> out of 5</span>
                        <span class="text-gray-500 ml-2">(<?php echo $service['review_count'] ? $service['review_count'] : '0'; ?> reviews)</span>
                    </div>
                    
                    <div class="text-3xl font-bold text-primary-600 mb-6">
                        $<?php echo number_format($service['price'], 2); ?>
                    </div>
                    
                    <div class="mb-6">
                        <h5 class="text-lg font-semibold text-gray-800 mb-2">Description</h5>
                        <p class="text-gray-600 whitespace-pre-line"><?php echo nl2br(htmlspecialchars($service['description'])); ?></p>
                    </div>
                    
                    <div class="mb-8">
                        <h5 class="text-lg font-semibold text-gray-800 mb-2">Provided by</h5>
                        <p class="flex items-center text-gray-600">
                            <i class="fas fa-user mr-2 text-primary-500"></i>
                            <?php echo htmlspecialchars($service['business_name'] ? $service['business_name'] : $service['owner_name']); ?>
                        </p>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row space-y-3 sm:space-y-0 sm:space-x-4">
                        <button class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300 book-now-btn"
                                data-service-id="<?php echo $service['id']; ?>">
                            <i class="fas fa-calendar-check mr-2"></i>Book Now
                        </button>
                        <button class="px-6 py-3 border border-primary-600 text-primary-600 font-semibold rounded-lg hover:bg-primary-50 transition duration-300 add-to-cart-btn"
                                data-service-id="<?php echo $service['id']; ?>">
                            <i class="fas fa-cart-plus mr-2"></i>Add to Cart
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Reviews Section -->
    <section class="py-12 bg-gray-50">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8">
                <h2 class="text-2xl font-bold text-gray-800">Customer Reviews</h2>
                <?php if ($canReview): ?>
                    <button id="write-review-btn" class="mt-4 md:mt-0 px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                        Write a Review
                    </button>
                <?php endif; ?>
            </div>
            
            <?php if ($canReview): ?>
            <!-- Review Form -->
            <div class="bg-white rounded-lg shadow-md p-6 mb-8 hidden" id="review-form-container">
                <h3 class="text-xl font-semibold mb-4">Write Your Review</h3>
                <form id="review-form">
                    <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Rating</label>
                        <div class="flex flex-wrap gap-3">
                            <?php for ($i = 5; $i >= 1; $i--): ?>
                            <label class="flex items-center">
                                <input type="radio" name="rating" value="<?php echo $i; ?>" <?php echo $i == 5 ? 'checked' : ''; ?> 
                                       class="mr-2 focus:ring-primary-500 h-4 w-4 text-primary-600 border-gray-300">
                                <span><?php echo $i; ?> stars</span>
                            </label>
                            <?php endfor; ?>
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label for="review-comment" class="block text-gray-700 mb-2">Your Review</label>
                        <textarea class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" 
                                  id="review-comment" name="comment" rows="4" required></textarea>
                    </div>
                    
                    <div class="flex space-x-3">
                        <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                            Submit Review
                        </button>
                        <button type="button" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300" id="cancel-review-btn">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>
            <?php endif; ?>
            
            <!-- Reviews List -->
            <?php if (count($reviews) > 0): ?>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <?php foreach ($reviews as $review): ?>
                        <div class="bg-white rounded-lg shadow-md p-6">
                            <div class="flex justify-between items-center mb-4">
                                <h4 class="text-lg font-semibold"><?php echo htmlspecialchars($review['user_name']); ?></h4>
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
                            <p class="text-gray-600 mb-3"><?php echo nl2br(htmlspecialchars($review['comment'])); ?></p>
                            <p class="text-gray-400 text-sm">
                                <?php echo date('F j, Y', strtotime($review['created_at'])); ?>
                            </p>
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
                            <p>No reviews yet. Be the first to review this service!</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Related Services -->
    <section class="py-12">
        <div class="container mx-auto px-4">
            <h2 class="text-2xl font-bold text-gray-800 mb-8">You May Also Like</h2>
            
            <?php
            // Get related services (same category, different service)
            $relatedQuery = $db->prepare("
                SELECT * FROM vw_services_with_details
                WHERE category_id = :category_id
                AND id != :service_id
                AND is_available = 1
                ORDER BY RAND()
                LIMIT 3
            ");
            $relatedQuery->bindParam(':category_id', $service['category_id'], PDO::PARAM_INT);
            $relatedQuery->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
            $relatedQuery->execute();
            $relatedServices = $relatedQuery->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($relatedServices) > 0):
            ?>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <?php foreach ($relatedServices as $relatedService): ?>
                    <div class="bg-white rounded-lg shadow-md overflow-hidden transition-all duration-300 hover:shadow-xl transform hover:-translate-y-1">
                        <img src="<?php echo !empty($relatedService['image']) ? 'uploads/services/' . htmlspecialchars($relatedService['image']) : 'assets/img/default-service.jpg'; ?>" 
                             class="w-full h-48 object-cover" 
                             alt="<?php echo htmlspecialchars($relatedService['name']); ?>">
                        <div class="p-6">
                            <h3 class="text-lg font-bold mb-2 text-gray-800"><?php echo htmlspecialchars($relatedService['name']); ?></h3>
                            
                            <!-- Rating stars -->
                            <div class="flex items-center mb-3">
                                <?php 
                                $rating = $relatedService['avg_rating'] ? round($relatedService['avg_rating']) : 0;
                                for ($i = 1; $i <= 5; $i++) {
                                    if ($i <= $rating) {
                                        echo '<i class="fas fa-star text-yellow-400"></i>';
                                    } else {
                                        echo '<i class="far fa-star text-yellow-400"></i>';
                                    }
                                }
                                ?>
                                <span class="text-gray-500 ml-2">(<?php echo $relatedService['review_count'] ? $relatedService['review_count'] : '0'; ?>)</span>
                            </div>
                            
                            <p class="text-primary-600 font-bold text-lg mb-4">
                                $<?php echo number_format($relatedService['price'], 2); ?>
                            </p>
                            <a href="service_details.php?id=<?php echo $relatedService['id']; ?>" 
                               class="block text-center px-4 py-2 bg-gray-100 text-primary-600 rounded-lg hover:bg-gray-200 transition duration-300">
                                View Details
                            </a>
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
                            <p>No related services found.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </section>

    <!-- Booking Modal -->
    <div id="bookingModal" class="fixed inset-0 z-50 overflow-auto bg-black bg-opacity-50 flex items-center justify-center hidden">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-2xl mx-4">
            <div class="border-b px-6 py-4 flex items-center justify-between">
                <h5 class="text-lg font-bold">Book Service: <?php echo htmlspecialchars($service['name']); ?></h5>
                <button id="closeBookingModal" class="text-gray-400 hover:text-gray-600">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <div class="p-6">
                <form id="booking-form">
                    <input type="hidden" name="service_id" value="<?php echo $serviceId; ?>">
                    
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
                    
                    <div class="bg-blue-50 p-4 rounded-lg mb-4">
                        <div class="flex justify-between mb-2">
                            <span>Service Fee:</span>
                            <span>$<?php echo number_format($service['price'], 2); ?></span>
                        </div>
                        <div class="flex justify-between font-bold">
                            <span>Total:</span>
                            <span>$<?php echo number_format($service['price'], 2); ?></span>
                        </div>
                    </div>
                </form>
            </div>
            <div class="border-t px-6 py-4 flex justify-end space-x-3">
                <button id="cancelBookingBtn" class="px-4 py-2 border border-gray-300 rounded-lg text-gray-700 hover:bg-gray-50 transition duration-300">
                    Close
                </button>
                <button id="submit-booking" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                    Confirm Booking
                </button>
            </div>
        </div>
    </div>

    <!-- Login check is now at the beginning of the file -->

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
            // Set minimum date to today for booking date
            var today = new Date();
            var dd = String(today.getDate()).padStart(2, '0');
            var mm = String(today.getMonth() + 1).padStart(2, '0');
            var yyyy = today.getFullYear();
            today = yyyy + '-' + mm + '-' + dd;
            $('#booking-date').attr('min', today);
            
            // Booking Modal Controls
            $('.book-now-btn').on('click', function() {
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
            
            // Show/hide review form
            $('#write-review-btn').on('click', function() {
                $('#review-form-container').removeClass('hidden');
                $(this).addClass('hidden');
            });
            
            $('#cancel-review-btn').on('click', function() {
                $('#review-form-container').addClass('hidden');
                $('#write-review-btn').removeClass('hidden');
            });
            
            // Submit review form
            $('#review-form').on('submit', function(e) {
                e.preventDefault();
                
                var formData = $(this).serialize();
                
                $.ajax({
                    url: 'process_review.php',
                    method: 'POST',
                    data: formData,
                    dataType: 'json',
                    success: function(response) {
                        if (response.status === 'success') {
                            alert('Thank you for your review!');
                            
                            // Reload page to show the new review
                            location.reload();
                        } else {
                            alert(response.message);
                        }
                    },
                    error: function() {
                        alert('Error submitting review. Please try again.');
                    }
                });
            });
        });
    </script>
</body>
</html>