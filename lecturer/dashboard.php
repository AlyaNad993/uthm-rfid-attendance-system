<?php
require_once '../includes/auth_check.php';
requireLecturer();

require_once __DIR__ . '/../includes/config.php';

$lecturer_id = $_SESSION['user_id'];
$today = date('l'); // Monday, Tuesday, ...

$stmt = $pdo->prepare("SELECT full_name FROM users WHERE user_id = ? LIMIT 1");
$stmt->execute([$lecturer_id]);
$lecturerName = $stmt->fetchColumn() ?: ($_SESSION['name'] ?? 'Lecturer');

/* =========================
   KPI DATA
   ========================= */

// Total courses taught by lecturer
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT course_id)
    FROM class_schedule
    WHERE lecturer_id = ?
      AND is_active = 1
");
$stmt->execute([$lecturer_id]);
$total_courses = $stmt->fetchColumn();

// Classes today
$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM class_schedule
    WHERE lecturer_id = ?
      AND day_of_week = ?
      AND is_active = 1
");
$stmt->execute([$lecturer_id, $today]);
$classes_today = $stmt->fetchColumn();

// Students attended today
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT ar.student_id)
    FROM attendance_records ar
    JOIN attendance_sessions s ON ar.session_id = s.session_id
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    WHERE cs.lecturer_id = ?
      AND s.session_date = CURDATE()
      AND ar.status = 'present'
");
$stmt->execute([$lecturer_id]);
$students_today = $stmt->fetchColumn();

// At-risk students (< 80%)
$stmt = $pdo->prepare("
    SELECT COUNT(DISTINCT e.student_id)
    FROM class_schedule cs
    JOIN enrollments e
      ON cs.course_id = e.course_id
     AND e.section_name = cs.section_name
     AND COALESCE(e.academic_year, '') = COALESCE(cs.academic_year, '')
    WHERE cs.lecturer_id = ?
      AND cs.day_of_week = ?
      AND cs.is_active = 1
      AND e.status = 'registered'
");
$stmt->execute([$lecturer_id, $today]);
$total_enrolled = (int)$stmt->fetchColumn();

$attendance_rate = $total_enrolled > 0 ? round(((int)$students_today / $total_enrolled) * 100) : 0;

$stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM (
        SELECT
            ar.student_id,
            SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) AS attended_count,
            SUM(CASE WHEN ar.status IN ('present', 'late', 'absent') THEN 1 ELSE 0 END) AS marked_count
        FROM attendance_records ar
        JOIN attendance_sessions s ON ar.session_id = s.session_id
        JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
        WHERE cs.lecturer_id = ?
        GROUP BY ar.student_id
        HAVING marked_count > 0
           AND (attended_count / marked_count) < 0.8
    ) risk
");
$stmt->execute([$lecturer_id]);
$at_risk = (int)$stmt->fetchColumn();

$stmt = $pdo->prepare("
    SELECT
        s.session_id,
        s.session_date,
        s.planned_start_time,
        s.planned_end_time,
        s.session_status,
        s.total_expected,
        s.total_present,
        s.total_late,
        s.total_absent,
        c.course_code,
        c.course_name,
        r.room_name,
        r.room_code
    FROM attendance_sessions s
    JOIN class_schedule cs ON s.schedule_id = cs.schedule_id
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE cs.lecturer_id = ?
    ORDER BY s.session_date DESC, s.planned_start_time DESC
    LIMIT 5
");
$stmt->execute([$lecturer_id]);
$recent_sessions = $stmt->fetchAll();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID IoT Attendance - Lecturer Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', system-ui, sans-serif;
        }

        :root {
            --lecturer-primary: #4f46e5;
            --lecturer-secondary: #7c3aed;
            --lecturer-accent: #06b6d4;
            --lecturer-light: #f8fafc;
            --lecturer-dark: #1e293b;
            --lecturer-gray: #64748b;
            --lecturer-success: #10b981;
            --lecturer-warning: #f59e0b;
            --lecturer-danger: #ef4444;
            --lecturer-border: #e2e8f0;
            --border-radius: 12px;
            --shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
            --transition: all 0.3s ease;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #e4edf5 100%);
            min-height: 100vh;
            color: var(--lecturer-dark);
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
        }

        .logo i {
            font-size: 2rem;
            color: var(--lecturer-primary);
            background: linear-gradient(135deg, var(--lecturer-primary), var(--lecturer-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo h1 {
            font-size: 1.8rem;
            font-weight: 700;
            background: linear-gradient(135deg, var(--lecturer-primary), var(--lecturer-secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo span {
            font-size: 0.9rem;
            color: var(--lecturer-gray);
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
            background: linear-gradient(135deg, var(--lecturer-success), #0da271);
            color: white;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 500;
        }

        .status-badge.offline {
            background: linear-gradient(135deg, var(--lecturer-danger), #dc2626);
        }

        .lecturer-profile {
            position: relative;
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            background: var(--lecturer-light);
            cursor: pointer;
            transition: var(--transition);
            border: 0;
            color: inherit;
        }

        .lecturer-profile:hover {
            background: #e9ecef;
        }

        .lecturer-profile img {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--lecturer-primary);
        }

        .lecturer-menu {
            position: relative;
        }

        .profile-caret {
            color: var(--lecturer-gray);
            font-size: 0.8rem;
            margin-left: 4px;
        }

        .lecturer-dropdown {
            position: absolute;
            right: 0;
            top: calc(100% + 10px);
            width: 230px;
            padding: 8px;
            background: white;
            border: 1px solid var(--lecturer-border);
            border-radius: 14px;
            box-shadow: 0 18px 40px rgba(15, 23, 42, 0.16);
            opacity: 0;
            visibility: hidden;
            transform: translateY(-6px);
            transition: var(--transition);
            z-index: 40;
        }

        .lecturer-menu:hover .lecturer-dropdown,
        .lecturer-menu:focus-within .lecturer-dropdown {
            opacity: 1;
            visibility: visible;
            transform: translateY(0);
        }

        .lecturer-dropdown a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px;
            border-radius: 10px;
            color: var(--lecturer-dark);
            text-decoration: none;
            font-weight: 700;
            transition: var(--transition);
        }

        .lecturer-dropdown a:hover {
            background: linear-gradient(135deg, rgba(0, 104, 55, 0.08), rgba(67, 97, 238, 0.08));
            color: #006837;
        }

        .lecturer-dropdown .danger-link {
            color: var(--lecturer-danger);
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
            color: var(--lecturer-gray);
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
            color: var(--lecturer-gray);
            text-decoration: none;
            transition: var(--transition);
            cursor: pointer;
        }

        .nav-item:hover, .nav-item.active {
            background: linear-gradient(135deg, var(--lecturer-primary), var(--lecturer-secondary));
            color: white;
            transform: translateX(5px);
        }

        .nav-item i {
            width: 20px;
            text-align: center;
            font-size: 1.2rem;
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
            color: var(--lecturer-dark);
        }

        .dashboard-header p {
            color: var(--lecturer-gray);
            margin-top: 5px;
        }

        .welcome-banner {
            background: linear-gradient(135deg, var(--lecturer-primary), var(--lecturer-secondary));
            color: white;
            padding: 2rem;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            position: relative;
            overflow: hidden;
        }

        .welcome-banner::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200px;
            height: 200px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }

        .welcome-banner h2 {
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .banner-stats {
            display: flex;
            gap: 2rem;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-top: 1rem;
        }

        .stat-item {
            text-align: center;
        }

        .stat-item .number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        .stat-item .label {
            font-size: 0.9rem;
            opacity: 0.8;
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
            background: linear-gradient(to bottom, var(--lecturer-primary), var(--lecturer-secondary));
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

        .stat-icon.courses { background: linear-gradient(135deg, #4f46e5, #4338ca); }
        .stat-icon.classes { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .stat-icon.students { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .stat-icon.risk { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--lecturer-dark);
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            color: var(--lecturer-gray);
            font-size: 1rem;
        }

        .stat-sub {
            font-size: 0.9rem;
            color: var(--lecturer-gray);
            margin-top: 10px;
            padding-top: 10px;
            border-top: 1px solid #eee;
        }

        /* TODAY'S CLASSES */
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
            color: var(--lecturer-dark);
        }

        .classes-grid {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .class-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.2rem;
            background: #f8fafc;
            border-radius: 10px;
            border-left: 4px solid var(--lecturer-primary);
            transition: var(--transition);
        }

        .class-item:hover {
            transform: translateX(5px);
            box-shadow: var(--shadow);
        }

        .class-time {
            background: var(--lecturer-primary);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            text-align: center;
            min-width: 100px;
        }

        .class-time .start {
            font-size: 1.1rem;
            font-weight: 600;
            display: block;
        }

        .class-time .duration {
            font-size: 0.8rem;
            opacity: 0.8;
        }

        .class-info {
            flex: 1;
        }

        .class-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--lecturer-dark);
        }

        .class-details {
            display: flex;
            gap: 1rem;
            color: var(--lecturer-gray);
            font-size: 0.9rem;
        }

        .class-actions {
            display: flex;
            gap: 10px;
        }

        /* BUTTONS */
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
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--lecturer-primary), var(--lecturer-secondary));
            color: white;
        }

        .btn-secondary {
            background: #6c757d;
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, var(--lecturer-success), #0da271);
            color: white;
        }

        .btn-outline {
            background: transparent;
            color: var(--lecturer-primary);
            border: 1px solid var(--lecturer-primary);
        }

        .btn:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.1);
        }

        .btn:active {
            transform: translateY(0);
        }

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
            border-color: var(--lecturer-primary);
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

        .action-icon.live { background: linear-gradient(135deg, #4f46e5, #4338ca); }
        .action-icon.records { background: linear-gradient(135deg, #7c3aed, #6d28d9); }
        .action-icon.reports { background: linear-gradient(135deg, #06b6d4, #0891b2); }
        .action-icon.calendar { background: linear-gradient(135deg, #f59e0b, #d97706); }

        .action-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--lecturer-dark);
        }

        .action-desc {
            color: var(--lecturer-gray);
            font-size: 0.9rem;
            line-height: 1.4;
        }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            color: var(--lecturer-gray);
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            color: var(--lecturer-dark);
        }

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
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
            
            .class-item {
                flex-direction: column;
                text-align: center;
            }
            
            .class-details {
                justify-content: center;
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
            
            .banner-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .class-actions {
                flex-direction: column;
                width: 100%;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
</head>
<body>
    <!-- DASHBOARD -->
    <div class="dashboard-container">
        <!-- HEADER -->
        <header class="header">
            <div class="logo">
                <i class="fas fa-chalkboard-teacher"></i>
                <div>
                    <h1>RFID IoT Attendance</h1>
                    <span>Lecturer Portal</span>
                </div>
            </div>
            
            <div class="header-right">
                <div class="status-badge">
                    <i class="fas fa-circle fa-xs"></i>
                    <span>Teaching Mode</span>
                </div>
                
                <div class="lecturer-menu">
                    <button type="button" class="lecturer-profile">
                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($lecturerName) ?>&background=006837&color=fff&size=128" 
                             alt="Lecturer">
                        <div>
                            <div style="font-weight: 600;"><?= htmlspecialchars($lecturerName) ?></div>
                            <div class="profile-time" style="font-size: 0.85rem; color: var(--lecturer-gray);">
                                <i class="far fa-clock"></i> <?= date('H:i:s') ?>
                            </div>
                        </div>
                        <i class="fas fa-chevron-down profile-caret"></i>
                    </button>
                    <div class="lecturer-dropdown">
                        <a href="../lecturer/notifications.php">
                            <i class="fas fa-bell"></i> Notifications
                        </a>
                        <a href="../lecturer/settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <a href="../logout.php" class="danger-link">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </header>

        <!-- SIDEBAR -->
        <nav class="sidebar">
            <div class="sidebar-section">
                <h3>Teaching</h3>
                <ul class="nav-menu">
                    <li class="nav-item active">
                        <i class="fas fa-chart-line"></i>
                        <span>Dashboard</span>
                    </li>
                    <li class="nav-item" onclick="window.location.href='../lecturer/courses.php'">
                        <i class="fas fa-book-open"></i>
                        <span>My Courses</span>
                    </li>
                    <li class="nav-item" onclick="window.location.href='../lecturer/calendar.php'">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>Attendance</h3>
                <ul class="nav-menu">
                   <li class="nav-item" onclick="window.location.href='../lecturer/live_attendance.php'">
    <i class="fas fa-play-circle"></i>
    <span>Live Attendance</span>
</li>

                    <li class="nav-item" onclick="window.location.href='../lecturer/records.php'">
                        <i class="fas fa-history"></i>
                        <span>Session Records</span>
                    </li>
                    <li class="nav-item" onclick="window.location.href='../lecturer/reports.php'">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </li>
                </ul>
            </div>
            
        </nav>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- WELCOME BANNER -->
            <div class="welcome-banner">
                <h2>Welcome, <?= htmlspecialchars($lecturerName) ?>!</h2>
                <p>Here's your teaching overview for today. You have <strong><?= $classes_today ?></strong> classes scheduled.</p>
                
                <div class="banner-stats">
                    <div class="stat-item">
                        <span class="number"><?= $students_today ?></span>
                        <span class="label">Students Today</span>
                    </div>
                    <div class="stat-item">
                        <span class="number"><?= $attendance_rate ?>%</span>
                        <span class="label">Attendance Rate</span>
                    </div>
                    <div class="stat-item">
                        <span class="number"><?= $at_risk ?></span>
                        <span class="label">At Risk</span>
                    </div>
                </div>
            </div>

            <!-- TEACHING STATS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number"><?= $total_courses ?></div>
                            <div class="stat-label">My Courses</div>
                        </div>
                        <div class="stat-icon courses">
                            <i class="fas fa-book"></i>
                        </div>
                    </div>
                    <div class="stat-sub"><?= $classes_today ?> classes today</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number"><?= $classes_today ?></div>
                            <div class="stat-label">Today's Classes</div>
                        </div>
                        <div class="stat-icon classes">
                            <i class="fas fa-calendar-day"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Check your schedule below</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number"><?= $students_today ?></div>
                            <div class="stat-label">Attendance Today</div>
                        </div>
                        <div class="stat-icon students">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-sub"><?= $attendance_rate ?>% attendance rate</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number"><?= $at_risk ?></div>
                            <div class="stat-label">At-Risk Students</div>
                        </div>
                        <div class="stat-icon risk">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-sub">< 80% attendance</div>
                </div>
            </div>

            <!-- QUICK ACTIONS -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                </div>
                <div class="quick-actions">
                    <div class="action-card" onclick="window.location.href='create_session.php'">
                        <div class="action-icon live">
                            <i class="fas fa-play-circle"></i>
                        </div>
                        <h4 class="action-title">Create Session</h4>
                        <p class="action-desc">Set the date and time window for RFID attendance</p>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='records.php'">
                        <div class="action-icon records">
                            <i class="fas fa-history"></i>
                        </div>
                        <h4 class="action-title">View Records</h4>
                        <p class="action-desc">Check attendance history and student records</p>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='reports.php'">
                        <div class="action-icon reports">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <h4 class="action-title">Generate Reports</h4>
                        <p class="action-desc">Create attendance analytics and reports</p>
                    </div>
                    
                    <div class="action-card" onclick="window.location.href='calendar.php'">
                        <div class="action-icon calendar">
                            <i class="fas fa-calendar-week"></i>
                        </div>
                        <h4 class="action-title">Schedule Calendar</h4>
                        <p class="action-desc">View and manage your teaching schedule</p>
                    </div>
                </div>
            </div>

            <!-- RECENT ACTIVITY -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-history"></i> Recent Activity
                    </div>
                    <a href="records.php" style="color: var(--lecturer-primary); text-decoration: none; font-size: 0.9rem;">
                        View All →
                    </a>
                </div>
                <div class="activity-list">
                    <?php if (empty($recent_sessions)): ?>
                        <div class="empty-state">
                            <i class="far fa-calendar-times"></i>
                            <h3>No recent sessions</h3>
                            <p>Create a session to start recording attendance.</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($recent_sessions as $session): ?>
                            <?php
                                $borderColor = $session['session_status'] === 'completed'
                                    ? 'var(--lecturer-success)'
                                    : ($session['session_status'] === 'ongoing' ? 'var(--lecturer-warning)' : 'var(--lecturer-accent)');
                                $presentTotal = (int)$session['total_present'];
                            ?>
                            <div class="class-item" style="background: #f8fafc; border-left: 4px solid <?= $borderColor ?>;">
                                <div class="class-info">
                                    <h4><?= htmlspecialchars($session['course_code'] . ' - ' . $session['course_name']) ?></h4>
                                    <div class="class-details">
                                        <span><i class="far fa-clock"></i> <?= htmlspecialchars(date('d M Y', strtotime($session['session_date']))) ?>, <?= substr($session['planned_start_time'], 0, 5) ?> - <?= substr($session['planned_end_time'], 0, 5) ?></span>
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($session['room_code'] ?: $session['room_name'] ?: 'TBA') ?></span>
                                        <span><i class="fas fa-user-check"></i> <?= $presentTotal ?>/<?= (int)$session['total_expected'] ?> Present</span>
                                    </div>
                                </div>
                                <div class="class-actions">
                                    <a class="btn btn-outline" href="records.php?session_id=<?= (int)$session['session_id'] ?>">
                                        <?= htmlspecialchars(ucfirst($session['session_status'])) ?>
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            // Set active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                });
            });
            
            // Auto update time in header
            function updateTime() {
                const now = new Date();
                const timeString = now.toLocaleTimeString('en-US', { 
                    hour12: false,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit'
                });
                
                const dateElement = document.querySelector('.profile-time');
                if (dateElement) {
                    dateElement.innerHTML = `<i class="far fa-clock"></i> ${timeString}`;
                }
            }
            
            updateTime();
            setInterval(updateTime, 1000);
            
            // Add hover effects to action cards
            const actionCards = document.querySelectorAll('.action-card');
            actionCards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                });
            });
            
            // Add click effects to class items
            const classItems = document.querySelectorAll('.class-item');
            classItems.forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('.btn')) {
                        this.style.backgroundColor = '#f1f5ff';
                        setTimeout(() => {
                            this.style.backgroundColor = '';
                        }, 300);
                    }
                });
            });
            
            // Simulate live attendance updates
            setInterval(() => {
                const attendanceNumber = document.querySelector('.attendance-stat:nth-child(2) .number');
                if (attendanceNumber) {
                    const current = parseInt(attendanceNumber.textContent);
                    attendanceNumber.textContent = current + Math.floor(Math.random() * 3);
                }
            }, 10000);
        });
        
        // Simple toast notification
        function showToast(message, type = 'success') {
            const toast = document.createElement('div');
            toast.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#4f46e5'};
                color: white;
                padding: 1rem 1.5rem;
                border-radius: 8px;
                box-shadow: 0 8px 20px rgba(0,0,0,0.15);
                z-index: 1000;
                animation: slideIn 0.3s ease;
            `;
            
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span style="margin-left: 10px;">${message}</span>
            `;
            
            document.body.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease';
                setTimeout(() => {
                    document.body.removeChild(toast);
                }, 300);
            }, 3000);
        }
        
        // Add CSS for animations
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from { transform: translateX(100%); opacity: 0; }
                to { transform: translateX(0); opacity: 1; }
            }
            
            @keyframes slideOut {
                from { transform: translateX(0); opacity: 1; }
                to { transform: translateX(100%); opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>
