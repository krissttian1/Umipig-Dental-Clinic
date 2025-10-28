// Notification System JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const notificationIcon = document.querySelector('.notification-icon');
    const dropdownContainer = document.querySelector('.notification-dropdown-container');
    
    if (notificationIcon && dropdownContainer) {
        // Toggle dropdown on click
        notificationIcon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            if (dropdownContainer.style.display === 'block') {
                dropdownContainer.style.display = 'none';
            } else {
                loadNotifications();
                dropdownContainer.style.display = 'block';
            }
        });
        
        // Load notifications via AJAX
        function loadNotifications() {
            fetch('notifications/notification_dropdown.php')
                .then(response => response.text())
                .then(data => {
                    dropdownContainer.innerHTML = data;
                })
                .catch(error => {
                    console.error('Error loading notifications:', error);
                    dropdownContainer.innerHTML = '<div class="notification-dropdown">Error loading notifications</div>';
                });
        }
        
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!notificationIcon.contains(e.target) && !dropdownContainer.contains(e.target)) {
                dropdownContainer.style.display = 'none';
            }
        });
    }
});

// Mark notification as read
function markNotificationAsRead(notificationId) {
    fetch('notifications/mark_read.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'notification_id=' + notificationId
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Update UI
            const notificationItem = document.querySelector(`[data-id="${notificationId}"]`);
            if (notificationItem) {
                notificationItem.classList.remove('unread');
                notificationItem.classList.add('read');
                const dot = notificationItem.querySelector('.notification-dot');
                if (dot) dot.remove();
                
                // Update badge count
                updateBadgeCount();
            }
        }
    });
}

// Update badge count
function updateBadgeCount() {
    // You can implement AJAX call to get updated count
    location.reload(); // Simple reload for now
}