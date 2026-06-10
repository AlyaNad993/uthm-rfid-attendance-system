<?php
require_once '../includes/auth_check.php';
requireAdmin();
require_once '../includes/config.php';

$role = strtolower(trim($_GET['role'] ?? 'all'));
$format = strtolower(trim($_GET['format'] ?? 'csv'));
$course = strtoupper(trim($_GET['course'] ?? 'all'));

$allowedRoles = ['all', 'student', 'lecturer', 'admin'];
if (!in_array($role, $allowedRoles, true)) {
    $role = 'all';
}

$allowedCourses = ['all', 'BIW', 'BIM', 'BIP', 'BIT', 'BIS'];
if (!in_array($course, $allowedCourses, true)) {
    $course = 'all';
}

if (!in_array($format, ['csv', 'pdf'], true)) {
    $format = 'csv';
}

$where = "u.is_active = 1";
$params = [];
if ($role !== 'all') {
    $where .= " AND u.role = ?";
    $params[] = $role;
}
if ($course !== 'all') {
    $where .= " AND u.department = ?";
    $params[] = $course;
}

$stmt = $pdo->prepare("
    SELECT
        u.user_id,
        u.matric_no,
        u.full_name,
        u.role,
        u.email,
        u.phone,
        u.department,
        CASE WHEN u.is_active = 1 THEN 'active' ELSE 'inactive' END AS account_status,
        COALESCE(rc.uid, '') AS rfid_uid,
        COALESCE(rc.status, '') AS card_status
    FROM users u
    LEFT JOIN rfid_cards rc
        ON rc.card_id = (
            SELECT rc2.card_id
            FROM rfid_cards rc2
            WHERE rc2.user_id = u.user_id
            ORDER BY rc2.issue_date DESC, rc2.card_id DESC
            LIMIT 1
        )
    WHERE $where
    ORDER BY u.department, FIELD(u.role, 'student', 'lecturer', 'admin', 'staff'), u.full_name
");
$stmt->execute($params);
$users = $stmt->fetchAll();

$label = $role === 'all' ? 'All Users' : ucfirst($role) . ' List';
if ($course !== 'all') {
    $label .= ' - ' . $course;
}
$date = date('Y-m-d');
$safeRole = preg_replace('/[^a-z0-9_-]/i', '_', $role . '_' . strtolower($course));

if ($format === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="user_rfid_' . $safeRole . '_' . $date . '.csv"');

    $out = fopen('php://output', 'w');
    fputcsv($out, [$label]);
    fputcsv($out, ['Generated At', date('Y-m-d H:i:s')]);
    fputcsv($out, []);
    fputcsv($out, [
        'User ID',
        'Matric / Staff ID',
        'Name',
        'Role',
        'Email',
        'Phone',
        'Course',
        'Account Status',
        'RFID UID',
        'RFID Card Status'
    ]);

    foreach ($users as $user) {
        fputcsv($out, [
            $user['user_id'],
            $user['matric_no'],
            $user['full_name'],
            $user['role'],
            $user['email'],
            $user['phone'],
            $user['department'],
            $user['account_status'],
            $user['rfid_uid'] ?: 'Not assigned',
            $user['card_status'] ?: 'Not assigned',
        ]);
    }

    fclose($out);
    exit;
}

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($label) ?> | User RFID Export</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; font-family: "Segoe UI", Arial, sans-serif; color: #172033; background: radial-gradient(circle at top left, rgba(0, 104, 55, 0.12), transparent 30%), radial-gradient(circle at bottom right, rgba(67, 97, 238, 0.10), transparent 28%), #f5f8fc; }
        .page { position: relative; max-width: 1180px; margin: 0 auto; background: rgba(255,255,255,0.96); border: 1px solid #dce4ef; border-radius: 18px; padding: 30px; box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12); overflow: hidden; }
        .page::before { content: ""; position: absolute; inset: 0 0 auto 0; height: 6px; background: linear-gradient(90deg, #006837, #0e8a83, #4361ee); }
        .top { display: flex; align-items: flex-start; justify-content: space-between; gap: 18px; margin-bottom: 24px; }
        h1 { margin: 0 0 6px; font-size: 32px; letter-spacing: 0; color: #102033; }
        .muted { color: #66738a; }
        .btn { min-height: 42px; padding: 0 16px; border: 1px solid transparent; border-radius: 12px; background: linear-gradient(135deg, #006837, #4361ee); color: #fff; font-weight: 800; cursor: pointer; box-shadow: 0 8px 18px rgba(28, 52, 84, 0.10); }
        table { width: 100%; border-collapse: collapse; font-size: 13px; }
        th, td { padding: 11px 12px; border: 1px solid #e7edf5; text-align: left; vertical-align: top; }
        th { background: linear-gradient(135deg, #edf8f1, #eef4ff); color: #24415c; text-transform: uppercase; font-size: 12px; }
        tr:nth-child(even) td { background: #f8fafc; }
        .badge { display: inline-block; padding: 4px 8px; border-radius: 999px; font-size: 11px; font-weight: 800; text-transform: uppercase; background: #ecfdf3; color: #067647; }
        .badge.off { background: #fee2e2; color: #991b1b; }
        @media print {
            body { background: #fff; padding: 0; }
            .page { box-shadow: none; border: 0; border-radius: 0; max-width: none; }
            .actions { display: none; }
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="top">
            <div>
                <h1><?= e($label) ?></h1>
                <div class="muted">RFID IoT Attendance - Admin Console</div>
                <div class="muted">Generated: <?= e(date('Y-m-d H:i:s')) ?></div>
            </div>
            <div class="actions">
                <button class="btn" onclick="window.location.href='dashboard.php'">Back to Dashboard</button>
                <button class="btn" onclick="window.print()">Print / Save PDF</button>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Matric / Staff ID</th>
                    <th>Name</th>
                    <th>Role</th>
                    <th>Email</th>
                    <th>Phone</th>
                    <th>Course</th>
                    <th>Account</th>
                    <th>RFID UID</th>
                    <th>Card Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!$users): ?>
                    <tr><td colspan="9">No users found.</td></tr>
                <?php endif; ?>
                <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?= e($user['matric_no']) ?></td>
                        <td><?= e($user['full_name']) ?></td>
                        <td><?= e(ucfirst($user['role'])) ?></td>
                        <td><?= e($user['email']) ?></td>
                        <td><?= e($user['phone'] ?: '-') ?></td>
                        <td><?= e($user['department'] ?: '-') ?></td>
                        <td><span class="badge"><?= e($user['account_status']) ?></span></td>
                        <td><?= e($user['rfid_uid'] ?: 'Not assigned') ?></td>
                        <td>
                            <?php $cardStatus = $user['card_status'] ?: 'Not assigned'; ?>
                            <span class="badge <?= $cardStatus === 'active' ? '' : 'off' ?>"><?= e($cardStatus) ?></span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </main>
</body>
</html>
