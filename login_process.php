<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: login.php');
    exit();
}

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header('Location: login.php?panel=login');
    exit();
}

$stmt = $pdo->prepare("
    SELECT user_id, full_name, email, password_hash, role, is_active
    FROM users
    WHERE email = ?
    LIMIT 1
");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user || !password_verify($password, $user['password_hash']) || (int)$user['is_active'] !== 1) {
    header('Location: login.php?panel=login');
    exit();
}

$_SESSION['user_id'] = $user['user_id'];
$_SESSION['email'] = $user['email'];
$_SESSION['fullname'] = $user['full_name'];
$_SESSION['role'] = $user['role'];
$_SESSION['last_activity'] = time();

if ($user['role'] === 'admin') {
    header('Location: admin/dashboard.php');
} elseif ($user['role'] === 'lecturer') {
    header('Location: lecturer/dashboard.php');
} elseif ($user['role'] === 'student') {
    header('Location: student/dashboard.php');
} else {
    session_destroy();
    header('Location: unauthorized.php');
}
exit();
