document.addEventListener('DOMContentLoaded', function () {
  // Cancel modal and buttons
  const cancelBtn = document.getElementById('cancelAppointmentBtn');
  const cancelModal = document.getElementById('cancelModal');
  const cancelSelect = document.getElementById('cancelSelectAppointment');
  const cancelReason = document.getElementById('cancelReason');
  const confirmCancelBtn = document.getElementById('confirmCancelBtn');
  const closeCancelModalBtn = document.getElementById('closeCancelModalBtn');

  // Open Cancel Modal
  if (cancelBtn && cancelModal) {
    cancelBtn.addEventListener('click', () => {
      cancelModal.style.display = 'block';
      cancelSelect.innerHTML = '<option disabled selected>Loading...</option>';

      fetch('fetch_dentist_appointment.php')
        .then(response => response.json())
        .then(data => {
          cancelSelect.innerHTML = '';

          if (data.length === 0) {
            const option = document.createElement('option');
            option.disabled = true;
            option.textContent = 'No appointments found';
            cancelSelect.appendChild(option);
            return;
          }

          data.forEach(app => {
            const timeFormatted = new Date(`1970-01-01T${app.appointment_time}`).toLocaleTimeString([], {
              hour: '2-digit',
              minute: '2-digit',
              hour12: true
            });

            const option = document.createElement('option');
            option.value = app.appointment_id;
            option.textContent = `${app.patient_name} on ${app.appointment_date} at ${timeFormatted}`;
            cancelSelect.appendChild(option);
          });
        })
        .catch(error => {
          console.error('Error loading appointments:', error);
          cancelSelect.innerHTML = '<option disabled>Error loading appointments</option>';
        });
    });
  }

  // Close Cancel Modal
  if (closeCancelModalBtn && cancelModal) {
    closeCancelModalBtn.addEventListener('click', () => {
      cancelModal.style.display = 'none';
    });
  }

  // Submit cancellation
  if (confirmCancelBtn) {
    confirmCancelBtn.addEventListener('click', () => {
      const appointmentId = cancelSelect.value;
      const reason = cancelReason.value.trim();

      if (!appointmentId || !reason) {
        alert('❌ Please select an appointment and provide a reason for cancellation.');
        return;
      }

      const formData = new FormData();
      formData.append('form_type', 'cancel');
      formData.append('appointment_id', appointmentId);
      formData.append('cancel_reason', reason);

      fetch('dentist_appointment_module.php', {
        method: 'POST',
        body: formData
      })
        .then(response => response.json())
        .then(result => {
          alert(result.message || 'Cancelled successfully.');
          cancelModal.style.display = 'none';
          cancelSelect.value = '';
          cancelReason.value = '';
          location.reload(); // Refresh to update table
        })
        .catch(error => {
          console.error('Cancel failed:', error);
          alert('❌ Error cancelling appointment.');
        });
    });
  }


  function loadCompletedAppointments() {
  const date = document.getElementById('filterDate').value;

  fetch('fetch_completed_appointments.php?date=' + date)
    .then(res => res.json())
    .then(data => {
      // clear table and update rows
    })
    .catch(error => console.error('Error fetching:', error));
}


        // Reschedule Modal Elements
        const rescheduleModal = document.getElementById('rescheduleModal');
        const rescheduleBtn = document.getElementById('rescheduleAppointmentBtn');
        const cancelRescheduleBtn = document.getElementById('cancelRescheduleBtn');
        const saveRescheduleBtn = document.getElementById('saveRescheduleBtn');
        const selectAppointment = document.getElementById('selectAppointment');
        const rescheduleDentist = document.getElementById('rescheduleDentist');
        const rescheduleDate = document.getElementById('rescheduleDate');
        const rescheduleTime = document.getElementById('rescheduleTime');
        const rescheduleCustomTime = document.getElementById('rescheduleCustomTime');
        const enableRescheduleCustomTimeBtn = document.getElementById('enableRescheduleCustomTimeBtn');
        const conflictWarning = document.getElementById('conflictWarning');

        // Open modal
        rescheduleBtn.addEventListener('click', function() {
            rescheduleModal.style.display = 'block';
        });

        // Close modal
        cancelRescheduleBtn.addEventListener('click', function() {
            rescheduleModal.style.display = 'none';
            resetRescheduleForm();
        });

        // When appointment is selected, set the dentist
        selectAppointment.addEventListener('change', function() {
            if (!this.value) return;
            
            const selectedOption = this.options[this.selectedIndex];
            const dentistId = selectedOption.getAttribute('data-dentist-id');
            
            // Set the dentist dropdown
            rescheduleDentist.value = dentistId;
            
            // Trigger dentist change to load available dates
            rescheduleDentist.dispatchEvent(new Event('change'));
        });

        // When dentist changes, load available dates
        rescheduleDentist.addEventListener('change', function() {
            if (!this.value) {
                rescheduleDate.disabled = true;
                rescheduleDate.innerHTML = '<option value="">Select Date</option>';
                return;
            }

            fetch(`get_available_dates.php?dentist_id=${this.value}`)
                .then(response => response.json())
                .then(dates => {
                    rescheduleDate.innerHTML = '<option value="">Select Date</option>';
                    dates.forEach(date => {
                        const option = document.createElement('option');
                        option.value = date;
                        option.textContent = date;
                        rescheduleDate.appendChild(option);
                    });
                    rescheduleDate.disabled = false;
                });
        });

        // When date changes, load available times
        rescheduleDate.addEventListener('change', function() {
            if (!this.value || !rescheduleDentist.value) {
                rescheduleTime.disabled = true;
                rescheduleTime.innerHTML = '<option value="">Select Time</option>';
                return;
            }

            fetch(`get_available_times.php?dentist_id=${rescheduleDentist.value}&date=${this.value}`)
                .then(response => response.json())
                .then(times => {
                    rescheduleTime.innerHTML = '<option value="">Select Time</option>';
                    times.forEach(time => {
                        const option = document.createElement('option');
                        option.value = time;
                        option.textContent = time;
                        rescheduleTime.appendChild(option);
                    });
                    rescheduleTime.disabled = false;
                });
        });

        // Enable custom time input
        enableRescheduleCustomTimeBtn.addEventListener('click', function() {
            rescheduleTime.style.display = 'none';
            rescheduleCustomTime.style.display = 'block';
            this.style.display = 'none';
        });

        // Check for conflicts when time inputs change
        [rescheduleTime, rescheduleCustomTime, rescheduleDate].forEach(element => {
            element.addEventListener('change', checkRescheduleConflict);
        });

function checkRescheduleConflict() {
    if (!selectAppointment.value || !rescheduleDentist.value || !rescheduleDate.value) {
        conflictWarning.style.display = 'none';
        return;
    }

    const time = rescheduleCustomTime.style.display === 'block' ? 
                 rescheduleCustomTime.value : rescheduleTime.value;
    
    if (!time) {
        conflictWarning.style.display = 'none';
        return;
    }

    // Get duration from selected appointment
    const selectedOption = selectAppointment.options[selectAppointment.selectedIndex];
    const duration = parseInt(selectedOption.getAttribute('data-duration')) || 60;

    // Format time to HH:MM (remove seconds if present)
    let formattedTime = time.split(':').slice(0, 2).join(':');

    // First check if appointment extends past closing time (5:00 PM)
    const [hours, minutes] = formattedTime.split(':').map(Number);
    const selectedTimeInMinutes = hours * 60 + minutes;
    const endTimeInMinutes = selectedTimeInMinutes + duration;
    
    // Clinic hours: 9:00 AM (540) to 5:00 PM (1020)
    if (endTimeInMinutes > 1020) {
        const availableDuration = 1020 - selectedTimeInMinutes;
        conflictWarning.innerHTML = `⚠️ <strong>Clinic Hours Conflict!</strong> The clinic closes at 5:00 PM. With these services (${duration} minutes), only ${availableDuration} minutes are available before closing. Please choose an earlier time.`;
        conflictWarning.style.display = 'block';
        conflictWarning.style.color = 'red';
        saveRescheduleBtn.disabled = true;
        return;
    }

    // Then check for other appointment conflicts
    const params = new URLSearchParams();
    params.append('dentist_id', rescheduleDentist.value);
    params.append('date', rescheduleDate.value);
    params.append('start_time', formattedTime);
    params.append('duration', duration);
    params.append('exclude_id', selectAppointment.value);

    fetch(`check_conflict.php?${params.toString()}`)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        })
        .then(data => {
            conflictWarning.style.display = 'block';
            if (data.conflict) {
                let conflictMessage = '⚠️ Conflict with existing appointment(s):\n';
                if (data.conflicts && data.conflicts.length > 0) {
                    data.conflicts.forEach(conflict => {
                        conflictMessage += `- ${conflict.patient} (${conflict.start} to ${conflict.end})\n`;
                    });
                } else {
                    conflictMessage = '⚠️ Time slot not available';
                }
                conflictWarning.innerHTML = conflictMessage;
                conflictWarning.style.color = "red";
                saveRescheduleBtn.disabled = true;
            } else {
                conflictWarning.innerHTML = "✅ Time slot available";
                conflictWarning.style.color = "green";
                saveRescheduleBtn.disabled = false;
            }
        })
        .catch(error => {
            console.error('Error checking conflict:', error);
            conflictWarning.style.display = 'block';
            conflictWarning.innerHTML = '⚠️ Error checking availability';
            conflictWarning.style.color = "red";
            saveRescheduleBtn.disabled = true;
        });
}

        // Save reschedule
saveRescheduleBtn.addEventListener('click', function() {
    if (!selectAppointment.value || !rescheduleDentist.value || !rescheduleDate.value) {
        alert('Please select an appointment, dentist, and date');
        return;
    }

    const time = rescheduleCustomTime.style.display === 'block' ? 
                rescheduleCustomTime.value : rescheduleTime.value;
    
    if (!time) {
        alert('Please select or enter a time');
        return;
    }

    if (conflictWarning.style.color === 'red' || conflictWarning.style.display === 'none') {
        alert('Please resolve the scheduling conflict before saving');
        return;
    }

    // Get duration from selected appointment
    const selectedOption = selectAppointment.options[selectAppointment.selectedIndex];
    const duration = parseInt(selectedOption.getAttribute('data-duration')) || 60;

    // Validate clinic hours
    const [hours, minutes] = time.split(':').map(Number);
    const selectedTimeInMinutes = hours * 60 + minutes;
    const endTimeInMinutes = selectedTimeInMinutes + duration;
    
    if (selectedTimeInMinutes < 540 || endTimeInMinutes > 1020) {
        alert('Appointment must be scheduled between 9:00 AM and 5:00 PM');
        return;
    }

    const formData = new FormData();
    formData.append('form_type', 'reschedule');
    formData.append('appointment_id', selectAppointment.value);
    formData.append('new_date', rescheduleDate.value);
    formData.append('new_time', time);

    fetch('dentist_appointment_module.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.status === 'success') {
            alert(data.message);
            window.location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred while rescheduling');
    });
});


        function resetRescheduleForm() {
            selectAppointment.value = '';
            rescheduleDentist.value = '';
            rescheduleDate.innerHTML = '<option value="">Select Date</option>';
            rescheduleDate.disabled = true;
            rescheduleTime.innerHTML = '<option value="">Select Time</option>';
            rescheduleTime.disabled = true;
            rescheduleCustomTime.value = '';
            rescheduleCustomTime.style.display = 'none';
            enableRescheduleCustomTimeBtn.style.display = 'block';
            conflictWarning.style.display = 'none';
            saveRescheduleBtn.disabled = false;
        }
    });
