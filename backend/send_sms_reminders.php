<?php
session_start();
// Ensure the user is logged in and is an admin
if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
    header('Location: ../login.php');
    exit();
}

// Include database connection
require_once 'db.php';

// Include SMS service
require_once 'sms_service.php';

// Load SMS configuration
$smsConfig = require_once 'config/sms_config.php';

// Initialize SMS service
$smsService = new SMSService(
    $smsConfig['username'],
    $smsConfig['api_key'],
    $conn
);

// Initialize response variables
$status = '';
$message = '';
$logDetails = [];

// Handle sending upcoming vaccination reminders
if (isset($_POST['send_upcoming_reminders'])) {
    $daysBefore = isset($_POST['days_before']) ? (int)$_POST['days_before'] : 3;
    
    try {
        $result = $smsService->sendUpcomingVaccinationReminders($daysBefore);
        
        if ($result['status'] === 'success') {
            $status = 'success';
            $message = "Successfully sent {$result['sent']} vaccination reminders.";
            $logDetails = $result['details'];
        } else {
            $status = 'error';
            $message = "Failed to send reminders: {$result['message']}";
        }
    } catch (Exception $e) {
        $status = 'error';
        $message = "An error occurred: " . $e->getMessage();
    }
}

// Handle sending missed vaccination notifications
if (isset($_POST['send_missed_notifications'])) {
    try {
        $result = $smsService->sendMissedVaccinationNotifications();
        
        if ($result['status'] === 'success') {
            $status = 'success';
            $message = "Successfully sent {$result['sent']} missed vaccination notifications.";
            $logDetails = $result['details'];
        } else {
            $status = 'error';
            $message = "Failed to send notifications: {$result['message']}";
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