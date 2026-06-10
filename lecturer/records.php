<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$lecturerId = (int)$_SESSION['user_id'];
$selectedSessionId = (int)($_GET['session_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array(($_POST['manual_action'] ?? ''), ['mark_present', 'approve_excuse'], true)) {
    $manualAction = $_POST['manual_action'] ?? '';
    $manualSessionId = (int)($_POST['session_id'] ?? 0);
    $manualStudentId = (int)($_POST['student_id'] ?? 0);
    $excuseType = $_POST['excuse_type'] ?? '';

    if ($manualSessionId > 0 && $manualStudentId > 0) {
        try {
            $stmt = $pdo->prepare("
                SELECT
                    s.session_id,
                    cs.course_id,
                    cs.section_name,
                    cs.academic_year,
                    ar.record_id,
                    er.excuse_id
                FROM attendance_sessions s
                JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
                JOIN enrollments e
                    ON e.course_id = cs.course_id
                   AND e.section_name = cs.section_name
                   AND COALESCE(e.academic_year, '') = COALESCE(cs.academic_year, '')
                   AND e.student_id = ?
                   AND e.status = 'registered'
                JOIN users u
                    ON u.user_id = e.student_id
                   AND u.role = 'student'
                   AND u.is_active = 1
                LEFT JOIN attendance_records ar
                    ON ar.session_id = s.session_id
                   AND ar.student_id = u.user_id
                LEFT JOIN excuse_requests er
                    ON er.record_id = ar.record_id
                   AND er.student_id = u.user_id
                WHERE s.session_id = ?
                  AND cs.lecturer_id = ?
                LIMIT 1
            ");
            $stmt->execute([$manualStudentId, $manualSessionId, $lecturerId]);
            $manualSession = $stmt->fetch();

            if (!$manualSession) {
                throw new RuntimeException('Session or student not found.');
            }

            $pdo->beginTransaction();

            if ($manualAction === 'mark_present') {
                $stmt = $pdo->prepare("
                    UPDATE attendance_records
                    SET
                        scan_time = NOW(),
                        status = 'present',
                        late_minutes = 0,
                        manual_override = 1,
                        notes = 'Manually marked present by lecturer because RFID/QR scan was not available'
                    WHERE session_id = ?
                      AND student_id = ?
                ");
                $stmt->execute([$manualSessionId, $manualStudentId]);

                if ($stmt->rowCount() === 0) {
                    $stmt = $pdo->prepare("
                        INSERT INTO attendance_records
                            (session_id, student_id, rfid_card_id, scan_time, status, late_minutes, manual_override, notes)
                        VALUES
                            (?, ?, NULL, NOW(), 'present', 0, 1, 'Manually marked present by lecturer because RFID/QR scan was not available')
                    ");
                    $stmt->execute([$manualSessionId, $manualStudentId]);
                }

                $redirectFlag = 'manual_marked=1';
            } elseif ($manualAction === 'approve_excuse') {
                $allowedExcuses = [
                    'medical_certificate' => [
                        'status' => 'mc',
                        'label' => 'Medical Certificate',
                        'note' => 'Medical certificate approved by lecturer'
                    ],
                    'excused_with_permission' => [
                        'status' => 'excused',
                        'label' => 'Excused With Permission',
                        'note' => 'Excused with permission approved by lecturer'
                    ],
                ];

                if (!isset($allowedExcuses[$excuseType]) || empty($manualSession['record_id']) || empty($manualSession['excuse_id'])) {
                    throw new RuntimeException('Valid attached excuse request not found.');
                }

                $excuse = $allowedExcuses[$excuseType];

                $stmt = $pdo->prepare("
                    UPDATE attendance_records
                    SET
                        status = ?,
                        manual_override = 1,
                        notes = ?
                    WHERE record_id = ?
                      AND session_id = ?
                      AND student_id = ?
                ");
                $stmt->execute([
                    $excuse['status'],
                    $excuse['note'],
                    $manualSession['record_id'],
                    $manualSessionId,
                    $manualStudentId
                ]);

                $stmt = $pdo->prepare("
                    UPDATE excuse_requests
                    SET
                        status = 'approved',
                        excuse_type = ?,
                        notes = ?,
                        reviewed_at = NOW()
                    WHERE excuse_id = ?
                      AND student_id = ?
                ");
                $stmt->execute([
                    $excuseType,
                    $excuse['label'],
                    $manualSession['excuse_id'],
                    $manualStudentId
                ]);

                $redirectFlag = 'excuse_updated=1';
            } else {
                throw new RuntimeException('Unknown manual action.');
            }

            refreshSessionTotals($pdo, $manualSessionId);

            $pdo->commit();

            header('Location: records.php?session_id=' . $manualSessionId . '&' . $redirectFlag);
            exit;
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            header('Location: records.php?session_id=' . $manualSessionId . '&manual_error=1');
            exit;
        }
    }
}

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
                u.user_id,
                u.full_name,
                u.matric_no,
                u.profile_image,
                ar.record_id,
                ar.scan_time,
                ar.status,
                ar.notes,
                er.excuse_id,
                er.file_path AS excuse_file_path,
                er.original_name AS excuse_original_name,
                er.status AS excuse_status,
                er.excuse_type
            FROM attendance_records ar
            JOIN users u ON ar.student_id = u.user_id
            LEFT JOIN excuse_requests er
                ON er.record_id = ar.record_id
               AND er.student_id = u.user_id
            WHERE ar.session_id = ?
            ORDER BY FIELD(ar.status, 'present', 'absent', 'excused', 'mc'), u.full_name
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

function excuseTypeLabel(?string $value): string {
    return match ($value) {
        'medical_certificate' => 'Medical Certificate',
        'excused_with_permission' => 'Excused With Permission',
        default => 'Excuse'
    };
}

function attendanceStatusLabel(?string $value): string {
    return match ($value) {
        'mc' => 'Medical Certificate',
        'excused' => 'Excused',
        'absent' => 'Absent',
        default => 'Present'
    };
}

function initials(?string $name): string {
    $name = trim((string)$name);
    if ($name === '') {
        return 'ST';
    }

    $parts = preg_split('/\s+/', $name);
    $first = strtoupper(substr($parts[0] ?? 'S', 0, 1));
    $second = strtoupper(substr($parts[1] ?? ($parts[0] ?? 'T'), 0, 1));

    return $first . $second;
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
        .alert {
            margin-bottom: 16px;
            padding: 13px 15px;
            border-radius: 8px;
            font-weight: 750;
        }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
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
            grid-template-columns: repeat(3, minmax(0, 1fr));
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
        .records-table {
            table-layout: auto;
            min-width: 100%;
        }
        .records-table th:nth-child(1),
        .records-table td:nth-child(1) { width: 34%; }
        .records-table th:nth-child(2),
        .records-table td:nth-child(2) { width: 13%; }
        .records-table th:nth-child(3),
        .records-table td:nth-child(3) { width: 12%; }
        .records-table th:nth-child(4),
        .records-table td:nth-child(4) { width: 12%; }
        .records-table th:nth-child(5),
        .records-table td:nth-child(5) {
            width: 29%;
            min-width: 260px;
        }
        .student-cell {
            display: flex;
            align-items: center;
            gap: 12px;
            min-width: 0;
        }
        .student-avatar {
            width: 34px;
            height: 34px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            flex: 0 0 auto;
            color: #fff;
            background: linear-gradient(135deg, #006837, #4361ee);
            font-size: 12px;
            font-weight: 900;
            box-shadow: 0 8px 18px rgba(28, 52, 84, 0.14);
        }
        .student-avatar img {
            width: 100%;
            height: 100%;
            border-radius: inherit;
            object-fit: cover;
        }
        .student-name {
            min-width: 0;
            max-width: 100%;
            display: -webkit-box;
            -webkit-box-orient: vertical;
            -webkit-line-clamp: 2;
            overflow: hidden;
            line-height: 1.25;
            font-weight: 800;
            color: #172033;
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
        .excused { background: #e0f2fe; color: #0369a1; }
        .mc { background: #f3e8ff; color: #6b21a8; }
        .record-actions {
            display: grid;
            align-items: start;
            gap: 8px;
            width: 100%;
        }
        .manual-present-form { margin: 0; }
        .icon-action {
            min-width: 132px;
            height: 36px;
            padding: 0 12px;
            border: 0;
            border-radius: 10px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 15px;
            font-weight: 900;
            color: #fff;
            cursor: pointer;
            box-shadow: 0 8px 16px rgba(15, 23, 42, 0.10);
            white-space: nowrap;
        }
        .present-action {
            background: linear-gradient(135deg, #047857, #3b5bdb);
        }
        .excuse-review {
            display: grid;
            grid-template-columns: 1fr;
            align-items: center;
            gap: 8px;
            min-height: 38px;
            padding: 8px;
            border: 1px solid #dbe7f3;
            border-radius: 14px;
            background: #f8fbff;
        }
        .excuse-review-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 8px;
        }
        .excuse-chip {
            display: inline-flex;
            align-items: center;
            min-height: 26px;
            padding: 0 9px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 11px;
            font-weight: 900;
            text-transform: uppercase;
        }
        .excuse-form {
            display: grid;
            grid-template-columns: minmax(170px, 1fr) minmax(92px, auto);
            align-items: center;
            gap: 6px;
            min-width: 0;
        }
        .excuse-select {
            min-height: 32px;
            width: 100%;
            min-width: 0;
            border: 1px solid #cbd5e1;
            border-radius: 999px;
            padding: 0 10px;
            background: #fff;
            color: #1f2937;
            font: inherit;
            font-size: 12px;
            font-weight: 700;
        }
        .excuse-save {
            min-width: 92px;
            height: 34px;
            padding: 0 12px;
            border-radius: 10px;
            border: 0;
            background: #16a34a;
            color: #fff;
            font-weight: 900;
            cursor: pointer;
            white-space: nowrap;
        }
        .excuse-file {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 10px;
            background: #ecfeff;
            color: #0e7490;
            font-size: 12px;
            font-weight: 800;
            text-decoration: none;
            white-space: nowrap;
        }
        .action-note {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f8fafc;
            color: #64748b;
            font-size: 11px;
            font-weight: 700;
        }
        .approved-excuse-actions {
            align-items: center;
        }
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
            .records-table { table-layout: auto; min-width: 860px; }
            .record-actions { max-width: none; }
            .excuse-review { grid-template-columns: 1fr; }
            .excuse-form { grid-template-columns: minmax(170px, 1fr) minmax(92px, auto); }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
    <style>
        .record-actions .icon-action {
            min-height: 34px !important;
        }
        .record-actions .excuse-save {
            min-height: 32px !important;
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

        <?php if (isset($_GET['manual_marked'])): ?>
            <div class="alert alert-success">Manual attendance updated. The student has been marked present.</div>
        <?php endif; ?>

        <?php if (isset($_GET['manual_error'])): ?>
            <div class="alert alert-error">Unable to update manual attendance. Please try again.</div>
        <?php endif; ?>

        <?php if (isset($_GET['excuse_updated'])): ?>
            <div class="alert alert-success">Excuse request approved. Attendance status has been updated.</div>
        <?php endif; ?>

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
                            <div class="stat-value"><?= e((int)$selectedSession['total_absent']) ?></div>
                            <div class="stat-label">Absent</div>
                        </div>
                    </div>

                    <?php if (!$records): ?>
                        <div class="empty">No attendance records for this session yet.</div>
                    <?php else: ?>
                        <table class="records-table">
                            <thead>
                                <tr>
                                    <th>Student</th>
                                    <th>Matric No</th>
                                    <th>Status</th>
                                    <th>Scan Time</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($records as $record): ?>
                                    <tr>
                                        <td>
                                            <div class="student-cell" title="<?= e($record['full_name']) ?>">
                                                <span class="student-avatar">
                                                    <?php if (!empty($record['profile_image'])): ?>
                                                        <img src="<?= e(profileImageUrl($record['profile_image'], $record['full_name'])) ?>" alt="<?= e($record['full_name']) ?>">
                                                    <?php else: ?>
                                                        <?= e(initials($record['full_name'])) ?>
                                                    <?php endif; ?>
                                                </span>
                                                <span class="student-name"><?= e($record['full_name']) ?></span>
                                            </div>
                                        </td>
                                        <td><?= e($record['matric_no']) ?></td>
                                        <td><span class="badge <?= e($record['status']) ?>"><?= e(attendanceStatusLabel($record['status'])) ?></span></td>
                                        <td><?= e(formatTime($record['scan_time'])) ?></td>
                                        <td>
                                            <?php if ($record['status'] === 'absent'): ?>
                                                <div class="record-actions">
                                                    <form class="manual-present-form" method="POST" action="records.php?session_id=<?= e($selectedSessionId) ?>" onsubmit="return confirm('Mark this student as present manually?');">
                                                        <input type="hidden" name="manual_action" value="mark_present">
                                                        <input type="hidden" name="session_id" value="<?= e($selectedSessionId) ?>">
                                                        <input type="hidden" name="student_id" value="<?= e($record['user_id']) ?>">
                                                        <button class="icon-action present-action" type="submit" title="Mark present manually" aria-label="Mark present manually">Mark Present</button>
                                                    </form>
                                                    <?php if (!empty($record['excuse_id'])): ?>
                                                        <div class="excuse-review">
                                                            <div class="excuse-review-header">
                                                                <span class="excuse-chip">Excuse</span>
                                                                <a class="excuse-file" href="../<?= e(ltrim((string)$record['excuse_file_path'], '/')) ?>" target="_blank" title="<?= e($record['excuse_original_name']) ?>">View File</a>
                                                            </div>
                                                            <form class="excuse-form" method="POST" action="records.php?session_id=<?= e($selectedSessionId) ?>">
                                                                <input type="hidden" name="manual_action" value="approve_excuse">
                                                                <input type="hidden" name="session_id" value="<?= e($selectedSessionId) ?>">
                                                                <input type="hidden" name="student_id" value="<?= e($record['user_id']) ?>">
                                                                <select class="excuse-select" name="excuse_type" aria-label="Excuse type">
                                                                    <option value="medical_certificate" <?= $record['excuse_type'] === 'medical_certificate' ? 'selected' : '' ?>>Medical Certificate</option>
                                                                    <option value="excused_with_permission" <?= $record['excuse_type'] === 'excused_with_permission' ? 'selected' : '' ?>>Excused With Permission</option>
                                                                </select>
                                                                <button class="excuse-save" type="submit" title="Approve excuse" aria-label="Approve excuse">Approve</button>
                                                            </form>
                                                        </div>
                                                    <?php else: ?>
                                                        <span class="action-note">No excuse file</span>
                                                    <?php endif; ?>
                                                </div>
                                            <?php elseif (in_array($record['status'], ['mc', 'excused'], true)): ?>
                                                <div class="record-actions approved-excuse-actions">
                                                    <span class="action-note"><?= e(excuseTypeLabel($record['excuse_type'])) ?> approved</span>
                                                    <?php if (!empty($record['excuse_file_path'])): ?>
                                                        <a class="excuse-file" href="../<?= e(ltrim((string)$record['excuse_file_path'], '/')) ?>" target="_blank" title="<?= e($record['excuse_original_name']) ?>">View Letter</a>
                                                    <?php endif; ?>
                                                </div>
                                            <?php else: ?>
                                                <span class="muted">-</span>
                                            <?php endif; ?>
                                        </td>
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
