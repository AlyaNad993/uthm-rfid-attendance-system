<?php
require_once '../includes/auth_check.php';
requireLecturerOrAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$userRole = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$sessionId = (int)($_POST['session_id'] ?? $_GET['session_id'] ?? 0);

if (!$sessionId) {
    header('Location: live_attendance.php?error=missing_session');
    exit;
}

$sessionSql = "
    SELECT
        s.session_id,
        s.schedule_id,
        s.session_date,
        s.planned_end_time,
        s.session_status,
        cs.course_id,
        cs.lecturer_id,
        cs.section_name,
        cs.academic_year
    FROM attendance_sessions s
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    WHERE s.session_id = ?
";
$params = [$sessionId];

if ($userRole !== 'admin') {
    $sessionSql .= " AND cs.lecturer_id = ?";
    $params[] = $userId;
}

$stmt = $pdo->prepare($sessionSql);
$stmt->execute($params);
$session = $stmt->fetch();

if (!$session) {
    header('Location: live_attendance.php?error=session_not_found');
    exit;
}

if ($session['session_status'] === 'completed') {
    header('Location: live_attendance.php?session_id=' . $sessionId . '&ended=1');
    exit;
}

$sessionEndTimestamp = strtotime($session['session_date'] . ' ' . $session['planned_end_time']);
if ($sessionEndTimestamp && time() < $sessionEndTimestamp) {
    header('Location: live_attendance.php?session_id=' . $sessionId . '&error=too_early');
    exit;
}

try {
    $pdo->beginTransaction();

    $absentScanTime = $session['session_date'] . ' ' . ($session['planned_end_time'] ?: date('H:i:s'));

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
            'Auto-marked absent when session ended'
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
    $stmt->execute([
        $sessionId,
        $absentScanTime,
        (int)$session['course_id'],
        $session['section_name'] ?? 'Section 1',
        $session['academic_year'] ?? '',
        $sessionId,
    ]);

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
            total_late = (
                0
            ),
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
    header('Location: live_attendance.php?session_id=' . $sessionId . '&ended=1');
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: live_attendance.php?session_id=' . $sessionId . '&error=end_failed');
    exit;
}
