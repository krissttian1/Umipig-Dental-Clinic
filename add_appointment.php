<?php
session_start();
require 'db_connection.php';

// Load PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
require 'vendor/autoload.php';

// Auto-cancel pending appointments past deadline
$now = date('Y-m-d H:i:s');
$auto_cancel_sql = "
    UPDATE appointment 
    SET Appointment_Status = 'Cancelled' 
    WHERE Appointment_Status = 'Pending' 
    AND confirmation_deadline IS NOT NULL 
    AND confirmation_deadline < ?
";
$auto_cancel_stmt = $conn->prepare($auto_cancel_sql);
$auto_cancel_stmt->bind_param("s", $now);
$auto_cancel_stmt->execute();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // -----------------------------
    // Collect and sanitize input data
    // -----------------------------
    $patient_name = trim($_POST['Patient_Name_Custom'] ?? '');
    $email_input = trim($_POST['email'] ?? '');
    $email = (!empty($email_input)) ? filter_var($email_input, FILTER_VALIDATE_EMAIL) : '';
    $services = $_POST['services'] ?? []; // Array of service IDs
    $preferred_date = trim($_POST['preferred_date'] ?? '');
    $preferred_time = trim($_POST['preferred_time'] ?? '');
    $total_duration = intval($_POST['total_duration'] ?? 0);
    $status = trim($_POST['status'] ?? 'Pending');
    
    // Multiple dentist assignments (like working form)
    $dentist_assignments = [];
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'dentist_for_service[') === 0) {
            preg_match('/dentist_for_service\[(\d+)\]/', $key, $matches);
            if (isset($matches[1])) {
                $service_id = intval($matches[1]);
                $dentist_id = intval($value);
                if ($dentist_id > 0) {
                    $dentist_assignments[$service_id] = $dentist_id;
                }
            }
        }
    }

    // -----------------------------
    // Validation
    // -----------------------------
    if (empty($patient_name) || empty($services) || empty($preferred_date) || 
        empty($preferred_time) || $total_duration <= 0) {
        echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
        exit;
    }

    // Format dates/times
    $appointment_date = date('Y-m-d', strtotime($preferred_date));
    $appointment_time = date('H:i:s', strtotime($preferred_time));
    $admin_id = 1; // Admin user ID

    // -----------------------------
    // Load services + available dentists
    // -----------------------------
    $services_int = array_map('intval', $services);
    $placeholders = implode(',', array_fill(0, count($services_int), '?'));
    $types = str_repeat('i', count($services_int));

    $sql = "
        SELECT s.service_ID, s.service_name, s.service_duration, ds.dentist_id
        FROM services s
        JOIN dentist_services ds ON ds.service_id = s.service_ID
        WHERE s.service_ID IN ($placeholders)
    ";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param($types, ...$services_int);
    $stmt->execute();
    $result = $stmt->get_result();

    $service_map = [];
    $service_names_map = [];
    while ($row = $result->fetch_assoc()) {
        $sid = intval($row['service_ID']);
        $service_map[$sid][] = [
            'dentist_id' => intval($row['dentist_id']),
            'duration' => intval($row['service_duration'])
        ];
        $service_names_map[$sid] = $row['service_name'];
    }
    $stmt->close();

    // Ensure every selected service has at least one dentist
    foreach ($services_int as $sid) {
        if (!isset($service_map[$sid]) || empty($service_map[$sid])) {
            echo json_encode(['status' => 'error', 'message' => "No dentist available for selected service."]);
            exit;
        }
    }

    // -----------------------------
    // Build consecutive appointment blocks
    // -----------------------------
    $appointments_to_insert = []; 
    $current_time = $appointment_time;

    foreach ($services_int as $sid) {
        $possible = $service_map[$sid];
        
        // Use assigned dentist or auto-assign
        $assigned_dentist = $dentist_assignments[$sid] ?? null;
        $duration = 0;
        
        if ($assigned_dentist) {
            // Verify assigned dentist can perform this service
            $valid_dentist = false;
            foreach ($possible as $p) {
                if ($p['dentist_id'] === $assigned_dentist) {
                    $duration = $p['duration'];
                    $valid_dentist = true;
                    break;
                }
            }
            if (!$valid_dentist) {
                $assigned_dentist = null;
            }
        }
        
        // Auto-assign if no valid assignment
        if (!$assigned_dentist) {
            $assigned_dentist = $possible[0]['dentist_id'];
            $duration = $possible[0]['duration'];
        }

        // Calculate end time (no buffer)
        $end_time_obj = DateTime::createFromFormat('H:i:s', $current_time);
        if (!$end_time_obj) {
            $end_time_obj = DateTime::createFromFormat('H:i', $current_time);
        }
        $end_time_obj->modify("+{$duration} minutes");
        $block_end_time = $end_time_obj->format('H:i:s');

        $appointments_to_insert[] = [
            'Dentist_ID' => $assigned_dentist,
            'Service_ID' => $sid,
            'start_time' => $current_time,
            'end_time' => $block_end_time
        ];

        $current_time = $block_end_time;
    }

    // -----------------------------
    // Conflict checking
    // -----------------------------
    $conflict_sql = "
        SELECT Appointment_ID 
        FROM appointment
        WHERE Dentist_ID = ?
          AND Appointment_Date = ?
          AND Appointment_Status NOT IN ('Cancelled', 'Completed')
          AND (start_time < ? AND end_time > ?)
        LIMIT 1
    ";
    $conflict_stmt = $conn->prepare($conflict_sql);

    foreach ($appointments_to_insert as $block) {
        $d_id = $block['Dentist_ID'];
        $b_start = $block['start_time'];
        $b_end = $block['end_time'];

        $conflict_stmt->bind_param("isss", $d_id, $appointment_date, $b_end, $b_start);
        $conflict_stmt->execute();
        $conflict_result = $conflict_stmt->get_result();

        if ($conflict_result && $conflict_result->num_rows > 0) {
            echo json_encode([
                'status' => 'error',
                'message' => "Conflict detected: Dentist is not available for {$b_start} - {$b_end} on {$appointment_date}. Please choose another time."
            ]);
            $conflict_stmt->close();
            exit;
        }
    }
    $conflict_stmt->close();

    // -----------------------------
    // Insert appointment blocks
    // -----------------------------
    try {
        $conn->begin_transaction();

        $insert_sql = "
            INSERT INTO appointment 
            (Patient_Name_Custom, Dentist_ID, Service_ID, Appointment_Date, start_time, end_time, 
             Appointment_Status, Admin_ID, optional_email)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ";
        $insert_stmt = $conn->prepare($insert_sql);

        foreach ($appointments_to_insert as $block) {
            $d_id = $block['Dentist_ID'];
            $s_id = $block['Service_ID'];
            $st = $block['start_time'];
            $et = $block['end_time'];

            $insert_stmt->bind_param(
                "siissssis", // 9 parameters now (includes optional_email)
                $patient_name, $d_id, $s_id, $appointment_date, $st, $et, $status, $admin_id, $email
            );

            if (!$insert_stmt->execute()) {
                throw new Exception("Insert failed: " . $insert_stmt->error);
            }
        }

        $conn->commit();
        $insert_stmt->close();
        
        // Get dentist names for email
        $dentist_names = [];
        $dentist_ids = array_unique(array_column($appointments_to_insert, 'Dentist_ID'));
        if (!empty($dentist_ids)) {
            $placeholders = implode(',', array_fill(0, count($dentist_ids), '?'));
            $dentist_sql = "SELECT Dentist_ID, name FROM dentists WHERE Dentist_ID IN ($placeholders)";
            $dentist_stmt = $conn->prepare($dentist_sql);
            $dentist_stmt->bind_param(str_repeat('i', count($dentist_ids)), ...$dentist_ids);
            $dentist_stmt->execute();
            $result = $dentist_stmt->get_result();
            while ($row = $result->fetch_assoc()) {
                $dentist_names[$row['Dentist_ID']] = $row['name'];
            }
            $dentist_stmt->close();
        }

        // -----------------------------
        // Send optional email (informational only)
        // -----------------------------
        if ($email) {
            $service_lines = '';
            foreach ($appointments_to_insert as $block) {
                $sname = htmlspecialchars($service_names_map[$block['Service_ID']]);
                $dentist_name = $dentist_names[$block['Dentist_ID']] ?? "Dentist";
                
                $service_lines .= "
                    <div style='margin-bottom:12px; padding:10px; background:#f9f9f9; border-radius:5px;'>
                        <strong>{$sname}</strong><br>
                        Date: {$appointment_date}<br>
                        Time: " . date('h:i A', strtotime($block['start_time'])) . " - " . 
                        date('h:i A', strtotime($block['end_time'])) . "<br>
                        Dentist: {$dentist_name}
                    </div>
                ";
            }

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
                $mail->addAddress($email, $patient_name);

                $mail->isHTML(true);
                $mail->Subject = 'Appointment Scheduled - Umipig Dental Clinic';

                $mail->Body = "
                    <h2>Appointment Scheduled</h2>
                    <p>Dear {$patient_name},</p>
                    <p>Your appointment has been scheduled with Umipig Dental Clinic. Below are your appointment details:</p>
                    {$service_lines}
                    <p>Please arrive 10 minutes before your scheduled time.</p>
                    <p>Best regards,<br>Umipig Dental Clinic Team</p>
                ";

                $mail->send();
                $email_status = "Email sent successfully.";
            } catch (Exception $e) {
                $email_status = "Appointment booked, but email could not be sent.";
            }
        } else {
            $email_status = "No email provided - appointment booked without notification.";
        }

        echo json_encode([
            "status" => "success", 
            "message" => "Appointment(s) booked successfully. {$email_status}"
        ]);

    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['status' => 'error', 'message' => 'Failed to book appointments: ' . $e->getMessage()]);
    }

    $conn->close();
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
}
?>
