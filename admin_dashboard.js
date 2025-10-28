function loadContent(page) {
    const content = document.getElementById('main-content');
    content.innerHTML = "<p>Loading...</p>";

    const xhr = new XMLHttpRequest();
    xhr.open('GET', page, true);
    xhr.onload = function () {
        if (xhr.status === 200) {
            content.innerHTML = xhr.responseText;
        } else {
            content.innerHTML = "<p>Error loading page.</p>";
        }
    };
    xhr.onerror = function () {
        content.innerHTML = "<p>Network error.</p>";
    };
    xhr.send();
}

        // Calendar Generation
        document.addEventListener('DOMContentLoaded', function() {
            // Get current date
            const currentDate = new Date();
            const currentMonth = currentDate.getMonth();
            const currentYear = currentDate.getFullYear();
            const currentDay = currentDate.getDate();

            // Generate calendar
            generateCalendar(currentMonth, currentYear);

            // Calendar navigation
            document.querySelectorAll('.calendar-nav-btn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const isPrev = this.textContent === '❮';
                    const newDate = new Date(currentYear, isPrev ? currentMonth - 1 : currentMonth + 1, 1);
                    generateCalendar(newDate.getMonth(), newDate.getFullYear());
                });
            });

            function generateCalendar(month, year) {
                const calendarGrid = document.getElementById('calendar-days');
                calendarGrid.innerHTML = '';

                // Add day headers
                const dayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
                dayNames.forEach(day => {
                    const dayHeader = document.createElement('div');
                    dayHeader.className = 'calendar-day-header';
                    dayHeader.textContent = day;
                    calendarGrid.appendChild(dayHeader);
                });

                // Get first day of month and total days
                const firstDay = new Date(year, month, 1).getDay();
                const daysInMonth = new Date(year, month + 1, 0).getDate();
                const daysInPrevMonth = new Date(year, month, 0).getDate();

                // Add days from previous month
                for (let i = 0; i < firstDay; i++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day other-month';
                    dayElement.innerHTML = `<div class="day-number">${daysInPrevMonth - firstDay + i + 1}</div>`;
                    calendarGrid.appendChild(dayElement);
                }

                // Add days of current month
                for (let i = 1; i <= daysInMonth; i++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day';
                    if (i === currentDay && month === currentDate.getMonth() && year === currentDate.getFullYear()) {
                        dayElement.classList.add('current-day');
                    }
                    
                    dayElement.innerHTML = `
                        <div class="day-number">${i}</div>
                        <div class="day-events">
                            ${i % 4 === 0 ? '<div class="event-dot"></div>' : ''}
                            ${i % 5 === 0 ? '<div class="event-dot"></div>' : ''}
                        </div>
                    `;
                    
                    calendarGrid.appendChild(dayElement);
                }

                // Calculate remaining cells
                const totalCells = firstDay + daysInMonth;
                const remainingCells = totalCells % 7 === 0 ? 0 : 7 - (totalCells % 7);

                // Add days from next month
                for (let i = 1; i <= remainingCells; i++) {
                    const dayElement = document.createElement('div');
                    dayElement.className = 'calendar-day other-month';
                    dayElement.innerHTML = `<div class="day-number">${i}</div>`;
                    calendarGrid.appendChild(dayElement);
                }

                // Update calendar title
                const monthNames = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
                document.querySelector('.calendar-title').textContent = `${monthNames[month]} ${year}`;
            }
        });

  // ==================== AUTO-REFRESH FUNCTIONALITY ====================
  const refreshInterval = 10000; // 10 seconds
  let refreshTimer;
  const refreshIndicator = document.createElement('div');
  refreshIndicator.className = 'refresh-indicator';
  refreshIndicator.textContent = 'Refreshing appointments...';
  document.body.appendChild(refreshIndicator);

  function startAutoRefresh() {
    refreshTimer = setInterval(refreshAppointments, refreshInterval);
  }
  
  function stopAutoRefresh() {
    clearInterval(refreshTimer);
  }
  
  function refreshAppointments() {
    if (document.hidden) return;
    
    refreshIndicator.style.display = 'block';
    fetch('appointment_module.php')
      .then(response => response.text())
      .then(html => {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        const newTable = doc.querySelector('#appointmentList');
        if (newTable) {
          document.querySelector('#appointmentList').innerHTML = newTable.innerHTML;
          // Hide indicator after 2 seconds
          setTimeout(() => {
            refreshIndicator.style.display = 'none';
          }, 2000);
        }
      })
      .catch(error => {
        console.error('Error refreshing appointments:', error);
        refreshIndicator.style.display = 'none';
      });
  }

  // Start auto-refresh when page loads
  startAutoRefresh();
  
  // Pause auto-refresh when tab is not active
  document.addEventListener('visibilitychange', function() {
    if (document.hidden) {
      stopAutoRefresh();
    } else {
      startAutoRefresh();
      refreshAppointments(); // Refresh immediately when tab becomes active
    }
  });
  
  // Also refresh after form submissions
  document.querySelectorAll('form').forEach(form => {
    form.addEventListener('submit', function() {
      setTimeout(refreshAppointments, 1000); // Refresh 1 second after form submission
    });
  });
