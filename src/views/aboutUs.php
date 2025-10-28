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
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Umipig Dental Clinic - About Us</title>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="aboutUs.css" />
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
        <a href="aboutUs.html" class="active">About Us</a>
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

  <!-- Hero -->
  <section class="hero">
    <div class="container">
      <h2>About Us</h2>
      <p>Put your best smile everywhere you go!</p>
    </div>
  </section>

  <!-- About Section -->
  <section class="about-section">
    <div class="container content">
      <div class="text-column">
        <h1>Your safety is our priority</h1>
          <p class="clinic-description">
            Umipig Dental Clinic specializes in providing all types of dental services 
            for kids to adults. We have a team of experienced dentists and highly trained staff to give our patients the best care they need.
          </p>

        <h1>Commitment to Excellence</h1>
          <p class="clinic-description">
            We know that a perfect smile can make a lasting impression and this is exactly the reason why we’re here. Our patients can enjoy comprehensive dental services as we are committed to giving you the highest quality care. We employ technologies and techniques to make your every visit worth it. As soon as you enter our clinic, we will ensure that you’ll remain comfortable and well taken care of. With us, you’ll have the peace of mind that you’ll only get the best care and treatment for your teeth!
        </p>

        <h1>Professional & Friendly Staff</h1>
          <p class="clinic-description">
            We at Umipig Dental Clinic take pride in the level of treatments and services we’re offering. Aside from the state-of-the-art equipment, our dental team is fully equipped with knowledge and experience in the field. All of our staff and dentists have been trained extensively to ensure that you’ll receive the best care and treatment. From consultation to the actual dental procedure, our team will assist you in every step of the way. Our services also cover after care services to ensure you’ll heal properly and recover quickly.
          </p>

        <!-- Meet Our Team Section -->
        <section class="team-section">
          <div class="container">
            <h2>Meet Our Team</h2>
            <p class="team-intro">Get to know the professionals who make your smile their priority.</p>
            <div class="team-grid">
              <div class="team-member">
                <img src="images/aurea.jpg" alt="Dr. Aurea Ramos Umipig" />
                <h4>Dr. Aurea Ramos Umipig</h4>
                <p>Dentist / Clinic Owner</p>
              </div>
              <div class="team-member">
                <img src="images/jairus.jpg" alt="Jairus Umipig" />
                <h4>Jairus Umipig</h4>
                <p>Admin</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Professional Certifications -->
<section class="certifications">
  <h2>Professional Certifications</h2>
  <p class="cert-desc">Committed to excellence through continuous education and specialized training in the latest dental techniques and technologies.</p>
  <div class="cert-grid">
    <a href="images/cert1.jpg" class="cert-card" target="_blank" style="text-decoration: none;">
      <div class="cert-icon">🎓</div>
      <h4>Doctor of Dental Surgery (DDS)</h4>
      <p>Univesity of Philippines</p>
      <span>📅 2007</span>
    </a>
    <a href="images/cert2.jpg" class="cert-card" target="_blank" style="text-decoration: none; color: blue;">
      <div class="cert-icon">⭐</div>
      <h4>Academic of Orthodontics</h4>
      <p>Philippines Academy of Orthodontics Dentistry</p>
      <span>📅 2008</span>
    </a>
    <a href="images/cert3.jpg" class="cert-card" target="_blank" style="text-decoration: none; color: blue;">
      <div class="cert-icon">🏅</div>
      <h4>Certification of Achievement</h4>
      <p>International Basic Implantt Training of </p>
      <span>📅 2011</span>    
    </a>
    <a href="images/cert4.jpg" class="cert-card" target="_blank" style="text-decoration: none; color: blue;">
      <div class="cert-icon">❤️</div>
      <h4>Orthodontics Dentistry Specialist</h4>
      <p>Philippines Board of Orthodontist Dentistry</p>
      <span>📅 2007</span>
    </a>
    <a href="images/cert5.jpg" class="cert-card" target="_blank" style="text-decoration: none; color: blue;">
      <div class="cert-icon">👨‍⚕️</div>
      <h4>Certification of Attendance</h4>
      <p>Attending the clubs scientific activity on topic "Maintenance of Implant"</p>
      <span>📅 2006</span>
    </a>
  </div>
</section>


        <!-- Partnership Section -->
        <!-- Inside .about-section, replace old partnership section with this updated version -->

<!-- Partnership Section -->
<section class="partnership-section">
  <div class="container">
    <h3>Our Partnership</h3>
    <div class="partnership-flex">
      <div class="orthero-image">
        <img src="images/orthero.jpg" alt="Orthero" />
      </div>
      <div class="orthero-description">
        <h4>What is ORTHERO?</h4>
        <p>
          Orthero uses the latest technology in aesthetic orthodontic in aligning your teeth with the use of smart and custom-made clear aligners.
        </p>
        <p>
          Under the supervision of an Orthero Certified Provider, your Orthero clear aligners will help you straighten your teeth to its ideal position according to the treatment plan without the painful wires and brackets used in the traditional orthodontic treatment.
        </p>
        <p>
          It is nearly invisible, offers faster treatment, 100% medical-grade plastic materials, biocompatible, easy-to-use, and removable.
        </p>
      </div>
    </div>
  </div>
</section>


  <!-- Modal -->
  <div id="imageModal" class="modal">
    <span class="close" onclick="closeModal()">&times;</span>
    <img class="modal-content" id="modalImage">
  </div>

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

  <!-- Modal Script -->
  <script>
    function openModal() {
      const modal = document.getElementById("imageModal");
      const modalImg = document.getElementById("modalImage");
      modal.style.display = "block";
      modalImg.src = "images/orthero.jpg";
    }

    function closeModal() {
      document.getElementById("imageModal").style.display = "none";
    }

    window.onclick = function(event) {
      const modal = document.getElementById("imageModal");
      if (event.target == modal) {
        modal.style.display = "none";
      }
    };
  </script>
<?php include 'chatbot.php'; ?>
</body>
</html>
