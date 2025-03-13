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

// Check if index was provided
if (!isset($_POST['index'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Missing item index'
    ]);
    exit;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$index = (int)$_POST['index'];

// Check if index is valid
if ($index < 0 || $index >= count($_SESSION['cart'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid item index'
    ]);
    exit;
}

// Store removed item info for response
$removedItem = [
    'service_id' => $_SESSION['cart'][$index]['service_id'],
    'name' => $_SESSION['cart'][$index]['name'] ?? 'Unknown service',
    'quantity' => $_SESSION['cart'][$index]['quantity']
];

// Remove item from cart
array_splice($_SESSION['cart'], $index, 1);

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Item removed from cart',
    'removed_item' => $removedItem
]);
?>