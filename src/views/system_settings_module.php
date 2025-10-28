<?php
session_start();
date_default_timezone_set('Asia/Manila');

// ===== MODIFIED LINES =====
// Include notification system (db_connection will be included from here)
require 'db_connection.php';
require_once 'notifications/notification_functions.php';

// Get user ID for notifications
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 1;
// ===== END MODIFICATIONS =====

// FIX: Check for admin role with case-insensitive comparison
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: home.php");
    exit;
}

// Handle form submissions
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Add Dentist Form Submission
    if (isset($_POST['add_dentist'])) {
        $dentistFullName = trim($_POST['dentistFullName']);
        $dentistUsername = trim($_POST['dentistUsername']);
        $dentistDob = $_POST['dentistDob'];
        $dentistAge = (int)$_POST['dentistAge'];
        $dentistGender = isset($_POST['dentistGender']) ? $_POST['dentistGender'] : 'Male';
        $dentistEmail = trim($_POST['dentistEmail']);
        $dentistPhone = trim($_POST['dentistPhone']);
        $dentistPassword = $_POST['dentistPassword'];
        $dentistLicense = trim($_POST['dentistLicense']);
        $dentistAddress = trim($_POST['dentistAddress']);
        $dentistHireDate = $_POST['dentistHireDate'];
        
        // Get specialties
        $specialties = isset($_POST['specialty']) ? $_POST['specialty'] : [];
        $specialization = !empty($specialties) ? implode(', ', $specialties) : 'General Dentistry';
        
// Validate required fields
if (empty($dentistFullName) || empty($dentistUsername) || empty($dentistEmail) || empty($dentistPhone) || empty($dentistPassword) || empty($dentistLicense)) {
    $error_message = "Please fill in all required fields for the dentist.";
} else {
    try {
        // Check if dentist already exists
        $check_sql = "SELECT Dentist_ID FROM dentists WHERE email = ? OR username = ?";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("ss", $dentistEmail, $dentistUsername);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $error_message = "A dentist with this email or username already exists.";
        } else {
            // Get the next available Dentist_ID
            $max_id_sql = "SELECT MAX(Dentist_ID) as max_id FROM dentists";
            $max_result = $conn->query($max_id_sql);
            $max_row = $max_result->fetch_assoc();
            $next_id = $max_row['max_id'] + 1;
            
            // Insert new dentist with manual ID calculation
            $insert_sql = "INSERT INTO dentists (Dentist_ID, name, username, email, phone, password, specialization, license_number, birthdate, age, gender, address, role) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'dentist')";
            $insert_stmt = $conn->prepare($insert_sql);
            $hashed_password = password_hash($dentistPassword, PASSWORD_DEFAULT);
            $insert_stmt->bind_param("issssssssiss", $next_id, $dentistFullName, $dentistUsername, $dentistEmail, $dentistPhone, $hashed_password, $specialization, $dentistLicense, $dentistDob, $dentistAge, $dentistGender, $dentistAddress);
            
if ($insert_stmt->execute()) {
    $dentist_id = $next_id;
    
    // ===== ADD THIS CODE: Insert into dentist_services table =====
    if (!empty($specialties)) {
        foreach ($specialties as $service_name) {
            // Get service ID from service name
            $service_sql = "SELECT service_ID FROM services WHERE service_name = ?";
            $service_stmt = $conn->prepare($service_sql);
            $service_stmt->bind_param("s", $service_name);
            $service_stmt->execute();
            $service_result = $service_stmt->get_result();
            
            if ($service_row = $service_result->fetch_assoc()) {
                $service_id = $service_row['service_ID'];
                
                // Insert into dentist_services
                $ds_sql = "INSERT INTO dentist_services (dentist_id, service_id) VALUES (?, ?)";
                $ds_stmt = $conn->prepare($ds_sql);
                $ds_stmt->bind_param("ii", $dentist_id, $service_id);
                $ds_stmt->execute();
                $ds_stmt->close();
            }
            $service_stmt->close();
        }
    }

// ===== FIXED: Generate availability based on form selection =====
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

// Define date range (generate for next 3 months)
$today = date('Y-m-d');
$endDate = date('Y-m-d', strtotime('+3 months'));

// Debug: Log availability data received
error_log("=== DENTIST AVAILABILITY DEBUG ===");
error_log("Dentist ID: " . $dentist_id);

$slots_created = 0;
$currentDate = $today;

while (strtotime($currentDate) <= strtotime($endDate)) {
    $currentDay = date('l', strtotime($currentDate));
    
    // Check if this day is selected in the form
    $dayField = "day_" . $currentDay;
    if (isset($_POST[$dayField]) && $_POST[$dayField] == '1') {
        // Get the start and end times from the form - FIXED LOGIC
        $startField = $currentDay . "_start";
        $endField = $currentDay . "_end";
        
        // FIX: Always use the form values, even if they appear to be "default"
        // The form will always submit the current values shown in the time inputs
        $startTimeValue = $_POST[$startField] ?? '09:00'; // Fallback to default
        $endTimeValue = $_POST[$endField] ?? '17:00';     // Fallback to default
        
        // Validate that we have time values
        if (!empty($startTimeValue) && !empty($endTimeValue)) {
            $startTime = strtotime($startTimeValue);
            $endTime = strtotime($endTimeValue);
            
            // Validate time range
            if ($startTime >= $endTime) {
                $error_message = "Error: End time must be after start time for " . $currentDay;
                break;
            }
            
            $interval = 30 * 60; // 30 minutes per slot
            
            // Generate time slots for this day
            for ($time = $startTime; $time < $endTime; $time += $interval) {
                $availableTime = date('H:i:s', $time);
                $endTimeSlot = date('H:i:s', $time + $interval);
                
                // Ensure we don't exceed the end time
                if (($time + $interval) > $endTime) {
                    break;
                }
                
                // Insert individual 30-minute slots into dentistavailability table
                $availability_sql = "INSERT INTO dentistavailability (Dentist_ID, available_date, available_time, day_of_week, end_time) 
                                    VALUES (?, ?, ?, ?, ?)";
                $availability_stmt = $conn->prepare($availability_sql);
                $availability_stmt->bind_param("issss", $dentist_id, $currentDate, $availableTime, $currentDay, $endTimeSlot);
                
                if ($availability_stmt->execute()) {
                    $slots_created++;
                } else {
                    error_log("Error inserting availability: " . $availability_stmt->error);
                    $error_message = "Error creating availability slots: " . $availability_stmt->error;
                }
                $availability_stmt->close();
            }
            
            error_log("Created slots for $currentDay ($currentDate): " . $startTimeValue . " to " . $endTimeValue);
        } else {
            error_log("Missing time values for $currentDay: start=$startTimeValue, end=$endTimeValue");
        }
    }
    $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
}

error_log("Total availability slots created for dentist $dentist_id: $slots_created");
// ===== END AVAILABILITY GENERATION =====



    if ($slots_created > 0) {
        $success_message = "Dentist added successfully with customized availability! (" . $slots_created . " slots created)";
    } else {
        $success_message = "Dentist added successfully, but no availability slots were created. Please check the availability settings.";
    }
} else {
    $error_message = "Error adding dentist: " . $insert_stmt->error;
}
            $insert_stmt->close();
        }
        $check_stmt->close();
    } catch (Exception $e) {
        $error_message = "Database error: " . $e->getMessage();
    }
}
}
    
    // Add Staff Form Submission
        elseif (isset($_POST['add_staff'])) {
        $staffFullName = trim($_POST['staffFullName']);
        $staffUsername = trim($_POST['staffUsername']);
        $staffDob = $_POST['staffDob'];
        $staffAge = (int)$_POST['staffAge'];
        $staffGender = $_POST['staffGender'];
        $staffEmail = trim($_POST['staffEmail']);
        $staffPhone = trim($_POST['staffPhone']);
        $staffPassword = $_POST['staffPassword'];
        $staffRole = $_POST['staffRole'];
        $staffAddress = trim($_POST['staffAddress']);
        $staffHireDate = isset($_POST['staffHireDate']) ? $_POST['staffHireDate'] : date('Y-m-d');
        
        // Handle availability data
        $availability_data = [];
        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
        
        foreach ($days as $day) {
            $day_field = "staff_day_" . $day;
            $start_field = "staff_" . $day . "_start";
            $end_field = "staff_" . $day . "_end";
            
            if (isset($_POST[$day_field]) && $_POST[$day_field] == '1') {
                $availability_data[$day] = [
                    'start' => $_POST[$start_field] . ':00',
                    'end' => $_POST[$end_field] . ':00'
                ];
            }
        }
        
        $availability_json = !empty($availability_data) ? json_encode($availability_data) : null;
        
        // Validate required fields
        if (empty($staffFullName) || empty($staffUsername) || empty($staffEmail) || empty($staffPhone) || empty($staffPassword) || empty($staffRole)) {
            $error_message = "Please fill in all required fields for the staff member.";
        } else {
            try {
                // Check if staff already exists
                $check_sql = "SELECT staff_id FROM clinic_staff WHERE email = ? OR username = ?";
                $check_stmt = $conn->prepare($check_sql);
                $check_stmt->bind_param("ss", $staffEmail, $staffUsername);
                $check_stmt->execute();
                $check_result = $check_stmt->get_result();
                
                if ($check_result->num_rows > 0) {
                    $error_message = "A staff member with this email or username already exists.";
                } else {
                    // Insert new staff with new columns
                    $insert_sql = "INSERT INTO clinic_staff (full_name, username, date_of_birth, age, gender, email, phone, password, staff_role, address, hire_date, availability) 
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $insert_stmt = $conn->prepare($insert_sql);
                    $hashed_password = password_hash($staffPassword, PASSWORD_DEFAULT);
                    $insert_stmt->bind_param("sssissssssss", $staffFullName, $staffUsername, $staffDob, $staffAge, $staffGender, $staffEmail, $staffPhone, $hashed_password, $staffRole, $staffAddress, $staffHireDate, $availability_json);
                    
                    if ($insert_stmt->execute()) {
                        $success_message = "Staff member added successfully!";
                    } else {
                        $error_message = "Error adding staff member: " . $insert_stmt->error;
                    }
                    $insert_stmt->close();
                }
                $check_stmt->close();
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Update Admin Details
    elseif (isset($_POST['update_admin'])) {
        $adminFullName = trim($_POST['adminFullName']);
        $adminUsername = trim($_POST['adminUsername']);
        $adminEmail = trim($_POST['adminEmail']);
        $adminPhone = trim($_POST['adminPhone']);
        $adminAddress = trim($_POST['adminAddress']);
        
        if (empty($adminFullName) || empty($adminUsername) || empty($adminEmail) || empty($adminPhone) || empty($adminAddress)) {
            $error_message = "Please fill in all required fields.";
        } else {
            try {
                $update_sql = "UPDATE clinic_staff SET full_name = ?, username = ?, email = ?, phone = ?, address = ? WHERE staff_id = ? AND staff_role = 'admin'";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sssssi", $adminFullName, $adminUsername, $adminEmail, $adminPhone, $adminAddress, $admin_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Admin details updated successfully!";
                } else {
                    $error_message = "Error updating admin details: " . $update_stmt->error;
                }
                $update_stmt->close();
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Update Admin Password
    elseif (isset($_POST['change_password'])) {
        $currentPassword = $_POST['currentPassword'];
        $newPassword = $_POST['newPassword'];
        $confirmPassword = $_POST['confirmPassword'];
        
        if (empty($currentPassword) || empty($newPassword) || empty($confirmPassword)) {
            $error_message = "Please fill in all password fields.";
        } elseif ($newPassword !== $confirmPassword) {
            $error_message = "New passwords do not match.";
        } else {
            // Get current admin user from clinic_staff table
            $admin_id = $_SESSION['user_id'];
            // FIX: Check for 'admin' role exactly as stored in database
            $check_sql = "SELECT password FROM clinic_staff WHERE staff_id = ? AND staff_role = 'admin'";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("i", $admin_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            
            if ($check_result->num_rows > 0) {
                $user = $check_result->fetch_assoc();
                if (password_verify($currentPassword, $user['password'])) {
                    // Update password
                    $hashed_password = password_hash($newPassword, PASSWORD_DEFAULT);
                    $update_sql = "UPDATE clinic_staff SET password = ? WHERE staff_id = ?";
                    $update_stmt = $conn->prepare($update_sql);
                    $update_stmt->bind_param("si", $hashed_password, $admin_id);
                    
                    if ($update_stmt->execute()) {
                        $success_message = "Password updated successfully!";
                    } else {
                        $error_message = "Error updating password.";
                    }
                    $update_stmt->close();
                } else {
                    $error_message = "Current password is incorrect.";
                }
            } else {
                $error_message = "Admin account not found.";
            }
            $check_stmt->close();
        }
    }
    
    // Service Management - Add Service
    elseif (isset($_POST['add_service'])) {
        $service_name = trim($_POST['service_name']);
        $service_duration = (int)$_POST['service_duration'];
        $service_price = floatval($_POST['service_price']);
        
        if (empty($service_name) || $service_duration <= 0 || $service_price < 0) {
            $error_message = "Please fill in all required service fields with valid values.";
        } else {
            try {
                $insert_sql = "INSERT INTO services (service_name, service_duration, price) VALUES (?, ?, ?)";
                $insert_stmt = $conn->prepare($insert_sql);
                $insert_stmt->bind_param("sid", $service_name, $service_duration, $service_price);
                
                if ($insert_stmt->execute()) {
                    $success_message = "Service added successfully!";
                } else {
                    $error_message = "Error adding service: " . $insert_stmt->error;
                }
                $insert_stmt->close();
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Service Management - Update Service
    elseif (isset($_POST['update_service'])) {
        $service_id = (int)$_POST['service_id'];
        $service_name = trim($_POST['service_name']);
        $service_duration = (int)$_POST['service_duration'];
        $service_price = floatval($_POST['service_price']);
        
        if ($service_id <= 0 || empty($service_name) || $service_duration <= 0 || $service_price < 0) {
            $error_message = "Please fill in all required service fields with valid values.";
        } else {
            try {
                $update_sql = "UPDATE services SET service_name = ?, service_duration = ?, price = ? WHERE service_ID = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("sidi", $service_name, $service_duration, $service_price, $service_id);
                
                if ($update_stmt->execute()) {
                    $success_message = "Service updated successfully!";
                } else {
                    $error_message = "Error updating service: " . $update_stmt->error;
                }
                $update_stmt->close();
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
    
    // Service Management - Remove Service
    elseif (isset($_POST['remove_service'])) {
        $service_id = (int)$_POST['service_id'];
        
        if ($service_id <= 0) {
            $error_message = "Invalid service ID.";
        } else {
            try {
                $delete_sql = "DELETE FROM services WHERE service_ID = ?";
                $delete_stmt = $conn->prepare($delete_sql);
                $delete_stmt->bind_param("i", $service_id);
                
                if ($delete_stmt->execute()) {
                    $success_message = "Service removed successfully!";
                } else {
                    $error_message = "Error removing service: " . $delete_stmt->error;
                }
                $delete_stmt->close();
            } catch (Exception $e) {
                $error_message = "Database error: " . $e->getMessage();
            }
        }
    }
}


// Delete Dentist
elseif (isset($_POST['delete_dentist'])) {
    $dentist_id = (int)$_POST['dentist_id'];
    
    if ($dentist_id <= 0) {
        $error_message = "Invalid dentist ID.";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // 1. Delete from specialization_requests table first (NEW)
            $delete_specialization_stmt = $conn->prepare("DELETE FROM specialization_requests WHERE dentist_id = ?");
            $delete_specialization_stmt->bind_param("i", $dentist_id);
            $delete_specialization_stmt->execute();
            $delete_specialization_stmt->close();
            
            // 2. Delete from dentist_services table
            $delete_services_stmt = $conn->prepare("DELETE FROM dentist_services WHERE dentist_id = ?");
            $delete_services_stmt->bind_param("i", $dentist_id);
            $delete_services_stmt->execute();
            $delete_services_stmt->close();
            
            // 3. Delete from dentistavailability table
            $delete_availability_stmt = $conn->prepare("DELETE FROM dentistavailability WHERE Dentist_ID = ?");
            $delete_availability_stmt->bind_param("i", $dentist_id);
            $delete_availability_stmt->execute();
            $delete_availability_stmt->close();

            // 4. Update appointments to remove dentist reference
            $update_appointments_stmt = $conn->prepare("UPDATE appointment SET Dentist_ID = NULL WHERE Dentist_ID = ?");
            $update_appointments_stmt->bind_param("i", $dentist_id);
            $update_appointments_stmt->execute();
            $update_appointments_stmt->close();
            
            // 5. Finally delete the dentist
            $delete_dentist_stmt = $conn->prepare("DELETE FROM dentists WHERE Dentist_ID = ?");
            $delete_dentist_stmt->bind_param("i", $dentist_id);
            
            if ($delete_dentist_stmt->execute()) {
                $conn->commit();
                $success_message = "Dentist deleted successfully!";
            } else {
                $conn->rollback();
                $error_message = "Error deleting dentist: " . $delete_dentist_stmt->error;
            }
            $delete_dentist_stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}


// Delete Staff
elseif (isset($_POST['delete_staff'])) {
    $staff_id = (int)$_POST['staff_id'];
    
    if ($staff_id <= 0) {
        $error_message = "Invalid staff ID.";
    } elseif ($staff_id == $_SESSION['user_id']) {
        $error_message = "You cannot delete your own account!";
    } else {
        try {
            // Start transaction
            $conn->begin_transaction();
            
            // 1. Update appointments to remove admin reference if this staff was an admin
            $update_appointments_stmt = $conn->prepare("UPDATE appointment SET Admin_ID = NULL WHERE Admin_ID = ?");
            $update_appointments_stmt->bind_param("i", $staff_id);
            $update_appointments_stmt->execute();
            $update_appointments_stmt->close();
            
            // 2. Delete the staff member
            $delete_staff_stmt = $conn->prepare("DELETE FROM clinic_staff WHERE staff_id = ?");
            $delete_staff_stmt->bind_param("i", $staff_id);
            
            if ($delete_staff_stmt->execute()) {
                $conn->commit();
                $success_message = "Staff member deleted successfully!";
            } else {
                $conn->rollback();
                $error_message = "Error deleting staff member: " . $delete_staff_stmt->error;
            }
            $delete_staff_stmt->close();
            
        } catch (Exception $e) {
            $conn->rollback();
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Get current admin data for auto-filling - MOVED HERE AFTER ALL POST HANDLING
$admin_id = $_SESSION['user_id'];
$admin_data = [];
$admin_availability = [];

// FIX: Fetch admin data from clinic_staff table with exact 'admin' role match
$admin_sql = "SELECT * FROM clinic_staff WHERE staff_id = ? AND staff_role = 'admin'";
$admin_stmt = $conn->prepare($admin_sql);
$admin_stmt->bind_param("i", $admin_id);
$admin_stmt->execute();
$admin_result = $admin_stmt->get_result();

if ($admin_result && $admin_result->num_rows > 0) {
    $admin_data = $admin_result->fetch_assoc();
    
    // Decode availability JSON if exists
    if (!empty($admin_data['availability'])) {
        $admin_availability = json_decode($admin_data['availability'], true);
    }
}
$admin_stmt->close();

// Fetch services for display
$services = [];
$services_sql = "SELECT * FROM services ORDER BY service_name";
$services_result = $conn->query($services_sql);
if ($services_result && $services_result->num_rows > 0) {
    while ($service = $services_result->fetch_assoc()) {
        $services[] = $service;
    }
}

// Fetch dentists and staff for profiles display
$dentists = [];
$staff_members = [];

$dentists_sql = "SELECT * FROM dentists ORDER BY name";
$dentists_result = $conn->query($dentists_sql);
if ($dentists_result && $dentists_result->num_rows > 0) {
    while ($dentist = $dentists_result->fetch_assoc()) {
        $dentists[] = $dentist;
    }
}

// FIX: Include admin accounts in staff display by removing the role filter
$staff_sql = "SELECT * FROM clinic_staff ORDER BY full_name";
$staff_result = $conn->query($staff_sql);
if ($staff_result && $staff_result->num_rows > 0) {
    while ($staff = $staff_result->fetch_assoc()) {
        $staff_members[] = $staff;
    }
}

// Function to format duration in minutes to hours and minutes
function formatDuration($minutes) {
    if ($minutes < 60) {
        return $minutes . ' min';
    } else {
        $hours = floor($minutes / 60);
        $remaining_minutes = $minutes % 60;
        if ($remaining_minutes > 0) {
            return $hours . 'h ' . $remaining_minutes . 'm';
        } else {
            return $hours . 'h';
        }
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
    <link rel="stylesheet" href="notifications/notification_style.css">

    <style>
        :root {
            --bg-color: #f5f5f5;
            --text-color: #333;
            --card-bg: #fff;
            --border-color: #ddd;
            --accent-color: #4285f4;
        }

        [data-theme="dark"] {
            --bg-color: #1a1a1a;
            --text-color: #f0f0f0;
            --card-bg: #2d2d2d;
            --border-color: #444;
            --accent-color: #8ab4f8;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: #f0f2f5;
            color: #333;
            margin: 0;
            padding: 20px;
            transition: background-color 0.3s, color 0.3s;
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 20px;
            background-color: #ecf5ff;
            width: 100%;
            box-sizing: border-box;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: 56px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            z-index: 1100;
        }

        .logo-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .logo-circle {
            width: 40px;
            height: 40px;
            background-color: rgba(255, 255, 255, 0.9);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }

        .logo-circle img {
            width: 45px;
            height: 45px;
            object-fit: contain;
        }

        .clinic-info h1 {
            font-size: 15px;
            text-align: left;
            color: #333;
            margin: 0 0 5px 0;
        }

        .clinic-info p {
            font-size: 10px;
            color: #555;
            margin: 0;
        }

        .header-right {
            display: flex;
            gap: 20px;
            margin-right: 10px;
            align-items: center;
        }

        .auth-link {
            text-decoration: none;
            color: #0066cc;
            font-weight: 600;
            font-size: 12px;
            transition: color 0.3s;
        }

        .auth-link:hover {
            color: #003d80;
        }

        .header-right span {
            color: black;
            font-size: 10px;
        }

        .welcome-text {
            font-weight: 700;
            font-size: 12px;
            color: #003366;
        }

        .welcome-text .auth-link {
            font-weight: 600;
            color: #0066cc;
            text-decoration: none;
        }

        .welcome-text .auth-link:hover {
            color: #003d80;
        }

        /* Sidebar */
        .sidebar {
            position: fixed;
            top: 56px;
            left: -260px;
            width: 30vw;
            max-width: 350px;
            min-width: 200px;
            height: calc(100vh - 56px);
            background-color: #6b839e;
            color: white;
            padding: 20px 0;
            transition: left 0.6s ease;
            z-index: 1000;
            overflow-x: hidden;
        }

        .sidebar:hover {
            left: 0;
        }

        .menu-icon {
            position: fixed;
            left: 20px;
            top: 90px;
            transform: translateY(-50%);
            font-size: 24px;
            background-color: #6b839e;
            color: white;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: opacity 0.3s;
        }

        .sidebar:hover .menu-icon {
            display: none;
        }

        .nav-menu {
            list-style: none;
            margin-top: 40px;
        }

        .nav-item {
            padding: 12px 20px;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .nav-item:hover {
            background-color: rgba(255, 255, 255, 0.1);
        }

        .nav-item span {
            display: none;
            white-space: nowrap;
        }

        .sidebar:hover .nav-item span {
            display: inline;
        }

        .nav-link {
            text-decoration: none;
            color: white;
            cursor: pointer;
            transition: background-color 0.3s;
        }

        .settings-container {
            max-width: 900px;
            margin: 0 auto;
            background-color: var(--card-bg);
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }

        .availability-info {
            margin-top: 30px;
            padding: 20px;
            background-color: #e8f5e9;
            border-radius: 8px;
            border: 1px solid #c8e6c9;
        }

        h1, h2, h3 {
            color: var(--accent-color);
            margin-top: 0;
            margin-bottom: 15px;
        }

        /* Tabbed Layout */
        .tabbed-layout {
            display: flex;
            gap: 20px;
            min-height: 500px;
        }

        .category-column {
            width: 200px;
            border-right: 1px solid var(--border-color);
            padding-right: 10px;
        }

        .category-tab {
            display: block;
            width: 100%;
            padding: 12px;
            margin-bottom: 10px;
            text-align: left;
            border: none;
            background: none;
            cursor: pointer;
            border-radius: 5px;
            transition: 0.3s;
        }

        .category-tab:hover {
            background-color: rgba(0, 0, 0, 0.05);
        }

        .category-tab.active {
            background-color: var(--accent-color);
            color: white;
            font-weight: 800;
        }

        .content-column {
            flex: 1;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        /* Clinic Operations Tabs */
        .clinic-operations-tabs {
            display: flex;
            margin-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .clinic-tab {
            padding: 10px 20px;
            cursor: pointer;
            border: none;
            background: none;
            border-bottom: 2px solid transparent;
            transition: 0.3s;
        }

        .clinic-tab.active {
            border-bottom: 2px solid var(--accent-color);
            color: var(--accent-color);
            font-weight: 600;
        }

        .clinic-tab-content {
            display: none;
        }

        .clinic-tab-content.active {
            display: block;
        }

        /* Services Table */
        .services-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }

        .services-table th,
        .services-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }

        .services-table th {
            background-color: #f8f9fa;
            font-weight: 600;
        }

        .services-table tr:hover {
            background-color: #f8f9fa;
        }

        .service-actions {
            display: flex;
            gap: 8px;
        }

        .service-actions .btn {
            flex: 1;
            text-align: center;
            padding: 6px 10px;
            font-size: 11px;
        }

        .btn {
            background-color: var(--accent-color);
            color: white;
            border: none;
            padding: 8px 12px;
            border-radius: 5px;
            cursor: pointer;
            transition: 0.3s;
            font-size: 12px;
        }

        .btn:hover {
            opacity: 0.9;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .btn-edit {
            background-color: #28a745;
        }

        .btn-edit:hover {
            background-color: #218838;
        }

        .btn-save {
            background-color: #17a2b8;
        }

        .btn-save:hover {
            background-color: #138496;
        }

        .btn-cancel {
            background-color: #6c757d;
        }

        .btn-cancel:hover {
            background-color: #5a6268;
        }

        /* Operating Hours */
        .operating-hours-container {
            margin: 20px 0;
        }

        .day-operating-hours {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            margin-bottom: 10px;
            background-color: #f9f9f9;
        }

        .day-operating-hours label {
            min-width: 100px;
            margin: 0;
        }

        .time-range {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .closed-checkbox {
            margin-left: auto;
        }

        /* Profiles Display */
        .profiles-container {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
        }

        .profile-card {
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 15px;
            background-color: var(--card-bg);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .profile-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 12px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }

        .profile-name {
            font-size: 16px;
            font-weight: 600;
            color: var(--accent-color);
        }

        .profile-role {
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 500;
        }

        .role-dentist {
            background-color: #3498db;
        }

        .role-admin {
            background-color: #e74c3c;
        }

        .role-receptionist {
            background-color: #2ecc71;
        }

        .role-dental_assistant {
            background-color: #f39c12;
        }

        .role-technician {
            background-color: #9b59b6;
        }

        .profile-details {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 8px;
        }

        .profile-detail {
            margin-bottom: 6px;
            font-size: 13px;
        }

        .profile-detail strong {
            color: var(--text-color);
        }

        /* Account Recovery Toggle Buttons */
        .user-type-toggle {
            display: flex;
            margin-bottom: 15px;
            gap: 10px;
        }

        .user-type-toggle button {
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            background-color: var(--border-color);
            cursor: pointer;
            transition: 0.3s;
        }

        .user-type-toggle button.active {
            background-color: var(--accent-color);
            color: white;
        }

        /* Forms */
        .recovery-form, .add-form {
            margin-top: 15px;
        }

        .form-group {
            margin-bottom: 15px;
        }

        label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        input[type="email"],
        input[type="text"],
        input[type="password"],
        input[type="tel"],
        input[type="date"],
        input[type="number"],
        select,
        textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: var(--card-bg);
            color: var(--text-color);
        }

        input[type="date"] {
            padding: 7px;
            cursor: pointer;
        }

        .form-row {
            display: flex;
            gap: 15px;
        }

        .form-row .form-group {
            flex: 1;
        }

        /* Improved Specialty Container with 2-column layout */
        .specialty-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 10px;
            max-height: 200px;
            overflow-y: auto;
            padding: 15px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .specialty-checkbox {
            display: flex;
            align-items: center;
            padding: 8px 12px;
            background: white;
            border: 1px solid #e0e0e0;
            border-radius: 4px;
            transition: all 0.3s ease;
        }

        .specialty-checkbox:hover {
            background-color: #f0f8ff;
            border-color: var(--accent-color);
        }

        .specialty-checkbox input {
            margin-right: 8px;
            transform: scale(1.1);
        }

        .staff-role-container {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-top: 10px;
        }

        .staff-role-radio {
            display: flex;
            align-items: center;
        }

        .staff-role-radio input {
            margin-right: 5px;
        }

        .admin-details {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }

        .admin-details p {
            margin-bottom: 10px;
        }

        .admin-details strong {
            color: var(--accent-color);
        }

        .system-info {
            margin-top: 20px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }

        .system-info h3 {
            margin-bottom: 10px;
        }

        .system-info p {
            margin-bottom: 8px;
        }

        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            margin: 20px auto;
            max-width: 900px;
            border-radius: 5px;
            border: 1px solid #c3e6cb;
        }

        .error-message {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            margin: 20px auto;
            max-width: 900px;
            border-radius: 5px;
            border: 1px solid #f5c6cb;
        }

        .weekly-availability {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 15px;
        }

        .day-availability {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 10px;
            border: 1px solid var(--border-color);
            border-radius: 5px;
            background-color: #f9f9f9;
        }

        .time-range {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .time-range input[type="time"] {
            width: 120px;
        }
        
        .password-row {
            display: flex;
            gap: 15px;
        }
        
        .password-row .form-group {
            flex: 1;
        }
        
        .availability-section {
            margin-top: 30px;
            padding: 20px;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            background-color: #f8f9fa;
        }

        .services-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .editable-input {
            width: 100%;
            padding: 4px 8px;
            border: 1px solid #ddd;
            border-radius: 3px;
            background-color: white;
        }

        .new-service-row {
            background-color: #f8f9fa !important;
        }

        .new-service-row td {
            padding: 8px 12px;
        }

        .admin-form-section {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<?php if ($success_message): ?>
    <div class="success-message">✅ <?php echo htmlspecialchars($success_message); ?></div>
<?php endif; ?>

<?php if ($error_message): ?>
    <div class="error-message">❌ <?php echo htmlspecialchars($error_message); ?></div>
<?php endif; ?>

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
            </ul>
        </div>

        <h2 style="color: royalblue; font-size: 33px; margin-left: 130px; margin-top: 80px; margin-bottom: 50px;">System Settings</h2>

<div class="settings-container">
    <div class="tabbed-layout">
        <div class="category-column">
            <button class="category-tab active" data-target="account">Account Management</button>
            <button class="category-tab" data-target="clinic-operations">Clinic Operations</button>
            <button class="category-tab" data-target="backups">Backups</button>
        </div>
        <div class="content-column">
            <div id="account" class="content-section active">
                <h2 style="color: royalblue; margin-bottom: 30px;">Account Management</h2>
                <div class="user-type-toggle">
                    <button class="active" id="adminBtn" style="font-weight: 500;">Admin (You)</button>
                    <button id="addDentistBtn" style="font-weight: 500;">Add Dentist</button>
                    <button id="addStaffBtn" style="font-weight: 500;">Add Staff</button>
                </div>

                <!-- Admin Form -->
                <div id="adminForm" class="recovery-form">
                    <div class="admin-form-section">
                        <h3 style="color: royalblue; margin-bottom: 20px;">Admin Profile</h3>
                        <form id="adminDetailsForm" method="POST">
                            <input type="hidden" name="update_admin" value="1">
                            <div class="form-group">
                                <label for="adminFullName">Full Name</label>
                                <input type="text" id="adminFullName" name="adminFullName" placeholder="Jairus Umipig" required 
                                       value="<?php echo htmlspecialchars($admin_data['full_name'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="adminUsername">Username</label>
                                <input type="text" id="adminUsername" name="adminUsername" placeholder="ADMIN" required 
                                       value="<?php echo htmlspecialchars($admin_data['username'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="adminEmail">Admin Email</label>
                                <input type="email" id="adminEmail" name="adminEmail" placeholder="umipigdentalclinic@gmail.com" required 
                                       value="<?php echo htmlspecialchars($admin_data['email'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="adminPhone">Phone Number</label>
                                <input type="tel" id="adminPhone" name="adminPhone" placeholder="09123456789" required 
                                       value="<?php echo htmlspecialchars($admin_data['phone'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="adminAddress">Address</label>
                                <input type="text" id="adminAddress" name="adminAddress" placeholder="Village East..." required 
                                       value="<?php echo htmlspecialchars($admin_data['address'] ?? ''); ?>">
                            </div>
                            <div class="form-group">
                                <label for="adminRole">Role</label>
                                <input type="text" id="adminRole" placeholder="Administrator" readonly disabled
                                       value="<?php echo htmlspecialchars($admin_data['staff_role'] ?? 'Administrator'); ?>">
                            </div>

                            <button type="submit" class="btn" style="margin-top: 20px;">Save Changes</button>
                        </form>
                    </div>

                    <!-- Admin Availability Section -->
                    <div class="availability-section">
                        <h2 style="color: royalblue; margin-bottom: 30px;">Admin Availability</h2>
                        <div class="availability-instructions">
                            <p><strong>Instructions:</strong> Check the days you're available and set your working hours.</p>
                        </div>
                        <div class="weekly-availability">
                            <?php 
                            $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                            $default_times = [
                                'Monday' => ['09:00', '17:00'],
                                'Tuesday' => ['09:00', '17:00'],
                                'Wednesday' => ['09:00', '17:00'],
                                'Thursday' => ['09:00', '17:00'],
                                'Friday' => ['09:00', '17:00'],
                                'Saturday' => ['09:00', '17:00']
                            ];
                            
                            foreach ($days as $day):
                                $is_checked = true;
                                $start_time = $default_times[$day][0];
                                $end_time = $default_times[$day][1];
                                
                                if (is_array($admin_availability) && isset($admin_availability[$day])) {
                                    $is_checked = true;
                                    $start_time = substr($admin_availability[$day]['start'], 0, 5);
                                    $end_time = substr($admin_availability[$day]['end'], 0, 5);
                                }
                            ?>
                                <div class="day-availability">
                                    <label style="min-width: 100px;">
                                        <input type="checkbox" name="admin_day_<?php echo $day; ?>" value="1" <?php echo $is_checked ? 'checked' : ''; ?>>
                                        <?php echo $day; ?>
                                    </label>
                                    <div class="time-range">
                                        <input type="time" name="admin_<?php echo $day; ?>_start" value="<?php echo $start_time; ?>">
                                        <span>to</span>
                                        <input type="time" name="admin_<?php echo $day; ?>_end" value="<?php echo $end_time; ?>">
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <button type="submit" class="btn" style="margin-top: 20px;">Update Availability</button>
                    </div>

                    <div class="change-password" style="margin-top: 30px;">
                        <h3 style="color: royalblue; margin-bottom: 40px; margin-top: 80px;">Change Password</h3>
                        <form id="passwordForm" method="POST">
                            <input type="hidden" name="change_password" value="1">
                            <div class="form-group"><label for="currentPassword">Current Password</label><input type="password" id="currentPassword" name="currentPassword" required></div>
                            <div class="form-group"><label for="newPassword">New Password</label><input type="password" id="newPassword" name="newPassword" required></div>
                            <div class="form-group"><label for="confirmPassword">Confirm Password</label><input type="password" id="confirmPassword" name="confirmPassword" required></div>
                            <button type="submit" class="btn" style="margin-bottom: 30px; margin-top: 30px;">Update Password</button>
                        </form>
                    </div>

                    <!-- Deactivate Account Section -->
                    <div class="deactivate-section">
                        <h3 style="color: #721c24;">Deactivate Account</h3>
                        <p class="deactivate-warning">⚠️ Warning: This action cannot be undone. Your account will be permanently deactivated.</p>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to deactivate your account? This action cannot be undone.');">
                            <input type="hidden" name="deactivate_admin" value="1">
                            <button type="submit" class="btn btn-danger">Deactivate My Account</button>
                        </form>
                    </div>
                </div> <!-- #adminForm -->

            </div> <!-- #account -->

            <!-- Clinic Operations Section -->
            <div id="clinic-operations" class="content-section">
                <h2 style="color: royalblue; margin-bottom: 30px;">Clinic Operations</h2>
                
                <div class="clinic-operations-tabs">
                    <button class="clinic-tab active" data-target="services-management">Services Management</button>
                    <button class="clinic-tab" data-target="staff-profiles">Staff Profiles</button>
                    <button class="clinic-tab" data-target="future-section">Future Section</button>
                </div>

                <!-- Services Management Tab -->
                <div id="services-management" class="clinic-tab-content active">
                    <div class="services-header">
                        <h3 style="color: royalblue; margin-bottom: 20px;">Services Management</h3>
                        <button type="button" class="btn" id="addServiceBtn">Add Service</button>
                    </div>
                    
                    <!-- Services List -->
                    <h4>Current Services</h4>
                    <?php if (!empty($services)): ?>
                        <table class="services-table">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Duration</th>
                                    <th>Price (₱)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="servicesTableBody">
                                <?php foreach ($services as $service): ?>
                                    <tr id="serviceRow<?php echo $service['service_ID']; ?>">
                                        <td>
                                            <span class="service-name"><?php echo htmlspecialchars($service['service_name']); ?></span>
                                            <input type="text" class="editable-input service-name-input" style="display: none;" 
                                                   value="<?php echo htmlspecialchars($service['service_name']); ?>">
                                        </td>
                                        <td>
                                            <span class="service-duration"><?php echo formatDuration($service['service_duration']); ?></span>
                                            <input type="number" class="editable-input service-duration-input" style="display: none;" 
                                                   value="<?php echo htmlspecialchars($service['service_duration']); ?>" min="1">
                                        </td>
                                        <td>
                                            <span class="service-price">₱<?php echo number_format($service['price'], 2); ?></span>
                                            <input type="number" class="editable-input service-price-input" style="display: none;" 
                                                   value="<?php echo htmlspecialchars($service['price']); ?>" step="0.01" min="0">
                                        </td>
                                        <td class="service-actions">
                                            <div class="view-actions">
                                                <button type="button" class="btn btn-edit" onclick="enableEdit(<?php echo $service['service_ID']; ?>)">Edit</button>
                                                <form method="POST" style="display: inline;" onsubmit="return confirm('⚠️ Are you sure you want to remove this service? This action cannot be undone and may affect existing appointments.');">
                                                    <input type="hidden" name="remove_service" value="1">
                                                    <input type="hidden" name="service_id" value="<?php echo $service['service_ID']; ?>">
                                                    <button type="submit" class="btn btn-danger">Remove</button>
                                                </form>
                                            </div>
                                            <div class="edit-actions" style="display: none;">
                                                <button type="button" class="btn btn-save" onclick="saveService(<?php echo $service['service_ID']; ?>)">Save</button>
                                                <button type="button" class="btn btn-cancel" onclick="cancelEdit(<?php echo $service['service_ID']; ?>)">Cancel</button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <!-- New Service Row (hidden by default) -->
                                <tr id="newServiceRow" class="new-service-row" style="display: none;">
                                    <td>
                                        <input type="text" class="editable-input" id="newServiceName" placeholder="Service Name">
                                    </td>
                                    <td>
                                        <input type="number" class="editable-input" id="newServiceDuration" placeholder="Duration" min="1">
                                    </td>
                                    <td>
                                        <input type="number" class="editable-input" id="newServicePrice" placeholder="Price" step="0.01" min="0">
                                    </td>
                                    <td class="service-actions">
                                        <button type="button" class="btn btn-save" onclick="saveNewService()">Save</button>
                                        <button type="button" class="btn btn-cancel" onclick="cancelNewService()">Cancel</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php else: ?>
                        <p>No services found.</p>
                        <table class="services-table">
                            <thead>
                                <tr>
                                    <th>Service Name</th>
                                    <th>Duration</th>
                                    <th>Price (₱)</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="servicesTableBody">
                                <!-- New Service Row (hidden by default) -->
                                <tr id="newServiceRow" class="new-service-row" style="display: none;">
                                    <td>
                                        <input type="text" class="editable-input" id="newServiceName" placeholder="Service Name">
                                    </td>
                                    <td>
                                        <input type="number" class="editable-input" id="newServiceDuration" placeholder="Duration" min="1">
                                    </td>
                                    <td>
                                        <input type="number" class="editable-input" id="newServicePrice" placeholder="Price" step="0.01" min="0">
                                    </td>
                                    <td class="service-actions">
                                        <button type="button" class="btn btn-save" onclick="saveNewService()">Save</button>
                                        <button type="button" class="btn btn-cancel" onclick="cancelNewService()">Cancel</button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>

                <!-- Staff Profiles Tab -->
                <div id="staff-profiles" class="clinic-tab-content">
                    <h3 style="color: royalblue; margin-bottom: 20px;">Dentists & Staff Profiles</h3>
                    
                <!-- Dentists Section -->
                <h4 style="margin-top: 30px; margin-bottom: 20px;">Dentists</h4>
                <div class="profiles-container">
                    <?php if (!empty($dentists)): ?>
                        <?php foreach ($dentists as $dentist): 
                // Get dentist availability as ranges - FIXED VERSION
                $dentist_availability = [];
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
                $availability_stmt->bind_param("i", $dentist['Dentist_ID']);
                $availability_stmt->execute();
                $availability_result = $availability_stmt->get_result();

                // Group by day and find time ranges - FIXED LOGIC
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
                        
                        $dentist_availability[$day] = "$earliest_start_formatted - $latest_end_formatted";
                    }
                }
                $availability_stmt->close();
        ?>
            <div class="profile-card" id="dentist-<?php echo $dentist['Dentist_ID']; ?>">
                <div class="profile-header">
                    <div class="profile-name">Dr. <?php echo htmlspecialchars($dentist['name']); ?></div>
                    <div class="profile-role role-dentist">Dentist</div>
                </div>
                <div class="profile-details">
                    <div class="profile-detail"><strong>Username:</strong> <?php echo htmlspecialchars($dentist['username']); ?></div>
                    <div class="profile-detail"><strong>Email:</strong> <?php echo htmlspecialchars($dentist['email']); ?></div>
                    <div class="profile-detail"><strong>Phone:</strong> <?php echo htmlspecialchars($dentist['phone']); ?></div>
                    <div class="profile-detail"><strong>License:</strong> <?php echo htmlspecialchars($dentist['license_number']); ?></div>
                    <div class="profile-detail"><strong>Specialization:</strong> <?php echo htmlspecialchars($dentist['specialization'] ?? 'General Dentistry'); ?></div>
                    <div class="profile-detail"><strong>Gender:</strong> <?php echo htmlspecialchars($dentist['gender'] ?? 'N/A'); ?></div>
                    <div class="profile-detail"><strong>Date of Birth:</strong> <?php echo !empty($dentist['birthdate']) ? htmlspecialchars($dentist['birthdate']) : 'N/A'; ?></div>
                    <div class="profile-detail"><strong>Age:</strong> <?php echo htmlspecialchars($dentist['age'] ?? 'N/A'); ?></div>
                    <div class="profile-detail"><strong>Address:</strong> <?php echo htmlspecialchars($dentist['address'] ?? 'N/A'); ?></div>
                    
                    <!-- Availability Display - Now in clean range format -->
                    <div class="profile-detail" style="grid-column: 1 / -1;">
                        <strong>Availability:</strong>
                        <?php if (!empty($dentist_availability)): ?>
                            <div style="margin-top: 5px; font-size: 12px;">
                                <?php 
                                $days_order = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
                                foreach ($days_order as $day): 
                                    if (isset($dentist_availability[$day])): 
                                ?>
                                        <div><strong><?php echo htmlspecialchars($day); ?>:</strong> 
                                            <?php echo htmlspecialchars($dentist_availability[$day]); ?>
                                        </div>
                                <?php 
                                    endif;
                                endforeach;  
                                ?>
                            </div>
                        <?php else: ?>
                            <span style="color: #666;">No availability set</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="profile-actions" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                    <form method="POST" onsubmit="return confirmDelete('dentist', 'Dr. <?php echo htmlspecialchars(addslashes($dentist['name'])); ?>')">
                        <input type="hidden" name="delete_dentist" value="1">
                        <input type="hidden" name="dentist_id" value="<?php echo $dentist['Dentist_ID']; ?>">
                        <button type="submit" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">
                            <i class="fas fa-trash"></i> Delete Dentist
                        </button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p>No dentists found.</p>
    <?php endif; ?>
</div>



    <!-- Staff Section (Now includes Admin accounts) -->
    <h4 style="margin-top: 30px; margin-bottom: 20px;">Staff Members</h4>
    <div class="profiles-container">
        <?php if (!empty($staff_members)): ?>
            <?php foreach ($staff_members as $staff): 
                // Decode staff availability
                $staff_availability = [];
                if (!empty($staff['availability'])) {
                    $staff_availability = json_decode($staff['availability'], true);
                }
            ?>
                <div class="profile-card" id="staff-<?php echo $staff['staff_id']; ?>">
                    <div class="profile-header">
                        <div class="profile-name"><?php echo htmlspecialchars($staff['full_name']); ?></div>
                        <div class="profile-role role-<?php echo htmlspecialchars($staff['staff_role']); ?>">
                            <?php echo ucfirst(htmlspecialchars($staff['staff_role'])); ?>
                        </div>
                    </div>
                    <div class="profile-details">
                        <div class="profile-detail"><strong>Username:</strong> <?php echo htmlspecialchars($staff['username']); ?></div>
                        <div class="profile-detail"><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></div>
                        <div class="profile-detail"><strong>Phone:</strong> <?php echo htmlspecialchars($staff['phone']); ?></div>
                        <div class="profile-detail"><strong>Role:</strong> <?php echo ucfirst(htmlspecialchars($staff['staff_role'])); ?></div>
                        <div class="profile-detail"><strong>Gender:</strong> <?php echo htmlspecialchars($staff['gender'] ?? 'N/A'); ?></div>
                        <div class="profile-detail"><strong>Date of Birth:</strong> <?php echo !empty($staff['date_of_birth']) ? htmlspecialchars($staff['date_of_birth']) : 'N/A'; ?></div>
                        <div class="profile-detail"><strong>Age:</strong> <?php echo htmlspecialchars($staff['age'] ?? 'N/A'); ?></div>
                        <div class="profile-detail"><strong>Hire Date:</strong> <?php echo htmlspecialchars($staff['hire_date'] ?? 'N/A'); ?></div>
                        <div class="profile-detail"><strong>Address:</strong> <?php echo htmlspecialchars($staff['address'] ?? 'N/A'); ?></div>
                        
                        <!-- Staff Availability Display -->
                        <div class="profile-detail" style="grid-column: 1 / -1;">
                            <strong>Availability:</strong>
                            <?php if (!empty($staff_availability) && is_array($staff_availability)): ?>
                                <div style="margin-top: 5px; font-size: 12px;">
                                    <?php foreach ($staff_availability as $day => $times): ?>
                                        <div><strong><?php echo htmlspecialchars($day); ?>:</strong> 
                                            <?php 
                                            $start = isset($times['start']) ? date('g:i A', strtotime($times['start'])) : 'N/A';
                                            $end = isset($times['end']) ? date('g:i A', strtotime($times['end'])) : 'N/A';
                                            echo htmlspecialchars("$start - $end"); 
                                            ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #666;">No availability set</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="profile-actions" style="margin-top: 15px; border-top: 1px solid #eee; padding-top: 10px;">
                        <!-- Don't show delete button for the currently logged-in admin -->
                        <?php if ($staff['staff_id'] != $_SESSION['user_id']): ?>
                            <form method="POST" onsubmit="return confirmDelete('staff', '<?php echo htmlspecialchars(addslashes($staff['full_name'])); ?>')">
                                <input type="hidden" name="delete_staff" value="1">
                                <input type="hidden" name="staff_id" value="<?php echo $staff['staff_id']; ?>">
                                <button type="submit" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px;">
                                    <i class="fas fa-trash"></i> Delete Staff
                                </button>
                            </form>
                        <?php else: ?>
                            <button type="button" class="btn btn-danger" style="font-size: 12px; padding: 6px 12px; opacity: 0.5; cursor: not-allowed;" disabled>
                                <i class="fas fa-trash"></i> Cannot Delete Yourself
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>No staff members found.</p>
        <?php endif; ?>
    </div>
</div>

                <!-- Future Section Tab -->
                <div id="future-section" class="clinic-tab-content">
                    <h3 style="color: royalblue; margin-bottom: 20px;">Future Section</h3>
                    <p>This section is prepared for future frontend and backend changes.</p>
                    <p>Content will be added here as needed for additional clinic operations features.</p>
                </div>
            </div> <!-- #clinic-operations -->

            <div id="backups" class="content-section">
                <h2 style="color: royalblue; margin-top: 20px; margin-bottom: 50px;">Backup Settings</h2>
                <div class="form-group">
                    <label for="backupSchedule">Backup Schedule</label>
                    <select id="backupSchedule" style="margin-bottom: 20px;">
                        <option value="daily">Daily</option>
                        <option value="weekly">Weekly</option>
                        <option value="monthly">Monthly</option>
                    </select>
                </div>
                <div class="form-group">
                    <p><strong>Last Backup:</strong> July 15, 2025 at 12:00 AM</p>
                    <p><strong>Status:</strong> - </p>
                </div>
                <div class="backup-buttons" style="margin-top: 30px;">
                    <button type="button" class="btn" id="backupNowBtn" style="width: 20%; border-radius:5px;">Backup Now</button>
                    <button type="button" class="btn" id="downloadBackupBtn" style="width: 30%; border-radius:5px;">Download Latest Backup</button>
                    <button type="button" class="btn" id="restoreBackupBtn" style="width: 20%; border-radius:5px;">Restore Backup</button>
                </div>
            </div> <!-- #backups -->

            <!-- Add Dentist Form -->
            <form id="addDentistForm" class="add-form" style="display: none;" method="POST">
                <input type="hidden" name="add_dentist" value="1">
                <input type="hidden" name="update_availability" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="dentistFullName">Full Name</label>
                        <input type="text" id="dentistFullName" name="dentistFullName" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label for="dentistUsername">Username</label>
                        <input type="text" id="dentistUsername" name="dentistUsername" placeholder="Username" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dentistDob">Date of Birth</label>
                        <input type="date" id="dentistDob" name="dentistDob" required>
                    </div>
                    <div class="form-group">
                        <label for="dentistAge">Age</label>
                        <input type="number" id="dentistAge" name="dentistAge" placeholder="Age" min="18" max="99" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dentistGender">Gender</label>
                        <select id="dentistGender" name="dentistGender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="dentistHireDate">Hire Date</label>
                        <input type="date" id="dentistHireDate" name="dentistHireDate" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="dentistEmail">Email</label>
                        <input type="email" id="dentistEmail" name="dentistEmail" placeholder="dentist@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="dentistPhone">Phone Number</label>
                        <input type="tel" id="dentistPhone" name="dentistPhone" placeholder="09123456789" required>
                    </div>
                </div>

                <div class="password-row">
                    <div class="form-group">
                        <label for="dentistPassword">Password</label>
                        <input type="password" id="dentistPassword" name="dentistPassword" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <label for="dentistConfirmPassword">Confirm Password</label>
                        <input type="password" id="dentistConfirmPassword" name="dentistConfirmPassword" placeholder="Confirm Password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Specialties</label>
                    <div class="specialty-container">
                        <?php
                        $serviceQuery = "SELECT service_ID, service_name FROM services ORDER BY service_name";
                        $serviceResult = mysqli_query($conn, $serviceQuery);

                        if ($serviceResult && mysqli_num_rows($serviceResult) > 0) {
                            while ($service = mysqli_fetch_assoc($serviceResult)) {
                                $sid = (int)$service['service_ID'];
                                $sname = htmlspecialchars($service['service_name']);
                                
                                echo "
                                <label class='specialty-checkbox'>
                                    <input type='checkbox' name='specialty[]' value='{$sname}'> {$sname}
                                </label>
                                ";
                            }
                        } else {
                            echo "<p>No services found in the database.</p>";
                        }
                        ?>
                    </div>
                </div>

                <div class="form-group">
                    <label for="dentistLicense">License Number</label>
                    <input type="text" id="dentistLicense" name="dentistLicense" placeholder="PRC License Number" required>
                </div>

                <div class="form-group">
                    <label for="dentistAddress">Address</label>
                    <textarea id="dentistAddress" name="dentistAddress" placeholder="Full Address" rows="3" required></textarea>
                </div>

                <!-- Complete Availability Section -->
                <div class="availability-section">
                    <h2 style="color: royalblue; margin-bottom: 30px;">Availability</h2>
                    <div class="availability-instructions">
                        <p><strong>Instructions:</strong> Check the days you're available and set your working hours. These will be applied to all future dates until you update them again.</p>
                    </div>
                    <div class="weekly-availability">
                    <?php 
                    $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                    $default_times = [
                        'Monday' => ['09:00', '17:00'],
                        'Tuesday' => ['09:00', '17:00'],
                        'Wednesday' => ['09:00', '17:00'],
                        'Thursday' => ['09:00', '17:00'],
                        'Friday' => ['09:00', '17:00'],
                        'Saturday' => ['09:00', '17:00']
                    ];

                    foreach ($days as $day):
                        $default_start = $default_times[$day][0];
                        $default_end = $default_times[$day][1];
                    ?>
                        <div class="day-availability">
                            <label style="min-width: 100px;">
                                <input type="checkbox" name="day_<?php echo $day; ?>" value="1" checked>
                                <?php echo $day; ?>
                            </label>
                            <div class="time-range">
                                <input type="time" name="<?php echo $day; ?>_start" value="<?php echo $default_start; ?>">
                                <span>to</span>
                                <input type="time" name="<?php echo $day; ?>_end" value="<?php echo $default_end; ?>">
                            </div>
                        </div>
                    <?php endforeach; ?>                    
                </div>
                </div>

                <button type="submit" class="btn" style="margin-bottom: 50px; margin-top: 30px;">Add Dentist</button>
            </form>

            <!-- Add Staff Form -->
            <form id="addStaffForm" class="add-form" style="display: none;" method="POST">
                <input type="hidden" name="add_staff" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label for="staffFullName">Full Name</label>
                        <input type="text" id="staffFullName" name="staffFullName" placeholder="Full Name" required>
                    </div>
                    <div class="form-group">
                        <label for="staffUsername">Username</label>
                        <input type="text" id="staffUsername" name="staffUsername" placeholder="Username" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staffDob">Date of Birth</label>
                        <input type="date" id="staffDob" name="staffDob" required>
                    </div>
                    <div class="form-group">
                        <label for="staffAge">Age</label>
                        <input type="number" id="staffAge" name="staffAge" placeholder="Age" min="18" max="99" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staffGender">Gender</label>
                        <select id="staffGender" name="staffGender" required>
                            <option value="">Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="staffHireDate">Hire Date</label>
                        <input type="date" id="staffHireDate" name="staffHireDate" required>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="staffEmail">Email</label>
                        <input type="email" id="staffEmail" name="staffEmail" placeholder="staff@example.com" required>
                    </div>
                    <div class="form-group">
                        <label for="staffPhone">Phone Number</label>
                        <input type="tel" id="staffPhone" name="staffPhone" placeholder="09123456789" required>
                    </div>
                </div>

                <div class="password-row">
                    <div class="form-group">
                        <label for="staffPassword">Password</label>
                        <input type="password" id="staffPassword" name="staffPassword" placeholder="Password" required>
                    </div>
                    <div class="form-group">
                        <label for="staffConfirmPassword">Confirm Password</label>
                        <input type="password" id="staffConfirmPassword" name="staffConfirmPassword" placeholder="Confirm Password" required>
                    </div>
                </div>

                <div class="form-group">
                    <label>Staff Role</label>
                    <div class="staff-role-container">
                        <label class="staff-role-radio">
                            <input type="radio" name="staffRole" value="receptionist" checked> Receptionist
                        </label>
                        <label class="staff-role-radio">
                            <input type="radio" name="staffRole" value="dental_assistant"> Dental Assistant
                        </label>
                        <label class="staff-role-radio">
                            <input type="radio" name="staffRole" value="technician"> Dental Technician
                        </label>
                        <label class="staff-role-radio">
                            <input type="radio" name="staffRole" value="admin"> Admin
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label for="staffAddress">Address</label>
                    <textarea id="staffAddress" name="staffAddress" placeholder="Full Address" rows="3" required></textarea>
                </div>

                <!-- Staff Availability Section -->
                <div class="availability-section">
                    <h2 style="color: royalblue; margin-bottom: 30px;">Availability</h2>
                    <div class="availability-instructions">
                        <p><strong>Instructions:</strong> Check the days you're available and set your working hours. These will be applied to all future dates until you update them again.</p>
                    </div>
                    <div class="weekly-availability">
                        <?php 
                        $days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                        $default_times = [
                            'Monday' => ['09:00', '17:00'],
                            'Tuesday' => ['09:00', '17:00'],
                            'Wednesday' => ['09:00', '17:00'],
                            'Thursday' => ['09:00', '17:00'],
                            'Friday' => ['09:00', '17:00'],
                            'Saturday' => ['09:00', '17:00']
                        ];
                        
                        foreach ($days as $day):
                            $default_start = $default_times[$day][0];
                            $default_end = $default_times[$day][1];
                        ?>
                            <div class="day-availability">
                                <label style="min-width: 100px;">
                                    <input type="checkbox" name="staff_day_<?php echo $day; ?>" value="1" checked>
                                    <?php echo $day; ?>
                                </label>
                                <div class="time-range">
                                    <input type="time" name="staff_<?php echo $day; ?>_start" value="<?php echo $default_start; ?>">
                                    <span>to</span>
                                    <input type="time" name="staff_<?php echo $day; ?>_end" value="<?php echo $default_end; ?>">
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn" style="margin-bottom: 50px; margin-top:30px;">Add Staff</button>
            </form>
        </div> <!-- .content-column -->
    </div> <!-- .tabbed-layout -->
</div> <!-- .settings-container -->

    <script>
        // Switch between Admin/Add Dentist/Add Staff tabs
        const adminBtn = document.getElementById("adminBtn");
        const addDentistBtn = document.getElementById("addDentistBtn");
        const addStaffBtn = document.getElementById("addStaffBtn");
        const adminForm = document.getElementById("adminForm");
        const addDentistForm = document.getElementById("addDentistForm");
        const addStaffForm = document.getElementById("addStaffForm");

        adminBtn.addEventListener("click", (e) => {
            e.preventDefault();
            adminBtn.classList.add("active");
            addDentistBtn.classList.remove("active");
            addStaffBtn.classList.remove("active");
            adminForm.style.display = "block";
            addDentistForm.style.display = "none";
            addStaffForm.style.display = "none";
        });

        addDentistBtn.addEventListener("click", (e) => {
            e.preventDefault();
            addDentistBtn.classList.add("active");
            adminBtn.classList.remove("active");
            addStaffBtn.classList.remove("active");
            addDentistForm.style.display = "block";
            adminForm.style.display = "none";
            addStaffForm.style.display = "none";
        });

        addStaffBtn.addEventListener("click", (e) => {
            e.preventDefault();
            addStaffBtn.classList.add("active");
            adminBtn.classList.remove("active");
            addDentistBtn.classList.remove("active");
            addStaffForm.style.display = "block";
            adminForm.style.display = "none";
            addDentistForm.style.display = "none";
        });

        // Enable/disable time inputs based on day checkbox for dentist form
        document.querySelectorAll('#addDentistForm .day-availability input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const dayAvailability = this.closest('.day-availability');
                const timeInputs = dayAvailability.querySelectorAll('input[type="time"]');
                timeInputs.forEach(input => {
                    input.disabled = !this.checked;
                });
            });
            
            // Initialize disabled state
            const dayAvailability = checkbox.closest('.day-availability');
            const timeInputs = dayAvailability.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                input.disabled = !checkbox.checked;
            });
        });

        // Enable/disable time inputs based on day checkbox for staff form
        document.querySelectorAll('#addStaffForm .day-availability input[type="checkbox"]').forEach(checkbox => {
            checkbox.addEventListener('change', function() {
                const dayAvailability = this.closest('.day-availability');
                const timeInputs = dayAvailability.querySelectorAll('input[type="time"]');
                timeInputs.forEach(input => {
                    input.disabled = !this.checked;
                });
            });
            
            // Initialize disabled state
            const dayAvailability = checkbox.closest('.day-availability');
            const timeInputs = dayAvailability.querySelectorAll('input[type="time"]');
            timeInputs.forEach(input => {
                input.disabled = !checkbox.checked;
            });
        });

        // Tab navigation
        document.querySelectorAll('.category-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all tabs
                document.querySelectorAll('.category-tab').forEach(t => t.classList.remove('active'));
                // Hide all content sections
                document.querySelectorAll('.content-section').forEach(section => section.classList.remove('active'));
                
                // Activate clicked tab and corresponding section
                this.classList.add('active');
                const targetId = this.getAttribute('data-target');
                document.getElementById(targetId).classList.add('active');
            });
        });

        // Clinic Operations Tab navigation
        document.querySelectorAll('.clinic-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                // Remove active class from all clinic tabs
                document.querySelectorAll('.clinic-tab').forEach(t => t.classList.remove('active'));
                // Hide all clinic tab contents
                document.querySelectorAll('.clinic-tab-content').forEach(content => content.classList.remove('active'));
                
                // Activate clicked clinic tab and corresponding content
                this.classList.add('active');
                const targetId = this.getAttribute('data-target');
                document.getElementById(targetId).classList.add('active');
            });
        });

        // Calculate age based on date of birth for dentist form
        document.getElementById('dentistDob').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            document.getElementById('dentistAge').value = age;
        });

        // Calculate age based on date of birth for staff form
        document.getElementById('staffDob').addEventListener('change', function() {
            const dob = new Date(this.value);
            const today = new Date();
            let age = today.getFullYear() - dob.getFullYear();
            const monthDiff = today.getMonth() - dob.getMonth();
            
            if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                age--;
            }
            
            document.getElementById('staffAge').value = age;
        });

        // Password confirmation validation for dentist form
        document.getElementById('addDentistForm').addEventListener('submit', function(e) {
            const password = document.getElementById('dentistPassword').value;
            const confirmPassword = document.getElementById('dentistConfirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password.');
                return false;
            }
        });

        // Password confirmation validation for staff form
        document.getElementById('addStaffForm').addEventListener('submit', function(e) {
            const password = document.getElementById('staffPassword').value;
            const confirmPassword = document.getElementById('staffConfirmPassword').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match. Please confirm your password.');
                return false;
            }
        });

        // Toggle operating hours for clinic
        function toggleDayHours(checkbox) {
            const dayOperatingHours = checkbox.closest('.day-operating-hours');
            const timeInputs = dayOperatingHours.querySelectorAll('input[type="time"]');
            
            timeInputs.forEach(input => {
                input.disabled = checkbox.checked;
            });
        }

        // Initialize disabled state for operating hours
        document.querySelectorAll('.closed-checkbox input[type="checkbox"]').forEach(checkbox => {
            toggleDayHours(checkbox);
        });

        // Auto-hide messages after 5 seconds
        setTimeout(function() {
            const successMsg = document.querySelector('.success-message');
            const errorMsg = document.querySelector('.error-message');
            
            if (successMsg) successMsg.style.display = 'none';
            if (errorMsg) errorMsg.style.display = 'none';
        }, 2000);

        // Services Management Functions
        const addServiceBtn = document.getElementById('addServiceBtn');
        const newServiceRow = document.getElementById('newServiceRow');

        addServiceBtn.addEventListener('click', function() {
            newServiceRow.style.display = 'table-row';
            addServiceBtn.disabled = true;
        });

        function cancelNewService() {
            newServiceRow.style.display = 'none';
            document.getElementById('newServiceName').value = '';
            document.getElementById('newServiceDuration').value = '';
            document.getElementById('newServicePrice').value = '';
            addServiceBtn.disabled = false;
        }

        function saveNewService() {
            const serviceName = document.getElementById('newServiceName').value.trim();
            const serviceDuration = document.getElementById('newServiceDuration').value;
            const servicePrice = document.getElementById('newServicePrice').value;

            if (!serviceName || !serviceDuration || !servicePrice) {
                alert('Please fill in all fields.');
                return;
            }

            if (serviceDuration <= 0) {
                alert('Duration must be greater than 0.');
                return;
            }

            if (servicePrice < 0) {
                alert('Price cannot be negative.');
                return;
            }

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'service_name';
            nameInput.value = serviceName;

            const durationInput = document.createElement('input');
            durationInput.type = 'hidden';
            durationInput.name = 'service_duration';
            durationInput.value = serviceDuration;

            const priceInput = document.createElement('input');
            priceInput.type = 'hidden';
            priceInput.name = 'service_price';
            priceInput.value = servicePrice;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'add_service';
            actionInput.value = '1';

            form.appendChild(nameInput);
            form.appendChild(durationInput);
            form.appendChild(priceInput);
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }

        function enableEdit(serviceId) {
            const row = document.getElementById('serviceRow' + serviceId);
            
            // Hide view elements, show edit inputs
            row.querySelector('.service-name').style.display = 'none';
            row.querySelector('.service-duration').style.display = 'none';
            row.querySelector('.service-price').style.display = 'none';
            
            row.querySelector('.service-name-input').style.display = 'block';
            row.querySelector('.service-duration-input').style.display = 'block';
            row.querySelector('.service-price-input').style.display = 'block';
            
            // Switch action buttons
            row.querySelector('.view-actions').style.display = 'none';
            row.querySelector('.edit-actions').style.display = 'block';
        }

        function cancelEdit(serviceId) {
            const row = document.getElementById('serviceRow' + serviceId);
            
            // Show view elements, hide edit inputs
            row.querySelector('.service-name').style.display = 'inline';
            row.querySelector('.service-duration').style.display = 'inline';
            row.querySelector('.service-price').style.display = 'inline';
            
            row.querySelector('.service-name-input').style.display = 'none';
            row.querySelector('.service-duration-input').style.display = 'none';
            row.querySelector('.service-price-input').style.display = 'none';
            
            // Switch action buttons
            row.querySelector('.view-actions').style.display = 'block';
            row.querySelector('.edit-actions').style.display = 'none';
            
            // Reset input values to original
            const nameInput = row.querySelector('.service-name-input');
            const durationInput = row.querySelector('.service-duration-input');
            const priceInput = row.querySelector('.service-price-input');
            
            nameInput.value = row.querySelector('.service-name').textContent;
            durationInput.value = row.querySelector('.service-duration').textContent.replace(/[^\d]/g, '');
            priceInput.value = row.querySelector('.service-price').textContent.replace('₱', '');
        }

        function saveService(serviceId) {
            const row = document.getElementById('serviceRow' + serviceId);
            const serviceName = row.querySelector('.service-name-input').value.trim();
            const serviceDuration = row.querySelector('.service-duration-input').value;
            const servicePrice = row.querySelector('.service-price-input').value;

            if (!serviceName || !serviceDuration || !servicePrice) {
                alert('Please fill in all fields.');
                return;
            }

            if (serviceDuration <= 0) {
                alert('Duration must be greater than 0.');
                return;
            }

            if (servicePrice < 0) {
                alert('Price cannot be negative.');
                return;
            }

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';

            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'service_id';
            idInput.value = serviceId;

            const nameInput = document.createElement('input');
            nameInput.type = 'hidden';
            nameInput.name = 'service_name';
            nameInput.value = serviceName;

            const durationInput = document.createElement('input');
            durationInput.type = 'hidden';
            durationInput.name = 'service_duration';
            durationInput.value = serviceDuration;

            const priceInput = document.createElement('input');
            priceInput.type = 'hidden';
            priceInput.name = 'service_price';
            priceInput.value = servicePrice;

            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'update_service';
            actionInput.value = '1';

            form.appendChild(idInput);
            form.appendChild(nameInput);
            form.appendChild(durationInput);
            form.appendChild(priceInput);
            form.appendChild(actionInput);

            document.body.appendChild(form);
            form.submit();
        }

        // Delete confirmation function
        function confirmDelete(type, name) {
            const message = `Are you sure you want to delete ${type} "${name}"? This action cannot be undone and will permanently remove all their data from the system.`;
            return confirm(message);
        }

    </script>

        <script src="notifications/notification_script.js"></script>

</body>
</html>