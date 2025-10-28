<?php

require_once 'db_connection.php';

session_start();

// Redirect admin users
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

// Initialize user data
$userData = [
    'fullname' => '',
    'email' => '',
    'phone' => ''
];

// Initialize force form variable
$forceShowForm = false;

if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];

    // Connect to database
    $conn = new mysqli('localhost', 'root', '', 'clinic_db');
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Fetch user info
    $sql = "SELECT fullname, email, phone FROM users WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result && $result->num_rows > 0) {
        $userData = $result->fetch_assoc();
    }
    $stmt->close();

    // Check if user has already submitted patient form
    $sql2 = "SELECT COUNT(*) FROM patient_records WHERE user_id = ?";
    $stmt2 = $conn->prepare($sql2);
    $stmt2->bind_param("i", $user_id);
    $stmt2->execute();
    $stmt2->bind_result($count);
    $stmt2->fetch();
    $stmt2->close();

    // If no records, force show form
    if ($count == 0) {
        $forceShowForm = true;
    }
}

?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Umipig Dental Clinic</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="home.css">
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

    <nav class="main-nav">
        <a href="home.php" class="active">Home</a>
        <a href="aboutUs.php">About Us</a>
        <a href="contactUs.php">Contact</a>
        <a href="services.php">Services</a>
    </nav>

    <div class="header-right">
    <?php if (isset($_SESSION['username'])): ?>
            <a href="user_profile_module.php" class="profile-icon" title="Profile">
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

<?php if (isset($_SESSION['user_id']) && $forceShowForm): ?>
  <style>
    body {
      overflow: hidden; /* Disable scrolling */
    }
    #forceOverlay {
      position: fixed;
      top: 0; left: 0; right: 0; bottom: 0;
      background: rgba(0, 0, 0, 0.6);
      z-index: 9998;
    }
    #patientInfoModal {
      display: block !important; /* Always show modal */
      z-index: 9999;
    }
  </style>
  <div id="forceOverlay"></div>
<?php endif; ?>

<div id="patientInfoModal" class="modal" style="display: none;">
  <div class="modal-content">
    <!-- Removed close button so they cannot dismiss -->
    <?php include 'patient_information_form.php'; ?>
  </div>
</div>


    <!-- About Us Section -->
    <section id="about" class="about-section fade-section" data-direction="up">
       
        <div class="section-container">
            <h2>About Our Clinic</h2>
            <div class="about-group">
                <div class="about-content">
                    <h3>Our Mission</h3>
                    <p>
                        At Umipig Dental Clinic, our mission is to deliver compassionate, high-quality dental care that enhances the health and confidence of every patient we serve.
                        We are dedicated to creating a welcoming and comfortable environment where patients of all ages feel safe and cared for. Our team of skilled professionals is committed to staying updated
                        with the latest advancements in dental technology and treatment methods to ensure optimal care. We believe in educating our patients, empowering them to make informed decisions about their
                        oral health. Integrity, professionalism, and genuine concern for our patients are at the heart of everything we do. We strive to build long-lasting relationships based on trust, respect,
                        and outstanding results. Through our commitment to excellence, we aim to transform smiles and improve lives—one patient at a time.
                    </p>
                    <h4>Our Values</h4>
                    <ul>
                        <li>Patient-Centered Care</li>
                        <li>Professional Excellence</li>
                        <li>Continuous Education</li>
                        <li>Gentle Approach</li>
                    </ul>
                </div>
            </div>
        </div>
    </section>


    <!-- Features Section -->
    <section id="features" class="features-section fade-section" data-direction="up">
        <h2>Why Choose Us?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">🦷</div>
                <h3>Modern Equipment</h3>
                <p>State-of-the-art dental technology for precise treatment</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">👨‍⚕️</div>
                <h3>Expert Team</h3>
                <p>Experienced dentists and friendly staff</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏥</div>
                <h3>Clean Environment</h3>
                <p>Sterile and comfortable clinic setting</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Affordable Care</h3>
                <p>Competitive pricing and flexible payment options</p>
            </div>
        </div>
    </section>


    <!-- Services Carousel -->
    <section class="carousel-section fade-section" data-direction="up">
        <div class="container">
            <div class="carousel-header">
                <h1>Services</h1>
            </div>


            <div class="carousel-container">
                <div class="carousel-viewport">
                    <div class="carousel-track">


                        <div class="carousel-slide">
                            <img src="images/cosmeticDentistry_Service.jpg" alt="Cosmetic Dentistry">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/pediatricDentistry_Service.jpg" alt="Pediatric Dentistry">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/tmjTreatment_Service.jpg" alt="TMJ Treatment">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/gumDiseaseTreatment_Service.jpg" alt="Gum Disease Treatment">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/dentalCrowns_Service.jpg" alt="Dental Crowns & Bridges">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/professionalTeethWhitening_Service.jpg" alt="Teeth Whitening">
                        </div>
                        <div class="carousel-slide">
                            <img src="images/wisdomToothExtraction_Service.jpg" alt="Wisdom Tooth Extraction">
                        </div>
                    </div>
                </div>


                <button class="carousel-button-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M15 19l-7-7 7-7"></path>
                    </svg>
                </button>


                <button class="carousel-button-right">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 5l7 7-7 7"></path>
                    </svg>
                </button>
            </div>
                <button class="servicesBtn"><a href="services.php" style="color: white; text-decoration: none;">Click here to see all services</a></button>

        </div>
    </section>








    <!-- Before & After Section -->
    <section id="results" class="results-section fade-section" data-direction="up">
        <div class="section-container">
            <h2>Treatment Results</h2>
            <div class="results-grid">
                <div class="result-case">
                    <div class="before-after">
                        <img src="images/beforeTreatment.png" alt="Before Treatment">
                        <img src="images/afterTreatment.png" alt="After Treatment">
                    </div>
                    <p>Orthodontic Treatment</p>
                </div>
                <div class="result-case">
                    <div class="before-after">
                        <img src="images/beforeTreatment2.jpg" alt="Before Treatment">
                        <img src="images/afterTreatment2.png" alt="After Treatment">
                    </div>
                    <p>Veneers</p>
                </div>
            </div>
        </div>
    </section>


<!-- Book Appointment Section -->
<section id="book-appointment" class="appointment-section fade-section" data-direction="up">
    <div class="section-container">
        <h2 style="color: #2563eb;">Schedule Your Visit</h2>
        <div class="appointment-grid">
            <div class="appointment-form">
                <?php
                if (session_status() === PHP_SESSION_NONE) session_start();
                require 'db_connection.php';
                ?>

                <form id="appointmentForm" action="javascript:void(0)" enctype="multipart/form-data" method="post">
                    <!-- Patient info (readonly) -->
                    <input type="text" id="name" name="name" placeholder="Full Name" required
                        value="<?php echo htmlspecialchars($userData['fullname'] ?? ''); ?>" readonly>

                    <input type="email" id="email" name="email" placeholder="Email Address" required
                        value="<?php echo htmlspecialchars($userData['email'] ?? ''); ?>" readonly>

                    <input type="tel" id="phone" name="phone" placeholder="Phone Number (+63)" required
                        value="<?php echo htmlspecialchars($userData['phone'] ?? ''); ?>" readonly>

                    <!-- Services selection -->
                    <div class="custom-multiselect" style="width: 100%; margin-bottom: 8px;">
                       <div onclick="toggleCheckboxDropdown(event)" style="width: 100%; padding: 10px; border: 1px solid #ccc; border-radius: 6px; background: #fff; cursor:pointer;">
                            <span id="serviceDropdownLabel" style="font-weight: bold;">Select up to 3 services</span>
                            <small style="color: gray; display:block; font-style: italic;">Click to choose services</small>
                        </div>

                        <div id="checkboxDropdown" class="checkbox-dropdown" style="display: none; width: 100%; background: #f9f9f9; border: 1px solid #ccc; max-height: 260px; overflow-y: auto; box-shadow: 0 2px 5px rgba(0,0,0,0.1); text-align: left; margin-top:8px;">
                            <?php
                            $serviceQuery = "SELECT service_ID, service_name, service_duration FROM services ORDER BY service_name";
                            $serviceResult = mysqli_query($conn, $serviceQuery);

                            while ($service = mysqli_fetch_assoc($serviceResult)) {
                                $sid = (int)$service['service_ID'];
                                $sname = htmlspecialchars($service['service_name']);
                                $sdur = (int)$service['service_duration'];

                                echo "
                                <div class='service-row' data-service-id='{$sid}' style='padding:8px 10px; border-bottom: 1px solid rgba(0,0,0,0.03);'>
                                    <label style='display:flex; align-items:center; gap:8px; cursor:pointer;'>
                                        <input type='checkbox' name='service[]' class='serviceCheckbox' value='{$sid}' data-duration='{$sdur}' style='width:18px; height:18px; margin:0;'>
                                        <span style='flex:1;'>{$sname} <small style='color:gray;'>({$sdur} min)</small></span>
                                    </label>
                                    <div class='dentist-select-container' id='dentist_container_{$sid}' style='margin-top:8px; display:none;'></div>
                                </div>
                                ";
                            }
                            ?>
                        </div>
                    </div>

                    <!-- Estimated duration -->
                    <p id="durationDisplay" style="color: gray; font-style: italic; margin-top:10px; margin-left: 10px; font-size: 13px;">
                        Estimated Duration: —
                    </p>
                    <input type="hidden" id="total_duration" name="total_duration" value="">

                    <!-- Date -->
                    <select id="preferred_date" name="preferred_date" required>
                        <option value="" style="font-weight: bold;">Select Date</option>
                    </select>

                    <!-- Time selection -->
                    <div class="time-selection-container" style="margin-top:12px;">
                        <select id="preferred_time" name="preferred_time" required>
                            <option value="" style="font-weight: bold;">Select Time</option>
                        </select>

                        <div class="custom-time-toggle" style="margin-top:10px;">
                            <button type="button" id="enableCustomTimeBtn" style="background:none; border:none; color:#2563eb; cursor:pointer; text-decoration:underline;">
                                Enter a custom time instead
                            </button>
                            <input type="time" id="custom_time_input" name="custom_time_input" style="display:none; margin-top:8px;" min="09:00" max="17:00">
                        </div>
                    </div>

                    <!-- File upload -->
                    <input type="file" id="fileInput" name="files[]" multiple style="margin-top:10px;">

                    <!-- Conflict Warning Display -->
                    <div id="conflict_warning" style="display:block; margin-top: 10px;"></div>

                    <label class="terms-container" style="margin-top: 10px; gap:8px; align-items:center;">
                    <input type="checkbox" id="agreeTerms_main" name="agreeTerms" style="margin-left:100px;" required>
                        <span>I agree to the <a href="terms_and_conditions.php" target="_blank" style="text-decoration: none;">Terms and Conditions</a>.</span>
                    </label>

                    <button type="submit" id="submitAppointmentBtn" class="cta-button" disabled style="margin-top:12px;">Book Appointment</button>

                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="login-warning" style="color: #d32f2f; background-color: #fde8e8; padding: 12px; border-radius: 4px; margin-top: 15px; text-align: center; border: 1px solid #f5c6cb;">
                            <i class="fas fa-exclamation-circle"></i>
                            You must be <a href="login.php" style="color: #d32f2f; font-weight: bold; text-decoration: none;">logged in</a> to schedule an appointment.
                            Don't have an account? <a href="register.php" style="color: #d32f2f; font-weight: bold; text-decoration: none;">Register here</a>.
                        </div>
                    <?php endif; ?>
                </form>
            </div>

<div class="appointment-info">
    <h3 style="color: royalblue;">Clinic Hours</h3>
    <ul>
        <li>Monday - Friday: <strong>9:00 AM - 5:00 PM</strong> </li>
        <li>Saturday: <strong>9:00 AM - 5:00 PM</strong></li>
        <li>Sunday: <strong>Closed</strong></li>
    </ul>
    <p style="margin-bottom: 30px;">For emergencies, don't hesitate to text/call <strong>09158289869</strong></p>

    <h3 style="color: royalblue; margin-bottom: 20px;">Dentists' Availabilities</h3>
    
    <div class="dentist-availability">
        <?php
        require 'db_connection.php';
        
        $sql = "SELECT d.Dentist_ID, d.name, d.specialization 
                FROM dentists d
                ORDER BY d.name DESC";
        $result = $conn->query($sql);
        
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $dentistID = $row['Dentist_ID'];
                $name = htmlspecialchars($row['name']);
                $specialization = htmlspecialchars($row['specialization']);
                
                // Get the next available date and its actual time slots
                $availabilitySql = "SELECT 
                                    available_date,
                                    available_time,
                                    day_of_week,
                                    end_time
                                FROM dentistavailability 
                                WHERE Dentist_ID = $dentistID 
                                AND available_date >= CURDATE()
                                ORDER BY available_date, available_time
                                LIMIT 50";
                $availabilityResult = $conn->query($availabilitySql);

                $nextAvailableDate = '';
                $availableSlots = [];
                $currentDate = '';

                // Get dentist availability as ranges - ALIGNED WITH SYSTEM SETTINGS
                $dentist_availability = [];
                $availability_by_day = [];

                if ($availabilityResult && $availabilityResult->num_rows > 0) {
                    while ($avail = $availabilityResult->fetch_assoc()) {
                        $day = $avail['day_of_week'];
                        $start_time = date('H:i', strtotime($avail['available_time']));
                        
                        // Use end_time if available, otherwise calculate it (30 minutes)
                        if (!empty($avail['end_time']) && $avail['end_time'] != '00:00:00') {
                            $end_time = date('H:i', strtotime($avail['end_time']));
                        } else {
                            $end_time = date('H:i', strtotime($avail['available_time'] . ' +30 minutes'));
                        }
                        
                        if (!isset($availability_by_day[$day])) {
                            $availability_by_day[$day] = [];
                        }
                        $availability_by_day[$day][] = ['start' => $start_time, 'end' => $end_time];
                        
                        // Set first available date
                        if (empty($currentDate)) {
                            $currentDate = $avail['available_date'];
                            $nextAvailableDate = date('D, M j', strtotime($currentDate));
                        }
                    }

                    // Convert to time ranges for display - SIMPLIFIED VERSION
                    foreach ($availability_by_day as $day => $time_slots) {
                        if (!empty($time_slots)) {
                            // Convert all times to timestamps for proper comparison
                            $start_timestamps = [];
                            $end_timestamps = [];
                            
                            foreach ($time_slots as $slot) {
                                $start_timestamps[] = strtotime($slot['start']);
                                $end_timestamps[] = strtotime($slot['end']);
                            }
                            
                            // Find the earliest start and latest end time
                            $earliest_start = min($start_timestamps);
                            $latest_end = max($end_timestamps);
                            
                            // Format back to readable time
                            $earliest_start_formatted = date('H:i', $earliest_start);
                            $latest_end_formatted = date('H:i', $latest_end);
                            
                            $dentist_availability[$day] = "$earliest_start_formatted - $latest_end_formatted";
                        }
                    }
                    
                    // Generate 30-minute slots based on availability ranges
                    if (!empty($dentist_availability) && !empty($currentDate)) {
                        $currentDayOfWeek = date('l', strtotime($currentDate));
                        
                        if (isset($dentist_availability[$currentDayOfWeek])) {
                            list($range_start, $range_end) = explode(' - ', $dentist_availability[$currentDayOfWeek]);
                            
                            $start = strtotime($range_start);
                            $end = strtotime($range_end);
                            
                            // Generate 30-minute slots within the range
                            $current_slot = $start;
                            while ($current_slot < $end) {
                                $slotTime = date('H:i', $current_slot);
                                $availableSlots[] = $slotTime;
                                $current_slot += 1800; // 30 minutes
                            }
                            
                            $availableSlots = array_unique($availableSlots);
                            sort($availableSlots);
                        }
                    }
                }

                // Get booked slots for the next available date (existing code remains the same)
                $bookedSlots = [];
                if (!empty($currentDate)) {
                    $bookedSql = "SELECT start_time, end_time 
                                FROM appointment 
                                WHERE Dentist_ID = $dentistID 
                                AND Appointment_Date = '$currentDate'
                                AND Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')";
                    $bookedResult = $conn->query($bookedSql);
                    
                    if ($bookedResult && $bookedResult->num_rows > 0) {
                        while ($bookedRow = $bookedResult->fetch_assoc()) {
                            $start = strtotime($bookedRow['start_time']);
                            $end = strtotime($bookedRow['end_time']);
                            
                            for ($time = $start; $time < $end; $time += 900) {
                                $bookedSlots[] = date('H:i', $time);
                            }
                        }
                    }
                    $bookedSlots = array_unique($bookedSlots);
                }

                // Filter out booked slots from available slots
                $finalAvailableSlots = array_diff($availableSlots, $bookedSlots);
                $slotsAvailable = !empty($finalAvailableSlots);

                echo '
                <div class="dentist-card">
                    <h1 class="dentist-name">'.$name.'</h1>
                    <div class="specialization">'.formatSpecialization($specialization).'</div>
                    <div class="availability">';

                if (!empty($nextAvailableDate)) {
                    echo '
                    <div class="time-range">
                        <i class="far fa-clock"></i> Next Available: '.$nextAvailableDate.'
                    </div>
                    <div class="availability-table">
                        <div class="date-header">
                            <span>Available Time Slots</span>
                            '.($slotsAvailable ? '<span class="available-badge">Available</span>' : '<span class="booked-badge">Fully Booked</span>').'
                        </div>
                        <div class="time-slots">';

                    if (!empty($finalAvailableSlots)) {
                        foreach ($finalAvailableSlots as $slotTime) {
                            $formattedTime = date('h:i A', strtotime($slotTime));
                            echo '<div class="time-slot">'.$formattedTime.'</div>';
                        }
                    } else {
                        echo '<div class="time-slot booked" style="grid-column: 1 / -1;">All slots booked</div>';
                    }

                    echo '
                        </div>
                    </div>';
                } else {
                    echo '
                    <div class="not-available">
                        <span>Currently fully booked</span>
                        <small>New slots may open soon</small>
                    </div>';
                }

                echo '
                    </div>
                </div>';
            }
        } else {
            echo '<p>No dentists available at the moment.</p>';
        }

        $conn->close();

        function formatSpecialization($text) {
            $items = explode(',', $text);
            $formatted = '<ul class="specialization-list">';
            foreach ($items as $item) {
                $formatted .= '<li>' . trim($item) . '</li>';
            }
            $formatted .= '</ul>';
            return $formatted;
        }
        ?>
    </div>
        
    <p style="margin-top: 20px; font-style: italic; font-size: 13px; text-align: center;">Check back regularly for schedule updates.</p>
</div>


<style>
    .dentist-availability {
        display: grid;
        grid-template-columns: 1fr;
        gap: 15px;
        margin-bottom: 15px;
    }
    
    .dentist-card {
        background: white;
        border-radius: 6px;
        box-shadow: 0 2px 6px rgba(0,0,0,0.08);
        padding: 10px;
    }
    
    .dentist-name {
        margin: 0 0 8px 0;
        font-size: 15px;
        color: #2c3e50;
        font-weight: 600;
    }
    
    .specialization-list {
    list-style: none;
    padding: 0;
    margin: 0;
}

.specialization-list li {
    position: relative;
    padding-left: 20px;
    margin-bottom: 5px;
    font-size: 12px;
}

.specialization-list li::before {
    content: "⭐";
    position: absolute;
    left: 0;
    color: gold;
}

    
    .availability {
        padding-top: 10px;
        border-top: 1px dashed #ddd;
        margin-top: 10px;
    }
    
    .time-range {
        display: flex;
        align-items: center;
        gap: 6px;
        color: #388e3c;
        font-weight: 500;
        margin-bottom: 10px;
        font-size: 0.9em;
    }
    
    .time-range i {
        font-size: 0.8em;
    }
    
    .availability-table {
        background: #f9f9f9;
        border-radius: 6px;
        padding: 7px;
    }
    
    .date-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 8px;
        font-weight: 500;
        font-size: 0.9em;
    }
    
    .available-badge {
        background: #e8f5e9;
        color: #388e3c;
        padding: 3px 6px;
        border-radius: 10px;
        font-size: 0.75em;
    }
    
    .booked-badge {
        background: #ffebee;
        color: #e53935;
        padding: 3px 6px;
        border-radius: 10px;
        font-size: 0.75em;
    }
    
    .time-slots {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 6px;
    }
    
    .time-slot {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 4px;
        padding: 2px 1px;
        text-align: center;
        font-size: 9px;
        min-height: 25px;
        display: flex;
        align-items: center;
        justify-content: center;
    }
    
    .time-slot.booked {
        background-color: #ffebee;
        color: #e53935;
        border-color: #ef9a9a;
        text-decoration: line-through;
    }
    
    .not-available {
        color: #e53935;
        font-style: italic;
        font-size: 0.9em;
    }
    
    .not-available small {
        display: block;
        color: #777;
        font-size: 0.8em;
        margin-top: 2px;
    }
    
    @media (min-width: 768px) {
        .dentist-availability {
            grid-template-columns: 1fr 1fr;
            gap: 12px;
        }
        
        .dentist-card {
            padding: 12px;
        }
    }

    /* small style for compatibility badge in option text (can't style <option> much) */
</style>
<!-- Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    </div>
</section>


    <!-- Footer -->
    <footer class="footer">
        <div class="footer-wrapper">
            <div class="footer-left">
                <div class="clinic-branding">
                    <img src="images/UmipigDentalClinic_NoBGLogo.jpg" alt="Clinic Logo" class="footer-logo" />
                    <div>
                        <h2>Umipig Dental Clinic</h2>
                        <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
                    </div>
                </div>


                <div class="footer-row">
                    <img src="images/gps.png" alt="Location Icon" class="icon" />
                    <div>
                        <p class="contact-label">Address</p>
                        <p>2nd Floor, Village Eats Food Park, Bldg., #9<br>Village East Executive Homes 1900 Cainta, Philippines</p>
                    </div>
                </div>
            </div>


            <div class="footer-right">
                <div class="footer-row">
                    <img src="images/gmail.png" alt="Email Icon" class="icon" />
                    <div>
                        <p class="contact-label">Email</p>
                        <a class="footer-links" href="mailto:Umipigdentalclinic@gmail.com" target="_blank" style="text-decoration: none; color: white;">Umipigdentalclinic@gmail.com</a>
                    </div>
                </div>


                <div class="footer-row">
                    <img src="images/phone-call.png" alt="Phone Icon" class="icon" />
                    <div>
                        <p class="contact-label">Hotline</p>
                        <a class="footer-links" href="tel:09158289869" style="text-decoration: none; color: white;">+63 915 828 9869</a>
                    </div>
                </div>


                <div class="footer-row">
                    <img src="images/facebook.png" alt="Facebook Icon" class="icon" />
                    <div>
                        <p class="contact-label">Facebook</p>
                        <a class="footer-links" href="https://www.facebook.com/umipigdentalcliniccainta" target="_blank" style="text-decoration: none; color: white;">
                            https://www.facebook.com/umipigdentalcliniccainta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="home.js"></script>
    <?php include 'chatbot.php'; ?>

<script>
/* --------- Elements --------- */
const checkboxDropdown = document.getElementById('checkboxDropdown');
const serviceDropdownLabel = document.getElementById('serviceDropdownLabel');
const serviceCheckboxes = document.querySelectorAll('input[name="service[]"]');
const durationDisplay = document.getElementById('durationDisplay');
const totalDurationInput = document.getElementById('total_duration');
const dateSelect = document.getElementById('preferred_date');
const timeSelect = document.getElementById('preferred_time');
const customTimeToggle = document.getElementById('enableCustomTimeBtn');
const customTimeInput = document.getElementById('custom_time_input');
const conflictWarning = document.getElementById('conflict_warning');
const submitBtn = document.getElementById('submitAppointmentBtn');
const agreeTerms = document.getElementById('agreeTerms_main');
let usingCustomTime = false;
let lastConflictCheck = null;

/* ---------- Helpers ---------- */
function toggleCheckboxDropdown(e) {
    if (!checkboxDropdown) return;
    e.stopPropagation();
    checkboxDropdown.style.display = (checkboxDropdown.style.display === 'block') ? 'none' : 'block';
}
document.addEventListener('click', function(e){
    if (!checkboxDropdown) return;
    if (checkboxDropdown.contains(e.target)) return;
    if (e.target.closest('[onclick="toggleCheckboxDropdown(event)"]')) return;
    checkboxDropdown.style.display = 'none';
});

function computeEndTime(startTime, minutes) {
    const [h, m] = startTime.split(':').map(Number);
    const dt = new Date();
    dt.setHours(h, m);
    dt.setMinutes(dt.getMinutes() + minutes);
    return `${String(dt.getHours()).padStart(2,'0')}:${String(dt.getMinutes()).padStart(2,'0')}`;
}

// Get selected service IDs
function getSelectedServices() {
    return Array.from(serviceCheckboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.value);
}

/* ---------- Duration & label updates ---------- */
function calculateTotalDuration() {
    let total = 0;
    const selected = [];
    serviceCheckboxes.forEach(cb => {
        if (cb.checked) {
            total += parseInt(cb.dataset.duration) || 0;
            selected.push(cb.value);
        }
    });
    
    totalDurationInput.value = total;
    return { totalMinutes: total, selectedServices: selected };
}

function updateDurationDisplay(){
    const { totalMinutes, selectedServices } = calculateTotalDuration();
    
    const serviceNames = Array.from(serviceCheckboxes)
        .filter(cb => cb.checked)
        .map(cb => cb.nextElementSibling ? cb.nextElementSibling.textContent.trim().split(' (')[0] : 'Service');
    
    durationDisplay.textContent = selectedServices.length > 0 ? 
        `Estimated Duration: ${totalMinutes} minutes (${selectedServices.length} service${selectedServices.length>1?'s':''})` 
        : 'Estimated Duration: —';
    
    updateServiceUI();
    checkForConflicts();
    updateSubmitState();
}

/* limit selection to 3 */
serviceCheckboxes.forEach(cb => {
    cb.addEventListener('change', function(){
        const checked = Array.from(serviceCheckboxes).filter(c => c.checked);
        if (checked.length > 3) {
            this.checked = false;
            alert('You can select up to 3 services only.');
            return;
        }
        const names = Array.from(serviceCheckboxes)
            .filter(c=>c.checked)
            .map(c => c.nextElementSibling ? c.nextElementSibling.textContent.trim().split(' (')[0] : c.value);
        serviceDropdownLabel.textContent = names.length>0 ? names.join(' • ') : 'Select up to 3 services';
        updateDurationDisplay();
        
        // Refresh availability when services change
        if (checked.length > 0) {
            fetchAvailableDates();
        } else {
            // Reset if no services selected
            resetSelect(dateSelect, 'Select Date');
            resetSelect(timeSelect, 'Select Time');
            hideTimelinePreview();
            conflictWarning.style.display = 'none';
        }
    });
});

/* ---------- Per-service dentist dropdowns ---------- */
function updateServiceUI() {
    document.querySelectorAll('.service-row').forEach(row=>{
        const sid = row.dataset.serviceId;
        const container = document.getElementById('dentist_container_' + sid);
        const isChecked = row.querySelector('.serviceCheckbox').checked;

        if (isChecked) {
            if (container && container.innerHTML.trim() === '') {
                container.style.display = 'block';
                container.innerHTML = '<em>Loading dentists…</em>';
                fetch(`get_dentists_for_service.php?service_id=${sid}`)
                    .then(r => r.json())
                    .then(json => {
                        container.innerHTML = '';
                        if (!Array.isArray(json) || json.length === 0) {
                            container.innerHTML = `<div style="color:darkorange;">No dentists found. Server will auto-assign.</div>`;
                            return;
                        }
                        const sel = document.createElement('select');
                        sel.name = `dentist_for_service[${sid}]`;
                        sel.id = `dentist_for_service_${sid}`;
                        sel.required = false;
                        sel.style = 'width:100%; padding:6px; margin-top:6px;';

                        const placeholder = document.createElement('option');
                        placeholder.value = '';
                        placeholder.textContent = 'Auto-assign if left blank';
                        sel.appendChild(placeholder);

                        json.forEach(d => {
                            const opt = document.createElement('option');
                            opt.value = d.Dentist_ID;
                            opt.textContent = `${d.name} — ${d.specialization || ''}`;
                            sel.appendChild(opt);
                        });

                        container.appendChild(sel);

                        // Auto-select if only one dentist
                        if (json.length === 1) {
                            sel.selectedIndex = 1;
                        }

                        // Update availability when dentist selection changes
                        sel.addEventListener('change', function() {
                            fetchAvailableDates();
                            checkForConflicts();
                            updateSubmitState();
                        });
                        
                        // Trigger initial availability fetch if dentist is auto-selected
                        if (json.length === 1) {
                            setTimeout(() => {
                                fetchAvailableDates();
                            }, 100);
                        }
                        
                        updateSubmitState();
                    })
                    .catch(err => {
                        container.innerHTML = `<div style="color:darkorange;">Failed to load dentists. Server will auto-assign.</div>`;
                        console.error('get_dentists_for_service error', err);
                        updateSubmitState();
                    });
            } else {
                container.style.display = 'block';
            }
        } else {
            if (container) {
                container.style.display = 'none';
                container.innerHTML = '';
            }
        }
    });
}

/* ---------- Conflict Detection ---------- */
function checkForConflicts() {
    const selectedDate = dateSelect.value;
    const selectedTime = usingCustomTime ? customTimeInput.value : timeSelect.value;
    const { totalMinutes, selectedServices } = calculateTotalDuration();
    
    // Only check if we have all required information
    if (!selectedDate || !selectedTime || totalMinutes === 0 || selectedServices.length === 0) {
        conflictWarning.innerHTML = '';
        conflictWarning.style.display = 'none';
        return;
    }
    
    // Build dentist parameters for each service
    const dentistParams = [];
    selectedServices.forEach(serviceId => {
        const dentistSelect = document.getElementById(`dentist_for_service_${serviceId}`);
        if (dentistSelect && dentistSelect.value) {
            dentistParams.push(`dentist_${serviceId}=${dentistSelect.value}`);
        }
    });
    
    // Avoid duplicate checks for the same parameters
    const checkKey = `${selectedDate}-${selectedTime}-${totalMinutes}-${selectedServices.join(',')}-${dentistParams.join(',')}`;
    if (lastConflictCheck === checkKey) {
        return;
    }
    lastConflictCheck = checkKey;
    
    // Show loading state
    conflictWarning.innerHTML = '<div style="color: #666;"><i class="fas fa-spinner fa-spin"></i> Checking availability...</div>';
    conflictWarning.style.display = 'block';
    conflictWarning.style.color = '#666';
    conflictWarning.style.backgroundColor = '#f8f9fa';
    conflictWarning.style.padding = '10px';
    conflictWarning.style.borderRadius = '4px';
    conflictWarning.style.border = '1px solid #dee2e6';
    
    // Build query parameters with service IDs and dentist selections
    const params = new URLSearchParams({
        date: selectedDate,
        start_time: selectedTime,
        duration: totalMinutes,
        services: selectedServices.join(',')
    });
    
    // Add dentist selections for each service
    selectedServices.forEach(serviceId => {
        const dentistSelect = document.getElementById(`dentist_for_service_${serviceId}`);
        if (dentistSelect && dentistSelect.value) {
            params.append(`dentist_${serviceId}`, dentistSelect.value);
        }
    });
    
    // Call the conflict detection API
    fetch(`check_conflict.php?${params}`)
        .then(r => r.json())
        .then(data => {
            if (data.conflict) {
                conflictWarning.innerHTML = `
                    <div style="color: #d32f2f; background-color: #ffebee; padding: 12px; border-radius: 4px; border: 1px solid #f5c6cb;">
                        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>Time Slot Not Available</strong>
                        </div>
                        <div style="font-size: 14px;">
                            ${data.message || 'Selected time slot is no longer available. Please choose another time.'}
                        </div>
                    </div>
                `;
                conflictWarning.style.display = 'block';
            } else {
                conflictWarning.innerHTML = `
                    <div style="color: #388e3c; background-color: #e8f5e9; padding: 12px; border-radius: 4px; border: 1px solid #c8e6c9;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-check-circle"></i>
                            <strong>Time Slot Available</strong>
                        </div>
                        <div style="font-size: 14px; margin-top: 4px;">
                            ${data.message || 'Time slot is available for booking.'}
                        </div>
                    </div>
                `;
                conflictWarning.style.display = 'block';
            }
        })
        .catch(err => {
            console.error('Conflict check error:', err);
            conflictWarning.innerHTML = `
                <div style="color: #ff9800; background-color: #fff3e0; padding: 12px; border-radius: 4px; border: 1px solid #ffcc80;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <i class="fas fa-exclamation-circle"></i>
                        <strong>Unable to Verify Availability</strong>
                    </div>
                    <div style="font-size: 14px; margin-top: 4px;">
                        Please check your selection and try again.
                    </div>
                </div>
            `;
            conflictWarning.style.display = 'block';
        });
}

/* ---------- Enhanced Date/time logic ---------- */
function resetSelect(sel, label = 'Select'){
    sel.innerHTML = `<option value="">${label}</option>`;
    sel.disabled = true;
}

function populateDateSelect(dates) {
    if (Array.isArray(dates) && dates.length > 0) {
        dates.forEach(d => {
            const o = document.createElement('option');
            o.value = d;
            
            // Format date for display (e.g., "Mon, Oct 23")
            const dateObj = new Date(d + 'T00:00:00');
            const formattedDate = dateObj.toLocaleDateString('en-US', { 
                weekday: 'short', 
                month: 'short', 
                day: 'numeric' 
            });
            
            o.textContent = formattedDate;
            dateSelect.appendChild(o);
        });
        dateSelect.disabled = false;
        
        // Auto-select the first available date and load its times
        if (dates.length > 0) {
            dateSelect.value = dates[0];
            setTimeout(() => {
                dateSelect.dispatchEvent(new Event('change'));
            }, 100);
        }
    } else {
        dateSelect.innerHTML = '<option value="">No available dates found</option>';
        resetSelect(timeSelect, 'Select Time');
        hideTimelinePreview();
        
        // Show no availability message
        conflictWarning.innerHTML = `
            <div style="color: #d32f2f; background-color: #ffebee; padding: 12px; border-radius: 4px; border: 1px solid #f5c6cb;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>No Availability</strong>
                </div>
                <div style="font-size: 14px; margin-top: 4px;">
                    No available dates found for the selected criteria. Please try another dentist or check back later.
                </div>
            </div>
        `;
        conflictWarning.style.display = 'block';
    }
}

// Time selection with timeline support
function populateTimeSelect(timesWithTimeline) {
    resetSelect(timeSelect, 'Select Time');
    
    if (Array.isArray(timesWithTimeline) && timesWithTimeline.length > 0) {
        timesWithTimeline.forEach(item => {
            const o = document.createElement('option');
            o.value = item.time;
            
            // Format display with start and end times
            const endTime = item.end_time || (item.timeline && item.timeline[item.timeline.length - 1]?.end_time) || item.time;
            o.textContent = `${formatTime12Hour(item.time)} - ${formatTime12Hour(endTime)}`;
            
            // Store timeline data for display
            if (item.timeline) {
                o.dataset.timeline = JSON.stringify(item.timeline);
            }
            
            timeSelect.appendChild(o);
        });
        timeSelect.disabled = false;
        
        // Show timeline for first option
        if (timesWithTimeline.length > 0 && timesWithTimeline[0].timeline) {
            showTimelinePreview(timesWithTimeline[0].timeline);
        }
    } else {
        timeSelect.innerHTML = '<option value="">No available times for this date</option>';
        hideTimelinePreview();
    }
}

// Hide timeline preview
function hideTimelinePreview() {
    const timelineContainer = document.getElementById('timelinePreview');
    if (timelineContainer) {
        timelineContainer.style.display = 'none';
    }
}

// Show timeline preview
function showTimelinePreview(timeline) {
    let timelineContainer = document.getElementById('timelinePreview');
    
    if (!timelineContainer) {
        timelineContainer = document.createElement('div');
        timelineContainer.id = 'timelinePreview';
        timelineContainer.className = 'timeline-preview';
        timelineContainer.style.cssText = `
            margin-top: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border: 1px solid #e9ecef;
            display: block;
        `;
        timeSelect.parentNode.appendChild(timelineContainer);
    } else {
        timelineContainer.style.display = 'block';
    }
    
    let timelineHTML = '<h4 style="margin: 0 0 12px 0; color: #2c3e50; font-size: 14px; font-weight: 600;"> Estimated Appointment Timeline:</h4>';
    
    timeline.forEach((slot, index) => {
        timelineHTML += `
            <div class="timeline-slot" style="padding: 10px; margin-bottom: 8px; background: white; border-radius: 6px; border-left: 4px solid #3498db;">
                <div class="timeline-time" style="font-weight: 600; color: #2c3e50; font-size: 13px;">${slot.start_time} - ${slot.end_time}</div>
                <div class="timeline-dentist" style="color: #7f8c8d; font-size: 12px; margin: 2px 0;">Dr. ${slot.dentist_name}</div>
                <div class="timeline-service" style="color: #27ae60; font-size: 12px; font-weight: 500;">${slot.service_name} (${slot.duration} min)</div>
            </div>
        `;
    });
    
    timelineContainer.innerHTML = timelineHTML;
}

// Update timeline when time selection changes
timeSelect?.addEventListener('change', function() {
    const selectedOption = this.options[this.selectedIndex];
    if (selectedOption.dataset.timeline) {
        const timeline = JSON.parse(selectedOption.dataset.timeline);
        showTimelinePreview(timeline);
    } else {
        hideTimelinePreview();
    }
    checkForConflicts();
    updateSubmitState();
});

function findNextAvailableDateWithSlots() {
    const currentDate = dateSelect.value;
    const allDates = Array.from(dateSelect.options).map(opt => opt.value).filter(val => val);
    const currentIndex = allDates.indexOf(currentDate);
    
    if (currentIndex !== -1 && currentIndex < allDates.length - 1) {
        // Try the next date
        const nextDate = allDates[currentIndex + 1];
        dateSelect.value = nextDate;
        dateSelect.dispatchEvent(new Event('change'));
        
        // Show message to user
        conflictWarning.innerHTML = `
            <div style="color: #ff9800; background-color: #fff3e0; padding: 12px; border-radius: 4px; border: 1px solid #ffcc80;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-info-circle"></i>
                    <strong>No available slots for selected date</strong>
                </div>
                <div style="font-size: 14px; margin-top: 4px;">
                    Showing next available date: ${dateSelect.options[dateSelect.selectedIndex].text}
                </div>
            </div>
        `;
        conflictWarning.style.display = 'block';
    } else {
        // No more dates to try
        conflictWarning.innerHTML = `
            <div style="color: #d32f2f; background-color: #ffebee; padding: 12px; border-radius: 4px; border: 1px solid #f5c6cb;">
                <div style="display: flex; align-items: center; gap: 8px;">
                    <i class="fas fa-exclamation-triangle"></i>
                    <strong>Fully Booked</strong>
                </div>
                <div style="font-size: 14px; margin-top: 4px;">
                    No available time slots found. Please try another dentist or check back later.
                </div>
            </div>
        `;
        conflictWarning.style.display = 'block';
    }
}

// Fetch available dates with service IDs and dentist parameters
function fetchAvailableDates(){
    resetSelect(dateSelect, 'Loading dates...');
    resetSelect(timeSelect, 'Select Time');
    hideTimelinePreview();
    conflictWarning.textContent = '';
    
    const selectedServices = getSelectedServices();
    if (selectedServices.length === 0) {
        dateSelect.innerHTML = '<option value="">Select services first</option>';
        return;
    }
    
    // Build parameters with service IDs and dentist selections
    const params = new URLSearchParams({
        services: selectedServices.join(',')
    });
    
    // Add dentist selections for each service
    selectedServices.forEach(serviceId => {
        const dentistSelect = document.getElementById(`dentist_for_service_${serviceId}`);
        if (dentistSelect && dentistSelect.value) {
            params.append(`dentist_${serviceId}`, dentistSelect.value);
        }
    });
    
    fetch(`get_available_dates.php?${params}`)
        .then(r => r.json())
        .then(dates => {
            if (dates.length === 0) {
                dateSelect.innerHTML = '<option value="">No available dates found</option>';
                resetSelect(timeSelect, 'Select Time');
                
                conflictWarning.innerHTML = `
                    <div style="color: #d32f2f; background-color: #ffebee; padding: 12px; border-radius: 4px; border: 1px solid #f5c6cb;">
                        <div style="display: flex; align-items: center; gap: 8px;">
                            <i class="fas fa-exclamation-triangle"></i>
                            <strong>No Availability</strong>
                        </div>
                        <div style="font-size: 14px; margin-top: 4px;">
                            No available dates found for the selected criteria. Please try another dentist or check back later.
                        </div>
                    </div>
                `;
                conflictWarning.style.display = 'block';
            } else {
                populateDateSelect(dates);
            }
        })
        .catch(err => {
            console.error('Error fetching dates:', err);
            dateSelect.innerHTML = '<option value="">Error loading dates</option>';
        });
}

// Date change event with proper parameters
dateSelect?.addEventListener('change', function(){
    const selectedDate = this.value;
    resetSelect(timeSelect, 'Loading times...');
    hideTimelinePreview();
    conflictWarning.textContent = '';
    
    if (!selectedDate) return;

    const selectedServices = getSelectedServices();
    const { totalMinutes } = calculateTotalDuration();
    
    if (selectedServices.length === 0) {
        timeSelect.innerHTML = '<option value="">Select services first</option>';
        return;
    }
    
    // Build parameters with service IDs, duration, and dentist selections
    const params = new URLSearchParams({
        date: selectedDate,
        services: selectedServices.join(','),
        duration: totalMinutes
    });
    
    // Add dentist selections for each service
    selectedServices.forEach(serviceId => {
        const dentistSelect = document.getElementById(`dentist_for_service_${serviceId}`);
        if (dentistSelect && dentistSelect.value) {
            params.append(`dentist_${serviceId}`, dentistSelect.value);
        }
    });
    
    fetch(`get_available_times.php?${params}`)
        .then(r => r.json())
        .then(times => {
            if (times.length === 0) {
                populateTimeSelect([]);
                findNextAvailableDateWithSlots();
            } else {
                populateTimeSelect(times);
                checkForConflicts();
            }
        })
        .catch(err => {
            console.error('Error fetching times:', err);
            timeSelect.innerHTML = '<option value="">Error loading times</option>';
        });
});

customTimeInput?.addEventListener('change', function(){
    checkForConflicts();
    updateSubmitState();
});

function formatTime12Hour(timeStr){
    if (!timeStr) return '';
    const [h,m] = timeStr.split(':').map(Number);
    const ampm = h>=12 ? 'PM' : 'AM';
    const hh = h%12 || 12;
    return `${hh}:${String(m).padStart(2,'0')} ${ampm}`;
}

customTimeToggle?.addEventListener('click', ()=>{
    usingCustomTime = !usingCustomTime;
    timeSelect.style.display = usingCustomTime ? 'none' : 'block';
    customTimeInput.style.display = usingCustomTime ? 'block' : 'none';
    customTimeToggle.textContent = usingCustomTime ? 'Use suggested times instead' : 'Enter a custom time instead';
    hideTimelinePreview();
    checkForConflicts();
    updateSubmitState();
});

/* ---------- Submit button logic ---------- */
function updateSubmitState(){
    const { selectedServices, totalMinutes } = calculateTotalDuration();
    const dateOk = !!dateSelect.value;
    const timeOk = usingCustomTime ? !!customTimeInput.value : !!timeSelect.value;
    const termsOk = agreeTerms ? agreeTerms.checked : true;

    const hasServices = selectedServices.length > 0;
    
    // Check if there's a conflict warning showing
    const hasConflict = conflictWarning.innerHTML.includes('Not Available') || 
                       conflictWarning.innerHTML.includes('Unable to Verify');
    
    const finalCondition = hasServices && dateOk && timeOk && termsOk && !hasConflict;

    submitBtn.disabled = !finalCondition;
}

dateSelect?.addEventListener('change', updateSubmitState);
timeSelect?.addEventListener('change', updateSubmitState);
customTimeInput?.addEventListener('change', updateSubmitState);

// Fixed terms checkbox listener
if (agreeTerms) {
    agreeTerms.addEventListener('change', updateSubmitState);
}

/* ---------- Form submission ---------- */
document.getElementById('appointmentForm').addEventListener('submit', function(e){
    e.preventDefault();

    const { totalMinutes, selectedServices } = calculateTotalDuration();
    if (selectedServices.length === 0) { 
        alert('Please select at least one service.'); 
        return; 
    }
    if (!dateSelect.value) { 
        alert('Please select a date.'); 
        return; 
    }
    const startTime = usingCustomTime ? customTimeInput.value : timeSelect.value;
    if (!startTime) { 
        alert('Please select or enter a start time.'); 
        return; 
    }

    // Final conflict check before submission
    const hasConflict = conflictWarning.innerHTML.includes('Not Available');
    if (hasConflict) {
        alert('Please resolve the scheduling conflict before submitting.');
        return;
    }

    const fd = new FormData(this);
    fd.set('total_duration', totalMinutes);
    fd.set('preferred_time', startTime);

    // Add dentist selections to form data
    selectedServices.forEach(serviceId => {
        const dentistSelect = document.getElementById(`dentist_for_service_${serviceId}`);
        if (dentistSelect && dentistSelect.value) {
            fd.set(`dentist_for_service[${serviceId}]`, dentistSelect.value);
        }
    });

    submitBtn.disabled = true;
    submitBtn.textContent = 'Booking...';

    fetch('submit_appointment.php', { method:'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            submitBtn.disabled = false;
            submitBtn.textContent = 'Book Appointment';
            if (data.status === 'success') {
                alert(data.message || 'Appointment(s) booked. Check your email for confirmation.');
                location.reload();
            } else {
                alert('Error: ' + (data.message || 'Unknown error'));
            }
        })
        .catch(err=>{
            console.error(err);
            submitBtn.disabled = false;
            submitBtn.textContent = 'Book Appointment';
            alert('An error occurred. Please try again.');
        });
});

// Enhanced initialization with better event handling
document.addEventListener('change', function(e) {
    // Handle dentist selection changes
    if (e.target && e.target.id && e.target.id.startsWith('dentist_for_service_')) {
        setTimeout(fetchAvailableDates, 200);
    }
    
    // Handle service checkbox changes for immediate availability fetch
    if (e.target && e.target.classList.contains('serviceCheckbox')) {
        const selectedServices = getSelectedServices();
        if (selectedServices.length > 0) {
            // Small delay to ensure UI updates complete
            setTimeout(fetchAvailableDates, 300);
        }
    }
});

/* ---------- Initialize ---------- */
updateDurationDisplay();

// Add CSS for better visual feedback
const style = document.createElement('style');
style.textContent = `
    #preferred_date:not([disabled]) {
        background-color: white;
        cursor: pointer;
    }
    #preferred_time:not([disabled]) {
        background-color: white;
        cursor: pointer;
    }
    select:disabled {
        background-color: #f5f5f5;
        color: #999;
        cursor: not-allowed;
    }
    select:not(:disabled) {
        background-color: white;
        color: #333;
        cursor: pointer;
    }
`;
document.head.appendChild(style);
</script>


</body>
</html>