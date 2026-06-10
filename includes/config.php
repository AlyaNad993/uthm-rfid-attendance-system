<?php
define('DB_HOST', $databaseHost);
// Database Configuration
// Support Railway's injected connection string. Create a service variable
// (e.g. `MYSQL_URL`) with the value `${{ MySQL.MYSQL_URL }}` and this code
// will parse it automatically. Falls back to individual env vars for local dev.
$mysqlUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: false;

if ($mysqlUrl) {
    $parts = parse_url($mysqlUrl);
    $dbHost = $parts['host'] ?? '127.0.0.1';
    $dbPort = isset($parts['port']) ? (string)$parts['port'] : '3306';
    $dbUser = $parts['user'] ?? 'root';
    $dbPass = $parts['pass'] ?? '';
    $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : 'uthm_rfid_attendance';
} else {
    $databaseHost = getenv('DB_HOST');
    $databaseHost = $databaseHost !== false ? $databaseHost : '127.0.0.1';
    $databasePort = getenv('DB_PORT');
    $databasePort = $databasePort !== false ? $databasePort : '3306';

    if ($databaseHost === 'localhost' && !empty($databasePort)) {
        // Force TCP instead of UNIX socket when a port is specified.
        $databaseHost = '127.0.0.1';
    }

    $dbHost = $databaseHost;
    $dbName = getenv('DB_NAME') ?: 'uthm_rfid_attendance';
    $dbUser = getenv('DB_USER') ?: 'root';
    $dbPass = getenv('DB_PASS') ?: '';
    $dbPort = $databasePort;
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_PORT', $dbPort);

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
