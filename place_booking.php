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

// Validate required fields
$requiredFields = ['service_id', 'booking_date', 'booking_time', 'quantity', 'name', 'email', 'phone', 'address', 'payment_method'];
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

// Get user and booking data
$userId = $_SESSION['user_id'];
$serviceId = (int)$_POST['service_id'];
$bookingDate = $_POST['booking_date'];
$bookingTime = $_POST['booking_time'];
$quantity = (int)$_POST['quantity'];
$name = trim($_POST['name']);
$email = trim($_POST['email']);
$phone = trim($_POST['phone']);
$address = trim($_POST['address']);
$paymentMethod = $_POST['payment_method'];
$specialRequests = isset($_POST['special_requests']) ? trim($_POST['special_requests']) : '';

// Validate booking date (must be today or in the future)
$currentDate = date('Y-m-d');
if ($bookingDate < $currentDate) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Booking date must be today or in the future.'
    ]);
    exit;
}

// Validate quantity
if ($quantity <= 0 || $quantity > 10) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Quantity must be between 1 and 10.'
    ]);
    exit;
}

// Get service details
try {
    $serviceStmt = $db->prepare("SELECT id, name, price, owner_id FROM services WHERE id = :id AND is_available = 1");
    $serviceStmt->bindParam(':id', $serviceId, PDO::PARAM_INT);
    $serviceStmt->execute();
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Service not found or unavailable.'
        ]);
        exit;
    }
    
    // Calculate total amount
    $totalAmount = $service['price'] * $quantity;
    
    // Add 10% tax
    $taxRate = 0.10;
    $tax = $totalAmount * $taxRate;
    $totalAmount += $tax;
    
} catch (Exception $e) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Error retrieving service information.'
    ]);
    error_log("Service retrieval error: " . $e->getMessage());
    exit;
}

try {
    // Begin transaction
    $db->beginTransaction();
    
    // Create booking record
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
    
    // Add customer details and payment method to special requests
    $fullSpecialRequests = "Payment Method: $paymentMethod\n";
    if (!empty($specialRequests)) {
        $fullSpecialRequests .= "Special Requests: $specialRequests\n";
    }
    
    $bookingStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $bookingStmt->bindParam(':total_amount', $totalAmount, PDO::PARAM_STR);
    $bookingStmt->bindParam(':booking_date', $bookingDate, PDO::PARAM_STR);
    $bookingStmt->bindParam(':booking_time', $bookingTime, PDO::PARAM_STR);
    $bookingStmt->bindParam(':special_requests', $fullSpecialRequests, PDO::PARAM_STR);
    $bookingStmt->execute();
    
    // Get the booking ID
    $bookingId = $db->lastInsertId();
    
    // Create booking item
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
    $bookingItemStmt->bindParam(':service_id', $serviceId, PDO::PARAM_INT);
    $bookingItemStmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
    $bookingItemStmt->bindParam(':price', $service['price'], PDO::PARAM_STR);
    $bookingItemStmt->execute();
    
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
    
    $historyNotes = "Booking created online. Payment method: $paymentMethod";
    $historyStmt->bindParam(':booking_id', $bookingId, PDO::PARAM_INT);
    $historyStmt->bindParam(':notes', $historyNotes, PDO::PARAM_STR);
    $historyStmt->execute();
    
    // Update user profile information if it has changed
    $updateUserStmt = $db->prepare("
        UPDATE users 
        SET name = :name, email = :email, phone = :phone, address = :address 
        WHERE id = :user_id
    ");
    
    $updateUserStmt->bindParam(':name', $name, PDO::PARAM_STR);
    $updateUserStmt->bindParam(':email', $email, PDO::PARAM_STR);
    $updateUserStmt->bindParam(':phone', $phone, PDO::PARAM_STR);
    $updateUserStmt->bindParam(':address', $address, PDO::PARAM_STR);
    $updateUserStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $updateUserStmt->execute();
    
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
    
    $notificationTitle = "Booking Confirmation #$bookingId";
    $notificationMessage = "Your booking for " . $service['name'] . " has been placed successfully and is pending confirmation. Total amount: $" . number_format($totalAmount, 2);
    
    $notificationStmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
    $notificationStmt->bindParam(':title', $notificationTitle, PDO::PARAM_STR);
    $notificationStmt->bindParam(':message', $notificationMessage, PDO::PARAM_STR);
    $notificationStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
    $notificationStmt->execute();
    
    // Create notification for service owner
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
    
    $ownerTitle = "New Booking #$bookingId";
    $ownerMessage = "You have received a new booking for " . $service['name'] . " on " . date('F j, Y', strtotime($bookingDate)) . " at " . date('g:i A', strtotime($bookingTime)) . ".";
    
    $ownerNotifStmt->bindParam(':owner_id', $service['owner_id'], PDO::PARAM_INT);
    $ownerNotifStmt->bindParam(':title', $ownerTitle, PDO::PARAM_STR);
    $ownerNotifStmt->bindParam(':message', $ownerMessage, PDO::PARAM_STR);
    $ownerNotifStmt->bindParam(':related_id', $bookingId, PDO::PARAM_INT);
    $ownerNotifStmt->execute();
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    echo json_encode([
        'status' => 'success',
        'message' => 'Booking placed successfully!',
        'booking_id' => $bookingId,
        'redirect' => 'booking_confirmation.php?id=' . $bookingId
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    error_log("Booking error: " . $e->getMessage());
    
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing your booking. Please try again.'
    ]);
}
?>