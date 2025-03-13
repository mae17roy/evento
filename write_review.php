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
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    header("Location: bookings.php");
    exit();
}

$bookingId = (int)$_GET['booking_id'];

// Verify the booking belongs to the current user and is eligible for review
$verifyStmt = $db->prepare("
    SELECT b.id, b.status
    FROM bookings b
    WHERE b.id = :booking_id AND b.user_id = :user_id AND b.status = 'completed'
");
$verifyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$verifyStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$verifyStmt->execute();
$booking = $verifyStmt->fetch(PDO::FETCH_ASSOC);

// If booking not found, not completed, or doesn't belong to user, redirect
if (!$booking) {
    header("Location: bookings.php");
    exit();
}

// Check if user has already reviewed this booking
$reviewCheckStmt = $db->prepare("
    SELECT COUNT(*) as review_count
    FROM reviews
    WHERE booking_id = :booking_id AND user_id = :user_id
");
$reviewCheckStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$reviewCheckStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$reviewCheckStmt->execute();
$reviewCheck = $reviewCheckStmt->fetch(PDO::FETCH_ASSOC);

if ($reviewCheck['review_count'] > 0) {
    header("Location: bookings.php");
    exit();
}

// Get booking items
$itemsStmt = $db->prepare("
    SELECT bi.*, s.name as service_name, s.image, s.id as service_id
    FROM booking_items bi
    JOIN services s ON bi.service_id = s.id
    WHERE bi.booking_id = :booking_id
");
$itemsStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$itemsStmt->execute();
$bookingItems = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$success = false;
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate input
    $serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
    $rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
    $comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';
    
    // Check if service ID is valid
    $validService = false;
    foreach ($bookingItems as $item) {
        if ($item['service_id'] == $serviceId) {
            $validService = true;
            break;
        }
    }
    
    if (!$validService) {
        $error = "Invalid service selected.";
    } elseif ($rating < 1 || $rating > 5) {
        $error = "Please select a rating between 1 and 5 stars.";
    } elseif (empty($comment)) {
        $error = "Please provide a review comment.";
    } else {
        try {
            // Insert review
            $reviewStmt = $db->prepare("
                INSERT INTO reviews (
                    user_id,
                    service_id,
                    booking_id,
                    rating,
                    comment,
                    created_at
                ) VALUES (
                    :user_id,
                    :service_id,
                    :booking_id,
                    :rating,
                    :comment,
                    NOW()
                )
            ");
            
            $reviewStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
            $reviewStmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
            $reviewStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
            $reviewStmt->bindParam(':rating', $rating, PDO::PARAM_INT);
            $reviewStmt->bindParam(':comment', $comment, PDO::PARAM_STR);
            $reviewStmt->execute();
            
            $success = true;
        } catch (Exception $e) {
            $error = "An error occurred while submitting your review. Please try again.";
            error_log("Review submission error: " . $e->getMessage());
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Write a Review - EVENTO</title>
    
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
                <a href="booking_details.php?id=<?php echo $bookingId; ?>" class="text-primary-600 hover:text-primary-700">Booking #<?php echo $bookingId; ?></a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Write Review</span>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-2xl mx-auto">
            <?php if ($success): ?>
                <!-- Success Message -->
                <div class="bg-white rounded-lg shadow-md p-8 text-center">
                    <div class="text-5xl text-green-500 mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <h1 class="text-2xl font-bold text-gray-800 mb-2">Review Submitted!</h1>
                    <p class="text-gray-600 mb-6">Thank you for sharing your feedback. Your review helps other customers make informed decisions.</p>
                    <div class="flex justify-center space-x-4">
                        <a href="booking_details.php?id=<?php echo $bookingId; ?>" class="px-6 py-2 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300">
                            Return to Booking
                        </a>
                        <a href="bookings.php" class="px-6 py-2 border border-gray-300 text-gray-700 font-semibold rounded-lg hover:bg-gray-50 transition duration-300">
                            View All Bookings
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <!-- Review Form -->
                <div class="bg-white rounded-lg shadow-md overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200">
                        <h1 class="text-xl font-semibold text-gray-800">Write a Review</h1>
                    </div>
                    
                    <div class="p-6">
                        <?php if (!empty($error)): ?>
                            <div class="mb-6 p-4 bg-red-100 text-red-700 rounded-lg">
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
                            <div class="mb-6">
                                <label for="service_id" class="block text-gray-700 font-medium mb-2">Select Service to Review</label>
                                <select id="service_id" name="service_id" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" required>
                                    <option value="">-- Select a service --</option>
                                    <?php foreach ($bookingItems as $item): ?>
                                        <option value="<?php echo $item['service_id']; ?>">
                                            <?php echo htmlspecialchars($item['service_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-6">
                                <label class="block text-gray-700 font-medium mb-2">Rating</label>
                                <div class="flex items-center space-x-1" id="rating-stars">
                                    <?php for ($i = 1; $i <= 5; $i++): ?>
                                        <button type="button" class="text-3xl text-gray-300 hover:text-yellow-400 focus:outline-none transition-colors star-btn" data-rating="<?php echo $i; ?>">
                                            <i class="fas fa-star"></i>
                                        </button>
                                    <?php endfor; ?>
                                </div>
                                <input type="hidden" name="rating" id="rating-input" value="" required>
                                <div class="text-sm text-gray-500 mt-1">Click to select your rating</div>
                            </div>
                            
                            <div class="mb-6">
                                <label for="comment" class="block text-gray-700 font-medium mb-2">Your Review</label>
                                <textarea id="comment" name="comment" rows="5" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500" required
                                    placeholder="Share your experience with this service..."></textarea>
                            </div>
                            
                            <div class="flex justify-end space-x-4">
                                <a href="booking_details.php?id=<?php echo $bookingId; ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded-lg hover:bg-gray-50 transition duration-300">
                                    Cancel
                                </a>
                                <button type="submit" class="px-4 py-2 bg-primary-600 text-white rounded-lg hover:bg-primary-700 transition duration-300">
                                    Submit Review
                                </button>
                            </div>
                        </form>
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
    
    <!-- Rating Star Script -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star-btn');
            const ratingInput = document.getElementById('rating-input');
            
            // Star rating functionality
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    ratingInput.value = rating;
                    
                    // Update star colors
                    stars.forEach((s, index) => {
                        if (index < rating) {
                            s.classList.remove('text-gray-300');
                            s.classList.add('text-yellow-400');
                        } else {
                            s.classList.remove('text-yellow-400');
                            s.classList.add('text-gray-300');
                        }
                    });
                });
            });
        });
    </script>
</body>
</html>