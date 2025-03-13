<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to cancel a booking.";
    header("Location: login.php");
    exit();
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: my_bookings.php");
    exit();
}

// Check if booking ID is provided
if (!isset($_POST['booking_id']) || empty($_POST['booking_id'])) {
    $_SESSION['error_message'] = "Booking ID is required.";
    header("Location: my_bookings.php");
    exit();
}

$bookingId = $_POST['booking_id'];
$userId = $_SESSION['user_id'];
$cancelReason = isset($_POST['cancel_reason']) ? $_POST['cancel_reason'] : '';

// Set variable to track user in triggers
$db->query("SET @admin_user_id = " . $userId);

try {
    // First check if the booking belongs to the current user
    $checkStmt = $db->prepare("
        SELECT user_id, status FROM bookings WHERE id = ?
    ");
    $checkStmt->execute([$bookingId]);
    $booking = $checkStmt->fetch();
    
    // If booking doesn't exist or doesn't belong to the current user
    if (!$booking || $booking['user_id'] != $userId) {
        $_SESSION['error_message'] = "You don't have permission to cancel this booking.";
        header("Location: my_bookings.php");
        exit();
    }
    
    // Check if booking is already cancelled or completed
    if ($booking['status'] === 'cancelled' || $booking['status'] === 'completed') {
        $_SESSION['error_message'] = "This booking cannot be cancelled because it is already " . $booking['status'] . ".";
        header("Location: view_booking.php?id=" . $bookingId);
        exit();
    }
    
    // Update booking status to cancelled
    $stmt = $db->prepare("
        UPDATE bookings SET status = 'cancelled', updated_at = NOW() WHERE id = ?
    ");
    $stmt->execute([$bookingId]);
    
    // Add cancellation reason to status history if provided
    if (!empty($cancelReason)) {
        $historyStmt = $db->prepare("
            INSERT INTO booking_status_history (
                booking_id, status, notes, changed_by, created_at
            ) VALUES (
                ?, 'cancelled', ?, ?, NOW()
            )
        ");
        $historyStmt->execute([$bookingId, $cancelReason, $userId]);
    }
    
    // Create notification for service owner
    $notifyStmt = $db->prepare("
        INSERT INTO notifications (
            owner_id, type, title, message, related_id, created_at
        )
        SELECT 
            s.owner_id,
            'booking',
            CONCAT('Booking #', ?) AS title,
            CONCAT('A booking for ', s.name, ' on ', DATE_FORMAT(b.booking_date, '%M %e, %Y'), ' at ', TIME_FORMAT(b.booking_time, '%h:%i %p'), ' has been cancelled by the customer.') AS message,
            ? AS related_id,
            NOW() AS created_at
        FROM booking_items bi
        JOIN services s ON bi.service_id = s.id
        JOIN bookings b ON bi.booking_id = b.id
        WHERE bi.booking_id = ?
        LIMIT 1
    ");
    $notifyStmt->execute([$bookingId, $bookingId, $bookingId]);
    
    $_SESSION['success_message'] = "Your booking has been successfully cancelled.";
    header("Location: view_booking.php?id=" . $bookingId);
    exit();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Failed to cancel booking: " . $e->getMessage();
    header("Location: view_booking.php?id=" . $bookingId);
    exit();
}