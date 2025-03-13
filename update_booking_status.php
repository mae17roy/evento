<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in and is a service owner
requireOwnerLogin();

// Get owner ID
$ownerId = $_SESSION['user_id'];

// Check if the request method is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $_SESSION['error_message'] = "Invalid request method.";
    header("Location: owner_bookings.php");
    exit();
}

// Check required parameters
if (!isset($_POST['booking_id']) || empty($_POST['booking_id']) || !isset($_POST['status']) || empty($_POST['status'])) {
    $_SESSION['error_message'] = "Missing required parameters.";
    header("Location: owner_bookings.php");
    exit();
}

$bookingId = $_POST['booking_id'];
$newStatus = $_POST['status'];
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

// Validate status
$validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
if (!in_array($newStatus, $validStatuses)) {
    $_SESSION['error_message'] = "Invalid status value.";
    header("Location: owner_bookings.php");
    exit();
}

// Check if the booking is for a service owned by this owner
if (!canAccessBooking($db, $ownerId, $bookingId, 'owner')) {
    $_SESSION['error_message'] = "You don't have permission to update this booking.";
    header("Location: owner_bookings.php");
    exit();
}

// Begin transaction
$db->beginTransaction();

try {
    // Set admin user ID for trigger
    $db->query("SET @admin_user_id = " . $ownerId);
    
    // Update booking status
    $updateStmt = $db->prepare("
        UPDATE bookings 
        SET status = ?, updated_at = NOW() 
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $bookingId]);
    
    // Add status change history with notes if provided
    if (!empty($notes)) {
        $historyStmt = $db->prepare("
            INSERT INTO booking_status_history (
                booking_id, status, notes, changed_by, created_at
            ) VALUES (
                ?, ?, ?, ?, NOW()
            )
        ");
        $historyStmt->execute([$bookingId, $newStatus, $notes, $ownerId]);
    }
    
    // Create notification for client
    $bookingData = getBookingById($db, $bookingId);
    $bookingItems = getBookingItems($db, $bookingId);
    
    if ($bookingData && !empty($bookingItems)) {
        $serviceName = $bookingItems[0]['service_name'];
        
        $title = "Booking #$bookingId " . ucfirst($newStatus);
        $message = "Your booking for $serviceName on " . formatDate($bookingData['booking_date']) . " at " . formatTime($bookingData['booking_time']) . " has been $newStatus.";
        
        if (!empty($notes)) {
            $message .= " Note: $notes";
        }
        
        $notifyStmt = $db->prepare("
            INSERT INTO notifications (
                user_id, type, title, message, related_id, created_at
            ) VALUES (
                ?, 'booking', ?, ?, ?, NOW()
            )
        ");
        $notifyStmt->execute([$bookingData['user_id'], $title, $message, $bookingId]);
    }
    
    // Commit transaction
    $db->commit();
    
    $_SESSION['success_message'] = "Booking status has been updated to " . ucfirst($newStatus) . ".";
    
    // Redirect back to the booking details page if it was accessed from there
    if (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'owner_view_booking.php') !== false) {
        header("Location: owner_view_booking.php?id=" . $bookingId);
    } else {
        header("Location: owner_bookings.php");
    }
    exit();
} catch (PDOException $e) {
    // Rollback transaction on error
    $db->rollBack();
    
    $_SESSION['error_message'] = "Error updating booking status: " . $e->getMessage();
    header("Location: owner_bookings.php");
    exit();
}