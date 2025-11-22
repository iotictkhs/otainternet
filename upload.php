<?php
require_once 'config.php';

// Check authentication
if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
    header('Location: index.php');
    exit;
}

// Check session timeout
if (!checkSessionTimeout()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ? $_POST['csrf_token'] : '';
    $version = sanitizeInput($_POST['version'] ? $_POST['version'] : '');
    $firmware_file = $_FILES['firmware'] ? $_FILES['firmware'] : null;
    
    // Verify CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        die('Invalid security token');
    }
    
    // Validate version format (semantic versioning)
    if (!preg_match('/^\d+\.\d+\.\d+$/', $version)) {
        die('Invalid version format. Use format: X.X.X (e.g., 1.2.3)');
    }
    
    // Validate file upload
    if (!$firmware_file || $firmware_file['error'] !== UPLOAD_ERR_OK) {
        die('File upload failed. Error code: ' . $firmware_file['error']);
    }
    
    // Check file extension (bukan mime type)
    $filename = $firmware_file['name'];
    $file_extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if ($file_extension !== 'bin') {
        die('Invalid file type. Only .bin files are allowed. Detected: .' . $file_extension);
    }
    
    // Check file size (max 4MB untuk ESP32)
    $max_size = 4 * 1024 * 1024; // 4MB
    if ($firmware_file['size'] > $max_size) {
        die('File too large. Maximum size is 4MB. Your file: ' . 
            round($firmware_file['size'] / 1024 / 1024, 2) . 'MB');
    }
    
    // Additional binary file check
    $file_content = file_get_contents($firmware_file['tmp_name']);
    if (strlen($file_content) === 0) {
        die('Uploaded file is empty');
    }
    
    // Fixed filename - SELALU jadi esp32.bin
    $fixed_filename = 'esp32.bin';
    $destination = FIRMWARE_DIR . $fixed_filename;
    
    try {
        $pdo = getPDO();
        
        // Delete old firmware if exists
        if (file_exists($destination)) {
            unlink($destination);
        }
        
        // Move uploaded file
        if (!move_uploaded_file($firmware_file['tmp_name'], $destination)) {
            throw new Exception('Failed to save firmware file. Check folder permissions.');
        }
        
        // Verify the file was saved
        if (!file_exists($destination)) {
            throw new Exception('Firmware file was not saved correctly.');
        }
        
        // Update version.json
        $version_info = [
            'version' => $version,
            'build_date' => date('Y-m-d H:i:s'),
            'file_size' => $firmware_file['size'],
            'url' => '/firmware/esp32.bin',
            'original_filename' => $filename // Simpan nama asli untuk reference
        ];
        
        if (!file_put_contents(FIRMWARE_DIR . 'version.json', json_encode($version_info))) {
            throw new Exception('Failed to update version file');
        }
        
        // Log the update
        $stmt = $pdo->prepare("
            INSERT INTO update_logs (version, filename, file_size, admin_id) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $version,
            $fixed_filename,
            $firmware_file['size'],
            $_SESSION['admin_id']
        ]);
        
        // Redirect to dashboard with success message
        header('Location: dashboard?success=1');
        exit;
        
    } catch (Exception $e) {
        // Clean up if error
        if (file_exists($destination)) {
            unlink($destination);
        }
        die('Error: ' . $e->getMessage());
    }
} else {
    header('Location: dashboard.php');
    exit;
}
?>