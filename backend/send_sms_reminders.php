<?php
session_start();
// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once 'db.php';

// Include the new SMS adapter
require_once '../sms-service/sms-adapter.php';

// Initialize response variables
$status = '';
$message = '';
$logDetails = [];

// Handle sending upcoming vaccination reminders
if (isset($_POST['send_upcoming_reminders'])) {
    $daysBefore = isset($_POST['days_before']) ? (int)$_POST['days_before'] : 3;
    
    try {
        // Get upcoming vaccinations
        $stmt = $conn->prepare("
            SELECT 
                c.child_id, 
                c.full_name AS child_name,
                c.guardian_name,
                c.guardian_phone,
                v.vaccine_name,
                mv.dose_number,
                mv.scheduled_date
            FROM 
                medical_records mr
                JOIN children c ON mr.child_id = c.child_id
                JOIN medical_vaccines mv ON mr.id = mv.medical_record_id
                JOIN vaccines v ON mv.vaccine_id = v.id
            WHERE 
                mv.status = 'scheduled' 
                AND DATE(mv.scheduled_date) = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                AND c.guardian_phone IS NOT NULL
        ");
        
        $stmt->execute([$daysBefore]);
        $upcomingVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($upcomingVaccinations)) {
            $status = 'info';
            $message = "No upcoming vaccinations found for reminder.";
        } else {
            $count = count($upcomingVaccinations);
            $successes = 0;
            $failures = 0;
            $logDetails = [];
            
            foreach ($upcomingVaccinations as $row) {
                $dueDate = date('d-m-Y', strtotime($row['scheduled_date']));
                
                // Send SMS using Node.js service
                $result = sendVaccinationReminderSMS(
                    $row['guardian_phone'],
                    $row['child_name'],
                    $row['guardian_name'],
                    $row['vaccine_name'],
                    $row['dose_number'],
                    $dueDate
                );
                
                $logDetails[] = [
                    'child_id' => $row['child_id'],
                    'child_name' => $row['child_name'],
                    'phone' => $row['guardian_phone'],
                    'status' => $result['status']
                ];
                
                if ($result['status'] === 'success') {
                    $successes++;
                } else {
                    $failures++;
                }
            }
            
            $status = 'success';
            $message = "Sent $successes of $count vaccination reminders successfully. Failed: $failures.";
        }
    } catch (Exception $e) {
        $status = 'error';
        $message = "An error occurred: " . $e->getMessage();
    }
}

// Handle sending missed vaccination notifications
if (isset($_POST['send_missed_notifications'])) {
    try {
        // Get missed vaccinations
        $stmt = $conn->prepare("
            SELECT 
                c.child_id, 
                c.full_name AS child_name,
                c.guardian_name,
                c.guardian_phone,
                v.vaccine_name,
                mv.dose_number,
                mv.scheduled_date
            FROM 
                medical_records mr
                JOIN children c ON mr.child_id = c.child_id
                JOIN medical_vaccines mv ON mr.id = mv.medical_record_id
                JOIN vaccines v ON mv.vaccine_id = v.id
            WHERE 
                mv.status = 'scheduled' 
                AND DATE(mv.scheduled_date) < CURDATE() 
                AND DATE(mv.scheduled_date) >= DATE_SUB(CURDATE(), INTERVAL 14 DAY)
                AND c.guardian_phone IS NOT NULL
        ");
        
        $stmt->execute();
        $missedVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (empty($missedVaccinations)) {
            $status = 'info';
            $message = "No missed vaccinations found for notification.";
        } else {
            $count = count($missedVaccinations);
            $successes = 0;
            $failures = 0;
            $logDetails = [];
            
            foreach ($missedVaccinations as $row) {
                $dueDate = date('d-m-Y', strtotime($row['scheduled_date']));
                
                // Send SMS using Node.js service
                $result = sendMissedVaccinationSMS(
                    $row['guardian_phone'],
                    $row['child_name'],
                    $row['guardian_name'],
                    $row['vaccine_name'],
                    $row['dose_number'],
                    $dueDate
                );
                
                $logDetails[] = [
                    'child_id' => $row['child_id'],
                    'child_name' => $row['child_name'],
                    'phone' => $row['guardian_phone'],
                    'status' => $result['status']
                ];
                
                if ($result['status'] === 'success') {
                    $successes++;
                } else {
                    $failures++;
                }
            }
            
            $status = 'success';
            $message = "Sent $successes of $count missed vaccination notifications successfully. Failed: $failures.";
        }
    } catch (Exception $e) {
        $status = 'error';
        $message = "An error occurred: " . $e->getMessage();
    }
}

// Store result in session for displaying after redirect
$_SESSION['sms_notification'] = [
    'status' => $status,
    'message' => $message,
    'details' => $logDetails
];

// Redirect back to admin panel
header('Location: ../admin-panel.php');
exit();
?> 