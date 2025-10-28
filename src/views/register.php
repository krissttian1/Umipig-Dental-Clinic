<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once 'PHPMailer/src/PHPMailer.php';
require_once 'PHPMailer/src/SMTP.php';
require_once 'PHPMailer/src/Exception.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    //$firstname = $_POST['firstname'];
    //$lastname = $_POST['lastname'];
    //$fullname = $firstname+' '+$lastname;
    $fullname = $_POST['fullname']; //delete for later.
    $username = $_POST['username'];
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $phone = $_POST['phone'];
    $password = $_POST['password'];
    $confirm = $_POST['confirm'];
    $sex = $_POST['sex'];

    if (!$email) {
        die("Invalid email format.");
    }

    if ($password !== $confirm) {
        die("Passwords do not match.");
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    $conn = new mysqli('localhost', 'root', '', 'clinic_db');
    if ($conn->connect_error) {
        die('Connection Failed: ' . $conn->connect_error);
    }

    $token = bin2hex(random_bytes(16)); 

    $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, phone, password, sex, verification_token, email_verification) VALUES (?, ?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("sssisss", $fullname, $username, $email, $phone, $hashedPassword, $sex, $token);

    if ($stmt->execute()) {
        $mail = new PHPMailer(true);
        try {
            $mail->isSMTP();
            $mail->Host       = 'smtp.gmail.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'onixeugenio2003@gmail.com';
            $mail->Password   = 'gckfonfnbqjiabhr';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            $mail->setFrom('onixeugenio2003@gmail.com', 'Umipig Dental Clinic');
            $mail->addAddress($email, $fullname);

            $verifyLink = "http://localhost/Thesis/verify_email.php?email=" . urlencode($email) . "&token=$token";

            $mail->isHTML(true);
            $mail->Subject = 'Email Verification - Umipig Dental Clinic';
            $mail->Body = "
                Hi $fullname,<br><br>
                Your account has now been registered!<br>
                Please click the link below to verify your email:<br><br>
                <a href='" . htmlspecialchars($verifyLink, ENT_QUOTES, 'UTF-8') . "'>Verify Email</a><br><br>
                If you didn't request this, you can ignore this email.
            ";

            $mail->send();

            echo "<script>
                alert('Please click the link that was sent to your email to verify your account.');
                window.location.href = 'login.php';
            </script>";
            exit;

        } catch (Exception $e) {
            echo "Registration successful, but email not sent. Mailer Error: {$mail->ErrorInfo}";
        }

        $stmt->close();
        $conn->close();
    } else {
        die("Error saving user: " . $stmt->error);
    }
}
?>


<!-- HTML FORM ALWAYS RENDERS -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Registration Form</title>
  <link rel="stylesheet" href="register.css">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
</head>
<header class="top-bar">
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
      <a href="home.php">Home</a>
      <a href="aboutUs.php">About Us</a>
      <a href="contactUs.php">Contact</a>
      <a href="services.php">Services</a>
  </nav>
  <div class="header-right">
      <a href="register.php" class="auth-link">Register</a>
      <span>|</span>
      <a href="login.php" class="auth-link">Login</a>
  </div>
</header>

<body>
  <div class="container">
    <h2>Registration</h2>
    <form action="register.php" method="post">
      <div class="form-group">
        <label for="fullname">Full Name</label>
        <input type="text" id="fullname" name="fullname" placeholder="Enter your name" required>
      </div>
      <div class="form-group">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" placeholder="Enter your username" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" placeholder="Enter your email" required>
      </div>
      <div class="form-group">
        <label for="phone">Phone Number</label>
        <input type="tel" id="phone" name="phone" placeholder="Enter your number" required>
      </div>
      <div class="form-group">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Enter your password" required>
      </div>
      <div class="form-group">
        <label for="confirm">Confirm Password</label>
        <input type="password" id="confirm" name="confirm" placeholder="Confirm your password" required>
      </div>
      <div class="gender-group">
        <label>Sex</label>
        <div class="gender-options">
          <label><input type="radio" name="sex" value="Male" required> Male</label>
          <label><input type="radio" name="sex" value="Female" required> Female</label>     
        </div>
      </div>
      <button type="submit" class="register-btn">Register</button>
    </form>
  </div>
</body>
</html>
