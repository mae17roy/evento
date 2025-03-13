<!-- Dashboard Content -->
<main class="flex-1 overflow-y-auto p-6">
    <div class="mb-8">
        <h2 class="text-2xl font-bold mb-2">
            <?php if(!empty($user['business_name'])): ?>
            <?php echo htmlspecialchars($user['business_name']); ?> Dashboard
            <?php else: ?>
            EVENTO Dashboard
            <?php endif; ?>
        </h2>
        <p class="text-gray-600">Manage your event services and reservations</p>
    </div>
    
    <!-- Enhanced Metrics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-8 responsive-grid">
        <div class="bg-white p-5 rounded-lg shadow-sm metric-card">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Event Services</h3>
                <i class="mif-cogs text-2xl text-blue-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold"><?php echo isset($metrics['total_services']) ? $metrics['total_services'] : 0; ?></h2>
                <div class="ml-4 text-sm text-green-600">
                    <span class="flex items-center">
                        <i class="mif-arrow-up mr-1"></i>
                        <?php echo isset($metrics['active_services_percent']) ? $metrics['active_services_percent'] : 0; ?>%
                    </span>
                    <span>Active services</span>
                </div>
            </div>
            <div class="flex justify-between mt-4 text-sm text-gray-500">
                <div>
                    <div class="font-medium"><?php echo isset($metrics['active_services']) ? $metrics['active_services'] : 0; ?></div>
                    <div>Active</div>
                </div>
                <div>
                    <div class="font-medium"><?php echo isset($metrics['total_categories']) ? $metrics['total_categories'] : 0; ?></div>
                    <div>Categories</div>
                </div>
                <div>
                    <a href="service_management.php" class="text-blue-600 hover:text-blue-800">Manage</a>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-lg shadow-sm metric-card">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Bookings</h3>
                <i class="mif-calendar-check text-2xl text-green-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold"><?php echo isset($metrics['total_bookings']) ? $metrics['total_bookings'] : 0; ?></h2>
                <div class="ml-4 text-sm text-green-600">
                    <span class="flex items-center">
                        <i class="mif-arrow-up mr-1"></i>
                        <?php echo isset($metrics['confirmed_percent']) ? $metrics['confirmed_percent'] : 0; ?>%
                    </span>
                    <span>This month</span>
                </div>
            </div>
            <div class="flex justify-between mt-4 text-sm text-gray-500">
                <div>
                    <div class="font-medium"><?php echo isset($metrics['pending_reservations']) ? $metrics['pending_reservations'] : 0; ?></div>
                    <div>Pending</div>
                </div>
                <div>
                    <div class="font-medium"><?php echo isset($metrics['confirmed_bookings']) ? $metrics['confirmed_bookings'] : 0; ?></div>
                    <div>Confirmed</div>
                </div>
                <div>
                    <div class="font-medium"><?php echo isset($metrics['completed_bookings']) ? $metrics['completed_bookings'] : 0; ?></div>
                    <div>Completed</div>
                </div>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-lg shadow-sm metric-card">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Revenue</h3>
                <i class="mif-dollar2 text-2xl text-yellow-500"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold">$<?php echo number_format(isset($metrics['total_revenue']) ? $metrics['total_revenue'] : 0, 2); ?></h2>
                <div class="ml-4 text-sm text-green-600">
                    <span class="flex items-center">
                        <i class="mif-arrow-up mr-1"></i>
                        <?php echo isset($metrics['pending_percent']) ? $metrics['pending_percent'] : 0; ?>%
                    </span>
                    <span>Growth</span>
                </div>
            </div>
            <div class="flex justify-between mt-4 text-sm text-gray-500">
                <div>
                    <div class="font-medium">$<?php echo number_format(isset($metrics['completed_revenue']) ? $metrics['completed_revenue'] : 0, 2); ?></div>
                    <div>Completed</div>
                </div>
                <div>
                    <div class="font-medium"><?php echo isset($metrics['customer_count']) ? $metrics['customer_count'] : 0; ?></div>
                    <div>Customers</div>
                </div>
                <div>
                    <a href="analytics.php" class="text-blue-600 hover:text-blue-800">Analytics</a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Dashboard Grid - Two Columns on larger screens -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Left Column -->
        <div class="space-y-6">
            <!-- Upcoming Bookings -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium">Upcoming Bookings</h3>
                </div>
                
                <div class="overflow-x-auto">
                    <?php if(empty($upcomingBookings)): ?>
                        <div class="p-6 text-center text-gray-500">
                            <div class="text-4xl mb-2">📅</div>
                            <p>No upcoming bookings found</p>
                            <p class="text-sm mt-2">When clients book your services, they'll appear here.</p>
                        </div>
                    <?php else: ?>
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Customer</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Service</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date/Time</th>
                                <th class="px-4 py-3 text-left text-xs font-medium text-gray-500 uppercase">Status</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach($upcomingBookings as $booking): ?>
                            <tr>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo isset($booking['customer_name']) ? htmlspecialchars($booking['customer_name']) : 'Customer'; ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo isset($booking['service_name']) ? htmlspecialchars($booking['service_name']) : 'Service'; ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo isset($booking['booking_date']) ? formatDate($booking['booking_date']) : 'Date'; ?></div>
                                    <div class="text-xs text-gray-500"><?php echo isset($booking['booking_time']) ? formatTime($booking['booking_time']) : 'Time'; ?></div>
                                </td>
                                <td class="px-4 py-3 whitespace-nowrap">
                                    <span class="status-badge status-<?php echo isset($booking['status']) ? strtolower($booking['status']) : 'pending'; ?>">
                                        <?php echo isset($booking['status']) ? ucfirst($booking['status']) : 'Pending'; ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
                
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right">
                    <a href="reservations.php" class="text-sm text-blue-600 hover:text-blue-800">View all bookings</a>
                </div>
            </div>
            
            <!-- Top Performing Services -->
            <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium">Top Services</h3>
                </div>
                
                <div class="p-6">
                    <?php if(empty($topServices)): ?>
                        <div class="text-center text-gray-500">
                            <div class="text-4xl mb-2">🏆</div>
                            <p>No service statistics available yet</p>
                            <p class="text-sm mt-2">Start adding services and getting bookings to see your top performers.</p>
                            <a href="service_management.php" class="inline-block mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">Add Services</a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach($topServices as $index => $service): ?>
                                <div class="flex items-center">
                                    <div class="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center mr-3">
                                        <?php echo $index + 1; ?>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <p class="text-sm font-medium text-gray-900 truncate">
                                            <?php echo isset($service['name']) ? htmlspecialchars($service['name']) : 'Service'; ?>
                                        </p>
                                        <p class="text-sm text-gray-500">
                                            <?php echo isset($service['booking_count']) ? $service['booking_count'] : 0; ?> bookings · $<?php echo number_format(isset($service['revenue']) ? $service['revenue'] : 0, 2); ?> revenue
                                        </p>
                                    </div>
                                    <div class="flex-shrink-0 text-sm font-medium text-gray-900">
                                        $<?php echo number_format(isset($service['price']) ? $service['price'] : 0, 2); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right">
                    <a href="service_management.php" class="text-sm text-blue-600 hover:text-blue-800">Manage services</a>
                </div>
            </div>
        </div>
        
        <!-- Right Column -->
        <div class="space-y-6">
            <!-- Recent Reservations -->
            <div class="bg-white rounded-lg shadow-sm mb-6">
                <div class="flex justify-between items-center p-6 border-b border-gray-200">
                    <h3 class="text-lg font-medium">Recent Reservations</h3>
                    <button id="filter-toggle" class="flex items-center text-gray-600 text-sm">
                        <i class="mif-filter mr-2"></i>
                        Filter
                    </button>
                </div>
                
                <!-- Filter Options (hidden by default) -->
                <div id="filter-options" class="hidden p-4 bg-gray-50 border-b border-gray-200">
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                            <select id="status-filter" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="">All Statuses</option>
                                <option value="pending">Pending</option>
                                <option value="confirmed">Confirmed</option>
                                <option value="completed">Completed</option>
                                <option value="cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Service</label>
                            <select id="service-filter" class="w-full p-2 border border-gray-300 rounded-md">
                                <option value="">All Services</option>
                                <?php
                                try {
                                    $serviceQuery = $db->prepare("SELECT id, name FROM services WHERE owner_id = ? ORDER BY name");
                                    $serviceQuery->execute([$_SESSION['user_id']]);
                                    while ($service = $serviceQuery->fetch()) {
                                        echo "<option value='{$service['id']}'>{$service['name']}</option>";
                                    }
                                } catch (Exception $e) {
                                    error_log("Error fetching services for filter: " . $e->getMessage());
                                }
                                ?>
                            </select>
                        </div>
                        <div class="flex items-end">
                            <button id="apply-filters" class="bg-blue-900 text-white px-4 py-2 rounded-md">Apply</button>
                            <button id="clear-filters" class="ml-2 bg-gray-100 text-gray-800 px-4 py-2 rounded-md">Clear</button>
                        </div>
                    </div>
                </div>
                
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Customer</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Service</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date & Time</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200" id="reservations-table-body">
                            <?php if(empty($reservations)): ?>
                            <tr>
                                <td colspan="5" class="px-6 py-4 text-center text-gray-500">
                                    No reservations found.
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($reservations as $reservation): ?>
                                <tr data-booking-id="<?php echo isset($reservation['id']) ? $reservation['id'] : '0'; ?>">
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?php echo isset($reservation['customer']) ? htmlspecialchars($reservation['customer']) : 'Customer'; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo isset($reservation['email']) ? htmlspecialchars($reservation['email']) : 'Email'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo isset($reservation['service']) ? htmlspecialchars($reservation['service']) : 'Service'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="text-sm text-gray-900"><?php echo isset($reservation['booking_date']) ? formatDate($reservation['booking_date']) : 'Date'; ?></div>
                                        <div class="text-sm text-gray-500"><?php echo isset($reservation['booking_time']) ? formatTime($reservation['booking_time']) : 'Time'; ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="status-badge status-<?php echo isset($reservation['status']) ? strtolower($reservation['status']) : 'pending'; ?>">
                                            <?php echo isset($reservation['status']) ? ucfirst($reservation['status']) : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-center">
                                        <div class="relative dropdown">
                                            <button class="text-gray-400 hover:text-gray-600 dropdown-toggle">
                                                <i class="mif-dots-vertical"></i>
                                            </button>
                                            <div class="dropdown-menu hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-10">
                                                <div class="py-1">
                                                    <a href="view_booking.php?id=<?php echo isset($reservation['id']) ? $reservation['id'] : '0'; ?>" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">View Details</a>
                                                    
                                                    <?php if (isset($reservation['status']) && $reservation['status'] === 'pending'): ?>
                                                    <form method="post" action="owner_index.php">
                                                        <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                                        <input type="hidden" name="status" value="confirmed">
                                                        <button type="submit" name="update_booking_status" class="block w-full text-left px-4 py-2 text-sm text-green-700 hover:bg-gray-100">Approve</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (isset($reservation['status']) && $reservation['status'] === 'confirmed'): ?>
                                                    <form method="post" action="owner_index.php">
                                                        <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                                        <input type="hidden" name="status" value="completed">
                                                        <button type="submit" name="update_booking_status" class="block w-full text-left px-4 py-2 text-sm text-blue-700 hover:bg-gray-100">Mark as Completed</button>
                                                    </form>
                                                    <?php endif; ?>
                                                    
                                                    <?php if (isset($reservation['status']) && $reservation['status'] !== 'cancelled' && $reservation['status'] !== 'completed'): ?>
                                                    <form method="post" action="owner_index.php">
                                                        <input type="hidden" name="booking_id" value="<?php echo $reservation['id']; ?>">
                                                        <input type="hidden" name="status" value="cancelled">
                                                        <button type="submit" name="update_booking_status" class="block w-full text-left px-4 py-2 text-sm text-red-700 hover:bg-gray-100">Cancel</button>
                                                    </form>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                
                <div class="p-4 border-t border-gray-200 text-center">
                    <a href="reservations.php" class="text-sm text-blue-600 hover:text-blue-800">View all reservations</a>
                </div>
            </div>
            
            <!-- Recent Reviews -->
            <div class="bg-white rounded-lg shadow-sm">
                <div class="px-6 py-4 border-b border-gray-200">
                    <h3 class="text-lg font-medium">Recent Reviews</h3>
                </div>
                
                <div class="p-6">
                    <?php if(empty($recentReviews)): ?>
                        <div class="text-center text-gray-500">
                            <div class="text-4xl mb-2">⭐</div>
                            <p>No reviews yet</p>
                            <p class="text-sm mt-2">When clients review your services, they'll appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-6">
                            <?php foreach($recentReviews as $review): ?>
                                <div class="border-b border-gray-200 pb-6 last:border-0 last:pb-0">
                                    <div class="flex justify-between mb-2">
                                        <div>
                                            <div class="font-medium"><?php echo isset($review['customer_name']) ? htmlspecialchars($review['customer_name']) : 'Customer'; ?></div>
                                            <div class="text-sm text-gray-500"><?php echo isset($review['service_name']) ? htmlspecialchars($review['service_name']) : 'Service'; ?></div>
                                        </div>
                                        <div class="rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo isset($review['rating']) && $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo isset($review['comment']) ? htmlspecialchars($review['comment']) : ''; ?></p>
                                    <div class="text-xs text-gray-500 mt-2"><?php echo isset($review['created_at']) ? date('F j, Y', strtotime($review['created_at'])) : ''; ?></div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right">
                    <a href="reviews.php" class="text-sm text-blue-600 hover:text-blue-800">View all reviews</a>
                </div>
            </div>
        </div>
    </div>
</main>