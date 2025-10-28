<?php
session_start();


// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====


// Check if user is admin
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: home.php");
    exit;
}

// Auto-cancel expired pending appointments (15 mins passed and still pending)
$now = date('Y-m-d H:i:s');
$cancel_sql = "UPDATE appointment 
               SET Appointment_Status = 'Cancelled' 
               WHERE Appointment_Status = 'Pending' 
               AND confirmation_deadline IS NOT NULL 
               AND confirmation_deadline < ?";
$cancel_stmt = $conn->prepare($cancel_sql);
$cancel_stmt->bind_param("s", $now);
$cancel_stmt->execute();
$cancel_stmt->close();

// Function to get service names from service IDs
function getServiceNamesFromIDs($conn, $service_ids) {
    if (empty($service_ids)) return [];
    
    // Clean and format the service IDs
    if (is_string($service_ids)) {
        // Remove brackets and quotes if present
        $cleaned = str_replace(['[', ']', '"', "'"], '', $service_ids);
        $service_ids = explode(',', $cleaned);
    }
    
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

// Get appointments for calendar (Pending and Confirmed only by default)
$sql = "SELECT 
          a.Appointment_ID,
          a.start_time,
          a.end_time,
          a.Appointment_Date,
          a.Appointment_Status,
          a.Patient_Name_Custom,
          a.Patient_ID,
          a.Dentist_ID,
          a.Service_ID as Service_Type,
          d.name AS dentist_name,
          u.fullname AS patient_name
        FROM appointment a
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        LEFT JOIN users u ON a.Patient_ID = u.id
        WHERE a.Appointment_Status IN ('Pending', 'Confirmed')
        ORDER BY a.Appointment_Date, a.start_time";

$result = $conn->query($sql);
$appointments = [];
while ($row = $result->fetch_assoc()) {
    // Get patient name - prefer Patient_Name_Custom, fall back to user record
    $patientName = $row['Patient_Name_Custom'];
    if (empty($patientName)) {
        $patientName = $row['patient_name'];
    }
    
    // Get service names
    $serviceNames = getServiceNamesFromIDs($conn, $row['Service_Type']);
    
    $appointments[] = array_merge($row, [
        'patient_name' => $patientName ?: 'Not specified',
        'services' => !empty($serviceNames) ? implode(', ', $serviceNames) : 'No service specified'
    ]);
}

// Get all possible appointments (for when filtered)
$allAppointmentsSql = "SELECT 
          a.Appointment_ID,
          a.start_time,
          a.end_time,
          a.Appointment_Date,
          a.Appointment_Status,
          a.Patient_Name_Custom,
          a.Patient_ID,
          a.Dentist_ID,
          a.Service_ID as Service_Type,
          d.name AS dentist_name,
          u.fullname AS patient_name
        FROM appointment a
        LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
        LEFT JOIN users u ON a.Patient_ID = u.id
        WHERE a.Appointment_Status IN ('Pending', 'Confirmed', 'Completed', 'Cancelled')
        ORDER BY a.Appointment_Date, a.start_time";

$allAppointmentsResult = $conn->query($allAppointmentsSql);
$allAppointments = [];
while ($row = $allAppointmentsResult->fetch_assoc()) {
    // Get patient name - prefer Patient_Name_Custom, fall back to user record
    $patientName = $row['Patient_Name_Custom'];
    if (empty($patientName)) {
        $patientName = $row['patient_name'];
    }
    
    // Get service names
    $serviceNames = getServiceNamesFromIDs($conn, $row['Service_Type']);
    
    $allAppointments[] = array_merge($row, [
        'patient_name' => $patientName ?: 'Not specified',
        'services' => !empty($serviceNames) ? implode(', ', $serviceNames) : 'No service specified'
    ]);
}

// Get dentists for filter dropdown
$dentists = $conn->query("SELECT Dentist_ID, name FROM dentists ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Clinic Calendar</title>
    <link href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="notifications/notification_style.css">

    <style>
        :root {
            --primary-blue: #1a73e8;
            --hover-blue: #0d5bba;
            --pending-color: #FFD700;
            --confirmed-color: #4169E1;
            --completed-color: #4CAF50;
            --cancelled-color: #F44336;
        }

        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f8fafc;
        }
        
        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px;
            background-color: #ecf5ff;
            width: 100%;
            box-sizing: border-box;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1100;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-circle {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo-circle img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }

        .clinic-info h1 {
            font-size: 15px;
            text-align: left;
            color: #333;
            margin: 0 0 5px 0;
        }

        .clinic-info p {
            font-size: 10px;
            color: #555;
            margin: 0;
        }

        .main-nav {
            flex-grow: 1;
            display: flex;
            justify-content: center;
            gap: 50px;
        }

        .main-nav a {
            text-decoration: none;
            color: #333;
            font-weight: 700;
            font-size: 12px;
            transition: color 0.3s;
        }

        .main-nav a:hover {
            color: var(--primary-blue);
        }

        .main-nav a.active {
            color: var(--primary-blue);
            font-weight: bold;
        }

        .header-right {
            display: flex;
            gap: 20px;
            margin-right: 10px;
            align-items: center;
        }

        .auth-link {
            text-decoration: none;
            color: var(--primary-blue);
            font-weight: 600;
            font-size: 12px;
            transition: color 0.3s;
        }

        .auth-link:hover {
            color: var(--hover-blue);
        }

        .header-right span {
            color: black;
            font-size: 10px;
        }

        .welcome-text {
            font-weight: 700;
            font-size: 12px;
            color: #003366;
        }

        .welcome-text .auth-link {
            font-weight: 600;
            color: var(--primary-blue);
            text-decoration: none;
        }

        .welcome-text .auth-link:hover {
            color: var(--hover-blue);
        }  

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 56px;
            left: -260px;
            width: 30vw;
            max-width: 350px;
            min-width: 200px;
            height: calc(100vh - 56px);
            background-color: #6b839e;
            color: white;
            padding: 20px 0;
            transition: left 0.6s ease;
            z-index: 1000;
            overflow-x: hidden;
        }

        .sidebar:hover {
            left: 0;
        }

        .menu-icon {
            position: fixed;
            left: 20px;
            top: 90px;
            transform: translateY(-50%);
            font-size: 24px;
            background-color: #6b839e;
            color: white;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .sidebar:hover .menu-icon {
            display: none;
        }

        .nav-menu {
            list-style: none;
            margin-top: 40px;
            margin-left: -30px;
        }

        .nav-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-item span {
            display: none;
            white-space: nowrap;
        }

        .sidebar:hover .nav-item span {
            display: inline;
        }

        .nav-link {
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        /* Calendar container */
        .container {
            max-width: 90%;
            padding: 25px;
            background-color: #fff;
            margin-left: 160px;
            margin-top: 70px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.08);
            overflow-x: auto;
        }

        .calendar-header {
            position: relative;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-right: 160px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .calendar-header h1 {
            margin: 0;
            color: #333;
            font-size: 1.5rem;
            flex-grow: 1;
        }

        .filter-controls {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        select {
            padding: 8px 15px;
            border-radius: 6px;
            border: 1px solid #e2e8f0;
            font-family: 'Poppins', sans-serif;
            background-color: white;
            color: #333;
            font-size: 0.9rem;
            transition: all 0.3s;
            min-width: 180px;
        }

        select:focus {
            outline: none;
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 2px rgba(26, 115, 232, 0.2);
        }

        /* Calendar styling */
        #calendar {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            width: 100%;
            min-width: 900px;
            overflow: visible;
        }

        /* Calendar header buttons */
        .fc .fc-button {
            background-color: var(--primary-blue);
            border: none;
            color: white;
            padding: 6px 12px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s;
            margin: 2px;
        }

        .fc .fc-button:hover {
            background-color: var(--hover-blue);
        }

        .fc .fc-button-primary:not(:disabled).fc-button-active {
            background-color: var(--hover-blue);
        }

        /* Calendar table styling */
        .fc-theme-standard .fc-scrollgrid {
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: visible;
        }

        .fc-theme-standard th {
            background-color: #f8fafc;
            border-color: #e2e8f0;
            color: #4a5568;
            font-weight: 600;
            padding: 10px 0;
        }

        .fc .fc-daygrid-day-frame {
            padding: 4px;
        }

        .fc .fc-daygrid-day-top {
            justify-content: center;
        }

        .fc .fc-daygrid-day-number {
            font-weight: 500;
            color: #4a5568;
            padding: 4px;
        }

        .fc .fc-day-today {
            background-color: #f0f7ff !important;
        }

        .fc .fc-daygrid-day.fc-day-today .fc-daygrid-day-number {
            font-weight: bold;
            color: var(--primary-blue);
        }

        /* Status Indicator Styles */
        .status-indicator {
            display: inline-block;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            margin-right: 8px;
        }

        .status-indicator.pending {
            background: var(--pending-color);
        }

        .status-indicator.confirmed {
            background: var(--confirmed-color);
        }

        .status-indicator.completed {
            background: var(--completed-color);
        }

        .status-indicator.cancelled {
            background: var(--cancelled-color);
        }

        /* Status Legend Styles */
        .status-legend {
            position: absolute;
            top: 20px;
            right: 20px;
            display: flex;
            gap: 15px;
            background: white;
            padding: 8px 12px;
            border-radius: 6px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            flex-wrap: wrap;
        }

        .legend-item {
            display: flex;
            align-items: center;
            font-size: 14px;
            color: #555;
            white-space: nowrap;
        }

        /* Calendar Event Status Styles */
        .fc-event-pending {
            background-color: var(--pending-color);
            border-color: var(--pending-color);
        }

        .fc-event-confirmed {
            background-color: var(--confirmed-color);
            border-color: var(--confirmed-color);
        }

        .fc-event-completed {
            background-color: var(--completed-color);
            border-color: var(--completed-color);
        }

        .fc-event-cancelled {
            background-color: var(--cancelled-color);
            border-color: var(--cancelled-color);
        }

        .fc-daygrid-event {
            margin: 3px auto !important;
            padding: 0 !important;
            background: none !important;
            border: none !important;
        }

        .fc-event-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin: 0 auto;
        }

        /* Month View: Vertical dots with gaps */
        .fc-daygrid-day-events {
            display: flex !important;
            flex-direction: column;
            align-items: center;
            gap: 4px !important;
            margin-top: 4px;
        }

        .fc-event-pending .fc-event-dot {
            background-color: var(--pending-color);
        }

        .fc-event-confirmed .fc-event-dot {
            background-color: var(--confirmed-color);
        }

        .fc-event-completed .fc-event-dot {
            background-color: var(--completed-color);
        }

        .fc-event-cancelled .fc-event-dot {
            background-color: var(--cancelled-color);
        }

        /* Week/Day view styling */
        .fc-timegrid-event {
            border: none !important;
            padding: 6px 8px !important;
            margin: 2px 4px !important;
            border-radius: 6px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            font-size: 0.55rem;
            min-height: 40px;
        }

        .fc-timegrid-event .fc-event-main {
            padding: 0;
            overflow: hidden;
        }

        .fc-event-title-container {
            line-height: 1.3;
        }

        /* Week View: Auto-resizing text */
        .fc-event-title {
            font-weight: 800;
            white-space: normal;
            word-break: break-word;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            font-size: calc(0.5rem + 0.3vw); /* Responsive font size */
        }

        .fc-event-time {
            font-size: 0.4rem;
            opacity: 0.9;
            margin-bottom: 2px;
            margin-top: 5px;
            white-space: nowrap;
        }

        /* Day View: Better spacing */
        .fc-timegrid-event-day {
            padding: 8px 10px !important;
        }

        .fc-timegrid-event-day .fc-event-main {
            display: flex;
            flex-direction: column;
            gap: 4px; /* Added spacing between lines */
        }

        .fc-event-service {
            font-size: 0.60rem;
            font-weight: 600;
            font-style: italic;
            margin-top: 5px;
            margin-bottom: 5px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.4; /* Improved line height */
        }

        /* Week/Day View Event Colors */
        .fc-timegrid-event-pending {
            background-color: var(--pending-color) !important;
            color: #000 !important;
        }

        .fc-timegrid-event-confirmed {
            background-color: var(--confirmed-color) !important;
            color: white !important;
        }

        .fc-timegrid-event-completed {
            background-color: var(--completed-color) !important;
            color: white !important;
        }

        .fc-timegrid-event-cancelled {
            background-color: var(--cancelled-color) !important;
            color: white !important;
        }

        /* Time grid adjustments */
        .fc-timegrid-slots {
            min-width: 150px;
        }

        .fc-timegrid-slot {
            height: 40px !important;
        }

        .fc-timegrid-axis {
            width: 60px !important;
        }

        .fc-timegrid-col-events {
            margin: 0 4px;
        }

        /* Custom styles for overlapping events */
        .fc-daygrid-day-events {
            display: flex !important;
            flex-wrap: wrap;
            gap: 5px;
        }

        .fc-daygrid-event-harness {
            position: relative !important;
            flex: 1 1 auto;
            min-width: calc(50% - 5px);
            max-width: 100%;
        }

        .fc-daygrid-event-harness + .fc-daygrid-event-harness {
            margin-top: 0 !important;
        }

        .fc-daygrid-day-bottom {
            display: none;
        }

        /* Week/Day View: Show ALL events without hiding */
        .fc-timegrid-col-events {
            margin: 0 !important;
        }

        .fc-timegrid-event-harness {
            position: relative !important;
            margin-bottom: 2px !important;
        }

        .fc-timegrid-event-harness + .fc-timegrid-event-harness {
            margin-top: 2px !important;
        }

        /* Ensure all events are visible and columns expand */
        .fc-timegrid-col-frame {
            min-height: auto !important;
        }

        .fc-timegrid-col.fc-day-today {
            background-color: #f0f7ff !important;
        }

        /* Modal styling */
        .modal {
            display: none;
            position: fixed;
            z-index: 100;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
            justify-content: center;
            align-items: center;
        }
        
        .modal-content {
            background: white;
            padding: 25px;
            border-radius: 12px;
            width: 420px;
            max-width: 90%;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .modal-header h3 {
            margin: 0;
            color: #333;
            font-size: 1.3rem;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #666;
            transition: color 0.2s;
        }

        .close-btn:hover {
            color: #333;
        }
        
        .appointment-details p {
            margin: 12px 0;
            line-height: 1.5;
        }
        
        .detail-label {
            font-weight: 500;
            color: #555;
        }
        
        .action-buttons {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            margin-top: 25px;
            padding-top: 15px;
            border-top: 1px solid #e2e8f0;
        }
        
        .btn {
            padding: 8px 18px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            font-family: 'Poppins', sans-serif;
            font-weight: 500;
            font-size: 0.9rem;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background-color: var(--primary-blue);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--hover-blue);
        }
        
        .btn-secondary {
            background-color: #e2e8f0;
            color: #4a5568;
        }

        .btn-secondary:hover {
            background-color: #d1d8e0;
        }
        
        .btn-danger {
            background-color: #e53e3e;
            color: white;
        }

        .btn-danger:hover {
            background-color: #c53030;
        }
        
        h1.page-title {
            color: var(--primary-blue);
            margin-top: 100px;
            margin-left: 150px;
            margin-bottom: 30px;
            font-size: 1.8rem;
        }

        /* Tooltip styling */
        .fc-event-tooltip {
            position: fixed;
            z-index: 1000;
            background: white;
            padding: 12px;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            font-size: 0.85rem;
            pointer-events: none;
            max-width: 240px;
            line-height: 1.4;
        }

        .fc-event-tooltip-title {
            font-weight: 600;
            margin-bottom: 6px;
            color: var(--primary-blue);
        }

        .fc-event-tooltip-content {
            color: #555;
        }

        .fc-event-tooltip-content div {
            margin-bottom: 4px;
        }

        /* Responsive adjustments */
        @media (max-width: 1200px) {
            .container {
                margin-left: 100px;
                max-width: calc(100% - 120px);
            }
            
            .calendar-header {
                padding-right: 0;
                flex-direction: column;
                align-items: flex-start;
            }
            
            .status-legend {
                position: static;
                margin-top: 15px;
            }
        }

        .refresh-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: rgba(0,0,0,0.7);
            color: white;
            padding: 5px 10px;
            border-radius: 4px;
            font-size: 12px;
            z-index: 1000;
            display: none;
        }

        @media (max-width: 768px) {
            .container {
                margin-left: 20px;
                max-width: calc(100% - 40px);
                padding: 15px;
            }
            
            .filter-controls {
                flex-direction: column;
                width: 100%;
            }
            
            select {
                width: 100%;
            }
            
            #calendar {
                min-width: 100%;
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

        <h1 class="page-title">Calendar</h1>
        
        <!-- Main Content -->
        <div class="container">
            <div class="calendar-header">
                <div class="filter-controls">
                    <select id="dentistFilter">
                        <option value="">All Dentists</option>
                        <?php while ($dentist = $dentists->fetch_assoc()): ?>
                            <option value="<?= $dentist['Dentist_ID'] ?>"><?= htmlspecialchars($dentist['name']) ?></option>
                        <?php endwhile; ?>
                    </select>
                    <select id="statusFilter">
                        <option value="">Default</option>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>
                
                <!-- Status Legend -->
                <div class="status-legend">
                    <div class="legend-item">
                        <span class="status-indicator pending"></span>
                        <span>Pending</span>
                    </div>
                    <div class="legend-item">
                        <span class="status-indicator confirmed"></span>
                        <span>Confirmed</span>
                    </div>
                </div>
            </div>
            
            <div id="calendar"></div>
        </div>
    </div>

    <!-- Appointment Details Modal -->
    <div id="appointmentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Appointment Details</h3>
                <button class="close-btn">&times;</button>
            </div>
            <div class="appointment-details">
                <p><strong id="modalPatient"></strong> with <strong id="modalDentist"></strong> at <strong id="modalTime"></strong></p>
                <p><span class="detail-label">Service:</span> <span id="modalServices"></span></p>
                <p><span class="detail-label">Date:</span> <span id="modalDate"></span></p>
                <p><span class="detail-label">Status:</span> <span id="modalStatus"></span></p>
            </div>
            <div class="action-buttons">
                <button class="btn btn-primary" id="confirmBtn">Confirm</button>
                <button class="btn btn-danger" id="cancelBtn">Cancel</button>
                <button class="btn btn-secondary" id="closeModalBtn">Close</button>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // ==================== AUTO-REFRESH FUNCTIONALITY ====================
            const refreshInterval = 10000; // 10 seconds
            let refreshTimer;
            const refreshIndicator = document.createElement('div');
            refreshIndicator.className = 'refresh-indicator';
            refreshIndicator.textContent = 'Refreshing calendar...';
            document.body.appendChild(refreshIndicator);

            function startAutoRefresh() {
                refreshTimer = setInterval(refreshCalendar, refreshInterval);
            }
            
            function stopAutoRefresh() {
                clearInterval(refreshTimer);
            }
            
            function refreshCalendar() {
                if (document.hidden) return;
                
                refreshIndicator.style.display = 'block';
                calendar.refetchEvents();
                
                // Hide indicator after refresh completes
                setTimeout(() => {
                    refreshIndicator.style.display = 'none';
                }, 2000);
            }

            // Start auto-refresh when page loads
            startAutoRefresh();
            
            // Pause auto-refresh when tab is not active
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    stopAutoRefresh();
                } else {
                    startAutoRefresh();
                    refreshCalendar(); // Refresh immediately when tab becomes active
                }
            });

            const defaultAppointments = <?php echo json_encode($appointments); ?>;
            const allAppointments = <?php echo json_encode($allAppointments); ?>;
            const calendarEl = document.getElementById('calendar');
            const dentistFilter = document.getElementById('dentistFilter');
            const statusFilter = document.getElementById('statusFilter');
            const modal = document.getElementById('appointmentModal');
            const closeBtn = document.querySelector('.close-btn');
            const closeModalBtn = document.getElementById('closeModalBtn');
            const confirmBtn = document.getElementById('confirmBtn');
            const cancelBtn = document.getElementById('cancelBtn');
            let tooltip = null;
            
            // Initialize calendar
            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                initialDate: new Date(),
                navLinks: true,
                editable: false,
                dayMaxEvents: false, // Show all events in month view
                businessHours: {
                    daysOfWeek: [1, 2, 3, 4, 5, 6],
                    startTime: '09:00',
                    endTime: '18:00'
                },
                slotMinTime: '09:00',
                slotMaxTime: '18:00',
                events: function(fetchInfo, successCallback, failureCallback) {
                    // Use allAppointments only when filtering for Completed/Cancelled
                    const appointmentsToUse = 
                        statusFilter.value === 'Completed' || statusFilter.value === 'Cancelled' || statusFilter.value === ''
                            ? allAppointments
                            : defaultAppointments;
                    
                    let filteredAppointments = [...appointmentsToUse];
                    
                    // Apply dentist filter
                    if (dentistFilter.value) {
                        filteredAppointments = filteredAppointments.filter(app => 
                            app.Dentist_ID == dentistFilter.value
                        );
                    }
                    
                    // Apply status filter
                    if (statusFilter.value) {
                        filteredAppointments = filteredAppointments.filter(app => 
                            app.Appointment_Status === statusFilter.value
                        );
                    } else {
                        // If no status filter, show only Pending/Confirmed by default
                        filteredAppointments = filteredAppointments.filter(app => 
                            ['Pending', 'Confirmed'].includes(app.Appointment_Status)
                        );
                    }
                    
                    // Format for FullCalendar
                    const events = filteredAppointments.map(app => ({
                        id: app.Appointment_ID,
                        title: app.patient_name || 'Not specified',
                        start: `${app.Appointment_Date}T${app.start_time}`,
                        end: `${app.Appointment_Date}T${app.end_time}`,
                        extendedProps: {
                            dentistId: app.Dentist_ID,
                            services: app.services || 'No service specified',
                            status: app.Appointment_Status,
                            dentistName: app.dentist_name,
                            patientName: app.patient_name || 'Not specified',
                            time: `${formatTime(app.start_time)} - ${formatTime(app.end_time)}`,
                            patientId: app.Patient_ID
                        },
                        className: `fc-event-${app.Appointment_Status.toLowerCase()}`
                    }));
                    
                    successCallback(events);
                },
                dateClick: function(info) {
                    if (info.view.type === 'dayGridMonth') {
                        calendar.changeView('timeGridWeek', info.dateStr);
                    }
                },
                eventClick: function(info) {
                    const event = info.event;
                    const status = event.extendedProps.status;
                    const patientId = event.extendedProps.patientId;
                    
                    document.getElementById('modalPatient').textContent = 
                        event.extendedProps.patientName || 'Not specified';
                    document.getElementById('modalDentist').textContent = 
                        event.extendedProps.dentistName || 'Not specified';
                    document.getElementById('modalDate').textContent = 
                        event.start.toLocaleDateString();
                    document.getElementById('modalTime').textContent = 
                        event.extendedProps.time;
                    document.getElementById('modalServices').textContent = 
                        event.extendedProps.services || 'No service specified';
                    document.getElementById('modalStatus').textContent = 
                        event.extendedProps.status;
                    
                    // Set data attribute for action buttons
                    confirmBtn.dataset.appointmentId = event.id;
                    cancelBtn.dataset.appointmentId = event.id;
                    cancelBtn.dataset.patientId = patientId;
                    
                    // Update button labels based on status
                    if (status === 'Confirmed') {
                        confirmBtn.textContent = 'Mark as Done';
                        cancelBtn.textContent = 'View Patient';
                    } else if (status === 'Pending') {
                        confirmBtn.textContent = 'Confirm';
                        cancelBtn.textContent = 'View Patient';
                    } else {
                        // For other statuses (Completed, Cancelled), hide the action buttons
                        confirmBtn.style.display = 'none';
                        cancelBtn.style.display = 'none';
                    }
                    
                    modal.style.display = 'flex';
                },
                eventDidMount: function(info) {
                    // Add tooltip to month view dots
                    if (info.view.type === 'dayGridMonth') {
                        const dot = info.el;
                        
                        dot.addEventListener('mouseenter', (e) => {
                            if (!tooltip) {
                                tooltip = document.createElement('div');
                                tooltip.className = 'fc-event-tooltip';
                                tooltip.innerHTML = `
                                    <div class="fc-event-tooltip-title">${info.event.extendedProps.patientName || 'Not specified'}</div>
                                    <div class="fc-event-tooltip-content">
                                        <div style="color: green; font-weight: 500;">With: ${info.event.extendedProps.dentistName || 'Not specified'}</div>
                                        <div>Time: ${info.event.extendedProps.time}</div>
                                        <div>Service: ${info.event.extendedProps.services || 'No service specified'}</div>
                                        <div>Status: ${info.event.extendedProps.status}</div>
                                    </div>
                                `;
                                document.body.appendChild(tooltip);
                            }
                            
                            // Position tooltip near mouse cursor
                            const mouseX = e.clientX;
                            const mouseY = e.clientY;
                            tooltip.style.left = `${mouseX + 15}px`;
                            tooltip.style.top = `${mouseY + 15}px`;
                            
                            // Adjust if tooltip goes off screen
                            const tooltipRect = tooltip.getBoundingClientRect();
                            if (tooltipRect.right > window.innerWidth) {
                                tooltip.style.left = `${mouseX - tooltipRect.width - 15}px`;
                            }
                            if (tooltipRect.bottom > window.innerHeight) {
                                tooltip.style.top = `${mouseY - tooltipRect.height - 15}px`;
                            }
                        });
                        
                        dot.addEventListener('mouseleave', () => {
                            if (tooltip) {
                                tooltip.remove();
                                tooltip = null;
                            }
                        });
                    }
                    
                    // Ensure correct status class is applied in Week/Day view
                    if (info.view.type === 'timeGridWeek' || info.view.type === 'timeGridDay') {
                        info.el.classList.add(`fc-timegrid-event-${info.event.extendedProps.status.toLowerCase()}`);
                        
                        // Calculate and set height based on duration
                        const duration = (info.event.end - info.event.start) / (1000 * 60); // duration in minutes
                        const height = Math.max(60, duration * 1.5); // Minimum height of 60px, scale by duration
                        info.el.style.height = `${height}px`;
                        
                        // Auto-resize text for Week view
                        if (info.view.type === 'timeGridWeek') {
                            const eventEl = info.el.querySelector('.fc-event-main');
                            if (eventEl) {
                                const availableHeight = height - 16; // Subtract padding
                                const fontSize = Math.min(10, Math.max(9, availableHeight / 5));
                                eventEl.style.fontSize = `${fontSize}px`;
                            }
                        }
                        
                        // Add spacing for Day view
                        if (info.view.type === 'timeGridDay') {
                            info.el.classList.add('fc-timegrid-event-day');
                        }
                    }
                },
                eventContent: function(arg) {
                    if (arg.view.type === 'dayGridMonth') {
                        // Month view - status indicator dot
                        const dot = document.createElement('div');
                        dot.className = 'fc-event-dot';
                        return { domNodes: [dot] };
                    } else {
                        // Week/Day view - detailed event info
                        const eventEl = document.createElement('div');
                        eventEl.className = 'fc-event-main';
                        
                        const duration = (arg.event.end - arg.event.start) / (1000 * 60); // duration in minutes
                        const durationText = duration > 60 
                            ? `${Math.floor(duration / 60)}h ${duration % 60}m` 
                            : `${duration}m`;
                        
                        eventEl.innerHTML = `
                            <div class="fc-event-title-container">
                                <div class="fc-event-title">${arg.event.extendedProps.patientName || 'Not specified'}</div>
                                <div class="fc-event-dentist">with ${arg.event.extendedProps.dentistName || 'Not specified'}</div>
                                <div class="fc-event-time">${arg.timeText} (${durationText})</div>
                                <div class="fc-event-service">${arg.event.extendedProps.services || 'No service specified'}</div>
                                <div class="fc-event-status">Status: ${arg.event.extendedProps.status}</div>
                            </div>
                        `;
                        return { domNodes: [eventEl] };
                    }
                },
                views: {
                    timeGridWeek: {
                        allDaySlot: false,
                        slotLabelFormat: {
                            hour: 'numeric',
                            minute: '2-digit',
                            omitZeroMinute: false,
                            meridiem: 'short'
                        },
                        dayHeaderFormat: { weekday: 'short', month: 'short', day: 'numeric' },
                        slotLabelInterval: '01:00',
                        slotDuration: '00:30:00',
                        expandRows: true,
                        eventMinHeight: 60,
                        eventOrder: 'start,-duration,title',
                        eventOverlap: false,
                        slotEventOverlap: false,
                        nowIndicator: true,
                        eventMaxStack: false, // Show ALL events - no stacking limit
                        eventTimeFormat: {
                            hour: 'numeric',
                            minute: '2-digit',
                            meridiem: 'short'
                        },
                        eventDisplay: 'block',
                        dayMaxEvents: false // Show ALL events without hiding any
                    },
                    timeGridDay: {
                        allDaySlot: false,
                        slotLabelFormat: {
                            hour: 'numeric',
                            minute: '2-digit',
                            omitZeroMinute: false,
                            meridiem: 'short'
                        },
                        slotLabelInterval: '01:00',
                        slotDuration: '00:30:00',
                        expandRows: true,
                        eventMinHeight: 60,
                        eventOrder: 'start,-duration,title',
                        eventOverlap: false,
                        slotEventOverlap: false,
                        nowIndicator: true,
                        eventMaxStack: false, // Show ALL events - no stacking limit
                        eventTimeFormat: {
                            hour: 'numeric',
                            minute: '2-digit',
                            meridiem: 'short'
                        },
                        eventDisplay: 'block',
                        dayMaxEvents: false // Show ALL events without hiding any
                    },
                    dayGridMonth: {
                        dayHeaderFormat: { weekday: 'short' },
                        fixedWeekCount: false,
                        dayMaxEventRows: false, // Allow unlimited events to be shown
                        dayMaxEvents: false // Allow unlimited events to be shown
                    }
                }
            });

            calendar.render();
            
            // Filter change handlers
            dentistFilter.addEventListener('change', function() {
                calendar.refetchEvents();
            });

            statusFilter.addEventListener('change', function() {
                calendar.refetchEvents();
            });

            // Modal handlers
            closeBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            closeModalBtn.addEventListener('click', function() {
                modal.style.display = 'none';
            });

            window.addEventListener('click', function(event) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });

            // Action button handlers
            confirmBtn.addEventListener('click', function() {
                const appointmentId = this.dataset.appointmentId;
                const currentStatus = document.getElementById('modalStatus').textContent;
                
                // Determine the new status based on current status
                const newStatus = currentStatus === 'Confirmed' ? 'Completed' : 'Confirmed';
                updateAppointmentStatus(appointmentId, newStatus);
            });

            cancelBtn.addEventListener('click', function() {
                const appointmentId = this.dataset.appointmentId;
                const patientId = this.dataset.patientId;
                const currentStatus = document.getElementById('modalStatus').textContent;
                
                if (currentStatus === 'Pending' || currentStatus === 'Confirmed') {
                    // For Pending/Confirmed appointments, redirect to patient records
                    window.location.href = `patient_records.php?patient_id=${patientId}`;
                } else {
                    // For other statuses, cancel the appointment
                    updateAppointmentStatus(appointmentId, 'Cancelled');
                }
            });

            // Modify the updateAppointmentStatus function to trigger a refresh
            function updateAppointmentStatus(appointmentId, status) {
                fetch('appointment_status.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `appointment_id=${appointmentId}&status=${status}`
                })
                .then(response => {
                    return response.json().then(data => {
                        if (data.success) {
                            return data;
                        }
                        throw new Error(data.message || 'Update failed');
                    });
                })
                .then(data => {
                    alert(data.message || 'Status updated successfully');
                    modal.style.display = 'none';
                    refreshCalendar(); // Refresh the calendar immediately after update
                })
                .catch(error => {
                    console.error('Update error:', error);
                    if (!modal.style.display || modal.style.display !== 'none') {
                        alert(error.message || 'Failed to update appointment');
                    }
                });
            }

            function formatTime(timeString) {
                if (!timeString) return '';
                const [hours, minutes] = timeString.split(':');
                const hour = parseInt(hours);
                const ampm = hour >= 12 ? 'PM' : 'AM';
                const hour12 = hour % 12 || 12;
                return `${hour12}:${minutes} ${ampm}`;
            }
        });
    </script>
        <script src="notifications/notification_script.js"></script>

</body>
</html>