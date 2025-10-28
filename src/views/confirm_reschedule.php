<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require 'db_connection.php';


if (!isset($_GET['token'])) {
    echo "⛔ Invalid or missing token.";
    exit;
}

$token = $_GET['token'];

// Step 1: Find the appointment using the reschedule_token
$sql = "SELECT Appointment_ID, Appointment_Status, reschedule_deadline 
        FROM appointment 
        WHERE reschedule_token = ? 
        LIMIT 1";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $token);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    echo "⛔ Invalid or expired reschedule link.";
    exit;
}

$row = $result->fetch_assoc();
$appointment_id = $row['Appointment_ID'];
$status = $row['Appointment_Status'];
$deadline = strtotime($row['reschedule_deadline']);
$current_time = time();

// Step 2: Check if expired
if ($current_time > $deadline) {
    // Auto-cancel if expired and still pending
    $cancel_sql = "UPDATE appointment SET Appointment_Status = 'Cancelled' WHERE Appointment_ID = ?";
    $cancel_stmt = $conn->prepare($cancel_sql);
    $cancel_stmt->bind_param("i", $appointment_id);
    $cancel_stmt->execute();
    echo "⛔ Your reschedule confirmation link has expired. The appointment was automatically cancelled.";
    exit;
}

// Step 3: Confirm the reschedule
$update_sql = "UPDATE appointment 
               SET Appointment_Status = 'Confirmed', 
                   reschedule_token = NULL, 
                   reschedule_deadline = NULL 
               WHERE Appointment_ID = ?";
$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("i", $appointment_id);
$update_stmt->execute();

echo "✅ Your rescheduled appointment has been successfully confirmed. Thank you!";
?>
