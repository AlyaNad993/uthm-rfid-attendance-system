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
        }

        .chart-placeholder i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: #adb5bd;
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
        .status-late { background: rgba(243, 156, 18, 0.15); color: #d35400; }
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
            gap: 1rem;
        }

        .activity-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.2rem;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid var(--primary);
        }

        .activity-time {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--dark);
        }

        .activity-course {
            color: var(--gray);
            font-size: 0.9rem;
        }

        .activity-actions {
            display: flex;
            gap: 10px;
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
                            <label class="form-label">Course Code</label>
                            <input type="text" class="form-control" id="courseCode" placeholder="e.g., BIC20403" required>
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
                                <option value="1">Semester 1</option>
                                <option value="2">Semester 2</option>
                                <option value="3">Semester 3</option>
                                <option value="4" selected>Semester 4</option>
                                <option value="5">Semester 5</option>
                                <option value="6">Semester 6</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" id="studentNotes" rows="3" placeholder="Additional information..."></textarea>
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
                    <input type="text" class="form-control" id="rfidUid" placeholder="e.g., 43F3X2" readonly>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Assign to Student</label>
                    <select class="form-control" id="assignStudent">
                        <option value="">Select a student...</option>
                        <option value="DI230076">DI230076 - Nur Alya</option>
                        <option value="DI230081">DI230081 - Adam</option>
                        <option value="BIE20009">BIE20009 - Idbal</option>
                        <option value="DI230110">DI230110 - Siti</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Card Type</label>
                    <select class="form-control" id="cardType">
                        <option value="student">Student Card</option>
                        <option value="lecturer">Lecturer Card</option>
                        <option value="staff">Staff Card</option>
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
                        <option value="BIC20403">BIC20403 - Database Systems</option>
                        <option value="BIC31502">BIC31502 - Web Development</option>
                        <option value="BIE20203">BIE20203 - Operating Systems</option>
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
                            <option value="saturday">Saturday</option>
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
                            <input type="checkbox" id="allowLate" checked> Allow late attendance (within 15 minutes)
                        </label>
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
                        <input type="date" class="form-control" id="exportFrom" value="2024-01-01">
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Date To</label>
                        <input type="date" class="form-control" id="exportTo" value="2024-12-31">
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
                
                <div class="admin-profile">
                    <img src="https://ui-avatars.com/api/?name=Admin+User&background=4361ee&color=fff&size=128" alt="Admin">
                    <div>
                        <div style="font-weight: 600;">Admin</div>
                        <div style="font-size: 0.85rem; color: var(--gray);">Super Admin</div>
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
                            <div class="stat-number" id="totalStudents">1,280</div>
                            <div class="stat-label">Total Students</div>
                        </div>
                        <div class="stat-icon students">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Active cards: <span id="activeCards">1,245</span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalLecturers">86</div>
                            <div class="stat-label">Total Lecturers</div>
                        </div>
                        <div class="stat-icon lecturers">
                            <i class="fas fa-chalkboard-teacher"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Logged today: <span id="loggedToday">78</span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="totalCourses">143</div>
                            <div class="stat-label">Courses (This Sem)</div>
                        </div>
                        <div class="stat-icon courses">
                            <i class="fas fa-book-open"></i>
                        </div>
                    </div>
                    <div class="stat-sub">Timetables updated: <span id="timetableUpdated">98%</span></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-header">
                        <div>
                            <div class="stat-number" id="todayAttendance">2,954</div>
                            <div class="stat-label">Today Attendance</div>
                        </div>
                        <div class="stat-icon attendance">
                            <i class="fas fa-clipboard-check"></i>
                        </div>
                    </div>
                    <div class="stat-sub">
                        <span style="color: #27ae60;">● <span id="presentCount">112</span> Present</span> | 
                        <span style="color: #d35400;">● <span id="lateCount">64</span> Late</span> | 
                        <span style="color: #c0392b;">● <span id="absentCount">38</span> Absent</span>
                    </div>
                </div>
            </div>

            <!-- CHARTS & TABLES -->
            <div class="content-grid">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Monthly Attendance Trend</div>
                        <div style="font-size: 1.2rem; font-weight: 700; color: var(--primary);" id="attendanceRate">94.2%</div>
                    </div>
                    <div class="chart-placeholder">
                        <div style="text-align: center;">
                            <i class="fas fa-chart-line"></i>
                            <div>Attendance trend visualization</div>
                            <div style="font-size: 0.9rem; margin-top: 10px;">Jan 2024 - Dec 2024</div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Recent Attendance Logs</div>
                        <button class="btn btn-view" onclick="viewAllLogs()">
                            <i class="fas fa-eye"></i> View All
                        </button>
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
        // Sample Data
        const attendanceData = [
            { time: "08:01", course: "BIC20403", studentId: "DI230076", name: "Nur Alya", rfid: "43F3X2", status: "present" },
            { time: "08:06", course: "BIC31502", studentId: "DI230081", name: "Adam", rfid: "F1C02B", status: "late" },
            { time: "08:10", course: "BIE20203", studentId: "BIE20009", name: "Idbal", rfid: "929301", status: "absent" },
            { time: "14:03", course: "BIC20403", studentId: "DI230110", name: "Siti", rfid: "882201", status: "present" },
            { time: "14:15", course: "BIC20403", studentId: "DI230115", name: "Ahmad", rfid: "A1B2C3", status: "present" },
            { time: "14:20", course: "BIC31502", studentId: "DI230120", name: "Sarah", rfid: "D4E5F6", status: "late" }
        ];

        const activities = [
            { time: "15:03:42", course: "FTC301 - Web Development" },
            { time: "14:03:05", course: "OST202 - Operating Systems" },
            { time: "13:45:22", course: "DBM401 - Database Management" },
            { time: "11:20:15", course: "NET301 - Networking" }
        ];

        // Initialize dashboard
        document.addEventListener('DOMContentLoaded', function() {
            populateAttendanceTable();
            populateActivities();
            updateLiveStats();
            
            // Set active nav item
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.addEventListener('click', function() {
                    navItems.forEach(i => i.classList.remove('active'));
                    this.classList.add('active');
                    showToast('Navigated to ' + this.querySelector('span').textContent, 'info');
                });
            });
            
            // Simulate RFID scan in modal
            document.getElementById('registerRFIDModal').addEventListener('click', function(e) {
                if (e.target.closest('.modal-content')) return;
                simulateRFIDScan();
            });
            
            // Auto refresh data every 30 seconds
            setInterval(updateLiveStats, 30000);
        });

        // Modal Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
            
            if (modalId === 'registerRFIDModal') {
                simulateRFIDScan();
            }
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
            
            if (!studentId || !studentName) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Update stats
            const totalStudents = document.getElementById('totalStudents');
            totalStudents.textContent = parseInt(totalStudents.textContent) + 1;
            
            const activeCards = document.getElementById('activeCards');
            activeCards.textContent = parseInt(activeCards.textContent) + 1;
            
            // Add to table
            attendanceData.unshift({
                time: new Date().toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}),
                course: document.getElementById('courseCode').value,
                studentId: studentId,
                name: studentName,
                rfid: 'Pending',
                status: 'absent'
            });
            
            populateAttendanceTable();
            closeModal('addStudentModal');
            document.getElementById('addStudentForm').reset();
            showToast(`Student ${studentName} added successfully!`, 'success');
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
            const rfidUid = document.getElementById('rfidUid').value;
            const student = document.getElementById('assignStudent').value;
            
            if (!rfidUid || !student) {
                showToast('Please scan a card and assign to a student', 'error');
                return;
            }
            
            // Find student in data and update RFID
            const studentData = attendanceData.find(item => item.studentId === student);
            if (studentData) {
                studentData.rfid = rfidUid;
                populateAttendanceTable();
            }
            
            // Update active cards count
            const activeCards = document.getElementById('activeCards');
            activeCards.textContent = parseInt(activeCards.textContent) + 1;
            
            closeModal('registerRFIDModal');
            showToast(`RFID card ${rfidUid} registered to student ${student}`, 'success');
        }

        function saveTimetable() {
            const course = document.getElementById('timetableCourse').value;
            const day = document.getElementById('timetableDay').value;
            const time = document.getElementById('timetableTime').value;
            
            if (!course) {
                showToast('Please select a course', 'error');
                return;
            }
            
            // Update timetable updated percentage
            const timetableUpdated = document.getElementById('timetableUpdated');
            let percentage = parseInt(timetableUpdated.textContent);
            if (percentage < 100) percentage += 2;
            timetableUpdated.textContent = percentage + '%';
            
            closeModal('timetableModal');
            showToast(`Timetable for ${course} saved successfully`, 'success');
        }

        function exportReport() {
            const exportType = document.getElementById('exportType').value;
            const format = document.querySelector('input[name="exportFormat"]:checked').value;
            
            // Simulate export process
            showToast(`Exporting ${exportType} report as ${format.toUpperCase()}...`, 'info');
            
            setTimeout(() => {
                closeModal('exportModal');
                showToast(`Report exported successfully! Download will begin shortly.`, 'success');
                
                // Simulate download
                const link = document.createElement('a');
                link.href = 'data:text/csv;charset=utf-8,Report%20data';
                link.download = `attendance_report_${new Date().toISOString().slice(0,10)}.${format}`;
                link.style.display = 'none';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 2000);
        }

        // Dashboard Functions
        function populateAttendanceTable() {
            const tbody = document.getElementById('attendanceTableBody');
            tbody.innerHTML = '';
            
            attendanceData.forEach(item => {
                const row = document.createElement('tr');
                
                let statusClass = '';
                let statusText = '';
                switch(item.status) {
                    case 'present':
                        statusClass = 'status-present';
                        statusText = 'Present';
                        break;
                    case 'late':
                        statusClass = 'status-late';
                        statusText = 'Late';
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
            
            activities.forEach(activity => {
                const activityItem = document.createElement('div');
                activityItem.className = 'activity-item';
                activityItem.innerHTML = `
                    <div>
                        <div class="activity-time">${activity.time}</div>
                        <div class="activity-course">${activity.course}</div>
                    </div>
                    <div class="activity-actions">
                        <button class="btn btn-view" onclick="viewActivity('${activity.course}')">View</button>
                        <button class="btn btn-export" onclick="exportActivity('${activity.course}')">Export</button>
                    </div>
                `;
                activityList.appendChild(activityItem);
            });
        }

        function updateLiveStats() {
            // Simulate live data updates
            const present = Math.floor(Math.random() * 20) + 100;
            const late = Math.floor(Math.random() * 10) + 60;
            const absent = Math.floor(Math.random() * 5) + 35;
            const total = present + late + absent;
            
            document.getElementById('presentCount').textContent = present;
            document.getElementById('lateCount').textContent = late;
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
            showToast('Opening all attendance logs...', 'info');
            // Implement view all logs functionality
        }

        function viewActivity(course) {
            showToast(`Viewing details for ${course}`, 'info');
        }

        function exportActivity(course) {
            showToast(`Exporting data for ${course}`, 'success');
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