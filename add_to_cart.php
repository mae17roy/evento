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
        'message' => 'Authentication required',
        'redirect' => 'index.php'
    ]);
    exit;
}

// Check if service_id and quantity are set
if (!isset($_POST['service_id'])) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Service ID is required'
    ]);
    exit;
}

$serviceId = (int)$_POST['service_id'];
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

// Validate quantity
if ($quantity <= 0) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Quantity must be greater than zero'
    ]);
    exit;
}

if ($quantity > 10) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Maximum quantity allowed is 10'
    ]);
    exit;
}

// Check if the service exists and is available
try {
    $stmt = $db->prepare("
        SELECT s.id, s.name, s.price, s.image, s.owner_id, u.business_name, u.name as owner_name 
        FROM services s
        LEFT JOIN users u ON s.owner_id = u.id
        WHERE s.id = :id AND s.is_available = 1
    ");
    $stmt->bindParam(':id', $serviceId, PDO::PARAM_INT);
    $stmt->execute();
    $service = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$service) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Service not found or unavailable'
        ]);
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching service: " . $e->getMessage());
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while processing your request'
    ]);
    exit;
}

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Check if service already in cart, update quantity if it is
$found = false;

foreach ($_SESSION['cart'] as &$item) {
    if ($item['service_id'] == $serviceId) {
        // Update quantity (but don't exceed max of 10)
        $newQuantity = min(10, $item['quantity'] + $quantity);
        
        // If trying to add more than allowed, show warning
        if ($newQuantity < $item['quantity'] + $quantity) {
            // Calculate total items in cart
            $cartCount = 0;
            foreach ($_SESSION['cart'] as $cartItem) {
                $cartCount += $cartItem['quantity'];
            }
            
            echo json_encode([
                'status' => 'warning',
                'message' => 'Maximum quantity of 10 reached for this service',
                'cart_count' => $cartCount
            ]);
            exit;
        }
        
        $item['quantity'] = $newQuantity;
        $found = true;
        break;
    }
}

// If not found, add to cart
if (!$found) {
    $_SESSION['cart'][] = [
        'service_id' => $serviceId,
        'name' => $service['name'],
        'price' => $service['price'],
        'image' => $service['image'],
        'owner_id' => $service['owner_id'],
        'business_name' => $service['business_name'],
        'owner_name' => $service['owner_name'],
        'quantity' => $quantity
    ];
}

// Calculate total items in cart
$cartCount = 0;
foreach ($_SESSION['cart'] as $item) {
    $cartCount += $item['quantity'];
}

// Create toast message
$toastMessage = '<div class="flex items-center">
                    <i class="fas fa-check-circle text-green-500 mr-2"></i>
                    <div>
                        <p class="font-medium">' . htmlspecialchars($service['name']) . ' added to cart!</p>
                        <p class="text-sm">' . $quantity . ' x $' . number_format($service['price'], 2) . '</p>
                    </div>
                </div>';

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => $service['name'] . ' added to cart successfully',
    'toast_message' => $toastMessage,
    'cart_count' => $cartCount,
    'cart_update' => true
]);
?>