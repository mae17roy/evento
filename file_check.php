<?php
// Save this as file_check.php in the same directory as your index.php

echo "<h1>File System Diagnostic</h1>";

// Current directory
echo "<h2>Current Directory</h2>";
echo "<p>Current working directory: " . getcwd() . "</p>";
echo "<p>PHP_SELF: " . $_SERVER['PHP_SELF'] . "</p>";
echo "<p>SCRIPT_FILENAME: " . $_SERVER['SCRIPT_FILENAME'] . "</p>";

// Check essential files
echo "<h2>File Existence Check</h2>";
$files_to_check = [
    'index.php',
    'owner_index.php',
    'user_index.php',
    'admin_index.php',
    'functions.php',
    'config.php'
];

echo "<table border='1' cellpadding='5'>";
echo "<tr><th>File</th><th>Exists</th><th>Readable</th><th>Path</th></tr>";

foreach ($files_to_check as $file) {
    echo "<tr>";
    echo "<td>$file</td>";
    
    // Check if file exists
    if (file_exists($file)) {
        echo "<td style='background-color: #dfd;'>Yes</td>";
        
        // Check if file is readable
        if (is_readable($file)) {
            echo "<td style='background-color: #dfd;'>Yes</td>";
        } else {
            echo "<td style='background-color: #fdd;'>No - Permission Issue</td>";
        }
        
        // Show real path
        echo "<td>" . realpath($file) . "</td>";
    } else {
        echo "<td style='background-color: #fdd;'>No</td>";
        echo "<td>-</td>";
        echo "<td>Not Found</td>";
    }
    
    echo "</tr>";
}
echo "</table>";

// List all PHP files in current directory
echo "<h2>All PHP Files in Current Directory</h2>";
$php_files = glob("*.php");

if (count($php_files) > 0) {
    echo "<ul>";
    foreach ($php_files as $php_file) {
        echo "<li>" . htmlspecialchars($php_file) . " - " . date("Y-m-d H:i:s", filemtime($php_file)) . "</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No PHP files found in current directory.</p>";
}

// PHP Configuration
echo "<h2>PHP Configuration</h2>";
echo "<p>PHP Version: " . phpversion() . "</p>";
echo "<p>Display Errors: " . ini_get('display_errors') . "</p>";
echo "<p>Error Reporting Level: " . error_reporting() . "</p>";
?>