<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Get POST data
$fullname = $_POST['fullname'];
$sex = $_POST['sex'];
$age = $_POST['age'];
$email = $_POST['email'];
$phone = $_POST['phone'];
$recovery_email = $_POST['recovery_email'];
$address = $_POST['address'];

// Update users table
$sql1 = "UPDATE users SET fullname=?, email=?, phone=?, sex=?, recovery_email=? WHERE id=?";
$stmt1 = $conn->prepare($sql1);
$stmt1->bind_param("sssssi", $fullname, $email, $phone, $sex, $recovery_email, $user_id);
$stmt1->execute();

// Update patient_records table
$sql2 = "UPDATE patient_records SET address=?, age=? WHERE user_id=?";
$stmt2 = $conn->prepare($sql2);
$stmt2->bind_param("sii", $address, $age, $user_id);
$stmt2->execute();

$stmt1->close();
$stmt2->close();
$conn->close();

header("Location: user_profile_module.php?success=1");
exit;
?>
