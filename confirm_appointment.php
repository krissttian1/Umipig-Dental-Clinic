<?php
require 'db_connection.php';

if (!isset($_GET['token'])) {
    echo "Invalid or missing token.";
    exit;
}

$token = $_GET['token'];

// Step 1: Check if any appointment with this token exists and is pending
$check_sql = "SELECT COUNT(*) as count 
              FROM appointment 
              WHERE confirmation_token = ? 
              AND Appointment_Status = 'Pending'";
$check_stmt = $conn->prepare($check_sql);
$check_stmt->bind_param("s", $token);
$check_stmt->execute();
$check_result = $check_stmt->get_result();
$check_row = $check_result->fetch_assoc();

if ($check_row['count'] === 0) {
    // Check if already confirmed
    $confirmed_check = "SELECT COUNT(*) as count FROM appointment WHERE confirmation_token = ? AND Appointment_Status = 'Confirmed'";
    $confirmed_stmt = $conn->prepare($confirmed_check);
    $confirmed_stmt->bind_param("s", $token);
    $confirmed_stmt->execute();
    $confirmed_result = $confirmed_stmt->get_result();
    $confirmed_row = $confirmed_result->fetch_assoc();
    
    if ($confirmed_row['count'] > 0) {
        echo "These appointments are already confirmed.";
    } else {
        echo "Invalid or expired confirmation link.";
    }
    exit;
}

// Step 2: Check deadline (using first appointment's deadline as reference)
$deadline_sql = "SELECT confirmation_deadline 
                 FROM appointment 
                 WHERE confirmation_token = ? 
                 LIMIT 1";
$deadline_stmt = $conn->prepare($deadline_sql);
$deadline_stmt->bind_param("s", $token);
$deadline_stmt->execute();
$deadline_result = $deadline_stmt->get_result();
$deadline_row = $deadline_result->fetch_assoc();

$deadline = strtotime($deadline_row['confirmation_deadline']);
$current_time = time();

if ($current_time > $deadline) {
    // Auto-cancel all expired appointments with this token
    $cancel_sql = "UPDATE appointment 
                   SET Appointment_Status = 'Cancelled' 
                   WHERE confirmation_token = ? 
                   AND Appointment_Status = 'Pending'";
    $cancel_stmt = $conn->prepare($cancel_sql);
    $cancel_stmt->bind_param("s", $token);
    $cancel_stmt->execute();
    echo "⛔ Your confirmation link has expired. The appointments were auto-cancelled.";
    exit;
}


// ✅ Step 3: Mark ALL appointments with this token as confirmed
// First, let's check how many appointments should be updated
$count_sql = "SELECT COUNT(*) as total_count, Appointment_ID 
              FROM appointment 
              WHERE confirmation_token = ? 
              AND Appointment_Status = 'Pending'";
$count_stmt = $conn->prepare($count_sql);
$count_stmt->bind_param("s", $token);
$count_stmt->execute();
$count_result = $count_stmt->get_result();

echo "Debug: Found " . $count_result->num_rows . " appointments to confirm<br>";

while ($row = $count_result->fetch_assoc()) {
    echo "Appointment ID: " . $row['Appointment_ID'] . "<br>";
}

$count_stmt->close();

// Now update all of them
$update_sql = "UPDATE appointment
               SET Appointment_Status = 'Confirmed',
                   confirmation_token = NULL,
                   confirmation_deadline = NULL
               WHERE confirmation_token = ?
                 AND Appointment_Status = 'Pending'";

$update_stmt = $conn->prepare($update_sql);
$update_stmt->bind_param("s", $token);

if ($update_stmt->execute()) {
    $affected_rows = $update_stmt->affected_rows;
    echo "✅ Successfully updated $affected_rows appointment(s).<br>";
    
    // Verify the update worked
    $verify_sql = "SELECT COUNT(*) as remaining FROM appointment 
                   WHERE confirmation_token = ? AND Appointment_Status = 'Pending'";
    $verify_stmt = $conn->prepare($verify_sql);
    $verify_stmt->bind_param("s", $token);
    $verify_stmt->execute();
    $verify_result = $verify_stmt->get_result();
    $verify_row = $verify_result->fetch_assoc();
    
    echo "Debug: $verify_row[remaining] appointments still pending after update.<br>";
} else {
    echo "❌ Update failed: " . $update_stmt->error . "<br>";
}

echo "✅ Your appointment(s) have been successfully confirmed. Thank you!";

?>