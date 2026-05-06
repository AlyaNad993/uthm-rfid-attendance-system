<?php
session_start();
require_once 'includes/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: login.php");
    exit;
}

$email    = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    header("Location: login.php?error=empty");
    exit;
}

$stmt = $conn->prepare(
    "SELECT user_id, full_name, email, password_hash, role 
     FROM users 
     WHERE email = ? AND is_active = 1 
     LIMIT 1"
);

$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();

if ($user && password_verify($password, $user['password_hash'])) {

    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['name']    = $user['full_name'];

    if ($user['role'] === 'admin') {
        header("Location: admin/dashboard.php");
    } elseif ($user['role'] === 'lecturer') {
        header("Location: lecturer/dashboard.php");
    } elseif ($user['role'] === 'student') {
        header("Location: student/dashboard.php");
    } else {
        // fallback safety
        session_destroy();
        header("Location: login.php?error=role");
    }
    exit;

} else {
    header("Location: login.php?error=invalid");
    exit;
}
