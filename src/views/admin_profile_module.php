<?php
session_start();
require 'db_connection.php';

// ✅ Check if user is logged in
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit;
}


if ($_SESSION['role'] !== 'admin') {
    header("Location: login.php"); // Or use: header("Location: dashboard.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user and patient info
$sql = "SELECT u.fullname, u.email, u.phone, u.sex, u.recovery_email, pr.address, pr.age
        FROM users u
        LEFT JOIN patient_records pr ON u.id = pr.user_id
        WHERE u.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Fetch appointments
$appointments = [];

$apptSql = "SELECT a.Appointment_Date, a.Service_ID as Service_Type, d.name AS dentist_name, a.Appointment_Status, a.notes 
            FROM appointment a
            LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
            WHERE a.Patient_ID = ?
            ORDER BY a.Appointment_Date DESC";

$apptStmt = $conn->prepare($apptSql);
$apptStmt->bind_param("i", $user_id);
$apptStmt->execute();
$apptResult = $apptStmt->get_result();

while ($row = $apptResult->fetch_assoc()) {
    $appointments[] = $row;
}

$apptStmt->close();



// Check for popup messages
$popupMessage = '';
$popupType = ''; // 'success' or 'error'
if (isset($_GET['password_success'])) {
    $popupMessage = "✔ Password updated successfully!";
    $popupType = 'success';
} elseif (isset($_GET['password_error'])) {
    $popupMessage = htmlspecialchars($_GET['password_error']);
    $popupType = 'error';
} elseif (isset($_GET['recovery_success'])) {
    $popupMessage = "✔ Recovery email updated successfully!";
    $popupType = 'success';
} elseif (isset($_GET['recovery_error'])) {
    $popupMessage = htmlspecialchars($_GET['recovery_error']);
    $popupType = 'error';
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Patient Account Settings</title>
    <link rel="stylesheet" href="admin_profile_module.css">
    <style>
        input[readonly], textarea[readonly], select[disabled] {
            background-color: #e9ecef;
            cursor: not-allowed;
        }
    </style>
</head>
<body>

<div id="popupMessage" class="popup-message <?php echo $popupType; ?>">
    <?php echo $popupMessage; ?>
</div>


<?php
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'personal';
$passwordSuccess = isset($_GET['password_success']);
$recoverySuccess = isset($_GET['recovery_success']);
$passwordError = isset($_GET['password_error']) ? $_GET['password_error'] : '';
$recoveryError = isset($_GET['recovery_error']) ? $_GET['recovery_error'] : '';
?>



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
            <a href="admin_profile_module.php" class="profile-icon" title="Profile">
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

        <h1 style="color: royalblue; margin-left: 150px; margin-top: 50px;">Account Settings</h1>

<div class="settings-container" style="margin-top: 70px; margin-bottom: 100px;"> 
    <div class="tabbed-layout">
        <!-- Category Tabs -->
        <div class="category-column">
            <button onclick="window.location.href='home.php'" style="margin-bottom: 20px; padding: 10px 15px; background-color: rgb(181, 187, 194); color: white; border: none; border-radius: 4px; cursor: pointer;">
                ← Back
            </button>

            <button type="button" class="category-tab active" data-target="personal">Personal Information</button>
            <button type="button" class="category-tab" data-target="security">Security</button>
            <button type="button" class="category-tab" data-target="appointments">Appointment History</button>
        </div>

        <div class="content-column">
            <!-- Personal Information -->
            <div id="personal" class="content-section active">
                <h2>Personal Information</h2>
                <?php if (isset($_GET['success'])): ?>
                    <div class="success-message">✔ Profile updated successfully!</div>
                <?php endif; ?>
                <form id="personalForm" method="POST" action="update_admin_profile.php">
                    <div class="form-group">
                        <label for="fullname">Full Name</label>
                        <input type="text" id="fullname" name="fullname" value="<?php echo htmlspecialchars($user['fullname']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="sex">Gender</label>
                        <select id="sex" name="sex" disabled>
                            <option value="Male" <?php echo ($user['sex'] === 'Male') ? 'selected' : ''; ?>>Male</option>
                            <option value="Female" <?php echo ($user['sex'] === 'Female') ? 'selected' : ''; ?>>Female</option>
                            <option value="Other" <?php echo ($user['sex'] === 'Other') ? 'selected' : ''; ?>>Other</option>
                            <option value="Prefer not to say" <?php echo ($user['sex'] === 'Prefer not to say') ? 'selected' : ''; ?>>Prefer not to say</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="recovery_email">Recovery Email</label>
                        <input type="email" id="recovery_email" name="recovery_email" value="<?php echo htmlspecialchars($user['recovery_email']); ?>" readonly>
                    </div>
                    <div class="form-group">
                        <label for="address">Address</label>
                        <textarea id="address" name="address" rows="3" readonly><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    <button type="button" id="editBtn" class="btn" style="width: 100%; padding: 15px; font-size: 15px;" >Edit</button>
                    <button type="submit" id="saveBtn" class="btn" style="display:none; width: 100%; padding: 15px; font-size: 15px;">Save</button>
                </form>
            </div>

            <!-- Security -->
            <div id="security" class="content-section">
                <h2>Account Security</h2>
                <form method="POST" action="update_admin_password.php">
                    <input type="hidden" name="action" value="update_password">
                    <div class="form-group">
                        <label for="currentPassword">Current Password</label>
                        <input type="password" id="currentPassword" name="currentPassword" required>
                    </div>
                    <div class="form-group">
                        <label for="newPassword">New Password</label>
                        <input type="password" id="newPassword" name="newPassword" required>
                    </div>
                    <div class="form-group">
                        <label for="confirmPassword">Confirm New Password</label>
                        <input type="password" id="confirmPassword" name="confirmPassword" required>
                    </div>
                    <button type="submit" class="btn">Update Password</button>
                </form>

                <hr style="margin: 2rem 0;">
                <h3>Recovery Options</h3>
                <form method="POST" action="update_admin_password.php">
                    <input type="hidden" name="action" value="update_recovery">
                    <div class="form-group">
                        <label for="recoveryEmail">Recovery Email</label>
                        <input type="email" id="recoveryEmail" name="recoveryEmail" value="<?php echo htmlspecialchars($user['recovery_email']); ?>" required>
                    </div>
                    <button type="submit" class="btn">Update Recovery Email</button>
                </form>
            </div>



        <!-- Appointment History -->
<?php
require 'db_connection.php';

$user_id = $_SESSION['user_id'] ?? null;
$appointments = [];
$servicesMap = [];

if ($user_id) {
    // Fetch appointments
    $sql = "SELECT a.Appointment_Date, a.Service_ID as Service_Type, a.notes, d.name AS dentist_name 
            FROM appointment a 
            LEFT JOIN dentists d ON a.Dentist_ID = d.Dentist_ID 
            WHERE a.Patient_ID = ?
            ORDER BY a.Appointment_Date DESC";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $appointments[] = $row;
    }
    $stmt->close();

    // Fetch services map
    $servicesSql = "SELECT service_ID, service_name FROM services";
    $servicesResult = $conn->query($servicesSql);
    while ($srow = $servicesResult->fetch_assoc()) {
        $servicesMap[$srow['service_ID']] = $srow['service_name'];
    }
}
?>
            <!-- Appointment History -->
            <div id="appointments" class="content-section">
                <h2>Appointment History</h2>
                <table class="history-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Services</th>
                            <th>Dentist</th>
                            <th>Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($appointments)): ?>
                            <?php foreach ($appointments as $appt): ?>
                                <?php
                                    $service_ids = json_decode($appt['Service_Type'], true);
                                    $service_names = [];
                                    if (is_array($service_ids)) {
                                        foreach ($service_ids as $sid) {
                                            if (isset($servicesMap[$sid])) {
                                                $service_names[] = $servicesMap[$sid];
                                            }
                                        }
                                    }
                                    $service_display = !empty($service_names) ? implode(", ", $service_names) : 'N/A';
                                    $formatted_date = date('m/d/Y', strtotime($appt['Appointment_Date']));
                                ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($formatted_date); ?></td>
                                    <td><?php echo htmlspecialchars($service_display); ?></td>
                                    <td><?php echo htmlspecialchars($appt['dentist_name']); ?></td>
                                    <td><?php echo htmlspecialchars($appt['notes']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4">No appointment history found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>


</div>

<script>
const tabs = document.querySelectorAll('.category-tab');
const sections = document.querySelectorAll('.content-section');

// --- Tab click event ---
tabs.forEach(tab => {
    tab.addEventListener('click', (e) => {
        e.preventDefault();

        tabs.forEach(t => t.classList.remove('active'));
        sections.forEach(s => s.classList.remove('active'));

        tab.classList.add('active');
        document.getElementById(tab.dataset.target).classList.add('active');

        // Update URL
        const newUrl = window.location.pathname + '?tab=' + tab.dataset.target;
        window.history.pushState({}, '', newUrl);
    });
});

// --- On load, set active tab from URL ---
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const activeTab = urlParams.get('tab') || 'personal';

    tabs.forEach(tab => {
        if (tab.dataset.target === activeTab) {
            tab.classList.add('active');
        } else {
            tab.classList.remove('active');
        }
    });
    sections.forEach(section => {
        if (section.id === activeTab) {
            section.classList.add('active');
        } else {
            section.classList.remove('active');
        }
    });

    document.querySelectorAll('.category-tab').forEach(tab => {
    tab.addEventListener('click', function() {
        // Remove active class from all tabs
        document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
        // Add active class to clicked tab
        this.classList.add('active');
        
        // Hide all content sections
        document.querySelectorAll('.content-section').forEach(section => {
            section.classList.remove('active');
        });
        
        // Show the targeted content section
        const targetId = this.getAttribute('data-target');
        document.getElementById(targetId).classList.add('active');
    });
});


    // Show popup if success/error messages exist
    const popup = document.getElementById('popupMessage');
    if (popup && popup.textContent.trim() !== "") {
        popup.classList.add('show');
        setTimeout(() => {
            popup.classList.remove('show');
        }, 3000);
    }
});

// --- Personal info edit logic ---
const form = document.getElementById('personalForm');
if (form) {
    const editBtn = document.getElementById('editBtn');
    const saveBtn = document.getElementById('saveBtn');
    const inputs = form.querySelectorAll('input, textarea, select');

    let originalData = {};
    inputs.forEach(input => {
        originalData[input.name] = input.value;
    });

    editBtn.addEventListener('click', () => {
        inputs.forEach(input => {
            if (input.tagName === 'SELECT') {
                input.disabled = false;
            } else {
                input.readOnly = false;
            }
        });
        editBtn.style.display = 'none';
        saveBtn.style.display = 'inline-block';
    });

    inputs.forEach(input => {
        input.addEventListener('input', () => {
            let hasChanges = false;
            inputs.forEach(i => {
                if (i.value !== originalData[i.name]) {
                    hasChanges = true;
                }
            });

            if (hasChanges) {
                saveBtn.disabled = false;
            } else {
                saveBtn.disabled = true;
            }
        });
    });

    form.addEventListener('submit', (e) => {
        let hasChanges = false;
        inputs.forEach(i => {
            if (i.value !== originalData[i.name]) {
                hasChanges = true;
            }
        });

        if (!hasChanges) {
            e.preventDefault();
            alert("No changes detected!");
            saveBtn.disabled = true;
            saveBtn.style.display = 'none';
            editBtn.style.display = 'inline-block';
            inputs.forEach(input => {
                if (input.tagName === 'SELECT') {
                    input.disabled = true;
                } else {
                    input.readOnly = true;
                }
            });
        }
    });
}

function updatePassword() {
    const newPassword = document.getElementById('newPassword').value;
    const confirmPassword = document.getElementById('confirmPassword').value;
    if (newPassword && newPassword === confirmPassword) {
        document.getElementById('passwordMessage').style.display = 'block';
    } else {
        alert("Passwords do not match!");
    }
}

function updateRecovery() {
    document.getElementById('recoveryMessage').style.display = 'block';
}
</script>



</body>
</html>
