<?php

include 'db_connection.php';
$user_id = $_SESSION['user_id'];
$query = mysqli_query($conn, "SELECT * FROM users WHERE id = '$user_id'");
$userData = mysqli_fetch_assoc($query);
?>


  
  <!DOCTYPE html>
  <html lang="en">
  <head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Patient Information Record</title>
  <link rel="stylesheet" href="patient_information_form.css" />
  </head>
  <body>

    <main class="page-wrapper" role="main">
      <section class="form-section" aria-label="Patient Information Record Form">
        <h1>Patient Information Record</h1>
        <form id="patientForm" action="Submit_Patient.php" method="POST">


          <label for="name">Full Name:</label>
  <input type="text" name="name" id="name" value="<?php echo htmlspecialchars($userData['fullname']); ?>" required readonly>
          
          <label for="age">Age</label>
          <input type="number" id="age" name="age" min="0" max="150" required placeholder="Age" readonly/>
          
          <label for="birthdate">Birthdate</label>
          <input type="date" id="birthdate" name="birthdate" required onchange="calculateAge()" required />
          
<label for="sex">Sex:</label>
<select name="sex" id="sex" required>
  <option value="Male" <?php if (isset($userData['sex']) && $userData['sex'] === 'Male') echo 'selected'; ?>>Male</option>
  <option value="Female" <?php if (isset($userData['sex']) && $userData['sex'] === 'Female') echo 'selected'; ?>>Female</option>
</select>


          
          <label for="address">Address</label>
          <textarea id="address" name="address" placeholder="Street, City, State, ZIP" required></textarea>
          
         <label for="contact">Contact Number:</label>
         <input type="text" name="contact" id="contact" value="<?php echo htmlspecialchars($userData['phone']); ?>" required readonly>
          
           <label for="email">Email:</label>
          <input type="email" name="email" id="email" value="<?php echo htmlspecialchars($userData['email']); ?>" required readonly>
          
          <label for="reason">What is your reason for the dental consultation?</label>
          <textarea id="reason" name="reason" placeholder="Describe your reason..." required></textarea>
          
          <div class="section-title">Medical History</div>
          
          <label>Are you in good health?</label>
          <div class="radio-group">
            <label><input type="radio" name="goodHealth" value="Yes" required /> Yes</label>
            <label><input type="radio" name="goodHealth" value="No" required /> No</label>
          </div>
          
          <label>Are you under medical treatment now? (if so, what illness or operation?)</label>
          <div class="radio-group">
            <label><input type="radio" name="underTreatment" value="Yes" required /> Yes</label>
            <label><input type="radio" name="underTreatment" value="No" required /> No</label>
          </div>
          <textarea id="treatmentDetails" name="treatmentDetails" placeholder="If yes, please specify illness or operation" style="display:none; margin-top:8px;"></textarea>
          
          <label>Are you taking any prescription/non-prescription medication?</label>
          <div class="radio-group">
            <label><input type="radio" name="medication" value="Yes" required /> Yes</label>
            <label><input type="radio" name="medication" value="No" required /> No</label>
          </div>
          <textarea id="medicationDetails" name="medicationDetails" placeholder="If yes, please specify medications" style="display:none; margin-top:8px;"></textarea>
          
          <label>Do you use tobacco products?</label>
          <div class="radio-group">
            <label><input type="radio" name="tobacco" value="Yes" required /> Yes</label>
            <label><input type="radio" name="tobacco" value="No" required /> No</label>
          </div>
          
          <label>Do you have an allergy? (state if you have allergy)</label>
          <div class="radio-group">
            <label><input type="radio" name="allergy" value="Yes" required /> Yes</label>
            <label><input type="radio" name="allergy" value="No" required /> No</label>
          </div>
          <textarea id="allergyDetails" name="allergyDetails" placeholder="If yes, please specify allergies" style="display:none; margin-top:8px;"></textarea>
          
          <!-- Dental-Specific Medical History -->

<label>Are you currently experiencing any tooth pain, gum issues, or mouth sores?</label>
<div class="radio-group">
  <label><input type="radio" name="oralIssues" value="Yes" required /> Yes</label>
  <label><input type="radio" name="oralIssues" value="No" required /> No</label>
</div>
<textarea id="oralIssuesDetails" name="oralIssuesDetails" placeholder="If yes, please describe your symptoms" style="display:none; margin-top:8px;"></textarea>

<label>Have you had any complications from previous dental treatments? (e.g., excessive bleeding, infection, allergic reactions)</label>
<div class="radio-group">
  <label><input type="radio" name="dentalComplications" value="Yes" required /> Yes</label>
  <label><input type="radio" name="dentalComplications" value="No" required /> No</label>
</div>
<textarea id="dentalComplicationsDetails" name="dentalComplicationsDetails" placeholder="If yes, please describe the complications" style="display:none; margin-top:8px;"></textarea>

<label>Do your gums bleed when you brush or floss?</label>
<div class="radio-group">
  <label><input type="radio" name="gumBleeding" value="Yes" required /> Yes</label>
  <label><input type="radio" name="gumBleeding" value="No" required /> No</label>
</div>

<label>Do you have sensitive teeth (to hot, cold, sweets, or pressure)?</label>
<div class="radio-group">
  <label><input type="radio" name="toothSensitivity" value="Yes" required /> Yes</label>
  <label><input type="radio" name="toothSensitivity" value="No" required /> No</label>
</div>

<label>Do you grind or clench your teeth?</label>
<div class="radio-group">
  <label><input type="radio" name="grindingClenching" value="Yes" required /> Yes</label>
  <label><input type="radio" name="grindingClenching" value="No" required /> No</label>
</div>

<label>Have you had any dental surgeries or procedures? (e.g., extraction, implants, gum surgery)</label>
<div class="radio-group">
  <label><input type="radio" name="dentalSurgery" value="Yes" required /> Yes</label>
  <label><input type="radio" name="dentalSurgery" value="No" required /> No</label>
</div>
<textarea id="dentalSurgeryDetails" name="dentalSurgeryDetails" placeholder="If yes, please specify the dental procedure(s)" style="display:none; margin-top:8px;"></textarea>

<label>Do you experience jaw problems? (e.g., pain, clicking, locking, difficulty opening mouth)</label>
<div class="radio-group">
  <label><input type="radio" name="jawProblems" value="Yes" required /> Yes</label>
  <label><input type="radio" name="jawProblems" value="No" required /> No</label>
</div>
<textarea id="jawProblemDetails" name="jawProblemDetails" placeholder="If yes, please describe your jaw symptoms" style="display:none; margin-top:8px;"></textarea>

          <div id="womenOnlySection" style="display:none;">
            <div class="section-title">For Women Only</div>
            
            <label>Are you pregnant?</label>
            <div class="radio-group">
              <label><input type="radio" name="pregnant" value="Yes" /> Yes</label>
              <label><input type="radio" name="pregnant" value="No" /> No</label>
            </div>
            
            <label>Are you taking birth control pills?</label>
            <div class="radio-group">
              <label><input type="radio" name="birthControl" value="Yes" /> Yes</label>
              <label><input type="radio" name="birthControl" value="No" /> No</label>
            </div>
</div>

<label class="terms-container" style="margin-top: 10px;">
  <input type="checkbox" id="agreeTerms" name="agreeTerms" required>
  I agree to the <a href="terms_and_conditions.php" target="_blank">Terms and Conditions</a>.
</label>

<button type="submit" class="submit-btn">Submit</button>
</form>
</section>


      <section class="info-container" aria-label="About the Medical History Section">
        <h3>About the Medical History Section</h3>
        <p>Your medical history helps us understand your overall health and any conditions or treatments that might affect your dental care. This includes chronic conditions like diabetes or heart disease, previous surgeries, recent illnesses, and any allergies you may have.</p>
        <p>Accurate disclosure helps us tailor your dental treatment safely and avoid potential complications.</p>
      </section>

      <section class="info-container" aria-label="Why We Need This Information">
        <h3>Why We Need This Information</h3>
        <ul>
          <li>To ensure dental treatments are safe and effective for your specific health conditions.</li>
          <li>To prevent potential adverse reactions due to allergies or medications.</li>
          <li>To identify any medical risks that may impact dental procedures.</li>
          <li>To provide personalized care tailored to your health needs and history.</li>
          <li>To maintain thorough documentation for your ongoing healthcare management.</li>
        </ul>
      </section>

      <section class="info-container" aria-label="Privacy and Confidentiality">
        <h3>Privacy &amp; Confidentiality</h3>
        <p>All information you provide is confidential and securely stored according to local privacy laws and regulations. We use your data solely to deliver safe and effective dental care. Your privacy and trust are paramount to us.</p>
        <p>We do not share your details with third parties without your explicit consent, except where required by law or for your treatment purposes.</p>
      </section>

      <section class="info-container" aria-label="Need Help">
        <h3>Need Help?</h3>
        <p>If you have any questions or concerns while filling out the form, please don't hesitate to <a href="contactUs.html">contact us</a>. Our friendly staff is here to assist you.</p>
        <p>During your visit, you can also ask our dental team for help in completing this form or clarifying any part of the process.</p>
        <p>Ensuring your comfort and understanding is important to us.</p>
      </section>
    </main>

 <script>

  function calculateAge() {
  const birthdate = new Date(document.getElementById('birthdate').value);
  const today = new Date();
  
  let age = today.getFullYear() - birthdate.getFullYear();
  const monthDiff = today.getMonth() - birthdate.getMonth();
  
  if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthdate.getDate())) {
    age--;
  }
  
  document.getElementById('age').value = age;
}

  document.addEventListener('DOMContentLoaded', function () {
    const sexRadios = document.getElementsByName('sex');
    const womenOnlySection = document.getElementById('womenOnlySection');

    function toggleWomenOnly() {
      const selectedSex = Array.from(sexRadios).find(r => r.checked)?.value;
      if (selectedSex === 'Female') {
        womenOnlySection.style.display = 'block';
        womenOnlySection.querySelectorAll('input[type=radio]').forEach(input => {
          input.required = true;
        });
      } else {
        womenOnlySection.style.display = 'none';
        womenOnlySection.querySelectorAll('input[type=radio]').forEach(input => {
          input.required = false;
          input.checked = false;
        });
      }
    }

    sexRadios.forEach(radio => radio.addEventListener('change', toggleWomenOnly));
    toggleWomenOnly();

    const underTreatmentRadios = document.getElementsByName('underTreatment');
    const treatmentDetails = document.getElementById('treatmentDetails');
    underTreatmentRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          treatmentDetails.style.display = 'block';
          treatmentDetails.required = true;
        } else {
          treatmentDetails.style.display = 'none';
          treatmentDetails.value = '';
          treatmentDetails.required = false;
        }
      });
    });

    const medicationRadios = document.getElementsByName('medication');
    const medicationDetails = document.getElementById('medicationDetails');
    medicationRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          medicationDetails.style.display = 'block';
          medicationDetails.required = true;
        } else {
          medicationDetails.style.display = 'none';
          medicationDetails.value = '';
          medicationDetails.required = false;
        }
      });
    });

    const allergyRadios = document.getElementsByName('allergy');
    const allergyDetails = document.getElementById('allergyDetails');
    allergyRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          allergyDetails.style.display = 'block';
          allergyDetails.required = true;
        } else {
          allergyDetails.style.display = 'none';
          allergyDetails.value = '';
          allergyDetails.required = false;
        }
      });
    });

    const oralIssuesRadios = document.getElementsByName('oralIssues');
    const oralIssuesDetails = document.getElementById('oralIssuesDetails');
    oralIssuesRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          oralIssuesDetails.style.display = 'block';
          oralIssuesDetails.required = true;
        } else {
          oralIssuesDetails.style.display = 'none';
          oralIssuesDetails.value = '';
          oralIssuesDetails.required = false;
        }
      });
    });

    const dentalComplicationsRadios = document.getElementsByName('dentalComplications');
    const dentalComplicationsDetails = document.getElementById('dentalComplicationsDetails');
    dentalComplicationsRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          dentalComplicationsDetails.style.display = 'block';
          dentalComplicationsDetails.required = true;
        } else {
          dentalComplicationsDetails.style.display = 'none';
          dentalComplicationsDetails.value = '';
          dentalComplicationsDetails.required = false;
        }
      });
    });

    const dentalSurgeryRadios = document.getElementsByName('dentalSurgery');
    const dentalSurgeryDetails = document.getElementById('dentalSurgeryDetails');
    dentalSurgeryRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          dentalSurgeryDetails.style.display = 'block';
          dentalSurgeryDetails.required = true;
        } else {
          dentalSurgeryDetails.style.display = 'none';
          dentalSurgeryDetails.value = '';
          dentalSurgeryDetails.required = false;
        }
      });
    });

    const jawProblemsRadios = document.getElementsByName('jawProblems');
    const jawProblemDetails = document.getElementById('jawProblemDetails');
    jawProblemsRadios.forEach(radio => {
      radio.addEventListener('change', () => {
        if (radio.value === 'Yes' && radio.checked) {
          jawProblemDetails.style.display = 'block';
          jawProblemDetails.required = true;
        } else {
          jawProblemDetails.style.display = 'none';
          jawProblemDetails.value = '';
          jawProblemDetails.required = false;
        }
      });
    });

    const form = document.getElementById('patientForm');
    form.addEventListener('submit', (event) => {
      if (!form.checkValidity()) {
        event.preventDefault(); // Prevent submission only if invalid
        form.reportValidity();
      } else {
        alert('Form submitted! Thank you for providing your information.');
        // Let form submit normally to PHP
      }
    });
  });
</script>
  </body>
  </html>
