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

        .btn-action-sm {
            padding: 6px 12px;
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

        /* ATTENDANCE RULES */
        .rules-section {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .rules-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 1rem;
        }

        .rule-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
            padding: 1rem;
            background: white;
            border-radius: 8px;
            border: 1px solid #eee;
        }

        .rule-icon {
            width: 40px;
            height: 40px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: rgba(67, 97, 238, 0.1);
            color: var(--primary);
        }

        .rule-content {
            flex: 1;
        }

        .rule-label {
            font-weight: 500;
            color: var(--dark);
            margin-bottom: 4px;
        }

        .rule-desc {
            font-size: 0.9rem;
            color: var(--gray);
        }

        .rule-toggle {
            position: relative;
        }

        .toggle-switch {
            width: 50px;
            height: 26px;
            background: #ddd;
            border-radius: 13px;
            position: relative;
            cursor: pointer;
            transition: var(--transition);
        }

        .toggle-switch.active {
            background: var(--success);
        }

        .toggle-knob {
            width: 22px;
            height: 22px;
            background: white;
            border-radius: 50%;
            position: absolute;
            top: 2px;
            left: 2px;
            transition: var(--transition);
        }

        .toggle-switch.active .toggle-knob {
            transform: translateX(24px);
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
                justify-content: stretch;
            }
            
            .action-buttons .btn {
                flex: 1;
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
</head>
<body>
    <!-- MODALS -->
    <div id="addCourseModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Add New Course</h3>
                <button class="modal-close" onclick="closeModal('addCourseModal')">&times;</button>
            </div>
            <div class="modal-body">
                <form id="addCourseForm">
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="courseCode" placeholder="e.g., BIC20403" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course Name</label>
                            <input type="text" class="form-control" id="courseName" placeholder="e.g., Software Engineering" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Credit Hours</label>
                            <input type="number" class="form-control" id="creditHours" min="1" max="6" value="3">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Semester</label>
                            <select class="form-control" id="courseSemester">
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3" selected>Semester 3</option>
                                <option value="4">Semester 4</option>
                                <option value="5">Semester 5</option>
                                <option value="6">Semester 6</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Department</label>
                        <select class="form-control" id="courseDepartment">
                            <option value="computing">Faculty of Computing</option>
                            <option value="engineering">Faculty of Engineering</option>
                            <option value="business">Faculty of Business</option>
                            <option value="science">Faculty of Science</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Course Description</label>
                        <textarea class="form-control" id="courseDescription" rows="3" placeholder="Brief description of the course..."></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button class="btn btn-secondary" onclick="closeModal('addCourseModal')">Cancel</button>
                <button class="btn btn-primary" onclick="addCourse()">
                    <i class="fas fa-plus"></i> Add Course
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
                            <label class="form-label">Course</label>
                            <select class="form-control" id="scheduleCourse">
                                <option value="">Select a course...</option>
                                <option value="BIC20403">BIC20403 - Software Engineering</option>
                                <option value="BIC31502">BIC31502 - Web Development</option>
                                <option value="BIE20203">BIE20203 - Operating Systems</option>
                                <option value="BIC20404">BIC20404 - Database Systems</option>
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
                            <input type="text" class="form-control" id="scheduleRoom" placeholder="e.g., BK-213" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Lecturer</label>
                            <select class="form-control" id="scheduleLecturer">
                                <option value="">Select lecturer...</option>
                                <option value="Dr. Nurul Aswa">Dr. Nurul Aswa</option>
                                <option value="Mr. Adam">Mr. Adam</option>
                                <option value="Prof. Ahmad">Prof. Ahmad</option>
                                <option value="Dr. Lee">Dr. Lee</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Grace Period (minutes)</label>
                            <input type="number" class="form-control" id="scheduleGrace" min="0" max="30" value="10">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Students</label>
                            <input type="number" class="form-control" id="scheduleMaxStudents" min="1" max="200" value="50">
                        </div>
                    </div>
                    
                    <!-- Attendance Rules -->
                    <div class="rules-section">
                        <div class="rules-title">Attendance Rules</div>
                        
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="rule-content">
                                <div class="rule-label">Allow Late Attendance</div>
                                <div class="rule-desc">Students can check-in within grace period</div>
                            </div>
                            <div class="rule-toggle">
                                <div class="toggle-switch active" onclick="toggleRule(this)">
                                    <div class="toggle-knob"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-id-card"></i>
                            </div>
                            <div class="rule-content">
                                <div class="rule-label">RFID Required</div>
                                <div class="rule-desc">Must use RFID card for attendance</div>
                            </div>
                            <div class="rule-toggle">
                                <div class="toggle-switch active" onclick="toggleRule(this)">
                                    <div class="toggle-knob"></div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="rule-item">
                            <div class="rule-icon">
                                <i class="fas fa-user-times"></i>
                            </div>
                            <div class="rule-content">
                                <div class="rule-label">Auto-mark Absent</div>
                                <div class="rule-desc">Automatically mark absent after 30 minutes</div>
                            </div>
                            <div class="rule-toggle">
                                <div class="toggle-switch" onclick="toggleRule(this)">
                                    <div class="toggle-knob"></div>
                                </div>
                            </div>
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
                            <label class="form-label">Course</label>
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
                                <option value="Dr. Nurul Aswa">Dr. Nurul Aswa</option>
                                <option value="Mr. Adam">Mr. Adam</option>
                                <option value="Prof. Ahmad">Prof. Ahmad</option>
                                <option value="Dr. Lee">Dr. Lee</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Grace Period (minutes)</label>
                        <input type="number" class="form-control" id="editScheduleGrace" min="0" max="30" value="10">
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
            <a href="admin-dashboard.html" class="logo">
                <i class="fas fa-id-card"></i>
                <div>
                    <h1>RFID IoT Attendance</h1>
                    <span>Admin Console</span>
                </div>
            </a>
            
            <div class="header-right">
                <div class="status-badge">
                    <i class="fas fa-circle fa-xs"></i>
                    <span>Online</span>
                </div>
                <div class="status-badge offline">
                    <i class="fas fa-sync-alt"></i>
                    <span id="syncQueue">Sync Queue: 2</span>
                </div>
                <div class="status-badge">
                    <i class="fas fa-rss"></i>
                    <span id="rfidReaders">RFID Readers: 8 Active</span>
                </div>
                
                <a href="#" class="admin-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=4361ee&color=fff&size=128" alt="Admin">
                    <div>
                        <div style="font-weight: 600;">Admin</div>
                        <div style="font-size: 0.85rem; color: var(--gray);">Super Admin</div>
                    </div>
                </a>
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
                        <i class="fas fa-shield-alt"></i>
                        <span>Security</span>
                    </li>
                </ul>
            </div>
            
            <button class="logout-btn" onclick="window.location.href='../logout.php'">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </button>
        </nav>

        <!-- MAIN CONTENT -->
        <main class="main-content">
            <!-- PAGE HEADER -->
            <div class="page-header">
                <div class="page-title">
                    <h1>Courses & Timetable</h1>
                    <p>Manage courses, class schedules, and attendance rules</p>
                </div>
                
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="openModal('addCourseModal')">
                        <i class="fas fa-plus"></i> Add Course
                    </button>
                    <button class="btn btn-success" onclick="openModal('addScheduleModal')">
                        <i class="fas fa-calendar-plus"></i> Add Schedule
                    </button>
                    <button class="btn btn-outline">
                        <i class="fas fa-file-export"></i> Export
                    </button>
                </div>
            </div>

            <!-- COURSE SELECTOR -->
            <div class="course-selector">
                <div class="selector-header">
                    <div class="selector-title">Select Course</div>
                    <div style="color: var(--gray); font-size: 0.9rem;">
                        Current Semester: <strong>Semester 3, 2024</strong>
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
                                <th>Course Code</th>
                                <th>Day</th>
                                <th>Time</th>
                                <th>Room</th>
                                <th>Lecturer</th>
                                <th>Grace (min)</th>
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
        // Sample Data
        const coursesData = [
            { code: "BIC20403", name: "Software Engineering", semester: 3, department: "computing", students: 120, lecturers: 2, attendance: 94, rfidActive: 102 },
            { code: "BIC31502", name: "Web Development", semester: 3, department: "computing", students: 85, lecturers: 1, attendance: 89, rfidActive: 78 },
            { code: "BIE20203", name: "Operating Systems", semester: 3, department: "computing", students: 95, lecturers: 1, attendance: 91, rfidActive: 88 },
            { code: "BIC20404", name: "Database Systems", semester: 3, department: "computing", students: 110, lecturers: 2, attendance: 96, rfidActive: 105 },
            { code: "BIM20203", name: "Data Structures", semester: 3, department: "computing", students: 75, lecturers: 1, attendance: 88, rfidActive: 70 }
        ];

        const schedulesData = [
            { id: 1, course: "BIC20403", day: "monday", start: "08:00", end: "10:00", room: "BK-213", lecturer: "Dr. Nurul Aswa", grace: 10 },
            { id: 2, course: "BIC20403", day: "wednesday", start: "14:00", end: "16:00", room: "LAB-301", lecturer: "Dr. Nurul Aswa", grace: 10 },
            { id: 3, course: "BIC31502", day: "tuesday", start: "10:00", end: "12:00", room: "DK-105", lecturer: "Mr. Adam", grace: 5 },
            { id: 4, course: "BIC31502", day: "thursday", start: "13:00", end: "15:00", room: "LAB-302", lecturer: "Mr. Adam", grace: 5 },
            { id: 5, course: "BIE20203", day: "monday", start: "13:00", end: "15:00", room: "BK-215", lecturer: "Prof. Ahmad", grace: 15 },
            { id: 6, course: "BIE20203", day: "friday", start: "09:00", end: "11:00", room: "LAB-303", lecturer: "Prof. Ahmad", grace: 15 },
            { id: 7, course: "BIC20404", day: "tuesday", start: "08:00", end: "10:00", room: "BK-210", lecturer: "Dr. Lee", grace: 10 },
            { id: 8, course: "BIC20404", day: "thursday", start: "15:00", end: "17:00", room: "LAB-304", lecturer: "Dr. Lee", grace: 10 }
        ];

        let selectedCourse = coursesData[0];
        let filteredSchedules = schedulesData.filter(s => s.course === selectedCourse.code);

        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            populateCourseDropdown();
            populateCourseInfo();
            populateWeekView();
            populateTimetableTable();
            
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

        function selectCourse(courseCode) {
            selectedCourse = coursesData.find(c => c.code === courseCode);
            
            // Update selected course display
            document.getElementById('selectedCourse').textContent = 
                `${selectedCourse.code} - ${selectedCourse.name}`;
            
            // Update current course display
            document.getElementById('currentCourseDisplay').textContent = 
                `${selectedCourse.code} - ${selectedCourse.name}`;
            
            // Update filtered schedules
            filteredSchedules = schedulesData.filter(s => s.course === selectedCourse.code);
            
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
            
            coursesData.forEach(course => {
                const option = document.createElement('div');
                option.className = 'course-option';
                option.onclick = () => selectCourse(course.code);
                option.innerHTML = `
                    <div class="course-code">${course.code}</div>
                    <div class="course-name">${course.name}</div>
                `;
                dropdownList.appendChild(option);
            });
        }

        function populateCourseInfo() {
            const courseInfoGrid = document.getElementById('courseInfoGrid');
            
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
                            <div class="class-course">${schedule.room} • ${schedule.lecturer}</div>
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
                    <td><strong>${schedule.course}</strong></td>
                    <td><span class="day-badge ${dayClass}">${dayText}</span></td>
                    <td>${schedule.start} – ${schedule.end}</td>
                    <td>${schedule.room}</td>
                    <td>${schedule.lecturer}</td>
                    <td><span style="font-weight: 500; color: ${schedule.grace > 10 ? 'var(--warning)' : 'var(--success)'}">${schedule.grace}</span></td>
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
        function addCourse() {
            const courseCode = document.getElementById('courseCode').value;
            const courseName = document.getElementById('courseName').value;
            
            if (!courseCode || !courseName) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Add new course to data
            const newCourse = {
                code: courseCode,
                name: courseName,
                semester: parseInt(document.getElementById('courseSemester').value),
                department: document.getElementById('courseDepartment').value,
                students: 0,
                lecturers: 0,
                attendance: 0,
                rfidActive: 0
            };
            
            coursesData.unshift(newCourse);
            
            // Reset form and close modal
            document.getElementById('addCourseForm').reset();
            closeModal('addCourseModal');
            
            // Update dropdown
            populateCourseDropdown();
            
            // Select the new course
            selectCourse(courseCode);
            
            showToast(`Course ${courseName} added successfully!`, 'success');
        }

        // Schedule Management Functions
        function addSchedule() {
            const course = document.getElementById('scheduleCourse').value;
            const day = document.getElementById('scheduleDay').value;
            const start = document.getElementById('scheduleStart').value;
            const end = document.getElementById('scheduleEnd').value;
            
            if (!course || !start || !end) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Add new schedule to data
            const newSchedule = {
                id: schedulesData.length + 1,
                course: course,
                day: day,
                start: start,
                end: end,
                room: document.getElementById('scheduleRoom').value,
                lecturer: document.getElementById('scheduleLecturer').value,
                grace: parseInt(document.getElementById('scheduleGrace').value)
            };
            
            schedulesData.push(newSchedule);
            
            // Update if selected course matches
            if (course === selectedCourse.code) {
                filteredSchedules.push(newSchedule);
                populateWeekView();
                populateTimetableTable();
            }
            
            // Update course info if it's the selected course
            if (course === selectedCourse.code) {
                selectedCourse.students += 10; // Simulate student enrollment
                populateCourseInfo();
            }
            
            // Reset form and close modal
            document.getElementById('addScheduleForm').reset();
            closeModal('addScheduleModal');
            
            showToast(`Schedule for ${course} added successfully!`, 'success');
        }

        function editSchedule(scheduleId) {
            const schedule = schedulesData.find(s => s.id === scheduleId);
            if (!schedule) return;
            
            // Populate form
            document.getElementById('editScheduleId').value = schedule.id;
            document.getElementById('editScheduleCourse').value = `${schedule.course} - ${coursesData.find(c => c.code === schedule.course).name}`;
            document.getElementById('editScheduleDay').value = schedule.day;
            document.getElementById('editScheduleStart').value = schedule.start;
            document.getElementById('editScheduleEnd').value = schedule.end;
            document.getElementById('editScheduleRoom').value = schedule.room;
            document.getElementById('editScheduleLecturer').value = schedule.lecturer;
            document.getElementById('editScheduleGrace').value = schedule.grace;
            
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
            schedulesData[scheduleIndex].grace = parseInt(document.getElementById('editScheduleGrace').value);
            
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

        function toggleRule(element) {
            element.classList.toggle('active');
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
        document.querySelector('.btn-outline').addEventListener('click', function() {
            showToast('Exporting timetable data...', 'info');
            
            setTimeout(() => {
                showToast('Timetable exported successfully! Download will begin shortly.', 'success');
                
                // Simulate download
                const csvContent = "data:text/csv;charset=utf-8," 
                    + "Course Code,Day,Start Time,End Time,Room,Lecturer,Grace Period\n"
                    + schedulesData.map(schedule => 
                        `${schedule.course},${schedule.day},${schedule.start},${schedule.end},${schedule.room},${schedule.lecturer},${schedule.grace}`
                    ).join("\n");
                
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `timetable_${new Date().toISOString().slice(0,10)}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 1000);
        });
    </script>
</body>
</html>