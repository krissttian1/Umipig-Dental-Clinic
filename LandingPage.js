document.addEventListener('DOMContentLoaded', function () {
    const track = document.querySelector('.carousel-track');
    const slides = Array.from(track.children);
    const nextButton = document.querySelector('.carousel-button-right');
    const prevButton = document.querySelector('.carousel-button-left');

    let currentIndex = 0;
    let slidesPerView = 3;

    function updateSlidesPerView() {
        if (window.innerWidth < 768) {
            slidesPerView = 1;
        } else {
            slidesPerView = 3;
        }
        moveToSlide(currentIndex);
    }

    function moveToSlide(index) {
        const slideWidth = slides[0].getBoundingClientRect().width;

       
        if (index < 0) index = 0;
        if (index > slides.length - slidesPerView) {
            index = slides.length - slidesPerView;
        }

        currentIndex = index;
        track.style.transform = `translateX(-${index * slideWidth}px)`;
    }

    nextButton.addEventListener('click', () => {
        if (currentIndex < slides.length - slidesPerView) {
            moveToSlide(currentIndex + 1);
        } else {
            moveToSlide(0);
        }
    });

    prevButton.addEventListener('click', () => {
        if (currentIndex > 0) {
            moveToSlide(currentIndex - 1);
        } else {
            moveToSlide(slides.length - slidesPerView); 
        }
    });

   
    updateSlidesPerView();
    window.addEventListener('resize', updateSlidesPerView);

   
    let autoSlideInterval = setInterval(() => {
        if (currentIndex < slides.length - slidesPerView) {
            moveToSlide(currentIndex + 1);
        } else {
            moveToSlide(0);
        }
    }, 4000);

    
    const carouselContainer = document.querySelector('.carousel-container');
    carouselContainer.addEventListener('mouseenter', () => {
        clearInterval(autoSlideInterval);
    });

    carouselContainer.addEventListener('mouseleave', () => {
        autoSlideInterval = setInterval(() => {
            if (currentIndex < slides.length - slidesPerView) {
                moveToSlide(currentIndex + 1);
            } else {
                moveToSlide(0);
            }
        }, 4000);
    });


});


document.addEventListener("DOMContentLoaded", () => {
    const sections = document.querySelectorAll(".fade-section");

    const observer = new IntersectionObserver(
        entries => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add("visible");
                } else {
                    // Remove the class when it's out of view
                    entry.target.classList.remove("visible");
                }
            });
        },
        {
            threshold: 0.1 // Trigger when 10% of element is visible
        }
    );

    sections.forEach(section => {
        observer.observe(section);
    });
});





