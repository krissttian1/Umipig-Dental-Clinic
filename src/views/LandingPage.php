<?php
session_start();
if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
    header("Location: admin_dashboard.php");
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Umipig Dental Clinic</title>
    <meta name="description" content="Umipig Dental Clinic - General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist">
    <meta name="author" content="Umipig Dental Clinic">
    <meta name="keywords" content="dental, clinic, dentist, oral health, teeth, dental care">
    
    <!-- Favicon -->
    <link rel="icon" type="image/jpg" href="assets/images/UmipigDentalClinic_Logo.jpg">
    
    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Styles -->
    <link rel="stylesheet" href="assets/css/output.css">
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

        <nav class="main-nav">
            <a href="home.php">Home</a>
            <a href="aboutUs.php">About Us</a>
            <a href="contactUs.php">Contact</a>
            <a href="services.php">Services</a>
        </nav>

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

    <section class="hero-section fade-section" data-direction="left">
        <div class="hero-background"></div>
        <div class="hero-overlay"></div>
        <div class="container">
            <div class="hero-content">
                <div class="hero-text">
                    <h2>Welcome to Umipig Dental Clinic, where your smile is our priority.</h2>
                    <p class="hero-description">
                        We provide expert care in a comfortable environment, helping you maintain a healthy, confident smile.
                        Our team is dedicated to ensuring a stress-free visit, using the latest technology and techniques for the best results.
                        Whether for routine check-ups or specialized treatments, we offer personalized care to meet your unique dental needs!
                    </p>
                    <p class="hero-subtext">
                        From routine checkups to advanced treatments, we're here for you. Book an appointment now!
                    </p>
                </div>

                <div class="appointment-card">
                    <div class="appointment-header">
                        <h3>Schedule Appointment Now</h3>
                        <p>Your Smile, Our Priority!</p>
                    </div>
                    <button class="appointment-button" id="appointmentButton">
                        Book an Appointment
                    </button>
                    
                    <?php if (!isset($_SESSION['user_id'])): ?>
                        <div class="login-warning" style="color: #d32f2f; background-color: #fde8e8; padding: 8px; border-radius: 4px; margin-top: 15px; text-align: center; border: 1px solid #f5c6cb; font-size: 11px;">
                            <i class="fas fa-exclamation-circle"></i>
                            You must be <a href="login.php" style="color: #d32f2f; font-weight: bold; text-decoration: none;">logged in</a> to schedule an appointment.
                            Don't have an account? <a href="register.php" style="color: #d32f2f; font-weight: bold; text-decoration: none;">Register here</a>.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <section class="carousel-section fade-section" data-direction="right">
        <div class="container">
            <div class="carousel-container">
                <div class="carousel-viewport">
                    <div class="carousel-track">
                        <div class="carousel-slide"><img src="images/Carousel_Image1.jpg" alt="Dental chair and equipment" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image2.jpg" alt="Modern dental clinic reception" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image3.jpg" alt="Dentist with patient" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image4.jpg" alt="Dental tools closeup" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image5.jpg" alt="Patient getting dental treatment" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image6.jpg" alt="Dental X-ray view" /></div>
                        <div class="carousel-slide"><img src="images/Carousel_Image7.jpg" alt="Modern dental office" /></div>
                    </div>
                </div>

                <button class="carousel-button-left">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="M15 19l-7-7 7-7" /></svg>
                </button>

                <button class="carousel-button-right">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24"><path d="M9 5l7 7-7 7" /></svg>
                </button>
            </div>
        </div>
    </section>

    <section class="services-section fade-section" data-direction="left">
        <div class="container">
            <h2>Services</h2>
            <div class="services-grid">
                <div class="service-card">
                    <div class="service-content">
                        <div class="service-image"><img src="images/braces.jpg" alt="Orthodontics" /></div>
                        <h4>Orthodontics</h4>
                        <p>Specialized treatment for teeth alignment and bite correction using modern techniques.</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-content">
                        <div class="service-image"><img src="images/generalDentistry.jpg" alt="General Dentistry" /></div>
                        <h4>General Dentistry</h4>
                        <p>Comprehensive dental care including cleanings, fillings, and preventive treatments.</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-content">
                        <div class="service-image"><img src="images/rootCanal.jpg" alt="Oral Surgery" /></div>
                        <h4>Oral Surgery</h4>
                        <p>Expert surgical procedures including extractions and implant placement.</p>
                    </div>
                </div>
                <div class="service-card">
                    <div class="service-content">
                        <div class="service-image"><img src="images/veneers.jpg" alt="Cosmetic Dentistry" /></div>
                        <h4>Cosmetic Dentistry</h4>
                        <p>Transform your smile with our advanced cosmetic procedures.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                        <p>Umipigdentalclinic@gmail.com</p>
                    </div>
                </div>

                <div class="footer-row">
                    <img src="images/phone-call.png" alt="Phone Icon" class="icon" />
                    <div>
                        <p class="contact-label">Hotline</p>
                        <p>0999-1112222</p>
                    </div>
                </div>

                <div class="footer-row">
                    <img src="images/facebook.png" alt="Facebook Icon" class="icon" />
                    <div>
                        <p class="contact-label">Facebook</p>
                        <a href="https://www.facebook.com/umipigdentalcliniccainta" target="_blank">
                            https://www.facebook.com/umipigdentalcliniccainta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </footer>

    <script src="LandingPage.js"></script>
    
    <script>
        // Appointment button functionality
        document.addEventListener('DOMContentLoaded', function() {
            const appointmentButton = document.getElementById('appointmentButton');
            
            if (appointmentButton) {
                appointmentButton.addEventListener('click', function() {
                    <?php if (isset($_SESSION['user_id'])): ?>
                        // If logged in, redirect to appointment page
                        window.location.href = 'appointment.php';
                    <?php else: ?>
                        // If not logged in, redirect to login page
                        window.location.href = 'login.php';
                    <?php endif; ?>
                });
            }
        });
    </script>
    
    <?php include 'chatbot.php'; ?>
</body>
</html>