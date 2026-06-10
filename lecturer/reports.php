<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];
$selectedScheduleId = (int)($_GET['schedule_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        cs.schedule_id,
        c.course_id,
        c.course_code,
        c.course_name,
        cs.section_name,
        cs.academic_year,
        cs.semester_label
    FROM courses c
    JOIN class_schedule cs ON c.course_id = cs.course_id
    WHERE cs.lecturer_id = ?
      AND cs.is_active = 1
      AND c.is_active = 1
    ORDER BY c.course_code, cs.section_name, cs.semester_label
");
$stmt->execute([$lecturerId]);
$classes = $stmt->fetchAll();

if (!$selectedScheduleId && $classes) {
    $selectedScheduleId = (int)$classes[0]['schedule_id'];
}

$selectedClass = null;
foreach ($classes as $class) {
    if ((int)$class['schedule_id'] === $selectedScheduleId) {
        $selectedClass = $class;
        break;
    }
}

$summary = [
    'students' => 0,
    'sessions' => 0,
    'present' => 0,
    'absent' => 0,
    'rate' => 0,
];
$studentRows = [];
$sessionRows = [];

if ($selectedClass) {
    $stmt = $pdo->prepare("
        SELECT COUNT(DISTINCT e.student_id)
        FROM enrollments e
        JOIN class_schedule cs
          ON cs.course_id = e.course_id
         AND cs.section_name = e.section_name
         AND COALESCE(cs.academic_year, '') = COALESCE(e.academic_year, '')
        WHERE cs.schedule_id = ?
          AND e.status = 'registered'
    ");
    $stmt->execute([$selectedScheduleId]);
    $summary['students'] = (int)$stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT s.session_id) AS sessions,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) AS absent_count
        FROM attendance_sessions s
        JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
        LEFT JOIN attendance_records ar ON ar.session_id = s.session_id
        WHERE cs.lecturer_id = ?
          AND cs.schedule_id = ?
    ");
    $stmt->execute([$lecturerId, $selectedScheduleId]);
    $counts = $stmt->fetch();

    $summary['sessions'] = (int)($counts['sessions'] ?? 0);
    $summary['present'] = (int)($counts['present_count'] ?? 0);
    $summary['absent'] = (int)($counts['absent_count'] ?? 0);
    $attended = $summary['present'];
    $totalMarked = $attended + $summary['absent'];
    $summary['rate'] = $totalMarked > 0 ? round(($attended / $totalMarked) * 100) : 0;

    $stmt = $pdo->prepare("
        SELECT
            u.user_id,
            u.full_name,
            u.matric_no,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS present_count,
            SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) AS absent_count,
            SUM(CASE WHEN ar.status = 'absent' AND er_valid.excuse_id IS NULL THEN 1 ELSE 0 END) AS unexcused_absent_count,
            (
                SELECT s2.session_id
                FROM attendance_sessions s2
                JOIN class_schedule cs2 ON cs2.schedule_id = s2.schedule_id
                JOIN attendance_records ar2
                  ON ar2.session_id = s2.session_id
                 AND ar2.student_id = u.user_id
                 AND ar2.status = 'absent'
                LEFT JOIN excuse_requests er2
                  ON er2.record_id = ar2.record_id
                 AND er2.student_id = u.user_id
                 AND er2.status IN ('pending', 'approved')
                WHERE cs2.lecturer_id = ?
                  AND cs2.schedule_id = ?
                  AND er2.excuse_id IS NULL
                ORDER BY s2.session_date DESC, s2.planned_start_time DESC
                LIMIT 1
            ) AS latest_unexcused_session_id,
            COUNT(ar.record_id) AS total_records
        FROM enrollments e
        JOIN users u ON e.student_id = u.user_id
        JOIN class_schedule selected_cs
          ON selected_cs.course_id = e.course_id
         AND selected_cs.section_name = e.section_name
         AND COALESCE(selected_cs.academic_year, '') = COALESCE(e.academic_year, '')
        LEFT JOIN attendance_records ar
            ON ar.student_id = u.user_id
           AND ar.session_id IN (
                SELECT s.session_id
                FROM attendance_sessions s
                JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
                WHERE cs.lecturer_id = ?
                  AND cs.schedule_id = ?
           )
        LEFT JOIN excuse_requests er_valid
            ON er_valid.record_id = ar.record_id
           AND er_valid.student_id = u.user_id
           AND er_valid.status IN ('pending', 'approved')
        WHERE selected_cs.schedule_id = ?
          AND e.status = 'registered'
          AND u.role = 'student'
          AND u.is_active = 1
        GROUP BY u.user_id, u.full_name, u.matric_no
        ORDER BY u.full_name
    ");
    $stmt->execute([$lecturerId, $selectedScheduleId, $lecturerId, $selectedScheduleId, $selectedScheduleId]);
    $studentRows = $stmt->fetchAll();

    $stmt = $pdo->prepare("
        SELECT
            s.session_id,
            s.session_date,
            s.planned_start_time,
            s.planned_end_time,
            s.session_status,
            s.total_expected,
            s.total_present,
            s.total_absent
        FROM attendance_sessions s
        JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
        WHERE cs.lecturer_id = ?
          AND cs.schedule_id = ?
        ORDER BY s.session_date DESC, s.planned_start_time DESC
        LIMIT 12
    ");
    $stmt->execute([$lecturerId, $selectedScheduleId]);
    $sessionRows = $stmt->fetchAll();
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function studentRate($present, $absent) {
    $attended = (int)$present;
    $total = $attended + (int)$absent;
    return $total > 0 ? round(($attended / $total) * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Attendance Analytics</title>
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
            align-items: flex-start;
            justify-content: space-between;
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
        .filter {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
            padding: 16px 18px;
            margin-bottom: 18px;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        select {
            min-height: 40px;
            min-width: 280px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            background: #fff;
            color: #111827;
            font-size: 14px;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
            margin-bottom: 18px;
        }
        .stat,
        .panel {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .stat { padding: 16px; }
        .stat-value { font-size: 28px; font-weight: 850; color: #111827; }
        .stat-label { color: #64748b; font-size: 13px; font-weight: 750; margin-top: 3px; }
        .panel { overflow: hidden; margin-bottom: 18px; }
        .panel-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 18px;
            border-bottom: 1px solid #e2e8f0;
            font-weight: 850;
            color: #111827;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td {
            padding: 13px 18px;
            border-bottom: 1px solid #eef2f7;
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
        .good { background: #dcfce7; color: #166534; }
        .warn { background: #fef3c7; color: #92400e; }
        .bad { background: #fee2e2; color: #991b1b; }
        .letter-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 34px;
            padding: 0 12px;
            border-radius: 999px;
            border: 1px solid #fecaca;
            background: #fff7f7;
            color: #991b1b;
            font-size: 12px;
            font-weight: 900;
            text-decoration: none;
            white-space: nowrap;
        }
        .letter-muted {
            display: inline-flex;
            align-items: center;
            min-height: 30px;
            padding: 0 10px;
            border-radius: 999px;
            background: #f8fafc;
            color: #64748b;
            font-size: 12px;
            font-weight: 800;
        }
        .empty {
            padding: 28px 18px;
            color: #64748b;
            text-align: center;
        }
        @media (max-width: 980px) {
            body { padding: 16px; }
            .topbar { display: block; }
            .actions { margin-top: 14px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            table { display: block; overflow-x: auto; white-space: nowrap; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(0, 104, 55, 0.16), transparent 30%),
                radial-gradient(circle at 92% 10%, rgba(67, 97, 238, 0.14), transparent 28%),
                linear-gradient(135deg, #f7fbff 0%, #edf8f3 100%) !important;
        }
        .page { max-width: 1240px; }
        .topbar,
        .filter,
        .panel,
        .stat {
            border: 1px solid #dce6f2 !important;
            border-radius: 18px !important;
            box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12) !important;
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(250,253,252,.94)) !important;
        }
        .topbar {
            padding: 26px 28px;
            align-items: center;
        }
        .filter {
            padding: 18px 20px;
        }
        select {
            min-height: 48px;
            border-radius: 12px;
        }
        .btn {
            min-height: 46px;
            border-radius: 12px !important;
            font-weight: 800;
        }
        .btn-primary {
            background: linear-gradient(135deg, #006837, #4361ee) !important;
        }
        .stats {
            grid-template-columns: repeat(5, minmax(0, 1fr));
        }
        .stat {
            position: relative;
            overflow: hidden;
            padding: 18px !important;
        }
        .stat::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, #006837, #4361ee, #0e8a83);
        }
        .panel-header {
            background: linear-gradient(90deg, rgba(0,104,55,.08), rgba(67,97,238,.07)) !important;
            border-bottom: 1px solid #dce6f2 !important;
        }
        table th {
            background: linear-gradient(90deg, #f1f7fb, #eef8f2) !important;
        }
        .letter-action:hover {
            background: #fee2e2;
            transform: translateY(-1px);
        }
        @media (max-width: 980px) {
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Attendance Analytics</h1>
        <p class="muted">View student attendance records by class, section, and semester.</p>
            </div>
            <div class="actions">
                <a class="btn btn-primary" href="records.php">Session Records</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <?php if (!$classes): ?>
            <section class="panel"><div class="empty">No courses are assigned to your account yet.</div></section>
        <?php else: ?>
            <form class="filter" method="GET">
                <label for="schedule_id" style="font-weight: 800;">Class / Section</label>
                <select id="schedule_id" name="schedule_id">
                    <?php foreach ($classes as $class): ?>
                        <option value="<?= e($class['schedule_id']) ?>" <?= (int)$class['schedule_id'] === $selectedScheduleId ? 'selected' : '' ?>>
                            <?= e($class['course_code'] . ' - ' . $class['course_name']) ?>
                            | <?= e($class['section_name'] ?: 'Section 1') ?>
                            <?= $class['semester_label'] ? ' | ' . e($class['semester_label']) : '' ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <button class="btn btn-primary" type="submit">Apply</button>
            </form>

            <section class="stats">
                <div class="stat">
                    <div class="stat-value"><?= e($summary['students']) ?></div>
                    <div class="stat-label">Students</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($summary['sessions']) ?></div>
                    <div class="stat-label">Sessions</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($summary['present']) ?></div>
                    <div class="stat-label">Present</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($summary['absent']) ?></div>
                    <div class="stat-label">Absent</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?= e($summary['rate']) ?>%</div>
                    <div class="stat-label">Rate</div>
                </div>
            </section>

            <section class="panel">
                <div class="panel-header">
                    <span>Student Attendance</span>
                    <span class="muted">
                        <?= e($selectedClass['course_code'] ?? '') ?>
                        <?= $selectedClass ? ' | ' . e($selectedClass['section_name'] ?: 'Section 1') : '' ?>
                    </span>
                </div>
                <?php if (!$studentRows): ?>
                    <div class="empty">No registered students found for this course.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Student</th>
                                <th>Matric No</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>No Excuse</th>
                                <th>Attendance Rate</th>
                                <th>Warning Letter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($studentRows as $row): ?>
                                <?php
                                    $rate = studentRate($row['present_count'], $row['absent_count']);
                                    $badgeClass = $rate >= 80 ? 'good' : ($rate >= 50 ? 'warn' : 'bad');
                                    $unexcusedAbsences = (int)$row['unexcused_absent_count'];
                                    $latestUnexcusedSessionId = (int)($row['latest_unexcused_session_id'] ?? 0);
                                ?>
                                <tr>
                                    <td><?= e($row['full_name']) ?></td>
                                    <td><?= e($row['matric_no']) ?></td>
                                    <td><?= e((int)$row['present_count']) ?></td>
                                    <td><?= e((int)$row['absent_count']) ?></td>
                                    <td><span class="badge <?= $unexcusedAbsences > 3 ? 'bad' : 'warn' ?>"><?= e($unexcusedAbsences) ?></span></td>
                                    <td><span class="badge <?= e($badgeClass) ?>"><?= e($rate) ?>%</span></td>
                                    <td>
                                        <?php if ($unexcusedAbsences > 3 && $latestUnexcusedSessionId > 0): ?>
                                            <a class="letter-action" href="warning_letter.php?session_id=<?= e($latestUnexcusedSessionId) ?>&student_id=<?= e($row['user_id']) ?>" target="_blank">Warning Letter</a>
                                        <?php else: ?>
                                            <span class="letter-muted">Not eligible</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>

            <section class="panel">
                <div class="panel-header">Recent Sessions</div>
                <?php if (!$sessionRows): ?>
                    <div class="empty">No sessions have been created for this course yet.</div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Time</th>
                                <th>Status</th>
                                <th>Expected</th>
                                <th>Present</th>
                                <th>Absent</th>
                                <th>Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessionRows as $session): ?>
                                <tr>
                                    <td><?= e(date('d M Y', strtotime($session['session_date']))) ?></td>
                                    <td><?= e(substr($session['planned_start_time'], 0, 5)) ?> - <?= e(substr($session['planned_end_time'], 0, 5)) ?></td>
                                    <td><?= e(ucfirst($session['session_status'])) ?></td>
                                    <td><?= e((int)$session['total_expected']) ?></td>
                                    <td><?= e((int)$session['total_present']) ?></td>
                                    <td><?= e((int)$session['total_absent']) ?></td>
                                    <td><a class="btn" href="records.php?session_id=<?= e($session['session_id']) ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
