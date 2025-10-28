// Switch between Account/Display tabs
const tabs = document.querySelectorAll(".category-tab");
const contentSections = document.querySelectorAll(".content-section");

tabs.forEach(tab => {
    tab.addEventListener("click", () => {
        // Remove active class from all tabs/sections
        tabs.forEach(t => t.classList.remove("active"));
        contentSections.forEach(s => s.classList.remove("active"));
        
        // Activate clicked tab and corresponding section
        tab.classList.add("active");
        const target = tab.getAttribute("data-target");
        document.getElementById(target).classList.add("active");
    });
});

// Admin/Dentist Toggle (Same as Before)
const adminBtn = document.getElementById("adminBtn");
const dentistBtn = document.getElementById("dentistBtn");
const adminForm = document.getElementById("adminForm");
const dentistForm = document.getElementById("dentistForm");

adminBtn.addEventListener("click", (e) => {
    e.preventDefault();
    adminBtn.classList.add("active");
    dentistBtn.classList.remove("active");
    adminForm.style.display = "block";
    dentistForm.style.display = "none";
});

dentistBtn.addEventListener("click", (e) => {
    e.preventDefault();
    dentistBtn.classList.add("active");
    adminBtn.classList.remove("active");
    dentistForm.style.display = "block";
    adminForm.style.display = "none";
});

// Dark Mode and Font Size (Same as Before)
const darkModeToggle = document.getElementById("darkModeToggle");
darkModeToggle.addEventListener("change", () => {
    document.body.setAttribute("data-theme", darkModeToggle.checked ? "dark" : "light");
    localStorage.setItem("darkMode", darkModeToggle.checked);
});

const fontSize = document.getElementById("fontSize");
const fontSizeValue = document.getElementById("fontSizeValue");
fontSize.addEventListener("input", () => {
    const size = fontSize.value + "px";
    document.body.style.fontSize = size;
    fontSizeValue.textContent = size;
    localStorage.setItem("fontSize", fontSize.value);
});

// Password Form (Same as Before)
document.getElementById("passwordForm").addEventListener("submit", (e) => {
    e.preventDefault();
    alert("Password updated!"); // Replace with backend logic
});

// Load saved settings
document.addEventListener("DOMContentLoaded", () => {
    // Dark Mode
    const savedDarkMode = localStorage.getItem("darkMode") === "true";
    darkModeToggle.checked = savedDarkMode;
    document.body.setAttribute("data-theme", savedDarkMode ? "dark" : "light");

    // Font Size
    const savedFontSize = localStorage.getItem("fontSize");
    if (savedFontSize) {
        fontSize.value = savedFontSize;
        document.body.style.fontSize = `${savedFontSize}px`;
        fontSizeValue.textContent = `${savedFontSize}px`;
    }
});