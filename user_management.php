<?php
session_start();

// Include database configuration
require_once 'config.php';

// Implement a robust authentication check
function is_admin() {
    return isset($_SESSION['user_id']) && 
           isset($_SESSION['user_role']) && 
           $_SESSION['user_role'] === 'admin';
}

// Redirect unauthorized users
if (!is_admin()) {
    $_SESSION['error_message'] = "You must be an admin to access this page.";
    header('Location: index.php');
    exit();
}

// Initialize variables
$action_message = '';
$error_message = '';

// Sanitize input function
function sanitize_input($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input);
    return $input;
}

// Create User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'create_user') {
    try {
        // Validate and sanitize inputs
        $name = sanitize_input($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        $role = in_array($_POST['role'], ['admin', 'client', 'owner']) ? $_POST['role'] : 'client';
        $phone = sanitize_input($_POST['phone'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $business_name = $role === 'owner' ? sanitize_input($_POST['business_name']) : NULL;

        // Check if email already exists
        $check_email = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $check_email->execute([$email]);
        if ($check_email->fetchColumn() > 0) {
            throw new Exception("Email already exists. Please use a different email.");
        }

        // Generate a secure temporary password
        $temp_password = bin2hex(random_bytes(8)); // 16-character random password
        $hashed_password = password_hash($temp_password, PASSWORD_DEFAULT);

        // Prepare and execute the insert query
        $stmt = $db->prepare("INSERT INTO users (name, email, password, phone, address, business_name, role) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $email, $hashed_password, $phone, $address, $business_name, $role]);

        $action_message = "User created successfully. Temporary password: $temp_password";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Update User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_user') {
    try {
        // Validate inputs
        $user_id = filter_var($_POST['user_id'], FILTER_VALIDATE_INT);
        if (!$user_id) {
            throw new Exception("Invalid user ID.");
        }

        $name = sanitize_input($_POST['name']);
        $email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
        
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new Exception("Invalid email format.");
        }

        $role = in_array($_POST['role'], ['admin', 'client', 'owner']) ? $_POST['role'] : 'client';
        $phone = sanitize_input($_POST['phone'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $business_name = $role === 'owner' ? sanitize_input($_POST['business_name']) : NULL;

        // Prepare update query
        $stmt = $db->prepare("UPDATE users SET name = ?, email = ?, phone = ?, address = ?, business_name = ?, role = ? WHERE id = ?");
        $stmt->execute([$name, $email, $phone, $address, $business_name, $role, $user_id]);

        $action_message = "User updated successfully.";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Delete User
if (isset($_GET['action']) && $_GET['action'] === 'delete_user' && isset($_GET['id'])) {
    try {
        $user_id = filter_var($_GET['id'], FILTER_VALIDATE_INT);
        if (!$user_id) {
            throw new Exception("Invalid user ID.");
        }

        // Check if user is not the last admin
        $admin_count_stmt = $db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
        $admin_count = $admin_count_stmt->fetchColumn();

        // Prevent deletion of the last admin
        if ($admin_count <= 1) {
            throw new Exception("Cannot delete the last admin account.");
        }

        // Prevent self-deletion
        if ($user_id == $_SESSION['user_id']) {
            throw new Exception("You cannot delete your own account.");
        }

        // Delete user
        $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
        $stmt->execute([$user_id]);

        $action_message = "User deleted successfully.";
    } catch (Exception $e) {
        $error_message = $e->getMessage();
    }
}

// Pagination and Search Parameters
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, 
    ['options' => ['default' => 1, 'min_range' => 1]]);
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Search and Filter
$search_query = filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '';
$role_filter = filter_input(INPUT_GET, 'role', FILTER_SANITIZE_STRING) ?? '';

// Prepare base query with dynamic conditions
$where_conditions = [];
$params = [];

// Build WHERE clause
if (!empty($search_query)) {
    $where_conditions[] = "(name LIKE :search OR email LIKE :search OR COALESCE(business_name, '') LIKE :search)";
    $params[':search'] = "%$search_query%";
}

if (!empty($role_filter)) {
    $where_conditions[] = "role = :role";
    $params[':role'] = $role_filter;
}

// Construct full WHERE clause
$where_sql = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Count total users
$count_query = "SELECT COUNT(*) FROM users $where_sql";
$count_stmt = $db->prepare($count_query);
$count_stmt->execute($params);
$total_users = $count_stmt->fetchColumn();

// Calculate total pages
$total_pages = max(1, ceil($total_users / $per_page));

// Ensure page is within valid range
$page = min($page, $total_pages);

// Fetch users query
$query = "SELECT * FROM users $where_sql ORDER BY created_at DESC LIMIT :limit OFFSET :offset";

// Prepare statement
$stmt = $db->prepare($query);

// Bind search and role parameters
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
}

// Bind pagination parameters
$stmt->bindValue(':limit', $per_page, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

// Execute query
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Pagination navigation
$prev_page = max(1, $page - 1);
$next_page = min($total_pages, $page + 1);
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - EVENTO Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/alpinejs@3.10.5/dist/cdn.min.js" defer></script>
</head>
<body class="bg-gray-100 dark:bg-gray-900 text-gray-900 dark:text-gray-100" 
    x-data="{ 
        showCreateModal: false, 
        showEditModal: false, 
        editingUser: null,
        confirmDelete: false,
        deleteUserId: null
    }">
    <!-- Notification Messages -->
    <?php if (!empty($action_message)): ?>
    <div class="fixed top-4 right-4 z-50 bg-green-500 text-white px-4 py-2 rounded shadow-lg">
        <?php echo htmlspecialchars($action_message); ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
    <div class="fixed top-4 right-4 z-50 bg-red-500 text-white px-4 py-2 rounded shadow-lg">
        <?php echo htmlspecialchars($error_message); ?>
    </div>
    <?php endif; ?>

    <div class="container mx-auto px-4 py-8">
        <h1 class="text-3xl font-bold mb-6 dark:text-gray-200">User Management</h1>

        <!-- Search and Filter -->
        <div class="mb-6 flex justify-between items-center">
            <form method="GET" class="flex space-x-2">
                <input 
                    type="text" 
                    name="search" 
                    placeholder="Search users..." 
                    value="<?php echo htmlspecialchars($search_query); ?>" 
                    class="px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                >
                <select 
                    name="role" 
                    class="px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                >
                    <option value="">All Roles</option>
                    <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    <option value="client" <?php echo $role_filter === 'client' ? 'selected' : ''; ?>>Client</option>
                    <option value="owner" <?php echo $role_filter === 'owner' ? 'selected' : ''; ?>>Service Owner</option>
                </select>
                <button type="submit" class="bg-blue-500 text-white px-4 py-2 rounded-md hover:bg-blue-600">
                    Search
                </button>
            </form>

            <!-- Create User Button -->
            <button 
                @click="showCreateModal = true" 
                class="bg-green-500 text-white px-4 py-2 rounded-md hover:bg-green-600"
            >
                + Create User
            </button>
        </div>

        <!-- Users Table -->
        <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
            <table class="min-w-full">
                <thead class="bg-gray-50 dark:bg-gray-700">
                    <tr>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Role</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Business Name</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-200 dark:divide-gray-700">
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                            <?php echo htmlspecialchars($user['name']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($user['email']); ?>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                            <span class="px-2 py-1 rounded-full text-xs <?php 
                                echo $user['role'] === 'admin' ? 'bg-red-100 text-red-800' : 
                                     ($user['role'] === 'owner' ? 'bg-green-100 text-green-800' : 'bg-blue-100 text-blue-800');
                            ?>">
                                <?php echo ucfirst(htmlspecialchars($user['role'])); ?>
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                            <?php echo htmlspecialchars($user['business_name'] ?? 'N/A'); ?>
                        </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <div class="flex space-x-2">
                <button 
                    @click="editingUser = <?php echo htmlspecialchars(json_encode($user), ENT_QUOTES); ?>; showEditModal = true;"
                    class="text-blue-500 hover:text-blue-700"
                >
                    Edit
                </button>
                <a
                    href="?action=delete_user&id=<?php echo $user['id']; ?>"
                    @click.prevent="deleteUserId = <?php echo $user['id']; ?>; confirmDelete = true;"
                    class="text-red-500 hover:text-red-700"
                >
                    Delete
                </a>
            </div>
        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="5" class="px-6 py-4 text-center text-gray-500 dark:text-gray-400">
                            No users found.
                        </td>
                    </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <div class="mt-4 flex justify-between items-center">
            <div class="text-sm text-gray-500 dark:text-gray-400">
                Showing <?php 
                    $start = ($page - 1) * $per_page + 1;
                    $end = min($start + $per_page - 1, $total_users);
                    echo "$start - $end of $total_users users"; 
                ?> 
            </div>
            <div class="flex space-x-2">
                <?php if ($page > 1): ?>
                <a href="?page=<?php echo $prev_page; ?>&search=<?php echo urlencode($search_query); ?>&role=<?php echo urlencode($role_filter); ?>" 
                   class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md">Previous</a>
                <?php endif; ?>
                <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $next_page; ?>&search=<?php echo urlencode($search_query); ?>&role=<?php echo urlencode($role_filter); ?>" 
                   class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md">Next</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Confirmation Modal for Delete -->
    <div 
        x-show="confirmDelete" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
    >
        <div 
            class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md mx-4"
        >
            <h2 class="text-2xl font-bold mb-4 dark:text-gray-200">Confirm Delete</h2>
            <p class="mb-4 text-gray-600 dark:text-gray-300">
                Are you sure you want to delete this user? This action cannot be undone.
            </p>
            <div class="flex justify-end space-x-2">
                <button 
                    @click="confirmDelete = false" 
                    class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md"
                >
                    Cancel
                </button>
                <a 
                    x-bind:href="'?action=delete_user&id=' + deleteUserId"
                    class="px-4 py-2 bg-red-500 text-white rounded-md hover:bg-red-600"
                >
                    Delete
                </a>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div 
        x-show="showCreateModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
    >
        <div 
            @click.outside="showCreateModal = false"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-2xl mx-4"
        >
            <h2 class="text-2xl font-bold mb-4 dark:text-gray-200">Create New User</h2>
            <form method="POST" x-data="{ 
                userType: 'client',
                generatePassword: false,
                password: '',
                generateRandomPassword() {
                    const length = 12;
                    const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
                    let password = '';
                    for (let i = 0; i < length; i++) {
                        const randomIndex = Math.floor(Math.random() * charset.length);
                        password += charset[randomIndex];
                    }
                    this.password = password;
                }
            }">
                <input type="hidden" name="action" value="create_user">
                
                <div class="grid grid-cols-2 gap-4">
                    <div class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Name *</label>
                        <input 
                            type="text" 
                            name="name" 
                            required 
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                            placeholder="Full Name"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Email *</label>
                        <input 
                            type="email" 
                            name="email" 
                            required 
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                            placeholder="user@example.com"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Role *</label>
                        <select 
                            name="role" 
                            x-model="userType"
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                        >
                            <option value="client">Client</option>
                            <option value="owner">Service Owner</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Phone</label>
                        <input 
                            type="tel" 
                            name="phone" 
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                            placeholder="Optional"
                        >
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Address</label>
                        <input 
                            type="text" 
                            name="address" 
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                            placeholder="Optional"
                        >
                    </div>

                    <div x-show="userType === 'owner'" class="mb-4">
                        <label class="block text-sm font-medium dark:text-gray-300 mb-2">Business Name *</label>
                        <input 
                            type="text" 
                            name="business_name" 
                            x-bind:required="userType === 'owner'"
                            class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                            placeholder="Business Name"
                        >
                    </div>

                    <div class="mb-4 col-span-2">
                        <div class="flex items-center">
                            <input 
                                type="checkbox" 
                                x-model="generatePassword" 
                                class="mr-2"
                                @change="generatePassword ? generateRandomPassword() : password = ''"
                            >
                            <label class="text-sm font-medium dark:text-gray-300">
                                Generate Temporary Password
                            </label>
                        </div>

                        <div x-show="!generatePassword" class="mt-2">
                            <label class="block text-sm font-medium dark:text-gray-300 mb-2">Set Password *</label>
                            <input 
                                type="password" 
                                name="password" 
                                x-bind:required="!generatePassword"
                                x-model="password"
                                class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                                placeholder="Enter password"
                            >
                        </div>

                        <div x-show="generatePassword" class="mt-2">
                            <label class="block text-sm font-medium dark:text-gray-300 mb-2">Generated Temporary Password</label>
                            <div class="flex">
                                <input 
                                    type="text" 
                                    x-model="password" 
                                    readonly
                                    class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-l-md bg-gray-100"
                                >
                                <button 
                                    type="button" 
                                    @click="generateRandomPassword()"
                                    class="px-4 py-2 bg-blue-500 text-white rounded-r-md hover:bg-blue-600"
                                >
                                    Regenerate
                                </button>
                            </div>
                            <p class="text-xs text-gray-500 mt-1">
                                Temporary password. User should change upon first login.
                            </p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-2 mt-4">
                    <button 
                        type="button" 
                        @click="showCreateModal = false" 
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-green-500 text-white rounded-md hover:bg-green-600"
                    >
                        Create User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Edit User Modal -->
    <div 
        x-show="showEditModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center bg-black bg-opacity-50"
    >
        <div 
            @click.outside="showEditModal = false"
            class="bg-white dark:bg-gray-800 rounded-lg shadow-xl p-6 w-full max-w-md mx-4"
        >
            <h2 class="text-2xl font-bold mb-4 dark:text-gray-200">Edit User</h2>
            <form method="POST" x-data="{ 
                userType: editingUser ? editingUser.role : 'client',
                user: editingUser
            }">
                <input type="hidden" name="action" value="update_user">
                <input type="hidden" name="user_id" x-bind:value="user ? user.id : ''">
                
                <div class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Name</label>
                    <input 
                        type="text" 
                        name="name" 
                        x-model="user.name"
                        required 
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Email</label>
                    <input 
                        type="email" 
                        name="email" 
                        x-model="user.email"
                        required 
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Role</label>
                    <select 
                        name="role" 
                        x-model="userType"
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                        <option value="client">Client</option>
                        <option value="owner">Service Owner</option>
                        <option value="admin">Admin</option>
                    </select>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Phone (Optional)</label>
                    <input 
                        type="tel" 
                        name="phone" 
                        x-model="user.phone"
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Address (Optional)</label>
                    <input 
                        type="text" 
                        name="address" 
                        x-model="user.address"
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                </div>

                <div x-show="userType === 'owner'" class="mb-4">
                    <label class="block text-sm font-medium dark:text-gray-300 mb-2">Business Name *</label>
                    <input 
                        type="text" 
                        name="business_name" 
                        x-model="user.business_name"
                        x-bind:required="userType === 'owner'"
                        class="w-full px-3 py-2 border dark:bg-gray-700 dark:border-gray-600 rounded-md"
                    >
                </div>

                <div class="flex justify-end space-x-2">
                    <button 
                        type="button" 
                        @click="showEditModal = false" 
                        class="px-4 py-2 bg-gray-200 dark:bg-gray-700 rounded-md"
                    >
                        Cancel
                    </button>
                    <button 
                        type="submit" 
                        class="px-4 py-2 bg-blue-500 text-white rounded-md hover:bg-blue-600"
                    >
                        Update User
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        
    </script>
</body>
</html>