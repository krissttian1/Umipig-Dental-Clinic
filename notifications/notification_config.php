<?php
// Notification types and their configurations
$notification_types = [
    'appointment' => [
        'icon' => 'fas fa-calendar-alt', 
        'color' => '#3498db',
        'title_prefix' => 'Appointment'
    ],
    'patient' => [
        'icon' => 'fas fa-user-injured', 
        'color' => '#9b59b6',
        'title_prefix' => 'Patient'
    ],
    'billing' => [
        'icon' => 'fas fa-money-bill-wave', 
        'color' => '#27ae60',
        'title_prefix' => 'Billing'
    ],
    'tasks' => [
        'icon' => 'fas fa-tasks', 
        'color' => '#e67e22',
        'title_prefix' => 'Task'
    ],
    'documents' => [
        'icon' => 'fas fa-file', 
        'color' => '#34495e',
        'title_prefix' => 'Document'
    ],
    'dentist' => [
        'icon' => 'fas fa-user-md', 
        'color' => '#16a085',
        'title_prefix' => 'Dentist'
    ],
    'reports' => [
        'icon' => 'fas fa-chart-bar', 
        'color' => '#8e44ad',
        'title_prefix' => 'Report'
    ]
];

$notification_priorities = [
    'low' => '#95a5a6',
    'medium' => '#3498db', 
    'high' => '#e74c3c',
    'urgent' => '#c0392b'
];
?>