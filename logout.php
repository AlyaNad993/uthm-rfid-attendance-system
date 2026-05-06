<?php
// logout.php
session_start();

// Clear all session variables
unset($_SESSION['user_id']);
unset($_SESSION['email']);
unset($_SESSION['fullname']);
unset($_SESSION['role']);

// Destroy the session
session_destroy();

// Clear session cookie
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Redirect to login page
header("Location: login.php");
exit();
?>