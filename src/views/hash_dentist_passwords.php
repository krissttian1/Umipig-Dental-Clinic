<?php
$dentists = [
    1 => 'umipigdentalclinic@gmail.com',
    2 => 'alyssa.quiambao@gmail.com',
    3 => 'ramon.deguzman@gmail.com',
    4 => 'john.jimenez@gmail.com',
    5 => 'lester.cruz@gmail.com'
];

$conn = new mysqli("localhost", "root", "", "clinic_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

foreach ($dentists as $id => $email) {
    $hashed = password_hash('dentist', PASSWORD_DEFAULT);
    $stmt = $conn->prepare("UPDATE dentists SET password = ? WHERE Dentist_ID = ?");
    $stmt->bind_param("si", $hashed, $id);
    $stmt->execute();
    $stmt->close();
}

echo "✅ Dentist passwords updated securely.";
?>
