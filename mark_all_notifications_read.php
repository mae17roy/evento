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

// Mark all notifications as read
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Check if we should use the multi-owner function first
    if (function_exists('markAllNotificationsAsReadMultiOwner')) {
        $success = markAllNotificationsAsReadMultiOwner($db);
    } else {
        // Pass both required parameters - db connection and user ID
        $success = markAllNotificationsAsRead($db, $_SESSION['user_id']);
    }
    
    if ($success) {
        $_SESSION['success_message'] = "All notifications marked as read.";
    } else {
        $_SESSION['error_message'] = "Failed to mark notifications as read.";
    }
}

// Redirect back to the referring page
$referrer = $_SERVER['HTTP_REFERER'] ?? 'notifications.php';
header("Location: $referrer");
exit;
?>