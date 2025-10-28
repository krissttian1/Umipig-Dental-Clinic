<?php
require 'db_connection.php';

header('Content-Type: application/json');

if (!isset($_GET['date']) || !isset($_GET['services'])) {
    echo json_encode(['error' => 'Missing parameters', 'debug' => $_GET]);
    exit;
}

$date = $_GET['date'];
$service_ids = explode(',', $_GET['services']);
$total_duration = isset($_GET['duration']) ? intval($_GET['duration']) : 0;

try {
    // Build dentist conditions from GET parameters
    $dentist_conditions = [];
    $dentist_params = [$date];
    
    foreach ($service_ids as $service_id) {
        $service_id = intval(trim($service_id));
        if ($service_id <= 0) continue;
        
        $dentist_key = "dentist_" . $service_id;
        if (isset($_GET[$dentist_key]) && !empty($_GET[$dentist_key])) {
            $dentist_conditions[] = "da.Dentist_ID = ?";
            $dentist_params[] = intval($_GET[$dentist_key]);
        }
    }
    
    // If specific dentists are selected, use them
    if (!empty($dentist_conditions)) {
        $dentist_condition = "AND (" . implode(" OR ", $dentist_conditions) . ")";
    } else {
        // Get all dentists that provide the selected services
        $dentist_condition = "AND da.Dentist_ID IN (
            SELECT DISTINCT ds.dentist_id 
            FROM dentist_services ds 
            WHERE ds.service_id IN (" . implode(',', array_fill(0, count($service_ids), '?')) . ")
        )";
        $dentist_params = array_merge($dentist_params, $service_ids);
    }
    
    // Query available time slots from dentistavailability table
    $sql = "SELECT 
                da.available_time,
                da.end_time,
                da.Dentist_ID,
                d.name as dentist_name
            FROM dentistavailability da
            JOIN dentists d ON da.Dentist_ID = d.Dentist_ID
            WHERE da.available_date = ?
            $dentist_condition
            AND NOT EXISTS (
                SELECT 1 FROM appointment a 
                WHERE a.Dentist_ID = da.Dentist_ID 
                AND a.Appointment_Date = da.available_date 
                AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                AND a.start_time = da.available_time
            )
            ORDER BY da.available_time";
    
    $stmt = $conn->prepare($sql);
    
    // Bind parameters
    $types = str_repeat('i', count($dentist_params));
    $stmt->bind_param($types, ...$dentist_params);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    $available_times = [];
    $debug_info = [
        'query_params' => [
            'date' => $date,
            'services' => $service_ids,
            'duration' => $total_duration,
            'dentist_conditions' => $dentist_conditions
        ],
        'found_slots' => []
    ];
    
    while ($row = $result->fetch_assoc()) {
        $start_time = $row['available_time'];
        $end_time = $row['end_time'];
        
        // Calculate if this slot can accommodate the total duration
        $slot_start = strtotime($start_time);
        $slot_end = strtotime($end_time);
        $required_end = $slot_start + ($total_duration * 60);
        
        $slot_info = [
            'start_time' => $start_time,
            'end_time' => $end_time,
            'slot_duration_minutes' => ($slot_end - $slot_start) / 60,
            'required_end' => date('H:i:s', $required_end),
            'fits_duration' => ($required_end <= $slot_end)
        ];
        
        $debug_info['found_slots'][] = $slot_info;
        
        // Check if this slot can accommodate the appointment
        if ($required_end <= $slot_end) {
            // Build timeline for display
            $timeline = [];
            $current_time = $start_time;
            
            // For multi-service appointments, create a timeline
            if ($total_duration > 0 && count($service_ids) > 0) {
                foreach ($service_ids as $service_id) {
                    $service_id = intval($service_id);
                    
                    // Get service details
                    $service_sql = "SELECT service_name, service_duration FROM services WHERE service_ID = ?";
                    $service_stmt = $conn->prepare($service_sql);
                    $service_stmt->bind_param("i", $service_id);
                    $service_stmt->execute();
                    $service_result = $service_stmt->get_result();
                    
                    if ($service_row = $service_result->fetch_assoc()) {
                        $service_end = date('H:i:s', strtotime($current_time . ' + ' . $service_row['service_duration'] . ' minutes'));
                        
                        // Get assigned dentist for this service
                        $assigned_dentist_id = $row['Dentist_ID'];
                        $assigned_dentist_name = $row['dentist_name'];
                        
                        // Check if specific dentist was selected for this service
                        $dentist_key = "dentist_" . $service_id;
                        if (isset($_GET[$dentist_key]) && !empty($_GET[$dentist_key])) {
                            $specific_dentist_id = intval($_GET[$dentist_key]);
                            $dentist_name_sql = "SELECT name FROM dentists WHERE Dentist_ID = ?";
                            $dentist_name_stmt = $conn->prepare($dentist_name_sql);
                            $dentist_name_stmt->bind_param("i", $specific_dentist_id);
                            $dentist_name_stmt->execute();
                            $dentist_name_result = $dentist_name_stmt->get_result();
                            
                            if ($dentist_name_row = $dentist_name_result->fetch_assoc()) {
                                $assigned_dentist_id = $specific_dentist_id;
                                $assigned_dentist_name = $dentist_name_row['name'];
                            }
                            $dentist_name_stmt->close();
                        }
                        
                        $timeline[] = [
                            'dentist_id' => $assigned_dentist_id,
                            'dentist_name' => $assigned_dentist_name,
                            'service_name' => $service_row['service_name'],
                            'start_time' => date('H:i', strtotime($current_time)),
                            'end_time' => date('H:i', strtotime($service_end)),
                            'duration' => $service_row['service_duration']
                        ];
                        
                        $current_time = $service_end;
                        $service_stmt->close();
                    }
                }
            } else {
                // Single service or no duration specified - simple timeline
                $timeline[] = [
                    'dentist_id' => $row['Dentist_ID'],
                    'dentist_name' => $row['dentist_name'],
                    'service_name' => 'Appointment',
                    'start_time' => date('H:i', strtotime($start_time)),
                    'end_time' => date('H:i', strtotime($end_time)),
                    'duration' => $total_duration
                ];
            }
            
            $available_times[] = [
                'time' => $start_time,
                'end_time' => $end_time,
                'dentist_id' => $row['Dentist_ID'],
                'dentist_name' => $row['dentist_name'],
                'timeline' => $timeline
            ];
        }
    }
    
    // Add debug info to response
    $response = [
        'available_times' => $available_times,
        'debug' => $debug_info,
        'total_slots_found' => count($debug_info['found_slots']),
        'total_slots_available' => count($available_times)
    ];
    
    echo json_encode($response);
    $stmt->close();
    
} catch (Exception $e) {
    echo json_encode([
        'available_times' => [],
        'error' => $e->getMessage(),
        'debug' => ['exception' => true]
    ]);
}

$conn->close();
?>