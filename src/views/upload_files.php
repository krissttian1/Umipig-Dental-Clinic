<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

$patient_id = $_SESSION['user_id'];

if (!isset($_FILES['files'])) {
    echo json_encode(["status" => "error", "message" => "No files uploaded."]);
    exit;
}

$upload_dir = 'uploads/';
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

$uploaded_files = [];

foreach ($_FILES['files']['tmp_name'] as $key => $tmp_name) {
    $file_name = basename($_FILES['files']['name'][$key]);
    $target_path = $upload_dir . uniqid() . '_' . $file_name;
    $file_type = strtolower($_FILES['files']['type'][$key]);
    $file_size = $_FILES['files']['size'][$key];

    // Get extension for fallback
    $extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // ✅ Determine category
    $category = "Documents/Files"; // default

    if (strpos($file_type, 'image') !== false || in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
        $category = "Images";
    } elseif (in_array($extension, ['pdf', 'doc', 'docx', 'txt'])) {
        $category = "Documents/Files";
    } elseif (strpos($file_name, 'form') !== false) {
        $category = "Clinic Forms";
    } else {
        $category = "Others";
    }

    if (move_uploaded_file($tmp_name, $target_path)) {
        // Save to database
        $stmt = $conn->prepare("INSERT INTO files (Patient_ID, File_Name, File_Path, File_Type, File_Size, category) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssis", $patient_id, $file_name, $target_path, $file_type, $file_size, $category);
        $stmt->execute();
        $stmt->close();

        $uploaded_files[] = $target_path;
    }
}

echo json_encode([
    "status" => "success",
    "message" => "Files uploaded successfully.",
    "files" => $uploaded_files
]);

$conn->close();
?>
