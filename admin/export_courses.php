<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$scope = strtolower(trim($_GET['scope'] ?? 'all'));
$format = strtolower(trim($_GET['format'] ?? 'pdf'));
$lecturerId = (int)($_GET['lecturer_id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);

if (!in_array($scope, ['all', 'lecturer', 'subject'], true)) {
    $scope = 'all';
}

if (!in_array($format, ['pdf', 'csv'], true)) {
    $format = 'pdf';
}

$where = "c.is_active = 1";
$params = [];
$reportTitle = 'All Courses & Timetable';

if ($scope === 'lecturer' && $lecturerId > 0) {
    $where .= " AND cs.lecturer_id = ?";
    $params[] = $lecturerId;

    $lecturerStmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
    $lecturerStmt->execute([$lecturerId]);
    $lecturerName = $lecturerStmt->fetchColumn();
    $reportTitle = 'Courses & Timetable by Lecturer' . ($lecturerName ? ': ' . $lecturerName : '');
}

if ($scope === 'subject' && $courseId > 0) {
    $where .= " AND c.course_id = ?";
    $params[] = $courseId;

    $courseStmt = $pdo->prepare("SELECT CONCAT(course_code, ' - ', course_name) FROM courses WHERE course_id = ? LIMIT 1");
    $courseStmt->execute([$courseId]);
    $courseName = $courseStmt->fetchColumn();
    $reportTitle = 'Subject Timetable' . ($courseName ? ': ' . $courseName : '');
}

$stmt = $pdo->prepare("
    SELECT
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.semester,
        c.academic_year,
        c.department,
        cs.section_name,
        COALESCE(cs.semester_label, CONCAT('Semester ', c.semester, ' ', c.academic_year)) AS semester_label,
        cs.day_of_week,
        TIME_FORMAT(cs.start_time, '%H:%i') AS start_time,
        TIME_FORMAT(cs.end_time, '%H:%i') AS end_time,
        COALESCE(r.room_code, r.room_name, '-') AS room,
        COALESCE(u.full_name, '-') AS lecturer,
        COUNT(DISTINCT e.student_id) AS enrolled_students
    FROM courses c
    LEFT JOIN class_schedule cs
        ON cs.course_id = c.course_id
       AND cs.is_active = 1
    LEFT JOIN users u
        ON u.user_id = cs.lecturer_id
    LEFT JOIN rooms r
        ON r.room_id = cs.room_id
    LEFT JOIN enrollments e
        ON e.course_id = c.course_id
       AND e.section_name = cs.section_name
       AND COALESCE(e.academic_year, '') = COALESCE(cs.academic_year, '')
       AND e.status = 'registered'
    WHERE $where
    GROUP BY
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.semester,
        c.academic_year,
        c.department,
        cs.schedule_id,
        cs.section_name,
        cs.semester_label,
        cs.day_of_week,
        cs.start_time,
        cs.end_time,
        r.room_code,
        r.room_name,
        u.full_name
    ORDER BY
        c.course_code,
        FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
        cs.start_time
");
$stmt->execute($params);
$rows = $stmt->fetchAll();

$safeScope = preg_replace('/[^A-Za-z0-9_-]/', '_', $scope);
$date = date('Y-m-d');

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="courses_timetable_' . $safeScope . '_' . $date . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [$reportTitle]);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, [
        'Course Code',
        'Course Name',
        'Credit Hours',
        'Semester',
        'Academic Year',
        'Section',
        'Semester / Year',
        'Department',
        'Day',
        'Start Time',
        'End Time',
        'Room',
        'Lecturer',
        'Enrolled Students'
    ]);

    foreach ($rows as $row) {
        fputcsv($out, [
            $row['course_code'],
            $row['course_name'],
            $row['credit_hours'],
            $row['semester'],
            $row['academic_year'],
            $row['section_name'] ?: '-',
            $row['semester_label'] ?: '-',
            $row['department'],
            $row['day_of_week'] ?: '-',
            $row['start_time'] ?: '-',
            $row['end_time'] ?: '-',
            $row['room'],
            $row['lecturer'],
            (int)$row['enrolled_students'],
        ]);
    }

    fclose($out);
    exit;
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
    <title><?= e($reportTitle) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; font-family: "Segoe UI", Arial, sans-serif; color: #172033; background: radial-gradient(circle at top left, rgba(0, 104, 55, 0.12), transparent 30%), radial-gradient(circle at bottom right, rgba(67, 97, 238, 0.10), transparent 28%), #f5f8fc; }
        .page { position: relative; max-width: 1180px; margin: 0 auto; background: rgba(255,255,255,0.96); border: 1px solid #dce4ef; border-radius: 18px; padding: 30px; box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12); overflow: hidden; }
        .page::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #006837, #0e8a83, #4361ee); }
        .top { display: flex; justify-content: space-between; gap: 18px; margin-bottom: 24px; align-items: flex-start; }
        h1 { margin: 0 0 6px; font-size: 32px; letter-spacing: 0; color: #102033; }
        .muted { color: #66738a; }
        .actions { display: flex; gap: 10px; }
        .btn { min-height: 42px; padding: 0 16px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; color: #172033; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(28, 52, 84, 0.08); }
        .btn:last-child { background: linear-gradient(135deg, #006837, #4361ee); border-color: transparent; color: #fff; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 11px 12px; border: 1px solid #e7edf5; text-align: left; vertical-align: top; }
        th { background: linear-gradient(135deg, #edf8f1, #eef4ff); color: #24415c; text-transform: uppercase; font-size: 12px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .code { font-weight: 900; color: #006837; }
        @media print {
            body { background: #fff; padding: 0; }
            .page { max-width: none; border: 0; border-radius: 0; box-shadow: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="top">
            <div>
                <h1><?= e($reportTitle) ?></h1>
                <div class="muted">RFID IoT Attendance - Admin Console</div>
                <div class="muted">Generated: <?= e(date('Y-m-d H:i:s')) ?></div>
            </div>
            <div class="actions">
                <button class="btn" onclick="window.location.href='courses.php'">Back to Courses</button>
                <button class="btn" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Course Code</th>
                    <th>Course Name</th>
                    <th>Credit</th>
                    <th>Semester</th>
                    <th>Academic Year</th>
                    <th>Section</th>
                    <th>Semester / Year</th>
                    <th>Department</th>
                    <th>Day</th>
                    <th>Time</th>
                    <th>Room</th>
                    <th>Lecturer</th>
                    <th>Students</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="13">No course or timetable data found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td class="code"><?= e($row['course_code']) ?></td>
                        <td><?= e($row['course_name']) ?></td>
                        <td><?= e($row['credit_hours'] ?: '-') ?></td>
                        <td><?= e($row['semester'] ?: '-') ?></td>
                        <td><?= e($row['academic_year'] ?: '-') ?></td>
                        <td><?= e($row['section_name'] ?: '-') ?></td>
                        <td><?= e($row['semester_label'] ?: '-') ?></td>
                        <td><?= e($row['department'] ?: '-') ?></td>
                        <td><?= e($row['day_of_week'] ?: '-') ?></td>
                        <td><?= e(($row['start_time'] && $row['end_time']) ? $row['start_time'] . ' - ' . $row['end_time'] : '-') ?></td>
                        <td><?= e($row['room']) ?></td>
                        <td><?= e($row['lecturer']) ?></td>
                        <td><?= e((int)$row['enrolled_students']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
