<?php
// Start session
session_start();

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

// Check if updates were sent
if (!isset($_POST['updates']) || !is_array($_POST['updates'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid request data'
    ]);
    exit;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$updates = $_POST['updates'];
$updatedItems = 0;

// Apply updates to cart items
foreach ($updates as $update) {
    $index = isset($update['index']) ? (int)$update['index'] : -1;
    $quantity = isset($update['quantity']) ? (int)$update['quantity'] : 0;
    
    // Validate index and quantity
    if ($index >= 0 && $index < count($_SESSION['cart']) && $quantity > 0 && $quantity <= 99) {
        $_SESSION['cart'][$index]['quantity'] = $quantity;
        $updatedItems++;
    }
}

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Cart updated successfully',
    'updated_items' => $updatedItems
]);
?>