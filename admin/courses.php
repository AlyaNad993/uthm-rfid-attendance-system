<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

function adminCoursesJsonResponse($ok, $message) {
    header('Content-Type: application/json');
    echo json_encode([
        'ok' => $ok,
        'message' => $message
    ]);
    exit();
}

function adminCoursesColumnExists(PDO $pdo, string $table, string $column): bool {
    foreach ($pdo->query("SHOW COLUMNS FROM `$table`") as $row) {
        if (($row['Field'] ?? '') === $column) {
            return true;
        }
    }

    return false;
}

function adminCoursesSetting(PDO $pdo, string $key, string $default): string {
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        return $value !== false && $value !== null && $value !== '' ? (string)$value : $default;
    } catch (Throwable $e) {
        return $default;
    }
}

function adminCoursesEnsureColumn(PDO $pdo, string $table, string $column, string $definition): void {
    if (adminCoursesColumnExists($pdo, $table, $column)) {
        return;
    }

    try {
        $pdo->exec("ALTER TABLE `$table` ADD $definition");
    } catch (Throwable $e) {
        // Keep the page usable; the next query will surface any remaining schema issue clearly.
    }
}

adminCoursesEnsureColumn($pdo, 'class_schedule', 'section_name', "section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER lecturer_id");
adminCoursesEnsureColumn($pdo, 'class_schedule', 'academic_year', "academic_year VARCHAR(20) NULL AFTER section_name");
adminCoursesEnsureColumn($pdo, 'class_schedule', 'semester_label', "semester_label VARCHAR(60) NULL AFTER academic_year");
adminCoursesEnsureColumn($pdo, 'enrollments', 'section_name', "section_name VARCHAR(30) NOT NULL DEFAULT 'Section 1' AFTER course_id");
adminCoursesEnsureColumn($pdo, 'enrollments', 'academic_year', "academic_year VARCHAR(20) NULL AFTER section_name");

$defaultAcademicYear = adminCoursesSetting($pdo, 'academic_year', '2025/2026');
$defaultSemesterLabel = adminCoursesSetting($pdo, 'current_semester', 'Semester 1 2025/2026');
$semesterOptions = [
    ['semester' => '1', 'academic_year' => '2025/2026', 'label' => 'Semester 1 2025/2026'],
    ['semester' => '2', 'academic_year' => '2025/2026', 'label' => 'Semester 2 2025/2026'],
];
if (!in_array($defaultSemesterLabel, array_column($semesterOptions, 'label'), true)) {
    $defaultSemesterLabel = $semesterOptions[0]['label'];
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_course') {
            $courseCode = strtoupper(trim($_POST['course_code'] ?? ''));
            $courseName = trim($_POST['course_name'] ?? '');
            $creditHours = (int)($_POST['credit_hours'] ?? 3);
            $semester = trim($_POST['semester'] ?? '1');
            $academicYear = trim($_POST['academic_year'] ?? $defaultAcademicYear);
            $department = trim($_POST['department'] ?? '');

            if ($courseCode === '' || $courseName === '' || $department === '') {
                adminCoursesJsonResponse(false, 'Please fill in course code, course name and course group.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO courses
                    (course_code, course_name, credit_hours, department, faculty, semester, academic_year, is_active)
                VALUES
                    (?, ?, ?, ?, 'FTK', ?, ?, 1)
            ");
            $stmt->execute([$courseCode, $courseName, $creditHours, $department, $semester, $academicYear]);

            adminCoursesJsonResponse(true, 'Course added successfully.');
        }

        if ($action === 'add_schedule') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $lecturerId = (int)($_POST['lecturer_id'] ?? 0);
            $sectionName = trim($_POST['section_name'] ?? 'Section 1');
            $academicYear = trim($_POST['academic_year'] ?? $defaultAcademicYear);
            $semesterLabel = trim($_POST['semester_label'] ?? $defaultSemesterLabel);
            $roomCode = trim($_POST['room_code'] ?? '');
            $day = trim($_POST['day'] ?? 'monday');
            $start = trim($_POST['start_time'] ?? '');
            $end = trim($_POST['end_time'] ?? '');

            $dayMap = [
                'monday' => 'Monday',
                'tuesday' => 'Tuesday',
                'wednesday' => 'Wednesday',
                'thursday' => 'Thursday',
                'friday' => 'Friday',
                'saturday' => 'Saturday'
            ];

            if ($courseId <= 0 || $lecturerId <= 0 || $sectionName === '' || $academicYear === '' || $semesterLabel === '' || $roomCode === '' || $start === '' || $end === '') {
                adminCoursesJsonResponse(false, 'Please fill in course, section, semester, lecturer, room and time.');
            }

            if (!isset($dayMap[$day])) {
                adminCoursesJsonResponse(false, 'Invalid schedule day.');
            }

            if ($start >= $end) {
                adminCoursesJsonResponse(false, 'End time must be after start time.');
            }

            $courseStmt = $pdo->prepare("SELECT department FROM courses WHERE course_id = ? AND is_active = 1 LIMIT 1");
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch();
            if (!$course) {
                adminCoursesJsonResponse(false, 'Selected course was not found.');
            }

            $lecturerStmt = $pdo->prepare("
                SELECT user_id
                FROM users
                WHERE user_id = ?
                  AND role = 'lecturer'
                  AND is_active = 1
                  AND department = ?
                LIMIT 1
            ");
            $lecturerStmt->execute([$lecturerId, $course['department'] ?? '']);
            if (!$lecturerStmt->fetch()) {
                adminCoursesJsonResponse(false, 'Please select a lecturer from the same course group.');
            }

            $roomStmt = $pdo->prepare("SELECT room_id FROM rooms WHERE room_code = ? OR room_name = ? LIMIT 1");
            $roomStmt->execute([$roomCode, $roomCode]);
            $room = $roomStmt->fetch();

            if (!$room) {
                adminCoursesJsonResponse(false, 'Room not found. Please use an existing room code from the database.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO class_schedule
                    (course_id, lecturer_id, section_name, academic_year, semester_label, room_id, day_of_week, start_time, end_time, repeat_weekly, start_date, end_date, is_active)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 6 MONTH), 1)
            ");
            $stmt->execute([$courseId, $lecturerId, $sectionName, $academicYear, $semesterLabel, $room['room_id'], $dayMap[$day], $start, $end]);

            adminCoursesJsonResponse(true, 'Schedule added successfully.');
        }

        if ($action === 'enroll_students') {
            $courseId = (int)($_POST['course_id'] ?? 0);
            $sectionName = trim($_POST['section_name'] ?? 'Section 1');
            $academicYear = trim($_POST['academic_year'] ?? $defaultAcademicYear);
            $semester = trim($_POST['semester'] ?? $defaultSemesterLabel);
            $studentId = (int)($_POST['student_id'] ?? 0);
            $studentMatricList = trim($_POST['student_matric_list'] ?? '');

            if ($courseId <= 0) {
                adminCoursesJsonResponse(false, 'Please select a course.');
            }
            if ($sectionName === '' || $academicYear === '' || $semester === '') {
                adminCoursesJsonResponse(false, 'Please enter section, semester and academic year.');
            }

            $courseStmt = $pdo->prepare("SELECT course_id, department FROM courses WHERE course_id = ? AND is_active = 1 LIMIT 1");
            $courseStmt->execute([$courseId]);
            $course = $courseStmt->fetch();
            if (!$course) {
                adminCoursesJsonResponse(false, 'Selected course was not found.');
            }
            $courseGroup = trim((string)($course['department'] ?? ''));

            $studentIds = [];
            if ($studentId > 0) {
                $singleStudentStmt = $pdo->prepare("
                    SELECT user_id
                    FROM users
                    WHERE user_id = ?
                      AND role = 'student'
                      AND is_active = 1
                      AND department = ?
                    LIMIT 1
                ");
                $singleStudentStmt->execute([$studentId, $courseGroup]);
                if ($singleStudentStmt->fetch()) {
                    $studentIds[] = $studentId;
                }
            }

            if ($studentMatricList !== '') {
                $matrics = preg_split('/[\s,;]+/', $studentMatricList, -1, PREG_SPLIT_NO_EMPTY);
                $matrics = array_values(array_unique(array_map('strtoupper', array_map('trim', $matrics))));

                if (!empty($matrics)) {
                    $placeholders = implode(',', array_fill(0, count($matrics), '?'));
                    $studentStmt = $pdo->prepare("
                        SELECT user_id
                        FROM users
                        WHERE role = 'student'
                          AND is_active = 1
                          AND department = ?
                          AND UPPER(matric_no) IN ($placeholders)
                    ");
                    $studentStmt->execute(array_merge([$courseGroup], $matrics));
                    $studentIds = array_merge($studentIds, array_column($studentStmt->fetchAll(), 'user_id'));
                }
            }

            $studentIds = array_values(array_unique(array_map('intval', $studentIds)));
            if (empty($studentIds)) {
                adminCoursesJsonResponse(false, 'No matching active students found.');
            }

            $checkStmt = $pdo->prepare("
                SELECT enrollment_id
                FROM enrollments
                WHERE student_id = ?
                  AND course_id = ?
                  AND section_name = ?
                  AND COALESCE(academic_year, '') = ?
                  AND semester = ?
                  AND status = 'registered'
                LIMIT 1
            ");
            $insertStmt = $pdo->prepare("
                INSERT INTO enrollments
                    (student_id, course_id, section_name, academic_year, semester, enrollment_date, status)
                VALUES
                    (?, ?, ?, ?, ?, CURDATE(), 'registered')
            ");

            $added = 0;
            $skipped = 0;
            foreach ($studentIds as $id) {
                $checkStmt->execute([$id, $courseId, $sectionName, $academicYear, $semester]);
                if ($checkStmt->fetch()) {
                    $skipped++;
                    continue;
                }

                $insertStmt->execute([$id, $courseId, $sectionName, $academicYear, $semester]);
                $added++;
            }

            adminCoursesJsonResponse(true, "Enrollment saved. Added $added student(s), skipped $skipped duplicate(s).");
        }

        adminCoursesJsonResponse(false, 'Unknown action.');
    } catch (PDOException $e) {
        if ($e->getCode() === '23000') {
            adminCoursesJsonResponse(false, 'This course or schedule already exists.');
        }

        adminCoursesJsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

$coursesRows = $pdo->query("
    SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        c.semester,
        c.academic_year,
        c.department,
        COUNT(DISTINCT e.student_id) AS students,
        COUNT(DISTINCT cs.lecturer_id) AS lecturers,
        ROUND(COALESCE(AVG(CASE WHEN ar.status = 'present' THEN 100 ELSE 0 END), 0)) AS attendance,
        COUNT(DISTINCT CASE WHEN rc.status = 'active' THEN rc.card_id END) AS rfid_active
    FROM courses c
    LEFT JOIN enrollments e
        ON e.course_id = c.course_id
        AND e.status = 'registered'
    LEFT JOIN class_schedule cs
        ON cs.course_id = c.course_id
        AND cs.is_active = 1
    LEFT JOIN attendance_sessions ats
        ON ats.schedule_id = cs.schedule_id
    LEFT JOIN attendance_records ar
        ON ar.session_id = ats.session_id
    LEFT JOIN rfid_cards rc
        ON rc.user_id = e.student_id
    WHERE c.is_active = 1
    GROUP BY c.course_id, c.course_code, c.course_name, c.semester, c.academic_year, c.department
    ORDER BY c.department, c.academic_year DESC, c.semester, c.course_code
")->fetchAll();

$schedulesRows = $pdo->query("
    SELECT
        cs.schedule_id,
        c.course_id,
        c.course_code,
        c.course_name,
        cs.section_name,
        COALESCE(cs.academic_year, c.academic_year) AS academic_year,
        COALESCE(cs.semester_label, CONCAT('Semester ', c.semester, ' ', c.academic_year)) AS semester_label,
        cs.day_of_week,
        TIME_FORMAT(cs.start_time, '%H:%i') AS start_time,
        TIME_FORMAT(cs.end_time, '%H:%i') AS end_time,
        COALESCE(r.room_code, r.room_name, '-') AS room,
        u.full_name AS lecturer
    FROM class_schedule cs
    JOIN courses c ON c.course_id = cs.course_id
    JOIN users u ON u.user_id = cs.lecturer_id
    LEFT JOIN rooms r ON r.room_id = cs.room_id
    WHERE cs.is_active = 1
    ORDER BY c.department, academic_year DESC, semester_label, c.course_code, cs.section_name, FIELD(cs.day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'), cs.start_time
")->fetchAll();

$lecturersRows = $pdo->query("
    SELECT user_id, matric_no, full_name, department
    FROM users
    WHERE role = 'lecturer' AND is_active = 1
    ORDER BY full_name
")->fetchAll();

$studentsRows = $pdo->query("
    SELECT user_id, matric_no, full_name, department
    FROM users
    WHERE role = 'student' AND is_active = 1
    ORDER BY full_name
")->fetchAll();

$roomsRows = $pdo->query("
    SELECT room_id, room_code, room_name, room_type, capacity
    FROM rooms
    ORDER BY room_code, room_name
")->fetchAll();

$coursesData = array_map(function ($row) {
    return [
        'id' => (string)$row['course_id'],
        'code' => $row['course_code'],
        'name' => $row['course_name'],
        'semester' => (int)$row['semester'],
        'academicYear' => $row['academic_year'] ?? '',
        'semesterLabel' => 'Semester ' . $row['semester'] . ' ' . ($row['academic_year'] ?? ''),
        'department' => $row['department'] ?? '',
        'students' => (int)$row['students'],
        'lecturers' => (int)$row['lecturers'],
        'attendance' => (int)$row['attendance'],
        'rfidActive' => (int)$row['rfid_active']
    ];
}, $coursesRows);

$schedulesData = array_map(function ($row) {
    return [
        'id' => (int)$row['schedule_id'],
        'courseId' => (string)$row['course_id'],
        'course' => $row['course_code'],
        'courseName' => $row['course_name'],
        'section' => $row['section_name'] ?: 'Section 1',
        'academicYear' => $row['academic_year'] ?? '',
        'semesterLabel' => $row['semester_label'] ?? '',
        'day' => strtolower($row['day_of_week']),
        'start' => $row['start_time'],
        'end' => $row['end_time'],
        'room' => $row['room'],
        'lecturer' => $row['lecturer']
    ];
}, $schedulesRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID IoT Attendance - Courses & Timetable</title>
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
            --info: #3498db;
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

        /* HEADER */
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
            text-decoration: none;
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
            text-decoration: none;
            color: inherit;
            border: 0;
            font: inherit;
        }

        .admin-profile:hover {
            background: #e9ecef;
            text-decoration: none;
            color: inherit;
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
            text-decoration: none;
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

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 1.5rem;
            margin-bottom: 1rem;
        }

        .page-title h1 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.5rem;
        }

        .page-title p {
            color: var(--gray);
            font-size: 1.1rem;
            max-width: 600px;
        }

        .action-buttons {
            display: grid;
            grid-template-columns: repeat(4, minmax(150px, 1fr));
            gap: 0.75rem;
            align-items: stretch;
            justify-content: end;
            max-width: 660px;
        }

        .btn {
            padding: 12px 24px;
            border-radius: 8px;
            border: none;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
            min-height: 56px;
            white-space: nowrap;
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

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #e67e22);
            color: white;
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
        }

        .btn:active {
            transform: translateY(0);
        }

        /* COURSE SELECTOR */
        .course-selector {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .selector-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .selector-title {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--dark);
        }

        .course-dropdown {
            position: relative;
            width: 100%;
        }

        .dropdown-header {
            padding: 14px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            background: white;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: var(--transition);
        }

        .dropdown-header:hover {
            border-color: var(--primary);
        }

        .dropdown-header.active {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .selected-course {
            font-weight: 500;
            color: var(--dark);
        }

        .dropdown-arrow {
            color: var(--gray);
            transition: var(--transition);
        }

        .dropdown-arrow.rotate {
            transform: rotate(180deg);
        }

        .dropdown-list {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-top: 4px;
            max-height: 300px;
            overflow-y: auto;
            box-shadow: var(--shadow);
            z-index: 100;
            display: none;
        }

        .dropdown-list.show {
            display: block;
        }

        .course-option {
            padding: 12px 16px;
            cursor: pointer;
            transition: var(--transition);
            border-bottom: 1px solid #f0f0f0;
        }

        .course-option:hover {
            background: #f8f9fa;
        }

        .course-option:last-child {
            border-bottom: none;
        }

        .course-code {
            font-weight: 600;
            color: var(--dark);
            display: block;
        }

        .course-name {
            font-size: 0.9rem;
            color: var(--gray);
            margin-top: 2px;
        }

        /* COURSE INFO CARDS */
        .course-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }

        .info-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
        }

        .info-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .info-icon {
            width: 48px;
            height: 48px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
            margin-bottom: 1rem;
        }

        .info-icon.students { background: linear-gradient(135deg, #4361ee, #3a56d4); }
        .info-icon.lecturers { background: linear-gradient(135deg, #7209b7, #5a0a9c); }
        .info-icon.attendance { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .info-icon.rfid { background: linear-gradient(135deg, #f39c12, #e67e22); }

        .info-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 4px;
        }

        .info-label {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* TIMETABLE SECTION */
        .timetable-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .table-container {
            overflow-x: auto;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 800px;
        }

        thead {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
        }

        th {
            padding: 18px 20px;
            text-align: left;
            color: var(--gray);
            font-weight: 600;
            border-bottom: 2px solid #dee2e6;
            white-space: nowrap;
        }

        td {
            padding: 18px 20px;
            border-bottom: 1px solid #eee;
            vertical-align: middle;
        }

        tr:hover td {
            background-color: #f8f9fa;
        }

        /* DAY BADGES */
        .day-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .day-monday { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
        .day-tuesday { background: rgba(114, 9, 183, 0.15); color: var(--secondary); }
        .day-wednesday { background: rgba(46, 204, 113, 0.15); color: var(--success); }
        .day-thursday { background: rgba(243, 156, 18, 0.15); color: var(--warning); }
        .day-friday { background: rgba(52, 152, 219, 0.15); color: var(--info); }
        .day-saturday { background: rgba(155, 89, 182, 0.15); color: #9b59b6; }
        .day-sunday { background: rgba(231, 76, 60, 0.15); color: var(--danger); }

        /* ACTION BUTTONS */
        .action-buttons-cell {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
            align-items: center;
            min-width: 270px;
        }

        .btn-action {
            min-height: 38px;
            padding: 0 13px;
            border-radius: 9px;
            border: none;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            box-shadow: 0 8px 18px rgba(28, 52, 84, 0.10);
        }

        .btn-action-sm {
            padding: 0 13px;
            font-size: 0.8rem;
        }

        .btn-edit { background: var(--success); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-view { background: var(--primary); color: white; }
        .btn-duplicate { background: var(--info); color: white; }

        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            text-decoration: none;
            color: inherit;
        }

        #timetableTable th:last-child,
        #timetableTable td:last-child {
            width: 290px;
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
            max-width: 800px;
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

        /* WEEK VIEW */
        .week-view {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            overflow-x: auto;
            padding-bottom: 1rem;
        }

        .day-card {
            min-width: 200px;
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
            border-top: 4px solid var(--primary);
        }

        .day-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .day-name {
            font-weight: 600;
            color: var(--dark);
        }

        .class-count {
            background: var(--primary);
            color: white;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.8rem;
        }

        .class-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .class-item {
            padding: 0.75rem;
            background: #f8f9fa;
            border-radius: 6px;
            border-left: 3px solid var(--success);
        }

        .class-time {
            font-weight: 500;
            color: var(--dark);
            font-size: 0.9rem;
        }

        .class-course {
            font-size: 0.85rem;
            color: var(--gray);
            margin-top: 2px;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .page-header {
                flex-direction: column;
            }

            .action-buttons {
                width: 100%;
                max-width: none;
            }

            .form-row {
                grid-template-columns: 1fr;
            }
            
            .week-view {
                flex-wrap: wrap;
            }
            
            .day-card {
                min-width: calc(33.333% - 1rem);
            }
        }

        @media (max-width: 992px) {
            .dashboard-container {
                grid-template-columns: 1fr;
            }
            
            .sidebar {
                display: none;
            }
            
            .course-info-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .day-card {
                min-width: calc(50% - 1rem);
            }
        }

        @media (max-width: 768px) {
            .course-info-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                width: 100%;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
            
            .action-buttons .btn {
                justify-content: center;
            }
            
            .section-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
            
            .day-card {
                min-width: 100%;
            }
        }

        @media (max-width: 576px) {
            .header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }
            
            .header-right {
                flex-wrap: wrap;
                justify-content: center;
            }

            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .main-content {
                padding: 1rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
            
            .action-buttons-cell {
                flex-direction: column;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <!-- MODALS -->
    <div id="addCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Subject</h3>
                <button class="modal-close" onclick="closeModal('addCourseModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addCourseForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Subject Code</label>
                            <input type="text" class="form-control" id="courseCode" placeholder="e.g., BIC20403" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Subject Name</label>
                            <input type="text" class="form-control" id="courseName" placeholder="e.g., Software Engineering" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Credit Hours</label>
                            <input type="number" class="form-control" id="creditHours" min="1" max="6" value="3">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Semester / Academic Year</label>
                            <select class="form-control" id="courseSemester">
                                <?php foreach ($semesterOptions as $option): ?>
                                    <option value="<?= htmlspecialchars($option['semester'] . '|' . $option['academic_year'] . '|' . $option['label']) ?>" <?= $option['label'] === $defaultSemesterLabel ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($option['label']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Group</label>
                        <select class="form-control" id="courseDepartment">
                            <option value="" selected disabled>Select Course Group</option>
                            <option value="BIW">BIW</option>
                            <option value="BIM">BIM</option>
                            <option value="BIP">BIP</option>
                            <option value="BIS">BIS</option>
                            <option value="BIT">BIT</option>
                        </select>
                    </div>
                    
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
                <button class="btn btn-primary" onclick="addCourse()">
                    <i class="fas fa-plus"></i> Add Subject
                </button>
            </div>
        </div>
    </div>

    <div id="enrollStudentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Enroll Students to Subject</h3>
                <button class="modal-close" onclick="closeModal('enrollStudentsModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="enrollStudentsForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <select class="form-control" id="enrollCourse" required onchange="syncCourseContext('enroll')">
                                <option value="">Select a subject...</option>
                                <?php foreach ($coursesRows as $courseOption): ?>
                                    <option
                                        value="<?= htmlspecialchars($courseOption['course_id']) ?>"
                                        data-dept="<?= htmlspecialchars($courseOption['department'] ?? '') ?>"
                                        data-semester-label="<?= htmlspecialchars('Semester ' . $courseOption['semester'] . ' ' . $courseOption['academic_year']) ?>"
                                        data-academic-year="<?= htmlspecialchars($courseOption['academic_year']) ?>"
                                    >
                                        <?= htmlspecialchars(($courseOption['department'] ?: '-') . ' | ' . $courseOption['course_code'] . ' - ' . $courseOption['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Section</label>
                        <select class="form-control" id="enrollSection" required>
                            <option value="Section 1">Section 1</option>
                            <option value="Section 2">Section 2</option>
                            <option value="Section 3">Section 3</option>
                            <option value="Section 4">Section 4</option>
                            <option value="Section 5" selected>Section 5</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Single Student</label>
                        <select class="form-control" id="enrollStudent">
                            <option value="">Select one student...</option>
                            <?php foreach ($studentsRows as $studentOption): ?>
                                <option value="<?= htmlspecialchars($studentOption['user_id']) ?>" data-dept="<?= htmlspecialchars($studentOption['department'] ?? '') ?>">
                                    <?= htmlspecialchars($studentOption['matric_no'] . ' - ' . $studentOption['full_name'] . ' (' . ($studentOption['department'] ?: '-') . ')') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Bulk Matric IDs</label>
                        <textarea class="form-control" id="enrollMatricList" rows="5" placeholder="DI230076&#10;DI230081&#10;DI230110"></textarea>
                        <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                            Paste many matric IDs separated by new line, comma, or space.
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('enrollStudentsModal')">Cancel</button>
                <button class="btn btn-primary" onclick="enrollStudents()">
                    <i class="fas fa-user-graduate"></i> Save Enrollment
                </button>
            </div>
        </div>
    </div>

    <div id="addScheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add Class Schedule</h3>
                <button class="modal-close" onclick="closeModal('addScheduleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addScheduleForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <select class="form-control" id="scheduleCourse" onchange="syncCourseContext('schedule')">
                                <option value="">Select a subject...</option>
                                <?php foreach ($coursesRows as $courseOption): ?>
                                    <option
                                        value="<?= htmlspecialchars($courseOption['course_id']) ?>"
                                        data-dept="<?= htmlspecialchars($courseOption['department'] ?? '') ?>"
                                        data-semester-label="<?= htmlspecialchars('Semester ' . $courseOption['semester'] . ' ' . $courseOption['academic_year']) ?>"
                                        data-academic-year="<?= htmlspecialchars($courseOption['academic_year']) ?>"
                                    >
                                        <?= htmlspecialchars(($courseOption['department'] ?: '-') . ' | ' . $courseOption['course_code'] . ' - ' . $courseOption['course_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Day</label>
                            <select class="form-control" id="scheduleDay">
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Section</label>
                            <select class="form-control" id="scheduleSection" required>
                                <option value="Section 1">Section 1</option>
                                <option value="Section 2">Section 2</option>
                                <option value="Section 3">Section 3</option>
                                <option value="Section 4">Section 4</option>
                                <option value="Section 5" selected>Section 5</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="scheduleStart" value="08:00" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" id="scheduleEnd" value="10:00" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Room/Lab</label>
                            <select class="form-control" id="scheduleRoom" required>
                                <option value="">Select room...</option>
                                <?php foreach ($roomsRows as $roomOption): ?>
                                    <option value="<?= htmlspecialchars($roomOption['room_code'] ?: $roomOption['room_name']) ?>">
                                        <?= htmlspecialchars(($roomOption['room_code'] ?: $roomOption['room_name']) . ' - ' . $roomOption['room_name'] . ' (' . $roomOption['room_type'] . ', ' . $roomOption['capacity'] . ' pax)') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lecturer</label>
                            <select class="form-control" id="scheduleLecturer">
                                <option value="">Select lecturer...</option>
                                <?php foreach ($lecturersRows as $lecturerOption): ?>
                                    <option value="<?= htmlspecialchars($lecturerOption['user_id']) ?>" data-dept="<?= htmlspecialchars($lecturerOption['department'] ?? '') ?>">
                                        <?= htmlspecialchars($lecturerOption['full_name'] . ' (' . ($lecturerOption['department'] ?: '-') . ')') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addScheduleModal')">Cancel</button>
                <button class="btn btn-success" onclick="addSchedule()">
                    <i class="fas fa-calendar-plus"></i> Add Schedule
                </button>
            </div>
        </div>
    </div>

    <div id="exportCoursesModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export Subjects & Timetable</h3>
                <button class="modal-close" onclick="closeModal('exportCoursesModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Export Scope</label>
                    <select class="form-control" id="exportCourseScope" onchange="toggleCourseExportFilters()">
                        <option value="all">All Subjects</option>
                        <option value="lecturer">Based on Lecturer</option>
                        <option value="subject">Subject Only</option>
                    </select>
                </div>

                <div class="form-group" id="exportLecturerGroup" style="display: none;">
                    <label class="form-label">Lecturer</label>
                    <select class="form-control" id="exportLecturerId">
                        <option value="">Select lecturer...</option>
                        <?php foreach ($lecturersRows as $lecturerOption): ?>
                            <option value="<?= htmlspecialchars($lecturerOption['user_id']) ?>">
                                <?= htmlspecialchars($lecturerOption['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" id="exportSubjectGroup" style="display: none;">
                    <label class="form-label">Subject</label>
                    <select class="form-control" id="exportCourseId">
                        <option value="">Select subject...</option>
                        <?php foreach ($coursesRows as $courseOption): ?>
                            <option
                                value="<?= htmlspecialchars($courseOption['course_id']) ?>"
                                data-semester-label="<?= htmlspecialchars('Semester ' . $courseOption['semester'] . ' ' . $courseOption['academic_year']) ?>"
                            >
                                <?= htmlspecialchars(($courseOption['department'] ?: '-') . ' | ' . $courseOption['course_code'] . ' - ' . $courseOption['course_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Format</label>
                    <select class="form-control" id="exportCourseFormat">
                        <option value="pdf">PDF Print View</option>
                        <option value="csv">CSV / Excel</option>
                    </select>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                        PDF Print View opens a neat report page. Use Print then Save as PDF.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportCoursesModal')">Cancel</button>
                <button class="btn btn-primary" onclick="exportCoursesTimetable()">
                    <i class="fas fa-file-export"></i> Export
                </button>
            </div>
        </div>
    </div>

    <div id="editScheduleModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit Schedule</h3>
                <button class="modal-close" onclick="closeModal('editScheduleModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editScheduleForm">
                    <input type="hidden" id="editScheduleId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Subject</label>
                            <input type="text" class="form-control" id="editScheduleCourse" readonly>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Day</label>
                            <select class="form-control" id="editScheduleDay">
                                <option value="monday">Monday</option>
                                <option value="tuesday">Tuesday</option>
                                <option value="wednesday">Wednesday</option>
                                <option value="thursday">Thursday</option>
                                <option value="friday">Friday</option>
                                <option value="saturday">Saturday</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Time</label>
                            <input type="time" class="form-control" id="editScheduleStart" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Time</label>
                            <input type="time" class="form-control" id="editScheduleEnd" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Room/Lab</label>
                            <input type="text" class="form-control" id="editScheduleRoom" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lecturer</label>
                            <select class="form-control" id="editScheduleLecturer">
                                <?php foreach ($lecturersRows as $lecturerOption): ?>
                                    <option value="<?= htmlspecialchars($lecturerOption['full_name']) ?>">
                                        <?= htmlspecialchars($lecturerOption['full_name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editScheduleModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveScheduleEdit()">
                    <i class="fas fa-save"></i> Save Changes
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
            <a href="dashboard.php" class="logo">
                <i class="fas fa-id-card"></i>
                <div>
                    <h1>RFID IoT Attendance</h1>
                    <span>Admin Console</span>
                </div>
            </a>
            
            <div class="header-right">
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
                    <li class="nav-item">
                        <i class="fas fa-chart-line"></i>
                        <a href="dashboard.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 14px;">
                            <span>Admin Dashboard</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <i class="fas fa-users"></i>
                        <a href="users.php" style="text-decoration: none; color: inherit; display: flex; align-items: center; gap: 14px;">
                            <span>Users & RFID</span>
                        </a>
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
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Courses & Timetable</h1>
                    <p>Manage subjects by course group, semester, section, lecturer, and timetable</p>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                        <i class="fas fa-plus"></i> Add Subject
                    </button>
                    <button class="btn btn-success" onclick="openModal('addScheduleModal')">
                        <i class="fas fa-calendar-plus"></i> Add Schedule
                    </button>
                    <button class="btn btn-warning" onclick="openModal('enrollStudentsModal')">
                        <i class="fas fa-user-graduate"></i> Enroll Students
                    </button>
                    <button class="btn btn-outline" onclick="openModal('exportCoursesModal')">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>

            <!-- COURSE SELECTOR -->
            <div class="course-selector">
                <div class="selector-header">
                    <div class="selector-title">Select Subject</div>
                    <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                        <label for="semesterViewFilter" style="color: var(--gray); font-size: 0.9rem; font-weight: 700;">Semester</label>
                        <select class="form-control" id="semesterViewFilter" onchange="applySemesterViewFilter()" style="min-width: 230px;">
                            <option value="all" selected>All Semesters</option>
                            <?php foreach ($semesterOptions as $option): ?>
                                <option value="<?= htmlspecialchars($option['label']) ?>">
                                    <?= htmlspecialchars($option['label']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="course-dropdown">
                    <div class="dropdown-header" id="dropdownHeader" onclick="toggleDropdown()">
                        <div class="selected-course" id="selectedCourse">
                            BIC20403 - Software Engineering
                        </div>
                        <div class="dropdown-arrow" id="dropdownArrow">
                            <i class="fas fa-chevron-down"></i>
                        </div>
                    </div>
                    <div class="dropdown-list" id="dropdownList">
                        <!-- Course options will be populated by JavaScript -->
                    </div>
                </div>
                
                <!-- COURSE INFO CARDS -->
                <div class="course-info-grid" id="courseInfoGrid">
                    <!-- Course info cards will be populated by JavaScript -->
                </div>
            </div>

            <!-- WEEKLY VIEW -->
            <div class="timetable-section">
                <div class="section-header">
                    <div class="section-title">Weekly Schedule View</div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        <i class="fas fa-calendar-week"></i> Week 12 of 14
                    </div>
                </div>
                
                <div class="week-view" id="weekView">
                    <!-- Weekly schedule will be populated by JavaScript -->
                </div>
            </div>

            <!-- TIMETABLE TABLE -->
            <div class="timetable-section">
                <div class="section-header">
                    <div class="section-title">Course Schedules</div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        Showing schedules for: <strong id="currentCourseDisplay">BIC20403 - Software Engineering</strong>
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="timetableTable">
                        <thead>
                            <tr>
                                <th>Subject Code</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Lecturer</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="timetableTableBody">
                            <!-- Timetable data will be populated by JavaScript -->
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <script>
        const coursesData = <?= json_encode($coursesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const schedulesData = <?= json_encode($schedulesData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        let visibleCoursesData = [...coursesData];
        let selectedCourse = visibleCoursesData[0];
        let filteredSchedules = selectedCourse ? schedulesData.filter(s => s.courseId === selectedCourse.id) : [];

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            applySemesterViewFilter(false);
            
            // Set active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    if (this.classList.contains('active')) return;
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    const pageName = this.querySelector('span').textContent;
                    showToast(`Navigating to ${pageName}`, 'info');
                });
            });
            
            // Close dropdown when clicking outside
            document.addEventListener('click', function(event) {
                if (!event.target.closest('.course-dropdown')) {
                    closeDropdown();
                }
            });
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

        // Dropdown Functions
        function toggleDropdown() {
            const dropdownList = document.getElementById('dropdownList');
            const dropdownArrow = document.getElementById('dropdownArrow');
            const dropdownHeader = document.getElementById('dropdownHeader');
            
            dropdownList.classList.toggle('show');
            dropdownArrow.classList.toggle('rotate');
            dropdownHeader.classList.toggle('active');
        }

        function closeDropdown() {
            const dropdownList = document.getElementById('dropdownList');
            const dropdownArrow = document.getElementById('dropdownArrow');
            const dropdownHeader = document.getElementById('dropdownHeader');
            
            dropdownList.classList.remove('show');
            dropdownArrow.classList.remove('rotate');
            dropdownHeader.classList.remove('active');
        }

        function selectCourse(courseId) {
            selectedCourse = visibleCoursesData.find(c => c.id === String(courseId));
            if (!selectedCourse) return;
            
            // Update selected course display
            document.getElementById('selectedCourse').textContent = 
                `${selectedCourse.code} - ${selectedCourse.name}`;
            
            // Update current course display
            document.getElementById('currentCourseDisplay').textContent = 
                `${selectedCourse.code} - ${selectedCourse.name}`;
            
            // Update filtered schedules
            filteredSchedules = schedulesData.filter(s => s.courseId === selectedCourse.id);
            
            // Update all components
            populateCourseInfo();
            populateWeekView();
            populateTimetableTable();
            
            closeDropdown();
            showToast(`Switched to ${selectedCourse.name}`, 'info');
        }

        // Populate Functions
        function populateCourseDropdown() {
            const dropdownList = document.getElementById('dropdownList');
            dropdownList.innerHTML = '';
            
            visibleCoursesData.forEach(course => {
                const option = document.createElement('div');
                option.className = 'course-option';
                option.onclick = () => selectCourse(course.id);
                option.innerHTML = `
                    <div class="course-code">${course.code}</div>
                    <div class="course-name">${course.name}</div>
                `;
                dropdownList.appendChild(option);
            });
        }

        function applySemesterViewFilter(showNotice = true) {
            const semesterFilter = document.getElementById('semesterViewFilter');
            const selectedSemester = semesterFilter ? semesterFilter.value : '';
            const showAllSemesters = selectedSemester === '' || selectedSemester === 'all';

            visibleCoursesData = showAllSemesters
                ? [...coursesData]
                : coursesData.filter(course => course.semesterLabel === selectedSemester);
            ['enrollCourse', 'scheduleCourse', 'exportCourseId'].forEach(selectId => {
                const select = document.getElementById(selectId);
                if (!select) return;

                Array.from(select.options).forEach(option => {
                    if (!option.value) {
                        option.hidden = false;
                        return;
                    }

                    option.hidden = !showAllSemesters && (option.dataset.semesterLabel || '') !== selectedSemester;
                });

                if (select.selectedOptions[0] && select.selectedOptions[0].hidden) {
                    select.value = '';
                }
            });

            selectedCourse = visibleCoursesData[0] || null;
            filteredSchedules = selectedCourse ? schedulesData.filter(s => s.courseId === selectedCourse.id) : [];

            if (selectedCourse) {
                document.getElementById('selectedCourse').textContent =
                    `${selectedCourse.code} - ${selectedCourse.name}`;
                document.getElementById('currentCourseDisplay').textContent =
                    `${selectedCourse.code} - ${selectedCourse.name}`;
            } else {
                document.getElementById('selectedCourse').textContent = 'No courses found for selected semester';
                document.getElementById('currentCourseDisplay').textContent = 'No course selected';
            }

            populateCourseDropdown();
            populateCourseInfo();
            populateWeekView();
            populateTimetableTable();

            if (showNotice) {
                showToast(showAllSemesters ? 'Showing subjects for all semesters' : `Showing subjects for ${selectedSemester}`, 'info');
            }
        }

        function populateCourseInfo() {
            const courseInfoGrid = document.getElementById('courseInfoGrid');

            if (!selectedCourse) {
                courseInfoGrid.innerHTML = '<div style="color: var(--gray); padding: 1rem;">No courses found.</div>';
                return;
            }
            
            courseInfoGrid.innerHTML = `
                <div class="info-card">
                    <div class="info-icon students">
                        <i class="fas fa-user-graduate"></i>
                    </div>
                    <div class="info-number">${selectedCourse.students}</div>
                    <div class="info-label">Registered Students</div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon lecturers">
                        <i class="fas fa-chalkboard-teacher"></i>
                    </div>
                    <div class="info-number">${selectedCourse.lecturers}</div>
                    <div class="info-label">Lecturers</div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon attendance">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="info-number">${selectedCourse.attendance}%</div>
                    <div class="info-label">Attendance Rate</div>
                </div>
                
                <div class="info-card">
                    <div class="info-icon rfid">
                        <i class="fas fa-id-card"></i>
                    </div>
                    <div class="info-number">${selectedCourse.rfidActive}</div>
                    <div class="info-label">Active RFID Cards</div>
                </div>
            `;
        }

        function populateWeekView() {
            const weekView = document.getElementById('weekView');
            const days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
            
            weekView.innerHTML = '';
            
            days.forEach((day, index) => {
                const daySchedules = filteredSchedules.filter(s => s.day === day);
                let dayClass = '';
                
                switch(day) {
                    case 'monday': dayClass = 'day-monday'; break;
                    case 'tuesday': dayClass = 'day-tuesday'; break;
                    case 'wednesday': dayClass = 'day-wednesday'; break;
                    case 'thursday': dayClass = 'day-thursday'; break;
                    case 'friday': dayClass = 'day-friday'; break;
                    case 'saturday': dayClass = 'day-saturday'; break;
                }
                
                const dayCard = document.createElement('div');
                dayCard.className = 'day-card';
                dayCard.style.borderTopColor = getDayColor(day);
                
                let classListHTML = '';
                daySchedules.forEach(schedule => {
                    classListHTML += `
                        <div class="class-item">
                            <div class="class-time">${schedule.start} - ${schedule.end}</div>
                            <div class="class-course">${schedule.section} • ${schedule.room} • ${schedule.lecturer}</div>
                        </div>
                    `;
                });
                
                if (classListHTML === '') {
                    classListHTML = '<div style="color: var(--gray); font-style: italic; text-align: center; padding: 1rem;">No classes</div>';
                }
                
                dayCard.innerHTML = `
                    <div class="day-header">
                        <div class="day-name">${dayNames[index]}</div>
                        <div class="class-count">${daySchedules.length}</div>
                    </div>
                    <div class="class-list">
                        ${classListHTML}
                    </div>
                `;
                
                weekView.appendChild(dayCard);
            });
        }

        function populateTimetableTable() {
            const tbody = document.getElementById('timetableTableBody');
            tbody.innerHTML = '';
            
            filteredSchedules.forEach(schedule => {
                const row = document.createElement('tr');
                
                // Day badge
                let dayClass = '';
                let dayText = schedule.day.charAt(0).toUpperCase() + schedule.day.slice(1);
                switch(schedule.day) {
                    case 'monday': dayClass = 'day-monday'; break;
                    case 'tuesday': dayClass = 'day-tuesday'; break;
                    case 'wednesday': dayClass = 'day-wednesday'; break;
                    case 'thursday': dayClass = 'day-thursday'; break;
                    case 'friday': dayClass = 'day-friday'; break;
                    case 'saturday': dayClass = 'day-saturday'; break;
                }
                
                row.innerHTML = `
                    <td><strong>${schedule.course}</strong><br><span style="color: var(--gray); font-size: 0.85rem;">${schedule.section} • ${schedule.semesterLabel}</span></td>
                    <td><span class="day-badge ${dayClass}">${dayText}</span></td>
                    <td>${schedule.start} – ${schedule.end}</td>
                    <td>${schedule.room}</td>
                    <td>${schedule.lecturer}</td>
                    <td>
                        <div class="action-buttons-cell">
                            <button class="btn-action btn-edit btn-action-sm" onclick="editSchedule(${schedule.id})">
                                <i class="fas fa-edit"></i> Edit
                            </button>
                            <button class="btn-action btn-delete btn-action-sm" onclick="deleteSchedule(${schedule.id})">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                            <button class="btn-action btn-duplicate btn-action-sm" onclick="duplicateSchedule(${schedule.id})">
                                <i class="fas fa-copy"></i> Duplicate
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
        }

        // Course Management Functions
        function parseCourseSemesterValue(value) {
            const [semester = '1', academicYear = '2025/2026', label = 'Semester 1 2025/2026'] = String(value || '').split('|');
            return { semester, academicYear, label };
        }

        function filterPersonOptions(selectId, courseSelectId) {
            const personSelect = document.getElementById(selectId);
            const courseSelect = document.getElementById(courseSelectId);
            if (!personSelect || !courseSelect) return;

            const selectedCourse = courseSelect.selectedOptions[0];
            const courseDept = selectedCourse ? (selectedCourse.dataset.dept || '') : '';

            Array.from(personSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }

                const optionDept = option.dataset.dept || '';
                option.hidden = courseDept !== '' && optionDept !== courseDept;
            });

            const selectedOption = personSelect.selectedOptions[0];
            if (selectedOption && selectedOption.hidden) {
                personSelect.value = '';
            }
        }

        function syncCourseContext(type) {
            const courseSelect = document.getElementById(type === 'enroll' ? 'enrollCourse' : 'scheduleCourse');
            const selectedCourse = courseSelect ? courseSelect.selectedOptions[0] : null;
            if (!selectedCourse) return;

            if (type === 'enroll') {
                filterPersonOptions('enrollStudent', 'enrollCourse');
            } else {
                filterPersonOptions('scheduleLecturer', 'scheduleCourse');
            }
        }

        function addCourse() {
            const courseCode = document.getElementById('courseCode').value;
            const courseName = document.getElementById('courseName').value;
            const semesterInfo = parseCourseSemesterValue(document.getElementById('courseSemester').value);
            const courseGroup = document.getElementById('courseDepartment').value;
            
            if (!courseCode || !courseName || !courseGroup) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            const formData = new URLSearchParams();
            formData.append('action', 'add_course');
            formData.append('course_code', courseCode);
            formData.append('course_name', courseName);
            formData.append('credit_hours', document.getElementById('creditHours').value);
            formData.append('semester', semesterInfo.semester);
            formData.append('academic_year', semesterInfo.academicYear);
            formData.append('department', courseGroup);

            fetch('courses.php', {
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

                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to add course. Please try again.', 'error'));
        }

        function enrollStudents() {
            const courseId = document.getElementById('enrollCourse').value;
            const studentId = document.getElementById('enrollStudent').value;
            const matricList = document.getElementById('enrollMatricList').value.trim();

            if (!courseId) {
                showToast('Please select a course', 'error');
                return;
            }

            if (!studentId && !matricList) {
                showToast('Please select a student or paste matric IDs', 'error');
                return;
            }

            const formData = new URLSearchParams();
            const selectedCourseOption = document.getElementById('enrollCourse').selectedOptions[0];
            formData.append('action', 'enroll_students');
            formData.append('course_id', courseId);
            formData.append('semester', selectedCourseOption ? selectedCourseOption.dataset.semesterLabel : '');
            formData.append('section_name', document.getElementById('enrollSection').value);
            formData.append('academic_year', selectedCourseOption ? selectedCourseOption.dataset.academicYear : '');
            formData.append('student_id', studentId);
            formData.append('student_matric_list', matricList);

            fetch('courses.php', {
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

                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to save enrollment. Please try again.', 'error'));
        }

        // Schedule Management Functions
        function addSchedule() {
            const courseId = document.getElementById('scheduleCourse').value;
            const selectedCourseOption = document.getElementById('scheduleCourse').selectedOptions[0];
            const course = selectedCourseOption ? selectedCourseOption.textContent.trim().split(' - ')[0] : '';
            const day = document.getElementById('scheduleDay').value;
            const start = document.getElementById('scheduleStart').value;
            const end = document.getElementById('scheduleEnd').value;
            
            if (!courseId || !start || !end) {
                showToast('Please fill in all required fields', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'add_schedule');
            formData.append('course_id', courseId);
            formData.append('section_name', document.getElementById('scheduleSection').value);
            formData.append('academic_year', selectedCourseOption ? selectedCourseOption.dataset.academicYear : '');
            formData.append('semester_label', selectedCourseOption ? selectedCourseOption.dataset.semesterLabel : '');
            formData.append('day', day);
            formData.append('start_time', start);
            formData.append('end_time', end);
            formData.append('room_code', document.getElementById('scheduleRoom').value);
            formData.append('lecturer_id', document.getElementById('scheduleLecturer').value);

            fetch('courses.php', {
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

                    showToast(data.message, 'success');
                    setTimeout(() => window.location.reload(), 700);
                })
                .catch(() => showToast('Unable to add schedule. Please try again.', 'error'));
        }

        function editSchedule(scheduleId) {
            const schedule = schedulesData.find(s => s.id === scheduleId);
            if (!schedule) return;
            
            // Populate form
            document.getElementById('editScheduleId').value = schedule.id;
            document.getElementById('editScheduleCourse').value = `${schedule.course} - ${(coursesData.find(c => c.id === schedule.courseId) || {}).name || schedule.courseName}`;
            document.getElementById('editScheduleDay').value = schedule.day;
            document.getElementById('editScheduleStart').value = schedule.start;
            document.getElementById('editScheduleEnd').value = schedule.end;
            document.getElementById('editScheduleRoom').value = schedule.room;
            document.getElementById('editScheduleLecturer').value = schedule.lecturer;
            
            openModal('editScheduleModal');
        }

        function saveScheduleEdit() {
            const scheduleId = document.getElementById('editScheduleId').value;
            const scheduleIndex = schedulesData.findIndex(s => s.id == scheduleId);
            
            if (scheduleIndex === -1) return;
            
            // Update schedule data
            schedulesData[scheduleIndex].day = document.getElementById('editScheduleDay').value;
            schedulesData[scheduleIndex].start = document.getElementById('editScheduleStart').value;
            schedulesData[scheduleIndex].end = document.getElementById('editScheduleEnd').value;
            schedulesData[scheduleIndex].room = document.getElementById('editScheduleRoom').value;
            schedulesData[scheduleIndex].lecturer = document.getElementById('editScheduleLecturer').value;
            
            // Update filtered data
            if (schedulesData[scheduleIndex].course === selectedCourse.code) {
                const filteredIndex = filteredSchedules.findIndex(s => s.id == scheduleId);
                if (filteredIndex !== -1) {
                    filteredSchedules[filteredIndex] = { ...schedulesData[scheduleIndex] };
                }
            }
            
            closeModal('editScheduleModal');
            populateWeekView();
            populateTimetableTable();
            
            showToast(`Schedule updated successfully!`, 'success');
        }

        function deleteSchedule(scheduleId) {
            if (!confirm('Are you sure you want to delete this schedule?')) return;
            
            const scheduleIndex = schedulesData.findIndex(s => s.id === scheduleId);
            if (scheduleIndex === -1) return;
            
            const schedule = schedulesData[scheduleIndex];
            
            // Remove from main data
            schedulesData.splice(scheduleIndex, 1);
            
            // Remove from filtered data if applicable
            if (schedule.course === selectedCourse.code) {
                const filteredIndex = filteredSchedules.findIndex(s => s.id === scheduleId);
                if (filteredIndex !== -1) {
                    filteredSchedules.splice(filteredIndex, 1);
                }
            }
            
            populateWeekView();
            populateTimetableTable();
            
            showToast(`Schedule deleted successfully!`, 'success');
        }

        function duplicateSchedule(scheduleId) {
            const schedule = schedulesData.find(s => s.id === scheduleId);
            if (!schedule) return;
            
            // Create a duplicate with new ID
            const duplicate = {
                ...schedule,
                id: schedulesData.length + 1
            };
            
            schedulesData.push(duplicate);
            
            // Update if selected course matches
            if (duplicate.course === selectedCourse.code) {
                filteredSchedules.push(duplicate);
                populateWeekView();
                populateTimetableTable();
            }
            
            showToast(`Schedule duplicated successfully!`, 'success');
        }

        // Helper Functions
        function getDayColor(day) {
            switch(day) {
                case 'monday': return '#4361ee';
                case 'tuesday': return '#7209b7';
                case 'wednesday': return '#2ecc71';
                case 'thursday': return '#f39c12';
                case 'friday': return '#3498db';
                case 'saturday': return '#9b59b6';
                default: return '#e74c3c';
            }
        }

        // Navigation
        function logout() {
            if (confirm('Are you sure you want to logout?')) {
                showToast('Logging out...', 'info');
                setTimeout(() => {
                    alert('Logged out successfully!');
                    // In a real app, redirect to login page
                    // window.location.href = '/login';
                }, 1500);
            }
        }

        function toggleCourseExportFilters() {
            const scope = document.getElementById('exportCourseScope').value;
            document.getElementById('exportLecturerGroup').style.display = scope === 'lecturer' ? 'block' : 'none';
            document.getElementById('exportSubjectGroup').style.display = scope === 'subject' ? 'block' : 'none';
        }

        function exportCoursesTimetable() {
            const scope = document.getElementById('exportCourseScope').value;
            const format = document.getElementById('exportCourseFormat').value;
            const params = new URLSearchParams();
            params.set('scope', scope);
            params.set('format', format);

            if (scope === 'lecturer') {
                const lecturerId = document.getElementById('exportLecturerId').value;
                if (!lecturerId) {
                    showToast('Please select a lecturer.', 'error');
                    return;
                }
                params.set('lecturer_id', lecturerId);
            }

            if (scope === 'subject') {
                const courseId = document.getElementById('exportCourseId').value;
                if (!courseId) {
                    showToast('Please select a subject.', 'error');
                    return;
                }
                params.set('course_id', courseId);
            }

            closeModal('exportCoursesModal');
            window.location.href = `export_courses.php?${params.toString()}`;
        }
    </script>
</body>
</html>
