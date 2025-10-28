<?php
include 'db_connection.php';
date_default_timezone_set('Asia/Manila');

// Get all dentists and their custom availability patterns
$dentists_result = mysqli_query($conn, "
    SELECT d.Dentist_ID, 
           GROUP_CONCAT(DISTINCT da.day_of_week) as custom_days,
           MIN(da.available_time) as typical_start,
           MAX(da.end_time) as typical_end
    FROM dentists d
    LEFT JOIN dentistavailability da ON d.Dentist_ID = da.Dentist_ID 
    WHERE da.day_of_week IS NOT NULL 
    AND da.available_date >= CURDATE() - INTERVAL 7 DAY
    GROUP BY d.Dentist_ID
");

$dentistPatterns = [];
while ($row = mysqli_fetch_assoc($dentists_result)) {
    if (!empty($row['custom_days'])) {
        $days = explode(',', $row['custom_days']);
        $dentistPatterns[$row['Dentist_ID']] = [
            'days' => $days,
            'start_time' => $row['typical_start'] ?: '09:00:00',
            'end_time' => $row['typical_end'] ?: '17:00:00'
        ];
    }
}

// Clear existing FUTURE availability (keep today's appointments)
$clear_sql = "DELETE FROM dentistavailability WHERE available_date > CURDATE()";
mysqli_query($conn, $clear_sql);

$today = new DateTime();
$daysToGenerate = 7; // One week ahead

$slots_created = 0;

for ($i = 1; $i <= $daysToGenerate; $i++) { // Start from tomorrow
    $date = clone $today;
    $date->modify("+$i days");
    
    $dayOfWeek = $date->format('l'); // Monday, Tuesday, etc.
    $formattedDate = $date->format('Y-m-d');
    
    // Skip Sundays
    if ($dayOfWeek === 'Sunday') {
        continue;
    }
    
    // Get all dentists
    $all_dentists_result = mysqli_query($conn, "SELECT Dentist_ID FROM dentists");
    while ($dentist = mysqli_fetch_assoc($all_dentists_result)) {
        $dentistId = $dentist['Dentist_ID'];
        
        // Check if dentist has custom pattern for this day
        if (isset($dentistPatterns[$dentistId]) && in_array($dayOfWeek, $dentistPatterns[$dentistId]['days'])) {
            $pattern = $dentistPatterns[$dentistId];
            $start_time = $pattern['start_time'];
            $end_time = $pattern['end_time'];
        } else {
            // Default schedule for dentists without custom patterns
            $start_time = '09:00:00';
            $end_time = '17:00:00';
        }
        
        // Generate time slots
        generateTimeSlots($conn, $dentistId, $formattedDate, $dayOfWeek, $start_time, $end_time);
        $slots_created++;
    }
}

function generateTimeSlots($conn, $dentistId, $date, $dayOfWeek, $start_time, $end_time) {
    $start = new DateTime($date . ' ' . $start_time);
    $end = new DateTime($date . ' ' . $end_time);
    
    $current = clone $start;
    
    while ($current < $end) {
        $slot_time = $current->format('H:i:s');
        $end_slot = clone $current;
        $end_slot->modify('+30 minutes')->format('H:i:s');
        
        // Insert slot with day_of_week and end_time for proper display
        $stmt = $conn->prepare("INSERT INTO dentistavailability 
                               (Dentist_ID, available_date, day_of_week, available_time, end_time) 
                               VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("issss", $dentistId, $date, $dayOfWeek, $slot_time, $end_slot);
        $stmt->execute();
        $stmt->close();
        
        $current->modify('+30 minutes');
    }
}

echo "Successfully regenerated availability for next 7 days. ";
echo "Created $slots_created time slots using custom patterns where available.";
?>