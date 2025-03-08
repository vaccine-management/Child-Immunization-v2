<?php
/**
 * EMERGENCY SMS SENDER
 * This script bypasses all normal workflows and forces an SMS to be sent,
 * providing detailed debug information at each step.
 */

// Enable maximum error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', 'php_errors.log');

// Set header
header('Content-Type: text/html; charset=utf-8');

echo "<html><head><title>Emergency SMS Sender</title>";
echo "<style>
    body { font-family: monospace; margin: 20px; line-height: 1.6; background: #f8f8f8; }
    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border: 1px solid #ddd; }
    h1 { color: #cc0000; }
    h2 { color: #333; margin-top: 20px; }
    pre { background: #eee; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .step { background: #f0f8ff; padding: 10px; margin: 10px 0; border-left: 4px solid #0066cc; }
    button { background: #0066cc; color: white; border: none; padding: 10px; cursor: pointer; margin: 10px 0; }
</style>";
echo "</head><body><div class='container'>";

echo "<h1>🚨 EMERGENCY SMS SENDER 🚨</h1>";
echo "<p>This script bypasses all normal workflows and tries to send an SMS using multiple methods.</p>";

// Start execution time tracking
$startTime = microtime(true);

// Include SMS adapter
echo "<div class='step'>";
echo "<h2>Step 1: Loading SMS Adapter</h2>";
try {
    require_once 'sms-service/sms-adapter.php';
    echo "<p class='success'>SMS adapter loaded successfully</p>";
} catch (Exception $e) {
    echo "<p class='error'>Failed to load SMS adapter: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Include database
echo "<div class='step'>";
echo "<h2>Step 2: Database Connection</h2>";
try {
    require_once 'backend/db.php';
    echo "<p class='success'>Database connection established</p>";
} catch (Exception $e) {
    echo "<p class='error'>Failed to connect to database: " . $e->getMessage() . "</p>";
    exit;
}
echo "</div>";

// Get target phone
echo "<div class='step'>";
echo "<h2>Step 3: Phone Number</h2>";

// If form is submitted, use the submitted phone
if (isset($_POST['phone'])) {
    $phone = trim($_POST['phone']);
    $childID = isset($_POST['child_id']) ? trim($_POST['child_id']) : '';
    echo "<p>Using submitted phone number: <strong>$phone</strong></p>";
} 
// If child ID is in the URL, get the phone from the database
elseif (isset($_GET['child_id'])) {
    $childID = $_GET['child_id'];
    $stmt = $conn->prepare("SELECT phone, full_name, guardian_name FROM children WHERE child_id = ?");
    $stmt->execute([$childID]);
    $child = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($child) {
        $phone = $child['phone'];
        $childName = $child['full_name'];
        $guardianName = $child['guardian_name'];
        echo "<p>Found child: <strong>$childName</strong></p>";
        echo "<p>Guardian: <strong>$guardianName</strong></p>";
        echo "<p>Phone from database: <strong>$phone</strong></p>";
    } else {
        echo "<p class='error'>Child ID not found in database</p>";
        $phone = '+254750014181'; // Default fallback
    }
} 
// Use default phone
else {
    $phone = '+254750014181';
    $childID = '';
    echo "<p>Using default phone number: <strong>$phone</strong></p>";
}

// Show form for phone entry
echo "<form method='post'>";
echo "Phone Number: <input type='text' name='phone' value='$phone' style='padding:5px;'><br>";
echo "Child ID (optional): <input type='text' name='child_id' value='$childID' style='padding:5px;'><br>";
echo "<button type='submit'>Update Phone</button>";
echo "</form>";

// Validate phone
if (empty($phone)) {
    echo "<p class='error'>Phone number cannot be empty</p>";
    exit;
}

// Add + prefix if missing
if (strpos($phone, '+') !== 0 && preg_match('/^\d+$/', $phone)) {
    $phone = '+' . $phone;
    echo "<p>Added + prefix: <strong>$phone</strong></p>";
}

echo "</div>";

// Create vaccination schedule
echo "<div class='step'>";
echo "<h2>Step 4: Vaccination Schedule</h2>";

// Create default vaccination schedule
$twoMonthsLater = date('Y-m-d', strtotime('+2 months'));
$fourMonthsLater = date('Y-m-d', strtotime('+4 months'));
$sixMonthsLater = date('Y-m-d', strtotime('+6 months'));

$defaultSchedule = [
    $twoMonthsLater => [
        ['vaccine_name' => 'OPV', 'dose_number' => '1'],
        ['vaccine_name' => 'DTP', 'dose_number' => '1']
    ],
    $fourMonthsLater => [
        ['vaccine_name' => 'OPV', 'dose_number' => '2'],
        ['vaccine_name' => 'DTP', 'dose_number' => '2']
    ],
    $sixMonthsLater => [
        ['vaccine_name' => 'OPV', 'dose_number' => '3'],
        ['vaccine_name' => 'DTP', 'dose_number' => '3']
    ]
];

// Try to get actual vaccination schedule if child ID is provided
$vaccineSchedule = $defaultSchedule;

if (!empty($childID)) {
    try {
        // Get birth date for the child
        $stmt = $conn->prepare("SELECT date_of_birth FROM children WHERE child_id = ?");
        $stmt->execute([$childID]);
        $birthDateResult = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($birthDateResult) {
            $birthDate = new DateTime($birthDateResult['date_of_birth']);
            echo "<p>Child birth date: " . $birthDate->format('Y-m-d') . "</p>";
            
            // Get vaccine schedules
            $scheduleStmt = $conn->query("
                SELECT id, vaccine_name, age_unit, age_value, dose_number 
                FROM vaccine_schedule 
                ORDER BY 
                    CASE age_unit 
                        WHEN 'days' THEN age_value
                        WHEN 'weeks' THEN age_value * 7
                        WHEN 'months' THEN age_value * 30
                        WHEN 'years' THEN age_value * 365
                    END ASC,
                    dose_number ASC
            ");
            
            $vaccineSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
            echo "<p>Found " . count($vaccineSchedules) . " vaccine schedule entries</p>";
            
            if (!empty($vaccineSchedules)) {
                // Create actual schedule based on birth date
                $actualSchedule = [];
                
                foreach ($vaccineSchedules as $vaccine) {
                    $scheduleDate = clone $birthDate;
                    
                    if (isset($vaccine['age_unit']) && $vaccine['age_unit'] === 'birth') {
                        // No need to add any time
                    } else {
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
                    }
                    
                    $appointmentDate = $scheduleDate->format('Y-m-d');
                    
                    if (!isset($actualSchedule[$appointmentDate])) {
                        $actualSchedule[$appointmentDate] = [];
                    }
                    
                    $actualSchedule[$appointmentDate][] = $vaccine;
                }
                
                if (!empty($actualSchedule)) {
                    $vaccineSchedule = $actualSchedule;
                    echo "<p class='success'>Successfully created actual vaccination schedule based on birth date</p>";
                }
            }
        }
    } catch (Exception $e) {
        echo "<p class='error'>Error creating vaccination schedule: " . $e->getMessage() . "</p>";
    }
}

// Display schedule
echo "<p>Vaccination Schedule:</p>";
echo "<pre>";
foreach ($vaccineSchedule as $date => $vaccines) {
    echo date('d-m-Y', strtotime($date)) . ":\n";
    foreach ($vaccines as $vaccine) {
        echo "  - " . $vaccine['vaccine_name'] . " (dose " . $vaccine['dose_number'] . ")\n";
    }
}
echo "</pre>";

echo "</div>";

// Send SMS if requested
if (isset($_GET['send']) && $_GET['send'] == '1') {
    echo "<div class='step'>";
    echo "<h2>Step 5: Sending SMS</h2>";
    
    $childName = isset($childName) ? $childName : "Test Child";
    $guardianName = isset($guardianName) ? $guardianName : "Test Guardian";
    
    echo "<p>Child Name: <strong>$childName</strong></p>";
    echo "<p>Guardian Name: <strong>$guardianName</strong></p>";
    echo "<p>Child ID: <strong>$childID</strong></p>";
    
    // Method 1: Using the reliable function
    echo "<h3>Method 1: Using sendRegistrationWithScheduleSMS_Reliable()</h3>";
    try {
        $result1 = sendRegistrationWithScheduleSMS_Reliable($phone, $childName, $guardianName, $vaccineSchedule, $childID);
        echo "<p>Result from reliable method:</p>";
        echo "<pre>";
        print_r($result1);
        echo "</pre>";
        
        if (isset($result1['status']) && $result1['status'] === 'success') {
            echo "<p class='success'>SUCCESS: SMS sent using reliable method!</p>";
        } else {
            echo "<p class='error'>FAILED: SMS not sent using reliable method.</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Exception in reliable method: " . $e->getMessage() . "</p>";
    }
    
    // Method 2: Using direct method
    echo "<h3>Method 2: Using sendSMSDirectly()</h3>";
    try {
        $message = "Dear $guardianName, DIRECT TEST for $childName";
        if (!empty($childID)) {
            $message .= " (ID: $childID)";
        }
        $message .= ". Your child's vaccination schedule: ";
        
        // Add first date
        $firstDate = array_key_first($vaccineSchedule);
        $message .= date('d-m-Y', strtotime($firstDate)) . ". Time: " . date('H:i:s');
        
        $result2 = sendSMSDirectly($phone, $message);
        echo "<p>Result from direct method:</p>";
        echo "<pre>";
        print_r($result2);
        echo "</pre>";
        
        if (isset($result2['status']) && $result2['status'] === 'success') {
            echo "<p class='success'>SUCCESS: SMS sent using direct method!</p>";
        } else {
            echo "<p class='error'>FAILED: SMS not sent using direct method.</p>";
        }
    } catch (Exception $e) {
        echo "<p class='error'>Exception in direct method: " . $e->getMessage() . "</p>";
    }
    
    echo "</div>";
} else {
    echo "<p><a href='?child_id=$childID&phone=$phone&send=1' style='background:#cc0000; color:white; padding:10px; text-decoration:none; display:inline-block;'>👉 SEND EMERGENCY SMS 👈</a></p>";
}

// Execution time
$endTime = microtime(true);
$executionTime = ($endTime - $startTime);
echo "<p>Script execution time: {$executionTime} seconds</p>";

echo "</div></body></html>";
?> 