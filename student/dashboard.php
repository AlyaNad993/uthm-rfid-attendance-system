<?php
require_once '../includes/auth_check.php';
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

if ($_SESSION['role'] !== 'student') {
    header('Location: ../unauthorized.php');
    exit();
}

$studentId = (int)$_SESSION['user_id'];

$stmt = $pdo->prepare("
    SELECT matric_no, full_name, email, phone, department, profile_image
    FROM users
    WHERE user_id = ?
    LIMIT 1
");
$stmt->execute([$studentId]);
$student = $stmt->fetch();

function studentUploadExcuseFile(array $file): string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please choose a valid file to upload.');
    }

    if (($file['size'] ?? 0) > 4 * 1024 * 1024) {
        throw new RuntimeException('File must be 4MB or below.');
    }

    $allowed = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $mimeType = mime_content_type($file['tmp_name']);
    if (!isset($allowed[$mimeType])) {
        throw new RuntimeException('Only PDF, JPG, PNG, or WEBP files are allowed.');
    }

    $uploadDir = __DIR__ . '/../uploads/excuse_documents';
    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0775, true);
    }

    $fileName = 'excuse_' . date('Ymd_His') . '_' . bin2hex(random_bytes(5)) . '.' . $allowed[$mimeType];
    $targetPath = $uploadDir . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        throw new RuntimeException('Unable to save uploaded file.');
    }

    return 'uploads/excuse_documents/' . $fileName;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'upload_excuse') {
    $recordId = (int)($_POST['record_id'] ?? 0);
    $selectedCourseForRedirect = (int)($_POST['selected_course_id'] ?? 0);
    $notes = trim($_POST['notes'] ?? '');

    try {
        $stmt = $pdo->prepare("
            SELECT
                ar.record_id,
                ar.session_id,
                ar.student_id,
                ar.status,
                cs.course_id,
                cs.lecturer_id,
                ats.session_date,
                TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
                TIME_FORMAT(ats.planned_end_time, '%H:%i') AS end_time,
                c.course_code,
                c.course_name
            FROM attendance_records ar
            JOIN attendance_sessions ats ON ats.session_id = ar.session_id
            JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
            JOIN courses c ON c.course_id = cs.course_id
            WHERE ar.record_id = ?
              AND ar.student_id = ?
              AND ar.status = 'absent'
            LIMIT 1
        ");
        $stmt->execute([$recordId, $studentId]);
        $record = $stmt->fetch();

        if (!$record) {
            throw new RuntimeException('Absent record was not found.');
        }

        $filePath = studentUploadExcuseFile($_FILES['excuse_file'] ?? []);
        $originalName = basename($_FILES['excuse_file']['name'] ?? 'excuse_document');

        $stmt = $pdo->prepare("
            INSERT INTO excuse_requests
                (record_id, session_id, student_id, course_id, file_path, original_name, notes, status)
            VALUES
                (?, ?, ?, ?, ?, ?, ?, 'pending')
            ON DUPLICATE KEY UPDATE
                file_path = VALUES(file_path),
                original_name = VALUES(original_name),
                notes = VALUES(notes),
                status = 'pending',
                excuse_type = NULL,
                submitted_at = CURRENT_TIMESTAMP,
                reviewed_at = NULL
        ");
        $stmt->execute([
            $record['record_id'],
            $record['session_id'],
            $studentId,
            $record['course_id'],
            $filePath,
            $originalName,
            $notes,
        ]);

        $notificationTitle = 'New excuse document submitted';
        $notificationMessage = sprintf(
            '%s (%s) uploaded an excuse document for %s - %s on %s, %s - %s.',
            $student['full_name'] ?? 'Student',
            $student['matric_no'] ?? '-',
            $record['course_code'],
            $record['course_name'],
            date('d M Y', strtotime($record['session_date'])),
            $record['start_time'],
            $record['end_time']
        );
        $relatedUrl = 'records.php?session_id=' . (int)$record['session_id'];

        $stmt = $pdo->prepare("
            INSERT INTO notifications
                (user_id, title, message, related_url, type, is_read)
            VALUES
                (?, ?, ?, ?, 'attendance', 0)
        ");
        $stmt->execute([
            (int)$record['lecturer_id'],
            $notificationTitle,
            $notificationMessage,
            $relatedUrl,
        ]);

        header('Location: dashboard.php?course_id=' . $selectedCourseForRedirect . '&excuse_uploaded=1');
        exit;
    } catch (Throwable $e) {
        header('Location: dashboard.php?course_id=' . $selectedCourseForRedirect . '&excuse_error=1');
        exit;
    }
}

$courseListStmt = $pdo->prepare("
    SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        c.semester,
        e.semester AS enrollment_semester,
        e.section_name,
        e.academic_year
    FROM enrollments e
    JOIN courses c ON c.course_id = e.course_id
    WHERE e.student_id = ?
      AND e.status = 'registered'
    ORDER BY c.course_code, e.section_name, e.semester
");
$courseListStmt->execute([$studentId]);
$studentCourses = $courseListStmt->fetchAll();

$selectedCourseId = (int)($_GET['course_id'] ?? 0);
$validCourseIds = array_map(fn($course) => (int)$course['course_id'], $studentCourses);
if ($selectedCourseId && !in_array($selectedCourseId, $validCourseIds, true)) {
    $selectedCourseId = 0;
}

$coursesStmt = $pdo->prepare("
    SELECT COUNT(*) AS total
    FROM enrollments
    WHERE student_id = ? AND status = 'registered'
");
$coursesStmt->execute([$studentId]);
$totalCourses = (int)($coursesStmt->fetch()['total'] ?? 0);

$attendanceStmt = $pdo->prepare("
    SELECT
        COUNT(*) AS total_records,
        SUM(CASE WHEN status = 'present' THEN 1 ELSE 0 END) AS present_records
    FROM attendance_records
    WHERE student_id = ?
");
$attendanceStmt->execute([$studentId]);
$attendanceSummary = $attendanceStmt->fetch();
$totalRecords = (int)($attendanceSummary['total_records'] ?? 0);
$presentRecords = (int)($attendanceSummary['present_records'] ?? 0);
$attendanceRate = $totalRecords > 0 ? round(($presentRecords / $totalRecords) * 100) : 0;

$rfidStmt = $pdo->prepare("
    SELECT uid, status
    FROM rfid_cards
    WHERE user_id = ?
    ORDER BY issue_date DESC, card_id DESC
    LIMIT 1
");
$rfidStmt->execute([$studentId]);
$rfid = $rfidStmt->fetch();

$courseSummaryStmt = $pdo->prepare("
    SELECT
        c.course_id,
        c.course_code,
        c.course_name,
        c.credit_hours,
        e.section_name,
        e.semester AS enrollment_semester,
        COUNT(ar.record_id) AS total_records,
        SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS attended_records,
        COUNT(DISTINCT wl.letter_id) AS warning_count
    FROM enrollments e
    JOIN courses c ON c.course_id = e.course_id
    LEFT JOIN class_schedule cs
        ON cs.course_id = c.course_id
       AND cs.section_name = e.section_name
       AND COALESCE(cs.academic_year, '') = COALESCE(e.academic_year, '')
    LEFT JOIN attendance_sessions ats ON ats.schedule_id = cs.schedule_id
    LEFT JOIN attendance_records ar
        ON ar.session_id = ats.session_id
       AND ar.student_id = e.student_id
       AND ar.status IN ('present', 'absent')
    LEFT JOIN warning_letters wl
        ON wl.course_id = c.course_id
       AND wl.student_id = e.student_id
       AND wl.status = 'issued'
    WHERE e.student_id = ?
      AND e.status = 'registered'
    GROUP BY c.course_id, c.course_code, c.course_name, c.credit_hours, e.section_name, e.semester
    ORDER BY c.course_code, e.section_name, e.semester
");
$courseSummaryStmt->execute([$studentId]);
$courseSummaries = $courseSummaryStmt->fetchAll();

$notificationStmt = $pdo->prepare("
    SELECT notification_id, title, message, related_url, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY is_read ASC, created_at DESC
    LIMIT 8
");
$notificationStmt->execute([$studentId]);
$notifications = $notificationStmt->fetchAll();

$recentParams = [$studentId];
$courseFilterSql = '';
if ($selectedCourseId > 0) {
    $courseFilterSql = ' AND c.course_id = ?';
    $recentParams[] = $selectedCourseId;
}

$recentStmt = $pdo->prepare("
    SELECT
        ar.record_id,
        c.course_code,
        c.course_name,
        c.course_id,
        ats.session_date,
        TIME_FORMAT(ats.planned_start_time, '%H:%i') AS start_time,
        ar.scan_time,
        ar.status,
        er.status AS excuse_status,
        er.excuse_type,
        er.original_name AS excuse_original_name
    FROM attendance_records ar
    JOIN attendance_sessions ats ON ats.session_id = ar.session_id
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    LEFT JOIN excuse_requests er
        ON er.record_id = ar.record_id
       AND er.student_id = ar.student_id
    WHERE ar.student_id = ?
    $courseFilterSql
    ORDER BY ar.scan_time DESC
    LIMIT 8
");
$recentStmt->execute($recentParams);
$recentRecords = $recentStmt->fetchAll();

function studentAttendanceStatusLabel(?string $value): string {
    return match ($value) {
        'mc' => 'MEDICAL CERTIFICATE',
        'excused' => 'EXCUSED',
        'absent' => 'ABSENT',
        default => 'PRESENT'
    };
}

function studentExcuseTypeLabel(?string $value): string {
    return match ($value) {
        'medical_certificate' => 'Medical Certificate',
        'excused_with_permission' => 'Excused With Permission',
        default => 'Excuse Document'
    };
}

function studentInitials(?string $name): string {
    $name = trim((string)$name);
    if ($name === '') {
        return 'ST';
    }

    $parts = preg_split('/\s+/', $name);
    $first = strtoupper(substr($parts[0] ?? 'S', 0, 1));
    $second = strtoupper(substr($parts[1] ?? ($parts[0] ?? 'T'), 0, 1));

    return $first . $second;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard | UTHM RFID Attendance</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Segoe UI', system-ui, sans-serif; }
        body { background: linear-gradient(135deg, #f5f8ff, #edf8f1); color: #172033; min-height: 100vh; }
        .topbar {
            background: rgba(255, 255, 255, 0.92);
            padding: 18px 28px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
            box-shadow: 0 8px 24px rgba(28, 52, 84, 0.08);
            backdrop-filter: blur(14px);
        }
        .brand { display: flex; align-items: center; gap: 14px; color: #172033; }
        .brand-icon {
            width: 46px;
            height: 46px;
            border-radius: 14px;
            display: grid;
            place-items: center;
            color: #fff;
            background: linear-gradient(135deg, #006837, #4361ee);
            box-shadow: 0 12px 24px rgba(67, 97, 238, 0.18);
            font-size: 20px;
        }
        .brand-title {
            font-size: clamp(24px, 3vw, 34px);
            line-height: 1;
            font-weight: 900;
            letter-spacing: 0;
            background: linear-gradient(135deg, #006837, #4361ee);
            -webkit-background-clip: text;
            background-clip: text;
            color: transparent;
        }
        .brand-accent {
            width: 70px;
            height: 4px;
            margin: 10px 0 6px;
            border-radius: 999px;
            background: linear-gradient(90deg, #006837, #4361ee);
        }
        .brand-subtitle { color: #68748a; font-size: 15px; }
        .header-right {
            display: flex;
            align-items: center;
            justify-content: flex-end;
            gap: 14px;
            flex-wrap: wrap;
        }
        .rfid-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            min-height: 42px;
            padding: 0 16px;
            border-radius: 999px;
            color: #fff;
            background: linear-gradient(135deg, #16a34a, #059669);
            box-shadow: 0 10px 20px rgba(22, 163, 74, 0.18);
            font-weight: 900;
            white-space: nowrap;
        }
        .rfid-pill.inactive { background: linear-gradient(135deg, #64748b, #475569); }
        .profile-menu { position: relative; }
        .profile-menu summary {
            list-style: none;
            display: flex;
            align-items: center;
            gap: 12px;
            min-height: 58px;
            padding: 8px 12px;
            border-radius: 16px;
            background: #f8fafc;
            border: 1px solid #eef2f7;
            cursor: pointer;
        }
        .profile-menu summary::-webkit-details-marker { display: none; }
        .profile-avatar {
            width: 42px;
            height: 42px;
            border-radius: 999px;
            display: grid;
            place-items: center;
            overflow: hidden;
            color: #fff;
            background: linear-gradient(135deg, #006837, #4361ee);
            border: 2px solid #fff;
            box-shadow: 0 10px 18px rgba(28, 52, 84, 0.16);
            font-weight: 900;
            flex: 0 0 auto;
        }
        .profile-avatar img { width: 100%; height: 100%; object-fit: cover; }
        .profile-copy { min-width: 0; text-align: left; }
        .profile-name {
            display: block;
            max-width: 180px;
            color: #172033;
            font-weight: 900;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .profile-meta { display: block; color: #68748a; font-size: 13px; margin-top: 2px; }
        .profile-chevron { color: #68748a; font-size: 13px; }
        .profile-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            width: 230px;
            padding: 10px;
            border-radius: 16px;
            background: #fff;
            border: 1px solid #eef2f7;
            box-shadow: 0 18px 36px rgba(28, 52, 84, 0.16);
            z-index: 20;
        }
        .profile-dropdown a,
        .profile-dropdown span {
            display: flex;
            align-items: center;
            gap: 10px;
            min-height: 40px;
            padding: 0 10px;
            border-radius: 10px;
            color: #172033;
            text-decoration: none;
            font-weight: 800;
        }
        .profile-dropdown span { color: #68748a; font-size: 13px; }
        .profile-dropdown a:hover { background: #f1f5f9; }
        .profile-dropdown .logout-link { color: #b42318; }
        .wrap { max-width: 1180px; margin: 0 auto; padding: 32px 20px; }
        .hero { display: flex; justify-content: space-between; gap: 20px; align-items: flex-start; margin-bottom: 24px; }
        .student-hero-profile { display: flex; align-items: center; gap: 18px; }
        .student-photo-large { width: 96px; height: 96px; border-radius: 24px; object-fit: cover; border: 3px solid rgba(255,255,255,0.9); box-shadow: 0 16px 34px rgba(28, 52, 84, 0.18); background: #fff; }
        .hero h1 { font-size: clamp(28px, 4vw, 42px); margin-bottom: 8px; }
        .muted { color: #68748a; line-height: 1.6; }
        .badge { display: inline-flex; align-items: center; gap: 8px; padding: 10px 14px; border-radius: 999px; background: #ecfdf3; color: #067647; font-weight: 800; }
        .grid { display: grid; grid-template-columns: repeat(4, minmax(0, 1fr)); gap: 18px; margin-bottom: 24px; }
        .card { background: white; border-radius: 16px; padding: 22px; box-shadow: 0 12px 30px rgba(28, 52, 84, 0.08); border: 1px solid #edf1f5; }
        .stat-icon { width: 44px; height: 44px; display: grid; place-items: center; border-radius: 12px; color: white; margin-bottom: 14px; }
        .blue { background: linear-gradient(135deg, #4361ee, #3a56d4); }
        .green { background: linear-gradient(135deg, #2ecc71, #27ae60); }
        .orange { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .purple { background: linear-gradient(135deg, #7209b7, #5a0a9c); }
        .number { font-size: 32px; font-weight: 900; }
        .section { background: white; border-radius: 16px; padding: 24px; box-shadow: 0 12px 30px rgba(28, 52, 84, 0.08); border: 1px solid #edf1f5; }
        .section h2 { margin-bottom: 16px; }
        .section + .section { margin-top: 24px; }
        .section-head { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; margin-bottom: 16px; }
        .course-filter { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; }
        .course-filter label { color: #68748a; font-weight: 800; font-size: 13px; text-transform: uppercase; }
        .course-filter select { min-width: 260px; min-height: 42px; padding: 0 12px; border: 1px solid #d7dde6; border-radius: 12px; background: #fff; color: #172033; font-weight: 700; }
        table { width: 100%; border-collapse: collapse; min-width: 720px; }
        .summary-table { min-width: 900px; }
        th, td { text-align: left; padding: 14px 16px; border-bottom: 1px solid #edf1f5; }
        th { color: #68748a; font-size: 13px; text-transform: uppercase; }
        .table-wrap { overflow-x: auto; }
        .status { display: inline-flex; padding: 6px 10px; border-radius: 999px; background: #ecfdf3; color: #067647; font-weight: 800; font-size: 13px; }
        .status.absent { background: #fee2e2; color: #991b1b; }
        .status.late { background: #fef3c7; color: #92400e; }
        .status.excused { background: #e0f2fe; color: #0369a1; }
        .status.mc { background: #f3e8ff; color: #6b21a8; }
        .status.pending { background: #fef3c7; color: #92400e; }
        .mini-tag { display: inline-flex; margin-top: 5px; padding: 3px 8px; border-radius: 6px; background: #e0f2fe; color: #0369a1; font-size: 11px; font-weight: 900; }
        .percentage { color: #1476b8; font-weight: 900; }
        .warning-text { font-weight: 900; color: #991b1b; }
        .notification-list { display: grid; gap: 12px; }
        .notification-item { display: block; border: 1px solid #e5eaf1; border-left: 5px solid #006837; border-radius: 14px; padding: 14px 16px; background: #fff; box-shadow: 0 10px 24px rgba(28, 52, 84, 0.07); color: inherit; text-decoration: none; }
        .notification-item.unread { border-left-color: #dc2626; background: #fff7f7; }
        .notification-title { font-weight: 900; color: #172033; margin-bottom: 4px; }
        .notification-meta { color: #66738a; font-size: 12px; font-weight: 700; margin-top: 6px; }
        .upload-form { display: grid; gap: 8px; min-width: 230px; }
        .upload-form input[type="file"], .upload-form input[type="text"] { width: 100%; border: 1px solid #d7dde6; border-radius: 10px; padding: 8px; font-size: 12px; }
        .upload-form button { min-height: 34px; border: 0; border-radius: 10px; background: linear-gradient(135deg, #006837, #4361ee); color: #fff; font-weight: 800; cursor: pointer; }
        .alert { margin-bottom: 16px; padding: 12px 14px; border-radius: 12px; font-weight: 800; }
        .alert-success { background: #dcfce7; color: #166534; }
        .alert-error { background: #fee2e2; color: #991b1b; }
        .empty { color: #68748a; padding: 24px; text-align: center; background: #f8fafc; border-radius: 12px; }
        @media (max-width: 900px) { .grid { grid-template-columns: repeat(2, 1fr); } .hero, .section-head { flex-direction: column; } }
        @media (max-width: 640px) { .student-hero-profile { align-items: flex-start; } .student-photo-large { width: 78px; height: 78px; border-radius: 18px; } }
        @media (max-width: 720px) {
            .topbar { align-items: flex-start; flex-direction: column; }
            .header-right { width: 100%; justify-content: space-between; }
            .profile-menu { margin-left: auto; }
        }
        @media (max-width: 560px) {
            .grid { grid-template-columns: 1fr; }
            .header-right { align-items: stretch; flex-direction: column; }
            .profile-menu { width: 100%; margin-left: 0; }
            .profile-menu summary { justify-content: space-between; }
            .profile-dropdown { left: 0; right: 0; width: auto; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <style>
        .brand-icon i {
            color: #fff !important;
        }
        .student-header .topbar,
        .topbar {
            border-radius: 0 !important;
        }
    </style>
</head>
<body>
    <header class="topbar">
        <div class="brand">
            <div class="brand-icon"><i class="fas fa-id-card"></i></div>
            <div>
                <div class="brand-title">RFID IoT Attendance</div>
                <div class="brand-accent"></div>
                <div class="brand-subtitle">Student Portal</div>
            </div>
        </div>

        <div class="header-right">
            <div class="rfid-pill <?= ($rfid && strtolower((string)$rfid['status']) === 'active') ? '' : 'inactive' ?>">
                <i class="fas fa-wifi"></i>
                RFID <?= $rfid ? htmlspecialchars(strtoupper($rfid['status'])) : 'NOT REGISTERED' ?>
            </div>

            <details class="profile-menu">
                <summary>
                    <span class="profile-avatar">
                        <?php if (!empty($student['profile_image'])): ?>
                            <img src="<?= htmlspecialchars(profileImageUrl($student['profile_image'], $student['full_name'] ?? 'Student')) ?>" alt="<?= htmlspecialchars($student['full_name'] ?? 'Student') ?>">
                        <?php else: ?>
                            <?= htmlspecialchars(studentInitials($student['full_name'] ?? 'Student')) ?>
                        <?php endif; ?>
                    </span>
                    <span class="profile-copy">
                        <span class="profile-name"><?= htmlspecialchars($student['full_name'] ?? 'Student') ?></span>
                        <span class="profile-meta"><?= htmlspecialchars($student['matric_no'] ?? '-') ?></span>
                    </span>
                    <i class="fas fa-chevron-down profile-chevron"></i>
                </summary>
                <div class="profile-dropdown">
                    <span><i class="fas fa-envelope"></i><?= htmlspecialchars($student['email'] ?? '-') ?></span>
                    <a class="logout-link" href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                </div>
            </details>
        </div>
    </header>

    <main class="wrap">
        <?php if (isset($_GET['excuse_uploaded'])): ?>
            <div class="alert alert-success">Document uploaded successfully. Your excuse request is pending lecturer/admin review.</div>
        <?php endif; ?>

        <?php if (isset($_GET['excuse_error'])): ?>
            <div class="alert alert-error">Unable to upload document. Please use PDF, JPG, PNG, or WEBP under 4MB.</div>
        <?php endif; ?>

        <section class="hero">
            <div class="student-hero-profile">
                <img
                    class="student-photo-large"
                    src="<?= htmlspecialchars(profileImageUrl($student['profile_image'] ?? '', $student['full_name'] ?? 'Student')) ?>"
                    alt="<?= htmlspecialchars($student['full_name'] ?? 'Student') ?>"
                >
                <div>
                    <h1>Hello, <?= htmlspecialchars($student['full_name'] ?? 'Student') ?></h1>
                    <p class="muted"><?= htmlspecialchars($student['matric_no'] ?? '-') ?> &middot; <?= htmlspecialchars($student['email'] ?? '-') ?></p>
                </div>
            </div>
        </section>

        <section class="grid">
            <div class="card">
                <div class="stat-icon blue"><i class="fas fa-book"></i></div>
                <div class="number"><?= $totalCourses ?></div>
                <p class="muted">Registered Courses</p>
            </div>
            <div class="card">
                <div class="stat-icon green"><i class="fas fa-clipboard-check"></i></div>
                <div class="number"><?= $attendanceRate ?>%</div>
                <p class="muted">Attendance Rate</p>
            </div>
            <div class="card">
                <div class="stat-icon orange"><i class="fas fa-check"></i></div>
                <div class="number"><?= $presentRecords ?></div>
                <p class="muted">Present Records</p>
            </div>
            <div class="card">
                <div class="stat-icon purple"><i class="fas fa-id-card"></i></div>
                <div class="number"><?= $rfid ? htmlspecialchars($rfid['uid']) : '-' ?></div>
                <p class="muted">RFID UID</p>
            </div>
        </section>

        <?php if (!empty($notifications)): ?>
            <section class="section">
                <div class="section-head">
                    <div>
                        <h2>Notifications</h2>
                        <p class="muted">Latest updates from your attendance records.</p>
                    </div>
                </div>
                <div class="notification-list">
                    <?php foreach ($notifications as $notification): ?>
                        <?php
                            $notificationUrl = trim((string)($notification['related_url'] ?? ''));
                            if (preg_match('/^dashboard\.php\?warning_letter=(\d+)$/', $notificationUrl, $matches)) {
                                $notificationUrl = 'warning_letter.php?letter_id=' . $matches[1];
                            }
                        ?>
                        <<?= $notificationUrl !== '' ? 'a href="' . htmlspecialchars($notificationUrl) . '"' : 'div' ?> class="notification-item <?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?>">
                            <div class="notification-title"><?= htmlspecialchars($notification['title']) ?></div>
                            <div><?= htmlspecialchars($notification['message']) ?></div>
                            <div class="notification-meta"><?= htmlspecialchars(date('d M Y, h:i A', strtotime($notification['created_at']))) ?></div>
                        </<?= $notificationUrl !== '' ? 'a' : 'div' ?>>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <section class="section">
            <div class="section-head">
                <div>
                    <h2>Course Attendance Summary</h2>
                    <p class="muted">Choose a subject below to filter recent attendance records.</p>
                </div>
                <form class="course-filter" method="GET" action="dashboard.php">
                    <label for="course_id">Subject</label>
                    <select id="course_id" name="course_id" onchange="this.form.submit()">
                        <option value="0">All Subjects</option>
                        <?php foreach ($studentCourses as $course): ?>
                            <option value="<?= htmlspecialchars($course['course_id']) ?>" <?= $selectedCourseId === (int)$course['course_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($course['course_code']) ?> - <?= htmlspecialchars($course['course_name']) ?>
                                | <?= htmlspecialchars($course['section_name'] ?: 'Section 1') ?>
                                <?= $course['enrollment_semester'] ? ' | ' . htmlspecialchars($course['enrollment_semester']) : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>

            <?php if (empty($courseSummaries)): ?>
                <div class="empty">No registered courses found.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table class="summary-table">
                        <thead>
                            <tr>
                                <th>Course Code</th>
                                <th>Course Name</th>
                                <th>Section</th>
                                <th>Credit</th>
                                <th>Attendance (%)</th>
                                <th>Warning Letter</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($courseSummaries as $course): ?>
                                <?php
                                    $totalCourseRecords = (int)$course['total_records'];
                                    $attendedCourseRecords = (int)$course['attended_records'];
                                    $coursePercentage = $totalCourseRecords > 0 ? round(($attendedCourseRecords / $totalCourseRecords) * 100) : 0;
                                    $warningCount = (int)$course['warning_count'];
                                ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($course['course_code']) ?></strong></td>
                                    <td>
                                        <?= htmlspecialchars($course['course_name']) ?>
                                        <br><span class="mini-tag">NORMAL</span>
                                    </td>
                                    <td>
                                        <?= htmlspecialchars($course['section_name'] ?: 'Section 1') ?>
                                        <br><span class="muted"><?= htmlspecialchars($course['enrollment_semester'] ?: '-') ?></span>
                                    </td>
                                    <td><?= htmlspecialchars($course['credit_hours'] ?? '-') ?></td>
                                    <td><span class="percentage"><?= $coursePercentage ?></span></td>
                                    <td>
                                        <?= $warningCount > 0 ? '<span class="warning-text">' . $warningCount . ' issued</span>' : '-' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>

        <section class="section">
            <h2>Recent Attendance</h2>
            <?php if (empty($recentRecords)): ?>
                <div class="empty">No attendance record yet. Your scans will appear here after lecturer sessions are created.</div>
            <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Course</th>
                                <th>Date</th>
                                <th>Class Time</th>
                                <th>Scan Time</th>
                                <th>Status</th>
                                <th>Excuse / MC</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentRecords as $record): ?>
                                <?php $statusClass = in_array($record['status'], ['absent', 'excused', 'mc'], true) ? $record['status'] : ''; ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($record['course_code']) ?></strong><br><span class="muted"><?= htmlspecialchars($record['course_name']) ?></span></td>
                                    <td><?= htmlspecialchars($record['session_date']) ?></td>
                                    <td><?= htmlspecialchars($record['start_time']) ?></td>
                                    <td><?= htmlspecialchars($record['scan_time'] ?? '-') ?></td>
                                    <td><span class="status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(studentAttendanceStatusLabel($record['status'])) ?></span></td>
                                    <td>
                                        <?php if ($record['status'] === 'absent'): ?>
                                            <?php if (!empty($record['excuse_status'])): ?>
                                                <span class="status <?= $record['excuse_status'] === 'rejected' ? 'absent' : ($record['excuse_status'] === 'approved' ? '' : 'pending') ?>">
                                                    <?= htmlspecialchars(strtoupper($record['excuse_status'])) ?>
                                                </span>
                                                <?php if ($record['excuse_status'] === 'rejected'): ?>
                                                    <form class="upload-form" method="POST" action="dashboard.php" enctype="multipart/form-data">
                                                        <input type="hidden" name="action" value="upload_excuse">
                                                        <input type="hidden" name="record_id" value="<?= htmlspecialchars($record['record_id']) ?>">
                                                        <input type="hidden" name="selected_course_id" value="<?= htmlspecialchars($selectedCourseId) ?>">
                                                        <input type="file" name="excuse_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                                        <input type="text" name="notes" placeholder="Upload replacement document">
                                                        <button type="submit">Upload Again</button>
                                                    </form>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <form class="upload-form" method="POST" action="dashboard.php" enctype="multipart/form-data">
                                                    <input type="hidden" name="action" value="upload_excuse">
                                                    <input type="hidden" name="record_id" value="<?= htmlspecialchars($record['record_id']) ?>">
                                                    <input type="hidden" name="selected_course_id" value="<?= htmlspecialchars($selectedCourseId) ?>">
                                                    <input type="file" name="excuse_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                                    <input type="text" name="notes" placeholder="Reason / note">
                                                    <button type="submit">Upload Document</button>
                                                </form>
                                            <?php endif; ?>
                                        <?php elseif (in_array($record['status'], ['mc', 'excused'], true)): ?>
                                            <span class="status <?= htmlspecialchars($record['status']) ?>">
                                                <?= htmlspecialchars(studentExcuseTypeLabel($record['excuse_type'])) ?>
                                            </span>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
