<?php
// Include database configuration and functions
require_once 'config.php';
require_once 'functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Debug flag
$debug = true;

// Check for logout success message
$logout_success = isset($_GET['logout']) && $_GET['logout'] === 'success';

// Check if user is already logged in, redirect to appropriate dashboard
if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
    switch ($_SESSION['user_role']) {
        case 'admin':
            header("Location: admin_index.php");
            exit();
        case 'owner':
            header("Location: owner_index.php");
            exit();
        case 'client':
            header("Location: user_index.php");
            exit();
        default:
            header("Location: user_index.php");
            exit();
    }
}

// Initialize login and registration error messages
$login_error = '';
$register_error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    
    // Validate input
    if (empty($email) || empty($password)) {
        $login_error = "Please enter both email and password.";
    } else {
        // Process login
        try {
            $user = processLogin($db, $email, $password);
            
            if ($user) {
                // Successful login - redirect based on user role
                switch ($_SESSION['user_role']) {
                    case 'admin':
                        header("Location: admin_index.php");
                        exit();
                    case 'owner':
                        header("Location: owner_index.php");
                        exit();
                    case 'client':
                        header("Location: user_index.php");
                        exit();
                    default:
                        header("Location: user_index.php");
                        exit();
                }
            } else {
                $login_error = "Invalid email or password. Please try again.";
            }
        } catch (Exception $e) {
            // Log the error for debugging
            error_log("Login error: " . $e->getMessage());
            $login_error = $debug 
                ? "An error occurred during login: " . $e->getMessage()
                : "An unexpected error occurred. Please try again.";
        }
    }
}

// Handle registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $role = $_POST['role'] ?? 'client';
    $phone = $_POST['phone'] ?? '';
    $address = $_POST['address'] ?? '';
    $business_name = ($role === 'owner') ? ($_POST['business_name'] ?? '') : null;
    
    // Basic validation
    if (empty($name) || empty($email) || empty($password) || empty($confirm_password)) {
        $register_error = "Please fill in all required fields.";
    } elseif ($password !== $confirm_password) {
        $register_error = "Passwords do not match.";
    } else {
        // Attempt registration
        $user_id = registerUser(
            $db, 
            $name, 
            $email, 
            $password, 
            $role, 
            $phone, 
            $address, 
            $business_name
        );
        
        if ($user_id) {
            // Successful registration - log in automatically
            $_SESSION['user_id'] = $user_id;
            $_SESSION['user_name'] = $name;
            $_SESSION['user_email'] = $email;
            $_SESSION['user_role'] = $role;
            
            // Redirect based on role
            switch ($role) {
                case 'admin':
                    header("Location: admin_index.php");
                    exit();
                case 'owner':
                    header("Location: owner_index.php");
                    exit();
                default:
                    header("Location: user_index.php");
                    exit();
            }
        } else {
            // Registration failed
            $register_error = "Registration failed. Email may already be in use.";
        }
    }
}

// Get categories for display
try {
    $categoryStmt = $db->prepare("
        SELECT id, name, description, image FROM global_categories 
        ORDER BY name
    ");
    $categoryStmt->execute();
    $categories = $categoryStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting categories: " . $e->getMessage());
    $categories = []; // Provide empty array as fallback
}

// Get featured providers
try {
    $featuredStmt = $db->prepare("
        SELECT 
            u.id, 
            u.name, 
            u.business_name, 
            COUNT(DISTINCT s.id) as service_count,
            AVG(r.rating) as avg_rating,
            COUNT(DISTINCT b.id) as booking_count
        FROM users u
        JOIN services s ON u.id = s.owner_id
        LEFT JOIN reviews r ON s.id = r.service_id
        LEFT JOIN booking_items bi ON s.id = bi.service_id
        LEFT JOIN bookings b ON bi.booking_id = b.id
        WHERE u.role = 'owner'
        GROUP BY u.id
        ORDER BY avg_rating DESC, service_count DESC
        LIMIT 3
    ");
    $featuredStmt->execute();
    $featuredProviders = $featuredStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Error getting featured providers: " . $e->getMessage());
    $featuredProviders = []; // Provide empty array as fallback
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Event Services Platform</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: relative;
            overflow: hidden;
        }
        .hero-section::before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            bottom: 0;
            left: 0;
            background: url('https://images.unsplash.com/photo-1517457373958-b7bdd4587205?auto=format&fit=crop&w=1500&q=80') center center;
            background-size: cover;
            opacity: 0.2;
        }
        .category-card {
            transition: all 0.3s ease;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
        .provider-card {
            transition: all 0.3s ease;
        }
        .provider-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md p-4">
        <div class="container mx-auto flex justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center">
                <div class="text-purple-600 mr-2">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <span class="text-xl font-bold text-gray-800">EVENTO</span>
            </div>
            
            <!-- Navigation Links -->
            <div class="hidden md:flex space-x-6">
                <a href="#" class="text-gray-800 hover:text-purple-600">Home</a>
                <a href="#about" class="text-gray-800 hover:text-purple-600">About</a>
                <a href="#services" class="text-gray-800 hover:text-purple-600">Services</a>
                <a href="#providers" class="text-gray-800 hover:text-purple-600">Providers</a>
                <a href="#contact" class="text-gray-800 hover:text-purple-600">Contact</a>
            </div>
            
            <!-- Authentication Buttons -->
            <div class="flex space-x-4">
                <button data-modal="login" class="px-4 py-2 border border-purple-600 text-purple-600 rounded-md hover:bg-purple-50 transition duration-300">
                    Login
                </button>
                <button data-modal="register" class="px-4 py-2 bg-purple-600 text-white rounded-md hover:bg-purple-700 transition duration-300">
                    Register
                </button>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <div class="hero-section py-16 md:py-20 relative">
        <div class="container mx-auto px-4 relative z-10">
            <div class="text-center max-w-3xl mx-auto">
                <h1 class="text-4xl md:text-5xl font-bold mb-6 text-white">Your Perfect Event Starts Here</h1>
                <p class="text-xl mb-8 text-white opacity-90">Discover, Book, and Create Unforgettable Moments with EVENTO - Your Comprehensive Event Services Platform</p>
                
                <div class="flex justify-center space-x-4">
                    <button data-modal="login" class="px-6 py-3 bg-white text-purple-600 font-semibold rounded-lg hover:bg-gray-100 transition duration-300">
                        Login
                    </button>
                    <button data-modal="register" class="px-6 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-purple-600 transition duration-300">
                        Register
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- About Section -->
    <div id="about" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">How EVENTO Works</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Your one-stop platform for discovering and booking top-quality event services with ease.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center p-6 hover:shadow-lg rounded-lg transition-shadow">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-search text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Explore Services</h3>
                    <p class="text-gray-600">Browse through a wide range of event services from verified providers.</p>
                </div>
                
                <div class="text-center p-6 hover:shadow-lg rounded-lg transition-shadow">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-calendar-check text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Book Instantly</h3>
                    <p class="text-gray-600">Easy booking with secure payments and instant confirmations.</p>
                </div>
                
                <div class="text-center p-6 hover:shadow-lg rounded-lg transition-shadow">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-star text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Review & Rate</h3>
                    <p class="text-gray-600">Share your experience and help others make informed decisions.</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Services Categories Section -->
    <div id="services" class="py-16 bg-gray-100">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Browse Services by Category</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Find the perfect services for your next event from our comprehensive categories.</p>
            </div>
            
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-6">
                <?php foreach ($categories as $category): ?>
                <a href="user_index.php?category=<?php echo $category['id']; ?>" class="block">
                    <div class="category-card h-40 bg-gradient-to-r from-blue-500 to-purple-600 relative">
                        <?php if (!empty($category['image'])): ?>
                        <img src="images/<?php echo $category['image']; ?>" alt="<?php echo htmlspecialchars($category['name']); ?>" 
                             class="absolute inset-0 w-full h-full object-cover opacity-50">
                        <?php endif; ?>
                        <div class="absolute bottom-0 left-0 right-0 p-4 text-white">
                            <h3 class="text-lg font-bold"><?php echo htmlspecialchars($category['name']); ?></h3>
                            <p class="text-sm text-white text-opacity-90"><?php echo htmlspecialchars($category['description']); ?></p>
                        </div>
                    </div>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Featured Service Providers -->
    <div id="providers" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Featured Service Providers</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Discover top-rated professionals ready to make your event extraordinary.</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach ($featuredProviders as $provider): ?>
                <div class="provider-card bg-white rounded-lg shadow-md p-6">
                    <div class="flex justify-center mb-4">
                        <img src="https://i.pravatar.cc/150?img=<?php echo ($provider['id'] % 70); ?>" 
                             alt="Provider" 
                             class="w-24 h-24 rounded-full object-cover border-4 border-gray-200">
                    </div>
                    <h3 class="text-xl font-bold text-center mb-2">
                        <?php echo htmlspecialchars($provider['business_name'] ?? $provider['name']); ?>
                        <?php if (isset($provider['avg_rating']) && $provider['avg_rating'] > 4.5): ?>
                        <i class="fas fa-check-circle text-blue-500 ml-1" title="Verified Provider"></i>
                        <?php endif; ?>
                    </h3>
                    <div class="flex justify-center text-yellow-400 mb-2">
                        <?php $rating = round($provider['avg_rating'] ?? 0); ?>
                        <?php for ($i = 1; $i <= 5; $i++): ?>
                            <?php if ($i <= $rating): ?>
                                <i class="fas fa-star"></i>
                            <?php else: ?>
                                <i class="far fa-star"></i>
                            <?php endif; ?>
                        <?php endfor; ?>
                        <span class="text-gray-600 ml-1">(<?php echo number_format($provider['avg_rating'] ?? 0, 1); ?>)</span>
                    </div>
                    <p class="text-center text-gray-600 mb-4"><?php echo $provider['service_count']; ?> services available</p>
                    <div class="text-center">
                        <a href="user_index.php?owner=<?php echo $provider['id']; ?>" 
                           class="px-4 py-2 bg-purple-100 text-purple-800 rounded-full text-sm font-semibold hover:bg-purple-200 transition-colors inline-block">
                            View Services
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Call to Action -->
    <div class="py-16 bg-gradient-to-r from-purple-600 to-indigo-600 text-white">
        <div class="container mx-auto px-4 text-center">
            <h2 class="text-3xl font-bold mb-4">Ready to Create Memorable Events?</h2>
            <p class="text-xl mb-8 max-w-2xl mx-auto">Join EVENTO today and unlock a world of exceptional event services.</p>
            <div class="flex justify-center space-x-4">
                <button data-modal="login" class="px-6 py-3 bg-white text-purple-600 font-semibold rounded-lg hover:bg-gray-100 transition duration-300">
                    Login
                </button>
                <button data-modal="register" class="px-6 py-3 border-2 border-white text-white font-semibold rounded-lg hover:bg-white hover:text-purple-600 transition duration-300">
                    Register
                </button>
            </div>
        </div>
    </div>

    <!-- Contact Section -->
    <div id="contact" class="py-16 bg-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-12">
                <h2 class="text-3xl font-bold mb-4">Contact Us</h2>
                <p class="text-gray-600 max-w-2xl mx-auto">Have questions or need assistance? We're here to help!</p>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <div class="text-center">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-map-marker-alt text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Our Location</h3>
                    <p class="text-gray-600">123 Event Street, Service City, SC 12345</p>
                </div>
                
                <div class="text-center">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-envelope text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Email Us</h3>
                    <p class="text-gray-600">info@evento.com</p>
                </div>
                
                <div class="text-center">
                    <div class="bg-purple-100 rounded-full w-16 h-16 flex items-center justify-center mx-auto mb-4">
                        <i class="fas fa-phone-alt text-purple-600 text-2xl"></i>
                    </div>
                    <h3 class="text-xl font-semibold mb-2">Call Us</h3>
                    <p class="text-gray-600">+1 (123) 456-7890</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <div class="flex items-center mb-4">
                        <div class="text-purple-400 mr-2">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold">EVENTO</span>
                    </div>
                    <p class="text-gray-400">Your one-stop platform for all event services. Making special moments truly memorable.</p>
                    <div class="flex space-x-4 mt-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Quick Links</h3>
                    <ul class="space-y-2">
                        <li><a href="#" class="text-gray-400 hover:text-white">Home</a></li>
                        <li><a href="#about" class="text-gray-400 hover:text-white">About Us</a></li>
                        <li><a href="#services" class="text-gray-400 hover:text-white">Services</a></li>
                        <li><a href="#providers" class="text-gray-400 hover:text-white">Providers</a></li>
                        <li><a href="#contact" class="text-gray-400 hover:text-white">Contact</a></li>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Categories</h3>
                    <ul class="space-y-2">
                        <?php foreach (array_slice($categories, 0, 5) as $cat): ?>
                        <li><a href="user_index.php?category=<?php echo $cat['id']; ?>" class="text-gray-400 hover:text-white"><?php echo htmlspecialchars($cat['name']); ?></a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div>
                    <h3 class="text-lg font-semibold mb-4">Contact Us</h3>
                    <ul class="space-y-2 text-gray-400">
                        <li class="flex items-start">
                            <i class="fas fa-map-marker-alt mt-1 mr-2"></i>
                            <span>123 Event Street, Service City, SC 12345</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-phone-alt mr-2"></i>
                            <span>+1 (123) 456-7890</span>
                        </li>
                        <li class="flex items-center">
                            <i class="fas fa-envelope mr-2"></i>
                            <span>info@evento.com</span>
                        </li>
                    </ul>
                </div>
            </div>
            <div class="border-t border-gray-700 mt-8 pt-6 flex flex-col md:flex-row justify-between items-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
                <div class="mt-4 md:mt-0">
                    <a href="#" class="text-gray-400 hover:text-white mx-2">Privacy Policy</a>
                    <a href="#" class="text-gray-400 hover:text-white mx-2">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

   <!-- Authentication Modal - Updated for Responsiveness -->
<div id="auth-modal" class="hidden fixed inset-0 z-50 overflow-y-auto items-center justify-center p-4 sm:p-6 md:p-8">
    <div class="relative bg-white rounded-lg shadow-2xl w-full max-w-md max-h-[90vh] flex flex-col transform transition-all duration-300 ease-in-out">
        <!-- Modal Close Button -->
        <button data-modal-close class="absolute top-4 right-4 text-gray-600 hover:text-gray-900 z-60">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
            </svg>
        </button>

        <!-- Modal Tabs -->
        <div class="flex border-b border-gray-200 sticky top-0 bg-white z-10">
            <button id="login-tab-btn" class="w-1/2 py-4 text-center font-semibold text-gray-600 border-b-2 border-purple-600 text-purple-600 transition-colors duration-300">
                Login
            </button>
            <button id="register-tab-btn" class="w-1/2 py-4 text-center font-semibold text-gray-600 transition-colors duration-300">
                Register
            </button>
        </div>

        <!-- Scrollable Content Container -->
        <div class="overflow-y-auto flex-grow">
            <!-- Login Form -->
            <div id="login-modal-content" class="p-6">
                <?php if (!empty($login_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $login_error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label for="modal-email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="modal-email" name="email" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="your@email.com" required>
                    </div>
                    <div>
                        <label for="modal-password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="modal-password" name="password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="Your password" required>
                    </div>
                    <button type="submit" name="login" 
                            class="w-full bg-purple-600 text-white py-3 rounded-md hover:bg-purple-700 transition duration-300 transform hover:scale-102 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-opacity-50">
                        Login
                    </button>
                </form>
                <div class="text-center mt-4">
                    <a href="#" class="text-sm text-purple-600 hover:text-purple-800 transition duration-300">Forgot Password?</a>
                </div>
            </div>

            <!-- Register Form -->
            <div id="register-modal-content" class="p-6 hidden">
                <?php if (!empty($register_error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $register_error; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($register_success)): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $register_success; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" class="space-y-4">
                    <div>
                        <label for="modal-reg-name" class="block text-sm font-medium text-gray-700 mb-2">Full Name</label>
                        <input type="text" id="modal-reg-name" name="name" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="Your name" required>
                    </div>
                    <div>
                        <label for="modal-reg-email" class="block text-sm font-medium text-gray-700 mb-2">Email Address</label>
                        <input type="email" id="modal-reg-email" name="email" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="your@email.com" required>
                    </div>
                    <div>
                        <label for="modal-reg-password" class="block text-sm font-medium text-gray-700 mb-2">Password</label>
                        <input type="password" id="modal-reg-password" name="password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="Create a password" required>
                    </div>
                    <div>
                        <label for="modal-reg-confirm-password" class="block text-sm font-medium text-gray-700 mb-2">Confirm Password</label>
                        <input type="password" id="modal-reg-confirm-password" name="confirm_password" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="Confirm your password" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-2">Account Type</label>
                        <div class="flex flex-col sm:flex-row space-y-2 sm:space-y-0 sm:space-x-4">
                            <label class="flex items-center">
                                <input type="radio" name="role" value="client" checked
                                       class="form-radio text-purple-600 focus:ring-purple-300 mr-2">
                                <span class="text-sm">Client (looking for services)</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" name="role" value="owner"
                                       class="form-radio text-purple-600 focus:ring-purple-300 mr-2">
                                <span class="text-sm">Service Provider</span>
                            </label>
                        </div>
                    </div>
                    
                    <div id="modal-business-fields" class="hidden">
                        <div>
                            <label for="modal-business-name" class="block text-sm font-medium text-gray-700 mb-2">Business Name</label>
                            <input type="text" id="modal-business-name" name="business_name" 
                                   class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                                   placeholder="Your business name">
                        </div>
                    </div>
                    
                    <div>
                        <label for="modal-reg-phone" class="block text-sm font-medium text-gray-700 mb-2">Phone Number (Optional)</label>
                        <input type="tel" id="modal-reg-phone" name="phone" 
                               class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300" 
                               placeholder="Your phone number">
                    </div>
                    
                    <div>
                        <label for="modal-reg-address" class="block text-sm font-medium text-gray-700 mb-2">Address (Optional)</label>
                        <textarea id="modal-reg-address" name="address" 
                                  class="w-full px-4 py-2 border border-gray-300 rounded-md focus:ring-2 focus:ring-purple-300 focus:outline-none transition duration-300 h-20" 
                                  placeholder="Your address"></textarea>
                    </div>
                    
                    <button type="submit" name="register" 
                            class="w-full bg-purple-600 text-white py-3 rounded-md hover:bg-purple-700 transition duration-300 transform hover:scale-102 focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-opacity-50">
                        Create Account
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

    <!-- Scripts -->
    <script>
        // Modal functionality with improved responsiveness
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    
    // Prevent body scrolling when modal is open
    document.body.style.overflow = 'hidden';
}

function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    
    // Restore body scrolling
    document.body.style.overflow = '';
}

// Toggle between login and register forms within the modal
function switchToLogin() {
    document.getElementById('login-modal-content').classList.remove('hidden');
    document.getElementById('register-modal-content').classList.add('hidden');
    document.getElementById('login-tab-btn').classList.add('border-b-2', 'border-purple-600', 'text-purple-600');
    document.getElementById('register-tab-btn').classList.remove('border-b-2', 'border-purple-600', 'text-purple-600');
}

function switchToRegister() {
    document.getElementById('register-modal-content').classList.remove('hidden');
    document.getElementById('login-modal-content').classList.add('hidden');
    document.getElementById('register-tab-btn').classList.add('border-b-2', 'border-purple-600', 'text-purple-600');
    document.getElementById('login-tab-btn').classList.remove('border-b-2', 'border-purple-600', 'text-purple-600');
}

// Event Listeners for Modal Triggers
document.addEventListener('DOMContentLoaded', () => {
    const loginTriggers = document.querySelectorAll('[data-modal="login"]');
    const registerTriggers = document.querySelectorAll('[data-modal="register"]');
    const modalClosers = document.querySelectorAll('[data-modal-close]');
    const authModal = document.getElementById('auth-modal');

    // Login Modal Triggers
    loginTriggers.forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            openModal('auth-modal');
            switchToLogin();
        });
    });

    // Register Modal Triggers
    registerTriggers.forEach(trigger => {
        trigger.addEventListener('click', (e) => {
            e.preventDefault();
            openModal('auth-modal');
            switchToRegister();
        });
    });

    // Modal Closers
    modalClosers.forEach(closer => {
        closer.addEventListener('click', () => {
            closeModal('auth-modal');
        });
    });

    // Tab switch within modal
    document.getElementById('login-tab-btn').addEventListener('click', switchToLogin);
    document.getElementById('register-tab-btn').addEventListener('click', switchToRegister);

    // Close modal when clicking outside
    authModal.addEventListener('click', (e) => {
        if (e.target === e.currentTarget) {
            closeModal('auth-modal');
        }
    });

    // Close modal with Escape key
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && !authModal.classList.contains('hidden')) {
            closeModal('auth-modal');
        }
    });

    // Toggle business fields in registration
    const roleRadios = document.querySelectorAll('input[name="role"]');
    const businessFields = document.getElementById('modal-business-fields');

    roleRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            if (this.value === 'owner') {
                businessFields.classList.remove('hidden');
            } else {
                businessFields.classList.add('hidden');
            }
        });
    });

    // Scroll to top of modal when switching tabs
    function scrollModalToTop() {
        const modalContent = document.querySelector('#auth-modal .overflow-y-auto');
        if (modalContent) {
            modalContent.scrollTop = 0;
        }
    }

    // Attach scroll to top to tab switches
    document.getElementById('login-tab-btn').addEventListener('click', scrollModalToTop);
    document.getElementById('register-tab-btn').addEventListener('click', scrollModalToTop);

    // Check for any PHP-generated errors to auto-open modal
    <?php if (!empty($login_error) || !empty($register_error)): ?>
        openModal('auth-modal');
        <?php if (!empty($login_error)): ?>
            switchToLogin();
        <?php else: ?>
            switchToRegister();
        <?php endif; ?>
    <?php endif; ?>
});
    </script>
</body>
</html>