<?php
// chatbot.php - Reusable Chatbot Component for Dental Clinic
// Get patient name from session
$patient_name = isset($_SESSION['username']) ? $_SESSION['username'] : 'there';
?>

<style>
    /* Chatbot Styles - Keep all your existing CSS styles */
    .chatbot-container {
        position: fixed;
        bottom: 20px;
        right: 20px;
        z-index: 1000;
        font-family: 'Poppins', sans-serif;
    }
    
    .chatbot-icon {
        width: 60px;
        height: 60px;
        background: linear-gradient(135deg, #2c7fb8, #1d5a8a);
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        transition: all 0.3s ease;
        animation: pulse 2s infinite;
    }
    
    @keyframes pulse {
        0% { transform: scale(1); }
        50% { transform: scale(1.05); }
        100% { transform: scale(1); }
    }
    
    .chatbot-icon:hover {
        transform: scale(1.1);
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
    }
    
    .chatbot-icon i {
        color: white;
        font-size: 24px;
    }
    
    .chatbot-window {
        position: absolute;
        bottom: 70px;
        right: 0;
        width: 350px;
        height: 450px;
        background: white;
        border-radius: 15px;
        box-shadow: 0 5px 25px rgba(0, 0, 0, 0.15);
        display: none;
        flex-direction: column;
        overflow: hidden;
        transition: all 0.3s ease;
        border: 1px solid #e0e0e0;
    }
    
    .chatbot-header {
        background: linear-gradient(135deg, #2c7fb8, #1d5a8a);
        color: white;
        padding: 15px;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .chatbot-header h3 {
        margin: 0;
        font-size: 18px;
        font-weight: 600;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .chatbot-header h3 i {
        font-size: 16px;
    }
    
    .close-chatbot {
        background: none;
        border: none;
        color: white;
        font-size: 18px;
        cursor: pointer;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.3s;
    }
    
    .close-chatbot:hover {
        background: rgba(255, 255, 255, 0.2);
    }
    
    .chatbot-messages {
        flex: 1;
        padding: 15px;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 10px;
        background: #f8f9fa;
    }
    
    .message {
        max-width: 80%;
        padding: 10px 15px;
        border-radius: 18px;
        font-size: 14px;
        line-height: 1.4;
        word-wrap: break-word;
        animation: fadeIn 0.3s ease;
    }
    
    @keyframes fadeIn {
        from { opacity: 0; transform: translateY(10px); }
        to { opacity: 1; transform: translateY(0); }
    }
    
    .bot-message {
        align-self: flex-start;
        background: white;
        color: #333;
        border-bottom-left-radius: 5px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        border: 1px solid #e9ecef;
    }
    
    .user-message {
        align-self: flex-end;
        background: #2c7fb8;
        color: white;
        border-bottom-right-radius: 5px;
        box-shadow: 0 2px 5px rgba(44, 127, 184, 0.3);
    }
    
    .chatbot-input {
        display: flex;
        padding: 15px;
        border-top: 1px solid #e9ecef;
        background: white;
    }
    
    .chatbot-input input {
        flex: 1;
        padding: 10px 15px;
        border: 1px solid #ddd;
        border-radius: 25px;
        outline: none;
        font-family: 'Poppins', sans-serif;
        font-size: 14px;
        transition: border 0.3s;
    }
    
    .chatbot-input input:focus {
        border-color: #2c7fb8;
    }
    
    .chatbot-input button {
        background: #2c7fb8;
        color: white;
        border: none;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        margin-left: 10px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s;
    }
    
    .chatbot-input button:hover {
        background: #1d5a8a;
        transform: scale(1.05);
    }
    
    .suggested-questions {
        display: flex;
        flex-direction: column;
        gap: 8px;
        margin-top: 10px;
    }
    
    .suggested-question {
        background: white;
        border: 1px solid #e9ecef;
        border-radius: 20px;
        padding: 8px 15px;
        font-size: 13px;
        cursor: pointer;
        transition: all 0.2s;
        text-align: left;
        color: #2c7fb8;
    }
    
    .suggested-question:hover {
        background: #e7f3ff;
        border-color: #2c7fb8;
        transform: translateX(5px);
    }
    
    .typing-indicator {
        display: flex;
        align-items: center;
        gap: 5px;
        padding: 10px 15px;
        background: white;
        border-radius: 18px;
        border-bottom-left-radius: 5px;
        align-self: flex-start;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
    }
    
    .typing-dot {
        width: 8px;
        height: 8px;
        background: #666;
        border-radius: 50%;
        animation: typing 1.4s infinite ease-in-out;
    }
    
    .typing-dot:nth-child(1) { animation-delay: -0.32s; }
    .typing-dot:nth-child(2) { animation-delay: -0.16s; }
    
    @keyframes typing {
        0%, 80%, 100% { transform: scale(0.8); opacity: 0.5; }
        40% { transform: scale(1); opacity: 1; }
    }
    
    /* Responsive adjustments */
    @media (max-width: 480px) {
        .chatbot-window {
            width: 300px;
            height: 400px;
            right: 10px;
        }
        
        .chatbot-icon {
            width: 50px;
            height: 50px;
        }
        
        .chatbot-icon i {
            font-size: 20px;
        }
    }
</style>

<div class="chatbot-container">
    <div class="chatbot-icon" id="chatbotIcon">
        <i class="fas fa-robot"></i>
    </div>
    <div class="chatbot-window" id="chatbotWindow">
        <div class="chatbot-header">
            <h3><i class="fas fa-tooth"></i> Basic Chatbot</h3>
            <button class="close-chatbot" id="closeChatbot">
                <i class="fas fa-times"></i>
            </button>
        </div>
        <div class="chatbot-messages" id="chatbotMessages">
            <div class="message bot-message">
                Hello <?php echo htmlspecialchars($patient_name); ?>! I'm your dental assistant. How can I help you with your dental care today?
            </div>
            <div class="suggested-questions">
                <div class="suggested-question" data-question="What are your opening hours?">
                    <i class="fas fa-clock"></i> What are your opening hours?
                </div>
                <div class="suggested-question" data-question="How do I book an appointment?">
                    <i class="fas fa-calendar-check"></i> How do I book an appointment?
                </div>
                <div class="suggested-question" data-question="Do you accept insurance?">
                    <i class="fas fa-file-invoice"></i> Do you accept insurance?
                </div>
            </div>
        </div>
        <div class="chatbot-input">
            <input type="text" id="chatbotInput" placeholder="Type here...">
            <button id="sendMessage">
                <i class="fas fa-arrow-right"></i>
            </button>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Chatbot elements
    const chatbotIcon = document.getElementById('chatbotIcon');
    const chatbotWindow = document.getElementById('chatbotWindow');
    const closeChatbot = document.getElementById('closeChatbot');
    const chatbotMessages = document.getElementById('chatbotMessages');
    const chatbotInput = document.getElementById('chatbotInput');
    const sendMessage = document.getElementById('sendMessage');
    const suggestedQuestions = document.querySelectorAll('.suggested-question');
    
    // Get patient name from PHP
    const patientName = "<?php echo addslashes($patient_name); ?>";
    
    // FAQ responses database - Updated greetings to include patient name
    const faqResponses = {
        // Greetings - Now personalized
        "hello": `Hello ${patientName}! Welcome to Umipig Dental Clinic. How can I assist you with your dental care today?`,
        "hi": `Hi ${patientName}! I'm here to help with any questions about our dental services.`,
        "hey": `Hey ${patientName}! How can I help you today?`,
        "good morning": `Good morning ${patientName}! How can I assist with your dental needs today?`,
        "good afternoon": `Good afternoon ${patientName}! What can I help you with regarding your dental care?`,
        "good evening": `Good evening ${patientName}! How can I assist you with our dental services?`,
        
        // Operating hours
        "hours": "Our clinic is open:\n\n🕘 Monday to Friday: 9:00 AM - 6:00 PM\n🕘 Saturday: 9:00 AM - 4:00 PM\n🚫 Sunday: Closed\n\nWe're here to serve your dental needs!",
        "opening hours": "We're open Monday to Friday from 9:00 AM to 6:00 PM, and Saturdays from 9:00 AM to 4:00 PM. We're closed on Sundays.",
        "open": "Yes, we're open! Our hours are Monday-Friday 9AM-6PM and Saturday 9AM-4PM.",
        "closed": "We're closed on Sundays. Our regular hours are Monday-Friday 9AM-6PM and Saturday 9AM-4PM.",
        
        // Appointments
        "appointment": `You can book an appointment in several ways ${patientName}:\n\n📞 Call us at: 0999-1112222\n💻 Use our online booking system\n📍 Visit us at the clinic\n\nWould you like to schedule an appointment?`,
        "book": `To book an appointment ${patientName}, please call us at 0999-1112222 or use the appointment booking feature on our website. We'll help you find a convenient time!`,
        "schedule": `Scheduling is easy ${patientName}! Call us at 0999-1112222 or book online through our website.`,
        "availability": `We have flexible scheduling options ${patientName}. Please call us at 0999-1112222 to check current availability.`,
        
        // Insurance and payments
        "insurance": "Yes, we accept most major dental insurance plans including:\n\n• PhilHealth\n• Maxicare\n• Intellicare\n• And many more!\n\nPlease contact us with your insurance details for verification.",
        "payment": "We accept various payment methods:\n\n💵 Cash\n💳 Credit/Debit Cards\n🏥 Dental Insurance\n📅 Payment Plans (for major procedures)\n\nWe can discuss payment options during your consultation.",
        "cost": `The cost varies depending on the procedure ${patientName}. We offer:\n\n• Free initial consultation\n• Transparent pricing\n• Payment plans available\n\nCall us for a detailed estimate!`,
        "price": `Treatment costs depend on the specific procedure ${patientName}. We provide detailed cost estimates during consultations after assessing your dental needs.`,
        
        // Emergency
        "emergency": `For dental emergencies ${patientName}:\n\n🚨 During office hours: Call us immediately at 0999-1112222\n🚨 After hours: Leave a message and we'll contact you ASAP\n\nWe prioritize emergency cases!`,
        "pain": `I'm sorry you're experiencing pain ${patientName}! For dental emergencies, please call us immediately at 0999-1112222. We'll help you get relief quickly.`,
        "urgent": `For urgent dental needs ${patientName}, call us at 0999-1112222. We accommodate emergency cases as a priority.`,
        
        // Services
        "services": "We offer comprehensive dental services:\n\n🦷 General Dentistry (cleanings, fillings)\n🎯 Orthodontics (braces, aligners)\n🔪 Oral Surgery (extractions, implants)\n💎 Cosmetic Dentistry (whitening, veneers)\n\nWhich service are you interested in?",
        "braces": `We offer various orthodontic treatments ${patientName}:\n\n• Traditional metal braces\n• Clear ceramic braces\n• Invisalign clear aligners\n• Retainers\n\nSchedule a consultation to find the best option for you!`,
        "cleaning": `We recommend dental cleanings every 6 months for optimal oral health ${patientName}. Our cleanings include:\n\n• Plaque and tartar removal\n• Teeth polishing\n• Oral hygiene instructions\n• Fluoride treatment (if needed)`,
        "whitening": `We offer professional teeth whitening that's:\n\n✅ Safe and effective\n✅ Supervised by dentists\n✅ Visible results in one session\n✅ Customized treatment plans`,
        "root canal": `Our root canal treatments are performed with modern techniques to ensure comfort and effectiveness ${patientName}. We use local anesthesia to minimize any discomfort.`,
        
        // Location and contact
        "address": "We're located at:\n\n📍 2nd Floor, Village Eats Food Park, Bldg., #9\nVillage East Executive Homes\nCainta, Philippines 1900\n\nEasy to find with ample parking!",
        "location": "Our clinic is at 2nd Floor, Village Eats Food Park, Bldg., #9, Village East Executive Homes, Cainta, Philippines.",
        "contact": "You can reach us through:\n\n📞 Phone: 0999-1112222\n📧 Email: Umipigdentalclinic@gmail.com\n💻 Facebook: Umipig Dental Clinic Cainta\n📍 Visit: 2nd Floor, Village Eats Food Park, Cainta",
        "phone": "Our main contact number is 0999-1112222. You can call us during business hours for appointments or inquiries.",
        
        // General information
        "dentist": "Our experienced dental team includes:\n\n• General Dentists\n• Orthodontists\n• Oral Surgeons\n• Cosmetic Dentists\n\nAll dedicated to providing exceptional care!",
        "experience": "Our dentists have years of experience and continuous training in the latest dental techniques and technologies.",
        "x-ray": "We use digital X-rays which are:\n\n✅ Safer with less radiation\n✅ Instant results\n✅ Better for diagnosis\n✅ Environmentally friendly",
        
        // Closing - Personalized
        "thanks": `You're very welcome ${patientName}! 😊 Is there anything else I can help you with about your dental care?`,
        "thank you": `You're welcome ${patientName}! Feel free to ask if you have any other questions about our services.`,
        "bye": `Goodbye ${patientName}! Remember to brush twice daily and floss regularly. We're here if you need us!`,
        "goodbye": `Take care of your smile ${patientName}! 🦷 We look forward to seeing you at the clinic.`
    };
    
    // Default response for unrecognized questions
    const defaultResponse = `I'm sorry ${patientName}, I don't have specific information about that. For detailed inquiries, please:\n\n📞 Call us: 0999-1112222\n📧 Email: Umipigdentalclinic@gmail.com\n💻 Message us on Facebook\n\nOur team will be happy to assist you!`;
    
    // Toggle chatbot window
    chatbotIcon.addEventListener('click', function() {
        chatbotWindow.style.display = 'flex';
        chatbotInput.focus();
    });
    
    closeChatbot.addEventListener('click', function() {
        chatbotWindow.style.display = 'none';
    });
    
    // Close chatbot when clicking outside (optional)
    document.addEventListener('click', function(event) {
        if (!chatbotWindow.contains(event.target) && !chatbotIcon.contains(event.target) && chatbotWindow.style.display === 'flex') {
            chatbotWindow.style.display = 'none';
        }
    });
    
    // Send message function
    function sendUserMessage() {
        const userMessage = chatbotInput.value.trim();
        if (userMessage === '') return;
        
        // Add user message to chat
        addMessage(userMessage, 'user');
        
        // Clear input
        chatbotInput.value = '';
        
        // Show typing indicator
        showTypingIndicator();
        
        // Process and respond after a short delay
        setTimeout(() => {
            removeTypingIndicator();
            const response = getBotResponse(userMessage);
            addMessage(response, 'bot');
        }, 1500);
    }
    
    // Send message on button click
    sendMessage.addEventListener('click', sendUserMessage);
    
    // Send message on Enter key
    chatbotInput.addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            sendUserMessage();
        }
    });
    
    // Suggested questions
    suggestedQuestions.forEach(question => {
        question.addEventListener('click', function() {
            const questionText = this.getAttribute('data-question');
            chatbotInput.value = questionText;
            sendUserMessage();
        });
    });
    
    // Add message to chat
    function addMessage(text, sender) {
        const messageDiv = document.createElement('div');
        messageDiv.classList.add('message');
        messageDiv.classList.add(sender === 'user' ? 'user-message' : 'bot-message');
        
        // Replace newlines with line breaks for bot messages
        if (sender === 'bot') {
            messageDiv.innerHTML = text.replace(/\n/g, '<br>');
        } else {
            messageDiv.textContent = text;
        }
        
        chatbotMessages.appendChild(messageDiv);
        
        // Scroll to bottom
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
    
    // Show typing indicator
    function showTypingIndicator() {
        const typingDiv = document.createElement('div');
        typingDiv.classList.add('typing-indicator');
        typingDiv.id = 'typingIndicator';
        typingDiv.innerHTML = `
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
            <div class="typing-dot"></div>
        `;
        chatbotMessages.appendChild(typingDiv);
        chatbotMessages.scrollTop = chatbotMessages.scrollHeight;
    }
    
    // Remove typing indicator
    function removeTypingIndicator() {
        const typingIndicator = document.getElementById('typingIndicator');
        if (typingIndicator) {
            typingIndicator.remove();
        }
    }
    
    // Get bot response based on user input
    function getBotResponse(userInput) {
        userInput = userInput.toLowerCase();
        
        // Check for exact matches first
        for (const [keyword, response] of Object.entries(faqResponses)) {
            if (userInput === keyword.toLowerCase()) {
                return response;
            }
        }
        
        // Check for keyword matches
        for (const [keyword, response] of Object.entries(faqResponses)) {
            if (userInput.includes(keyword.toLowerCase())) {
                return response;
            }
        }
        
        // Check for similar phrases
        const similarPhrases = {
            "when are you open": "hours",
            "what time do you open": "hours",
            "make appointment": "appointment",
            "set appointment": "appointment",
            "how much": "cost",
            "pricing": "cost",
            "fees": "cost",
            "where are you located": "address",
            "how to find you": "address",
            "what do you do": "services",
            "treatments": "services",
            "procedures": "services",
            "toothache": "emergency",
            "broken tooth": "emergency",
            "dentists": "dentist",
            "doctors": "dentist"
        };
        
        for (const [phrase, responseKey] of Object.entries(similarPhrases)) {
            if (userInput.includes(phrase)) {
                return faqResponses[responseKey];
            }
        }
        
        return defaultResponse;
    }
    
    // Auto-open chatbot after 30 seconds if not interacted with
    setTimeout(() => {
        if (chatbotWindow.style.display !== 'flex' && !sessionStorage.getItem('chatbotInteracted')) {
            chatbotWindow.style.display = 'flex';
            sessionStorage.setItem('chatbotInteracted', 'true');
        }
    }, 30000);
});
</script>