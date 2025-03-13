<?php
// Service management functions
function get_all_categories() {
    global $conn;
    
    $sql = "SELECT * FROM categories ORDER BY name";
    $result = mysqli_query($conn, $sql);
    
    $categories = [];
    while($row = mysqli_fetch_assoc($result)) {
        $categories[] = $row;
    }
    
    return $categories;
}

function get_services_by_category($category_id) {
    global $conn;
    
    $sql = "SELECT * FROM services WHERE category_id = ? AND is_available = 1";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $category_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            $services = [];
            while($row = mysqli_fetch_assoc($result)) {
                $services[] = $row;
            }
            
            return $services;
        }
    }
    
    return [];
}

function get_service_details($service_id) {
    global $conn;
    
    $sql = "SELECT s.*, c.name as category_name 
            FROM services s 
            JOIN categories c ON s.category_id = c.id 
            WHERE s.id = ?";
    
    if($stmt = mysqli_prepare($conn, $sql)) {
        mysqli_stmt_bind_param($stmt, "i", $service_id);
        
        if(mysqli_stmt_execute($stmt)) {
            $result = mysqli_stmt_get_result($stmt);
            
            if($row = mysqli_fetch_assoc($result)) {
                return $row;
            }
        }
    }
    
    return null;
}
?>