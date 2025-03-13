<?php
// Simple test file to verify login page can load
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Login Page</title>
</head>
<body>
    <h1>Test Login Page</h1>
    <p>If you can see this, the basic PHP file is loading correctly.</p>
    
    <form method="post" action="">
        <div>
            <label>Email: <input type="email" name="email"></label>
        </div>
        <div>
            <label>Password: <input type="password" name="password"></label>
        </div>
        <div>
            <button type="submit">Login</button>
        </div>
    </form>
    
    <p><a href="register.php">Register</a></p>
</body>
</html>