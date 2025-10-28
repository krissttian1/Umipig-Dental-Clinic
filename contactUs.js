// Wait for the DOM to fully load
document.addEventListener("DOMContentLoaded", function () {
    const form = document.getElementById("contactForm");

    form.addEventListener("submit", function (e) {
        e.preventDefault();

        // Gather form data
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());

        console.log("Form submitted:", data);

        // 🔧 Edit here to handle sending the data to your backend, email system, or API
        // Example: sendFormDataToServer(data);
        // You can use fetch() or XMLHttpRequest to send it

        alert("Thank you for contacting us!");

        // Optionally reset the form
        form.reset();
    });
});
