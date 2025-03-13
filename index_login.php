<?php
require_once "config.php";

// Initialize variables
$email = $password = "";
$email_err = $password_err = $login_err = "";
$role = isset($_GET['role']) ? $_GET['role'] : "client"; // Default to client if no role specified

// Process form data when submitted
if($_SERVER["REQUEST_METHOD"] == "POST") {
    
    // Validate email
    if(empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Validate password
    if(empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Get role from form
    $role = isset($_POST["role"]) ? $_POST["role"] : "client";
    
    // Validate credentials
    if(empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, name, email, password, role FROM users WHERE email = ? AND role = ?";
        
        if($stmt = mysqli_prepare($conn, $sql)) {
            // Bind variables to the prepared statement as parameters
            mysqli_stmt_bind_param($stmt, "ss", $param_email, $param_role);
            
            // Set parameters
            $param_email = $email;
            $param_role = $role;
            
            // Attempt to execute the prepared statement
            if(mysqli_stmt_execute($stmt)) {
                // Store result
                mysqli_stmt_store_result($stmt);
                
                // Check if email exists, if yes then verify password
                if(mysqli_stmt_num_rows($stmt) == 1) {                    
                    // Bind result variables
                    mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password, $user_role);
                    
                    if(mysqli_stmt_fetch($stmt)) {
                        // Verify password
                        // Note: In a production environment, you should use password_verify() with properly hashed passwords
                        if($password == $hashed_password) {
                            // Password is correct, start a new session
                            session_start();
                            
                            // Store data in session variables
                            $_SESSION["loggedin"] = true;
                            $_SESSION["id"] = $id;
                            $_SESSION["name"] = $name;
                            $_SESSION["email"] = $email;
                            $_SESSION["role"] = $user_role;
                            
                            // Redirect user to appropriate page
                            if($user_role == "admin") {
                                header("location: admin/dashboard.php");
                            } else if($user_role == "owner") {
                                header("location: owner/dashboard.php");
                            } else {
                                header("location: index.php");
                            }
                        } else {
                            // Password is not valid
                            $login_err = "Invalid password.";
                        }
                    }
                } else {
                    // Email doesn't exist for this role
                    $login_err = "No account found with that email for the selected role.";
                }
            } else {
                $login_err = "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            mysqli_stmt_close($stmt);
        }
    }
    
    // Close connection
    mysqli_close($conn);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EventEase</title>
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-lg shadow-md w-full max-w-md">
        <div class="text-center mb-8">
            <a href="index.php" class="text-3xl font-bold text-indigo-600">EventEase</a>
            <h2 class="text-2xl font-semibold mt-4 text-gray-800">Welcome Back</h2>
            <p class="text-gray-600 mt-2">
                Login as 
                <?php if($role == "owner"): ?>
                    <span class="font-medium">Business Owner</span>
                <?php else: ?>
                    <span class="font-medium">Client</span>
                <?php endif; ?>
            </p>
        </div>

        <?php if(!empty($login_err)): ?>
            <div class="bg-red-100 border-l-4 border-red-500 text-red-700 p-4 mb-6" role="alert">
                <p><?php echo $login_err; ?></p>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]) . '?role=' . $role; ?>" method="post">
            <!-- Hidden role field -->
            <input type="hidden" name="role" value="<?php echo $role; ?>">
            
            <div class="mb-6">
                <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email Address</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-envelope text-gray-400"></i>
                    </div>
                    <input type="email" name="email" id="email" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 <?php echo (!empty($email_err)) ? 'border-red-500' : ''; ?>" placeholder="Your email address" value="<?php echo $email; ?>">
                </div>
                <?php if(!empty($email_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $email_err; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="mb-6">
                <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="fas fa-lock text-gray-400"></i>
                    </div>
                    <input type="password" name="password" id="password" class="pl-10 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?>" placeholder="Your password">
                </div>
                <?php if(!empty($password_err)): ?>
                    <p class="mt-1 text-sm text-red-600"><?php echo $password_err; ?></p>
                <?php endif; ?>
            </div>
            
            <div class="flex items-center justify-between mb-6">
                <div class="flex items-center">
                    <input id="remember-me" name="remember-me" type="checkbox" class="h-4 w-4 text-indigo-600 focus:ring-indigo-500 border-gray-300 rounded">
                    <label for="remember-me" class="ml-2 block text-sm text-gray-700">
                        Remember me
                    </label>
                </div>
                <a href="forgot-password.php" class="text-sm font-medium text-indigo-600 hover:text-indigo-500">
                    Forgot password?
                </a>
            </div>
            
            <div>
                <button type="submit" class="w-full bg-indigo-600 text-white px-4 py-3 rounded-md hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2">
                    Sign in
                </button>
            </div>
            
            <div class="mt-6 text-center">
                <p class="text-sm text-gray-600">
                    Don't have an account? 
                    <a href="register.php" class="font-medium text-indigo-600 hover:text-indigo-500">
                        Sign up
                    </a>
                </p>
                
                <?php if($role == "owner"): ?>
                    <p class="mt-2 text-sm text-gray-600">
                        Are you a client? 
                        <a href="index_login.php?role=client" class="font-medium text-indigo-600 hover:text-indigo-500">
                            Login as Client
                        </a>
                    </p>
                <?php else: ?>
                    <p class="mt-2 text-sm text-gray-600">
                        Are you a business owner? 
                        <a href="index_login.php?role=owner" class="font-medium text-indigo-600 hover:text-indigo-500">
                            Login as Business Owner
                        </a>
                    </p>
                <?php endif; ?>
            </div>
        </form>
    </div>
</body>
</html>