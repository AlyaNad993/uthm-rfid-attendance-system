<!-- test_connection.php -->
<?php
// Debug mode
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";
echo "<p>Testing connection to UTHM RFID database...</p>";

// Try to load config
try {
    require_once 'includes/config.php';
    echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;'>
          <strong>✓ Success:</strong> config.php loaded successfully</div>";
} catch (Exception $e) {
    echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>
          <strong>✗ Error:</strong> Cannot load config.php - " . $e->getMessage() . "</div>";
    exit();
}

// Test database connection
try {
    echo "<h3>1. Testing Database Connection...</h3>";
    
    // Test PDO connection
    $test_pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
    
    echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;'>
          <strong>✓ Success:</strong> Connected to database: <strong>" . DB_NAME . "</strong></div>";
    
    // Get database info
    echo "<h3>2. Database Information:</h3>";
    $stmt = $test_pdo->query("SELECT DATABASE() as db_name, USER() as user, VERSION() as version");
    $db_info = $stmt->fetch();
    
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
            <tr style='background: #f5f5f5;'>
                <th>Database Name</th>
                <th>User</th>
                <th>MySQL Version</th>
            </tr>
            <tr>
                <td>{$db_info['db_name']}</td>
                <td>{$db_info['user']}</td>
                <td>{$db_info['version']}</td>
            </tr>
          </table>";
    
    // List all tables
    echo "<h3>3. Tables in Database:</h3>";
    $stmt = $test_pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    if (empty($tables)) {
        echo "<div style='color: orange; padding: 10px; background: #fff3e0; border: 1px solid #ff9800; border-radius: 5px; margin: 10px 0;'>
              <strong>⚠ Warning:</strong> No tables found in database!</div>";
    } else {
        echo "<p>Found " . count($tables) . " tables:</p>";
        echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
                <tr style='background: #f5f5f5;'>
                    <th>#</th>
                    <th>Table Name</th>
                    <th>Rows</th>
                    <th>Size</th>
                </tr>";
        
        foreach ($tables as $index => $table) {
            // Get row count
            $row_stmt = $test_pdo->query("SELECT COUNT(*) as count FROM `$table`");
            $row_count = $row_stmt->fetch()['count'];
            
            // Get table size
            $size_stmt = $test_pdo->query("
                SELECT 
                    ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024, 2) as size_kb
                FROM information_schema.TABLES 
                WHERE TABLE_SCHEMA = '" . DB_NAME . "' 
                AND TABLE_NAME = '$table'
            ");
            $size = $size_stmt->fetch();
            $table_size = $size ? $size['size_kb'] . ' KB' : 'N/A';
            
            echo "<tr>
                    <td>" . ($index + 1) . "</td>
                    <td><strong>$table</strong></td>
                    <td>$row_count rows</td>
                    <td>$table_size</td>
                  </tr>";
        }
        echo "</table>";
    }
    
    // Check essential tables
    echo "<h3>4. Essential Tables Check:</h3>";
    $essential_tables = ['users', 'rfid_cards', 'courses', 'attendance_records', 'class_schedule'];
    $missing_tables = [];
    
    foreach ($essential_tables as $table) {
        if (!in_array($table, $tables)) {
            $missing_tables[] = $table;
        }
    }
    
    if (empty($missing_tables)) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;'>
              <strong>✓ Success:</strong> All essential tables found</div>";
    } else {
        echo "<div style='color: orange; padding: 10px; background: #fff3e0; border: 1px solid #ff9800; border-radius: 5px; margin: 10px 0;'>
              <strong>⚠ Warning:</strong> Missing tables: " . implode(', ', $missing_tables) . "</div>";
    }
    
    // Test users table structure
    echo "<h3>5. Users Table Structure:</h3>";
    try {
        $stmt = $test_pdo->query("DESCRIBE users");
        $columns = $stmt->fetchAll();
        
        if ($columns) {
            echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
                    <tr style='background: #f5f5f5;'>
                        <th>Column</th>
                        <th>Type</th>
                        <th>Null</th>
                        <th>Key</th>
                        <th>Default</th>
                        <th>Extra</th>
                    </tr>";
            
            foreach ($columns as $col) {
                echo "<tr>
                        <td><strong>{$col['Field']}</strong></td>
                        <td>{$col['Type']}</td>
                        <td>{$col['Null']}</td>
                        <td>{$col['Key']}</td>
                        <td>{$col['Default']}</td>
                        <td>{$col['Extra']}</td>
                      </tr>";
            }
            echo "</table>";
            
            // Check if there are any users
            $stmt = $test_pdo->query("SELECT COUNT(*) as count FROM users");
            $user_count = $stmt->fetch()['count'];
            
            if ($user_count == 0) {
                echo "<div style='color: orange; padding: 10px; background: #fff3e0; border: 1px solid #ff9800; border-radius: 5px; margin: 10px 0;'>
                      <strong>⚠ Note:</strong> No users found in database. You need to create at least one admin user.</div>";
            } else {
                echo "<p>Total users in database: <strong>$user_count</strong></p>";
                
                // Show sample users
                $stmt = $test_pdo->query("SELECT matric_no, full_name, email, role FROM users LIMIT 5");
                $sample_users = $stmt->fetchAll();
                
                echo "<h4>Sample Users:</h4>";
                echo "<table border='1' cellpadding='8' style='border-collapse: collapse; width: 100%;'>
                        <tr style='background: #f5f5f5;'>
                            <th>Matric No</th>
                            <th>Full Name</th>
                            <th>Email</th>
                            <th>Role</th>
                        </tr>";
                
                foreach ($sample_users as $user) {
                    $role_color = '';
                    switch ($user['role']) {
                        case 'admin': $role_color = 'background: #d4edda; color: #155724; padding: 3px 8px; border-radius: 3px;'; break;
                        case 'lecturer': $role_color = 'background: #d1ecf1; color: #0c5460; padding: 3px 8px; border-radius: 3px;'; break;
                        case 'student': $role_color = 'background: #fff3cd; color: #856404; padding: 3px 8px; border-radius: 3px;'; break;
                    }
                    
                    echo "<tr>
                            <td>{$user['matric_no']}</td>
                            <td>{$user['full_name']}</td>
                            <td>{$user['email']}</td>
                            <td><span style='$role_color'>{$user['role']}</span></td>
                          </tr>";
                }
                echo "</table>";
            }
        }
    } catch (Exception $e) {
        echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>
              <strong>✗ Error:</strong> Cannot describe users table - " . $e->getMessage() . "</div>";
    }
    
    // Test sample queries
    echo "<h3>6. Test Queries:</h3>";
    
    $test_queries = [
        "SELECT COUNT(*) as total FROM users WHERE role = 'student'" => "Count Students",
        "SELECT COUNT(*) as total FROM rfid_cards WHERE status = 'active'" => "Count Active RFID Cards",
        "SELECT COUNT(*) as total FROM courses WHERE is_active = 1" => "Count Active Courses",
    ];
    
    foreach ($test_queries as $query => $description) {
        try {
            $stmt = $test_pdo->query($query);
            $result = $stmt->fetch();
            echo "<p><strong>$description:</strong> " . reset($result) . "</p>";
        } catch (Exception $e) {
            echo "<p style='color: red;'><strong>$description:</strong> Error - " . $e->getMessage() . "</p>";
        }
    }
    
    // Check PHP extensions
    echo "<h3>7. PHP Extensions Check:</h3>";
    $required_extensions = ['pdo_mysql', 'mbstring', 'json'];
    $missing_extensions = [];
    
    foreach ($required_extensions as $ext) {
        if (!extension_loaded($ext)) {
            $missing_extensions[] = $ext;
        }
    }
    
    if (empty($missing_extensions)) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;'>
              <strong>✓ Success:</strong> All required PHP extensions are loaded</div>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>
              <strong>✗ Error:</strong> Missing PHP extensions: " . implode(', ', $missing_extensions) . "</div>";
        echo "<p>Enable them in php.ini and restart your web server.</p>";
    }
    
    // Session test
    echo "<h3>8. Session Test:</h3>";
    session_start();
    $_SESSION['test_time'] = date('Y-m-d H:i:s');
    
    if (isset($_SESSION['test_time'])) {
        echo "<div style='color: green; padding: 10px; background: #e8f5e9; border: 1px solid #4CAF50; border-radius: 5px; margin: 10px 0;'>
              <strong>✓ Success:</strong> PHP sessions are working. Test time: " . $_SESSION['test_time'] . "</div>";
    } else {
        echo "<div style='color: red; padding: 10px; background: #ffebee; border: 1px solid #f44336; border-radius: 5px; margin: 10px 0;'>
              <strong>✗ Error:</strong> PHP sessions not working</div>";
    }
    
    // Database permissions test
    echo "<h3>9. Database Permissions Test:</h3>";
    $permission_tests = [
        "INSERT INTO users (matric_no, full_name, email, password_hash, role) VALUES ('TEST001', 'Test User', 'test@uthm.edu.my', 'test_hash', 'student')" => "INSERT permission",
        "SELECT * FROM users LIMIT 1" => "SELECT permission",
        "UPDATE users SET full_name = 'Test Updated' WHERE matric_no = 'TEST001'" => "UPDATE permission",
        "DELETE FROM users WHERE matric_no = 'TEST001'" => "DELETE permission",
    ];
    
    $test_pdo->beginTransaction(); // Start transaction to rollback
    
    foreach ($permission_tests as $query => $permission) {
        try {
            $test_pdo->exec($query);
            echo "<p style='color: green;'><strong>✓ $permission:</strong> OK</p>";
        } catch (Exception $e) {
            echo "<p style='color: orange;'><strong>⚠ $permission:</strong> Failed - " . $e->getMessage() . "</p>";
        }
    }
    
    $test_pdo->rollBack(); // Rollback test changes
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #e3f2fd; border: 1px solid #2196F3; border-radius: 5px;'>
          <h4>🎯 Next Steps:</h4>
          <ol>
            <li>If all tests pass, your database is ready!</li>
            <li>If no users exist, create an admin user</li>
            <li>Test the login page</li>
            <li>Access the admin dashboard</li>
          </ol>
          </div>";
    
} catch (PDOException $e) {
    echo "<div style='color: red; padding: 15px; background: #ffebee; border: 2px solid #f44336; border-radius: 5px; margin: 20px 0;'>
          <h3>✗ Database Connection Failed!</h3>
          <p><strong>Error:</strong> " . $e->getMessage() . "</p>
          <p><strong>Possible solutions:</strong></p>
          <ul>
            <li>Check database credentials in config.php</li>
            <li>Verify database name exists: <strong>" . DB_NAME . "</strong></li>
            <li>Check if MySQL service is running</li>
            <li>Verify username/password permissions</li>
          </ul>
          </div>";
    
    // Show current config (masked password)
    echo "<h3>Current Configuration:</h3>";
    echo "<table border='1' cellpadding='8' style='border-collapse: collapse;'>
            <tr><th>Setting</th><th>Value</th></tr>
            <tr><td>DB_HOST</td><td>" . DB_HOST . "</td></tr>
            <tr><td>DB_NAME</td><td>" . DB_NAME . "</td></tr>
            <tr><td>DB_USER</td><td>" . DB_USER . "</td></tr>
            <tr><td>DB_PASS</td><td>" . (DB_PASS ? '***' . substr(DB_PASS, -3) : 'Empty') . "</td></tr>
          </table>";
}

echo "<hr>
      <div style='text-align: center; margin-top: 30px;'>
        <a href='login.php' style='display: inline-block; padding: 10px 20px; background: #006837; color: white; text-decoration: none; border-radius: 5px; margin-right: 10px;'>Test Login Page</a>
        <a href='admin/dashboard.php' style='display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px;'>Test Admin Dashboard</a>
      </div>";
?>