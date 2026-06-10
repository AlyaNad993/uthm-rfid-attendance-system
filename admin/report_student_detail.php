<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$studentId = (int)($_GET['student_id'] ?? 0);
$courseId = (int)($_GET['course_id'] ?? 0);
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-12-31');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-01-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-12-31');
}

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.matric_no,
        u.email,
        u.profile_image,
        c.course_id,
        c.course_code,
        c.course_name
    FROM users u
    JOIN enrollments e ON e.student_id = u.user_id AND e.status = 'registered'
    JOIN courses c ON c.course_id = e.course_id
    WHERE u.user_id = ?
      AND c.course_id = ?
    LIMIT 1
");
$stmt->execute([$studentId, $courseId]);
$profile = $stmt->fetch();

if (!$profile) {
    http_response_code(404);
    echo 'Student/course detail not found.';
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        ats.session_id,
        ats.session_date,
        TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
        TIME_FORMAT(ats.planned_end_time, '%H:%i') AS end_time,
        lecturer.full_name AS lecturer_name,
        COALESCE(r.room_code, r.room_name, '-') AS room,
        COALESCE(ar.status, 'not_marked') AS status,
        ar.scan_time,
        COALESCE(ar.late_minutes, 0) AS late_minutes,
        COALESCE(ar.manual_override, 0) AS manual_override,
        ar.notes,
        er.status AS excuse_status,
        er.original_name AS excuse_name,
        er.file_path AS excuse_file
    FROM class_schedule cs
    JOIN attendance_sessions ats ON ats.schedule_id = cs.schedule_id
    JOIN users lecturer ON lecturer.user_id = cs.lecturer_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id
    LEFT JOIN attendance_records ar
        ON ar.session_id = ats.session_id
       AND ar.student_id = ?
    LEFT JOIN excuse_requests er
        ON er.record_id = ar.record_id
       AND er.student_id = ?
    WHERE cs.course_id = ?
      AND ats.session_date BETWEEN ? AND ?
    ORDER BY ats.session_date DESC, ats.planned_start_time DESC
");
$stmt->execute([$studentId, $studentId, $courseId, $dateFrom, $dateTo]);
$records = $stmt->fetchAll();

$expected = count($records);
$attended = count(array_filter($records, fn($row) => $row['status'] === 'present'));
$absent = count(array_filter($records, fn($row) => $row['status'] === 'absent'));
$rate = $expected > 0 ? round(($attended / $expected) * 100) : 0;

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Detail | Reports</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --green: #087a55;
            --blue: #4361ee;
            --ink: #172033;
            --muted: #66738a;
            --line: #dce4ef;
            --soft: #f6fafc;
            --shadow: 0 18px 48px rgba(28, 52, 84, 0.12);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: "Segoe UI", system-ui, sans-serif;
        }

        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 10% 0%, rgba(67, 97, 238, 0.14), transparent 32%),
                radial-gradient(circle at 92% 12%, rgba(8, 122, 85, 0.16), transparent 28%),
                linear-gradient(135deg, #f5f8ff, #edf8f1);
            color: var(--ink);
            padding: 28px;
        }

        .page {
            max-width: 1180px;
            margin: 0 auto;
        }

        .topbar {
            position: relative;
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
            padding: 24px 26px;
            margin-bottom: 22px;
            background: rgba(255, 255, 255, 0.94);
            border: 1px solid rgba(220, 228, 239, 0.95);
            border-radius: 18px;
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .topbar::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 5px;
            background: linear-gradient(90deg, var(--green), #0e8a83, var(--blue));
        }

        h1 {
            font-size: clamp(30px, 4vw, 44px);
            line-height: 1.05;
            letter-spacing: 0;
        }

        h2 {
            font-size: 28px;
            line-height: 1.15;
        }

        .muted {
            color: var(--muted);
            line-height: 1.6;
        }

        .actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .btn {
            min-height: 52px;
            padding: 0 18px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            font-weight: 800;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            cursor: pointer;
            box-shadow: 0 10px 22px rgba(28, 52, 84, 0.08);
            white-space: nowrap;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--green), var(--blue));
            color: #fff;
            border-color: transparent;
        }

        .card {
            background: rgba(255, 255, 255, 0.96);
            border: 1px solid var(--line);
            border-radius: 18px;
            padding: 26px;
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .summary-card {
            display: grid;
            grid-template-columns: minmax(280px, 1fr) 1.25fr;
            gap: 24px;
            align-items: center;
        }

        .profile {
            display: flex;
            align-items: center;
            gap: 18px;
            min-width: 0;
        }

        .profile img {
            width: 104px;
            height: 104px;
            object-fit: cover;
            border-radius: 24px;
            border: 4px solid #fff;
            box-shadow: 0 16px 34px rgba(28, 52, 84, 0.14);
            flex: 0 0 auto;
        }

        .profile-meta {
            display: grid;
            gap: 4px;
            min-width: 0;
        }

        .course-chip {
            display: inline-flex;
            width: fit-content;
            margin-top: 8px;
            padding: 7px 11px;
            border-radius: 999px;
            background: linear-gradient(135deg, rgba(8, 122, 85, 0.10), rgba(67, 97, 238, 0.10));
            color: #245168;
            font-weight: 800;
            font-size: 13px;
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 14px;
        }

        .stat {
            border: 1px solid #e2eaf4;
            border-radius: 14px;
            background: linear-gradient(180deg, #fff, #f8fbff);
            padding: 18px;
            min-height: 118px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .stat strong {
            display: block;
            font-size: 34px;
            line-height: 1;
            margin-bottom: 10px;
        }

        .stat.rate strong { color: var(--green); }
        .stat.absent-count strong { color: #991b1b; }

        .table-card {
            background: rgba(255, 255, 255, 0.96);
            border-radius: 18px;
            border: 1px solid var(--line);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 18px 20px;
            border-bottom: 1px solid #e8eef6;
            background: linear-gradient(135deg, rgba(8, 122, 85, 0.06), rgba(67, 97, 238, 0.06));
        }

        .table-head h3 {
            font-size: 20px;
        }

        .record-count {
            padding: 7px 12px;
            border-radius: 999px;
            background: #fff;
            border: 1px solid #dbe5f0;
            color: var(--muted);
            font-weight: 800;
            font-size: 13px;
        }

        .table-wrap {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 920px;
        }

        th,
        td {
            padding: 14px 16px;
            border-bottom: 1px solid #edf1f5;
            text-align: left;
            vertical-align: middle;
        }

        th {
            background: #f5f8fc;
            color: #44556b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0;
        }

        tbody tr:hover td {
            background: #fbfdff;
        }

        .badge {
            display: inline-flex;
            min-width: 86px;
            justify-content: center;
            padding: 7px 11px;
            border-radius: 999px;
            background: #e0f2fe;
            color: #0369a1;
            font-size: 12px;
            font-weight: 900;
            text-transform: uppercase;
        }

        .present {
            background: #dcfce7;
            color: #166534;
        }

        .absent {
            background: #fee2e2;
            color: #991b1b;
        }

        .empty {
            padding: 42px;
            text-align: center;
            color: var(--muted);
        }

        @media (max-width: 980px) {
            .summary-card {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            body {
                padding: 16px;
            }

            .topbar {
                flex-direction: column;
                align-items: stretch;
            }

            .actions,
            .btn {
                width: 100%;
            }

            .profile {
                align-items: flex-start;
            }

            .profile img {
                width: 82px;
                height: 82px;
                border-radius: 20px;
            }

            .stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 520px) {
            .stats {
                grid-template-columns: 1fr;
            }
        }

        @media print {
            body {
                background: #fff;
                padding: 0;
            }

            .page {
                max-width: none;
            }

            .topbar,
            .card,
            .table-card {
                box-shadow: none;
            }

            .actions {
                display: none;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Attendance Detail</h1>
                <p class="muted">Student attendance monitoring by selected course.</p>
            </div>
            <div class="actions">
                <a class="btn" href="reports.php?course_id=<?= e($courseId) ?>&date_from=<?= e($dateFrom) ?>&date_to=<?= e($dateTo) ?>">
                    <i class="fas fa-arrow-left"></i> Back to Reports
                </a>
                <button class="btn btn-primary" onclick="window.print()"><i class="fas fa-print"></i> Print</button>
            </div>
        </div>

        <section class="card summary-card">
            <div class="profile">
                <img src="<?= e(profileImageUrl($profile['profile_image'] ?? '', $profile['full_name'])) ?>" alt="<?= e($profile['full_name']) ?>">
                <div class="profile-meta">
                    <h2><?= e($profile['full_name']) ?></h2>
                    <div class="muted"><?= e($profile['matric_no']) ?> / <?= e($profile['email']) ?></div>
                    <div class="muted"><?= e($dateFrom) ?> to <?= e($dateTo) ?></div>
                    <div class="course-chip"><?= e($profile['course_code']) ?> - <?= e($profile['course_name']) ?></div>
                </div>
            </div>
            <div class="stats">
                <div class="stat rate"><strong><?= e($rate) ?>%</strong><span class="muted">Attendance Rate</span></div>
                <div class="stat"><strong><?= e($attended) ?>/<?= e($expected) ?></strong><span class="muted">Attended</span></div>
                <div class="stat absent-count"><strong><?= e($absent) ?></strong><span class="muted">Absent</span></div>
            </div>
        </section>

        <section class="table-card">
            <div class="table-head">
                <div>
                    <h3>Attendance Records</h3>
                    <p class="muted">Session-by-session details for this student.</p>
                </div>
                <div class="record-count"><?= e($expected) ?> sessions</div>
            </div>
            <?php if (!$records): ?>
                <div class="empty">No sessions found for this course and date range.</div>
            <?php else: ?>
                <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Time</th>
                            <th>Lecturer</th>
                            <th>Room</th>
                            <th>Status</th>
                            <th>Scan Time</th>
                            <th>Source</th>
                            <th>Excuse / MC</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($records as $record): ?>
                            <?php $statusClass = in_array($record['status'], ['present', 'absent'], true) ? $record['status'] : ''; ?>
                            <tr>
                                <td><?= e($record['session_date']) ?></td>
                                <td><?= e($record['start_time']) ?> - <?= e($record['end_time']) ?></td>
                                <td><?= e($record['lecturer_name']) ?></td>
                                <td><?= e($record['room']) ?></td>
                                <td><span class="badge <?= e($statusClass) ?>"><?= e($record['status']) ?></span></td>
                                <td><?= e($record['scan_time'] ?: '-') ?></td>
                                <td><?= ((int)$record['manual_override'] === 1) ? 'Manual' : ($record['scan_time'] ? 'RFID/QR' : '-') ?></td>
                                <td>
                                    <?php if ($record['excuse_status']): ?>
                                        <span class="badge"><?= e($record['excuse_status']) ?></span>
                                        <?php if ($record['excuse_file']): ?>
                                            <br><a class="muted" href="../<?= e($record['excuse_file']) ?>" target="_blank"><?= e($record['excuse_name'] ?: 'View file') ?></a>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
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
