<?php
// Ensure no extra whitespace or HTML before or after the opening PHP tag

/**
 * Authentication and User Management Functions
 */

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Require admin login for protected pages
 * Redirects to login page if not logged in or not an admin
 */
function requireAdminLogin() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in and is an admin
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
        // Store the attempted page for potential redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Store an error message to show on login page
        $_SESSION['error_message'] = "You must be an admin to access this page.";
        
        // Redirect to login page
        header("Location: index.php");
        exit();
    }
}


/**
 * Process user login
 * 
 * @param PDO $db Database connection
 * @param string $email User's email
 * @param string $password User's password
 * @return bool|array False if login fails, user data array if successful
 */
function processLogin($db, $email, $password) {
    try {
        // Prepare SQL to prevent SQL injection
        $stmt = $db->prepare("
            SELECT id, name, email, role, password 
            FROM users 
            WHERE email = :email
        ");
        $stmt->execute(['email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // Check if user exists and password matches
        if ($user && $user['password'] === $password) {
            // Start session and store user details
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['user_role'] = $user['role'];

            // Log successful login
            error_log("Successful login: {$user['email']} (ID: {$user['id']}, Role: {$user['role']})");

            return $user;
        }
        
        // Login failed
        error_log("Failed login attempt for email: $email");
        return false;
    } catch (PDOException $e) {
        // Log database errors
        error_log("Login error: " . $e->getMessage());
        return false;
    }
}

/**
 * Register a new user
 * 
 * @param PDO $db Database connection
 * @param string $name User's full name
 * @param string $email User's email
 * @param string $password User's password
 * @param string $role User role (client, owner, admin)
 * @param string|null $phone Optional phone number
 * @param string|null $address Optional address
 * @param string|null $business_name Optional business name for owners
 * @return int|false User ID if registration succeeds, false otherwise
 */
function registerUser($db, $name, $email, $password, $role, $phone = null, $address = null, $business_name = null) {
    try {
        // Check if email already exists
        $checkStmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
        $checkStmt->execute(['email' => $email]);
        if ($checkStmt->fetchColumn() > 0) {
            return false; // Email already exists
        }

        // Prepare insert statement
        $stmt = $db->prepare("
            INSERT INTO users 
            (name, email, password, role, phone, address, business_name, created_at) 
            VALUES 
            (:name, :email, :password, :role, :phone, :address, :business_name, NOW())
        ");

        $result = $stmt->execute([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'role' => $role,
            'phone' => $phone,
            'address' => $address,
            'business_name' => $business_name
        ]);

        // Return the new user's ID if successful
        return $result ? $db->lastInsertId() : false;
    } catch (PDOException $e) {
        // Log any database errors
        error_log("Registration error: " . $e->getMessage());
        return false;
    }
}

/**
 * Retrieve current user details
 * 
 * @param PDO $db Database connection
 * @return array|false User details or false if not found
 */
function getCurrentUser($db) {
    if (!isset($_SESSION['user_id'])) {
        return false;
    }

    try {
        $stmt = $db->prepare("
            SELECT id, name, email, role, phone, address, business_name 
            FROM users 
            WHERE id = :user_id
        ");
        $stmt->execute(['user_id' => $_SESSION['user_id']]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Error fetching current user: " . $e->getMessage());
        return false;
    }
}

/**
 * Require owner login for protected pages
 * Redirects to login page if not logged in or not an owner
 */
function requireOwnerLogin() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in and is an owner
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'owner') {
        // Store the attempted page for potential redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Store an error message to show on login page
        $_SESSION['error_message'] = "You must be a service provider to access this page.";
        
        // Redirect to login page
        header("Location: index.php");
        exit();
    }
}

/**
 * Require client login for protected pages
 * Redirects to login page if not logged in or not a client
 */
function requireClientLogin() {
    // Start session if not already started
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Check if user is logged in and is a client
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role']) || $_SESSION['user_role'] !== 'client') {
        // Store the attempted page for potential redirect after login
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        
        // Store an error message to show on login page
        $_SESSION['error_message'] = "You must be logged in as a client to access this page.";
        
        // Redirect to login page
        header("Location: index.php");
        exit();
    }
}
/**
 * Get recent notifications for a user
 * 
 * @param PDO $db Database connection
 * @param int $user_id ID of the user
 * @return array Recent notifications
 */
function getRecentNotifications($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                id,
                title,
                message,
                type,
                related_id,
                is_read,
                created_at,
                DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as time
            FROM notifications
            WHERE user_id = :user_id
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Recent notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Count unread notifications for a user
 * 
 * @param PDO $db Database connection
 * @param int $user_id ID of the user
 * @return int Number of unread notifications
 */
function countUnreadNotifications($db, $user_id) {
    try {
        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute(['user_id' => $user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Unread notifications count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Get recent notifications for an owner, including those with owner_id field
 * 
 * @param PDO $db Database connection
 * @return array Recent notifications
 */
function getRecentNotificationsMultiOwner($db) {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return [];
        }

        $owner_id = $_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT 
                id,
                title,
                message,
                type,
                related_id,
                is_read,
                created_at,
                DATE_FORMAT(created_at, '%b %d, %Y %h:%i %p') as time
            FROM notifications
            WHERE (user_id = :owner_id OR owner_id = :owner_id)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Recent owner notifications error: " . $e->getMessage());
        return [];
    }
}

/**
 * Count unread notifications for an owner, including those with owner_id field
 * 
 * @param PDO $db Database connection
 * @return int Number of unread notifications
 */
function countUnreadNotificationsMultiOwner($db) {
    try {
        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!isset($_SESSION['user_id'])) {
            return 0;
        }

        $owner_id = $_SESSION['user_id'];

        $stmt = $db->prepare("
            SELECT COUNT(*) 
            FROM notifications 
            WHERE (user_id = :owner_id OR owner_id = :owner_id) AND is_read = 0
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        error_log("Unread owner notifications count error: " . $e->getMessage());
        return 0;
    }
}

/**
 * Create a notification
 * 
 * @param PDO $db Database connection
 * @param int $user_id User to notify
 * @param string $type Notification type
 * @param string $title Notification title
 * @param string $message Notification message
 * @param int|null $related_id Related entity ID
 * @param int|null $owner_id Optional owner ID
 * @return int|false Notification ID or false on failure
 */
function createNotification($db, $user_id, $type, $title, $message, $related_id = null, $owner_id = null) {
    try {
        $stmt = $db->prepare("
            INSERT INTO notifications 
            (user_id, owner_id, type, title, message, related_id, is_read, created_at) 
            VALUES 
            (:user_id, :owner_id, :type, :title, :message, :related_id, 0, NOW())
        ");

        $stmt->execute([
            'user_id' => $user_id,
            'owner_id' => $owner_id,
            'type' => $type,
            'title' => $title,
            'message' => $message,
            'related_id' => $related_id
        ]);

        return $db->lastInsertId();
    } catch (PDOException $e) {
        error_log("Notification creation error: " . $e->getMessage());
        return false;
    }
}
/**
 * Retrieve a system setting from the system_settings table
 * 
 * @param PDO $db Database connection
 * @param string $setting_key The key of the setting to retrieve
 * @param mixed $default_value Optional default value if setting is not found
 * @return mixed The value of the setting or the default value
 */
function getSystemSetting($db, $setting_key, $default_value = null) {
    try {
        // Prepare SQL to prevent SQL injection
        $stmt = $db->prepare("
            SELECT setting_value 
            FROM system_settings 
            WHERE setting_key = :setting_key
        ");
        
        // Execute the query
        $stmt->execute(['setting_key' => $setting_key]);
        
        // Fetch the result
        $result = $stmt->fetchColumn();
        
        // Return the setting value or default if not found
        return $result !== false ? $result : $default_value;
    } catch (PDOException $e) {
        // Log any database errors
        error_log("Error retrieving system setting '$setting_key': " . $e->getMessage());
        
        // Return the default value in case of an error
        return $default_value;
    }
}

/**
 * Update a system setting in the system_settings table
 * 
 * @param PDO $db Database connection
 * @param string $setting_key The key of the setting to update
 * @param mixed $setting_value The new value for the setting
 * @param string|null $setting_description Optional description for the setting
 * @return bool True if update successful, false otherwise
 */
function updateSystemSetting($db, $setting_key, $setting_value, $setting_description = null) {
    try {
        // Prepare the SQL statement
        if ($setting_description !== null) {
            // Update with description
            $stmt = $db->prepare("
                INSERT INTO system_settings 
                (setting_key, setting_value, setting_description, updated_at) 
                VALUES (:setting_key, :setting_value, :setting_description, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = :setting_value, 
                setting_description = :setting_description, 
                updated_at = NOW()
            ");
            $stmt->execute([
                'setting_key' => $setting_key,
                'setting_value' => $setting_value,
                'setting_description' => $setting_description
            ]);
        } else {
            // Update without description
            $stmt = $db->prepare("
                INSERT INTO system_settings 
                (setting_key, setting_value, updated_at) 
                VALUES (:setting_key, :setting_value, NOW())
                ON DUPLICATE KEY UPDATE 
                setting_value = :setting_value, 
                updated_at = NOW()
            ");
            $stmt->execute([
                'setting_key' => $setting_key,
                'setting_value' => $setting_value
            ]);
        }
        
        return true;
    } catch (PDOException $e) {
        // Log any database errors
        error_log("Error updating system setting '$setting_key': " . $e->getMessage());
        return false;
    }
}

/**
 * Delete a system setting from the system_settings table
 * 
 * @param PDO $db Database connection
 * @param string $setting_key The key of the setting to delete
 * @return bool True if deletion successful, false otherwise
 */
function deleteSystemSetting($db, $setting_key) {
    try {
        // Prepare SQL to prevent SQL injection
        $stmt = $db->prepare("
            DELETE FROM system_settings 
            WHERE setting_key = :setting_key
        ");
        
        // Execute the query
        $stmt->execute(['setting_key' => $setting_key]);
        
        return true;
    } catch (PDOException $e) {
        // Log any database errors
        error_log("Error deleting system setting '$setting_key': " . $e->getMessage());
        return false;
    }
}
/**
 * Get dashboard metrics for service providers (owners)
 * 
 * @param PDO $db Database connection
 * @param int $owner_id ID of the service provider
 * @return array Dashboard metrics
 */
function getOwnerDashboardMetrics($db, $owner_id) {
    try {
        $metrics = [];

        // Total services
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_services,
                SUM(CASE WHEN is_available = 1 THEN 1 ELSE 0 END) as active_services
            FROM services
            WHERE owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $serviceMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $metrics['total_services'] = $serviceMetrics['total_services'] ?? 0;
        $metrics['active_services'] = $serviceMetrics['active_services'] ?? 0;
        $metrics['active_services_percent'] = $metrics['total_services'] > 0 
            ? round(($metrics['active_services'] / $metrics['total_services']) * 100, 2) 
            : 0;

        // Bookings and reservations
        $stmt = $db->prepare("
            SELECT 
                COUNT(*) as total_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reservations,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed_bookings,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_bookings,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as pending_percent,
                SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) * 100.0 / COUNT(*) as confirmed_percent
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $bookingMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $metrics['total_bookings'] = $bookingMetrics['total_bookings'] ?? 0;
        $metrics['pending_reservations'] = $bookingMetrics['pending_reservations'] ?? 0;
        $metrics['confirmed_bookings'] = $bookingMetrics['confirmed_bookings'] ?? 0;
        $metrics['completed_bookings'] = $bookingMetrics['completed_bookings'] ?? 0;
        $metrics['pending_percent'] = round($bookingMetrics['pending_percent'] ?? 0, 2);
        $metrics['confirmed_percent'] = round($bookingMetrics['confirmed_percent'] ?? 0, 2);

        // Revenue calculation
        $stmt = $db->prepare("
            SELECT 
                SUM(b.total_amount) as total_revenue,
                SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as completed_revenue
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $revenueMetrics = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $metrics['total_revenue'] = $revenueMetrics['total_revenue'] ?? 0;
        $metrics['completed_revenue'] = $revenueMetrics['completed_revenue'] ?? 0;

        // Customer count
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT b.user_id) as customer_count
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $metrics['customer_count'] = $stmt->fetchColumn() ?? 0;

        // Categories count
        $stmt = $db->prepare("
            SELECT COUNT(DISTINCT category_id) as total_categories
            FROM services
            WHERE owner_id = :owner_id
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $metrics['total_categories'] = $stmt->fetchColumn() ?? 0;

        // Recent activity
        $stmt = $db->prepare("
            (SELECT 
                'booking' as type, 
                b.id, 
                b.status, 
                b.total_amount as amount, 
                b.created_at
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id)
            UNION
            (SELECT 
                'service' as type, 
                s.id, 
                s.availability_status as status, 
                s.price as amount, 
                s.created_at
            FROM services s
            WHERE s.owner_id = :owner_id)
            ORDER BY created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        $metrics['recent_activity'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $metrics;
    } catch (PDOException $e) {
        error_log("Dashboard metrics error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent reservations for a service provider
 * 
 * @param PDO $db Database connection
 * @param int $owner_id ID of the service provider
 * @return array Recent reservations
 */
function getRecentReservations($db, $owner_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                b.id, 
                b.status, 
                b.booking_date, 
                b.booking_time,
                b.created_at,
                s.name as service_name,
                u.name as customer_name,
                u.email as customer_email,
                s.price
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            JOIN users u ON b.user_id = u.id
            WHERE s.owner_id = :owner_id
            ORDER BY b.created_at DESC
            LIMIT 10
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Recent reservations error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get upcoming bookings for a service provider
 * 
 * @param PDO $db Database connection
 * @param int $owner_id ID of the service provider
 * @return array Upcoming bookings
 */
function getUpcomingBookings($db, $owner_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                b.id, 
                b.status, 
                b.booking_date, 
                b.booking_time,
                s.name as service_name,
                u.name as customer_name,
                u.email as customer_email,
                s.price
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            JOIN users u ON b.user_id = u.id
            WHERE s.owner_id = :owner_id
            AND b.booking_date >= CURDATE()
            AND b.status IN ('confirmed', 'pending')
            ORDER BY b.booking_date, b.booking_time
            LIMIT 10
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Upcoming bookings error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get top services for a service provider
 * 
 * @param PDO $db Database connection
 * @param int $owner_id ID of the service provider
 * @return array Top services
 */
function getTopServices($db, $owner_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                s.id,
                s.name,
                s.price,
                s.description,
                COUNT(DISTINCT b.id) as booking_count,
                COALESCE(AVG(r.rating), 0) as avg_rating,
                COUNT(DISTINCT r.id) as review_count
            FROM services s
            LEFT JOIN booking_items bi ON s.id = bi.service_id
            LEFT JOIN bookings b ON bi.booking_id = b.id
            LEFT JOIN reviews r ON s.id = r.service_id
            WHERE s.owner_id = :owner_id
            GROUP BY s.id
            ORDER BY booking_count DESC, avg_rating DESC
            LIMIT 5
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Top services error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent reviews for a service provider
 * 
 * @param PDO $db Database connection
 * @param int $owner_id ID of the service provider
 * @return array Recent reviews
 */
function getRecentReviews($db, $owner_id) {
    try {
        $stmt = $db->prepare("
            SELECT 
                r.id,
                r.rating,
                r.comment,
                r.created_at,
                u.name as reviewer_name,
                s.name as service_name
            FROM reviews r
            JOIN services s ON r.service_id = s.id
            JOIN users u ON r.user_id = u.id
            WHERE s.owner_id = :owner_id
            ORDER BY r.created_at DESC
            LIMIT 5
        ");
        $stmt->execute(['owner_id' => $owner_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("Recent reviews error: " . $e->getMessage());
        return false;
    }
}
/**
 * Require admin/owner login for restricted pages
 * 
 * This function checks if:
 * 1. A user is logged in
 * 2. The user has admin/owner permissions
 * 
 * If not, it redirects to the login page or shows an error
 */
function requireAdminOwnerLogin() {
    // Start session if not already started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        // Not logged in, redirect to login page
        header("Location: login.php?error=" . urlencode("Please login to access this page"));
        exit();
    }
    
    // Check if user has admin/owner permissions
    $allowedRoles = ['owner', 'admin'];
    $userRole = $_SESSION['user_role'] ?? '';
    
    if (!in_array(strtolower($userRole), $allowedRoles)) {
        // Unauthorized access
        header("Location: unauthorized.php");
        exit();
    }
    
    // Optional: Additional checks can be added here
    // For example, checking account status, permissions, etc.
    
    // Return true if all checks pass
    return true;
}

/**
 * Update booking status
 * 
 * @param PDO $db Database connection
 * @param int $bookingId Booking ID to update
 * @param string $newStatus New status for the booking
 * @return bool True if update successful, false otherwise
 */
function updateBookingStatus($db, $bookingId, $newStatus) {
    // Validate status
    $validStatuses = ['pending', 'confirmed', 'completed', 'cancelled'];
    if (!in_array(strtolower($newStatus), $validStatuses)) {
        return false;
    }
    
    try {
        // Ensure the booking belongs to the current owner's services
        $stmt = $db->prepare("
            UPDATE bookings b
            JOIN booking_items bi ON bi.booking_id = b.id
            JOIN services s ON bi.service_id = s.id
            SET b.status = ?
            WHERE b.id = ? AND s.owner_id = ?
        ");
        
        return $stmt->execute([
            $newStatus, 
            $bookingId, 
            $_SESSION['user_id']
        ]) && $stmt->rowCount() > 0;
    } catch (PDOException $e) {
        // Log the error
        error_log("Error updating booking status: " . $e->getMessage());
        return false;
    }
}

/**
 * Get owner's reservations with advanced filtering and pagination
 * 
 * @param PDO $db Database connection
 * @param int $ownerId Owner's user ID
 * @param array $filters Filtering options
 * @param int $page Page number for pagination
 * @param int $itemsPerPage Number of items per page
 * @return array Associative array with reservations and pagination info
 */
function getOwnerReservations($db, $ownerId, $filters = [], $page = 1, $itemsPerPage = 10) {
    // Base query to get total count and reservations
    $baseQuery = "
        FROM bookings b
        JOIN booking_items bi ON bi.booking_id = b.id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON b.user_id = u.id
        WHERE s.owner_id = :owner_id
    ";
    
    // Apply filters
    $whereConditions = [];
    $params = ['owner_id' => $ownerId];
    
    if (!empty($filters['status'])) {
        $whereConditions[] = "b.status = :status";
        $params['status'] = $filters['status'];
    }
    
    if (!empty($filters['service_id'])) {
        $whereConditions[] = "s.id = :service_id";
        $params['service_id'] = $filters['service_id'];
    }
    
    if (!empty($filters['date'])) {
        $whereConditions[] = "DATE(b.booking_date) = :booking_date";
        $params['booking_date'] = $filters['date'];
    }
    
    if (!empty($filters['search'])) {
        $searchTerm = "%{$filters['search']}%";
        $whereConditions[] = "(
            u.name LIKE :search OR 
            u.email LIKE :search OR 
            s.name LIKE :search OR 
            b.id LIKE :search
        )";
        $params['search'] = $searchTerm;
    }
    
    // Combine where conditions
    if (!empty($whereConditions)) {
        $baseQuery .= " AND " . implode(" AND ", $whereConditions);
    }
    
    // Get total count
    $countQuery = "SELECT COUNT(*) as total " . $baseQuery;
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($params);
    $totalItems = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Calculate pagination
    $totalPages = max(1, ceil($totalItems / $itemsPerPage));
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $itemsPerPage;
    
    // Get reservations with full details
    $query = "
        SELECT 
            b.id, 
            b.booking_date, 
            b.status, 
            b.total_amount,
            u.name as customer_name,
            u.email as customer_email,
            s.name as service_name,
            s.id as service_id
        " . $baseQuery . "
        ORDER BY b.booking_date DESC
        LIMIT :offset, :items_per_page
    ";
    
    $stmt = $db->prepare($query);
    
    // Bind parameters
    foreach ($params as $key => $value) {
        $stmt->bindValue(":{$key}", $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
    }
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':items_per_page', $itemsPerPage, PDO::PARAM_INT);
    
    $stmt->execute();
    $reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    return [
        'reservations' => $reservations,
        'pagination' => [
            'total_items' => $totalItems,
            'total_pages' => $totalPages,
            'current_page' => $page,
            'items_per_page' => $itemsPerPage
        ]
    ];

/**
 * Get monthly booking statistics for an owner
 * 
 * @param PDO $db Database connection
 * @param int $ownerId Owner ID to get statistics for
 * @param string $startDate Start date in Y-m-d format
 * @param string $endDate End date in Y-m-d format
 * @return array Monthly booking statistics
 */
function getMonthlyBookingStats($db, $ownerId, $startDate, $endDate) {
    try {
        // Get total bookings
        $totalStmt = $db->prepare("
            SELECT 
                COUNT(DISTINCT b.id) as total_bookings,
                SUM(CASE WHEN b.status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN b.status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
                SUM(CASE WHEN b.status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN b.status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
                SUM(b.total_amount) as total_revenue,
                SUM(CASE WHEN b.status = 'completed' THEN b.total_amount ELSE 0 END) as completed_revenue
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id
            AND b.booking_date BETWEEN :start_date AND :end_date
        ");
        
        $totalStmt->execute([
            'owner_id' => $ownerId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        $stats = $totalStmt->fetch(PDO::FETCH_ASSOC);
        
        // If no stats found, provide defaults
        if (!$stats) {
            $stats = [
                'total_bookings' => 0,
                'pending' => 0,
                'confirmed' => 0,
                'completed' => 0,
                'cancelled' => 0,
                'total_revenue' => 0,
                'completed_revenue' => 0
            ];
        }
        
        // Get bookings by day
        $dayStmt = $db->prepare("
            SELECT 
                DATE(b.booking_date) as day,
                COUNT(DISTINCT b.id) as num_bookings
            FROM bookings b
            JOIN booking_items bi ON b.id = bi.booking_id
            JOIN services s ON bi.service_id = s.id
            WHERE s.owner_id = :owner_id
            AND b.booking_date BETWEEN :start_date AND :end_date
            GROUP BY DATE(b.booking_date)
        ");
        
        $dayStmt->execute([
            'owner_id' => $ownerId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);
        
        $bookingsByDay = [];
        while ($day = $dayStmt->fetch(PDO::FETCH_ASSOC)) {
            $bookingsByDay[$day['day']] = $day['num_bookings'];
        }
        
        $stats['bookings_by_day'] = $bookingsByDay;
        
        return $stats;
    } catch (PDOException $e) {
        // Log the error for debugging
        error_log("Error in getMonthlyBookingStats: " . $e->getMessage());
        
        // Return empty stats array on error
        return [
            'total_bookings' => 0,
            'pending' => 0,
            'confirmed' => 0,
            'completed' => 0, 
            'cancelled' => 0,
            'total_revenue' => 0,
            'completed_revenue' => 0,
            'bookings_by_day' => []
        ];
    }
}
if (!function_exists('formatTime')) {
    /**
     * Format a time string for display
     * 
     * @param string $time Time string in HH:MM:SS format
     * @return string Formatted time (e.g., "10:30 AM")
     */
    function formatTime($time) {
        return date('g:i A', strtotime($time));
    }
}

if (!function_exists('formatDate')) {
    /**
     * Format a date string for display
     * 
     * @param string $date Date string in YYYY-MM-DD format
     * @return string Formatted date (e.g., "Monday, January 15, 2025")
     */
    function formatDate($date) {
        return date('l, F j, Y', strtotime($date));
    }
}

}
// End of functions.php
?> 