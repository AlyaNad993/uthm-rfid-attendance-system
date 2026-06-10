<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? '';

$dashboard = 'login.php';
if ($role === 'admin') {
    $dashboard = 'admin/dashboard.php';
} elseif ($role === 'lecturer') {
    $dashboard = 'lecturer/dashboard.php';
} elseif ($role === 'student') {
    $dashboard = 'student/dashboard.php';
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Unauthorized Access</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { min-height: 100vh; margin: 0; display: grid; place-items: center; background: #f4f7fb; color: #111827; padding: 20px; }
        .card { width: min(460px, 100%); background: #fff; border: 1px solid #e2e8f0; border-radius: 14px; padding: 28px; box-shadow: 0 18px 40px rgba(15, 23, 42, 0.12); text-align: center; }
        h1 { margin: 0 0 10px; font-size: 28px; }
        p { margin: 0 0 20px; color: #64748b; line-height: 1.5; }
        .btn { display: inline-flex; align-items: center; justify-content: center; min-height: 42px; padding: 0 16px; border-radius: 10px; background: #2563eb; color: #fff; font-weight: 800; text-decoration: none; }
    </style>
</head>
<body>
    <main class="card">
        <h1>Unauthorized Access</h1>
        <p>You do not have permission to open this page with your current account.</p>
        <a class="btn" href="<?= e($dashboard) ?>"><?= $role ? 'Back to Dashboard' : 'Back to Login' ?></a>
    </main>
</body>
</html>
