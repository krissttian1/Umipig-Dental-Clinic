<?php
header('Content-Type: application/json');

// db_connection.php
$host = 'localhost';
$dbname = 'clinic_db';
$username = 'root';
$password = '';

try {
    $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException $e) {
    // Log this error to a file for production
    error_log("Database connection failed: " . $e->getMessage());
    die(json_encode(['success' => false, 'message' => 'Database connection failed']));
}


// Verify database connection
if (!isset($db)) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Get raw POST data
    $rawData = file_get_contents('php://input');
    
    // Debugging: Log raw input
    error_log("Raw POST data: " . $rawData);

    // Decode JSON data
    $data = json_decode($rawData, true);
    
    // Check if JSON decoding was successful
    if (json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['success' => false, 'message' => 'Invalid JSON data']);
        exit;
    }

    $fileId = $data['fileId'] ?? null;
    $newFileName = $data['newName'] ?? null;

    // Validate parameters
    if ($fileId === null || $newFileName === null) {
        echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
        exit;
    }

    // Trim and validate new file name
    $newFileName = trim($newFileName);
    if (empty($newFileName)) {
        echo json_encode(['success' => false, 'message' => 'File name cannot be empty']);
        exit;
    }

    try {
        // Update database
        $query = "UPDATE files SET File_Name = ? WHERE Files_ID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$newFileName, $fileId]);

        if ($stmt->rowCount() > 0) {
            echo json_encode(['success' => true, 'message' => 'File renamed successfully']);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made. File may not exist or name is the same']);
        }
    } catch (PDOException $e) {
        error_log("Database Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>