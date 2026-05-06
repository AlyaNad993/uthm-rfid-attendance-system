<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];
$month = $_GET['month'] ?? date('Y-m');

if (!preg_match('/^\d{4}-\d{2}$/', $month)) {
    $month = date('Y-m');
}

$monthStart = new DateTimeImmutable($month . '-01');
$monthEnd = $monthStart->modify('last day of this month');
$calendarStart = $monthStart->modify('monday this week');
$calendarEnd = $monthEnd->modify('sunday this week');
$prevMonth = $monthStart->modify('-1 month')->format('Y-m');
$nextMonth = $monthStart->modify('+1 month')->format('Y-m');

$stmt = $pdo->prepare("
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
    WHERE cs.lecturer_id = ?
      AND s.session_date BETWEEN ? AND ?
    ORDER BY s.session_date, s.planned_start_time
");
$stmt->execute([
    $lecturerId,
    $calendarStart->format('Y-m-d'),
    $calendarEnd->format('Y-m-d'),
]);

$sessionsByDate = [];
foreach ($stmt->fetchAll() as $session) {
    $sessionsByDate[$session['session_date']][] = $session;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Calendar</title>
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
        .calendar {
            display: grid;
            grid-template-columns: repeat(7, minmax(0, 1fr));
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
            background: #fff;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
        }
        .weekday {
            background: #f8fafc;
            color: #475569;
            font-size: 12px;
            font-weight: 800;
            padding: 12px;
            text-transform: uppercase;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
        }
        .weekday:nth-child(7) { border-right: 0; }
        .day {
            min-height: 150px;
            padding: 10px;
            border-right: 1px solid #e2e8f0;
            border-bottom: 1px solid #e2e8f0;
            background: #fff;
        }
        .day:nth-child(7n) { border-right: 0; }
        .day.outside { background: #f8fafc; color: #94a3b8; }
        .day.today { box-shadow: inset 0 0 0 2px #2563eb; }
        .day-number {
            font-weight: 800;
            margin-bottom: 8px;
            color: #111827;
        }
        .outside .day-number { color: #94a3b8; }
        .session {
            display: block;
            padding: 8px;
            margin-top: 7px;
            border-radius: 8px;
            background: #eff6ff;
            color: #1e40af;
            text-decoration: none;
            font-size: 12px;
            line-height: 1.3;
            border: 1px solid #bfdbfe;
        }
        .session.ongoing {
            background: #dcfce7;
            color: #166534;
            border-color: #bbf7d0;
        }
        .session.completed {
            background: #f1f5f9;
            color: #475569;
            border-color: #cbd5e1;
        }
        .session.cancelled {
            background: #fee2e2;
            color: #991b1b;
            border-color: #fecaca;
        }
        .session-title { font-weight: 800; }
        .session-meta { margin-top: 4px; }
        @media (max-width: 900px) {
            body { padding: 16px; }
            .topbar { display: block; }
            .actions { margin-top: 14px; }
            .calendar { display: block; border: 0; box-shadow: none; background: transparent; }
            .weekday { display: none; }
            .day {
                min-height: auto;
                margin-bottom: 10px;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            }
            .day.outside { display: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Schedule Calendar</h1>
                <p class="muted">Sessions shown here are created attendance sessions, including future sessions.</p>
            </div>
            <div class="actions">
                <a class="btn" href="calendar.php?month=<?= e($prevMonth) ?>">Previous</a>
                <a class="btn" href="calendar.php?month=<?= e($nextMonth) ?>">Next</a>
                <a class="btn btn-primary" href="create_session.php">Create Session</a>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <h2 style="margin-bottom: 14px; color: #111827;"><?= e($monthStart->format('F Y')) ?></h2>

        <section class="calendar">
            <?php foreach (['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $dayName): ?>
                <div class="weekday"><?= e($dayName) ?></div>
            <?php endforeach; ?>

            <?php for ($day = $calendarStart; $day <= $calendarEnd; $day = $day->modify('+1 day')): ?>
                <?php
                    $dateKey = $day->format('Y-m-d');
                    $isOutside = $day->format('Y-m') !== $monthStart->format('Y-m');
                    $isToday = $dateKey === date('Y-m-d');
                ?>
                <div class="day <?= $isOutside ? 'outside' : '' ?> <?= $isToday ? 'today' : '' ?>">
                    <div class="day-number"><?= e($day->format('j')) ?></div>

                    <?php foreach ($sessionsByDate[$dateKey] ?? [] as $session): ?>
                        <a class="session <?= e($session['session_status']) ?>" href="live_attendance.php?session_id=<?= e($session['session_id']) ?>">
                            <div class="session-title"><?= e($session['course_code']) ?></div>
                            <div><?= e(substr($session['planned_start_time'], 0, 5)) ?> - <?= e(substr($session['planned_end_time'], 0, 5)) ?></div>
                            <div class="session-meta"><?= e($session['room_code'] ?: $session['room_name'] ?: 'Room TBA') ?></div>
                            <div class="session-meta"><?= e(ucfirst($session['session_status'])) ?></div>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </section>
    </main>
</body>
</html>
