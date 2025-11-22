<?php
// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// Redirect jika sudah login
if (isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Handle login form submission
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';
    $csrf_token = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';
    
    // Verify CSRF token
    if (!verifyCSRFToken($csrf_token)) {
        $error = 'Invalid security token';
    } else {
        $pdo = getPDO();
        
        // Check login attempts
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE username = ?");
        $stmt->execute(array($username));
        $admin = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Gunakan md5 untuk PHP 5.6
        if ($admin && md5($password) === $admin['password_hash']) {
            // Login successful
            $_SESSION['loggedin'] = true;
            $_SESSION['admin_id'] = $admin['id'];
            $_SESSION['username'] = $admin['username'];
            $_SESSION['LAST_ACTIVITY'] = time();
            
            // Regenerate session ID for security
            session_regenerate_id(true);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ESP32 OTA Login</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { 
            font-family: Arial, sans-serif; 
            background: #f5f5f5;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        h2 { 
            text-align: center; 
            margin-bottom: 1.5rem;
            color: #333;
        }
        .form-group { margin-bottom: 1rem; }
        label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #555;
            font-weight: bold;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #ddd;
            border-radius: 5px;
            font-size: 1rem;
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1rem;
            cursor: pointer;
        }
        .btn:hover { background: #0056b3; }
        .error {
            background: #fee;
            color: #c33;
            padding: 0.75rem;
            border-radius: 5px;
            margin-bottom: 1rem;
            text-align: center;
        }
        .login-info {
            background: #f0f8ff;
            padding: 1rem;
            border-radius: 5px;
            margin-top: 1rem;
            font-size: 0.9rem;
            color: #666;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <h2>HIWING GPS - OTA UPDATE</h2>
        
        <?php if ($error): ?>
            <div class="error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
            
            <div class="form-group">
                <label for="username">Username :</label>
                <input type="text" id="username" name="username" placeholder="Input Username" required>
            </div>
            
            <div class="form-group">
                <label for="password">Password :</label>
                <input type="password" id="password" name="password" placeholder="Input Password" required>
            </div>
            
            <button type="submit" class="btn">Login</button>
        </form>
        
    </div>
</body>
</html>