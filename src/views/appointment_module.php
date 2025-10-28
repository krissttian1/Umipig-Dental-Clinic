<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'reschedule') {
    error_log("RESCHEDULE REQUEST RECEIVED: " . date('Y-m-d H:i:s'));
    error_log("POST data: " . print_r($_POST, true));
}


session_start();
date_default_timezone_set('Asia/Manila');


// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (!isset($_SESSION['role'])) {
    header("Location: home.php");
    exit;
}

function getServiceNamesFromIDs($conn, $service_ids) {
    $clean_ids = array_map('intval', $service_ids);
    $service_names = [];

    if (!empty($clean_ids)) {
        $ids_string = implode(',', $clean_ids);
        $query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
        $result = $conn->query($query);
        while ($row = $result->fetch_assoc()) {
            $service_names[] = $row['service_name'];
        }
    }

    return $service_names;
}

function checkScheduleConflict($conn, $dentist_id, $date, $start_time, $end_time, $exclude_appointment_id = null) {
    $conflict_sql = "SELECT COUNT(*) as conflict_count FROM appointment 
                    WHERE Dentist_ID = ? 
                    AND Appointment_Date = ? 
                    AND ((start_time < ? AND end_time > ?) OR 
                         (start_time >= ? AND start_time < ?) OR 
                         (end_time > ? AND end_time <= ?)) 
                    AND Appointment_Status NOT IN ('Cancelled', 'Completed')";

    if ($exclude_appointment_id) {
        $conflict_sql .= " AND Appointment_ID != ?";
    }

    $stmt = $conn->prepare($conflict_sql);

    if ($exclude_appointment_id) {
        $stmt->bind_param("isssssssi", $dentist_id, $date, $end_time, $start_time, 
                         $start_time, $end_time, $start_time, $end_time, $exclude_appointment_id);
    } else {
        $stmt->bind_param("isssssss", $dentist_id, $date, $end_time, $start_time, 
                         $start_time, $end_time, $start_time, $end_time);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    $stmt->close();

    return $row['conflict_count'] > 0;
}

// Cancel expired rescheduled appointments
$now = date('Y-m-d H:i:s');
$cancel_sql = "UPDATE appointment 
              SET Appointment_Status = 'Cancelled' 
              WHERE Appointment_Status = 'Pending' 
              AND reschedule_deadline IS NOT NULL 
              AND reschedule_deadline < ?";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("s", $now);
$cancel_stmt->execute();
$cancel_stmt->close();

// Fetch dentists for dropdown
$dentists_result = $conn->query("SELECT Dentist_ID, name FROM dentists ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {
    // UPDATE STATUS
    if ($_POST['form_type'] === 'update') {
        $id = (int)$_POST['appointment_id'];
        $status = $_POST['Appointment_Status'];
        
        $stmt = $conn->prepare("UPDATE appointment SET Appointment_Status = ? WHERE Appointment_ID = ?");
        $stmt->bind_param("si", $status, $id);
        
        if ($stmt->execute()) {
            // ===== NOTIFICATION TRIGGER FOR STATUS CHANGE =====
            // Get appointment details for notification
            $appointment_stmt = $conn->prepare("SELECT Patient_Name_Custom, Appointment_Date, start_time FROM appointment WHERE Appointment_ID = ?");
            $appointment_stmt->bind_param("i", $id);
            $appointment_stmt->execute();
            $appointment_result = $appointment_stmt->get_result();
            
            if ($appointment_row = $appointment_result->fetch_assoc()) {
                $patient_name = $appointment_row['Patient_Name_Custom'];
                $appointment_date = $appointment_row['Appointment_Date'];
                $appointment_time = $appointment_row['start_time'];
                
                createAdminNotification(
                    'appointment',
                    'Appointment ' . $status,
                    'Appointment with ' . $patient_name . ' on ' . $appointment_date . ' at ' . $appointment_time . ' has been ' . strtolower($status) . ' by ' . $_SESSION['username'] . '.',
                    ($status == 'Cancelled' ? 'high' : 'medium'),
                    'appointment_module.php',
                    $id
                );
            }
            $appointment_stmt->close();
            // ===== END NOTIFICATION TRIGGER =====
            
            $_SESSION['message'] = "Appointment status updated successfully";
        } else {
            $_SESSION['message'] = "Error updating appointment: " . $stmt->error;
        }
        $stmt->close();
        
        header("Location: appointment_module.php");
        exit;
    }


elseif ($_POST['form_type'] === 'add') {
    // Start with JSON header
    header('Content-Type: application/json');
    
    try {
        // 1. Validate required fields
        $requiredFields = [
            'Patient_Name_Custom',
            'doctor',
            'preferred_date',
            'preferred_time',
            'total_duration',
            'status',
            'service'
        ];
        
        foreach ($requiredFields as $field) {
            if (!isset($_POST[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }

        // 2. Get and validate services
        $services = $_POST['service'];
        if (!is_array($services)) {
            $services = explode(',', $services);
        }
        
        if (count($services) > 3) {
            throw new Exception('Maximum 3 services allowed');
        }
        
        $serviceIds = array_map('intval', $services);
        $serviceJson = json_encode($serviceIds);

        // 3. Prepare time values
        $startTime = $_POST['preferred_time'];
        $totalDuration = (int)$_POST['total_duration'];
        
        // Format time properly (HH:MM:SS)
        if (strlen($startTime) === 5) { // If format is HH:MM
            $startTime .= ':00';
        }
        
        // Calculate end time
        $start = DateTime::createFromFormat('H:i:s', $startTime);
        if (!$start) {
            throw new Exception('Invalid time format');
        }
        
        $end = clone $start;
        $end->modify("+{$totalDuration} minutes");
        $endTime = $end->format('H:i:s');

        // 4. Validate clinic hours
        $startMinutes = $start->format('H') * 60 + $start->format('i');
        $endMinutes = $end->format('H') * 60 + $end->format('i');
        
        // Clinic hours: 9:00 AM (540) to 5:00 PM (1020)
        if ($startMinutes < 540 || $endMinutes > 1020) {
            throw new Exception('Appointment must be scheduled between 9:00 AM and 5:00 PM');
        }

        // 5. Check for conflicts
        if (checkScheduleConflict($conn, $_POST['doctor'], $_POST['preferred_date'], $startTime, $endTime)) {
            throw new Exception('The selected time conflicts with another appointment');
        }

        // 6. Insert into database
        $stmt = $conn->prepare("INSERT INTO appointment (
            start_time, end_time, Appointment_Date, Patient_Name_Custom,
            Dentist_ID, Service_Type, Appointment_Status, Admin_ID
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        $adminId = $_SESSION['user_id'] ?? 1; // Default to 1 if not set
        
        $stmt->bind_param("ssssissi",
            $startTime,
            $endTime,
            $_POST['preferred_date'],
            $_POST['Patient_Name_Custom'],
            $_POST['doctor'],
            $serviceJson,
            $_POST['status'],
            $adminId
        );

        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->error);
        }

        $new_appointment_id = $conn->insert_id;

        // ===== NOTIFICATION TRIGGER FOR NEW APPOINTMENT =====
        createAdminNotification(
            'appointment',
            'New Appointment Created',
            'New appointment for ' . $_POST['Patient_Name_Custom'] . ' on ' . $_POST['preferred_date'] . ' at ' . $startTime . ' has been created by ' . $_SESSION['username'] . '.',
            'medium',
            'appointment_module.php',
            $new_appointment_id
        );
        // ===== END NOTIFICATION TRIGGER =====

        // ========== FIXED: Remove booked slots from dentistavailability table for ALL dentists ==========
        try {
            // Get ALL selected dentists from the multi-service selection
            $allDentistIds = [];
            
            // Start with the main dentist
            $mainDentistId = (int)$_POST['doctor'];
            $allDentistIds[] = $mainDentistId;
            
            // Add any additional dentists from multi-service selection
            if (isset($_POST['selected_dentists']) && is_array($_POST['selected_dentists'])) {
                foreach ($_POST['selected_dentists'] as $dentistId) {
                    $cleanDentistId = (int)$dentistId;
                    if ($cleanDentistId > 0 && $cleanDentistId !== $mainDentistId && !in_array($cleanDentistId, $allDentistIds)) {
                        $allDentistIds[] = $cleanDentistId;
                    }
                }
            }
            
            // Remove availability for EACH selected dentist
            $remove_availability_sql = "
                DELETE FROM dentistavailability 
                WHERE Dentist_ID = ? 
                AND available_date = ? 
                AND available_time BETWEEN ? AND ?
            ";
            $remove_stmt = $conn->prepare($remove_availability_sql);
            
            foreach ($allDentistIds as $dentistId) {
                $remove_stmt->bind_param("isss", $dentistId, $_POST['preferred_date'], $startTime, $endTime);
                $remove_stmt->execute();
                
                error_log("ADMIN APPOINTMENT: Removed availability for Dentist ID: $dentistId on {$_POST['preferred_date']} from $startTime to $endTime");
            }
            
            $remove_stmt->close();
            
        } catch (Exception $e) {
            error_log("Admin availability update failed: " . $e->getMessage());
        }

        // Return JSON success response
        echo json_encode([
            'status' => 'success',
            'message' => 'Appointment added successfully',
            'appointment_id' => $new_appointment_id
        ]);
        exit;

    } catch (Exception $e) {
        // Return JSON error response
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
        exit;
    }
}


elseif ($_POST['form_type'] === 'reschedule') {
    header('Content-Type: application/json');
    
    try {
        // Validate required fields
        if (!isset($_POST['appointment_id']) || !isset($_POST['new_date']) || !isset($_POST['new_time']) || !isset($_POST['new_dentist'])) {
            throw new Exception('Missing required fields for rescheduling');
        }

        $id = (int)$_POST['appointment_id'];
        $newDate = $_POST['new_date'];
        $newTime = $_POST['new_time'];
        $newDentist = (int)$_POST['new_dentist'];

        // Get original appointment details
        $stmt = $conn->prepare("SELECT Service_ID as Service_Type, Patient_ID, Dentist_ID, Patient_Name_Custom FROM appointment WHERE Appointment_ID = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            throw new Exception('Appointment not found');
        }

        $appointment = $result->fetch_assoc();
        $stmt->close();

        // Calculate total duration
        $service_ids = json_decode($appointment['Service_Type'], true);
        if (!is_array($service_ids)) {
            $service_ids = explode(',', $appointment['Service_Type']);
        }
        $service_ids = array_map('intval', $service_ids);

        $total_duration = 0;
        if (!empty($service_ids)) {
            $ids_string = implode(',', $service_ids);
            $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $total_duration += (int)$row['service_duration'];
            }
        }

        // Format time properly
        if (strlen($newTime) === 5) {
            $newTime .= ':00';
        }

        $start = DateTime::createFromFormat('H:i:s', $newTime);
        if (!$start) {
            throw new Exception('Invalid time format');
        }

        $end = clone $start;
        $end->modify("+{$total_duration} minutes");
        $end_time = $end->format('H:i:s');

        // Check for conflicts with the NEW dentist
        if (checkScheduleConflict($conn, $newDentist, $newDate, $newTime, $end_time, $id)) {
            throw new Exception('The selected time conflicts with another appointment for this dentist');
        }

        // Get patient and NEW dentist details for email
        $stmt = $conn->prepare("
            SELECT u.email, u.fullname, d.name AS dentist_name, d.specialization
            FROM users u
            JOIN dentists d ON d.Dentist_ID = ?
            WHERE u.id = ?");
        $stmt->bind_param("ii", $newDentist, $appointment['Patient_ID']);
        $stmt->execute();
        $result = $stmt->get_result();
        $appointmentData = $result->fetch_assoc();
        $stmt->close();

        $patientName = $appointment['Patient_Name_Custom'] ?? ($appointmentData['fullname'] ?? 'Unknown Patient');

        // Get service names for email
        $service_names = getServiceNamesFromIDs($conn, $service_ids);
        $formatted_services = implode(', ', $service_names);

        // ========== FIXED LOGIC: Determine status based on email availability ==========
        if (!$appointmentData || empty($appointmentData['email'])) {
            // No email provided - auto-confirm the reschedule
            $status = 'Confirmed';
            $reschedule_token = NULL;
            $reschedule_deadline = NULL;
        } else {
            // Email provided - require confirmation
            $status = 'Pending';
            $reschedule_token = bin2hex(random_bytes(16));
            $reschedule_deadline = date('Y-m-d H:i:s', strtotime('+15 minutes'));
        }

        // Update appointment with NEW dentist and other details
        $stmt = $conn->prepare("UPDATE appointment 
            SET Appointment_Date = ?, start_time = ?, end_time = ?, 
                reschedule_token = ?, reschedule_deadline = ?, Appointment_Status = ?,
                Dentist_ID = ?
            WHERE Appointment_ID = ?");
        $stmt->bind_param("ssssssii", 
            $newDate, $newTime, $end_time, 
            $reschedule_token, $reschedule_deadline, $status, 
            $newDentist, $id);

        if (!$stmt->execute()) {
            throw new Exception('Database error: ' . $stmt->execute());
        }
        $stmt->close();

        // ===== NOTIFICATION TRIGGER FOR RESCHEDULE =====
        createAdminNotification(
            'appointment',
            'Appointment Rescheduled',
            'Appointment for ' . $patientName . ' has been rescheduled to ' . $newDate . ' at ' . $newTime . ' by ' . $_SESSION['username'] . '.',
            'medium',
            'appointment_module.php',
            $id
        );
        // ===== END NOTIFICATION TRIGGER =====

        // ========== FIXED: Remove booked slots from dentistavailability table for rescheduled appointments ==========
        try {
            // Get the NEW dentist for the rescheduled appointment
            $newDentistId = (int)$_POST['new_dentist'];
            
            $remove_availability_sql = "
                DELETE FROM dentistavailability 
                WHERE Dentist_ID = ? 
                AND available_date = ? 
                AND available_time BETWEEN ? AND ?
            ";
            $remove_stmt = $conn->prepare($remove_availability_sql);
            
            $remove_stmt->bind_param("isss", $newDentistId, $newDate, $newTime, $end_time);
            $remove_stmt->execute();
            $remove_stmt->close();
            
            error_log("RESCHEDULE: Removed availability for Dentist ID: $newDentistId on $newDate from $newTime to $end_time");
            
        } catch (Exception $e) {
            error_log("Reschedule availability update failed: " . $e->getMessage());
        }

        // Send confirmation email if we have patient details
        if ($appointmentData && !empty($appointmentData['email'])) {
            $mail = new PHPMailer(true);
            try {
                $mail->isSMTP();
                $mail->Host = 'smtp.gmail.com';
                $mail->SMTPAuth = true;
                $mail->Username = 'kristianespinase01@gmail.com';
                $mail->Password = 'upin izwz iker gbou';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;

                $mail->setFrom('UmipigDentalClinic@gmail.com', 'Umipig Dental Clinic');
                $mail->addAddress($appointmentData['email'], $appointmentData['fullname']);

                $mail->isHTML(true);
                $mail->Subject = 'Appointment Rescheduled - Umipig Dental Clinic';

                $reschedule_link = "http://localhost/UmipigDentalClinic/confirm_reschedule.php?token={$reschedule_token}";

                // Build the services breakdown
                $services_breakdown = "";
                if (count($service_names) === 1) {
                    // Single service format
                    $services_breakdown = "
                        <p><strong>Service:</strong> {$formatted_services}<br>
                        <strong>Date:</strong> {$newDate}<br>
                        <strong>Time:</strong> {$newTime} - {$end_time}<br>
                        <strong>Dentist:</strong> {$appointmentData['dentist_name']} ({$appointmentData['specialization']})</p>
                    ";
                } else {
                    // Multiple services format
                    $services_breakdown = "<p><strong>Services:</strong> {$formatted_services}</p>";
                    
                    foreach ($service_names as $service) {
                        $services_breakdown .= "
                            <div style='margin-top: 15px;'>
                                <h3 style='margin-bottom: 5px;'>" . strtoupper($service) . "</h3>
                                <p><strong>Date:</strong> {$newDate}<br>
                                <strong>Time:</strong> {$newTime} - {$end_time}<br>
                                <strong>Dentist:</strong> {$appointmentData['dentist_name']} ({$appointmentData['specialization']})</p>
                            </div>
                        ";
                    }
                }

                $mail->Body = "
                    <h2>Appointment Rescheduled</h2>
                    <p>Dear {$patientName},</p>
                    <p>Your appointment has been rescheduled with Umipig Dental Clinic.</p>
                    {$services_breakdown}
                    <p>
                        ✅ <strong>To confirm your rescheduled appointment, please click the link below:</strong><br>
                        <a href='{$reschedule_link}'>{$reschedule_link}</a><br><br>
                        ⚠️ <strong>Note:</strong> This rescheduled appointment will be automatically cancelled if not confirmed within EXACTLY <span style=\"color:red;\">15 minutes</span>.
                    </p>
                    <p>Best regards,<br>Umipig Dental Clinic Team</p>
                ";

                $mail->send();
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Appointment rescheduled successfully.'
                ]);
            } catch (Exception $e) {
                echo json_encode([
                    'status' => 'success',
                    'message' => 'Appointment rescheduled, but email could not be sent. Mailer Error: ' . $e->getMessage()
                ]);
            }
        } else {
            echo json_encode([
                'status' => 'success',
                'message' => 'Appointment rescheduled successfully.'
            ]);
        }

    } catch (Exception $e) {
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
}


$statusColors = [
    'Completed' => '#2ecc71',
    'Confirmed' => '#3498db', 
    'Pending' => '#f39c12',
    'Cancelled' => '#e74c3c',
    'No Show' => '#9b59b6'  // Added No Show with purple color
];


$sql = "SELECT 
    a.Appointment_ID,
    a.start_time,
    a.end_time,
    a.Appointment_Date,
    a.Service_ID as Service_Type,
    a.Appointment_Status,
    a.Patient_Name_Custom,
    a.Patient_ID,
    a.Dentist_ID,
    a.reschedule_token,
    a.reschedule_deadline,
    a.is_confirmed,
    u.fullname AS user_fullname,
    d.name AS dentist_name
FROM appointment a
LEFT JOIN users u ON a.Patient_ID = u.id
LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
WHERE a.Appointment_Status NOT IN ('Completed', 'Cancelled', 'No Show')
ORDER BY 
    a.Appointment_Date ASC,
    COALESCE(u.fullname, a.Patient_Name_Custom) ASC,
    a.start_time ASC,
    d.name ASC,
    a.Appointment_Status DESC";

$result = $conn->query($sql);
?>



<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dental Clinic Appointment System</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="appointment_module.css" />
  <link rel="stylesheet" href="notifications/notification_style.css">

  <style>
    table select, table input[type="time"], table input[type="date"], table input[type="text"] {
      width: 70%;
      box-sizing: border-box;
      border: 1px solid #ccc;
      padding: 4px;
      font-size: 10px;
      font-weight: 600;
    }
    .update-btn {
      cursor: pointer;
      padding: 6px 10px;
      background-color: green;
      color: white;
      border: none;
      border-radius: 4px;
    }
    .update-btn:disabled {
      background-color: #ccc;
      cursor: not-allowed;
    }
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      z-index: 100;
      left: 0; top: 40px;
      width: 100%; height: 100%;
      overflow: auto;
      background-color: rgba(0,0,0,0.5);
      justify-content: center;
      align-items: center;
    }
    .modal-content {
      background: white;
      padding: 20px;
      border-radius: 6px;
      width: 300px;
      max-height: 80vh;
      overflow-y: auto;
      margin-bottom: 50px;
    }
    .modal-content label {
      display: block;
      margin-top: 20px;
    }
    .modal-content input, .modal-content select {
      width: 100%;
      padding: 6px;
      margin-top: 4px;
    }
    .modal-content button {
      margin-top: 15px;
      padding: 8px 12px;
      background-color: #28a745;
      color: white;
      border: none;
      border-radius: 4px;
      cursor: pointer;
    }
    .modal-content .close-btn {
      background-color: #dc3545;
      margin-left: 10px;
    }
    #conflictWarning {
      color: red;
      margin-top: 10px;
      display: none;
    }
     .time-options {
      display: flex;
      flex-wrap: wrap;
      gap: 5px;
      margin-top: 5px;
    }
    .time-option {
      padding: 5px 10px;
      background: #e0e0e0;
      border-radius: 4px;
      cursor: pointer;
    }
    .time-option:hover {
      background: #d0d0d0;
    }
    .time-option.selected {
      background: #4CAF50;
      color: white;
    }
      
    .refresh-indicator {
      position: fixed;
      bottom: 20px;
      right: 20px;
      background: rgba(0,0,0,0.7);
      color: white;
      padding: 5px 10px;
      border-radius: 4px;
      font-size: 8px;
      z-index: 1000;
      display: none;
    }

    .status-no-show {
    color: #9b59b6;
    font-weight: bold;
    }


  </style>
</head>
<body>

<header>
    <div class="logo-container">
        <div class="logo-circle">
            <img src="images/UmipigDentalClinic_Logo.jpg" alt="Umipig Dental Clinic">
        </div>

        <div class="clinic-info">
            <h1>Umipig Dental Clinic</h1>
            <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
        </div>
    </div>

    <div class="header-right">
    <?php if (isset($_SESSION['username'])): ?>
		          <!-- Notification Icon -->
            <div class="notification-container">
                <a href="javascript:void(0)" class="notification-icon" title="Notifications" style="color: royalblue;">
                    <i class="fas fa-bell"></i>
                    <?php
                    $unread_count = getUnreadCount($user_id);
                    if($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notification-dropdown-container"></div>
            </div>

            <!-- Profile Icon -->
            <a href="admin_profile_module.php" class="profile-icon" title="Profile" style="color: royalblue;">
                <i class="fas fa-user-circle"></i>
            </a>

        <span class="welcome-text">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! &nbsp;
            &nbsp;|&nbsp;
            <a href="logout.php" class="auth-link">Logout</a>
        </span>
    <?php else: ?>
        <a href="register.php" class="auth-link">Register</a>
        <span>|</span>
        <a href="login.php" class="auth-link">Login</a>
    <?php endif; ?>
    </div>
</header>

    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="menu-icon">☰</div>
            <ul class="nav-menu">
               <li class="nav-item">
                     <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
               </li>
                <li class="nav-item">
                     <a href="appointment_module.php" class="nav-link">Appointment Management</a>
               </li>
                <li class="nav-item">
                     <a href="billing_module.php" class="nav-link">Billing</a>
               </li>
               <li class="nav-item">
                     <a href="patient_records.php" class="nav-link">Patient Records</a>
               </li>
               <li class="nav-item">
                     <a href="reports_module.php" class="nav-link active">Reports</a>
               <li class="nav-item">
                     <a href="documents_files_module.php" class="nav-link active">Documents / Files</a>
               </li>
               <li class="nav-item">
                     <a href="calendar_module.php" class="nav-link active">Calendar</a>
               </li>
               <li class="nav-item">
                     <a href="tasks_reminders_module.php" class="nav-link active">Tasks & Reminders</a>
               <li class="nav-item">
                     <a href="system_settings_module.php" class="nav-link active">System Settings</a>
               </li>
        </div>


<div class="container">
<div class="header">
  <h1>Appointments</h1>
  <button id="rescheduleAppointmentBtn">Reschedule Appointment</button>
  <button id="newAppointmentBtn">Add Appointment</button>
</div>

<?php if (isset($_SESSION['message'])): ?>
    <div id="message-container">
        <p><?= htmlspecialchars($_SESSION['message']); ?></p>
    </div>
    <?php unset($_SESSION['message']); ?>
<?php endif; ?>

<div class="appointment-table-container">
  <table id="appointmentTable">
    <thead>
      <tr>
        <th>START</th>
        <th>END</th>
        <th>DATE</th>
        <th>PATIENT</th>
        <th>DENTIST</th>
        <th>PROCEDURE</th>
        <th>STATUS</th>
        <th>ACTIONS</th>
      </tr>
    </thead>
    <tbody id="appointmentList">
      <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
          <tr>
            <form action="appointment_module.php" method="POST">
            <input type="hidden" name="form_type" value="update">
            <input type="hidden" name="appointment_id" value="<?= $row['Appointment_ID'] ?>">

<td>
  <?php
    $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
    $start_display = $start ? $start->format('g:i A') : 'N/A';
    echo htmlspecialchars($start_display);
  ?>
  <input type="hidden" name="start_time" value="<?= htmlspecialchars($row['start_time']) ?>">
</td>

<td>
  <?php
    $end = DateTime::createFromFormat('H:i:s', $row['end_time']);
    echo $end ? htmlspecialchars($end->format('g:i A')) : 'N/A';
    $computed_end_time = $row['end_time'];
  ?>
  <input type="hidden" name="appointment_end_time" value="<?= htmlspecialchars($computed_end_time) ?>">
</td>

<td>
  <?= htmlspecialchars($row['Appointment_Date']) ?>
  <input type="hidden" name="appointment_date" value="<?= htmlspecialchars($row['Appointment_Date']) ?>">
</td>

<td>
<?= !empty($row['user_fullname']) ? htmlspecialchars($row['user_fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>
<input type="hidden" name="Patient_Name_Custom" value="<?= !empty($row['user_fullname']) ? htmlspecialchars($row['user_fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>">
</td>

<td>
  <?= htmlspecialchars($row['dentist_name']) ?>
  <input type="hidden" name="dentist_id" value="<?= htmlspecialchars($row['Dentist_ID']) ?>">
</td>

<td>
  <?php
    // Parse the stored service IDs
    $service_ids = json_decode($row['Service_Type'], true);
    if (!is_array($service_ids)) {
        $service_ids = explode(',', $row['Service_Type']);
    }

    $clean_ids = array_map('intval', $service_ids);
    $service_names = [];

    if (!empty($clean_ids)) {
        $ids_string = implode(',', $clean_ids);
        $query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
        $result_services = $conn->query($query);

        while ($service = $result_services->fetch_assoc()) {
            $service_names[] = $service['service_name'];
        }
    }

    // Display the names as comma-separated
    echo htmlspecialchars(implode(', ', $service_names));
  ?>
  <input type="hidden" name="procedure" value="<?= htmlspecialchars($row['Service_Type']) ?>">
</td>

  <input type="hidden" name="form_type" value="update">
  <input type="hidden" name="appointment_id" value="<?= $row['Appointment_ID'] ?>">

<td>
    <select name="Appointment_Status" required>
        <?php 
        $statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled', 'No Show'];
        foreach ($statuses as $status_option): 
            $selected = ($status_option === $row['Appointment_Status']) ? "selected" : "";
        ?>
            <option value="<?= $status_option ?>" <?= $selected ?>><?= $status_option ?></option>
        <?php endforeach; ?>
    </select>
</td>

  <td>
    <button type="submit" class="update-button">Update</button>
  </td>
        </form>
          </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No appointments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- FOR COMPLETED APPOINTMENTS TO DISPLAY -->
<?php
// Get selected date range for completed appointments (default: today only)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate date range (max 3 months apart)
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    
    if ($interval->m > 3 || $interval->y > 0 || $interval->invert) {
        // If range is invalid (more than 3 months or end before start), reset to today
        $startDate = $endDate = date('Y-m-d');
    }
}

// Apply date range filter inside the SQL query
$query = "SELECT a.*, u.fullname, d.name 
          FROM appointment a 
          LEFT JOIN users u ON a.Patient_ID = u.id 
          LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID 
          WHERE a.Appointment_Status = 'Completed' 
          AND a.Appointment_Date BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
  <div class="header">
    <h2 class="completed_appointments">Completed Appointments</h2>
  </div>

  <div class="appointment-table-container">
    <!-- DATE RANGE FILTER FORM for Completed Appointments -->
    <form method="GET" class="date-filter-form">
      <label for="start_date" style="font-size: 12px;">From:</label>
      <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
      
      <label for="end_date" style="font-size: 12px;">To:</label>
      <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
      
      <!-- Preserve cancelled_date if already selected -->
      <?php if (isset($_GET['cancelled_date'])): ?>
        <input type="hidden" name="cancelled_date" value="<?= htmlspecialchars($_GET['cancelled_date']) ?>">
      <?php endif; ?>

      <button type="submit">Filter</button>
    </form>

    <!-- Completed Appointments Table -->
    <table id="appointmentTable">
      <thead>
        <tr>
          <th>START</th>
          <th>END</th>
          <th>DATE</th>
          <th>PATIENT</th>
          <th>DENTIST</th>
          <th>PROCEDURE</th>
          <th>STATUS</th>
        </tr>
      </thead>
      <tbody id="appointmentList">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
            <td>
              <?php
                $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
                echo $start ? htmlspecialchars($start->format('g:i A')) : 'N/A';
              ?>
            </td>

            <td>
              <?php
                $end = DateTime::createFromFormat('H:i:s', $row['end_time']);
                echo $end ? htmlspecialchars($end->format('g:i A')) : 'N/A';
              ?>
            </td>

              <td><?= htmlspecialchars($row['Appointment_Date']) ?></td>

              <td>
                <?= !empty($row['fullname']) ? htmlspecialchars($row['fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>
              </td>

              <td><?= htmlspecialchars($row['name']) ?></td>

              <td>
                <?php
                  $service_ids = json_decode($row['Service_ID'], true);
                  if (!is_array($service_ids)) {
                      $service_ids = explode(',', $row['Service_ID']);
                  }
                  

                  $clean_ids = array_map('intval', $service_ids);
                  $service_names = [];

                  if (!empty($clean_ids)) {
                      $ids_string = implode(',', $clean_ids);
                      $name_query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
                      $name_result = $conn->query($name_query);

                      while ($service = $name_result->fetch_assoc()) {
                          $service_names[] = $service['service_name'];
                      }
                  }

                  echo htmlspecialchars(implode(', ', $service_names));
                ?>
              </td>

              <td><span style="color:green;"><?= htmlspecialchars($row['Appointment_Status']) ?></span></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No completed appointments found for this date range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- FOR CANCELLED APPOINTMENTS TO DISPLAY -->
<?php
// Get selected date range for cancelled appointments (default: today only)
$startDate = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$endDate = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d');

// Validate date range (max 3 months apart)
if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start = new DateTime($startDate);
    $end = new DateTime($endDate);
    $interval = $start->diff($end);
    
    if ($interval->m > 3 || $interval->y > 0 || $interval->invert) {
        // If range is invalid (more than 3 months or end before start), reset to today
        $startDate = $endDate = date('Y-m-d');
    }
}

// Prepare query with date range filter
$query = "SELECT a.*, u.fullname, d.name 
          FROM appointment a 
          LEFT JOIN users u ON a.Patient_ID = u.id 
          LEFT JOIN dentists d ON a.Dentist_ID = d.dentist_id 
          WHERE a.Appointment_Status = 'Cancelled' 
          AND a.Appointment_Date BETWEEN ? AND ?";

$stmt = $conn->prepare($query);
$stmt->bind_param("ss", $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
  <div class="header">
    <h2>Cancelled Appointments</h2>
  </div>

  <div class="appointment-table-container">
    <!-- DATE RANGE FILTER FORM for Cancelled Appointments -->
    <form method="GET" class="date-filter-form">
      <label for="start_date" style="font-size: 12px;">From:</label>
      <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" required>
      
      <label for="end_date" style="font-size: 12px;">To:</label>
      <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" required>
      
      <!-- Preserve completed_date if already selected -->
      <?php if (isset($_GET['completed_date'])): ?>
        <input type="hidden" name="completed_date" value="<?= htmlspecialchars($_GET['completed_date']) ?>">
      <?php endif; ?>

      <button type="submit">Filter</button>
    </form>

    <!-- Cancelled Appointments Table -->
    <table id="appointmentTable">
      <thead>
        <tr>
          <th>START</th>
          <th>END</th>
          <th>DATE</th>
          <th>PATIENT</th>
          <th>DENTIST</th>
          <th>PROCEDURE</th>
          <th>STATUS</th>
        </tr>
      </thead>
      <tbody id="appointmentList">
        <?php if ($result && $result->num_rows > 0): ?>
          <?php while ($row = $result->fetch_assoc()): ?>
            <tr>
              <td>
              <td>
              <?php
                $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
                echo $start ? htmlspecialchars($start->format('g:i A')) : 'N/A';
              ?>
            </td>

            <td>
              <?php
                $end = DateTime::createFromFormat('H:i:s', $row['end_time']);
                echo $end ? htmlspecialchars($end->format('g:i A')) : 'N/A';
              ?>
            </td>

              <td><?= htmlspecialchars($row['Appointment_Date']) ?></td>

              <td>
                <?= !empty($row['fullname']) ? htmlspecialchars($row['fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>
              </td>

              <td><?= htmlspecialchars($row['name']) ?></td>

              <td>
                <?php
                  $service_ids = json_decode($row['Service_ID'], true);
                  if (!is_array($service_ids)) {
                      $service_ids = explode(',', $row['Service_ID']);
                  }
                  

                  $clean_ids = array_map('intval', $service_ids);
                  $service_names = [];

                  if (!empty($clean_ids)) {
                      $ids_string = implode(',', $clean_ids);
                      $query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
                      $result_services = $conn->query($query);

                      while ($service = $result_services->fetch_assoc()) {
                          $service_names[] = $service['service_name'];
                      }
                  }

                  echo htmlspecialchars(implode(', ', $service_names));
                ?>
              </td>

              <td><span style="color:red;">Cancelled</span></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No cancelled appointments found for this date range.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<!-- Add Appointment Modal -->
<div id="appointmentModal" class="modal">
  <div class="modal-content">
    <h3>Add New Appointment</h3>
    <form id="addAppointmentForm">

      <!-- Patient Name -->
      <label for="patient_name">Patient Name:</label>
      <input type="text" id="patient_name" name="Patient_Name_Custom" required placeholder="Enter patient name">

      <!-- ADD: Optional Email Field -->
      <label for="email">Email (Optional):</label>
      <input type="email" id="email" name="email" placeholder="Enter patient email">

      <!-- Service Multi-select (CORRECT - no changes needed) -->
      <label>Select Services (up to 3):</label>
      <div id="checkboxDropdown" style="border:1px solid #ccc; padding:10px; border-radius:5px; max-height:120px; overflow-y:auto; background:#f9f9f9; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
        <?php
        include 'db_connection.php';
        $query = "SELECT service_ID, service_name, service_duration FROM services";
        $result = mysqli_query($conn, $query);
        while ($row = mysqli_fetch_assoc($result)) {
          echo "
          <label style=\"
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 10px;
            font-size: 14px;
            width: 100%;
            box-sizing: border-box;
            cursor: pointer;
            margin-bottom: 5px;
          \">
            <input type='checkbox' 
                   name='services[]' 
                   class='serviceCheckbox' 
                   value='{$row['service_ID']}' 
                   data-duration='{$row['service_duration']}'
                   data-service-name=\"".htmlspecialchars($row['service_name'])."\"
                   style='width:18px; height:18px; margin:0; flex-shrink:0; cursor:pointer;'>
            <span style='flex-grow:1; word-break:break-word;'>{$row['service_name']}</span>
          </label>
          ";
        }
        ?>
      </div>

      <!-- Dentist Selection Container (CORRECT - no changes needed) -->
      <div id="dentist_container" style="margin-top:10px;"></div>

      <!-- Duration Display (CORRECT - no changes needed) -->
      <p id="durationDisplay" style="font-style:italic; color:gray;">Estimated Duration: —</p>
      <input type="hidden" id="total_duration" name="total_duration">

      <!-- Date Dropdown (CORRECT - no changes needed) -->
      <label for="preferred_date">Select Date:</label>
      <select id="preferred_date" name="preferred_date" required disabled>
        <option value="">Select Date</option>
      </select>

      <!-- Time Dropdown (CORRECT - no changes needed) -->
      <label for="preferred_time">Select Time:</label>
      <select id="preferred_time" name="preferred_time" required disabled>
        <option value="">Select Time</option>
      </select>
      
      <!-- Custom Time Toggle (CORRECT - no changes needed) -->
      <div style="margin-top: 10px;">
        <button type="button" id="enableCustomTimeBtn" style="background:none; border:none; color:#2563eb; cursor:pointer; text-decoration:underline;">
          Enter a custom time instead
        </button>
        <input type="time" id="custom_time_input" name="custom_time_input" style="display:none; margin-top: 10px;" step="60">
      </div>

      <!-- Conflict Warning (CORRECT - no changes needed) -->
      <span id="conflict_warning" style="display:block; margin-top:10px; color:red;"></span>

      <!-- Appointment Status (CORRECT - no changes needed) -->
      <label for="status">Status:</label>
      <select id="status" name="status" required>
          <option value="Pending">Pending</option>
          <option value="Confirmed">Confirmed</option>
          <option value="Completed">Completed</option>
          <option value="Cancelled">Cancelled</option>
          <option value="No Show">No Show</option>
      </select>

      <!-- Buttons (CORRECT - no changes needed) -->
      <button type="submit" id="submitAppointmentBtn">Add Appointment</button>
      <button type="button" id="closeModal" class="close-btn">Cancel</button>
    </form>
  </div>
</div>



<!-- Reschedule Modal - SIMPLIFIED -->
<div id="rescheduleModal" class="modal">
  <div class="modal-content" style="position: relative;">
    <!-- Close button -->
<a href="appointment_module.php" class="close-btn" 
   style="position:absolute; top:15px; right:10px; font-size:24px; text-decoration:none; background-color:white;">
  &times;
</a>

    <h4>Reschedule Appointment</h4>

    
    <!-- Appointment Selection Dropdown -->
    <label for="selectAppointment">Select Appointment:</label>
    <select id="selectAppointment" class="form-control">
      <option value="">Select an appointment</option>
      <?php
      $appointments = $conn->query("SELECT a.Appointment_ID, a.start_time, a.Appointment_Date, 
                                  COALESCE(u.fullname, a.Patient_Name_Custom) AS patient_name, 
                                  u.email AS patient_email,
                                  a.Service_ID as Service_Type,
                                  a.Dentist_ID,
                                  d.name AS dentist_name
                                  FROM appointment a 
                                  LEFT JOIN users u ON a.Patient_ID = u.id
                                  LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                                  WHERE a.Appointment_Status NOT IN ('Completed', 'Cancelled')");
      while ($row = $appointments->fetch_assoc()): 
      ?>
        <option value="<?= $row['Appointment_ID'] ?>" 
                data-patient-name="<?= htmlspecialchars($row['patient_name']) ?>"
                data-patient-email="<?= htmlspecialchars($row['patient_email'] ?? '') ?>"
                data-services="<?= htmlspecialchars($row['Service_Type']) ?>"
                data-dentist-id="<?= $row['Dentist_ID'] ?>"
                data-dentist-name="<?= htmlspecialchars($row['dentist_name']) ?>">
          <?= htmlspecialchars($row['patient_name']) ?> - 
          <?= htmlspecialchars($row['Appointment_Date']) ?> 
          <?= htmlspecialchars(date('g:i A', strtotime($row['start_time']))) ?>
          (Dr. <?= htmlspecialchars($row['dentist_name']) ?>)
        </option>
      <?php endwhile; ?>
    </select>

    <!-- Simplified Form -->
    <form id="rescheduleAppointmentForm" style="display: none;">
      <!-- Read-only Patient Info -->
      <label for="reschedule_patient_name">Patient Name:</label>
      <input type="text" id="reschedule_patient_name" readonly style="background-color: #f5f5f5;">

      <label for="reschedule_email">Email:</label>
      <input type="email" id="reschedule_email" readonly style="background-color: #f5f5f5;">

      <!-- Read-only Service Display -->
      <label>Services:</label>
      <div id="reschedule_services_display" style="background-color: #f5f5f5; padding: 7px; border-radius: 4px; font-size: 14px;">
        <!-- Services will be displayed here -->
      </div>

      <!-- Read-only Dentist Display (Hidden from user but used internally) -->
      <div style="display: none;">
        <label for="reschedule_dentist_id">Dentist:</label>
        <input type="text" id="reschedule_dentist_id" name="new_dentist">
        <span id="reschedule_dentist_name_display"></span>
      </div>

      <!-- Hidden Fields -->
      <input type="hidden" id="original_appointment_id" name="original_appointment_id">
      <input type="hidden" id="reschedule_total_duration" name="total_duration">

      <!-- Date Selection (Auto-enabled by hidden dentist) -->
      <label for="reschedule_preferred_date">Select New Date:</label>
      <select id="reschedule_preferred_date" name="preferred_date" required disabled>
        <option value="">Select Date</option>
      </select>

      <!-- Time Selection -->
      <label for="reschedule_preferred_time">Select New Time:</label>
      <select id="reschedule_preferred_time" name="preferred_time" required disabled>
        <option value="">Select Time</option>
      </select>
      
      <!-- Custom Time Toggle -->
      <div style="margin-top: 10px;">
        <button type="button" id="enableRescheduleCustomTimeBtn" style="background:none; border:none; color:#2563eb; cursor:pointer; text-decoration:underline;">
          Enter a custom time instead
        </button>
        <input type="time" id="reschedule_custom_time_input" name="custom_time_input" style="display:none; margin-top: 10px;" step="60">
      </div>

      <!-- Conflict Warning -->
      <span id="reschedule_conflict_warning" style="display:block; margin-top:10px; color:red;"></span>

      <!-- Buttons -->
      <button type="submit" id="submitRescheduleBtn">Reschedule Appointment</button>
      <button type="button" id="cancelRescheduleBtn" class="close-btn">Cancel</button>
    </form>
  </div>
</div>

<script src="appointment_module.js"></script>
<script>

document.addEventListener('DOMContentLoaded', function() {
  // ==================== AUTO-REFRESH FUNCTIONALITY ====================
  const refreshInterval = 10000; // 10 seconds
  let refreshTimer;
  const refreshIndicator = document.createElement('div');
  refreshIndicator.className = 'refresh-indicator';
  refreshIndicator.textContent = 'Refreshing appointments...';
  document.body.appendChild(refreshIndicator);

  function startAutoRefresh() {
    refreshTimer = setInterval(refreshAppointments, refreshInterval);
  }
  
  function stopAutoRefresh() {
    clearInterval(refreshTimer);
  }
  
  function refreshAppointments() {
    if (document.hidden) return;
    
    refreshIndicator.style.display = 'block';
    fetch('appointment_module.php')
      .then(response => response.text())
      .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTable = doc.querySelector('#appointmentList');
        if (newTable) {
          document.querySelector('#appointmentList').innerHTML = newTable.innerHTML;
          // Hide indicator after 2 seconds
          setTimeout(() => {
            refreshIndicator.style.display = 'none';
          }, 2000);
        }
      })
      .catch(error => {
        console.error('Error refreshing appointments:', error);
        refreshIndicator.style.display = 'none';
      });
  }

  // Start auto-refresh when page loads
  startAutoRefresh();
  
  // Pause auto-refresh when tab is not active
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      stopAutoRefresh();
    } else {
      startAutoRefresh();
      refreshAppointments(); // Refresh immediately when tab becomes active
    }
  });
  
  // Also refresh after form submissions
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
      setTimeout(refreshAppointments, 1000); // Refresh 1 second after form submission
    });
  });


  // ==================== AUTO-OPEN MODAL FROM DASHBOARD ====================
  const urlParams = new URLSearchParams(window.location.search);
  if (urlParams.get('open_modal') === 'true') {
      // Open the appointment modal
      const modal = document.getElementById('appointmentModal');
      if (modal) {
          modal.style.display = 'flex';
          
          // Pre-fill the patient name from URL parameters
          const patientName = urlParams.get('patient_name');
          const patientNameInput = document.getElementById('patient_name');
          if (patientName && patientNameInput) {
              patientNameInput.value = decodeURIComponent(patientName);
          }
          
          // Remove the parameters from URL without refreshing
          const newUrl = window.location.pathname;
          window.history.replaceState({}, '', newUrl);
      }
  }


// ==================== APPOINTMENT MODAL ====================
const modal = document.getElementById('appointmentModal');
const openBtn = document.getElementById('newAppointmentBtn');
const closeBtn = document.getElementById('closeModal');

if (openBtn && modal) openBtn.onclick = () => modal.style.display = 'flex';
if (closeBtn && modal) closeBtn.onclick = () => modal.style.display = 'none';
window.onclick = (e) => { if (e.target === modal) modal.style.display = 'none'; };

// Appointment form elements
const form = document.getElementById('addAppointmentForm');
const dateSelect = document.getElementById('preferred_date');
const timeSelect = document.getElementById('preferred_time');
const serviceCheckboxes = document.querySelectorAll('.serviceCheckbox');
const durationDisplay = document.getElementById('durationDisplay');
const conflictWarning = document.getElementById('conflict_warning');
const submitBtn = document.getElementById('submitAppointmentBtn');
const dentistContainer = document.getElementById('dentist_container');

// Custom time elements
const customToggleBtn = document.getElementById('enableCustomTimeBtn');
const customTimeInput = document.getElementById('custom_time_input');
let usingCustomTime = false;

// Duration calculation
function calculateTotalDuration() {
  let totalMinutes = 0;
  const checkedServices = document.querySelectorAll('.serviceCheckbox:checked');
  
  checkedServices.forEach((cb, index) => {
    if (cb.checked) {
      const minutes = parseInt(cb.dataset.duration) || 10;
      totalMinutes += minutes;
      
      // Add 10-minute buffer after EVERY service (including the last one)
      totalMinutes += 10;
    }
  });

  document.getElementById('total_duration').value = totalMinutes;
  return totalMinutes;
}

function updateDurationDisplay() {
  const total = calculateTotalDuration();
  durationDisplay.textContent = `Estimated Duration: ${total} minutes`;
}

// Time formatting
function formatTime12Hour(timeStr) {
  const [hour, minute] = timeStr.split(':').map(Number);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const hour12 = hour % 12 || 12;
  return `${hour12}:${String(minute).padStart(2, '0')} ${ampm}`;
}

function computeEndTime(startTime, totalMinutes) {
  const [hours, minutes] = startTime.split(':').map(Number);
  const startDate = new Date();
  startDate.setHours(hours, minutes);
  startDate.setMinutes(startDate.getMinutes() + totalMinutes);
  return `${String(startDate.getHours()).padStart(2, '0')}:${String(startDate.getMinutes()).padStart(2, '0')}:00`;
}

// Custom time toggle
customToggleBtn?.addEventListener('click', () => {
  usingCustomTime = !usingCustomTime;
  timeSelect.style.display = usingCustomTime ? 'none' : 'block';
  customTimeInput.style.display = usingCustomTime ? 'block' : 'none';
  customToggleBtn.textContent = usingCustomTime
    ? 'Use suggested time slots instead'
    : 'Enter a custom time instead';
  checkConflict();
});

// ========== SERVICE SELECTION & AUTO DENTIST ==========

// When services change → update dentists dynamically
serviceCheckboxes.forEach(cb => cb.addEventListener('change', function() {
  const checked = document.querySelectorAll('.serviceCheckbox:checked');
  if (checked.length > 3) {
    this.checked = false;
    alert("You can only select up to 3 services.");
    return;
  }
  updateDurationDisplay();
  updateDentistOptions();
  checkConflict();
}));

// Load dentists for each checked service
function updateDentistOptions() {
  dentistContainer.innerHTML = ""; // reset

  const checkedServices = document.querySelectorAll('.serviceCheckbox:checked');
  checkedServices.forEach(cb => {
    const serviceId = cb.value;
    const serviceName = cb.parentElement.innerText.trim();

    // Create wrapper
    const wrapper = document.createElement('div');
    wrapper.className = "dentist-wrapper";
    wrapper.style.marginTop = "8px";

    const label = document.createElement('label');
    label.textContent = `Dentist for ${serviceName}:`;

    const select = document.createElement('select');
    select.name = `dentist_for_service[${serviceId}]`; // ✅ CORRECT - matches backend
    select.classList.add('dentistSelect');
    select.dataset.serviceId = serviceId;
    select.innerHTML = `<option value="">Auto-assign if left blank</option>`; // ✅ CHANGED

    wrapper.appendChild(label);
    wrapper.appendChild(select);
    dentistContainer.appendChild(wrapper);

    // Fetch dentists for this service
    fetch(`get_dentists_for_service.php?service_id=${serviceId}`)
      .then(res => res.json())
      .then(dentists => {
        select.innerHTML = `<option value="">Auto-assign if left blank</option>`; // ✅ CHANGED
        if (Array.isArray(dentists) && dentists.length > 0) {
          dentists.forEach(d => {
            const opt = document.createElement('option');
            opt.value = d.Dentist_ID;
            opt.textContent = `${d.name} - ${d.specialization}`;
            select.appendChild(opt);
          });
        } else {
          const opt = document.createElement('option');
          opt.value = "";
          opt.textContent = "No dentist available - will auto-assign";
          select.appendChild(opt);
        }
      });
  });
}

// ========== DATE & TIME HANDLING ==========

// Update available dates when dentist changes
dentistContainer.addEventListener('change', function(e) {
  if (!e.target.classList.contains('dentistSelect')) return;

  const dentistId = e.target.value;
  dateSelect.innerHTML = '<option value="">Select Date</option>';
  dateSelect.disabled = true;
  timeSelect.innerHTML = '<option value="">Select Time</option>';
  timeSelect.disabled = true;

  if (!dentistId) return;

  fetch(`get_available_dates.php?dentist_id=${dentistId}`)
    .then(res => res.json())
    .then(dates => {
      if (Array.isArray(dates) && dates.length > 0) {
        dates.forEach(date => {
          const option = document.createElement('option');
          option.value = date;
          option.textContent = date;
          dateSelect.appendChild(option);
        });
        dateSelect.disabled = false;
      }
    });
});

// Update available times when date changes
dateSelect.addEventListener('change', function() {
  // Get the first dentist selected (for now one timeline is assumed)
  const firstDentist = document.querySelector('.dentistSelect')?.value;
  const selectedDate = this.value;

  timeSelect.innerHTML = '<option value="">Select Time</option>';
  timeSelect.disabled = true;

  if (!firstDentist || !selectedDate) return;

  fetch(`get_available_times.php?dentist_id=${firstDentist}&date=${selectedDate}`)
    .then(res => res.json())
    .then(times => {
      if (Array.isArray(times) && times.length > 0) {
        times.forEach(time => {
          const option = document.createElement('option');
          option.value = time;
          option.textContent = formatTime12Hour(time);
          timeSelect.appendChild(option);
        });
        timeSelect.disabled = false;
      }
    });
});

// ========== CONFLICT CHECKING ==========

function checkConflict() {
  const firstDentist = document.querySelector('.dentistSelect')?.value;
  const date = dateSelect.value;
  const duration = calculateTotalDuration(); // This now returns duration WITHOUT buffer
  const startTime = usingCustomTime ? customTimeInput.value : timeSelect.value;

  if (!firstDentist || !date || !startTime || !duration) return;

  // First check if appointment extends past closing time (include buffer for last service)
  const [hours, minutes] = startTime.split(':').map(Number);
  const selectedTimeInMinutes = hours * 60 + minutes;
  const endTimeInMinutes = selectedTimeInMinutes + duration + 10; // Add buffer for closing time check

  // Clinic hours: 9:00 AM (540) to 5:00 PM (1020)
  if (endTimeInMinutes > 1020) {
    const availableDuration = 1020 - selectedTimeInMinutes;
    conflictWarning.innerHTML = `⚠️ <strong>Clinic Hours Conflict!</strong> The clinic closes at 5:00 PM. With your selected services (${duration} minutes + 10 min buffer), only ${availableDuration} minutes are available before closing. Please choose an earlier time or reduce services.`;
    conflictWarning.style.color = "red";
    submitBtn.disabled = true;
    return;
  }

  
  // Then check for other appointment conflicts
  fetch(`check_conflict.php?dentist_id=${firstDentist}&date=${date}&start_time=${startTime}&duration=${duration}`)
    .then(res => res.json())
    .then(data => {
      if (data.conflict) {
        let conflictEnd = data.conflict_end_time || 'that time';

        if (conflictEnd !== 'that time') {
          const [h, m] = conflictEnd.split(':');
          const hour = parseInt(h);
          const minute = parseInt(m);
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const formattedHour = hour % 12 === 0 ? 12 : hour % 12;
          conflictEnd = `${formattedHour}:${minute.toString().padStart(2, '0')} ${ampm}`;
        }

        conflictWarning.innerHTML = `⚠️ <strong>Appointment Conflict!</strong> Another appointment ends at ${conflictEnd}. Please choose a later time.`;
        conflictWarning.style.color = "red";
        submitBtn.disabled = true;
      } else {
        conflictWarning.innerHTML = "✅ No conflicts found! Time slot is available.";
        conflictWarning.style.color = "green";
        submitBtn.disabled = false;
      }
    });
}

// Event listeners for conflict checking
timeSelect.addEventListener('change', checkConflict);
customTimeInput.addEventListener('input', checkConflict);
dateSelect.addEventListener('change', checkConflict);

// ========== FORM SUBMISSION ==========
form.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  try {
    submitBtn.disabled = true;
    submitBtn.textContent = 'Processing...';

    // Validate conflicts
    if (conflictWarning.style.color === 'red') {
      throw new Error("Time conflict detected. Please choose another time.");
    }

    // Collect ALL selected dentists from multi-service form
    const dentistSelects = document.querySelectorAll('select[name^="dentist_for_service"]');
    const selectedDentists = [];
    
    dentistSelects.forEach(select => {
      if (select.value) {
        selectedDentists.push(select.value);
        console.log(`Found selected dentist: ${select.value}`);
      }
    });

    // If no specific dentists selected, try to get the main dentist
    let mainDentistId = selectedDentists.length > 0 ? selectedDentists[0] : document.querySelector('.dentistSelect')?.value;
    
    if (!mainDentistId) {
      throw new Error("Please select at least one dentist for the services");
    }

    const formData = new FormData(form);

    // Add time fields
    let selectedTime = usingCustomTime ? customTimeInput.value : timeSelect.value;
    const totalDuration = calculateTotalDuration();

    if (!selectedTime) throw new Error("Please select a time");

    if (usingCustomTime) {
      const timeParts = selectedTime.split(':');
      if (timeParts.length < 2) throw new Error("Invalid time format");
      selectedTime = timeParts.map(part => part.padStart(2, '0')).slice(0, 2).join(':') + ':00';
    }

    // Add the main dentist (for backward compatibility)
    formData.set('doctor', mainDentistId);
    
    // Add all dentists as a separate field for the backend
    selectedDentists.forEach((dentistId, index) => {
      formData.append(`selected_dentists[${index}]`, dentistId);
    });

    // DEBUG: Log what we're sending
    console.log('Sending form data:');
    console.log('Selected dentists:', selectedDentists);
    console.log('Main dentist:', mainDentistId);
    for (let [key, value] of formData.entries()) {
      console.log(key + ': ' + value);
    }

    // Send request
    const response = await fetch('add_appointment.php', {
      method: 'POST',
      body: formData
    });

    // DEBUG: Check the raw response
    const responseText = await response.text();
    console.log('Raw server response:', responseText);

    // Try to parse as JSON
    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error('JSON parse error:', parseError);
      console.error('Response that failed to parse:', responseText);
      throw new Error('Server returned non-JSON response. Check console for details.');
    }

    // Check if response is successful
    if (!response.ok || data.status === 'error') {
      throw new Error(data.message || 'Submission failed');
    }

    alert(data.message || "Appointment added successfully!");
    modal.style.display = 'none';
    location.reload();

  } catch (error) {
    console.error('Submission error:', error);
    alert(`Error: ${error.message}`);
  } finally {
    submitBtn.disabled = false;
    submitBtn.textContent = 'Add Appointment';
  }
});


// ==================== RESCHEDULE MODAL - SIMPLIFIED VERSION ====================
const rescheduleModal = document.getElementById('rescheduleModal');
const rescheduleBtn = document.getElementById('rescheduleAppointmentBtn');
const cancelRescheduleBtn = document.getElementById('cancelRescheduleBtn');
const selectAppointment = document.getElementById('selectAppointment');
const rescheduleForm = document.getElementById('rescheduleAppointmentForm');

// Form elements (make sure these match your HTML IDs)
const reschedulePatientName = document.getElementById('reschedule_patient_name');
const rescheduleEmail = document.getElementById('reschedule_email');
const rescheduleServicesDisplay = document.getElementById('reschedule_services_display');
const rescheduleDentistId = document.getElementById('reschedule_dentist_id');
const rescheduleDateSelect = document.getElementById('reschedule_preferred_date');
const rescheduleTimeSelect = document.getElementById('reschedule_preferred_time');
const rescheduleCustomTimeBtn = document.getElementById('enableRescheduleCustomTimeBtn');
const rescheduleCustomTimeInput = document.getElementById('reschedule_custom_time_input');
const rescheduleConflictWarning = document.getElementById('reschedule_conflict_warning');
const rescheduleSubmitBtn = document.getElementById('submitRescheduleBtn');

let usingRescheduleCustomTime = false;

// Service data cache (to avoid multiple database calls)
let serviceDataCache = null;

// Preload service data from PHP
function preloadServiceData() {
    // This will be populated from your existing PHP service data
    const serviceCheckboxes = document.querySelectorAll('.rescheduleServiceCheckbox');
    serviceDataCache = {};
    
    serviceCheckboxes.forEach(checkbox => {
        serviceDataCache[checkbox.value] = {
            name: checkbox.dataset.serviceName,
            duration: parseInt(checkbox.dataset.duration) || 30
        };
    });
}

// Call this on page load
preloadServiceData();

// Open modal - show only the appointment selection
rescheduleBtn.addEventListener('click', function() {
  rescheduleModal.style.display = 'flex';
  rescheduleForm.style.display = 'none'; // Hide form initially
  selectAppointment.value = ''; // Reset selection
});

// When appointment is selected, show the form
selectAppointment.addEventListener('change', function() {
  if (!this.value) {
    rescheduleForm.style.display = 'none'; // Hide form if no selection
    return;
  }
  
  const selectedOption = this.options[this.selectedIndex];
  loadAppointmentData(selectedOption);
  rescheduleForm.style.display = 'block'; // Show form
});

// Close modal
cancelRescheduleBtn.addEventListener('click', function() {
  rescheduleModal.style.display = 'none';
  resetRescheduleForm();
});

// Load appointment data into reschedule form
function loadAppointmentData(option) {
  const appointmentId = option.value;
  const patientName = option.getAttribute('data-patient-name');
  const patientEmail = option.getAttribute('data-patient-email');
  const servicesJson = option.getAttribute('data-services');
  const dentistId = option.getAttribute('data-dentist-id');
  const dentistName = option.getAttribute('data-dentist-name');
  
  // Set basic info
  document.getElementById('original_appointment_id').value = appointmentId;
  reschedulePatientName.value = patientName;
  rescheduleEmail.value = patientEmail || '';
  
  // Set hidden dentist (this enables date/time selection)
  rescheduleDentistId.value = dentistId;
  
  // Display services (read-only) and calculate duration
  displayServicesAndCalculateDuration(servicesJson);
  
  // Auto-load available dates for this dentist
  loadAvailableDates(dentistId);
}

// Combined function to display services and calculate duration
function displayServicesAndCalculateDuration(servicesJson) {
  let servicesArray = [];
  let totalDuration = 0;
  let serviceNames = [];
  
  try {
    servicesArray = JSON.parse(servicesJson);
    if (!Array.isArray(servicesArray)) {
      servicesArray = servicesJson.split(',').map(id => parseInt(id.trim()));
    }
  } catch (e) {
    servicesArray = servicesJson.split(',').map(id => parseInt(id.trim()));
  }
  
  // Get service names from the existing service checkboxes in your HTML
  const serviceCheckboxes = document.querySelectorAll('.serviceCheckbox');
  const serviceData = {};
  
  // Build service data object from existing checkboxes
  serviceCheckboxes.forEach(checkbox => {
    serviceData[checkbox.value] = {
      name: checkbox.parentElement.querySelector('span').textContent.split(' (')[0].trim(), // Extract service name
      duration: parseInt(checkbox.dataset.duration) || 30
    };
  });
  
  // Match service IDs with their names
  servicesArray.forEach(serviceId => {
    if (serviceData[serviceId]) {
      serviceNames.push(serviceData[serviceId].name);
      totalDuration += serviceData[serviceId].duration;
    } else {
      // Fallback if service not found
      serviceNames.push('Service ID: ' + serviceId);
      totalDuration += 30; // Default 30 minutes
    }
  });
  

  
  // Display service names
  rescheduleServicesDisplay.innerHTML = serviceNames.join(', ');
  
  // Set total duration in hidden field
  document.getElementById('reschedule_total_duration').value = totalDuration;
  
  // Debug log to verify it's working
  console.log('Services found:', serviceNames);
  console.log('Total duration:', totalDuration);
}

// Auto-load dates when appointment is selected
function loadAvailableDates(dentistId) {
  rescheduleDateSelect.innerHTML = '<option value="">Select Date</option>';
  rescheduleDateSelect.disabled = true;
  rescheduleTimeSelect.innerHTML = '<option value="">Select Time</option>';
  rescheduleTimeSelect.disabled = true;

  if (!dentistId) return;

  fetch(`get_available_dates.php?dentist_id=${dentistId}`)
    .then(res => res.json())
    .then(dates => {
      if (Array.isArray(dates) && dates.length > 0) {
        dates.forEach(date => {
          const option = document.createElement('option');
          option.value = date;
          option.textContent = date;
          rescheduleDateSelect.appendChild(option);
        });
        rescheduleDateSelect.disabled = false;
      } else {
        rescheduleDateSelect.innerHTML = '<option value="">No available dates</option>';
      }
    })
    .catch(error => {
      console.error('Error loading dates:', error);
      rescheduleDateSelect.innerHTML = '<option value="">Error loading dates</option>';
    });
}

// Time handling for reschedule
rescheduleDateSelect.addEventListener('change', function() {
  const dentistId = rescheduleDentistId.value;
  const selectedDate = this.value;

  rescheduleTimeSelect.innerHTML = '<option value="">Select Time</option>';
  rescheduleTimeSelect.disabled = true;

  if (!dentistId || !selectedDate) return;

  fetch(`get_available_times.php?dentist_id=${dentistId}&date=${selectedDate}`)
    .then(res => res.json())
    .then(times => {
      if (Array.isArray(times) && times.length > 0) {
        times.forEach(time => {
          const option = document.createElement('option');
          option.value = time;
          option.textContent = formatTime12Hour(time);
          rescheduleTimeSelect.appendChild(option);
        });
        rescheduleTimeSelect.disabled = false;
      } else {
        rescheduleTimeSelect.innerHTML = '<option value="">No available times</option>';
      }
    })
    .catch(error => {
      console.error('Error loading times:', error);
      rescheduleTimeSelect.innerHTML = '<option value="">Error loading times</option>';
    });
});

// Custom time toggle for reschedule
rescheduleCustomTimeBtn.addEventListener('click', () => {
  usingRescheduleCustomTime = !usingRescheduleCustomTime;
  rescheduleTimeSelect.style.display = usingRescheduleCustomTime ? 'none' : 'block';
  rescheduleCustomTimeInput.style.display = usingRescheduleCustomTime ? 'block' : 'none';
  rescheduleCustomTimeBtn.textContent = usingRescheduleCustomTime
    ? 'Use suggested time slots instead'
    : 'Enter a custom time instead';
  checkRescheduleConflict();
});

// Reschedule conflict checking
function checkRescheduleConflict() {
  const dentistId = rescheduleDentistId.value;
  const date = rescheduleDateSelect.value;
  const duration = document.getElementById('reschedule_total_duration').value;
  const startTime = usingRescheduleCustomTime ? rescheduleCustomTimeInput.value : rescheduleTimeSelect.value;

  if (!dentistId || !date || !startTime || !duration) return;

  // Check clinic hours
  const [hours, minutes] = startTime.split(':').map(Number);
  const selectedTimeInMinutes = hours * 60 + minutes;
  const endTimeInMinutes = selectedTimeInMinutes + parseInt(duration);

  if (endTimeInMinutes > 1020) { // 5:00 PM = 1020 minutes
    const availableDuration = 1020 - selectedTimeInMinutes;
    rescheduleConflictWarning.innerHTML = `⚠️ <strong>Clinic Hours Conflict!</strong> The clinic closes at 5:00 PM. With your selected services (${duration} minutes), only ${availableDuration} minutes are available before closing. Please choose an earlier time.`;
    rescheduleConflictWarning.style.color = "red";
    rescheduleConflictWarning.style.display = "block";
    rescheduleSubmitBtn.disabled = true;
    return;
  }

  // Check appointment conflicts
  const excludeId = document.getElementById('original_appointment_id').value;
  fetch(`check_conflict.php?dentist_id=${dentistId}&date=${date}&start_time=${startTime}&duration=${duration}&exclude_id=${excludeId}`)
    .then(res => res.json())
    .then(data => {
      if (data.conflict) {
        let conflictEnd = data.conflict_end_time || 'that time';
        if (conflictEnd !== 'that time') {
          const [h, m] = conflictEnd.split(':');
          const hour = parseInt(h);
          const minute = parseInt(m);
          const ampm = hour >= 12 ? 'PM' : 'AM';
          const formattedHour = hour % 12 === 0 ? 12 : hour % 12;
          conflictEnd = `${formattedHour}:${minute.toString().padStart(2, '0')} ${ampm}`;
        }

        rescheduleConflictWarning.innerHTML = `⚠️ <strong>Appointment Conflict!</strong> Another appointment ends at ${conflictEnd}. Please choose a later time.`;
        rescheduleConflictWarning.style.color = "red";
        rescheduleConflictWarning.style.display = "block";
        rescheduleSubmitBtn.disabled = true;
      } else {
        rescheduleConflictWarning.innerHTML = "✅ No conflicts found! Time slot is available.";
        rescheduleConflictWarning.style.color = "green";
        rescheduleConflictWarning.style.display = "block";
        rescheduleSubmitBtn.disabled = false;
      }
    })
    .catch(error => {
      rescheduleConflictWarning.innerHTML = "⚠️ Could not check for conflicts. Please try again.";
      rescheduleConflictWarning.style.color = "orange";
      rescheduleConflictWarning.style.display = "block";
    });
}

// Event listeners for conflict checking
rescheduleTimeSelect.addEventListener('change', checkRescheduleConflict);
rescheduleCustomTimeInput.addEventListener('input', checkRescheduleConflict);
rescheduleDateSelect.addEventListener('change', checkRescheduleConflict);

// Helper function to format time
function formatTime12Hour(timeString) {
  if (!timeString) return '';
  const [hours, minutes] = timeString.split(':');
  const hour = parseInt(hours);
  const ampm = hour >= 12 ? 'PM' : 'AM';
  const displayHour = hour % 12 === 0 ? 12 : hour % 12;
  return `${displayHour}:${minutes} ${ampm}`;
}

// Reschedule form submission - FIXED VERSION
rescheduleForm.addEventListener('submit', async function(e) {
  e.preventDefault();
  
  try {
    rescheduleSubmitBtn.disabled = true;
    rescheduleSubmitBtn.textContent = 'Processing...';

    if (rescheduleConflictWarning.style.color === 'red') {
      throw new Error("Time conflict detected. Please choose another time.");
    }

    const formData = new FormData(rescheduleForm);
    formData.append('form_type', 'reschedule');
    formData.append('appointment_id', document.getElementById('original_appointment_id').value);
    formData.append('new_dentist', rescheduleDentistId.value);

    // Add time fields
    let selectedTime = usingRescheduleCustomTime ? rescheduleCustomTimeInput.value : rescheduleTimeSelect.value;
    const totalDuration = document.getElementById('reschedule_total_duration').value;

    if (!selectedTime) throw new Error("Please select a time");

    // Format time properly
    if (usingRescheduleCustomTime) {
      const timeParts = selectedTime.split(':');
      if (timeParts.length < 2) throw new Error("Invalid time format");
      selectedTime = timeParts.map(part => part.padStart(2, '0')).slice(0, 2).join(':') + ':00';
    }

    formData.append('new_time', selectedTime);
    formData.append('new_date', rescheduleDateSelect.value);

    console.log('Sending reschedule request...');
    console.log('New dentist ID:', rescheduleDentistId.value);
    console.log('New date:', rescheduleDateSelect.value);
    console.log('New time:', selectedTime);
    console.log('Duration:', totalDuration);
    
    const response = await fetch('appointment_module.php', {
      method: 'POST',
      body: formData
    });

    console.log('Response status:', response.status);
    
    const responseText = await response.text();
    console.log('Raw response text:', responseText);
    
    // Check if response is HTML (indicating the problem)
    if (responseText.trim().startsWith('<!DOCTYPE') || responseText.includes('<html') || responseText.includes('<div')) {
      console.error('SERVER RETURNED HTML INSTEAD OF JSON!');
      console.error('First 500 chars of response:', responseText.substring(0, 500));
      throw new Error('Server returned HTML page instead of JSON response. Check server-side errors.');
    }

    let data;
    try {
      data = JSON.parse(responseText);
    } catch (parseError) {
      console.error('JSON Parse Error:', parseError);
      console.error('Response that failed to parse:', responseText);
      throw new Error('Server returned non-JSON response. Actual response: ' + responseText.substring(0, 200));
    }

    if (!response.ok || data.status === 'error') {
      throw new Error(data.message || 'Reschedule failed');
    }

    alert(data.message || "Appointment rescheduled successfully!");
    rescheduleModal.style.display = 'none';
    location.reload();

  } catch (error) {
    console.error('Reschedule error:', error);
    alert(`Error: ${error.message}`);
  } finally {
    rescheduleSubmitBtn.disabled = false;
    rescheduleSubmitBtn.textContent = 'Reschedule Appointment';
  }
});


function resetRescheduleForm() {
  selectAppointment.value = '';
  rescheduleForm.style.display = 'none';
  reschedulePatientName.value = '';
  rescheduleEmail.value = '';
  rescheduleServicesDisplay.innerHTML = '';
  rescheduleDentistId.value = '';
  rescheduleDateSelect.innerHTML = '<option value="">Select Date</option>';
  rescheduleDateSelect.disabled = true;
  rescheduleTimeSelect.innerHTML = '<option value="">Select Time</option>';
  rescheduleTimeSelect.disabled = true;
  rescheduleCustomTimeInput.value = '';
  rescheduleCustomTimeInput.style.display = 'none';
  rescheduleCustomTimeBtn.textContent = 'Enter a custom time instead';
  rescheduleConflictWarning.style.display = 'none';
  rescheduleSubmitBtn.disabled = false;
  usingRescheduleCustomTime = false;
}

// Make sure this function exists (add it if missing)
if (typeof formatTime12Hour === 'undefined') {
  function formatTime12Hour(timeString) {
    if (!timeString) return '';
    const [hours, minutes] = timeString.split(':');
    const hour = parseInt(hours);
    const ampm = hour >= 12 ? 'PM' : 'AM';
    const displayHour = hour % 12 === 0 ? 12 : hour % 12;
    return `${displayHour}:${minutes} ${ampm}`;
  }
}


  // Client-side validation for date range (max 3 months)
  document.querySelector('.date-filter-form')?.addEventListener('submit', function(e) {
    const startDate = new Date(document.getElementById('start_date').value);
    const endDate = new Date(document.getElementById('end_date').value);
    
    // Calculate month difference
    const monthDiff = (endDate.getFullYear() - startDate.getFullYear()) * 12 + 
                     (endDate.getMonth() - startDate.getMonth());
    
    if (monthDiff > 3 || startDate > endDate) {
      alert('Date range cannot exceed 3 months and end date must be after start date.');
      e.preventDefault();
    }
  });
});

</script>

    <script src="notifications/notification_script.js"></script>

</body>
</html>