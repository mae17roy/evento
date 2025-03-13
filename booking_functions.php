<?php

// After successfully inserting a new booking into the database
if ($stmt->execute()) {
    $bookingId = $db->lastInsertId();
    
    // Create notifications for the new booking
    if (function_exists('createBookingNotificationMultiOwner')) {
        createBookingNotificationMultiOwner($db, $bookingId);
    } else {
        // Fallback if the multi-owner function doesn't exist
        // Get booking information with owner details
        $bookingStmt = $db->prepare("
            SELECT b.id, b.user_id, b.booking_date, b.booking_time, u.name as customer_name, 
                   s.name as service_name, s.owner_id, ou.name as owner_name
            FROM bookings b
            JOIN users u ON b.user_id = u.id
            JOIN booking_items bi ON bi.booking_id = b.id
            JOIN services s ON bi.service_id = s.id
            JOIN users ou ON s.owner_id = ou.id
            WHERE b.id = ?
            LIMIT 1
        ");
        
        $bookingStmt->execute([$bookingId]);
        $booking = $bookingStmt->fetch();
        
        $formattedDate = date('F j, Y', strtotime($booking['booking_date']));
        $formattedTime = date('g:i A', strtotime($booking['booking_time']));
        
        // Create notification for the owner
        createNotification(
            $db, 
            null, 
            'booking', 
            'New Booking Received', 
            "New booking for '{$booking['service_name']}' on {$formattedDate} at {$formattedTime} from {$booking['customer_name']}.", 
            $bookingId,
            $booking['owner_id']  // This assumes your createNotification function supports the owner_id parameter
        );
        
        // Create notification for the customer
        createNotification(
            $db, 
            $booking['user_id'], 
            'booking', 
            'Booking Confirmation', 
            "Your booking for '{$booking['service_name']}' on {$formattedDate} at {$formattedTime} has been received and is pending confirmation.", 
            $bookingId
        );
        
        // Notify admins
        $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
        $adminStmt->execute();
        $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($adminUsers as $adminId) {
            createNotification(
                $db, 
                $adminId, 
                'booking', 
                'New Booking', 
                "{$booking['customer_name']} booked '{$booking['service_name']}' from owner {$booking['owner_name']} on {$formattedDate} at {$formattedTime}.", 
                $bookingId
            );
        }
    }
    
    $_SESSION['success_message'] = "Booking created successfully!";
    header("Location: bookings.php");
    exit();
} else {
    $_SESSION['error_message'] = "Error creating booking. Please try again.";
}

// Booking functions
function create_booking($user_id, $booking_date, $booking_time, $total_amount, $special_requests) {
    global $conn;
    
    $sql = "INSERT INTO bookings (user_id, booking_date, booking_time, total_amount, special_requests) 
            VALUES (?, ?, ?, ?, ?)";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "issds", $user_id, $booking_date, $booking_time, $total_amount, $special_requests);
        
        if(mysqli_stmt_execute($stmt)) {
            return mysqli_insert_id($conn);
        }
    }
    
    return false;
}

function add_booking_item($booking_id, $service_id, $quantity, $price) {
    global $conn;
    
    $sql = "INSERT INTO booking_items (booking_id, service_id, quantity, price) 
            VALUES (?, ?, ?, ?)";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "iiid", $booking_id, $service_id, $quantity, $price);
        
        if(mysqli_stmt_execute($stmt)) {
            return true;
        }
    }
    
    return false;
}

function get_user_bookings($user_id) {
    global $conn;
    
    $sql = "SELECT b.*, COUNT(bi.id) as num_services 
            FROM bookings b 
            LEFT JOIN booking_items bi ON b.id = bi.booking_id 
            WHERE b.user_id = ? 
            GROUP BY b.id 
            ORDER BY b.created_at DESC";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $user_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            $bookings = [];
            while($row = mysqli_fetch_assoc($result)) {
                $bookings[] = $row;
            }
            
            return $bookings;
        }
    }
    
    return [];
}

// Function to check availability
function check_availability($booking_date, $booking_time) {
    global $conn;
    
    // Convert date to day of week (0 = Sunday, 1 = Monday, etc.)
    $day_of_week = date('w', strtotime($booking_date));
    
    $sql = "SELECT * FROM availability 
            WHERE day_of_week = ? 
            AND ? BETWEEN start_time AND end_time 
            AND is_available = 1";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "is", $day_of_week, $booking_time);
        
        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            return mysqli_stmt_num_rows($stmt) > 0;
        }
    }
    
    return false;
}
?>