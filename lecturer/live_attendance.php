<?php
require_once '../includes/auth_check.php';
requireLecturerOrAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$userRole = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $userRole === 'admin';
$requestedSessionId = (int)($_GET['session_id'] ?? 0);
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
        s.attendance_method,
        s.qr_token,
        s.qr_expires_at,
        cs.course_id,
        cs.lecturer_id,
        cs.section_name,
        cs.academic_year,
        cs.semester_label,
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

$presentStudents = [];
$absentStudents = [];
$allStudents = [];
$recentScans = [];
$stats = [
    'present' => 0,
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
        markSessionAbsentees(
            $pdo,
            $sessionId,
            $courseId,
            $activeSession['section_name'] ?? 'Section 1',
            $activeSession['academic_year'] ?? '',
            $activeSession['session_date'],
            $activeSession['planned_end_time']
        );

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
                s.attendance_method,
                s.qr_token,
                s.qr_expires_at,
                cs.course_id,
                cs.lecturer_id,
                cs.section_name,
                cs.academic_year,
                cs.semester_label,
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
            u.profile_image,
            ar.scan_time,
            ar.status,
            ar.late_minutes
        FROM attendance_records ar
        JOIN users u ON ar.student_id = u.user_id
        WHERE ar.session_id = ?
          AND ar.status = 'present'
        ORDER BY ar.scan_time DESC
    ");
    $stmt->execute([$sessionId]);
    $presentStudents = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.matric_no,
            u.profile_image,
            ar.scan_time,
            ar.status,
            ar.late_minutes,
            er.status AS excuse_status
        FROM enrollments e
        JOIN users u ON e.student_id = u.user_id
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
          AND u.is_active = 1
        ORDER BY u.full_name
    ");
    $stmt->execute([
        $sessionId,
        $courseId,
        $activeSession['section_name'] ?? 'Section 1',
        $activeSession['academic_year'] ?? ''
    ]);
    $allStudents = $stmt->fetchAll();

    foreach ($allStudents as $student) {
        if (empty($student['scan_time']) || $student['status'] === 'absent') {
            $absentStudents[] = $student;
        }
    }

    $stmt = $pdo->prepare("
        SELECT
            ar.record_id,
            u.full_name,
            u.matric_no,
            u.profile_image,
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
    $stats['absent'] = count($absentStudents);
    $stats['rate'] = $stats['total'] > 0 ? round((count($presentStudents) / $stats['total']) * 100) : 0;

    $sessionEndTimestamp = strtotime($activeSession['session_date'] . ' ' . $activeSession['planned_end_time']);
    $canEndSession = $activeSession['session_status'] !== 'completed'
        && $activeSession['session_status'] !== 'cancelled'
        && $sessionEndTimestamp
        && time() >= $sessionEndTimestamp;
}

function markSessionAbsentees(PDO $pdo, int $sessionId, int $courseId, string $sectionName, string $academicYear, string $sessionDate, string $plannedEndTime): void {
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
              AND e.section_name = ?
              AND COALESCE(e.academic_year, '') = ?
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
        $stmt->execute([$sessionId, $absentScanTime, $courseId, $sectionName, $academicYear, $sessionId]);

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
                total_late = 0,
                total_absent = (
                    SELECT COUNT(*)
                    FROM attendance_records
                    WHERE session_id = ?
                      AND status = 'absent'
                )
            WHERE session_id = ?
        ");
        $stmt->execute([$sessionId, $sessionId, $sessionId]);

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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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

        .student-cell {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .student-photo {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #e2e8f0;
            flex: 0 0 auto;
        }

        .qr-box {
            grid-column: 1 / -1;
            width: min(620px, 100%);
            margin: 18px auto 0;
            padding: 22px;
            border: 1px solid #bfdbfe;
            border-radius: 18px;
            background:
                linear-gradient(180deg, rgba(255, 255, 255, 0.98), rgba(240, 253, 250, 0.95));
            display: grid;
            justify-items: center;
            align-items: center;
            gap: 14px;
            text-align: center;
            box-shadow: 0 18px 42px rgba(37, 99, 235, 0.12);
        }

        .qr-box img {
            width: 220px;
            height: 220px;
            border-radius: 18px;
            background: #fff;
            padding: 12px;
            border: 1px solid #dbeafe;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        }

        .qr-title {
            font-size: 18px;
            font-weight: 900;
            color: #0f172a;
        }

        .qr-help {
            max-width: 480px;
            color: #64748b;
            font-size: 14px;
            font-weight: 650;
        }

        .qr-link {
            max-width: 100%;
            word-break: break-all;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 700;
            margin-top: 2px;
            padding: 9px 12px;
            border-radius: 999px;
            background: #eff6ff;
            border: 1px solid #bfdbfe;
        }

        .session-main {
            display: contents;
        }

        .session-info {
            min-width: 0;
        }

        .scan-modal {
            position: fixed;
            inset: 0;
            z-index: 1000;
            display: none;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(15, 23, 42, 0.48);
            backdrop-filter: blur(3px);
        }

        .scan-modal.show {
            display: flex;
        }

        .scan-card {
            width: min(780px, 100%);
            display: grid;
            grid-template-columns: 0.9fr 1fr;
            gap: 22px;
            background: linear-gradient(145deg, #ffffff, #f8fff9);
            border: 2px solid #86efac;
            border-radius: 22px;
            box-shadow: 0 28px 70px rgba(15, 23, 42, 0.28);
            padding: 20px;
            position: relative;
        }

        .scan-close {
            position: absolute;
            top: 12px;
            right: 14px;
            width: 34px;
            height: 34px;
            border: 0;
            border-radius: 50%;
            background: #eef2ff;
            color: #334155;
            font-weight: 800;
            cursor: pointer;
        }

        .scan-photo {
            width: 100%;
            aspect-ratio: 4 / 5;
            object-fit: cover;
            border-radius: 18px;
            border: 3px solid #86efac;
            box-shadow: 0 14px 30px rgba(22, 163, 74, 0.18);
        }

        .scan-success {
            display: flex;
            gap: 12px;
            align-items: center;
            margin-bottom: 18px;
            color: #15803d;
            font-weight: 900;
            text-transform: uppercase;
        }

        .scan-success-icon {
            width: 54px;
            height: 54px;
            display: grid;
            place-items: center;
            border-radius: 50%;
            background: linear-gradient(135deg, #22c55e, #16a34a);
            color: #fff;
            font-size: 30px;
        }

        .scan-name {
            font-size: clamp(34px, 6vw, 58px);
            line-height: 1;
            color: #111827;
            margin-bottom: 8px;
        }

        .scan-matric {
            color: #4f46e5;
            font-size: 22px;
            font-weight: 800;
            margin-bottom: 18px;
        }

        .scan-detail {
            display: grid;
            grid-template-columns: 120px 1fr;
            gap: 8px;
            padding: 11px 0;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            font-size: 14px;
        }

        .scan-detail span:first-child {
            color: #64748b;
            font-weight: 700;
        }

        .scan-footer {
            margin-top: 18px;
            padding: 16px;
            border-radius: 14px;
            background: #ecfdf3;
            color: #166534;
            font-weight: 700;
        }

        @media (max-width: 720px) {
            .qr-box {
                padding: 18px;
            }

            .qr-box img {
                width: 190px;
                height: 190px;
            }

            .scan-card {
                grid-template-columns: 1fr;
            }

            .scan-photo {
                max-height: 360px;
            }
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
            .session {
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
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
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
            <section class="panel session">
                <div class="session-main">
                    <div class="session-info">
                    <div class="section-label">Selected Session Details</div>
                    <div class="session-title">
                        <?= e($activeSession['course_code']) ?> - <?= e($activeSession['course_name']) ?>
                    </div>
                    <div class="session-meta">
                        <span>Date: <?= e($activeSession['session_date']) ?></span>
                        <span>Time: <?= e(substr($activeSession['planned_start_time'] ?: $activeSession['start_time'], 0, 5)) ?> - <?= e(substr($activeSession['planned_end_time'] ?: $activeSession['end_time'], 0, 5)) ?></span>
                        <span>Room: <?= e($activeSession['room_code'] ?: $activeSession['room_name'] ?: 'Not assigned') ?></span>
                        <span>Method: <?= e(strtoupper($activeSession['attendance_method'] ?? 'rfid')) ?></span>
                        <span>Started: <?= e(formatTime($activeSession['actual_start_time'])) ?></span>
                    </div>
                    </div>
                    <?php if (in_array($activeSession['attendance_method'] ?? 'rfid', ['qr', 'both'], true) && !empty($activeSession['qr_token'])): ?>
                        <?php
                            $qrBaseUrl = defined('SITE_URL') ? rtrim(SITE_URL, '/') : '';
                            if ($qrBaseUrl === '') {
                                $qrBaseUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                                    . '://' . $_SERVER['HTTP_HOST']
                                    . dirname(dirname($_SERVER['SCRIPT_NAME']));
                            }
                            $qrUrl = $qrBaseUrl . '/student/scan_qr.php?token=' . urlencode($activeSession['qr_token']);
                        ?>
                        <div class="qr-box">
                            <img alt="Session QR Code" src="https://api.qrserver.com/v1/create-qr-code/?size=320x320&data=<?= urlencode($qrUrl) ?>">
                            <div>
                                <div class="qr-title">QR Attendance Link</div>
                                <div class="qr-help">Students can scan this QR for online/hybrid attendance until session end time.</div>
                                <div class="qr-link"><?= e($qrUrl) ?></div>
                            </div>
                        </div>
                    <?php endif; ?>
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
                                            <div class="student-cell">
                                                <img class="student-photo" src="<?= e(profileImageUrl($student['profile_image'] ?? '', $student['full_name'])) ?>" alt="<?= e($student['full_name']) ?>">
                                                <div class="student"><?= e($student['full_name']) ?></div>
                                            </div>
                                        </td>
                                        <td><?= e($student['matric_no']) ?></td>
                                        <td><?= e(formatTime($student['scan_time'])) ?></td>
                                        <td>
                                            <span class="badge badge-present">
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
                                    <div class="student-cell">
                                        <img class="student-photo" src="<?= e(profileImageUrl($student['profile_image'] ?? '', $student['full_name'])) ?>" alt="<?= e($student['full_name']) ?>">
                                        <div>
                                            <div class="student"><?= e($student['full_name']) ?></div>
                                            <div class="muted small"><?= e($student['matric_no']) ?></div>
                                        </div>
                                    </div>
                                    <div class="actions">
                                        <span class="badge badge-absent">Absent</span>
                                    </div>
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
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentScans as $scan): ?>
                                <tr>
                                    <td><?= e(formatTime($scan['scan_time'])) ?></td>
                                    <td>
                                        <div class="student-cell">
                                            <img class="student-photo" src="<?= e(profileImageUrl($scan['profile_image'] ?? '', $scan['full_name'])) ?>" alt="<?= e($scan['full_name']) ?>">
                                            <div><?= e($scan['full_name']) ?></div>
                                        </div>
                                    </td>
                                    <td><?= e($scan['matric_no']) ?></td>
                                    <td>
                                        <span class="badge <?= $scan['status'] === 'absent' ? 'badge-absent' : 'badge-present' ?>">
                                            <?= e($scan['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>

    <?php
        $latestScan = $recentScans[0] ?? null;
        $shouldShowScanModal = $activeSession
            && $latestScan
            && $latestScan['status'] === 'present'
            && strtotime($latestScan['scan_time']) >= time() - 30;
    ?>
    <?php if ($shouldShowScanModal): ?>
        <div class="scan-modal" id="scanSuccessModal" data-record-id="<?= e($latestScan['record_id']) ?>">
            <div class="scan-card">
                <button class="scan-close" type="button" onclick="closeScanModal()">&times;</button>
                <img class="scan-photo" src="<?= e(profileImageUrl($latestScan['profile_image'] ?? '', $latestScan['full_name'])) ?>" alt="<?= e($latestScan['full_name']) ?>">
                <div>
                    <div class="scan-success">
                        <div class="scan-success-icon">✓</div>
                        <div>
                            <div>Scan Successful</div>
                            <div style="font-size: 13px; text-transform: none; font-weight: 700;">Attendance recorded</div>
                        </div>
                    </div>
                    <div class="scan-name"><?= e($latestScan['full_name']) ?></div>
                    <div class="scan-matric"><?= e($latestScan['matric_no']) ?></div>
                    <div class="scan-detail">
                        <span>Course</span>
                        <strong><?= e($activeSession['course_code']) ?> - <?= e($activeSession['course_name']) ?></strong>
                    </div>
                    <div class="scan-detail">
                        <span>Scan Time</span>
                        <strong><?= e(formatTime($latestScan['scan_time'])) ?></strong>
                    </div>
                    <div class="scan-detail">
                        <span>Date</span>
                        <strong><?= e(date('d M Y', strtotime($latestScan['scan_time']))) ?></strong>
                    </div>
                    <div class="scan-detail">
                        <span>Status</span>
                        <strong><span class="badge badge-present"><?= e($latestScan['status']) ?></span></strong>
                    </div>
                    <div class="scan-footer">
                        Attendance recorded successfully.
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($activeSession && in_array($activeSession['session_status'], ['scheduled', 'ongoing'], true)): ?>
        <script>
            setInterval(() => {
                window.location.reload();
            }, 7000);
        </script>
    <?php endif; ?>
    <script>
        const scanModal = document.getElementById('scanSuccessModal');
        if (scanModal) {
            const recordKey = `scan_success_seen_${scanModal.dataset.recordId}`;
            if (!localStorage.getItem(recordKey)) {
                scanModal.classList.add('show');
                localStorage.setItem(recordKey, '1');
                setTimeout(closeScanModal, 6500);
            }
        }

        function closeScanModal() {
            const modal = document.getElementById('scanSuccessModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }
    </script>
</body>
</html>
