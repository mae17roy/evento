<?php
// Include database configuration
require_once 'config.php';

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: user_index.php");
    exit();
}

// Initialize variables
$name = $email = $password = $confirm_password = $phone = $address = '';
$errors = [];
$account_type = 'client'; // Default and only account type

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate input
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $phone = trim($_POST['phone'] ?? '');
    $address = trim($_POST['address'] ?? '');
    
    // Validation
    if (empty($name)) {
        $errors[] = "Name is required";
    }
    
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // Check if email already exists
    try {
        // Check if we have a valid database connection
        if (!$db) {
            throw new Exception("Database connection failed. Please try again later.");
        }
        
        $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Email already exists. Please use a different email or login.";
        }
        
        // If no errors, proceed with registration
        if (empty($errors)) {
            // Start transaction
            $db->beginTransaction();
            
            // Hash password for security (using password_hash)
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert new user (always as client)
            $stmt = $db->prepare("
                INSERT INTO users (name, email, password, phone, address, role, created_at, updated_at)
                VALUES (?, ?, ?, ?, ?, 'client', NOW(), NOW())
            ");
            
            $stmt->execute([
                $name, 
                $email, 
                $hashed_password, 
                $phone, 
                $address
            ]);
            
            $userId = $db->lastInsertId();
            
            // Create welcome notification
            $welcomeTitle = 'Welcome to EVENTO';
            $welcomeMessage = 'Welcome to EVENTO! Start exploring event services.';
            
            $notifyStmt = $db->prepare("
                INSERT INTO notifications (user_id, type, title, message, created_at)
                VALUES (?, 'system', ?, ?, NOW())
            ");
            $notifyStmt->execute([$userId, $welcomeTitle, $welcomeMessage]);
            
            // Commit transaction
            $db->commit();
            
            // Set success message for login page
            $_SESSION['success_message'] = "Registration successful! You can now log in.";
            
            // Redirect to login page
            header("Location: login.php");
            exit();
        }
    } catch (PDOException $e) {
        // Rollback transaction on error
        if ($db && $db->inTransaction()) {
            $db->rollBack();
        }
        
        error_log("Registration database error: " . $e->getMessage());
        $errors[] = "Registration failed: Database error, please try again later.";
    } catch (Exception $e) {
        error_log("Registration error: " . $e->getMessage());
        $errors[] = $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Register</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 m-4">
        <div class="flex justify-center mb-8">
            <a href="user_index.php" class="flex items-center">
                <div class="text-purple-600 mr-2">
                    <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                        <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </div>
                <h1 class="text-2xl font-bold">EVENTO</h1>
            </a>
        </div>
        
        <h2 class="text-2xl font-bold text-center mb-6">Create a Client Account</h2>
        
        <?php if (!empty($errors)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <ul class="list-disc list-inside">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo $error; ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>
        
        <form action="register.php" method="post" class="space-y-4">
            <div>
                <label for="name" class="block text-gray-700 font-medium mb-1">Full Name</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-user text-gray-400"></i>
                    </div>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" required
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
            </div>
            
            <div>
                <label for="email" class="block text-gray-700 font-medium mb-1">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
            </div>
            
            <div>
                <label for="password" class="block text-gray-700 font-medium mb-1">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="password" name="password" required
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                    <p class="text-gray-500 text-sm mt-1">Minimum 6 characters</p>
                </div>
            </div>
            
            <div>
                <label for="confirm_password" class="block text-gray-700 font-medium mb-1">Confirm Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
            </div>
            
            <div>
                <label for="phone" class="block text-gray-700 font-medium mb-1">Phone Number (Optional)</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-phone text-gray-400"></i>
                    </div>
                    <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>"
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
            </div>
            
            <div>
                <label for="address" class="block text-gray-700 font-medium mb-1">Address (Optional)</label>
                <div class="relative">
                    <div class="absolute top-3 left-3 flex items-start pointer-events-none">
                        <i class="fas fa-map-marker-alt text-gray-400"></i>
                    </div>
                    <textarea id="address" name="address" rows="2"
                           class="w-full pl-10 px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300"><?php echo htmlspecialchars($address); ?></textarea>
                </div>
            </div>
            
            <!-- Hidden field to ensure user is registered as a client -->
            <input type="hidden" name="account_type" value="client">
            
            <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50 transition-colors">
                Create Account
            </button>
        </form>
        
        <div class="mt-6 text-center">
            <p class="text-gray-600">Already have an account? <a href="login.php" class="text-purple-600 hover:text-purple-800">Login</a></p>
        </div>
        
        <div class="mt-4 text-center text-sm text-gray-600">
            <p>Are you a service provider? Please <a href="owner/register.php" class="text-purple-600 hover:text-purple-800">register here</a> instead.</p>
        </div>
        
        <div class="mt-6 text-center">
            <a href="user_index.php" class="text-gray-500 hover:text-gray-700 text-sm">
                <i class="fas fa-arrow-left mr-1"></i> Back to Home
            </a>
        </div>
    </div>
</body>
</html>