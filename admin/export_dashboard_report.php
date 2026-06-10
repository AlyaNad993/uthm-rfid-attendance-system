<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

$type = strtolower(trim($_GET['type'] ?? 'attendance'));
$format = strtolower(trim($_GET['format'] ?? 'pdf'));
$from = trim($_GET['from'] ?? date('Y-01-01'));
$to = trim($_GET['to'] ?? date('Y-12-31'));

if (!in_array($type, ['attendance', 'rfid'], true)) {
    $type = 'attendance';
}

if (!in_array($format, ['pdf', 'csv'], true)) {
    $format = 'pdf';
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from = date('Y-01-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) {
    $to = date('Y-12-31');
}

if ($type === 'attendance') {
    $stmt = $pdo->prepare("
        SELECT
            ats.session_date,
            TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
            TIME_FORMAT(ats.planned_end_time, '%H:%i') AS end_time,
            c.course_code,
            c.course_name,
            lecturer.full_name AS lecturer_name,
            u.matric_no,
            u.full_name AS student_name,
            u.email,
            ar.scan_time,
            ar.status,
            COALESCE(ar.late_minutes, 0) AS late_minutes,
            COALESCE(ar.manual_override, 0) AS manual_override,
            ar.notes
        FROM attendance_records ar
        JOIN users u ON u.user_id = ar.student_id
        JOIN attendance_sessions ats ON ats.session_id = ar.session_id
        JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
        JOIN courses c ON c.course_id = cs.course_id
        JOIN users lecturer ON lecturer.user_id = cs.lecturer_id
        WHERE ats.session_date BETWEEN ? AND ?
        ORDER BY ats.session_date DESC, ats.planned_start_time DESC, c.course_code, u.full_name
    ");
    $stmt->execute([$from, $to]);
    $rows = $stmt->fetchAll();
    $title = 'Attendance Report';
    $filename = 'attendance_report_' . $from . '_to_' . $to;
} else {
    $stmt = $pdo->query("
        SELECT
            u.matric_no,
            u.full_name,
            u.role,
            u.email,
            u.phone,
            COALESCE(rc.uid, '') AS rfid_uid,
            COALESCE(rc.card_type, '') AS card_type,
            COALESCE(rc.status, 'Not assigned') AS card_status,
            rc.issue_date
        FROM users u
        LEFT JOIN rfid_cards rc
            ON rc.card_id = (
                SELECT rc2.card_id
                FROM rfid_cards rc2
                WHERE rc2.user_id = u.user_id
                ORDER BY rc2.issue_date DESC, rc2.card_id DESC
                LIMIT 1
            )
        WHERE u.is_active = 1
        ORDER BY FIELD(u.role, 'student', 'lecturer', 'admin', 'staff'), u.full_name
    ");
    $rows = $stmt->fetchAll();
    $title = 'RFID Card Registry';
    $filename = 'rfid_card_registry_' . date('Y-m-d');
}

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [$title]);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    if ($type === 'attendance') {
        fputcsv($out, ['Date Range', $from . ' to ' . $to]);
    }
    fputcsv($out, []);

    if ($type === 'attendance') {
        fputcsv($out, ['Date', 'Time', 'Course', 'Course Name', 'Lecturer', 'Matric No', 'Student', 'Email', 'Scan Time', 'Status', 'Source', 'Notes']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['session_date'],
                $row['start_time'] . ' - ' . $row['end_time'],
                $row['course_code'],
                $row['course_name'],
                $row['lecturer_name'],
                $row['matric_no'],
                $row['student_name'],
                $row['email'],
                $row['scan_time'] ?: '-',
                $row['status'],
                ((int)$row['manual_override'] === 1) ? 'Manual' : 'RFID/QR',
                $row['notes'] ?: '',
            ]);
        }
    } else {
        fputcsv($out, ['Matric / Staff ID', 'Name', 'Role', 'Email', 'Phone', 'RFID UID', 'Card Type', 'Card Status', 'Issue Date']);
        foreach ($rows as $row) {
            fputcsv($out, [
                $row['matric_no'],
                $row['full_name'],
                $row['role'],
                $row['email'],
                $row['phone'],
                $row['rfid_uid'] ?: 'Not assigned',
                $row['card_type'] ?: '-',
                $row['card_status'],
                $row['issue_date'] ?: '-',
            ]);
        }
    }

    fclose($out);
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

$present = $type === 'attendance' ? count(array_filter($rows, fn($row) => $row['status'] === 'present')) : 0;
$absent = $type === 'attendance' ? count(array_filter($rows, fn($row) => $row['status'] === 'absent')) : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title) ?></title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; font-family: "Segoe UI", Arial, sans-serif; color: #172033; background: radial-gradient(circle at top left, rgba(0, 104, 55, 0.12), transparent 30%), radial-gradient(circle at bottom right, rgba(67, 97, 238, 0.10), transparent 28%), #f5f8fc; }
        .page { position: relative; max-width: 1220px; margin: 0 auto; background: rgba(255,255,255,0.96); border: 1px solid #dce4ef; border-radius: 18px; padding: 30px; box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12); overflow: hidden; }
        .page::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #006837, #0e8a83, #4361ee); }
        .top { display: flex; justify-content: space-between; gap: 18px; margin-bottom: 24px; align-items: flex-start; }
        h1 { margin: 0 0 6px; font-size: 32px; letter-spacing: 0; color: #102033; }
        .muted { color: #66738a; }
        .actions { display: flex; gap: 10px; }
        .btn { min-height: 42px; padding: 0 16px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; color: #172033; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(28, 52, 84, 0.08); }
        .btn:last-child { background: linear-gradient(135deg, #006837, #4361ee); border-color: transparent; color: #fff; }
        .stats { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin-bottom: 22px; }
        .stat { border: 1px solid #e1eaf4; border-radius: 14px; padding: 14px; background: linear-gradient(180deg, #ffffff, #f7fbf9); }
        .stat span { display: block; color: #66738a; font-size: 12px; font-weight: 800; text-transform: uppercase; margin-bottom: 4px; }
        .stat strong { font-size: 23px; color: #102033; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 10px 11px; border: 1px solid #e7edf5; text-align: left; vertical-align: top; }
        th { background: linear-gradient(135deg, #edf8f1, #eef4ff); color: #24415c; text-transform: uppercase; font-size: 11px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 900; text-transform: uppercase; background: #e0f2fe; color: #0369a1; }
        .present, .active { background: #dcfce7; color: #166534; }
        .absent, .inactive, .lost, .damaged { background: #fee2e2; color: #991b1b; }
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
                <h1><?= e($title) ?></h1>
                <div class="muted">RFID IoT Attendance - Admin Console</div>
                <div class="muted">Generated: <?= e(date('Y-m-d H:i:s')) ?></div>
                <?php if ($type === 'attendance'): ?>
                    <div class="muted">Date range: <?= e($from) ?> to <?= e($to) ?></div>
                <?php endif; ?>
            </div>
            <div class="actions">
                <button class="btn" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
                <button class="btn" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <?php if ($type === 'attendance'): ?>
            <section class="stats">
                <div class="stat"><span>Total Records</span><strong><?= e(count($rows)) ?></strong></div>
                <div class="stat"><span>Present</span><strong><?= e($present) ?></strong></div>
                <div class="stat"><span>Absent</span><strong><?= e($absent) ?></strong></div>
            </section>
        <?php endif; ?>

        <table>
            <thead>
                <?php if ($type === 'attendance'): ?>
                    <tr>
                        <th>Date</th>
                        <th>Course</th>
                        <th>Lecturer</th>
                        <th>Student</th>
                        <th>Scan Time</th>
                        <th>Status</th>
                        <th>Source</th>
                    </tr>
                <?php else: ?>
                    <tr>
                        <th>Matric / Staff ID</th>
                        <th>Name</th>
                        <th>Role</th>
                        <th>Email</th>
                        <th>Phone</th>
                        <th>RFID UID</th>
                        <th>Card Type</th>
                        <th>Card Status</th>
                    </tr>
                <?php endif; ?>
            </thead>
            <tbody>
                <?php if (!$rows): ?>
                    <tr><td colspan="8">No records found.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <?php if ($type === 'attendance'): ?>
                        <tr>
                            <td><?= e($row['session_date']) ?><br><?= e($row['start_time']) ?> - <?= e($row['end_time']) ?></td>
                            <td><strong><?= e($row['course_code']) ?></strong><br><?= e($row['course_name']) ?></td>
                            <td><?= e($row['lecturer_name']) ?></td>
                            <td><strong><?= e($row['matric_no']) ?></strong><br><?= e($row['student_name']) ?></td>
                            <td><?= e($row['scan_time'] ?: '-') ?></td>
                            <td><span class="badge <?= e($row['status']) ?>"><?= e($row['status']) ?></span></td>
                            <td><?= ((int)$row['manual_override'] === 1) ? 'Manual' : 'RFID/QR' ?></td>
                        </tr>
                    <?php else: ?>
                        <?php $cardClass = strtolower(str_replace(['/', ' '], '', $row['card_status'])); ?>
                        <tr>
                            <td><?= e($row['matric_no']) ?></td>
                            <td><?= e($row['full_name']) ?></td>
                            <td><?= e(ucfirst($row['role'])) ?></td>
                            <td><?= e($row['email']) ?></td>
                            <td><?= e($row['phone'] ?: '-') ?></td>
                            <td><?= e($row['rfid_uid'] ?: 'Not assigned') ?></td>
                            <td><?= e($row['card_type'] ?: '-') ?></td>
                            <td><span class="badge <?= e($cardClass) ?>"><?= e($row['card_status']) ?></span></td>
                        </tr>
                    <?php endif; ?>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
