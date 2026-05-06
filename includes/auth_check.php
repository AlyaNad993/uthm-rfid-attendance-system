<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check login
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: ../login.php");
    exit();
}

// Session timeout (30 minutes)
$timeout_duration = 1800;
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
    session_unset();
    session_destroy();
    header("Location: ../login.php?error=session_expired");
    exit();
}

$_SESSION['last_activity'] = time();

// Role guards
function requireAdmin() {
    if ($_SESSION['role'] !== 'admin') {
        header("Location: ../unauthorized.php");
        exit();
    }
}

function requireLecturer() {
    if ($_SESSION['role'] !== 'lecturer') {
        header("Location: ../unauthorized.php");
        exit();
    }
}

function requireLecturerOrAdmin() {
    if (!in_array($_SESSION['role'], ['lecturer', 'admin'])) {
        header("Location: ../unauthorized.php");
        exit();
    }
}
