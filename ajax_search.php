<?php
// Include database configuration
require_once 'config.php';

// Check if query is set
if (isset($_POST['query']) && !empty($_POST['query'])) {
    $query = '%' . $_POST['query'] . '%';
    
    // Prepare SQL
    $sql = "SELECT id, name, price, image, category_name, avg_rating, review_count FROM vw_services_with_details 
            WHERE (name LIKE :query OR description LIKE :query) 
            AND is_available = 1 
            ORDER BY featured DESC 
            LIMIT 5";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':query', $query, PDO::PARAM_STR);
    $stmt->execute();
    
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($results) > 0) {
        echo '<div class="divide-y divide-gray-100">';
        foreach ($results as $result) {
            echo '<a href="service_details.php?id=' . $result['id'] . '" class="block p-3 hover:bg-gray-50">';
            echo '<div class="flex items-center">';
            
            // Service image
            echo '<div class="flex-shrink-0 w-12 h-12 mr-3 overflow-hidden rounded">';
            echo '<img src="' . (!empty($result['image']) ? 'uploads/services/' . htmlspecialchars($result['image']) : 'assets/img/default-service.jpg') . '" 
                  class="w-full h-full object-cover">';
            echo '</div>';
            
            // Service details
            echo '<div class="flex-1 min-w-0">';
            echo '<h6 class="text-sm font-medium text-gray-900 truncate">' . htmlspecialchars($result['name']) . '</h6>';
            
            // Display stars
            echo '<div class="flex items-center">';
            $rating = $result['avg_rating'] ? round($result['avg_rating']) : 0;
            for ($i = 1; $i <= 5; $i++) {
                if ($i <= $rating) {
                    echo '<i class="fas fa-star text-yellow-400 text-xs"></i>';
                } else {
                    echo '<i class="far fa-star text-yellow-400 text-xs"></i>';
                }
            }
            echo ' <span class="text-xs text-gray-500 ml-1">(' . ($result['review_count'] ? $result['review_count'] : '0') . ')</span>';
            echo '</div>';
            
            echo '<div class="text-xs text-gray-500 mt-1">' . htmlspecialchars($result['category_name']) . ' · $' . number_format($result['price'], 2) . '</div>';
            echo '</div>';
            
            echo '</div>';
            echo '</a>';
        }
        echo '</div>';
    } else {
        echo '<div class="p-3 text-center text-gray-500">No services found matching "' . htmlspecialchars($_POST['query']) . '"</div>';
    }
}
?>