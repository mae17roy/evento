<!-- Dashboard Content -->
<main class="flex-1 overflow-y-auto p-6">
    <div class="mb-6">
        <h2 class="text-2xl font-bold mb-1">Customer Management</h2>
        <p class="text-gray-600">Manage your customer relationships and communication</p>
    </div>
    
    <div class="flex flex-col lg:flex-row gap-6">
        <!-- Customer List -->
        <div class="lg:w-1/3 bg-white rounded-lg shadow-sm overflow-hidden">
            <div class="px-6 py-4 border-b border-gray-200">
                <h3 class="text-lg font-medium">Your Customers</h3>
            </div>
            
            <div class="p-4">
                <div class="relative mb-4">
                    <input type="text" id="customer-search" placeholder="Filter customers..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md">
                    <i class="mif-search absolute left-3 top-2.5 text-gray-400"></i>
                </div>
            </div>
            
            <div class="overflow-y-auto" style="max-height: 600px;">
                <?php if(empty($customers)): ?>
                    <div class="p-6 text-center text-gray-500">
                        <div class="text-4xl mb-2">👤</div>
                        <p>No customers found</p>
                        <p class="text-sm mt-2">When clients book your services, they'll appear here.</p>
                    </div>
                <?php else: ?>
                    <ul class="divide-y divide-gray-200" id="customer-list">
                        <?php foreach($customers as $customer): ?>
                        <li>
                            <a href="customers.php?client_id=<?php echo $customer['id']; ?>" class="block hover:bg-gray-50 <?php echo (isset($_GET['client_id']) && $_GET['client_id'] == $customer['id']) ? 'bg-blue-50' : ''; ?>">
                                <div class="p-4">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 mr-3">
                                            <div class="w-10 h-10 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold">
                                                <?php echo strtoupper(substr($customer['name'], 0, 1)); ?>
                                            </div>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-sm font-medium text-gray-900 truncate"><?php echo htmlspecialchars($customer['name']); ?></p>
                                            <p class="text-sm text-gray-500 truncate"><?php echo htmlspecialchars($customer['email']); ?></p>
                                        </div>
                                        <div class="ml-4 flex-shrink-0 text-right">
                                            <p class="text-sm font-medium text-gray-900"><?php echo $customer['booking_count']; ?> bookings</p>
                                            <p class="text-xs text-gray-500"><?php echo formatTimeAgo($customer['last_booking']); ?></p>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Customer Details -->
        <div class="lg:w-2/3 bg-white rounded-lg shadow-sm overflow-hidden">
            <?php if ($selectedCustomer): ?>
                <div class="px-6 py-4 border-b border-gray-200 flex items-center justify-between">
                    <div class="flex items-center">
                        <div class="w-12 h-12 rounded-full bg-gray-300 flex items-center justify-center text-gray-600 font-bold mr-4">
                            <?php echo strtoupper(substr($selectedCustomer['customer']['name'], 0, 1)); ?>
                        </div>
                        <div>
                            <h3 class="text-lg font-medium"><?php echo htmlspecialchars($selectedCustomer['customer']['name']); ?></h3>
                            <p class="text-gray-500">Client since <?php echo date('F Y', strtotime($selectedCustomer['customer']['created_at'])); ?></p>
                        </div>
                    </div>
                    <div class="flex space-x-2">
                        <a href="mailto:<?php echo htmlspecialchars($selectedCustomer['customer']['email']); ?>" class="p-2 text-gray-500 hover:text-gray-700">
                            <i class="mif-mail"></i>
                        </a>
                        <?php if (!empty($selectedCustomer['customer']['phone'])): ?>
                        <a href="tel:<?php echo htmlspecialchars($selectedCustomer['customer']['phone']); ?>" class="p-2 text-gray-500 hover:text-gray-700">
                            <i class="mif-phone"></i>
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="lg:grid lg:grid-cols-2 lg:gap-6 p-6">
                    <div>
                        <div class="mb-6">
                            <h4 class="text-md font-medium mb-2">Contact Information</h4>
                            <div class="space-y-2">
                                <div class="flex">
                                    <div class="w-20 text-gray-500">Email:</div>
                                    <div class="font-medium"><?php echo htmlspecialchars($selectedCustomer['customer']['email']); ?></div>
                                </div>
                                <div class="flex">
                                    <div class="w-20 text-gray-500">Phone:</div>
                                    <div class="font-medium"><?php echo !empty($selectedCustomer['customer']['phone']) ? htmlspecialchars($selectedCustomer['customer']['phone']) : 'Not provided'; ?></div>
                                </div>
                                <div class="flex">
                                    <div class="w-20 text-gray-500">Address:</div>
                                    <div class="font-medium"><?php echo !empty($selectedCustomer['customer']['address']) ? htmlspecialchars($selectedCustomer['customer']['address']) : 'Not provided'; ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <h4 class="text-md font-medium mb-2">Booking History</h4>
                            <?php if (empty($selectedCustomer['bookings'])): ?>
                                <p class="text-gray-500">No booking history available.</p>
                            <?php else: ?>
                                <div class="space-y-4 max-h-80 overflow-y-auto">
                                    <?php foreach($selectedCustomer['bookings'] as $booking): ?>
                                    <div class="border border-gray-200 rounded-md p-3">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <p class="font-medium"><?php echo htmlspecialchars($booking['service_name']); ?></p>
                                                <p class="text-sm text-gray-500">
                                                    <?php echo formatDate($booking['booking_date']); ?> at <?php echo formatTime($booking['booking_time']); ?>
                                                </p>
                                            </div>
                                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                                <?php echo ucfirst($booking['status']); ?>
                                            </span>
                                        </div>
                                        <div class="mt-2 text-sm text-gray-500">
                                            <span>Amount: $<?php echo number_format($booking['total_amount'], 2); ?></span>
                                            <span class="ml-4">Booked on: <?php echo date('M j, Y', strtotime($booking['created_at'])); ?></span>
                                        </div>
                                        <div class="mt-2">
                                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="text-xs text-blue-600 hover:text-blue-800">View details</a>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if (!empty($selectedCustomer['reviews'])): ?>
                        <div>
                            <h4 class="text-md font-medium mb-2">Reviews</h4>
                            <div class="space-y-4 max-h-80 overflow-y-auto">
                                <?php foreach($selectedCustomer['reviews'] as $review): ?>
                                <div class="border border-gray-200 rounded-md p-3">
                                    <div class="flex justify-between items-start mb-2">
                                        <p class="font-medium"><?php echo htmlspecialchars($review['service_name']); ?></p>
                                        <div class="rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <span class="star <?php echo $i <= $review['rating'] ? 'filled' : ''; ?>">★</span>
                                            <?php endfor; ?>
                                        </div>
                                    </div>
                                    <p class="text-sm text-gray-600"><?php echo htmlspecialchars($review['comment']); ?></p>
                                    <p class="text-xs text-gray-500 mt-1"><?php echo date('M j, Y', strtotime($review['created_at'])); ?></p>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    
                    <div>
                        <h4 class="text-md font-medium mb-2">Message Center</h4>
                        
                        <div class="border border-gray-200 rounded-md p-3 mb-4">
                            <div class="message-container mb-4" id="message-container">
                                <?php if (empty($messageHistory)): ?>
                                    <div class="text-center text-gray-500 py-4">
                                        <p>No messages yet.</p>
                                        <p class="text-sm">Start a conversation with this customer.</p>
                                    </div>
                                <?php else: ?>
                                    <?php foreach($messageHistory as $message): ?>
                                        <div class="flex flex-col <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'items-end' : 'items-start'; ?>">
                                            <div class="message-bubble <?php echo $message['sender_id'] == $_SESSION['user_id'] ? 'message-sent' : 'message-received'; ?>">
                                                <?php echo nl2br(htmlspecialchars($message['message'])); ?>
                                            </div>
                                            <div class="text-xs text-gray-500 mb-2">
                                                <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                            
                            <form method="post" action="customers.php?client_id=<?php echo $_GET['client_id']; ?>" id="message-form">
                                <input type="hidden" name="client_id" value="<?php echo $_GET['client_id']; ?>">
                                <div class="flex">
                                    <textarea name="message" rows="3" placeholder="Type your message here..." class="flex-1 p-2 border border-gray-300 rounded-md mr-2" required></textarea>
                                    <button type="submit" name="send_message" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">
                                        <i class="mif-paper-plane"></i>
                                    </button>
                                </div>
                            </form>
                        </div>
                        
                        <div>
                            <h4 class="text-md font-medium mb-2">Quick Actions</h4>
                            <div class="grid grid-cols-2 gap-3">
                                <a href="create_booking.php?client_id=<?php echo $_GET['client_id']; ?>" class="bg-gray-100 hover:bg-gray-200 text-center py-2 rounded-md">
                                    <i class="mif-calendar-plus"></i>
                                    <span class="block text-sm">New Booking</span>
                                </a>
                                <a href="send_special_offer.php?client_id=<?php echo $_GET['client_id']; ?>" class="bg-gray-100 hover:bg-gray-200 text-center py-2 rounded-md">
                                    <i class="mif-gift"></i>
                                    <span class="block text-sm">Special Offer</span>
                                </a>
                                <a href="export_customer_data.php?client_id=<?php echo $_GET['client_id']; ?>" class="bg-gray-100 hover:bg-gray-200 text-center py-2 rounded-md">
                                    <i class="mif-file-download"></i>
                                    <span class="block text-sm">Export Data</span>
                                </a>
                                <a href="#" class="bg-gray-100 hover:bg-gray-200 text-center py-2 rounded-md" onclick="addNote(<?php echo $_GET['client_id']; ?>)">
                                    <i class="mif-pencil"></i>
                                    <span class="block text-sm">Add Note</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="p-6 text-center">
                    <div class="text-5xl text-gray-300 mb-4">👤</div>
                    <h3 class="text-lg font-medium mb-2">Select a customer to view details</h3>
                    <p class="text-gray-500 max-w-md mx-auto">
                        Choose a customer from the list to view their details, booking history, and send messages.
                    </p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</main>

<!-- Add Note Modal -->
<div id="add-note-modal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white rounded-lg max-w-md w-full p-6">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-medium">Add Customer Note</h3>
            <button onclick="closeNoteModal()" class="text-gray-500 hover:text-gray-700">
                <i class="mif-cross"></i>
            </button>
        </div>
        <form action="add_customer_note.php" method="post">
            <input type="hidden" name="client_id" id="note-client-id">
            <div class="mb-4">
                <label for="note" class="block text-sm font-medium text-gray-700 mb-1">Note</label>
                <textarea name="note" id="note" rows="4" class="w-full p-2 border border-gray-300 rounded-md" required></textarea>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="closeNoteModal()" class="bg-gray-200 text-gray-800 px-4 py-2 rounded-md mr-2">Cancel</button>
                <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded-md hover:bg-blue-700">Save Note</button>
            </div>
        </form>
    </div>
</div>