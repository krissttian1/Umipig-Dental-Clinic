<?php
require 'db_connection.php';

$patients = [];
$dentists = [];

$p_result = $conn->query("SELECT id, fullname FROM users");
$d_result = $conn->query("SELECT Dentist_ID as id, name FROM dentists");

while ($p = $p_result->fetch_assoc()) {
    $patients[] = $p;
}

while ($d = $d_result->fetch_assoc()) {
    $dentists[] = $d;
}

echo json_encode([
    'patients' => $patients,
    'dentists' => $dentists
]);
?>
