<?php
header('Content-Type: application/json');
require 'db_connection.php';

if (!isset($_GET['service_id'])) {
    echo json_encode([]);
    exit;
}

$service_id = intval($_GET['service_id']);

$sql = "
    SELECT DISTINCT d.Dentist_ID, d.name, d.specialization
    FROM dentists d
    INNER JOIN dentist_services ds ON ds.dentist_id = d.Dentist_ID
    WHERE ds.service_id = ?
    AND d.Dentist_ID IN (
        SELECT DISTINCT Dentist_ID 
        FROM dentistavailability 
        WHERE available_date >= CURDATE()
    )
    ORDER BY d.name ASC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $service_id);
$stmt->execute();

$result = $stmt->get_result();

$dentists = [];
while ($row = $result->fetch_assoc()) {
    $dentists[] = [
        'Dentist_ID' => (int)$row['Dentist_ID'],
        'name' => $row['name'],
        'specialization' => $row['specialization']
    ];
}

echo json_encode($dentists);

$stmt->close();
$conn->close();
?>