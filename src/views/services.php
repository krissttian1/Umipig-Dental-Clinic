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
    <title>Umipig Dental Clinic - Services</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="services.css">
    <style>
        .modal {
            display: none;
            position: fixed;
            z-index: 999;
            left: 0; top: 0;
            width: 100%; height: 100%;
            overflow: auto;
            background-color: rgba(0,0,0,0.6);
        }
        .modal-content {
            background-color: #fff;
            margin: 10% auto;
            padding: 30px;
            border-radius: 10px;
            width: 90%;
            max-width: 600px;
            font-family: 'Poppins', sans-serif;
            position: relative;
        }
        .modal-content img {
            width: 100%;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .modal-content h2 {
            margin: 0 0 10px;
            color: #2a2a2a;
        }
        .modal-content p {
            margin: 10px 0;
        }
        .close {
            position: absolute;
            top: 15px; right: 20px;
            font-size: 22px;
            font-weight: bold;
            color: #aaa;
            cursor: pointer;
        }
        .close:hover {
            color: #000;
        }
        .modal-content button {
            background-color: #007bff;
            color: white;
            border: none;
            width: 100%;
            padding: 15px;
            font-size: 16px;
            margin-top: 20px;
            border-radius: 5px;
            cursor: pointer;
        }
        .modal-content button:hover {
            background-color: #0056b3;
        }
        
        .confirm-btn {
        width: 50%;
        padding: 12px 20px;
        background-color: royalblue;
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 12px;
        font-weight: bold;
        cursor: pointer;
        transition: background-color 0.3s ease;
        }

        .confirm-btn:hover {
        background-color: #274bdb; /* darker blue on hover */
        }

        .service-image,
        .overlay {
            cursor: pointer;
        }

    </style>
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
        <a href="contactUs.php">Contact</a>
        <a href="services.php" class="active">Services</a>
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

<h1>- Our Services -</h1>
<div class="services-container"></div>

<!-- Modal -->
<div id="serviceModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <img id="modalImage" src="" alt="Service Image">
        <h2 id="modalTitle"></h2>
        <p id="modalDescription"></p>
        <button onclick="proceedToAppointment()">Proceed with This Service</button>
    </div>
</div>

<script>
const servicesInfo = {
    "Dental Cleaning": {
        image: "images/dentalCleaning_Service.jpg",
        description: "Professional dental cleaning removes plaque, tartar, and stains from your teeth, helping prevent cavities and gum disease. Our hygienists use specialized tools to clean hard-to-reach areas, leaving your teeth smooth and your mouth feeling fresh. Regular cleanings are essential for maintaining optimal oral health and preventing more serious dental issues."
    },
    "Regular Checkup": {
        image: "images/regularCheckup_Service.jpg",
        description: "Comprehensive dental examinations assess your overall oral health, including teeth, gums, and soft tissues. These checkups include oral cancer screening, bite analysis, and evaluation of existing dental work. Early detection of problems during regular checkups can save you time, money, and discomfort in the long run."
    },
    "Orthodontic Treatment": {
        image: "images/orthodonticTreatment_Service.jpg",
        description: "Orthodontic treatment aligns crooked teeth and corrects bite issues using braces or clear aligners like Invisalign. It's beneficial for improving oral function, preventing jaw strain, minimizing the risk of decay due to misalignment, and enhancing smile aesthetics. Early treatment can also guide proper jaw development in children and teens."
    },
    "Emergency Care": {
        image: "images/emergencyCare_Service.jpg",
        description: "Immediate dental care for urgent situations like severe toothache, knocked-out teeth, broken restorations, or dental trauma. Our emergency services provide prompt pain relief and address acute dental problems to prevent further complications. We offer same-day appointments for genuine dental emergencies."
    },
    "Root Canal": {
        image: "images/rootCanal_Service.png",
        description: "Root canal therapy is a dental procedure designed to treat infection at the center of a tooth (the pulp). This procedure involves removing infected or damaged tissue, cleaning the inside of the tooth, and sealing it. It's a crucial alternative to tooth extraction and helps eliminate severe pain, stop the spread of infection, and retain the natural tooth structure."
    },
    "Cosmetic Dentistry": {
        image: "images/cosmeticDentistry_Service.jpg",
        description: "Cosmetic dentistry focuses on improving the appearance of teeth, gums, and smile. It includes procedures like teeth whitening, dental veneers, enamel bonding, and reshaping. These treatments not only enhance visual appeal but also boost confidence and self-esteem. They are tailored to each patient's facial features for a balanced, attractive smile."
    },
    "Dental Implants": {
        image: "images/dentalImplants_Service.jpeg",
        description: "Dental implants are titanium posts surgically inserted into the jawbone to support replacement teeth. They provide a durable, natural-looking solution for missing teeth, helping preserve bone structure, restore normal eating and speaking, and prevent facial sagging caused by tooth loss. Implants offer a long-term alternative to dentures and bridges."
    },
    "Professional Teeth Whitening": {
        image: "images/professionalTeethWhitening_Service.jpg",
        description: "This cosmetic procedure brightens your smile by using dental-grade bleaching agents to remove stains caused by coffee, tea, tobacco, and aging. In-office whitening is safer and more effective than over-the-counter products, offering noticeable results in a single session and customized treatment for tooth sensitivity."
    },
    "Pediatric Dentistry": {
        image: "images/pediatricDentistry_Service.jpg",
        description: "Pediatric dentistry provides age-appropriate dental care to infants, children, and teens. It includes cavity prevention, fluoride applications, habit-breaking counseling (e.g., thumb sucking), and early orthodontic evaluations. These visits are designed to be friendly and fun, encouraging lifelong dental health habits in young patients."
    },
    "Dentures": {
        image: "images/dentures_Service.jpg",
        description: "Dentures are removable prosthetics designed to replace multiple missing teeth. They help restore chewing ability, improve speech, and support facial muscles to prevent sagging. Modern dentures are made from durable, comfortable materials and are custom-fitted to provide a natural look and feel."
    },
    "Gum Disease Treatment": {
        image: "images/gumDiseaseTreatment_Service.jpg",
        description: "Also known as periodontal therapy, this treatment focuses on controlling gum infections and restoring gum health. Depending on the severity, procedures may include scaling and root planing (deep cleaning), antibiotic therapy, or surgery. Treating gum disease helps prevent tooth loss and supports overall health, as gum infection is linked to heart disease and diabetes."
    },
    "Tooth Extraction": {
        image: "images/toothExtraction_Service.jpg",
        description: "Tooth extraction involves removing severely damaged, decayed, or problematic teeth that cannot be saved through other treatments. Our gentle extraction techniques minimize discomfort and promote proper healing. We provide clear aftercare instructions to ensure a smooth recovery process."
    },
    "Dental Crowns & Bridges": {
        image: "images/dentalCrowns_Service.jpg",
        description: "Crowns are protective caps placed over damaged or weak teeth, restoring their shape, strength, and appearance. Bridges replace one or more missing teeth by anchoring artificial teeth to adjacent natural teeth or implants. These restorations improve chewing, prevent tooth shifting, and enhance aesthetics."
    },
    "TMJ Treatment": {
        image: "images/tmjTreatment_Service.jpg",
        description: "Temporomandibular joint (TMJ) disorders affect the jaw joint and surrounding muscles, leading to pain, clicking sounds, and limited jaw movement. Treatments vary based on severity and may include custom mouthguards, physical therapy, lifestyle changes, medication, or surgery. Early diagnosis can prevent chronic pain and dysfunction."
    },
    "Dental Sealants": {
        image: "images/dentalSealants_Service.jpg",
        description: "Sealants are thin, protective coatings applied to the grooves of molars and premolars to prevent cavities. They act as a barrier against food and bacteria and are especially effective in children with developing teeth. Application is quick, painless, and offers years of decay protection."
    },
    "Fluoride Treatment": {
        image: "images/fluorideTreatment_Service.jpg",
        description: "Fluoride treatments strengthen enamel and help reverse early tooth decay. Commonly applied during dental cleanings, the treatment is quick and non-invasive. It benefits both children and adults by providing additional protection against cavities, particularly in high-risk patients."
    },
    "Dental Filling (Pasta)": {
        image: "images/dentalFilling_Service.jpg",
        description: "Dental fillings restore teeth damaged by decay back to their normal function and shape. We use tooth-colored composite resin that matches your natural tooth color for a seamless appearance. The procedure involves removing decayed material, cleaning the affected area, and filling the cavity with durable material."
    },
    "Veneers": {
        image: "images/veneers_Service.jpg",
        description: "Dental veneers are thin, custom-made shells of tooth-colored materials designed to cover the front surface of teeth. They can dramatically improve the appearance of discolored, worn down, chipped, or misaligned teeth. Veneers provide a natural tooth appearance and are resistant to stains."
    },
    "Bridges": {
        image: "images/bridges_Service.jpg",
        description: "Dental bridges literally bridge the gap created by one or more missing teeth. A bridge is made up of two or more crowns for the teeth on either side of the gap and a false tooth/teeth in between. Bridges can restore your smile, ability to properly chew and speak, and maintain the shape of your face."
    }
};

const container = document.querySelector('.services-container');
const modal = document.getElementById('serviceModal');
const modalImage = document.getElementById('modalImage');
const modalTitle = document.getElementById('modalTitle');
const modalDescription = document.getElementById('modalDescription');

Object.entries(servicesInfo).forEach(([service, data]) => {
    const card = document.createElement('div');
    card.className = 'service-card';
    card.innerHTML = `
        <img src="${data.image}" class="service-image" alt="${service}" onclick="openModal('${service}')">
        <div class="overlay" onclick="openModal('${service}')"><h3>${service}</h3></div>
        <button class="confirm-btn" onclick="openModal('${service}')">Learn More</button>
    `;

    container.appendChild(card);
});

function openModal(service) {
    const data = servicesInfo[service];
    modalImage.src = data.image;
    modalTitle.textContent = service;
    modalDescription.textContent = data.description;
    modal.style.display = 'block';
    localStorage.setItem('selectedService', service);
}

function closeModal() {
    modal.style.display = 'none';
}

function proceedToAppointment() {
    window.location.href = 'home.php#book-appointment';
}

window.onclick = function(event) {
    if (event.target === modal) {
        closeModal();
    }
}
</script>
<?php include 'chatbot.php'; ?>
</body>
</html>