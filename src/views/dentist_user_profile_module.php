<?php
session_start();



// ==================== CSRF Protection ====================
function generate_csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf_token($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ==================== Form Processing ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once 'db_connection.php';
    
    // Determine which form was submitted
    if (isset($_POST['update_profile'])) {
        // Update Profile Handler
        if (!validate_csrf_token($_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }

        $dentist_id = $_SESSION['user_id'];
        $name = filter_input(INPUT_POST, 'name', FILTER_SANITIZE_STRING);
        $license_number = filter_input(INPUT_POST, 'license_number', FILTER_SANITIZE_STRING);
        $birthdate = filter_input(INPUT_POST, 'birthdate', FILTER_SANITIZE_STRING);
        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
        $phone = filter_input(INPUT_POST, 'phone', FILTER_SANITIZE_STRING);
        $recovery_email = filter_input(INPUT_POST, 'recovery_email', FILTER_SANITIZE_EMAIL);
        $address = filter_input(INPUT_POST, 'address', FILTER_SANITIZE_STRING);

        // Only validate email fields if they're not empty
        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            die("Invalid email format");
        }
        
        if (!empty($recovery_email) && !filter_var($recovery_email, FILTER_VALIDATE_EMAIL)) {
            die("Invalid recovery email format");
        }

        try {
            $stmt = $conn->prepare("UPDATE dentists SET 
                                  name = ?, 
                                  license_number = ?, 
                                  birthdate = ?, 
                                  email = ?, 
                                  phone = ?, 
                                  recovery_email = ?, 
                                  address = ? 
                                  WHERE Dentist_ID = ?");
            
            $stmt->bind_param("sssssssi", $name, $license_number, $birthdate, $email, $phone, $recovery_email, $address, $dentist_id);
            $stmt->execute();
            
            header("Location: dentist_user_profile_module.php?success=1");
            $stmt->close();
        } catch (Exception $e) {
            die("Database error: " . $e->getMessage());
        }

        $conn->close();
        exit;
        
    } elseif (isset($_POST['request_specialization'])) {
        // Specialization Request Handler
        if (!validate_csrf_token($_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }

        $dentist_id = $_SESSION['user_id'];
        $requested_service = filter_input(INPUT_POST, 'specialization', FILTER_SANITIZE_STRING);
        $dentist_name = $_SESSION['username'];

        try {
            // Check if there's already a pending request for this service
            $check_stmt = $conn->prepare("SELECT request_id FROM specialization_requests WHERE dentist_id = ? AND requested_service = ? AND status = 'pending'");
            $check_stmt->bind_param("is", $dentist_id, $requested_service);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                header("Location: dentist_user_profile_module.php?error=You already have a pending request for this specialization");
                exit;
            }
            $check_stmt->close();

            // Insert the specialization request
            $stmt = $conn->prepare("INSERT INTO specialization_requests (dentist_id, dentist_name, requested_service) VALUES (?, ?, ?)");
            $stmt->bind_param("iss", $dentist_id, $dentist_name, $requested_service);
            $stmt->execute();
            $request_id = $stmt->insert_id;
            $stmt->close();

            // ===== NOTIFICATION TRIGGER FOR SPECIALIZATION REQUEST =====
            require_once 'notifications/notification_functions.php';
            createAdminNotification(
                'specialization',
                'Specialization Request',
                'Dentist ' . $dentist_name . ' has requested to add "' . $requested_service . '" to their specialization.',
                'medium',
                'admin_specialization_requests.php',
                $request_id,
                'specialization_request'  // This enables the action buttons
            );
            // ===== END NOTIFICATION TRIGGER =====

            header("Location: dentist_user_profile_module.php?success=Specialization request submitted for admin approval");
            exit;
            
        } catch (Exception $e) {
            die("Database error: " . $e->getMessage());
        }

        $conn->close();
        exit;
        
} elseif (isset($_POST['update_availability'])) {
    // Update Availability Handler
    if (!validate_csrf_token($_POST['csrf_token'])) {
        die("CSRF token validation failed");
    }

    $dentist_id = $_SESSION['user_id'];
    
    try {
        // Clear existing availability for next 3 months (matches system settings)
        $today = date('Y-m-d');
        $endOfPeriod = date('Y-m-d', strtotime('+3 months'));
        $stmt = $conn->prepare("DELETE FROM dentistavailability WHERE Dentist_ID = ? AND available_date BETWEEN ? AND ?");
        $stmt->bind_param("iss", $dentist_id, $today, $endOfPeriod);
        $stmt->execute();
        
        // Process each day's availability
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        // Define date range (3 months - matches system settings)
        $today = date('Y-m-d');
        $endDate = date('Y-m-d', strtotime('+3 months'));
        
        $currentDate = $today;
        $slots_created = 0;
        
        while (strtotime($currentDate) <= strtotime($endDate)) {
            $currentDay = date('l', strtotime($currentDate)); // Get day name
            
            // Check if this day is selected in the form
            $dayField = "day_" . $currentDay;
            if (isset($_POST[$dayField]) && $_POST[$dayField] == '1') {
                // Get the start and end times from the form - FIXED LOGIC
                $startField = $currentDay . "_start";
                $endField = $currentDay . "_end";
                
                // FIX: Always use the form values, don't check if they're "empty"
                $startTimeValue = $_POST[$startField] ?? '09:00'; // Fallback to default
                $endTimeValue = $_POST[$endField] ?? '17:00';     // Fallback to default
                
                // Validate that we have time values
                if (!empty($startTimeValue) && !empty($endTimeValue)) {
                    $startTimestamp = strtotime($startTimeValue);
                    $endTimestamp = strtotime($endTimeValue);
                    
                    // Validate time range
                    if ($startTimestamp >= $endTimestamp) {
                        die("End time must be after start time for $currentDay");
                    }
                    
                    // Generate 30-minute time slots
                    $interval = 30 * 60; // 30 minutes in seconds
                    
                    for ($time = $startTimestamp; $time < $endTimestamp; $time += $interval) {
                        $availableTime = date('H:i:s', $time);
                        $endTimeSlot = date('H:i:s', $time + $interval); // Calculate end time
                        
                        // Ensure we don't exceed the end time
                        if (($time + $interval) > $endTimestamp) {
                            break;
                        }
                        
                        // Insert individual 30-minute slots with ALL required fields
                        $availability_sql = "INSERT INTO dentistavailability 
                                            (Dentist_ID, available_date, available_time, day_of_week, end_time) 
                                            VALUES (?, ?, ?, ?, ?)";
                        $availability_stmt = $conn->prepare($availability_sql);
                        $availability_stmt->bind_param("issss", $dentist_id, $currentDate, $availableTime, $currentDay, $endTimeSlot);
                        $availability_stmt->execute();
                        $availability_stmt->close();
                        $slots_created++;
                    }
                }
            }
            $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
        }
        
        // Log for debugging
        error_log("Dentist $dentist_id updated availability: $slots_created slots created");
        
        header("Location: dentist_user_profile_module.php?success=1");
    } catch (Exception $e) {
        die("Database error: " . $e->getMessage());
    }

    $conn->close();
    exit;
}

    } elseif (isset($_POST['update_password'])) {
        // Update Password Handler
        if (!validate_csrf_token($_POST['csrf_token'])) {
            die("CSRF token validation failed");
        }

        $dentist_id = $_SESSION['user_id'];
        $current_password = $_POST['currentPassword'];
        $new_password = $_POST['newPassword'];
        $confirm_password = $_POST['confirmPassword'];

        if ($new_password !== $confirm_password) {
            die("Passwords do not match!");
        }

        try {
            $stmt = $conn->prepare("SELECT password FROM dentists WHERE Dentist_ID = ?");
            $stmt->bind_param("i", $dentist_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 0) {
                die("User not found");
            }
            
            $user = $result->fetch_assoc();
            if (!password_verify($current_password, $user['password'])) {
                die("Current password is incorrect");
            }
            
            $stmt->close();
            
            // Hash new password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            
            // Update password
            $stmt = $conn->prepare("UPDATE dentists SET password = ? WHERE Dentist_ID = ?");
            $stmt->bind_param("si", $hashed_password, $dentist_id);
            $stmt->execute();
            
            header("Location: dentist_user_profile_module.php?success=1");
            $stmt->close();
        } catch (Exception $e) {
            die("Database error: " . $e->getMessage());
        }

        $conn->close();
        exit;
    }


// ==================== Main Profile Page ====================
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'dentist') {
    header("Location: login.php");
    exit;
}

// Generate CSRF token
$csrf_token = generate_csrf_token();

// Database connection
require_once 'db_connection.php';

// Fetch dentist information
$dentist_id = $_SESSION['user_id'];
$user = [];
$services = [];
$availability = [];
$pending_requests = [];

try {
    // Fetch basic dentist info
    $stmt = $conn->prepare("SELECT * FROM dentists WHERE Dentist_ID = ?");
    $stmt->bind_param("i", $dentist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        if (!empty($user['birthdate'])) {
            $birthdate = new DateTime($user['birthdate']);
            $today = new DateTime();
            $age = $birthdate->diff($today)->y;
            $user['age'] = $age;
        }
    } else {
        die("Dentist not found in database");
    }
    $stmt->close();
    
    // Fetch all available services for specialization
    $stmt = $conn->prepare("SELECT * FROM services");
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $services[] = $row;
    }
    $stmt->close();
    
    // Fetch pending specialization requests
    $stmt = $conn->prepare("SELECT * FROM specialization_requests WHERE dentist_id = ? AND status = 'pending'");
    $stmt->bind_param("i", $dentist_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $pending_requests[] = $row;
    }
    $stmt->close();
    
// Fetch current availability - FIXED VERSION
$availability = [];
$stmt = $conn->prepare("
    SELECT 
        day_of_week,
        MIN(available_time) as earliest_start,
        MAX(end_time) as latest_end
    FROM dentistavailability 
    WHERE Dentist_ID = ? 
    AND day_of_week IS NOT NULL
    AND end_time IS NOT NULL
    AND available_date >= CURDATE()
    GROUP BY day_of_week
    ORDER BY FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday')
");
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

// Process availability data - SIMPLIFIED
while ($row = $result->fetch_assoc()) {
    $day = $row['day_of_week'];
    $availability[$day] = [
        'start' => $row['earliest_start'],
        'end' => $row['latest_end']
    ];
}
$stmt->close();

// Process availability data - SIMPLIFIED
while ($row = $result->fetch_assoc()) {
    $day = $row['day_of_week'];
    $availability[$day] = [
        'start' => $row['earliest_start'],
        'end' => $row['latest_end']
    ];
}
$stmt->close();
$stmt->bind_param("i", $dentist_id);
$stmt->execute();
$result = $stmt->get_result();

// Group by day and find time ranges
$availability_by_day = [];
while ($row = $result->fetch_assoc()) {
    $day = $row['day_of_week'];
    $start_time = date('H:i:s', strtotime($row['available_time']));
    $end_time = date('H:i:s', strtotime($row['end_time']));
    
    if (!isset($availability_by_day[$day])) {
        $availability_by_day[$day] = [];
    }
    $availability_by_day[$day][] = ['start' => $start_time, 'end' => $end_time];
}

// Convert to time ranges for display
foreach ($availability_by_day as $day => $time_slots) {
    if (!empty($time_slots)) {
        // Find the earliest start and latest end time for each day
        $start_times = array_column($time_slots, 'start');
        $end_times = array_column($time_slots, 'end');
        $earliest_start = min($start_times);
        $latest_end = max($end_times);
        $availability[$day] = [
            'start' => $earliest_start,
            'end' => $latest_end
        ];
    }
}
$stmt->close();


    
} catch (Exception $e) {
    die("Database error: " . $e->getMessage());
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dentist Profile</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <style>
        /* General Styles */
        body {
            font-family: 'Poppins', sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f5f5f5;
            color: #333;
        }

        .settings-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            margin-top: 50px;
        }

                .pending-requests {
            margin: 20px 0;
            padding: 15px;
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 4px;
        }
        
        .request-item {
            padding: 10px;
            margin: 10px 0;
            background: white;
            border-left: 4px solid #ffc107;
            border-radius: 4px;
        }
        
        .request-status {
            display: inline-block;
            padding: 4px 8px;
            background: #ffc107;
            color: #856404;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .success-message {
            padding: 10px;
            background: #d4edda;
            color: #155724;
            border-radius: 4px;
            margin-bottom: 20px;
        }
        
        .error-message {
            padding: 10px;
            background: #f8d7da;
            color: #721c24;
            border-radius: 4px;
            margin-bottom: 20px;
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
            position: page;
            top: 0;
            left: 0;
            z-index: 1000;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
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
            color: #0066cc;
        }

        .main-nav a.active {
            color: #0056b3;
            font-weight: bold;
        }

        .header-right {
            display: flex;
            gap: 20px;
            margin-right: 10px;
            align-items: center;
        }

        .profile-icon i {
            margin-top: 7px;
            color: royalblue;
            font-size: 1.1em;
            transition: color 0.3s;
        }

        .profile-icon i:hover {
            color: green;
        }

        .auth-link {
            text-decoration: none;
            color: #0066cc;
            font-weight: 600;
            font-size: 12px;
            transition: color 0.3s;
        }

        .auth-link:hover {
            color: #003d80;
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
            color: #0066cc;
            text-decoration: none;
        }

        .welcome-text .auth-link:hover {
            color: #003d80;
        }

        /* Dashboard Layout */
        .dashboard {
            display: flex;
            min-height: calc(100vh - 90px);
        }

        .menu-icon {
            color: white;
            font-size: 24px;
            padding: 10px 20px;
            cursor: pointer;
            display: none;
        }

        .nav-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .nav-item {
            border-bottom: 1px solid #34495e;
        }

        .nav-link {
            display: block;
            padding: 15px 20px;
            color: #ecf0f1;
            text-decoration: none;
            transition: all 0.3s;
        }

        .nav-link:hover {
            background: #34495e;
        }

        .nav-link.active {
            background: #3498db;
        }

        .main-content {
            flex: 1;
            padding: 20px;
            background: white;
        }

        /* Tabbed Layout */
        .tabbed-layout {
            display: flex;
            margin-top: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: hidden;
        }

        .category-column {
            width: 250px;
            background: #f8f9fa;
            border-right: 1px solid #ddd;
        }

        .category-tab {
            display: block;
            width: 100%;
            padding: 15px 20px;
            text-align: left;
            border: none;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s;
            border-bottom: 1px solid #ddd;
        }

        .category-tab:last-child {
            border-bottom: none;
        }

        .category-tab.active {
            background: #007bff;
            color: white;
        }

        .category-tab:hover:not(.active) {
            background: #e9ecef;
        }

        .content-column {
            flex: 1;
            padding: 20px;
            background: white;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 16px;
        }

        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }

        .btn {
            padding: 10px 20px;
            background: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 16px;
            transition: background 0.3s;
        }

        .btn-secondary {
            background: #6c757d;
        }

        .btn-secondary:hover {
            background: #5a6268;
        }

        .btn:hover {
            background: #0056b3;
        }

        .back-button {
            margin-top: 0px;
            padding: 10px 15px;
            background-color: rgb(181, 187, 194);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }


        /* Weekly Availability Styles */
        .weekly-availability {
            margin-top: 20px;
        }
        
        .day-availability {
            margin-bottom: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 4px;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .day-availability label {
            display: flex;
            align-items: center;
            width: 100px;
            font-weight: 500;
        }
        
        .day-availability input[type="checkbox"] {
            margin-right: 10px;
        }
        
        .day-availability input[type="time"] {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        
        .time-range {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-left: 20px;
        }
        
        .availability-instructions {
            margin-bottom: 20px;
            padding: 15px;
            background: #e7f4ff;
            border-radius: 4px;
            border-left: 4px solid #007bff;
        }

        .star-bullets {
            list-style: none;
            padding-left: 0;
        }

        .star-bullets li::before {
            content: "⭐";
            margin-right: 5px; /* space between star and text */
        }


        /* Responsive Styles */
        @media (max-width: 768px) {
            .dashboard {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
            }
            
            .tabbed-layout {
                flex-direction: column;
            }
            
            .category-column {
                width: 100%;
                display: flex;
                overflow-x: auto;
            }
            
            .category-tab {
                white-space: nowrap;
                border-right: 1px solid #ddd;
                border-bottom: none;
            }
            
            .day-availability {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .time-range {
                margin-left: 0;
                margin-top: 10px;
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header>
        <div class="logo-container">
            <div class="logo-circle">
                <img src="images/UmipigDentalClinic_Logo.jpg" alt="Umipig Dental Clinic" />
            </div>
            <div class="clinic-info">
                <h1>Umipig Dental Clinic</h1>
                <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
            </div>
        </div>

        <div class="header-right">
            <?php if (isset($_SESSION['username'])): ?>
                <span class="welcome-text">
                    Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! &nbsp; | &nbsp;
                    <a href="logout.php" class="auth-link">Logout</a>
                </span>
            <?php else: ?>
                <a href="register.php" class="auth-link">Register</a>
                <span>|</span>
                <a href="login.php" class="auth-link">Login</a>
            <?php endif; ?>
        </div>
    </header>

    <div class="main-content">
        <div class="settings-container">
            <button onclick="history.back()" class="back-button">
                ← Back
            </button>
            
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message"><?php echo htmlspecialchars($_GET['success']); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['error'])): ?>
                <div class="error-message"><?php echo htmlspecialchars($_GET['error']); ?></div>
            <?php endif; ?>
            
            <!-- Tabbed Layout -->
            <div class="tabbed-layout">
                <!-- Left Column: Category Tabs -->
                <div class="category-column">
                    <button class="category-tab active" data-target="basic-info">Basic Information</button>
                    <button class="category-tab" data-target="specialization">Specialization</button>
                    <button class="category-tab" data-target="availability">Availability</button>
                    <button class="category-tab" data-target="account-settings">Account Settings</button>
                </div>

                <!-- Right Column: Content -->
                <div class="content-column">
                    <!-- Basic Information Content -->
                    <div id="basic-info" class="content-section active">
                        <h2>Basic Information</h2>
                        <?php if (isset($_GET['success']) && $_GET['success'] === '1'): ?>
                            <div class="success-message">✔ Profile updated successfully!</div>
                        <?php endif; ?>
                        <form id="basicInfoForm" method="POST" action="dentist_user_profile_module.php">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                            <input type="hidden" name="update_profile" value="1">
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($user['name'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="license_number">License Number</label>
                                <input type="text" id="license_number" name="license_number" value="<?php echo htmlspecialchars($user['license_number'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="age">Age</label>
                                <input type="number" id="age" name="age" value="<?php echo htmlspecialchars($user['age'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="birthdate">Birthdate</label>
                                <input type="date" id="birthdate" name="birthdate" value="<?php echo htmlspecialchars($user['birthdate'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="recovery_email">Recovery Email</label>
                                <input type="email" id="recovery_email" name="recovery_email" value="<?php echo htmlspecialchars($user['recovery_email'] ?? ''); ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="address">Address</label>
                                <textarea id="address" name="address" rows="3" readonly><?php echo htmlspecialchars($user['address'] ?? ''); ?></textarea>
                            </div>
                            <button type="button" id="editBasicInfoBtn" class="btn">Edit Information</button>
                            <button type="submit" id="saveBasicInfoBtn" class="btn" style="display:none;">Save Changes</button>
                            <button type="button" id="cancelEditBtn" class="btn btn-secondary" style="display:none;">Cancel</button>
                        </form>
                    </div>

                    <!-- Specialization Content -->
                    <div id="specialization" class="content-section">
                        <h2>Specialization</h2>
                        <div class="specialization-info">
                            <h3>Current Specialization</h3>
                            <?php if (!empty($user['specialization'])): ?>
                                <?php
                                    // Split by comma if there are multiple specializations
                                    $specializations = explode(',', $user['specialization']);
                                ?>
                                <ul class="star-bullets">
                                    <?php foreach ($specializations as $spec): ?>
                                        <li><?php echo htmlspecialchars(trim($spec)); ?></li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <p>No specialization set</p>
                            <?php endif; ?>
                        </div>

                        <!-- Pending Requests Section -->
                        <?php if (!empty($pending_requests)): ?>
                        <div class="pending-requests">
                            <h3>Pending Requests</h3>
                            <?php foreach ($pending_requests as $request): ?>
                                <div class="request-item">
                                    <strong><?php echo htmlspecialchars($request['requested_service']); ?></strong>
                                    <span class="request-status">Pending Approval</span>
                                    <br>
                                    <small>Requested on: <?php echo date('M d, Y g:i A', strtotime($request['request_date'])); ?></small>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <div class="update-specialization">
                            <h3>Request New Specialization</h3>
                            <form id="updateSpecializationForm" method="POST" action="dentist_user_profile_module.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="request_specialization" value="1">
                                <div class="form-group">
                                    <label for="specialization">Select Service to Add</label>
                                    <select id="specialization" name="specialization" required>
                                        <option value="">Select a service</option>
                                        <?php foreach ($services as $service): ?>
                                            <?php 
                                                // Check if this service is already in current specialization
                                                $current_specs = !empty($user['specialization']) ? explode(',', $user['specialization']) : [];
                                                $is_current = in_array($service['service_name'], array_map('trim', $current_specs));
                                            ?>
                                            <option value="<?php echo htmlspecialchars($service['service_name']); ?>"
                                                <?php echo $is_current ? 'disabled' : ''; ?>>
                                                <?php echo htmlspecialchars($service['service_name']); ?>
                                                <?php echo $is_current ? ' (Already in your specialization)' : ''; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <button type="submit" class="btn">Request Specialization</button>
                                <p class="note"><small>Note: Specialization changes require admin approval</small></p>
                            </form>
                        </div>
                    </div>

                <!-- Availability Content -->
                <div id="availability" class="content-section">
                    <h2>Your Availability</h2>
                    <div class="availability-instructions">
                        <p><strong>Instructions:</strong> Check the days you're available and set your working hours. These will be applied to all future dates until you update them again.</p>
                    </div>
                    
                    <form id="availabilityForm" method="POST" action="dentist_user_profile_module.php">
                        <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                        <input type="hidden" name="update_availability" value="1">
                        
                        <div class="weekly-availability">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $defaultTimes = ['start' => '09:00', 'end' => '17:00'];
                            
                            foreach ($days as $day): 
                                // Get current availability or use defaults
                                $dayData = isset($availability[$day]) ? $availability[$day] : $defaultTimes;
                                // Convert to time input format (HH:MM)
                                $start_value = substr($dayData['start'], 0, 5);
                                $end_value = substr($dayData['end'], 0, 5);
                                $is_checked = isset($availability[$day]);
                            ?>
                            <div class="day-availability">
                                <label>
                                    <input type="checkbox" name="day_<?php echo $day; ?>" value="1" <?php echo $is_checked ? 'checked' : ''; ?>>
                                    <?php echo $day; ?>
                                </label>
                                <div class="time-range">
                                    <input type="time" name="<?php echo $day; ?>_start" value="<?php echo $start_value; ?>" <?php echo !$is_checked ? 'disabled' : ''; ?>>
                                    <span>to</span>
                                    <input type="time" name="<?php echo $day; ?>_end" value="<?php echo $end_value; ?>" <?php echo !$is_checked ? 'disabled' : ''; ?>>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <button type="submit" class="btn">Update Availability</button>
                    </form>
                </div>
                    <!-- Account Settings Content -->
                    <div id="account-settings" class="content-section">
                        <h2>Account Settings</h2>
                        
                        <div class="change-password">
                            <h3>Change Password</h3>
                            <form id="passwordForm" method="POST" action="dentist_user_profile_module.php">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <input type="hidden" name="update_password" value="1">
                                <div class="form-group">
                                    <label for="currentPassword">Current Password</label>
                                    <input type="password" id="currentPassword" name="currentPassword" required>
                                </div>
                                <div class="form-group">
                                    <label for="newPassword">New Password</label>
                                    <input type="password" id="newPassword" name="newPassword" required>
                                </div>
                                <div class="form-group">
                                    <label for="confirmPassword">Confirm Password</label>
                                    <input type="password" id="confirmPassword" name="confirmPassword" required>
                                </div>
                                <button type="submit" class="btn">Update Password</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Tab switching functionality
            const tabs = document.querySelectorAll('.category-tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    // Remove active class from all tabs and content sections
                    tabs.forEach(t => t.classList.remove('active'));
                    document.querySelectorAll('.content-section').forEach(section => {
                        section.classList.remove('active');
                    });
                    
                    // Add active class to clicked tab and corresponding section
                    this.classList.add('active');
                    const targetId = this.getAttribute('data-target');
                    document.getElementById(targetId).classList.add('active');
                });
            });
            
            // Edit/Save functionality for basic info
            const editBasicInfoBtn = document.getElementById('editBasicInfoBtn');
            const saveBasicInfoBtn = document.getElementById('saveBasicInfoBtn');
            const cancelEditBtn = document.getElementById('cancelEditBtn');
            const basicInfoForm = document.getElementById('basicInfoForm');
            
            if (editBasicInfoBtn) {
                // Store original values
                const originalValues = {};
                const inputs = basicInfoForm.querySelectorAll('input, textarea, select');
                inputs.forEach(input => {
                    originalValues[input.id] = input.value;
                });
                
                editBasicInfoBtn.addEventListener('click', function() {
                    // Enable all editable fields
                    const inputs = basicInfoForm.querySelectorAll('input[readonly], textarea[readonly]');
                    inputs.forEach(input => {
                        input.removeAttribute('readonly');
                    });
                    
                    // Show save/cancel buttons and hide edit button
                    editBasicInfoBtn.style.display = 'none';
                    saveBasicInfoBtn.style.display = 'inline-block';
                    cancelEditBtn.style.display = 'inline-block';
                });
                
                // Cancel edit functionality
                cancelEditBtn.addEventListener('click', function() {
                    // Restore original values
                    for (const id in originalValues) {
                        if (document.getElementById(id)) {
                            document.getElementById(id).value = originalValues[id];
                        }
                    }
                    
                    // Make fields readonly again
                    const inputs = basicInfoForm.querySelectorAll('input, textarea');
                    inputs.forEach(input => {
                        if (input.id !== 'currentPassword' && input.id !== 'newPassword' && input.id !== 'confirmPassword') {
                            input.setAttribute('readonly', true);
                        }
                    });
                    
                    // Show edit button and hide save/cancel buttons
                    editBasicInfoBtn.style.display = 'inline-block';
                    saveBasicInfoBtn.style.display = 'none';
                    cancelEditBtn.style.display = 'none';
                });
            }
            
            // Toggle time inputs based on checkbox state
            const dayCheckboxes = document.querySelectorAll('.day-availability input[type="checkbox"]');
            dayCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const day = this.name.replace('day_', '');
                    const startInput = document.querySelector(`input[name="${day}_start"]`);
                    const endInput = document.querySelector(`input[name="${day}_end"]`);
                    
                    if (this.checked) {
                        startInput.removeAttribute('disabled');
                        endInput.removeAttribute('disabled');
                    } else {
                        startInput.setAttribute('disabled', 'disabled');
                        endInput.setAttribute('disabled', 'disabled');
                    }
                });
            });
            
            // Password form validation
            const passwordForm = document.getElementById('passwordForm');
            if (passwordForm) {
                passwordForm.addEventListener('submit', function(e) {
                    const newPassword = document.getElementById('newPassword').value;
                    const confirmPassword = document.getElementById('confirmPassword').value;
                    
                    if (newPassword !== confirmPassword) {
                        alert('Passwords do not match!');
                        e.preventDefault();
                        return;
                    }
                });
            }

            // Availability form handling
            const addAvailabilityForm = document.getElementById('availabilityForm');
            if (addAvailabilityForm) {
                addAvailabilityForm.addEventListener('submit', function(e) {
                    const days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    let hasError = false;
                    
                    days.forEach(day => {
                        const checkbox = document.querySelector(`input[name="day_${day}"]`);
                        if (checkbox.checked) {
                            const start = document.querySelector(`input[name="${day}_start"]`).value;
                            const end = document.querySelector(`input[name="${day}_end"]`).value;
                            
                            if (!start || !end) {
                                alert(`Please set both start and end times for ${day}`);
                                hasError = true;
                            } else if (start >= end) {
                                alert(`End time must be after start time for ${day}`);
                                hasError = true;
                            }
                        }
                    });
                    
                    if (hasError) {
                        e.preventDefault();
                    }
                });
            }

            // Mobile menu toggle
            const menuIcon = document.querySelector('.menu-icon');
            if (menuIcon) {
                menuIcon.addEventListener('click', function() {
                    const navMenu = document.querySelector('.nav-menu');
                    navMenu.style.display = navMenu.style.display === 'none' ? 'block' : 'none';
                });
                
                // Initialize menu state for mobile
                if (window.innerWidth <= 768) {
                    document.querySelector('.nav-menu').style.display = 'none';
                }
            }
            
            // Window resize handler for mobile menu
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    document.querySelector('.nav-menu').style.display = '';
                }
            });
        });
    </script>
</body>
</html>