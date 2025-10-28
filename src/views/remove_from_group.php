<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    $fileId = $data['fileId'] ?? null;
    
    if (!$fileId) {
        echo json_encode(['success' => false, 'message' => 'File ID is required']);
        exit;
    }
    
    try {
        $stmt = $conn->prepare("UPDATE files SET `group` = NULL WHERE Files_ID = ?");
        $stmt->bind_param("i", $fileId);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to remove from group']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>