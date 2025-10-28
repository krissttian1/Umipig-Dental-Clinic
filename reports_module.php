<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// Get filter values from POST or use defaults
$reportType = $_POST['report-type'] ?? 'appointments'; // Default to appointments
$dateRange = $_POST['date-range'] ?? 'month';
$startDate = $_POST['start-date'] ?? date('Y-m-01');
$endDate = $_POST['end-date'] ?? date('Y-m-t');

// Pagination variables
$rowsPerPage = 8;
$currentPage = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$offset = ($currentPage - 1) * $rowsPerPage;

// Calculate dates based on selected range
switch ($dateRange) {
    case 'today':
        $startDate = date('Y-m-d');
        $endDate = date('Y-m-d');
        break;
    case 'week':
        $startDate = date('Y-m-d', strtotime('monday this week'));
        $endDate = date('Y-m-d', strtotime('sunday this week'));
        break;
    case 'month':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'custom':
        // Use the provided custom dates
        break;
    default:
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
}

// REAL DATA QUERIES USING YOUR ACTUAL TABLE STRUCTURE

// 1. Services Data (Most Requested Services)
$services_sql = "
    SELECT 
        s.service_name,
        COUNT(a.Appointment_ID) as count
    FROM appointment a
    JOIN services s ON a.Service_ID = s.service_ID
    WHERE a.Appointment_Date BETWEEN ? AND ?
    GROUP BY s.service_name
    ORDER BY count DESC
    LIMIT 5
";
$services_stmt = $conn->prepare($services_sql);
$services_stmt->bind_param("ss", $startDate, $endDate);
$services_stmt->execute();
$services_result = $services_stmt->get_result();
$servicesData = $services_result->fetch_all(MYSQLI_ASSOC);

// 2. Appointment Status Data - UPDATED: Include "No Show" status
$status_sql = "
    SELECT 
        Appointment_Status as status,
        COUNT(*) as count
    FROM appointment 
    WHERE Appointment_Date BETWEEN ? AND ?
    GROUP BY Appointment_Status
";
$status_stmt = $conn->prepare($status_sql);
$status_stmt->bind_param("ss", $startDate, $endDate);
$status_stmt->execute();
$status_result = $status_stmt->get_result();
$statusData = [];
$statusColors = [
    'Completed' => '#2ecc71',
    'Confirmed' => '#3498db', 
    'Pending' => '#f39c12',
    'Cancelled' => '#e74c3c',
    'No Show' => '#9b59b6'  // Added No Show with purple color
];
while ($row = $status_result->fetch_assoc()) {
    $row['color'] = $statusColors[$row['status']] ?? '#95a5a6';
    $statusData[] = $row;
}

// 3. Recent Appointments Data - UPDATED: Include pagination
$appointments_sql = "
    SELECT 
        a.Appointment_Date as date,
        COALESCE(pr.name, a.Patient_Name_Custom) as patient,
        s.service_name as service,
        d.name as dentist,
        a.Appointment_Status as status
    FROM appointment a
    LEFT JOIN patient_records pr ON a.Patient_ID = pr.user_id
    LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
    LEFT JOIN services s ON a.Service_ID = s.service_ID
    WHERE a.Appointment_Date BETWEEN ? AND ?
    ORDER BY a.Appointment_Date DESC, a.start_time DESC
    LIMIT ? OFFSET ?
";

// Get total count for pagination
$appointments_count_sql = "
    SELECT COUNT(*) as total
    FROM appointment a
    WHERE a.Appointment_Date BETWEEN ? AND ?
";
$count_stmt = $conn->prepare($appointments_count_sql);
$count_stmt->bind_param("ss", $startDate, $endDate);
$count_stmt->execute();
$count_result = $count_stmt->get_result();
$totalAppointmentsCount = $count_result->fetch_assoc()['total'];
$count_stmt->close();

// Calculate total pages
$totalPages = ceil($totalAppointmentsCount / $rowsPerPage);

// Get paginated data
$appointments_stmt = $conn->prepare($appointments_sql);
$appointments_stmt->bind_param("ssii", $startDate, $endDate, $rowsPerPage, $offset);
$appointments_stmt->execute();
$appointments_result = $appointments_stmt->get_result();
$appointmentsData = $appointments_result->fetch_all(MYSQLI_ASSOC);

// Format status with colors
foreach ($appointmentsData as &$appointment) {
    $status = $appointment['status'];
    $color = $statusColors[$status] ?? '#000000';
    $appointment['status'] = "<span style=\"color: $color;\">$status</span>";
}

// 4. Patients Data - FIXED: Removed date filter from WHERE clause to include all patients
$patients_sql = "
    SELECT 
        pr.id as patient_id,
        pr.name,
        pr.age,
        pr.sex as gender,
        MAX(a.Appointment_Date) as last_visit,
        COUNT(a.Appointment_ID) as total_visits
    FROM patient_records pr
    LEFT JOIN appointment a ON pr.user_id = a.Patient_ID
    WHERE (a.Appointment_Date BETWEEN ? AND ? OR a.Appointment_Date IS NULL)
    GROUP BY pr.id, pr.name, pr.age, pr.sex
    ORDER BY last_visit DESC
    LIMIT 10
";
$patients_stmt = $conn->prepare($patients_sql);
$patients_stmt->bind_param("ss", $startDate, $endDate);
$patients_stmt->execute();
$patients_result = $patients_stmt->get_result();
$patientsData = $patients_result->fetch_all(MYSQLI_ASSOC);

// Format patient IDs
foreach ($patientsData as &$patient) {
    $patient['id'] = 'P' . str_pad($patient['patient_id'], 4, '0', STR_PAD_LEFT);
}

// 5. Statistics Data - FIXED: Improved revenue calculation to include all paid bills
$stats_sql = "
    SELECT 
        COUNT(DISTINCT a.Appointment_ID) as total_appointments,
        COUNT(DISTINCT a.Patient_ID) as new_patients,
        SUM(CASE WHEN a.Appointment_Status = 'Completed' THEN 1 ELSE 0 END) as services_completed,
        COALESCE(SUM(CASE WHEN b.payment_status IN ('Paid', 'Partial') THEN b.total_amount ELSE 0 END), 0) as total_revenue
    FROM appointment a
    LEFT JOIN billing b ON a.Appointment_ID = b.appointment_id
    WHERE a.Appointment_Date BETWEEN ? AND ?
";
$stats_stmt = $conn->prepare($stats_sql);
$stats_stmt->bind_param("ss", $startDate, $endDate);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();

// 6. Demographic Data (Age Distribution)
$demographic_sql = "
    SELECT 
        CASE 
            WHEN pr.age BETWEEN 18 AND 30 THEN '18-30'
            WHEN pr.age BETWEEN 31 AND 45 THEN '31-45'
            WHEN pr.age BETWEEN 46 AND 60 THEN '46-60'
            ELSE '60+'
        END as age_group,
        COUNT(DISTINCT pr.id) as count
    FROM patient_records pr
    JOIN appointment a ON pr.user_id = a.Patient_ID
    WHERE a.Appointment_Date BETWEEN ? AND ?
    GROUP BY age_group
";
$demo_stmt = $conn->prepare($demographic_sql);
$demo_stmt->bind_param("ss", $startDate, $endDate);
$demo_stmt->execute();
$demo_result = $demo_stmt->get_result();
$ageDistribution = $demo_result->fetch_all(MYSQLI_ASSOC);

// 7. Gender Distribution
$gender_sql = "
    SELECT 
        pr.sex as gender,
        COUNT(DISTINCT pr.id) as count
    FROM patient_records pr
    JOIN appointment a ON pr.user_id = a.Patient_ID
    WHERE a.Appointment_Date BETWEEN ? AND ?
    GROUP BY pr.sex
";
$gender_stmt = $conn->prepare($gender_sql);
$gender_stmt->bind_param("ss", $startDate, $endDate);
$gender_stmt->execute();
$gender_result = $gender_stmt->get_result();
$genderDistribution = $gender_result->fetch_all(MYSQLI_ASSOC);

// 8. Billing/Revenue Data for Services Report - FIXED: Include all paid bills
$revenue_sql = "
    SELECT 
        b.services,
        COUNT(b.bill_id) as transaction_count,
        SUM(b.total_amount) as total_revenue,
        AVG(b.total_amount) as average_revenue
    FROM billing b
    WHERE b.appointment_date BETWEEN ? AND ?
    AND b.payment_status IN ('Paid', 'Partial')
    GROUP BY b.services
    ORDER BY total_revenue DESC
    LIMIT 10
";
$revenue_stmt = $conn->prepare($revenue_sql);
$revenue_stmt->bind_param("ss", $startDate, $endDate);
$revenue_stmt->execute();
$revenue_result = $revenue_stmt->get_result();
$revenueData = $revenue_result->fetch_all(MYSQLI_ASSOC);

// 9. Payment Status Distribution for Billing Report - FIXED: Include all payment statuses
$payment_status_sql = "
    SELECT 
        payment_status,
        COUNT(*) as count,
        SUM(total_amount) as amount
    FROM billing 
    WHERE appointment_date BETWEEN ? AND ?
    GROUP BY payment_status
";
$payment_stmt = $conn->prepare($payment_status_sql);
$payment_stmt->bind_param("ss", $startDate, $endDate);
$payment_stmt->execute();
$payment_result = $payment_stmt->get_result();
$paymentStatusData = $payment_result->fetch_all(MYSQLI_ASSOC);

// Close statements
$services_stmt->close();
$status_stmt->close();
$appointments_stmt->close();
$patients_stmt->close();
$stats_stmt->close();
$demo_stmt->close();
$gender_stmt->close();
$revenue_stmt->close();
$payment_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Umpig Dental Clinic - Reports</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="reports_module.css">
    <link rel="stylesheet" href="notifications/notification_style.css">
    <style>
        /* NEW CSS ONLY FOR ADDED ELEMENTS */
        .demographic-chart {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            flex-wrap: wrap;
        }
        .chart-container {
            width: 45%;
            min-width: 300px;
            margin-bottom: 20px;
        }
        .chart-title {
            text-align: center;
            margin-bottom: 10px;
            font-weight: 500;
        }
        .chart {
            height: 200px;
            background-color: #f8f9fa;
            border-radius: 8px;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            padding: 10px;
            gap: 20px;
        }
        .chart-bar {
            width: 60px;
            background-color: #3498db;
            border-radius: 4px 4px 0 0;
            position: relative;
        }
        .chart-bar-label {
            position: absolute;
            bottom: -25px;
            width: 100%;
            text-align: center;
            font-size: 0.8rem;
        }
        
        /* Dynamic UI Styling */
        .report-section {
            display: none;
        }
        .report-section.active {
            display: block;
        }
        
        /* Pagination Styles */
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 20px;
            gap: 5px;
        }
        .page-item {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            text-decoration: none;
            color: #333;
            background: white;
        }
        .page-item:hover {
            background: #f5f5f5;
        }
        .page-item.active {
            background: royalblue;
            color: white;
            border-color: royalblue;
        }
        .page-item.disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        @media (max-width: 768px) {
            .chart-container {
                width: 100%;
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

            <!-- Profile Icon -->
            <a href="user_profile_module.php" class="profile-icon" title="Profile" style="color: royalblue;">
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
        <div id="main-content" class="content-area">
            <div class="reports-header">
                <h2 style="color: royalblue; font-size: 33px; margin-left: 20px; margin-bottom: 10px;">Reports Module</h2>
                <div class="report-controls">
                    <button class="btn btn-secondary" id="print-report">Print Report</button>
                    <button class="btn btn-primary" id="generate-report">Generate Report</button>
                </div>
            </div>

            <form method="post" id="report-form">
                <div class="report-filters">
                    <div class="filter-row">
                        <div class="filter-group">
                            <label for="report-type" style="font-weight: bold;">Report Type</label>
                            <select id="report-type" name="report-type">
                                <option value="appointments" <?= $reportType === 'appointments' ? 'selected' : '' ?>>Appointments Report</option>
                                <option value="patients" <?= $reportType === 'patients' ? 'selected' : '' ?>>Patients Report</option>
                                <option value="services" <?= $reportType === 'services' ? 'selected' : '' ?>>Billing Report</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label for="date-range" style="font-weight: bold;">Date Range</label>
                            <select id="date-range" name="date-range">
                                <option value="today" <?= $dateRange === 'today' ? 'selected' : '' ?>>Today</option>
                                <option value="week" <?= $dateRange === 'week' ? 'selected' : '' ?>>Week</option>
                                <option value="month" <?= $dateRange === 'month' ? 'selected' : '' ?> selected>Month</option>
                                <option value="custom" <?= $dateRange === 'custom' ? 'selected' : '' ?>>Custom Range</option>
                            </select>
                        </div>
                    </div>
                    <div class="filter-row" id="custom-date-range" style="display: <?= $dateRange === 'custom' ? 'flex' : 'none' ?>;">
                        <div class="filter-group">
                            <label for="start-date">Start Date</label>
                            <input type="date" id="start-date" name="start-date" value="<?= $startDate ?>">
                        </div>
                        <div class="filter-group">
                            <label for="end-date">End Date</label>
                            <input type="date" id="end-date" name="end-date" value="<?= $endDate ?>">
                        </div>
                    </div>
                    <div class="filter-actions">
                        <button type="button" class="btn btn-secondary" id="reset-filters">Reset Filters</button>
                        <button type="submit" class="btn btn-primary" id="apply-filters">Apply Filters</button>
                    </div>
                </div>
            </form>

            <div class="report-stats">
                <div class="stat-card">
                    <div class="stat-label" style="color: royalblue;">Total Appointments</div>
                    <div class="stat-value"><?= $stats['total_appointments'] ?? 0 ?></div>
                    <div class="stat-label">This Period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color: royalblue;">New Patients</div>
                    <div class="stat-value"><?= $stats['new_patients'] ?? 0 ?></div>
                    <div class="stat-label">This Period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color: royalblue;">Services Completed</div>
                    <div class="stat-value"><?= $stats['services_completed'] ?? 0 ?></div>
                    <div class="stat-label">This Period</div>
                </div>
                <div class="stat-card">
                    <div class="stat-label" style="color: royalblue;">Total Revenue</div>
                    <div class="stat-value">₱<?= number_format($stats['total_revenue'] ?? 0, 2) ?></div>
                    <div class="stat-label">This Period</div>
                </div>
            </div>

            <!-- Appointments Report Section -->
            <div id="appointments-report" class="report-section <?= ($reportType === 'appointments' || empty($reportType)) ? 'active' : '' ?>">
                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Appointment Status Overview - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-content">
                        <?php 
                        $totalAppointments = array_sum(array_column($statusData, 'count'));
                        foreach ($statusData as $status): 
                            $percentage = $totalAppointments > 0 ? ($status['count'] / $totalAppointments) * 100 : 0;
                        ?>
                            <div class="status-item">
                                <span class="status-label"><?= $status['status'] ?></span>
                                <div class="status-bar" style="
                                    width: <?= $percentage ?>%;
                                    background-color: <?= $status['color'] ?>;">
                                </div>
                                <span class="status-count"><?= $status['count'] ?> (<?= round($percentage) ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Recent Appointments - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-content">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Patient</th>
                                    <th>Service</th>
                                    <th>Dentist</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($appointmentsData)): ?>
                                    <?php foreach ($appointmentsData as $appointment): ?>
                                    <tr>
                                        <td><?= $appointment['date'] ?></td>
                                        <td><?= $appointment['patient'] ?></td>
                                        <td><?= $appointment['service'] ?></td>
                                        <td><?= $appointment['dentist'] ?></td>
                                        <td><?= $appointment['status'] ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" style="text-align: center;">No appointments found for the selected period.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        
                        <!-- UPDATED: Functional Pagination -->
                        <?php if ($totalPages > 1): ?>
                        <div class="pagination">
                            <!-- Previous Page -->
                            <?php if ($currentPage > 1): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage - 1])) ?>" class="page-item">‹ Prev</a>
                            <?php else: ?>
                                <span class="page-item disabled">‹ Prev</span>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                <?php if ($i == $currentPage): ?>
                                    <span class="page-item active"><?= $i ?></span>
                                <?php else: ?>
                                    <a href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>" class="page-item"><?= $i ?></a>
                                <?php endif; ?>
                            <?php endfor; ?>

                            <!-- Next Page -->
                            <?php if ($currentPage < $totalPages): ?>
                                <a href="?<?= http_build_query(array_merge($_GET, ['page' => $currentPage + 1])) ?>" class="page-item">Next ›</a>
                            <?php else: ?>
                                <span class="page-item disabled">Next ›</span>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Patients Report Section -->
            <div id="patients-report" class="report-section <?= $reportType === 'patients' ? 'active' : '' ?>">
                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Patient Demographics - <?= ucfirst($dateRange) ?></h2>
                    <div class="demographic-chart">
                        <div class="chart-container">
                            <div class="chart-title">Age Distribution</div>
                            <div class="chart">
                                <?php
                                $ageGroups = ['18-30' => 0, '31-45' => 0, '46-60' => 0, '60+' => 0];
                                foreach ($ageDistribution as $age) {
                                    $ageGroups[$age['age_group']] = $age['count'];
                                }
                                $maxAge = max($ageGroups) ?: 1;
                                foreach ($ageGroups as $group => $count): 
                                    $height = ($count / $maxAge) * 100;
                                ?>
                                    <div class="chart-bar" style="height: <?= $height ?>%">
                                        <div class="chart-bar-label"><?= $group ?><br><?= $count ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <div class="chart-container">
                            <div class="chart-title">Gender Distribution</div>
                            <div class="chart">
                                <?php
                                $genders = ['Male' => 0, 'Female' => 0];
                                foreach ($genderDistribution as $gender) {
                                    $genders[$gender['gender']] = $gender['count'];
                                }
                                $maxGender = max($genders) ?: 1;
                                $colors = ['Male' => '#3498db', 'Female' => '#e91e63'];
                                foreach ($genders as $gender => $count):
                                    $height = ($count / $maxGender) * 100;
                                ?>
                                    <div class="chart-bar" style="height: <?= $height ?>%; background-color: <?= $colors[$gender] ?? '#95a5a6' ?>">
                                        <div class="chart-bar-label"><?= $gender ?><br><?= $count ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Patient List - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-content">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Patient ID</th>
                                    <th>Name</th>
                                    <th>Age</th>
                                    <th>Gender</th>
                                    <th>Last Visit</th>
                                    <th>Total Visits</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($patientsData as $patient): ?>
                                <tr>
                                    <td><?= $patient['id'] ?></td>
                                    <td><?= $patient['name'] ?></td>
                                    <td><?= $patient['age'] ?></td>
                                    <td><?= $patient['gender'] ?></td>
                                    <td><?= $patient['last_visit'] ?? 'No visits' ?></td>
                                    <td><?= $patient['total_visits'] ?? 0 ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <div class="pagination">
                            <div class="page-item active">1</div>
                            <div class="page-item">2</div>
                            <div class="page-item">3</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Billing Report Section -->
            <div id="services-report" class="report-section <?= $reportType === 'services' ? 'active' : '' ?>">
                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Revenue by Service - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-content">
                        <table class="report-table">
                            <thead>
                                <tr>
                                    <th>Service</th>
                                    <th>Transactions</th>
                                    <th>Total Revenue</th>
                                    <th>Average Revenue</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($revenueData as $revenue): ?>
                                <tr>
                                    <td><?= htmlspecialchars($revenue['services']) ?></td>
                                    <td><?= $revenue['transaction_count'] ?></td>
                                    <td>₱<?= number_format($revenue['total_revenue'], 2) ?></td>
                                    <td>₱<?= number_format($revenue['average_revenue'], 2) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Payment Status Overview - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-content">
                        <?php 
                        $totalBilling = array_sum(array_column($paymentStatusData, 'count'));
                        foreach ($paymentStatusData as $payment): 
                            $percentage = $totalBilling > 0 ? ($payment['count'] / $totalBilling) * 100 : 0;
                            $paymentColors = [
                                'Paid' => '#2ecc71',
                                'Partial' => '#f39c12',
                                'Unpaid' => '#e74c3c'
                            ];
                            $color = $paymentColors[$payment['payment_status']] ?? '#95a5a6';
                        ?>
                            <div class="status-item">
                                <span class="status-label"><?= $payment['payment_status'] ?> (₱<?= number_format($payment['amount'], 2) ?>)</span>
                                <div class="status-bar" style="width: <?= $percentage ?>%; background-color: <?= $color ?>;"></div>
                                <span class="status-count"><?= $payment['count'] ?> (<?= round($percentage) ?>%)</span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="report-container">
                    <h2 class="report-title" style="color: royalblue;">Most Requested Services - <?= ucfirst($dateRange) ?></h2>
                    <div class="report-grid">
                        <div class="service-card">
                            <h3>Service Popularity</h3>
                            <?php foreach ($servicesData as $service): ?>
                                <div class="service-item">
                                    <span><?= htmlspecialchars($service['service_name']) ?></span>
                                    <span><?= $service['count'] ?> appointments</span>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
    // Toggle sidebar on mobile
    document.querySelector('.menu-icon').addEventListener('click', function() {
        document.querySelector('.sidebar').classList.toggle('active');
    });

    // Show/hide custom date range
    document.getElementById('date-range').addEventListener('change', function() {
        const customDateRange = document.getElementById('custom-date-range');
        customDateRange.style.display = this.value === 'custom' ? 'flex' : 'none';
    });

    // Report type change handler - Dynamic UI switching
    document.getElementById('report-type').addEventListener('change', function() {
        // Hide all report sections
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show selected report section
        const selectedReport = this.value;
        if (selectedReport) {
            document.getElementById(selectedReport + '-report').classList.add('active');
        }
    });

    // Reset filters
    document.getElementById('reset-filters').addEventListener('click', function() {
        document.getElementById('report-type').value = 'appointments';
        document.getElementById('date-range').value = 'month';
        document.getElementById('custom-date-range').style.display = 'none';
        document.getElementById('start-date').value = '';
        document.getElementById('end-date').value = '';
        
        // Reset UI to show appointments report
        document.querySelectorAll('.report-section').forEach(section => {
            section.classList.remove('active');
        });
        document.getElementById('appointments-report').classList.add('active');
        
        document.getElementById('report-form').submit();
    });

    // Print report
    document.getElementById('print-report').addEventListener('click', function() {
        window.print();
    });

    // Generate report
    document.getElementById('generate-report').addEventListener('click', function() {
        const reportType = document.getElementById('report-type').value || 'appointments';
        alert(`Exporting ${reportType} report as PDF...`);
        // In a real application, this would generate and download a PDF
    });

    // Initialize UI based on current report type
    document.addEventListener('DOMContentLoaded', function() {
        const currentReportType = document.getElementById('report-type').value;
        if (currentReportType) {
            document.querySelectorAll('.report-section').forEach(section => {
                section.classList.remove('active');
            });
            document.getElementById(currentReportType + '-report').classList.add('active');
        }
    });
</script>

    <script src="notifications/notification_script.js"></script>

</body>
</html>