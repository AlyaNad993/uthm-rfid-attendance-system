<?php
// START SESSION
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include config
require_once 'includes/config.php';

// Initialize variables
$error = '';
$email = '';
$password = '';

// Check if form submitted
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    // Simple validation
    if (empty($email) || empty($password)) {
        $error = 'Please enter both email and password';
    } else {
        try {
            // Check user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
            $stmt->execute([$email]);
            $user = $stmt->fetch();
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password_hash'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['fullname'] = $user['full_name'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Redirect based on role
                    if ($user['role'] == 'admin') {
                        header('Location: admin/dashboard.php');
                    } elseif ($user['role'] == 'lecturer') {
                        header('Location: lecturer/dashboard.php');
                    } elseif ($user['role'] == 'student') {
                        header('Location: student/dashboard.php');
                    }
                    exit();
                } else {
                    $error = 'Invalid password';
                }
            } else {
                $error = 'User not found';
            }
            
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - UTHM RFID System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #006837 0%, #004d29 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
        }
        
        .login-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            animation: slideUp 0.5s ease;
        }
        
        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        .logo {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #006837, #00a859);
            border-radius: 50%;
            margin: 0 auto 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 32px;
        }
        
        .logo h1 {
            color: #006837;
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .logo p {
            color: #666;
            font-size: 14px;
        }
        
        .error-box {
            background: linear-gradient(135deg, #ffebee, #ffcdd2);
            color: #d32f2f;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            border-left: 4px solid #f44336;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
        }
        
        .input-group {
            position: relative;
        }
        
        .input-group i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #666;
        }
        
        .input-group input {
            width: 100%;
            padding: 15px 15px 15px 45px;
            border: 2px solid #ddd;
            border-radius: 10px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .input-group input:focus {
            outline: none;
            border-color: #006837;
            box-shadow: 0 0 0 3px rgba(0, 104, 55, 0.1);
        }
        
        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #006837, #00a859);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }
        
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 104, 55, 0.3);
        }
        
        .demo-box {
            background: linear-gradient(135deg, #fff8e1, #ffecb3);
            border: 2px solid #ffd54f;
            border-radius: 10px;
            padding: 20px;
            margin-top: 25px;
        }
        
        .demo-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
            color: #5d4037;
        }
        
        .demo-header i {
            color: #ff9800;
        }
        
        .demo-list {
            list-style: none;
        }
        
        .demo-list li {
            margin-bottom: 8px;
            color: #5d4037;
            font-size: 14px;
        }
        
        .demo-list strong {
            color: #006837;
        }
        
        .links {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        
        .links a {
            color: #006837;
            text-decoration: none;
            font-weight: 500;
        }
        
        .links a:hover {
            text-decoration: underline;
        }
        
        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <!-- Logo -->
            <div class="logo">
                <div class="logo-icon">
                    <i class="fas fa-id-card"></i>
                </div>
                <h1>UTHM RFID System</h1>
                <p>Attendance Management System</p>
            </div>
            
            <!-- Error Message -->
            <?php if (!empty($error)): ?>
            <div class="error-box">
                <i class="fas fa-exclamation-circle"></i>
                <div>
                    <strong>Login Failed</strong>
                    <p style="margin-top: 3px; font-size: 14px;"><?php echo htmlspecialchars($error); ?></p>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Login Form -->
            <form method="POST" action="">
                <!-- Email -->
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-group">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($email); ?>"
                               placeholder="email@uthm.edu.my" required>
                    </div>
                </div>
                
                <!-- Password -->
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-group">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" 
                               placeholder="Enter your password" required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye"></i>
                        </button>
                    </div>
                </div>
                
                <!-- Submit Button -->
                <button type="submit" class="btn-login">
                    <i class="fas fa-sign-in-alt"></i> Login to System
                </button>
            </form>
            
            <!-- Demo Credentials -->
            <div class="demo-box">
                <div class="demo-header">
                    <i class="fas fa-info-circle"></i>
                    <h3>Demo Credentials</h3>
                </div>
                <ul class="demo-list">
                    <li><i class="fas fa-user-shield"></i> <strong>Admin:</strong> admin@uthm.edu.my / admin123</li>
                    <li><i class="fas fa-chalkboard-teacher"></i> <strong>Lecturer:</strong> ali@uthm.edu.my / lect123</li>
                    <li><i class="fas fa-user-graduate"></i> <strong>Student:</strong> cd20001@uthm.edu.my / student123</li>
                </ul>
                <p style="margin-top: 10px; font-size: 12px; color: #8d6e63;">
                    <i class="fas fa-exclamation-triangle"></i> Note: Create real users in admin panel
                </p>
            </div>
            
            <!-- Links -->
            <div class="links">
                <a href="create_admin.php">Create Admin Account</a> | 
                <a href="check_users.php">Check Users</a>
            </div>
        </div>
    </div>
    
    <script>
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleButton = document.querySelector('.password-toggle i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleButton.className = 'fas fa-eye-slash';
            } else {
                passwordInput.type = 'password';
                toggleButton.className = 'fas fa-eye';
            }
        }
        
        // Auto-fill demo credentials on click
        document.addEventListener('DOMContentLoaded', function() {
            // Auto-fill if URL has demo parameter
            const urlParams = new URLSearchParams(window.location.search);
            const demo = urlParams.get('demo');
            
            if (demo === 'admin') {
                document.getElementById('email').value = 'admin@uthm.edu.my';
                document.getElementById('password').value = 'admin123';
            } else if (demo === 'lecturer') {
                document.getElementById('email').value = 'ali@uthm.edu.my';
                document.getElementById('password').value = 'lect123';
            } else if (demo === 'student') {
                document.getElementById('email').value = 'cd20001@uthm.edu.my';
                document.getElementById('password').value = 'student123';
            }
            
            // Add click events to demo items
            document.querySelectorAll('.demo-list li').forEach(item => {
                item.style.cursor = 'pointer';
                item.addEventListener('click', function() {
                    const text = this.textContent;
                    if (text.includes('Admin')) {
                        document.getElementById('email').value = 'admin@uthm.edu.my';
                        document.getElementById('password').value = 'admin123';
                    } else if (text.includes('Lecturer')) {
                        document.getElementById('email').value = 'ali@uthm.edu.my';
                        document.getElementById('password').value = 'lect123';
                    } else if (text.includes('Student')) {
                        document.getElementById('email').value = 'cd20001@uthm.edu.my';
                        document.getElementById('password').value = 'student123';
                    }
                });
            });
        });
    </script>
</body>
</html>