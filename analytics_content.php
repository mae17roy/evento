<!-- Analytics Content -->
<main class="flex-1 overflow-y-auto p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-2">Business Analytics</h2>
        <p class="text-gray-600">View your business performance and trends</p>
    </div>
    
    <!-- Summary Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white p-5 rounded-lg shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Total Revenue</h3>
                <i class="mif-dollar2 text-2xl text-green-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold">$<?php echo number_format($analytics['total_revenue'] ?? 0, 2); ?></h2>
                <?php if (isset($analytics['revenue_growth']) && $analytics['revenue_growth'] !== 0): ?>
                <div class="ml-4 text-sm <?php echo $analytics['revenue_growth'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <span class="flex items-center">
                        <i class="<?php echo $analytics['revenue_growth'] > 0 ? 'mif-arrow-up' : 'mif-arrow-down'; ?> mr-1"></i>
                        <?php echo abs($analytics['revenue_growth']); ?>%
                    </span>
                    <span>vs last period</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-lg shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Total Bookings</h3>
                <i class="mif-calendar-check text-2xl text-blue-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold"><?php echo number_format($analytics['total_bookings'] ?? 0); ?></h2>
                <?php if (isset($analytics['bookings_growth']) && $analytics['bookings_growth'] !== 0): ?>
                <div class="ml-4 text-sm <?php echo $analytics['bookings_growth'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <span class="flex items-center">
                        <i class="<?php echo $analytics['bookings_growth'] > 0 ? 'mif-arrow-up' : 'mif-arrow-down'; ?> mr-1"></i>
                        <?php echo abs($analytics['bookings_growth']); ?>%
                    </span>
                    <span>vs last period</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-lg shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Customers</h3>
                <i class="mif-users text-2xl text-purple-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold"><?php echo number_format($analytics['total_customers'] ?? 0); ?></h2>
                <?php if (isset($analytics['customers_growth']) && $analytics['customers_growth'] !== 0): ?>
                <div class="ml-4 text-sm <?php echo $analytics['customers_growth'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <span class="flex items-center">
                        <i class="<?php echo $analytics['customers_growth'] > 0 ? 'mif-arrow-up' : 'mif-arrow-down'; ?> mr-1"></i>
                        <?php echo abs($analytics['customers_growth']); ?>%
                    </span>
                    <span>vs last period</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white p-5 rounded-lg shadow-sm">
            <div class="flex justify-between items-center mb-3">
                <h3 class="text-lg font-medium text-gray-700">Avg. Order Value</h3>
                <i class="mif-chart-line text-2xl text-orange-600"></i>
            </div>
            <div class="flex items-end">
                <h2 class="text-3xl font-bold">$<?php echo number_format($analytics['avg_order_value'] ?? 0, 2); ?></h2>
                <?php if (isset($analytics['aov_growth']) && $analytics['aov_growth'] !== 0): ?>
                <div class="ml-4 text-sm <?php echo $analytics['aov_growth'] > 0 ? 'text-green-600' : 'text-red-600'; ?>">
                    <span class="flex items-center">
                        <i class="<?php echo $analytics['aov_growth'] > 0 ? 'mif-arrow-up' : 'mif-arrow-down'; ?> mr-1"></i>
                        <?php echo abs($analytics['aov_growth']); ?>%
                    </span>
                    <span>vs last period</span>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Date Range Selector -->
    <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <form method="get" action="analytics.php" class="flex flex-wrap items-end gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select name="period" class="border border-gray-300 rounded-md p-2">
                    <option value="7days" <?php echo $period === '7days' ? 'selected' : ''; ?>>Last 7 Days</option>
                    <option value="30days" <?php echo $period === '30days' ? 'selected' : ''; ?>>Last 30 Days</option>
                    <option value="90days" <?php echo $period === '90days' ? 'selected' : ''; ?>>Last 90 Days</option>
                    <option value="year" <?php echo $period === 'year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            
            <div id="custom-date-range" class="<?php echo $period === 'custom' ? 'flex' : 'hidden'; ?> items-center gap-2">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">From</label>
                    <input type="date" name="start_date" class="border border-gray-300 rounded-md p-2" value="<?php echo $start_date; ?>">
                </div>
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">To</label>
                    <input type="date" name="end_date" class="border border-gray-300 rounded-md p-2" value="<?php echo $end_date; ?>">
                </div>
            </div>
            
            <div>
                <button type="submit" class="bg-blue-900 text-white px-4 py-2 rounded-md">Apply</button>
            </div>
        </form>
    </div>
    
    <!-- Revenue Chart -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
        <div class="lg:col-span-2 bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium mb-4">Revenue Over Time</h3>
            <div class="h-80">
                <canvas id="revenueChart"></canvas>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm p-6">
            <h3 class="text-lg font-medium mb-4">Booking Status</h3>
            <div class="h-80">
                <canvas id="bookingStatusChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Top Services & Recent Customers -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium">Top Performing Services</h3>
            </div>
            <div class="p-6">
                <?php if (empty($topServices)): ?>
                    <div class="text-center text-gray-500 py-10">
                        <p>No service data available for the selected period.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($topServices as $index => $service): ?>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-8 h-8 bg-blue-100 text-blue-800 rounded-full flex items-center justify-center mr-3">
                                    <?php echo $index + 1; ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900 truncate">
                                        <?php echo htmlspecialchars($service['name']); ?>
                                    </p>
                                    <div class="flex justify-between">
                                        <p class="text-sm text-gray-500">
                                            <?php echo $service['booking_count']; ?> bookings
                                        </p>
                                        <p class="text-sm font-medium">
                                            $<?php echo number_format($service['revenue'], 2); ?>
                                        </p>
                                    </div>
                                    <div class="w-full bg-gray-200 rounded-full h-1.5 mt-1">
                                        <div class="bg-blue-600 h-1.5 rounded-full" style="width: <?php echo $service['percentage']; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium">Recent Customers</h3>
            </div>
            <div class="p-6">
                <?php if (empty($recentCustomers)): ?>
                    <div class="text-center text-gray-500 py-10">
                        <p>No customer data available for the selected period.</p>
                    </div>
                <?php else: ?>
                    <div class="space-y-4">
                        <?php foreach ($recentCustomers as $customer): ?>
                            <div class="flex items-center">
                                <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold mr-3">
                                    <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-medium text-gray-900">
                                        <?php echo htmlspecialchars($customer['name']); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo htmlspecialchars($customer['email']); ?>
                                    </p>
                                </div>
                                <div class="text-right">
                                    <p class="text-sm font-medium">
                                        $<?php echo number_format($customer['total_spent'], 2); ?>
                                    </p>
                                    <p class="text-xs text-gray-500">
                                        <?php echo $customer['booking_count']; ?> bookings
                                    </p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            <div class="px-6 py-3 bg-gray-50 border-t border-gray-200 text-right">
                <a href="customers.php" class="text-sm text-blue-600 hover:text-blue-800">View all customers</a>
            </div>
        </div>
    </div>
    
    <!-- Export Data Section -->
    <div class="bg-white rounded-lg shadow-sm p-6">
        <h3 class="text-lg font-medium mb-4">Export Analytics Data</h3>
        <p class="text-gray-600 mb-4">Download your analytics data for further analysis or reporting.</p>
        
        <form method="get" action="export_analytics.php" class="flex flex-wrap gap-4">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Export Type</label>
                <select name="export_type" class="border border-gray-300 rounded-md p-2">
                    <option value="bookings">Bookings</option>
                    <option value="revenue">Revenue</option>
                    <option value="customers">Customers</option>
                    <option value="services">Services</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Date Range</label>
                <select name="export_period" class="border border-gray-300 rounded-md p-2">
                    <option value="7days">Last 7 Days</option>
                    <option value="30days">Last 30 Days</option>
                    <option value="90days">Last 90 Days</option>
                    <option value="year">This Year</option>
                    <option value="all">All Time</option>
                </select>
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Format</label>
                <select name="format" class="border border-gray-300 rounded-md p-2">
                    <option value="csv">CSV</option>
                    <option value="excel">Excel</option>
                    <option value="pdf">PDF</option>
                </select>
            </div>
            
            <div class="flex items-end">
                <button type="submit" class="bg-blue-900 text-white px-4 py-2 rounded-md">
                    <i class="mif-file-download mr-1"></i> Export
                </button>
            </div>
        </form>
    </div>
</main>

<!-- Chart initialization Script (Will be moved to external JS in production) -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Show/hide custom date range based on period selection
    const periodSelect = document.querySelector('select[name="period"]');
    const customDateRange = document.getElementById('custom-date-range');
    
    if (periodSelect) {
        periodSelect.addEventListener('change', function() {
            if (this.value === 'custom') {
                customDateRange.classList.remove('hidden');
                customDateRange.classList.add('flex');
            } else {
                customDateRange.classList.add('hidden');
                customDateRange.classList.remove('flex');
            }
        });
    }
    
    // Chart initialization - You'll need to replace with your actual data
    // Revenue Chart
    const revenueCtx = document.getElementById('revenueChart');
    if (revenueCtx) {
        const revenueLabels = <?php echo json_encode(array_column($revenueData ?? [], 'label')); ?>;
        const revenueValues = <?php echo json_encode(array_column($revenueData ?? [], 'value')); ?>;
        
        new Chart(revenueCtx, {
            type: 'line',
            data: {
                labels: revenueLabels,
                datasets: [{
                    label: 'Revenue',
                    data: revenueValues,
                    borderColor: '#1E40AF',
                    backgroundColor: 'rgba(30, 64, 175, 0.1)',
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `$${context.raw.toFixed(2)}`;
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                return '$' + value;
                            }
                        }
                    }
                }
            }
        });
    }
    
    // Booking Status Chart
    const statusCtx = document.getElementById('bookingStatusChart');
    if (statusCtx) {
        const statusLabels = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
        const statusValues = [
            <?php echo $analytics['pending_bookings'] ?? 0; ?>, 
            <?php echo $analytics['confirmed_bookings'] ?? 0; ?>, 
            <?php echo $analytics['completed_bookings'] ?? 0; ?>, 
            <?php echo $analytics['cancelled_bookings'] ?? 0; ?>
        ];
        const statusColors = ['#F59E0B', '#1A2E46', '#10B981', '#EF4444'];
        
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    data: statusValues,
                    backgroundColor: statusColors,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const label = context.label || '';
                                const value = context.raw;
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? Math.round((value / total) * 100) : 0;
                                return `${label}: ${value} (${percentage}%)`;
                            }
                        }
                    }
                }
            }
        });
    }
});
</script>