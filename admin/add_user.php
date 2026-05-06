<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $conn->prepare(
    "INSERT INTO users (fullname, matric, email, role) VALUES (?,?,?,?)"
  );
  $stmt->bind_param(
    "ssss",
    $_POST['fullname'],
    $_POST['matric'],
    $_POST['email'],
    $_POST['role']
  );
  $stmt->execute();
  header("Location: users.php");
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add User</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">

<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main">
<?php include '../includes/topbar.php'; ?>

<div class="card">
<h2>Add User</h2>

<form method="POST" class="form-grid">
<input name="fullname" placeholder="Full Name" required>
<input name="matric" placeholder="Matric / Staff ID" required>
<input name="email" type="email" placeholder="Email" required>

<select name="role">
  <option value="student">Student</option>
  <option value="lecturer">Lecturer</option>
</select>

<button class="btn-primary">Save</button>
</form>
</div>

</div>
</div>

</body>
</html>
