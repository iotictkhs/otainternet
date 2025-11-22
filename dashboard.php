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

// Get current version info
$versionFile = FIRMWARE_DIR . 'version.json';
$currentVersion = file_exists($versionFile) ? 
    json_decode(file_get_contents($versionFile), true) : 
    ['version' => '1.0.0', 'build_date' => 'Unknown'];

// Get update history dengan waktu WIB
$pdo = getPDO();
$stmt = $pdo->query("
    SELECT 
        ul.*, 
        a.username,
        datetime(ul.timestamp, '+7 hours') as timestamp_wib
    FROM update_logs ul 
    JOIN admin a ON ul.admin_id = a.id 
    ORDER BY ul.timestamp DESC 
    LIMIT 10
");
$updateHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Check if firmware file exists
$firmwareExists = file_exists(FIRMWARE_DIR . 'esp32.bin');
$firmwareSize = $firmwareExists ? filesize(FIRMWARE_DIR . 'esp32.bin') : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HIWING GPS - OTA Update System</title>
    <link rel="icon" type="image/x-icon" href="favicon.ico">
    <style>
        * { 
            margin: 0; 
            padding: 0; 
            box-sizing: border-box; 
        }
        
        body { 
            font-family: Arial, sans-serif; 
            background: #f8f9fa;
            color: #333;
            line-height: 1.6;
        }
        
        .header {
            background: white;
            padding: 1rem 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            border-bottom: 3px solid #007bff;
        }
        
        .detail-p { 
            color: #0f2339ff; 
            font-weight: bold;
            font-size: 1.4rem;
            margin: 0;
        }
        
        .user-info { 
            margin-left: auto;
            color: #666;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .logout-btn {
            background: #dc3545;
            color: white;
            padding: 0.5rem 1rem;
            text-decoration: none;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .logout-btn:hover { 
            background: #c82333;
        }
        
        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        
        .card h2 { 
            color: #333;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #0a1118ff;
            font-size: 1.3rem;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .status-item {
            background: white;
            padding: 1.25rem;
            border-radius: 6px;
            border-left: 4px solid #007bff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        
        .status-item h3 { 
            color: #666;
            font-size: 0.9rem;
            margin-bottom: 0.5rem;
            font-weight: normal;
        }
        
        .status-item p { 
            color: #333;
            font-size: 1.2rem;
            font-weight: bold;
        }
        
        .form-group { 
            margin-bottom: 1rem; 
        }
        
        label { 
            display: block; 
            margin-bottom: 0.5rem; 
            color: #333;
            font-weight: bold;
        }
        
        input[type="text"],
        input[type="file"] {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 1rem;
        }
        
        input[type="text"]:focus,
        input[type="file"]:focus {
            outline: none;
            border-color: #007bff;
        }
        
        .btn {
            background: #007bff;
            color: white;
            padding: 0.75rem 1.5rem;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn:hover { 
            background: #0056b3;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
            background: white;
        }
        
        th, td {
            padding: 0.75rem;
            text-align: left;
            border-bottom: 1px solid #dee2e6;
        }
        
        th {
            background: #f8f9fa;
            color: #333;
            font-weight: bold;
        }
        
        tr:hover {
            background: #f8f9fa;
        }
        
        .endpoint-info {
            background: #e9ecef;
            padding: 1rem;
            border-radius: 4px;
            margin: 1rem 0;
        }
        
        .endpoint-info code {
            background: #dee2e6;
            color: #333;
            padding: 0.2rem 0.4rem;
            border-radius: 3px;
            font-family: 'Courier New', monospace;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
        }
        
        .alert-success { 
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .upload-tips {
            background: #fff3cd;
            padding: 1rem;
            border-radius: 4px;
            margin-bottom: 1rem;
            border: 1px solid #ffeaa7;
        }
        
        .upload-tips ul {
            margin: 0.5rem 0 0 1rem;
        }
        
        .upload-tips li {
            margin-bottom: 0.25rem;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .header {
                padding: 1rem;
                flex-direction: column;
                text-align: center;
                gap: 1rem;
            }
            
            .user-info {
                margin-left: 0;
            }
            
            .container {
                padding: 0 0.5rem;
                margin: 1rem auto;
            }
            
            .card {
                padding: 1rem;
            }
            
            .status-grid {
                grid-template-columns: 1fr;
            }
            
            table {
                display: block;
                overflow-x: auto;
            }
            
            th, td {
                padding: 0.5rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 480px) {
            .detail-p {
                font-size: 1.2rem;
            }
            
            .card h2 {
                font-size: 1.1rem;
            }
            
            .status-item {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <p class="detail-p">HIWING GPS Update System</p>
        <div class="user-info">
            Welcome, <?php echo $_SESSION['username']; ?>!
            <a href="logout.php" class="logout-btn">Logout</a>
        </div>
    </div>

    <div class="container">
        <?php if (isset($_GET['success'])): ?>
            <div class="alert alert-success">
                ✅ Firmware updated successfully!
            </div>
        <?php endif; ?>

        <div class="status-grid">
            <div class="status-item">
                <h3>Current Version</h3>
                <p><?php echo $currentVersion['version']; ?></p>
            </div>
            <div class="status-item">
                <h3>Last Build Date</h3>
                <p><?php echo $currentVersion['build_date']; ?></p>
            </div>
            <div class="status-item">
                <h3>Firmware Status</h3>
                <p><?php echo $firmwareExists ? '✅ Available' : '❌ Not Found'; ?></p>
            </div>
            <div class="status-item">
                <h3>Firmware Size</h3>
                <p><?php echo round($firmwareSize / 1024, 2); ?> KB</p>
            </div>
        </div>

        <div class="card">
            <h2>📤 Upload New Firmware</h2>
            
            <div class="upload-tips">
                <strong>💡 Upload Tips:</strong>
                <ul>
                    <li>File harus berekstensi <code>.bin</code></li>
                    <li>Nama file asli tidak masalah (contoh: <code>hiwing-gps.ino.esp32.bin</code>)</li>
                    <li>File akan disimpan sebagai <code>esp32.bin</code> (nama tetap)</li>
                    <li>Maksimal ukuran file: 4MB</li>
                </ul>
            </div>
            
            <form action="upload" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                
                <div class="form-group">
                    <label for="version">Version Number (semantic):</label>
                    <input type="text" id="version" name="version" 
                        placeholder="exm 1.0.0" 
                        pattern="\d+\.\d+\.\d+" 
                        title="Format: X.X.X (numbers only)" required>
                </div>
                
                <div class="form-group">
                    <label for="firmware">Firmware File (.bin):</label>
                    <input type="file" id="firmware" name="firmware" 
                        accept=".bin" required>
                    <small style="color: #666; display: block; margin-top: 0.5rem;">
                        File .bin dari Arduino IDE (contoh: hiwing-gps.ino.esp32.bin)
                    </small>
                </div>
                
                <button type="submit" class="btn">Upload & Update</button>
            </form>
        </div>

        <div class="card">
            <h2>🔗 Static Endpoints Untuk ESP32</h2>
            <div class="endpoint-info">
                <p><strong>URL GET UNTUK ESP32 :</strong></p>
                <p>Version Check: <code>http://<?php echo $_SERVER['HTTP_HOST']; ?>/iototainternet/firmware/version.json</code></p>
                <p>Firmware Download: <code>http://<?php echo $_SERVER['HTTP_HOST']; ?>/iototainternet/firmware/esp32.bin</code></p>
            </div>
        </div>

        <div class="card">
            <h2>📋 Update History</h2>
            <?php if ($updateHistory): ?>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Version</th>
                                <th>File Size</th>
                                <th>Uploaded By</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($updateHistory as $log): ?>
                                <tr>
                                    <td><?php echo $log['version']; ?></td>
                                    <td><?php echo round($log['file_size'] / 1024, 2); ?> KB</td>
                                    <td><?php echo $log['username']; ?></td>
                                    <td><?php echo $log['timestamp_wib']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p style="text-align: center; color: #666; padding: 1rem;">No update history found.</p>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>