<?php
// เปิดเพื่อทดสอบ SMTP ให้ลบไปหลังจากทดสอบแล้ว
session_start();
require_once 'functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testEmail = trim($_POST['email'] ?? '');
    
    if ($testEmail) {
        $error = null;
        if (send_test_email($testEmail, $error)) {
            $message = "✅ ส่งอีเมลทดสอบสำเร็จไปที่ $testEmail";
        } else {
            $message = "❌ ข้อผิดพลาด: " . ($error ?? 'Unknown error');
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Test Email SMTP</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <style>
        body { padding: 2rem; }
        .container { max-width: 500px; }
    </style>
</head>
<body>
    <div class="container">
        <h2>🧪 ทดสอบการตั้งค่า SMTP</h2>
        
        <?php if (isset($message)): ?>
            <div class="alert alert-<?php echo strpos($message, '✅') === 0 ? 'success' : 'danger'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="mb-3">
                <label class="form-label">ที่อยู่อีเมลทดสอบ:</label>
                <input type="email" class="form-control" name="email" required placeholder="your@email.com">
            </div>
            <button type="submit" class="btn btn-primary w-100">ส่งเมลทดสอบ</button>
        </form>

        <hr>
        <h5>📋 ข้อมูล SMTP ปัจจุบัน:</h5>
        <pre><?php 
            $config = app_config();
            echo "Host: " . $config['smtp_host'] . "\n";
            echo "Port: " . $config['smtp_port'] . "\n";
            echo "Secure: " . $config['smtp_secure'] . "\n";
            echo "Username: " . $config['smtp_username'] . "\n";
            echo "Password: " . (strpos($config['smtp_password'], '*') !== false ? '[ยังเป็น placeholder]' : '[กำหนดแล้ว]') . "\n";
        ?></pre>
    </div>
</body>
</html>
