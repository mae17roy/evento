<?php
// Include database configuration
require_once 'config.php';

// Require login to access this page
requireLogin();

// Initialize variables
$errors = [];
$success = false;

// Get current user data
$user = getCurrentUser($db);

if (!$user) {
    $_SESSION['error_message'] = "User not found. Please log in again.";
    header("Location: logout.php");
    exit();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Password change form
    if (isset($_POST['current_password'])) {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validate
        if (empty($currentPassword)) {
            $errors[] = "Current password is required";
        } else {
            // Verify current password
            if (!(password_verify($currentPassword, $user['password']) || $currentPassword === $user['password'])) {
                $errors[] = "Current password is incorrect";
            }
        }
        
        if (empty($newPassword)) {
            $errors[] = "New password is required";
        } elseif (strlen($newPassword) < 6) {
            $errors[] = "New password must be at least 6 characters";
        }
        
        if ($newPassword !== $confirmPassword) {
            $errors[] = "New passwords do not match";
        }
        
        // If no errors, update password
        if (empty($errors)) {
            try {
                $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $stmt = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                $success = true;
                $successMessage = "Password updated successfully";
            } catch (PDOException $e) {
                $errors[] = "Password update failed: " . $e->getMessage();
            }
        }
    }
    // Profile update form
    else {
        $name = trim($_POST['name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $address = trim($_POST['address'] ?? '');
        
        // Validate
        if (empty($name)) {
            $errors[] = "Name is required";
        }
        
        // If no errors, update profile
        if (empty($errors)) {
            try {
                $stmt = $db->prepare("UPDATE users SET name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$name, $phone, $address, $user['id']]);
                
                // Update session data
                $_SESSION['user_name'] = $name;
                
                // Update user data for display
                $user['name'] = $name;
                $user['phone'] = $phone;
                $user['address'] = $address;
                
                $success = true;
                $successMessage = "Profile updated successfully";
            } catch (PDOException $e) {
                $errors[] = "Profile update failed: " . $e->getMessage();
            }
        }
    }
}

// Get booking history
$bookingStmt = $db->prepare("
    SELECT b.*, 
           s.name as service_name, 
           s.image as service_image,
           u.name as owner_name,
           u.business_name
    FROM bookings b
    JOIN booking_items bi ON b.id = bi.booking_id
    JOIN services s ON bi.service_id = s.id
    JOIN users u ON s.owner_id = u.id
    WHERE b.user_id = ?
    ORDER BY b.booking_date DESC
    LIMIT 5
");
$bookingStmt->execute([$user['id']]);
$recentBookings = $bookingStmt->fetchAll();

// Count total bookings
$countStmt = $db->prepare("SELECT COUNT(*) FROM bookings WHERE user_id = ?");
$countStmt->execute([$user['id']]);
$totalBookings = $countStmt->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - My Profile</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <!-- Navigation Bar -->
    <nav class="bg-white shadow-md p-4">
        <div class="container mx-auto flex flex-col md:flex-row justify-between items-center">
            <!-- Logo -->
            <div class="flex items-center mb-4 md:mb-0">
                <a href="user_index.php" class="flex items-center">
                    <div class="text-purple-600 mr-2">
                        <svg class="h-8 w-8" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                            <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                            <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                            <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <span class="text-xl font-bold text-gray-800">EVENTO</span>
                </a>
            </div>
            
            <!-- Navigation Links -->
            <div class="flex space-x-4 mb-4 md:mb-0">
                <a href="user_index.php" class="text-gray-600 hover:text-purple-600">Home</a>
                <a href="my_bookings.php" class="text-gray-600 hover:text-purple-600">My Bookings</a>
                <a href="notifications.php" class="text-gray-600 hover:text-purple-600">Notifications</a>
            </div>
            
            <!-- User Menu -->
            <div class="flex items-center space-x-4">
                <div class="relative">
                    <button id="userMenuButton" class="flex items-center focus:outline-none">
                        <img src="https://i.pravatar.cc/150?img=<?php echo ($user['id'] % 70); ?>" alt="User" 
                             class="w-10 h-10 rounded-full object-cover border-2 border-purple-500">
                        <span class="ml-2 text-gray-700"><?php echo htmlspecialchars($user['name']); ?></span>
                        <i class="fas fa-chevron-down ml-1 text-gray-500"></i>
                    </button>
                    <div id="userDropdown" class="absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg hidden z-10">
                        <div class="py-1">
                            <div class="px-4 py-2 font-semibold border-b"><?php echo htmlspecialchars($user['name']); ?></div>
                            <a href="profile.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100 bg-gray-100">Profile</a>
                            <a href="my_bookings.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">My Bookings</a>
                            <a href="logout.php" class="block px-4 py-2 text-gray-700 hover:bg-gray-100">Log out</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mx-auto py-8 px-4">
        <div class="flex flex-col md:flex-row">
            <!-- Sidebar -->
            <div class="w-full md:w-1/4 mb-6 md:mb-0 md:pr-8">
                <div class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex flex-col items-center">
                        <img src="https://i.pravatar.cc/150?img=<?php echo ($user['id'] % 70); ?>" alt="User" 
                             class="w-24 h-24 rounded-full object-cover border-4 border-purple-500 mb-4">
                        <h2 class="text-2xl font-bold"><?php echo htmlspecialchars($user['name']); ?></h2>
                        <p class="text-gray-600"><?php echo htmlspecialchars($user['email']); ?></p>
                        <div class="w-full border-t border-gray-200 mt-6 pt-4">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Role:</span>
                                <span class="font-semibold capitalize"><?php echo $user['role']; ?></span>
                            </div>
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-gray-600">Member since:</span>
                                <span class="font-semibold"><?php echo date('M Y', strtotime($user['created_at'])); ?></span>
                            </div>
                            <div class="flex justify-between items-center">
                                <span class="text-gray-600">Total bookings:</span>
                                <span class="font-semibold"><?php echo $totalBookings; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="bg-white rounded-lg shadow-md p-6">
                    <h3 class="text-lg font-semibold mb-4">Account Menu</h3>
                    <ul class="space-y-2">
                        <li>
                            <a href="#profile" class="flex items-center p-2 rounded-md bg-purple-100 text-purple-700">
                                <i class="fas fa-user-circle w-5 text-center mr-2"></i>
                                <span>Profile Information</span>
                            </a>
                        </li>
                        <li>
                            <a href="#bookings" class="flex items-center p-2 rounded-md hover:bg-gray-100">
                                <i class="fas fa-calendar-alt w-5 text-center mr-2"></i>
                                <span>Recent Bookings</span>
                            </a>
                        </li>
                        <li>
                            <a href="#password" class="flex items-center p-2 rounded-md hover:bg-gray-100">
                                <i class="fas fa-lock w-5 text-center mr-2"></i>
                                <span>Change Password</span>
                            </a>
                        </li>
                        <li>
                            <a href="my_bookings.php" class="flex items-center p-2 rounded-md hover:bg-gray-100">
                                <i class="fas fa-history w-5 text-center mr-2"></i>
                                <span>Booking History</span>
                            </a>
                        </li>
                        <li>
                            <a href="logout.php" class="flex items-center p-2 rounded-md hover:bg-gray-100 text-red-600">
                                <i class="fas fa-sign-out-alt w-5 text-center mr-2"></i>
                                <span>Log Out</span>
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="w-full md:w-3/4">
                <?php if ($success): ?>
                    <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                        <?php echo $successMessage; ?>
                    </div>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                        <ul class="list-disc list-inside">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo $error; ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
                
                <!-- Profile Section -->
                <div id="profile" class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <h2 class="text-xl font-bold mb-6 border-b pb-2">Profile Information</h2>
                    <form action="profile.php" method="post" class="space-y-4">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="name" class="block text-gray-700 font-medium mb-1">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required
                                       class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                            <div>
                                <label for="email" class="block text-gray-700 font-medium mb-1">Email Address</label>
                                <input type="email" id="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled
                                       class="w-full px-4 py-2 border rounded bg-gray-100">
                                <p class="text-gray-500 text-sm mt-1">Email cannot be changed</p>
                            </div>
                            <div>
                                <label for="phone" class="block text-gray-700 font-medium mb-1">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>"
                                       class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                            </div>
                            <div>
                                <label for="role" class="block text-gray-700 font-medium mb-1">Account Type</label>
                                <input type="text" id="role" value="<?php echo ucfirst(htmlspecialchars($user['role'])); ?>" disabled
                                       class="w-full px-4 py-2 border rounded bg-gray-100">
                            </div>
                        </div>
                        
                        <div>
                            <label for="address" class="block text-gray-700 font-medium mb-1">Address</label>
                            <textarea id="address" name="address" rows="3"
                                      class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300"><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                        </div>
                        
                        <div class="pt-2">
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">
                                Update Profile
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Recent Bookings Section -->
                <div id="bookings" class="bg-white rounded-lg shadow-md p-6 mb-6">
                    <div class="flex justify-between items-center mb-6 border-b pb-2">
                        <h2 class="text-xl font-bold">Recent Bookings</h2>
                        <a href="my_bookings.php" class="text-purple-600 hover:text-purple-800">View All</a>
                    </div>
                    
                    <?php if (empty($recentBookings)): ?>
                        <div class="text-center py-8">
                            <div class="text-gray-400 mb-2"><i class="fas fa-calendar-times text-5xl"></i></div>
                            <h3 class="text-lg font-medium text-gray-700">No bookings yet</h3>
                            <p class="text-gray-500 mt-1">Your booking history will appear here.</p>
                            <a href="user_index.php" class="inline-block mt-4 px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700">
                                Browse Services
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($recentBookings as $booking): ?>
                                <div class="flex border rounded-lg overflow-hidden hover:shadow-md transition duration-300">
                                    <div class="w-1/4 bg-cover bg-center" 
                                         style="background-image: url('../images/<?php echo $booking['service_image'] ?? 'default-service.jpg'; ?>');">
                                    </div>
                                    <div class="w-3/4 p-4">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-bold"><?php echo htmlspecialchars($booking['service_name']); ?></h3>
                                                <p class="text-gray-600 text-sm">
                                                    by <?php echo htmlspecialchars($booking['business_name'] ?? $booking['owner_name']); ?>
                                                </p>
                                            </div>
                                            <span class="px-2 py-1 rounded text-xs font-bold uppercase
                                                <?php 
                                                    if ($booking['status'] === 'confirmed') echo 'bg-blue-100 text-blue-800';
                                                    elseif ($booking['status'] === 'completed') echo 'bg-green-100 text-green-800';
                                                    elseif ($booking['status'] === 'cancelled') echo 'bg-red-100 text-red-800';
                                                    else echo 'bg-gray-100 text-gray-800';
                                                ?>">
                                                <?php echo $booking['status']; ?>
                                            </span>
                                        </div>
                                        <div class="mt-2 text-sm">
                                            <div class="flex items-center mb-1">
                                                <i class="far fa-calendar-alt w-4 text-gray-500"></i>
                                                <span class="ml-2"><?php echo date('F j, Y', strtotime($booking['booking_date'])); ?></span>
                                            </div>
                                            <div class="flex items-center">
                                                <i class="far fa-clock w-4 text-gray-500"></i>
                                                <span class="ml-2"><?php echo date('g:i A', strtotime($booking['booking_time'])); ?></span>
                                            </div>
                                        </div>
                                        <div class="mt-3 flex justify-between items-center">
                                            <span class="font-bold">$<?php echo number_format($booking['total_amount'], 2); ?></span>
                                            <a href="view_booking.php?id=<?php echo $booking['id']; ?>" class="text-purple-600 hover:text-purple-800 text-sm">
                                                View Details
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Change Password Section -->
                <div id="password" class="bg-white rounded-lg shadow-md p-6">
                    <h2 class="text-xl font-bold mb-6 border-b pb-2">Change Password</h2>
                    <form action="profile.php#password" method="post" class="space-y-4">
                        <div>
                            <label for="current_password" class="block text-gray-700 font-medium mb-1">Current Password</label>
                            <input type="password" id="current_password" name="current_password" required
                                   class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        
                        <div>
                            <label for="new_password" class="block text-gray-700 font-medium mb-1">New Password</label>
                            <input type="password" id="new_password" name="new_password" required
                                   class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                            <p class="text-gray-500 text-sm mt-1">Minimum 6 characters</p>
                        </div>
                        
                        <div>
                            <label for="confirm_password" class="block text-gray-700 font-medium mb-1">Confirm New Password</label>
                            <input type="password" id="confirm_password" name="confirm_password" required
                                   class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                        </div>
                        
                        <div class="pt-2">
                            <button type="submit" class="px-4 py-2 bg-purple-600 text-white rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">
                                Update Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Footer -->
    <footer class="bg-gray-800 text-white mt-16 py-8">
        <div class="container mx-auto px-4">
            <div class="border-t border-gray-700 mt-8 pt-6 text-center">
                <p class="text-gray-400">&copy; <?php echo date('Y'); ?> EVENTO. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Scripts -->
    <script>
        // Toggle user dropdown
        document.getElementById('userMenuButton')?.addEventListener('click', function() {
            document.getElementById('userDropdown').classList.toggle('hidden');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', function(event) {
            const dropdown = document.getElementById('userDropdown');
            const button = document.getElementById('userMenuButton');
            if (dropdown && button && !event.target.closest('#userMenuButton') && !event.target.closest('#userDropdown')) {
                dropdown.classList.add('hidden');
            }
        });
    </script>
</body>
</html>