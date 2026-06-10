<?php
// Database Configuration
// Use Railway-provided environment variables when deployed.
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'uthm_rfid_attendance');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_PORT', getenv('DB_PORT') ?: '3306');

// REMOVE session_start() from here
// session_start(); // ← COMMENT OR REMOVE THIS LINE

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Create database connection
try {
    $pdoOptions = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];

    if (defined('PDO::MYSQL_ATTR_USE_BUFFERED_QUERY')) {
        $pdoOptions[PDO::MYSQL_ATTR_USE_BUFFERED_QUERY] = true;
    }

    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
    if (!empty(DB_PORT)) {
        $dsn .= ";port=" . DB_PORT;
    }

    $pdo = new PDO(
        $dsn,
        DB_USER,
        DB_PASS,
        $pdoOptions
    );
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Site Configuration
define('SITE_NAME', 'UTHM RFID Attendance System');
define('SITE_URL', getenv('SITE_URL') ?: 'http://172.20.10.2/uthm_rfid_attendance/');
?>
