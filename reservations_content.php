<!-- Reservations Content -->
<main class="flex-1 overflow-y-auto p-6">
    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Reservations</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($bookingMetrics['total'] ?? 0); ?></h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="mif-file-text text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($bookingMetrics['pending'] ?? 0); ?></h3>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="mif-history text-yellow-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Confirmed</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($bookingMetrics['confirmed'] ?? 0); ?></h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="mif-checkmark text-green-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Revenue</p>
                    <h3 class="text-2xl font-bold">$<?php echo number_format($bookingMetrics['total_revenue'] ?? 0, 2); ?></h3>
                </div>
                <div class="bg-indigo-100 p-3 rounded-full">
                    <i class="mif-dollar text-indigo-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-2">Manage Reservations</h2>
        <p class="text-gray-600">View and manage all customer bookings for your services</p>
    </div>
    
    <!-- Filters -->
    <div class="bg-white rounded-md shadow-sm mb-6 p-4">
        <form method="get" action="reservations.php" class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                <select name="status" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">All Statuses</option>
                    <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                    <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                    <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Service</label>
                <select name="service" class="w-full p-2 border border-gray-300 rounded-md">
                    <option value="">All Services</option>
                    <?php foreach ($services as $service): ?>
                    <option value="<?php echo $service['id']; ?>" <?php echo ($serviceFilter == $service['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($service['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date</label>
                <input type="date" name="date" class="w-full p-2 border border-gray-300 rounded-md" value="<?php echo htmlspecialchars($dateFilter); ?>">
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-900 text-white px-4 py-2 rounded-md mr-2">Apply Filters</button>
                <a href="reservations.php" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md">Clear</a>
            </div>
            
            <!-- Preserve search term if it exists -->
            <?php if (!empty($searchFilter)): ?>
            <input type="hidden" name="search" value="<?php echo htmlspecialchars($searchFilter); ?>">
            <?php endif; ?>
        </form>
    </div>
    
    <!-- Reservations Table -->
    <div class="bg-white rounded-md shadow-sm mb-6">
        <div class="overflow-x-auto">
            <?php if (empty($reservations)): ?>
                <div class="p-8 text-center text-gray-500">
                    <i class="mif-calendar-empty text-4xl mb-2"></i>
                    <p>No reservations found matching your criteria.</p>
                    <?php if (!empty($searchFilter) || !empty($statusFilter) || !empty($serviceFilter) || !empty($dateFilter)): ?>
                        <a href="reservations.php" class="text-blue-600 mt-2 inline-block">Clear all filters</a>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <table class="min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($reservations as $reservation): ?>
                        <tr data-booking-id="<?php echo $reservation['id']; ?>">
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($reservation['customer']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($reservation['email']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($reservation['service']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900"><?php echo formatDate($reservation['booking_date']); ?></div>
                                <div class="text-sm text-gray-500"><?php echo formatTime($reservation['booking_time']); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="text-sm text-gray-900">$<?php echo number_format($reservation['total_amount'], 2); ?></div>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <span class="booking-status status-badge status-<?php echo strtolower($reservation['status']); ?>" data-booking-id="<?php echo $reservation['id']; ?>">
                                    <?php echo ucfirst($reservation['status']); ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap">
                                <div class="flex space-x-2">
                                    <a href="view_booking.php?id=<?php echo $reservation['id']; ?>" class="text-blue-600 hover:text-blue-800">
                                        <i class="mif-eye"></i>
                                    </a>

                                    <div class="relative dropdown">
                                        <button class="text-gray-400 hover:text-gray-600 dropdown-toggle">
                                            <i class="mif-dots-vertical"></i>
                                        </button>
                                        <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                            <div class="py-1">
                                                <a href="view_booking.php?id=<?php echo $reservation['id']; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Details</a>
                                                
                                                <?php if ($reservation['status'] === 'pending'): ?>
                                                <button 
                                                    class="booking-action block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-gray-100" 
                                                    data-booking-id="<?php echo $reservation['id']; ?>" 
                                                    data-action="confirmed">
                                                    Approve
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['status'] === 'confirmed'): ?>
                                                <button 
                                                    class="booking-action block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-gray-100" 
                                                    data-booking-id="<?php echo $reservation['id']; ?>" 
                                                    data-action="completed">
                                                    Mark as Completed
                                                </button>
                                                <?php endif; ?>
                                                
                                                <?php if ($reservation['status'] !== 'cancelled' && $reservation['status'] !== 'completed'): ?>
                                                <button 
                                                    class="booking-action block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-gray-100" 
                                                    data-booking-id="<?php echo $reservation['id']; ?>" 
                                                    data-action="cancelled">
                                                    Cancel
                                                </button>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- Pagination -->
    <?php if ($pagination['total_pages'] > 1): ?>
    <div class="flex justify-between items-center">
        <p class="text-sm text-gray-600">
            Showing <?php echo count($reservations); ?> of <?php echo $pagination['total']; ?> reservation(s)
        </p>
        <div class="flex space-x-1">
            <?php if ($pagination['current_page'] > 1): ?>
            <a href="?page=<?php echo $pagination['current_page'] - 1; ?><?php echo (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''); ?><?php echo (!empty($serviceFilter) ? '&service=' . urlencode($serviceFilter) : ''); ?><?php echo (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''); ?><?php echo (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''); ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">&laquo;</a>
            <?php else: ?>
            <span class="px-3 py-1 border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">&laquo;</span>
            <?php endif; ?>
            
            <?php
            // Calculate range of page numbers to show
            $startPage = max(1, $pagination['current_page'] - 2);
            $endPage = min($pagination['total_pages'], $pagination['current_page'] + 2);
            
            // Always show first page
            if ($startPage > 1) {
                echo '<a href="?page=1' . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($serviceFilter) ? '&service=' . urlencode($serviceFilter) : '') . (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : '') . (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '') . '" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">1</a>';
                if ($startPage > 2) {
                    echo '<span class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600">...</span>';
                }
            }
            
            // Show page numbers
            for ($i = $startPage; $i <= $endPage; $i++) {
                if ($i == $pagination['current_page']) {
                    echo '<span class="px-3 py-1 border border-gray-300 rounded-md bg-blue-900 text-white">' . $i . '</span>';
                } else {
                    echo '<a href="?page=' . $i . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($serviceFilter) ? '&service=' . urlencode($serviceFilter) : '') . (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : '') . (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '') . '" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">' . $i . '</a>';
                }
            }
            
            // Always show last page
            if ($endPage < $pagination['total_pages']) {
                if ($endPage < $pagination['total_pages'] - 1) {
                    echo '<span class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600">...</span>';
                }
                echo '<a href="?page=' . $pagination['total_pages'] . (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : '') . (!empty($serviceFilter) ? '&service=' . urlencode($serviceFilter) : '') . (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : '') . (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : '') . '" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">' . $pagination['total_pages'] . '</a>';
            }
            ?>
            
            <?php if ($pagination['current_page'] < $pagination['total_pages']): ?>
            <a href="?page=<?php echo $pagination['current_page'] + 1; ?><?php echo (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''); ?><?php echo (!empty($serviceFilter) ? '&service=' . urlencode($serviceFilter) : ''); ?><?php echo (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''); ?><?php echo (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''); ?>" class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">&raquo;</a>
            <?php else: ?>
            <span class="px-3 py-1 border border-gray-300 rounded-md bg-gray-100 text-gray-400 cursor-not-allowed">&raquo;</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>
</main>

<!-- Toast Container for AJAX messages -->
<div id="toast-container"></div>