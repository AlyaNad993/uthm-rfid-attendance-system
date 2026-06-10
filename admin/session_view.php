<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    header('Location: dashboard.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        ats.session_id,
        ats.session_date,
        ats.planned_start_time,
        ats.planned_end_time,
        ats.actual_start_time,
        ats.actual_end_time,
        ats.session_status,
        ats.attendance_method,
        ats.total_expected,
        ats.total_present,
        ats.total_late,
        ats.total_absent,
        c.course_id,
        c.course_code,
        c.course_name,
        cs.section_name,
        cs.academic_year,
        cs.semester_label,
        lecturer.full_name AS lecturer_name,
        r.room_code,
        r.room_name
    FROM attendance_sessions ats
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    JOIN users lecturer ON lecturer.user_id = cs.lecturer_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id
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
        u.full_name,
        u.matric_no,
        u.email,
        u.profile_image,
        ar.scan_time,
        COALESCE(ar.status, 'not_marked') AS status,
        COALESCE(ar.late_minutes, 0) AS late_minutes,
        COALESCE(ar.manual_override, 0) AS manual_override,
        ar.notes,
        er.status AS excuse_status,
        er.file_path AS excuse_file,
        er.original_name AS excuse_name,
        er.submitted_at AS excuse_submitted_at
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
    ORDER BY FIELD(COALESCE(ar.status, 'not_marked'), 'present', 'absent', 'not_marked'), u.full_name
");
$stmt->execute([
    $sessionId,
    (int)$session['course_id'],
    $session['section_name'] ?? 'Section 1',
    $session['academic_year'] ?? ''
]);
$records = $stmt->fetchAll();

$expected = count($records);
$present = count(array_filter($records, fn($row) => $row['status'] === 'present'));
$absent = count(array_filter($records, fn($row) => $row['status'] === 'absent'));
$rate = $expected > 0 ? round(($present / $expected) * 100, 1) : 0;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function fmtTime($value) {
    return $value ? date('h:i A', strtotime($value)) : '-';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Detail | Admin</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: "Segoe UI", system-ui, sans-serif; }
        body { min-height: 100vh; background: radial-gradient(circle at top left, rgba(67, 97, 238, 0.15), transparent 30%), radial-gradient(circle at bottom right, rgba(0, 104, 55, 0.14), transparent 28%), #f6f9fc; color: #172033; padding: 28px; }
        .page { max-width: 1280px; margin: 0 auto; }
        .topbar { position: relative; display: flex; justify-content: space-between; align-items: center; gap: 16px; margin-bottom: 22px; padding: 24px 26px; background: rgba(255,255,255,.92); border: 1px solid #dce4ef; border-radius: 18px; box-shadow: 0 16px 38px rgba(28, 52, 84, 0.1); overflow: hidden; }
        .topbar::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #006837, #0e8a83, #4361ee); }
        h1 { font-size: clamp(30px, 4vw, 44px); line-height: 1.05; letter-spacing: 0; }
        .muted { color: #66738a; line-height: 1.6; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn { min-height: 44px; padding: 0 16px; border: 1px solid #cbd5e1; border-radius: 12px; background: #fff; color: #172033; font-weight: 800; text-decoration: none; display: inline-flex; align-items: center; gap: 8px; transition: .2s ease; white-space: nowrap; }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 10px 20px rgba(28,52,84,.08); }
        .btn-primary { background: linear-gradient(135deg, #006837, #4361ee); color: #fff; border-color: transparent; }
        .card { background: rgba(255,255,255,0.94); border: 1px solid #dce4ef; border-radius: 18px; padding: 24px; box-shadow: 0 16px 38px rgba(28, 52, 84, 0.1); margin-bottom: 20px; }
        .session-grid { display: grid; grid-template-columns: 1.4fr 0.9fr; gap: 18px; }
        .course-heading { display: flex; align-items: center; gap: 14px; margin-bottom: 18px; }
        .course-icon { width: 52px; height: 52px; border-radius: 16px; display: grid; place-items: center; color: #fff; background: linear-gradient(135deg, #006837, #4361ee); box-shadow: 0 12px 24px rgba(67,97,238,.18); }
        .course-heading h2 { font-size: 25px; line-height: 1.2; }
        .meta { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 12px; color: #475569; }
        .meta span { padding: 12px 14px; border: 1px solid #e7edf5; border-radius: 12px; background: #f8fafc; }
        .meta strong { color: #24364d; }
        .stats { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 14px; }
        .stat { background: linear-gradient(135deg, #fff, #f8fbff); border: 1px solid #dce4ef; border-radius: 16px; padding: 18px; min-height: 104px; display: flex; flex-direction: column; justify-content: center; }
        .stat strong { font-size: 32px; display: block; color: #0f1f36; line-height: 1; margin-bottom: 8px; }
        .table-wrap { overflow: hidden; background: #fff; border-radius: 18px; border: 1px solid #dce4ef; box-shadow: 0 16px 38px rgba(28, 52, 84, 0.08); }
        .table-scroll { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 1100px; }
        th, td { text-align: left; padding: 16px 18px; border-bottom: 1px solid #edf1f5; vertical-align: middle; }
        th { background: linear-gradient(135deg, #eef7f2, #eef4ff); color: #42536a; font-size: 12px; text-transform: uppercase; letter-spacing: .02em; }
        tbody tr:hover td { background: #fafcff; }
        .student-cell { display: flex; align-items: center; gap: 12px; font-weight: 850; min-width: 150px; }
        .student-photo { width: 46px; height: 46px; border-radius: 14px; object-fit: cover; border: 2px solid #e7eef8; box-shadow: 0 8px 18px rgba(28,52,84,.08); }
        .badge { display: inline-flex; align-items: center; justify-content: center; padding: 7px 11px; border-radius: 999px; background: #e0f2fe; color: #0369a1; font-size: 12px; font-weight: 900; text-transform: uppercase; white-space: nowrap; }
        .present { background: #dcfce7; color: #166534; }
        .absent { background: #fee2e2; color: #991b1b; }
        .notes { max-width: 360px; color: #334155; line-height: 1.45; }
        .source { font-weight: 750; color: #24364d; }
        .empty { padding: 28px; text-align: center; color: #66738a; }
        @media (max-width: 980px) { body { padding: 18px; } .topbar { align-items: flex-start; flex-direction: column; } .session-grid { grid-template-columns: 1fr; } .stats { grid-template-columns: repeat(2, 1fr); } .meta { grid-template-columns: 1fr; } }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Session Detail</h1>
                <p class="muted"><?= e($session['course_code']) ?> - <?= e($session['course_name']) ?></p>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="export_attendance.php?session_id=<?= e($sessionId) ?>"><i class="fas fa-download"></i> Export CSV</a>
                <a class="btn" href="dashboard.php"><i class="fas fa-arrow-left"></i> Back to Dashboard</a>
            </div>
        </div>

        <section class="session-grid">
            <div class="card">
                <div class="course-heading">
                    <div class="course-icon"><i class="fas fa-chalkboard"></i></div>
                    <div>
                        <h2><?= e($session['course_code']) ?> - <?= e($session['course_name']) ?></h2>
                        <p class="muted">Attendance session overview</p>
                    </div>
                </div>
                <div class="meta">
                    <span><strong>Date:</strong> <?= e($session['session_date']) ?></span>
                    <span><strong>Time:</strong> <?= e(substr($session['planned_start_time'], 0, 5)) ?> - <?= e(substr($session['planned_end_time'], 0, 5)) ?></span>
                    <span><strong>Lecturer:</strong> <?= e($session['lecturer_name']) ?></span>
                    <span><strong>Room:</strong> <?= e($session['room_code'] ?: $session['room_name'] ?: '-') ?></span>
                    <span><strong>Method:</strong> <?= e(strtoupper($session['attendance_method'] ?? 'rfid')) ?></span>
                    <span><strong>Status:</strong> <span class="badge"><?= e($session['session_status']) ?></span></span>
                </div>
            </div>

            <div class="card">
                <div class="stats">
                    <div class="stat"><strong><?= e($present) ?></strong><span class="muted">Present</span></div>
                    <div class="stat"><strong><?= e($absent) ?></strong><span class="muted">Absent</span></div>
                    <div class="stat"><strong><?= e($rate) ?>%</strong><span class="muted">Rate</span></div>
                </div>
            </div>
        </section>

        <section class="table-wrap">
            <?php if (!$records): ?>
                <div class="empty">No enrolled students found for this session course.</div>
            <?php else: ?>
                <div class="table-scroll">
                <table>
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Matric No</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Scan Time</th>
                            <th>Source</th>
                            <th>Excuse / MC</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php
                                $status = $record['status'];
                                $badgeClass = in_array($status, ['present', 'absent'], true) ? $status : '';
                            ?>
                            <tr>
                                <td>
                                    <div class="student-cell">
                                        <img class="student-photo" src="<?= e(profileImageUrl($record['profile_image'] ?? '', $record['full_name'])) ?>" alt="<?= e($record['full_name']) ?>">
                                        <?= e($record['full_name']) ?>
                                    </div>
                                </td>
                                <td><?= e($record['matric_no']) ?></td>
                                <td><?= e($record['email']) ?></td>
                                <td><span class="badge <?= e($badgeClass) ?>"><?= e($status) ?></span></td>
                                <td><?= e(fmtTime($record['scan_time'])) ?></td>
                                <td><span class="source"><?= ((int)$record['manual_override'] === 1) ? 'Manual' : ($record['scan_time'] ? 'RFID/QR' : '-') ?></span></td>
                                <td>
                                    <?php if ($record['excuse_status']): ?>
                                        <span class="badge <?= $record['excuse_status'] === 'rejected' ? 'absent' : '' ?>"><?= e($record['excuse_status']) ?></span>
                                        <?php if ($record['excuse_file']): ?>
                                            <br><a class="muted" href="../<?= e($record['excuse_file']) ?>" target="_blank"><?= e($record['excuse_name'] ?: 'View file') ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                                <td><div class="notes"><?= e($record['notes'] ?: '-') ?></div></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
