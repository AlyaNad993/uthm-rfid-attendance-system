<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';

$lecturerId = (int)$_SESSION['user_id'];
$selectedScheduleId = (int)($_POST['schedule_id'] ?? $_GET['schedule_id'] ?? 0);
$error = '';
$success = '';

$stmt = $pdo->prepare("
    SELECT
        cs.schedule_id,
        cs.course_id,
        cs.start_time,
        cs.end_time,
        c.course_code,
        c.course_name,
        r.room_code,
        r.room_name
    FROM class_schedule cs
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE cs.lecturer_id = ?
      AND cs.is_active = 1
    ORDER BY c.course_code, cs.day_of_week, cs.start_time
");
$stmt->execute([$lecturerId]);
$schedules = $stmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $scheduleId = $selectedScheduleId;
    $sessionDate = trim($_POST['session_date'] ?? '');
    $startTime = trim($_POST['start_time'] ?? '');
    $endTime = trim($_POST['end_time'] ?? '');

    if (!$scheduleId || $sessionDate === '' || $startTime === '' || $endTime === '') {
        $error = 'Please fill in all fields.';
    } elseif ($startTime >= $endTime) {
        $error = 'End time must be later than start time.';
    } else {
        $stmt = $pdo->prepare("
            SELECT schedule_id
            FROM class_schedule
            WHERE schedule_id = ?
              AND lecturer_id = ?
              AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([$scheduleId, $lecturerId]);
        $schedule = $stmt->fetch();

        if (!$schedule) {
            $error = 'Selected class schedule was not found.';
        } else {
            $stmt = $pdo->prepare("
                SELECT session_id, session_status
                FROM attendance_sessions
                WHERE schedule_id = ?
                  AND session_date = ?
                LIMIT 1
            ");
            $stmt->execute([$scheduleId, $sessionDate]);
            $existingSession = $stmt->fetch();

            if ($existingSession && $existingSession['session_status'] === 'completed') {
                $error = 'A completed session already exists for this class and date. Choose another date, or review the completed session.';
            }
        }

        if (!$error) {
            $stmt = $pdo->prepare("
                SELECT COUNT(*)
                FROM enrollments e
                JOIN class_schedule cs ON e.course_id = cs.course_id
                WHERE cs.schedule_id = ?
                  AND e.status = 'registered'
            ");
            $stmt->execute([$scheduleId]);
            $totalExpected = (int)$stmt->fetchColumn();

            try {
                $stmt = $pdo->prepare("
                    INSERT INTO attendance_sessions
                        (schedule_id, lecturer_id, session_date, planned_start_time, planned_end_time, session_status, total_expected, total_present, total_late, total_absent)
                    VALUES
                        (?, ?, ?, ?, ?, 'scheduled', ?, 0, 0, 0)
                    ON DUPLICATE KEY UPDATE
                        lecturer_id = VALUES(lecturer_id),
                        planned_start_time = VALUES(planned_start_time),
                        planned_end_time = VALUES(planned_end_time),
                        session_status = 'scheduled',
                        total_expected = VALUES(total_expected)
                ");
                $stmt->execute([$scheduleId, $lecturerId, $sessionDate, $startTime, $endTime, $totalExpected]);

                $stmt = $pdo->prepare("
                    SELECT session_id
                    FROM attendance_sessions
                    WHERE schedule_id = ?
                      AND session_date = ?
                    LIMIT 1
                ");
                $stmt->execute([$scheduleId, $sessionDate]);
                $sessionId = (int)$stmt->fetchColumn();

                header("Location: live_attendance.php?session_id=" . $sessionId);
                exit;
            } catch (PDOException $e) {
                $error = 'Unable to create session: ' . $e->getMessage();
            }
        }
    }
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
    <title>Create Attendance Session</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f4f7fb;
            color: #1f2937;
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 24px;
        }
        .page { max-width: 820px; margin: 0 auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        h1 { font-size: 30px; line-height: 1.2; color: #111827; }
        .muted { color: #64748b; margin-top: 6px; }
        .card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            padding: 22px;
        }
        .form-grid { display: grid; gap: 18px; }
        label {
            display: block;
            margin-bottom: 8px;
            color: #334155;
            font-size: 14px;
            font-weight: 700;
        }
        select,
        input {
            width: 100%;
            min-height: 42px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            padding: 0 12px;
            color: #111827;
            font-size: 15px;
            background: #fff;
        }
        .time-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 15px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .alert {
            margin-bottom: 16px;
            padding: 12px 14px;
            border-radius: 8px;
            font-weight: 650;
        }
        .alert-error { background: #fee2e2; color: #991b1b; }
        @media (max-width: 700px) {
            body { padding: 16px; }
            .topbar,
            .time-grid { display: block; }
            .actions { margin-top: 14px; }
            .time-grid > div + div { margin-top: 18px; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Create Attendance Session</h1>
                <p class="muted">Set the class, date, and attendance scan window.</p>
            </div>
            <div class="actions">
                <a class="btn" href="dashboard.php">Dashboard</a>
                <a class="btn" href="live_attendance.php">Live Attendance</a>
            </div>
        </div>

        <section class="card">
            <?php if ($error): ?>
                <div class="alert alert-error"><?= e($error) ?></div>
            <?php endif; ?>

            <?php if (!$schedules): ?>
                <p class="muted">No class schedules are assigned to your lecturer account yet.</p>
            <?php else: ?>
                <form method="POST" class="form-grid">
                    <div>
                        <label for="schedule_id">Class</label>
                        <select id="schedule_id" name="schedule_id" required>
                            <option value="">Select class</option>
                            <?php foreach ($schedules as $schedule): ?>
                                <option
                                    value="<?= e($schedule['schedule_id']) ?>"
                                    data-start="<?= e(substr($schedule['start_time'], 0, 5)) ?>"
                                    data-end="<?= e(substr($schedule['end_time'], 0, 5)) ?>"
                                    <?= $selectedScheduleId === (int)$schedule['schedule_id'] ? 'selected' : '' ?>
                                >
                                    <?= e($schedule['course_code'] . ' - ' . $schedule['course_name']) ?>
                                    <?= $schedule['room_code'] ? ' | ' . e($schedule['room_code']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div>
                        <label for="session_date">Date</label>
                        <input id="session_date" name="session_date" type="date" value="<?= e(date('Y-m-d')) ?>" required>
                    </div>

                    <div class="time-grid">
                        <div>
                            <label for="start_time">Start Time</label>
                            <input id="start_time" name="start_time" type="time" required>
                        </div>
                        <div>
                            <label for="end_time">End Time</label>
                            <input id="end_time" name="end_time" type="time" required>
                        </div>
                    </div>

                    <div class="actions">
                        <button class="btn btn-primary" type="submit">Create Session</button>
                        <a class="btn" href="dashboard.php">Cancel</a>
                    </div>
                </form>
            <?php endif; ?>
        </section>
    </main>

    <script>
        const scheduleSelect = document.getElementById('schedule_id');
        const startInput = document.getElementById('start_time');
        const endInput = document.getElementById('end_time');

        if (scheduleSelect) {
            if (scheduleSelect.value) {
                const option = scheduleSelect.options[scheduleSelect.selectedIndex];
                if (option && option.dataset.start && option.dataset.end && !startInput.value && !endInput.value) {
                    startInput.value = option.dataset.start;
                    endInput.value = option.dataset.end;
                }
            }

            scheduleSelect.addEventListener('change', () => {
                const option = scheduleSelect.options[scheduleSelect.selectedIndex];
                if (option && option.dataset.start && option.dataset.end) {
                    startInput.value = option.dataset.start;
                    endInput.value = option.dataset.end;
                }
            });
        }
    </script>
</body>
</html>
