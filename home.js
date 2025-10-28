// ======================
// MODAL FUNCTIONALITY
// ======================
function openPatientInfoModal() {
    document.getElementById('patientInfoModal').style.display = 'block';
}

function closePatientInfoModal() {
    document.getElementById('patientInfoModal').style.display = 'none';
}

// Close modal when clicking outside
window.addEventListener('click', function(event) {
    const modal = document.getElementById('patientInfoModal');
    if (event.target === modal) {
        modal.style.display = "none";
    }
});

// ======================
// CAROUSEL IMPLEMENTATION
// ======================
function initializeCarousel() {
    const track = document.querySelector('.carousel-track');
    if (!track) return;

    const slides = Array.from(track.children);
    const nextButton = document.querySelector('.carousel-button-right');
    const prevButton = document.querySelector('.carousel-button-left');
    const carouselContainer = document.querySelector('.carousel-container');

    let currentIndex = 0;
    let slidesPerView = window.innerWidth < 768 ? 1 : 3;
    let autoSlideInterval;

    function updateSlidesPerView() {
        slidesPerView = window.innerWidth < 768 ? 1 : 3;
        moveToSlide(currentIndex);
    }

    function moveToSlide(index) {
        const slideWidth = slides[0].getBoundingClientRect().width;
        
        // Boundary checks
        index = Math.max(0, Math.min(index, slides.length - slidesPerView));
        
        currentIndex = index;
        track.style.transform = `translateX(-${index * slideWidth}px)`;
    }

    function startAutoSlide() {
        autoSlideInterval = setInterval(() => {
            const nextIndex = currentIndex < slides.length - slidesPerView ? currentIndex + 1 : 0;
            moveToSlide(nextIndex);
        }, 4000);
    }

    // Event listeners
    nextButton.addEventListener('click', () => {
        const nextIndex = currentIndex < slides.length - slidesPerView ? currentIndex + 1 : 0;
        moveToSlide(nextIndex);
    });

    prevButton.addEventListener('click', () => {
        const prevIndex = currentIndex > 0 ? currentIndex - 1 : slides.length - slidesPerView;
        moveToSlide(prevIndex);
    });

    carouselContainer.addEventListener('mouseenter', () => clearInterval(autoSlideInterval));
    carouselContainer.addEventListener('mouseleave', startAutoSlide);

    // Initialize
    updateSlidesPerView();
    window.addEventListener('resize', updateSlidesPerView);
    startAutoSlide();
}

// ======================
// PAGE EFFECTS
// ======================
function initializeSmoothScrolling() {
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function(e) {
            e.preventDefault();
            const targetElement = document.querySelector(this.getAttribute('href'));
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
}

function initializeLazyLoading() {
    if ('loading' in HTMLImageElement.prototype) {
        document.querySelectorAll('img[loading="lazy"]').forEach(img => {
            img.src = img.dataset.src;
        });
    }
    // Note: Add polyfill here if needed for older browsers
}

function initializeFadeSections() {
    const sections = document.querySelectorAll('.fade-section');
    if (sections.length === 0) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            entry.target.classList.toggle('visible', entry.isIntersecting);
        });
    }, {
        threshold: 0.1
    });

    sections.forEach(section => observer.observe(section));
}

// ======================
// FORM HELPERS
// ======================
function initializePhoneValidation() {
    const phoneInput = document.getElementById('phone');
    if (phoneInput) {
        phoneInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9+()-]/g, '');
        });
    }
}

// ======================
// MAIN INITIALIZATION
// ======================
document.addEventListener('DOMContentLoaded', function() {
    initializeCarousel();
    initializeSmoothScrolling();
    initializeLazyLoading();
    initializeFadeSections();
    initializePhoneValidation();
});