<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

$user_id = $_GET['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $stmt = $conn->prepare(
    "INSERT INTO rfid_cards (uid, user_id) VALUES (?,?)"
  );
  $stmt->bind_param("si", $_POST['uid'], $user_id);
  $stmt->execute();
  header("Location: users.php");
  exit;
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Assign RFID</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">

<div class="layout">
<?php include '../includes/sidebar.php'; ?>
<div class="main">
<?php include '../includes/topbar.php'; ?>

<div class="card">
<h2>Assign RFID Card</h2>

<form method="POST">
  <input name="uid" placeholder="Scan RFID UID" required>
  <button class="btn-primary">Assign</button>
</form>

<p style="color:#64748b;margin-top:10px;">
  (ESP32 will auto-fill this later)
</p>

</div>
</div>
</div>

</body>
</html>
