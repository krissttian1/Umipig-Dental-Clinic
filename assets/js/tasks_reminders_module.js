            // Sample Data (Replace with PHP/DB in production)
        const tasks = [
            {
                id: 1,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-20",
                priority: "high",
                status: "pending",
                assignedTo: "dr_santos",
                createdAt: "2025-06-15"
            },
            {
                id: 2,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-18",
                priority: "medium",
                status: "pending",
                assignedTo: "admin",
                createdAt: "2025-06-10"
            },
            {
                id: 3,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-17",
                priority: "medium",
                status: "pending",
                assignedTo: "admin",
                createdAt: "2025-06-14"
            },
            {
                id: 4,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-19",
                priority: "low",
                status: "pending",
                assignedTo: "all",
                createdAt: "2025-06-16"
            },
            {
                id: 5,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-17",
                priority: "high",
                status: "due_today",
                assignedTo: "admin",
                createdAt: "2024-06-10"
            },
            {
                id: 6,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-17",
                priority: "medium",
                status: "due_today",
                assignedTo: "all",
                createdAt: "2025-06-16"
            },
            {
                id: 7,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-17",
                priority: "high",
                status: "due_today",
                assignedTo: "dr_gomez",
                createdAt: "2025-06-15"
            },
            {
                id: 8,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-10",
                priority: "medium",
                status: "completed",
                assignedTo: "admin",
                createdAt: "2025-06-01"
            },
            {
                id: 9,
                title: "Follow up with Kristian Espinase",
                description: "Patient needs rescheduling for root canal",
                dueDate: "2025-06-12",
                priority: "low",
                status: "completed",
                assignedTo: "admin",
                createdAt: "2025-06-05"
            }
        ];

        // DOM Elements
        const pendingTasksList = document.getElementById('pending-tasks');
        const dueTodayTasksList = document.getElementById('due-today-tasks');
        const completedTasksList = document.getElementById('completed-tasks');
        const addTaskBtn = document.getElementById('add-task-btn');
        const taskModal = document.getElementById('task-modal');
        const closeModalBtn = document.getElementById('close-modal');
        const taskForm = document.getElementById('task-form');
        const columnHeaders = document.querySelectorAll('.column-header');

        // Display today's date in YYYY-MM-DD format
        const today = new Date().toISOString().split('T')[0];

        // Render tasks
        function renderTasks() {
            pendingTasksList.innerHTML = '';
            dueTodayTasksList.innerHTML = '';
            completedTasksList.innerHTML = '';

            // Update counts
            const pendingCount = tasks.filter(t => t.status === 'pending').length;
            const dueTodayCount = tasks.filter(t => t.status === 'due_today').length;
            const completedCount = tasks.filter(t => t.status === 'completed').length;

            document.querySelectorAll('.column-title .count').forEach((el, index) => {
                if (index === 0) el.textContent = pendingCount;
                if (index === 1) el.textContent = dueTodayCount;
                if (index === 2) el.textContent = completedCount;
            });

            // Render each task
            tasks.forEach(task => {
                const taskElement = createTaskElement(task);
                
                if (task.status === 'pending') {
                    pendingTasksList.appendChild(taskElement);
                } else if (task.status === 'due_today') {
                    dueTodayTasksList.appendChild(taskElement);
                } else if (task.status === 'completed') {
                    completedTasksList.appendChild(taskElement);
                }
            });
        }

        // Create task HTML element
        function createTaskElement(task) {
            const taskElement = document.createElement('div');
            taskElement.className = `task-item ${task.priority}`;
            
            const dueDate = new Date(task.dueDate);
            const isOverdue = dueDate < new Date() && task.status !== 'completed';
            
            const assignedToText = {
                'dr_santos': 'Dra. Aurea Umipig',
                'dr_gomez': 'Dr. Ramon De Guzman',
                'admin': 'Admin',
                'all': 'All Staff'
            }[task.assignedTo];

            taskElement.innerHTML = `
                <div class="task-title">${task.title}</div>
                <div class="task-meta">
                    <span>Assigned to: ${assignedToText}</span>
                    <span class="task-due ${isOverdue ? 'overdue' : ''}">
                        <i>📅</i> ${formatDate(task.dueDate)}
                    </span>
                </div>
                ${task.description ? `<div class="task-description">${task.description}</div>` : ''}
                <div class="task-actions">
                    ${task.status !== 'completed' ? `
                        <button class="task-btn complete" data-id="${task.id}">Complete</button>
                        <button class="task-btn edit" data-id="${task.id}">Edit</button>
                    ` : ''}
                </div>
            `;
            
            return taskElement;
        }

        // Format date as "Jun 17, 2024"
        function formatDate(dateString) {
            const options = { year: 'numeric', month: 'short', day: 'numeric' };
            return new Date(dateString).toLocaleDateString('en-US', options);
        }

        // Toggle column collapse/expand
        function setupColumnToggles() {
            columnHeaders.forEach(header => {
                header.addEventListener('click', function() {
                    const columnType = this.getAttribute('data-column');
                    const taskList = document.getElementById(`${columnType}-tasks`);
                    const toggleIcon = this.querySelector('.column-toggle');
                    
                    taskList.classList.toggle('collapsed');
                    toggleIcon.classList.toggle('collapsed');
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

        // Close modal when clicking outside
        window.addEventListener('click', (e) => {
            if (e.target === taskModal) {
                taskModal.style.display = 'none';
            }
        });

        // Form submission
        taskForm.addEventListener('submit', (e) => {
            e.preventDefault();
            
            const newTask = {
                id: tasks.length + 1,
                title: document.getElementById('task-title').value,
                description: document.getElementById('task-description').value,
                dueDate: document.getElementById('task-due').value,
                priority: document.querySelector('input[name="priority"]:checked').value,
                assignedTo: document.getElementById('task-assigned').value,
                status: new Date(document.getElementById('task-due').value).toISOString().split('T')[0] === today ? 'due_today' : 'pending',
                createdAt: today
            };
            
            tasks.push(newTask);
            renderTasks();
            taskForm.reset();
            taskModal.style.display = 'none';
        });

        // Task actions (complete/edit)
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('complete')) {
                const taskId = parseInt(e.target.dataset.id);
                const taskIndex = tasks.findIndex(t => t.id === taskId);
                if (taskIndex !== -1) {
                    tasks[taskIndex].status = 'completed';
                    renderTasks();
                }
            }
            
            if (e.target.classList.contains('edit')) {
                // In a real implementation, you would open the edit modal
                alert('Edit functionality would be implemented here');
            }
        });


        // Initialize
        renderTasks();
        setupColumnToggles();