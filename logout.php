<?php
// Start the session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Log the logout attempt
if (isset($_SESSION['user_id'])) {
    error_log("User logged out: ID {$_SESSION['user_id']}, Email: " . ($_SESSION['user_email'] ?? 'unknown'));
}

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Set a success message
session_start();
$_SESSION['success_message'] = "You have been successfully logged out.";

// Redirect to index.php
header("Location: index.php");
exit;
?>