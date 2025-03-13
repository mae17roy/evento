<!-- Service Management Content -->
<main class="flex-1 overflow-y-auto p-4 md:p-6">
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-6">
        <h2 class="text-xl md:text-2xl font-bold mb-4 md:mb-0">
            Services
            <?php if (!empty($search)): ?>
                <span class="text-sm font-normal text-gray-600 ml-2">
                    Search results for "<?php echo htmlspecialchars($search); ?>"
                    <a href="service_management.php" class="text-blue-600 ml-2">(Clear)</a>
                </span>
            <?php endif; ?>
        </h2>
        <button id="add-service-btn" class="bg-blue-900 text-white px-4 py-2 rounded-md flex items-center">
            <i class="mif-plus mr-2"></i>
            Add New Service
        </button>
    </div>
    
    <?php if (empty($services)): ?>
        <div class="bg-white rounded-lg shadow-sm p-8 text-center">
            <i class="mif-search text-5xl text-gray-400 mb-4"></i>
            <h3 class="text-xl font-medium text-gray-700 mb-2">No services found</h3>
            <?php if (!empty($search)): ?>
                <p class="text-gray-500 mb-4">No services match your search criteria "<?php echo htmlspecialchars($search); ?>"</p>
                <a href="service_management.php" class="text-blue-600">Clear search</a>
            <?php else: ?>
                <p class="text-gray-500 mb-4">Start by adding your first service</p>
                <button id="add-first-service-btn" class="bg-blue-900 text-white px-4 py-2 rounded-md">
                    <i class="mif-plus mr-2"></i>
                    Add Service
                </button>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <!-- Service Cards -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4 md:gap-6 mb-8">
            <?php foreach ($services as $service): ?>
            <div class="bg-white rounded-lg shadow-sm overflow-hidden service-card" data-service-id="<?php echo $service['id']; ?>">
                <div class="h-40 bg-gray-200 relative">
                    <img src="images/<?php echo htmlspecialchars($service['image']); ?>" alt="<?php echo htmlspecialchars($service['name']); ?>" class="w-full h-full object-cover">
                    <span class="absolute top-2 right-2 px-2 py-1 bg-white rounded-md text-sm font-medium">
                        $<?php echo number_format($service['price'], 2); ?>
                    </span>
                    
                    <?php 
                    // Get badge class based on availability status
                    $statusClass = '';
                    $statusText = isset($service['availability_status']) ? $service['availability_status'] : 'Unavailable';
                    
                    switch ($statusText) {
                        case 'Available':
                            $statusClass = 'status-available';
                            break;
                        case 'Limited':
                            $statusClass = 'status-limited';
                            break;
                        case 'Coming Soon':
                            $statusClass = 'status-coming-soon';
                            break;
                        default:
                            $statusClass = 'status-unavailable';
                            $statusText = 'Unavailable';
                    }
                    ?>
                    
                    <span class="absolute bottom-2 left-2 status-badge <?php echo $statusClass; ?>">
                        <?php echo $statusText; ?>
                    </span>
                </div>
                <div class="p-4 card-body">
                    <div class="flex justify-between items-start mb-2">
                        <div>
                            <h3 class="font-bold text-lg"><?php echo htmlspecialchars($service['name']); ?></h3>
                            <p class="text-sm text-gray-500"><?php echo htmlspecialchars($service['category_name']); ?></p>
                        </div>
                    </div>
                    <p class="text-gray-600 text-sm mb-4 line-clamp-2"><?php echo htmlspecialchars($service['description']); ?></p>
                    <div class="flex justify-between card-footer">
                        <button class="edit-service-btn text-blue-600 hover:text-blue-800 text-sm" 
                                data-service-id="<?php echo $service['id']; ?>">
                            <i class="mif-pencil mr-1"></i> Edit
                        </button>
                        <button class="delete-service-btn text-red-600 hover:text-red-800 text-sm" 
                                data-service-id="<?php echo $service['id']; ?>" 
                                data-service-name="<?php echo htmlspecialchars($service['name']); ?>">
                            <i class="mif-bin mr-1"></i> Delete
                        </button>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="flex justify-center my-6">
            <div class="inline-flex rounded-md shadow-sm">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-l-md bg-white text-gray-600 hover:bg-gray-50">
                        Previous
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1 border border-gray-300 rounded-l-md bg-gray-100 text-gray-400 cursor-not-allowed">
                        Previous
                    </span>
                <?php endif; ?>
                
                <?php
                // Calculate range of page numbers to show
                $startPage = max(1, $page - 2);
                $endPage = min($totalPages, $page + 2);
                
                // Always show first page
                if ($startPage > 1) {
                    echo '<a href="?page=1' . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                             class="px-3 py-1 border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                             1
                          </a>';
                    if ($startPage > 2) {
                        echo '<span class="px-3 py-1 border border-gray-300 bg-white text-gray-600">
                            ...
                          </span>';
                    }
                }
                
                // Show page numbers
                for ($i = $startPage; $i <= $endPage; $i++) {
                    if ($i == $page) {
                        echo '<span class="px-3 py-1 border border-gray-300 bg-blue-900 text-white">
                                ' . $i . '
                              </span>';
                    } else {
                        echo '<a href="?page=' . $i . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                                 class="px-3 py-1 border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                                 ' . $i . '
                              </a>';
                    }
                }
                
                // Always show last page
                if ($endPage < $totalPages) {
                    if ($endPage < $totalPages - 1) {
                        echo '<span class="px-3 py-1 border border-gray-300 bg-white text-gray-600">
                            ...
                          </span>';
                    }
                    echo '<a href="?page=' . $totalPages . (!empty($search) ? '&search=' . urlencode($search) : '') . '" 
                             class="px-3 py-1 border border-gray-300 bg-white text-gray-600 hover:bg-gray-50">
                             ' . $totalPages . '
                          </a>';
                }
                ?>
                
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?><?php echo !empty($search) ? '&search=' . urlencode($search) : ''; ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-r-md bg-white text-gray-600 hover:bg-gray-50">
                        Next
                    </a>
                <?php else: ?>
                    <span class="px-3 py-1 border border-gray-300 rounded-r-md bg-gray-100 text-gray-400 cursor-not-allowed">
                        Next
                    </span>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</main>

<!-- Modals for Add, Edit, Delete Service -->
<!-- Add Service Modal -->
<div id="add-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-4">
            <h3 class="text-xl font-bold">Add New Service</h3>
            <button id="close-add-modal" class="text-gray-400 hover:text-gray-600">
                <i class="mif-cross"></i>
            </button>
        </div>
        
        <form method="post" action="service_management.php" enctype="multipart/form-data" id="add-service-form">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="name">Service Name *</label>
                    <input type="text" id="name" name="name" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="category_id">Category *</label>
                    <select id="category_id" name="category_id" required class="w-full p-2 border border-gray-300 rounded-md">
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="price">Price ($) *</label>
                    <input type="number" id="price" name="price" step="0.01" min="0.01" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="availability_status">Availability Status *</label>
                    <select id="availability_status" name="availability_status" required class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="Available">Available</option>
                        <option value="Limited">Limited</option>
                        <option value="Coming Soon">Coming Soon</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="description">Description</label>
                <textarea id="description" name="description" rows="4" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="service_image">Service Image</label>
                <div class="flex items-center space-x-4 mb-2">
                    <div class="w-full">
                        <input type="file" id="service_image" name="service_image" accept="image/*" class="w-full p-2 border border-gray-300 rounded-md">
                        <p class="text-sm text-gray-500 mt-1">Recommended size: 800x600 pixels. Maximum file size: 5MB.</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancel-add" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" name="add_service" class="bg-blue-900 text-white px-4 py-2 rounded-md">Add Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Service Modal -->
<div id="edit-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-2xl p-6 mx-4 max-h-screen overflow-y-auto">
        <div class="flex justify-between items-center mb-4 border-b border-gray-200 pb-4">
            <h3 class="text-xl font-bold">Edit Service</h3>
            <button id="close-edit-modal" class="text-gray-400 hover:text-gray-600">
                <i class="mif-cross"></i>
            </button>
        </div>
        
        <form method="post" action="service_management.php" id="edit-service-form" enctype="multipart/form-data">
            <input type="hidden" id="edit_service_id" name="service_id" value="">
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_name">Service Name *</label>
                    <input type="text" id="edit_name" name="name" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_category_id">Category *</label>
                    <select id="edit_category_id" name="category_id" required class="w-full p-2 border border-gray-300 rounded-md">
                        <?php foreach ($categories as $category): ?>
                        <option value="<?php echo $category['id']; ?>"><?php echo htmlspecialchars($category['name']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_price">Price ($) *</label>
                    <input type="number" id="edit_price" name="price" step="0.01" min="0.01" required class="w-full p-2 border border-gray-300 rounded-md">
                </div>
                
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_availability_status">Availability Status *</label>
                    <select id="edit_availability_status" name="availability_status" required class="w-full p-2 border border-gray-300 rounded-md">
                        <option value="Available">Available</option>
                        <option value="Limited">Limited</option>
                        <option value="Coming Soon">Coming Soon</option>
                        <option value="Unavailable">Unavailable</option>
                    </select>
                </div>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_description">Description</label>
                <textarea id="edit_description" name="description" rows="4" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
            </div>
            
            <div class="mb-4">
                <label class="block text-sm font-medium text-gray-700 mb-1" for="edit_service_image">Service Image</label>
                <div class="flex items-center space-x-4 mb-2">
                    <div id="current_image_container" class="w-20 h-20 bg-gray-200 rounded-md flex items-center justify-center overflow-hidden">
                        <!-- Current image will be displayed here via JavaScript -->
                    </div>
                    <div class="flex-1">
                        <p class="text-sm text-gray-600" id="current_image_name"></p>
                        <p class="text-xs text-gray-500">Current image</p>
                        <input type="file" id="edit_service_image" name="service_image" accept="image/*" class="w-full mt-2 p-2 border border-gray-300 rounded-md">
                        <p class="text-sm text-gray-500 mt-1">Upload a new image only if you want to change the current one.</p>
                    </div>
                </div>
            </div>
            
            <div class="flex justify-end space-x-2">
                <button type="button" id="cancel-edit" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" name="update_service" class="bg-blue-900 text-white px-4 py-2 rounded-md">Update Service</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Service Confirmation Modal -->
<div id="delete-service-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
    <div class="bg-white rounded-lg shadow-lg w-full max-w-md p-6 mx-4">
        <div class="text-center mb-6">
            <i class="mif-warning text-5xl text-red-500 mb-4"></i>
            <h3 class="text-xl font-bold">Delete Service</h3>
            <p class="text-gray-600 mt-2" id="delete-service-message">Are you sure you want to delete this service?</p>
        </div>
        
        <form method="post" action="service_management.php" id="delete-service-form">
            <input type="hidden" id="delete_service_id" name="service_id" value="">
            
            <div class="flex justify-center space-x-4">
                <button type="button" id="cancel-delete" class="bg-gray-100 text-gray-800 px-4 py-2 rounded-md">Cancel</button>
                <button type="submit" name="delete_service" class="bg-red-600 text-white px-4 py-2 rounded-md">Delete</button>
            </div>
        </form>
    </div>
</div>