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

// -----------------------------
// Basic authentication & input
// -----------------------------
if (!isset($_SESSION['username']) || !isset($_SESSION['user_id'])) {
    echo json_encode(["status" => "error", "message" => "User not logged in."]);
    exit;
}

// Get and sanitize user input
$name = trim($_POST['name'] ?? '');
$email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
$phone = trim($_POST['phone'] ?? '');
$services = $_POST['service'] ?? [];
$preferred_date = trim($_POST['preferred_date'] ?? '');
$preferred_time = trim($_POST['preferred_time'] ?? '');
$total_duration = intval($_POST['total_duration'] ?? 0);
$message = trim($_POST['message'] ?? '');

// Get dentist selections from form
$dentist_selections = $_POST['dentist_for_service'] ?? [];

// Validate required fields
if (
    empty($name) || !$email || empty($phone) ||
    empty($preferred_date) || empty($preferred_time) ||
    empty($services) || $total_duration <= 0
) {
    echo json_encode(["status" => "error", "message" => "Please fill in all required fields."]);
    exit;
}

// Format data
$patient_id = intval($_SESSION['user_id']);
$admin_id = 1;
$appointment_date = date('Y-m-d', strtotime($preferred_date));
$appointment_time = date('H:i:s', strtotime($preferred_time));

// Single confirmation token for all appointments in this booking
$confirmation_token = bin2hex(random_bytes(16));
$confirmation_deadline = date('Y-m-d H:i:s', strtotime('+10 minutes'));
$appointment_status = 'Pending';

// -----------------------------
// Cancel expired 'Pending' appointments
// -----------------------------
$now = date("Y-m-d H:i:s");
$cancel_sql = "
    UPDATE appointment 
    SET Appointment_Status = 'Cancelled' 
    WHERE Appointment_Status = 'Pending' 
    AND confirmation_deadline < ?
";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("s", $now);
$cancel_stmt->execute();
$cancel_stmt->close();

// -----------------------------
// CRITICAL FIX: Final availability validation BEFORE booking
// -----------------------------
function validateDentistAvailability($conn, $dentist_id, $date, $start_time, $end_time) {
    // Check if dentist has availability in dentistavailability table
    $availability_sql = "
        SELECT 1 FROM dentistavailability 
        WHERE Dentist_ID = ? 
        AND available_date = ? 
        AND available_time <= ? 
        AND end_time >= ?
        LIMIT 1
    ";
    $stmt = $conn->prepare($availability_sql);
    $stmt->bind_param("isss", $dentist_id, $date, $start_time, $end_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $is_available = $result->num_rows > 0;
    $stmt->close();
    
    return $is_available;
}

function checkAppointmentConflict($conn, $dentist_id, $date, $start_time, $end_time) {
    // Check for overlapping appointments
    $conflict_sql = "
        SELECT Appointment_ID 
        FROM appointment
        WHERE Dentist_ID = ?
        AND Appointment_Date = ?
        AND Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
        AND ((start_time < ? AND end_time > ?))
        LIMIT 1
    ";
    $stmt = $conn->prepare($conflict_sql);
    $stmt->bind_param("isss", $dentist_id, $date, $end_time, $start_time);
    $stmt->execute();
    $result = $stmt->get_result();
    $has_conflict = $result->num_rows > 0;
    $stmt->close();
    
    return $has_conflict;
}

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
        echo json_encode(['status' => 'error', 'message' => "No dentist available for selected service ID: $sid"]);
        exit;
    }
}

// -----------------------------
// NEW: Build sequential appointment blocks using selected dentists
// -----------------------------
$appointments_to_insert = []; 
$current_time = $appointment_time;

foreach ($services_int as $sid) {
    $possible = $service_map[$sid];
    $assigned_dentist = null;
    $duration = 0;
    $service_booked = false;

    // 1. Use the dentist selected by user (from dentist_for_service parameters)
    if (isset($dentist_selections[$sid]) && !empty($dentist_selections[$sid])) {
        $selected_dentist_id = intval($dentist_selections[$sid]);
        
        // Verify selected dentist is qualified for this service
        $dentist_qualified = false;
        foreach ($possible as $p) {
            if ($p['dentist_id'] === $selected_dentist_id) {
                $dentist_qualified = true;
                $duration = $p['duration'];
                break;
            }
        }
        
        if ($dentist_qualified) {
            // Calculate end time for this service
            $end_time_obj = DateTime::createFromFormat('H:i:s', $current_time);
            if (!$end_time_obj) {
                $end_time_obj = DateTime::createFromFormat('H:i', $current_time);
            }
            $end_time_obj->modify("+{$duration} minutes");
            $block_end_time = $end_time_obj->format('H:i:s');
            
            // CRITICAL: Validate availability for selected dentist
            $is_available = validateDentistAvailability($conn, $selected_dentist_id, $appointment_date, $current_time, $block_end_time);
            $has_conflict = checkAppointmentConflict($conn, $selected_dentist_id, $appointment_date, $current_time, $block_end_time);
            
            if ($is_available && !$has_conflict) {
                $assigned_dentist = $selected_dentist_id;
                $service_booked = true;
            }
        }
    }
    
    // 2. Auto-assign if no specific selection or selected dentist not available
    if (!$service_booked) {
        foreach ($possible as $p) {
            $dentist_id = $p['dentist_id'];
            $duration = $p['duration'];
            
            // Calculate end time with actual duration
            $end_time_obj = DateTime::createFromFormat('H:i:s', $current_time);
            if (!$end_time_obj) {
                $end_time_obj = DateTime::createFromFormat('H:i', $current_time);
            }
            $end_time_obj->modify("+{$duration} minutes");
            $block_end_time = $end_time_obj->format('H:i:s');
            
            // Check availability and conflicts
            $is_available = validateDentistAvailability($conn, $dentist_id, $appointment_date, $current_time, $block_end_time);
            $has_conflict = checkAppointmentConflict($conn, $dentist_id, $appointment_date, $current_time, $block_end_time);
            
            if ($is_available && !$has_conflict) {
                $assigned_dentist = $dentist_id;
                $service_booked = true;
                break;
            }
        }
    }
    
    // 3. If still no dentist available, throw error
    if (!$service_booked || $assigned_dentist === null) {
        echo json_encode([
            'status' => 'error',
            'message' => "No available dentist found for service '{$service_names_map[$sid]}' at the selected date/time. Please refer to Dentist Availability table and choose a different date/time."
        ]);
        exit;
    }

    // Final end time calculation with correct duration
    $end_time_obj = DateTime::createFromFormat('H:i:s', $current_time);
    if (!$end_time_obj) {
        $end_time_obj = DateTime::createFromFormat('H:i', $current_time);
    }
    $end_time_obj->modify("+{$duration} minutes");
    $block_end_time = $end_time_obj->format('H:i:s');

    $appointments_to_insert[] = [
        'Dentist_ID' => intval($assigned_dentist),
        'Service_ID' => intval($sid),
        'start_time' => $current_time,
        'end_time' => $block_end_time,
        'duration' => $duration,
        'service_name' => $service_names_map[$sid]
    ];

    // Move to next time slot (sequential booking)
    $current_time = $block_end_time;
}

// -----------------------------
// FINAL CONFLICT CHECK (Double verification)
// -----------------------------
foreach ($appointments_to_insert as $block) {
    $has_conflict = checkAppointmentConflict($conn, $block['Dentist_ID'], $appointment_date, $block['start_time'], $block['end_time']);
    if ($has_conflict) {
        echo json_encode([
            'status' => 'error',
            'message' => "Time slot is no longer available. Please refresh and try again."
        ]);
        exit;
    }
}

// -----------------------------
// NEW: Insert all sequential appointment blocks (transactional)
// -----------------------------
try {
    $conn->begin_transaction();

    $insert_sql = "
        INSERT INTO appointment 
        (Patient_ID, Dentist_ID, Service_ID, Appointment_Date, start_time, end_time, Appointment_Status, Admin_ID, confirmation_token, confirmation_deadline)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    $insert_stmt = $conn->prepare($insert_sql);

    $inserted_appointment_ids = [];
    $appointment_count = 0;
    
    foreach ($appointments_to_insert as $block) {
        $appointment_count++;
        $p = $patient_id;
        $d = $block['Dentist_ID'];
        $s = $block['Service_ID'];
        $ad = $appointment_date;
        $st = $block['start_time'];
        $et = $block['end_time'];
        $astatus = $appointment_status;
        $aid = $admin_id;
        $ctoken = $confirmation_token;
        $cdeadline = $confirmation_deadline;

        $insert_stmt->bind_param(
            "iiisssssss",
            $p, $d, $s, $ad, $st, $et, $astatus, $aid, $ctoken, $cdeadline
        );

        if (!$insert_stmt->execute()) {
            throw new Exception("Insert failed for service {$block['service_name']}: " . $insert_stmt->error);
        }
        $inserted_appointment_ids[] = $conn->insert_id;
    }

    $conn->commit();
    $insert_stmt->close();
    
    // ===== NOTIFICATION TRIGGER FOR NEW APPOINTMENT =====
    createAdminNotification(
        'appointments',
        'New Sequential Appointment Booking',
        'Patient ' . $name . ' has booked ' . $appointment_count . ' sequential services for ' . $appointment_date,
        'high',
        'appointment_module.php',
        $inserted_appointment_ids[0] // First appointment ID
    );
    // ===== END NOTIFICATION TRIGGER =====
    
} catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Failed to book appointments: ' . $e->getMessage()]);
    exit;
}

// -----------------------------
// Handle uploaded files (attach to first appointment)
// -----------------------------
if (!empty($_FILES['files']['name'][0])) {
    $first_appointment_id = $inserted_appointment_ids[0] ?? null;
    $upload_dir = 'uploads/appointments/';
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);

    $file_count = count($_FILES['files']['name']);
    for ($i = 0; $i < $file_count; $i++) {
        if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
            $original_name = basename($_FILES['files']['name'][$i]);
            $file_type = $_FILES['files']['type'][$i];
            $file_ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));
            $file_size = $_FILES['files']['size'][$i];
            $unique_name = uniqid() . '.' . $file_ext;
            $target_path = $upload_dir . $unique_name;

            // Auto-categorize
            $category = 'documents';
            $file_type_lower = strtolower($file_type);
            if (strpos($file_type_lower, 'image') !== false) {
                $category = 'images';
            } elseif (strpos($original_name, 'consent') !== false || strpos($original_name, 'form') !== false) {
                $category = 'consentForms';
            } elseif ($file_ext === 'pdf') {
                $category = 'documents';
            } elseif (in_array($file_ext, ['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'])) {
                $category = 'documents';
            } else {
                $category = 'others';
            }

            if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $target_path)) {
                $check_stmt = $conn->prepare("SELECT Files_ID FROM files WHERE Appointment_ID = ? AND File_Name = ?");
                $check_stmt->bind_param("is", $first_appointment_id, $original_name);
                $check_stmt->execute();
                $check_res = $check_stmt->get_result();

                if ($check_res->num_rows === 0) {
                    $file_stmt = $conn->prepare("INSERT INTO files (Patient_ID, Appointment_ID, File_Name, File_Path, category, File_Type, File_Size) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    $file_stmt->bind_param("iissssi", $patient_id, $first_appointment_id, $original_name, $target_path, $category, $file_type, $file_size);
                    $file_stmt->execute();
                    $new_file_id = $file_stmt->insert_id;
                    $file_stmt->close();
                    
                    // ===== NOTIFICATION TRIGGER FOR PATIENT FILE SUBMISSION =====
                    createAdminNotification(
                        'documents',
                        'Patient File Submitted',
                        'Patient ' . $name . ' has submitted a file: ' . $original_name,
                        'medium',
                        'documents_files_module.php',
                        $new_file_id
                    );
                    // ===== END NOTIFICATION TRIGGER =====
                }
                $check_stmt->close();
            }
        }
    }
}

// -----------------------------
// NEW: Build email contents with sequential timeline
// -----------------------------
$service_lines = '';
$overall_start_time = '';
$overall_end_time = '';

foreach ($appointments_to_insert as $idx => $block) {
    $sname = htmlspecialchars($block['service_name']);
    
    // Get dentist name
    $dsql = "SELECT name FROM dentists WHERE Dentist_ID = ?";
    $dstmt = $conn->prepare($dsql);
    $dstmt->bind_param("i", $block['Dentist_ID']);
    $dstmt->execute();
    $dres = $dstmt->get_result();
    $drow = $dres->fetch_assoc();
    $dentist_name = $drow['name'] ?? "Dentist #{$block['Dentist_ID']}";
    $dstmt->close();

    $start_12hr = date("g:i A", strtotime($block['start_time']));
    $end_12hr = date("g:i A", strtotime($block['end_time']));

    // Set overall start and end times
    if ($idx === 0) $overall_start_time = $start_12hr;
    if ($idx === count($appointments_to_insert) - 1) $overall_end_time = $end_12hr;

    $service_lines .= "
        <div style='margin-bottom:12px; padding:10px; background:#f8f9fa; border-radius:5px;'>
            <strong>{$sname}</strong><br>
            Time: {$start_12hr} - {$end_12hr}<br>
            Dentist: Dr. {$dentist_name}<br>
            Duration: {$block['duration']} minutes
        </div>
    ";
}

// Confirmation link
$confirm_link = "http://localhost/UmipigDentalClinic/confirm_appointment.php?token={$confirmation_token}";

// -----------------------------
// Send email with sequential booking details
// -----------------------------
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
    $mail->addAddress($email, $name);

    $mail->isHTML(true);
    $mail->Subject = 'Appointment Confirmation - Umipig Dental Clinic';

    $mail->Body = "
        <h2>Appointment Confirmation</h2>
        <p>Dear {$name},</p>
        <p>Thank you for scheduling your sequential appointment with Umipig Dental Clinic. Below are the details of your booking:</p>
        
        <div style='background:#e8f5e9; padding:15px; border-radius:8px; margin-bottom:20px;'>
            <strong>Overall Appointment:</strong><br>
            Date: {$appointment_date}<br>
            Time: {$overall_start_time} - {$overall_end_time}<br>
            Total Services: " . count($appointments_to_insert) . "<br>
            Total Duration: {$total_duration} minutes
        </div>
        
        <h3>Service Schedule:</h3>
        {$service_lines}
        
        <p>
            ✅ <strong>To confirm your appointment, please click the link below:</strong><br>
            <a href='{$confirm_link}' style='color: #2563eb; text-decoration: none; font-weight: bold;'>{$confirm_link}</a><br><br>
            ⚠️ <strong>Important:</strong> This appointment will be automatically cancelled if not confirmed within <span style='color:red;'>10 minutes</span>.
        </p>
        
        <p>Best regards,<br>Umipig Dental Clinic Team</p>
    ";

    $mail->send();
    echo json_encode(["status" => "success", "message" => "Appointment(s) booked successfully! Please check your email and click the confirmation link."]);
} catch (Exception $e) {
    echo json_encode(["status" => "success", "message" => "Appointment(s) booked, but email could not be sent. Please contact the clinic to confirm your appointment."]);
}

$conn->close();
exit;
?>