<?php
// fetch_patient.php
$conn = new mysqli("localhost", "root", "", "clinic_db");
if ($conn->connect_error) {
    die(json_encode(['error' => 'Database connection failed']));
}

$id = intval($_GET['id']);
$sql = "SELECT * FROM patient_records WHERE id = $id";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $data = $result->fetch_assoc();
    echo json_encode($data);
} else {
    echo json_encode(['error' => 'No patient found']);
}
$conn->close();
?>
