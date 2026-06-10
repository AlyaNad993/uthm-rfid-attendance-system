<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$token = trim($_GET['token'] ?? $_POST['token'] ?? '');
$message = '';
$messageType = 'error';
$session = null;

if ($token !== '') {
    $stmt = $pdo->prepare("
        SELECT
            s.session_id,
            s.schedule_id,
            s.session_date,
            s.planned_start_time,
            s.planned_end_time,
            s.session_status,
            s.attendance_method,
            c.course_id,
            c.course_code,
            c.course_name,
            cs.section_name,
            cs.academic_year
        FROM attendance_sessions s
        JOIN class_schedule cs ON cs.schedule_id = s.schedule_id
        JOIN courses c ON c.course_id = cs.course_id
        WHERE s.qr_token = ?
          AND s.attendance_method IN ('qr', 'both')
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $session = $stmt->fetch();
}

if (!$session) {
    $message = 'Invalid or expired QR code.';
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $identifier = trim($_POST['identifier'] ?? '');
    $studentId = (int)($_SESSION['role'] === 'student' ? ($_SESSION['user_id'] ?? 0) : 0);

    if (!$studentId && $identifier !== '') {
        $stmt = $pdo->prepare("
            SELECT user_id
            FROM users
            WHERE role = 'student'
              AND is_active = 1
              AND (matric_no = ? OR email = ?)
            LIMIT 1
        ");
        $stmt->execute([$identifier, $identifier]);
        $studentId = (int)($stmt->fetchColumn() ?: 0);
    }

    $now = time();
    $start = strtotime($session['session_date'] . ' ' . $session['planned_start_time']);
    $end = strtotime($session['session_date'] . ' ' . $session['planned_end_time']);

    if (!$studentId) {
        $message = 'Please enter a valid matric number or email.';
    } elseif ($now < $start || $now > $end) {
        $message = 'This QR attendance is only valid during the session time.';
    } else {
        $stmt = $pdo->prepare("
            SELECT enrollment_id
            FROM enrollments
            WHERE student_id = ?
              AND course_id = ?
              AND section_name = ?
              AND COALESCE(academic_year, '') = ?
              AND status = 'registered'
            LIMIT 1
        ");
        $stmt->execute([
            $studentId,
            $session['course_id'],
            $session['section_name'] ?? 'Section 1',
            $session['academic_year'] ?? ''
        ]);

        if (!$stmt->fetch()) {
            $message = 'You are not enrolled in this course.';
        } else {
            $stmt = $pdo->prepare("
                SELECT record_id
                FROM attendance_records
                WHERE session_id = ?
                  AND student_id = ?
                LIMIT 1
            ");
            $stmt->execute([$session['session_id'], $studentId]);

            if ($stmt->fetch()) {
                $messageType = 'success';
                $message = 'Attendance already recorded for this session.';
            } else {
                if ($session['session_status'] === 'scheduled') {
                    $stmt = $pdo->prepare("
                        UPDATE attendance_sessions
                        SET session_status = 'ongoing',
                            actual_start_time = IFNULL(actual_start_time, NOW())
                        WHERE session_id = ?
                    ");
                    $stmt->execute([$session['session_id']]);
                }

                $stmt = $pdo->prepare("
                    INSERT INTO attendance_records
                        (session_id, student_id, rfid_card_id, scan_time, status, late_minutes, notes)
                    VALUES
                        (?, ?, NULL, NOW(), 'present', 0, 'Recorded by QR code')
                ");
                $stmt->execute([$session['session_id'], $studentId]);

                $stmt = $pdo->prepare("
                    UPDATE attendance_sessions
                    SET
                        total_present = (
                            SELECT COUNT(*) FROM attendance_records WHERE session_id = ? AND status = 'present'
                        ),
                        total_late = (
                            0
                        ),
                        total_absent = (
                            SELECT COUNT(*) FROM attendance_records WHERE session_id = ? AND status = 'absent'
                        )
                    WHERE session_id = ?
                ");
                $stmt->execute([$session['session_id'], $session['session_id'], $session['session_id']]);

                $messageType = 'success';
                $message = 'Attendance recorded successfully.';
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
    <title>QR Attendance</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { min-height: 100vh; margin: 0; background: linear-gradient(135deg, #eef2ff, #ecfdf5); display: grid; place-items: center; padding: 20px; color: #111827; }
        .card { width: min(460px, 100%); background: #fff; border: 1px solid #dbe3ef; border-radius: 12px; box-shadow: 0 18px 38px rgba(15, 23, 42, 0.12); padding: 24px; }
        h1 { margin: 0 0 8px; font-size: 28px; }
        .muted { color: #64748b; line-height: 1.5; }
        label { display: block; margin: 18px 0 8px; font-weight: 700; }
        input { width: 100%; min-height: 44px; border: 1px solid #cbd5e1; border-radius: 8px; padding: 0 12px; font-size: 15px; }
        .btn { width: 100%; min-height: 44px; border: 0; border-radius: 8px; background: #2563eb; color: #fff; font-weight: 800; margin-top: 16px; cursor: pointer; }
        .alert { padding: 12px 14px; border-radius: 8px; margin: 14px 0; font-weight: 700; }
        .success { background: #dcfce7; color: #166534; }
        .error { background: #fee2e2; color: #991b1b; }
    </style>
</head>
<body>
    <main class="card">
        <h1>QR Attendance</h1>
        <?php if ($session): ?>
            <p class="muted"><?= e($session['course_code']) ?> - <?= e($session['course_name']) ?><br>
            <?= e($session['session_date']) ?>, <?= e(substr($session['planned_start_time'], 0, 5)) ?> - <?= e(substr($session['planned_end_time'], 0, 5)) ?></p>
        <?php endif; ?>

        <?php if ($message): ?>
            <div class="alert <?= e($messageType) ?>"><?= e($message) ?></div>
        <?php endif; ?>

        <?php if ($session && $messageType !== 'success'): ?>
            <form method="POST">
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <?php if (($_SESSION['role'] ?? '') !== 'student'): ?>
                    <label for="identifier">Matric No or Email</label>
                    <input id="identifier" name="identifier" placeholder="DI230078 or email@uthm.edu.my" required>
                <?php endif; ?>
                <button class="btn" type="submit">Record Attendance</button>
            </form>
        <?php endif; ?>
    </main>
</body>
</html>
