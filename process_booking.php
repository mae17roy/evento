<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Check if the request is AJAX
header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
    exit();
}

$userId = $_SESSION['user_id'];

// Check if required fields are provided
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['booking_date']) || 
    !isset($_POST['booking_time']) || 
    !isset($_POST['event_address']) || 
    !isset($_POST['payment_method'])) {
    
    echo json_encode(['status' => 'error', 'message' => 'Missing required fields']);
    exit();
}

// Get form data
$bookingDate = $_POST['booking_date'];
$bookingTime = $_POST['booking_time'];
$eventAddress = $_POST['event_address'];
$specialRequests = $_POST['special_requests'] ?? '';
$paymentMethod = $_POST['payment_method'];

// Validate date and time
try {
    $bookingDateTime = new DateTime($bookingDate . ' ' . $bookingTime);
    $now = new DateTime();
    
    if ($bookingDateTime < $now) {
        echo json_encode(['status' => 'error', 'message' => 'Booking date and time must be in the future']);
        exit();
    }
} catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid date or time format']);
    exit();
}

// Get service ID from POST or from cart if booking all items
if (isset($_POST['service_id'])) {
    // Single service booking
    $serviceId = (int)$_POST['service_id'];
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    // Verify service exists and is available
    $stmt = $db->prepare("SELECT id, name, price FROM services WHERE id = :id AND is_available = 1");
    $stmt->bindParam(':id', $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode(['status' => 'error', 'message' => 'Service not available']);
        exit();
    }
    
    $services = [
        [
            'service_id' => $service['id'],
            'name' => $service['name'],
            'price' => $service['price'],
            'quantity' => $quantity,
            'subtotal' => $service['price'] * $quantity
        ]
    ];
    
} else if (isset($_POST['book_all']) && isset($_SESSION['cart']) && !empty($_SESSION['cart'])) {
    // Book all items in cart
    $services = [];
    
    foreach ($_SESSION['cart'] as $item) {
        // Get the latest service information from the database
        $stmt = $db->prepare("SELECT id, name, price FROM services WHERE id = :id AND is_available = 1");
        $stmt->bindParam(':id', $item['service_id'], PDO::PARAM_INT);
        $stmt->execute();
        $service = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($service) {
            $services[] = [
                'service_id' => $service['id'],
                'name' => $service['name'],
                'price' => $service['price'],
                'quantity' => $item['quantity'],
                'subtotal' => $service['price'] * $item['quantity']
            ];
        }
    }
    
    if (empty($services)) {
        echo json_encode(['status' => 'error', 'message' => 'No valid services found in your cart']);
        exit();
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'No services specified for booking']);
    exit();
}

// Calculate totals
$subtotal = 0;
foreach ($services as $service) {
    $subtotal += $service['subtotal'];
}

$taxRate = 0.10; // 10% tax
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

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
    foreach ($services as $service) {
        $stmt = $db->prepare("INSERT INTO booking_details (booking_id, service_id, quantity, price, subtotal) 
                              VALUES (:booking_id, :service_id, :quantity, :price, :subtotal)");
        
        $stmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $stmt->bindParam(':service_id', $service['service_id'], PDO::PARAM_INT);
        $stmt->bindParam(':quantity', $service['quantity'], PDO::PARAM_INT);
        $stmt->bindParam(':price', $service['price']);
        $stmt->bindParam(':subtotal', $service['subtotal']);
        
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
    
    // Clear the cart if booking was from cart
    if (isset($_POST['book_all'])) {
        $_SESSION['cart'] = [];
    }
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Booking successfully created',
        'booking_id' => $bookingId,
        'redirect_url' => 'booking_success.php?id=' . $bookingId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    echo json_encode(['status' => 'error', 'message' => 'Error processing booking: ' . $e->getMessage()]);
}
?>