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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: var(--dark);
        }

        .filter-select {
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
        }

        .date-inputs span {
            color: var(--gray);
        }

        .filter-actions {
            display: flex;
            justify-content: flex-end;
            gap: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #eee;
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
            
            .filter-grid {
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
</head>
<body>
    <div class="dashboard-container">
        <!-- HEADER -->
        <div class="header">
            <a href="#" class="logo">
                <i class="fas fa-id-card"></i>
                <div>
                    <h1>RFID IoT Attendance</h1>
                    <span>Admin Console</span>
                </div>
            </a>
            <div class="header-right">
                <div class="status-badge">
                    <i class="fas fa-circle"></i>
                    <span>System Online</span>
                </div>
                <a href="#" class="admin-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=4361ee&color=fff" alt="Admin">
                    <span>Admin User</span>
                </a>
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
                    <li><a href="reports.php" class="nav-item active"><i class="fas fa-chart-bar"></i> Reports & Analytics</a></li>
                </ul>
            </div>
            
            <div class="sidebar-section">
                <h3>System</h3>
                <ul class="nav-menu">
                    <li><a href="#" class="nav-item"><i class="fas fa-cog"></i> Settings</a></li>
                    <li><a href="#" class="nav-item"><i class="fas fa-bell"></i> Notifications</a></li>
                    <li><a href="#" class="nav-item"><i class="fas fa-shield-alt"></i> Security</a></li>
                </ul>
            </div>
            
            <button class="logout-btn" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                Logout
            </button>
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
                    <button class="btn btn-info" onclick="showNotifyModal()">
                        <i class="fas fa-bell"></i> Notify At Risk
                    </button>
                </div>
            </div>

            <!-- FILTER BAR -->
            <div class="filter-bar">
                <div class="filter-grid">
                    <div class="filter-group">
                        <label class="filter-label">Semester</label>
                        <select class="filter-select" id="semesterFilter">
                            <option>Current Semester</option>
                            <option>Previous Semester</option>
                            <option>All Semesters</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Course</label>
                        <select class="filter-select" id="courseFilter">
                            <option>All Courses</option>
                            <option>BIC20403 - Software Engineering</option>
                            <option>BIT10102 - Introduction to Programming</option>
                            <option>BIT31405 - Database Systems</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Date Range</label>
                        <div class="date-inputs">
                            <input type="date" class="filter-select">
                            <span>to</span>
                            <input type="date" class="filter-select">
                        </div>
                    </div>
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter">
                            <option>All Status</option>
                            <option>Good (≥80%)</option>
                            <option>At Risk (60-79%)</option>
                            <option>Warning (<60%)</option>
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
                        <h3>85%</h3>
                        <p>Total Attendance Rate</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-risk">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stat-content">
                        <h3>4</h3>
                        <p>Students At Risk</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-high">
                        <i class="fas fa-trophy"></i>
                    </div>
                    <div class="stat-content">
                        <h3>92%</h3>
                        <p>Highest Course (BIC20403)</p>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon icon-low">
                        <i class="fas fa-chart-line-down"></i>
                    </div>
                    <div class="stat-content">
                        <h3>55%</h3>
                        <p>Lowest Course (BIT10102)</p>
                    </div>
                </div>
            </div>

            <!-- CHARTS SECTION -->
            <div class="charts-section">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Attendance by Course</div>
                        <select class="filter-select" style="width: auto;">
                            <option>This Semester</option>
                            <option>Last Semester</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="courseChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Weekly Attendance Trend</div>
                        <select class="filter-select" style="width: auto;">
                            <option>All Courses</option>
                            <option>BIC20403</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="trendChart"></canvas>
                    </div>
                </div>
                
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-title">Student Status Distribution</div>
                        <select class="filter-select" style="width: auto;">
                            <option>All Courses</option>
                            <option>By Course</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                </div>
                
                <div class="heatmap-card">
                    <div class="chart-header">
                        <div class="chart-title">Attendance Heatmap (This Week)</div>
                        <button class="btn btn-outline" style="padding: 6px 12px;">
                            <i class="fas fa-expand"></i>
                        </button>
                    </div>
                    <div class="heatmap-grid">
                        <div class="heatmap-cell" style="background: #2ecc71;">92%</div>
                        <div class="heatmap-cell" style="background: #27ae60;">88%</div>
                        <div class="heatmap-cell" style="background: #f1c40f;">78%</div>
                        <div class="heatmap-cell" style="background: #f1c40f;">75%</div>
                        <div class="heatmap-cell" style="background: #f39c12;">68%</div>
                        <div class="heatmap-cell" style="background: #e67e22;">62%</div>
                        <div class="heatmap-cell" style="background: #e74c3c;">55%</div>
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
                        <button class="btn btn-success">
                            <i class="fas fa-plus"></i> Add Schedule
                        </button>
                    </div>
                </div>
                <div style="overflow-x: auto;">
                    <table>
                        <thead>
                            <tr>
                                <th>Student/Lecturer Name</th>
                                <th>Course</th>
                                <th>Attendance %</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Nur Alya Nadhirah</td>
                                <td>BIC20403 - Software Engineering</td>
                                <td class="attendance-percent">92%</td>
                                <td><span class="status-badge-table status-good">Good</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Nur Alya Nadhirah')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-view" onclick="viewDetails('Nur Alya Nadhirah')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Adam</td>
                                <td>BIC20403 - Software Engineering</td>
                                <td class="attendance-percent">80%</td>
                                <td><span class="status-badge-table status-risk">At Risk</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Adam')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-notify" onclick="notifyStudent('Adam')">
                                            <i class="fas fa-bell"></i> Notify
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Aina</td>
                                <td>BIC20403 - Software Engineering</td>
                                <td class="attendance-percent">60%</td>
                                <td><span class="status-badge-table status-risk">At Risk</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Aina')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-notify" onclick="notifyStudent('Aina')">
                                            <i class="fas fa-bell"></i> Notify
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Syafiq</td>
                                <td>BIC20403 - Software Engineering</td>
                                <td class="attendance-percent">60%</td>
                                <td><span class="status-badge-table status-risk">At Risk</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Syafiq')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-notify" onclick="notifyStudent('Syafiq')">
                                            <i class="fas fa-bell"></i> Notify
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Farah</td>
                                <td>BIT10102 - Introduction to Programming</td>
                                <td class="attendance-percent">55%</td>
                                <td><span class="status-badge-table status-warning">Warning</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Farah')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-notify" onclick="notifyStudent('Farah')">
                                            <i class="fas fa-bell"></i> Notify
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Dr. Nurul Aswa</td>
                                <td>BIC20403 - Software Engineering</td>
                                <td class="attendance-percent">97%</td>
                                <td><span class="status-badge-table status-good">Good</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Dr. Nurul Aswa')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-view" onclick="viewDetails('Dr. Nurul Aswa')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>Mr. Adam</td>
                                <td>BIT31405 - Database Systems</td>
                                <td class="attendance-percent">78%</td>
                                <td><span class="status-badge-table status-good">Good</span></td>
                                <td>
                                    <div class="action-buttons-cell">
                                        <button class="btn-action btn-edit" onclick="editRecord('Mr. Adam')">
                                            <i class="fas fa-edit"></i> Edit
                                        </button>
                                        <button class="btn-action btn-view" onclick="viewDetails('Mr. Adam')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- FOOTER -->
            <div class="footer">
                <p>RFID IoT Attendance System &copy; 2024 | Current Semester: Semester 3, 2024 | Week 12 of 14</p>
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
                <div class="export-options">
                    <div class="export-option" onclick="exportAs('CSV')">
                        <div class="export-icon icon-csv">
                            <i class="fas fa-file-csv"></i>
                        </div>
                        <div>
                            <h4>Export as CSV</h4>
                            <p>Comma-separated values for spreadsheet software</p>
                        </div>
                    </div>
                    <div class="export-option" onclick="exportAs('PDF')">
                        <div class="export-icon icon-pdf">
                            <i class="fas fa-file-pdf"></i>
                        </div>
                        <div>
                            <h4>Export as PDF</h4>
                            <p>Portable Document Format for printing and sharing</p>
                        </div>
                    </div>
                    <div class="export-option" onclick="exportAs('Excel')">
                        <div class="export-icon icon-excel">
                            <i class="fas fa-file-excel"></i>
                        </div>
                        <div>
                            <h4>Export as Excel</h4>
                            <p>Microsoft Excel format with formatting</p>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Date Range for Export</label>
                    <div class="date-inputs">
                        <input type="date" class="form-control">
                        <span>to</span>
                        <input type="date" class="form-control">
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeExportModal()">Cancel</button>
                <button class="btn btn-primary" onclick="startExport()">Start Export</button>
            </div>
        </div>
    </div>

    <!-- NOTIFY MODAL -->
    <div class="modal" id="notifyModal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Notify At Risk Students</div>
                <button class="modal-close" onclick="closeNotifyModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Message Template</label>
                    <select class="form-control">
                        <option>Low Attendance Warning</option>
                        <option>Attendance Improvement Required</option>
                        <option>Meeting Request</option>
                        <option>Custom Message</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Custom Message</label>
                    <textarea class="form-control" rows="4" placeholder="Enter your custom notification message here..."></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Send Via</label>
                    <div style="display: flex; gap: 1rem; margin-top: 0.5rem;">
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" checked> Email
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox"> SMS
                        </label>
                        <label style="display: flex; align-items: center; gap: 0.5rem;">
                            <input type="checkbox" checked> System Notification
                        </label>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button class="btn btn-outline" onclick="closeNotifyModal()">Cancel</button>
                <button class="btn btn-primary" onclick="sendNotifications()">Send Notifications</button>
            </div>
        </div>
    </div>

    <script>
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Update current date and time
            updateDateTime();
            setInterval(updateDateTime, 60000); // Update every minute

            // Course Attendance Bar Chart
            const courseCtx = document.getElementById('courseChart').getContext('2d');
            new Chart(courseCtx, {
                type: 'bar',
                data: {
                    labels: ['BIC20403', 'BIT10102', 'BIT31405', 'BIT20304', 'BIT40506'],
                    datasets: [{
                        label: 'Attendance %',
                        data: [92, 55, 78, 85, 88],
                        backgroundColor: [
                            '#4361ee',
                            '#e74c3c',
                            '#3498db',
                            '#f39c12',
                            '#9b59b6'
                        ],
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
                    labels: ['W1', 'W2', 'W3', 'W4', 'W5', 'W6', 'W7', 'W8', 'W9', 'W10', 'W11', 'W12'],
                    datasets: [{
                        label: 'BIC20403',
                        data: [85, 88, 90, 87, 92, 91, 93, 92, 90, 92, 91, 92],
                        borderColor: '#4361ee',
                        backgroundColor: 'rgba(67, 97, 238, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.3
                    }, {
                        label: 'BIT10102',
                        data: [70, 65, 68, 60, 58, 55, 53, 52, 55, 54, 56, 55],
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
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
                        data: [65, 30, 5],
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

        function showNotifyModal() {
            document.getElementById('notifyModal').style.display = 'flex';
        }

        function closeNotifyModal() {
            document.getElementById('notifyModal').style.display = 'none';
        }

        // Export function
        function exportAs(format) {
            showToast(`Exporting as ${format}...`, 'info');
            // Simulate export delay
            setTimeout(() => {
                showToast(`Report exported successfully as ${format}`, 'success');
                closeExportModal();
            }, 1500);
        }

        function startExport() {
            showToast('Export process started...', 'info');
            closeExportModal();
        }

        function sendNotifications() {
            showToast('Notifications sent to at-risk students', 'success');
            closeNotifyModal();
        }

        // Filter functions
        function applyFilters() {
            showToast('Filters applied successfully', 'success');
        }

        function clearFilters() {
            document.getElementById('semesterFilter').value = 'Current Semester';
            document.getElementById('courseFilter').value = 'All Courses';
            document.getElementById('statusFilter').value = 'All Status';
            showToast('Filters cleared', 'info');
        }

        // Record functions
        function editRecord(name) {
            showToast(`Editing record for ${name}`, 'info');
        }

        function viewDetails(name) {
            showToast(`Viewing details for ${name}`, 'info');
        }

        function notifyStudent(name) {
            showToast(`Notification sent to ${name}`, 'success');
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