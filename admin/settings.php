<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

$adminId = (int)($_SESSION['user_id'] ?? 0);
$message = '';
$error = '';

$pdo->exec("
    CREATE TABLE IF NOT EXISTS system_settings (
        setting_key VARCHAR(80) PRIMARY KEY,
        setting_value VARCHAR(255) NOT NULL,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )
");

$defaults = [
    'attendance_method' => 'rfid',
    'late_grace_minutes' => '15',
    'auto_absent_enabled' => '1',
    'warning_threshold' => '80',
    'academic_year' => '2025/2026',
    'current_semester' => 'Semester 2, 2026',
];

$insertSetting = $pdo->prepare("
    INSERT IGNORE INTO system_settings (setting_key, setting_value)
    VALUES (?, ?)
");
foreach ($defaults as $key => $value) {
    $insertSetting->execute([$key, $value]);
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function settingsValue(PDO $pdo, string $key, string $fallback = ''): string {
    $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $value = $stmt->fetchColumn();
    return $value === false ? $fallback : (string)$value;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'profile';

    try {
        if ($action === 'profile') {
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $stmt = $pdo->prepare("SELECT phone, department FROM users WHERE user_id = ? LIMIT 1");
            $stmt->execute([$adminId]);
            $currentAdminMeta = $stmt->fetch() ?: [];
            $phone = trim($_POST['phone'] ?? ($currentAdminMeta['phone'] ?? ''));
            $department = trim($_POST['department'] ?? ($currentAdminMeta['department'] ?? ''));
            $currentPassword = $_POST['current_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($fullName === '' || $email === '') {
                throw new RuntimeException('Please fill in admin name and email.');
            }

            if ($newPassword !== '') {
                if ($newPassword !== $confirmPassword) {
                    throw new RuntimeException('New password and confirmation do not match.');
                }

                if (strlen($newPassword) < 6) {
                    throw new RuntimeException('New password must be at least 6 characters.');
                }

                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$adminId]);
                $currentHash = $stmt->fetchColumn();

                if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
                    throw new RuntimeException('Current password is incorrect.');
                }

                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, phone = ?, department = ?, password_hash = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $department, password_hash($newPassword, PASSWORD_DEFAULT), $adminId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, phone = ?, department = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $department, $adminId]);
            }

            $message = 'Admin profile updated successfully.';
        }

        if ($action === 'system') {
            $attendanceMethod = $_POST['attendance_method'] ?? 'rfid';
            $lateGrace = max(0, min(60, (int)($_POST['late_grace_minutes'] ?? 15)));
            $autoAbsent = isset($_POST['auto_absent_enabled']) ? '1' : '0';
            $warningThreshold = max(1, min(100, (int)($_POST['warning_threshold'] ?? 80)));
            $academicYear = trim($_POST['academic_year'] ?? '2025/2026');
            $currentSemester = trim($_POST['current_semester'] ?? 'Semester 2, 2026');

            if (!in_array($attendanceMethod, ['rfid', 'qr', 'both'], true)) {
                $attendanceMethod = 'rfid';
            }

            $settings = [
                'attendance_method' => $attendanceMethod,
                'late_grace_minutes' => (string)$lateGrace,
                'auto_absent_enabled' => $autoAbsent,
                'warning_threshold' => (string)$warningThreshold,
                'academic_year' => $academicYear,
                'current_semester' => $currentSemester,
            ];

            $stmt = $pdo->prepare("
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES (?, ?)
                ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)
            ");
            foreach ($settings as $key => $value) {
                $stmt->execute([$key, $value]);
            }

            $message = 'System settings updated successfully.';
        }
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT user_id, matric_no, full_name, email, phone, department, role
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$adminId]);
$admin = $stmt->fetch();

$attendanceMethod = settingsValue($pdo, 'attendance_method', 'rfid');
$lateGraceMinutes = settingsValue($pdo, 'late_grace_minutes', '15');
$autoAbsentEnabled = settingsValue($pdo, 'auto_absent_enabled', '1');
$warningThreshold = settingsValue($pdo, 'warning_threshold', '80');
$academicYear = settingsValue($pdo, 'academic_year', '2025/2026');
$currentSemester = settingsValue($pdo, 'current_semester', 'Semester 2, 2026');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Settings | RFID IoT Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", system-ui, sans-serif; }
        body { min-height: 100vh; background: radial-gradient(circle at top left, rgba(67, 97, 238, 0.16), transparent 30%), radial-gradient(circle at bottom right, rgba(0, 104, 55, 0.14), transparent 28%), #f6f9fc; color: #172033; padding: 28px; }
        .page { max-width: 1180px; margin: 0 auto; }
        .topbar { position: relative; display: flex; align-items: flex-start; justify-content: space-between; gap: 16px; margin-bottom: 22px; padding: 24px 26px; border: 1px solid #dce4ef; border-radius: 18px; background: rgba(255,255,255,0.88); box-shadow: 0 16px 40px rgba(28,52,84,.1); overflow: hidden; }
        .topbar::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #006837, #0e8a83, #4361ee); }
        h1 { font-size: clamp(30px, 4vw, 44px); line-height: 1.1; letter-spacing: 0; }
        .muted { color: #66738a; line-height: 1.6; margin-top: 6px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { min-height: 42px; padding: 0 16px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; color: #172033; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; cursor: pointer; transition: .2s ease; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(28,52,84,.08); }
        .btn-primary { background: linear-gradient(135deg, #006837, #4361ee); color: #fff; border-color: transparent; }
        .grid { display: grid; grid-template-columns: minmax(0, .9fr) minmax(0, 1.1fr); gap: 20px; align-items: start; }
        .stack { display: grid; gap: 20px; }
        .card { background: rgba(255,255,255,0.94); border: 1px solid #dce4ef; border-radius: 18px; padding: 24px; box-shadow: 0 16px 38px rgba(28,52,84,.1); }
        .card h2 { margin-bottom: 6px; font-size: 24px; }
        .card-subtitle { color: #66738a; margin-bottom: 18px; line-height: 1.5; }
        .form-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        .full { grid-column: 1 / -1; }
        label { display: block; margin-bottom: 8px; color: #334155; font-size: 14px; font-weight: 850; }
        input, select { width: 100%; min-height: 46px; border: 1px solid #cbd5e1; border-radius: 12px; padding: 0 13px; color: #111827; font-size: 15px; background: #fff; }
        input:focus, select:focus { outline: 3px solid rgba(67,97,238,.12); border-color: #4361ee; }
        input[readonly] { background: #f8fafc; color: #64748b; }
        .check-row { display: flex; align-items: center; gap: 10px; min-height: 43px; }
        .check-row input { width: auto; min-height: auto; }
        .profile-mini { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; padding: 14px; border-radius: 16px; background: linear-gradient(135deg, rgba(0,104,55,.08), rgba(67,97,238,.08)); border: 1px solid #e2e8f0; }
        .avatar { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; color: #fff; font-weight: 900; background: linear-gradient(135deg, #006837, #4361ee); box-shadow: 0 10px 22px rgba(67,97,238,.18); }
        .meta-title { font-weight: 900; color: #111827; }
        .meta-sub { color: #66738a; font-size: 13px; margin-top: 3px; }
        .alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 10px; font-weight: 800; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 900px) { body { padding: 18px; } .topbar, .grid { display: block; } .card { margin-bottom: 18px; } .form-grid { grid-template-columns: 1fr; } .actions { margin-top: 12px; } }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Admin Settings</h1>
                <p class="muted">Manage your admin account and the attendance defaults used by new sessions.</p>
            </div>
            <div class="actions">
                <a class="btn" href="dashboard.php"><i class="fas fa-arrow-left"></i> Dashboard</a>
            </div>
        </div>

        <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
        <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

        <section class="grid">
            <div class="stack">
                <div class="card">
                    <h2>Admin Profile</h2>
                    <p class="card-subtitle">Keep the account identity simple and clear.</p>
                    <div class="profile-mini">
                        <div class="avatar">AU</div>
                        <div>
                            <div class="meta-title"><?= e($admin['full_name'] ?? 'Admin') ?></div>
                            <div class="meta-sub"><?= e($admin['email'] ?? '') ?></div>
                        </div>
                    </div>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="action" value="profile">
                        <div class="full">
                            <label>Full Name</label>
                            <input name="full_name" value="<?= e($admin['full_name'] ?? '') ?>" required>
                        </div>
                        <div class="full">
                            <label>Email</label>
                            <input name="email" type="email" value="<?= e($admin['email'] ?? '') ?>" required>
                        </div>
                        <div class="full">
                            <label>Admin ID</label>
                            <input value="<?= e($admin['matric_no'] ?? '') ?>" readonly>
                        </div>
                        <div class="full actions">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Profile</button>
                        </div>
                    </form>
                </div>

                <div class="card">
                    <h2>Password</h2>
                    <p class="card-subtitle">Leave these fields empty if you do not want to change the password.</p>
                    <form method="POST" class="form-grid">
                        <input type="hidden" name="action" value="profile">
                        <input type="hidden" name="full_name" value="<?= e($admin['full_name'] ?? '') ?>">
                        <input type="hidden" name="email" value="<?= e($admin['email'] ?? '') ?>">
                        <div class="full">
                            <label>Current Password</label>
                            <input name="current_password" type="password" autocomplete="current-password">
                        </div>
                        <div>
                            <label>New Password</label>
                            <input name="new_password" type="password" autocomplete="new-password">
                        </div>
                        <div>
                            <label>Confirm New Password</label>
                            <input name="confirm_password" type="password" autocomplete="new-password">
                        </div>
                        <div class="full actions">
                            <button class="btn btn-primary" type="submit"><i class="fas fa-key"></i> Update Password</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <h2>Attendance Defaults</h2>
                <p class="card-subtitle">These values are used as defaults when lecturers create attendance sessions.</p>
                <form method="POST" class="form-grid">
                    <input type="hidden" name="action" value="system">
                    <div>
                        <label>Default Attendance Method</label>
                        <select name="attendance_method">
                            <option value="rfid" <?= $attendanceMethod === 'rfid' ? 'selected' : '' ?>>RFID</option>
                            <option value="qr" <?= $attendanceMethod === 'qr' ? 'selected' : '' ?>>QR Code</option>
                            <option value="both" <?= $attendanceMethod === 'both' ? 'selected' : '' ?>>RFID + QR Code</option>
                        </select>
                    </div>
                    <div>
                        <label>Late Grace Minutes</label>
                        <input name="late_grace_minutes" type="number" min="0" max="60" value="<?= e($lateGraceMinutes) ?>">
                    </div>
                    <div>
                        <label>Warning Threshold (%)</label>
                        <input name="warning_threshold" type="number" min="1" max="100" value="<?= e($warningThreshold) ?>">
                    </div>
                    <div>
                        <label>Auto Mark Absent</label>
                        <label class="check-row">
                            <input name="auto_absent_enabled" type="checkbox" <?= $autoAbsentEnabled === '1' ? 'checked' : '' ?>>
                            Enable after session ends
                        </label>
                    </div>
                    <div>
                        <label>Academic Year</label>
                        <input name="academic_year" value="<?= e($academicYear) ?>">
                    </div>
                    <div>
                        <label>Current Semester</label>
                        <input name="current_semester" value="<?= e($currentSemester) ?>">
                    </div>
                    <div class="full actions">
                        <button class="btn btn-primary" type="submit"><i class="fas fa-save"></i> Save Defaults</button>
                    </div>
                </form>
            </div>
        </section>
    </main>
</body>
</html>
