<?php
header('Content-Type: application/json');
require 'db_connection.php';

if (isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("SELECT Service_ID as Service_Type, Dentist_ID FROM appointment WHERE Appointment_ID = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    // Decode the service list
    $service_ids = json_decode($row['Service_Type'], true);
    if (!is_array($service_ids)) {
        $service_ids = explode(',', $row['Service_Type']);
    }

    echo json_encode([
        'service_ids' => $service_ids,
        'dentist_id' => $row['Dentist_ID']
    ]);
}
?>
