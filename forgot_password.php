<?php
// Include database configuration
require_once 'config.php';

// Initialize variables
$email = '';
$errors = [];
$success = false;

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    
    // Validation
    if (empty($email)) {
        $errors[] = "Email is required";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    // If no errors, proceed with password reset
    if (empty($errors)) {
        try {
            // Check if email exists
            $stmt = $db->prepare("SELECT COUNT(*) FROM users WHERE email = ?");
            $stmt->execute([$email]);
            
            if ($stmt->fetchColumn() > 0) {
                // Generate unique token
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
                
                // Store token in database
                $stmt = $db->prepare("
                    INSERT INTO password_resets (email, token, expires_at, created_at)
                    VALUES (?, ?, ?, NOW())
                ");
                $stmt->execute([$email, $token, $expires]);
                
                // In a real application, send email with reset link
                // For now, we'll just show the reset link on screen
                $resetLink = "reset_password.php?token=$token";
                $success = true;
                
                // Set message in session for display on the login page
                $_SESSION['success_message'] = "Password reset instructions have been sent to your email.";
            } else {
                $errors[] = "Email not found in our records";
            }
        } catch (PDOException $e) {
            $errors[] = "Password reset request failed: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Forgot Password</title>
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
        
        <h2 class="text-2xl font-bold text-center mb-6">Forgot Password</h2>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <p>Password reset instructions have been sent to your email.</p>
                <p class="mt-2">
                    <a href="login.php" class="text-purple-600 hover:text-purple-800">Return to login</a>
                </p>
                
                <?php if (isset($resetLink)): ?>
                    <!-- For development purposes only, show the reset link -->
                    <div class="mt-4 p-3 bg-gray-100 rounded text-sm">
                        <p class="mb-1"><strong>Development Only:</strong> Reset link</p>
                        <a href="<?php echo $resetLink; ?>" class="text-blue-600 break-all"><?php echo $resetLink; ?></a>
                    </div>
                <?php endif; ?>
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
            
            <p class="text-gray-700 mb-6">Enter your email address and we'll send you instructions to reset your password.</p>
            
            <form action="forgot_password.php" method="post" class="space-y-4">
                <div>
                    <label for="email" class="block text-gray-700 font-medium mb-1">Email Address</label>
                    <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required
                           class="w-full px-4 py-2 border rounded focus:outline-none focus:ring-2 focus:ring-purple-300">
                </div>
                
                <button type="submit" class="w-full bg-purple-600 text-white py-2 rounded-lg hover:bg-purple-700 focus:outline-none focus:ring-2 focus:ring-purple-500 focus:ring-opacity-50">
                    Send Reset Instructions
                </button>
            </form>
            
            <div class="mt-6 text-center">
                <a href="login.php" class="text-purple-600 hover:text-purple-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Login
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>