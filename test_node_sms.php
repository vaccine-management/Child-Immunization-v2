<?php
/**
 * Test Script for Node.js SMS Service
 * 
 * WARNING: This will send REAL SMS messages and incur charges on your Africa's Talking account
 * when using production mode!
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include the SMS adapter
require_once 'sms-service/sms-adapter.php';

echo "<pre>";
echo "⚠️ PRODUCTION SMS SERVICE TEST ⚠️\n";
echo "===============================\n";
echo "This test will send REAL SMS messages and may incur charges!\n\n";

// Test phone number - THIS SHOULD BE YOUR ACTUAL PHONE NUMBER
$testPhone = "+254750014181"; 
echo "Recipient Phone: $testPhone\n\n";

// Test message
$message = "This is a test message from the Child Immunization System. Time: " . date('H:i:s');
echo "Message Content: $message\n\n";

// Ask for confirmation
echo "Are you sure you want to send this SMS? If this appears in a browser, the SMS has already been sent.\n\n";

echo "Sending SMS...\n";
$result = sendSMSViaNodeService($testPhone, $message, 'test');

echo "\nSMS Test Results:\n";
echo "----------------\n";
echo "Status: " . ($result['status'] ?? 'Unknown') . "\n";

if (isset($result['status']) && $result['status'] === 'success') {
    echo "Message ID: " . ($result['messageId'] ?? 'N/A') . "\n";
    echo "Cost: " . ($result['cost'] ?? 'N/A') . "\n";
    echo "Number: " . ($result['number'] ?? 'N/A') . "\n";
    echo "\n✅ SMS sent successfully! Check your phone for the message.\n";
} else {
    echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
    if (isset($result['details'])) {
        echo "\nError Details:\n";
        print_r($result['details']);
    }
    echo "\n❌ SMS sending failed. See error details above.\n";
    echo "Common issues:\n";
    echo "- Insufficient credits in your Africa's Talking account\n";
    echo "- Invalid API credentials\n";
    echo "- Phone number format issues\n";
    echo "- Network connectivity problems\n";
}

echo "\nTest Registration SMS Function\n";
echo "----------------------------\n";
$registrationResult = sendRegistrationSMS($testPhone, "John Doe", "Parent Name");
echo "Status: " . ($registrationResult['status'] ?? 'Unknown') . "\n";
if (isset($registrationResult['status']) && $registrationResult['status'] === 'success') {
    echo "✅ Registration SMS sent successfully!\n";
} else {
    echo "❌ Registration SMS failed.\n";
}

echo "\nTest Registration with Schedule SMS Function\n";
echo "-------------------------------------------\n";
// Create a sample vaccination schedule
$sampleSchedule = [
    '2023-05-01' => [
        ['vaccine_name' => 'Polio', 'dose_number' => '1'],
        ['vaccine_name' => 'Hepatitis B', 'dose_number' => '1']
    ],
    '2023-06-01' => [
        ['vaccine_name' => 'Polio', 'dose_number' => '2'],
        ['vaccine_name' => 'DTP', 'dose_number' => '1']
    ],
    '2023-07-01' => [
        ['vaccine_name' => 'Polio', 'dose_number' => '3'],
        ['vaccine_name' => 'Rotavirus', 'dose_number' => '1']
    ]
];

$scheduleResult = sendRegistrationWithScheduleSMS($testPhone, "John Doe", "Parent Name", $sampleSchedule);
echo "Status: " . ($scheduleResult['status'] ?? 'Unknown') . "\n";
if (isset($scheduleResult['status']) && $scheduleResult['status'] === 'success') {
    echo "✅ Registration with schedule SMS sent successfully!\n";
} else {
    echo "❌ Registration with schedule SMS failed.\n";
}

echo "</pre>";
?> 