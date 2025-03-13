<?php
// Cart functions
function initialize_cart() {
    if(!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
}

function add_to_cart($service_id, $quantity = 1) {
    initialize_cart();
    
    $service = get_service_details($service_id);
    if(!$service) {
        return false;
    }
    
    // Check if service already in cart
    foreach($_SESSION['cart'] as &$item) {
        if($item['service_id'] == $service_id) {
            $item['quantity'] += $quantity;
            return true;
        }
    }
    
    // Add new item to cart
    $_SESSION['cart'][] = [
        'service_id' => $service_id,
        'name' => $service['name'],
        'price' => $service['price'],
        'quantity' => $quantity,
        'image' => $service['image']
    ];
    
    return true;
}

function remove_from_cart($index) {
    initialize_cart();
    
    if(isset($_SESSION['cart'][$index])) {
        unset($_SESSION['cart'][$index]);
        $_SESSION['cart'] = array_values($_SESSION['cart']); // Reindex array
        return true;
    }
    
    return false;
}

function get_cart_total() {
    initialize_cart();
    
    $total = 0;
    foreach($_SESSION['cart'] as $item) {
        $total += $item['price'] * $item['quantity'];
    }
    
    return $total;
}

function clear_cart() {
    $_SESSION['cart'] = [];
}

// Checkout process
function process_checkout($user_id, $booking_date, $booking_time, $special_requests) {
    global $conn;
    
    initialize_cart();
    
    if(empty($_SESSION['cart'])) {
        return false;
    }
    
    $total_amount = get_cart_total();
    
    // Start transaction
    mysqli_begin_transaction($conn);
    
    try {
        // Create booking
        $booking_id = create_booking($user_id, $booking_date, $booking_time, $total_amount, $special_requests);
        
        if(!$booking_id) {
            throw new Exception("Failed to create booking");
        }
        
        // Add booking items
        foreach($_SESSION['cart'] as $item) {
            $success = add_booking_item(
                $booking_id, 
                $item['service_id'], 
                $item['quantity'], 
                $item['price']
            );
            
            if(!$success) {
                throw new Exception("Failed to add booking item");
            }
        }
        
        // Commit transaction
        mysqli_commit($conn);
        
        // Clear cart after successful checkout
        clear_cart();
        
        return $booking_id;
    } catch (Exception $e) {
        // Rollback transaction on error
        mysqli_rollback($conn);
        return false;
    }
}
?>