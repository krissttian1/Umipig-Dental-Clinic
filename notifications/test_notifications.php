<?php
session_start();
require_once 'notifications/notification_functions.php';

// Test creating a notification
if (isset($_SESSION['user_id'])) {
    $success = createNotification(
        $_SESSION['user_id'], 
        'appointment', 
        'New Appointment', 
        'Patient John Doe scheduled an appointment for tomorrow.',
        'medium',
        'appointment_module.php',
        123
    );
    
    if ($success) {
        echo "Notification created successfully!<br>";
        echo "Unread count: " . getUnreadCount($_SESSION['user_id']);
    }
}
?>