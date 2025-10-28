<?php
require 'db_connection.php';

header('Content-Type: application/json');

try {
    // Check if services parameter is provided
    if (isset($_GET['services']) && !empty($_GET['services'])) {
        $service_ids = explode(',', $_GET['services']);
        $service_ids = array_map('intval', $service_ids);
        $service_ids = array_filter($service_ids);
        
        if (empty($service_ids)) {
            echo json_encode([]);
            exit;
        }
        
        // Build dentist conditions from GET parameters
        $dentist_conditions = [];
        $dentist_params = [];
        
        foreach ($service_ids as $service_id) {
            $dentist_key = "dentist_" . $service_id;
            if (isset($_GET[$dentist_key]) && !empty($_GET[$dentist_key])) {
                $dentist_conditions[] = "da.Dentist_ID = ?";
                $dentist_params[] = intval($_GET[$dentist_key]);
            }
        }
        
        // If specific dentists are selected, use them; otherwise get all dentists for the services
        if (!empty($dentist_conditions)) {
            $dentist_condition = "AND (" . implode(" OR ", $dentist_conditions) . ")";
        } else {
            // Get all dentists that provide the selected services
            $dentist_condition = "AND da.Dentist_ID IN (
                SELECT DISTINCT ds.dentist_id 
                FROM dentist_services ds 
                WHERE ds.service_id IN (" . implode(',', array_fill(0, count($service_ids), '?')) . ")
            )";
            $dentist_params = array_merge($service_ids, $dentist_params);
        }
        
        // Query to get available dates based on dentist availability
        $sql = "SELECT DISTINCT da.available_date 
                FROM dentistavailability da 
                WHERE da.available_date >= CURDATE() 
                $dentist_condition
                AND NOT EXISTS (
                    SELECT 1 FROM appointment a 
                    WHERE a.Dentist_ID = da.Dentist_ID 
                    AND a.Appointment_Date = da.available_date 
                    AND a.Appointment_Status IN ('Pending', 'Confirmed', 'Rescheduled')
                    AND a.start_time = da.available_time
                )
                ORDER BY da.available_date 
                LIMIT 30";
        
        $stmt = $conn->prepare($sql);
        
        // Bind parameters based on condition type
        if (!empty($dentist_conditions)) {
            // Only specific dentists selected
            $types = str_repeat('i', count($dentist_params));
            $stmt->bind_param($types, ...$dentist_params);
        } else {
            // All dentists for selected services
            $types = str_repeat('i', count($dentist_params));
            $stmt->bind_param($types, ...$dentist_params);
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['available_date'];
        }
        
        echo json_encode(array_unique($dates));
        $stmt->close();
        
    } else if (isset($_GET['dentist_id']) && !empty($_GET['dentist_id'])) {
        // SPECIFIC DENTIST: Get dates for a specific dentist
        $dentist_id = intval($_GET['dentist_id']);
        
        $sql = "SELECT DISTINCT available_date 
                FROM dentistavailability 
                WHERE Dentist_ID = ? 
                AND available_date >= CURDATE()
                ORDER BY available_date ASC
                LIMIT 30";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $dentist_id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['available_date'];
        }
        
        echo json_encode($dates);
        $stmt->close();
        
    } else {
        // ALL DENTISTS: Get dates from all dentists
        $sql = "SELECT DISTINCT available_date 
                FROM dentistavailability 
                WHERE available_date >= CURDATE()
                ORDER BY available_date ASC
                LIMIT 30";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $dates = [];
        while ($row = $result->fetch_assoc()) {
            $dates[] = $row['available_date'];
        }
        
        echo json_encode($dates);
        $stmt->close();
    }
    
} catch (Exception $e) {
    error_log("Error in get_available_dates.php: " . $e->getMessage());
    echo json_encode([]);
}

$conn->close();
?>