<?php
// Process booking form submission
// Include database configuration and functions
require_once 'config.php';
require_once 'functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to make a booking'
    ]);
    exit;
}

// Verify the request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => 'Invalid request method'
    ]);
    exit;
}

try {
    // Validate required fields
    $requiredFields = [
        'first_name', 'last_name', 'email', 'phone',
        'booking_date', 'booking_time', 'items'
    ];
    
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($_POST[$field]) || empty($_POST[$field])) {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        throw new Exception('Missing required fields: ' . implode(', ', $missingFields));
    }
    
    // Parse cart items
    $items = json_decode($_POST['items'], true);
    
    if (empty($items) || !is_array($items)) {
        throw new Exception('Invalid cart items');
    }
    
    // Calculate total
    $totalAmount = 0;
    foreach ($items as $item) {
        $totalAmount += $item['price'] * $item['quantity'];
    }
    
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
            created_at,
            updated_at
        ) VALUES (
            :user_id,
            :total_amount,
            'pending',
            :booking_date,
            :booking_time,
            :special_requests,
            NOW(),
            NOW()
        )
    ");
    
    $bookingStmt->execute([
        'user_id' => $_SESSION['user_id'],
        'total_amount' => $totalAmount,
        'booking_date' => $_POST['booking_date'],
        'booking_time' => $_POST['booking_time'],
        'special_requests' => isset($_POST['special_requests']) ? $_POST['special_requests'] : null
    ]);
    
    $bookingId = $db->lastInsertId();
    
    // Add booking items
    $itemStmt = $db->prepare("
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
    
    foreach ($items as $item) {
        $itemStmt->execute([
            'booking_id' => $bookingId,
            'service_id' => $item['id'],
            'quantity' => $item['quantity'],
            'price' => $item['price']
        ]);
    }
    
    // Create notifications
    // For the customer
    createNotification(
        $db,
        $_SESSION['user_id'],
        'booking',
        'Booking Confirmation',
        "Your booking #{$bookingId} has been received and is pending confirmation.",
        $bookingId
    );
    
    // Get service owner IDs
    $serviceIds = array_column($items, 'id');
    $placeholders = str_repeat('?,', count($serviceIds) - 1) . '?';
    
    $ownerStmt = $db->prepare("
        SELECT DISTINCT owner_id
        FROM services
        WHERE id IN ({$placeholders})
    ");
    
    $ownerStmt->execute($serviceIds);
    $owners = $ownerStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Notify service owners
    foreach ($owners as $ownerId) {
        createNotification(
            $db,
            null,
            'booking',
            'New Booking Request',
            "You have received a new booking request #{$bookingId}.",
            $bookingId,
            $ownerId
        );
    }
    
    // Create booking status history entry
    $historyStmt = $db->prepare("
        INSERT INTO booking_status_history (
            booking_id,
            status,
            notes,
            created_at
        ) VALUES (
            :booking_id,
            'pending',
            'Booking created by customer',
            NOW()
        )
    ");
    
    $historyStmt->execute([
        'booking_id' => $bookingId
    ]);
    
    // Commit transaction
    $db->commit();
    
    // Return success response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'booking_id' => $bookingId,
        'message' => 'Booking successfully created'
    ]);
    
} catch (Exception $e) {
    // Rollback transaction on error
    if ($db && $db->inTransaction()) {
        $db->rollBack();
    }
    
    // Return error response
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
    
    // Log error
    error_log('Booking error: ' . $e->getMessage());
}
?>