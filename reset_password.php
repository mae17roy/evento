<?php
// Include database configuration
require_once 'config.php';

// Initialize variables
$token = $_GET['token'] ?? '';
$errors = [];
$success = false;
$email = '';

// Check if token is provided
if (empty($token)) {
    header("Location: login.php");
    exit();
}

// Verify token validity
try {
    $stmt = $db->prepare("
        SELECT * FROM password_resets 
        WHERE token = ? 
        AND expires_at > NOW()
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $reset = $stmt->fetch();
    
    if (!$reset) {
        $_SESSION['error_message'] = "Invalid or expired password reset token. Please request a new one.";
        header("Location: forgot_password.php");
        exit();
    }
    
    $email = $reset['email'];
    
} catch (PDOException $e) {
    $errors[] = "Error verifying token: " . $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['password'])) {
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($password)) {
        $errors[] = "Password is required";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match";
    }
    
    // If no errors, update password
    if (empty($errors)) {
        try {
            // Update user password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            $stmt = $db->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $email]);
            
            // Delete used token
            $stmt = $db->prepare("DELETE FROM password_resets WHERE token = ?");
            $stmt->execute([$token]);
            
            $success = true;
            
            // Set message for login page
            $_SESSION['success_message'] = "Your password has been reset successfully. You can now log in with your new password.";
            
        } catch (PDOException $e) {
            $errors[] = "Password reset failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Reset Password</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="max-w-md w-full bg-white rounded-lg shadow-lg p-8 m-4">
        <div class="flex justify-center mb-8">
            <div class="text-purple-600 mr-2">
                <svg class="h-10 w-10" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 7L12 12L22 7L12 2Z" fill="currentColor"/>
                    <path d="M2 17L12 22L22 17" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    <path d="M2 12L12 17L22 12" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </div>
            <h1 class="text-2xl font-bold">EVENTO</h1>
        </div>
        
        <h2 class="text-2xl font-bold text-center mb-6">Reset Your Password</h2>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p>Your password has been reset successfully!</p>
                <p class="mt-4">
                    <a href="login.php" class="bg-purple-600 text-white py-2 px-4 rounded-lg hover:bg-purple-700">
                        Continue to Login
                    </a>
                </p>
            </div>
        <?php else: ?>
            <?php if (!empty($errors)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <ul class="list-disc list-inside">
                        <?php foreach ($errors as $error): ?>
                            <li><?php echo $error; ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
            
            <p class="text-gray-700 mb-6">Enter your new password below:</p>
            
            <form action="reset_password.php?token=<?php echo htmlspecialchars($token); ?>" method="post" class="space-y-4">
                <div>
                    <label for="password" class="block text-gray-700 font-medium mb-1">New Password</label>
                    <input type="password" id="password" name="password" required
                           class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                    <p class="text-gray-500 text-sm mt-1">Minimum 6 characters</p>
                </div>
                
                <div>
                    <label for="confirm_password" class="block text-gray-700 font-medium mb-1">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
                
                <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">
                    Reset Password
                </button>
            </form>
        <?php endif; ?>
    </div>
</body>
</html>