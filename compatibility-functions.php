<?php
/**
 * Helper functions to ensure compatibility between owner and client interfaces
 * Include this file in both user_index.php and owner_index.php
 */

/**
 * Creates a notification for both client and owner
 * 
 * @param PDO $db Database connection
 * @param int|null $userId ID of the user to notify (usually client or admin)
 * @param string $type Notification type (booking, service, etc.)
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|null $relatedId Related ID (booking_id, service_id, etc.)
 * @param int|null $ownerId ID of the owner to notify (for owner notifications)
 * @return bool Success status
 */
function createNotification($db, $userId, $type, $title, $message, $relatedId = null, $ownerId = null) {
    try {
        if ($userId) {
            $stmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, related_id, is_read)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$userId, $type, $title, $message, $relatedId]);
        }
        
        if ($ownerId) {
            $stmt = $db->prepare("
                INSERT INTO notifications (owner_id, type, title, message, related_id, is_read)
                VALUES (?, ?, ?, ?, ?, 0)
            ");
            $stmt->execute([$ownerId, $type, $title, $message, $relatedId]);
        }
        
        return true;
    } catch (PDOException $e) {
        // Log error
        error_log("Error creating notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates booking notifications for both client, owner, and admin
 * 
 * @param PDO $db Database connection
 * @param int $bookingId ID of the booking
 * @return bool Success status
 */
function createBookingNotificationMultiOwner($db, $bookingId) {
    try {
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
        $booking = $bookingStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$booking) {
            return false;
        }
        
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
            $booking['owner_id']
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
        
        return true;
    } catch (PDOException $e) {
        // Log error
        error_log("Error creating multi-owner notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Creates service notifications when a service is added or updated
 * 
 * @param PDO $db Database connection
 * @param int $serviceId ID of the service
 * @param int $ownerId ID of the owner
 * @return bool Success status
 */
function createServiceNotificationMultiOwner($db, $serviceId, $ownerId) {
    try {
        // Get service information
        $serviceStmt = $db->prepare("SELECT name, price FROM services WHERE id = ?");
        $serviceStmt->execute([$serviceId]);
        $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$service) {
            return false;
        }
        
        // Create notification for the owner
        createNotification(
            $db,
            $ownerId, 
            'service',
            'New Service Added',
            "Your service '{$service['name']}' has been added at price " . number_format($service['price'], 2),
            $serviceId,
            $ownerId
        );
                  
        // Create notification for admins
        $adminStmt = $db->prepare("SELECT id FROM users WHERE role = 'admin'");
        $adminStmt->execute();
        $adminUsers = $adminStmt->fetchAll(PDO::FETCH_COLUMN);
        
        foreach ($adminUsers as $adminId) {
            createNotification(
                $db, 
                $adminId, 
                'service', 
                'New Service Added', 
                "A new service '{$service['name']}' has been added at price " . number_format($service['price'], 2), 
                $serviceId
            );
        }
        
        return true;
    } catch (PDOException $e) {
        // Log error
        error_log("Error creating service notification: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent notifications for a user or owner
 * 
 * @param PDO $db Database connection
 * @param int $userId ID of the user or owner
 * @param int $limit Maximum number of notifications to return
 * @return array Array of notifications
 */
function getRecentNotifications($db, $userId, $limit = 10) {
    try {
        // Check if user is owner or client/admin
        $userStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return [];
        }
        
        $isOwner = ($user['role'] === 'owner');
        
        // Get notifications
        if ($isOwner) {
            $stmt = $db->prepare("
                SELECT id, type, title, message, related_id, is_read, 
                       created_at, TIME_FORMAT(created_at, '%h:%i %p') as time
                FROM notifications 
                WHERE owner_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
        } else {
            $stmt = $db->prepare("
                SELECT id, type, title, message, related_id, is_read, 
                       created_at, TIME_FORMAT(created_at, '%h:%i %p') as time
                FROM notifications 
                WHERE user_id = ?
                ORDER BY created_at DESC
                LIMIT ?
            ");
        }
        
        $stmt->execute([$userId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting notifications: " . $e->getMessage());
        return [];
    }
}

/**
 * Count unread notifications for a user or owner
 * 
 * @param PDO $db Database connection
 * @param int $userId ID of the user or owner
 * @return int Number of unread notifications
 */
function countUnreadNotifications($db, $userId) {
    try {
        // Check if user is owner or client/admin
        $userStmt = $db->prepare("SELECT role FROM users WHERE id = ?");
        $userStmt->execute([$userId]);
        $user = $userStmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            return 0;
        }
        
        $isOwner = ($user['role'] === 'owner');
        
        // Count notifications
        if ($isOwner) {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM notifications 
                WHERE owner_id = ? AND is_read = 0
            ");
        } else {
            $stmt = $db->prepare("
                SELECT COUNT(*) 
                FROM notifications 
                WHERE user_id = ? AND is_read = 0
            ");
        }
        
        $stmt->execute([$userId]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        // Log error
        error_log("Error counting notifications: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get current user data
 * 
 * @param PDO $db Database connection
 * @return array|null User data or null if not logged in
 */
function getCurrentUser($db) {
    if (!isset($_SESSION['user_id'])) {
        return null;
    }
    
    try {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting current user: " . $e->getMessage());
        return null;
    }
}

/**
 * Require login for specific roles
 * 
 * @param string|array $roles Role(s) required ('admin', 'client', 'owner')
 * @return void
 */
function requireLogin($roles = null) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_message'] = "Please log in to access this page.";
        header("Location: login.php");
        exit();
    }
    
    if ($roles !== null) {
        if (!isset($_SESSION['role'])) {
            $_SESSION['error_message'] = "Invalid session. Please log in again.";
            header("Location: login.php");
            exit();
        }
        
        $allowedRoles = is_array($roles) ? $roles : [$roles];
        
        if (!in_array($_SESSION['role'], $allowedRoles)) {
            $_SESSION['error_message'] = "You don't have permission to access this page.";
            if ($_SESSION['role'] === 'client') {
                header("Location: user_index.php");
            } elseif ($_SESSION['role'] === 'owner') {
                header("Location: owner_index.php");
            } else {
                header("Location: index.php");
            }
            exit();
        }
    }
}

/**
 * Require admin login
 */
function requireAdminLogin() {
    requireLogin('admin');
}

/**
 * Require owner login
 */
function requireOwnerLogin() {
    requireLogin('owner');
}

/**
 * Require client login
 */
function requireClientLogin() {
    requireLogin('client');
}

/**
 * Get system setting value
 * 
 * @param PDO $db Database connection
 * @param string $key Setting key
 * @param string $default Default value if setting not found
 * @return string Setting value
 */
function getSystemSetting($db, $key, $default = '') {
    try {
        $stmt = $db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['setting_value'] : $default;
    } catch (PDOException $e) {
        // Log error
        error_log("Error getting system setting: " . $e->getMessage());
        return $default;
    }
}

/**
 * Format date for display
 * 
 * @param string $date Date string
 * @param string $format Format string
 * @return string Formatted date
 */
function formatDate($date, $format = 'F j, Y') {
    return date($format, strtotime($date));
}

/**
 * Format time for display
 * 
 * @param string $time Time string
 * @param string $format Format string
 * @return string Formatted time
 */
function formatTime($time, $format = 'g:i A') {
    return date($format, strtotime($time));
}
