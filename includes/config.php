<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'uthm_rfid_attendance');
define('DB_USER', 'root');
define('DB_PASS', '');

// REMOVE session_start() from here
// session_start(); // ← COMMENT OR REMOVE THIS LINE

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Create database connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Site Configuration
define('SITE_NAME', 'UTHM RFID Attendance System');
define('SITE_URL', 'http://localhost/uthm_rfid_attendance/');
?>