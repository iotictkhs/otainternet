<?php
session_start();

// Set Timezone to Indonesia (WIB)
date_default_timezone_set('Asia/Jakarta');

// Security Configuration
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 menit
define('SESSION_TIMEOUT', 1800); // 30 menit

// Database Configuration
define('DB_PATH', __DIR__ . '/db.sqlite');

// Firmware Directory
define('FIRMWARE_DIR', __DIR__ . '/firmware/');

// Create directories if not exist
if (!is_dir(FIRMWARE_DIR)) {
    mkdir(FIRMWARE_DIR, 0755, true);
}

// Initialize Database
initDatabase();

// Security Functions
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        // Fallback untuk PHP 5.6 (ganti random_bytes)
        $_SESSION['csrf_token'] = md5(uniqid(rand(), true));
    }
    return $_SESSION['csrf_token'];
}

function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && $_SESSION['csrf_token'] === $token;
}

function checkSessionTimeout() {
    if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY'] > SESSION_TIMEOUT)) {
        session_unset();
        session_destroy();
        return false;
    }
    $_SESSION['LAST_ACTIVITY'] = time();
    return true;
}

function initDatabase() {
    if (!file_exists(DB_PATH)) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Create admin table
        $pdo->exec("CREATE TABLE IF NOT EXISTS admin (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username VARCHAR(50) UNIQUE NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");
        
        // Create update logs table
        $pdo->exec("CREATE TABLE IF NOT EXISTS update_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            version VARCHAR(20) NOT NULL,
            filename VARCHAR(255) NOT NULL,
            file_size INTEGER NOT NULL,
            admin_id INTEGER NOT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (admin_id) REFERENCES admin (id)
        )");
        
        // Create default admin - gunakan md5 untuk PHP 5.6
        $passwordHash = md5('9874563210');
        $stmt = $pdo->prepare("INSERT INTO admin (username, password_hash) VALUES (?, ?)");
        $stmt->execute(['iotmaintenance', $passwordHash]);
        
        // Create version.json file
        $initialVersion = array(
            'version' => '1.0.0',
            'build_date' => date('Y-m-d H:i:s'),
            'file_size' => 0,
            'url' => '/firmware/esp32.bin'
        );
        
        file_put_contents(FIRMWARE_DIR . 'version.json', json_encode($initialVersion));
    }
}

function getPDO() {
    return new PDO('sqlite:' . DB_PATH);
}

// Format waktu untuk display
function formatWIB($datetime) {
    return date('Y-m-d H:i:s', strtotime($datetime));
}
?>