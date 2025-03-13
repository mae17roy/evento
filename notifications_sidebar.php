<?php
// notifications_sidebar.php - Include this file in all dashboard pages

// Make sure we have the needed functions
if (!function_exists('getRecentNotificationsMultiOwner')) {
    /**
     * Get recent unread notifications for current user/owner
     * @param PDO $db Database connection
     * @param int $limit Maximum number of notifications to return
     * @return array Array of notification data
     */
    function getRecentNotificationsMultiOwner($db, $limit = 5) {
        if (!isset($_SESSION['user_id'])) {
            return [];
        }
        
        $userId = $_SESSION['user_id'];
        $userRole = $_SESSION['user_role'] ?? 'client';
        
        // Different query based on user role
        if ($userRole == 'owner') {
            $stmt = $db->prepare("
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.is_read, n.created_at,
                      CASE WHEN n.owner_id = ? THEN 'owner' ELSE 'user' END as notification_for
                FROM notifications n
                WHERE (n.owner_id = ? OR n.user_id = ?) AND n.is_read = 0
                ORDER BY n.created_at DESC
                LIMIT $limit
            ");
            $stmt->execute([$userId, $userId, $userId]);
        } else {
            $stmt = $db->prepare("
                SELECT n.id, n.type, n.title, n.message, n.related_id, n.is_read, n.created_at,
                      'user' as notification_for
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
                'is_read' => (bool)$notification['is_read'], 
                'notification_for' => $notification['notification_for'] ?? 'user',
                'booking_id' => $notification['type'] === 'booking' ? $notification['related_id'] : null,
                'service_id' => $notification['type'] === 'service' ? $notification['related_id'] : null
            ];
        }
        
        return $notifications;
    }
}

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

// Get notifications for current user
// First try to use multi-owner functions if available, then fallback to regular functions
if (function_exists('getRecentNotificationsMultiOwner')) {
    $notifications = getRecentNotificationsMultiOwner($db);
} else {
    // Fallback to original function if available, otherwise use empty array
    $notifications = function_exists('getRecentNotifications') ? 
        getRecentNotifications($db, $_SESSION['user_id']) : [];
}

// Count unread notifications
if (function_exists('countUnreadNotificationsMultiOwner')) {
    $unreadNotificationsCount = countUnreadNotificationsMultiOwner($db);
} else {
    // Fallback to original function if available, otherwise use 0
    $unreadNotificationsCount = function_exists('countUnreadNotifications') ? 
        countUnreadNotifications($db, $_SESSION['user_id']) : 0;
}
?>

<!-- Notifications Sidebar - Add this HTML to all dashboard pages -->
<div id="notifications-sidebar" class="w-80 bg-white border-l border-gray-200 fixed right-0 top-0 h-full transform translate-x-full transition-transform duration-300 ease-in-out z-30 overflow-hidden flex flex-col">
    <div class="p-4 border-b border-gray-200 flex justify-between items-center">
        <h2 class="text-lg font-medium">Notifications</h2>
        <button id="close-notifications" class="text-gray-400 hover:text-gray-600">
            <i class="mif-cross"></i>
        </button>
    </div>
    
    <div class="notification-panel flex-1 overflow-y-auto">
        <?php if (empty($notifications)): ?>
            <div class="p-4 text-center text-gray-500">
                No new notifications
            </div>
        <?php else: ?>
            <?php foreach ($notifications as $notification): ?>
            <div class="p-4 border-b border-gray-100 <?php echo ($notification['is_read'] ? '' : 'bg-blue-50'); ?>">
                <div class="flex justify-between items-start">
                    <h3 class="font-medium mb-1"><?php echo htmlspecialchars($notification['title']); ?></h3>
                    <span class="text-xs text-gray-500"><?php echo $notification['time']; ?></span>
                </div>
                <p class="text-sm text-gray-600 mb-2"><?php echo htmlspecialchars($notification['message']); ?></p>
                <div class="flex justify-between items-center">
                    <?php if (!empty($notification['booking_id'])): ?>
                        <a href="view_booking.php?id=<?php echo $notification['booking_id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                    <?php elseif (!empty($notification['service_id'])): ?>
                        <a href="service_management.php" class="text-xs text-blue-600 hover:text-blue-800">View services</a>
                    <?php else: ?>
                        <span></span>
                    <?php endif; ?>
                    
                    <?php if (!$notification['is_read']): ?>
                        <form action="mark_notification_read.php" method="post" class="inline">
                            <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                            <button type="submit" class="text-xs text-gray-500 hover:text-gray-700">Mark as read</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="p-4 text-center border-t border-gray-200">
        <a href="notifications.php" class="inline-block text-sm text-blue-600 hover:text-blue-800">View all notifications</a>
        <?php if ($unreadNotificationsCount > 0): ?>
            <form action="mark_all_notifications_read.php" method="post" class="mt-2">
                <button type="submit" class="text-sm text-gray-500 hover:text-gray-700">Mark all as read</button>
            </form>
        <?php endif; ?>
    </div>
</div>

<!-- JS for Notifications - Include in each dashboard page -->
<script>
// Notification toggle
document.addEventListener('DOMContentLoaded', function() {
    // Notification toggle
    const notificationToggle = document.getElementById('notification-toggle');
    const notificationsSidebar = document.getElementById('notifications-sidebar');
    
    if (notificationToggle && notificationsSidebar) {
        notificationToggle.addEventListener('click', function() {
            notificationsSidebar.classList.toggle('translate-x-full');
        });
    }
    
    // Close notifications
    const closeNotifications = document.getElementById('close-notifications');
    
    if (closeNotifications && notificationsSidebar) {
        closeNotifications.addEventListener('click', function() {
            notificationsSidebar.classList.add('translate-x-full');
        });
    }
});
</script>