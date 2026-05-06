<!-- api/get_stats.php -->
<?php
require_once '../includes/config.php';
require_once '../includes/auth_check.php';

header('Content-Type: application/json');

$response = [];

try {
    // Get total students
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'student' AND is_active = 1");
    $response['total_students'] = $stmt->fetch()['count'];
    
    // Get total lecturers
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users WHERE role = 'lecturer' AND is_active = 1");
    $response['total_lecturers'] = $stmt->fetch()['count'];
    
    // Get active RFID cards
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM rfid_cards WHERE status = 'active'");
    $response['active_rfid'] = $stmt->fetch()['count'];
    
    // Get today's attendance percentage
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(DISTINCT student_id) as present,
            (SELECT COUNT(DISTINCT e.student_id) 
             FROM enrollments e
             JOIN attendance_sessions a_sess ON e.course_id = (
                 SELECT course_id FROM class_schedule WHERE schedule_id = a_sess.schedule_id
             )
             WHERE DATE(a_sess.session_date) = ?) as total
        FROM attendance_records ar
        JOIN attendance_sessions a_sess ON ar.session_id = a_sess.session_id
        WHERE DATE(a_sess.session_date) = ? 
        AND ar.status IN ('present', 'late')
    ");
    $stmt->execute([$today, $today]);
    $attendance = $stmt->fetch();
    
    $response['today_attendance'] = $attendance['total'] > 0 
        ? round(($attendance['present'] / $attendance['total']) * 100, 2) 
        : 0;
    
    // Get recent scans (last 10)
    $stmt = $pdo->prepare("
        SELECT 
            ar.scan_time,
            u.matric_no,
            u.full_name,
            c.course_code,
            c.course_name,
            ar.status,
            r.room_code,
            b.building_name
        FROM attendance_records ar
        JOIN users u ON ar.student_id = u.user_id
        JOIN attendance_sessions a_sess ON ar.session_id = a_sess.session_id
        JOIN class_schedule cs ON a_sess.schedule_id = cs.schedule_id
        JOIN courses c ON cs.course_id = c.course_id
        JOIN rooms r ON cs.room_id = r.room_id
        JOIN floors f ON r.floor_id = f.floor_id
        JOIN buildings b ON f.building_id = b.building_id
        ORDER BY ar.scan_time DESC
        LIMIT 10
    ");
    $stmt->execute();
    $response['recent_activity'] = $stmt->fetchAll();
    
    echo json_encode(['success' => true, 'data' => $response]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>