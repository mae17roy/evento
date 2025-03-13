<?php
// Enable error display for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database credentials - use exactly what's in your config.php
$db_host = 'localhost';
$db_name = 'event_management_system';
$db_user = 'root';
$db_pass = '';

echo "<h2>Database Connection Test</h2>";

// Test MySQL server connection (without specifying a database)
echo "<h3>Step 1: Testing connection to MySQL server</h3>";
try {
    $conn = new PDO("mysql:host=$db_host", $db_user, $db_pass);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✓ Successfully connected to MySQL server</p>";
} catch(PDOException $e) {
    echo "<p style='color:red'>✗ Failed to connect to MySQL server: " . $e->getMessage() . "</p>";
    echo "<p>Please check that MySQL is running and that the username and password are correct.</p>";
    exit;
}

// Test if database exists
echo "<h3>Step 2: Checking if database exists</h3>";
try {
    $stmt = $conn->query("SELECT COUNT(*) FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$db_name'");
    $dbExists = (int)$stmt->fetchColumn();
    
    if ($dbExists) {
        echo "<p style='color:green'>✓ Database '$db_name' exists</p>";
    } else {
        echo "<p style='color:red'>✗ Database '$db_name' does not exist</p>";
        echo "<p>Attempting to create database...</p>";
        
        try {
            $conn->exec("CREATE DATABASE `$db_name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<p style='color:green'>✓ Successfully created database '$db_name'</p>";
        } catch(PDOException $e) {
            echo "<p style='color:red'>✗ Failed to create database: " . $e->getMessage() . "</p>";
            exit;
        }
    }
} catch(PDOException $e) {
    echo "<p style='color:red'>✗ Error checking database existence: " . $e->getMessage() . "</p>";
    exit;
}

// Test connection to the specific database
echo "<h3>Step 3: Testing connection to the database</h3>";
try {
    $dbConn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
    $dbConn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<p style='color:green'>✓ Successfully connected to database '$db_name'</p>";
    
    // Check if essential tables exist
    echo "<h3>Step 4: Checking for essential tables</h3>";
    $requiredTables = ['users', 'services', 'categories', 'bookings'];
    $missingTables = [];
    
    foreach ($requiredTables as $table) {
        $result = $dbConn->query("SHOW TABLES LIKE '$table'");
        if ($result->rowCount() == 0) {
            $missingTables[] = $table;
        }
    }
    
    if (empty($missingTables)) {
        echo "<p style='color:green'>✓ All required tables exist</p>";
    } else {
        echo "<p style='color:orange'>⚠ The following tables are missing: " . implode(', ', $missingTables) . "</p>";
        echo "<p>You may need to import your database schema.</p>";
    }
    
} catch(PDOException $e) {
    echo "<p style='color:red'>✗ Failed to connect to the database: " . $e->getMessage() . "</p>";
    exit;
}

echo "<h3>Summary</h3>";
echo "<p style='color:green'>MySQL server is running and accessible.</p>";
echo "<p style='color:green'>Database '$db_name' exists.</p>";
echo "<p>Your database connection parameters appear to be correct.</p>";
echo "<p>If you are still experiencing issues with your application, please check the following:</p>";
echo "<ol>";
echo "<li>Ensure your config.php file has the correct settings</li>";
echo "<li>Check that your application code is properly accessing the database connection</li>";
echo "<li>Verify error reporting is enabled during development</li>";
echo "</ol>";
?>