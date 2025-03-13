<?php
// User authentication functions
function register_user($name, $email, $password, $phone, $address) {
    global $conn;
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    $sql = "INSERT INTO users (name, email, password, phone, address) 
            VALUES (?, ?, ?, ?, ?)";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "sssss", $name, $email, $hashed_password, $phone, $address);
        
        if(mysqli_stmt_execute($stmt)) {
            return true;
        } else {
            return false;
        }
    }
    
    mysqli_stmt_close($stmt);
    return false;
}

function login_user($email, $password) {
    global $conn;
    
    $sql = "SELECT id, name, email, password, role FROM users WHERE email = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "s", $email);
        
        if(mysqli_stmt_execute($stmt)) {
            mysqli_stmt_store_result($stmt);
            
            if(mysqli_stmt_num_rows($stmt) == 1) {
                mysqli_stmt_bind_result($stmt, $id, $name, $email, $hashed_password, $role);
                
                if(mysqli_stmt_fetch($stmt)) {
                    if(password_verify($password, $hashed_password)) {
                        // Password is correct, start a new session
                        session_start();
                        
                        // Store data in session variables
                        $_SESSION["loggedin"] = true;
                        $_SESSION["id"] = $id;
                        $_SESSION["name"] = $name;
                        $_SESSION["email"] = $email;
                        $_SESSION["role"] = $role;
                        
                        return true;
                    }
                }
            }
        }
    }
    
    mysqli_stmt_close($stmt);
    return false;
}
?>