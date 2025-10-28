<?php
session_start();
require 'db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($_POST['action'] === 'update_password') {
        $current = $_POST['currentPassword'];
        $new = $_POST['newPassword'];
        $confirm = $_POST['confirmPassword'];

        // Fetch current hashed password
        $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($current, $hashed_password)) {     
            header("Location: admin_profile_module.php?tab=security&password_error=Your+error+message+here");
            exit;

        }

        if ($new !== $confirm) {
            header("Location: admin_profile_module.php?password_error=New passwords do not match");
            exit;
        }

        $new_hashed = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $new_hashed, $user_id);
        $stmt->execute();
        $stmt->close();

       header("Location: admin_profile_module.php?tab=security&password_success=1");
        exit;

    }

    if ($_POST['action'] === 'update_recovery') {
        $recovery = $_POST['recoveryEmail'];

        if (!filter_var($recovery, FILTER_VALIDATE_EMAIL)) {
           header("Location: admin_profile_module.php?tab=security&password_error=Your+error+message+here");
            exit;
        }

        $stmt = $conn->prepare("UPDATE users SET recovery_email = ? WHERE id = ?");
        $stmt->bind_param("si", $recovery, $user_id);
        $stmt->execute();
        $stmt->close();

        header("Location: admin_profile_module.php?recovery_success=1");
        exit;
    }
}
?>
