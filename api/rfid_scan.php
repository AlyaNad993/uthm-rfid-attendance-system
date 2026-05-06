<!-- api/rfid_scan.php -->
<?php
require_once '../includes/config.php';

header('Content-Type: application/json');

// Allow CORS for RFID readers
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

$input = json_decode(file_get_contents('php://input'), true);
$response = [];

try {
    if (!isset($input['card_id']) || !isset($input['reader_id'])) {
        throw new Exception('Missing required parameters');
    }
    
    $card_id = $input['card_id'];
    $reader_id = $input['reader_id'];
    $scan_time = date('Y-m-d H:i:s');
    
    // 1. Verify RFID card
    $stmt = $pdo->prepare("
        SELECT rc.*, u.user_id, u.matric_no, u.full_name, u.role
        FROM rfid_cards rc
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.card_id = ? AND rc.status = 'active'
        AND u.is_active = 1
    ");
    $stmt->execute([$card_id]);
    $card_info = $stmt->fetch();
    
    if (!$card_info) {
        throw new Exception('Invalid or inactive RFID card');
    }
    
    // 2. Get reader location and active session
    $stmt = $pdo->prepare("
        SELECT r.*, rm.room_id, cs.schedule_id, a_sess.session_id
        FROM rfid_readers r
        JOIN rooms rm ON r.room_id = rm.room_id
        LEFT JOIN class_schedule cs ON rm.room_id = cs.room_id 
            AND DAYOFWEEK(CURDATE()) = 
                CASE cs.day_of_week
                    WHEN 'Monday' THEN 2
                    WHEN 'Tuesday' THEN 3
                    WHEN 'Wednesday' THEN 4
                    WHEN 'Thursday' THEN 5
                    WHEN 'Friday' THEN 6
                    WHEN 'Saturday' THEN 7
                END
            AND TIME(NOW()) BETWEEN cs.start_time AND cs.end_time
            AND cs.is_active = 1
        LEFT JOIN attendance_sessions a_sess ON cs.schedule_id = a_sess.schedule_id 
            AND DATE(a_sess.session_date) = CURDATE()
            AND a_sess.session_status IN ('scheduled', 'ongoing')
        WHERE r.reader_id = ?
        LIMIT 1
    ");
    $stmt->execute([$reader_id]);
    $reader_info = $stmt->fetch();
    
    if (!$reader_info) {
        throw new Exception('RFID reader not found');
    }
    
    // 3. Check if student is enrolled in this course
    if ($reader_info['session_id']) {
        $stmt = $pdo->prepare("
            SELECT e.* 
            FROM enrollments e
            JOIN class_schedule cs ON e.course_id = cs.course_id
            WHERE e.student_id = ? 
            AND cs.schedule_id = ?
            AND e.status = 'registered'
        ");
        $stmt->execute([$card_info['user_id'], $reader_info['schedule_id']]);
        $enrollment = $stmt->fetch();
        
        if (!$enrollment && $card_info['role'] == 'student') {
            throw new Exception('Student not enrolled in this course');
        }
    }
    
    // 4. Calculate if late
    $status = 'present';
    $late_minutes = 0;
    
    if ($reader_info['session_id'] && $card_info['role'] == 'student') {
        $stmt = $pdo->prepare("
            SELECT cs.start_time 
            FROM class_schedule cs
            JOIN attendance_sessions a_sess ON cs.schedule_id = a_sess.schedule_id
            WHERE a_sess.session_id = ?
        ");
        $stmt->execute([$reader_info['session_id']]);
        $schedule = $stmt->fetch();
        
        if ($schedule) {
            $start_time = strtotime($schedule['start_time']);
            $scan_timestamp = strtotime($scan_time);
            $late_minutes = round(($scan_timestamp - $start_time) / 60);
            
            if ($late_minutes > 15) {
                $status = 'late';
            }
        }
    }
    
    // 5. Insert attendance record
    $stmt = $pdo->prepare("
        INSERT INTO attendance_records 
        (session_id, student_id, rfid_card_id, scan_time, reader_id, status, late_minutes)
        VALUES (?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
        scan_time = VALUES(scan_time),
        status = VALUES(status),
        late_minutes = VALUES(late_minutes)
    ");
    
    $stmt->execute([
        $reader_info['session_id'] ?: NULL,
        $card_info['user_id'],
        $card_id,
        $scan_time,
        $reader_id,
        $status,
        $late_minutes
    ]);
    
    // 6. Update session statistics if applicable
    if ($reader_info['session_id']) {
        $stmt = $pdo->prepare("
            UPDATE attendance_sessions 
            SET 
                total_present = total_present + 1,
                total_late = total_late + ?,
                session_status = 'ongoing'
            WHERE session_id = ?
        ");
        $stmt->execute([$status == 'late' ? 1 : 0, $reader_info['session_id']]);
    }
    
    // 7. Log the scan
    $stmt = $pdo->prepare("
        INSERT INTO system_logs 
        (user_id, action_type, action_description, table_affected, record_id, ip_address)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $card_info['user_id'],
        'rfid_scan',
        'RFID attendance scan',
        'attendance_records',
        $pdo->lastInsertId(),
        $_SERVER['REMOTE_ADDR']
    ]);
    
    // 8. Send response
    $response = [
        'success' => true,
        'message' => 'Attendance recorded successfully',
        'data' => [
            'student_name' => $card_info['full_name'],
            'matric_no' => $card_info['matric_no'],
            'scan_time' => $scan_time,
            'status' => $status,
            'room' => $reader_info['room_code'] ?? 'Unknown',
            'late_minutes' => $late_minutes
        ]
    ];
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage()
    ];
}

echo json_encode($response);
?>