<?php
require_once '../includes/auth_check.php';
requireLecturer();

require_once __DIR__ . '/../includes/config.php';

$lecturer_id = $_SESSION['user_id'];
$today = date('l'); // Monday, Tuesday, ...

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
      AND DATE(ar.scan_time) = CURDATE()
");
$stmt->execute([$lecturer_id]);
$students_today = $stmt->fetchColumn();

// At-risk students (< 80%)
$at_risk = 0; // Placeholder – computed in reports module

/* =========================
   TODAY'S CLASSES LIST
   ========================= */
$stmt = $pdo->prepare("
    SELECT 
        cs.schedule_id,
        cs.start_time,
        cs.end_time,
        r.room_name,
        c.course_code,
        c.course_name
    FROM class_schedule cs
    JOIN courses c ON cs.course_id = c.course_id
    LEFT JOIN rooms r ON cs.room_id = r.room_id
    WHERE cs.lecturer_id = ?
      AND cs.day_of_week = ?
      AND cs.is_active = 1
      AND (
            cs.repeat_weekly = 1
            OR (
                cs.start_date <= CURDATE()
                AND (cs.end_date IS NULL OR cs.end_date >= CURDATE())
            )
          )
    ORDER BY cs.start_time
");
$stmt->execute([$lecturer_id, $today]);
$today_classes = $stmt->fetchAll();
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
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 8px 16px;
            border-radius: var(--border-radius);
            background: var(--lecturer-light);
            cursor: pointer;
            transition: var(--transition);
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

        /* QUICK ATTENDANCE */
        .quick-attendance {
            background: linear-gradient(135deg, var(--lecturer-accent), #0891b2);
            color: white;
            margin-top: 1rem;
        }

        .attendance-summary {
            text-align: center;
        }

        .attendance-stats {
            display: flex;
            justify-content: space-around;
            margin: 1.5rem 0;
        }

        .attendance-stat {
            text-align: center;
        }

        .attendance-stat .number {
            font-size: 2rem;
            font-weight: 700;
            display: block;
        }

        .attendance-stat .label {
            font-size: 0.9rem;
            opacity: 0.8;
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
                
                <div class="lecturer-profile">
                    <img src="https://ui-avatars.com/api/?name=<?= urlencode($_SESSION['name'] ?? 'Lecturer') ?>&background=4f46e5&color=fff&size=128" 
                         alt="Lecturer">
                    <div>
                        <div style="font-weight: 600;"><?= htmlspecialchars($_SESSION['name'] ?? 'Lecturer') ?></div>
                        <div style="font-size: 0.85rem; color: var(--lecturer-gray);">
                            <i class="far fa-calendar-alt"></i> <?= date('F j, Y') ?>
                        </div>
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
                    <li class="nav-item">
                        <i class="fas fa-book-open"></i>
                        <span>My Courses</span>
                        <span style="margin-left: auto; background: var(--lecturer-accent); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                            <?= $total_courses ?>
                        </span>
                    </li>
                    <li class="nav-item" onclick="window.location.href='../lecturer/calendar.php'">
                        <i class="fas fa-calendar-alt"></i>
                        <span>Schedule</span>
                        <span style="margin-left: auto; background: var(--lecturer-accent); color: white; padding: 2px 8px; border-radius: 10px; font-size: 0.8rem;">
                            <?= $classes_today ?>
                        </span>
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
                    <li class="nav-item">
                        <i class="fas fa-chart-bar"></i>
                        <span>Analytics</span>
                    </li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>System</h3>
                <ul class="nav-menu">
                    <li class="nav-item">
                        <i class="fas fa-cog"></i>
                        <span>Settings</span>
                    </li>
                    <li class="nav-item">
                        <i class="fas fa-bell"></i>
                        <span>Notifications</span>
                    </li>
                    <li class="nav-item">
                        <i class="fas fa-question-circle"></i>
                        <span>Help</span>
                    </li>
                </ul>
            </div>
            
            <a href="../logout.php" class="logout-btn">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- WELCOME BANNER -->
            <div class="welcome-banner">
                <h2>Welcome, <?= htmlspecialchars($_SESSION['name'] ?? 'Professor') ?>!</h2>
                <p>Here's your teaching overview for today. You have <strong><?= $classes_today ?></strong> classes scheduled.</p>
                
                <div class="banner-stats">
                    <div class="stat-item">
                        <span class="number"><?= $students_today ?></span>
                        <span class="label">Students Today</span>
                    </div>
                    <div class="stat-item">
                        <span class="number"><?= $attendance_rate ?? '85' ?>%</span>
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
                    <div class="stat-sub"><?= $attendance_rate ?? '85' ?>% attendance rate</div>
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

            <!-- TODAY'S CLASSES -->
            <div class="card">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-clock"></i> Today's Classes
                    </div>
                    <div style="color: var(--lecturer-gray);">
                        <?= date('l, F j, Y') ?>
                    </div>
                </div>
                
                <?php if (empty($today_classes)): ?>
                    <div class="empty-state">
                        <i class="far fa-calendar-times"></i>
                        <h3>No classes scheduled for today</h3>
                        <p>Enjoy your day off or prepare for upcoming classes.</p>
                    </div>
                <?php else: ?>
                    <div class="classes-grid">
                        <?php foreach ($today_classes as $c): 
                            $current_time = date('H:i:s');
                            $class_status = '';
                            if ($current_time >= $c['start_time'] && $current_time <= $c['end_time']) {
                                $class_status = 'Ongoing';
                                $status_color = 'var(--lecturer-success)';
                            } elseif ($current_time < $c['start_time']) {
                                $class_status = 'Upcoming';
                                $status_color = 'var(--lecturer-warning)';
                            } else {
                                $class_status = 'Completed';
                                $status_color = 'var(--lecturer-gray)';
                            }
                        ?>
                            <div class="class-item">
                                <div class="class-time">
                                    <span class="start"><?= substr($c['start_time'], 0, 5) ?></span>
                                    <span class="duration">2h</span>
                                </div>
                                <div class="class-info">
                                    <h4>
                                        <?= htmlspecialchars($c['course_code']) ?> - 
                                        <?= htmlspecialchars($c['course_name']) ?>
                                    </h4>
                                    <div class="class-details">
                                        <span><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($c['room_name'] ?? 'TBA') ?></span>
                                        <span><i class="fas fa-user-graduate"></i> 45 Students</span>
                                        <span style="color: <?= $status_color ?>; font-weight: 600;">
                                            <i class="fas fa-circle fa-xs"></i> <?= $class_status ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="class-actions">
                                    <?php if ($class_status === 'Ongoing' || $class_status === 'Upcoming'): ?>
                                        <a class="btn btn-primary" 
                                           href="create_session.php?schedule_id=<?= $c['schedule_id'] ?>">
                                            <i class="fas fa-play-circle"></i> Create Session
                                        </a>
                                    <?php else: ?>
                                        <a class="btn btn-outline" 
                                           href="attendance_report.php?schedule_id=<?= $c['schedule_id'] ?>">
                                            <i class="fas fa-chart-bar"></i> View Report
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- QUICK ATTENDANCE -->
            <div class="card quick-attendance">
                <div class="card-header">
                    <div class="card-title">
                        <i class="fas fa-user-check"></i> Quick Attendance Status
                    </div>
                    <div style="opacity: 0.8;">
                        Live Updates
                    </div>
                </div>
                <div class="attendance-summary">
                    <div class="attendance-stats">
                        <div class="attendance-stat">
                            <span class="number"><?= $attendance_rate ?? '85' ?>%</span>
                            <span class="label">Today's Rate</span>
                        </div>
                        <div class="attendance-stat">
                            <span class="number"><?= $students_today ?></span>
                            <span class="label">Present Today</span>
                        </div>
                        <div class="attendance-stat">
                            <span class="number"><?= $total_enrolled ?? '200' ?></span>
                            <span class="label">Total Enrolled</span>
                        </div>
                    </div>
                    <a class="btn btn-success" href="attendance_report.php" style="margin-top: 1rem;">
                        <i class="fas fa-chart-bar"></i> View Detailed Report
                    </a>
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
                    <a href="activity.php" style="color: var(--lecturer-primary); text-decoration: none; font-size: 0.9rem;">
                        View All →
                    </a>
                </div>
                <div class="activity-list">
                    <div class="class-item" style="background: #f8fafc; border-left: 4px solid var(--lecturer-success);">
                        <div class="class-info">
                            <h4>CS101 - Programming Fundamentals</h4>
                            <div class="class-details">
                                <span><i class="far fa-clock"></i> 08:30 - 10:30</span>
                                <span><i class="fas fa-map-marker-alt"></i> LAB 301</span>
                                <span><i class="fas fa-user-check"></i> 42/45 Present</span>
                            </div>
                        </div>
                        <div class="class-actions">
                            <span style="color: var(--lecturer-success); font-weight: 600;">Completed</span>
                        </div>
                    </div>
                    
                    <div class="class-item" style="background: #fef3c7; border-left: 4px solid var(--lecturer-warning);">
                        <div class="class-info">
                            <h4>IT203 - Database Systems</h4>
                            <div class="class-details">
                                <span><i class="far fa-clock"></i> 14:00 - 16:00</span>
                                <span><i class="fas fa-map-marker-alt"></i> Lecture Hall 2</span>
                                <span><i class="fas fa-exclamation-triangle"></i> 3 at-risk students</span>
                            </div>
                        </div>
                        <div class="class-actions">
                            <span style="color: var(--lecturer-warning); font-weight: 600;">Ongoing</span>
                        </div>
                    </div>
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
                
                const dateElement = document.querySelector('.lecturer-profile div div:nth-child(2)');
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
