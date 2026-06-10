<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

function countRows($pdo, $sql) {
    try {
        return (int) $pdo->query($sql)->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

function adminDashboardJsonResponse($ok, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message
    ], $extra));
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_student') {
            $studentId = strtoupper(trim($_POST['student_id'] ?? ''));
            $studentName = trim($_POST['student_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $semester = trim($_POST['semester'] ?? '');

            if ($studentId === '' || $studentName === '' || $email === '') {
                adminDashboardJsonResponse(false, 'Please fill in student ID, name and email.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (matric_no, full_name, email, phone, password_hash, role, department, faculty, is_active)
                VALUES
                    (?, ?, ?, ?, ?, 'student', ?, 'OTHER', 1)
            ");
            $stmt->execute([
                $studentId,
                $studentName,
                $email,
                $phone,
                password_hash('password123', PASSWORD_DEFAULT),
                trim($department . ($semester !== '' ? ' Semester ' . $semester : ''))
            ]);

            adminDashboardJsonResponse(true, 'Student added successfully.', [
                'user_id' => $pdo->lastInsertId()
            ]);
        }

        if ($action === 'register_rfid') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $uid = strtoupper(trim($_POST['uid'] ?? ''));
            $cardType = trim($_POST['card_type'] ?? 'student');
            $status = ($_POST['activate_now'] ?? '1') === '1' ? 'active' : 'inactive';

            if ($userId <= 0 || $uid === '') {
                adminDashboardJsonResponse(false, 'Please select a student and enter RFID UID.');
            }

            if (!in_array($cardType, ['student', 'lecturer', 'staff'], true)) {
                $cardType = 'student';
            }

            $userStmt = $pdo->prepare("SELECT user_id FROM users WHERE user_id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            if (!$userStmt->fetch()) {
                adminDashboardJsonResponse(false, 'Selected user was not found.');
            }

            $cardId = 'CARD' . date('YmdHis') . random_int(10, 99);
            $stmt = $pdo->prepare("
                INSERT INTO rfid_cards
                    (card_id, user_id, uid, card_type, issue_date, status, registered_by)
                VALUES
                    (?, ?, ?, ?, CURDATE(), ?, ?)
            ");
            $stmt->execute([$cardId, $userId, $uid, $cardType, $status, $_SESSION['user_id']]);

            adminDashboardJsonResponse(true, 'RFID card registered successfully.');
        }

        if ($action === 'save_timetable') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $day = trim($_POST['day'] ?? 'monday');
            $startTime = trim($_POST['start_time'] ?? '');
            $duration = max(1, min(4, (int)($_POST['duration'] ?? 2)));
            $roomCode = trim($_POST['room'] ?? '');

            $dayMap = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday'
            ];

            if ($courseId <= 0 || $startTime === '' || $roomCode === '') {
                adminDashboardJsonResponse(false, 'Please select course, time and room.');
            }

            if (!isset($dayMap[$day])) {
                adminDashboardJsonResponse(false, 'Invalid timetable day.');
            }

            $lecturerStmt = $pdo->query("SELECT user_id FROM users WHERE role = 'lecturer' AND is_active = 1 ORDER BY user_id LIMIT 1");
            $lecturer = $lecturerStmt->fetch();
            if (!$lecturer) {
                adminDashboardJsonResponse(false, 'Please add at least one lecturer before creating a timetable.');
            }

            $roomStmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_code = ? OR room_name = ? LIMIT 1");
            $roomStmt->execute([$roomCode, $roomCode]);
            $room = $roomStmt->fetch();
            if (!$room) {
                adminDashboardJsonResponse(false, 'Room not found. Please use an existing room code.');
            }

            $endTime = date('H:i:s', strtotime($startTime . ' +' . $duration . ' hours'));
            $stmt = $pdo->prepare("
                INSERT INTO class_schedule
                    (course_id, lecturer_id, room_id, day_of_week, start_time, end_time, repeat_weekly, start_date, end_date, is_active)
                VALUES
                    (?, ?, ?, ?, ?, ?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 1)
            ");
            $stmt->execute([$courseId, $lecturer['user_id'], $room['room_id'], $dayMap[$day], $startTime, $endTime]);

            adminDashboardJsonResponse(true, 'Timetable saved successfully.');
        }

        adminDashboardJsonResponse(false, 'Unknown action.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            adminDashboardJsonResponse(false, 'Duplicate data found. Please check ID, email, RFID UID or schedule.');
        }
        adminDashboardJsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

$totalStudents = countRows($pdo, "SELECT COUNT(*) FROM users WHERE role = 'student'");
$totalLecturers = countRows($pdo, "SELECT COUNT(*) FROM users WHERE role = 'lecturer'");
$totalCourses = countRows($pdo, "SELECT COUNT(*) FROM courses");
$activeCards = countRows($pdo, "SELECT COUNT(*) FROM rfid_cards WHERE status = 'active'");

$presentCount = countRows($pdo, "
    SELECT COUNT(*)
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ats.session_id = ar.session_id
    WHERE ats.session_date = CURDATE() AND ar.status = 'present'
");
$absentCount = countRows($pdo, "
    SELECT COUNT(*)
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ats.session_id = ar.session_id
    WHERE ats.session_date = CURDATE() AND ar.status = 'absent'
");

$todayAttendance = $presentCount + $absentCount;
$totalToday = max($todayAttendance, 1);
$attendanceRate = round(($presentCount / $totalToday) * 100, 1);

$loggedToday = countRows($pdo, "
    SELECT COUNT(DISTINCT ar.student_id)
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ats.session_id = ar.session_id
    WHERE ats.session_date = CURDATE()
");
$totalSchedules = countRows($pdo, "SELECT COUNT(*) FROM class_schedule");

$studentOptions = $pdo->query("
    SELECT user_id, matric_no, full_name
    FROM users
    WHERE role = 'student' AND is_active = 1
    ORDER BY full_name
")->fetchAll();

$courseOptions = $pdo->query("
    SELECT course_id, course_code, course_name
    FROM courses
    WHERE is_active = 1
    ORDER BY course_code
")->fetchAll();

$recentLogsStmt = $pdo->query("
    SELECT
        DATE_FORMAT(ar.scan_time, '%H:%i') AS scan_time,
        c.course_code,
        u.matric_no,
        u.full_name,
        COALESCE(rc.uid, '-') AS uid,
        ar.status
    FROM attendance_records ar
    JOIN users u ON u.user_id = ar.student_id
    LEFT JOIN rfid_cards rc ON rc.card_id = ar.rfid_card_id
    JOIN attendance_sessions ats ON ats.session_id = ar.session_id
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    ORDER BY ar.scan_time DESC, ar.record_id DESC
    LIMIT 8
");
$recentLogs = $recentLogsStmt->fetchAll();

$recentActivityStmt = $pdo->query("
    SELECT
        ats.session_id,
        c.course_code,
        c.course_name,
        ats.session_date,
        TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
        TIME_FORMAT(ats.planned_end_time, '%H:%i') AS end_time,
        ats.session_status,
        ats.total_expected,
        ats.total_present,
        ats.total_absent
    FROM attendance_sessions ats
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    ORDER BY ats.session_date DESC, ats.planned_start_time DESC, ats.session_id DESC
    LIMIT 6
");
$recentActivities = $recentActivityStmt->fetchAll();

$monthlyRows = $pdo->query("
    SELECT
        MONTH(ats.session_date) AS month_no,
        COUNT(ar.record_id) AS total_records,
        SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS attended_records
    FROM attendance_sessions ats
    LEFT JOIN attendance_records ar ON ar.session_id = ats.session_id
    WHERE YEAR(ats.session_date) = YEAR(CURDATE())
    GROUP BY MONTH(ats.session_date)
")->fetchAll();

$monthLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
$monthlyTrend = [];
for ($i = 1; $i <= 12; $i++) {
    $monthlyTrend[$i] = [
        'label' => $monthLabels[$i - 1],
        'rate' => 0,
        'total' => 0
    ];
}

foreach ($monthlyRows as $row) {
    $monthNo = (int)$row['month_no'];
    $total = (int)$row['total_records'];
    $attended = (int)$row['attended_records'];
    $monthlyTrend[$monthNo]['rate'] = $total > 0 ? round(($attended / $total) * 100) : 0;
    $monthlyTrend[$monthNo]['total'] = $total;
}

$dashboardData = [
    'recentLogs' => array_map(function ($row) {
        return [
            'time' => $row['scan_time'] ?? '-',
            'course' => $row['course_code'],
            'studentId' => $row['matric_no'],
            'name' => $row['full_name'],
            'rfid' => $row['uid'],
            'status' => $row['status']
        ];
    }, $recentLogs),
    'activities' => array_map(function ($row) {
        return [
            'sessionId' => (int)$row['session_id'],
            'time' => $row['session_date'] . ' ' . $row['start_time'] . '-' . $row['end_time'],
            'course' => $row['course_code'] . ' - ' . $row['course_name'],
            'status' => $row['session_status'],
            'summary' => ((int)$row['total_present']) . '/' . ((int)$row['total_expected']) . ' present, ' . ((int)$row['total_absent']) . ' absent'
        ];
    }, $recentActivities),
    'monthlyTrend' => array_values($monthlyTrend)
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID IoT Attendance - Admin Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        :root {
            --primary: #4361ee;
            --primary-dark: #3a56d4;
            --secondary: #7209b7;
            --light: #f8f9fa;
            --dark: #212529;
            --gray: #6c757d;
            --success: #2ecc71;
            --warning: #f39c12;
            --danger: #e74c3c;
            --border-radius: 12px;
            --shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            min-height: 100vh;
            color: var(--dark);
        }

        .dashboard-container {
            display: grid;
            grid-template-columns: 260px 1fr;
            grid-template-rows: auto 1fr;
            min-height: 100vh;
        }

        /* MODAL STYLES */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .modal-content {
            background: white;
            border-radius: var(--border-radius);
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.15);
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from { transform: translateY(30px); opacity: 0; }
            to { transform: translateY(0); opacity: 1; }
        }

        .modal-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--gray);
            cursor: pointer;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
        }

        .modal-close:hover {
            background: #f8f9fa;
            color: var(--danger);
        }

        .modal-body {
            padding: 2rem;
        }

        .modal-footer {
            padding: 1.5rem 2rem;
            border-top: 1px solid #eee;
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
        }

        /* FORM STYLES */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }

        /* BUTTON STYLES */
        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #27ae60);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .btn:active {
            transform: translateY(0);
        }

        /* TOAST NOTIFICATION */
        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            background: white;
            box-shadow: var(--shadow);
            display: flex;
            align-items: center;
            gap: 12px;
            z-index: 1100;
            animation: slideInRight 0.3s ease;
            transform: translateX(0);
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); }
            to { transform: translateX(0); }
        }

        .toast-success {
            border-left: 4px solid var(--success);
        }

        .toast-error {
            border-left: 4px solid var(--danger);
        }

        .toast-info {
            border-left: 4px solid var(--primary);
        }

        .toast-close {
            background: none;
            border: none;
            color: var(--gray);
            cursor: pointer;
            font-size: 1.2rem;
        }

        /* REST OF THE STYLES SAME AS BEFORE... */
        .header {
            grid-column: 1 / -1;
            background: white;
            padding: 1.2rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: var(--shadow);
            z-index: 10;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .logo i {
            font-size: 2rem;
            color: var(--primary);
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo span {
            font-size: 0.9rem;
            color: var(--gray);
            font-weight: 400;
        }

        .header-right {
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .status-badge {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 14px;
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-badge.offline {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            background: var(--light);
            cursor: pointer;
            transition: var(--transition);
            border: 0;
            color: inherit;
            font: inherit;
        }

        .admin-profile:hover {
            background: #e9ecef;
        }

        .admin-profile img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--primary);
        }

        .admin-menu {
            position: relative;
        }

        .profile-caret {
            color: var(--gray);
            font-size: 0.8rem;
        }

        .admin-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            min-width: 180px;
            padding: 8px;
            border-radius: 14px;
            background: white;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
            border: 1px solid rgba(67, 97, 238, 0.12);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: var(--transition);
            z-index: 20;
        }

        .admin-menu:hover .admin-dropdown,
        .admin-menu:focus-within .admin-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .admin-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            border-radius: 10px;
            color: var(--dark);
            text-decoration: none;
            font-weight: 600;
            transition: var(--transition);
        }

        .admin-dropdown a:hover {
            background: linear-gradient(135deg, rgba(6, 120, 88, 0.08), rgba(67, 97, 238, 0.08));
            color: var(--primary);
        }

        /* SIDEBAR */
        .sidebar {
            background: white;
            padding: 2rem 1.5rem;
            box-shadow: var(--shadow);
            border-radius: 0 var(--border-radius) var(--border-radius) 0;
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .sidebar-section h3 {
            font-size: 0.9rem;
            text-transform: uppercase;
            color: var(--gray);
            margin-bottom: 1rem;
            letter-spacing: 1px;
        }

        .nav-menu {
            list-style: none;
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .nav-item {
            padding: 14px 18px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            gap: 14px;
            color: var(--gray);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .nav-item:hover, .nav-item.active {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            color: white;
            transform: translateX(5px);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
        }

        .logout-btn {
            margin-top: auto;
            background: linear-gradient(135deg, #ff6b6b, #ee5a52);
            color: white;
            border: none;
            padding: 14px;
            border-radius: var(--border-radius);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }

        .logout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(255, 107, 107, 0.3);
        }

        /* MAIN CONTENT */
        .main-content {
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 2rem;
            overflow-y: auto;
        }

        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .dashboard-header h2 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
        }

        .dashboard-header p {
            color: var(--gray);
            margin-top: 5px;
        }

        .filters {
            display: flex;
            gap: 12px;
        }

        .filter-btn {
            padding: 10px 20px;
            border-radius: var(--border-radius);
            border: 1px solid #ddd;
            background: white;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .filter-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            position: relative;
            overflow: hidden;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 30px rgba(0, 0, 0, 0.1);
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            background: linear-gradient(to bottom, var(--primary), var(--secondary));
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.2rem;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .stat-icon.students { background: linear-gradient(135deg, #4361ee, #3a56d4); }
        .stat-icon.lecturers { background: linear-gradient(135deg, #7209b7, #5a0a9c); }
        .stat-icon.courses { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .stat-icon.attendance { background: linear-gradient(135deg, #f39c12, #e67e22); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--gray);
            font-size: 1rem;
        }

        .stat-sub {
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        /* CHARTS & TABLES */
        .content-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 2rem;
        }

        .card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
        }

        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .card-title {
            font-size: 1.4rem;
            font-weight: 600;
            color: var(--dark);
        }

        .chart-placeholder {
            height: 280px;
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--gray);
            font-size: 1.1rem;
            padding: 1rem;
        }

        .chart-placeholder i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
        }

        .trend-chart {
            width: 100%;
            height: 100%;
            display: grid;
            grid-template-columns: repeat(12, minmax(28px, 1fr));
            gap: 10px;
            align-items: end;
        }

        .trend-bar-wrap {
            height: 100%;
            min-width: 0;
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            align-items: center;
            gap: 8px;
        }

        .trend-bar {
            width: 100%;
            max-width: 34px;
            min-height: 6px;
            border-radius: 8px 8px 4px 4px;
            background: linear-gradient(180deg, var(--primary), var(--secondary));
            box-shadow: 0 8px 16px rgba(67, 97, 238, 0.2);
        }

        .trend-label {
            font-size: 0.78rem;
            color: var(--gray);
            white-space: nowrap;
        }

        .empty-state {
            width: 100%;
            padding: 1rem;
            text-align: center;
            color: var(--gray);
            background: #f8f9fa;
            border-radius: 10px;
        }

        /* TABLE */
        .table-container {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        th {
            padding: 16px 20px;
            text-align: left;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
        }

        td {
            padding: 16px 20px;
            border-bottom: 1px solid #eee;
        }

        tr:hover td {
            background-color: #f8f9fa;
        }

        .status-badge-table {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-present { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
        .status-absent { background: rgba(231, 76, 60, 0.15); color: #c0392b; }

        /* QUICK ACTIONS */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1rem;
        }

        .action-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            transition: var(--transition);
            cursor: pointer;
            border: 2px solid transparent;
        }

        .action-card:hover {
            border-color: var(--primary);
            transform: translateY(-5px);
        }

        .action-icon {
            width: 64px;
            height: 64px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin-bottom: 1.2rem;
        }

        .action-icon.add { background: linear-gradient(135deg, #4361ee, #3a56d4); }
        .action-icon.register { background: linear-gradient(135deg, #7209b7, #5a0a9c); }
        .action-icon.manage { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .action-icon.export { background: linear-gradient(135deg, #f39c12, #e67e22); }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--dark);
        }

        .action-desc {
            color: var(--gray);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* RECENT ACTIVITY */
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
        }

        .activity-item {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 18px;
            align-items: center;
            padding: 18px;
            background: linear-gradient(135deg, #ffffff, #f8fbff);
            border: 1px solid #e3eaf4;
            border-radius: 14px;
            border-left: 5px solid var(--primary);
            box-shadow: 0 10px 24px rgba(28, 52, 84, 0.06);
            transition: var(--transition);
        }

        .activity-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 16px 34px rgba(28, 52, 84, 0.1);
        }

        .activity-main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .activity-icon {
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: linear-gradient(135deg, #006837, #4361ee);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.16);
            flex: 0 0 auto;
        }

        .activity-info {
            min-width: 0;
        }

        .activity-item > div:first-child:not(.activity-main) {
            position: relative;
            min-width: 0;
            padding-left: 62px;
        }

        .activity-item > div:first-child:not(.activity-main)::before {
            content: "\f274";
            font-family: "Font Awesome 6 Free";
            font-weight: 900;
            position: absolute;
            left: 0;
            top: 50%;
            transform: translateY(-50%);
            width: 48px;
            height: 48px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            background: linear-gradient(135deg, #006837, #4361ee);
            box-shadow: 0 10px 20px rgba(67, 97, 238, 0.16);
        }

        .activity-time {
            font-size: 1.05rem;
            font-weight: 800;
            color: var(--dark);
            line-height: 1.3;
        }

        .activity-course {
            color: #52647a;
            font-size: 0.9rem;
            margin-top: 3px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .activity-meta {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .activity-status {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 0.76rem;
            font-weight: 900;
            text-transform: uppercase;
            letter-spacing: 0.02em;
        }

        .activity-status.completed {
            background: rgba(46, 204, 113, 0.14);
            color: #047857;
        }

        .activity-status.ongoing {
            background: rgba(67, 97, 238, 0.12);
            color: #304ed8;
        }

        .activity-status.scheduled {
            background: rgba(243, 156, 18, 0.14);
            color: #b45309;
        }

        .activity-summary {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 6px 10px;
            border-radius: 999px;
            background: #f1f5f9;
            color: #52647a;
            font-size: 0.82rem;
            font-weight: 700;
        }

        .activity-actions {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
        }

        .btn-view {
            background: var(--primary);
            color: white;
        }

        .btn-export {
            background: transparent;
            color: var(--primary);
            border: 1px solid var(--primary);
        }

        .activity-actions .btn {
            min-width: 96px;
            justify-content: center;
            border-radius: 12px;
            min-height: 42px;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 760px) {
            .activity-item {
                grid-template-columns: 1fr;
            }

            .activity-actions {
                justify-content: stretch;
            }

            .activity-actions .btn {
                flex: 1;
            }

            .activity-item > div:first-child:not(.activity-main) {
                padding-left: 58px;
            }
        }

        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 576px) {
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .filters {
                flex-wrap: wrap;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <!-- MODALS -->
    <div id="addStudentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Student</h3>
                <button class="modal-close" onclick="closeModal('addStudentModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addStudentForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Student ID</label>
                            <input type="text" class="form-control" id="studentId" placeholder="e.g., DI230076" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="studentName" placeholder="e.g., Nur Alya" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
    <label class="form-label">Course</label>
    <select class="form-control" id="courseCode" required>
        <option value="" selected disabled>Select Course</option>
        <option value="BIW">BIW</option>
        <option value="BIM">BIM</option>
        <option value="BIP">BIP</option>
        <option value="BIT">BIT</option>
        <option value="BIS">BIS</option>
    </select>
</div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="studentEmail" placeholder="student@university.edu" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="studentPhone" placeholder="+60 12-345 6789">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Semester</label>
                            <select class="form-control" id="semester">
                                <option value="1" selected>Year 1 Semester 1</option>
                                <option value="2">Year 1 Semester 2</option>
                                <option value="3">Year 2 Semester 1</option>
                                <option value="4">Year 2 Semester 2</option>
                                <option value="5">Year 3 Semester 1</option>
                                <option value="6">Year 3 Semester 2</option>
                                <option value="5">Year 4 Semester 1</option>
                                <option value="6">Year 4 Semester 2</option>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addStudentModal')">Cancel</button>
                <button class="btn btn-primary" onclick="addStudent()">
                    <i class="fas fa-user-plus"></i> Add Student
                </button>
            </div>
        </div>
    </div>

    <div id="registerRFIDModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Register RFID Card</h3>
                <button class="modal-close" onclick="closeModal('registerRFIDModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Scan RFID Card</label>
                    <div style="text-align: center; padding: 2rem; border: 2px dashed #ddd; border-radius: 10px; margin-bottom: 1rem;">
                        <i class="fas fa-id-card" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <p>Place RFID card near the reader</p>
                        <div id="rfidStatus" style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                            Waiting for RFID scan...
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">RFID UID</label>
                    <input type="text" class="form-control" id="rfidUid" placeholder="e.g., 60CCFC61">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assign to Student</label>
                    <select class="form-control" id="assignStudent">
                        <option value="">Select a student...</option>
                        <?php foreach ($studentOptions as $studentOption): ?>
                            <option value="<?= htmlspecialchars($studentOption['user_id']) ?>">
                                <?= htmlspecialchars($studentOption['matric_no'] . ' - ' . $studentOption['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Card Type</label>
                    <select class="form-control" id="cardType">
                        <option value="student">Student Card</option>
                        <option value="lecturer">Lecturer Card</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="activateNow" checked> Activate immediately
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('registerRFIDModal')">Cancel</button>
                <button class="btn btn-success" onclick="registerRFID()">
                    <i class="fas fa-save"></i> Register Card
                </button>
            </div>
        </div>
    </div>

    <div id="timetableModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Manage Timetable</h3>
                <button class="modal-close" onclick="closeModal('timetableModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select class="form-control" id="timetableCourse">
                        <option value="">Select course...</option>
                        <?php foreach ($courseOptions as $courseOption): ?>
                            <option value="<?= htmlspecialchars($courseOption['course_id']) ?>">
                                <?= htmlspecialchars($courseOption['course_code'] . ' - ' . $courseOption['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Day</label>
                        <select class="form-control" id="timetableDay">
                            <option value="monday">Monday</option>
                            <option value="tuesday">Tuesday</option>
                            <option value="wednesday">Wednesday</option>
                            <option value="thursday">Thursday</option>
                            <option value="friday">Friday</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Time</label>
                        <input type="time" class="form-control" id="timetableTime" value="08:00">
                    </div>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Duration (hours)</label>
                        <input type="number" class="form-control" id="timetableDuration" min="1" max="4" value="2">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Room</label>
                        <input type="text" class="form-control" id="timetableRoom" placeholder="e.g., LAB 301">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Attendance Rules</label>
                    <div style="margin-top: 0.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="requireRFID" checked> Require RFID check-in
                        </label>
                        <label style="display: block;">
                            <input type="checkbox" id="autoAbsent"> Auto-mark absent after 30 minutes
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('timetableModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveTimetable()">
                    <i class="fas fa-calendar-plus"></i> Save Schedule
                </button>
            </div>
        </div>
    </div>

    <div id="exportModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Reports</h3>
                <button class="modal-close" onclick="closeModal('exportModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Report Type</label>
                    <select class="form-control" id="exportType">
                        <option value="attendance">Attendance Report</option>
                        <option value="students">Student List</option>
                        <option value="courses">Course Summary</option>
                        <option value="rfid">RFID Card Registry</option>
                    </select>
                </div>
                
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date From</label>
                        <input type="date" class="form-control" id="exportFrom" value="2026-01-01">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" id="exportTo" value="2026-12-31">
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Format</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="radio" name="exportFormat" value="csv" checked> CSV
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="radio" name="exportFormat" value="pdf"> PDF
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="radio" name="exportFormat" value="excel"> Excel
                        </label>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Filters</label>
                    <div style="margin-top: 0.5rem;">
                        <label style="display: block; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="filterCourse" checked> Filter by Course
                        </label>
                        <label style="display: block; margin-bottom: 0.5rem;">
                            <input type="checkbox" id="filterStatus" checked> Include Status Breakdown
                        </label>
                        <label style="display: block;">
                            <input type="checkbox" id="includeDetails"> Include Student Details
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportModal')">Cancel</button>
                <button class="btn btn-success" onclick="exportReport()">
                    <i class="fas fa-download"></i> Export Report
                </button>
            </div>
        </div>
    </div>

    <!-- TOAST NOTIFICATION -->
    <div id="toast" class="toast" style="display: none;">
        <i id="toastIcon" class="fas fa-check-circle"></i>
        <span id="toastMessage">Operation completed successfully!</span>
        <button class="toast-close" onclick="hideToast()">&times;</button>
    </div>

    <!-- DASHBOARD -->
    <div class="dashboard-container">
        <!-- HEADER -->
        <header class="header">
            <div class="logo">
                <i class="fas fa-id-card"></i>
                <div>
                    <h1>RFID IoT Attendance</h1>
                    <span>Admin Console</span>
                </div>
            </div>
            
            <div class="header-right">
                <div class="status-badge">
                    <i class="fas fa-circle fa-xs"></i>
                    <span>Online</span>
                </div>
                <div class="status-badge offline">
                    <i class="fas fa-sync-alt"></i>
                    <span id="syncQueue">Sync Queue: 3</span>
                </div>
                <div class="status-badge">
                    <i class="fas fa-rss"></i>
                    <span id="rfidReaders">RFID Readers: 8 Active</span>
                </div>
                
                <div class="admin-menu">
                    <button type="button" class="admin-profile">
                        <img src="https://ui-avatars.com/api/?name=Admin+User&background=4361ee&color=fff&size=128" alt="Admin">
                        <div style="font-weight: 600;">Admin</div>
                        <i class="fas fa-chevron-down profile-caret"></i>
                    </button>
                    <div class="admin-dropdown">
                        <a href="../admin/settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </header>

        <!-- SIDEBAR -->
        <nav class="sidebar">
            <div class="sidebar-section">
                <h3>Dashboard</h3>
                <ul class="nav-menu">
                    <li class="nav-item active">
                        <i class="fas fa-chart-line"></i>
                        <span>Admin Dashboard</span>
                    </li>
                    <li class="nav-item" onclick="window.location.href='../admin/users.php'">
    <i class="fas fa-users"></i>
    <span>Users & RFID</span>
</li>

                    <li class="nav-item" onclick="window.location.href='../admin/courses.php'">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Courses & Timetable</span>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Reports</h3>
                <ul class="nav-menu">
                    <li class="nav-item" onclick="window.location.href='../admin/reports.php'">
    <i class="fas fa-chart-bar"></i>
    <span>Reports & Analytics</span>
</li>
                </ul>
            </div>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <div class="dashboard-header">
                <div>
                    <h2>Admin Dashboard</h2>
                    <p>System overview, attendance trends, and recent activity.</p>
                </div>
                
                <div class="filters">
                    <button class="filter-btn" onclick="filterByCourse()">
                        <i class="fas fa-filter"></i>
                        All Courses
                    </button>
                    <button class="filter-btn" onclick="filterByStatus()">
                        <i class="fas fa-user-check"></i>
                        All Status
                    </button>
                    <button class="filter-btn" id="syncBtn" onclick="simulateSync()">
                        <i class="fas fa-sync"></i>
                        Simulate Offline Sync
                    </button>
                </div>
            </div>

            <!-- STATS GRID -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalStudents"><?= number_format($totalStudents) ?></div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Active cards: <span id="activeCards"><?= number_format($activeCards) ?></span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalLecturers"><?= number_format($totalLecturers) ?></div>
                            <div class="stat-label">Total Lecturers</div>
                        </div>
                        <div class="stat-icon lecturers">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Logged today: <span id="loggedToday"><?= number_format($loggedToday) ?></span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalCourses"><?= number_format($totalCourses) ?></div>
                            <div class="stat-label">Courses (This Sem)</div>
                        </div>
                        <div class="stat-icon courses">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Timetables updated: <span id="timetableUpdated"><?= number_format($totalSchedules) ?></span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="todayAttendance"><?= number_format($todayAttendance) ?></div>
                            <div class="stat-label">Today Attendance</div>
                        </div>
                        <div class="stat-icon attendance">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                    <div class="stat-sub">
                        <span style="color: #27ae60;">● <span id="presentCount"><?= number_format($presentCount) ?></span> Present</span> | 
                        <span style="color: #c0392b;">● <span id="absentCount"><?= number_format($absentCount) ?></span> Absent</span>
                    </div>
                </div>
            </div>

            <!-- CHARTS & TABLES -->
            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Monthly Attendance Trend</div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);" id="attendanceRate"><?= $attendanceRate ?>%</div>
                    </div>
                    <div class="chart-placeholder">
                        <div class="trend-chart" id="monthlyTrendChart"></div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Attendance Logs</div>
                        <a class="btn btn-view" href="reports.php">
                            <i class="fas fa-eye"></i> View All
                        </a>
                    </div>
                    <div class="table-container">
                        <table id="attendanceTable">
                            <thead>
                                <tr>
                                    <th>Time</th>
                                    <th>Course</th>
                                    <th>Student ID</th>
                                    <th>Name</th>
                                    <th>RFID UID</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="attendanceTableBody">
                                <!-- Data will be populated by JavaScript -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Quick Actions</div>
                </div>
                <div class="quick-actions">
                    <div class="action-card" onclick="openModal('addStudentModal')">
                        <div class="action-icon add">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="action-title">Add Student</div>
                        <div class="action-desc">Create new student account</div>
                    </div>
                    
                    <div class="action-card" onclick="openModal('registerRFIDModal')">
                        <div class="action-icon register">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="action-title">Register RFID Card</div>
                        <div class="action-desc">Link RFID UID to student activate</div>
                    </div>
                    
                    <div class="action-card" onclick="openModal('timetableModal')">
                        <div class="action-icon manage">
                            <i class="fas fa-calendar-plus"></i>
                        </div>
                        <div class="action-title">Manage Timetable</div>
                        <div class="action-desc">Create course schedules & attendance rules</div>
                    </div>
                    
                    <div class="action-card" onclick="openModal('exportModal')">
                        <div class="action-icon export">
                            <i class="fas fa-file-export"></i>
                        </div>
                        <div class="action-title">Export Reports</div>
                        <div class="action-desc">Download CSV / PDF for attendance data</div>
                    </div>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">Recent Attendance Activity</div>
                </div>
                <div class="activity-list" id="activityList">
                    <!-- Activities will be populated by JavaScript -->
                </div>
            </div>
        </main>
    </div>

    <script>
        const dashboardData = <?= json_encode($dashboardData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const attendanceData = dashboardData.recentLogs;
        const activities = dashboardData.activities;
        const monthlyTrend = dashboardData.monthlyTrend;

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            populateMonthlyTrend();
            populateAttendanceTable();
            populateActivities();
            //updateLiveStats();
            
            // Set active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    showToast('Navigated to ' + this.querySelector('span').textContent, 'info');
                });
            });
            
            // Auto refresh data every 30 seconds
            //setInterval(updateLiveStats, 30000);
        });

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Toast Notification
        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            const toastIcon = document.getElementById('toastIcon');
            const toastMessage = document.getElementById('toastMessage');
            
            toast.className = 'toast';
            toast.classList.add(`toast-${type}`);
            
            if (type === 'success') {
                toastIcon.className = 'fas fa-check-circle';
                toast.style.borderLeftColor = 'var(--success)';
            } else if (type === 'error') {
                toastIcon.className = 'fas fa-exclamation-circle';
                toast.style.borderLeftColor = 'var(--danger)';
            } else if (type === 'info') {
                toastIcon.className = 'fas fa-info-circle';
                toast.style.borderLeftColor = 'var(--primary)';
            }
            
            toastMessage.textContent = message;
            toast.style.display = 'flex';
            
            setTimeout(hideToast, 5000);
        }

        function hideToast() {
            document.getElementById('toast').style.display = 'none';
        }

        // Quick Action Functions
        function addStudent() {
            const studentId = document.getElementById('studentId').value;
            const studentName = document.getElementById('studentName').value;
            const studentEmail = document.getElementById('studentEmail').value;
            
            if (!studentId || !studentName || !studentEmail) {
                showToast('Please fill in all required fields', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'add_student');
            formData.append('student_id', studentId);
            formData.append('student_name', studentName);
            formData.append('email', studentEmail);
            formData.append('phone', document.getElementById('studentPhone').value);
            formData.append('department', document.getElementById('courseCode').value);
            formData.append('semester', document.getElementById('semester').value);

            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) {
                        showToast(data.message, 'error');
                        return;
                    }

                    closeModal('addStudentModal');
                    document.getElementById('addStudentForm').reset();
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to add student. Please try again.', 'error'));
        }

        function simulateRFIDScan() {
            const rfidStatus = document.getElementById('rfidStatus');
            const rfidUid = document.getElementById('rfidUid');
            
            rfidStatus.innerHTML = '<i class="fas fa-circle-notch fa-spin"></i> Scanning...';
            rfidStatus.style.color = 'var(--warning)';
            
            setTimeout(() => {
                // Generate random RFID UID
                const characters = '0123456789ABCDEF';
                let uid = '';
                for (let i = 0; i < 6; i++) {
                    uid += characters.charAt(Math.floor(Math.random() * characters.length));
                }
                
                rfidUid.value = uid;
                rfidStatus.innerHTML = '<i class="fas fa-check-circle" style="color: var(--success);"></i> Card detected!';
                rfidStatus.style.color = 'var(--success)';
                showToast(`RFID card ${uid} detected successfully`, 'success');
            }, 1500);
        }

        function registerRFID() {
            const rfidUid = document.getElementById('rfidUid').value.trim().toUpperCase();
            const student = document.getElementById('assignStudent').value;
            
            if (!rfidUid || !student) {
                showToast('Please enter RFID UID and assign to a student', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'register_rfid');
            formData.append('user_id', student);
            formData.append('uid', rfidUid);
            formData.append('card_type', document.getElementById('cardType').value);
            formData.append('activate_now', document.getElementById('activateNow').checked ? '1' : '0');

            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) {
                        showToast(data.message, 'error');
                        return;
                    }

                    closeModal('registerRFIDModal');
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to register RFID. Please try again.', 'error'));
        }

        function saveTimetable() {
            const course = document.getElementById('timetableCourse').value;
            const day = document.getElementById('timetableDay').value;
            const time = document.getElementById('timetableTime').value;
            
            if (!course || !time || !document.getElementById('timetableRoom').value) {
                showToast('Please select course, time and room', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'save_timetable');
            formData.append('course_id', course);
            formData.append('day', day);
            formData.append('start_time', time);
            formData.append('duration', document.getElementById('timetableDuration').value);
            formData.append('room', document.getElementById('timetableRoom').value);

            fetch('dashboard.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: formData.toString()
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) {
                        showToast(data.message, 'error');
                        return;
                    }

                    closeModal('timetableModal');
                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to save timetable. Please try again.', 'error'));
        }

        function exportReport() {
            const exportType = document.getElementById('exportType').value;
            const format = document.querySelector('input[name="exportFormat"]:checked').value;
            const exportFormat = format === 'excel' ? 'csv' : format;
            const from = document.getElementById('exportFrom').value;
            const to = document.getElementById('exportTo').value;

            const routeMap = {
                attendance: `export_dashboard_report.php?type=attendance&format=${encodeURIComponent(exportFormat)}&from=${encodeURIComponent(from)}&to=${encodeURIComponent(to)}`,
                students: `export_users.php?role=student&format=${encodeURIComponent(exportFormat)}`,
                courses: `export_courses.php?scope=all&format=${encodeURIComponent(exportFormat)}`,
                rfid: `export_dashboard_report.php?type=rfid&format=${encodeURIComponent(exportFormat)}`
            };

            closeModal('exportModal');
            window.location.href = routeMap[exportType] || routeMap.attendance;
        }

        // Dashboard Functions
        function populateMonthlyTrend() {
            const chart = document.getElementById('monthlyTrendChart');
            chart.innerHTML = '';

            const hasData = monthlyTrend.some(month => month.total > 0);
            if (!hasData) {
                chart.innerHTML = `
                    <div class="empty-state" style="grid-column: 1 / -1;">
                        <i class="fas fa-chart-line" style="display: block; font-size: 2rem; margin-bottom: 0.75rem;"></i>
                        No attendance records yet for this year.
                    </div>
                `;
                return;
            }

            monthlyTrend.forEach(month => {
                const wrap = document.createElement('div');
                wrap.className = 'trend-bar-wrap';
                wrap.title = `${month.label}: ${month.rate}% attendance (${month.total} records)`;
                wrap.innerHTML = `
                    <div class="trend-bar" style="height: ${Math.max(month.rate, 4)}%; opacity: ${month.total > 0 ? 1 : 0.25};"></div>
                    <div class="trend-label">${month.label}</div>
                `;
                chart.appendChild(wrap);
            });
        }

        function populateAttendanceTable() {
            const tbody = document.getElementById('attendanceTableBody');
            tbody.innerHTML = '';

            if (attendanceData.length === 0) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="6">
                            <div class="empty-state">No attendance logs yet. Student scans will appear here after a session is active.</div>
                        </td>
                    </tr>
                `;
                return;
            }
            
            attendanceData.forEach(item => {
                const row = document.createElement('tr');
                
                let statusClass = '';
                let statusText = '';
                switch(item.status) {
                    case 'present':
                        statusClass = 'status-present';
                        statusText = 'Present';
                        break;
                    case 'absent':
                        statusClass = 'status-absent';
                        statusText = 'Absent';
                        break;
                }
                
                row.innerHTML = `
                    <td>${item.time}</td>
                    <td>${item.course}</td>
                    <td>${item.studentId}</td>
                    <td>${item.name}</td>
                    <td>${item.rfid}</td>
                    <td><span class="status-badge-table ${statusClass}">${statusText}</span></td>
                `;
                
                tbody.appendChild(row);
            });
        }

        function populateActivities() {
            const activityList = document.getElementById('activityList');
            activityList.innerHTML = '';

            if (activities.length === 0) {
                activityList.innerHTML = '<div class="empty-state">No attendance sessions created yet.</div>';
                return;
            }
            
            activities.forEach(activity => {
                const activityItem = document.createElement('div');
                activityItem.className = 'activity-item';
                activityItem.innerHTML = `
                    <div>
                        <div class="activity-time">${activity.time}</div>
                        <div class="activity-course">${activity.course}</div>
                        <div style="color: var(--gray); font-size: 0.9rem; margin-top: 4px;">${activity.status.toUpperCase()} • ${activity.summary}</div>
                    </div>
                    <div class="activity-actions">
                        <button class="btn btn-view" onclick="viewActivity(${activity.sessionId})"><i class="fas fa-eye"></i> View</button>
                        <button class="btn btn-export" onclick="exportActivity(${activity.sessionId})"><i class="fas fa-file-export"></i> Export</button>
                    </div>
                `;
                activityList.appendChild(activityItem);
            });
        }

        function updateLiveStats() {
            // Simulate live data updates
            const present = Math.floor(Math.random() * 20) + 100;
            const absent = Math.floor(Math.random() * 5) + 35;
            const total = present + absent;
            
            document.getElementById('presentCount').textContent = present;
            document.getElementById('absentCount').textContent = absent;
            document.getElementById('todayAttendance').textContent = total;
            
            // Update attendance rate
            const rate = (95 + Math.random() * 2).toFixed(1);
            document.getElementById('attendanceRate').textContent = rate + '%';
            
            // Update logged today (simulate changes)
            const logged = Math.floor(Math.random() * 5) + 75;
            document.getElementById('loggedToday').textContent = logged;
            
            showToast('Live data updated', 'info');
        }

        function simulateSync() {
            const syncBtn = document.getElementById('syncBtn');
            const syncQueue = document.getElementById('syncQueue');
            const offlineBadge = document.querySelector('.status-badge.offline');
            
            // Reset after 3 seconds
            syncBtn.innerHTML = '<i class="fas fa-sync fa-spin"></i> Syncing...';
            syncBtn.style.pointerEvents = 'none';
            
            setTimeout(() => {
                syncBtn.innerHTML = '<i class="fas fa-sync"></i> Simulate Offline Sync';
                syncBtn.style.pointerEvents = 'auto';
                syncQueue.textContent = 'Sync Queue: 0';
                offlineBadge.classList.remove('offline');
                offlineBadge.style.background = 'linear-gradient(135deg, #2ecc71, #27ae60)';
                
                // Add new attendance record
                const now = new Date();
                const time = now.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
                const newRecord = {
                    time: time,
                    course: 'SYNC001',
                    studentId: 'SY' + now.getSeconds(),
                    name: 'Sync Test',
                    rfid: 'SYNC' + now.getMilliseconds(),
                    status: 'present'
                };
                
                attendanceData.unshift(newRecord);
                populateAttendanceTable();
                
                showToast('Offline data synchronized successfully!', 'success');
                
                setTimeout(() => {
                    offlineBadge.classList.add('offline');
                    offlineBadge.style.background = 'linear-gradient(135deg, #e74c3c, #c0392b)';
                    syncQueue.textContent = 'Sync Queue: ' + (Math.floor(Math.random() * 5) + 1);
                }, 10000);
            }, 3000);
        }

        function filterByCourse() {
            showToast('Filtering by course...', 'info');
            // Implement actual filtering logic here
        }

        function filterByStatus() {
            showToast('Filtering by status...', 'info');
            // Implement actual filtering logic here
        }

        function viewAllLogs() {
            window.location.href = 'reports.php';
        }

        function viewActivity(sessionId) {
            window.location.href = `session_view.php?session_id=${encodeURIComponent(sessionId)}`;
        }

        function exportActivity(sessionId) {
            window.location.href = `export_attendance.php?session_id=${encodeURIComponent(sessionId)}&format=pdf&from=dashboard`;
        }

        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                setTimeout(() => {
                    alert('Logged out successfully!');
                    // In a real app, this would redirect to login page
                    // window.location.href = '/login';
                }, 1500);
            }
        }
    </script>
</body>
</html>
