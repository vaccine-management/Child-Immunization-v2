<?php
/**
 * Test Script for Vaccination Schedule SMS
 * 
 * This script simulates the vaccination schedule generation and SMS sending
 * process that happens during child registration.
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include necessary files
require_once 'sms-service/sms-adapter.php';
require_once 'backend/db.php';

echo "<pre>";
echo "📱 VACCINATION SCHEDULE SMS TEST 📱\n";
echo "==================================\n\n";

// Test phone number - THIS SHOULD BE YOUR ACTUAL PHONE NUMBER FOR TESTING
$testPhone = "+254750014181"; // Change this to your phone number

// Child details for the test
$childName = "Test Child";
$guardianName = "Test Parent";
$dateOfBirth = date('Y-m-d'); // Today's date as birth date
$childID = "TEST-1234-" . date('ymd'); // Sample child ID

echo "Test Details:\n";
echo "------------\n";
echo "Child Name: $childName\n";
echo "Guardian Name: $guardianName\n";
echo "Phone: $testPhone\n";
echo "Date of Birth: $dateOfBirth\n";
echo "Child ID: $childID\n\n";

// Create a sample vaccination schedule (simulating what would be generated during registration)
try {
    echo "Generating Sample Vaccination Schedule...\n\n";
    
    // Fetch vaccine schedule information from database
    $scheduleStmt = $conn->query("
        SELECT id, vaccine_name, age_unit, age_value, dose_number, 
               administration_method, dosage, target_disease, notes 
        FROM vaccine_schedule 
        ORDER BY 
            CASE age_unit 
                WHEN 'days' THEN age_value
                WHEN 'weeks' THEN age_value * 7
                WHEN 'months' THEN age_value * 30
                WHEN 'years' THEN age_value * 365
            END ASC,
            dose_number ASC
        LIMIT 10 -- Limiting to 10 vaccines for the test
    ");
    
    $vaccineSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group vaccines by their scheduled date from birth
    $groupedVaccinesByDate = [];
    $registrationDateTime = new DateTime($dateOfBirth);
    
    foreach ($vaccineSchedules as $vaccine) {
        // Create a proper date interval based on the unit and value
        $scheduleDate = clone $registrationDateTime;
        
        switch ($vaccine['age_unit']) {
            case 'days':
                $scheduleDate->add(new DateInterval('P' . $vaccine['age_value'] . 'D'));
                break;
            case 'weeks':
                $scheduleDate->add(new DateInterval('P' . ($vaccine['age_value'] * 7) . 'D'));
                break;
            case 'months':
                $scheduleDate->add(new DateInterval('P' . $vaccine['age_value'] . 'M'));
                break;
            case 'years':
                $scheduleDate->add(new DateInterval('P' . $vaccine['age_value'] . 'Y'));
                break;
        }
        
        $appointmentDate = $scheduleDate->format('Y-m-d');
        
        if (!isset($groupedVaccinesByDate[$appointmentDate])) {
            $groupedVaccinesByDate[$appointmentDate] = [];
        }
        $groupedVaccinesByDate[$appointmentDate][] = $vaccine;
    }
    
    // Display the generated schedule
    echo "Generated Schedule:\n";
    echo "------------------\n";
    foreach ($groupedVaccinesByDate as $date => $vaccines) {
        echo date('d-m-Y', strtotime($date)) . ":\n";
        foreach ($vaccines as $vaccine) {
            echo "  - " . $vaccine['vaccine_name'] . " (dose " . $vaccine['dose_number'] . ")\n";
        }
    }
    
    echo "\nSending SMS with Schedule...\n";
    
    // Send the SMS
    $smsResult = sendRegistrationWithScheduleSMS($testPhone, $childName, $guardianName, $groupedVaccinesByDate, $childID);
    
    echo "\nSMS Result:\n";
    echo "-----------\n";
    echo "Status: " . ($smsResult['status'] ?? 'Unknown') . "\n";
    
    if (isset($smsResult['status']) && $smsResult['status'] === 'success') {
        echo "Message ID: " . ($smsResult['messageId'] ?? 'N/A') . "\n";
        echo "Cost: " . ($smsResult['cost'] ?? 'N/A') . "\n";
        echo "Number: " . ($smsResult['number'] ?? 'N/A') . "\n";
        echo "\n✅ Schedule SMS sent successfully! Check your phone for the message.\n";
    } else {
        echo "Error: " . ($smsResult['message'] ?? 'Unknown error') . "\n";
        if (isset($smsResult['details'])) {
            echo "\nError Details:\n";
            print_r($smsResult['details']);
        }
        echo "\n❌ SMS sending failed. See error details above.\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error occurred: " . $e->getMessage() . "\n";
}

echo "</pre>";
?> 