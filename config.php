<?php
$host = "localhost"; // Your database host (default: localhost)
$dbname = "event_management_system"; // Your database name
$username = "root"; // Your database username (default: root for XAMPP)
$password = ""; // Your database password (default: empty for XAMPP)

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
