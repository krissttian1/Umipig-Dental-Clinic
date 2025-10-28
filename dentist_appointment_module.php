<?php
session_start();
require 'db_connection.php';
// ===== ADD NOTIFICATION SYSTEM =====
require_once 'notifications/notification_functions.php';
// ===== END NOTIFICATION SYSTEM =====

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    // Not an admin, redirect to home or login page
    header("Location: login.php");
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

// Cancel expired rescheduled appointments (25 mins passed and still pending)
$now = date('Y-m-d H:i:s');

// Only cancel pending appointments that are PAST their deadline
$autoCancelSQL = "
    UPDATE appointment
    SET Appointment_Status = 'Cancelled'
    WHERE Appointment_Status = 'Pending'
    AND reschedule_deadline IS NOT NULL
    AND reschedule_deadline < ?
    AND Appointment_ID NOT IN (
        SELECT Appointment_ID FROM (
            SELECT Appointment_ID FROM appointment 
            WHERE reschedule_token IS NOT NULL 
            AND reschedule_deadline > ?
        ) AS temp
    )
";
$stmt = $conn->prepare($autoCancelSQL);
$stmt->bind_param("ss", $now, $now);
$stmt->execute();
$stmt->close();

// Auto-cancel regular pending appointments past confirmation deadline
$cancel_sql = "
  UPDATE appointment 
  SET Appointment_Status = 'Cancelled' 
  WHERE Appointment_Status = 'Pending' 
  AND confirmation_deadline IS NOT NULL 
  AND confirmation_deadline < ?
  AND reschedule_token IS NULL
";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("s", $now);
$cancel_stmt->execute();
$cancel_stmt->close();

// Fetch dentists for dropdown
$dentists_result = $conn->query("SELECT Dentist_ID, name FROM dentists ORDER BY name ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type'])) {

    // UPDATE
    if ($_POST['form_type'] === 'update') {
        // Collect values from POST
        $procedure = $_POST['procedure'] ?? '';
        $start_time = $_POST['start_time'] ?? '';
        $date = $_POST['appointment_date'] ?? '';
        $patient_name_custom = $_POST['Patient_Name_Custom'] ?? '';
        $dentist_id = $_POST['Dentist_ID'] ?? '';
        $status = $_POST['Appointment_Status'] ?? ''; // ✅ Get selected status from dropdown
        $id = $_POST['appointment_id'] ?? '';  // ✅ Get the correct appointment ID

        // Convert procedure to array
        $service_ids = json_decode($procedure, true);
        if (!is_array($service_ids)) {
            $service_ids = explode(',', $procedure);
        }

        // Duration calculation
        $total_duration = 0;
        if (!empty($service_ids)) {
            $ids_string = implode(',', array_map('intval', $service_ids));
            $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
            $result = $conn->query($query);
            while ($row = $result->fetch_assoc()) {
                $total_duration += (int)$row['service_duration'];
            }
        }

        // Calculate end time
        $start = DateTime::createFromFormat('H:i:s', $start_time);
        $end_time = $start_time;
        if ($start && $total_duration > 0) {
            $end = clone $start;
            $end->modify("+{$total_duration} minutes");
            $end_time = $end->format('H:i:s');
        }

        // Convert service IDs back to JSON
        $procedure_json = json_encode($service_ids);

        // Update the database
        $stmt = $conn->prepare("UPDATE appointment 
            SET start_time = ?, end_time = ?, Appointment_Date = ?, Patient_Name_Custom = ?, Service_Type = ?, Appointment_Status = ? 
            WHERE Appointment_ID = ?");
        $stmt->bind_param("ssssssi", $start_time, $end_time, $date, $patient_name_custom,  $procedure_json, $status, $id);

        if ($stmt->execute()) {
            $_SESSION['message'] = "Appointment updated successfully!";
        } else {
            $_SESSION['message'] = "Error updating appointment: " . $stmt->error;
        }

        $stmt->close();
        header("Location: dentist_appointment_module.php");
        exit;
    }
    elseif ($_POST['form_type'] === 'cancel') {
        $id = $_POST['appointment_id'];
        $reason = $_POST['cancel_reason'];

        // Step 1: Update status and reason
        $stmt = $conn->prepare("UPDATE appointment SET Appointment_Status = 'Cancelled', cancellation_reason = ? WHERE Appointment_ID = ?");
        $stmt->bind_param("si", $reason, $id);

        if ($stmt->execute()) {
            $stmt->close();

            // Step 2: Fetch appointment + user + dentist info
            $stmt = $conn->prepare("
                SELECT a.Appointment_Date, a.start_time, a.Service_Type,
                       u.email, u.fullname,
                       d.name AS dentist_name, d.specialization
                FROM appointment a
                LEFT JOIN users u ON a.Patient_ID = u.id
                LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                WHERE a.Appointment_ID = ?
            ");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $data = $result->fetch_assoc();
            $stmt->close();

            // Step 3: Get service names
            $decoded_ids = json_decode($data['Service_Type'], true);
            if (!is_array($decoded_ids)) {
                $decoded_ids = explode(',', $data['Service_Type']);
            }

            $service_names = getServiceNamesFromIDs($conn, $decoded_ids);
            $procedure_names = implode(', ', $service_names);

            // Step 4: Compute end time
            $start_time = $data['start_time'];
            $total_duration = 0;
            if (!empty($decoded_ids)) {
                $ids_string = implode(',', array_map('intval', $decoded_ids));
                $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
                $result = $conn->query($query);
                while ($row = $result->fetch_assoc()) {
                    $total_duration += (int)$row['service_duration'];
                }
            }

            $end_time = $start_time;
            $start = DateTime::createFromFormat('H:i:s', $start_time);
            if ($start && $total_duration > 0) {
                $end = clone $start;
                $end->modify("+{$total_duration} minutes");
                $end_time = $end->format('H:i:s');
            }

            // Format time display
            $formattedStart = $start ? $start->format('g:i A') : 'N/A';
            $formattedEnd = isset($end) ? $end->format('g:i A') : 'N/A';

            // ===== NOTIFICATION TRIGGER FOR APPOINTMENT CANCELLATION =====
            createAdminNotification(
                'appointment',
                'Appointment Cancelled by Dentist',
                'Dentist ' . $_SESSION['username'] . ' has cancelled an appointment.' . "\n" .
                'Patient: ' . $data['fullname'] . "\n" .
                'Date: ' . $data['Appointment_Date'] . "\n" .
                'Time: ' . $formattedStart . ' - ' . $formattedEnd . "\n" .
                'Services: ' . $procedure_names . "\n" .
                'Reason: ' . $reason,
                'high',
                'appointment_module.php',
                $id
            );

            // DEBUG: Check if notification was created
            error_log("DEBUG: Cancellation notification triggered for appointment ID: " . $id);
            // ===== END NOTIFICATION TRIGGER =====

            // Step 5: Send email
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
                $mail->addAddress($data['email'], $data['fullname']);

                $mail->isHTML(true);
                $mail->Subject = 'Appointment Cancellation - Umipig Dental Clinic';
                $mail->Body = "
                    <h2>Appointment Cancelled</h2>
                    <p>Dear {$data['fullname']},</p>
                    <p>We regret to inform you that your appointment has been <strong style='color:red;'>CANCELLED</strong>.</p>

                    <p><strong>Appointment Details:</strong><br>
                    <strong>Service:</strong> {$procedure_names}<br>
                    <strong>Date:</strong> {$data['Appointment_Date']}<br>
                    <strong>Time:</strong> {$formattedStart} - {$formattedEnd}<br>
                    <strong>Dentist:</strong> {$data['dentist_name']} ({$data['specialization']})</p>

                    <p><strong style='color:red;'>Reason for Cancellation</strong><br>" . nl2br(htmlspecialchars($reason)) . "</p>

                    <p>If you believe this was a mistake or you would like to reschedule, please contact us at your earliest convenience.</p>

                    <p>We apologize for the inconvenience.<br>
                    Best regards,<br>
                    <strong>Umipig Dental Clinic Team</strong></p>
                ";

                $mail->send();
                echo json_encode(['status' => 'success', 'message' => 'Appointment cancelled. Email sent.']);
            } catch (Exception $e) {
                echo json_encode(['status' => 'success', 'message' => 'Appointment cancelled, but email could not be sent. Mailer Error: ' . $mail->ErrorInfo]);
            }
        } else {
            echo json_encode(['status' => 'error', 'message' => 'Error cancelling appointment: ' . $stmt->error]);
            $stmt->close();
        }

        exit;
    }
    elseif ($_POST['form_type'] === 'reschedule') {
        header('Content-Type: application/json');
        
        try {
            $id = $_POST['appointment_id'];
            $newDate = $_POST['new_date'];
            $newTime = $_POST['new_time'];

            // Validate required fields
            if (empty($id) || empty($newDate) || empty($newTime)) {
                throw new Exception('Missing required fields for rescheduling');
            }

            // Step 1: Get service_ids and calculate duration
            $stmt = $conn->prepare("SELECT Service_Type, Dentist_ID FROM appointment WHERE Appointment_ID = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointment = $result->fetch_assoc();
            $stmt->close();

            $service_ids = json_decode($appointment['Service_Type'], true);
            if (!is_array($service_ids)) {
                $service_ids = explode(',', $appointment['Service_Type']);
            }

            // Calculate total duration
            $total_duration = 0;
            if (!empty($service_ids)) {
                $ids_string = implode(',', array_map('intval', $service_ids));
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

            // Validate clinic hours (9:00 AM to 5:00 PM)
            $startMinutes = $start->format('H') * 60 + $start->format('i');
            $endMinutes = $startMinutes + $total_duration;
            
            if ($startMinutes < 540 || $endMinutes > 1020) {
                throw new Exception('Appointment must be scheduled between 9:00 AM and 5:00 PM');
            }

            $end = clone $start;
            $end->modify("+{$total_duration} minutes");
            $end_time = $end->format('H:i:s');

            // Check for conflicts with other appointments
            $conflict_sql = "SELECT COUNT(*) as conflict_count FROM appointment 
                            WHERE Dentist_ID = ?
                            AND Appointment_Date = ?
                            AND Appointment_ID != ?
                            AND Appointment_Status NOT IN ('Cancelled', 'Completed')
                            AND ((start_time < ? AND end_time > ?) OR 
                                 (start_time >= ? AND start_time < ?) OR 
                                 (end_time > ? AND end_time <= ?))";
            
            $stmt = $conn->prepare($conflict_sql);
            $stmt->bind_param("issssssss", $appointment['Dentist_ID'], $newDate, $id, 
                             $end_time, $newTime, 
                             $newTime, $end_time, 
                             $newTime, $end_time);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $stmt->close();

            if ($row['conflict_count'] > 0) {
                throw new Exception('The selected time conflicts with another appointment');
            }

            // Step 3: Fetch appointment data
            $stmt = $conn->prepare("
                SELECT a.Service_Type, u.email, u.fullname, d.name AS dentist_name, d.specialization
                FROM appointment a
                LEFT JOIN users u ON a.Patient_ID = u.id
                LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                WHERE a.Appointment_ID = ?");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $result = $stmt->get_result();
            $appointmentData = $result->fetch_assoc();
            $stmt->close();

            // Step 4: Get service names
            $decoded_ids = json_decode($appointmentData['Service_Type'], true);
            if (!is_array($decoded_ids)) {
                $decoded_ids = explode(',', $appointmentData['Service_Type']);
            }
            $service_names = getServiceNamesFromIDs($conn, $decoded_ids);
            $procedure_names = implode(', ', $service_names);

            // Step 5: Set new token and deadline
            $reschedule_token = bin2hex(random_bytes(16));
            $reschedule_deadline = date('Y-m-d H:i:s', strtotime('+25 minutes'));
            $status = 'Pending';

            // Step 6: Final update
            $stmt = $conn->prepare("UPDATE appointment 
                SET Appointment_Date = ?, start_time = ?, end_time = ?, 
                    reschedule_token = ?, reschedule_deadline = ?, Appointment_Status = ?
                WHERE Appointment_ID = ?");
            $stmt->bind_param("ssssssi", $newDate, $newTime, $end_time, $reschedule_token, $reschedule_deadline, $status, $id);

            if ($stmt->execute()) {
                $stmt->close();

                // ===== NOTIFICATION TRIGGER FOR APPOINTMENT RESCHEDULE =====
                createAdminNotification(
                    'appointment',
                    'Appointment Rescheduled by Dentist',
                    'Dentist ' . $_SESSION['username'] . ' has rescheduled an appointment.' . "\n" .
                    'Patient: ' . $appointmentData['fullname'] . "\n" .
                    'New Date: ' . $newDate . "\n" .
                    'New Time: ' . $newTime . ' - ' . $end_time . "\n" .
                    'Services: ' . $procedure_names . "\n" .
                    'Status: Waiting for patient confirmation',
                    'medium',
                    'appointment_module.php',
                    $id
                );
                // ===== END NOTIFICATION TRIGGER =====

                // Step 7: Send confirmation email with improved service breakdown
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
                            <p><strong>Service:</strong> {$procedure_names}<br>
                            <strong>Date:</strong> {$newDate}<br>
                            <strong>Time:</strong> {$newTime} - {$end_time}<br>
                            <strong>Dentist:</strong> {$appointmentData['dentist_name']} ({$appointmentData['specialization']})</p>
                        ";
                    } else {
                        // Multiple services format
                        $services_breakdown = "<p><strong>Services:</strong> {$procedure_names}</p>";
                        
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
                        <p>Dear {$appointmentData['fullname']},</p>
                        <p>Your appointment has been rescheduled with Umipig Dental Clinic.</p>
                        {$services_breakdown}
                        <p>
                            ✅ <strong>To confirm your rescheduled appointment, please click the link below:</strong><br>
                            <a href='{$reschedule_link}'>{$reschedule_link}</a><br><br>
                            &#9888; <strong>Note:</strong> This rescheduled appointment will be automatically cancelled if not confirmed within EXACTLY <span style=\"color:red;\">25 minutes</span>.
                        </p>
                        <p>Best regards,<br>Umipig Dental Clinic Team</p>
                    ";

                    $mail->send();
                    echo json_encode(['status' => 'success', 'message' => 'Appointment rescheduled. Email sent.']);
                } catch (Exception $e) {
                    echo json_encode(['status' => 'success', 'message' => 'Appointment rescheduled, but email could not be sent. Mailer Error: ' . $mail->ErrorInfo]);
                }
            } else {
                throw new Exception('Error updating appointment: ' . $stmt->error);
            }
        } catch (Exception $e) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'message' => $e->getMessage()
            ]);
            exit;
        }
        exit;
    }
} 

// Fetch appointments for this dentist (excluding completed/cancelled)
$dentist_id = (int) $_SESSION['dentist_id'];

$sql = "SELECT 
            a.Appointment_ID,
            a.start_time,
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
        WHERE a.Dentist_ID = ? 
        AND a.Appointment_Status IN ('Pending', 'Confirmed')  -- ✅ filter clearly
        ORDER BY a.Appointment_Date DESC, a.start_time ASC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$appointments_result = $stmt->get_result();
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Dental Clinic Appointment System</title>
      <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="dentist_appointment_module.css" />
  <style>
    table select, table input[type="time"], table input[type="date"], table input[type="text"] {
      width: 80%;
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
      margin-bottom: 200px;
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
      margin-bottom: 60px;
      margin-top: 40px;

    }
    .modal-content label {
      display: block;
      margin-top: 10px;
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
            <a href="dentist_user_profile_module.php" class="profile-icon" title="Profile">
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

<div class="container">
<div class="header">
  <h1>Appointments</h1>
  <button id="rescheduleAppointmentBtn">Reschedule Appointment</button>
  <button id="cancelAppointmentBtn">Cancel Appointment</button>
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
      <?php if ($appointments_result && $appointments_result->num_rows > 0): ?>
        <?php while ($row = $appointments_result->fetch_assoc()): ?>
          <tr>
            <form action="dentist_appointment_module.php" method="POST">
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
    $start = DateTime::createFromFormat('H:i:s', $row['start_time']);

    // Parse Service_Type (either JSON or comma-separated string)
    $service_ids = json_decode($row['Service_Type'], true);
    if (!is_array($service_ids)) {
        $service_ids = explode(',', $row['Service_Type']);
    }

    // Clean IDs
    $clean_ids = array_map('intval', $service_ids);
    $total_duration = 0;

    if (!empty($clean_ids)) {
        $ids_string = implode(',', $clean_ids);
        $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
        $result_services = $conn->query($query);

        while ($service = $result_services->fetch_assoc()) {
            $total_duration += (int)$service['service_duration'];
        }
    }

    if ($start && $total_duration > 0) {
        $end = clone $start;
        $end->modify("+$total_duration minutes");
        echo htmlspecialchars($end->format('g:i A'));
        $computed_end_time = $end->format('H:i:s');
    } else {
        echo "N/A";
        $computed_end_time = '';
    }
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
        $statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
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

// Get the logged-in dentist's ID from session
$dentist_id = $_SESSION['dentist_id'];

// Apply date range filter and dentist filter in the SQL query
$query = "SELECT a.*, u.fullname, d.name 
          FROM appointment a 
          LEFT JOIN users u ON a.Patient_ID = u.id 
          LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID 
          WHERE a.Appointment_Status = 'Completed' 
          AND a.Dentist_ID = ?
          AND a.Appointment_Date BETWEEN ? AND ?
          ORDER BY a.Appointment_Date DESC, a.start_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $dentist_id, $startDate, $endDate);
$stmt->execute();
$result = $stmt->get_result();
?>

<div class="container">
  <div class="header">
    <h2 class="completed_appointments">Completed Appointments</h2>
  </div>
  <div class="appointment-table-container">
    <!-- ✅ DATE FILTER FORM for Completed Appointments -->
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
              <!-- Start Time -->
              <td>
                <?php
                  $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
                  echo $start ? htmlspecialchars($start->format('g:i A')) : 'N/A';
                ?>
              </td>

              <!-- End Time -->
              <td>
                <?php
                  $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
                  $service_ids = json_decode($row['Service_Type'], true);
                  if (!is_array($service_ids)) {
                      $service_ids = explode(',', $row['Service_Type']);
                  }

                  $clean_ids = array_map('intval', $service_ids);
                  $total_duration = 0;

                  if (!empty($clean_ids)) {
                      $ids_string = implode(',', $clean_ids);
                      $dur_query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
                      $dur_result = $conn->query($dur_query);

                      while ($service = $dur_result->fetch_assoc()) {
                          $total_duration += (int)$service['service_duration'];
                      }
                  }

                  if ($start && $total_duration > 0) {
                      $end = clone $start;
                      $end->modify("+$total_duration minutes");
                      echo htmlspecialchars($end->format('g:i A'));
                  } else {
                      echo "N/A";
                  }
                ?>
              </td>

              <!-- Date -->
              <td><?= htmlspecialchars($row['Appointment_Date']) ?></td>

              <!-- Patient -->
              <td>
                <?= !empty($row['fullname']) ? htmlspecialchars($row['fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>
              </td>

              <!-- Dentist -->
              <td><?= htmlspecialchars($row['name']) ?></td>

              <!-- Procedure -->
              <td>
                <?php
                  $service_ids = json_decode($row['Service_Type'], true);
                  if (!is_array($service_ids)) {
                      $service_ids = explode(',', $row['Service_Type']);
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

              <!-- Status -->
              <td><span style="color:green;"><?= htmlspecialchars($row['Appointment_Status']) ?></span></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No completed appointments found.</td></tr>
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

// Get the logged-in dentist's ID from session
$dentist_id = $_SESSION['dentist_id'];

// Prepare query with date range filter and dentist filter
$query = "SELECT a.*, u.fullname, d.name 
          FROM appointment a 
          LEFT JOIN users u ON a.Patient_ID = u.id 
          LEFT JOIN dentists d ON a.Dentist_ID = d.dentist_id 
          WHERE a.Appointment_Status = 'Cancelled' 
          AND a.Dentist_ID = ?
          AND a.Appointment_Date BETWEEN ? AND ?
          ORDER BY a.Appointment_Date DESC, a.start_time DESC";

$stmt = $conn->prepare($query);
$stmt->bind_param("iss", $dentist_id, $startDate, $endDate);
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
                  $start = DateTime::createFromFormat('H:i:s', $row['start_time']);
                  $service_ids = json_decode($row['Service_Type'], true);
                  if (!is_array($service_ids)) {
                      $service_ids = explode(',', $row['Service_Type']);
                  }

                  $clean_ids = array_map('intval', $service_ids);
                  $total_duration = 0;

                  if (!empty($clean_ids)) {
                      $ids_string = implode(',', $clean_ids);
                      $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
                      $result_services = $conn->query($query);

                      while ($service = $result_services->fetch_assoc()) {
                          $total_duration += (int)$service['service_duration'];
                      }
                  }

                  if ($start && $total_duration > 0) {
                      $end = clone $start;
                      $end->modify("+$total_duration minutes");
                      echo htmlspecialchars($end->format('g:i A'));
                  } else {
                      echo "N/A";
                  }
                ?>
              </td>

              <td><?= htmlspecialchars($row['Appointment_Date']) ?></td>

              <td>
                <?= !empty($row['fullname']) ? htmlspecialchars($row['fullname']) : htmlspecialchars($row['Patient_Name_Custom']) ?>
              </td>

              <td><?= htmlspecialchars($row['name']) ?></td>

              <td>
                <?php
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

                  echo htmlspecialchars(implode(', ', $service_names));
                ?>
              </td>

              <td><span style="color:red;">Cancelled</span></td>
            </tr>
          <?php endwhile; ?>
        <?php else: ?>
          <tr><td colspan="7">No cancelled appointments found.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>




<!-- Cancel Modal -->
<div id="cancelModal" class="modal" style="display:none;">
  <div class="modal-content" style="padding:20px; background:#fff; border-radius:5px; width:400px; margin:auto; margin-top:100px; position:relative;">
    <h2>Cancel Appointment</h2>

    <label for="cancelSelectAppointment">Select Appointment:</label>
    <select id="cancelSelectAppointment">
      <!-- appointment options will be added dynamically via JS -->
    </select>

    <br><br>
    <label for="cancelReason">Reason for Cancellation:</label>
    <textarea id="cancelReason" rows="4" style="width:100%;" placeholder="Enter reason here..." required></textarea>

    <br><br>
    <button id="confirmCancelBtn" style="background-color: red; color: white;">Confirm Cancel</button>
    <button id="closeCancelModalBtn">Close</button>
  </div>
</div>




<!-- Reschedule Modal - SIMPLIFIED WITH SECURITY -->
<div id="rescheduleModal" class="modal" style="display:none;">
  <div class="modal-content" style="position: relative; width:450px; height: auto; margin-left: 500px; margin-top: 50px;">
    <!-- Close button -->
    <a href="dentist_appointment_module.php" class="close-btn" 
       style="position:absolute; top:15px; right:10px; font-size:24px; text-decoration:none; background-color:white;">
      &times;
    </a>

    <h4>Reschedule Appointment</h4>

    <!-- Appointment Selection Dropdown - SECURED -->
    <label for="selectAppointment">Select Appointment:</label>
    <select id="selectAppointment" class="form-control">
      <option value="">Select an appointment</option>
      <?php
      $loggedInDentistId = $_SESSION['dentist_id']; // Get logged-in dentist ID

      $appointments = $conn->query("SELECT a.Appointment_ID, a.start_time, a.Appointment_Date, 
                                  COALESCE(u.fullname, a.Patient_Name_Custom) AS patient_name, 
                                  u.email AS patient_email,
                                  a.Service_ID as Service_Type,
                                  a.Dentist_ID,
                                  d.name AS dentist_name
                                  FROM appointment a 
                                  LEFT JOIN users u ON a.Patient_ID = u.id
                                  LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                                  WHERE a.Appointment_Status NOT IN ('Completed', 'Cancelled')
                                  AND a.Dentist_ID = $loggedInDentistId"); // SECURITY FILTER ADDED

      while ($row = $appointments->fetch_assoc()): 
        // Calculate total duration (keeping your original logic)
        $service_ids = json_decode($row['Service_Type'], true);
        $total_duration = 0;
        if (is_array($service_ids)) {
            $ids_string = implode(',', array_map('intval', $service_ids));
            $query = "SELECT service_duration FROM services WHERE service_ID IN ($ids_string)";
            $result_services = $conn->query($query);
            while ($service = $result_services->fetch_assoc()) {
                $total_duration += (int)$service['service_duration'];
            }
        }
      ?>
        <option value="<?= $row['Appointment_ID'] ?>" 
                data-patient-name="<?= htmlspecialchars($row['patient_name']) ?>"
                data-patient-email="<?= htmlspecialchars($row['patient_email'] ?? '') ?>"
                data-services="<?= htmlspecialchars($row['Service_Type']) ?>"
                data-dentist-id="<?= $row['Dentist_ID'] ?>"
                data-dentist-name="<?= htmlspecialchars($row['dentist_name']) ?>"
                data-duration="<?= $total_duration ?>"> <!-- Keep duration calculation -->
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

      <!-- Date Selection -->
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
      <div style="margin-top:20px; display:flex; justify-content:space-between;">
        <button type="submit" id="submitRescheduleBtn" class="btn btn-primary">Reschedule Appointment</button>
        <button type="button" id="cancelRescheduleBtn" class="btn btn-secondary">Cancel</button>
      </div>
    </form>
  </div>
</div>

<!-- Hidden Service Data for JavaScript (Add this somewhere in your page) -->
<div id="serviceDataContainer" style="display: none;">
    <?php
    // Preload service data for JavaScript
    $services = $conn->query("SELECT service_ID, service_name, service_duration FROM services");
    while ($service = $services->fetch_assoc()): ?>
        <div class="serviceCheckbox" 
             value="<?= $service['service_ID'] ?>" 
             data-service-name="<?= htmlspecialchars($service['service_name']) ?>" 
             data-duration="<?= $service['service_duration'] ?>">
            <span><?= htmlspecialchars($service['service_name']) ?> (<?= $service['service_duration'] ?> mins)</span>
        </div>
    <?php endwhile; ?>
</div>

<script>

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
    fetch('dentist_appointment_module.php')
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
let serviceDataCache = {};

// Preload service data from PHP
function preloadServiceData() {
    const serviceElements = document.querySelectorAll('#serviceDataContainer .serviceCheckbox');
    serviceDataCache = {};
    
    serviceElements.forEach(element => {
        serviceDataCache[element.getAttribute('value')] = {
            name: element.getAttribute('data-service-name'),
            duration: parseInt(element.getAttribute('data-duration')) || 30
        };
    });
    console.log('Service data preloaded:', serviceDataCache);
}

// Call this on page load
document.addEventListener('DOMContentLoaded', function() {
    preloadServiceData();
    
    // Make sure the reschedule button exists before adding event listener
    if (rescheduleBtn) {
        rescheduleBtn.addEventListener('click', function() {
            rescheduleModal.style.display = 'flex';
            rescheduleForm.style.display = 'none'; // Hide form initially
            selectAppointment.value = ''; // Reset selection
        });
    }
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
    const totalDuration = option.getAttribute('data-duration');
    
    // Set basic info
    document.getElementById('original_appointment_id').value = appointmentId;
    reschedulePatientName.value = patientName;
    rescheduleEmail.value = patientEmail || '';
    
    // Set hidden dentist (this enables date/time selection)
    rescheduleDentistId.value = dentistId;
    
    // Set total duration from PHP calculation
    document.getElementById('reschedule_total_duration').value = totalDuration;
    
    // Display services (read-only)
    displayServices(servicesJson);
    
    // Auto-load available dates for this dentist
    loadAvailableDates(dentistId);
}

// Display services names only (duration already calculated in PHP)
function displayServices(servicesJson) {
    let servicesArray = [];
    let serviceNames = [];
    
    try {
        servicesArray = JSON.parse(servicesJson);
        if (!Array.isArray(servicesArray)) {
            servicesArray = servicesJson.split(',').map(id => parseInt(id.trim()));
        }
    } catch (e) {
        servicesArray = servicesJson.split(',').map(id => parseInt(id.trim()));
    }
    
    // Get service names from cache
    servicesArray.forEach(serviceId => {
        if (serviceDataCache[serviceId]) {
            serviceNames.push(serviceDataCache[serviceId].name);
        } else {
            // Fallback if service not found
            serviceNames.push('Service ID: ' + serviceId);
        }
    });
    
    // Display service names
    rescheduleServicesDisplay.innerHTML = serviceNames.join(', ');
    
    console.log('Services displayed:', serviceNames);
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

// Reschedule form submission
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
</script>







  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <script src="dentist_appointment_module.js"></script>
</body>
</html>