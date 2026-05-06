<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';

$selected_course = $_GET['course_id'] ?? '';
$selected_lab    = $_GET['lab_id'] ?? '';

$courses = $conn->query("SELECT id, course_code, course_name FROM courses ORDER BY course_code ASC");
$labs    = $conn->query("SELECT id, lab_code, lab_name, floor_no FROM labs ORDER BY floor_no ASC, lab_code ASC");

$where = "1=1";
$params = [];
$types = "";

if ($selected_course !== '') { $where .= " AND s.course_id=?"; $params[] = $selected_course; $types .= "i"; }
if ($selected_lab !== '')    { $where .= " AND s.lab_id=?";    $params[] = $selected_lab;    $types .= "i"; }

$sql = "
  SELECT s.*, c.course_code, c.course_name, l.lab_code, l.lab_name, l.floor_no
  FROM schedules s
  JOIN courses c ON s.course_id=c.id
  JOIN labs l ON s.lab_id=l.id
  WHERE $where
  ORDER BY FIELD(s.day_of_week,'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'),
           s.start_time ASC
";

$stmt = $conn->prepare($sql);
if (!empty($params)) $stmt->bind_param($types, ...$params);
$stmt->execute();
$rows = $stmt->get_result();
?>
<!DOCTYPE html>
<html>
<head>
  <title>Courses & Timetable</title>
  <link rel="stylesheet" href="../assets/css/dashboard.css">
</head>
<body class="dashboard-body">
<div class="layout">

  <?php include '../includes/sidebar.php'; ?>

  <div class="main">
    <?php include '../includes/topbar.php'; ?>

    <div class="page-header">
      <div>
        <h1>Courses & Timetable</h1>
        <p>Manage courses, lab schedules, and attendance rules (grace minutes)</p>
      </div>
      <a class="btn-primary" href="timetable_add.php">+ Add Schedule</a>
    </div>

    <div class="card filter-bar">
      <form method="GET" style="display:flex;gap:12px;flex-wrap:wrap;align-items:center;">
        <select class="dash-input" name="course_id" style="min-width:260px;">
          <option value="">All Courses</option>
          <?php while($c = $courses->fetch_assoc()): ?>
            <option value="<?= $c['id'] ?>" <?= ($selected_course==$c['id'])?'selected':'' ?>>
              <?= htmlspecialchars($c['course_code'].' - '.$c['course_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <select class="dash-input" name="lab_id" style="min-width:260px;">
          <option value="">All Labs</option>
          <?php while($l = $labs->fetch_assoc()): ?>
            <option value="<?= $l['id'] ?>" <?= ($selected_lab==$l['id'])?'selected':'' ?>>
              <?= htmlspecialchars('Floor '.$l['floor_no'].' • '.$l['lab_code'].' - '.$l['lab_name']) ?>
            </option>
          <?php endwhile; ?>
        </select>

        <button class="btn-outline" type="submit">Filter</button>
        <a class="btn-outline" href="timetable.php">Reset</a>
      </form>
    </div>

    <div class="card">
      <h3 style="margin-bottom:12px;">Course & Lab Timetable</h3>

      <table class="table">
        <thead>
          <tr>
            <th>Course</th>
            <th>Day</th>
            <th>Time</th>
            <th>Lab</th>
            <th>Floor</th>
            <th>Lecturer</th>
            <th>Grace (min)</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if($rows->num_rows===0): ?>
            <tr><td colspan="8" style="padding:18px;color:#64748b;">No schedules found.</td></tr>
          <?php endif; ?>

          <?php while($r = $rows->fetch_assoc()): ?>
            <tr>
              <td><?= htmlspecialchars($r['course_code'].' - '.$r['course_name']) ?></td>
              <td><?= htmlspecialchars($r['day_of_week']) ?></td>
              <td><?= substr($r['start_time'],0,5) ?> - <?= substr($r['end_time'],0,5) ?></td>
              <td><?= htmlspecialchars($r['lab_code']) ?></td>
              <td><?= (int)$r['floor_no'] ?></td>
              <td><?= htmlspecialchars($r['lecturer_name']) ?></td>
              <td><?= (int)$r['grace_minutes'] ?></td>
              <td>
                <?php if((int)$r['is_active']===1): ?>
                  <span class="status-pill status-online">Active</span>
                <?php else: ?>
                  <span class="status-pill status-absent">Inactive</span>
                <?php endif; ?>
              </td>
            </tr>
          <?php endwhile; ?>
        </tbody>
      </table>

    </div>

  </div>
</div>
</body>
</html>
