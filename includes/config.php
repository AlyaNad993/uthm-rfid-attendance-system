<?php
// Database Configuration

$mysqlUrl = getenv('MYSQL_URL') ?: getenv('DATABASE_URL') ?: false;

if ($mysqlUrl) {
    $parts = parse_url($mysqlUrl);

    $dbHost = $parts['host'] ?? '127.0.0.1';
    $dbPort = isset($parts['port']) ? (string) $parts['port'] : '3306';
    $dbUser = $parts['user'] ?? 'di230078';
    $dbPass = $parts['pass'] ?? 'di230078';
    $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : 'di230078';
} else {
    $databaseHost = getenv('DB_HOST') ?: '127.0.0.1';
    $databasePort = getenv('DB_PORT') ?: '3306';

    if ($databaseHost === 'localhost' && !empty($databasePort)) {
        $databaseHost = '127.0.0.1';
    }

    $dbHost = $databaseHost;
    $dbPort = $databasePort;

    // Hosting database credentials
    $dbName = getenv('DB_NAME') ?: 'di230078';
    $dbUser = getenv('DB_USER') ?: 'di230078';
    $dbPass = getenv('DB_PASS') ?: 'di230078';
}

define('DB_HOST', $dbHost);
define('DB_NAME', $dbName);
define('DB_USER', $dbUser);
define('DB_PASS', $dbPass);
define('DB_PORT', $dbPort);

// Timezone
date_default_timezone_set('Asia/Kuala_Lumpur');

// Create PDO database connection
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
define('SITE_URL', getenv('SITE_URL') ?: 'https://10.65.200.8/di230078/');
?>