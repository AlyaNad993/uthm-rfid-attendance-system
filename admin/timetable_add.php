<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

$error = "";

$courses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC");
$labs    = $conn->query("SELECT id, lab_code, lab_name, floor_no FROM labs ORDER BY floor_no ASC, lab_code ASC");

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $course_id = (int)($_POST['course_id'] ?? 0);
  $lab_id    = (int)($_POST['lab_id'] ?? 0);
  $lecturer  = trim($_POST['lecturer_name'] ?? '');
  $day       = $_POST['day_of_week'] ?? '';
  $start     = $_POST['start_time'] ?? '';
  $end       = $_POST['end_time'] ?? '';
  $grace     = (int)($_POST['grace_minutes'] ?? 10);

  if(!$course_id || !$lab_id || $lecturer==="" || $day==="" || $start==="" || $end==="") {
    $error = "Please fill all fields.";
  } else {
    $stmt = $conn->prepare("INSERT INTO schedules(course_id,lab_id,lecturer_name,day_of_week,start_time,end_time,grace_minutes,is_active) VALUES (?,?,?,?,?,?,?,1)");
    $stmt->bind_param("iissssii", $course_id, $lab_id, $lecturer, $day, $start, $end, $grace);
    if($stmt->execute()){
      header("Location: timetable.php");
      exit;
    } else {
      $error = $stmt->error;
    }
  }
}
?>
<!DOCTYPE html>
<html>
<head>
  <title>Add Schedule</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">
<div class="layout">
  <?php include '../includes/sidebar.php'; ?>
  <div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-header">
      <div>
        <h1>Add Schedule</h1>
        <p>Create timetable for lab and course</p>
      </div>
      <a class="btn-outline" href="timetable.php">Back</a>
    </div>

    <div class="card" style="max-width:900px;">
      <?php if($error): ?>
        <div style="background:#fee2e2;color:#991b1b;padding:10px 12px;border-radius:10px;margin-bottom:12px;">
          <?= htmlspecialchars($error) ?>
        </div>
      <?php endif; ?>

      <form method="POST" class="add-user-form">
        <div class="form-group">
          <label>Course</label>
          <select name="course_id" required>
            <option value="">Select course</option>
            <?php while($c=$courses->fetch_assoc()): ?>
              <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c['course_code'].' - '.$c['course_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Lab</label>
          <select name="lab_id" required>
            <option value="">Select lab</option>
            <?php while($l=$labs->fetch_assoc()): ?>
              <option value="<?= $l['id'] ?>"><?= htmlspecialchars('Floor '.$l['floor_no'].' • '.$l['lab_code'].' - '.$l['lab_name']) ?></option>
            <?php endwhile; ?>
          </select>
        </div>

        <div class="form-group">
          <label>Lecturer Name</label>
          <input name="lecturer_name" type="text" placeholder="e.g. Dr. Nurul Aswa" required>
        </div>

        <div class="form-group">
          <label>Day</label>
          <select name="day_of_week" required>
            <option>Monday</option><option>Tuesday</option><option>Wednesday</option>
            <option>Thursday</option><option>Friday</option><option>Saturday</option><option>Sunday</option>
          </select>
        </div>

        <div class="form-group">
          <label>Start Time</label>
          <input name="start_time" type="time" required>
        </div>

        <div class="form-group">
          <label>End Time</label>
          <input name="end_time" type="time" required>
        </div>

        <div class="form-group">
          <label>Grace Minutes</label>
          <input name="grace_minutes" type="number" min="0" max="60" value="10">
        </div>

        <div class="form-actions">
          <a class="btn-outline" href="timetable.php">Cancel</a>
          <button class="btn-primary" type="submit">Save Schedule</button>
        </div>
      </form>
    </div>

  </div>
</div>
</body>
</html>
