<?php

session_start();

// Redirect admin users
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <title>Terms and Conditions</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <style>


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
    font-family: 'Poppins', sans-serif;
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

    body {
      font-family: 'Georgia', serif;
      max-width: 800px;
      margin: 100px auto;
      padding: 40px;
      background: #fff;
      box-shadow: 0 0 10px rgba(0,0,0,0.7);
      line-height: 1.8;
      color: #333;

      
    }
    h1 {
      text-align: center;
      font-size: 28px;
      margin-bottom: 30px;
    }
    h2 {
      font-size: 20px;
      margin-top: 30px;
    }
    p {
      margin-bottom: 15px;
      text-align: justify;
    }
    a.close-link {
      display: block;
      margin-top: 30px;
      text-align: center;
      font-weight: bold;
      text-decoration: none;
      color: #0066cc;
    }
    a.close-link:hover {
      text-decoration: underline;
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

<div class="container">
  <h1>Terms and Conditions & Privacy Policy</h1>

  <p><strong>1. Personal Information</strong><br>
  By filling out and submitting the patient information form, you agree to provide accurate and complete personal details. This information will be used to manage your dental appointments, keep medical records, and contact you when necessary.</p>

  <p><strong>2. Privacy and Data Protection</strong><br>
  We are committed to protecting your privacy. Your information will be kept strictly confidential and used only for dental clinic-related purposes. We will not share your data with third parties without your consent, except when required by law.</p>

  <p><strong>3. Appointment Policy</strong><br>
  You are encouraged to arrive on time for appointments. If you need to cancel or reschedule, please inform us at least 24 hours in advance to help us accommodate other patients.</p>

  <p><strong>4. Confirmation and Cancellations</strong><br>
  Certain appointments require confirmation by email or phone. If not confirmed within the specified time, the appointment may be cancelled or given to another patient.</p>

  <p><strong>5. Treatment and Fees</strong><br>
  Treatment options and related fees will be clearly explained to you before proceeding. You agree to pay the fees for any dental services received at the clinic.</p>

  <p><strong>6. Health and Safety</strong><br>
  It is important to inform us about any current health conditions, allergies, medications, or medical treatments. This allows us to provide safe and appropriate care during your visit.</p>

  <p><strong>7. Changes to Schedule or Services</strong><br>
  In some cases, the clinic may need to adjust appointment schedules or services due to unforeseen circumstances. You will be notified as early as possible of any changes.</p>

  <p><strong>8. Your Rights</strong><br>
  You have the right to access, update, or request deletion of your personal data, except where it is legally or medically necessary to retain your records. You may also withdraw your consent at any time, though this may affect our ability to provide services.</p>

  <p><strong>9. Agreement</strong><br>
  By submitting the patient information form, you acknowledge that you have read, understood, and agreed to these Terms and Conditions and Privacy Policy. You also consent to the collection and use of your personal information as described above.</p>

  <a href="javascript:window.close();" class="close-link">Close this window</a>
  </div>
</body>
</html>
