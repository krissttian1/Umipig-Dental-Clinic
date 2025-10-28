<?php
// Database connection
$conn = new mysqli("localhost", "root", "", "clinic_db");
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Make sure email and token are set
if (!isset($_GET['email']) || !isset($_GET['token'])) {
    die("<h2>Invalid verification link.</h2>");
}

// Sanitize and decode values
$email = urldecode($_GET['email']);
$token = urldecode($_GET['token']);

// Query the user with matching email and token
$stmt = $conn->prepare("SELECT id, email_verification FROM Users WHERE email = ? AND verification_token = ? LIMIT 1");
$stmt->bind_param("ss", $email, $token);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows === 1) {
    $stmt->bind_result($id, $email_verification);
    $stmt->fetch();
    $stmt->close();

    // Check if already verified
    if ($email_verification == 1) {
        echo "<h2>Your email is already verified.</h2>";
        echo "<p><a href='login.php'>Go to Login</a></p>";
        exit;
    }

    // Mark as verified
    $update = $conn->prepare("UPDATE users SET email_verification = 1, verification_token = NULL WHERE id = ?");
    $update->bind_param("i", $id);
    if ($update->execute()) {
        $update->close();
        echo "<script>alert('✅ Email verified! You can now log in.'); window.location.href='login.php';</script>";
        exit;
    } else {
        $update->close();
        die("<h2>Failed to verify email. Please try again later.</h2>");
    }
} else {
    $stmt->close();
    echo "<h2>❌ Invalid or expired verification link.</h2>";
    exit;
}
?>
