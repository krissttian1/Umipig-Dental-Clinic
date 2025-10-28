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

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Records</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="patient_records.css">
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

<div class="content-area">
    <h1 style="color: royalblue; margin-top: 70px; margin-left: 120px;">Patient Records</h1>
    <table class="records-table">
<tr>
    <th>ID</th>
    <th>Name</th>
    <th>Age</th>
    <th>Birthdate</th>
    <th>Sex</th>
    <th>Address</th> <!-- New Address header -->
    <th>Action</th>
</tr>

<?php
$sql = "SELECT id, name, age, birthdate, sex, address FROM patient_records";
$result = $conn->query($sql);
if ($result->num_rows > 0):
    while ($row = $result->fetch_assoc()):
?>
<tr>
    <td><?= htmlspecialchars($row['id']) ?></td>
    <td><?= htmlspecialchars($row['name']) ?></td>
    <td><?= htmlspecialchars($row['age']) ?></td>
    <td><?= htmlspecialchars($row['birthdate']) ?></td>
    <td><?= htmlspecialchars($row['sex']) ?></td>
    <td><?= htmlspecialchars($row['address']) ?></td> <!-- New Address cell -->
    <td>
        <a class="btn-view" href="javascript:void(0)" onclick="openModal(<?= $row['id'] ?>)">View</a>
        <a href="dental_records.php?id=<?= $row['id'] ?>" class="btn-view">View Chart</a>
    </td>
</tr>

        <?php endwhile; else: ?>
        <tr><td colspan="9" style="text-align: center;">No records found.</td></tr>

        <?php endif; $conn->close(); ?>
    </table>
</div>

<div id="viewModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Patient Details</h3>
        <form id="patientForm">
            <div id="formFields"></div>
        </form>
    </div>
</div>
    <script src="patient_records.js"></script>
    <script src="notifications/notification_script.js"></script>
</body>
</html>
