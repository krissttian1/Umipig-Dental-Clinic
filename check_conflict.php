<?php
header('Content-Type: application/json');
include 'db_connection.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Validate required parameters
if (!isset($_GET['date']) || !isset($_GET['start_time']) || !isset($_GET['duration'])) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameters',
        'required' => ['date', 'start_time', 'duration']
    ]);
    exit;
}

// Process parameters
$date = $_GET['date'];
$start_time = $_GET['start_time'];
$duration = (int)$_GET['duration'];
$services = isset($_GET['services']) ? $_GET['services'] : null;
$dentist_id = isset($_GET['dentist_id']) ? (int)$_GET['dentist_id'] : null;

// NEW: Get dentist selections for each service (for sequential booking)
$dentist_selections = [];
foreach ($_GET as $key => $value) {
    if (strpos($key, 'dentist_') === 0) {
        $service_id = str_replace('dentist_', '', $key);
        $dentist_selections[$service_id] = (int)$value;
    }
}

// Validate date format
if (!DateTime::createFromFormat('Y-m-d', $date)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid date format (YYYY-MM-DD required)']);
    exit;
}

// Validate time format and calculate end time for the main appointment
$start_dt = DateTime::createFromFormat('H:i', $start_time);
if (!$start_dt) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid time format (HH:MM required)']);
    exit;
}

$end_dt = clone $start_dt;
$end_dt->modify("+{$duration} minutes");

// Format times for database
$start_str = $start_dt->format('H:i:s');
$end_str = $end_dt->format('H:i:s');

// Auto-complete past appointments
$now = new DateTime();
$now_date = $now->format('Y-m-d');
$now_time = $now->format('H:i:s');

$complete_sql = "UPDATE appointment 
                SET Appointment_Status = 'Completed' 
                WHERE Appointment_Status = 'Confirmed' 
                AND Appointment_Date < ? 
                OR (Appointment_Date = ? AND end_time <= ?)";
$complete_stmt = $conn->prepare($complete_sql);
$complete_stmt->bind_param("sss", $now_date, $now_date, $now_time);
$complete_stmt->execute();
$complete_stmt->close();

try {
    // NEW: Sequential conflict checking for multiple services
    if ($services && !empty($dentist_selections)) {
        // SEQUENTIAL BOOKING: Check conflicts for each service with its assigned dentist
        $service_ids = explode(',', $services);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids);
        
        if (empty($service_ids)) {
            echo json_encode([
                'conflict' => false,
                'message' => 'No services specified - assuming available'
            ]);
            exit;
        }
        
        // Get service durations for sequential timing
        $service_durations = [];
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        $duration_sql = "SELECT service_ID, service_duration FROM services WHERE service_ID IN ($placeholders)";
        $duration_stmt = $conn->prepare($duration_sql);
        $duration_stmt->bind_param(str_repeat('i', count($service_ids)), ...$service_ids);
        $duration_stmt->execute();
        $duration_result = $duration_stmt->get_result();
        
        while ($row = $duration_result->fetch_assoc()) {
            $service_durations[$row['service_ID']] = $row['service_duration'];
        }
        $duration_stmt->close();
        
        // Check conflicts for each service in sequence
        $all_conflicts = [];
        $current_time = $start_dt;
        
        foreach ($service_ids as $service_id) {
            $service_duration = $service_durations[$service_id] ?? 30; // Default 30 minutes
            $service_end = clone $current_time;
            $service_end->modify("+{$service_duration} minutes");
            
            $service_start_str = $current_time->format('H:i:s');
            $service_end_str = $service_end->format('H:i:s');
            
            // Get the dentist assigned to this service
            $assigned_dentist_id = $dentist_selections[$service_id] ?? null;
            
            if (!$assigned_dentist_id) {
                // If no dentist specified, check all qualified dentists for this service
                $dentist_sql = "SELECT dentist_id FROM dentist_services WHERE service_id = ? LIMIT 1";
                $dentist_stmt = $conn->prepare($dentist_sql);
                $dentist_stmt->bind_param("i", $service_id);
                $dentist_stmt->execute();
                $dentist_result = $dentist_stmt->get_result();
                if ($dentist_row = $dentist_result->fetch_assoc()) {
                    $assigned_dentist_id = $dentist_row['dentist_id'];
                }
                $dentist_stmt->close();
            }
            
            if ($assigned_dentist_id) {
                // Check conflicts for this specific service-dentist combination
                $conflict_sql = "SELECT 
                                    a.Appointment_ID, 
                                    a.start_time, 
                                    a.end_time,
                                    a.Patient_Name_Custom,
                                    a.Appointment_Status,
                                    d.name AS dentist_name,
                                    d.Dentist_ID,
                                    s.service_name
                                FROM appointment a
                                JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                                LEFT JOIN services s ON a.Service_ID = s.service_ID
                                WHERE a.Dentist_ID = ?
                                AND a.Appointment_Date = ?
                                AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                                AND (
                                    (a.start_time < ? AND a.end_time > ?)
                                )";
                $conflict_stmt = $conn->prepare($conflict_sql);
                $conflict_stmt->bind_param("isss", $assigned_dentist_id, $date, $service_end_str, $service_start_str);
                $conflict_stmt->execute();
                $result = $conflict_stmt->get_result();
                
                if ($result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $all_conflicts[] = [
                            'id' => $row['Appointment_ID'],
                            'patient' => $row['Patient_Name_Custom'],
                            'start' => substr($row['start_time'], 0, 5),
                            'end' => substr($row['end_time'], 0, 5),
                            'status' => $row['Appointment_Status'],
                            'dentist' => $row['dentist_name'],
                            'dentist_id' => $row['Dentist_ID'],
                            'service' => $row['service_name'] ?? 'Unknown Service',
                            'conflict_period' => "{$service_start_str} - {$service_end_str}",
                            'conflicting_service' => $service_id
                        ];
                    }
                }
                $conflict_stmt->close();
            }
            
            // Move to next time slot
            $current_time = $service_end;
        }
        
        if (!empty($all_conflicts)) {
            echo json_encode([
                'conflict' => true,
                'conflicts' => $all_conflicts,
                'message' => 'Time slot conflicts with existing appointment(s) for sequential booking'
            ]);
        } else {
            echo json_encode([
                'conflict' => false,
                'message' => 'Time slot available for sequential booking with all assigned dentists'
            ]);
        }
        
    } else if ($dentist_id) {
        // SPECIFIC DENTIST: Check conflicts only for this dentist (single appointment)
        $conflict_sql = "SELECT 
                            a.Appointment_ID, 
                            a.start_time, 
                            a.end_time,
                            a.Patient_Name_Custom,
                            a.Appointment_Status,
                            d.name AS dentist_name,
                            d.Dentist_ID,
                            s.service_name
                        FROM appointment a
                        JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                        LEFT JOIN services s ON a.Service_ID = s.service_ID
                        WHERE a.Dentist_ID = ?
                        AND a.Appointment_Date = ?
                        AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                        AND (
                            (a.start_time < ? AND a.end_time > ?)
                        )";
        $conflict_stmt = $conn->prepare($conflict_sql);
        $conflict_stmt->bind_param("isss", $dentist_id, $date, $end_str, $start_str);
        $conflict_stmt->execute();
        $result = $conflict_stmt->get_result();

        if ($result->num_rows > 0) {
            $conflicts = [];
            while ($row = $result->fetch_assoc()) {
                $conflicts[] = [
                    'id' => $row['Appointment_ID'],
                    'patient' => $row['Patient_Name_Custom'],
                    'start' => substr($row['start_time'], 0, 5),
                    'end' => substr($row['end_time'], 0, 5),
                    'status' => $row['Appointment_Status'],
                    'dentist' => $row['dentist_name'],
                    'dentist_id' => $row['Dentist_ID'],
                    'service' => $row['service_name'] ?? 'Unknown Service'
                ];
            }
            
            echo json_encode([
                'conflict' => true,
                'conflicts' => $conflicts,
                'message' => "Selected time conflicts with existing appointment(s) for this dentist"
            ]);
        } else {
            echo json_encode([
                'conflict' => false,
                'message' => "Time slot available for selected dentist"
            ]);
        }

        $conflict_stmt->close();
        
    } else if ($services) {
        // SERVICE-BASED: Check conflicts for all dentists qualified for these services (single appointment)
        $service_ids = explode(',', $services);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids);
        
        if (empty($service_ids)) {
            echo json_encode([
                'conflict' => false,
                'message' => 'No services specified - assuming available'
            ]);
            exit;
        }
        
        $placeholders = implode(',', array_fill(0, count($service_ids), '?'));
        
        $conflict_sql = "SELECT 
                            a.Appointment_ID, 
                            a.start_time, 
                            a.end_time,
                            a.Patient_Name_Custom,
                            a.Appointment_Status,
                            d.name AS dentist_name,
                            d.Dentist_ID,
                            s.service_name
                        FROM appointment a
                        JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                        LEFT JOIN services s ON a.Service_ID = s.service_ID
                        JOIN dentist_services ds ON d.Dentist_ID = ds.dentist_id
                        WHERE ds.service_id IN ($placeholders)
                        AND a.Appointment_Date = ?
                        AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                        AND (
                            (a.start_time < ? AND a.end_time > ?)
                        )
                        GROUP BY a.Appointment_ID";
        
        $conflict_stmt = $conn->prepare($conflict_sql);
        
        $types = str_repeat('i', count($service_ids)) . 'sss';
        $params = $service_ids;
        $params[] = $date;
        $params[] = $end_str;
        $params[] = $start_str;
        $conflict_stmt->bind_param($types, ...$params);
        $conflict_stmt->execute();
        $result = $conflict_stmt->get_result();

        if ($result->num_rows > 0) {
            $conflicts = [];
            while ($row = $result->fetch_assoc()) {
                $conflicts[] = [
                    'id' => $row['Appointment_ID'],
                    'patient' => $row['Patient_Name_Custom'],
                    'start' => substr($row['start_time'], 0, 5),
                    'end' => substr($row['end_time'], 0, 5),
                    'status' => $row['Appointment_Status'],
                    'dentist' => $row['dentist_name'],
                    'dentist_id' => $row['Dentist_ID'],
                    'service' => $row['service_name'] ?? 'Unknown Service'
                ];
            }
            
            echo json_encode([
                'conflict' => true,
                'conflicts' => $conflicts,
                'message' => "Time slot not available among qualified dentists"
            ]);
        } else {
            echo json_encode([
                'conflict' => false,
                'message' => "Time slot available among qualified dentists"
            ]);
        }

        $conflict_stmt->close();
        
    } else {
        // ALL DENTISTS: Check conflicts across all dentists (single appointment)
        $conflict_sql = "SELECT 
                            a.Appointment_ID, 
                            a.start_time, 
                            a.end_time,
                            a.Patient_Name_Custom,
                            a.Appointment_Status,
                            d.name AS dentist_name,
                            d.Dentist_ID,
                            s.service_name
                        FROM appointment a
                        JOIN dentists d ON a.Dentist_ID = d.Dentist_ID
                        LEFT JOIN services s ON a.Service_ID = s.service_ID
                        WHERE a.Appointment_Date = ?
                        AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                        AND (
                            (a.start_time < ? AND a.end_time > ?)
                        )";
        $conflict_stmt = $conn->prepare($conflict_sql);
        $conflict_stmt->bind_param("sss", $date, $end_str, $start_str);
        $conflict_stmt->execute();
        $result = $conflict_stmt->get_result();

        if ($result->num_rows > 0) {
            $conflicts = [];
            while ($row = $result->fetch_assoc()) {
                $conflicts[] = [
                    'id' => $row['Appointment_ID'],
                    'patient' => $row['Patient_Name_Custom'],
                    'start' => substr($row['start_time'], 0, 5),
                    'end' => substr($row['end_time'], 0, 5),
                    'status' => $row['Appointment_Status'],
                    'dentist' => $row['dentist_name'],
                    'dentist_id' => $row['Dentist_ID'],
                    'service' => $row['service_name'] ?? 'Unknown Service'
                ];
            }
            
            echo json_encode([
                'conflict' => true,
                'conflicts' => $conflicts,
                'message' => "Time slot not available (conflict with existing appointments)"
            ]);
        } else {
            echo json_encode([
                'conflict' => false,
                'message' => "Time slot available across all dentists"
            ]);
        }

        $conflict_stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Error in check_conflict.php: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'error' => 'Internal server error',
        'message' => 'Unable to check availability'
    ]);
}

$conn->close();
?>