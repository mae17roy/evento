<?php
// This file handles the initial database setup

// Start a session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database configuration file
require_once 'config.php';

// Check if the setup was requested
$runSetup = isset($_GET['run']) && $_GET['run'] === 'true';
$message = '';
$success = false;

if ($runSetup) {
    // Attempt to run the database setup
    $setupResult = setupDatabase();
    
    if ($setupResult) {
        $success = true;
        $message = "Database setup completed successfully! The database and required tables have been created.";
        $_SESSION['setup_success'] = $message;
    } else {
        $message = "Database setup failed. Please check the error logs for more information.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EVENTO - Database Setup</title>
    
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    
    <style>
        .setup-container {
            max-width: 800px;
            margin: 0 auto;
        }
        .step {
            border-left: 2px solid #e5e7eb;
            padding-left: 1.5rem;
            position: relative;
            margin-bottom: 2rem;
        }
        .step:before {
            content: "";
            position: absolute;
            width: 1rem;
            height: 1rem;
            border-radius: 50%;
            background-color: #e5e7eb;
            left: -0.5rem;
            top: 0;
        }
        .step.completed:before {
            background-color: #10b981;
        }
        .step.active:before {
            background-color: #6366f1;
        }
        .step.error:before {
            background-color: #ef4444;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen">
    <div class="container mx-auto py-8 px-4">
        <div class="setup-container bg-white rounded-lg shadow-md p-8">
            <div class="text-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800 mb-2">EVENTO Database Setup</h1>
                <p class="text-gray-600">This utility will help you set up the database for your EVENTO system.</p>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="mb-8 p-4 rounded-lg <?php echo $success ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'; ?>">
                    <div class="flex items-center">
                        <i class="fas <?php echo $success ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-3 text-xl"></i>
                        <p><?php echo htmlspecialchars($message); ?></p>
                    </div>
                    <?php if ($success): ?>
                        <div class="mt-4 text-center">
                            <a href="index.php" class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                                Go to Homepage
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Database Status</h2>
                <?php 
                $dbStatus = testDatabaseConnection();
                ?>
                
                <div class="step <?php echo isset($dbStatus['connection']) && $dbStatus['connection'] ? 'completed' : 'error'; ?>">
                    <h3 class="font-semibold mb-1">Database Connection</h3>
                    <p class="text-gray-600 mb-1">Connection to MySQL server: <?php echo htmlspecialchars($dbStatus['host']); ?></p>
                    <p class="<?php echo isset($dbStatus['connection']) && $dbStatus['connection'] ? 'text-green-600' : 'text-red-600'; ?>">
                        <i class="fas <?php echo isset($dbStatus['connection']) && $dbStatus['connection'] ? 'fa-check-circle' : 'fa-times-circle'; ?> mr-1"></i>
                        <?php echo isset($dbStatus['connection']) && $dbStatus['connection'] ? 'Connected successfully' : 'Connection failed'; ?>
                    </p>
                    <?php if (!empty($dbStatus['error'])): ?>
                        <p class="text-red-600 text-sm mt-1"><?php echo htmlspecialchars($dbStatus['error']); ?></p>
                    <?php endif; ?>
                </div>
                
                <div class="step <?php echo isset($dbStatus['connection']) && $dbStatus['connection'] && isset($dbStatus['tables_exist']) && $dbStatus['tables_exist'] ? 'completed' : (isset($dbStatus['connection']) && $dbStatus['connection'] ? 'active' : 'error'); ?>">
                    <h3 class="font-semibold mb-1">Required Tables</h3>
                    <p class="text-gray-600 mb-1">Database: <?php echo htmlspecialchars($dbStatus['database']); ?></p>
                    <p class="<?php echo isset($dbStatus['tables_exist']) && $dbStatus['tables_exist'] ? 'text-green-600' : 'text-yellow-600'; ?>">
                        <i class="fas <?php echo isset($dbStatus['tables_exist']) && $dbStatus['tables_exist'] ? 'fa-check-circle' : 'fa-exclamation-circle'; ?> mr-1"></i>
                        <?php echo isset($dbStatus['tables_exist']) && $dbStatus['tables_exist'] ? 'All required tables exist' : 'Some required tables are missing'; ?>
                    </p>
                </div>
            </div>
            
            <div class="mb-8">
                <h2 class="text-xl font-semibold mb-4">Setup Instructions</h2>
                <ol class="space-y-4 list-decimal list-inside">
                    <li>
                        <span class="font-medium">Verify MySQL Connection</span>
                        <p class="text-gray-600 ml-6 mt-1">Make sure your MySQL server is running through XAMPP or similar software.</p>
                    </li>
                    <li>
                        <span class="font-medium">Check Database Configuration</span>
                        <p class="text-gray-600 ml-6 mt-1">Verify the database credentials in <code>config.php</code> match your MySQL setup.</p>
                        <div class="bg-gray-100 p-3 rounded-lg ml-6 mt-2">
                            <code>
                                $db_host = 'localhost';<br>
                                $db_name = 'event_management_system';<br>
                                $db_user = 'root'; // Your MySQL username<br>
                                $db_pass = ''; // Your MySQL password
                            </code>
                        </div>
                    </li>
                    <li>
                        <span class="font-medium">Run the Setup</span>
                        <p class="text-gray-600 ml-6 mt-1">Click the button below to create the database and required tables if they don't exist.</p>
                    </li>
                </ol>
            </div>
            
            <div class="text-center">
                <?php if ($success): ?>
                    <a href="index.php" class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Go to Homepage
                    </a>
                <?php else: ?>
                    <a href="db_setup.php?run=true" class="inline-block px-6 py-3 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700">
                        Run Database Setup
                    </a>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>