<?php
require_once '../includes/auth_check.php';
requireLecturerOrAdmin();
require_once '../includes/config.php';

$userRole = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $userRole === 'admin';
$requestedSessionId = (int)($_GET['session_id'] ?? 0);
$upcomingSessions = [];
$canEndSession = false;
$sessionEndTimestamp = null;

$sessionSql = "
    SELECT
        s.session_id,
        s.schedule_id,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        s.actual_start_time,
        s.actual_end_time,
        s.session_status,
        cs.course_id,
        cs.lecturer_id,
        cs.start_time,
        cs.end_time,
        c.course_code,
        c.course_name,
        r.room_code,
        r.room_name
    FROM attendance_sessions s
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE 1 = 1
";

$params = [];
if ($requestedSessionId > 0) {
    $sessionSql .= " AND s.session_id = ?";
    $params[] = $requestedSessionId;
} else {
    $sessionSql .= " AND s.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                     AND s.session_status IN ('ongoing', 'scheduled')";
}

if (!$isAdmin) {
    $sessionSql .= " AND cs.lecturer_id = ?";
    $params[] = $userId;
}

$sessionSql .= "
    ORDER BY FIELD(s.session_status, 'ongoing', 'scheduled', 'completed', 'cancelled'),
             s.session_date ASC,
             s.planned_start_time ASC,
             s.session_id DESC
    LIMIT 1
";

$stmt = $pdo->prepare($sessionSql);
$stmt->execute($params);
$activeSession = $stmt->fetch();

$listSql = "
    SELECT
        s.session_id,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        s.session_status,
        c.course_code,
        c.course_name,
        r.room_code,
        r.room_name
    FROM attendance_sessions s
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE s.session_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
      AND s.session_status IN ('scheduled', 'ongoing')
";

$listParams = [];
if (!$isAdmin) {
    $listSql .= " AND cs.lecturer_id = ?";
    $listParams[] = $userId;
}

$listSql .= "
    ORDER BY s.session_date ASC,
             s.planned_start_time ASC,
             FIELD(s.session_status, 'ongoing', 'scheduled')
";

$stmt = $pdo->prepare($listSql);
$stmt->execute($listParams);
$upcomingSessions = $stmt->fetchAll();

$presentStudents = [];
$absentStudents = [];
$allStudents = [];
$recentScans = [];
$stats = [
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'total' => 0,
    'rate' => 0,
];

if ($activeSession) {
    $sessionId = (int)$activeSession['session_id'];
    $courseId = (int)$activeSession['course_id'];
    $sessionEnd = strtotime($activeSession['session_date'] . ' ' . $activeSession['planned_end_time']);

    if (
        $activeSession['session_status'] !== 'completed'
        && $activeSession['planned_end_time']
        && time() > $sessionEnd
    ) {
        markSessionAbsentees($pdo, $sessionId, $courseId, $activeSession['session_date'], $activeSession['planned_end_time']);

        $stmt = $pdo->prepare("
            SELECT
                s.session_id,
                s.schedule_id,
                s.session_date,
                s.planned_start_time,
                s.planned_end_time,
                s.actual_start_time,
                s.actual_end_time,
                s.session_status,
                cs.course_id,
                cs.lecturer_id,
                cs.start_time,
                cs.end_time,
                c.course_code,
                c.course_name,
                r.room_code,
                r.room_name
            FROM attendance_sessions s
            JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
            JOIN courses c ON cs.course_id = c.course_id
            LEFT JOIN rooms r ON cs.room_id = r.room_id
            WHERE s.session_id = ?
            LIMIT 1
        ");
        $stmt->execute([$sessionId]);
        $activeSession = $stmt->fetch();
    }

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.matric_no,
            ar.scan_time,
            ar.status,
            ar.late_minutes
        FROM attendance_records ar
        JOIN users u ON ar.student_id = u.user_id
        WHERE ar.session_id = ?
          AND ar.status IN ('present', 'late')
        ORDER BY ar.scan_time DESC
    ");
    $stmt->execute([$sessionId]);
    $presentStudents = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.matric_no,
            ar.scan_time,
            ar.status,
            ar.late_minutes
        FROM enrollments e
        JOIN users u ON e.student_id = u.user_id
        LEFT JOIN attendance_records ar
            ON ar.student_id = u.user_id
           AND ar.session_id = ?
        WHERE e.course_id = ?
          AND e.status = 'registered'
          AND u.role = 'student'
          AND u.is_active = 1
        ORDER BY u.full_name
    ");
    $stmt->execute([$sessionId, $courseId]);
    $allStudents = $stmt->fetchAll();

    foreach ($allStudents as $student) {
        if (empty($student['scan_time']) || $student['status'] === 'absent') {
            $absentStudents[] = $student;
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            u.full_name,
            u.matric_no,
            ar.scan_time,
            ar.status,
            ar.late_minutes
        FROM attendance_records ar
        JOIN users u ON ar.student_id = u.user_id
        WHERE ar.session_id = ?
        ORDER BY ar.scan_time DESC
        LIMIT 10
    ");
    $stmt->execute([$sessionId]);
    $recentScans = $stmt->fetchAll();

    $stats['total'] = count($allStudents);
    $stats['present'] = count(array_filter($presentStudents, fn($s) => $s['status'] === 'present'));
    $stats['late'] = count(array_filter($presentStudents, fn($s) => $s['status'] === 'late'));
    $stats['absent'] = count($absentStudents);
    $stats['rate'] = $stats['total'] > 0 ? round((count($presentStudents) / $stats['total']) * 100) : 0;

    $sessionEndTimestamp = strtotime($activeSession['session_date'] . ' ' . $activeSession['planned_end_time']);
    $canEndSession = $activeSession['session_status'] !== 'completed'
        && $activeSession['session_status'] !== 'cancelled'
        && $sessionEndTimestamp
        && time() >= $sessionEndTimestamp;
}

function markSessionAbsentees(PDO $pdo, int $sessionId, int $courseId, string $sessionDate, string $plannedEndTime): void {
    $pdo->beginTransaction();

    try {
        $absentScanTime = $sessionDate . ' ' . $plannedEndTime;

        $stmt = $pdo->prepare("
            INSERT INTO attendance_records
                (session_id, student_id, rfid_card_id, scan_time, status, late_minutes, manual_override, notes)
            SELECT
                ?,
                e.student_id,
                NULL,
                ?,
                'absent',
                0,
                1,
                'Auto-marked absent after session end time'
            FROM enrollments e
            JOIN users u ON e.student_id = u.user_id
            WHERE e.course_id = ?
              AND e.status = 'registered'
              AND u.role = 'student'
              AND u.is_active = 1
              AND NOT EXISTS (
                  SELECT 1
                  FROM attendance_records ar
                  WHERE ar.session_id = ?
                    AND ar.student_id = e.student_id
              )
        ");
        $stmt->execute([$sessionId, $absentScanTime, $courseId, $sessionId]);

        $stmt = $pdo->prepare("
            UPDATE attendance_sessions
            SET
                session_status = 'completed',
                actual_end_time = IFNULL(actual_end_time, NOW()),
                total_present = (
                    SELECT COUNT(*)
                    FROM attendance_records
                    WHERE session_id = ?
                      AND status = 'present'
                ),
                total_late = (
                    SELECT COUNT(*)
                    FROM attendance_records
                    WHERE session_id = ?
                      AND status = 'late'
                ),
                total_absent = (
                    SELECT COUNT(*)
                    FROM attendance_records
                    WHERE session_id = ?
                      AND status = 'absent'
                )
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId, $sessionId, $sessionId, $sessionId]);

        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        throw $e;
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
    <title>Live Attendance</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            min-height: 100vh;
            background: #f4f7fb;
            color: #1f2937;
            font-family: "Segoe UI", Arial, sans-serif;
            line-height: 1.5;
            padding: 24px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
        }

        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
            margin-bottom: 22px;
        }

        h1 {
            font-size: 30px;
            line-height: 1.2;
            color: #111827;
        }

        .muted {
            color: #64748b;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #ffffff;
            color: #334155;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
        }

        .btn-primary {
            background: #2563eb;
            border-color: #2563eb;
            color: #ffffff;
        }

        .notice,
        .panel,
        .stat {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }

        .notice {
            padding: 24px;
        }

        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            font-weight: 650;
        }

        .alert-success {
            background: #dcfce7;
            color: #166534;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
        }

        .session {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .session-list {
            display: grid;
            gap: 10px;
            margin-bottom: 0;
        }

        .session-list-card {
            padding: 18px;
            margin-bottom: 20px;
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }

        .session-list-header {
            display: flex;
            align-items: flex-end;
            justify-content: space-between;
            gap: 14px;
            margin-bottom: 8px;
        }

        .session-list-title {
            color: #111827;
            font-size: 15px;
            font-weight: 800;
        }

        .session-list-note {
            color: #64748b;
            font-size: 13px;
        }

        .session-row {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 14px;
            align-items: center;
            padding: 14px 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            text-decoration: none;
            color: inherit;
        }

        .session-row.active-row {
            background: #ffffff;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.12);
        }

        .session-row-kicker {
            display: inline-flex;
            align-items: center;
            margin-bottom: 6px;
            color: #2563eb;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }

        .session-row-title {
            font-weight: 750;
            color: #111827;
        }

        .session-row-meta {
            display: flex;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 5px;
            color: #64748b;
            font-size: 13px;
        }

        .session-title {
            font-size: 20px;
            font-weight: 750;
            color: #111827;
        }

        .section-label {
            color: #64748b;
            font-size: 13px;
            font-weight: 800;
            margin-bottom: 6px;
            text-transform: uppercase;
        }

        .session-meta {
            display: flex;
            gap: 18px;
            flex-wrap: wrap;
            margin-top: 8px;
            color: #475569;
            font-size: 14px;
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
            font-size: 13px;
            font-weight: 700;
            text-transform: uppercase;
        }

        .badge-present {
            background: #dcfce7;
            color: #166534;
        }

        .badge-late {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 20px;
        }

        .stat {
            padding: 18px;
        }

        .stat-value {
            font-size: 34px;
            font-weight: 800;
            color: #111827;
        }

        .stat-label {
            margin-top: 4px;
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }

        .grid {
            display: grid;
            grid-template-columns: 1.4fr 0.9fr;
            gap: 18px;
        }

        .panel {
            overflow: hidden;
        }

        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
        }

        .panel-title {
            font-size: 17px;
            font-weight: 750;
            color: #111827;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        th,
        td {
            padding: 13px 18px;
            border-bottom: 1px solid #eef2f7;
            text-align: left;
            vertical-align: middle;
            font-size: 14px;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            letter-spacing: 0;
            text-transform: uppercase;
        }

        tr:last-child td {
            border-bottom: 0;
        }

        .student {
            font-weight: 700;
            color: #111827;
        }

        .empty {
            padding: 28px 18px;
            color: #64748b;
            text-align: center;
        }

        .list {
            display: grid;
            gap: 0;
        }

        .list-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            padding: 13px 18px;
            border-bottom: 1px solid #eef2f7;
        }

        .list-row:last-child {
            border-bottom: 0;
        }

        .small {
            font-size: 13px;
        }

        @media (max-width: 860px) {
            body {
                padding: 16px;
            }

            .topbar,
            .session,
            .session-row {
                grid-template-columns: 1fr;
            }

            .topbar {
                display: block;
            }

            .actions {
                margin-top: 14px;
            }

            .stats,
            .grid {
                grid-template-columns: 1fr;
            }

            table {
                display: block;
                overflow-x: auto;
                white-space: nowrap;
            }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Live Attendance</h1>
                <p class="muted">Showing attendance data recorded from RFID scans.</p>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="create_session.php">Create Session</a>
                <button class="btn" type="button" onclick="window.location.reload()">Refresh</button>
                <a class="btn" href="dashboard.php">Back to Dashboard</a>
            </div>
        </div>

        <?php if (isset($_GET['ended'])): ?>
            <div class="alert alert-success">Session completed. Students without RFID scans have been marked absent.</div>
        <?php endif; ?>

        <?php if (($_GET['error'] ?? '') === 'too_early'): ?>
            <div class="alert alert-error">This session cannot be ended before its scheduled end time.</div>
        <?php elseif (($_GET['error'] ?? '') === 'end_failed'): ?>
            <div class="alert alert-error">Unable to end the session. Please try again.</div>
        <?php elseif (($_GET['error'] ?? '') === 'session_not_found'): ?>
            <div class="alert alert-error">Session was not found or you do not have access to it.</div>
        <?php endif; ?>

        <?php if (!$activeSession): ?>
            <section class="notice">
                <h2>No Scheduled Sessions</h2>
                <p class="muted" style="margin-top: 8px;">
                    No scheduled or ongoing attendance session was found from today through the next 3 days.
                    Create a session first, then RFID scans will appear here during the session window.
                </p>
                <div class="actions" style="margin-top: 16px;">
                    <a class="btn btn-primary" href="create_session.php">Create Attendance Session</a>
                </div>
            </section>
        <?php else: ?>
            <section class="session-list-card">
                <div class="session-list-header">
                    <div>
                        <div class="session-list-title">Upcoming Attendance Sessions</div>
                        <div class="session-list-note">Next 3 days. Select a session to view or manage its attendance.</div>
                    </div>
                </div>

                <div class="session-list">
                    <?php foreach ($upcomingSessions as $sessionOption): ?>
                        <a
                            class="session-row <?= (int)$sessionOption['session_id'] === (int)$activeSession['session_id'] ? 'active-row' : '' ?>"
                            href="live_attendance.php?session_id=<?= e($sessionOption['session_id']) ?>"
                        >
                            <div>
                                <?php if ((int)$sessionOption['session_id'] === (int)$activeSession['session_id']): ?>
                                    <div class="session-row-kicker">
                                        <?= $sessionOption['session_status'] === 'ongoing' ? 'Currently Active Session' : 'Next Scheduled Session' ?>
                                    </div>
                                <?php endif; ?>
                                <div class="session-row-title">
                                    <?= e($sessionOption['course_code']) ?> - <?= e($sessionOption['course_name']) ?>
                                </div>
                                <div class="session-row-meta">
                                    <span><?= e(date('D, d M Y', strtotime($sessionOption['session_date']))) ?></span>
                                    <span><?= e(substr($sessionOption['planned_start_time'], 0, 5)) ?> - <?= e(substr($sessionOption['planned_end_time'], 0, 5)) ?></span>
                                    <span><?= e($sessionOption['room_code'] ?: $sessionOption['room_name'] ?: 'Not assigned') ?></span>
                                </div>
                            </div>
                            <span class="badge"><?= e($sessionOption['session_status']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>

            <section class="panel session">
                <div>
                    <div class="section-label">Selected Session Details</div>
                    <div class="session-title">
                        <?= e($activeSession['course_code']) ?> - <?= e($activeSession['course_name']) ?>
                    </div>
                    <div class="session-meta">
                        <span>Date: <?= e($activeSession['session_date']) ?></span>
                        <span>Time: <?= e(substr($activeSession['planned_start_time'] ?: $activeSession['start_time'], 0, 5)) ?> - <?= e(substr($activeSession['planned_end_time'] ?: $activeSession['end_time'], 0, 5)) ?></span>
                        <span>Room: <?= e($activeSession['room_code'] ?: $activeSession['room_name'] ?: 'Not assigned') ?></span>
                        <span>Started: <?= e(formatTime($activeSession['actual_start_time'])) ?></span>
                    </div>
                </div>
                <div>
                    <span class="badge"><?= e($activeSession['session_status']) ?></span>
                    <?php if ($activeSession['session_status'] !== 'completed' && $activeSession['session_status'] !== 'cancelled'): ?>
                        <?php if ($canEndSession): ?>
                            <form method="POST" action="end_session.php" style="margin-top: 12px;">
                                <input type="hidden" name="session_id" value="<?= e($activeSession['session_id']) ?>">
                                <button class="btn" type="submit">End Session</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info" style="margin-top: 12px; margin-bottom: 0;">
                                Can end after <?= e(formatTime($activeSession['session_date'] . ' ' . $activeSession['planned_end_time'])) ?>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="actions" style="margin-top: 12px;">
                            <a class="btn" href="records.php?session_id=<?= e($activeSession['session_id']) ?>">View Record</a>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="stats">
                <div class="stat">
                    <div class="stat-value"><?= e($stats['present']) ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($stats['late']) ?></div>
                    <div class="stat-label">Late</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($stats['absent']) ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($stats['rate']) ?>%</div>
                    <div class="stat-label">Attendance Rate</div>
                </div>
            </section>

            <section class="grid">
                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Scanned Students</div>
                        <span class="muted small"><?= e(count($presentStudents)) ?> record(s)</span>
                    </div>

                    <?php if (!$presentStudents): ?>
                        <div class="empty">No RFID scans have been recorded for this session yet.</div>
                    <?php else: ?>
                        <table>
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Matric No</th>
                                    <th>Scan Time</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($presentStudents as $student): ?>
                                    <tr>
                                        <td>
                                            <div class="student"><?= e($student['full_name']) ?></div>
                                        </td>
                                        <td><?= e($student['matric_no']) ?></td>
                                        <td><?= e(formatTime($student['scan_time'])) ?></td>
                                        <td>
                                            <span class="badge <?= $student['status'] === 'late' ? 'badge-late' : 'badge-present' ?>">
                                                <?= e($student['status']) ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <div class="panel-title">Absent Students</div>
                        <span class="muted small">
                            <?= e(count($absentStudents)) ?>
                            <?= $activeSession['session_status'] === 'completed' ? 'marked absent' : 'remaining' ?>
                        </span>
                    </div>

                    <?php if (!$allStudents): ?>
                        <div class="empty">No registered students found for this course.</div>
                    <?php elseif (!$absentStudents): ?>
                        <div class="empty">All registered students have scanned for this session.</div>
                    <?php else: ?>
                        <div class="list">
                            <?php foreach ($absentStudents as $student): ?>
                                <div class="list-row">
                                    <div>
                                        <div class="student"><?= e($student['full_name']) ?></div>
                                        <div class="muted small"><?= e($student['matric_no']) ?></div>
                                    </div>
                                    <span class="badge badge-absent">Absent</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <section class="panel" style="margin-top: 18px;">
                <div class="panel-header">
                    <div class="panel-title">Recent RFID Scans</div>
                    <span class="muted small">Latest 10</span>
                </div>

                <?php if (!$recentScans): ?>
                    <div class="empty">No recent scans yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Time</th>
                                <th>Student</th>
                                <th>Matric No</th>
                                <th>Status</th>
                                <th>Late Minutes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScans as $scan): ?>
                                <tr>
                                    <td><?= e(formatTime($scan['scan_time'])) ?></td>
                                    <td><?= e($scan['full_name']) ?></td>
                                    <td><?= e($scan['matric_no']) ?></td>
                                    <td>
                                        <span class="badge <?= $scan['status'] === 'absent' ? 'badge-absent' : ($scan['status'] === 'late' ? 'badge-late' : 'badge-present') ?>">
                                            <?= e($scan['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= e((int)$scan['late_minutes']) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php if ($activeSession && in_array($activeSession['session_status'], ['scheduled', 'ongoing'], true)): ?>
        <script>
            setInterval(() => {
                window.location.reload();
            }, 7000);
        </script>
    <?php endif; ?>
</body>
</html>
