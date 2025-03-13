<?php
/**
 * Migration script to transfer data from old specialized booking tables to unified system
 * This script handles:
 * 1. Migrating specialized booking tables to the unified bookings table
 * 2. Fixing relationships between users, services, and bookings
 * 3. Setting up proper relationships between clients and service owners
 */

// Database connection
$host = 'localhost';
$dbname = 'event_management_system';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Connected to database successfully.\n";
    
    // Start transaction
    $pdo->beginTransaction();
    
    // 1. Add admin user if not exists
    $checkAdmin = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
    if ($checkAdmin->fetchColumn() == 0) {
        echo "Creating admin user...\n";
        $adminStmt = $pdo->prepare("
            INSERT INTO users (name, email, password, phone, role, created_at)
            VALUES ('Admin User', 'admin@evento.com', 'admin123', '12345678', 'admin', NOW())
        ");
        $adminStmt->execute();
        echo "Admin user created.\n";
    }
    
    // 2. Update availability table to link with services
    $availabilityCheck = $pdo->query("SHOW COLUMNS FROM availability LIKE 'service_id'");
    if ($availabilityCheck->rowCount() == 0) {
        echo "Updating availability table structure...\n";
        $pdo->exec("ALTER TABLE availability ADD COLUMN service_id INT NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE availability ADD CONSTRAINT fk_availability_service FOREIGN KEY (service_id) REFERENCES services(id) ON DELETE CASCADE");
        echo "Availability table updated.\n";
    }
    
    // 3. Update photo_studio table to include owner_id
    $photoStudioCheck = $pdo->query("SHOW COLUMNS FROM photo_studio LIKE 'owner_id'");
    if ($photoStudioCheck->rowCount() == 0) {
        echo "Updating photo_studio table structure...\n";
        $pdo->exec("ALTER TABLE photo_studio ADD COLUMN owner_id INT NOT NULL AFTER id");
        $pdo->exec("ALTER TABLE photo_studio ADD CONSTRAINT photo_studio_ibfk_1 FOREIGN KEY (owner_id) REFERENCES users(id) ON DELETE CASCADE");
        
        // Associate existing photo_studio entries with the first owner
        $ownerQuery = $pdo->query("SELECT id FROM users WHERE role = 'owner' LIMIT 1");
        $ownerId = $ownerQuery->fetchColumn();
        if ($ownerId) {
            $pdo->exec("UPDATE photo_studio SET owner_id = $ownerId");
        }
        echo "Photo_studio table updated.\n";
    }
    
    // 4. Tables to migrate from
    $sourceTables = [
        'booking',
        'basic_catering_package',
        'cocktail_reception',
        'deluxe_corporate_package',
        'family_feast_package',
        'premier_event_package',
        'wedding_reception_package',
        'basic_portrait_package',
        'business_branding',
        'corporate_gift_package',
        'deluxe_souviners_ensemble',
        'deluxe_souviner_ensemble',
        'engagement_session',
        'essential_essential_packs',
        'family_adventure_pack',
        'family_session_package',
        'honeymoon_memento_kit',
        'traditional_crafts_collection'
    ];
    
    // 5. Migrate data from each source table
    foreach ($sourceTables as $sourceTable) {
        echo "Checking table $sourceTable...\n";
        
        // Check if table exists
        $stmt = $pdo->query("SHOW TABLES LIKE '$sourceTable'");
        if ($stmt->rowCount() == 0) {
            echo "Table $sourceTable does not exist. Skipping.\n";
            continue;
        }
        
        echo "Migrating data from $sourceTable...\n";
        
        // Get bookings from source table
        $stmt = $pdo->query("SELECT * FROM $sourceTable");
        $bookings = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $migratedCount = 0;
        foreach ($bookings as $booking) {
            // Get or create user based on email
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $userStmt->execute([$booking['client_email']]);
            $user = $userStmt->fetch(PDO::FETCH_ASSOC);
            
            $userId = null;
            if ($user) {
                $userId = $user['id'];
            } else {
                // Create new user with client role
                $insertUserStmt = $pdo->prepare("
                    INSERT INTO users (name, email, password, phone, address, role, created_at)
                    VALUES (?, ?, ?, ?, ?, 'client', NOW())
                ");
                
                // Build address from components if available
                $address = isset($booking['billing_address']) ? $booking['billing_address'] : '';
                if (!empty($booking['city'])) {
                    $address .= ', ' . $booking['city'];
                }
                if (!empty($booking['state'])) {
                    $address .= ', ' . $booking['state'];
                }
                if (!empty($booking['country'])) {
                    $address .= ', ' . $booking['country'];
                }
                $address = trim($address, ', ');
                
                $insertUserStmt->execute([
                    $booking['client_name'],
                    $booking['client_email'],
                    password_hash('changeme', PASSWORD_DEFAULT), // temporary password
                    $booking['client_phone'] ?? null,
                    $address
                ]);
                $userId = $pdo->lastInsertId();
                echo "  Created new user: {$booking['client_name']} (ID: $userId)\n";
            }
            
            // Find corresponding service based on package and table name
            $serviceId = null;
            $serviceQuery = '';
            
            // Try to infer service from table name and package
            $categoryType = '';
            if (strpos($sourceTable, 'catering') !== false) {
                $categoryType = 'Catering';
            } elseif (strpos($sourceTable, 'photo') !== false || strpos($sourceTable, 'portrait') !== false) {
                $categoryType = 'Photo Studio';
            } elseif (strpos($sourceTable, 'sound') !== false || strpos($sourceTable, 'dj') !== false) {
                $categoryType = 'Sound System';
            } elseif (strpos($sourceTable, 'souvenir') !== false || strpos($sourceTable, 'gift') !== false) {
                $categoryType = 'Souvenir';
            }
            
            if (!empty($categoryType)) {
                $serviceQuery = "
                    SELECT s.id FROM services s
                    JOIN categories c ON s.category_id = c.id
                    WHERE c.name LIKE ?
                    ORDER BY s.id
                    LIMIT 1
                ";
                $serviceStmt = $pdo->prepare($serviceQuery);
                $serviceStmt->execute(["%$categoryType%"]);
                $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
                $serviceId = $service ? $service['id'] : null;
            }
            
            // If we couldn't find a service based on category, try by package name
            if (!$serviceId && isset($booking['package'])) {
                $packageName = $booking['package'];
                $serviceQuery = "
                    SELECT id FROM services 
                    WHERE name LIKE ? OR description LIKE ?
                    LIMIT 1
                ";
                $serviceStmt = $pdo->prepare($serviceQuery);
                $packagePattern = "%{$packageName}%";
                $serviceStmt->execute([$packagePattern, $packagePattern]);
                $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
                $serviceId = $service ? $service['id'] : null;
            }
            
            // Last resort - get the first service
            if (!$serviceId) {
                $serviceQuery = "SELECT id FROM services LIMIT 1";
                $serviceStmt = $pdo->prepare($serviceQuery);
                $serviceStmt->execute();
                $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
                $serviceId = $service ? $service['id'] : null;
            }
            
            if (!$serviceId) {
                echo "  Warning: No service found for booking. Using first available service.\n";
                continue;
            }
            
            // Determine booking date and time
            $bookingDate = isset($booking['booking_date']) ? $booking['booking_date'] : 
                           (isset($booking['event_date']) ? $booking['event_date'] : date('Y-m-d'));
            
            $bookingTime = isset($booking['booking_time']) ? $booking['booking_time'] : 
                           (isset($booking['event_time']) ? $booking['event_time'] : date('H:i:s'));
            
            // Insert into bookings table
            $insertBookingStmt = $pdo->prepare("
                INSERT INTO bookings (
                    user_id, total_amount, status, booking_date, booking_time, 
                    special_requests, billing_address, city, state, zip, country, created_at
                ) VALUES (
                    ?, ?, 'pending', ?, ?, ?, ?, ?, ?, ?, ?, ?
                )
            ");
            
            $insertBookingStmt->execute([
                $userId,
                $booking['total_amount'],
                $bookingDate,
                $bookingTime,
                $booking['special_requests'] ?? null,
                $booking['billing_address'] ?? null,
                $booking['city'] ?? null,
                $booking['state'] ?? null,
                $booking['zip'] ?? null,
                $booking['country'] ?? null,
                isset($booking['created_at']) ? $booking['created_at'] : date('Y-m-d H:i:s')
            ]);
            
            $bookingId = $pdo->lastInsertId();
            
            // Add to booking_items
            $insertItemStmt = $pdo->prepare("
                INSERT INTO booking_items (booking_id, service_id, price, created_at)
                VALUES (?, ?, ?, NOW())
            ");
            $insertItemStmt->execute([
                $bookingId,
                $serviceId,
                $booking['total_amount']
            ]);
            
            $migratedCount++;
            echo "  Migrated booking ID {$booking['id']} to new booking ID $bookingId\n";
        }
        
        echo "Completed migrating $migratedCount bookings from $sourceTable.\n";
    }
    
    // 6. Make sure each service has an owner
    echo "Checking services without owners...\n";
    $serviceCheck = $pdo->query("SELECT COUNT(*) FROM services WHERE owner_id IS NULL");
    $serviceCount = $serviceCheck->fetchColumn();
    
    if ($serviceCount > 0) {
        echo "Fixing $serviceCount services without owners...\n";
        
        // Get the first owner
        $ownerQuery = $pdo->query("SELECT id FROM users WHERE role = 'owner' LIMIT 1");
        $ownerId = $ownerQuery->fetchColumn();
        
        if ($ownerId) {
            $updateStmt = $pdo->prepare("UPDATE services SET owner_id = ? WHERE owner_id IS NULL");
            $updateStmt->execute([$ownerId]);
            echo "Updated services with owner ID: $ownerId\n";
        } else {
            echo "Warning: No owner found to assign services to.\n";
        }
    }
    
    // 7. Add business_name to users table if it doesn't exist
    $businessNameCheck = $pdo->query("SHOW COLUMNS FROM users LIKE 'business_name'");
    if ($businessNameCheck->rowCount() == 0) {
        echo "Adding business_name column to users table...\n";
        $pdo->exec("ALTER TABLE users ADD COLUMN business_name VARCHAR(100) DEFAULT NULL AFTER address");
        
        // Set default business names for existing owners
        $ownersStmt = $pdo->query("SELECT id, name FROM users WHERE role = 'owner'");
        $owners = $ownersStmt->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($owners as $owner) {
            $businessName = $owner['name'] . "'s Event Services";
            $updateStmt = $pdo->prepare("UPDATE users SET business_name = ? WHERE id = ?");
            $updateStmt->execute([$businessName, $owner['id']]);
            echo "  Set business name for owner {$owner['name']}: $businessName\n";
        }
    }
    
    // 8. Update packages table if needed
    $packagesCheck = $pdo->query("SHOW COLUMNS FROM packages LIKE 'duration'");
    if ($packagesCheck->rowCount() == 0) {
        echo "Updating packages table structure...\n";
        $pdo->exec("ALTER TABLE packages ADD COLUMN duration VARCHAR(255) DEFAULT NULL AFTER price");
        $pdo->exec("ALTER TABLE packages ADD COLUMN tile_class VARCHAR(255) DEFAULT NULL");
        echo "Packages table updated.\n";
    }
    
    // 9. Update service availability_status enum if needed
    $availStatus = $pdo->query("SHOW COLUMNS FROM services WHERE Field = 'availability_status'");
    $availStatusColumn = $availStatus->fetch(PDO::FETCH_ASSOC);
    
    if ($availStatusColumn && strpos($availStatusColumn['Type'], 'Limited') === false) {
        echo "Updating service availability_status enum values...\n";
        $pdo->exec("ALTER TABLE services MODIFY COLUMN availability_status ENUM('Available','Unavailable','Coming Soon','Limited') NOT NULL DEFAULT 'Available'");
        echo "Service availability_status updated.\n";
    }
    
    // Commit transaction
    $pdo->commit();
    echo "Migration completed successfully.\n";
    
} catch (PDOException $e) {
    // Roll back transaction on error
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    echo "Database error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

// Check if procedure exists
$checkProc = $pdo->query("SELECT COUNT(*) FROM information_schema.routines 
    WHERE routine_schema = '$dbname' 
    AND routine_name = 'create_booking_notification'");

if ($checkProc->fetchColumn() == 0) {
    // Create procedure if it doesn't exist
    $pdo->exec("CREATE PROCEDURE create_booking_notification...");
} else {
    // Optionally update the existing procedure
    $pdo->exec("DROP PROCEDURE create_booking_notification");
    $pdo->exec("CREATE PROCEDURE create_booking_notification...");
    // Or skip creating it
    echo "Procedure create_booking_notification already exists. Skipping creation.\n";
}