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
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Contact Us</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <link rel="stylesheet" href="contactUs.css" />
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
            <a href="home.php">Home</a>
            <a href="aboutUs.php">About Us</a>            
            <a href="contactUs.php" class="active">Contact</a>
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


    <section class="contact-section">
        <div class="contact-wrapper">

            <div class="contact-text">
                <h2>Contact Us</h2>
                <p><em>For Emergencies, Feel Free to Text / Call +63 915 828 9869</em></p>
            </div>


            <form id="contactForm" onsubmit="handleSubmit(event)">
                <div class="form-row">
                    <div class="form-group">
                        <label for="firstName">First Name</label><br>
                        <input type="text" id="firstName" name="firstName" required>
                    </div>

                    <div class="form-group">
                        <label for="lastName">Last Name</label><br>
                        <input type="text" id="lastName" name="lastName" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="email">Email</label><br>
                        <input type="email" id="email" name="email" required>
                    </div>

                    <div class="form-group">
                        <label for="contactNumber">Contact Number</label><br>
                        <input type="tel" id="contactNumber" name="contactNumber" required>
                    </div>
                </div>

                <div class="form-column">
                    <label for="message">Reason For Contacting:</label><br>
                    <textarea id="message" name="message" rows="6" required></textarea>
                </div>

                <div class="form-button">
                    <button type="submit">Send Message</button>
                </div>
            </form>
        </div>
    </section>




    <section class="visit-us">
        <h2>Visit Us</h2>


        <div class="map-container">
            <div class="map-container">
                <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.0610746807906!2d121.10951001060636!3d14.59559567714048!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397b953aa8f4adb%3A0x9219c85f35081204!2sUmipig%20Dental%20Clinic%20-%20Cainta!5e0!3m2!1sen!2sph!4v1745230919291!5m2!1sen!2sph"
                        width="1980" height="400" style="border:0;" allowfullscreen="" loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade"></iframe>
            </div>


            <div class="visit-info">
                <div class="address-box">
                    <h4>Our Location</h4>
                    <p>
                        2nd Floor, Village Eats Food Park, Bldg. #9,<br>
                        Village East Executive Homes, 1900 Cainta, Philippines
                    </p>
                </div>

                <div class="hours-box">
                    <h4>Clinic Hours</h4>
                    <ul>
                        <li>Mon–Fri: 9:00 AM – 6:00 PM</li>
                        <li>Sat: 9:00 AM – 3:00 PM</li>
                        <li>Sun: Closed</li>
                    </ul>
                </div>

                <div class="directions-box">
                    <h4>Need Help Finding Us?</h4>
                    <p>We're inside Village Eats Food Park. Look for Building #9 on the 2nd floor. Free parking available.</p>
                    <a href="https://www.google.com/maps/dir/?api=1&destination=Your+Clinic+Address+Here"
                       target="_blank"
                       class="get-directions-btn">
                        Get Directions
                    </a>
                </div>
            </div>
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


    <script src="contactUs.js"></script>
    <?php include 'chatbot.php'; ?>
</body>
</html>
