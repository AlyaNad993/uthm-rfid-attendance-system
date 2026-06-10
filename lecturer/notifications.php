<?php
require_once '../includes/auth_check.php';
requireLecturer();
require_once '../includes/config.php';
require_once '../includes/attendance_features.php';
ensureAttendanceFeatureSchema($pdo);

$lecturerId = (int)$_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'mark_all_read') {
        $stmt = $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([$lecturerId]);
    } elseif ($action === 'mark_read') {
        $notificationId = (int)($_POST['notification_id'] ?? 0);
        if ($notificationId) {
            $stmt = $pdo->prepare("
                UPDATE notifications
                SET is_read = 1
                WHERE notification_id = ?
                  AND user_id = ?
            ");
            $stmt->execute([$notificationId, $lecturerId]);
        }
    }

    header('Location: notifications.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT notification_id, title, message, related_url, type, is_read, created_at
    FROM notifications
    WHERE user_id = ?
    ORDER BY is_read ASC, created_at DESC
");
$stmt->execute([$lecturerId]);
$notifications = $stmt->fetchAll();

$unreadCount = count(array_filter($notifications, fn($n) => (int)$n['is_read'] === 0));

function e($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            min-height: 100vh;
            background: #f4f7fb;
            color: #1f2937;
            font-family: "Segoe UI", Arial, sans-serif;
            padding: 24px;
        }
        .page { max-width: 980px; margin: 0 auto; }
        .topbar {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            gap: 16px;
            margin-bottom: 22px;
        }
        h1 { font-size: 30px; line-height: 1.2; color: #111827; }
        .muted { color: #64748b; margin-top: 6px; }
        .actions { display: flex; gap: 10px; flex-wrap: wrap; }
        .notification-actions {
            display: flex;
            gap: 8px;
            align-items: center;
            flex-wrap: wrap;
            justify-content: flex-end;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0 14px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
            color: #334155;
            font-weight: 700;
            text-decoration: none;
            cursor: pointer;
        }
        .btn-primary { background: #2563eb; border-color: #2563eb; color: #fff; }
        .list {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 10px 22px rgba(15, 23, 42, 0.06);
            overflow: hidden;
        }
        .notification {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 16px;
            padding: 16px 18px;
            border-bottom: 1px solid #eef2f7;
        }
        .notification:last-child { border-bottom: 0; }
        .notification.unread {
            background: #eff6ff;
            box-shadow: inset 3px 0 0 #2563eb;
        }
        .title {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #111827;
            font-weight: 850;
        }
        .message { margin-top: 6px; color: #475569; line-height: 1.5; }
        .meta {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 8px;
            color: #64748b;
            font-size: 13px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            height: 26px;
            padding: 0 10px;
            border-radius: 999px;
            background: #dbeafe;
            color: #1d4ed8;
            font-size: 12px;
            font-weight: 800;
            text-transform: uppercase;
        }
        .alert { background: #fee2e2; color: #991b1b; }
        .attendance { background: #dcfce7; color: #166534; }
        .reminder { background: #fef3c7; color: #92400e; }
        .empty {
            padding: 36px 18px;
            color: #64748b;
            text-align: center;
        }
        @media (max-width: 720px) {
            body { padding: 16px; }
            .topbar,
            .notification { display: block; }
            .actions { margin-top: 14px; }
            .notification-actions { justify-content: flex-start; margin-top: 12px; }
        }
    </style>
    <link rel="stylesheet" href="../assets/css/lecturer-theme.css">
    <link rel="stylesheet" href="../assets/css/app-polish.css">
    <link rel="stylesheet" href="../assets/css/lecturer-polish.css">
    <style>
        body {
            background:
                radial-gradient(circle at 8% 8%, rgba(0, 104, 55, 0.16), transparent 30%),
                radial-gradient(circle at 92% 10%, rgba(67, 97, 238, 0.14), transparent 28%),
                linear-gradient(135deg, #f7fbff 0%, #edf8f3 100%) !important;
        }
        .page { max-width: 1040px; }
        .topbar,
        .list {
            border: 1px solid #dce6f2 !important;
            border-radius: 18px !important;
            box-shadow: 0 18px 46px rgba(28, 52, 84, 0.12) !important;
            background: linear-gradient(180deg, rgba(255,255,255,.98), rgba(250,253,252,.94)) !important;
        }
        .topbar {
            padding: 26px 28px;
            align-items: center;
        }
        .btn {
            min-height: 46px;
            border-radius: 12px !important;
            font-weight: 800;
        }
        .btn-primary {
            background: linear-gradient(135deg, #006837, #4361ee) !important;
        }
        .btn-view {
            background: linear-gradient(135deg, #0f766e, #2563eb) !important;
            color: #fff !important;
            border-color: transparent !important;
        }
        .notification {
            padding: 18px 20px;
            transition: transform .18s ease, box-shadow .18s ease;
        }
        .notification:hover {
            transform: translateY(-1px);
            box-shadow: 0 10px 24px rgba(28, 52, 84, 0.08);
        }
        .notification.unread {
            background: linear-gradient(90deg, rgba(0,104,55,.08), rgba(67,97,238,.07)) !important;
            box-shadow: inset 4px 0 0 #006837 !important;
        }
        .badge {
            border: 1px solid rgba(67, 97, 238, 0.14);
        }
        .empty {
            padding: 46px 24px;
        }
    </style>
</head>
<body>
    <main class="page">
        <div class="topbar">
            <div>
                <h1>Notifications</h1>
                <p class="muted"><?= e($unreadCount) ?> unread notification(s).</p>
            </div>
            <div class="actions">
                <?php if ($unreadCount > 0): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="mark_all_read">
                        <button class="btn btn-primary" type="submit">Mark All Read</button>
                    </form>
                <?php endif; ?>
                <a class="btn" href="dashboard.php">Dashboard</a>
            </div>
        </div>

        <section class="list">
            <?php if (!$notifications): ?>
                <div class="empty">No notifications yet.</div>
            <?php else: ?>
                <?php foreach ($notifications as $notification): ?>
                    <article class="notification <?= (int)$notification['is_read'] === 0 ? 'unread' : '' ?>">
                        <div>
                            <div class="title">
                                <?= e($notification['title']) ?>
                                <span class="badge <?= e($notification['type']) ?>"><?= e($notification['type']) ?></span>
                            </div>
                            <div class="message"><?= e($notification['message']) ?></div>
                            <div class="meta">
                                <span><?= e(date('d M Y, h:i A', strtotime($notification['created_at']))) ?></span>
                                <span><?= (int)$notification['is_read'] === 0 ? 'Unread' : 'Read' ?></span>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!empty($notification['related_url'])): ?>
                                <a class="btn btn-view" href="<?= e($notification['related_url']) ?>">View Details</a>
                            <?php endif; ?>
                            <?php if ((int)$notification['is_read'] === 0): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= e($notification['notification_id']) ?>">
                                    <button class="btn" type="submit">Mark Read</button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php endif; ?>
        </section>
    </main>
</body>
</html>
