<?php
// availability_functions.php
function getDentistAvailabilityRanges($dentist_id, $conn) {
    $availability_ranges = [];
    
    $availability_stmt = $conn->prepare("
        SELECT 
            available_date,
            available_time,
            day_of_week,
            end_time
        FROM dentistavailability 
        WHERE Dentist_ID = ? 
        AND available_date >= CURDATE()
        ORDER BY available_date, available_time
    ");
    $availability_stmt->bind_param("i", $dentist_id);
    $availability_stmt->execute();
    $availability_result = $availability_stmt->get_result();

    // Group by day and find time ranges - USING FIXED LOGIC
    $availability_by_day = [];
    while ($avail = $availability_result->fetch_assoc()) {
        $day = $avail['day_of_week'];
        $start_time = date('g:i A', strtotime($avail['available_time']));
        
        // Use end_time if available, otherwise calculate it
        if (!empty($avail['end_time']) && $avail['end_time'] != '00:00:00') {
            $end_time = date('g:i A', strtotime($avail['end_time']));
        } else {
            // Calculate end time (30 minutes after start time)
            $end_time = date('g:i A', strtotime($avail['available_time'] . ' +30 minutes'));
        }
        
        if (!isset($availability_by_day[$day])) {
            $availability_by_day[$day] = [];
        }
        $availability_by_day[$day][] = ['start' => $start_time, 'end' => $end_time];
    }

    // Convert to time ranges for display - FIXED COMPARISON
    foreach ($availability_by_day as $day => $time_slots) {
        if (!empty($time_slots)) {
            // Convert all times to timestamps for proper comparison
            $start_timestamps = [];
            $end_timestamps = [];
            
            foreach ($time_slots as $slot) {
                $start_timestamps[] = strtotime($slot['start']);
                $end_timestamps[] = strtotime($slot['end']);
            }
            
            // Find the earliest start and latest end time
            $earliest_start = min($start_timestamps);
            $latest_end = max($end_timestamps);
            
            // Format back to readable time
            $earliest_start_formatted = date('g:i A', $earliest_start);
            $latest_end_formatted = date('g:i A', $latest_end);
            
            $availability_ranges[$day] = "$earliest_start_formatted - $latest_end_formatted";
        }
    }
    $availability_stmt->close();
    
    return $availability_ranges;
}

// Function to get all available dentists with their schedules
function getAvailableDentistsWithSchedules($conn) {
    $dentists = [];
    
    $sql = "SELECT d.Dentist_ID, d.name, d.specialization 
            FROM dentists d 
            WHERE EXISTS (
                SELECT 1 FROM dentistavailability da 
                WHERE da.Dentist_ID = d.Dentist_ID 
                AND da.available_date >= CURDATE()
            )
            ORDER BY d.name";
    
    $result = $conn->query($sql);
    
    if ($result && $result->num_rows > 0) {
        while ($dentist = $result->fetch_assoc()) {
            $dentist_id = $dentist['Dentist_ID'];
            $dentist['availability'] = getDentistAvailabilityRanges($dentist_id, $conn);
            $dentists[] = $dentist;
        }
    }
    
    return $dentists;
}
?>