document.addEventListener('DOMContentLoaded', function() {
    // DOM Elements
    const elements = {
        monthView: document.getElementById('month-view'),
        weekView: document.getElementById('week-view'),
        dayView: document.getElementById('day-view'),
        monthGrid: document.getElementById('month-grid'),
        weekHeader: document.getElementById('week-header'),
        weekTimeGrid: document.getElementById('week-time-grid'),
        dayAppointmentsColumn: document.getElementById('day-appointments-column'),
        currentMonthYear: document.getElementById('current-month-year'),
        dayViewTitle: document.getElementById('day-view-title'),
        prevBtn: document.getElementById('prev-btn'),
        nextBtn: document.getElementById('next-btn'),
        backBtn: document.getElementById('back-btn'),
        monthViewBtn: document.getElementById('month-view-btn'),
        weekViewBtn: document.getElementById('week-view-btn'),
        dayViewBtn: document.getElementById('day-view-btn'),
        loadingOverlay: document.getElementById('loading-overlay')
    };

    // Calendar State
    const state = {
        currentDate: new Date(),
        currentView: 'month',
        appointments: [],
    isLoading: false,
    filters: {
        pending: true,
        confirmed: true
    }
    };

    // Initialize
    setupEventListeners();
    loadInitialView();

    // Event Listeners
    function setupEventListeners() {
        elements.prevBtn.addEventListener('click', navigatePrevious);
        elements.nextBtn.addEventListener('click', navigateNext);
        elements.backBtn.addEventListener('click', goBack);
        
        elements.monthViewBtn.addEventListener('click', () => switchView('month'));
        elements.weekViewBtn.addEventListener('click', () => switchView('week'));
        elements.dayViewBtn.addEventListener('click', () => switchView('day'));
    }

    // Initial Load
    function loadInitialView() {
        updateMonthYearDisplay();
        renderCurrentView();
    }

    // FILTER
    function setupFilterListeners() {
    document.querySelectorAll('.status-filter').forEach(filter => {
        filter.addEventListener('click', function() {
            const status = this.classList.contains('pending') ? 'pending' : 'confirmed';
            state.filters[status] = !state.filters[status];
            this.classList.toggle('active');
            renderCurrentView();
        });
    });
}


    // View Switching
    function switchView(view) {
        state.currentView = view;
        
        // Update active button
        document.querySelectorAll('.view-btn').forEach(btn => btn.classList.remove('active'));
        elements[`${view}ViewBtn`].classList.add('active');
        
        // Update view visibility
        document.querySelectorAll('.calendar-view').forEach(view => view.classList.remove('active'));
        elements[`${view}View`].classList.add('active');
        
        // Fetch data for the new view
        fetchDataForCurrentView();
    }

    // Data Fetching
    async function fetchDataForCurrentView() {
        showLoading();
        
        try {
            let startDate, endDate;
            const date = state.currentDate;
            
            if (state.currentView === 'month') {
                startDate = new Date(date.getFullYear(), date.getMonth(), 1);
                endDate = new Date(date.getFullYear(), date.getMonth() + 1, 0);
            } 
            else if (state.currentView === 'week') {
                startDate = new Date(date);
                startDate.setDate(date.getDate() - date.getDay());
                endDate = new Date(startDate);
                endDate.setDate(startDate.getDate() + 6);
            } 
            else { // day view
                startDate = new Date(date);
                endDate = new Date(date);
            }
            
            const response = await fetch(`fetch_appointment.php?start=${formatDateForAPI(startDate)}&end=${formatDateForAPI(endDate)}`);
            const data = await response.json();
            state.appointments = processAppointments(data);
            renderCurrentView();
        } catch (error) {
            console.error('Error fetching appointments:', error);
            alert('Failed to load appointments. Please try again.');
        } finally {
            hideLoading();
        }
    }

    function processAppointments(appointments) {
        return appointments.map(appointment => {
            const date = new Date(appointment.date.replace(' ', 'T') + 'Z');
            const localDate = new Date(date.getTime() + date.getTimezoneOffset() * 60000);
            
            const [startHours, startMinutes] = appointment.start_time.split(':').map(Number);
            const [endHours, endMinutes] = appointment.end_time.split(':').map(Number);
            
            return {
                ...appointment,
                dateObj: localDate,
                dateKey: `${localDate.getFullYear()}-${localDate.getMonth()}-${localDate.getDate()}`,
                startHour: startHours,
                startMinutes: startMinutes,
                endHour: endHours,
                endMinutes: endMinutes,
                duration: appointment.duration
            };
        });
    }

    // Navigation
    function navigatePrevious() {
        if (state.currentView === 'month') {
            state.currentDate.setMonth(state.currentDate.getMonth() - 1);
        } 
        else if (state.currentView === 'week') {
            state.currentDate.setDate(state.currentDate.getDate() - 7);
        } 
        else { // day view
            state.currentDate.setDate(state.currentDate.getDate() - 1);
        }
        
        updateMonthYearDisplay();
        fetchDataForCurrentView();
    }

    function navigateNext() {
        if (state.currentView === 'month') {
            state.currentDate.setMonth(state.currentDate.getMonth() + 1);
        } 
        else if (state.currentView === 'week') {
            state.currentDate.setDate(state.currentDate.getDate() + 7);
        } 
        else { // day view
            state.currentDate.setDate(state.currentDate.getDate() + 1);
        }
        
        updateMonthYearDisplay();
        fetchDataForCurrentView();
    }

    function goBack() {
        if (state.currentView === 'day') {
            switchView('week');
        } 
        else if (state.currentView === 'week') {
            switchView('month');
        }
    }

    // Rendering
    function renderCurrentView() {
        if (state.currentView === 'month') {
            renderMonthView();
        } 
        else if (state.currentView === 'week') {
            renderWeekView();
        } 
        else { // day view
            renderDayView();
        }
    }

    function renderMonthView() {
        elements.monthGrid.innerHTML = '';
        
        // Add day headers
        const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        dayNames.forEach(day => {
            const dayHeader = document.createElement('div');
            dayHeader.className = 'day-header';
            dayHeader.textContent = day;
            elements.monthGrid.appendChild(dayHeader);
        });

        // Get first day of month and days in month
        const firstDay = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), 1).getDay();
        const daysInMonth = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth() + 1, 0).getDate();
        const today = new Date();

        // Add empty cells for days before first day of month
        for (let i = 0; i < firstDay; i++) {
            elements.monthGrid.appendChild(createEmptyDayElement());
        }

        // Add days of month
        for (let day = 1; day <= daysInMonth; day++) {
            const date = new Date(state.currentDate.getFullYear(), state.currentDate.getMonth(), day);
            const isToday = date.toDateString() === today.toDateString();
            elements.monthGrid.appendChild(createDayElement(day, date, isToday));
        }

        // Fill remaining cells
        const totalCells = firstDay + daysInMonth;
        const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);
        for (let i = 0; i < remainingCells; i++) {
            elements.monthGrid.appendChild(createEmptyDayElement());
        }
    }

    function createDayElement(day, date, isToday) {
        const dayElement = document.createElement('div');
        dayElement.className = 'month-day' + (isToday ? ' today' : '');
        
        // Date number
        const dateElement = document.createElement('div');
        dateElement.className = 'date';
        dateElement.textContent = day;
        dayElement.appendChild(dateElement);

        // Get appointments for this day
        const dateKey = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
        const dayAppointments = filterAppointments(
            state.appointments.filter(apt => apt.dateKey === dateKey)
        );

        // Add appointment dots if any
// Replace the dots creation with this:
if (dayAppointments.length > 0) {
    const dotsContainer = document.createElement('div');
    dotsContainer.className = 'appointment-dots';
    
    // Count statuses
    const statusCounts = {
        pending: dayAppointments.filter(a => a.status === 'pending').length,
        confirmed: dayAppointments.filter(a => a.status === 'confirmed').length
    };
    
    // Add dots (max 2 to prevent overflow)
    if (statusCounts.pending > 0) {
        const dot = document.createElement('div');
        dot.className = 'appointment-dot pending';
        dotsContainer.appendChild(dot);
    }
    if (statusCounts.confirmed > 0) {
        const dot = document.createElement('div');
        dot.className = 'appointment-dot confirmed';
        dotsContainer.appendChild(dot);
    }
    
    dayElement.appendChild(dotsContainer);
}

        // Click handler
        dayElement.addEventListener('click', () => {
            state.currentDate = date;
            switchView('week');
        });

        return dayElement;
    }

    function createEmptyDayElement() {
        const dayElement = document.createElement('div');
        dayElement.className = 'month-day empty';
        return dayElement;
    }

    function renderWeekView() {
        elements.weekHeader.innerHTML = '';
        elements.weekTimeGrid.innerHTML = '';

        // Create week days
        const weekDates = getWeekDates(state.currentDate);
        const today = new Date();

        // Create header
        weekDates.forEach(date => {
            const dayHeader = document.createElement('div');
            dayHeader.className = 'week-day-header' + 
                (date.toDateString() === today.toDateString() ? ' today' : '') +
                (date.toDateString() === state.currentDate.toDateString() ? ' selected-day' : '');
            
            const dayName = document.createElement('div');
            dayName.className = 'day-name';
            dayName.textContent = date.toLocaleDateString('en-US', { weekday: 'short' });
            
            const dayNumber = document.createElement('div');
            dayNumber.className = 'day-number';
            dayNumber.textContent = date.getDate();
            
            dayHeader.appendChild(dayName);
            dayHeader.appendChild(dayNumber);
            
            dayHeader.addEventListener('click', () => {
                state.currentDate = date;
                switchView('day');
            });
            
            elements.weekHeader.appendChild(dayHeader);
        });

        // Create time grid
        const timeGridContainer = document.createElement('div');
        timeGridContainer.className = 'week-time-grid-container';

        // Time column
        const timeColumn = document.createElement('div');
        timeColumn.className = 'time-list';
        for (let hour = 9; hour <= 18; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = formatHour(hour);
            timeColumn.appendChild(timeSlot);
        }
        timeGridContainer.appendChild(timeColumn);

        // Day columns
        const dayColumns = document.createElement('div');
        dayColumns.className = 'week-columns-container';

        weekDates.forEach(date => {
            const dayColumn = document.createElement('div');
            dayColumn.className = 'week-day-column' + 
                (date.toDateString() === state.currentDate.toDateString() ? ' selected-day' : '');
            
            // Get appointments for this day
            const dateKey = `${date.getFullYear()}-${date.getMonth()}-${date.getDate()}`;
            const dayAppointments = filterAppointments(
                state.appointments.filter(apt => apt.dateKey === dateKey)
            );
            
            // Create appointments blocks
            const blocksContainer = document.createElement('div');
            blocksContainer.className = 'day-appointments-blocks';
            
            dayAppointments.forEach(appointment => {
                const appointmentElement = createAppointmentElement(appointment);
                
                // Calculate position
                const top = (appointment.startHour - 9) * 60 + appointment.startMinutes;
                appointmentElement.style.top = `${top}px`;
                appointmentElement.style.height = `${appointment.duration}px`;
                
                blocksContainer.appendChild(appointmentElement);
            });
            
            dayColumn.appendChild(blocksContainer);
            dayColumns.appendChild(dayColumn);
        });

        timeGridContainer.appendChild(dayColumns);
        elements.weekTimeGrid.appendChild(timeGridContainer);
    }

    function renderDayView() {
        elements.dayViewTitle.textContent = state.currentDate.toLocaleDateString('en-US', {
            weekday: 'long',
            month: 'long',
            day: 'numeric',
            year: 'numeric'
        });

        elements.dayAppointmentsColumn.innerHTML = '';

        // Time column
        const timeColumn = document.createElement('div');
        timeColumn.className = 'time-column';
        for (let hour = 9; hour <= 18; hour++) {
            const timeSlot = document.createElement('div');
            timeSlot.className = 'time-slot';
            timeSlot.textContent = formatHour(hour);
            timeColumn.appendChild(timeSlot);
        }

        // Appointments column
        const appointmentsColumn = document.createElement('div');
        appointmentsColumn.className = 'appointments-column';

        // Get appointments for this day
        const dateKey = `${state.currentDate.getFullYear()}-${state.currentDate.getMonth()}-${state.currentDate.getDate()}`;
        const dayAppointments = filterAppointments(
            state.appointments.filter(apt => apt.dateKey === dateKey)
        );

        if (dayAppointments.length > 0) {
            dayAppointments.forEach(appointment => {
                const appointmentElement = createAppointmentElement(appointment);
                
                // Calculate position
                const top = (appointment.startHour - 9) * 60 + appointment.startMinutes;
                appointmentElement.style.top = `${top}px`;
                appointmentElement.style.height = `${appointment.duration}px`;
                
                appointmentsColumn.appendChild(appointmentElement);
            });
        } else {
            const noAppointments = document.createElement('div');
            noAppointments.className = 'no-appointments';
            noAppointments.textContent = 'No appointments scheduled for this day';
            appointmentsColumn.appendChild(noAppointments);
        }

        // Combine columns
        elements.dayAppointmentsColumn.appendChild(timeColumn);
        elements.dayAppointmentsColumn.appendChild(appointmentsColumn);
    }

    // Helper Functions

    function filterAppointments(appointments) {
    return appointments.filter(apt => {
        if (apt.status === 'pending') return state.filters.pending;
        if (apt.status === 'confirmed') return state.filters.confirmed;
        return true;
    });
}


    function getWeekDates(date) {
        const startDate = new Date(date);
        startDate.setDate(date.getDate() - date.getDay()); // Start from Sunday
        
        const weekDates = [];
        for (let i = 0; i < 7; i++) {
            const newDate = new Date(startDate);
            newDate.setDate(startDate.getDate() + i);
            weekDates.push(newDate);
        }
        
        return weekDates;
    }

    function formatHour(hour) {
        return hour > 12 ? `${hour - 12}pm` : hour === 12 ? '12pm' : `${hour}am`;
    }

    function createAppointmentElement(appointment) {
        const element = document.createElement('div');
        element.className = `appointment ${appointment.status}`;
        
        element.innerHTML = `
            <div class="patient-name">${appointment.patientName} (${appointment.age})</div>
            <div class="appointment-time">${formatTime(appointment.start_time)} - ${formatTime(appointment.end_time)}</div>
            <div class="dentist-service">${appointment.dentist} • ${appointment.service}</div>
            <div class="status-indicator ${appointment.status}"></div>
        `;
        
        return element;
    }

    function formatTime(timeStr) {
        const [hours, minutes] = timeStr.split(':');
        const hour = parseInt(hours, 10);
        return hour > 12 ? `${hour - 12}:${minutes} PM` : hour === 12 ? `12:${minutes} PM` : `${hour}:${minutes} AM`;
    }

    function formatDateForAPI(date) {
        return date.toISOString().split('T')[0];
    }

    function updateMonthYearDisplay() {
        elements.currentMonthYear.textContent = state.currentDate.toLocaleDateString('en-US', {
            month: 'long',
            year: 'numeric'
        });
    }

    function showLoading() {
        state.isLoading = true;
        elements.loadingOverlay.style.display = 'flex';
    }

    function hideLoading() {
        state.isLoading = false;
        elements.loadingOverlay.style.display = 'none';
    }
    
});


