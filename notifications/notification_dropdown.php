<?php
session_start();
// Include database connection first
require_once '../db_connection.php';
require_once 'notification_functions.php';
require_once 'notification_config.php';

// Get user ID from session
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;

// ==================== HANDLE SPECIALIZATION ACTIONS ====================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['specialization_action'])) {
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
        echo 'unauthorized';
        exit;
    }
    
    $request_id = $_POST['request_id'];
    $action = $_POST['action'];
    $admin_notes = $_POST['admin_notes'] ?? '';
    
    if ($action === 'approve') {
        // Get the request details
        $stmt = $conn->prepare("SELECT * FROM specialization_requests WHERE request_id = ?");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $request = $result->fetch_assoc();
        
        if ($request) {
            // Update dentist's specialization
            $dentist_stmt = $conn->prepare("SELECT specialization FROM dentists WHERE Dentist_ID = ?");
            $dentist_stmt->bind_param("i", $request['dentist_id']);
            $dentist_stmt->execute();
            $dentist_result = $dentist_stmt->get_result();
            $dentist = $dentist_result->fetch_assoc();
            
            $current_specialization = $dentist['specialization'] ?? '';
            
            // Check if the service is already in specialization to avoid duplicates
            $current_specs = $current_specialization ? array_map('trim', explode(',', $current_specialization)) : [];
            if (!in_array($request['requested_service'], $current_specs)) {
                $new_specialization = $current_specialization ? $current_specialization . ', ' . $request['requested_service'] : $request['requested_service'];
                
                $update_stmt = $conn->prepare("UPDATE dentists SET specialization = ? WHERE Dentist_ID = ?");
                $update_stmt->bind_param("si", $new_specialization, $request['dentist_id']);
                $update_stmt->execute();
            }
            
            // Update request status
            $status_stmt = $conn->prepare("UPDATE specialization_requests SET status = 'approved', processed_date = NOW(), dentist_viewed = 0 WHERE request_id = ?");
            $status_stmt->bind_param("i", $request_id);
            $status_stmt->execute();
            
            echo 'success';
        }
        
    } elseif ($action === 'reject') {
        // Update request status to rejected
        $stmt = $conn->prepare("UPDATE specialization_requests SET status = 'rejected', admin_notes = ?, processed_date = NOW(), dentist_viewed = 0 WHERE request_id = ?");
        $stmt->bind_param("si", $admin_notes, $request_id);
        $stmt->execute();
        
        echo 'success';
    }
    exit; // Stop here for AJAX requests
}

// ==================== REGULAR NOTIFICATION DISPLAY ====================
// Get notifications
$notifications = getRecentNotifications($user_id, 7);
$unread_count = getUnreadCount($user_id);
?>

<div class="notification-dropdown">
    <div class="notification-header">
        <h3>Notifications</h3>
        <?php if ($unread_count > 0): ?>
            <span class="notification-count"><?php echo $unread_count; ?> unread</span>
        <?php endif; ?>
    </div>
    
    <div class="notification-list">
        <?php if(empty($notifications)): ?>
            <div class="notification-item no-notifications">
                <i class="fas fa-bell-slash"></i>
                <span>No notifications yet</span>
            </div>
        <?php else: ?>
            <?php foreach($notifications as $notification): ?>
                <div class="notification-item <?php echo $notification['is_read'] ? 'read' : 'unread'; ?>" id="notification-<?php echo $notification['id']; ?>">
                    
                    <div class="notification-icon">
                        <i class="<?php echo $notification_types[$notification['type']]['icon'] ?? 'fas fa-bell'; ?>"></i>
                    </div>
                    
                    <div class="notification-content">
                        <div class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></div>
                        <div class="notification-message"><?php echo htmlspecialchars($notification['message']); ?></div>
                        <div class="notification-time"><?php echo time_elapsed_string($notification['created_at']); ?></div>
                        
                        <!-- Add action buttons for specialization requests -->
                        <?php if (hasActionButtons($notification)): ?>
                            <div class="notification-actions">
                                <button type="button" class="btn-approve" onclick="handleSpecializationAction(<?php echo $notification['related_id']; ?>, 'approve', <?php echo $notification['id']; ?>)">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                                <button type="button" class="btn-reject" onclick="handleSpecializationAction(<?php echo $notification['related_id']; ?>, 'reject', <?php echo $notification['id']; ?>)">
                                    <i class="fas fa-times"></i> Reject
                                </button>
                                <input type="text" id="reject-reason-<?php echo $notification['related_id']; ?>" placeholder="Reason (optional)" class="reject-reason" style="display: none;">
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <?php if(!$notification['is_read']): ?>
                        <div class="notification-dot"></div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <div class="notification-footer">
        <a href="notifications.php" class="view-all">View All Notifications</a>
        <button type="button" class="mark-all-read" onclick="markAllAsRead()">Mark All as Read</button>
    </div>
</div>

<style>
.notification-actions {
    margin-top: 10px;
    padding-top: 10px;
    border-top: 1px solid #eee;
    display: flex;
    gap: 8px;
    align-items: center;
    flex-wrap: wrap;
}

.btn-approve {
    background: #28a745;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
    transition: background 0.3s;
}

.btn-reject {
    background: #dc3545;
    color: white;
    border: none;
    padding: 4px 8px;
    border-radius: 3px;
    cursor: pointer;
    font-size: 11px;
    transition: background 0.3s;
}

.reject-reason {
    padding: 4px 6px;
    border: 1px solid #ddd;
    border-radius: 3px;
    font-size: 11px;
    width: 120px;
}

.btn-approve:hover {
    background: #218838;
}

.btn-reject:hover {
    background: #c82333;
}

.notification-item {
    padding: 12px;
    position: relative;
}

.action-success {
    background: #d4edda !important;
    border-left: 4px solid #28a745 !important;
}

.action-processed {
    opacity: 0.6;
}
</style>

<script>
function handleSpecializationAction(requestId, action, notificationId) {
    console.log('Action clicked:', action, 'Request ID:', requestId, 'Notification ID:', notificationId);
    
    if (action === 'reject') {
        const reasonInput = document.getElementById('reject-reason-' + requestId);
        if (reasonInput.style.display === 'none') {
            reasonInput.style.display = 'inline-block';
            return; // Wait for user to enter reason and click again
        }
    }
    
    const reason = action === 'reject' ? document.getElementById('reject-reason-' + requestId).value : '';
    
    // Show loading state
    const notificationItem = document.getElementById('notification-' + notificationId);
    const buttons = notificationItem.querySelectorAll('.btn-approve, .btn-reject');
    buttons.forEach(btn => {
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    });
    
    // Send AJAX request to THIS SAME FILE
    const formData = new FormData();
    formData.append('specialization_action', '1');
    formData.append('request_id', requestId);
    formData.append('action', action);
    if (reason) {
        formData.append('admin_notes', reason);
    }
    
    fetch('notification_dropdown.php', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.text();
    })
    .then(data => {
        console.log('Response:', data);
        if (data === 'success') {
            // Add success styling
            notificationItem.classList.add('action-success', 'action-processed');
            
            // Remove the action buttons
            const actionsDiv = notificationItem.querySelector('.notification-actions');
            if (actionsDiv) {
                actionsDiv.innerHTML = '<span style="color: #28a745; font-weight: bold;">✓ ' + 
                    (action === 'approve' ? 'Approved' : 'Rejected') + 
                    '</span>';
            }
            
            // Remove unread dot
            const dot = notificationItem.querySelector('.notification-dot');
            if (dot) dot.remove();
            
        } else {
            alert('Error: ' + data);
            // Re-enable buttons on error
            buttons.forEach(btn => {
                btn.disabled = false;
                btn.innerHTML = action === 'approve' ? 
                    '<i class="fas fa-check"></i> Approve' : 
                    '<i class="fas fa-times"></i> Reject';
            });
        }
    })
    .catch(error => {
        console.error('Error:', error);
        // Re-enable buttons on error
        buttons.forEach(btn => {
            btn.disabled = false;
            btn.innerHTML = btn.classList.contains('btn-approve') ? 
                '<i class="fas fa-check"></i> Approve' : 
                '<i class="fas fa-times"></i> Reject';
        });
        alert('Error processing request. Please try again.');
    });
}

function markAllAsRead() {
    if (confirm('Mark all notifications as read?')) {
        // Simple page reload for now - you can implement AJAX later
        window.location.href = 'notifications.php?mark_all_read=1';
    }
}

// Auto-hide reject reason input when clicking elsewhere
document.addEventListener('click', function(e) {
    if (!e.target.classList.contains('btn-reject') && !e.target.classList.contains('reject-reason')) {
        const reasonInputs = document.querySelectorAll('.reject-reason');
        reasonInputs.forEach(input => {
            input.style.display = 'none';
        });
    }
});
</script>