<?php
session_start(); // Start the session to access session variables

// Database connection
$conn = new mysqli("localhost", "root", "", "clinic_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}


if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    // Not an admin, redirect to home or login page
    header("Location: login.php");
    exit;
}


$patient = null;

if (isset($_GET['id'])) {
    $id = $_GET['id'];
   $stmt = $conn->prepare("SELECT u.fullname, p.age, p.sex, p.contact, p.address
                        FROM patient_records p
                        JOIN users u ON p.name = u.id
                        WHERE p.user_id = ?");

   $stmt->bind_param("i", $id);
$stmt->execute();
$stmt->bind_result($patientName, $patientAge, $patientGender, $patientContact, $patientAddress);
$stmt->fetch();
$stmt->close();

}



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dental Chart Page</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="dental_records.css">
</head>
<body onload="createDentalChart()">

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
            <!-- Notification Bell Icon -->
            <a href="notifications.php" class="notification-icon" title="Notifications" style="color: royalblue;">
                <i class="fas fa-bell"></i>
            </a>

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


    <div class="main-content">

<button onclick="history.back()" style="margin-top: 0px; padding: 10px 15px; background-color:rgb(181, 187, 194); color: white; border: none; border-radius: 4px; cursor: pointer;">
    ← Back
</button>

        <h1>Dental Records</h1>
        <div class="content-grid">
            <div class="left-column">
                <div class="tooth-chart">
                    <h2>Dental Chart</h2>
                    <div class="chart-container">
                        <div class="upper-label">UPPER</div>
                        <div id="upper-row" class="tooth-row"></div>
                        <div class="mid-section">
                            <span class="side-label left-label">RIGHT</span>
                            <div class="mid-teeth-container">
                                <div id="right-upper-row" class="tooth-row right-row"></div>
                                <div id="left-upper-row" class="tooth-row left-row"></div>
                            </div>
                            <span class="side-label right-label">LEFT</span>
                        </div>
                        <div class="lower-label">LOWER</div>
                        <div id="lower-row" class="tooth-row"></div>
                    </div>
                </div>

                <div class="additional-treatment">
                    <h2>Additional Treatment Information</h2>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="extraction">Extraction</label>
                            <div class="input-with-buttons">
                                <input type="text" id="extraction">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="cavity">Cavity</label>
                            <div class="input-with-buttons">
                                <input type="text" id="cavity">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="tissueSealant">Tissue Sealant</label>
                            <div class="input-with-buttons">
                                <input type="text" id="tissueSealant">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="fillings">Fillings</label>
                            <div class="input-with-buttons">
                                <input type="text" id="fillings">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="oralProphylaxis">Oral Prophylaxis</label>
                            <div class="input-with-buttons">
                                <input type="text" id="oralProphylaxis">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="treatmentNeeded">Treatment Needed</label>
                            <div class="input-with-buttons">
                                <input type="text" id="treatmentNeeded">
                            </div>
                        </div>

                        <div class="form-group form-wide">
                            <label for="additional-remarks">Remarks</label>
                            <div class="input-with-buttons textarea-container">
                            <textarea id="additional-remarks" rows="2"></textarea>
                                <div class="textarea-buttons">

                            </div>
                                <button type="button" id="submit">Submit</button>
                            </div>

                        </div>
                    </div>
                </div>
            </div>

<?php
// Assuming you're already connected to the database
$patientName = $patientAge = $patientGender = $patientContact = $patientAddress = '';

// Fetch patient data if ID is present
if (isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $stmt = $conn->prepare("SELECT name, age, sex, contact, address FROM patient_records WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $stmt->bind_result($patientName, $patientAge, $patientGender, $patientContact, $patientAddress);
    $stmt->fetch();
    $stmt->close();
}
?>

<div class="right-column">
    <div class="patient-info">
        <h2>Patient Information</h2>
        <div class="info-card">
            <div class="form-group">
<div class="form-group">
    <label for="patientName">Full Name</label>
    <input type="text" id="patientName" name="patientName" value="<?= htmlspecialchars($patientName) ?>">
</div>
<div class="form-group">
    <label for="patientAge">Age</label>
    <input type="number" id="patientAge" name="patientAge" value="<?= htmlspecialchars($patientAge) ?>" readonly>
</div>
<div class="form-group">
    <label for="patientGender">Gender</label>
    <select id="patientGender" name="patientGender" disabled>
        <option value="">Select</option>
        <option value="male" <?= strtolower($patientGender) == 'male' ? 'selected' : '' ?>>Male</option>
        <option value="female" <?= strtolower($patientGender) == 'female' ? 'selected' : '' ?>>Female</option>
        <option value="other" <?= strtolower($patientGender) == 'other' ? 'selected' : '' ?>>Other</option>
    </select>
</div>

            <div class="form-group">
                <label for="patientContact">Contact Number</label>
                <input type="tel" id="patientContact" name="patientContact" value="<?= htmlspecialchars($patientContact) ?>" readonly>

            <div class="form-group form-wide">
                <label for="patientAddress">Address</label>
                <textarea id="patientAddress" name="patientAddress" rows="2" readonly><?= htmlspecialchars($patientAddress) ?></textarea>
            </div>
        </div>
    </div>
</div>



                <div class="treatment-records">
                    <h2>Dental Treatment Records</h2>
                    <table id="dental-table">
                        <tr>
                            <th>Date</th>
                            <th>Diagnosis</th>
                            <th>Treatment</th>
                            <th>Charge</th>
                        </tr>
                        <tr>
                            <td><input type="date" placeholder=""></td>
                            <td><input type="text" placeholder=""></td>
                            <td><input type="text" placeholder=""></td>
                            <td><input type="text" placeholder=""></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script src="dental_records.js"></script>
</body>
</html>
