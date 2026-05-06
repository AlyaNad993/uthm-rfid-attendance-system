<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];
$selectedSessionId = (int)($_GET['session_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        s.session_id,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        s.actual_start_time,
        s.actual_end_time,
        s.session_status,
        s.total_expected,
        s.total_present,
        s.total_late,
        s.total_absent,
        c.course_code,
        c.course_name,
        r.room_code,
        r.room_name
    FROM attendance_sessions s
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE cs.lecturer_id = ?
    ORDER BY s.session_date DESC, s.planned_start_time DESC
");
$stmt->execute([$lecturerId]);
$sessions = $stmt->fetchAll();

if (!$selectedSessionId && $sessions) {
    $selectedSessionId = (int)$sessions[0]['session_id'];
}

$selectedSession = null;
$records = [];

if ($selectedSessionId) {
    $stmt = $pdo->prepare("
        SELECT
            s.session_id,
            s.session_date,
            s.planned_start_time,
            s.planned_end_time,
            s.actual_start_time,
            s.actual_end_time,
            s.session_status,
            s.total_expected,
            s.total_present,
            s.total_late,
            s.total_absent,
            c.course_code,
            c.course_name,
            r.room_code,
            r.room_name
        FROM attendance_sessions s
        JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
        JOIN courses c ON cs.course_id = c.course_id
        LEFT JOIN rooms r ON cs.room_id = r.room_id
        WHERE s.session_id = ?
          AND cs.lecturer_id = ?
        LIMIT 1
    ");
    $stmt->execute([$selectedSessionId, $lecturerId]);
    $selectedSession = $stmt->fetch();

    if ($selectedSession) {
        $stmt = $pdo->prepare("
            SELECT
                u.full_name,
                u.matric_no,
                ar.scan_time,
                ar.status,
                ar.late_minutes,
                ar.notes
            FROM attendance_records ar
            JOIN users u ON ar.student_id = u.user_id
            WHERE ar.session_id = ?
            ORDER BY FIELD(ar.status, 'present', 'late', 'absent', 'excused', 'mc'), u.full_name
        ");
        $stmt->execute([$selectedSessionId]);
        $records = $stmt->fetchAll();
    }
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function formatTime($value) {
    if (!$value) {
        return '-';
    }

    return date('h:i A', strtotime($value));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Session Records</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f4f7fb;
            color: #1f2937;
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 24px;
        }
        .page { max-width: 1180px; margin: 0 auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        h1 { font-size: 30px; line-height: 1.2; color: #111827; }
        .muted { color: #64748b; margin-top: 6px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            font-weight: 700;
            text-decoration: none;
        }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .grid {
            display: grid;
            grid-template-columns: 330px 1fr;
            gap: 18px;
        }
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .panel-header {
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 800;
            color: #111827;
        }
        .session-link {
            display: block;
            padding: 14px 16px;
            border-bottom: 1px solid #eef2f7;
            color: inherit;
            text-decoration: none;
        }
        .session-link.active {
            background: #eff6ff;
            box-shadow: inset 3px 0 0 #2563eb;
        }
        .session-title { font-weight: 800; color: #111827; }
        .session-meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 5px;
            color: #64748b;
            font-size: 13px;
        }
        .summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            padding: 18px;
        }
        .stat {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 14px;
            background: #f8fafc;
        }
        .stat-value {
            font-size: 26px;
            font-weight: 850;
            color: #111827;
        }
        .stat-label {
            margin-top: 3px;
            color: #64748b;
            font-size: 13px;
            font-weight: 700;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 13px 18px;
            border-top: 1px solid #eef2f7;
            text-align: left;
            font-size: 14px;
        }
        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            text-transform: uppercase;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 28px;
            padding: 0 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .present { background: #dcfce7; color: #166534; }
        .late { background: #fef3c7; color: #92400e; }
        .absent { background: #fee2e2; color: #991b1b; }
        .empty {
            padding: 28px 18px;
            color: #64748b;
            text-align: center;
        }
        @media (max-width: 900px) {
            body { padding: 16px; }
            .topbar,
            .grid,
            .summary { display: block; }
            .actions { margin-top: 14px; }
            .panel { margin-bottom: 16px; }
            .stat { margin-bottom: 10px; }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Session Records</h1>
                <p class="muted">Review created sessions and recorded attendance.</p>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="create_session.php">Create Session</a>
                <a class="btn" href="live_attendance.php">Live Attendance</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <section class="grid">
            <aside class="panel">
                <div class="panel-header">Sessions</div>
                <?php if (!$sessions): ?>
                    <div class="empty">No sessions have been created yet.</div>
                <?php else: ?>
                    <?php foreach ($sessions as $session): ?>
                        <a class="session-link <?= (int)$session['session_id'] === $selectedSessionId ? 'active' : '' ?>" href="records.php?session_id=<?= e($session['session_id']) ?>">
                            <div class="session-title"><?= e($session['course_code']) ?> - <?= e($session['course_name']) ?></div>
                            <div class="session-meta">
                                <span><?= e(date('d M Y', strtotime($session['session_date']))) ?></span>
                                <span><?= e(substr($session['planned_start_time'], 0, 5)) ?> - <?= e(substr($session['planned_end_time'], 0, 5)) ?></span>
                            </div>
                            <div class="session-meta">
                                <span><?= e($session['room_code'] ?: $session['room_name'] ?: 'Room TBA') ?></span>
                                <span><?= e(ucfirst($session['session_status'] ?: 'unknown')) ?></span>
                            </div>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </aside>

            <section class="panel">
                <?php if (!$selectedSession): ?>
                    <div class="empty">Select a session to view records.</div>
                <?php else: ?>
                    <div class="panel-header">
                        <?= e($selectedSession['course_code']) ?> - <?= e($selectedSession['course_name']) ?>
                        <div class="session-meta">
                            <span><?= e(date('d M Y', strtotime($selectedSession['session_date']))) ?></span>
                            <span><?= e(substr($selectedSession['planned_start_time'], 0, 5)) ?> - <?= e(substr($selectedSession['planned_end_time'], 0, 5)) ?></span>
                            <span><?= e($selectedSession['room_code'] ?: $selectedSession['room_name'] ?: 'Room TBA') ?></span>
                        </div>
                    </div>

                    <div class="summary">
                        <div class="stat">
                            <div class="stat-value"><?= e((int)$selectedSession['total_expected']) ?></div>
                            <div class="stat-label">Expected</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= e((int)$selectedSession['total_present']) ?></div>
                            <div class="stat-label">Present</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= e((int)$selectedSession['total_late']) ?></div>
                            <div class="stat-label">Late</div>
                        </div>
                        <div class="stat">
                            <div class="stat-value"><?= e((int)$selectedSession['total_absent']) ?></div>
                            <div class="stat-label">Absent</div>
                        </div>
                    </div>

                    <?php if (!$records): ?>
                        <div class="empty">No attendance records for this session yet.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Matric No</th>
                                    <th>Status</th>
                                    <th>Scan Time</th>
                                    <th>Late Minutes</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td><?= e($record['full_name']) ?></td>
                                        <td><?= e($record['matric_no']) ?></td>
                                        <td><span class="badge <?= e($record['status']) ?>"><?= e($record['status']) ?></span></td>
                                        <td><?= e(formatTime($record['scan_time'])) ?></td>
                                        <td><?= e((int)$record['late_minutes']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        </section>
    </main>
</body>
</html>
