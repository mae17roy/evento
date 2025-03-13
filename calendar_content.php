<!-- Calendar Content -->
<main class="flex-1 overflow-y-auto p-6">
    <!-- Month Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Total Bookings</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($monthlyStats['total'] ?? 0); ?></h3>
                </div>
                <div class="bg-blue-100 p-3 rounded-full">
                    <i class="mif-calendar text-blue-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Confirmed</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($monthlyStats['confirmed'] ?? 0); ?></h3>
                </div>
                <div class="bg-green-100 p-3 rounded-full">
                    <i class="mif-checkmark text-green-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Pending</p>
                    <h3 class="text-2xl font-bold"><?php echo number_format($monthlyStats['pending'] ?? 0); ?></h3>
                </div>
                <div class="bg-yellow-100 p-3 rounded-full">
                    <i class="mif-history text-yellow-600"></i>
                </div>
            </div>
        </div>
        
        <div class="bg-white rounded-lg p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-gray-500">Revenue</p>
                    <h3 class="text-2xl font-bold">$<?php echo number_format($monthlyStats['total_revenue'] ?? 0, 2); ?></h3>
                </div>
                <div class="bg-indigo-100 p-3 rounded-full">
                    <i class="mif-dollar text-indigo-600"></i>
                </div>
            </div>
        </div>
    </div>
    
    <div class="flex justify-between items-center mb-6">
        <h2 class="text-2xl font-bold"><?php echo date('F Y', strtotime($currentDate)); ?></h2>
        
        <div class="flex items-center space-x-4">
            <a href="calendar.php?date=<?php echo $prevMonth; ?>" class="bg-white px-3 py-1 rounded-md border border-gray-300 flex items-center">
                <i class="mif-arrow-left mr-1"></i>
                <span>Prev</span>
            </a>
            
            <form method="get" action="calendar.php" class="flex items-center">
                <input type="month" name="month" value="<?php echo date('Y-m', strtotime($currentDate)); ?>" class="border border-gray-300 rounded-md px-3 py-1 mr-2">
                <button type="submit" class="bg-blue-900 text-white px-3 py-1 rounded-md">Go</button>
            </form>
            
            <a href="calendar.php?date=<?php echo date('Y-m-d'); ?>" class="bg-gray-100 px-3 py-1 rounded-md">Today</a>
            
            <a href="calendar.php?date=<?php echo $nextMonth; ?>" class="bg-white px-3 py-1 rounded-md border border-gray-300 flex items-center">
                <span>Next</span>
                <i class="mif-arrow-right ml-1"></i>
            </a>
        </div>
    </div>
    
    <!-- Calendar Grid -->
    <div class="bg-white rounded-md shadow-sm mb-6">
        <!-- Days of Week Header -->
        <div class="grid grid-cols-7 gap-px bg-gray-200">
            <div class="bg-gray-100 p-2 text-center font-medium">Sun</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Mon</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Tue</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Wed</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Thu</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Fri</div>
            <div class="bg-gray-100 p-2 text-center font-medium">Sat</div>
        </div>
        
        <!-- Calendar Days -->
        <div class="grid grid-cols-7 gap-px bg-gray-200">
            <?php
            // Add empty cells for days before the first day of the month
            for ($i = 0; $i < $firstDayOfWeek; $i++) {
                echo '<div class="bg-white p-2 text-gray-300"></div>';
            }
            
            // Add cells for each day of the month
            for ($day = 1; $day <= $totalDays; $day++) {
                $date = sprintf('%s-%02d-%02d', $currentYear, $currentMonth, $day);
                $isToday = ($date === date('Y-m-d'));
                $hasEvents = isset($bookingsByDate[$date]);
                $dayClass = 'bg-white p-2 calendar-day cursor-pointer';
                
                if ($isToday) {
                    $dayClass .= ' today';
                }
                
                if ($hasEvents) {
                    $dayClass .= ' has-events';
                    $eventCount = count($bookingsByDate[$date]);
                }
                
                echo '<div class="' . $dayClass . '" data-date="' . $date . '">';
                echo '<div class="font-medium mb-2">' . $day . '</div>';
                
                // Show event count badge if there are events
                if ($hasEvents) {
                    echo '<span class="event-count">' . $eventCount . '</span>';
                    
                    // Display a preview of events for this day (limited to 3)
                    echo '<div class="space-y-1 overflow-y-auto" style="max-height: 80px;">';
                    $displayCount = min(count($bookingsByDate[$date]), 2);
                    
                    for ($i = 0; $i < $displayCount; $i++) {
                        $event = $bookingsByDate[$date][$i];
                        $eventClass = 'event-' . $event['status'];
                        echo '<div class="block p-1 text-xs rounded ' . $eventClass . '">';
                        echo '<div class="font-medium truncate">' . formatTime($event['booking_time']) . ' - ' . htmlspecialchars($event['service_name']) . '</div>';
                        echo '</div>';
                    }
                    
                    // Show "more" indicator if there are more events
                    if (count($bookingsByDate[$date]) > $displayCount) {
                        $remaining = count($bookingsByDate[$date]) - $displayCount;
                        echo '<div class="text-xs text-center text-gray-500 mt-1">+' . $remaining . ' more</div>';
                    }
                    
                    echo '</div>';
                }
                
                echo '</div>';
            }
            
            // Add empty cells for days after the last day of the month
            $lastDayOfWeek = date('w', strtotime($lastDayOfMonth));
            for ($i = $lastDayOfWeek + 1; $i < 7; $i++) {
                echo '<div class="bg-white p-2 text-gray-300"></div>';
            }
            ?>
        </div>
    </div>
    
    <!-- Legend -->
    <div class="bg-white rounded-md shadow-sm p-4">
        <h3 class="text-lg font-medium mb-3">Booking Status Legend</h3>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="flex items-center">
                <div class="w-4 h-4 bg-yellow-50 border-l-4 border-yellow-500 mr-2"></div>
                <span>Pending</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-green-50 border-l-4 border-green-500 mr-2"></div>
                <span>Confirmed</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-blue-50 border-l-4 border-blue-500 mr-2"></div>
                <span>Completed</span>
            </div>
            <div class="flex items-center">
                <div class="w-4 h-4 bg-red-50 border-l-4 border-red-500 mr-2"></div>
                <span>Cancelled</span>
            </div>
        </div>
    </div>
</main>

<!-- Day Details Modal -->
<div id="day-modal" class="day-modal">
    <div class="day-modal-content p-5">
        <div class="flex justify-between items-center mb-4 pb-3 border-b">
            <h3 class="text-xl font-bold" id="modal-day-title">Date Title</h3>
            <button id="close-day-modal" class="text-gray-400 hover:text-gray-600">
                <i class="mif-cross"></i>
            </button>
        </div>
        <div id="modal-day-content">
            <!-- Content will be loaded via AJAX -->
            <div class="flex justify-center p-6">
                <div class="animate-spin rounded-full h-12 w-12 border-b-2 border-blue-900"></div>
            </div>
        </div>
    </div>
</div>