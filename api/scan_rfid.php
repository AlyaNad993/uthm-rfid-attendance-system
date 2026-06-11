<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: text/plain');

// Create MySQLi connection using config.php constants
mysqli_report(MYSQLI_REPORT_OFF);

$conn = new mysqli(
    DB_HOST,
    DB_USER,
    DB_PASS,
    DB_NAME,
    (int) DB_PORT
);

if ($conn->connect_error) {
    echo "DB_ERROR";
    exit;
}

$conn->set_charset("utf8mb4");

function scanColumnExists(mysqli $conn, string $table, string $column): bool {
    $safeTable = preg_replace('/[^A-Za-z0-9_]/', '', $table);
    $result = $conn->query("SHOW COLUMNS FROM `$safeTable`");

    if (!$result) {
        return false;
    }

    while ($row = $result->fetch_assoc()) {
        if (($row['Field'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

if (!scanColumnExists($conn, 'class_schedule', 'section_name')) {
    $conn->query("ALTER TABLE class_schedule ADD section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER lecturer_id");
}

if (!scanColumnExists($conn, 'class_schedule', 'academic_year')) {
    $conn->query("ALTER TABLE class_schedule ADD academic_year VARCHAR(20) NULL AFTER section_name");
}

if (!scanColumnExists($conn, 'class_schedule', 'semester_label')) {
    $conn->query("ALTER TABLE class_schedule ADD semester_label VARCHAR(60) NULL AFTER academic_year");
}

if (!scanColumnExists($conn, 'enrollments', 'section_name')) {
    $conn->query("ALTER TABLE enrollments ADD section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER course_id");
}

if (!scanColumnExists($conn, 'enrollments', 'academic_year')) {
    $conn->query("ALTER TABLE enrollments ADD academic_year VARCHAR(20) NULL AFTER section_name");
}

if (!isset($_GET['uid']) || trim($_GET['uid']) === '') {
    echo "NO_UID";
    exit;
}

$uid = strtoupper(trim($_GET['uid']));

$sql = "SELECT rc.card_id, rc.user_id, u.full_name, u.matric_no
        FROM rfid_cards rc
        JOIN users u ON rc.user_id = u.user_id
        WHERE rc.uid = ?
        AND rc.status = 'active'
        AND u.role = 'student'
        LIMIT 1";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo "DB_ERROR";
    exit;
}

$stmt->bind_param("s", $uid);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows == 0) {
    echo "CARD_NOT_REGISTERED";
    exit;
}

$student = $result->fetch_assoc();
$student_id = (int) $student['user_id'];

$sqlSession = "SELECT s.session_id, s.session_status, s.planned_start_time, s.planned_end_time
               FROM attendance_sessions s
               JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
               JOIN enrollments e ON e.course_id = cs.course_id
                                  AND e.section_name = cs.section_name
                                  AND COALESCE(e.academic_year, '') = COALESCE(cs.academic_year, '')
               WHERE s.session_date = CURDATE()
               AND s.session_status IN ('scheduled', 'ongoing')
               AND s.attendance_method IN ('rfid', 'both')
               AND NOW() BETWEEN CONCAT(s.session_date, ' ', s.planned_start_time)
                             AND CONCAT(s.session_date, ' ', s.planned_end_time)
               AND e.student_id = ?
               AND e.status = 'registered'
               ORDER BY FIELD(s.session_status, 'ongoing', 'scheduled'), s.planned_start_time DESC, s.session_id DESC
               LIMIT 1";

$stmtSession = $conn->prepare($sqlSession);

if (!$stmtSession) {
    echo "DB_ERROR";
    exit;
}

$stmtSession->bind_param("i", $student_id);
$stmtSession->execute();
$sessionResult = $stmtSession->get_result();

if ($sessionResult->num_rows == 0) {
    echo "NO_ACTIVE_SESSION";
    exit;
}

$session = $sessionResult->fetch_assoc();

$rfid_card_id = (int) $student['card_id'];
$session_id = (int) $session['session_id'];

if ($session['session_status'] === 'scheduled') {
    $sqlStartSession = "UPDATE attendance_sessions
                        SET session_status = 'ongoing',
                            actual_start_time = IFNULL(actual_start_time, NOW())
                        WHERE session_id = ?";

    $stmtStartSession = $conn->prepare($sqlStartSession);

    if (!$stmtStartSession) {
        echo "DB_ERROR";
        exit;
    }

    $stmtStartSession->bind_param("i", $session_id);
    $stmtStartSession->execute();
}

$sqlCheck = "SELECT record_id
             FROM attendance_records
             WHERE session_id = ?
             AND student_id = ?
             LIMIT 1";

$stmtCheck = $conn->prepare($sqlCheck);

if (!$stmtCheck) {
    echo "DB_ERROR";
    exit;
}

$stmtCheck->bind_param("ii", $session_id, $student_id);
$stmtCheck->execute();
$checkResult = $stmtCheck->get_result();

if ($checkResult->num_rows > 0) {
    echo "ALREADY_SCANNED|" . $student['full_name'] . "|" . $student['matric_no'];
    exit;
}

$sqlInsert = "INSERT INTO attendance_records
              (session_id, student_id, rfid_card_id, scan_time, status, late_minutes, manual_override)
              VALUES (?, ?, ?, NOW(), 'present', 0, 0)";

$stmtInsert = $conn->prepare($sqlInsert);

if (!$stmtInsert) {
    echo "DB_ERROR";
    exit;
}

$stmtInsert->bind_param("iii", $session_id, $student_id, $rfid_card_id);

if (!$stmtInsert->execute()) {
    echo "INSERT_FAILED";
    exit;
}

$sqlUpdateTotals = "UPDATE attendance_sessions
                    SET total_present = (
                            SELECT COUNT(*)
                            FROM attendance_records
                            WHERE session_id = ?
                            AND status = 'present'
                        ),
                        total_late = 0,
                        total_absent = (
                            SELECT COUNT(*)
                            FROM attendance_records
                            WHERE session_id = ?
                            AND status = 'absent'
                        )
                    WHERE session_id = ?";

$stmtUpdateTotals = $conn->prepare($sqlUpdateTotals);

if (!$stmtUpdateTotals) {
    echo "DB_ERROR";
    exit;
}

$stmtUpdateTotals->bind_param("iii", $session_id, $session_id, $session_id);
$stmtUpdateTotals->execute();

echo "SUCCESS|" . $student['full_name'] . "|" . $student['matric_no'];
exit;
?>