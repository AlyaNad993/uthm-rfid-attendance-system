<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

if (($_SESSION['role'] ?? '') !== 'student') {
    header('Location: ../unauthorized.php');
    exit();
}

$studentId = (int)($_SESSION['user_id'] ?? 0);
$letterId = (int)($_GET['letter_id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT
        wl.letter_id,
        wl.reason,
        wl.letter_content,
        wl.generated_at,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        c.course_code,
        c.course_name,
        lecturer.full_name AS lecturer_name,
        student.full_name AS student_name,
        student.matric_no
    FROM warning_letters wl
    JOIN attendance_sessions s ON s.session_id = wl.session_id
    JOIN courses c ON c.course_id = wl.course_id
    JOIN users lecturer ON lecturer.user_id = wl.lecturer_id
    JOIN users student ON student.user_id = wl.student_id
    WHERE wl.letter_id = ?
      AND wl.student_id = ?
      AND wl.status = 'issued'
    LIMIT 1
");
$stmt->execute([$letterId, $studentId]);
$letter = $stmt->fetch();

if (!$letter) {
    http_response_code(404);
    echo 'Warning letter was not found for your account.';
    exit;
}

$stmt = $pdo->prepare("
    UPDATE notifications
    SET is_read = 1
    WHERE user_id = ?
      AND related_url = ?
");
$stmt->execute([$studentId, 'warning_letter.php?letter_id=' . $letterId]);

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warning Letter</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { margin: 0; background: #f4f7fb; color: #111827; padding: 28px; }
        .page { max-width: 820px; margin: 0 auto; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        .btn { display: inline-flex; align-items: center; min-height: 40px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; color: #334155; cursor: pointer; font-weight: 700; text-decoration: none; }
        .letter { background: #fff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 42px; box-shadow: 0 14px 30px rgba(15, 23, 42, 0.08); line-height: 1.7; }
        h1 { text-align: center; margin-bottom: 28px; letter-spacing: 0; }
        .meta { color: #475569; margin-bottom: 22px; }
        .signature { margin-top: 46px; }
        @media print {
            body { background: #fff; padding: 0; }
            .actions { display: none; }
            .letter { box-shadow: none; border: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="actions">
            <a class="btn" href="dashboard.php">Back to Dashboard</a>
            <button class="btn" onclick="window.print()">Print / Save PDF</button>
        </div>
        <section class="letter">
            <h1>Warning Letter</h1>
            <div class="meta">Date issued: <?= e(date('d F Y', strtotime($letter['generated_at']))) ?></div>
            <p><strong>To:</strong> <?= e($letter['student_name']) ?> (<?= e($letter['matric_no']) ?>)</p>
            <p>
                This letter is issued because you were marked absent for
                <strong><?= e($letter['course_code']) ?> - <?= e($letter['course_name']) ?></strong>
                on <?= e(date('d F Y', strtotime($letter['session_date']))) ?> from
                <?= e(substr($letter['planned_start_time'], 0, 5)) ?> to <?= e(substr($letter['planned_end_time'], 0, 5)) ?>.
            </p>
            <p>
                Please meet your lecturer if you have a valid reason or supporting document for this absence.
            </p>
            <div class="signature">
                <p>Regards,</p>
                <p><strong><?= e($letter['lecturer_name']) ?></strong></p>
            </div>
        </section>
    </main>
</body>
</html>
