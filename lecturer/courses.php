<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.semester,
        c.academic_year,
        GROUP_CONCAT(DISTINCT cs.section_name ORDER BY cs.section_name SEPARATOR ', ') AS sections,
        COUNT(DISTINCT cs.schedule_id) AS schedule_count,
        COUNT(DISTINCT e.student_id) AS enrolled_count,
        COUNT(DISTINCT s.session_id) AS session_count,
        COALESCE(SUM(s.total_present), 0) AS attended_count,
        COALESCE(SUM(s.total_expected), 0) AS expected_count
    FROM courses c
    JOIN class_schedule cs ON c.course_id = cs.course_id
    LEFT JOIN enrollments e
        ON e.course_id = c.course_id
       AND e.section_name = cs.section_name
       AND COALESCE(e.academic_year, '') = COALESCE(cs.academic_year, '')
       AND e.status = 'registered'
    LEFT JOIN attendance_sessions s
        ON s.schedule_id = cs.schedule_id
    WHERE cs.lecturer_id = ?
      AND cs.is_active = 1
      AND c.is_active = 1
    GROUP BY c.course_id, c.course_code, c.course_name, c.credit_hours, c.semester, c.academic_year
    ORDER BY c.course_code
");
$stmt->execute([$lecturerId]);
$courses = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        cs.schedule_id,
        cs.course_id,
        cs.day_of_week,
        cs.start_time,
        cs.end_time,
        cs.section_name,
        cs.semester_label,
        cs.academic_year,
        cs.repeat_weekly,
        cs.start_date,
        cs.end_date,
        c.course_code,
        c.course_name,
        r.room_code,
        r.room_name
    FROM class_schedule cs
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE cs.lecturer_id = ?
      AND cs.is_active = 1
    ORDER BY c.course_code,
             FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'),
             cs.start_time
");
$stmt->execute([$lecturerId]);
$schedules = $stmt->fetchAll();

$schedulesByCourse = [];
foreach ($schedules as $schedule) {
    $schedulesByCourse[(int)$schedule['course_id']][] = $schedule;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function attendanceRate($attended, $expected) {
    return $expected > 0 ? round(($attended / $expected) * 100) : 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Courses</title>
    <style>
        :root {
            --green: #006837;
            --green-2: #0f7a4d;
            --blue: #4361ee;
            --teal: #0e8a83;
            --ink: #172033;
            --muted: #65728a;
            --line: #dce6f2;
            --surface: rgba(255, 255, 255, 0.94);
            --shadow: 0 20px 50px rgba(28, 52, 84, 0.12);
            --soft-shadow: 0 12px 28px rgba(28, 52, 84, 0.09);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background:
                radial-gradient(circle at 8% 8%, rgba(0, 104, 55, 0.16), transparent 30%),
                radial-gradient(circle at 92% 10%, rgba(67, 97, 238, 0.14), transparent 28%),
                linear-gradient(135deg, #f7fbff 0%, #edf8f3 100%);
            color: var(--ink);
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 28px;
        }
        .page { max-width: 1240px; margin: 0 auto; }
        .topbar {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 24px;
            padding: 26px 28px;
            background: linear-gradient(135deg, rgba(255, 255, 255, 0.96), rgba(248, 253, 251, 0.9));
            border: 1px solid rgba(220, 230, 242, 0.9);
            border-radius: 18px;
            box-shadow: var(--shadow);
        }
        h1 { font-size: 34px; line-height: 1.1; color: var(--ink); }
        h1::after {
            content: "";
            display: block;
            width: 58px;
            height: 3px;
            margin-top: 10px;
            border-radius: 999px;
            background: linear-gradient(90deg, var(--green), var(--blue), var(--teal));
        }
        .muted { color: var(--muted); margin-top: 6px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 46px;
            padding: 0 18px;
            border: 1px solid #cbd5e1;
            border-radius: 12px;
            background: #fff;
            color: var(--ink);
            font-weight: 800;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(28, 52, 84, 0.08);
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .btn:hover { transform: translateY(-1px); box-shadow: 0 12px 24px rgba(28, 52, 84, 0.12); }
        .btn-primary { background: linear-gradient(135deg, var(--green), var(--blue)); border-color: transparent; color: #fff; }
        .course-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(420px, 1fr));
            gap: 20px;
        }
        .card {
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            box-shadow: var(--soft-shadow);
            overflow: hidden;
            transition: transform 0.18s ease, box-shadow 0.18s ease;
        }
        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }
        .card-header {
            padding: 22px 24px;
            border-bottom: 1px solid var(--line);
            background: linear-gradient(135deg, rgba(0, 104, 55, 0.07), rgba(67, 97, 238, 0.06), rgba(14, 138, 131, 0.05));
        }
        .course-headline {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 16px;
        }
        .course-title { font-size: 21px; font-weight: 900; color: var(--ink); margin-top: 8px; }
        .course-code {
            display: inline-flex;
            padding: 6px 10px;
            border-radius: 999px;
            background: rgba(67, 97, 238, 0.10);
            color: var(--blue);
            font-weight: 900;
            font-size: 13px;
        }
        .section-note {
            display: inline-flex;
            margin-top: 12px;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(0, 104, 55, 0.10);
            color: var(--green);
            font-size: 13px;
            font-weight: 900;
        }
        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
            padding: 18px 22px;
        }
        .stat {
            position: relative;
            overflow: hidden;
            background: linear-gradient(180deg, #fff, #f8fbff);
            border: 1px solid var(--line) !important;
            border-top: 1px solid var(--line) !important;
            border-radius: 14px;
            padding: 14px 12px;
            min-height: 86px;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }
        .stat::before {
            content: "";
            position: absolute;
            inset: 0 0 auto 0;
            height: 4px;
            background: linear-gradient(90deg, var(--green), var(--blue));
        }
        .stat-value { font-size: 26px; line-height: 1; font-weight: 900; color: var(--ink); }
        .stat-label { color: var(--muted); font-size: 12px; font-weight: 800; margin-top: 8px; }
        .schedule-list { border-top: 1px solid var(--line); background: rgba(255, 255, 255, 0.72); }
        .schedule-row {
            display: grid;
            grid-template-columns: 1fr auto;
            align-items: center;
            gap: 16px;
            padding: 18px 22px;
            border-bottom: 1px solid #edf2f7;
        }
        .schedule-row:last-child { border-bottom: 0; }
        .schedule-main {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            font-weight: 900;
            color: var(--ink);
        }
        .day-pill {
            display: inline-flex;
            padding: 7px 11px;
            border-radius: 999px;
            background: rgba(0, 104, 55, 0.10);
            color: var(--green);
            font-size: 13px;
            font-weight: 900;
        }
        .schedule-meta { color: var(--muted); font-size: 13px; margin-top: 8px; line-height: 1.5; }
        .schedule-row .btn {
            min-width: 150px;
        }
        .empty {
            padding: 42px 24px;
            background: var(--surface);
            border: 1px solid var(--line);
            border-radius: 18px;
            color: var(--muted);
            text-align: center;
            box-shadow: var(--soft-shadow);
        }
        @media (max-width: 980px) {
            .course-grid { grid-template-columns: 1fr; }
        }
        @media (max-width: 760px) {
            body { padding: 16px; }
            .topbar { display: block; }
            .actions { margin-top: 14px; }
            .stats { grid-template-columns: repeat(2, minmax(0, 1fr)); }
            .schedule-row { grid-template-columns: 1fr; }
            .schedule-row .btn { width: 100%; }
        }
        @media (max-width: 520px) {
            .stats { grid-template-columns: 1fr; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
    <style>
        .course-grid .stat {
            border: 1px solid var(--line) !important;
            border-top: 1px solid var(--line) !important;
            box-shadow: none !important;
        }

        .course-grid .stat::before {
            height: 4px !important;
            background: linear-gradient(90deg, var(--green), var(--blue)) !important;
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>My Courses</h1>
                <p class="muted">Courses and class schedules assigned to your lecturer account.</p>
            </div>
            <div class="actions">
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <?php if (!$courses): ?>
            <div class="empty">No active courses are assigned to your account yet.</div>
        <?php else: ?>
            <section class="course-grid">
                <?php foreach ($courses as $course): ?>
                    <?php $rate = attendanceRate((int)$course['attended_count'], (int)$course['expected_count']); ?>
                    <article class="card">
                        <div class="card-header">
                            <div class="course-headline">
                                <div>
                                    <div class="course-code"><?= e($course['course_code']) ?></div>
                                    <div class="course-title"><?= e($course['course_name']) ?></div>
                                    <p class="muted">
                                        <?= e($course['credit_hours']) ?> credit hours
                                        <?= $course['semester'] ? ' | Semester ' . e($course['semester']) : '' ?>
                                        <?= $course['academic_year'] ? ' | ' . e($course['academic_year']) : '' ?>
                                    </p>
                                </div>
                            </div>
                            <div class="section-note">Sections: <?= e($course['sections'] ?: 'Not assigned') ?></div>
                        </div>

                        <div class="stats">
                            <div class="stat">
                                <div class="stat-value"><?= e((int)$course['enrolled_count']) ?></div>
                                <div class="stat-label">Students</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?= e((int)$course['schedule_count']) ?></div>
                                <div class="stat-label">Schedules</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?= e((int)$course['session_count']) ?></div>
                                <div class="stat-label">Sessions</div>
                            </div>
                            <div class="stat">
                                <div class="stat-value"><?= e($rate) ?>%</div>
                                <div class="stat-label">Attendance</div>
                            </div>
                        </div>

                        <div class="schedule-list">
                            <?php foreach ($schedulesByCourse[(int)$course['course_id']] ?? [] as $schedule): ?>
                                <div class="schedule-row">
                                    <div>
                                        <div class="schedule-main">
                                            <span class="day-pill"><?= e($schedule['day_of_week']) ?></span>
                                            <span><?= e(substr($schedule['start_time'], 0, 5)) ?> - <?= e(substr($schedule['end_time'], 0, 5)) ?></span>
                                        </div>
                                        <div class="schedule-meta">
                                            <?= e($schedule['section_name'] ?: 'Section 1') ?>
                                            <?= $schedule['semester_label'] ? ' | ' . e($schedule['semester_label']) : '' ?>
                                            | <?= e($schedule['room_code'] ?: $schedule['room_name'] ?: 'Room TBA') ?>
                                        </div>
                                    </div>
                                    <a class="btn" href="create_session.php?schedule_id=<?= e($schedule['schedule_id']) ?>">Create Session</a>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </section>
        <?php endif; ?>
    </main>
</body>
</html>
