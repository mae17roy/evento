<?php
// This script tests session functionality and displays connection info

// Start a session
session_start();

// Set debug mode
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Function to check database connection
function checkDatabase() {
    require_once 'config.php';
    global $db, $db_host, $db_name, $db_user, $db_pass;
    
    $result = [
        'connection' => false,
        'error' => null,
        'config' => [
            'host' => $db_host,
            'database' => $db_name,
            'user' => $db_user
        ]
    ];
    
    try {
        if ($db) {
            // Test the connection
            $stmt = $db->query("SELECT 1");
            if ($stmt) {
                $result['connection'] = true;
                
                // Test users table
                $userStmt = $db->query("SELECT COUNT(*) FROM users");
                if ($userStmt) {
                    $result['users_count'] = $userStmt->fetchColumn();
                    
                    // Test client users
                    $clientStmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'client'");
                    if ($clientStmt) {
                        $result['client_count'] = $clientStmt->fetchColumn();
                    }
                }
            }
        } else {
            $result['error'] = "Database variable is not initialized";
        }
    } catch (PDOException $e) {
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

// Function to check if a path is writable
function checkPathWritable($path) {
    return is_writable($path) ? "Writable" : "Not writable";
}

// Test the session
if (!isset($_SESSION['test_counter'])) {
    $_SESSION['test_counter'] = 1;
} else {
    $_SESSION['test_counter']++;
}

// Set a test value for this page view
$testValue = "Test-" . time();
$_SESSION['test_value'] = $testValue;

// Get server and PHP information
$serverInfo = [
    'PHP Version' => phpversion(),
    'Server Software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
    'Server Name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
    'Document Root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
    'Script Path' => $_SERVER['SCRIPT_FILENAME'] ?? 'Unknown',
    'Session Path' => session_save_path(),
    'Session Path Writable' => checkPathWritable(session_save_path()),
    'Current Dir Writable' => checkPathWritable('.'),
    'Session ID' => session_id(),
    'Session Name' => session_name(),
    'Session Module' => ini_get('session.save_handler'),
    'Session Cookie Domain' => ini_get('session.cookie_domain'),
    'Session Cookie Path' => ini_get('session.cookie_path'),
    'Session Cookie Lifetime' => ini_get('session.cookie_lifetime'),
    'Session Cookie Secure' => ini_get('session.cookie_secure'),
    'Session Cookie HttpOnly' => ini_get('session.cookie_httponly'),
    'Session Cookie SameSite' => ini_get('session.cookie_samesite'),
    'Output Buffering' => ini_get('output_buffering'),
];

// Check database connection
$dbResult = checkDatabase();

// Header
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Session Test</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1, h2 { color: #8044DD; }
        .section { margin-bottom: 30px; background: #f5f5f5; padding: 15px; border-radius: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #ddd; }
        th { background-color: #f0f0f0; }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        pre { background: #f0f0f0; padding: 10px; overflow: auto; }
    </style>
</head>
<body>
    <h1>EVENTO - Session and Database Test</h1>
    
    <div class="section">
        <h2>Session Test</h2>
        <p>Session working: <span class="<?php echo isset($_SESSION['test_counter']) ? 'success' : 'error'; ?>">
            <?php echo isset($_SESSION['test_counter']) ? 'Yes' : 'No'; ?>
        </span></p>
        <p>Page views in this session: <?php echo $_SESSION['test_counter']; ?></p>
        <p>Test value set: <?php echo htmlspecialchars($testValue); ?></p>
        <p>Test value from session: <?php echo htmlspecialchars($_SESSION['test_value']); ?></p>
        <p>Test value matches: <span class="<?php echo $_SESSION['test_value'] === $testValue ? 'success' : 'error'; ?>">
            <?php echo $_SESSION['test_value'] === $testValue ? 'Yes' : 'No'; ?>
        </span></p>
    </div>
    
    <div class="section">
        <h2>Session Variables</h2>
        <?php if (empty($_SESSION) || count($_SESSION) <= 2): ?>
            <p class="warning">No meaningful session variables found other than test values.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Variable</th>
                    <th>Value</th>
                </tr>
                <?php foreach($_SESSION as $key => $value): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($key); ?></td>
                        <td><?php 
                            if (is_array($value)) {
                                echo '<pre>' . htmlspecialchars(print_r($value, true)) . '</pre>';
                            } else {
                                echo htmlspecialchars(substr((string)$value, 0, 100));
                                if (strlen((string)$value) > 100) echo '...';
                            }
                        ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Database Connection Test</h2>
        <p>Connection: <span class="<?php echo $dbResult['connection'] ? 'success' : 'error'; ?>">
            <?php echo $dbResult['connection'] ? 'Successful' : 'Failed'; ?>
        </span></p>
        
        <?php if ($dbResult['error']): ?>
            <p class="error">Error: <?php echo htmlspecialchars($dbResult['error']); ?></p>
        <?php endif; ?>
        
        <h3>Database Configuration</h3>
        <table>
            <tr><th>Host</th><td><?php echo htmlspecialchars($dbResult['config']['host']); ?></td></tr>
            <tr><th>Database</th><td><?php echo htmlspecialchars($dbResult['config']['database']); ?></td></tr>
            <tr><th>User</th><td><?php echo htmlspecialchars($dbResult['config']['user']); ?></td></tr>
        </table>
        
        <?php if ($dbResult['connection'] && isset($dbResult['users_count'])): ?>
            <h3>Database Contents</h3>
            <p>Total users in database: <?php echo $dbResult['users_count']; ?></p>
            <p>Client users in database: <?php echo $dbResult['client_count']; ?></p>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Server Information</h2>
        <table>
            <?php foreach($serverInfo as $key => $value): ?>
                <tr>
                    <th><?php echo htmlspecialchars($key); ?></th>
                    <td><?php echo htmlspecialchars($value); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>
    
    <div class="section">
        <h2>Cookie Information</h2>
        <?php if (empty($_COOKIE)): ?>
            <p class="warning">No cookies found.</p>
        <?php else: ?>
            <table>
                <tr>
                    <th>Name</th>
                    <th>Value</th>
                </tr>
                <?php foreach($_COOKIE as $key => $value): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($key); ?></td>
                        <td><?php echo htmlspecialchars(substr($value, 0, 50)); ?><?php echo (strlen($value) > 50) ? '...' : ''; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
    </div>
    
    <div class="section">
        <h2>Links</h2>
        <p><a href="login.php">Go to Login Page</a></p>
        <p><a href="user_index.php">Go to Dashboard</a></p>
        <p><a href="?">Refresh This Page</a></p>
    </div>
</body>
</html>