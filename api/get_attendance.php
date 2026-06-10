<?php
require_once '../includes/auth_check.php';
requireLecturerOrAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

header('Content-Type: application/json');

$userRole = $_SESSION['role'] ?? '';
$userId = (int)($_SESSION['user_id'] ?? 0);
$isAdmin = $userRole === 'admin';
$sessionId = (int)($_GET['session_id'] ?? 0);

if ($sessionId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Missing session_id'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        s.session_id,
        s.session_status,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        cs.course_id,
        cs.lecturer_id,
        cs.section_name,
        cs.academic_year,
        c.course_code,
        c.course_name
    FROM attendance_sessions s
    JOIN class_schedule cs ON cs.schedule_id = s.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    WHERE s.session_id = ?
      AND (? = 1 OR cs.lecturer_id = ?)
    LIMIT 1
");
$stmt->execute([$sessionId, $isAdmin ? 1 : 0, $userId]);
$session = $stmt->fetch();

if (!$session) {
    http_response_code(404);
    echo json_encode([
        'success' => false,
        'error' => 'Session not found'
    ]);
    exit;
}

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.matric_no,
        ar.scan_time,
        ar.status
    FROM attendance_records ar
    JOIN users u ON u.user_id = ar.student_id
    WHERE ar.session_id = ?
      AND ar.status = 'present'
    ORDER BY ar.scan_time DESC
");
$stmt->execute([$sessionId]);
$presentStudents = $stmt->fetchAll();

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.full_name,
        u.matric_no,
        ar.scan_time,
        COALESCE(ar.status, 'absent') AS status
    FROM enrollments e
    JOIN users u ON u.user_id = e.student_id
    LEFT JOIN attendance_records ar
        ON ar.student_id = u.user_id
       AND ar.session_id = ?
    WHERE e.course_id = ?
      AND e.section_name = ?
      AND COALESCE(e.academic_year, '') = ?
      AND e.status = 'registered'
      AND u.role = 'student'
      AND u.is_active = 1
    ORDER BY u.full_name
");
$stmt->execute([
    $sessionId,
    (int)$session['course_id'],
    $session['section_name'] ?? 'Section 1',
    $session['academic_year'] ?? ''
]);
$allStudents = $stmt->fetchAll();

$absentStudents = array_values(array_filter($allStudents, function ($student) {
    return empty($student['scan_time']) || $student['status'] === 'absent';
}));

$stmt = $pdo->prepare("
    SELECT
        ar.record_id,
        u.full_name,
        u.matric_no,
        ar.scan_time,
        ar.status
    FROM attendance_records ar
    JOIN users u ON u.user_id = ar.student_id
    WHERE ar.session_id = ?
    ORDER BY ar.scan_time DESC
    LIMIT 10
");
$stmt->execute([$sessionId]);
$recentScans = $stmt->fetchAll();

$total = count($allStudents);
$present = count($presentStudents);
$absent = count($absentStudents);

echo json_encode([
    'success' => true,
    'session' => [
        'session_id' => (int)$session['session_id'],
        'status' => $session['session_status'],
        'course_code' => $session['course_code'],
        'course_name' => $session['course_name'],
        'date' => $session['session_date'],
        'start_time' => $session['planned_start_time'],
        'end_time' => $session['planned_end_time'],
    ],
    'stats' => [
        'present' => $present,
        'absent' => $absent,
        'total' => $total,
        'rate' => $total > 0 ? round(($present / $total) * 100) : 0,
    ],
    'present_students' => $presentStudents,
    'absent_students' => $absentStudents,
    'recent_scans' => $recentScans,
]);
