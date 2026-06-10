<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

function adminUsersJsonResponse($ok, $message, $extra = []) {
    header('Content-Type: application/json');
    echo json_encode(array_merge([
        'ok' => $ok,
        'message' => $message
    ], $extra));
    exit();
}

function adminUsersValidPhone(string $phone): bool {
    return $phone === '' || (bool)preg_match('/^\+?[0-9\s\-]{7,20}$/', $phone);
}

function adminUsersValidRfidUid(string $uid): bool {
    return (bool)preg_match('/^[A-F0-9]{4,32}$/', $uid);
}

function saveUserProfileImage(int $userId): ?string {
    if (empty($_FILES['profile_image']['name']) || $_FILES['profile_image']['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($_FILES['profile_image']['error'] !== UPLOAD_ERR_OK) {
        adminUsersJsonResponse(false, 'Unable to upload profile image.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];
    $mimeType = mime_content_type($_FILES['profile_image']['tmp_name']);

    if (!isset($allowedTypes[$mimeType])) {
        adminUsersJsonResponse(false, 'Only JPG, PNG, or WEBP images are allowed.');
    }

    if ($_FILES['profile_image']['size'] > 2 * 1024 * 1024) {
        adminUsersJsonResponse(false, 'Profile image must be 2MB or smaller.');
    }

    $uploadDir = __DIR__ . '/../uploads/profile_images';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'user_' . $userId . '_' . time() . '.' . $allowedTypes[$mimeType];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
        adminUsersJsonResponse(false, 'Unable to save profile image.');
    }

    return 'uploads/profile_images/' . $fileName;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        if ($action === 'add_user') {
            $matricNo = trim($_POST['matric_no'] ?? '');
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $role = trim($_POST['role'] ?? 'student');

            if ($matricNo === '' || $fullName === '' || $email === '') {
                adminUsersJsonResponse(false, 'Please fill in ID, name and email.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                adminUsersJsonResponse(false, 'Please enter a valid email address.');
            }

            if (!adminUsersValidPhone($phone)) {
                adminUsersJsonResponse(false, 'Please enter a valid phone number.');
            }

            if (!in_array($role, ['student', 'lecturer', 'staff', 'admin'], true)) {
                adminUsersJsonResponse(false, 'Invalid user role.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO users
                    (matric_no, full_name, email, phone, password_hash, role, department, faculty, is_active)
                VALUES
                    (?, ?, ?, ?, ?, ?, ?, 'OTHER', 1)
            ");
            $stmt->execute([
                $matricNo,
                $fullName,
                $email,
                $phone,
                password_hash('password123', PASSWORD_DEFAULT),
                $role,
                $department
            ]);

            $newUserId = (string)$pdo->lastInsertId();
            adminUsersJsonResponse(true, 'User added successfully.', [
                'user' => [
                    'id' => $newUserId,
                    'matric' => $matricNo,
                    'name' => $fullName,
                    'role' => $role,
                    'rfid' => '',
                    'status' => 'active',
                    'email' => $email,
                    'phone' => $phone,
                    'dept' => $department,
                    'profileImage' => '',
                    'cardStatus' => '',
                    'studentStats' => [
                        'courseCount' => 0,
                        'attendanceRate' => 0,
                        'attendanceRecords' => 0,
                        'warningCount' => 0,
                        'excuseCount' => 0
                    ],
                    'lecturerStats' => [
                        'courseCount' => 0,
                        'scheduleCount' => 0,
                        'sessionCount' => 0,
                        'studentCount' => 0
                    ]
                ]
            ]);
        }

        if ($action === 'assign_rfid') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $uid = strtoupper(trim($_POST['uid'] ?? ''));
            $status = trim($_POST['status'] ?? 'active');

            if ($userId <= 0 || $uid === '') {
                adminUsersJsonResponse(false, 'Please select a user and enter RFID UID.');
            }

            if (!adminUsersValidRfidUid($uid)) {
                adminUsersJsonResponse(false, 'RFID UID must contain 4 to 32 hexadecimal characters.');
            }

            if (!in_array($status, ['active', 'inactive', 'lost', 'damaged', 'expired'], true)) {
                adminUsersJsonResponse(false, 'Invalid card status.');
            }

            $userStmt = $pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ?");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                adminUsersJsonResponse(false, 'Selected user does not exist.');
            }

            $cardType = in_array($user['role'], ['student', 'lecturer', 'staff'], true) ? $user['role'] : 'staff';
            $cardId = 'CARD' . date('YmdHis') . random_int(10, 99);
            $registeredBy = $_SESSION['user_id'] ?? null;

            $stmt = $pdo->prepare("
                INSERT INTO rfid_cards
                    (card_id, user_id, uid, card_type, issue_date, status, registered_by)
                VALUES
                    (?, ?, ?, ?, CURDATE(), ?, ?)
            ");
            $stmt->execute([$cardId, $userId, $uid, $cardType, $status, $registeredBy]);

            adminUsersJsonResponse(true, 'RFID card assigned successfully.');
        }

        if ($action === 'update_user') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $fullName = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $department = trim($_POST['department'] ?? '');
            $role = trim($_POST['role'] ?? 'student');
            $status = trim($_POST['status'] ?? 'active');

            if ($userId <= 0 || $fullName === '' || $email === '') {
                adminUsersJsonResponse(false, 'Please fill in name and email.');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                adminUsersJsonResponse(false, 'Please enter a valid email address.');
            }

            if (!adminUsersValidPhone($phone)) {
                adminUsersJsonResponse(false, 'Please enter a valid phone number.');
            }

            if (!in_array($role, ['student', 'lecturer', 'staff', 'admin'], true)) {
                adminUsersJsonResponse(false, 'Invalid user role.');
            }

            $isActive = $status === 'active' ? 1 : 0;
            $profileImage = saveUserProfileImage($userId);

            if ($profileImage) {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, phone = ?, role = ?, department = ?, is_active = ?, profile_image = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $role, $department, $isActive, $profileImage, $userId]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE users
                    SET full_name = ?, email = ?, phone = ?, role = ?, department = ?, is_active = ?
                    WHERE user_id = ?
                ");
                $stmt->execute([$fullName, $email, $phone, $role, $department, $isActive, $userId]);
            }

            adminUsersJsonResponse(true, 'User updated successfully.', [
                'profile_image' => $profileImage
            ]);
        }

        if ($action === 'delete_user') {
            $userId = (int)($_POST['user_id'] ?? 0);

            if ($userId <= 0) {
                adminUsersJsonResponse(false, 'Invalid user selected.');
            }

            if ($userId === (int)($_SESSION['user_id'] ?? 0)) {
                adminUsersJsonResponse(false, 'You cannot delete your own admin account.');
            }

            $userStmt = $pdo->prepare("SELECT user_id, role FROM users WHERE user_id = ? LIMIT 1");
            $userStmt->execute([$userId]);
            $user = $userStmt->fetch();

            if (!$user) {
                adminUsersJsonResponse(false, 'User was not found.');
            }

            if (!in_array($user['role'], ['student', 'lecturer'], true)) {
                adminUsersJsonResponse(false, 'Only student and lecturer accounts can be deleted here.');
            }

            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ?");
            $stmt->execute([$userId]);

            $stmt = $pdo->prepare("UPDATE rfid_cards SET status = 'inactive' WHERE user_id = ?");
            $stmt->execute([$userId]);

            $pdo->commit();

            adminUsersJsonResponse(true, 'User deleted successfully. Existing attendance records were kept for reports.');
        }

        adminUsersJsonResponse(false, 'Unknown action.');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ($e->getCode() === '23000') {
            adminUsersJsonResponse(false, 'This ID, email or RFID UID already exists.');
        }

        adminUsersJsonResponse(false, 'Database error: ' . $e->getMessage());
    }
}

$usersStmt = $pdo->query("
    SELECT
        u.user_id,
        u.matric_no,
        u.full_name,
        u.role,
        u.email,
        u.phone,
        u.department,
        u.profile_image,
        u.is_active,
        rc.uid AS rfid_uid,
        rc.status AS card_status
    FROM users u
    LEFT JOIN rfid_cards rc
        ON rc.card_id = (
            SELECT rc2.card_id
            FROM rfid_cards rc2
            WHERE rc2.user_id = u.user_id
            ORDER BY rc2.issue_date DESC, rc2.card_id DESC
            LIMIT 1
        )
    WHERE u.is_active = 1
    ORDER BY u.created_at DESC, u.user_id DESC
");

$usersRows = $usersStmt->fetchAll();

$studentStatsRows = $pdo->query("
    SELECT
        u.user_id,
        COUNT(DISTINCT e.course_id) AS course_count,
        COUNT(DISTINCT ar.record_id) AS attendance_records,
        COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ar.record_id END) AS attended_records,
        COUNT(DISTINCT wl.letter_id) AS warning_count,
        COUNT(DISTINCT er.excuse_id) AS excuse_count
    FROM users u
    LEFT JOIN enrollments e
        ON e.student_id = u.user_id
       AND e.status = 'registered'
    LEFT JOIN attendance_records ar
        ON ar.student_id = u.user_id
       AND ar.status IN ('present', 'late', 'absent')
    LEFT JOIN warning_letters wl
        ON wl.student_id = u.user_id
       AND wl.status = 'issued'
    LEFT JOIN excuse_requests er
        ON er.student_id = u.user_id
    WHERE u.role = 'student'
    GROUP BY u.user_id
")->fetchAll();

$studentStats = [];
foreach ($studentStatsRows as $row) {
    $total = (int)$row['attendance_records'];
    $attended = (int)$row['attended_records'];
    $studentStats[(int)$row['user_id']] = [
        'courseCount' => (int)$row['course_count'],
        'attendanceRate' => $total > 0 ? round(($attended / $total) * 100) : 0,
        'attendanceRecords' => $total,
        'warningCount' => (int)$row['warning_count'],
        'excuseCount' => (int)$row['excuse_count']
    ];
}

$lecturerStatsRows = $pdo->query("
    SELECT
        u.user_id,
        COUNT(DISTINCT cs.course_id) AS course_count,
        COUNT(DISTINCT cs.schedule_id) AS schedule_count,
        COUNT(DISTINCT ats.session_id) AS session_count,
        COUNT(DISTINCT e.student_id) AS student_count
    FROM users u
    LEFT JOIN class_schedule cs
        ON cs.lecturer_id = u.user_id
       AND cs.is_active = 1
    LEFT JOIN attendance_sessions ats
        ON ats.schedule_id = cs.schedule_id
    LEFT JOIN enrollments e
        ON e.course_id = cs.course_id
       AND e.status = 'registered'
    WHERE u.role = 'lecturer'
    GROUP BY u.user_id
")->fetchAll();

$lecturerStats = [];
foreach ($lecturerStatsRows as $row) {
    $lecturerStats[(int)$row['user_id']] = [
        'courseCount' => (int)$row['course_count'],
        'scheduleCount' => (int)$row['schedule_count'],
        'sessionCount' => (int)$row['session_count'],
        'studentCount' => (int)$row['student_count']
    ];
}

$usersData = array_map(function ($row) {
    global $studentStats, $lecturerStats;

    $status = $row['is_active'] ? 'active' : 'inactive';
    $userId = (int)$row['user_id'];

    if (!empty($row['rfid_uid']) && !empty($row['card_status']) && $row['card_status'] !== 'active') {
        $status = 'inactive';
    }

    return [
        'id' => (string)$row['user_id'],
        'matric' => $row['matric_no'],
        'name' => $row['full_name'],
        'role' => $row['role'],
        'rfid' => $row['rfid_uid'] ?? '',
        'status' => $status,
        'email' => $row['email'],
        'phone' => $row['phone'] ?? '',
        'dept' => $row['department'] ?? '',
        'profileImage' => $row['profile_image'] ?? '',
        'cardStatus' => $row['card_status'] ?? '',
        'studentStats' => $studentStats[$userId] ?? [
            'courseCount' => 0,
            'attendanceRate' => 0,
            'attendanceRecords' => 0,
            'warningCount' => 0,
            'excuseCount' => 0
        ],
        'lecturerStats' => $lecturerStats[$userId] ?? [
            'courseCount' => 0,
            'scheduleCount' => 0,
            'sessionCount' => 0,
            'studentCount' => 0
        ]
    ];
}, $usersRows);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID IoT Attendance - Users & RFID Management</title>
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
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
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
            gap: 8px;
            text-decoration: none;
            font-size: 0.95rem;
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
            text-decoration: none;
            color: inherit;
        }

        .btn:active {
            transform: translateY(0);
        }

        /* FILTERS */
        .filters-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            box-shadow: var(--shadow);
        }

        .filters-row {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-group {
            flex: 1;
            min-width: 200px;
        }

        .filter-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-select, .filter-input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
        }

        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .search-box {
            position: relative;
            flex: 2;
            min-width: 300px;
        }

        .search-input {
            padding-left: 44px;
            background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='%236c757d' viewBox='0 0 16 16'%3E%3Cpath d='M11.742 10.344a6.5 6.5 0 1 0-1.397 1.398h-.001c.03.04.062.078.098.115l3.85 3.85a1 1 0 0 0 1.415-1.414l-3.85-3.85a1.007 1.007 0 0 0-.115-.1zM12 6.5a5.5 5.5 0 1 1-11 0 5.5 5.5 0 0 1 11 0z'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: 16px center;
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
        .stat-icon.active-cards { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .stat-icon.inactive-cards { background: linear-gradient(135deg, #f39c12, #e67e22); }

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

        /* USERS TABLE */
        .table-section {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
        }

        .table-header {
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

        .role-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .role-student { background: rgba(67, 97, 238, 0.15); color: var(--primary); }
        .role-lecturer { background: rgba(114, 9, 183, 0.15); color: var(--secondary); }
        .role-admin { background: rgba(46, 204, 113, 0.15); color: var(--success); }

        .status-badge-table {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .status-active { background: rgba(46, 204, 113, 0.15); color: #27ae60; }
        .status-inactive { background: rgba(243, 156, 18, 0.15); color: #d35400; }
        .status-pending { background: rgba(231, 76, 60, 0.15); color: #c0392b; }

        /* ACTION BUTTONS */
        .action-buttons-cell {
            display: grid;
            grid-template-columns: repeat(2, max-content);
            gap: 6px;
            align-items: center;
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 6px;
            border: none;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 6px;
            text-decoration: none;
        }

        .btn-action-sm {
            min-width: 92px;
            justify-content: center;
            padding: 7px 10px;
            font-size: 0.78rem;
        }

        .btn-assign { background: var(--primary); color: white; }
        .btn-replace { background: var(--warning); color: white; }
        .btn-edit { background: var(--success); color: white; }
        .btn-delete { background: var(--danger); color: white; }
        .btn-view { background: #6c757d; color: white; }

        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            text-decoration: none;
            color: inherit;
        }

        .view-profile {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1rem;
            background: linear-gradient(135deg, #f8fbff, #eef7f1);
            border: 1px solid #e2e8f0;
            border-radius: var(--border-radius);
            margin-bottom: 1.2rem;
        }

        .view-profile img {
            width: 86px;
            height: 86px;
            border-radius: 20px;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: var(--shadow);
            background: white;
        }

        .view-profile h4 {
            font-size: 1.35rem;
            margin-bottom: 0.25rem;
            color: var(--dark);
        }

        .view-detail-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.9rem;
        }

        .view-detail {
            padding: 0.9rem;
            border: 1px solid #edf1f5;
            border-radius: 10px;
            background: #fff;
        }

        .view-detail span {
            display: block;
            color: var(--gray);
            font-size: 0.82rem;
            font-weight: 800;
            text-transform: uppercase;
            margin-bottom: 0.3rem;
        }

        .view-detail strong {
            color: var(--dark);
            word-break: break-word;
        }

        .view-summary {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 0.8rem;
            margin-top: 1rem;
        }

        .view-summary-card {
            padding: 0.95rem;
            border-radius: 12px;
            background: #f8fafc;
            border: 1px solid #edf1f5;
            text-align: center;
        }

        .view-summary-card strong {
            display: block;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .view-summary-card span {
            color: var(--gray);
            font-size: 0.82rem;
            font-weight: 700;
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

        /* PAGINATION */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 0.5rem;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 1px solid #eee;
        }

        .page-btn {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            border: 1px solid #ddd;
            background: white;
            color: var(--gray);
            cursor: pointer;
            transition: var(--transition);
        }

        .page-btn:hover {
            border-color: var(--primary);
            color: var(--primary);
        }

        .page-btn.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .page-btn.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .filters-row {
                flex-direction: column;
            }
            
            .search-box {
                min-width: 100%;
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
            
            .action-buttons-cell {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .page-header {
                flex-direction: column;
                gap: 1rem;
            }
            
            .action-buttons {
                width: 100%;
                justify-content: stretch;
            }
            
            .action-buttons .btn {
                flex: 1;
                justify-content: center;
            }
            
            .form-row {
                grid-template-columns: 1fr;
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
            
            .main-content {
                padding: 1rem;
            }
            
            .modal-content {
                width: 95%;
                margin: 1rem;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <!-- MODALS -->
    <div id="addUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New User</h3>
                <button class="modal-close" onclick="closeModal('addUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addUserForm">
                    <div class="form-group">
                        <label class="form-label">User Type</label>
                        <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="radio" name="userType" value="student" checked> Student
                            </label>
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="radio" name="userType" value="lecturer"> Lecturer
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Matric/Staff ID</label>
                            <input type="text" class="form-control" id="userId" placeholder="e.g., DI230076" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="userName" placeholder="e.g., Nur Alya" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="userEmail" placeholder="user@university.edu" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="userPhone" placeholder="+60 12-345 6789">
                        </div>
                    </div>
                    
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
                        <label class="form-label">Assign RFID Now?</label>
                        <label style="display: flex; align-items: center; gap: 0.5rem; margin-top: 0.5rem;">
                            <input type="checkbox" id="assignRFIDNow" checked> Assign RFID card immediately
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addUserModal')">Cancel</button>
                <button class="btn btn-primary" onclick="addUser()">
                    <i class="fas fa-user-plus"></i> Add User
                </button>
            </div>
        </div>
    </div>

    <div id="assignRFIDModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Assign RFID Card</h3>
                <button class="modal-close" onclick="closeModal('assignRFIDModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">User</label>
                    <select class="form-control" id="assignUserSelect">
                        <option value="">Select a user...</option>
                        <?php foreach ($usersRows as $userOption): ?>
                            <option value="<?= htmlspecialchars($userOption['user_id']) ?>">
                                <?= htmlspecialchars($userOption['matric_no'] . ' - ' . $userOption['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Scan RFID Card</label>
                    <div style="text-align: center; padding: 2rem; border: 2px dashed #ddd; border-radius: 10px; margin-bottom: 1rem;">
                        <i class="fas fa-id-card" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <p>Enter the UID shown in Arduino Serial Monitor</p>
                        <div id="rfidScanStatus" style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                            Ready for RFID UID input
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">RFID UID</label>
                    <input type="text" class="form-control" id="assignRFIDUid" placeholder="e.g., 60CCFC61">
                </div>
                
                <div class="form-group">
                    <label class="form-label">Card Status</label>
                    <select class="form-control" id="cardStatus">
                        <option value="active">Active</option>
                        <option value="inactive">Inactive</option>
                        <option value="lost">Lost/Stolen</option>
                        <option value="damaged">Damaged</option>
                    </select>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('assignRFIDModal')">Cancel</button>
                <button class="btn btn-success" onclick="assignRFID()">
                    <i class="fas fa-id-card"></i> Assign Card
                </button>
            </div>
        </div>
    </div>

    <div id="editUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Edit User Details</h3>
                <button class="modal-close" onclick="closeModal('editUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="editUserForm">
                    <input type="hidden" id="editUserId">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="editUserName" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" id="editUserEmail" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Phone Number</label>
                            <input type="tel" class="form-control" id="editUserPhone">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Role</label>
                            <select class="form-control" id="editUserRole">
                                <option value="student">Student</option>
                                <option value="lecturer">Lecturer</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Course</label>
                        <select class="form-control" id="editUserDepartment" required>
                            <option value="BIW">BIW</option>
                            <option value="BIM">BIM</option>
                            <option value="BIP">BIP</option>
                            <option value="BIT">BIT</option>
                            <option value="BIS">BIS</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Student Profile Image</label>
                        <input type="file" class="form-control" id="editUserProfileImage" accept="image/jpeg,image/png,image/webp">
                        <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                            Upload JPG, PNG, or WEBP. This photo appears in lecturer live attendance when RFID/QR attendance is recorded.
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Account Status</label>
                        <select class="form-control" id="editUserStatus">
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="suspended">Suspended</option>
                        </select>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('editUserModal')">Cancel</button>
                <button class="btn btn-primary" onclick="saveUserEdit()">
                    <i class="fas fa-save"></i> Save Changes
                </button>
            </div>
        </div>
    </div>

    <div id="viewUserModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">User Details</h3>
                <button class="modal-close" onclick="closeModal('viewUserModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="view-profile">
                    <img id="viewUserImage" src="" alt="User profile">
                    <div>
                        <h4 id="viewUserName">User Name</h4>
                        <div id="viewUserIdText" style="color: var(--gray); font-weight: 700;">-</div>
                        <div style="margin-top: 0.5rem;">
                            <span id="viewUserRoleBadge" class="role-badge role-student">Student</span>
                            <span id="viewUserStatusBadge" class="status-badge-table status-active">Active</span>
                        </div>
                    </div>
                </div>

                <div class="view-detail-grid">
                    <div class="view-detail">
                        <span>Email</span>
                        <strong id="viewUserEmail">-</strong>
                    </div>
                    <div class="view-detail">
                        <span>Phone</span>
                        <strong id="viewUserPhone">-</strong>
                    </div>
                    <div class="view-detail">
                        <span>Course / Department</span>
                        <strong id="viewUserDepartment">-</strong>
                    </div>
                    <div class="view-detail">
                        <span>RFID UID</span>
                        <strong id="viewUserRfid">-</strong>
                    </div>
                    <div class="view-detail">
                        <span>RFID Card Status</span>
                        <strong id="viewUserCardStatus">-</strong>
                    </div>
                    <div class="view-detail">
                        <span>Account ID</span>
                        <strong id="viewUserAccountId">-</strong>
                    </div>
                </div>

                <div class="view-summary" id="viewUserSummary"></div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('viewUserModal')">Close</button>
                <button class="btn btn-primary" onclick="editViewedUser()">
                    <i class="fas fa-edit"></i> Edit User
                </button>
            </div>
        </div>
    </div>

    <div id="exportUsersModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Export User List</h3>
                <button class="modal-close" onclick="closeModal('exportUsersModal')">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">User List</label>
                    <select class="form-control" id="exportUserRole">
                        <option value="all">All Users</option>
                        <option value="student">Student List</option>
                        <option value="lecturer">Lecturer List</option>
                        <option value="admin">Admin List</option>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Course</label>
                    <select class="form-control" id="exportUserCourse">
                        <option value="all">All Courses</option>
                        <option value="BIW">BIW</option>
                        <option value="BIM">BIM</option>
                        <option value="BIP">BIP</option>
                        <option value="BIT">BIT</option>
                        <option value="BIS">BIS</option>
                    </select>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                        Use this to export student or lecturer lists by course group.
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Format</label>
                    <select class="form-control" id="exportUserFormat">
                        <option value="csv">CSV / Excel</option>
                        <option value="pdf">PDF Print View</option>
                    </select>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                        PDF Print View opens a neat report page. Use Print then Save as PDF.
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('exportUsersModal')">Cancel</button>
                <button class="btn btn-primary" onclick="exportData()">
                    <i class="fas fa-file-export"></i> Export
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
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Users & RFID Management</h1>
                    <p>Register users and manage RFID card assignments</p>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openModal('addUserModal')">
                        <i class="fas fa-user-plus"></i> Add User
                    </button>
                    <button class="btn btn-success" onclick="openModal('assignRFIDModal')">
                        <i class="fas fa-id-card"></i> Assign RFID
                    </button>
                    <button class="btn btn-outline" onclick="openModal('exportUsersModal')">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>

            <!-- FILTERS -->
            <div class="filters-section">
                <div class="filters-row">
                    <div class="filter-group">
                        <label class="filter-label">Role</label>
                        <select class="filter-select" id="roleFilter" onchange="filterUsers()">
                            <option value="all">All Roles</option>
                            <option value="student">Student</option>
                            <option value="lecturer">Lecturer</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="filterUsers()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">RFID Status</label>
                        <select class="filter-select" id="rfidFilter" onchange="filterUsers()">
                            <option value="all">All RFID</option>
                            <option value="assigned">Has RFID</option>
                            <option value="unassigned">No RFID</option>
                        </select>
                    </div>

                    <div class="filter-group">
                        <label class="filter-label">Course</label>
                        <select class="filter-select" id="courseFilter" onchange="filterUsers()">
                            <option value="all">All Courses</option>
                            <option value="BIW">BIW</option>
                            <option value="BIM">BIM</option>
                            <option value="BIP">BIP</option>
                            <option value="BIT">BIT</option>
                            <option value="BIS">BIS</option>
                        </select>
                    </div>
                    
                    <div class="search-box">
                        <label class="filter-label">Search</label>
                        <input type="text" class="filter-input search-input" id="searchInput"
                               placeholder="Search name / matric / RFID UID / course" onkeyup="filterUsers()">
                    </div>
                </div>
            </div>

            <!-- STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalStudents">120</div>
                            <div class="stat-label">Registered Students</div>
                        </div>
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-sub">+5 new this week</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalLecturers">18</div>
                            <div class="stat-label">Lecturers</div>
                        </div>
                        <div class="stat-icon lecturers">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="stat-sub">All departments</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="activeCards">102</div>
                            <div class="stat-label">Active RFID Cards</div>
                        </div>
                        <div class="stat-icon active-cards">
                            <i class="fas fa-id-card"></i>
                        </div>
                    </div>
                    <div class="stat-sub">93% activation rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="inactiveCards">16</div>
                            <div class="stat-label">Inactive RFID Cards</div>
                        </div>
                        <div class="stat-icon inactive-cards">
                            <i class="fas fa-id-card-alt"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Need assignment</div>
                </div>
            </div>

            <!-- USERS TABLE -->
            <div class="table-section">
                <div class="table-header">
                    <div class="section-title">User & RFID List</div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        Showing <span id="shownCount">8</span> of <span id="totalCount">138</span> users
                    </div>
                </div>
                
                <div class="table-container">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>Matric / Staff ID</th>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Course</th>
                                <th>RFID UID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="usersTableBody">
                            <!-- Data akan diisi oleh JavaScript -->
                        </tbody>
                    </table>
                </div>
                
                <!-- PAGINATION -->
                <div class="pagination">
                    <button class="page-btn" onclick="changePage('prev')">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    <button class="page-btn active">1</button>
                    <button class="page-btn" onclick="changePage(2)">2</button>
                    <button class="page-btn" onclick="changePage(3)">3</button>
                    <button class="page-btn" onclick="changePage(4)">4</button>
                    <span style="color: var(--gray); margin: 0 0.5rem;">...</span>
                    <button class="page-btn" onclick="changePage(10)">10</button>
                    <button class="page-btn" onclick="changePage('next')">
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>
            </div>
        </main>
    </div>

    <script>
        const usersData = <?= json_encode($usersData, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;

        let currentPage = 1;
        const itemsPerPage = 8;
        let filteredData = [...usersData];
        let currentViewedUserId = null;

        function escapeHtml(value) {
            return String(value ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function profileImageUrl(user) {
            if (user.profileImage) {
                if (/^https?:\/\//i.test(user.profileImage)) {
                    return user.profileImage;
                }

                return '../' + String(user.profileImage).replace(/^\/+/, '');
            }

            return `https://ui-avatars.com/api/?name=${encodeURIComponent(user.name || 'User')}&background=006837&color=fff&size=128`;
        }

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            populateUsersTable();
            updateStats();
            
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
            
            // Setup RFID scan simulation
            setupRFIDScan();
            
            // Update counts
            updateUserCounts();
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

        // Table Functions
        function populateUsersTable() {
            const tbody = document.getElementById('usersTableBody');
            tbody.innerHTML = '';
            
            const startIndex = (currentPage - 1) * itemsPerPage;
            const endIndex = startIndex + itemsPerPage;
            const pageData = filteredData.slice(startIndex, endIndex);
            
            pageData.forEach(user => {
                const row = document.createElement('tr');
                
                // Role badge
                let roleClass = '';
                let roleText = user.role.charAt(0).toUpperCase() + user.role.slice(1);
                switch(user.role) {
                    case 'student':
                        roleClass = 'role-student';
                        break;
                    case 'lecturer':
                        roleClass = 'role-lecturer';
                        break;
                    case 'admin':
                        roleClass = 'role-admin';
                        break;
                }
                
                // Status badge
                let statusClass = '';
                let statusText = user.status.charAt(0).toUpperCase() + user.status.slice(1);
                switch(user.status) {
                    case 'active':
                        statusClass = 'status-active';
                        break;
                    case 'inactive':
                        statusClass = 'status-inactive';
                        break;
                    case 'pending':
                        statusClass = 'status-pending';
                        break;
                }
                
                // RFID display
                const rfidDisplay = user.rfid ? user.rfid : '<span style="color: var(--gray); font-style: italic;">Not assigned</span>';
                
                // Action buttons
                let actionButtons = '';
                if (user.rfid) {
                    actionButtons = `
                        <button class="btn-action btn-replace btn-action-sm" onclick="replaceRFID('${user.id}')">
                            <i class="fas fa-sync-alt"></i> Replace RFID
                        </button>
                        <button class="btn-action btn-edit btn-action-sm" onclick="editUser('${user.id}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    `;
                } else {
                    actionButtons = `
                        <button class="btn-action btn-assign btn-action-sm" onclick="assignRFIDToUser('${user.id}')">
                            <i class="fas fa-id-card"></i> Assign RFID
                        </button>
                        <button class="btn-action btn-edit btn-action-sm" onclick="editUser('${user.id}')">
                            <i class="fas fa-edit"></i> Edit
                        </button>
                    `;
                }

                if (['student', 'lecturer'].includes(user.role)) {
                    actionButtons += `
                        <button class="btn-action btn-delete btn-action-sm" onclick="deleteUser('${user.id}')">
                            <i class="fas fa-trash"></i> Delete
                        </button>
                    `;
                }
                
                row.innerHTML = `
                    <td><strong>${user.matric || user.id}</strong></td>
                    <td>${user.name}</td>
                    <td><span class="role-badge ${roleClass}">${roleText}</span></td>
                    <td><strong>${escapeHtml(user.dept || '-')}</strong></td>
                    <td>${rfidDisplay}</td>
                    <td><span class="status-badge-table ${statusClass}">${statusText}</span></td>
                    <td>
                        <div class="action-buttons-cell">
                            ${actionButtons}
                            <button class="btn-action btn-view btn-action-sm" onclick="viewUser('${user.id}')">
                                <i class="fas fa-eye"></i> View
                            </button>
                        </div>
                    </td>
                `;
                
                tbody.appendChild(row);
            });
            
            // Update counts
            document.getElementById('shownCount').textContent = pageData.length;
            document.getElementById('totalCount').textContent = filteredData.length;
        }

        // Filter Functions
        function filterUsers() {
            const roleFilter = document.getElementById('roleFilter').value;
            const statusFilter = document.getElementById('statusFilter').value;
            const rfidFilter = document.getElementById('rfidFilter').value;
            const courseFilter = document.getElementById('courseFilter').value;
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            filteredData = usersData.filter(user => {
                // Role filter
                if (roleFilter !== 'all' && user.role !== roleFilter) return false;
                
                // Status filter
                if (statusFilter !== 'all' && user.status !== statusFilter) return false;
                
                // RFID filter
                if (rfidFilter === 'assigned' && !user.rfid) return false;
                if (rfidFilter === 'unassigned' && user.rfid) return false;

                // Course filter
                if (courseFilter !== 'all' && user.dept !== courseFilter) return false;

                if (searchTerm !== '') {
                    const matchesSearch =
                        user.id.toLowerCase().includes(searchTerm) ||
                        (user.matric && user.matric.toLowerCase().includes(searchTerm)) ||
                        user.name.toLowerCase().includes(searchTerm) ||
                        (user.rfid && user.rfid.toLowerCase().includes(searchTerm)) ||
                        (user.dept && user.dept.toLowerCase().includes(searchTerm));

                    if (!matchesSearch) return false;
                }
                
                return true;
            });
            
            currentPage = 1;
            populateUsersTable();
            updatePagination();
        }

        function searchUsers() {
            filterUsers();
        }

        function changePage(page) {
            if (page === 'prev' && currentPage > 1) {
                currentPage--;
            } else if (page === 'next' && currentPage < Math.ceil(filteredData.length / itemsPerPage)) {
                currentPage++;
            } else if (typeof page === 'number') {
                currentPage = page;
            }
            
            populateUsersTable();
            updatePagination();
        }

        function updatePagination() {
            // In a real app, update pagination buttons based on filtered data
            // For now, just update the active page button
            const pageBtns = document.querySelectorAll('.page-btn');
            pageBtns.forEach(btn => {
                btn.classList.remove('active');
                if (btn.textContent == currentPage) {
                    btn.classList.add('active');
                }
            });
        }

        // User Management Functions
        function addUser() {
            const userId = document.getElementById('userId').value;
            const userName = document.getElementById('userName').value;
            const userType = document.querySelector('input[name="userType"]:checked').value;
            const userEmail = document.getElementById('userEmail').value;
            const assignNow = document.getElementById('assignRFIDNow').checked;
            
            if (!userId || !userName || !userEmail) {
                showToast('Please fill in all required fields', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'add_user');
            formData.append('matric_no', userId);
            formData.append('full_name', userName);
            formData.append('email', userEmail);
            formData.append('phone', document.getElementById('userPhone').value);
            formData.append('department', document.getElementById('courseCode').value);
            formData.append('role', userType);

            fetch('users.php', {
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

                    usersData.unshift(data.user);
                    filteredData = [...usersData];
                    updateStats();
                    updateUserCounts();
                    populateUsersTable();

                    const option = document.createElement('option');
                    option.value = data.user.id;
                    option.textContent = `${data.user.matric} - ${data.user.name}`;
                    document.getElementById('assignUserSelect').prepend(option);

                    document.getElementById('addUserForm').reset();
                    closeModal('addUserModal');
                    showToast(data.message, 'success');

                    if (assignNow) {
                        document.getElementById('assignUserSelect').value = data.user.id;
                        setTimeout(() => openModal('assignRFIDModal'), 500);
                    }
                })
                .catch(() => showToast('Unable to add user. Please try again.', 'error'));
        }

        function editUser(userId) {
            const user = usersData.find(u => u.id === userId);
            if (!user) return;
            
            // Populate form
            document.getElementById('editUserId').value = user.id;
            document.getElementById('editUserName').value = user.name;
            document.getElementById('editUserEmail').value = user.email;
            document.getElementById('editUserPhone').value = user.phone;
            document.getElementById('editUserRole').value = user.role;
            document.getElementById('editUserDepartment').value = user.dept;
            document.getElementById('editUserStatus').value = user.status;
            document.getElementById('editUserProfileImage').value = '';
            
            openModal('editUserModal');
        }

        function saveUserEdit() {
            const userId = document.getElementById('editUserId').value;
            const userIndex = usersData.findIndex(u => u.id === userId);
            
            if (userIndex === -1) return;

            const formData = new FormData();
            formData.append('action', 'update_user');
            formData.append('user_id', userId);
            formData.append('full_name', document.getElementById('editUserName').value);
            formData.append('email', document.getElementById('editUserEmail').value);
            formData.append('phone', document.getElementById('editUserPhone').value);
            formData.append('role', document.getElementById('editUserRole').value);
            formData.append('department', document.getElementById('editUserDepartment').value);
            const selectedStatus = document.getElementById('editUserStatus').value;
            if (selectedStatus !== 'active') {
                const confirmed = confirm('This will deactivate the account and the user will not be able to login. Continue?');
                if (!confirmed) {
                    return;
                }
            }
            formData.append('status', selectedStatus);

            const imageInput = document.getElementById('editUserProfileImage');
            if (imageInput.files.length > 0) {
                formData.append('profile_image', imageInput.files[0]);
            }

            fetch('users.php', {
                method: 'POST',
                body: formData
            })
                .then(response => response.json())
                .then(data => {
                    if (!data.ok) {
                        showToast(data.message, 'error');
                        return;
                    }

                    usersData[userIndex].name = document.getElementById('editUserName').value;
                    usersData[userIndex].email = document.getElementById('editUserEmail').value;
                    usersData[userIndex].phone = document.getElementById('editUserPhone').value;
                    usersData[userIndex].role = document.getElementById('editUserRole').value;
                    usersData[userIndex].dept = document.getElementById('editUserDepartment').value;
                    usersData[userIndex].status = selectedStatus;
                    if (data.profile_image) {
                        usersData[userIndex].profileImage = data.profile_image;
                    }

                    const filteredIndex = filteredData.findIndex(u => u.id === userId);
                    if (filteredIndex !== -1) {
                        filteredData[filteredIndex] = { ...usersData[userIndex] };
                    }

                    closeModal('editUserModal');
                    populateUsersTable();
                    updateStats();
                    showToast(data.message, 'success');
                })
                .catch(() => showToast('Unable to update user. Please try again.', 'error'));
        }

        function assignRFIDToUser(userId) {
            const user = usersData.find(u => u.id === userId);
            if (user) {
                document.getElementById('assignUserSelect').value = userId;
                openModal('assignRFIDModal');
            }
        }

        function setupRFIDScan() {
            const modal = document.getElementById('assignRFIDModal');
            modal.addEventListener('click', function(e) {
                if (e.target.closest('.modal-content')) return;
            });
        }

        function simulateRFIDScan() {
            const rfidStatus = document.getElementById('rfidScanStatus');
            const rfidUid = document.getElementById('assignRFIDUid');
            
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

        function assignRFID() {
            const userId = document.getElementById('assignUserSelect').value;
            const rfidUid = document.getElementById('assignRFIDUid').value.trim().toUpperCase();
            const cardStatus = document.getElementById('cardStatus').value;
            
            if (!userId || !rfidUid) {
                showToast('Please select a user and enter RFID UID', 'error');
                return;
            }

            const formData = new URLSearchParams();
            formData.append('action', 'assign_rfid');
            formData.append('user_id', userId);
            formData.append('uid', rfidUid);
            formData.append('status', cardStatus);

            fetch('users.php', {
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
                .catch(() => showToast('Unable to assign RFID. Please try again.', 'error'));
        }

        function replaceRFID(userId) {
            const user = usersData.find(u => u.id === userId);
            if (!user) return;
            
            document.getElementById('assignUserSelect').value = userId;
            openModal('assignRFIDModal');
            
            showToast(`Replacing RFID for ${user.name}`, 'info');
        }

        function viewUser(userId) {
            const user = usersData.find(u => u.id === userId);
            if (!user) return;

            currentViewedUserId = userId;

            const roleText = user.role.charAt(0).toUpperCase() + user.role.slice(1);
            const statusText = user.status.charAt(0).toUpperCase() + user.status.slice(1);
            const roleClass = user.role === 'lecturer' ? 'role-lecturer' : (user.role === 'admin' ? 'role-admin' : 'role-student');
            const statusClass = user.status === 'active' ? 'status-active' : (user.status === 'pending' ? 'status-pending' : 'status-inactive');

            document.getElementById('viewUserImage').src = profileImageUrl(user);
            document.getElementById('viewUserName').textContent = user.name || '-';
            document.getElementById('viewUserIdText').textContent = user.matric || user.id || '-';
            document.getElementById('viewUserEmail').textContent = user.email || '-';
            document.getElementById('viewUserPhone').textContent = user.phone || '-';
            document.getElementById('viewUserDepartment').textContent = user.dept || '-';
            document.getElementById('viewUserRfid').textContent = user.rfid || 'Not assigned';
            document.getElementById('viewUserCardStatus').textContent = user.cardStatus ? user.cardStatus.toUpperCase() : (user.rfid ? user.status.toUpperCase() : 'Not assigned');
            document.getElementById('viewUserAccountId').textContent = user.id || '-';

            const roleBadge = document.getElementById('viewUserRoleBadge');
            roleBadge.className = `role-badge ${roleClass}`;
            roleBadge.textContent = roleText;

            const statusBadge = document.getElementById('viewUserStatusBadge');
            statusBadge.className = `status-badge-table ${statusClass}`;
            statusBadge.textContent = statusText;

            const summary = document.getElementById('viewUserSummary');
            if (user.role === 'student') {
                const stats = user.studentStats || {};
                summary.innerHTML = `
                    <div class="view-summary-card"><strong>${escapeHtml(stats.courseCount ?? 0)}</strong><span>Enrolled Courses</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.attendanceRate ?? 0)}%</strong><span>Attendance Rate</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.warningCount ?? 0)}</strong><span>Warning Letters</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.excuseCount ?? 0)}</strong><span>Excuse / MC Files</span></div>
                `;
            } else if (user.role === 'lecturer') {
                const stats = user.lecturerStats || {};
                summary.innerHTML = `
                    <div class="view-summary-card"><strong>${escapeHtml(stats.courseCount ?? 0)}</strong><span>Assigned Courses</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.scheduleCount ?? 0)}</strong><span>Schedules</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.sessionCount ?? 0)}</strong><span>Sessions</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(stats.studentCount ?? 0)}</strong><span>Students</span></div>
                `;
            } else {
                summary.innerHTML = `
                    <div class="view-summary-card"><strong>${escapeHtml(user.role)}</strong><span>System Role</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(user.status)}</strong><span>Account Status</span></div>
                    <div class="view-summary-card"><strong>${user.rfid ? 'Yes' : 'No'}</strong><span>RFID Assigned</span></div>
                    <div class="view-summary-card"><strong>${escapeHtml(user.dept || '-')}</strong><span>Course</span></div>
                `;
            }

            openModal('viewUserModal');
        }

        function editViewedUser() {
            if (!currentViewedUserId) return;
            closeModal('viewUserModal');
            editUser(currentViewedUserId);
        }

        function deleteUser(userId) {
            const user = usersData.find(u => u.id === userId);
            if (!user) return;

            if (!['student', 'lecturer'].includes(user.role)) {
                showToast('Only student and lecturer accounts can be deleted here.', 'error');
                return;
            }

            const confirmed = confirm(
                `Delete ${user.name}?\n\nThis will deactivate the account and RFID card, but existing attendance records will be kept for reports.`
            );

            if (!confirmed) return;

            const formData = new URLSearchParams();
            formData.append('action', 'delete_user');
            formData.append('user_id', userId);

            fetch('users.php', {
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

                    const allIndex = usersData.findIndex(u => u.id === userId);
                    if (allIndex !== -1) {
                        usersData.splice(allIndex, 1);
                    }

                    filteredData = filteredData.filter(u => u.id !== userId);
                    const totalPages = Math.max(1, Math.ceil(filteredData.length / itemsPerPage));
                    if (currentPage > totalPages) {
                        currentPage = totalPages;
                    }

                    populateUsersTable();
                    updatePagination();
                    updateStats();
                    showToast(data.message, 'success');
                })
                .catch(() => showToast('Unable to delete user. Please try again.', 'error'));
        }

        // Stats Functions
        function updateStats() {
            // Count students
            const students = usersData.filter(u => u.role === 'student').length;
            document.getElementById('totalStudents').textContent = students;
            
            // Count lecturers
            const lecturers = usersData.filter(u => u.role === 'lecturer').length;
            document.getElementById('totalLecturers').textContent = lecturers;
            
            // Count active RFID cards
            const activeCards = usersData.filter(u => u.rfid && u.status === 'active').length;
            document.getElementById('activeCards').textContent = activeCards;
            
            // Count inactive RFID cards
            const inactiveCards = usersData.filter(u => !u.rfid || u.status !== 'active').length;
            document.getElementById('inactiveCards').textContent = inactiveCards;
        }

        function updateUserCounts() {
            // Update total user count
            document.getElementById('totalCount').textContent = filteredData.length;
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

        // Export function
        function exportData() {
            const role = document.getElementById('exportUserRole').value;
            const format = document.getElementById('exportUserFormat').value;
            const course = document.getElementById('exportUserCourse').value;
            const url = `export_users.php?role=${encodeURIComponent(role)}&format=${encodeURIComponent(format)}&course=${encodeURIComponent(course)}`;

            closeModal('exportUsersModal');
            window.location.href = url;
            showToast(format === 'pdf' ? 'Opening PDF print view...' : 'Downloading CSV export...', 'success');
        }
    </script>
</body>
</html>
