<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

header('Content-Type: application/json');

// Verify CSRF token
$data = json_decode(file_get_contents('php://input'), true);
if (!isset($data['csrf_token']) || $data['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'CSRF token validation failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedFiles = $data['selectedFiles'] ?? [];
    $groupName = trim($data['groupName'] ?? '');

    if (empty($selectedFiles)) {
        echo json_encode(['success' => false, 'message' => 'No files selected']);
        exit;
    }

    if (empty($groupName)) {
        echo json_encode(['success' => false, 'message' => 'Group name cannot be empty']);
        exit;
    }

    try {
        // Validate all files exist and belong to the current user/clinic
        $placeholders = implode(',', array_fill(0, count($selectedFiles), '?'));
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM files WHERE Files_ID IN ($placeholders)");
        $stmt->bind_param(str_repeat('i', count($selectedFiles)), ...$selectedFiles);
        $stmt->execute();
        $result = $stmt->get_result()->fetch_assoc();
        
        if ($result['count'] != count($selectedFiles)) {
            echo json_encode(['success' => false, 'message' => 'One or more files do not exist']);
            exit;
        }

        // Update each file in the database with the group name
        $stmt = $conn->prepare("UPDATE files SET `group` = ? WHERE Files_ID = ?");
        $successCount = 0;

        foreach ($selectedFiles as $fileId) {
            $stmt->bind_param("si", $groupName, $fileId);
            if ($stmt->execute()) {
                $successCount++;
            }
        }

        if ($successCount === count($selectedFiles)) {
            echo json_encode(['success' => true, 'message' => 'Group created successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Some files could not be grouped']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>