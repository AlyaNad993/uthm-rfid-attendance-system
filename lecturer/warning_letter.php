<?php
require_once '../includes/auth_check.php';
requireLecturerOrAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$sessionId = (int)($_GET['session_id'] ?? 0);
$studentId = (int)($_GET['student_id'] ?? 0);
$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = ($_SESSION['role'] ?? '') === 'admin';

$stmt = $pdo->prepare("
    SELECT
        s.session_id,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        cs.course_id,
        cs.lecturer_id,
        c.course_code,
        c.course_name,
        lecturer.full_name AS lecturer_name,
        student.full_name AS student_name,
        student.matric_no,
        ar.status
    FROM attendance_sessions s
    JOIN class_schedule cs ON cs.schedule_id = s.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    JOIN users lecturer ON lecturer.user_id = cs.lecturer_id
    JOIN users student ON student.user_id = ?
    LEFT JOIN attendance_records ar
        ON ar.session_id = s.session_id
       AND ar.student_id = student.user_id
    WHERE s.session_id = ?
      AND (? = 1 OR cs.lecturer_id = ?)
    LIMIT 1
");
$stmt->execute([$studentId, $sessionId, $isAdmin ? 1 : 0, $userId]);
$data = $stmt->fetch();

if (!$data || $data['status'] !== 'absent') {
    http_response_code(404);
    echo 'Warning letter is only available for absent students in your session.';
    exit;
}

$stmt = $pdo->prepare("
    SELECT status
    FROM excuse_requests
    WHERE session_id = ?
      AND student_id = ?
      AND status IN ('pending', 'approved')
    ORDER BY submitted_at DESC
    LIMIT 1
");
$stmt->execute([$sessionId, $studentId]);
$excuseStatus = $stmt->fetchColumn();

if ($excuseStatus) {
    http_response_code(409);
    echo 'Warning letter cannot be generated because the student has submitted an excuse document with status: ' . htmlspecialchars($excuseStatus, ENT_QUOTES, 'UTF-8') . '.';
    exit;
}

$reason = 'Absent from scheduled class without recorded attendance.';
$content = "WARNING LETTER\n\n"
    . "Date: " . date('d F Y') . "\n\n"
    . "To: {$data['student_name']} ({$data['matric_no']})\n\n"
    . "This letter is issued because you were marked absent for {$data['course_code']} - {$data['course_name']} on "
    . date('d F Y', strtotime($data['session_date'])) . " from "
    . substr($data['planned_start_time'], 0, 5) . " to " . substr($data['planned_end_time'], 0, 5) . ".\n\n"
    . "Please meet your lecturer, {$data['lecturer_name']}, if you have a valid reason or supporting document.\n\n"
    . "Regards,\n{$data['lecturer_name']}";

$stmt = $pdo->prepare("
    INSERT INTO warning_letters
        (session_id, student_id, lecturer_id, course_id, reason, letter_content, status)
    VALUES
        (?, ?, ?, ?, ?, ?, 'issued')
    ON DUPLICATE KEY UPDATE
        reason = VALUES(reason),
        letter_content = VALUES(letter_content),
        generated_at = CURRENT_TIMESTAMP,
        status = 'issued'
");
$stmt->execute([
    $sessionId,
    $studentId,
    $data['lecturer_id'],
    $data['course_id'],
    $reason,
    $content
]);

$letterId = (int)$pdo->lastInsertId();
if ($letterId === 0) {
    $stmt = $pdo->prepare("
        SELECT letter_id
        FROM warning_letters
        WHERE session_id = ?
          AND student_id = ?
        LIMIT 1
    ");
    $stmt->execute([$sessionId, $studentId]);
    $letterId = (int)($stmt->fetchColumn() ?: 0);
}

$notificationTitle = 'Warning Letter Issued';
$notificationMessage = sprintf(
    'You have received a warning letter for being absent from %s - %s on %s.',
    $data['course_code'],
    $data['course_name'],
    date('d M Y', strtotime($data['session_date']))
);
$relatedUrl = 'warning_letter.php?letter_id=' . $letterId;

$stmt = $pdo->prepare("
    SELECT notification_id
    FROM notifications
    WHERE user_id = ?
      AND title = ?
      AND related_url = ?
    LIMIT 1
");
$stmt->execute([$studentId, $notificationTitle, $relatedUrl]);

if (!$stmt->fetch()) {
    $stmt = $pdo->prepare("
        INSERT INTO notifications
            (user_id, title, message, related_url, type, is_read)
        VALUES
            (?, ?, ?, ?, 'alert', 0)
    ");
    $stmt->execute([
        $studentId,
        $notificationTitle,
        $notificationMessage,
        $relatedUrl,
    ]);
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
    <title>Warning Letter</title>
    <style>
        * { box-sizing: border-box; font-family: "Segoe UI", Arial, sans-serif; }
        body { margin: 0; background: #f4f7fb; color: #111827; padding: 28px; }
        .page { max-width: 820px; margin: 0 auto; }
        .actions { display: flex; justify-content: flex-end; gap: 10px; margin-bottom: 16px; }
        .btn { min-height: 40px; padding: 0 14px; border: 1px solid #cbd5e1; border-radius: 8px; background: #fff; cursor: pointer; font-weight: 700; }
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
            <button class="btn" onclick="window.print()">Print / Save PDF</button>
            <button class="btn" onclick="window.close()">Close</button>
        </div>
        <section class="letter">
            <h1>Warning Letter</h1>
            <div class="meta">Date: <?= e(date('d F Y')) ?></div>
            <p><strong>To:</strong> <?= e($data['student_name']) ?> (<?= e($data['matric_no']) ?>)</p>
            <p>
                This letter is issued because you were marked absent for
                <strong><?= e($data['course_code']) ?> - <?= e($data['course_name']) ?></strong>
                on <?= e(date('d F Y', strtotime($data['session_date']))) ?> from
                <?= e(substr($data['planned_start_time'], 0, 5)) ?> to <?= e(substr($data['planned_end_time'], 0, 5)) ?>.
            </p>
            <p>
                Please meet your lecturer if you have a valid reason or supporting document for this absence.
            </p>
            <div class="signature">
                <p>Regards,</p>
                <p><strong><?= e($data['lecturer_name']) ?></strong></p>
            </div>
        </section>
    </main>
</body>
</html>
