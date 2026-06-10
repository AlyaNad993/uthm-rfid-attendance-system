<?php
// debug.php
require_once '../includes/auth_check.php';
requireAdmin();

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Debug - Check Paths</h1>";

// Check current directory
echo "<p>Current directory: " . __DIR__ . "</p>";
echo "<p>Current file: " . __FILE__ . "</p>";

// Define base paths
$base_path1 = dirname(dirname(__FILE__));
$base_path2 = dirname(__DIR__);
$base_path3 = realpath(__DIR__ . '/..');

echo "<p>Base path 1 (dirname(dirname)): $base_path1</p>";
echo "<p>Base path 2 (dirname(__DIR__)): $base_path2</p>";
echo "<p>Base path 3 (realpath): $base_path3</p>";

// Check config.php locations
$config_locations = [
    $base_path1 . '/config.php',
    $base_path2 . '/config.php',
    $base_path3 . '/config.php',
    __DIR__ . '/../config.php',
    dirname(__DIR__) . '/config.php',
    realpath(__DIR__ . '/../config.php'),
    'C:/xampp/htdocs/uthm_rfid_attendance/config.php'
];

echo "<h2>Checking config.php locations:</h2>";
foreach ($config_locations as $location) {
    if (file_exists($location)) {
        echo "<p style='color: green;'>✓ Found: $location</p>";
    } else {
        echo "<p style='color: red;'>✗ Not found: $location</p>";
    }
}

// Check if we're in admin folder
echo "<h2>Folder structure:</h2>";
echo "<pre>";
echo "Current: " . __DIR__ . "\n";
echo "Parent: " . dirname(__DIR__) . "\n";
$parent_files = scandir(dirname(__DIR__));
echo "Files in parent folder:\n";
print_r($parent_files);
echo "</pre>";

// Try to find config.php manually
echo "<h2>Searching for config.php:</h2>";
function findConfigFile($dir) {
    $files = scandir($dir);
    foreach ($files as $file) {
        if ($file === 'config.php') {
            return $dir . '/' . $file;
        }
    }
    return false;
}

$found = findConfigFile(dirname(__DIR__));
if ($found) {
    echo "<p style='color: green;'>Found config.php at: $found</p>";
} else {
    echo "<p style='color: red;'>config.php not found in parent directory</p>";
    
    // Check project root
    $project_root = 'C:/xampp/htdocs/uthm_rfid_attendance';
    if (file_exists($project_root . '/config.php')) {
        echo "<p style='color: green;'>Found config.php at: $project_root/config.php</p>";
    }
}
?>
