<?php
/**
 * Debug Script for SMS functionality
 * This script can help diagnose issues with SMS sending
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'sms-service/sms-adapter.php';
require_once 'backend/db.php';

echo "<pre>";
echo "🔍 SMS DEBUG TOOL 🔍\n";
echo "===================\n\n";

// Check if the SMS service is running
echo "Step 1: Checking if SMS service is running...\n";
if (isSMSServiceRunning()) {
    echo "✅ SMS service is running!\n\n";
} else {
    echo "❌ SMS service is NOT running!\n";
    echo "   Please start the SMS service by running start_sms_service.bat\n\n";
}

// Check for specific child ID if provided
if (isset($_GET['child_id']) && !empty($_GET['child_id'])) {
    $childId = $_GET['child_id'];
    echo "Step 2: Getting information for child ID: $childId\n";
    
    try {
        // Get child information
        $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = ?");
        $stmt->execute([$childId]);
        $child = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($child) {
            echo "Child found: {$child['full_name']}\n";
            echo "Parent/Guardian: {$child['guardian_name']}\n";
            echo "Phone number: {$child['phone']}\n\n";
            
            // Get vaccination schedule
            echo "Step 3: Generating vaccination schedule...\n";
            
            // Fetch vaccine schedule information
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
                LIMIT 10
            ");
            
            $vaccineSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Group vaccines by their scheduled date from birth
            $groupedVaccinesByDate = [];
            $birthDate = new DateTime($child['date_of_birth']);
            
            foreach ($vaccineSchedules as $vaccine) {
                // Create a proper date interval based on the unit and value
                $scheduleDate = clone $birthDate;
                
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
            
            echo "Generated " . count($groupedVaccinesByDate) . " vaccination dates\n\n";
            
            // Format and display the schedule
            foreach ($groupedVaccinesByDate as $date => $vaccines) {
                echo date('d-m-Y', strtotime($date)) . ":\n";
                foreach ($vaccines as $vaccine) {
                    echo "  - " . $vaccine['vaccine_name'] . " (dose " . $vaccine['dose_number'] . ")\n";
                }
            }
            
            echo "\nStep 4: Testing SMS sending...\n";
            
            // Validate phone number
            $phone = $child['phone'];
            if (empty($phone)) {
                echo "❌ Phone number is empty!\n\n";
            } else {
                // Clean and format the phone number
                $phone = trim($phone);
                if (!preg_match('/^\+?\d{10,15}$/', $phone)) {
                    if (preg_match('/^\d{10,15}$/', $phone)) {
                        $phone = '+' . $phone;
                        echo "Added + prefix to phone number: $phone\n";
                    } else {
                        echo "❌ Phone number format is invalid: $phone\n\n";
                    }
                }
                
                // Attempt to send SMS
                if (isset($_GET['send']) && $_GET['send'] == '1') {
                    echo "Sending SMS with vaccination schedule...\n";
                    $smsResult = sendRegistrationWithScheduleSMS(
                        $phone,
                        $child['full_name'],
                        $child['guardian_name'],
                        $groupedVaccinesByDate,
                        $childId
                    );
                    
                    if (isset($smsResult['status']) && $smsResult['status'] === 'success') {
                        echo "✅ SMS sent successfully!\n";
                        echo "Message ID: " . ($smsResult['messageId'] ?? 'N/A') . "\n";
                        echo "Cost: " . ($smsResult['cost'] ?? 'N/A') . "\n";
                        echo "Number: " . ($smsResult['number'] ?? 'N/A') . "\n";
                    } else {
                        echo "❌ Failed to send SMS\n";
                        echo "Error: " . ($smsResult['message'] ?? 'Unknown error') . "\n";
                        if (isset($smsResult['details'])) {
                            echo "\nError Details:\n";
                            print_r($smsResult['details']);
                        }
                    }
                } else {
                    echo "To send a test SMS, add &send=1 to the URL\n";
                }
            }
        } else {
            echo "❌ Child not found with ID: $childId\n";
        }
    } catch (Exception $e) {
        echo "❌ Error: " . $e->getMessage() . "\n";
    }
} else {
    // List recently registered children
    echo "Step 2: Listing recently registered children...\n";
    echo "To debug a specific child, add ?child_id=CHILD_ID to the URL\n\n";
    
    $stmt = $conn->query("SELECT child_id, full_name, guardian_name, phone, registration_date FROM children ORDER BY registration_date DESC LIMIT 10");
    $children = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($children) > 0) {
        echo "Recent children:\n";
        foreach ($children as $child) {
            echo "- {$child['full_name']} (ID: {$child['child_id']}, Phone: {$child['phone']}, Registered: {$child['registration_date']})\n";
        }
        echo "\nClick on a child ID to debug: ";
        foreach ($children as $child) {
            echo "<a href='?child_id={$child['child_id']}'>{$child['child_id']}</a> | ";
        }
    } else {
        echo "No children found in the database.\n";
    }
}

echo "</pre>";
?> 