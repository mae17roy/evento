<?php
// Include this file after config.php to add enhanced notification functionality

// Only define these functions if they don't already exist
if (!function_exists('getUnreadNotificationsMultiOwner')) {
    /**
     * Get recent unread notifications for current user/owner
     * @param PDO $db Database connection
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notification data
     */
    function getUnreadNotificationsMultiOwner($db, $limit = 5) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? 'client';
        
        // Different query based on user role
        if ($userRole == 'owner') {
            $stmt = $db->prepare("
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.created_at
                FROM notifications n
                WHERE (n.owner_id = ? OR n.user_id = ?) AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$userId, $userId]);
        } else {
            $stmt = $db->prepare("
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.created_at
                FROM notifications n
                WHERE n.user_id = ? AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$userId]);
        }
        
        $notifications = [];
        
        foreach ($stmt->fetchAll() as $notification) {
            $timeAgo = time() - strtotime($notification['created_at']);
            $timeString = formatTimeAgo($timeAgo);
            
            $notifications[] = [
                'id' => $notification['id'],
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'time' => $timeString,
                'date' => $notification['created_at'],
                'booking_id' => $notification['type'] === 'booking' ? $notification['related_id'] : null,
                'service_id' => $notification['type'] === 'service' ? $notification['related_id'] : null
            ];
        }
        
        return $notifications;
    }
}

if (!function_exists('getAllNotificationsMultiOwner')) {
    /**
     * Get all notifications for current user/owner with pagination
     * @param PDO $db Database connection
     * @param int $page Current page number
     * @param int $perPage Number of items per page
     * @return array Array containing notifications and pagination data
     */
    function getAllNotificationsMultiOwner($db, $page = 1, $perPage = 20) {
        if (!isset($_SESSION['user_id'])) {
            return [
                'notifications' => [],
                'total' => 0,
                'total_pages' => 0,
                'current_page' => 1
            ];
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? 'client';
        
        // Get total notifications count based on role
        if ($userRole == 'owner') {
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM notifications n
                WHERE n.owner_id = ? OR n.user_id = ?
            ");
            $countStmt->execute([$userId, $userId]);
        } else {
            $countStmt = $db->prepare("
                SELECT COUNT(*) as total
                FROM notifications n
                WHERE n.user_id = ?
            ");
            $countStmt->execute([$userId]);
        }
        
        $total = $countStmt->fetch()['total'];
        
        // Calculate total pages
        $totalPages = ceil($total / $perPage);
        $page = max(1, min($page, $totalPages));
        $offset = ($page - 1) * $perPage;
        
        // Get notifications for current page based on role
        if ($userRole == 'owner') {
            // Build query with hardcoded LIMIT and OFFSET
            $query = "
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.is_read, n.created_at,
                      CASE WHEN n.owner_id = ? THEN 'owner' ELSE 'user' END as notification_for
                FROM notifications n
                WHERE n.owner_id = ? OR n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([$userId, $userId, $userId]);
        } else {
            // Build query with hardcoded LIMIT and OFFSET
            $query = "
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.is_read, n.created_at,
                      'user' as notification_for
                FROM notifications n
                WHERE n.user_id = ?
                ORDER BY n.created_at DESC
                LIMIT $perPage OFFSET $offset
            ";
            $stmt = $db->prepare($query);
            $stmt->execute([$userId]);
        }
        
        $notifications = [];
        
        foreach ($stmt->fetchAll() as $notification) {
            $timeAgo = time() - strtotime($notification['created_at']);
            $timeString = formatTimeAgo($timeAgo);
            
            $notifications[] = [
                'id' => $notification['id'],
                'type' => $notification['type'],
                'title' => $notification['title'],
                'message' => $notification['message'],
                'time' => $timeString,
                'date' => $notification['created_at'],
                'is_read' => (bool)$notification['is_read'],
                'notification_for' => $notification['notification_for'],
                'booking_id' => $notification['type'] === 'booking' ? $notification['related_id'] : null,
                'service_id' => $notification['type'] === 'service' ? $notification['related_id'] : null
            ];
        }
        
        return [
            'notifications' => $notifications,
            'total' => $total,
            'total_pages' => $totalPages,
            'current_page' => $page
        ];
    }
}

if (!function_exists('createServiceNotificationMultiOwner')) {
    /**
     * Create a service notification for owner and/or admin
     * @param PDO $db Database connection
     * @param int $serviceId Service ID
     * @param int $ownerId Owner ID who added the service
     * @return bool True if successful
     */
    function createServiceNotificationMultiOwner($db, $serviceId, $ownerId) {
        try {
            // Get service information
            $stmt = $db->prepare("
                SELECT s.id, s.name, s.price, s.owner_id, u.name as owner_name
                FROM services s
                JOIN users u ON s.owner_id = u.id
                WHERE s.id = ?
            ");
            
            $stmt->execute([$serviceId]);
            $service = $stmt->fetch();
            
            if (!$service) {
                return false;
            }
            
            // Create notification message
            $title = 'New Service Added';
            $message = "New service '{$service['name']}' has been added at price " . number_format($service['price'], 2);
            
            // Get admin users to notify
            $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
            $adminStmt->execute();
            $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $success = true;
            
            // Notify owner
            $ownerNotification = createNotification(
                $db, 
                $ownerId, 
                'service', 
                $title, 
                $message, 
                $serviceId,
                $ownerId
            );
            $success = $success && $ownerNotification;
            
            // Notify all admins
            foreach ($adminUsers as $adminId) {
                $adminNotification = createNotification(
                    $db, 
                    $adminId, 
                    'service', 
                    $title, 
                    "Owner {$service['owner_name']} added new service '{$service['name']}' at price " . number_format($service['price'], 2), 
                    $serviceId
                );
                $success = $success && $adminNotification;
            }
            
            return $success;
        } catch (PDOException $e) {
            // Log error
            error_log("Error creating service notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('createBookingNotificationMultiOwner')) {
    /**
     * Create a booking notification for owner and customer
     * @param PDO $db Database connection
     * @param int $bookingId Booking ID
     * @return bool True if successful
     */
    function createBookingNotificationMultiOwner($db, $bookingId) {
        try {
            // Get booking information with owner details
            $stmt = $db->prepare("
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
            
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                return false;
            }
            
            $formattedDate = date('F j, Y', strtotime($booking['booking_date']));
            $formattedTime = date('g:i A', strtotime($booking['booking_time']));
            
            // Create notification for the owner
            $ownerTitle = 'New Booking Received';
            $ownerMessage = "New booking for '{$booking['service_name']}' on {$formattedDate} at {$formattedTime} from {$booking['customer_name']}.";
            $ownerNotification = createNotification(
                $db, 
                null, 
                'booking', 
                $ownerTitle, 
                $ownerMessage, 
                $bookingId,
                $booking['owner_id']
            );
            
            // Create notification for the customer
            $customerTitle = 'Booking Confirmation';
            $customerMessage = "Your booking for '{$booking['service_name']}' on {$formattedDate} at {$formattedTime} has been received and is pending confirmation.";
            $customerNotification = createNotification(
                $db, 
                $booking['user_id'], 
                'booking', 
                $customerTitle, 
                $customerMessage, 
                $bookingId
            );
            
            // Get admin users to notify
            $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
            $adminStmt->execute();
            $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
            
            $success = $ownerNotification && $customerNotification;
            
            // Notify all admins
            foreach ($adminUsers as $adminId) {
                $adminTitle = 'New Booking';
                $adminMessage = "{$booking['customer_name']} booked '{$booking['service_name']}' from owner {$booking['owner_name']} on {$formattedDate} at {$formattedTime}.";
                $adminNotification = createNotification(
                    $db, 
                    $adminId, 
                    'booking', 
                    $adminTitle, 
                    $adminMessage, 
                    $bookingId
                );
                $success = $success && $adminNotification;
            }
            
            return $success;
        } catch (PDOException $e) {
            // Log error
            error_log("Error creating booking notification: " . $e->getMessage());
            return false;
        }
    }
}

if (!function_exists('createBookingStatusNotificationMultiOwner')) {
    /**
     * Create a booking status change notification for all parties
     * @param PDO $db Database connection
     * @param int $bookingId Booking ID
     * @param string $oldStatus Old booking status
     * @param string $newStatus New booking status
     * @return bool True if successful, false if not
     */
    function createBookingStatusNotificationMultiOwner($db, $bookingId, $oldStatus, $newStatus) {
        try {
            // Get booking information with owner details
            $stmt = $db->prepare("
                SELECT b.id, b.user_id, u.name as customer_name, s.name as service_name,
                       s.owner_id, ou.name as owner_name
                FROM bookings b
                JOIN users u ON b.user_id = u.id
                JOIN booking_items bi ON bi.booking_id = b.id
                JOIN services s ON bi.service_id = s.id
                JOIN users ou ON s.owner_id = ou.id
                WHERE b.id = ?
                LIMIT 1
            ");
            
            $stmt->execute([$bookingId]);
            $booking = $stmt->fetch();
            
            if (!$booking) {
                return false;
            }
            
            // Create notification messages based on status change
            $customerTitle = '';
            $customerMessage = '';
            $ownerTitle = '';
            $ownerMessage = '';
            
            switch ($newStatus) {
                case 'confirmed':
                    $customerTitle = 'Booking Confirmed';
                    $customerMessage = "Your booking for '{$booking['service_name']}' has been confirmed.";
                    $ownerTitle = 'Booking Confirmed';
                    $ownerMessage = "Booking for '{$booking['service_name']}' by {$booking['customer_name']} has been confirmed.";
                    break;
                case 'cancelled':
                    $customerTitle = 'Booking Cancelled';
                    $customerMessage = "Your booking for '{$booking['service_name']}' has been cancelled.";
                    $ownerTitle = 'Booking Cancelled';
                    $ownerMessage = "Booking for '{$booking['service_name']}' by {$booking['customer_name']} has been cancelled.";
                    break;
                case 'completed':
                    $customerTitle = 'Booking Completed';
                    $customerMessage = "Your booking for '{$booking['service_name']}' has been marked as completed.";
                    $ownerTitle = 'Booking Completed';
                    $ownerMessage = "Booking for '{$booking['service_name']}' by {$booking['customer_name']} has been marked as completed.";
                    break;
                default:
                    $customerTitle = 'Booking Status Updated';
                    $customerMessage = "Your booking for '{$booking['service_name']}' status has been updated to {$newStatus}.";
                    $ownerTitle = 'Booking Status Updated';
                    $ownerMessage = "Booking for '{$booking['service_name']}' by {$booking['customer_name']} status has been updated to {$newStatus}.";
            }
            
            // Create notification for the customer
            $customerNotification = createNotification(
                $db, 
                $booking['user_id'], 
                'booking', 
                $customerTitle, 
                $customerMessage, 
                $bookingId
            );
            
            // Create notification for the owner
            $ownerNotification = createNotification(
                $db, 
                null, 
                'booking', 
                $ownerTitle, 
                $ownerMessage, 
                $bookingId,
                $booking['owner_id']
            );
            
            return $customerNotification && $ownerNotification;
        } catch (PDOException $e) {
            // Log error
            error_log("Error creating booking status notification: " . $e->getMessage());
            return false;
        }
    }
}

// Add the countUnreadNotificationsMultiOwner function if it doesn't exist
if (!function_exists('countUnreadNotificationsMultiOwner')) {
    /**
     * Count unread notifications for current user/owner
     * @param PDO $db Database connection
     * @return int Number of unread notifications
     */
    function countUnreadNotificationsMultiOwner($db) {
        if (!isset($_SESSION['user_id'])) {
            return 0;
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? 'client';
        
        if ($userRole == 'owner') {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM notifications
                WHERE (owner_id = ? OR user_id = ?) AND is_read = 0
            ");
            $stmt->execute([$userId, $userId]);
        } else {
            $stmt = $db->prepare("
                SELECT COUNT(*) as count
                FROM notifications
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$userId]);
        }
        
        return $stmt->fetch()['count'];
    }
}

if (!function_exists('markAllNotificationsAsReadMultiOwner')) {
    /**
     * Mark all notifications as read for the current user/owner
     * @param PDO $db Database connection
     * @return bool True if successful, false if not
     */
    function markAllNotificationsAsReadMultiOwner($db) {
        if (!isset($_SESSION['user_id'])) {
            return false;
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? 'client';
        
        try {
            // Different query based on user role
            if ($userRole == 'owner') {
                $stmt = $db->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE (owner_id = ? OR user_id = ?) AND is_read = 0
                ");
                $result = $stmt->execute([$userId, $userId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE notifications
                    SET is_read = 1
                    WHERE user_id = ? AND is_read = 0
                ");
                $result = $stmt->execute([$userId]);
            }
            
            return $result;
        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            return false;
        }
    }
}
/**
 * Helper function to format time strings
 */
if (!function_exists('formatTimeAgo')) {
    function formatTimeAgo($timeAgo) {
        if ($timeAgo < 60) {
            return 'just now';
        } elseif ($timeAgo < 3600) {
            return floor($timeAgo / 60) . ' min ago';
        } elseif ($timeAgo < 86400) {
            return floor($timeAgo / 3600) . ' hours ago';
        } else {
            return floor($timeAgo / 86400) . ' days ago';
        }
    }
}