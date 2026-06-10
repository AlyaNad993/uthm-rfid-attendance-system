<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';

$message = '';
$messageType = 'error';
$activePanel = $_GET['panel'] ?? 'login';
$email = '';

function redirectByRole($role) {
    if ($role === 'admin') {
        header('Location: admin/dashboard.php');
    } elseif ($role === 'lecturer') {
        header('Location: lecturer/dashboard.php');
    } elseif ($role === 'student') {
        header('Location: student/dashboard.php');
    } else {
        header('Location: unauthorized.php');
    }
    exit();
}

function startUserSession($user) {
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['fullname'] = $user['full_name'];
    $_SESSION['role'] = $user['role'];
    $_SESSION['last_activity'] = time();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $formAction = $_POST['form_action'] ?? 'login';
    $activePanel = $formAction;

    try {
        if ($formAction === 'login') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($email === '' || $password === '') {
                $message = 'Please enter both email and password.';
            } else {
                $stmt = $pdo->prepare("
                    SELECT user_id, matric_no, full_name, email, password_hash, role, is_active
                    FROM users
                    WHERE email = ?
                    LIMIT 1
                ");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user || !password_verify($password, $user['password_hash'])) {
                    $message = 'Invalid email or password.';
                } elseif ((int)$user['is_active'] !== 1) {
                    $message = 'This account is inactive. Please contact admin.';
                } else {
                    startUserSession($user);
                    redirectByRole($user['role']);
                }
            }
        }

        if ($formAction === 'register') {
            $matricNo = strtoupper(trim($_POST['matric_no'] ?? ''));
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['register_email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $role = trim($_POST['role'] ?? 'student');
            $password = $_POST['register_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($matricNo === '' || $fullName === '' || $email === '' || $password === '') {
                $message = 'Please fill in all required account details.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $message = 'Please enter a valid email address.';
            } elseif (!in_array($role, ['student', 'lecturer', 'admin'], true)) {
                $message = 'Please choose a valid account type.';
            } elseif (strlen($password) < 6) {
                $message = 'Password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $message = 'Password confirmation does not match.';
            } else {
                $stmt = $pdo->prepare("
                    INSERT INTO users
                        (matric_no, full_name, email, phone, password_hash, role, department, faculty, is_active)
                    VALUES
                        (?, ?, ?, ?, ?, ?, ?, 'OTHER', 1)
                ");
                $stmt->execute([
                    $matricNo,
                    $fullName,
                    $email,
                    $phone,
                    password_hash($password, PASSWORD_DEFAULT),
                    $role,
                    $department
                ]);

                $user = [
                    'user_id' => $pdo->lastInsertId(),
                    'full_name' => $fullName,
                    'email' => $email,
                    'role' => $role
                ];

                startUserSession($user);
                redirectByRole($role);
            }
        }

        if ($formAction === 'forgot') {
            $email = trim($_POST['reset_email'] ?? '');
            $password = $_POST['reset_password'] ?? '';
            $confirmPassword = $_POST['reset_confirm_password'] ?? '';

            if ($email === '' || $password === '') {
                $message = 'Please enter your email and new password.';
            } elseif (strlen($password) < 6) {
                $message = 'New password must be at least 6 characters.';
            } elseif ($password !== $confirmPassword) {
                $message = 'Password confirmation does not match.';
            } else {
                $stmt = $pdo->prepare("SELECT user_id FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $message = 'No active account found for that email.';
                } else {
                    $stmt = $pdo->prepare("UPDATE users SET password_hash = ? WHERE user_id = ?");
                    $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $user['user_id']]);

                    $activePanel = 'login';
                    $messageType = 'success';
                    $message = 'Password updated. You can login with your new password.';
                }
            }
        }
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            $message = 'This matric/staff ID or email already exists.';
        } else {
            $message = 'Database error: ' . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>UTHM RFID Attendance | Access</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at top left, rgba(67, 97, 238, 0.28), transparent 32%),
                radial-gradient(circle at bottom right, rgba(46, 204, 113, 0.22), transparent 30%),
                linear-gradient(135deg, #f5f8ff 0%, #e8f5ee 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            color: #172033;
        }

        .auth-shell {
            width: min(1040px, 100%);
            display: grid;
            grid-template-columns: 0.95fr 1.05fr;
            background: rgba(255, 255, 255, 0.88);
            border: 1px solid rgba(255, 255, 255, 0.72);
            border-radius: 24px;
            box-shadow: 0 24px 70px rgba(28, 52, 84, 0.18);
            overflow: hidden;
            backdrop-filter: blur(16px);
        }

        .brand-panel {
            padding: 44px;
            background:
                linear-gradient(145deg, rgba(0, 104, 55, 0.92), rgba(67, 97, 238, 0.88)),
                #006837;
            color: white;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            min-height: 680px;
        }

        .brand-mark {
            width: 76px;
            height: 76px;
            border-radius: 22px;
            display: grid;
            place-items: center;
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.28);
            font-size: 32px;
            margin-bottom: 24px;
        }

        .brand-panel h1 {
            font-size: clamp(32px, 5vw, 48px);
            line-height: 1.04;
            margin-bottom: 16px;
            letter-spacing: 0;
        }

        .brand-panel p {
            color: rgba(255, 255, 255, 0.82);
            font-size: 16px;
            line-height: 1.7;
            max-width: 420px;
        }

        .feature-list {
            display: grid;
            gap: 14px;
            margin-top: 34px;
        }

        .feature-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px;
            border-radius: 14px;
            background: rgba(255, 255, 255, 0.12);
        }

        .feature-item i { width: 20px; text-align: center; }

        .form-panel {
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .panel-header {
            margin-bottom: 24px;
        }

        .panel-header h2 {
            font-size: 30px;
            margin-bottom: 8px;
        }

        .panel-header p {
            color: #68748a;
            line-height: 1.6;
        }

        .tabs {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            padding: 5px;
            border-radius: 14px;
            background: #eef3f8;
            margin-bottom: 22px;
            gap: 4px;
        }

        .tab-btn {
            border: 0;
            border-radius: 11px;
            padding: 11px 10px;
            background: transparent;
            color: #5f6b80;
            font-weight: 700;
            cursor: pointer;
            transition: 0.2s ease;
        }

        .tab-btn.active {
            background: white;
            color: #006837;
            box-shadow: 0 8px 22px rgba(29, 51, 84, 0.1);
        }

        .notice {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 14px 16px;
            border-radius: 14px;
            margin-bottom: 18px;
            line-height: 1.45;
        }

        .notice.error {
            background: #fff0f0;
            color: #b42318;
            border: 1px solid #ffd0d0;
        }

        .notice.success {
            background: #ecfdf3;
            color: #067647;
            border: 1px solid #b7ebc6;
        }

        .auth-form { display: none; }
        .auth-form.active { display: block; animation: fadeIn 0.2s ease; }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(8px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .form-group {
            margin-bottom: 16px;
        }

        .form-group.full {
            grid-column: 1 / -1;
        }

        label {
            display: block;
            margin-bottom: 8px;
            color: #273246;
            font-weight: 700;
            font-size: 14px;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap > i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #7b8798;
        }

        input, select {
            width: 100%;
            min-height: 50px;
            padding: 13px 15px 13px 44px;
            border: 1px solid #d7dde6;
            border-radius: 12px;
            background: white;
            color: #172033;
            font-size: 15px;
            transition: 0.2s ease;
        }

        select {
            padding-left: 16px;
            cursor: pointer;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #4361ee;
            box-shadow: 0 0 0 4px rgba(67, 97, 238, 0.12);
        }

        .toggle-password {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            border: 0;
            background: transparent;
            color: #7b8798;
            cursor: pointer;
            padding: 6px;
        }

        .submit-btn {
            width: 100%;
            min-height: 52px;
            border: 0;
            border-radius: 13px;
            background: linear-gradient(135deg, #006837, #4361ee);
            color: white;
            font-size: 16px;
            font-weight: 800;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            transition: 0.2s ease;
            margin-top: 4px;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 14px 28px rgba(0, 104, 55, 0.22);
        }

        .helper-row {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            color: #68748a;
            font-size: 13px;
            margin-top: 14px;
        }

        .helper-row button {
            border: 0;
            background: transparent;
            color: #006837;
            font-weight: 800;
            cursor: pointer;
        }

        .hint {
            margin-top: 14px;
            color: #68748a;
            font-size: 13px;
            line-height: 1.5;
        }

        @media (max-width: 860px) {
            .auth-shell { grid-template-columns: 1fr; }
            .brand-panel { min-height: auto; padding: 34px; }
        }

        @media (max-width: 560px) {
            body { padding: 12px; }
            .form-panel { padding: 24px; }
            .form-grid { grid-template-columns: 1fr; }
            .tabs { grid-template-columns: 1fr; }
            .panel-header h2 { font-size: 26px; }
        }
    </style>
</head>
<body>
    <main class="auth-shell">
        <section class="brand-panel">
            <div>
                <div class="brand-mark"><i class="fas fa-id-card"></i></div>
                <h1>UTHM RFID Attendance</h1>
                <p>Create an account, sign in by role, and continue directly to the correct dashboard for admin, lecturer, or student.</p>
                <div class="feature-list">
                    <div class="feature-item"><i class="fas fa-route"></i><span>Role-based dashboard redirect</span></div>
                    <div class="feature-item"><i class="fas fa-database"></i><span>Accounts are saved into the database</span></div>
                    <div class="feature-item"><i class="fas fa-key"></i><span>Local password reset for testing</span></div>
                </div>
            </div>
            <p>For testing, newly registered accounts are activated immediately.</p>
        </section>

        <section class="form-panel">
            <div class="panel-header">
                <h2>System Access</h2>
                <p>Use one form to login, create a new user account, or reset a password.</p>
            </div>

            <div class="tabs" role="tablist">
                <button type="button" class="tab-btn" data-panel="login">Login</button>
                <button type="button" class="tab-btn" data-panel="register">Create Account</button>
                <button type="button" class="tab-btn" data-panel="forgot">Forgot Password</button>
            </div>

            <?php if ($message !== ''): ?>
                <div class="notice <?= htmlspecialchars($messageType) ?>">
                    <i class="fas <?= $messageType === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                    <span><?= htmlspecialchars($message) ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" id="loginPanel" method="POST">
                <input type="hidden" name="form_action" value="login">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="email" name="email" value="<?= htmlspecialchars($email) ?>" placeholder="email@uthm.edu.my" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="toggle-password" data-target="password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-sign-in-alt"></i> Login to Dashboard</button>
                <div class="helper-row">
                    <span>No account yet?</span>
                    <button type="button" data-panel-link="register">Create one</button>
                </div>
            </form>

            <form class="auth-form" id="registerPanel" method="POST">
                <input type="hidden" name="form_action" value="register">
                <div class="form-grid">
                    <div class="form-group">
                        <label for="matric_no">Matric / Staff ID</label>
                        <div class="input-wrap">
                            <i class="fas fa-id-badge"></i>
                            <input type="text" id="matric_no" name="matric_no" placeholder="DI230078" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="role">Account Type</label>
                        <select id="role" name="role" required>
                            <option value="student">Student</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    <div class="form-group full">
                        <label for="full_name">Full Name</label>
                        <div class="input-wrap">
                            <i class="fas fa-user"></i>
                            <input type="text" id="full_name" name="full_name" placeholder="Nur Alya Nadhirah" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="register_email">Email</label>
                        <div class="input-wrap">
                            <i class="fas fa-envelope"></i>
                            <input type="email" id="register_email" name="register_email" placeholder="email@uthm.edu.my" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone</label>
                        <div class="input-wrap">
                            <i class="fas fa-phone"></i>
                            <input type="tel" id="phone" name="phone" placeholder="+60 12-345 6789">
                        </div>
                    </div>
                    <div class="form-group full">
                        <label for="department">Course</label>
                        <select id="role" name="role" required>
                            <option value="student">BIW</option>
                            <option value="lecturer">BIM</option>
                            <option value="admin">BIP</option>
                            <option value="admin">BIT</option>
                            <option value="admin">BIS</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="register_password">Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="register_password" name="register_password" placeholder="At least 6 characters" required>
                            <button type="button" class="toggle-password" data-target="register_password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <div class="input-wrap">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="confirm_password" name="confirm_password" placeholder="Repeat password" required>
                            <button type="button" class="toggle-password" data-target="confirm_password"><i class="fas fa-eye"></i></button>
                        </div>
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-user-plus"></i> Create Account</button>
                <p class="hint">The new account will be saved in the `users` table and opened immediately based on the selected role.</p>
            </form>

            <form class="auth-form" id="forgotPanel" method="POST">
                <input type="hidden" name="form_action" value="forgot">
                <div class="form-group">
                    <label for="reset_email">Account Email</label>
                    <div class="input-wrap">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="reset_email" name="reset_email" placeholder="email@uthm.edu.my" required>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reset_password">New Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-key"></i>
                        <input type="password" id="reset_password" name="reset_password" placeholder="At least 6 characters" required>
                        <button type="button" class="toggle-password" data-target="reset_password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <div class="form-group">
                    <label for="reset_confirm_password">Confirm New Password</label>
                    <div class="input-wrap">
                        <i class="fas fa-key"></i>
                        <input type="password" id="reset_confirm_password" name="reset_confirm_password" placeholder="Repeat new password" required>
                        <button type="button" class="toggle-password" data-target="reset_confirm_password"><i class="fas fa-eye"></i></button>
                    </div>
                </div>
                <button type="submit" class="submit-btn"><i class="fas fa-rotate-right"></i> Reset Password</button>
                <p class="hint">This local reset is for project testing. A production system should send a secure email reset link.</p>
            </form>
        </section>
    </main>

    <script>
        const initialPanel = <?= json_encode(in_array($activePanel, ['login', 'register', 'forgot'], true) ? $activePanel : 'login') ?>;

        function showPanel(panel) {
            document.querySelectorAll('.auth-form').forEach(form => form.classList.remove('active'));
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));

            document.getElementById(`${panel}Panel`).classList.add('active');
            document.querySelector(`[data-panel="${panel}"]`).classList.add('active');
        }

        document.querySelectorAll('[data-panel], [data-panel-link]').forEach(button => {
            button.addEventListener('click', () => {
                showPanel(button.dataset.panel || button.dataset.panelLink);
            });
        });

        document.querySelectorAll('.toggle-password').forEach(button => {
            button.addEventListener('click', () => {
                const input = document.getElementById(button.dataset.target);
                const icon = button.querySelector('i');
                input.type = input.type === 'password' ? 'text' : 'password';
                icon.className = input.type === 'password' ? 'fas fa-eye' : 'fas fa-eye-slash';
            });
        });

        showPanel(initialPanel);
    </script>
</body>
</html>
