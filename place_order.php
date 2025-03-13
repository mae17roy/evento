<?php
// Start session
session_start();

// Include database configuration
require_once 'config.php';

// Set response header
header('Content-Type: application/json');

// User must be logged in to access this page
if (!isset($_SESSION['user_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Authentication required'
    ]);
    exit;
}

// Check if cart is empty
if (!isset($_SESSION['cart']) || empty($_SESSION['cart'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Your cart is empty'
    ]);
    exit;
}

// Validate required fields
$requiredFields = ['name', 'email', 'phone', 'address', 'payment_method'];
$missingFields = [];

foreach ($requiredFields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        $missingFields[] = $field;
    }
}

if (!empty($missingFields)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing required fields: ' . implode(', ', $missingFields)
    ]);
    exit;
}

// Get user data
$userId = $_SESSION['user_id'];
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$address = trim($_POST['address']);
$paymentMethod = $_POST['payment_method'];
$specialNotes = isset($_POST['special_notes']) ? trim($_POST['special_notes']) : '';

// Calculate order totals
$cartItems = [];
$subtotal = 0;

foreach ($_SESSION['cart'] as $item) {
    // Get the latest service information from the database
    $stmt = $db->prepare("SELECT id, name, price FROM services WHERE id = :id AND is_available = 1");
    $stmt->bindParam(':id', $item['service_id'], PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($service) {
        // Use the current price from the database
        $itemSubtotal = $service['price'] * $item['quantity'];
        $subtotal += $itemSubtotal;
        
        $cartItems[] = [
            'service_id' => $item['service_id'],
            'name' => $service['name'],
            'price' => $service['price'],
            'quantity' => $item['quantity'],
            'subtotal' => $itemSubtotal
        ];
    }
}

// Calculate tax (10%) and total
$taxRate = 0.10;
$tax = $subtotal * $taxRate;
$total = $subtotal + $tax;

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Record billing details in booking
    $bookingStmt = $db->prepare("
        INSERT INTO bookings (
            user_id, 
            total_amount, 
            status, 
            booking_date, 
            booking_time, 
            special_requests, 
            created_at
        ) VALUES (
            :user_id,
            :total_amount,
            'pending',
            :booking_date,
            :booking_time,
            :special_requests,
            NOW()
        )
    ");
    
    // Set booking date to one week from now by default
    $bookingDate = date('Y-m-d', strtotime('+1 week'));
    $bookingTime = '10:00:00'; // Default time
    
    // Combine payment method and special notes
    $specialRequests = "Payment Method: $paymentMethod\n";
    $specialRequests .= "Billing Address: $address\n";
    $specialRequests .= "Phone: $phone\n";
    if (!empty($specialNotes)) {
        $specialRequests .= "\nCustomer Notes: $specialNotes";
    }
    
    $bookingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingStmt->bindParam(':total_amount', $total, PDO::PARAM_STR);
    $bookingStmt->bindParam(':booking_date', $bookingDate, PDO::PARAM_STR);
    $bookingStmt->bindParam(':booking_time', $bookingTime, PDO::PARAM_STR);
    $bookingStmt->bindParam(':special_requests', $specialRequests, PDO::PARAM_STR);
    $bookingStmt->execute();
    
    // Get the booking ID
    $bookingId = $db->lastInsertId();
    
    // Insert booking items
    foreach ($cartItems as $item) {
        $bookingItemStmt = $db->prepare("
            INSERT INTO booking_items (
                booking_id,
                service_id,
                quantity,
                price,
                created_at
            ) VALUES (
                :booking_id,
                :service_id,
                :quantity,
                :price,
                NOW()
            )
        ");
        
        $bookingItemStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
        $bookingItemStmt->bindParam(':service_id', $item['service_id'], PDO::PARAM_INT);
        $bookingItemStmt->bindParam(':quantity', $item['quantity'], PDO::PARAM_INT);
        $bookingItemStmt->bindParam(':price', $item['price'], PDO::PARAM_STR);
        $bookingItemStmt->execute();
    }
    
    // Record booking status history
    $historyStmt = $db->prepare("
        INSERT INTO booking_status_history (
            booking_id,
            status,
            notes,
            created_at
        ) VALUES (
            :booking_id,
            'pending',
            :notes,
            NOW()
        )
    ");
    
    $statusNotes = "Order placed via checkout. Payment method: $paymentMethod";
    $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $historyStmt->bindParam(':notes', $statusNotes, PDO::PARAM_STR);
    $historyStmt->execute();
    
    // Create notification for customer
    $notificationStmt = $db->prepare("
        INSERT INTO notifications (
            user_id,
            type,
            title,
            message,
            related_id,
            created_at
        ) VALUES (
            :user_id,
            'booking',
            :title,
            :message,
            :related_id,
            NOW()
        )
    ");
    
    $notificationTitle = "Order Confirmation #$bookingId";
    $notificationMessage = "Your order has been placed successfully and is pending confirmation. Order total: $" . number_format($total, 2);
    
    $notificationStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $notificationStmt->bindParam(':title', $notificationTitle, PDO::PARAM_STR);
    $notificationStmt->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
    $notificationStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
    $notificationStmt->execute();
    
    // Also notify service providers about new orders
    foreach ($cartItems as $item) {
        // Get the service owner
        $ownerStmt = $db->prepare("
            SELECT owner_id FROM services
            WHERE id = :service_id
        ");
        $ownerStmt->bindParam(':service_id', $item['service_id'], PDO::PARAM_INT);
        $ownerStmt->execute();
        $owner = $ownerStmt->fetch(PDO::FETCH_ASSOC);
        
        if ($owner && $owner['owner_id']) {
            // Create notification for the service owner
            $ownerNotifStmt = $db->prepare("
                INSERT INTO notifications (
                    owner_id,
                    type,
                    title,
                    message,
                    related_id,
                    created_at
                ) VALUES (
                    :owner_id,
                    'booking',
                    :title,
                    :message,
                    :related_id,
                    NOW()
                )
            ");
            
            $ownerNotifTitle = "New Order #$bookingId";
            $ownerNotifMessage = "You have received a new order for " . $item['name'] . " (Qty: " . $item['quantity'] . ").";
            
            $ownerNotifStmt->bindParam(':owner_id', $owner['owner_id'], PDO::PARAM_INT);
            $ownerNotifStmt->bindParam(':title', $ownerNotifTitle, PDO::PARAM_STR);
            $ownerNotifStmt->bindParam(':message', $ownerNotifMessage, PDO::PARAM_STR);
            $ownerNotifStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
            $ownerNotifStmt->execute();
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Clear the cart
    $_SESSION['cart'] = [];
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Order placed successfully!',
        'order_id' => $bookingId,
        'redirect' => 'order_confirmation.php?id=' . $bookingId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    error_log("Order error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing your order. Please try again.',
        'debug' => $e->getMessage() // Remove in production
    ]);
}
?>