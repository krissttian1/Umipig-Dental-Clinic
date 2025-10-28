<?php
session_start();
header('Content-Type: application/json');
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'db_connection.php';

// 🔒 Ensure a dentist is logged in
if (!isset($_SESSION['dentist_id'])) {
    echo json_encode(['error' => 'Session missing']);
    exit;
}

$dentist_id = $_SESSION['dentist_id'];

// ✅ Fetch this dentist's appointments (Pending, Confirmed, Rescheduled)
$sql = "
  SELECT 
    a.Appointment_ID, 
    a.Appointment_Date, 
    a.start_time AS Appointment_Time, 
    a.Appointment_Status,
    COALESCE(u.fullname, a.Patient_Name_Custom) AS patient_name,
    d.name AS dentist_name
  FROM appointment a
  LEFT JOIN users u ON a.Patient_ID = u.id
  LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
  WHERE a.Dentist_ID = ?
    AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
  ORDER BY a.Appointment_Date DESC, a.start_time ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

$appointments = [];

while ($row = $result->fetch_assoc()) {
    // ✅ Skip incomplete data
    if (empty($row['Appointment_Time']) || empty($row['Appointment_Date'])) continue;

    $appointments[] = [
        'appointment_id' => $row['Appointment_ID'],
        'appointment_date' => $row['Appointment_Date'],
        'appointment_time' => $row['Appointment_Time'],
        'appointment_status' => $row['Appointment_Status'],
        'patient_name' => $row['patient_name'],
        'dentist_name' => $row['dentist_name']
    ];
}

$stmt->close();
echo json_encode($appointments);
?>
