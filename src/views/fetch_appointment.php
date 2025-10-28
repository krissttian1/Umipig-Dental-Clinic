<?php
session_start();
header('Content-Type: application/json');

// 1. Set your clinic's timezone first!
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized access']);
    exit;
}

require_once 'db_connection.php';

// 2. Get and validate dates
$startDate = $_GET['start'] ?? date('Y-m-01'); // Default to current month start
$endDate = $_GET['end'] ?? date('Y-m-t'); // Default to current month end

// 3. Debug output - check what dates are being received
error_log("Fetching appointments between: $startDate and $endDate");

$sql = "
    SELECT 
        a.Appointment_ID as id,
        COALESCE(a.Patient_Name_Custom, u.fullname) as patientName,
        TIMESTAMPDIFF(YEAR, pr.birthdate, CURDATE()) as age,
        COALESCE(u.phone, 'N/A') as phone,
        DATE(a.Appointment_Date) as date_only,  // Added for debugging
        TIME(a.start_time) as start_time,
        TIME(a.end_time) as end_time,
        TIMESTAMPDIFF(MINUTE, a.start_time, a.end_time) as duration,
        a.Service_Type as service,
        COALESCE(d.name, 'Unassigned') as dentist,
        LOWER(a.Appointment_Status) as status
    FROM appointment a
    LEFT JOIN users u ON a.Patient_ID = u.id
    LEFT JOIN patient_records pr ON u.id = pr.user_id
    LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
    WHERE a.Appointment_Status IN ('Pending', 'Confirmed')
";

if ($startDate && $endDate) {
    $sql .= " AND DATE(a.Appointment_Date) BETWEEN ? AND ?";
}

$sql .= " ORDER BY a.Appointment_Date, a.start_time";

$stmt = $conn->prepare($sql);

if ($startDate && $endDate) {
    $stmt->bind_param("ss", $startDate, $endDate);
}

$stmt->execute();
$result = $stmt->get_result();

$appointments = [];
while ($row = $result->fetch_assoc()) {
    // 4. Convert to proper datetime format with timezone
    $dateTime = new DateTime($row['date_only'] . ' ' . $row['start_time']);
    
    $appointments[] = [
        'id' => $row['id'],
        'patientName' => $row['patientName'] ?? 'Unknown Patient',
        'age' => $row['age'] ?? 'N/A',
        'phone' => $row['phone'] ?? 'N/A',
        'date' => $dateTime->format('Y-m-d H:i:s'), // ISO format
        'date_only' => $row['date_only'], // For debugging
        'start_time' => $row['start_time'],
        'end_time' => $row['end_time'],
        'duration' => $row['duration'] ?? 30,
        'service' => $row['service'] ?? 'Unknown Service',
        'dentist' => $row['dentist'] ?? 'Unassigned',
        'status' => strtolower($row['status'])
    ];
}

// 5. Add debug info to response
$response = [
    'meta' => [
        'timezone' => date_default_timezone_get(),
        'date_range' => [$startDate, $endDate],
        'appointment_count' => count($appointments)
    ],
    'appointments' => $appointments
];

echo json_encode($response);
$stmt->close();
$conn->close();