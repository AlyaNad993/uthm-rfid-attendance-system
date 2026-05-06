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
                flex-direction: column;
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
                            <label style="display: flex; align-items: center; gap: 0.5rem;">
                                <input type="radio" name="userType" value="staff"> Staff
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
                        <label class="form-label">Course/Department</label>
                        <input type="text" class="form-control" id="userDepartment" placeholder="e.g., Computer Science">
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
                        <option value="DI230076">DI230076 - Nur Alya Nadhirah</option>
                        <option value="DI230081">DI230081 - Adam</option>
                        <option value="STF004">STF004 - Dr. Nurul Aswa</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label class="form-label">Scan RFID Card</label>
                    <div style="text-align: center; padding: 2rem; border: 2px dashed #ddd; border-radius: 10px; margin-bottom: 1rem;">
                        <i class="fas fa-id-card" style="font-size: 3rem; color: var(--primary); margin-bottom: 1rem;"></i>
                        <p>Place RFID card near the reader</p>
                        <div id="rfidScanStatus" style="color: var(--gray); font-size: 0.9rem; margin-top: 0.5rem;">
                            Waiting for RFID scan...
                        </div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label class="form-label">RFID UID</label>
                    <input type="text" class="form-control" id="assignRFIDUid" placeholder="e.g., A3F9X2" readonly>
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
                    <div class="form-group">
                        <label class="form-label">User ID</label>
                        <input type="text" class="form-control" id="editUserId" readonly>
                    </div>
                    
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
                                <option value="staff">Staff</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Department/Course</label>
                        <input type="text" class="form-control" id="editUserDepartment">
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
                    <button class="btn btn-outline">
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
                            <option value="staff">Staff</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label class="filter-label">Status</label>
                        <select class="filter-select" id="statusFilter" onchange="filterUsers()">
                            <option value="all">All Status</option>
                            <option value="active">Active</option>
                            <option value="inactive">Inactive</option>
                            <option value="pending">Pending</option>
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
                    
                    <div class="search-box">
                        <label class="filter-label">Search</label>
                        <input type="text" class="filter-input search-input" id="searchInput" 
                               placeholder="Search name / matric / RFID UID" onkeyup="searchUsers()">
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
        // Sample Data
        const usersData = [
            { id: "DI230076", name: "Nur Alya Nadhirah", role: "student", rfid: "A3F9X2", status: "active", email: "nuralya@university.edu", phone: "+60 12-345 6789", dept: "Computer Science" },
            { id: "DI230081", name: "Adam", role: "student", rfid: "", status: "inactive", email: "adam@university.edu", phone: "+60 13-456 7890", dept: "Software Engineering" },
            { id: "STF004", name: "Dr. Nurul Aswa", role: "lecturer", rfid: "L9C31A", status: "active", email: "nurulaswa@university.edu", phone: "+60 14-567 8901", dept: "Faculty of Computing" },
            { id: "DI230110", name: "Siti", role: "student", rfid: "882201", status: "active", email: "siti@university.edu", phone: "+60 15-678 9012", dept: "Data Science" },
            { id: "STF012", name: "Prof. Ahmad", role: "lecturer", rfid: "B5D42C", status: "active", email: "ahmad@university.edu", phone: "+60 16-789 0123", dept: "Faculty of Engineering" },
            { id: "ADM001", name: "Admin User", role: "admin", rfid: "AD1234", status: "active", email: "admin@university.edu", phone: "+60 17-890 1234", dept: "Administration" },
            { id: "DI230115", name: "Ahmad", role: "student", rfid: "A1B2C3", status: "active", email: "ahmad.s@university.edu", phone: "+60 18-901 2345", dept: "Cyber Security" },
            { id: "DI230120", name: "Sarah", role: "student", rfid: "D4E5F6", status: "pending", email: "sarah@university.edu", phone: "+60 19-012 3456", dept: "AI & Machine Learning" },
            { id: "STF008", name: "Dr. Lee", role: "lecturer", rfid: "", status: "inactive", email: "lee@university.edu", phone: "+60 10-123 4567", dept: "Faculty of Business" },
            { id: "DI230125", name: "Raju", role: "student", rfid: "X7Y8Z9", status: "active", email: "raju@university.edu", phone: "+60 11-234 5678", dept: "Network Technology" }
        ];

        let currentPage = 1;
        const itemsPerPage = 8;
        let filteredData = [...usersData];

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
            
            if (modalId === 'assignRFIDModal') {
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
                
                row.innerHTML = `
                    <td><strong>${user.id}</strong></td>
                    <td>${user.name}</td>
                    <td><span class="role-badge ${roleClass}">${roleText}</span></td>
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
            
            filteredData = usersData.filter(user => {
                // Role filter
                if (roleFilter !== 'all' && user.role !== roleFilter) return false;
                
                // Status filter
                if (statusFilter !== 'all' && user.status !== statusFilter) return false;
                
                // RFID filter
                if (rfidFilter === 'assigned' && !user.rfid) return false;
                if (rfidFilter === 'unassigned' && user.rfid) return false;
                
                return true;
            });
            
            currentPage = 1;
            populateUsersTable();
            updatePagination();
        }

        function searchUsers() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            
            if (searchTerm === '') {
                filteredData = [...usersData];
            } else {
                filteredData = usersData.filter(user => 
                    user.id.toLowerCase().includes(searchTerm) ||
                    user.name.toLowerCase().includes(searchTerm) ||
                    (user.rfid && user.rfid.toLowerCase().includes(searchTerm))
                );
            }
            
            currentPage = 1;
            populateUsersTable();
            updatePagination();
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
            
            if (!userId || !userName) {
                showToast('Please fill in all required fields', 'error');
                return;
            }
            
            // Add new user to data
            const newUser = {
                id: userId,
                name: userName,
                role: userType,
                rfid: document.getElementById('assignRFIDNow').checked ? 'NEWRFID' : '',
                status: 'active',
                email: document.getElementById('userEmail').value,
                phone: document.getElementById('userPhone').value,
                dept: document.getElementById('userDepartment').value
            };
            
            usersData.unshift(newUser);
            filteredData.unshift(newUser);
            
            // Update stats
            updateStats();
            updateUserCounts();
            
            // Reset form and close modal
            document.getElementById('addUserForm').reset();
            closeModal('addUserModal');
            populateUsersTable();
            
            showToast(`User ${userName} added successfully!`, 'success');
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
            
            openModal('editUserModal');
        }

        function saveUserEdit() {
            const userId = document.getElementById('editUserId').value;
            const userIndex = usersData.findIndex(u => u.id === userId);
            
            if (userIndex === -1) return;
            
            // Update user data
            usersData[userIndex].name = document.getElementById('editUserName').value;
            usersData[userIndex].email = document.getElementById('editUserEmail').value;
            usersData[userIndex].phone = document.getElementById('editUserPhone').value;
            usersData[userIndex].role = document.getElementById('editUserRole').value;
            usersData[userIndex].dept = document.getElementById('editUserDepartment').value;
            usersData[userIndex].status = document.getElementById('editUserStatus').value;
            
            // Update filtered data
            const filteredIndex = filteredData.findIndex(u => u.id === userId);
            if (filteredIndex !== -1) {
                filteredData[filteredIndex] = { ...usersData[userIndex] };
            }
            
            closeModal('editUserModal');
            populateUsersTable();
            updateStats();
            
            showToast(`User ${usersData[userIndex].name} updated successfully!`, 'success');
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
                simulateRFIDScan();
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
            const rfidUid = document.getElementById('assignRFIDUid').value;
            const cardStatus = document.getElementById('cardStatus').value;
            
            if (!userId || !rfidUid) {
                showToast('Please select a user and scan a card', 'error');
                return;
            }
            
            const userIndex = usersData.findIndex(u => u.id === userId);
            if (userIndex === -1) return;
            
            // Assign RFID
            usersData[userIndex].rfid = rfidUid;
            usersData[userIndex].status = cardStatus === 'active' ? 'active' : 'inactive';
            
            // Update filtered data
            const filteredIndex = filteredData.findIndex(u => u.id === userId);
            if (filteredIndex !== -1) {
                filteredData[filteredIndex] = { ...usersData[userIndex] };
            }
            
            // Update stats
            updateStats();
            updateUserCounts();
            
            closeModal('assignRFIDModal');
            populateUsersTable();
            
            showToast(`RFID card ${rfidUid} assigned to user ${usersData[userIndex].name}`, 'success');
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
            if (user) {
                showToast(`Viewing details for ${user.name}`, 'info');
                // In a real app, you would navigate to user detail page
            }
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
            showToast('Exporting user data...', 'info');
            
            setTimeout(() => {
                showToast('Data exported successfully! Download will begin shortly.', 'success');
                
                // Simulate download
                const csvContent = "data:text/csv;charset=utf-8," 
                    + "ID,Name,Role,RFID UID,Status,Email,Phone,Department\n"
                    + usersData.map(user => 
                        `${user.id},${user.name},${user.role},${user.rfid || 'N/A'},${user.status},${user.email},${user.phone || 'N/A'},${user.dept || 'N/A'}`
                    ).join("\n");
                
                const encodedUri = encodeURI(csvContent);
                const link = document.createElement("a");
                link.setAttribute("href", encodedUri);
                link.setAttribute("download", `users_rfid_${new Date().toISOString().slice(0,10)}.csv`);
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }, 1000);
        }

        // Add event listener to export button
        document.querySelector('.btn-outline').addEventListener('click', exportData);
    </script>
</body>
</html>