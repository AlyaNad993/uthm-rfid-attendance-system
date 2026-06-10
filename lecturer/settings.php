<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];
$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $phone = trim($_POST['phone'] ?? '');
    $department = trim($_POST['department'] ?? '');
    $faculty = trim($_POST['faculty'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    try {
        if ($newPassword !== '') {
            if ($newPassword !== $confirmPassword) {
                $error = 'New password and confirmation do not match.';
            } elseif (strlen($newPassword) < 6) {
                $error = 'New password must be at least 6 characters.';
            } else {
                $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE user_id = ? LIMIT 1");
                $stmt->execute([$lecturerId]);
                $currentHash = $stmt->fetchColumn();

                if (!$currentHash || !password_verify($currentPassword, $currentHash)) {
                    $error = 'Current password is incorrect.';
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET phone = ?, department = ?, faculty = ?, password_hash = ?
                        WHERE user_id = ?
                    ");
                    $stmt->execute([$phone, $department, $faculty, password_hash($newPassword, PASSWORD_DEFAULT), $lecturerId]);
                    $message = 'Settings and password updated successfully.';
                }
            }
        }

        if (!$error && $newPassword === '') {
            $stmt = $pdo->prepare("
                UPDATE users
                SET phone = ?, department = ?, faculty = ?
                WHERE user_id = ?
            ");
            $stmt->execute([$phone, $department, $faculty, $lecturerId]);
            $message = 'Settings updated successfully.';
        }
    } catch (PDOException $e) {
        $error = 'Unable to update settings: ' . $e->getMessage();
    }
}

$stmt = $pdo->prepare("
    SELECT user_id, matric_no, full_name, email, phone, role, department, faculty
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$lecturerId]);
$lecturer = $stmt->fetch();

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lecturer Settings</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f4f7fb;
            color: #1f2937;
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 24px;
        }
        .page { max-width: 860px; margin: 0 auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        h1 { font-size: 30px; line-height: 1.2; color: #111827; }
        .muted { color: #64748b; margin-top: 6px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            padding: 22px;
        }
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 18px;
        }
        .full { grid-column: 1 / -1; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: 800;
        }
        input,
        select {
            width: 100%;
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            color: #111827;
            font-size: 15px;
            background: #fff;
        }
        input[readonly] { background: #f8fafc; color: #64748b; }
        .section-title {
            margin-top: 10px;
            padding-top: 18px;
            border-top: 1px solid #e2e8f0;
            color: #111827;
            font-size: 17px;
            font-weight: 850;
        }
        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            font-weight: 700;
        }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 720px) {
            body { padding: 16px; }
            .topbar,
            .form-grid { display: block; }
            .actions { margin-top: 14px; }
            .form-grid > div { margin-bottom: 18px; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(0, 104, 55, 0.16), transparent 30%),
                radial-gradient(circle at 92% 10%, rgba(67, 97, 238, 0.14), transparent 28%),
                linear-gradient(135deg, #f7fbff 0%, #edf8f3 100%) !important;
        }
        .page { max-width: 980px; }
        .topbar,
        .card {
            border: 1px solid #dce6f2 !important;
            border-radius: 18px !important;
            box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12) !important;
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(250,253,252,.94)) !important;
        }
        .topbar {
            padding: 26px 28px;
            align-items: center;
        }
        .card { padding: 28px; }
        .btn {
            min-height: 46px;
            border-radius: 12px !important;
            font-weight: 800;
        }
        .btn-primary {
            background: linear-gradient(135deg, #006837, #4361ee) !important;
        }
        input,
        select {
            min-height: 48px;
            border-radius: 12px !important;
            border-color: #d8e0ef !important;
        }
        label {
            color: #27364c !important;
        }
        .section-title {
            margin-top: 18px;
            padding-top: 22px;
            border-top: 1px solid #dce6f2;
            color: #172033 !important;
        }
        .alert {
            border-radius: 12px !important;
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Settings</h1>
                <p class="muted">Manage your lecturer profile and password.</p>
            </div>
            <div class="actions">
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <section class="card">
            <?php if ($message): ?><div class="alert success"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert error"><?= e($error) ?></div><?php endif; ?>

            <form method="POST" class="form-grid">
                <div>
                    <label>Full Name</label>
                    <input value="<?= e($lecturer['full_name'] ?? '') ?>" readonly>
                </div>
                <div>
                    <label>Email</label>
                    <input value="<?= e($lecturer['email'] ?? '') ?>" readonly>
                </div>
                <div>
                    <label>Staff ID</label>
                    <input value="<?= e($lecturer['matric_no'] ?? '') ?>" readonly>
                </div>
                <div>
                    <label>Phone</label>
                    <input name="phone" value="<?= e($lecturer['phone'] ?? '') ?>" placeholder="Phone number">
                </div>
                <div>
                    <label>Department</label>
                    <input name="department" value="<?= e($lecturer['department'] ?? '') ?>" placeholder="Department">
                </div>
                <div>
                    <label>Faculty</label>
                    <select name="faculty">
                        <?php foreach (['FKEE', 'FKE', 'FKAAS', 'FKMP', 'FTK', 'FPTV', 'OTHER'] as $faculty): ?>
                            <option value="<?= e($faculty) ?>" <?= ($lecturer['faculty'] ?? '') === $faculty ? 'selected' : '' ?>>
                                <?= e($faculty) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="full section-title">Change Password</div>
                <div>
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
                    <button class="btn btn-primary" type="submit">Save Settings</button>
                    <a class="btn" href="dashboard.php">Cancel</a>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
