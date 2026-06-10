<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$sessionId = (int)($_GET['session_id'] ?? 0);
$format = strtolower(trim($_GET['format'] ?? 'csv'));
if (!in_array($format, ['csv', 'pdf'], true)) {
    $format = 'csv';
}
$from = strtolower(trim($_GET['from'] ?? ''));
$backUrl = $from === 'dashboard' ? 'dashboard.php' : 'session_view.php?session_id=' . $sessionId;

if ($sessionId <= 0) {
    http_response_code(400);
    echo 'Missing session_id.';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        ats.session_id,
        ats.session_date,
        TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
        TIME_FORMAT(ats.planned_end_time, '%H:%i') AS end_time,
        c.course_id,
        c.course_code,
        c.course_name,
        cs.section_name,
        cs.academic_year,
        cs.semester_label,
        lecturer.full_name AS lecturer_name
    FROM attendance_sessions ats
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    JOIN users lecturer ON lecturer.user_id = cs.lecturer_id
    WHERE ats.session_id = ?
    LIMIT 1
");
$stmt->execute([$sessionId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo 'Session not found.';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.matric_no,
        u.full_name,
        u.email,
        COALESCE(ar.status, 'not_marked') AS status,
        ar.scan_time,
        COALESCE(ar.late_minutes, 0) AS late_minutes,
        COALESCE(ar.manual_override, 0) AS manual_override,
        ar.notes,
        er.status AS excuse_status,
        er.original_name AS excuse_file
    FROM enrollments e
    JOIN users u ON u.user_id = e.student_id
    LEFT JOIN attendance_records ar
        ON ar.student_id = u.user_id
       AND ar.session_id = ?
    LEFT JOIN excuse_requests er
        ON er.record_id = ar.record_id
       AND er.student_id = u.user_id
    WHERE e.course_id = ?
      AND e.section_name = ?
      AND COALESCE(e.academic_year, '') = ?
      AND e.status = 'registered'
      AND u.role = 'student'
    ORDER BY u.full_name
");
$stmt->execute([
    $sessionId,
    (int)$session['course_id'],
    $session['section_name'] ?? 'Section 1',
    $session['academic_year'] ?? ''
]);
$rows = $stmt->fetchAll();

if ($format === 'pdf') {
    $present = count(array_filter($rows, fn($row) => $row['status'] === 'present'));
    $absent = count(array_filter($rows, fn($row) => $row['status'] === 'absent'));
    $expected = count($rows);
    $rate = $expected > 0 ? round(($present / $expected) * 100, 1) : 0;

    function e($value) {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Report | <?= e($session['course_code']) ?></title>
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
        .meta { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 22px; }
        .box { border: 1px solid #e1eaf4; border-radius: 14px; padding: 14px; background: linear-gradient(180deg, #ffffff, #f7fbf9); }
        .box span { display: block; color: #66738a; font-size: 12px; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .box strong { font-size: 19px; color: #102033; }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 11px 12px; border: 1px solid #e7edf5; text-align: left; vertical-align: top; }
        th { background: linear-gradient(135deg, #edf8f1, #eef4ff); color: #24415c; text-transform: uppercase; font-size: 12px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 900; text-transform: uppercase; background: #e0f2fe; color: #0369a1; }
        .present { background: #dcfce7; color: #166534; }
        .absent { background: #fee2e2; color: #991b1b; }
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
                <h1>Attendance Report</h1>
                <div class="muted"><?= e($session['course_code']) ?> - <?= e($session['course_name']) ?></div>
                <div class="muted">Generated: <?= e(date('Y-m-d H:i:s')) ?></div>
            </div>
            <div class="actions">
                <button class="btn" onclick="window.location.href='<?= e($backUrl) ?>'">Back to <?= $from === 'dashboard' ? 'Dashboard' : 'Session Detail' ?></button>
                <button class="btn" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <section class="meta">
            <div class="box"><span>Lecturer</span><strong><?= e($session['lecturer_name']) ?></strong></div>
            <div class="box"><span>Date</span><strong><?= e($session['session_date']) ?></strong></div>
            <div class="box"><span>Time</span><strong><?= e($session['start_time']) ?> - <?= e($session['end_time']) ?></strong></div>
            <div class="box"><span>Attendance Rate</span><strong><?= e($rate) ?>%</strong></div>
            <div class="box"><span>Present</span><strong><?= e($present) ?></strong></div>
            <div class="box"><span>Absent</span><strong><?= e($absent) ?></strong></div>
            <div class="box"><span>Total Students</span><strong><?= e($expected) ?></strong></div>
        </section>

        <table>
            <thead>
                <tr>
                    <th>Matric No</th>
                    <th>Student Name</th>
                    <th>Email</th>
                    <th>Status</th>
                    <th>Scan Time</th>
                    <th>Source</th>
                    <th>Excuse / MC</th>
                    <th>Notes</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="9">No attendance records found.</td></tr>
                <?php endif; ?>
                <?php foreach ($rows as $row): ?>
                    <?php $statusClass = in_array($row['status'], ['present', 'absent'], true) ? $row['status'] : ''; ?>
                    <tr>
                        <td><?= e($row['matric_no']) ?></td>
                        <td><?= e($row['full_name']) ?></td>
                        <td><?= e($row['email']) ?></td>
                        <td><span class="badge <?= e($statusClass) ?>"><?= e($row['status']) ?></span></td>
                        <td><?= e($row['scan_time'] ?: '-') ?></td>
                        <td><?= ((int)$row['manual_override'] === 1) ? 'Manual' : ($row['scan_time'] ? 'RFID/QR' : '-') ?></td>
                        <td><?= e($row['excuse_status'] ?: '-') ?></td>
                        <td><?= e($row['notes'] ?: '-') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
    <?php
    exit;
}

$filename = 'attendance_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $session['course_code']) . '_session_' . $sessionId . '.csv';

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fputcsv($out, ['Session ID', $session['session_id']]);
fputcsv($out, ['Course', $session['course_code'] . ' - ' . $session['course_name']]);
fputcsv($out, ['Lecturer', $session['lecturer_name']]);
fputcsv($out, ['Date', $session['session_date']]);
fputcsv($out, ['Time', $session['start_time'] . ' - ' . $session['end_time']]);
fputcsv($out, []);
fputcsv($out, [
    'Matric No',
    'Student Name',
    'Email',
    'Status',
    'Scan Time',
    'Source',
    'Notes',
    'Excuse Status',
    'Excuse File'
]);

foreach ($rows as $row) {
    fputcsv($out, [
        $row['matric_no'],
        $row['full_name'],
        $row['email'],
        $row['status'],
        $row['scan_time'] ?: '-',
        ((int)$row['manual_override'] === 1) ? 'Manual' : ($row['scan_time'] ? 'RFID/QR' : '-'),
        $row['notes'] ?: '',
        $row['excuse_status'] ?: '',
        $row['excuse_file'] ?: '',
    ]);
}

fclose($out);
exit;
