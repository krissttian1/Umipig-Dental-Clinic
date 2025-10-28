<?php
session_start();  // This must be before accessing $_SESSION

// Enable error reporting (remove or set to 0 in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Database connection
$host = "localhost";
$user = "root";
$pass = "";
$db = "clinic_db";

// Connect to MySQL
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// ===== ADD NOTIFICATION SYSTEM =====
require_once 'notifications/notification_functions.php';
// ===== END NOTIFICATION SYSTEM =====

// Helper function to get safe POST values
function post($key) {
    return isset($_POST[$key]) ? htmlspecialchars(trim($_POST[$key])) : null;
}

// Collect form data
$name = post('name');
$age = post('age');
$birthdate = post('birthdate');
$sex = post('sex');
$address = post('address');
$contact = post('contact');
$email = post('email');
$reason = post('reason');

$goodHealth = post('goodHealth');
$underTreatment = post('underTreatment');
$treatmentDetails = post('treatmentDetails');
$medication = post('medication');
$medicationDetails = post('medicationDetails');
$tobacco = post('tobacco');
$allergy = post('allergy');
$allergyDetails = post('allergyDetails');

$oralIssues = post('oralIssues');
$oralIssuesDetails = post('oralIssuesDetails');
$dentalComplications = post('dentalComplications');
$dentalComplicationsDetails = post('dentalComplicationsDetails');
$gumBleeding = post('gumBleeding');
$toothSensitivity = post('toothSensitivity');
$grindingClenching = post('grindingClenching');
$dentalSurgery = post('dentalSurgery');
$dentalSurgeryDetails = post('dentalSurgeryDetails');
$jawProblems = post('jawProblems');
$jawProblemDetails = post('jawProblemDetails');

$pregnant = post('pregnant');
$birthControl = post('birthControl');

// Prepare SQL statement
$stmt = $conn->prepare("INSERT INTO patient_records (
  name, age, birthdate, sex, address, contact, email, reason,
  goodHealth, underTreatment, treatmentDetails, medication, medicationDetails,
  tobacco, allergy, allergyDetails, oralIssues, oralIssuesDetails,
  dentalComplications, dentalComplicationsDetails, gumBleeding, toothSensitivity,
  grindingClenching, dentalSurgery, dentalSurgeryDetails, jawProblems, jawProblemDetails,
  pregnant, birthControl, user_id
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

// Check if prepare() succeeded
if (!$stmt) {
    die("Prepare failed: " . $conn->error);
}

// Bind parameters (29 fields)
$stmt->bind_param("sisssssssssssssssssssssssssssi",
  $name, $age, $birthdate, $sex, $address, $contact, $email, $reason,
  $goodHealth, $underTreatment, $treatmentDetails, $medication, $medicationDetails,
  $tobacco, $allergy, $allergyDetails, $oralIssues, $oralIssuesDetails,
  $dentalComplications, $dentalComplicationsDetails, $gumBleeding, $toothSensitivity,
  $grindingClenching, $dentalSurgery, $dentalSurgeryDetails, $jawProblems, $jawProblemDetails,
  $pregnant, $birthControl, $user_id
);

// Execute and redirect or show error
if ($stmt->execute()) {
    $new_patient_id = $conn->insert_id; // Get the ID of the newly created patient
    
    // ===== NOTIFICATION TRIGGER =====
    createAdminNotification(
        'patient', 
        'New Patient Registered', 
        'Patient ' . $name . ' has been registered in the system by ' . $_SESSION['username'] . '.',
        'medium',
        'patient_records.php',
        $new_patient_id
    );
    // ===== END NOTIFICATION TRIGGER =====
    
    echo "<script>alert('Patient record submitted successfully!'); window.location.href = 'home.php';</script>";
} else {
    echo "Error: " . $stmt->error;
}

// Clean up
$stmt->close();
$conn->close();
?>