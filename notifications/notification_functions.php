<?php
// REMOVE this line: require_once 'db_connection.php';
require_once 'notification_config.php';

/**
 * Create a new notification
 */
function createNotification($user_id, $type, $title, $message, $priority = 'medium', $link = null, $related_id = null, $action_type = null) {
    global $conn;
    
    if (!isset($conn)) {
        error_log("Database connection not available for notifications");
        return false;
    }
    
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, priority, link, related_id, action_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isssssis", $user_id, $type, $title, $message, $priority, $link, $related_id, $action_type);
    return $stmt->execute();
}

/**
 * Create notification for ALL admin users
 */
function createAdminNotification($type, $title, $message, $priority = 'medium', $link = null, $related_id = null, $action_type = null) {
    global $conn;
    
    // Get all admin users
    $admin_sql = "SELECT id FROM users WHERE role = 'admin'";
    $admin_result = $conn->query($admin_sql);
    
    $success_count = 0;
    while ($admin = $admin_result->fetch_assoc()) {
        $stmt = $conn->prepare("INSERT INTO notifications (user_id, type, title, message, priority, link, related_id, action_type) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssssis", $admin['id'], $type, $title, $message, $priority, $link, $related_id, $action_type);
        if ($stmt->execute()) {
            $success_count++;
        }
        $stmt->close();
    }
    
    return $success_count > 0;
}

/**
 * Get unread notification count for a user
 */
function getUnreadCount($user_id) {
    global $conn;
    
    if (!isset($conn)) {
        error_log("Database connection not available for notifications");
        return 0;
    }
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    return $result->fetch_assoc()['count'];
}

/**
 * Get recent notifications for a user
 */
function getRecentNotifications($user_id, $limit = 7) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT ?");
    $stmt->bind_param("ii", $user_id, $limit);
    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

/**
 * Mark notification as read
 */
function markAsRead($notification_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ?");
    $stmt->bind_param("i", $notification_id);
    return $stmt->execute();
}

/**
 * Mark all notifications as read for a user
 */
function markAllAsRead($user_id) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    return $stmt->execute();
}

/**
 * Helper function to format time - SIMPLE VERSION
 */
function time_elapsed_string($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 3600) {
        return 'Recently';
    } elseif ($diff < 86400) {
        return 'Today';
    } elseif ($diff < 172800) {
        return 'Yesterday';
    } else {
        return date('M j, Y', $time);
    }
}

/**
 * Check if notification requires action buttons
 */
function hasActionButtons($notification) {
    return isset($notification['action_type']) && $notification['action_type'] === 'specialization_request';
}
?>