<?php
/**
 * Process image upload for services
 * 
 * @param array $file The uploaded file array from $_FILES
 * @return string|false The filename of the uploaded image if successful, false otherwise
 */
function processImageUpload($file) {
    // Check if the upload directory exists, create it if not
    $uploadDir = 'images/';
    if (!file_exists($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }
    
    // Check for upload errors
    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log("Upload error: " . $file['error']);
        return false;
    }
    
    // Validate file type
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
    $detectedType = finfo_file($fileInfo, $file['tmp_name']);
    finfo_close($fileInfo);
    
    if (!in_array($detectedType, $allowedTypes)) {
        error_log("Invalid file type: " . $detectedType);
        return false;
    }
    
    // Validate file size (5MB max)
    $maxSize = 5 * 1024 * 1024; // 5MB in bytes
    if ($file['size'] > $maxSize) {
        error_log("File too large: " . $file['size'] . " bytes");
        return false;
    }
    
    // Generate a unique filename to prevent overwriting
    $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid('service_') . '.' . $fileExtension;
    $targetPath = $uploadDir . $newFilename;
    
    // Move the uploaded file to the target directory
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Optionally resize the image here if needed
        return $newFilename;
    } else {
        error_log("Failed to move uploaded file");
        return false;
    }
}
?>