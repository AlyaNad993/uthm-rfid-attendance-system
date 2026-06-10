<?php
// create_admin.php
require_once 'includes/config.php';

echo "<h2>Create Admin User</h2>";

// Check if admin already exists
$stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'");
$admin_count = $stmt->fetch()['count'];

if ($admin_count > 0) {
    echo "<div style='color: orange; padding: 15px; background: #fff3e0; border: 2px solid #ff9800; border-radius: 5px;'>
          <strong>⚠ Admin user already exists!</strong><br>
          Please login with the existing admin account or update the password from the database/admin panel.</div>";
    
    echo '<a href="login.php" style="display: inline-block; padding: 10px 20px; background: #006837; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0;">Go to Login Page</a><br>';
}

// Create admin
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = 'admin@uthm.edu.my';
    $password = 'admin123';
    
    try {
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert admin
        $stmt = $pdo->prepare("
            INSERT INTO users (matric_no, full_name, email, password_hash, role, faculty) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            'ADMIN001',
            'System Administrator',
            $email,
            $hashed_password,
            'admin',
            'FKEE'
        ]);
        
        echo "<div style='color: green; padding: 15px; background: #e8f5e9; border: 2px solid #4CAF50; border-radius: 5px;'>
              <strong>✅ Admin user created successfully!</strong><br><br>
              <strong>Login Details:</strong><br>
              📧 Email: <strong>$email</strong><br>
              🔑 Password: <strong>$password</strong></div>";
        
        echo '<br><a href="login.php" style="display: inline-block; padding: 10px 20px; background: #006837; color: white; text-decoration: none; border-radius: 5px;">Go to Login Page</a>';
        
    } catch (PDOException $e) {
        echo "<div style='color: red; padding: 15px; background: #ffebee; border: 2px solid #f44336; border-radius: 5px;'>
              <strong>❌ Error:</strong> " . $e->getMessage() . "</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Create Admin</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 20px; }
        .form-box { max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; border-radius: 10px; }
        .btn { padding: 10px 20px; background: #006837; color: white; border: none; border-radius: 5px; cursor: pointer; }
        .btn:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="form-box">
        <h3>Create New Admin User</h3>
        <p>This will create a default admin user with the following credentials:</p>
        
        <div style="background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 15px 0;">
            <strong>Default Credentials:</strong><br>
            Email: <strong>admin@uthm.edu.my</strong><br>
            Password: <strong>admin123</strong>
        </div>
        
        <form method="POST">
            <button type="submit" class="btn">Create Admin User</button>
            <a href="login.php" style="margin-left: 15px;">Cancel</a>
        </form>
        
        <div style="margin-top: 20px; padding: 15px; background: #e3f2fd; border-radius: 5px;">
            <strong>Note:</strong> After creating admin, you can login and create other users from the admin panel.
        </div>
    </div>
</body>
</html>
