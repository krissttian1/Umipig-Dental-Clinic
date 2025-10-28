const services = [
    "Root Canal",
    "Cosmetic Dentistry",
    "Dental Implants",
    "Orthodontic Treatment",
    "General Dental Checkup",
    "Professional Teeth Whitening",
    "Pediatric Dentistry",
    "Dentures",
    "Gum Disease Treatment",
    "Wisdom Teeth Extraction",
    "Dental Crowns & Bridges",
    "TMJ Treatment",
    "Dental Sealants",
    "Fluoride Treatment"
];

const imagePaths = {
    "Root Canal": "images/rootCanal_Service.png",
    "Cosmetic Dentistry": "images/cosmeticDentistry_Service.jpg",
    "Dental Implants": "images/dentalImplants_Service.jpeg",
    "Orthodontic Treatment": "images/orthodonticTreatment_Service.jpg",
    "General Dental Checkup": "images/generalDentalCheckup_Service.jpg",
    "Professional Teeth Whitening": "images/professionalTeethWhitening_Service.jpg",
    "Pediatric Dentistry": "images/pediatricDentistry_Service.jpg",
    "Dentures": "images/dentures_Service.jpg",
    "Gum Disease Treatment": "images/gumDiseaseTreatment_Service.jpg",
    "Wisdom Teeth Extraction": "images/wisdomToothExtraction_Service.jpg",
    "Dental Crowns & Bridges": "images/dentalCrowns_Service.jpg",
    "TMJ Treatment": "images/tmjTreatment_Service.jpg",
    "Dental Sealants": "images/dentalSealants_Service.jpg",
    "Fluoride Treatment": "images/fluorideTreatment_Service.jpg"
};

const container = document.querySelector('.services-container');

services.forEach(service => {
    const card = document.createElement('div');
    card.className = 'service-card';
    card.setAttribute('data-service', service);

    const imageSrc = imagePaths[service] || `https://via.placeholder.com/300x250?text=${encodeURIComponent(service)}`;

    card.innerHTML = `
    <img src="${imageSrc}" class="service-image" alt="${service}">
    <div class="overlay">
      <h3>${service}</h3>
    </div>
    <button class="confirm-btn" onclick="confirmService(this)">Select Service</button>
  `;

    container.appendChild(card);
});

function confirmService(button) {
    const card = button.closest('.service-card');
    const serviceName = card.getAttribute('data-service');

    // Store selected service in local storage
    localStorage.setItem('selectedService', serviceName);

    // Redirect to appointment page
    window.location.href = 'appointment.html';

    // NOTE: On appointment.html, retrieve with:
    // const service = localStorage.getItem('selectedService');
}
