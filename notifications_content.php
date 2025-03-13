<!-- Notifications Content -->
<main class="flex-1 overflow-y-auto p-6">
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold">
            <?php if ($filter === 'unread'): ?>
                Unread Notifications
            <?php elseif ($filter === 'bookings'): ?>
                Booking Notifications
            <?php elseif ($filter === 'services'): ?>
                Service Notifications
            <?php elseif ($filter === 'owner'): ?>
                Owner Notifications
            <?php elseif ($filter === 'personal'): ?>
                Personal Notifications
            <?php else: ?>
                All Notifications
            <?php endif; ?>
        </h2>
        
        <form method="post" action="mark_all_notifications_read.php">
            <button type="submit" class="bg-blue-900 text-white px-4 py-2 rounded-md">
                Mark All as Read
            </button>
        </form>
    </div>
    
    <!-- Filter Tabs -->
    <div class="bg-white rounded-t-md shadow-sm">
        <div class="border-b border-gray-200">
            <nav class="flex -mb-px overflow-x-auto">
                <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    All
                </a>
                <a href="?filter=unread" class="filter-tab <?php echo $filter === 'unread' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    Unread
                </a>
                <a href="?filter=bookings" class="filter-tab <?php echo $filter === 'bookings' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    Bookings
                </a>
                <a href="?filter=services" class="filter-tab <?php echo $filter === 'services' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    Services
                </a>
                <?php if ($userRole === 'owner'): ?>
                <a href="?filter=owner" class="filter-tab <?php echo $filter === 'owner' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    Owner Notifications
                </a>
                <?php endif; ?>
                <a href="?filter=personal" class="filter-tab <?php echo $filter === 'personal' ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-500 hover:text-gray-700'; ?> py-4 px-6 font-medium whitespace-nowrap">
                    Personal
                </a>
            </nav>
        </div>
    </div>
    
    <!-- Notifications List -->
    <div class="bg-white rounded-b-md shadow-sm mb-6">
        <?php if (empty($filteredNotifications)): ?>
            <div class="p-8 text-center text-gray-500">
                <i class="mif-bell-off text-4xl mb-2"></i>
                <p>No notifications found</p>
            </div>
        <?php else: ?>
            <div class="divide-y divide-gray-200" id="notifications-container">
                <?php foreach ($filteredNotifications as $notification): ?>
                <div class="notification-item p-4 hover:bg-gray-50 <?php echo !$notification['is_read'] ? 'unread' : ''; ?>" 
                     data-type="<?php echo $notification['type']; ?>"
                     data-for="<?php echo isset($notification['notification_for']) ? $notification['notification_for'] : 'user'; ?>">
                    <div class="flex items-start">
                        <div class="flex-shrink-0 mr-3">
                            <?php if ($notification['type'] === 'booking' && strpos($notification['title'], 'New') !== false): ?>
                                <div class="w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                    <i class="mif-calendar-plus text-blue-600"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'booking' && strpos($notification['title'], 'Confirmed') !== false): ?>
                                <div class="w-10 h-10 rounded-full bg-green-100 flex items-center justify-center">
                                    <i class="mif-checkmark text-green-600"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'booking' && strpos($notification['title'], 'Cancelled') !== false): ?>
                                <div class="w-10 h-10 rounded-full bg-red-100 flex items-center justify-center">
                                    <i class="mif-cross text-red-600"></i>
                                </div>
                            <?php elseif ($notification['type'] === 'service'): ?>
                                <div class="w-10 h-10 rounded-full bg-purple-100 flex items-center justify-center">
                                    <i class="mif-cogs text-purple-600"></i>
                                </div>
                            <?php elseif (isset($notification['notification_for']) && $notification['notification_for'] === 'owner'): ?>
                                <div class="w-10 h-10 rounded-full bg-amber-100 flex items-center justify-center">
                                    <i class="mif-user text-amber-600"></i>
                                </div>
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                    <i class="mif-bell text-gray-600"></i>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="flex-1">
                            <div class="flex justify-between items-start">
                                <div>
                                    <span class="notification-type-badge 
                                        <?php 
                                        if ($notification['type'] === 'booking' && strpos($notification['title'], 'New') !== false) {
                                            echo 'type-new';
                                        } elseif ($notification['type'] === 'booking' && strpos($notification['title'], 'Confirmed') !== false) {
                                            echo 'type-confirmed';
                                        } elseif ($notification['type'] === 'booking' && strpos($notification['title'], 'Cancelled') !== false) {
                                            echo 'type-cancelled';
                                        } elseif ($notification['type'] === 'service') {
                                            echo 'type-service';
                                        } elseif (isset($notification['notification_for']) && $notification['notification_for'] === 'owner') {
                                            echo 'type-owner';
                                        }
                                        ?>">
                                        <?php echo htmlspecialchars($notification['title']); ?>
                                    </span>
                                    
                                    <?php if (isset($notification['notification_for']) && $notification['notification_for'] === 'owner'): ?>
                                    <span class="ml-2 px-2 py-1 bg-amber-100 text-amber-800 text-xs rounded">Owner</span>
                                    <?php endif; ?>
                                </div>
                                <span class="text-xs text-gray-500"><?php echo $notification['time']; ?></span>
                            </div>
                            
                            <p class="mt-2 text-gray-700"><?php echo htmlspecialchars($notification['message']); ?></p>
                            
                            <div class="mt-2 flex space-x-3">
                                <?php if (isset($notification['related_id']) && $notification['type'] === 'booking'): ?>
                                <a href="view_booking.php?id=<?php echo $notification['related_id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                    View booking details
                                </a>
                                <?php endif; ?>
                                
                                <?php if (isset($notification['related_id']) && $notification['type'] === 'service'): ?>
                                <a href="service_management.php?id=<?php echo $notification['related_id']; ?>" class="text-sm text-blue-600 hover:text-blue-800">
                                    View service details
                                </a>
                                <?php endif; ?>
                                
                                <?php if (!$notification['is_read']): ?>
                                <form action="mark_notification_read.php" method="post" class="inline">
                                    <input type="hidden" name="notification_id" value="<?php echo $notification['id']; ?>">
                                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-800">
                                        Mark as read
                                    </button>
                                </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Showing <?php echo count($filteredNotifications); ?> of <?php echo $total; ?> notification(s)
        </p>
        <div class="flex space-x-1">
            <?php if ($currentPage > 1): ?>
            <a href="?page=<?php echo $currentPage - 1; ?>&filter=<?php echo $filter; ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">&laquo;</a>
            <?php endif; ?>
            
            <?php for($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
            <a href="?page=<?php echo $i; ?>&filter=<?php echo $filter; ?>" 
               class="px-3 py-1 border border-gray-300 rounded-md <?php echo $i === $currentPage ? 'bg-blue-900 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
               <?php echo $i; ?>
            </a>
            <?php endfor; ?>
            
            <?php if ($currentPage < $totalPages): ?>
            <a href="?page=<?php echo $currentPage + 1; ?>&filter=<?php echo $filter; ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">&raquo;</a>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>