<?php
session_start();
require_once 'db_connection.php';
require_once 'notifications/notification_functions.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Handle mark all as read
if (isset($_GET['mark_all_read'])) {
    markAllAsRead($user_id);
    header("Location: notifications.php");
    exit;
}

// Handle mark single as read
if (isset($_GET['mark_as_read'])) {
    markAsRead($_GET['mark_as_read']);
    header("Location: notifications.php");
    exit;
}

// Get all notifications
$notifications = getRecentNotifications($user_id, 50);
$unread_count = getUnreadCount($user_id);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #007bff;
        }
        .mark-all-read {
            background: #007bff;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
        }
        .notification-item {
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        .notification-item.unread {
            background: #f8f9fa;
            border-left: 4px solid #007bff;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Notifications (<?php echo $unread_count; ?> unread)</h1>
            <?php if ($unread_count > 0): ?>
                <a href="notifications.php?mark_all_read=1" class="mark-all-read">Mark All as Read</a>
            <?php endif; ?>
        </div>
        
        <?php if(empty($notifications)): ?>
            <div style="text-align: center; padding: 40px; color: #666;">
                <i class="fas fa-bell-slash" style="font-size: 48px; margin-bottom: 20px;"></i>
                <h3>No notifications yet</h3>
            </div>
        <?php else: ?>
            <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? '' : 'unread'; ?>">
                    <h3><?php echo htmlspecialchars($notification['title']); ?></h3>
                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                    <small><?php echo time_elapsed_string($notification['created_at']); ?></small>
                    <?php if(!$notification['is_read']): ?>
                        <div style="margin-top: 10px;">
                            <a href="notifications.php?mark_as_read=<?php echo $notification['id']; ?>" style="color: #007bff; text-decoration: none;">
                                Mark as Read
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>