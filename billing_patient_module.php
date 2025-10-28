<?php
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

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php");
    exit;
}

// ✅ NEW: Get service prices from services table
function getServicePricesFromIDs($conn, $service_ids) {
    $service_prices = [];

    if (empty($service_ids)) {
        return $service_prices;
    }

    // Try decoding as JSON, fallback to comma-separated
    $decoded = json_decode($service_ids, true);
    if (is_array($decoded)) {
        $ids = $decoded;
    } else {
        $ids = explode(',', $service_ids);
    }

    // Clean IDs (remove empty/non-numeric)
    $ids = array_filter(array_map('intval', $ids));

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT service_name, price FROM services WHERE service_ID IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $service_prices[$row['service_name']] = $row['price'];
        }

        $stmt->close();
    }

    return $service_prices;
}

// ✅ SIMPLIFIED: Directly get service names from services table
function getServiceNamesFromIDs($conn, $service_ids) {
    $service_names = [];

    if (empty($service_ids)) {
        return $service_names;
    }

    // Try decoding as JSON, fallback to comma-separated
    $decoded = json_decode($service_ids, true);
    if (is_array($decoded)) {
        $ids = $decoded;
    } else {
        $ids = explode(',', $service_ids);
    }

    // Clean IDs (remove empty/non-numeric)
    $ids = array_filter(array_map('intval', $ids));

    if (!empty($ids)) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $types = str_repeat('i', count($ids));
        $sql = "SELECT service_name FROM services WHERE service_ID IN ($placeholders)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param($types, ...$ids);
        $stmt->execute();
        $result = $stmt->get_result();

        while ($row = $result->fetch_assoc()) {
            $service_names[] = $row['service_name'];
        }

        $stmt->close();
    }

    return $service_names;
}

// ✅ FIXED: Get ALL dentists from ALL grouped appointments (SIMPLIFIED - using same pattern as billing dashboard)
function getDentistsForGroupedAppointments($conn, $grouped_appointments) {
    $all_dentist_names = [];
    
    foreach ($grouped_appointments as $appointment) {
        // DIRECTLY use the dentist_name from the appointment (same as billing dashboard)
        if (!empty($appointment['dentist_name'])) {
            $all_dentist_names[] = $appointment['dentist_name'];
        }
    }
    
    // Remove duplicates and return (same as billing dashboard)
    $all_dentist_names = array_unique($all_dentist_names);
    return $all_dentist_names;
}

// ✅ FIXED: Get ALL appointments for this patient that are grouped together (handles custom names)
function getGroupedAppointments($conn, $bill_id, $patient_id) {
    $grouped_appointments = [];
    
    // First, get the main appointment to find its grouping
    $main_appointment_sql = "SELECT * FROM appointment WHERE Appointment_ID = ?";
    $main_stmt = $conn->prepare($main_appointment_sql);
    $main_stmt->bind_param("i", $bill_id);
    $main_stmt->execute();
    $main_result = $main_stmt->get_result();
    $main_appointment = $main_result->fetch_assoc();
    
    if (!$main_appointment) {
        return $grouped_appointments;
    }
    
    // Get patient identifier - handle both registered users and custom names
    $patient_identifier = $main_appointment['Patient_ID'] ?: $main_appointment['Patient_Name_Custom'];
    $is_custom_name = empty($main_appointment['Patient_ID']);
    
    // Get ALL appointments for this patient with Confirmed/Completed status on the same date
    $all_appointments_sql = "
        SELECT 
            a.Appointment_ID,
            a.Appointment_Date,
            a.Service_ID,
            a.start_time,
            a.end_time,
            a.Appointment_Status,
            a.Patient_Name_Custom,
            COALESCE(u.fullname, a.Patient_Name_Custom) as patient_name,
            u.id as patient_id,
            d.name as dentist_name
        FROM appointment a
        LEFT JOIN users u ON a.Patient_ID = u.id
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        WHERE a.Appointment_Status IN ('Confirmed', 'Completed')
        AND a.Appointment_Date = ?
    ";
    
    // Add condition based on whether it's a registered user or custom name
    if ($is_custom_name) {
        $all_appointments_sql .= " AND a.Patient_Name_Custom = ?";
        $all_stmt = $conn->prepare($all_appointments_sql);
        $all_stmt->bind_param("ss", $main_appointment['Appointment_Date'], $patient_identifier);
    } else {
        $all_appointments_sql .= " AND a.Patient_ID = ?";
        $all_stmt = $conn->prepare($all_appointments_sql);
        $all_stmt->bind_param("si", $main_appointment['Appointment_Date'], $patient_identifier);
    }
    
    $all_appointments_sql .= " ORDER BY a.start_time ASC";
    
    $all_stmt->execute();
    $all_result = $all_stmt->get_result();
    
    while ($row = $all_result->fetch_assoc()) {
        $grouped_appointments[] = $row;
    }
    
    return $grouped_appointments;
}

// ✅ NEW: Send payment confirmation email
function sendPaymentConfirmationEmail($conn, $bill_id, $patient_id, $service_updates, $discounted_amount, $payment_method, $payment_status) {
    try {
        // Get patient email and details - handle both registered users and custom names
        if ($patient_id > 0) {
            // Registered user
            $patient_sql = "
                SELECT 
                    u.email,
                    COALESCE(pr.name, u.fullname) as name
                FROM users u 
                LEFT JOIN patient_records pr ON u.id = pr.user_id
                WHERE u.id = ?
            ";
            $patient_stmt = $conn->prepare($patient_sql);
            $patient_stmt->bind_param("i", $patient_id);
            $patient_stmt->execute();
            $patient_result = $patient_stmt->get_result();
            $patient = $patient_result->fetch_assoc();
        } else {
            // Custom name - get from billing table
            $billing_sql = "SELECT patient_name FROM billing WHERE bill_id = ?";
            $billing_stmt = $conn->prepare($billing_sql);
            $billing_stmt->bind_param("i", $bill_id);
            $billing_stmt->execute();
            $billing_result = $billing_stmt->get_result();
            $billing = $billing_result->fetch_assoc();
            
            $patient = [
                'name' => $billing['patient_name'],
                'email' => null // No email for custom names
            ];
        }
        
        if (!$patient || empty($patient['email'])) {
            error_log("No email found for patient ID: " . $patient_id);
            return false; // Don't send email if no email address
        }

        // Get billing details
        $billing_sql = "SELECT * FROM billing WHERE bill_id = ?";
        $billing_stmt = $conn->prepare($billing_sql);
        $billing_stmt->bind_param("i", $bill_id);
        $billing_stmt->execute();
        $billing_result = $billing_stmt->get_result();
        $billing = $billing_result->fetch_assoc();

        // Build services HTML
        $services_html = '';
        $subtotal = 0;
        foreach ($service_updates as $service) {
            $services_html .= "
                <tr>
                    <td style='padding: 8px; border-bottom: 1px solid #eee;'>{$service['description']}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: center;'>{$service['quantity']}</td>
                    <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>₱" . number_format($service['amount'], 2) . "</td>
                    <td style='padding: 8px; border-bottom: 1px solid #eee; text-align: right;'>₱" . number_format($service['total'], 2) . "</td>
                </tr>
            ";
            $subtotal += $service['total'];
        }

        // Calculate discount amount
        $discount_amount = $subtotal - $discounted_amount;

        // ✅ NEW: Dynamic status styling and messaging
        $status_color = '';
        $status_message = '';
        
        if ($payment_status === 'Paid') {
            $status_color = '#28a745'; // Green
            $status_message = 'Your payment has been successfully processed. Thank you for choosing Umipig Dental Clinic!';
        } else {
            $status_color = '#f59e0b'; // Orange/Amber for Partial
            $status_message = 'Your partial payment has been processed. Some services may still require additional payment.';
        }

        $mail = new PHPMailer(true);
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'kristianespinase01@gmail.com';
        $mail->Password = 'upin izwz iker gbou';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        $mail->setFrom('UmipigDentalClinic@gmail.com', 'Umipig Dental Clinic');
        $mail->addAddress($patient['email'], $patient['name']);

        $mail->isHTML(true);
        $mail->Subject = 'Payment Confirmation - Invoice #' . str_pad($bill_id, 3, '0', STR_PAD_LEFT);

        $mail->Body = "
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background: linear-gradient(135deg, #5b7ce6 0%, #4a68d9 100%); color: white; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
                    .content { background: #f9f9f9; padding: 20px; border-radius: 0 0 8px 8px; }
                    .invoice-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
                    .invoice-table th { background: #e9ecef; padding: 12px; text-align: left; font-weight: bold; }
                    .total-section { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-top: 20px; }
                    .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee; color: #666; }
                </style>
            </head>
            <body>
                <div class='container'>
                    <div class='header'>
                        <h1>Umipig Dental Clinic</h1>
                        <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
                    </div>
                    
                    <div class='content'>
                        <h2>E-receipt</h2>
                        <p>Dear <strong>{$patient['name']}</strong>,</p>
                        <p>Thank you for your payment. Your transaction has been processed successfully.</p>
                        
                        <div style='background: white; padding: 20px; border-radius: 5px; margin: 20px 0;'>
                            <h3>Invoice Details</h3>
                            <p><strong>Invoice #:</strong> " . str_pad($bill_id, 3, '0', STR_PAD_LEFT) . "</p>
                            <p><strong>Payment Date:</strong> " . date('F j, Y') . "</p>
                            <p><strong>Payment Method:</strong> {$payment_method}</p>
                        </div>

                        <table class='invoice-table'>
                            <thead>
                                <tr>
                                    <th>Description</th>
                                    <th>Qty</th>
                                    <th>Amount</th>
                                    <th>Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                {$services_html}
                            </tbody>
                        </table>

                        <div class='total-section'>
                            <div style='display: flex; justify-content: space-between; margin-bottom: 8px;'>
                                <span>Subtotal:</span>
                                <span>₱" . number_format($subtotal, 2) . "</span>
                            </div>
                            " . ($discount_amount > 0 ? "
                            <div style='display: flex; justify-content: space-between; margin-bottom: 8px; color: #dc3545;'>
                                <span>Discount:</span>
                                <span>-₱" . number_format($discount_amount, 2) . "</span>
                            </div>
                            " : "") . "
                            <div style='display: flex; justify-content: space-between; font-size: 18px; font-weight: bold; color: #2c5aa0;'>
                                <span>Total Paid:</span>
                                <span>₱" . number_format($discounted_amount, 2) . "</span>
                            </div>
                        </div>

                        <div style='background: #e7f3ff; padding: 15px; border-radius: 5px; margin-top: 20px;'>
                            <h4 style='margin-top: 0; color: #2c5aa0;'>Payment Status: <span style='color: {$status_color};'>{$payment_status}</span></h4>
                            <p style='margin-bottom: 0;'>{$status_message}</p>
                        </div>
                    </div>
                    
                    <div class='footer'>
                        <p><strong>Umipig Dental Clinic</strong><br>
                        2nd Floor, Village Eats Food Park, Bldg.,<br>
                        #9 Village East Executive Homes 1900 Cainta, Philippines<br>
                        Contact Number: +63 915 828 9869</p>
                        <p style='font-size: 12px; color: #999;'>
                            This is an automated email. Please do not reply to this message.
                        </p>
                    </div>
                </div>
            </body>
            </html>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Email sending failed: " . $e->getMessage());
        return false;
    }
}

// -----------------------------
// Get Bill and Appointment Data
// -----------------------------
$bill_id = isset($_GET['bill_id']) ? (int)$_GET['bill_id'] : 0;
$patient_id = isset($_GET['patient_id']) ? (int)$_GET['patient_id'] : null;

if ($bill_id === 0) {
    header("Location: billing_module.php");
    exit;
}

// Get appointment info
$appointment_sql = "
    SELECT 
        a.Appointment_ID,
        a.Appointment_Date,
        a.Service_ID AS service_ids,
        a.Appointment_Status,
        COALESCE(u.fullname, a.Patient_Name_Custom) AS patient_name,
        u.id AS patient_id,
        d.name AS dentist_name
    FROM appointment a
    LEFT JOIN users u ON a.Patient_ID = u.id
    LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
    WHERE a.Appointment_ID = ?
";
$appointment_stmt = $conn->prepare($appointment_sql);
$appointment_stmt->bind_param("i", $bill_id);
$appointment_stmt->execute();
$appointment_result = $appointment_stmt->get_result();

if ($appointment_result->num_rows === 0) {
    header("Location: billing_module.php");
    exit;
}
$appointment = $appointment_result->fetch_assoc();

// ✅ FIXED: Get ALL grouped appointments for this patient
$grouped_appointments = getGroupedAppointments($conn, $bill_id, $patient_id);

// ✅ FIXED: Get ALL dentists from ALL grouped appointments using the SIMPLIFIED function
$all_dentists = getDentistsForGroupedAppointments($conn, $grouped_appointments);
$dentists_display = !empty($all_dentists) ? implode(', ', $all_dentists) : 'No dentist assigned';

// Check for existing billing
$billing_check_sql = "SELECT * FROM billing WHERE appointment_id = ?";
$billing_check_stmt = $conn->prepare($billing_check_sql);
$billing_check_stmt->bind_param("i", $bill_id);
$billing_check_stmt->execute();
$billing_result = $billing_check_stmt->get_result();

if ($billing_result->num_rows === 0) {
    // ✅ FIXED: Get ALL services from ALL grouped appointments
    $all_service_names = [];
    foreach ($grouped_appointments as $appt) {
        $service_names = getServiceNamesFromIDs($conn, $appt['Service_ID']);
        $all_service_names = array_merge($all_service_names, $service_names);
    }
    $all_service_names = array_unique($all_service_names);
    $services_display = !empty($all_service_names) ? implode(', ', $all_service_names) : 'Dental Consultation';

    // ✅ FIXED: Handle both registered users and custom names for patient_id
    $patient_id_for_billing = $appointment['patient_id'] ?: NULL;
    $patient_name_for_billing = $appointment['patient_name'];

    $insert_sql = "
        INSERT INTO billing (appointment_id, patient_id, patient_name, appointment_date, services, total_amount, payment_status)
        VALUES (?, ?, ?, ?, ?, 0, 'Unpaid')
    ";
    $insert_stmt = $conn->prepare($insert_sql);
    $insert_stmt->bind_param(
        "iisss", // Changed to "i" for integer (including NULL)
        $appointment['Appointment_ID'],
        $patient_id_for_billing,
        $patient_name_for_billing,
        $appointment['Appointment_Date'],
        $services_display
    );
    $insert_stmt->execute();
    $billing_id = $insert_stmt->insert_id;
    $insert_stmt->close();

    // Get the newly created billing record
    $bill_sql = "
        SELECT 
            b.*,
            a.Service_ID AS service_ids,
            a.Appointment_Status,
            d.name AS dentist_name
        FROM billing b
        LEFT JOIN appointment a ON b.appointment_id = a.Appointment_ID
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        WHERE b.bill_id = ?
    ";
    $bill_stmt = $conn->prepare($bill_sql);
    $bill_stmt->bind_param("i", $billing_id);
    $bill_stmt->execute();
    $bill_result = $bill_stmt->get_result();
    $bill = $bill_result->fetch_assoc();
    
    // ✅ FIXED: Add ALL dentists to the bill array
    $bill['all_dentists'] = $dentists_display;
} else {
    // Get existing billing data
    $bill_sql = "
        SELECT 
            b.*,
            a.Service_ID AS service_ids,
            a.Appointment_Status,
            d.name AS dentist_name
        FROM billing b
        LEFT JOIN appointment a ON b.appointment_id = a.Appointment_ID
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        WHERE b.appointment_id = ?
    ";
    $bill_stmt = $conn->prepare($bill_sql);
    $bill_stmt->bind_param("i", $bill_id);
    $bill_stmt->execute();
    $bill_result = $bill_stmt->get_result();
    $bill = $bill_result->fetch_assoc();
    
    // ✅ FIXED: Add ALL dentists to the bill array
    $bill['all_dentists'] = $dentists_display;
}

// ✅ FIXED: Get ALL services from ALL grouped appointments, not just one
$all_service_names = [];
$service_prices = []; // ✅ NEW: Store service prices

foreach ($grouped_appointments as $appt) {
    $service_names = getServiceNamesFromIDs($conn, $appt['Service_ID']);
    $all_service_names = array_merge($all_service_names, $service_names);
    
    // ✅ NEW: Get prices for services
    $prices = getServicePricesFromIDs($conn, $appt['Service_ID']);
    $service_prices = array_merge($service_prices, $prices);
}
$all_service_names = array_unique($all_service_names);

$services_data = [];

// ✅ FIXED: Create separate rows for EACH service from ALL appointments with ACTUAL PRICES
if (!empty($all_service_names)) {
    foreach ($all_service_names as $service_name) {
        $price = isset($service_prices[$service_name]) ? $service_prices[$service_name] : 0;
        $services_data[] = [
            'description' => $service_name,
            'quantity' => 1,
            'amount' => $price, // ✅ FIXED: Use actual price from database
            'total' => $price * 1
        ];
    }
} else {
    // Fallback if no services found
    $services_data[] = [
        'description' => 'Dental Consultation',
        'quantity' => 1,
        'amount' => 0,
        'total' => 0
    ];
}

// Enhanced payment processing with status logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'process_payment') {
    $discount_percent = isset($_POST['discount_percent']) ? (float)$_POST['discount_percent'] : 0;
    $payment_method = trim($_POST['payment_method']);
    
    // Validate payment method
    if (empty($payment_method)) {
        $error_message = "Please select a payment method.";
    } else {
        // Calculate final amount from services and check if all services are updated
        $final_amount = 0;
        $service_updates = [];
        $all_services_updated = true; // Track if ALL services have been updated
        
        if (isset($_POST['service_description']) && is_array($_POST['service_description'])) {
            foreach ($_POST['service_description'] as $index => $description) {
                $description = trim($description);
                $quantity = isset($_POST['service_quantity'][$index]) ? (int)$_POST['service_quantity'][$index] : 1;
                $amount = isset($_POST['service_amount'][$index]) ? (float)$_POST['service_amount'][$index] : 0;
                
                if (!empty($description)) {
                    $service_total = $amount * $quantity;
                    $final_amount += $service_total;
                    
                    $service_updates[] = [
                        'description' => $description,
                        'quantity' => $quantity,
                        'amount' => $amount,
                        'total' => $service_total
                    ];
                    
                    // ✅ NEW LOGIC: Check if this service has been properly updated
                    // If quantity is 1 (default) AND amount is 0, consider it NOT updated
                    // This means the admin didn't touch this service
                    if ($quantity === 1 && $amount == 0) {
                        $all_services_updated = false;
                    }
                }
            }
        }
        
        // ✅ NEW LOGIC: Determine payment status based on whether ALL services are updated
        if ($all_services_updated && $final_amount > 0) {
            $payment_status = 'Paid';
        } else {
            $payment_status = 'Partial';
        }
        
        // Apply discount
        $discounted_amount = $final_amount - ($final_amount * $discount_percent / 100);
        
        // Start transaction for data consistency
        $conn->begin_transaction();
        
        try {
            // Combine all service descriptions
            $all_service_descriptions = array_map(function($service) {
                return $service['description'];
            }, $service_updates);
            
            $combined_services = implode(', ', $all_service_descriptions);
            
            // Update billing record
            $update_sql = "
                UPDATE billing 
                SET services = ?, 
                    total_amount = ?, 
                    payment_status = ?, 
                    payment_method = ?,
                    payment_date = NOW(),
                    updated_once = 1
                WHERE bill_id = ?
            ";

            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param(
                "sdssi", 
                $combined_services,
                $discounted_amount,
                $payment_status, // Now uses the dynamically determined status
                $payment_method,
                $bill['bill_id']
            );            
            $update_stmt->execute();
            
            if ($update_stmt->affected_rows > 0) {
                // ✅ FIXED: Update ALL grouped appointments to Completed only if FULLY PAID
                // For Partial payments, keep appointments as Confirmed
                $new_appointment_status = ($payment_status === 'Paid') ? 'Completed' : 'Confirmed';
                
                foreach ($grouped_appointments as $appt) {
                    $update_appointment_sql = "UPDATE appointment SET Appointment_Status = ? WHERE Appointment_ID = ?";
                    $update_appointment_stmt = $conn->prepare($update_appointment_sql);
                    $update_appointment_stmt->bind_param("si", $new_appointment_status, $appt['Appointment_ID']);
                    $update_appointment_stmt->execute();
                    $update_appointment_stmt->close();
                }
                
                // ===== NOTIFICATION TRIGGER FOR PAYMENT CONFIRMATION =====
                createAdminNotification(
                    'billing',
                    'Payment Processed Successfully',
                    'Payment of ₱' . number_format($discounted_amount, 2) . ' from ' . $bill['patient_name'] . ' has been processed by ' . $_SESSION['username'] . '. Status: ' . $payment_status,
                    'medium',
                    'billing_module.php',
                    $bill['bill_id']
                );
                // ===== END NOTIFICATION TRIGGER =====
                
                // ✅ NEW: Send payment confirmation email (only for Paid status)
                $email_sent = false;
                if ($payment_status === 'Paid') {
                    $email_sent = sendPaymentConfirmationEmail($conn, $bill['bill_id'], $bill['patient_id'], $service_updates, $discounted_amount, $payment_method, $payment_status);
                }
                
                // Commit transaction
                $conn->commit();
                
                // Store success message in session
                $success_message = "Payment of ₱" . number_format($discounted_amount, 2) . " was successfully processed! Status: " . $payment_status;
                if ($email_sent) {
                    $success_message .= " A confirmation email has been sent to the patient.";
                } else if ($payment_status === 'Partial') {
                    $success_message .= " Note: Some services still need to be updated.";
                }
                $_SESSION['payment_success'] = $success_message;
                
                // Redirect to prevent form resubmission
                header("Location: " . $_SERVER['PHP_SELF'] . "?bill_id=" . $bill['bill_id'] . "&patient_id=" . $bill['patient_id']);
                exit;
            } else {
                throw new Exception("No records were updated.");
            }
            
            $update_stmt->close();
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollback();
            $error_message = "Error processing payment: " . $e->getMessage();
            error_log("Payment processing error: " . $e->getMessage());
        }
    }
}

// Get patient details using the direct patient_id
if ($bill['patient_id'] > 0) {
    // Registered user - get details from users/patient_records
    $patient_sql = "
        SELECT 
            COALESCE(pr.name, u.fullname) as name,
            COALESCE(pr.address, 'Address not provided') as address,
            COALESCE(pr.contact, u.phone, 'No contact info') as contact
        FROM users u 
        LEFT JOIN patient_records pr ON u.id = pr.user_id
        WHERE u.id = ?
    ";
    $patient_stmt = $conn->prepare($patient_sql);
    $patient_stmt->bind_param("i", $bill['patient_id']);
    $patient_stmt->execute();
    $patient_result = $patient_stmt->get_result();
    $patient = $patient_result->fetch_assoc();
} else {
    // Custom name - use basic info
    $patient = [
        'name' => $bill['patient_name'],
        'address' => 'Address not provided',
        'contact' => 'No contact info'
    ];
}

// Use patient data if available, otherwise use the name from billing table
$client_name = $patient ? $patient['name'] : $bill['patient_name'];
$client_address = $patient ? ($patient['address'] ?? 'Address not provided') : 'Address not available';
$client_contact = $patient ? ($patient['contact'] ?? '') : '';

// Calculate initial subtotal
$subtotal = 0;
foreach ($services_data as $service) {
    $subtotal += $service['total'];
}

// Calculate initial values for display
$initial_discount = 0;
$initial_total = $subtotal;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing of Patient - Umipig Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="billing_patient_module.css">
    <link rel="stylesheet" href="notifications/notification_style.css">
    <meta name="description" content="Patient billing and payment portal for Umipig Dental Clinic">

<style>
    /* Your existing CSS styles remain exactly the same */
    body {
        font-family: 'Poppins', sans-serif;
        margin: 0;
        background-color: #f6f9fb;
        background-image: url('images/LandingPage-Background.jpg');
        background-size: cover;
        background-position: center;
        background-repeat: no-repeat;
        background-attachment: fixed;
        overflow: visible;
    }

    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        z-index: -1;
    }

    .main-content {
        margin-left: 150px;
        margin-top: 150px;
        margin-bottom: 150px;
        height: calc(100vh - 100px);
        display: flex;
        justify-content: center;
        align-items: center;
        padding: 0 20px;
        position: relative;
        z-index: 1;
    }

    .billing-card {
        background: linear-gradient(145deg, #ecf5ff 0%, #d9e3e8 100%);
        border-radius: 25px;
        padding: 25px 35px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        max-width: 1000px;
        width: 100%;
        transform: scale(1.1);
        margin-bottom: 10px;
        transform-origin: center;
        position: relative;
        z-index: 2;
    }

    .clinic-header {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        margin-bottom: 25px;
    }

    .logoo {
        width: 105px;
        margin-top: -15px;
        margin-left: -25px;
        height: 95px;
        object-fit: contain;
    }

    .clinic-info {
        flex: 1;
        text-align: right;
        margin-left: 20px;
    }

    .clinic-name {
        font-size: 15px;
        font-weight: 700;
        color: #1a1a1a;
        margin-bottom: 4px;
    }

    .clinic-specialty {
        font-size: 10px;
        color: #4a4a4a;
        margin-bottom: 10px;
    }

    .clinic-address {
        font-size: 10px;
        color: #2c2c2c;
        line-height: 1.3;
    }

    .divider {
        height: 2px;
        background: linear-gradient(90deg, #1a4d5e 0%, transparent 100%);
        margin: 20px 0;
    }

    .invoice-section {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 25px;
        font-size: 11px;
    }

    .bill-to {
        color: #1a237e;
    }

    .bill-to h4 {
        margin-bottom: 5px;
        font-size: 12px;
        font-weight: 700;
    }

    .bill-to p {
        margin: 2px 0;
        color: #000;
    }

    .invoice-details {
        text-align: right;
        color: #1a237e;
    }

    .invoice-details p {
        margin: 2px 0;
    }

    .invoice-details strong {
        color: #1a237e;
    }

    .invoice-table {
        width: 100%;
        margin-bottom: 20px;
    }

    .table-header {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 10px;
        padding: 10px 0;
        border-bottom: 2px solid #9db4be;
    }

    .table-header-cell {
        font-size: 11px;
        font-weight: 600;
        color: #1a1a1a;
    }

    .table-row {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr;
        gap: 10px;
        padding: 8px 0;
        border-bottom: 1px solid #c5d4db;
    }

    .table-cell {
        font-size: 10px;
        color: #2c2c2c;
    }

    .discount-section {
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .discount-label {
        font-size: 8px;
        font-style: italic;
        color: #1a1a1a;
    }

    .discount-input {
        padding: 1px 5px;
        border: 1.5px solid #b8c9d1;
        border-radius: 7px;
        font-size: 8px;
        text-align: center;
        width: 90px;
        background: white;
    }

    .payment-method-section {
        margin: 15px 0;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .payment-method-label {
        font-size: 10px;
        color: #1a1a1a;
    }

    .payment-method-select {
        padding: 3px 5px;
        border: 1.5px solid #b8c9d1;
        border-radius: 6px;
        font-size: 8px;
        text-align: center;
        width: 150px;
        background: white;
    }

    .total-divider {
        height: 2px;
        background: linear-gradient(90deg, #1a4d5e 0%, transparent 100%);
        margin: 15px 0;
    }

    .total-section {
        display: flex;
        justify-content: flex-end;
        align-items: center;
        gap: 15px;
        margin-bottom: 20px;
        margin-right: 20px;
    }

    .total-label {
        font-size: 11px;
        font-weight: 600;
        color: #1a1a1a;
        letter-spacing: 1px;
    }

    .total-amount {
        font-size: 16px;
        font-weight: 700;
        color: #dc3545;
    }

    .payment-button {
        display: block;
        margin: 0 auto;
        padding: 10px 50px;
        background: linear-gradient(135deg, #5b7ce6 0%, #4a68d9 100%);
        color: white;
        font-size: 14px;
        font-weight: 600;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .payment-button:hover {
        transform: translateY(-2px);
        background: linear-gradient(135deg, #6b8cf0 0%, #5a78e3 100%);
    }

    .edit-icon {
        width: 22px;
        height: 22px;
        cursor: pointer;
        transition: transform 0.2s ease;
    }

    .edit-icon:hover {
        transform: scale(1.1);
    }

    .status-unpaid { color: #ef4444; font-weight: bold; }
    .status-paid { color: #10b981; font-weight: bold; }
    .status-partial { color: #f59e0b; font-weight: bold; }

    /* ✅ NEW: Fixed amount display styling */
    .amount-display {
        font-weight: 600;
        color: #1a4d5e;
    }

    @media (max-width: 768px) {
        .main-content {
            margin-left: 0;
            height: auto;
            padding: 20px;
            overflow: auto;
        }

        .billing-card {
            transform: scale(1);
            padding: 20px;
            min-width: unset;
            min-height: unset;
            max-width: 100%;
            max-height: 100%;
        }

        .invoice-section {
            flex-direction: column;
            text-align: left;
            gap: 10px;
        }

        .invoice-details {
            text-align: left;
        }
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
                <a href="billing_module.php" class="nav-link active">Billing</a>
            </li>
            <li class="nav-item">
                <a href="patient_records.php" class="nav-link">Patient Records</a>
            </li>
            <li class="nav-item">
                <a href="reports_module.php" class="nav-link">Reports</a>
            <li class="nav-item">
                <a href="documents_files_module.php" class="nav-link">Documents / Files</a>
            </li>
            <li class="nav-item">
                <a href="calendar_module.php" class="nav-link">Calendar</a>
            </li>
            <li class="nav-item">
                <a href="tasks_reminders_module.php" class="nav-link">Tasks & Reminders</a>
            <li class="nav-item">
                <a href="system_settings_module.php" class="nav-link">System Settings</a>
            </li>
        </ul>
    </div>
</div>

<main class="main-content">
    <form method="POST" action="">
        <input type="hidden" name="form_type" value="process_payment">
        
        <div class="billing-card">
            <div class="scalable-content">
                <div class="clinic-header">
                    <img class="logoo" src="images/UmipigDentalClinic_NoBGLogo.jpg" alt="Umipig Dental Clinic">

                    <div class="clinic-info">
                        <h2 class="clinic-name">Umipig Dental Clinic</h2>
                        <p class="clinic-specialty" style="margin-bottom: 10px;">General Dentist, Orthodontist,<br> Oral Surgeon & Cosmetic Dentist</p>
                        <p class="clinic-address">
                            2nd Floor, Village Eats Food Park, Bldg., <br> #9 Village East Executive Homes 1900 Cainta, Philippines
                        </p>
                    </div>
                </div>

                <div class="divider"></div>

                <!-- DYNAMIC INVOICE LAYOUT -->
                <div class="invoice-section">
                    <div class="bill-to">
                        <h4>Bill to:</h4>
                        <p><strong><?php echo htmlspecialchars($client_name); ?></strong></p>
                        <p><?php echo htmlspecialchars($client_address); ?></p>
                        <?php if (!empty($client_contact)): ?>
                            <p>Contact: <?php echo htmlspecialchars($client_contact); ?></p>
                        <?php endif; ?>
                        <?php if (!empty($bill['all_dentists'])): ?>
                            <p>Dentist(s): <?php echo htmlspecialchars($bill['all_dentists']); ?></p>
                        <?php endif; ?>
                        <!-- REMOVED: Grouped appointments text -->
                    </div>

                    <div class="invoice-details">
                        <p><strong>Invoice No.</strong> <?php echo $bill['bill_id']; ?></p>
                        <p><strong>Date:</strong> <?php echo date('m/d/Y', strtotime($bill['appointment_date'])); ?></p>
                        <p><strong>Status:</strong> 
                            <span class="status-<?php echo strtolower($bill['payment_status']); ?>">
                                <?php echo htmlspecialchars($bill['payment_status']); ?>
                            </span>
                        </p>
                    </div>
                </div>

                <!-- END DYNAMIC INVOICE LAYOUT -->

                <div class="invoice-table">
                    <svg class="edit-icon" viewBox="0 0 24 24" fill="none">
                        <path d="M3 21h18M12.222 5.828L15.05 3 20 7.95l-2.828 2.828m-4.95-4.95l-5.607 5.607a1 1 0 0 0-.293.707v4.536h4.536a1 1 0 0 0 .707-.293l5.607-5.607m-4.95-4.95l4.95 4.95" stroke="#2c2c2c" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <div class="table-header">
                        <div class="table-header-cell">Description</div>
                        <div class="table-header-cell">Quantity</div>
                        <div class="table-header-cell">Amount</div>
                        <div class="table-header-cell">Total</div>
                    </div>
                    
                    <?php foreach ($services_data as $index => $service): ?>
                    <div class="table-row" data-index="<?php echo $index; ?>">
                        <div class="table-cell">
                            <input type="text" 
                                name="service_description[]" 
                                class="description-input" 
                                value="<?php echo htmlspecialchars($service['description']); ?>" 
                                style="width: 100%; padding: 2px; font-size: 10px; border: 1px solid #ccc;"
                                placeholder="Enter service description" required readonly>
                        </div>
                        <div class="table-cell">
                            <input type="number" 
                                name="service_quantity[]" 
                                class="quantity-input" 
                                value="<?php echo $service['quantity']; ?>" 
                                min="1" 
                                style="width: 60px; padding: 2px; font-size: 10px; border: 1px solid #ccc;"
                                onchange="calculateRowTotal(<?php echo $index; ?>)">
                        </div>
                        <div class="table-cell">
                            <!-- ✅ FIXED: Display formatted amount but store raw value in hidden field -->
                            <span class="amount-display" id="amountDisplay<?php echo $index; ?>">
                                ₱<?php echo number_format($service['amount'], 2); ?>
                            </span>
                            <input type="hidden" 
                                name="service_amount[]" 
                                class="amount-input" 
                                value="<?php echo $service['amount']; ?>"> <!-- ✅ FIXED: Use raw value without formatting -->
                        </div>
                        <div class="table-cell">
                            <span class="row-total" id="rowTotal<?php echo $index; ?>">
                                ₱<?php echo number_format($service['total'], 2); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>   
                    
                    
                    <!-- Add new row button -->
                    <div class="table-row">
                        <div class="table-cell" colspan="4" style="text-align: center; padding: 10px;">
                            <button type="button" onclick="addNewRow()" style="padding: 5px 10px; font-size: 10px; background: #4CAF50; color: white; border: none; border-radius: 4px; cursor: pointer;">
                                + Add Service
                            </button>
                        </div>
                    </div>
                </div>

                <div class="discount-section">
                    <label class="discount-label">Discount (Optional):</label>
                    <input type="number" class="discount-input" placeholder="Enter a Value (%)" 
                        id="discountInput" name="discount_percent" min="0" max="100" step="0.01"
                        value="<?php echo $initial_discount; ?>">
                </div>

                <div class="payment-method-section">
                    <label class="payment-method-label">Payment Method:</label>
                    <select class="payment-method-select" name="payment_method" required>
                        <option value="">Select Method</option>
                        <option value="Cash" <?php echo (isset($bill['payment_method']) && $bill['payment_method'] === 'Cash') ? 'selected' : ''; ?>>Cash</option>
                        <option value="GCash" <?php echo (isset($bill['payment_method']) && $bill['payment_method'] === 'GCash') ? 'selected' : ''; ?>>GCash</option>
                    </select>
                </div>

                <div class="total-divider"></div>

                <div class="total-section">
                    <span class="total-label">TOTAL</span>
                    <span class="total-amount" id="totalAmount">₱<?php echo number_format($initial_total, 2); ?></span>
                </div>

                <button type="submit" class="payment-button">
                    <?php echo $bill['payment_status'] === 'Paid' ? 'Update Payment' : 'Confirm Payment'; ?>
                </button>
                
                <?php if ($bill['payment_status'] === 'Paid'): ?>
                    <div style="text-align: center; margin-top: 10px;">
                        <small style="color: #10b981;">
                            <i class="fas fa-check-circle"></i> Payment already processed 
                            <?php if (!empty($bill['payment_date'])): ?>
                                on <?php echo date('m/d/Y', strtotime($bill['payment_date'])); ?>
                            <?php endif; ?>
                        </small>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </form>
</main>

<script>
    let rowCount = <?php echo count($services_data); ?>;

    function calculateRowTotal(index) {
        const row = document.querySelector(`[data-index="${index}"]`);
        const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
        const amountInput = row.querySelector('.amount-input');
        
        // ✅ FIXED: Get the raw numeric value from the hidden input, not the formatted display
        const amount = parseFloat(amountInput.value) || 0;
        const total = quantity * amount;
        
        document.getElementById(`rowTotal${index}`).textContent = '₱' + total.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
        
        calculateGrandTotal();
    }
    
    function calculateGrandTotal() {
        let grandTotal = 0;
        const discountPercent = parseFloat(document.getElementById('discountInput').value) || 0;
        
        // ✅ FIXED: Calculate from the actual numeric values, not displayed text
        document.querySelectorAll('.table-row').forEach(row => {
            if (row.querySelector('.amount-input')) {
                const quantity = parseFloat(row.querySelector('.quantity-input').value) || 0;
                const amount = parseFloat(row.querySelector('.amount-input').value) || 0;
                const rowTotal = quantity * amount;
                grandTotal += rowTotal;
            }
        });
        
        const discountedTotal = grandTotal - (grandTotal * discountPercent / 100);
        document.getElementById('totalAmount').textContent = '₱' + discountedTotal.toLocaleString('en-PH', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }
    
    function addNewRow() {
        const table = document.querySelector('.invoice-table');
        const newIndex = rowCount++;
        
        const newRow = document.createElement('div');
        newRow.className = 'table-row';
        newRow.setAttribute('data-index', newIndex);
        newRow.innerHTML = `
            <div class="table-cell">
                <input type="text" 
                       name="service_description[]" 
                       class="description-input" 
                       value="" 
                       style="width: 100%; padding: 2px; font-size: 10px; border: 1px solid #ccc;"
                       placeholder="Enter service description" required>
            </div>
            <div class="table-cell">
                <input type="number" 
                       name="service_quantity[]" 
                       class="quantity-input" 
                       value="1" 
                       min="1" 
                       style="width: 60px; padding: 2px; font-size: 10px; border: 1px solid #ccc;"
                       onchange="calculateRowTotal(${newIndex})">
            </div>
            <div class="table-cell">
                <!-- ✅ FIXED: For new rows, amount is editable since we don't have price from database -->
                <input type="number" 
                       name="service_amount[]" 
                       class="amount-input" 
                       value="0" 
                       min="0" 
                       step="0.01"
                       style="width: 80px; padding: 2px; font-size: 10px; border: 1px solid #ccc;"
                       onchange="calculateRowTotal(${newIndex})">
            </div>
            <div class="table-cell">
                <span class="row-total" id="rowTotal${newIndex}">₱0.00</span>
            </div>
        `;
        
        // Insert before the "Add Service" button row
        const addButtonRow = table.querySelector('.table-row:last-child');
        table.insertBefore(newRow, addButtonRow);
    }
    
    // Initialize calculation on page load
    document.addEventListener('DOMContentLoaded', function() {
        // ✅ FIXED: Recalculate all totals on page load to ensure consistency
        document.querySelectorAll('.table-row').forEach((row, index) => {
            if (row.querySelector('.amount-input')) {
                calculateRowTotal(index);
            }
        });
        
        // FRONTEND ONLY: Hide the "0" value on page load, but keep the actual value
        const discountInput = document.getElementById('discountInput');
        if (discountInput.value === '0') {
            discountInput.setAttribute('data-actual-value', '0');
            discountInput.value = '';
        }
    });

    const discountInput = document.getElementById('discountInput');
    discountInput.addEventListener('input', function() {
        // Store the actual value whenever user types
        if (this.value === '') {
            this.setAttribute('data-actual-value', '0');
        } else {
            this.setAttribute('data-actual-value', this.value);
        }
        calculateGrandTotal();
    });

    // Enhanced success/error messaging
    <?php if (isset($_SESSION['payment_success'])): ?>
        setTimeout(function() {
            alert('✅ <?php echo $_SESSION['payment_success']; ?>');
            <?php unset($_SESSION['payment_success']); ?>
        }, 100);
    <?php endif; ?>

    <?php if (isset($error_message)): ?>
        setTimeout(function() {
            alert('❌ <?php echo addslashes($error_message); ?>');
        }, 100);
    <?php endif; ?>
</script>

    <script src="notifications/notification_script.js"></script>

</body>
</html>