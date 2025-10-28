<?php
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}


// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====



// Fetch upcoming appointments (today and future, not completed/cancelled)
$currentDate = date('Y-m-d');
$sql = "SELECT 
            a.Appointment_ID,
            a.start_time,
            a.end_time,
            a.Appointment_Date,
            a.Service_ID AS Service_Type,
            a.Appointment_Status,
            a.Patient_Name_Custom,
            a.Patient_ID,
            d.name AS dentist_name,
            u.fullname AS patient_fullname
        FROM appointment a
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        LEFT JOIN users u ON a.Patient_ID = u.id
        WHERE a.Appointment_Status NOT IN ('Completed', 'Cancelled')
        AND a.Appointment_Date >= ?
        ORDER BY 
            a.Appointment_Date ASC,
            COALESCE(u.fullname, a.Patient_Name_Custom) ASC,
            a.start_time ASC,
            d.name ASC,
            a.Appointment_Status DESC
        LIMIT 5";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $currentDate);
$stmt->execute();
$upcomingAppointments = $stmt->get_result();

// Fetch recent patients with completed appointments
$recentPatientsSql = "SELECT 
    COALESCE(u.fullname, a.Patient_Name_Custom) AS patient_name,
    a.Patient_ID,
    MAX(a.Appointment_Date) AS last_visit,
    u.id AS user_id,
    a.Patient_Name_Custom AS custom_name
FROM appointment a
LEFT JOIN users u ON a.Patient_ID = u.id
WHERE a.Appointment_Status = 'Completed'
GROUP BY COALESCE(u.fullname, a.Patient_Name_Custom), a.Patient_ID, u.id, a.Patient_Name_Custom
ORDER BY last_visit DESC
LIMIT 4";

$recentPatientsResult = $conn->query($recentPatientsSql);
$recentPatients = [];

if ($recentPatientsResult && $recentPatientsResult->num_rows > 0) {
    while ($patient = $recentPatientsResult->fetch_assoc()) {
        // Check for next appointment
        $patientId = $patient['Patient_ID'];
        $userId = $patient['user_id'];
        $customName = $patient['custom_name'];
        
        $nextAppointmentSql = "SELECT Appointment_Date 
                              FROM appointment 
                              WHERE Appointment_Status NOT IN ('Completed', 'Cancelled') 
                              AND Appointment_Date > ? 
                              AND (Patient_ID = ? OR (Patient_ID IS NULL AND Patient_Name_Custom = ?))
                              ORDER BY Appointment_Date ASC 
                              LIMIT 1";
        
        $nextStmt = $conn->prepare($nextAppointmentSql);
        $nextStmt->bind_param("sis", $currentDate, $patientId, $patient['patient_name']);
        $nextStmt->execute();
        $nextResult = $nextStmt->get_result();
        
        $nextAppointment = '-';
        if ($nextResult->num_rows > 0) {
            $nextRow = $nextResult->fetch_assoc();
            $nextDate = new DateTime($nextRow['Appointment_Date']);
            $nextAppointment = $nextDate->format('M j, Y');
        }
        
        $lastVisit = new DateTime($patient['last_visit']);
        
        $recentPatients[] = [
            'patient_name' => $patient['patient_name'],
            'last_visit' => $lastVisit->format('M j, Y'),
            'next_appointment' => $nextAppointment,
            'patient_id' => $patientId,
            'user_id' => $userId,
            'custom_name' => $customName
        ];
        
        $nextStmt->close();
    }
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Umpig Dental Clinic - Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="admin_dashboard.css">
    <link rel="stylesheet" href="notifications/notification_style.css">
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

        <!-- Main Content Area -->
    <main class="main-content">
        <div class="header">
             <h2 style="color: royalblue; font-size: 33px; margin-left: 20px; margin-bottom: 20px;">Dashboard</h2>

        <!-- Upcoming Appointments Section -->
        <div class="data-section">
            <div class="section-header">
                <h3 class="section-title" style="color: royalblue;">Upcoming Appointments</h3>
                <div class="section-actions">
                    <a href="appointment_module.php" class="btn btn-outline" style="text-decoration: none;">View All</a>
                </div>
            </div>
            <table>
                <thead>
                    <tr>
                        <th>Patient</th>
                        <th>Service</th>
                        <th>Date & Time</th>
                        <th>Dentist</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($upcomingAppointments->num_rows > 0): ?>
                        <?php while ($appointment = $upcomingAppointments->fetch_assoc()): ?>
                            <?php
                            // Parse service IDs and get service names
                            $service_ids = json_decode($appointment['Service_Type'], true);
                            if (!is_array($service_ids)) {
                                $service_ids = explode(',', $appointment['Service_Type']);
                            }
                            
                            $service_names = [];
                            if (!empty($service_ids)) {
                                $ids_string = implode(',', array_map('intval', $service_ids));
                                $service_query = "SELECT service_name FROM services WHERE service_ID IN ($ids_string)";
                                $service_result = $conn->query($service_query);
                                while ($service = $service_result->fetch_assoc()) {
                                    $service_names[] = $service['service_name'];
                                }
                            }
                            
                            // Format date and time
                            $date = new DateTime($appointment['Appointment_Date']);
                            $start_time = DateTime::createFromFormat('H:i:s', $appointment['start_time']);
                            $formatted_date = $date->format('M j, Y');
                            $formatted_time = $start_time ? $start_time->format('g:i A') : '';
                            
                            // Determine if appointment is today
                            $isToday = ($appointment['Appointment_Date'] == $currentDate) ? 'Today, ' : $formatted_date . ', ';
                            
                            // Determine patient name (prefer fullname from users table, fallback to custom name)
                            $patient_name = !empty($appointment['patient_fullname']) 
                                ? $appointment['patient_fullname'] 
                                : $appointment['Patient_Name_Custom'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($patient_name) ?></td>
                                <td><?= htmlspecialchars(implode(', ', $service_names)) ?></td>
                                <td><?= $isToday . $formatted_time ?></td>
                                <td><?= htmlspecialchars($appointment['dentist_name']) ?></td>
                                <td><span class="status status-<?= strtolower($appointment['Appointment_Status']) ?>"><?= $appointment['Appointment_Status'] ?></span></td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" style="text-align: center;">No upcoming appointments</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>


         <!-- Calendar and Recent Patients Section -->
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 50px;">
    <!-- Calendar Widget -->
    <div class="calendar-widget">
        <div class="calendar-header">
            <h3 class="calendar-title" style="color: royalblue; font-weight: bold;" id="calendar-month-year">June 2025</h3>
            <div class="calendar-nav">
                <button class="calendar-nav-btn" id="prev-month">❮</button>
                <button class="calendar-nav-btn" id="next-month">❯</button>
            </div>
        </div>
        <div class="calendar-grid" id="calendar-days">
            <!-- Calendar days will be populated by JavaScript -->
        </div>
    </div>

            <!-- Recent Patients Section -->
            <div class="data-section">
                <div class="section-header">
                    <h3 class="section-title" style="color: royalblue;">Recent Patients</h3>
                    <div class="section-actions">
                        <a href="patient_records.php" class="btn btn-outline" style="text-decoration: none;">View All</a>
                    </div>
                </div>
                <table>
                    <thead>
                        <tr>
                            <th>Patient</th>
                            <th>Last Visit</th>
                            <th>Next Appointment</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentPatients)): ?>
                            <?php foreach ($recentPatients as $patient): ?>
                                <tr>
                                    <td><?= htmlspecialchars($patient['patient_name']) ?></td>
                                    <td><?= htmlspecialchars($patient['last_visit']) ?></td>
                                    <td>
                                        <?php if ($patient['next_appointment'] === '-'): ?>
                                            <button class="btn-set-appointment" 
                                                    onclick="openAppointmentModal('<?= htmlspecialchars($patient['patient_name']) ?>', '<?= $patient['patient_id'] ?>', '<?= $patient['user_id'] ?>', '<?= htmlspecialchars($patient['custom_name']) ?>')"
                                                    style="background: royalblue; color: white; border: none; padding: 10px 15px; border-radius: 7px; cursor: pointer; font-size: 12px; font-weight: 800; width: 50%;">
                                                Set next appointment
                                            </button>
                                        <?php else: ?>
                                            <?= htmlspecialchars($patient['next_appointment']) ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="3" style="text-align: center;">No recent patients with completed appointments</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>



        <!-- Dashboard Cards -->
        <div class="dashboard-cards">
        <?php
        // Get the current month's appointments count
        $currentMonthStart = date('Y-m-01'); // First day of current month
        $currentMonthEnd = date('Y-m-t');    // Last day of current month

        $sql = "SELECT COUNT(*) AS appointment_count 
                FROM appointment 
                WHERE Appointment_Date BETWEEN ? AND ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $currentMonthStart, $currentMonthEnd);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $appointmentCount = $row['appointment_count'] ?? 0;
        $stmt->close();
        ?>

        <!-- Appointments Card -->
        <div class="card">
            <div class="card-header">
                <h3 class="card-title" style="color: royalblue; font-weight: bold;">Appointments</h3>
                <div class="card-icon icon-appointments">
                    <i>📅</i>
                </div>
            </div>
            <div class="card-body">
                <div class="card-value"><?= $appointmentCount ?></div>
                <div class="card-description">Total appointments this month</div>
            </div>
            <div class="card-footer">
                <a href="appointment_module.php" class="card-link" style="text-decoration: none;">
                    View all appointments
                    <i>→</i>
                </a>
            </div>
        </div>


            <?php
            // Count total number of registered patients
            $sql = "SELECT COUNT(id) AS total_patients FROM patient_records";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $totalPatients = $row['total_patients'] ?? 0;
            ?>

            <!-- Total Patients Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="color: royalblue; font-weight: bold;">Total Patients</h3>
                    <div class="card-icon icon-patients">
                        <i>👤</i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-value"><?= $totalPatients ?></div>
                    <div class="card-description">Patients currently in system</div>
                </div>
                <div class="card-footer">
                    <a href="patient_records.php" class="card-link">
                        View patient records
                        <i>→</i>
                    </a>
                </div>
            </div>



            <?php
            // Get total files count
            $sql = "SELECT COUNT(*) AS total_files FROM files";
            $result = $conn->query($sql);
            $row = $result->fetch_assoc();
            $totalFiles = $row['total_files'] ?? 0;

            // Optional: Get count by category
            $categoriesSql = "SELECT category, COUNT(*) AS count FROM files GROUP BY category";
            $categoriesResult = $conn->query($categoriesSql);
            $categoryCounts = [];
            while ($catRow = $categoriesResult->fetch_assoc()) {
                $categoryCounts[$catRow['category']] = $catRow['count'];
            }
            ?>

            <!-- Documents/Files Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="color: royalblue; font-weight: bold;">Documents/Files</h3>
                    <div class="card-icon icon-documents">
                        <i>📄</i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-value"><?= $totalFiles ?></div>
                    <div class="card-description">Total files in system</div>
                    
                    <!-- Optional: Display breakdown by category -->
                    <?php if (!empty($categoryCounts)): ?>
                    <div class="category-breakdown">
                        <?php foreach ($categoryCounts as $category => $count): ?>
                            <div class="category-item">
                                <span class="category-name"><?= ucfirst($category) ?>:</span>
                                <span class="category-count"><?= $count ?></span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="card-footer">
                    <a href="documents_files_module.php" class="card-link" style="text-decoration: none;">
                        View all documents
                        <i>→</i>
                    </a>
                </div>
            </div>


            <?php
            // Get task counts from database
            $today = date('Y-m-d');
            $sql = "SELECT 
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                        SUM(CASE WHEN status = 'due_today' THEN 1 ELSE 0 END) AS due_today_count,
                        SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed_count
                    FROM tasks";
            $result = $conn->query($sql);
            $task_counts = $result->fetch_assoc();

            $pending_count = $task_counts['pending_count'] ?? 0;
            $due_today_count = $task_counts['due_today_count'] ?? 0;
            $completed_count = $task_counts['completed_count'] ?? 0;
            $total_tasks = $pending_count + $due_today_count + $completed_count;
            ?>

            <!-- Tasks Card -->
            <div class="card">
                <div class="card-header">
                    <h3 class="card-title" style="color: royalblue; font-weight: bold;">Tasks & Reminders</h3>
                    <div class="card-icon icon-appointments">
                        <i>✅</i>
                    </div>
                </div>
                <div class="card-body">
                    <div class="card-value"><?= $total_tasks ?></div>
                    <div class="card-description">Total tasks</div>
                    
                    <!-- Task breakdown -->
                    <div class="task-breakdown" style="margin-top: 10px; font-size: 14px;">
                        <div class="breakdown-item">
                            <span class="breakdown-label">Pending:</span>
                            <span class="breakdown-value"><?= $pending_count ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">Due Today:</span>
                            <span class="breakdown-value"><?= $due_today_count ?></span>
                        </div>
                        <div class="breakdown-item">
                            <span class="breakdown-label">Completed:</span>
                            <span class="breakdown-value"><?= $completed_count ?></span>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <a href="tasks_reminders_module.php" class="card-link" style="text-decoration: none;">
                        View all tasks
                        <i>→</i>
                    </a>
                </div>
            </div>  
        </div>
        
<!-- Referrals Card -->
<div class="card">
    <div class="card-header">
        <h3 class="card-title" style="color: royalblue; font-weight: bold;">Referrals</h3>
        <div class="card-icon icon-referrals">
            <i>🤝</i>
        </div>
    </div>
    <div class="card-body">
        <div class="card-value">15</div>
        <div class="card-description">This month</div>
        
        <div class="referrals-list">
            <div class="referral-item">
                <div class="referral-meta">
                    <span class="referral-date">Jun 15</span>
                    <span class="referral-code">REF-78945</span>
                </div>
                <div class="referral-details">
                    <span style="color: green;"><strong>Kristian Espinase</strong></span> referred <span style="color: darkblue;"><strong>Maria Marimar</strong></span>

                </div>
            </div>
            
            <div class="referral-item">
                <div class="referral-meta">
                    <span class="referral-date">Jun 12</span>
                    <span class="referral-code">REF-78231</span>
                </div>
                <div class="referral-details">
                    <span style="color: green;"><strong>Hennesy Villa</strong></span> referred <span style="color: darkblue;"><strong>Jane Doe</strong></span>
                </div>
            </div>
            
            <div class="referral-item">
                <div class="referral-meta">
                    <span class="referral-date">Jun 8</span>
                    <span class="referral-code">REF-77982</span>
                </div>
                <div class="referral-details">
                    <span style="color: green;"><strong>John Jones</strong></span> referred <span style="color: darkblue;"><strong>John Doe</strong></span>
                </div>
            </div>
        </div>
    </div>
    <div class="card-footer">
        <a href="#" class="card-link" style="text-decoration: none;">
            View all referrals
            <i>→</i>
        </a>
    </div>
</div>

    </main>

</body>
</html>

<script>
function openAppointmentModal(patientName, patientId, userId, customName) {
    // Redirect to appointment module with patient info in URL
    // Use custom name if available, otherwise use the regular patient name
    const nameToUse = customName && customName !== '' ? customName : patientName;
    window.location.href = 'appointment_module.php?open_modal=true&patient_name=' + encodeURIComponent(nameToUse) + '&patient_id=' + patientId;
}

// Check if modal should be opened on page load (for appointment_module.php)
document.addEventListener('DOMContentLoaded', function() {
    const urlParams = new URLSearchParams(window.location.search);
    if (urlParams.get('open_modal') === 'true') {
        // Open the modal
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
});
</script>

    <script src="admin_dashboard.js"></script>
    <script src="notifications/notification_script.js"></script>
</body>
</html>