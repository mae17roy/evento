<?php
// Test connection with explicit port specification
try {
    // Default MySQL port is 3306
    $db = new PDO("mysql:host=localhost;port=3306;dbname=event_management_system", "root", "");
    echo "Connection successful using explicit port 3306";
} catch (PDOException $e) {
    echo "Connection failed using port 3306: " . $e->getMessage();
    
    // Try alternative port
    try {
        $db = new PDO("mysql:host=127.0.0.1;dbname=event_management_system", "root", "");
        echo "<br>Connection successful using 127.0.0.1 instead of localhost";
    } catch (PDOException $e2) {
        echo "<br>Alternative connection also failed: " . $e2->getMessage();
    }
}
?>