<?php
session_start();
date_default_timezone_set('Asia/Manila');

if (!isset($_SESSION['role'])) {
    header("Location: home.php");
    exit;
}

// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====

// Fetch appointments with Confirmed and Completed status, ordered by patient and time
$appointments_sql = "
    SELECT 
        a.Appointment_ID,
        a.Appointment_Date,
        a.start_time,
        a.end_time,
        a.Service_ID as Service_Type,
        a.Appointment_Status,
        COALESCE(u.fullname, a.Patient_Name_Custom) as patient_name,
        u.id as patient_id,
        d.name as dentist_name,
        CONCAT(a.Appointment_Date, ' ', a.start_time) as appointment_datetime
    FROM appointment a
    LEFT JOIN users u ON a.Patient_ID = u.id
    LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
    WHERE a.Appointment_Status IN ('Confirmed', 'Completed')
    ORDER BY patient_name, a.Appointment_Date ASC, a.start_time ASC
";

$appointments_result = $conn->query($appointments_sql);

// Fetch billing records to check payment status
$billing_sql = "SELECT appointment_id, payment_status, total_amount, payment_method, updated_once FROM billing";
$billing_result = $conn->query($billing_sql);

// Create a mapping of appointment_id to billing info
$billing_info = [];
if ($billing_result && $billing_result->num_rows > 0) {
    while ($bill = $billing_result->fetch_assoc()) {
        $billing_info[$bill['appointment_id']] = $bill;
    }
}

// Function to get service names from service IDs
function getServiceNamesFromIDs($conn, $service_ids) {
    $service_names = [];
    
    try {
        // Parse the service IDs (could be JSON array or comma-separated)
        $services_array = json_decode($service_ids, true);
        if (!is_array($services_array)) {
            $services_array = explode(',', $service_ids);
        }
        
        $clean_ids = array_map('intval', $services_array);
        
        if (!empty($clean_ids)) {
            $ids_string = implode(',', $clean_ids);
            $query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
            $result = $conn->query($query);
            
            while ($row = $result->fetch_assoc()) {
                $service_names[] = $row['service_name'];
            }
        }
    } catch (Exception $e) {
        error_log("Error getting service names: " . $e->getMessage());
    }
    
    return $service_names;
}

// NEW: Function to convert time to 12-hour format
function formatTimeTo12Hour($time) {
    return date('g:i A', strtotime($time));
}

function hasBeenUpdatedOnce($appointments, $billing_info) {
    foreach ($appointments as $appointment) {
        // Normalize both possible key formats
        $appointment_id = $appointment['Appointment_ID'] ?? $appointment['appointment_id'] ?? null;
        if ($appointment_id && isset($billing_info[$appointment_id]) && intval($billing_info[$appointment_id]['updated_once']) === 1) {
            return true;
        }
    }
    return false;
}

// UPDATED: Zero-gap grouping function - only group appointments with exactly zero time gap
function groupConsecutiveAppointments($appointments, $max_gap_minutes = 0) {
    $grouped = [];
    $current_group = [];
    $previous_end_datetime = null;
    $current_patient = null;
    $current_date = null;
    
    foreach ($appointments as $appointment) {
        $current_start_datetime = strtotime($appointment['appointment_datetime']);
        
        // Calculate end datetime using end_time
        $end_time = $appointment['end_time'];
        $appointment_date = $appointment['Appointment_Date'];
        $current_end_datetime = strtotime($appointment_date . ' ' . $end_time);
        
        $current_patient_id = $appointment['patient_id'] ?: $appointment['patient_name'];
        
        // Check if we should start a new group:
        // 1. Different patient
        // 2. Different date
        // 3. Time gap greater than 0 minutes
        if ($current_patient !== $current_patient_id || 
            $current_date !== $appointment_date ||
            ($previous_end_datetime && ($current_start_datetime - $previous_end_datetime) > ($max_gap_minutes * 60))) {
            
            // Save previous group if not empty
            if (!empty($current_group)) {
                $group_key = $current_patient . '_' . $current_group[0]['Appointment_ID'];
                $grouped[$group_key] = $current_group;
            }
            
            // Start new group
            $current_group = [$appointment];
            $current_patient = $current_patient_id;
            $current_date = $appointment_date;
            $previous_end_datetime = $current_end_datetime;
        } else {
            // Add to current group and update the end time to the latest appointment's end time
            $current_group[] = $appointment;
            $previous_end_datetime = max($previous_end_datetime, $current_end_datetime);
        }
    }
    
    // Don't forget the last group
    if (!empty($current_group)) {
        $group_key = $current_patient . '_' . $current_group[0]['Appointment_ID'];
        $grouped[$group_key] = $current_group;
    }
    
    return $grouped;
}

// Group appointments by zero-gap consecutive time periods
$consecutive_groups = [];
if ($appointments_result && $appointments_result->num_rows > 0) {
    $all_appointments = $appointments_result->fetch_all(MYSQLI_ASSOC);
    $consecutive_groups = groupConsecutiveAppointments($all_appointments, 0); // ZERO gap only
}

// Process the consecutive groups for display
$grouped_appointments = [];
foreach ($consecutive_groups as $group_key => $appointments) {
    if (empty($appointments)) continue;
    
    $first_appointment = $appointments[0];
    $patient_id = $first_appointment['patient_id'] ?: $first_appointment['patient_name'];
    $patient_name = $first_appointment['patient_name'];
    
    $grouped_appointments[$group_key] = [
        'patient_name' => $patient_name,
        'patient_id' => $patient_id,
        'appointments' => $appointments,
        'all_services' => [],
        'dentists' => [],
        'appointment_dates' => [],
        'appointment_statuses' => [],
        'appointment_ids' => [],
        'time_range' => '' // NEW: Store time range for display
    ];
    
    // Collect all data from appointments in this group
    foreach ($appointments as $appointment) {
        // Collect all services
        $service_names = getServiceNamesFromIDs($conn, $appointment['Service_Type']);
        $grouped_appointments[$group_key]['all_services'] = array_merge(
            $grouped_appointments[$group_key]['all_services'], 
            $service_names
        );
        
        // Collect unique dentists
        if (!in_array($appointment['dentist_name'], $grouped_appointments[$group_key]['dentists'])) {
            $grouped_appointments[$group_key]['dentists'][] = $appointment['dentist_name'];
        }
        
        // Collect appointment dates
        $grouped_appointments[$group_key]['appointment_dates'][] = $appointment['Appointment_Date'];
        
        // Collect appointment statuses
        $grouped_appointments[$group_key]['appointment_statuses'][] = $appointment['Appointment_Status'];
        
        // Collect appointment IDs
        $grouped_appointments[$group_key]['appointment_ids'][] = $appointment['Appointment_ID'];
    }
    
    // Remove duplicates
    $grouped_appointments[$group_key]['all_services'] = array_unique($grouped_appointments[$group_key]['all_services']);
    
    // UPDATED: Calculate time range for display with 12-hour format
    if (count($appointments) > 1) {
        $first_start = formatTimeTo12Hour($appointments[0]['start_time']);
        $last_end = formatTimeTo12Hour(end($appointments)['end_time']);
        $grouped_appointments[$group_key]['time_range'] = $first_start . ' - ' . $last_end;
    } else {
        $start_time = formatTimeTo12Hour($appointments[0]['start_time']);
        $end_time = formatTimeTo12Hour($appointments[0]['end_time']);
        $grouped_appointments[$group_key]['time_range'] = $start_time . ' - ' . $end_time;
    }
}

// Function to calculate overall payment status for grouped appointments
// ✅ FIXED: Simplified payment status logic that aligns with billing module
function getOverallPaymentStatus($appointments, $billing_info) {
    // For grouped appointments, we only need to check the FIRST appointment's billing record
    // because in the billing module, all grouped appointments share the same billing record
    if (empty($appointments)) {
        return 'Unpaid';
    }
    
    $first_appointment_id = $appointments[0]['Appointment_ID'];
    
    // Check if billing record exists for this appointment
    if (isset($billing_info[$first_appointment_id])) {
        // ✅ SIMPLIFIED: Just return the status stored in the billing table
        // The billing_patient_module already determines Paid vs Partial correctly
        return $billing_info[$first_appointment_id]['payment_status'];
    }
    
    return 'Unpaid';
}

// Function to calculate total amount for grouped appointments
// ✅ FIXED: Consistent total amount logic
function getTotalAmount($appointments, $billing_info) {
    if (empty($appointments)) {
        return 0;
    }
    
    $first_appointment_id = $appointments[0]['Appointment_ID'];
    
    // Check if billing record exists for this appointment
    if (isset($billing_info[$first_appointment_id])) {
        return $billing_info[$first_appointment_id]['total_amount'];
    }
    
    return 0;
}

// Function to get payment methods for grouped appointments
function getPaymentMethods($appointments, $billing_info) {
    $methods = [];
    foreach ($appointments as $appointment) {
        $appointment_id = $appointment['Appointment_ID'];
        if (isset($billing_info[$appointment_id]) && !empty($billing_info[$appointment_id]['payment_method'])) {
            $methods[] = $billing_info[$appointment_id]['payment_method'];
        }
    }
    return array_unique($methods);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="billing_module.css">
    <link rel="stylesheet" href="notifications/notification_style.css">
<style>
    .status-unpaid { color: #ef4444; font-weight: bold; }
    .status-paid { color: #10b981; font-weight: bold; }
    .status-partial { color: #f59e0b; font-weight: bold; }
    .status-confirmed { color: #3b82f6; font-weight: bold; }
    .status-completed { color: #8b5cf6; font-weight: bold; }
    
    .method-text {
        padding: 4px 8px;
        border-radius: 4px;
        font-size: 12px;
        font-weight: 500;
        margin-left: 100px;
    }
    
    .method-text.cash { background: #dbeafe; color: #1e40af; font-size: 10px;}
    .method-text.gcash { background: #f0f9ff; color: #0369a1; font-size: 10px;}
    .method-text.credit { background: #fef7cd; color: #92400e; font-size: 10px;}
    .method-text.debit { background: #f3e8ff; color: #7e22ce; }
    .method-text.bank { background: #dcfce7; color: #166534; }
    
    .checkmark {
        color: #10b981;
        font-weight: bold;
        margin-left: 5px;
    }
    
    .payment-btn {
        background: #2563eb;
        color: white;
        border: none;
        padding: 7px 8px;
        border-radius: 4px;
        cursor: pointer;
        font-size: 9px;
    }
    
    .payment-btn:hover {
        background: #1d4ed8;
    }
    
    .appointment-status {
        font-size: 11px;
        padding: 2px 6px;
        border-radius: 3px;
        background: #f1f5f9;
    }
    
    .service-list {
        max-width: 200px;
    }
    
    .dentist-list {
        max-width: 150px;
    }
    
    .date-range {
        font-size: 12px;
        color: #666;
    }
    
    .action-buttons {
        display: flex;
        gap: 5px;
        justify-content: center;
    }
    
    /* UPDATED: Consecutive badge styling for fixed position below patient name */
    .consecutive-badge {
        background: #e0f2fe;
        color: #0369a1;
        padding: 2px 6px;
        border-radius: 10px;
        font-size: 10px;
        display: inline-block;
        margin-top: 4px;
        margin-left: 0;
    }
    
    /* NEW: Container for patient name and badge to ensure proper layout */
    .patient-name-container {
        display: flex;
        flex-direction: column;
        align-items: flex-start;
    }
    
    .time-range {
        font-size: 11px;
        color: #666;
        margin-top: 2px;
    }
</style>
</head>
<body>

<!-- Your existing header and sidebar -->
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
                <a href="billing_module.php" class="nav-link">Billing</a>
            </li>
            <li class="nav-item">
                <a href="patient_records.php" class="nav-link">Patient Records</a>
            </li>
            <li class="nav-item">
                <a href="reports_module.php" class="nav-link active">Reports</a>
            </li>
            <li class="nav-item">
                <a href="documents_files_module.php" class="nav-link active">Documents / Files</a>
            </li>
            <li class="nav-item">
                <a href="calendar_module.php" class="nav-link active">Calendar</a>
            </li>
            <li class="nav-item">
                <a href="tasks_reminders_module.php" class="nav-link active">Tasks & Reminders</a>
            </li>
            <li class="nav-item">
                <a href="system_settings_module.php" class="nav-link active">System Settings</a>
            </li>
        </ul>
    </div>
</div>

<h1 style="color: royalblue; margin-top: 100px; margin-left: 150px;">Billing</h1>

<div class="table-controls" style="margin-top: 180px; margin-right: 60px;">
    <button class="icon-button" id="searchBtn" title="Search">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"></circle>
            <path d="m21 21-4.35-4.35"></path>
        </svg>
    </button>
    <button class="icon-button" id="filterBtn" title="Filter">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <line x1="4" y1="6" x2="16" y2="6"></line>
            <line x1="4" y1="12" x2="16" y2="12"></line>
            <line x1="4" y1="18" x2="16" y2="18"></line>
            <circle cx="18" cy="6" r="2"></circle>
            <circle cx="18" cy="18" r="2"></circle>
        </svg>
    </button>
</div>

<!-- Main Content -->
<main class="main-content">
    <!-- Table Container -->
    <div class="table-container">
        <!-- Table -->
        <table class="billing-table">
            <thead>
                <tr>
                    <th>Patient</th>
                    <th>Date</th> <!-- CHANGED: From "Date Range" to "Date" -->
                    <th>Dentist/s</th>
                    <th>Service/s</th>
                    <th>Appointment Status</th>
                    <th>Amount</th>
                    <th>Payment Status</th>
                    <th>Payment Method</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($grouped_appointments)): ?>
                    <?php foreach ($grouped_appointments as $group_key => $patient_data): ?>
                        <?php
                        // Calculate values for the grouped record
                        $all_services = array_unique($patient_data['all_services']);
                        $services_display = !empty($all_services) ? implode(', ', $all_services) : 'No services';
                        
                        $dentists_display = !empty($patient_data['dentists']) ? implode(', ', $patient_data['dentists']) : 'No dentist';
                        
                        // UPDATED: Show specific date only (not range)
                        $dates = array_unique($patient_data['appointment_dates']);
                        $specific_date = $dates[0]; // All appointments in group are same date due to zero-gap logic
                        
                        // Appointment status (show most completed status)
                        $statuses = $patient_data['appointment_statuses'];
                        $overall_appointment_status = in_array('Completed', $statuses) ? 'Completed' : 'Confirmed';
                        
                        // Payment info
                        $payment_status = getOverallPaymentStatus($patient_data['appointments'], $billing_info);
                        $total_amount = getTotalAmount($patient_data['appointments'], $billing_info);
                        $payment_methods = getPaymentMethods($patient_data['appointments'], $billing_info);
                        ?>
                        <tr>
                            <td>
                                <div class="patient-name-container">
                                    <strong><?= htmlspecialchars($patient_data['patient_name']) ?></strong>
                                    <?php if (count($patient_data['appointments']) > 1): ?>
                                        <span class="consecutive-badge" title="Zero-gap consecutive appointments grouped together" style="font-size: 7px; margin-left: 10px;">Consecutive</span>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td>
                                <span class="date-range" style="font-size: 10px;"><?= htmlspecialchars($specific_date) ?></span>
                                <?php if (count($patient_data['appointments']) > 1): ?>
                                    <div class="time-range" style="font-size: 9px;"><?= htmlspecialchars($patient_data['time_range']) ?></div>
                                <?php else: ?>
                                    <!-- UPDATED: Use formatted time for single appointments too -->
                                    <?php 
                                    $start_time = formatTimeTo12Hour($patient_data['appointments'][0]['start_time']);
                                    $end_time = formatTimeTo12Hour($patient_data['appointments'][0]['end_time']);
                                    ?>
                                    <div class="time-range"><?= htmlspecialchars($start_time . ' - ' . $end_time) ?></div>
                                <?php endif; ?>
                                <small>(<?= count($patient_data['appointments']) ?> appointment<?= count($patient_data['appointments']) > 1 ? 's' : '' ?>)</small>
                            </td>
                            <td class="dentist-list"><?= htmlspecialchars($dentists_display) ?></td>
                            <td class="service-list"><?= htmlspecialchars($services_display) ?></td>
                            <td>
                                <span class="status-<?= strtolower($overall_appointment_status) ?> appointment-status" style="font-size: 9px;">
                                    <?= htmlspecialchars($overall_appointment_status) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($total_amount > 0): ?>
                                    <strong>₱<?= number_format($total_amount, 2) ?></strong>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="status-<?= strtolower($payment_status) ?>">
                                    <?= htmlspecialchars($payment_status) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($payment_methods) && $payment_status !== 'Unpaid'): ?>
                                    <div class="payment-method">
                                        <?php foreach ($payment_methods as $method): ?>
                                            <span class="method-text <?= strtolower($method) ?>">
                                                <?= htmlspecialchars($method) ?>
                                            </span>
                                            <?php if ($payment_status === 'Paid'): ?>
                                                <span class="checkmark">✓</span>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="action-buttons" >
                                    <?php 
                                    $first_appointment_id = !empty($patient_data['appointment_ids']) ? $patient_data['appointment_ids'][0] : null;
                                    $patient_identifier = $patient_data['patient_id'] ?: urlencode($patient_data['patient_name']);
                                    $already_updated = hasBeenUpdatedOnce($patient_data['appointments'], $billing_info);
                                    ?>

                                    <?php if ($first_appointment_id): ?>
                                        <?php 
                                            // Get overall payment status
                                            $payment_status = getOverallPaymentStatus($patient_data['appointments'], $billing_info);

                                            // Determine button state
                                            $enablePayment = false;
                                            if ($already_updated && ($payment_status === 'Unpaid' || $payment_status === 'Partial')) {
                                                $enablePayment = true; // Allow editing again if Unpaid or Partial
                                            } elseif (!$already_updated) {
                                                $enablePayment = true; // Always enable if never updated before
                                            }
                                        ?>

                                        <?php if ($enablePayment): ?>
                                            <!-- Active Payment button -->
                                            <a href="billing_patient_module.php?bill_id=<?= $first_appointment_id ?>&patient_id=<?= $patient_identifier ?>" 
                                               class="payment-btn" style="background: royalblue; font-size: 9px; text-decoration: none; font-weight: 800;">
                                                Payment
                                            </a>
                                        <?php else: ?>
                                            <!-- Disabled Payment button -->
                                            <span class="payment-btn" style="background: #6c757d; cursor: not-allowed; width: 60px;" 
                                                  title="This record has already been updated and is fully paid.">
                                                Paid
                                            </span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="payment-btn" style="background: #6c757d; cursor: not-allowed; " title="No appointment available ">
                                            Payment
                                        </span>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9" style="text-align: center; padding: 20px;">
                            No appointments found with Confirmed or Completed status.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</main>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple search functionality
    const searchBtn = document.getElementById('searchBtn');
    const filterBtn = document.getElementById('filterBtn');
    
    searchBtn.addEventListener('click', function() {
        const searchTerm = prompt('Enter patient name to search:');
        if (searchTerm) {
            // Simple client-side search
            const rows = document.querySelectorAll('.billing-table tbody tr');
            rows.forEach(row => {
                const patientName = row.querySelector('td:first-child').textContent.toLowerCase();
                if (patientName.includes(searchTerm.toLowerCase())) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
    });
    
    filterBtn.addEventListener('click', function() {
        const statusFilter = prompt('Filter by payment status (Unpaid/Paid/Partial) or type "reset" to show all:');
        if (statusFilter) {
            if (statusFilter.toLowerCase() === 'reset') {
                // Show all rows
                const rows = document.querySelectorAll('.billing-table tbody tr');
                rows.forEach(row => row.style.display = '');
            } else {
                // Filter by payment status
                const rows = document.querySelectorAll('.billing-table tbody tr');
                rows.forEach(row => {
                    const statusCell = row.querySelector('td:nth-child(7)'); // Payment Status column
                    if (statusCell && statusCell.textContent.toLowerCase().includes(statusFilter.toLowerCase())) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }
        }
    });
});
</script>
    <script src="notifications/notification_script.js"></script>
</body>
</html>