<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle approve/reject actions
// Handle approve/reject actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_request'])) {
        $request_id = $_POST['request_id'];
        
        // Get the request details
        $stmt = $conn->prepare("SELECT * FROM specialization_requests WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        
        if ($request) {
            // Update dentist's specialization
            $dentist_stmt = $conn->prepare("SELECT specialization FROM dentists WHERE Dentist_ID = ?");
            $dentist_stmt->bind_param("i", $request['dentist_id']);
            $dentist_stmt->execute();
            $dentist_result = $dentist_stmt->get_result();
            $dentist = $dentist_result->fetch_assoc();
            
            $current_specialization = $dentist['specialization'] ?? '';
            
            // Check if the service is already in specialization to avoid duplicates
            $current_specs = $current_specialization ? array_map('trim', explode(',', $current_specialization)) : [];
            if (!in_array($request['requested_service'], $current_specs)) {
                $new_specialization = $current_specialization ? $current_specialization . ', ' . $request['requested_service'] : $request['requested_service'];
                
                $update_stmt = $conn->prepare("UPDATE dentists SET specialization = ? WHERE Dentist_ID = ?");
                $update_stmt->bind_param("si", $new_specialization, $request['dentist_id']);
                $update_stmt->execute();
            }
            
            // Update request status and mark as unread for dentist
            $status_stmt = $conn->prepare("UPDATE specialization_requests SET status = 'approved', processed_date = NOW(), dentist_viewed = 0 WHERE request_id = ?");
            $status_stmt->bind_param("i", $request_id);
            $status_stmt->execute();
        }
        
    } elseif (isset($_POST['reject_request'])) {
        $request_id = $_POST['request_id'];
        $admin_notes = $_POST['admin_notes'] ?? '';
        
        // Update request status to rejected and mark as unread for dentist
        $stmt = $conn->prepare("UPDATE specialization_requests SET status = 'rejected', admin_notes = ?, processed_date = NOW(), dentist_viewed = 0 WHERE request_id = ?");
        $stmt->bind_param("si", $admin_notes, $request_id);
        $stmt->execute();
    }
    
    header("Location: admin_specialization_requests.php");
    exit;
}



// Fetch all pending requests
$stmt = $conn->prepare("SELECT * FROM specialization_requests WHERE status = 'pending' ORDER BY request_date DESC");
$stmt->execute();
$result = $stmt->get_result();
$pending_requests = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Specialization Requests - Admin</title>
    <style>
        .request-card {
            border: 1px solid #ddd;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
        .approve-btn { background: green; color: white; padding: 5px 10px; border: none; cursor: pointer; }
        .reject-btn { background: red; color: white; padding: 5px 10px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h1>Specialization Requests</h1>
    
    <?php foreach ($pending_requests as $request): ?>
    <div class="request-card">
        <h3>Request from: <?php echo htmlspecialchars($request['dentist_name']); ?></h3>
        <p>Service: <?php echo htmlspecialchars($request['requested_service']); ?></p>
        <p>Requested: <?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?></p>
        
        <form method="POST" style="display: inline;">
            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
            <button type="submit" name="approve_request" class="approve-btn">Approve</button>
        </form>
        
        <form method="POST" style="display: inline;">
            <input type="hidden" name="request_id" value="<?php echo $request['request_id']; ?>">
            <input type="text" name="admin_notes" placeholder="Reason for rejection (optional)">
            <button type="submit" name="reject_request" class="reject-btn">Reject</button>
        </form>
    </div>
    <?php endforeach; ?>
</body>
</html>