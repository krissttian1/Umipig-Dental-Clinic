<?php
session_start();
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_task'])) {
        // Add new task
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $due_time = $conn->real_escape_string($_POST['due_time'] ?? '00:00');
        $priority = $conn->real_escape_string($_POST['priority'] ?? 'medium');
        $status = (date('Y-m-d') == $due_date) ? 'due_today' : 'pending';

        $sql = "INSERT INTO tasks (title, description, due_date, due_time, priority, status) 
                VALUES ('$title', '$description', '$due_date', '$due_time', '$priority', '$status')";
        
        if ($conn->query($sql)) {
            $new_task_id = $conn->insert_id;
            
            // ===== NOTIFICATION TRIGGER FOR NEW TASK =====
            createAdminNotification(
                'tasks',
                'New Task Created',
                'Task "' . $title . '" has been created by ' . $_SESSION['username'] . '. Due: ' . $due_date . ' ' . $due_time,
                $priority,
                'tasks_reminders_module.php',
                $new_task_id
            );
            // ===== END NOTIFICATION TRIGGER =====
            
            header("Location: tasks_reminders_module.php");
            exit;
        } else {
            $error = "Error adding task: " . $conn->error;
        }
    } elseif (isset($_POST['update_task'])) {
        // Update existing task
        $task_id = intval($_POST['task_id']);
        $title = $conn->real_escape_string($_POST['title']);
        $description = $conn->real_escape_string($_POST['description'] ?? '');
        $due_date = $conn->real_escape_string($_POST['due_date']);
        $due_time = $conn->real_escape_string($_POST['due_time'] ?? '00:00');
        $priority = $conn->real_escape_string($_POST['priority'] ?? 'medium');
        $status = (date('Y-m-d') == $due_date) ? 'due_today' : 'pending';

        $sql = "UPDATE tasks SET 
                title = '$title',
                description = '$description',
                due_date = '$due_date',
                due_time = '$due_time',
                priority = '$priority',
                status = '$status'
                WHERE id = $task_id";
        
        if ($conn->query($sql)) {
            header("Location: tasks_reminders_module.php");
            exit;
        } else {
            $error = "Error updating task: " . $conn->error;
        }
    }
}

// Handle task completion
if (isset($_GET['complete_task'])) {
    $task_id = intval($_GET['complete_task']);
    $sql = "UPDATE tasks SET status = 'completed' WHERE id = $task_id";
    if ($conn->query($sql)) {
        // ===== NOTIFICATION TRIGGER FOR TASK COMPLETION =====
        $task_sql = "SELECT title FROM tasks WHERE id = $task_id";
        $task_result = $conn->query($task_sql);
        if ($task_row = $task_result->fetch_assoc()) {
            createAdminNotification(
                'tasks',
                'Task Completed',
                'Task "' . $task_row['title'] . '" has been completed by ' . $_SESSION['username'] . '.',
                'medium',
                'tasks_reminders_module.php',
                $task_id
            );
        }
        // ===== END NOTIFICATION TRIGGER =====
    }
    header("Location: tasks_reminders_module.php");
    exit;
}

// Handle task archiving
if (isset($_GET['archive_task'])) {
    $task_id = intval($_GET['archive_task']);
    $sql = "UPDATE tasks SET status = 'archived' WHERE id = $task_id";
    $conn->query($sql);
    header("Location: tasks_reminders_module.php");
    exit;
}

// Filter parameters
$filter_priority = isset($_GET['priority']) ? $conn->real_escape_string($_GET['priority']) : 'all';
$filter_status = isset($_GET['status']) ? $conn->real_escape_string($_GET['status']) : 'all';

// Build filter conditions
$conditions = [];
if ($filter_priority != 'all') {
    $conditions[] = "priority = '$filter_priority'";
}
if ($filter_status != 'all') {
    $conditions[] = "status = '$filter_status'";
}
$where_clause = empty($conditions) ? '' : 'WHERE ' . implode(' AND ', $conditions);

// Fetch tasks from database
$today = date('Y-m-d');
$sql = "SELECT * FROM tasks $where_clause ORDER BY 
        CASE status 
            WHEN 'due_today' THEN 1 
            WHEN 'pending' THEN 2 
            WHEN 'completed' THEN 3
            ELSE 4
        END, due_date ASC, due_time ASC";
$result = $conn->query($sql);
$tasks = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $tasks[] = $row;
    }
}

// Function to calculate overdue status
function getOverdueStatus($due_date, $due_time) {
    $now = new DateTime();
    $due_datetime = new DateTime($due_date . ' ' . $due_time);
    
    if ($due_datetime > $now) {
        $interval = $now->diff($due_datetime);
        if ($interval->days == 0 && $interval->h <= 1) {
            return 'Due soon';
        }
        return '';
    }
    
    $interval = $now->diff($due_datetime);
    
    if ($interval->days > 0) {
        return 'Overdue by ' . $interval->days . ' day' . ($interval->days > 1 ? 's' : '');
    } elseif ($interval->h > 0) {
        return 'Overdue by ' . $interval->h . ' hour' . ($interval->h > 1 ? 's' : '');
    } else {
        return 'Overdue by ' . $interval->i . ' minute' . ($interval->i > 1 ? 's' : '');
    }
}

// Count tasks by status (excluding archived)
$pending_count = 0;
$due_today_count = 0;
$completed_count = 0;

foreach ($tasks as $task) {
    if ($task['status'] == 'pending') $pending_count++;
    if ($task['status'] == 'due_today') $due_today_count++;
    if ($task['status'] == 'completed') $completed_count++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks & Reminders</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="tasks_reminders_module.css">
    <link rel="stylesheet" href="notifications/notification_style.css">

    <style>
        .filter-container {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            display: none;
        }
        .filter-container.active {
            display: block;
        }
        .filter-group {
            margin-bottom: 10px;
        }
        .filter-group label {
            display: inline-block;
            width: 100px;
            font-weight: 500;
        }
        .edit-modal {
            display: none;
        }
        .time-input {
            margin-top: 5px;
        }
        .overdue-badge {
            background-color: #ff4444;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
            display: inline-block;
        }
        .due-soon-badge {
            background-color: #ffbb33;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
            display: inline-block;
        }
        .task-meta {
            margin-top: 5px;
            margin-bottom: 5px;
        }
    </style>

<body>
            <h2>Tasks & Reminders</h2>
    
<header>
    <div class="logo-container">
        <div class="logo-circle">
            <img src="images/UmipigDentalClinic_Logo.jpg" alt="Umipig Dental Clinic">
        </div>

        <div class="clinic-info">
            <h1>Umipig Dental Clinic</h1>
            <p>General Dentist, Orthodontist, Oral Surgeon & Cosmetic Dentist</p>
        </div>
    </div>

    <div class="header-right">
    <?php if (isset($_SESSION['username'])): ?>
		<!-- Notification Icon -->
            <div class="notification-container">
                <a href="javascript:void(0)" class="notification-icon" title="Notifications" style="color: royalblue;">
                    <i class="fas fa-bell"></i>
                    <?php
                    $unread_count = getUnreadCount($user_id);
                    if($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count > 9 ? '9+' : $unread_count; ?></span>
                    <?php endif; ?>
                </a>
                <div class="notification-dropdown-container"></div>
            </div>

            <!-- Profile Icon -->
            <a href="user_profile_module.php" class="profile-icon" title="Profile" style="color: royalblue;">
                <i class="fas fa-user-circle"></i>
            </a>

        <span class="welcome-text">
            Welcome, <?php echo htmlspecialchars($_SESSION['username']); ?>! &nbsp;
            &nbsp;|&nbsp;
            <a href="logout.php" class="auth-link">Logout</a>
        </span>
    <?php else: ?>
        <a href="register.php" class="auth-link">Register</a>
        <span>|</span>
        <a href="login.php" class="auth-link">Login</a>
    <?php endif; ?>
    </div>
</header>


    <div class="dashboard">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="menu-icon">☰</div>
            <ul class="nav-menu">
               <li class="nav-item">
                     <a href="admin_dashboard.php" class="nav-link">Dashboard</a>
               </li>
                <li class="nav-item">
                     <a href="appointment_module.php" class="nav-link">Appointment Management</a>
               </li>
                <li class="nav-item">
                     <a href="billing_module.php" class="nav-link">Billing</a>
               </li>
               <li class="nav-item">
                     <a href="patient_records.php" class="nav-link">Patient Records</a>
               </li>
               <li class="nav-item">
                     <a href="reports_module.php" class="nav-link active">Reports</a>
               <li class="nav-item">
                     <a href="documents_files_module.php" class="nav-link active">Documents / Files</a>
               </li>
               <li class="nav-item">
                     <a href="calendar_module.php" class="nav-link active">Calendar</a>
               </li>
               <li class="nav-item">
                     <a href="tasks_reminders_module.php" class="nav-link active">Tasks & Reminders</a>
               <li class="nav-item">
                     <a href="system_settings_module.php" class="nav-link active">System Settings</a>
               </li>
        </div>



 <div class="tasks-module">
            <div class="module-header">
                <div class="module-actions">
                    <button class="btn btn-outline" id="filter-btn">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                    <button class="btn btn-primary" id="add-task-btn">
                        <i class="fas fa-plus"></i> Add Task
                    </button>
                </div>
            </div>

            <!-- Filter Container -->
            <div class="filter-container" id="filter-container">
                <form method="GET" action="tasks_reminders_module.php">
                    <div class="filter-group">
                        <label for="filter-priority">Priority:</label>
                        <select id="filter-priority" name="priority">
                            <option value="all" <?php echo $filter_priority == 'all' ? 'selected' : ''; ?>>All Priorities</option>
                            <option value="high" <?php echo $filter_priority == 'high' ? 'selected' : ''; ?>>High</option>
                            <option value="medium" <?php echo $filter_priority == 'medium' ? 'selected' : ''; ?>>Medium</option>
                            <option value="low" <?php echo $filter_priority == 'low' ? 'selected' : ''; ?>>Low</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label for="filter-status">Status:</label>
                        <select id="filter-status" name="status">
                            <option value="all" <?php echo $filter_status == 'all' ? 'selected' : ''; ?>>All Statuses</option>
                            <option value="pending" <?php echo $filter_status == 'pending' ? 'selected' : ''; ?>>Pending</option>
                            <option value="due_today" <?php echo $filter_status == 'due_today' ? 'selected' : ''; ?>>Due Today</option>
                            <option value="completed" <?php echo $filter_status == 'completed' ? 'selected' : ''; ?>>Completed</option>
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary">Apply Filters</button>
                    <a href="tasks_reminders_module.php" class="btn btn-outline">Reset</a>
                </form>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <div class="tasks-container">
                <!-- Pending Tasks Column -->
                <div class="task-column">
                    <div class="column-header" data-column="pending">
                        <h3 class="column-title">
                            <span>📋</span> Pending
                            <span class="count"><?php echo $pending_count; ?></span>
                        </h3>
                        <span class="column-toggle">▼</span>
                    </div>
                    <div class="task-list collapsed" id="pending-tasks">
                        <?php foreach ($tasks as $task): ?>
                            <?php if ($task['status'] == 'pending'): ?>
                                <?php 
                                $overdue_status = getOverdueStatus($task['due_date'], $task['due_time']);
                                $is_overdue = strpos($overdue_status, 'Overdue') === 0;
                                $is_due_soon = $overdue_status === 'Due soon';
                                ?>
                                <div class="task-item <?php echo $task['priority']; ?>">
                                    <div class="task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="overdue-badge"><?php echo $overdue_status; ?></span>
                                        <?php elseif ($is_due_soon): ?>
                                            <span class="due-soon-badge"><?php echo $overdue_status; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            <?php if (!empty($task['due_time']) && $task['due_time'] != '00:00:00'): ?>
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($task['due_time'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                        <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="task-actions">
                                        <a href="?complete_task=<?php echo $task['id']; ?>" class="task-btn complete" style="text-decoration: none;"><i class="fas fa-check" ></i> Complete</a>
                                        <button class="task-btn edit" data-task-id="<?php echo $task['id']; ?>" style="text-decoration: none;"><i class="fas fa-edit"></i> Edit</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Due Today Column -->
                <div class="task-column">
                    <div class="column-header" data-column="due-today">
                        <h3 class="column-title">
                            <span>⏰</span> Due Today
                            <span class="count"><?php echo $due_today_count; ?></span>
                        </h3>
                        <span class="column-toggle">▼</span>
                    </div>
                    <div class="task-list collapsed" id="due-today-tasks">
                        <?php foreach ($tasks as $task): ?>
                            <?php if ($task['status'] == 'due_today'): ?>
                                <?php 
                                $overdue_status = getOverdueStatus($task['due_date'], $task['due_time']);
                                $is_overdue = strpos($overdue_status, 'Overdue') === 0;
                                $is_due_soon = $overdue_status === 'Due soon';
                                ?>
                                <div class="task-item <?php echo $task['priority']; ?>">
                                    <div class="task-title">
                                        <?php echo htmlspecialchars($task['title']); ?>
                                        <?php if ($is_overdue): ?>
                                            <span class="overdue-badge"><?php echo $overdue_status; ?></span>
                                        <?php elseif ($is_due_soon): ?>
                                            <span class="due-soon-badge"><?php echo $overdue_status; ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="task-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            <?php if (!empty($task['due_time']) && $task['due_time'] != '00:00:00'): ?>
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($task['due_time'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                        <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="task-actions">
                                        <a href="?complete_task=<?php echo $task['id']; ?>" class="task-btn complete" style="text-decoration: none;"><i class="fas fa-check"></i> Complete</a>
                                        <button class="task-btn edit" data-task-id="<?php echo $task['id']; ?>" style="text-decoration: none;"><i class="fas fa-edit"></i> Edit</button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Completed Column -->
                <div class="task-column">
                    <div class="column-header" data-column="completed">
                        <h3 class="column-title">
                            <span>✅</span> Completed
                            <span class="count"><?php echo $completed_count; ?></span>
                        </h3>
                        <span class="column-toggle">▼</span>
                    </div>
                    <div class="task-list collapsed" id="completed-tasks">
                        <?php foreach ($tasks as $task): ?>
                            <?php if ($task['status'] == 'completed'): ?>
                                <div class="task-item <?php echo $task['priority']; ?>">
                                    <div class="task-title"><?php echo htmlspecialchars($task['title']); ?></div>
                                    <div class="task-meta">
                                        <span>
                                            <i class="fas fa-calendar-alt"></i> <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                            <?php if (!empty($task['due_time']) && $task['due_time'] != '00:00:00'): ?>
                                                <i class="fas fa-clock"></i> <?php echo date('h:i A', strtotime($task['due_time'])); ?>
                                            <?php endif; ?>
                                        </span>
                                    </div>
                                    <?php if (!empty($task['description'])): ?>
                                        <div class="task-description"><?php echo htmlspecialchars($task['description']); ?></div>
                                    <?php endif; ?>
                                    <div class="task-actions">
                                        <span class="completed-text"><i class="fas fa-check-circle" style="text-decoration: none;"></i> Completed</span>
                                        <a href="?archive_task=<?php echo $task['id']; ?>" class="task-btn remove" style="text-decoration: none;"><i class="fas fa-trash"></i> Remove</a>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Add Task Modal -->
        <div class="modal" id="task-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Add New Task</h2>
                    <button class="close-modal" id="close-modal">&times;</button>
                </div>
                <form id="task-form" method="POST" action="tasks_reminders_module.php">
                    <input type="hidden" name="add_task" value="1">
                    <div class="form-group">
                        <label for="task-title">Task Title</label>
                        <input type="text" id="task-title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="task-description">Description</label>
                        <textarea id="task-description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="task-due">Due Date</label>
                        <input type="date" id="task-due" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group time-input">
                        <label for="task-time">Time (optional)</label>
                        <input type="time" id="task-time" name="due_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <div class="priority-options">
                            <label class="priority-option">
                                <input type="radio" name="priority" value="high" checked>
                                <span class="priority-dot high"></span>
                                High
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority" value="medium">
                                <span class="priority-dot medium"></span>
                                Medium
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority" value="low">
                                <span class="priority-dot low"></span>
                                Low
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-save-primary">Save Task</button>
                </form>
            </div>
        </div>

        <!-- Edit Task Modal -->
        <div class="modal edit-modal" id="edit-task-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h2>Edit Task</h2>
                    <button class="close-modal" id="close-edit-modal">&times;</button>
                </div>
                <form id="edit-task-form" method="POST" action="tasks_reminders_module.php">
                    <input type="hidden" name="update_task" value="1">
                    <input type="hidden" id="edit-task-id" name="task_id" value="">
                    <div class="form-group">
                        <label for="edit-task-title">Task Title</label>
                        <input type="text" id="edit-task-title" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label for="edit-task-description">Description</label>
                        <textarea id="edit-task-description" name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-group">
                        <label for="edit-task-due">Due Date</label>
                        <input type="date" id="edit-task-due" name="due_date" class="form-control" required>
                    </div>
                    <div class="form-group time-input">
                        <label for="edit-task-time">Time (optional)</label>
                        <input type="time" id="edit-task-time" name="due_time" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Priority</label>
                        <div class="priority-options">
                            <label class="priority-option">
                                <input type="radio" name="priority" value="high" id="edit-priority-high">
                                <span class="priority-dot high"></span>
                                High
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority" value="medium" id="edit-priority-medium">
                                <span class="priority-dot medium"></span>
                                Medium
                            </label>
                            <label class="priority-option">
                                <input type="radio" name="priority" value="low" id="edit-priority-low">
                                <span class="priority-dot low"></span>
                                Low
                            </label>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-save-primary">Update Task</button>
                </form>
            </div>
        </div>

        <script>
            // DOM Elements
            const addTaskBtn = document.getElementById('add-task-btn');
            const taskModal = document.getElementById('task-modal');
            const closeModalBtn = document.getElementById('close-modal');
            const editTaskModal = document.getElementById('edit-task-modal');
            const closeEditModalBtn = document.getElementById('close-edit-modal');
            const columnHeaders = document.querySelectorAll('.column-header');
            const filterBtn = document.getElementById('filter-btn');
            const filterContainer = document.getElementById('filter-container');
            const editButtons = document.querySelectorAll('.task-btn.edit');

            // Display today's date in YYYY-MM-DD format
            const today = new Date().toISOString().split('T')[0];

            // Toggle column collapse/expand
            function setupColumnToggles() {
                columnHeaders.forEach(header => {
                    header.addEventListener('click', function() {
                        const columnType = this.getAttribute('data-column');
                        const taskList = document.getElementById(`${columnType}-tasks`);
                        const toggleIcon = this.querySelector('.column-toggle');
                        
                        taskList.classList.toggle('collapsed');
                        toggleIcon.textContent = taskList.classList.contains('collapsed') ? '▼' : '▲';
                    });
                });
            }

            // Modal handling
            addTaskBtn.addEventListener('click', () => {
                taskModal.style.display = 'flex';
                document.getElementById('task-due').value = today;
            });

            closeModalBtn.addEventListener('click', () => {
                taskModal.style.display = 'none';
            });

            closeEditModalBtn.addEventListener('click', () => {
                editTaskModal.style.display = 'none';
            });

            // Close modal when clicking outside
            window.addEventListener('click', (e) => {
                if (e.target === taskModal) {
                    taskModal.style.display = 'none';
                }
                if (e.target === editTaskModal) {
                    editTaskModal.style.display = 'none';
                }
            });

            // Filter button toggle
            filterBtn.addEventListener('click', () => {
                filterContainer.classList.toggle('active');
            });

            // Edit button functionality
            editButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const taskId = this.getAttribute('data-task-id');
                    const task = <?php echo json_encode($tasks); ?>.find(t => t.id == taskId);
                    
                    if (task) {
                        document.getElementById('edit-task-id').value = task.id;
                        document.getElementById('edit-task-title').value = task.title;
                        document.getElementById('edit-task-description').value = task.description;
                        document.getElementById('edit-task-due').value = task.due_date;
                        document.getElementById('edit-task-time').value = task.due_time ? task.due_time.substring(0, 5) : '';
                        
                        // Set priority
                        document.getElementById(`edit-priority-${task.priority}`).checked = true;
                        
                        editTaskModal.style.display = 'flex';
                    }
                });
            });

            // Initialize
            setupColumnToggles();
        </script>
            <script src="notifications/notification_script.js"></script>

</body>
</html>