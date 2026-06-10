<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

$courseFilter = $_GET['course_id'] ?? 'all';
$lecturerFilter = $_GET['lecturer_id'] ?? 'all';
$statusFilter = $_GET['status'] ?? 'all';
$dateFrom = $_GET['date_from'] ?? date('Y-01-01');
$dateTo = $_GET['date_to'] ?? date('Y-12-31');

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $dateFrom = date('Y-01-01');
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $dateTo = date('Y-12-31');
}

$courses = $pdo->query("
    SELECT course_id, course_code, course_name
    FROM courses
    WHERE is_active = 1
    ORDER BY course_code
")->fetchAll();

$lecturers = $pdo->query("
    SELECT DISTINCT u.user_id, u.full_name
    FROM users u
    JOIN class_schedule cs ON cs.lecturer_id = u.user_id
    WHERE u.role = 'lecturer'
      AND u.is_active = 1
      AND cs.is_active = 1
    ORDER BY u.full_name
")->fetchAll();

$reportSql = "
    SELECT
        u.user_id,
        u.matric_no,
        u.full_name,
        c.course_id,
        c.course_code,
        c.course_name,
        COUNT(DISTINCT ats.session_id) AS expected_sessions,
        COUNT(DISTINCT CASE WHEN ar.status = 'present' THEN ats.session_id END) AS attended_sessions
    FROM enrollments e
    JOIN users u ON u.user_id = e.student_id
    JOIN courses c ON c.course_id = e.course_id
    LEFT JOIN class_schedule cs
        ON cs.course_id = c.course_id
       AND cs.is_active = 1
    LEFT JOIN attendance_sessions ats
        ON ats.schedule_id = cs.schedule_id
       AND ats.session_date BETWEEN ? AND ?
    LEFT JOIN attendance_records ar
        ON ar.session_id = ats.session_id
       AND ar.student_id = e.student_id
    WHERE e.status = 'registered'
";
$params = [$dateFrom, $dateTo];
if ($courseFilter !== 'all' && ctype_digit((string)$courseFilter)) {
    $reportSql .= " AND c.course_id = ?";
    $params[] = (int)$courseFilter;
}
if ($lecturerFilter !== 'all' && ctype_digit((string)$lecturerFilter)) {
    $reportSql .= " AND cs.lecturer_id = ?";
    $params[] = (int)$lecturerFilter;
}
$reportSql .= "
    GROUP BY u.user_id, u.matric_no, u.full_name, c.course_id, c.course_code, c.course_name
    ORDER BY c.course_code, u.full_name
";
$stmt = $pdo->prepare($reportSql);
$stmt->execute($params);
$reportRowsRaw = $stmt->fetchAll();

$reportRows = [];
foreach ($reportRowsRaw as $row) {
    $expected = (int)$row['expected_sessions'];
    $attended = (int)$row['attended_sessions'];
    $rate = $expected > 0 ? round(($attended / $expected) * 100) : 0;
    $status = $rate >= 80 ? 'Good' : ($rate >= 60 ? 'At Risk' : 'Warning');

    if ($statusFilter !== 'all' && $status !== $statusFilter) {
        continue;
    }

    $reportRows[] = [
        'name' => $row['full_name'],
        'matric' => $row['matric_no'],
        'user_id' => (int)$row['user_id'],
        'course_id' => (int)$row['course_id'],
        'course' => $row['course_code'] . ' - ' . $row['course_name'],
        'course_code' => $row['course_code'],
        'rate' => $rate,
        'status' => $status,
        'expected' => $expected,
        'attended' => $attended
    ];
}

$totalRows = count($reportRows);
$overallRate = $totalRows > 0 ? round(array_sum(array_column($reportRows, 'rate')) / $totalRows) : 0;
$atRiskCount = count(array_filter($reportRows, fn($row) => $row['status'] !== 'Good'));
$highest = $totalRows > 0 ? max(array_column($reportRows, 'rate')) : 0;
$lowest = $totalRows > 0 ? min(array_column($reportRows, 'rate')) : 0;

$chartWhere = "WHERE c.is_active = 1";
$chartParams = [];
if ($courseFilter !== 'all' && ctype_digit((string)$courseFilter)) {
    $chartWhere .= " AND c.course_id = ?";
    $chartParams[] = (int)$courseFilter;
}
if ($lecturerFilter !== 'all' && ctype_digit((string)$lecturerFilter)) {
    $chartWhere .= " AND cs.lecturer_id = ?";
    $chartParams[] = (int)$lecturerFilter;
}

$courseChartStmt = $pdo->prepare("
    SELECT
        c.course_code,
        COALESCE(ROUND((SUM(ats.total_present) / NULLIF(SUM(ats.total_expected), 0)) * 100), 0) AS rate
    FROM courses c
    LEFT JOIN class_schedule cs ON cs.course_id = c.course_id AND cs.is_active = 1
    LEFT JOIN attendance_sessions ats
        ON ats.schedule_id = cs.schedule_id
       AND ats.session_date BETWEEN ? AND ?
    $chartWhere
    GROUP BY c.course_id, c.course_code
    ORDER BY c.course_code
");
$courseChartStmt->execute(array_merge([$dateFrom, $dateTo], $chartParams));
$courseChartRows = $courseChartStmt->fetchAll();

$trendWhere = "WHERE ats.session_date BETWEEN ? AND ?";
$trendParams = [$dateFrom, $dateTo];
if ($courseFilter !== 'all' && ctype_digit((string)$courseFilter)) {
    $trendWhere .= " AND c.course_id = ?";
    $trendParams[] = (int)$courseFilter;
}
if ($lecturerFilter !== 'all' && ctype_digit((string)$lecturerFilter)) {
    $trendWhere .= " AND cs.lecturer_id = ?";
    $trendParams[] = (int)$lecturerFilter;
}

$trendStmt = $pdo->prepare("
    SELECT
        CONCAT('W', WEEK(ats.session_date, 1)) AS week_label,
        COALESCE(ROUND((SUM(ats.total_present) / NULLIF(SUM(ats.total_expected), 0)) * 100), 0) AS rate
    FROM attendance_sessions ats
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    $trendWhere
    GROUP BY YEARWEEK(ats.session_date, 1), week_label
    ORDER BY YEARWEEK(ats.session_date, 1)
");
$trendStmt->execute($trendParams);
$trendRows = $trendStmt->fetchAll();

$statusCounts = [
    'Good' => count(array_filter($reportRows, fn($row) => $row['status'] === 'Good')),
    'At Risk' => count(array_filter($reportRows, fn($row) => $row['status'] === 'At Risk')),
    'Warning' => count(array_filter($reportRows, fn($row) => $row['status'] === 'Warning'))
];

$heatmapWhere = "WHERE ats.session_date BETWEEN ? AND ?";
$heatmapParams = [$dateFrom, $dateTo];
if ($courseFilter !== 'all' && ctype_digit((string)$courseFilter)) {
    $heatmapWhere .= " AND c.course_id = ?";
    $heatmapParams[] = (int)$courseFilter;
}
if ($lecturerFilter !== 'all' && ctype_digit((string)$lecturerFilter)) {
    $heatmapWhere .= " AND cs.lecturer_id = ?";
    $heatmapParams[] = (int)$lecturerFilter;
}

$heatmapStmt = $pdo->prepare("
    SELECT
        DAYNAME(ats.session_date) AS day_name,
        COALESCE(ROUND((SUM(ats.total_present) / NULLIF(SUM(ats.total_expected), 0)) * 100), 0) AS rate
    FROM attendance_sessions ats
    JOIN class_schedule cs ON cs.schedule_id = ats.schedule_id
    JOIN courses c ON c.course_id = cs.course_id
    $heatmapWhere
    GROUP BY DAYOFWEEK(ats.session_date), DAYNAME(ats.session_date)
    ORDER BY DAYOFWEEK(ats.session_date)
");
$heatmapStmt->execute($heatmapParams);
$heatmapRows = $heatmapStmt->fetchAll();

function statusClass($status) {
    if ($status === 'Good') return 'status-good';
    if ($status === 'At Risk') return 'status-risk';
    return 'status-warning';
}

function heatmapColor($rate) {
    if ($rate >= 85) return '#2ecc71';
    if ($rate >= 70) return '#f1c40f';
    return '#e74c3c';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID IoT Attendance | Reports & Analytics</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .btn-warning {
            background: linear-gradient(135deg, var(--warning), #e67e22);
            color: white;
        }

        .btn-info {
            background: linear-gradient(135deg, var(--info), #2980b9);
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

        /* FILTER BAR */
        .filter-bar {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            margin-bottom: 1rem;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: minmax(190px, 0.9fr) minmax(230px, 1.15fr) minmax(330px, 1.35fr) minmax(180px, 0.8fr);
            gap: 1.25rem;
            margin-bottom: 1.5rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            min-width: 0;
        }

        .filter-label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-select {
            width: 100%;
            min-width: 0;
            padding: 12px 16px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 1rem;
            transition: var(--transition);
            background: white;
            cursor: pointer;
        }

        .filter-select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(67, 97, 238, 0.1);
        }

        .date-inputs {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            min-width: 0;
        }

        .date-inputs .filter-select {
            flex: 1 1 0;
            min-width: 0;
        }

        .date-inputs span {
            color: var(--gray);
            flex: 0 0 auto;
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
        }

        #statusFilter {
            max-width: 190px;
        }

        /* STATS CARDS */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
            transition: var(--transition);
            border-left: 4px solid var(--primary);
            display: flex;
            align-items: center;
            gap: 1.5rem;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
        }

        .icon-attendance { 
            background: linear-gradient(135deg, #2ecc71, #27ae60); 
        }
        .icon-risk { 
            background: linear-gradient(135deg, #f39c12, #e67e22); 
        }
        .icon-high { 
            background: linear-gradient(135deg, #4361ee, #3a56d4); 
        }
        .icon-low { 
            background: linear-gradient(135deg, #e74c3c, #c0392b); 
        }

        .stat-content h3 {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--dark);
            line-height: 1;
            margin-bottom: 4px;
        }

        .stat-content p {
            color: var(--gray);
            font-size: 0.95rem;
        }

        /* CHARTS SECTION */
        .charts-section {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .chart-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
        }

        .chart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .chart-title {
            font-size: 1.3rem;
            font-weight: 600;
            color: var(--dark);
        }

        .chart-container {
            position: relative;
            height: 280px;
            width: 100%;
        }

        /* HEATMAP CARD */
        .heatmap-card {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.8rem;
            box-shadow: var(--shadow);
        }

        .heatmap-grid {
            display: grid;
            grid-template-columns: repeat(7, 1fr);
            gap: 8px;
            margin: 2rem 0;
            justify-items: center;
        }

        .heatmap-cell {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: white;
        }

        .heatmap-legend {
            display: flex;
            justify-content: center;
            gap: 2rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .legend-color {
            width: 20px;
            height: 20px;
            border-radius: 4px;
        }

        /* TABLE SECTION */
        .table-section {
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
            overflow: hidden;
        }

        .table-header {
            padding: 1.5rem 2rem;
            border-bottom: 1px solid #eee;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .table-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--dark);
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

        /* STATUS BADGES */
        .status-badge-table {
            padding: 8px 16px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-block;
        }

        .status-good {
            background: rgba(46, 204, 113, 0.15);
            color: var(--success);
        }

        .status-risk {
            background: rgba(243, 156, 18, 0.15);
            color: var(--warning);
        }

        .status-warning {
            background: rgba(231, 76, 60, 0.15);
            color: var(--danger);
        }

        .attendance-percent {
            font-weight: 700;
            font-size: 1.1rem;
        }

        /* ACTION BUTTONS */
        .action-buttons-cell {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
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

        .btn-edit { 
            background: linear-gradient(135deg, var(--success), #27ae60); 
            color: white; 
        }
        .btn-view { 
            background: linear-gradient(135deg, var(--info), #2980b9); 
            color: white; 
        }
        .btn-notify { 
            background: linear-gradient(135deg, var(--warning), #e67e22); 
            color: white; 
        }

        .btn-action:hover {
            opacity: 0.9;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            text-decoration: none;
            color: inherit;
        }

        /* FOOTER */
        .footer {
            text-align: center;
            padding: 1.5rem;
            color: var(--gray);
            font-size: 0.95rem;
            border-top: 1px solid #eee;
            margin-top: 2rem;
            background: white;
            border-radius: var(--border-radius);
            box-shadow: var(--shadow);
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
            max-width: 500px;
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

        /* EXPORT OPTIONS */
        .export-options {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .export-option {
            padding: 1rem 1.5rem;
            border: 2px solid #eee;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 1rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .export-option:hover {
            border-color: var(--primary);
            background: #f8f9fa;
        }

        .export-icon {
            width: 48px;
            height: 48px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            color: white;
        }

        .icon-csv { background: linear-gradient(135deg, #27ae60, #2ecc71); }
        .icon-pdf { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        .icon-excel { background: linear-gradient(135deg, #2ecc71, #27ae60); }

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

        /* RESPONSIVE */
        @media (max-width: 1200px) {
            .charts-section {
                grid-template-columns: 1fr;
            }

            .filter-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .filter-group:nth-child(3) {
                grid-column: auto;
            }

            #statusFilter {
                max-width: none;
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
            
            .filter-grid {
                grid-template-columns: 1fr;
            }

            .filter-group:nth-child(3) {
                grid-column: auto;
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
            
            .table-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
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
            
            .action-buttons-cell {
                flex-direction: column;
            }
            
            .heatmap-grid {
                grid-template-columns: repeat(4, 1fr);
            }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/app-polish.css">
</head>
<body>
    <div class="dashboard-container">
        <!-- HEADER -->
        <div class="header">
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
                        <img src="https://ui-avatars.com/api/?name=Admin+User&background=4361ee&color=fff" alt="Admin">
                        <div style="font-weight: 600;">Admin</div>
                        <i class="fas fa-chevron-down profile-caret"></i>
                    </button>
                    <div class="admin-dropdown">
                        <a href="../admin/settings.php"><i class="fas fa-cog"></i> Settings</a>
                        <a href="../logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
            </div>
        </div>

        <!-- SIDEBAR -->
        <div class="sidebar">
            <div class="sidebar-section">
                <h3>Dashboard</h3>
                <ul class="nav-menu">
                    <li><a href="dashboard.php" class="nav-item"><i class="fas fa-chart-line"></i> Admin Dashboard</a></li>
                    <li><a href="users.php" class="nav-item"><i class="fas fa-users"></i> Users & RFID</a></li>
                    <li><a href="courses.php" class="nav-item"><i class="fas fa-calendar-alt"></i> Courses & Timetable</a></li>
                </ul>
            </div>

            <div class="sidebar-section">
                <h3>Reports</h3>
                <ul class="nav-menu">
                    <li><a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                </ul>
            </div>
        </div>

        <!-- MAIN CONTENT -->
        <div class="main-content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Reports & Analytics</h1>
                    <p>View detailed attendance reports, analytics, and export data</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-outline" onclick="showPrintOptions()">
                        <i class="fas fa-print"></i> Print
                    </button>
                    <button class="btn btn-primary" onclick="showExportModal()">
                        <i class="fas fa-download"></i> Export
                    </button>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="filter-bar">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Lecturer</label>
                        <select class="filter-select" id="lecturerFilter">
                            <option value="all">All Lecturers</option>
                            <?php foreach ($lecturers as $lecturer): ?>
                                <option value="<?= htmlspecialchars($lecturer['user_id']) ?>" <?= (string)$lecturerFilter === (string)$lecturer['user_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($lecturer['full_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Course</label>
                        <select class="filter-select" id="courseFilter">
                            <option value="all">All Courses</option>
                            <?php foreach ($courses as $course): ?>
                                <option value="<?= htmlspecialchars($course['course_id']) ?>" <?= (string)$courseFilter === (string)$course['course_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <div class="date-inputs">
                            <input type="date" class="filter-select" id="dateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
                            <span>to</span>
                            <input type="date" class="filter-select" id="dateTo" value="<?= htmlspecialchars($dateTo) ?>">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter">
                            <option value="all">All Status</option>
                            <option>Good (≥80%)</option>
                            <option value="At Risk">At Risk (60-79%)</option>
                            <option value="Warning">Warning (&lt;60%)</option>
                        </select>
                    </div>
                </div>
                <div class="filter-actions">
                    <button class="btn btn-outline" onclick="clearFilters()">
                        <i class="fas fa-times"></i> Clear Filters
                    </button>
                    <button class="btn btn-primary" onclick="applyFilters()">
                        <i class="fas fa-filter"></i> Apply Filters
                    </button>
                </div>
            </div>

            <!-- STATS CARDS -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon icon-attendance">
                        <i class="fas fa-clipboard-check"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $overallRate ?>%</h3>
                        <p>Total Attendance Rate</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-risk">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $atRiskCount ?></h3>
                        <p>Students At Risk</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-high">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $highest ?>%</h3>
                        <p>Highest Student Rate</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-low">
                        <i class="fas fa-chart-line-down"></i>
                    </div>
                    <div class="stat-content">
                        <h3><?= $lowest ?>%</h3>
                        <p>Lowest Student Rate</p>
                    </div>
                </div>
            </div>

            <!-- CHARTS SECTION -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Attendance by Course</div>
                        <span style="color: var(--gray); font-size: 0.9rem;">Based on selected filters</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Weekly Attendance Trend</div>
                        <span style="color: var(--gray); font-size: 0.9rem;">By selected lecturer/course</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Student Status Distribution</div>
                        <span style="color: var(--gray); font-size: 0.9rem;">Current table results</span>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="heatmap-card">
                    <div class="chart-header">
                        <div class="chart-title">Attendance Heatmap</div>
                        <button class="btn btn-outline" style="padding: 6px 12px;">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="heatmap-grid">
                        <?php if (empty($heatmapRows)): ?>
                            <div style="grid-column: 1 / -1; color: var(--gray); text-align: center; padding: 1rem;">No sessions this week</div>
                        <?php else: ?>
                            <?php foreach ($heatmapRows as $heatmap): ?>
                                <div class="heatmap-cell" style="background: <?= heatmapColor((int)$heatmap['rate']) ?>;" title="<?= htmlspecialchars($heatmap['day_name']) ?>">
                                    <?= (int)$heatmap['rate'] ?>%
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                    <div class="heatmap-legend">
                        <div class="legend-item">
                            <div class="legend-color" style="background: #2ecc71;"></div>
                            <span>Excellent (≥85%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #f1c40f;"></div>
                            <span>Good (70-84%)</span>
                        </div>
                        <div class="legend-item">
                            <div class="legend-color" style="background: #e74c3c;"></div>
                            <span>Needs Attention (<70%)</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TABLE SECTION -->
            <div class="table-section">
                <div class="table-header">
                    <div class="table-title">Attendance Details</div>
                    <div class="action-buttons">
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Student Name</th>
                                <th>Course</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($reportRows)): ?>
                                <tr>
                                    <td colspan="5" style="text-align: center; color: var(--gray); padding: 2rem;">
                                        No enrollment or attendance data found.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($reportRows as $row): ?>
                                    <tr>
                                        <td>
                                            <?= htmlspecialchars($row['name']) ?><br>
                                            <span style="color: var(--gray); font-size: 0.85rem;"><?= htmlspecialchars($row['matric']) ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['course']) ?></td>
                                        <td class="attendance-percent"><?= $row['rate'] ?>%</td>
                                        <td><span class="status-badge-table <?= statusClass($row['status']) ?>"><?= htmlspecialchars($row['status']) ?></span></td>
                                        <td>
                                            <div class="action-buttons-cell">
                                                <button class="btn-action btn-view" onclick="viewDetails(<?= (int)$row['user_id'] ?>, <?= (int)$row['course_id'] ?>)">
                                                    <i class="fas fa-eye"></i> View
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="footer">
                <p>RFID IoT Attendance System &copy; 2026 | Nur Alya Nadhirah binti Naaidith | DI230078</p>
                <p>Last Updated: <span id="currentDateTime"></span></p>
            </div>
        </div>
    </div>

    <!-- EXPORT MODAL -->
    <div class="modal" id="exportModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Export Report</div>
                <button class="modal-close" onclick="closeExportModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Format</label>
                    <select class="form-control" id="reportExportFormat">
                        <option value="pdf">PDF Print View</option>
                        <option value="csv">CSV / Excel</option>
                    </select>
                    <div style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                        PDF Print View opens a neat report page. Use Print then Save as PDF.
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Date Range for Export</label>
                    <div class="date-inputs">
                        <input type="date" class="form-control" id="exportDateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
                        <span>to</span>
                        <input type="date" class="form-control" id="exportDateTo" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeExportModal()">Cancel</button>
                <button class="btn btn-primary" onclick="startExport()">Start Export</button>
            </div>
        </div>
    </div>

    <script>
        const reportRows = <?= json_encode($reportRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const courseChartRows = <?= json_encode($courseChartRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const trendRows = <?= json_encode($trendRows, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const statusCounts = <?= json_encode($statusCounts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>;
        const selectedStatusFilter = <?= json_encode($statusFilter) ?>;

        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Update current date and time
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute
            if (selectedStatusFilter !== 'all') {
                const statusFilter = document.getElementById('statusFilter');
                [...statusFilter.options].forEach(option => {
                    if (option.value === selectedStatusFilter || option.textContent.includes(selectedStatusFilter)) {
                        option.selected = true;
                    }
                });
            }

            // Course Attendance Bar Chart
            const courseCtx = document.getElementById('courseChart').getContext('2d');
            new Chart(courseCtx, {
                type: 'bar',
                data: {
                    labels: courseChartRows.map(row => row.course_code),
                    datasets: [{
                        label: 'Attendance %',
                        data: courseChartRows.map(row => Number(row.rate || 0)),
                        backgroundColor: '#4361ee',
                        borderWidth: 0,
                        borderRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { drawBorder: false },
                            ticks: {
                                callback: function(value) { return value + '%'; }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Trend Line Chart
            const trendCtx = document.getElementById('trendChart').getContext('2d');
            new Chart(trendCtx, {
                type: 'line',
                data: {
                    labels: trendRows.map(row => row.week_label),
                    datasets: [{
                        label: 'Attendance %',
                        data: trendRows.map(row => Number(row.rate || 0)),
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            max: 100,
                            grid: { drawBorder: false },
                            ticks: {
                                callback: function(value) { return value + '%'; }
                            }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });

            // Status Pie Chart
            const statusCtx = document.getElementById('statusChart').getContext('2d');
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Good', 'At Risk', 'Warning'],
                    datasets: [{
                        data: [statusCounts.Good || 0, statusCounts['At Risk'] || 0, statusCounts.Warning || 0],
                        backgroundColor: ['#2ecc71', '#f39c12', '#e74c3c'],
                        borderWidth: 0,
                        hoverOffset: 15
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { position: 'right' } },
                    cutout: '70%'
                }
            });
        });

        // Update date and time
        function updateDateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            };
            document.getElementById('currentDateTime').textContent = now.toLocaleDateString('en-US', options);
        }

        // Modal Functions
        function showExportModal() {
            document.getElementById('exportModal').style.display = 'flex';
        }

        function closeExportModal() {
            document.getElementById('exportModal').style.display = 'none';
        }

        function startExport() {
            const params = new URLSearchParams();
            params.set('type', 'attendance');
            params.set('format', document.getElementById('reportExportFormat').value || 'pdf');
            params.set('from', document.getElementById('exportDateFrom').value || document.getElementById('dateFrom').value || '');
            params.set('to', document.getElementById('exportDateTo').value || document.getElementById('dateTo').value || '');
            closeExportModal();
            window.location.href = `export_dashboard_report.php?${params.toString()}`;
        }

        // Filter functions
        function applyFilters() {
            const params = new URLSearchParams();
            params.set('lecturer_id', document.getElementById('lecturerFilter').value || 'all');
            params.set('course_id', document.getElementById('courseFilter').value || 'all');
            params.set('date_from', document.getElementById('dateFrom').value || '');
            params.set('date_to', document.getElementById('dateTo').value || '');
            const statusText = document.getElementById('statusFilter').value;
            if (statusText.includes('Good')) {
                params.set('status', 'Good');
            } else if (statusText.includes('At Risk')) {
                params.set('status', 'At Risk');
            } else if (statusText.includes('Warning')) {
                params.set('status', 'Warning');
            } else {
                params.set('status', 'all');
            }
            window.location.href = `reports.php?${params.toString()}`;
        }

        function clearFilters() {
            document.getElementById('lecturerFilter').value = 'all';
            document.getElementById('courseFilter').value = 'all';
            document.getElementById('statusFilter').value = 'all';
            window.location.href = 'reports.php';
        }

        // Record functions
        function editRecord(name) {
            showToast(`Editing record for ${name}`, 'info');
        }

        function viewDetails(studentId, courseId) {
            const params = new URLSearchParams();
            params.set('student_id', studentId);
            params.set('course_id', courseId);
            params.set('date_from', document.getElementById('dateFrom').value || '');
            params.set('date_to', document.getElementById('dateTo').value || '');
            window.location.href = `report_student_detail.php?${params.toString()}`;
        }

        function showPrintOptions() {
            showToast('Opening print dialog...', 'info');
            setTimeout(() => window.print(), 500);
        }

        // Toast notification
        function showToast(message, type = 'info') {
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            toast.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
                <span>${message}</span>
                <button class="toast-close" onclick="this.parentElement.remove()">&times;</button>
            `;
            document.body.appendChild(toast);
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>
