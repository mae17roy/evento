<?php
// Include database configuration
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_message'] = "You must be logged in to view your bookings.";
    header("Location: login.php");
    exit();
}

$userId = $_SESSION['user_id'];

// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : '';
$dateFilter = isset($_GET['date']) ? $_GET['date'] : '';
$searchFilter = isset($_GET['search']) ? $_GET['search'] : '';
$sortFilter = isset($_GET['sort']) ? $_GET['sort'] : 'newest';

// Get current page for pagination
$currentPage = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = 5;
$offset = ($currentPage - 1) * $perPage;

try {
    // Build the query with filters
    $query = "
        SELECT b.*, 
               s.name as service_name, s.image,
               u.name as owner_name, u.business_name
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON s.owner_id = u.id
        WHERE b.user_id = ?
    ";
    
    $params = [$userId];
    
    // Add status filter if set
    if (!empty($statusFilter)) {
        $query .= " AND b.status = ?";
        $params[] = $statusFilter;
    }
    
    // Add date filter if set
    if (!empty($dateFilter)) {
        $query .= " AND b.booking_date = ?";
        $params[] = $dateFilter;
    }
    
    // Add search filter if set
    if (!empty($searchFilter)) {
        $query .= " AND (s.name LIKE ? OR u.name LIKE ? OR u.business_name LIKE ?)";
        $searchTerm = "%$searchFilter%";
        $params[] = $searchTerm;
        $params[] = $searchTerm;
        $params[] = $searchTerm;
    }
    
    // Get total count for pagination
    $countStmt = $db->prepare("SELECT COUNT(*) FROM ($query) as booking_count");
    $countStmt->execute($params);
    $totalBookings = $countStmt->fetchColumn();
    $totalPages = ceil($totalBookings / $perPage);
    
    // Ensure current page is valid
    if ($currentPage < 1) $currentPage = 1;
    if ($currentPage > $totalPages && $totalPages > 0) $currentPage = $totalPages;
    
    // Add sorting
    switch ($sortFilter) {
        case 'date_asc':
            $query .= " ORDER BY b.booking_date ASC, b.booking_time ASC";
            break;
        case 'date_desc':
            $query .= " ORDER BY b.booking_date DESC, b.booking_time DESC";
            break;
        case 'price_low':
            $query .= " ORDER BY b.total_amount ASC";
            break;
        case 'price_high':
            $query .= " ORDER BY b.total_amount DESC";
            break;
        case 'oldest':
            $query .= " ORDER BY b.created_at ASC";
            break;
        case 'newest':
        default:
            $query .= " ORDER BY b.created_at DESC";
            break;
    }
    
    // Add limit for pagination
    $query .= " LIMIT ? OFFSET ?";
    $params[] = $perPage;
    $params[] = $offset;
    
    // Execute the query
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
    
    // Get upcoming bookings (confirmed bookings with future dates)
    $upcomingStmt = $db->prepare("
        SELECT b.*, 
               s.name as service_name, s.image,
               u.name as owner_name, u.business_name
        FROM bookings b
        JOIN booking_items bi ON b.id = bi.booking_id
        JOIN services s ON bi.service_id = s.id
        JOIN users u ON s.owner_id = u.id
        WHERE b.user_id = ? 
        AND b.status = 'confirmed' 
        AND b.booking_date >= CURDATE()
        ORDER BY b.booking_date ASC, b.booking_time ASC
        LIMIT 3
    ");
    $upcomingStmt->execute([$userId]);
    $upcomingBookings = $upcomingStmt->fetchAll();
    
    // Get booking metrics
    $metricsStmt = $db->prepare("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
            SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) as confirmed,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END) as cancelled,
            SUM(total_amount) as total_spent
        FROM bookings
        WHERE user_id = ?
    ");
    $metricsStmt->execute([$userId]);
    $metrics = $metricsStmt->fetch();
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Database error: " . $e->getMessage();
    header("Location: user_index.php");
    exit();
}

// Helper function to format date
function formatDate($date) {
    return date('F j, Y', strtotime($date));
}

// Helper function to format time
function formatTime($time) {
    return date('g:i A', strtotime($time));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Bookings - EVENTO</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .status-badge {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        .status-pending {
            background-color: #f3f4f6;
            color: #4b5563;
        }
        .status-confirmed {
            background-color: #1a2e46;
            color: white;
        }
        .status-completed {
            background-color: #10b981;
            color: white;
        }
        .status-cancelled {
            background-color: #ef4444;
            color: white;
        }
        .filter-tag {
            transition: all 0.3s ease;
        }
        .filter-tag:hover .close-icon {
            opacity: 1;
        }
        .close-icon {
            opacity: 0;
            transition: all 0.3s ease;
        }
    </style>
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md p-4 fixed top-0 left-0 right-0 z-50">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center mb-4 md:mb-0">
                <div class="text-purple-600 mr-2">
                    <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <a href="user_index.php" class="text-xl font-bold text-gray-800">EVENTO</a>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-6">
                <a href="my_bookings.php" class="text-purple-600 hover:text-gray-800">
                    <i class="fas fa-calendar-alt text-xl"></i>
                </a>
                <a href="notifications.php" class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-bell text-xl"></i>
                </a>
                <div class="relative">
                    <button id="userMenuButton" class="flex items-center focus:outline-none">
                        <img src="https://i.pravatar.cc/150?img=<?php echo ($_SESSION['user_id'] % 70); ?>" alt="User" 
                             class="w-10 h-10 rounded-full object-cover border-2 border-purple-500">
                    </button>
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden z-10">
                        <div class="py-1">
                            <div class="px-4 py-2 font-semibold border-b"><?php echo htmlspecialchars($_SESSION['user_name']); ?></div>
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Profile</a>
                            <a href="my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Bookings</a>
                            <a href="settings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Settings</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Log out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content with top padding -->
    <main class="container mx-auto py-8 px-4 mt-16 mb-10">
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border-l-4 border-green-500 text-green-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['success_message']; ?></p>
                <?php unset($_SESSION['success_message']); ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $_SESSION['error_message']; ?></p>
                <?php unset($_SESSION['error_message']); ?>
            </div>
        <?php endif; ?>
        
        <h1 class="text-3xl font-bold mb-6">My Bookings</h1>
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="bg-purple-100 p-3 rounded-full mr-4">
                        <i class="fas fa-calendar-check text-purple-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Bookings</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($metrics['total']); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="bg-gray-100 p-3 rounded-full mr-4">
                        <i class="fas fa-clock text-gray-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Pending</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($metrics['pending']); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="bg-blue-100 p-3 rounded-full mr-4">
                        <i class="fas fa-check text-blue-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Confirmed</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($metrics['confirmed']); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="bg-green-100 p-3 rounded-full mr-4">
                        <i class="fas fa-flag-checkered text-green-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Completed</p>
                        <h3 class="text-2xl font-bold"><?php echo number_format($metrics['completed']); ?></h3>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-4">
                <div class="flex items-center">
                    <div class="bg-indigo-100 p-3 rounded-full mr-4">
                        <i class="fas fa-dollar-sign text-indigo-600"></i>
                    </div>
                    <div>
                        <p class="text-sm text-gray-500">Total Spent</p>
                        <h3 class="text-2xl font-bold">$<?php echo number_format($metrics['total_spent'], 2); ?></h3>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Upcoming Bookings -->
        <?php if (!empty($upcomingBookings)): ?>
        <div class="mb-8">
            <h2 class="text-2xl font-bold mb-4">Upcoming Bookings</h2>
            
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <?php foreach ($upcomingBookings as $booking): ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="relative h-40">
                        <img src="../images/<?php echo $booking['image'] ?? 'default-service.jpg'; ?>" alt="<?php echo htmlspecialchars($booking['service_name']); ?>" 
                             class="w-full h-full object-cover">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/70 to-transparent"></div>
                        <div class="absolute bottom-0 left-0 p-4 text-white">
                            <h3 class="font-bold"><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                            <p class="text-sm"><?php echo htmlspecialchars($booking['business_name'] ?? $booking['owner_name']); ?></p>
                        </div>
                    </div>
                    
                    <div class="p-4">
                        <div class="flex justify-between items-center mb-2">
                            <div class="flex items-center">
                                <i class="far fa-calendar-alt mr-2 text-gray-600"></i>
                                <span><?php echo formatDate($booking['booking_date']); ?></span>
                            </div>
                            <div class="flex items-center">
                                <i class="far fa-clock mr-2 text-gray-600"></i>
                                <span><?php echo formatTime($booking['booking_time']); ?></span>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <span class="status-badge status-<?php echo strtolower($booking['status']); ?>">
                                <?php echo ucfirst($booking['status']); ?>
                            </span>
                        </div>
                        
                        <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="block w-full text-center py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                            View Details
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- All Bookings -->
        <div>
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-4">
                <h2 class="text-2xl font-bold">All Bookings</h2>
                
                <div class="flex space-x-2 mt-2 md:mt-0">
                    <a href="user_index.php" class="flex items-center px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                        <i class="fas fa-plus mr-2"></i>
                        <span>Book New Service</span>
                    </a>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-lg font-medium">Filters</h3>
                    <?php if (!empty($statusFilter) || !empty($dateFilter) || !empty($searchFilter)): ?>
                    <a href="my_bookings.php" class="text-sm text-purple-600 hover:text-purple-800">
                        Clear All Filters
                    </a>
                    <?php endif; ?>
                </div>
                
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <!-- Search -->
                    <div>
                        <form method="get" action="my_bookings.php">
                            <div class="relative">
                                <input type="text" name="search" placeholder="Search bookings..." value="<?php echo htmlspecialchars($searchFilter); ?>"
                                       class="w-full pl-10 pr-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                                <button type="submit" class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            
                            <?php if (!empty($statusFilter)): ?>
                            <input type="hidden" name="status" value="<?php echo htmlspecialchars($statusFilter); ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($dateFilter)): ?>
                            <input type="hidden" name="date" value="<?php echo htmlspecialchars($dateFilter); ?>">
                            <?php endif; ?>
                            
                            <?php if (!empty($sortFilter)): ?>
                            <input type="hidden" name="sort" value="<?php echo htmlspecialchars($sortFilter); ?>">
                            <?php endif; ?>
                        </form>
                    </div>
                    
                    <!-- Status Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Status</label>
                        <select id="status-filter" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                            <option value="">All Statuses</option>
                            <option value="pending" <?php echo ($statusFilter === 'pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="confirmed" <?php echo ($statusFilter === 'confirmed') ? 'selected' : ''; ?>>Confirmed</option>
                            <option value="completed" <?php echo ($statusFilter === 'completed') ? 'selected' : ''; ?>>Completed</option>
                            <option value="cancelled" <?php echo ($statusFilter === 'cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                    
                    <!-- Date Filter -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Booking Date</label>
                        <input type="date" id="date-filter" value="<?php echo htmlspecialchars($dateFilter); ?>" 
                               class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                    </div>
                    
                    <!-- Sort Options -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Sort By</label>
                        <select id="sort-filter" class="w-full p-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                            <option value="newest" <?php echo ($sortFilter === 'newest') ? 'selected' : ''; ?>>Newest First</option>
                            <option value="oldest" <?php echo ($sortFilter === 'oldest') ? 'selected' : ''; ?>>Oldest First</option>
                            <option value="date_asc" <?php echo ($sortFilter === 'date_asc') ? 'selected' : ''; ?>>Booking Date (Ascending)</option>
                            <option value="date_desc" <?php echo ($sortFilter === 'date_desc') ? 'selected' : ''; ?>>Booking Date (Descending)</option>
                            <option value="price_low" <?php echo ($sortFilter === 'price_low') ? 'selected' : ''; ?>>Price (Low to High)</option>
                            <option value="price_high" <?php echo ($sortFilter === 'price_high') ? 'selected' : ''; ?>>Price (High to Low)</option>
                        </select>
                    </div>
                </div>
                
                <!-- Active Filters -->
                <?php if (!empty($statusFilter) || !empty($dateFilter) || !empty($searchFilter)): ?>
                <div class="mt-4 flex flex-wrap gap-2">
                    <?php if (!empty($statusFilter)): ?>
                    <div class="filter-tag bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm flex items-center">
                        <span>Status: <?php echo ucfirst($statusFilter); ?></span>
                        <a href="<?php 
                            $params = $_GET;
                            unset($params['status']);
                            echo '?' . http_build_query($params);
                        ?>" class="ml-2 close-icon">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($dateFilter)): ?>
                    <div class="filter-tag bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm flex items-center">
                        <span>Date: <?php echo formatDate($dateFilter); ?></span>
                        <a href="<?php 
                            $params = $_GET;
                            unset($params['date']);
                            echo '?' . http_build_query($params);
                        ?>" class="ml-2 close-icon">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    
                    <?php if (!empty($searchFilter)): ?>
                    <div class="filter-tag bg-purple-100 text-purple-800 px-3 py-1 rounded-full text-sm flex items-center">
                        <span>Search: "<?php echo htmlspecialchars($searchFilter); ?>"</span>
                        <a href="<?php 
                            $params = $_GET;
                            unset($params['search']);
                            echo '?' . http_build_query($params);
                        ?>" class="ml-2 close-icon">
                            <i class="fas fa-times"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Bookings List -->
            <?php if (empty($bookings)): ?>
            <div class="bg-white rounded-lg shadow-sm p-8 text-center">
                <img src="https://via.placeholder.com/150" alt="No bookings" class="w-24 h-24 mx-auto mb-4 rounded-full">
                <h3 class="text-xl font-medium mb-2">No bookings found</h3>
                <?php if (!empty($statusFilter) || !empty($dateFilter) || !empty($searchFilter)): ?>
                <p class="text-gray-500 mb-4">Try changing your filters</p>
                <a href="my_bookings.php" class="text-purple-600 hover:text-purple-800">Clear all filters</a>
                <?php else: ?>
                <p class="text-gray-500 mb-4">You haven't made any bookings yet.</p>
                <a href="user_index.php" class="inline-block px-6 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                    Browse Services
                </a>
                <?php endif; ?>
            </div>
            <?php else: ?>
            <div class="space-y-4">
                <?php foreach ($bookings as $booking): ?>
                <div class="bg-white rounded-lg shadow-sm overflow-hidden">
                    <div class="grid grid-cols-1 md:grid-cols-4">
                        <!-- Service Image -->
                        <div class="md:col-span-1">
                            <img src="../images/<?php echo $booking['image'] ?? 'default-service.jpg'; ?>" alt="<?php echo htmlspecialchars($booking['service_name']); ?>" 
                                 class="w-full h-full object-cover">
                        </div>
                        
                        <!-- Booking Details -->
                        <div class="md:col-span-3 p-6">
                            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-2">
                                <h3 class="text-xl font-bold"><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                                <span class="status-badge status-<?php echo strtolower($booking['status']); ?> mt-2 md:mt-0">
                                    <?php echo ucfirst($booking['status']); ?>
                                </span>
                            </div>
                            
                            <p class="text-gray-600 mb-3">Provider: <?php echo htmlspecialchars($booking['business_name'] ?? $booking['owner_name']); ?></p>
                            
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-4">
                                <div>
                                    <span class="text-gray-500">Booking Date</span>
                                    <p class="font-medium"><?php echo formatDate($booking['booking_date']); ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Booking Time</span>
                                    <p class="font-medium"><?php echo formatTime($booking['booking_time']); ?></p>
                                </div>
                                <div>
                                    <span class="text-gray-500">Total Amount</span>
                                    <p class="font-medium">$<?php echo number_format($booking['total_amount'], 2); ?></p>
                                </div>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex flex-wrap gap-2">
                                <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="px-4 py-2 bg-purple-600 text-white rounded hover:bg-purple-700">
                                    View Details
                                </a>
                                
                                <?php if ($booking['status'] === 'pending'): ?>
                                <a href="#" onclick="openCancelModal(<?php echo $booking['id']; ?>)" class="px-4 py-2 border border-red-600 text-red-600 rounded hover:bg-red-50">
                                    Cancel Booking
                                </a>
                                <?php elseif ($booking['status'] === 'confirmed'): ?>
                                <a href="contact_provider.php?id=<?php echo $booking['owner_id']; ?>&booking_id=<?php echo $booking['id']; ?>" class="px-4 py-2 border border-gray-300 text-gray-700 rounded hover:bg-gray-50">
                                    Contact Provider
                                </a>
                                <?php elseif ($booking['status'] === 'completed'): ?>
                                <a href="submit_review.php?booking_id=<?php echo $booking['id']; ?>" class="px-4 py-2 border border-blue-600 text-blue-600 rounded hover:bg-blue-50">
                                    Leave Review
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            
            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <div class="mt-6 flex justify-center">
                <div class="flex space-x-1">
                    <?php if ($currentPage > 1): ?>
                    <a href="?page=<?php echo $currentPage - 1; ?><?php echo (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''); ?><?php echo (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''); ?><?php echo (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''); ?><?php echo (!empty($sortFilter) ? '&sort=' . urlencode($sortFilter) : ''); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $currentPage - 2); $i <= min($totalPages, $currentPage + 2); $i++): ?>
                    <a href="?page=<?php echo $i; ?><?php echo (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''); ?><?php echo (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''); ?><?php echo (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''); ?><?php echo (!empty($sortFilter) ? '&sort=' . urlencode($sortFilter) : ''); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-md <?php echo ($i == $currentPage) ? 'bg-purple-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50'; ?>">
                        <?php echo $i; ?>
                    </a>
                    <?php endfor; ?>
                    
                    <?php if ($currentPage < $totalPages): ?>
                    <a href="?page=<?php echo $currentPage + 1; ?><?php echo (!empty($statusFilter) ? '&status=' . urlencode($statusFilter) : ''); ?><?php echo (!empty($dateFilter) ? '&date=' . urlencode($dateFilter) : ''); ?><?php echo (!empty($searchFilter) ? '&search=' . urlencode($searchFilter) : ''); ?><?php echo (!empty($sortFilter) ? '&sort=' . urlencode($sortFilter) : ''); ?>" 
                       class="px-3 py-1 border border-gray-300 rounded-md bg-white text-gray-600 hover:bg-gray-50">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Cancel Booking Modal -->
    <div id="cancel-modal" class="fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50 hidden">
        <div class="bg-white rounded-lg max-w-md w-full p-6">
            <h2 class="text-xl font-bold mb-4">Cancel Booking</h2>
            <p class="mb-4">Are you sure you want to cancel this booking? This action cannot be undone.</p>
            
            <form action="cancel_booking.php" method="post">
                <input type="hidden" name="booking_id" id="cancel-booking-id">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Reason for cancellation (optional)</label>
                    <textarea name="cancel_reason" rows="3" class="w-full p-2 border border-gray-300 rounded-md"></textarea>
                </div>
                
                <div class="flex justify-end space-x-2">
                    <button type="button" id="cancel-modal-close" class="px-4 py-2 bg-gray-200 text-gray-800 rounded hover:bg-gray-300">
                        No, Keep Booking
                    </button>
                    <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Yes, Cancel Booking
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-800 text-white py-8">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-4 md:mb-0">
                    <div class="flex items-center">
                        <div class="text-purple-400 mr-2">
                            <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                                <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                                <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            </svg>
                        </div>
                        <span class="text-xl font-bold">EVENTO</span>
                    </div>
                    <p class="text-gray-400 mt-2">Your one-stop platform for all event services.</p>
                </div>
                
                <div>
                    <div class="flex space-x-4">
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-instagram"></i></a>
                        <a href="#" class="text-gray-400 hover:text-white"><i class="fab fa-linkedin-in"></i></a>
                    </div>
                    <p class="text-gray-400 mt-2">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
                </div>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle user dropdown
        document.getElementById('userMenuButton').addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            if (!event.target.closest('#userMenuButton') && !event.target.closest('#userDropdown')) {
                dropdown.classList.add('hidden');
            }
        });
        
        // Filter handlers
        document.getElementById('status-filter').addEventListener('change', function() {
            applyFilters();
        });
        
        document.getElementById('date-filter').addEventListener('change', function() {
            applyFilters();
        });
        
        document.getElementById('sort-filter').addEventListener('change', function() {
            applyFilters();
        });
        
        function applyFilters() {
            const status = document.getElementById('status-filter').value;
            const date = document.getElementById('date-filter').value;
            const sort = document.getElementById('sort-filter').value;
            const search = '<?php echo htmlspecialchars($searchFilter); ?>';
            
            let url = 'my_bookings.php?';
            const params = [];
            
            if (status) {
                params.push('status=' + encodeURIComponent(status));
            }
            
            if (date) {
                params.push('date=' + encodeURIComponent(date));
            }
            
            if (search) {
                params.push('search=' + encodeURIComponent(search));
            }
            
            if (sort) {
                params.push('sort=' + encodeURIComponent(sort));
            }
            
            url += params.join('&');
            window.location.href = url;
        }
        
        // Cancel booking modal
        function openCancelModal(bookingId) {
            document.getElementById('cancel-booking-id').value = bookingId;
            document.getElementById('cancel-modal').classList.remove('hidden');
        }
        
        document.getElementById('cancel-modal-close').addEventListener('click', function() {
            document.getElementById('cancel-modal').classList.add('hidden');
        });
        
        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            const modal = document.getElementById('cancel-modal');
            if (event.target === modal) {
                modal.classList.add('hidden');
            }
        });
    </script>
</body>
</html>