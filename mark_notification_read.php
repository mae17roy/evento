<?php
// Include database configuration
require_once 'config.php';
require_once 'notifications_functions.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Check if notification ID is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notification_id'])) {
    $notificationId = intval($_POST['notification_id']);
    
    // Mark notification as read
    $success = markNotificationAsRead($db, $notificationId);
    
    if ($success) {
        $_SESSION['success_message'] = "Notification marked as read.";
    } else {
        $_SESSION['error_message'] = "Failed to mark notification as read.";
    }
}

// Redirect back to the referring page
$referrer = $_SERVER['HTTP_REFERER'] ?? 'notifications.php';
header("Location: $referrer");
exit;
?>