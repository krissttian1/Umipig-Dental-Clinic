<?php
session_start();
require 'db_connection.php';

// Error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

header('Content-Type: application/json');

try {
    // Check admin privileges
    if (!isset($_SESSION['role'])) {
        throw new Exception('Session not initialized');
    }
    
    if ($_SESSION['role'] !== 'admin') {
        throw new Exception('Unauthorized access');
    }

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Invalid request method');
    }

    // Get and validate input
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_DEFAULT);
    $new_status = trim($new_status);

    if (!$appointment_id) {
        throw new Exception('Invalid appointment ID');
    }

    if (!in_array($new_status, ['Pending', 'Confirmed', 'Completed', 'Cancelled'])) {
        throw new Exception('Invalid status value');
    }

    // Update appointment status
    $stmt = $conn->prepare("UPDATE appointment SET Appointment_Status = ? WHERE Appointment_ID = ?");
    if (!$stmt) {
        throw new Exception('Prepare failed: ' . $conn->error);
    }

    if (!$stmt->bind_param("si", $new_status, $appointment_id)) {
        throw new Exception('Bind failed: ' . $stmt->error);
    }

    if (!$stmt->execute()) {
        throw new Exception('Execute failed: ' . $stmt->error);
    }

    // If updating to Completed, record completion time
    if ($new_status === 'Completed') {
        $complete_stmt = $conn->prepare("UPDATE appointment SET completed_at = NOW() WHERE Appointment_ID = ?");
        $complete_stmt->bind_param("i", $appointment_id);
        $complete_stmt->execute();
        $complete_stmt->close();
    }

    echo json_encode([
        'success' => true,
        'message' => 'Status updated successfully',
        'status' => $new_status
    ]);

} catch (Exception $e) {
    error_log('Appointment Status Error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}

$conn->close();
?>