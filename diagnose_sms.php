<?php
/**
 * SMS Diagnosis Tool
 * This script performs a comprehensive diagnosis of the SMS functionality
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set header for better display of special characters
header('Content-Type: text/html; charset=utf-8');

// Include required files
require_once 'sms-service/sms-adapter.php';
require_once 'backend/db.php';

// Function to format output with colored status
function formatStatus($text, $status) {
    $statusColor = $status ? '#4CAF50' : '#F44336';
    return "<span style='color: $statusColor; font-weight: bold;'>$text</span>";
}

echo "<html><head><title>SMS Diagnostic Tool</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; background-color: #f5f5f5; }
    .container { max-width: 1000px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    h1 { color: #2196F3; }
    h2 { color: #333; margin-top: 30px; border-bottom: 1px solid #eee; padding-bottom: 10px; }
    pre { background: #f8f8f8; padding: 10px; border-radius: 4px; overflow-x: auto; }
    .success { color: #4CAF50; font-weight: bold; }
    .error { color: #F44336; font-weight: bold; }
    .warning { color: #FF9800; font-weight: bold; }
    .step { background: #e9f5fe; padding: 15px; margin: 10px 0; border-left: 4px solid #2196F3; border-radius: 4px; }
    button, .btn { background: #2196F3; color: white; border: none; padding: 10px 15px; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; margin: 5px; }
    button:hover, .btn:hover { background: #0b7dda; }
    .test-btn { background: #FF9800; }
    .test-btn:hover { background: #e68a00; }
    table { width: 100%; border-collapse: collapse; margin: 20px 0; }
    table, th, td { border: 1px solid #ddd; }
    th, td { padding: 12px; text-align: left; }
    th { background-color: #f2f2f2; }
    tr:nth-child(even) { background-color: #f9f9f9; }
</style>";
echo "</head><body><div class='container'>";

echo "<h1>📱 SMS System Diagnostic Tool</h1>";
echo "<p>This tool performs a comprehensive diagnosis of your SMS system and provides specific solutions to fix any issues.</p>";

// STEP 1: Check SMS service status
echo "<h2>Step 1: SMS Service Status</h2>";
echo "<div class='step'>";
$serviceRunning = isSMSServiceRunning();
echo "SMS Service Status: " . formatStatus($serviceRunning ? "RUNNING" : "NOT RUNNING", $serviceRunning) . "<br>";

if (!$serviceRunning) {
    echo "<p class='error'>The SMS service is not running. This is the most common cause of SMS delivery failures.</p>";
    echo "<h3>Solutions:</h3>";
    echo "<ol>";
    echo "<li>Run the <code>start_sms_service.bat</code> file to start the SMS service</li>";
    echo "<li>Check if the service is running on port 3000</li>";
    echo "<li>Ensure Node.js is installed properly on your system</li>";
    echo "</ol>";
    
    echo "<p>Command to start the service manually:</p>";
    echo "<pre>cd sms-service && node server.js</pre>";
}
echo "</div>";

// STEP 2: Check Africa's Talking configuration
echo "<h2>Step 2: Africa's Talking Configuration</h2>";
echo "<div class='step'>";

// Read .env file
$envFile = 'sms-service/.env';
$envConfigValid = false;
$apiKey = '';
$username = '';

if (file_exists($envFile)) {
    $envContent = file_get_contents($envFile);
    preg_match('/AT_API_KEY=([^\n]+)/', $envContent, $apiKeyMatches);
    preg_match('/AT_USERNAME=([^\n]+)/', $envContent, $usernameMatches);
    
    $apiKey = isset($apiKeyMatches[1]) ? trim($apiKeyMatches[1]) : '';
    $username = isset($usernameMatches[1]) ? trim($usernameMatches[1]) : '';
    
    $envConfigValid = !empty($apiKey) && !empty($username);
}

echo "Africa's Talking Configuration: " . formatStatus($envConfigValid ? "VALID" : "INCOMPLETE", $envConfigValid) . "<br>";
echo "API Username: " . (!empty($username) ? $username : "<span class='error'>Missing</span>") . "<br>";
echo "API Key: " . (!empty($apiKey) ? substr($apiKey, 0, 5) . "..." . substr($apiKey, -5) : "<span class='error'>Missing</span>") . "<br>";

if (!$envConfigValid) {
    echo "<p class='error'>Africa's Talking configuration is incomplete or missing.</p>";
    echo "<h3>Solutions:</h3>";
    echo "<ol>";
    echo "<li>Ensure you have a valid Africa's Talking account</li>";
    echo "<li>Create a proper .env file in the sms-service directory with the following content:</li>";
    echo "</ol>";
    
    echo "<pre>AT_API_KEY=your_api_key_here
AT_USERNAME=your_username_here
PORT=3000</pre>";
}
echo "</div>";

// STEP 3: Test direct API connection
echo "<h2>Step 3: Direct API Connection</h2>";
echo "<div class='step'>";

if ($serviceRunning) {
    echo "<p>Testing direct connection to the SMS service API...</p>";
    
    $apiUrl = 'http://localhost:3000/health';
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    $apiConnectionValid = ($httpCode === 200 && !$error);
    
    echo "API Connection: " . formatStatus($apiConnectionValid ? "SUCCESSFUL" : "FAILED", $apiConnectionValid) . "<br>";
    echo "HTTP Status Code: " . $httpCode . "<br>";
    
    if (!$apiConnectionValid) {
        echo "<p class='error'>Failed to connect directly to the SMS API.</p>";
        if ($error) {
            echo "<p>Error: $error</p>";
        }
        echo "<h3>Solutions:</h3>";
        echo "<ol>";
        echo "<li>Make sure your firewall is not blocking localhost connections</li>";
        echo "<li>Check if the SMS service is running on the correct port (3000)</li>";
        echo "<li>Restart the SMS service</li>";
        echo "</ol>";
    } else {
        echo "<p class='success'>Successfully connected to the SMS API.</p>";
        echo "<p>API Response: $response</p>";
    }
} else {
    echo "<p class='warning'>Skipping API connection test because SMS service is not running.</p>";
}
echo "</div>";

// STEP 4: Check if sample phone number is valid
echo "<h2>Step 4: Phone Number Validation</h2>";
echo "<div class='step'>";

$testPhone = isset($_POST['phone']) ? $_POST['phone'] : "+254750014181";
$phoneValid = !empty($testPhone) && (strpos($testPhone, '+') === 0) && strlen($testPhone) >= 10;

echo "<form method='post' action='#phone-test'>";
echo "Test Phone Number: <input type='text' name='phone' value='$testPhone' style='padding:5px; width:200px;'>";
echo "<button type='submit'>Validate</button>";
echo "</form>";

echo "<a name='phone-test'></a>";
echo "Phone Format: " . formatStatus($phoneValid ? "VALID" : "INVALID", $phoneValid) . "<br>";

if (!$phoneValid) {
    echo "<p class='error'>The phone number format is invalid.</p>";
    echo "<h3>Solutions:</h3>";
    echo "<ol>";
    echo "<li>Ensure the phone number starts with a plus sign (+)</li>";
    echo "<li>Use the full international format (e.g., +254XXXXXXXXX)</li>";
    echo "<li>Remove any spaces or special characters</li>";
    echo "</ol>";
}
echo "</div>";

// STEP 5: Test sending a simple SMS
echo "<h2>Step 5: Simple SMS Test</h2>";
echo "<div class='step'>";

if (isset($_POST['send_test']) && $_POST['send_test'] === '1') {
    if ($serviceRunning && $phoneValid) {
        $testMessage = "This is a test message from the Child Immunization System. Time: " . date('H:i:s');
        echo "<p>Sending test SMS to: $testPhone</p>";
        echo "<p>Message: $testMessage</p>";
        
        $result = sendSMSViaNodeService($testPhone, $testMessage, 'test');
        
        if (isset($result['status']) && $result['status'] === 'success') {
            echo "<p class='success'>SMS sent successfully!</p>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
        } else {
            echo "<p class='error'>Failed to send SMS.</p>";
            echo "<h3>Error Details:</h3>";
            echo "<pre>";
            print_r($result);
            echo "</pre>";
            
            echo "<h3>Solutions:</h3>";
            echo "<ol>";
            echo "<li>Check if your Africa's Talking account has sufficient credit</li>";
            echo "<li>Verify that the phone number is correct and can receive SMS</li>";
            echo "<li>Make sure the API credentials are valid</li>";
            echo "<li>Check the network connectivity from your server to Africa's Talking</li>";
            echo "</ol>";
        }
    } else {
        echo "<p class='error'>Cannot send SMS because either:</p>";
        echo "<ul>";
        if (!$serviceRunning) echo "<li>SMS service is not running</li>";
        if (!$phoneValid) echo "<li>Phone number format is invalid</li>";
        echo "</ul>";
    }
} else {
    echo "<form method='post' action='#sms-test'>";
    echo "<input type='hidden' name='phone' value='$testPhone'>";
    echo "<input type='hidden' name='send_test' value='1'>";
    echo "<button type='submit' class='test-btn'>Send Test SMS</button>";
    echo "</form>";
    echo "<a name='sms-test'></a>";
    echo "<p>Click the button above to send a test SMS to $testPhone</p>";
}
echo "</div>";

// STEP 6: Check database records
echo "<h2>Step 6: Database Records</h2>";
echo "<div class='step'>";

// Count total children
$childCountStmt = $conn->query("SELECT COUNT(*) as total FROM children");
$childCount = $childCountStmt->fetch(PDO::FETCH_ASSOC)['total'];

// Get the most recent 5 children
$recentChildrenStmt = $conn->query("SELECT child_id, full_name, guardian_name, phone, registration_date FROM children ORDER BY registration_date DESC LIMIT 5");
$recentChildren = $recentChildrenStmt->fetchAll(PDO::FETCH_ASSOC);

echo "<p>Total children in database: <strong>$childCount</strong></p>";
echo "<p>Recently registered children:</p>";

if (count($recentChildren) > 0) {
    echo "<table>";
    echo "<tr><th>ID</th><th>Name</th><th>Guardian</th><th>Phone</th><th>Registration Date</th><th>Test SMS</th></tr>";
    
    foreach ($recentChildren as $child) {
        echo "<tr>";
        echo "<td>{$child['child_id']}</td>";
        echo "<td>{$child['full_name']}</td>";
        echo "<td>{$child['guardian_name']}</td>";
        
        $phoneClass = (strpos($child['phone'], '+') === 0 && strlen($child['phone']) >= 10) ? 'success' : 'error';
        echo "<td class='$phoneClass'>{$child['phone']}</td>";
        
        echo "<td>{$child['registration_date']}</td>";
        echo "<td>";
        echo "<form method='post' action='#child-test-{$child['child_id']}'>";
        echo "<input type='hidden' name='child_id' value='{$child['child_id']}'>";
        echo "<input type='hidden' name='phone' value='{$child['phone']}'>";
        echo "<input type='hidden' name='test_child' value='1'>";
        echo "<button type='submit' class='test-btn'>Test</button>";
        echo "</form>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
} else {
    echo "<p>No children found in the database.</p>";
}

// Test sending SMS to a specific child
if (isset($_POST['test_child']) && $_POST['test_child'] === '1' && isset($_POST['child_id'])) {
    $childId = $_POST['child_id'];
    echo "<a name='child-test-$childId'></a>";
    echo "<h3>Testing SMS for Child ID: $childId</h3>";
    
    if ($serviceRunning) {
        try {
            // Get child information
            $stmt = $conn->prepare("SELECT * FROM children WHERE child_id = ?");
            $stmt->execute([$childId]);
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($child) {
                $childName = $child['full_name'];
                $guardianName = $child['guardian_name'];
                $phone = $child['phone'];
                
                echo "<p>Child: $childName</p>";
                echo "<p>Guardian: $guardianName</p>";
                echo "<p>Phone: $phone</p>";
                
                // Check phone format
                $phoneValid = !empty($phone) && (strpos($phone, '+') === 0) && strlen($phone) >= 10;
                echo "Phone Format: " . formatStatus($phoneValid ? "VALID" : "INVALID", $phoneValid) . "<br>";
                
                if (!$phoneValid) {
                    echo "<p class='error'>Invalid phone number format. Cannot send SMS.</p>";
                } else {
                    // Generate vaccination schedule
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
                        LIMIT 10
                    ");
                    
                    $vaccineSchedules = $scheduleStmt->fetchAll(PDO::FETCH_ASSOC);
                    $groupedVaccinesByDate = [];
                    $birthDate = new DateTime($child['date_of_birth']);
                    
                    foreach ($vaccineSchedules as $vaccine) {
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
                    
                    echo "<p>Generated " . count($groupedVaccinesByDate) . " vaccination dates</p>";
                    
                    // Display upcoming vaccination details
                    echo "<h4>Detailed Upcoming Vaccinations:</h4>";
                    echo "<div style='background:#f5f5f5; padding:10px; border-left:4px solid #4CAF50; margin-bottom:15px;'>";
                    
                    // Sort dates to ensure we're showing the next upcoming vaccinations
                    $dates = array_keys($groupedVaccinesByDate);
                    sort($dates);
                    
                    $today = new DateTime();
                    $upcomingDates = array_filter($dates, function($date) use ($today) {
                        return new DateTime($date) >= $today;
                    });
                    
                    if (count($upcomingDates) > 0) {
                        $count = 0;
                        foreach ($upcomingDates as $date) {
                            if ($count >= 3) break; // Show only the next 3 dates
                            
                            $formattedDate = date('d-m-Y (l)', strtotime($date));
                            echo "<p><strong>📅 " . $formattedDate . ":</strong></p>";
                            echo "<ul style='margin-top:5px;'>";
                            
                            foreach ($groupedVaccinesByDate[$date] as $vaccine) {
                                echo "<li style='margin-bottom:8px;'>";
                                echo "<strong>" . $vaccine['vaccine_name'] . " (Dose " . $vaccine['dose_number'] . ")</strong>";
                                
                                // Add age information
                                if (isset($vaccine['age_unit']) && isset($vaccine['age_value'])) {
                                    echo " - Given at " . $vaccine['age_value'] . " " . $vaccine['age_unit'];
                                }
                                
                                // Add target disease if available
                                if (isset($vaccine['target_disease']) && !empty($vaccine['target_disease'])) {
                                    echo "<br><em>Protects against: " . $vaccine['target_disease'] . "</em>";
                                }
                                
                                // Add administration method if available
                                if (isset($vaccine['administration_method']) && !empty($vaccine['administration_method'])) {
                                    echo "<br>Method: " . $vaccine['administration_method'];
                                }
                                
                                echo "</li>";
                            }
                            
                            echo "</ul>";
                            $count++;
                        }
                        
                        // Show message if there are more dates
                        if (count($upcomingDates) > 3) {
                            echo "<p><em>+" . (count($upcomingDates) - 3) . " more future vaccination dates...</em></p>";
                        }
                    } else {
                        echo "<p>No upcoming vaccinations found. All scheduled dates are in the past.</p>";
                    }
                    
                    echo "</div>";
                    
                    // Create a custom message for SMS preview
                    $customMessage = "Dear $guardianName, ";
                    $customMessage .= "here is the vaccination schedule for $childName (ID: $childId):\n\n";
                    
                    $upcomingCount = 0;
                    foreach ($upcomingDates as $date) {
                        if ($upcomingCount >= 2) break; // Only include the next 2 dates in preview
                        
                        $formattedDate = date('d-m-Y', strtotime($date));
                        $customMessage .= "📅 " . $formattedDate . ":\n";
                        
                        foreach ($groupedVaccinesByDate[$date] as $vaccine) {
                            $customMessage .= "• " . $vaccine['vaccine_name'] . " (Dose " . $vaccine['dose_number'] . ")";
                            
                            // Add target disease if available
                            if (isset($vaccine['target_disease']) && !empty($vaccine['target_disease'])) {
                                $customMessage .= " - Protects against: " . $vaccine['target_disease'];
                            }
                            
                            $customMessage .= "\n";
                        }
                        $customMessage .= "\n";
                        $upcomingCount++;
                    }
                    
                    if (count($upcomingDates) > 2) {
                        $customMessage .= "+" . (count($upcomingDates) - 2) . " more dates. We'll send reminders before each appointment.";
                    }
                    
                    // Show the custom message preview
                    echo "<h4>SMS Message Preview:</h4>";
                    echo "<pre style='background:#e8f5e9; padding:15px; border:1px solid #4CAF50; white-space: pre-wrap;'>";
                    echo htmlspecialchars($customMessage);
                    echo "</pre>";
                    
                    // Add option to send the custom message
                    echo "<form method='post' action='#child-test-{$child['child_id']}'>";
                    echo "<input type='hidden' name='child_id' value='{$child['child_id']}'>";
                    echo "<input type='hidden' name='phone' value='{$child['phone']}'>";
                    echo "<input type='hidden' name='custom_message' value='" . htmlspecialchars($customMessage) . "'>";
                    echo "<input type='hidden' name='send_custom' value='1'>";
                    echo "<button type='submit' class='test-btn'>Send Detailed Message</button>";
                    echo "</form>";
                    
                    // Send custom message if requested
                    if (isset($_POST['send_custom']) && $_POST['send_custom'] === '1' && isset($_POST['custom_message'])) {
                        $customMsg = $_POST['custom_message'];
                        echo "<h4>Sending Custom Message:</h4>";
                        $customResult = sendSMSViaNodeService($phone, $customMsg, 'detailed_schedule');
                        
                        if (isset($customResult['status']) && $customResult['status'] === 'success') {
                            echo "<p class='success'>Detailed SMS sent successfully!</p>";
                        } else {
                            echo "<p class='error'>Failed to send detailed SMS.</p>";
                            echo "<pre>";
                            print_r($customResult);
                            echo "</pre>";
                        }
                    }
                    
                    // Send SMS with schedule
                    echo "<h4>Standard SMS Test:</h4>";
                    echo "<p>Sending standard SMS with vaccination schedule...</p>";
                    
                    $smsResult = sendRegistrationWithScheduleSMS($phone, $childName, $guardianName, $groupedVaccinesByDate, $childId);
                    
                    if (isset($smsResult['status']) && $smsResult['status'] === 'success') {
                        echo "<p class='success'>SMS sent successfully!</p>";
                        echo "<pre>";
                        print_r($smsResult);
                        echo "</pre>";
                    } else {
                        echo "<p class='error'>Failed to send SMS with vaccination schedule.</p>";
                        echo "<h3>Error Details:</h3>";
                        echo "<pre>";
                        print_r($smsResult);
                        echo "</pre>";
                        
                        echo "<h3>Solutions:</h3>";
                        echo "<ol>";
                        echo "<li>Check if your Africa's Talking account has sufficient credit</li>";
                        echo "<li>Verify that the phone number is correct and can receive SMS</li>";
                        echo "<li>Make sure the API credentials are valid</li>";
                        echo "</ol>";
                    }
                }
            } else {
                echo "<p class='error'>Child not found with ID: $childId</p>";
            }
        } catch (Exception $e) {
            echo "<p class='error'>Error: " . $e->getMessage() . "</p>";
        }
    } else {
        echo "<p class='error'>Cannot test because SMS service is not running.</p>";
    }
}
echo "</div>";

// STEP 7: Check the logs
echo "<h2>Step 7: PHP Error Logs</h2>";
echo "<div class='step'>";

// Try to find PHP error log
$possibleLogLocations = [
    'C:/xampp/php/logs/php_error_log',
    'C:/xampp/logs/php_error_log',
    '/var/log/apache2/error.log',
    './php_errors.log'
];

$logFound = false;
$logContent = '';

foreach ($possibleLogLocations as $logFile) {
    if (file_exists($logFile) && is_readable($logFile)) {
        $logFound = true;
        $logContent = shell_exec("type $logFile 2>&1 | findstr /i \"SMS service\"");
        if (empty($logContent)) {
            $logContent = "No SMS-related errors found in the log.";
        }
        break;
    }
}

if ($logFound) {
    echo "<p>Found PHP error log. SMS-related entries:</p>";
    echo "<pre>$logContent</pre>";
} else {
    echo "<p class='warning'>Could not locate PHP error log.</p>";
}
echo "</div>";

// STEP 8: Summary and Recommendations
echo "<h2>Step 8: Summary & Recommendations</h2>";
echo "<div class='step'>";

echo "<h3>Summary:</h3>";
echo "<ul>";
echo "<li>SMS Service: " . formatStatus($serviceRunning ? "RUNNING" : "NOT RUNNING", $serviceRunning) . "</li>";
echo "<li>API Configuration: " . formatStatus($envConfigValid ? "VALID" : "INVALID", $envConfigValid) . "</li>";
if (isset($apiConnectionValid)) {
    echo "<li>API Connection: " . formatStatus($apiConnectionValid ? "SUCCESSFUL" : "FAILED", $apiConnectionValid) . "</li>";
}
echo "<li>Phone Number Format: " . formatStatus($phoneValid ? "VALID" : "INVALID", $phoneValid) . "</li>";
echo "</ul>";

echo "<h3>Final Recommendations:</h3>";
echo "<ol>";
if (!$serviceRunning) {
    echo "<li class='error'>Start the SMS service by running <code>start_sms_service.bat</code></li>";
}
if (!$envConfigValid) {
    echo "<li class='error'>Configure proper Africa's Talking API credentials in the .env file</li>";
}
if (isset($apiConnectionValid) && !$apiConnectionValid) {
    echo "<li class='error'>Check firewall settings and ensure the SMS service is running on port 3000</li>";
}
if (!$phoneValid) {
    echo "<li class='error'>Ensure phone numbers are in international format (e.g., +254XXXXXXXXX)</li>";
}
echo "<li>Check if your Africa's Talking account has sufficient credit</li>";
echo "<li>Verify network connectivity from your server to Africa's Talking API</li>";
echo "</ol>";

echo "</div>";

// Additional Tools
echo "<h2>Additional Tools</h2>";
echo "<div class='step'>";
echo "<a href='test_sms_server.php' class='btn'>SMS Server Test</a>";
echo "<a href='test_node_sms.php' class='btn'>Simple SMS Test</a>";
echo "<a href='debug_sms.php' class='btn'>Debug SMS Details</a>";
echo "</div>";

echo "</div></body></html>";
?> 