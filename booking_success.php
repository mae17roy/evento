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
$stmt = $db->prepare("SELECT b.*, u.name as customer_name, u.email as customer_email, u.phone as customer_phone 
                      FROM bookings b 
                      JOIN users u ON b.user_id = u.id 
                      WHERE b.id = :booking_id AND b.user_id = :user_id");
                      
$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
$stmt->execute();
$booking = $stmt->fetch(PDO::FETCH_ASSOC);

// If booking not found or doesn't belong to the user, redirect
if (!$booking) {
    header("Location: bookings.php");
    exit();
}

// Get booking details (services)
$stmt = $db->prepare("SELECT bd.*, s.name as service_name, s.image as service_image 
                      FROM booking_details bd 
                      JOIN services s ON bd.service_id = s.id 
                      WHERE bd.booking_id = :booking_id");
                      
$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$stmt->execute();
$bookingDetails = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get invoice details
$stmt = $db->prepare("SELECT * FROM invoices WHERE booking_id = :booking_id");
$stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
$stmt->execute();
$invoice = $stmt->fetch(PDO::FETCH_ASSOC);

// Calculate totals
$subtotal = 0;
foreach ($bookingDetails as $detail) {
    $subtotal += $detail['subtotal'];
}

$taxRate = 0.10; // 10% tax
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation - EVENTO</title>
    
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
                <a href="bookings.php" class="text-primary-600 hover:text-primary-700">My Bookings</a>
                <span class="mx-2">/</span>
                <span class="text-gray-500">Booking Confirmation</span>
            </div>
        </div>
    </div>

    <!-- Success Message -->
    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="container mx-auto px-4 mt-6">
            <div class="bg-green-50 border-l-4 border-green-500 p-4 rounded-md">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <i class="fas fa-check-circle text-green-500"></i>
                    </div>
                    <div class="ml-3">
                        <p class="text-green-700"><?php echo $_SESSION['success_message']; ?></p>
                    </div>
                </div>
            </div>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8">
        <div class="bg-white rounded-lg shadow-md overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex items-center justify-center mb-6">
                    <div class="text-6xl text-green-500 mb-4">
                        <i class="fas fa-check-circle"></i>
                    </div>
                </div>
                
                <h1 class="text-2xl font-bold text-center text-gray-800 mb-4">Booking Confirmed!</h1>
                <p class="text-center text-gray-600 mb-6">Your booking has been successfully placed and is now being processed.</p>
                
                <div class="border-t border-gray-200 pt-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Booking Summary</h2>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Booking Details</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="mb-2">
                                    <span class="font-medium">Booking ID:</span>
                                    <span class="ml-2">#<?php echo $bookingId; ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-medium">Booking Date:</span>
                                    <span class="ml-2"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-medium">Booking Time:</span>
                                    <span class="ml-2"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                                </div>
                                <div class="mb-2">
                                    <span class="font-medium">Status:</span>
                                    <span class="ml-2 px-2 py-1 text-xs rounded-full 
                                        <?php 
                                        switch($booking['status']) {
                                            case 'confirmed':
                                                echo 'bg-green-100 text-green-800';
                                                break;
                                            case 'pending':
                                                echo 'bg-yellow-100 text-yellow-800';
                                                break;
                                            case 'cancelled':
                                                echo 'bg-red-100 text-red-800';
                                                break;
                                            default:
                                                echo 'bg-gray-100 text-gray-800';
                                        }
                                        ?>">
                                        <?php echo ucfirst($booking['status']); ?>
                                    </span>
                                </div>
                                <div>
                                    <span class="font-medium">Payment Method:</span>
                                    <span class="ml-2"><?php echo ucfirst($booking['payment_method']); ?></span>
                                </div>
                            </div>
                        </div>
                        
                        <div>
                            <h3 class="text-lg font-semibold text-gray-700 mb-2">Event Details</h3>
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="mb-2">
                                    <span class="font-medium">Event Address:</span>
                                    <span class="ml-2"><?php echo htmlspecialchars($booking['event_address']); ?></span>
                                </div>
                                <?php if (!empty($booking['special_requests'])): ?>
                                <div>
                                    <span class="font-medium">Special Requests:</span>
                                    <span class="ml-2"><?php echo htmlspecialchars($booking['special_requests']); ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <h3 class="text-lg font-semibold text-gray-700 mb-2">Booked Services</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full bg-white">
                            <thead class="bg-gray-50 border-b">
                                <tr>
                                    <th class="py-3 px-4 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Price</th>
                                    <th class="py-3 px-4 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Qty</th>
                                    <th class="py-3 px-4 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Subtotal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200">
                                <?php foreach ($bookingDetails as $detail): ?>
                                    <tr>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <img src="<?php echo !empty($detail['service_image']) ? 'uploads/services/' . htmlspecialchars($detail['service_image']) : 'assets/img/default-service.jpg'; ?>" 
                                                     alt="<?php echo htmlspecialchars($detail['service_name']); ?>" 
                                                     class="w-10 h-10 object-cover rounded-md mr-3">
                                                <div>
                                                    <p class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($detail['service_name']); ?></p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4 text-center text-sm text-gray-500">
                                            $<?php echo number_format($detail['price'], 2); ?>
                                        </td>
                                        <td class="py-4 px-4 text-center text-sm text-gray-500">
                                            <?php echo $detail['quantity']; ?>
                                        </td>
                                        <td class="py-4 px-4 text-right text-sm font-medium text-gray-900">
                                            $<?php echo number_format($detail['subtotal'], 2); ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="bg-gray-50">
                                <tr>
                                    <td colspan="3" class="py-3 px-4 text-right text-sm font-medium text-gray-500">Subtotal:</td>
                                    <td class="py-3 px-4 text-right text-sm font-medium text-gray-900">$<?php echo number_format($subtotal, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="py-3 px-4 text-right text-sm font-medium text-gray-500">Tax (10%):</td>
                                    <td class="py-3 px-4 text-right text-sm font-medium text-gray-900">$<?php echo number_format($tax, 2); ?></td>
                                </tr>
                                <tr>
                                    <td colspan="3" class="py-3 px-4 text-right text-sm font-bold text-gray-900">Total:</td>
                                    <td class="py-3 px-4 text-right text-sm font-bold text-primary-600">$<?php echo number_format($total, 2); ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                
                <?php if (isset($invoice)): ?>
                <div class="border-t border-gray-200 pt-6 mb-6">
                    <h2 class="text-xl font-bold text-gray-800 mb-4">Invoice Details</h2>
                    <div class="bg-gray-50 p-4 rounded-lg">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="mb-2">
                                <span class="font-medium">Invoice Number:</span>
                                <span class="ml-2"><?php echo $invoice['invoice_number']; ?></span>
                            </div>
                            <div class="mb-2">
                                <span class="font-medium">Amount:</span>
                                <span class="ml-2">$<?php echo number_format($invoice['amount'], 2); ?></span>
                            </div>
                            <div class="mb-2">
                                <span class="font-medium">Status:</span>
                                <span class="ml-2 px-2 py-1 text-xs rounded-full 
                                    <?php echo ($invoice['status'] == 'paid') ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($invoice['status']); ?>
                                </span>
                            </div>
                            <div class="mb-2">
                                <span class="font-medium">Due Date:</span>
                                <span class="ml-2"><?php echo date('F j, Y', strtotime($invoice['due_date'])); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="text-center space-y-4">
                    <p class="text-gray-600">
                        A confirmation email has been sent to <strong><?php echo $booking['customer_email']; ?></strong> with all booking details.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row justify-center gap-4">
                        <a href="bookings.php" class="px-6 py-3 bg-primary-600 text-white font-semibold rounded-lg hover:bg-primary-700 transition duration-300 text-center">
                            <i class="fas fa-calendar-check mr-2"></i> View All Bookings
                        </a>
                        <?php if (isset($invoice) && $invoice['status'] != 'paid'): ?>
                        <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="px-6 py-3 border border-primary-600 text-primary-600 font-semibold rounded-lg hover:bg-primary-50 transition duration-300 text-center">
                            <i class="fas fa-file-invoice-dollar mr-2"></i> View Invoice
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Additional Features Section -->
    <div class="container mx-auto px-4 py-6">
        <h2 class="text-xl font-bold text-gray-800 mb-6">What's Next?</h2>
        
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
                <div class="text-primary-500 text-3xl mb-4">
                    <i class="fas fa-calendar-day"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Manage Your Booking</h3>
                <p class="text-gray-600 mb-4">You can view and manage your booking details from your account dashboard at any time.</p>
                <a href="bookings.php" class="text-primary-600 hover:text-primary-700 font-medium">
                    View Bookings <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
                <div class="text-primary-500 text-3xl mb-4">
                    <i class="fas fa-comments"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Contact Service Providers</h3>
                <p class="text-gray-600 mb-4">You can directly communicate with your service providers for any specific requirements.</p>
                <a href="messages.php" class="text-primary-600 hover:text-primary-700 font-medium">
                    Messages <i class="fas fa-arrow-right ml-1"></i>
                </a>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 hover:shadow-lg transition duration-300">
                <div class="text-primary-500 text-3xl mb-4">
                    <i class="fas fa-file-invoice-dollar"></i>
                </div>
                <h3 class="text-lg font-semibold mb-2">Payment Information</h3>
                <p class="text-gray-600 mb-4">View your invoice and complete your payment based on your selected payment method.</p>
                <?php if (isset($invoice)): ?>
                <a href="view_invoice.php?id=<?php echo $invoice['id']; ?>" class="text-primary-600 hover:text-primary-700 font-medium">
                    View Invoice <i class="fas fa-arrow-right ml-1"></i>
                </a>
                <?php else: ?>
                <span class="text-gray-400">Invoice Pending</span>
                <?php endif; ?>
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
    
    <!-- Print Functionality -->
    <script>
        function printBookingDetails() {
            window.print();
        }
    </script>
</body>
</html>

<?php unset($_SESSION['success_message']); ?>