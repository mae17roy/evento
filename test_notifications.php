<?php
// Include database configuration
require_once 'config.php';

// Require admin login
requireAdminLogin();

// Get current user data
$user = getCurrentUser($db);

// This script will help diagnose notification system issues

// Create a test notification if requested
if (isset($_GET['create'])) {
    $userId = $_SESSION['user_id'] ?? 0;
    $result = createNotification(
        $db,
        $userId,
        'service',
        'Test Notification',
        'This is a test notification created at ' . date('Y-m-d H:i:s'),
        null
    );
    
    if ($result) {
        $successMessage = "Test notification created successfully!";
    } else {
        $errorMessage = "Failed to create test notification.";
    }
}

// Clear all notifications if requested
if (isset($_GET['clear'])) {
    $userId = $_SESSION['user_id'] ?? 0;
    $stmt = $db->prepare("DELETE FROM notifications WHERE user_id = ?");
    $result = $stmt->execute([$userId]);
    
    if ($result) {
        $successMessage = "All notifications cleared successfully!";
    } else {
        $errorMessage = "Failed to clear notifications.";
    }
}

// Mark all as read if requested
if (isset($_GET['markread'])) {
    $result = markAllNotificationsAsRead($db);
    
    if ($result) {
        $successMessage = "All notifications marked as read!";
    } else {
        $errorMessage = "Failed to mark notifications as read.";
    }
}

// Get notifications
$notifications = getRecentNotifications($db, 50);
$unreadCount = countUnreadNotifications($db);

// Get notification table schema
try {
    $schemaQuery = "SHOW CREATE TABLE notifications";
    $schemaStmt = $db->query($schemaQuery);
    $schema = $schemaStmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $schemaError = $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notification System Test</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Metro UI CSS -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/metro/4.4.3/css/metro-icons.min.css">
</head>
<body class="bg-gray-100">
    <div class="max-w-6xl mx-auto p-6">
        <header class="mb-6">
            <h1 class="text-3xl font-bold mb-2">Notification System Test</h1>
            <p class="text-gray-600">Use this tool to diagnose issues with your notification system</p>
            
            <?php if (isset($successMessage)): ?>
                <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mt-4">
                    <?php echo $successMessage; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($errorMessage)): ?>
                <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mt-4">
                    <?php echo $errorMessage; ?>
                </div>
            <?php endif; ?>
        </header>
        
        <main>
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">Notification Actions</h2>
                <div class="flex flex-wrap gap-4">
                    <a href="?create=1" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Test Notification</a>
                    <a href="?markread=1" class="bg-green-600 text-white px-4 py-2 rounded hover:bg-green-700">Mark All as Read</a>
                    <a href="?clear=1" class="bg-red-600 text-white px-4 py-2 rounded hover:bg-red-700">Clear All Notifications</a>
                    <a href="service_management.php" class="bg-gray-600 text-white px-4 py-2 rounded hover:bg-gray-700">Back to Services</a>
                </div>
            </div>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">System Information</h2>
                    <ul class="space-y-2">
                        <li><strong>Current User:</strong> <?php echo htmlspecialchars($user['name']); ?> (ID: <?php echo $user['id']; ?>)</li>
                        <li><strong>Role:</strong> <?php echo htmlspecialchars($user['role']); ?></li>
                        <li><strong>Session User ID:</strong> <?php echo $_SESSION['user_id'] ?? 'Not set'; ?></li>
                        <li><strong>Session Status:</strong> <?php echo (session_status() === PHP_SESSION_ACTIVE) ? 'Active' : 'Not active'; ?></li>
                        <li><strong>Unread Notifications:</strong> <?php echo $unreadCount; ?></li>
                        <li><strong>Total Notifications:</strong> <?php echo count($notifications); ?></li>
                    </ul>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-4">Notification Table Schema</h2>
                    <?php if (isset($schema) && isset($schema['Create Table'])): ?>
                        <div class="overflow-x-auto">
                            <pre class="text-xs bg-gray-100 p-4 rounded"><?php echo htmlspecialchars($schema['Create Table']); ?></pre>
                        </div>
                    <?php elseif (isset($schemaError)): ?>
                        <div class="text-red-600">Error: <?php echo $schemaError; ?></div>
                    <?php else: ?>
                        <p>Could not retrieve table schema.</p>
                    <?php endif; ?>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                <h2 class="text-xl font-bold mb-4">Current Notifications (<?php echo count($notifications); ?>)</h2>
                
                <?php if (empty($notifications)): ?>
                    <div class="text-center py-8 text-gray-500">
                        <i class="mif-bell-off text-4xl mb-2"></i>
                        <p>No notifications found</p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="min-w-full">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-2 text-left text-<th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Title</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Message</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Related ID</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Read</th>
                                    <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                <?php foreach ($notifications as $notification): ?>
                                <tr>
                                    <td class="px-4 py-2 text-sm"><?php echo $notification['id']; ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($notification['type']); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($notification['title']); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo htmlspecialchars($notification['message']); ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo $notification['related_id'] ?? 'None'; ?></td>
                                    <td class="px-4 py-2 text-sm"><?php echo $notification['time']; ?></td>
                                    <td class="px-4 py-2 text-sm">
                                        <?php if ($notification['is_read']): ?>
                                            <span class="text-green-600">Yes</span>
                                        <?php else: ?>
                                            <span class="text-red-600">No</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="px-4 py-2 text-sm">
                                        <form action="mark_notification_read.php" method="post" class="inline">
                                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                            <button type="submit" class="text-blue-600 hover:text-blue-800">Mark read</button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-bold mb-4">Debug Information</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-lg font-medium mb-2">PHP Info</h3>
                        <ul class="space-y-1">
                            <li><strong>PHP Version:</strong> <?php echo phpversion(); ?></li>
                            <li><strong>Server:</strong> <?php echo $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown'; ?></li>
                            <li><strong>PDO Drivers:</strong> <?php echo implode(', ', PDO::getAvailableDrivers()); ?></li>
                        </ul>
                    </div>
                    
                    <div>
                        <h3 class="text-lg font-medium mb-2">Notification Functions</h3>
                        <ul class="space-y-1">
                            <li><strong>getRecentNotifications:</strong> <?php echo function_exists('getRecentNotifications') ? 'Available' : 'Not found'; ?></li>
                            <li><strong>countUnreadNotifications:</strong> <?php echo function_exists('countUnreadNotifications') ? 'Available' : 'Not found'; ?></li>
                            <li><strong>markNotificationAsRead:</strong> <?php echo function_exists('markNotificationAsRead') ? 'Available' : 'Not found'; ?></li>
                            <li><strong>markAllNotificationsAsRead:</strong> <?php echo function_exists('markAllNotificationsAsRead') ? 'Available' : 'Not found'; ?></li>
                            <li><strong>createNotification:</strong> <?php echo function_exists('createNotification') ? 'Available' : 'Not found'; ?></li>
                        </ul>
                    </div>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-2">Database Connection Test</h3>
                    <?php
                    try {
                        $testStmt = $db->query("SELECT 1");
                        echo '<p class="text-green-600">Database connection is working properly.</p>';
                    } catch (PDOException $e) {
                        echo '<p class="text-red-600">Database connection error: ' . $e->getMessage() . '</p>';
                    }
                    ?>
                </div>
                
                <div class="mt-6">
                    <h3 class="text-lg font-medium mb-2">Test createNotification Function</h3>
                    <form action="test_notifications.php" method="get" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Title</label>
                            <input type="text" name="title" value="Test Notification" class="w-full p-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Message</label>
                            <input type="text" name="message" value="This is a test notification" class="w-full p-2 border border-gray-300 rounded-md">
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Type</label>
                            <select name="type" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="service">Service</option>
                                <option value="booking">Booking</option>
                                <option value="system">System</option>
                            </select>
                        </div>
                        <input type="hidden" name="create" value="1">
                        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded hover:bg-blue-700">Create Custom Notification</button>
                    </form>
                </div>
            </div>
        </main>
        
        <footer class="mt-6 text-center text-gray-500 text-sm">
            <p>Notification System Test Tool</p>
        </footer>
    </div>
</body>
</html>