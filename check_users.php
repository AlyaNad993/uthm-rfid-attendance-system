<?php
// check_users.php
require_once 'includes/config.php';

echo "<h2>Checking Users in Database</h2>";

try {
    // Check all users
    $stmt = $pdo->query("SELECT user_id, matric_no, full_name, email, role FROM users");
    $users = $stmt->fetchAll();
    
    if (empty($users)) {
        echo "<div style='color: red; padding: 15px; background: #ffebee; border: 2px solid #f44336; border-radius: 5px;'>
              <strong>❌ NO USERS FOUND!</strong><br>
              Database is empty. You need to create users first.</div>";
    } else {
        echo "<p>Found " . count($users) . " users:</p>";
        echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>
                <tr style='background: #f5f5f5;'>
                    <th>ID</th>
                    <th>Matric No</th>
                    <th>Full Name</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                </tr>";
        
        foreach ($users as $user) {
            echo "<tr>
                    <td>{$user['user_id']}</td>
                    <td>{$user['matric_no']}</td>
                    <td>{$user['full_name']}</td>
                    <td>{$user['email']}</td>
                    <td>{$user['role']}</td>
                    <td>";
            
            // Check password
            $test_passwords = ['admin123', 'lect123', 'student123', 'password', '123456'];
            $password_found = false;
            
            foreach ($test_passwords as $test_pass) {
                $stmt2 = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ?");
                $stmt2->execute([$user['user_id']]);
                $hash = $stmt2->fetch()['password_hash'];
                
                if (password_verify($test_pass, $hash)) {
                    echo "✅ Password: <strong>$test_pass</strong>";
                    $password_found = true;
                    break;
                }
            }
            
            if (!$password_found) {
                echo "❌ Password unknown";
            }
            
            echo "</td></tr>";
        }
        echo "</table>";
    }
    
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

echo "<hr>";
echo '<h3>Quick Actions:</h3>';
echo '<a href="create_admin.php" style="display: inline-block; padding: 10px 20px; background: #006837; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Create Admin User</a>';
echo '<a href="login.php" style="display: inline-block; padding: 10px 20px; background: #2196F3; color: white; text-decoration: none; border-radius: 5px; margin: 5px;">Back to Login</a>';
?>