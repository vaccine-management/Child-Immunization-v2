<?php
/**
 * Test Direct SMS API - Bypassing Node.js Service
 * This script tests sending SMS directly using Africa's Talking API
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once 'sms-service/sms-adapter.php';

echo "<pre>";
echo "⚠️ DIRECT SMS API TEST ⚠️\n";
echo "=========================\n";
echo "This test will send an SMS directly via Africa's Talking API\n\n";

// Test phone number - YOU MUST CHANGE THIS TO A VALID NUMBER
$testPhone = isset($_GET['phone']) ? $_GET['phone'] : "+254750014181";
echo "Recipient Phone: $testPhone\n\n";

// Test message
$message = "This is a DIRECT API test message from Child Immunization System. Time: " . date('H:i:s');
echo "Message Content: $message\n\n";

// Check if we should actually send the message
if (isset($_GET['send']) && $_GET['send'] == '1') {
    echo "Sending SMS directly via Africa's Talking API...\n";
    $result = sendSMSDirectly($testPhone, $message);
    
    echo "\nAPI Test Results:\n";
    echo "----------------\n";
    echo "Status: " . ($result['status'] ?? 'Unknown') . "\n";
    
    if (isset($result['status']) && $result['status'] === 'success') {
        echo "Message ID: " . ($result['messageId'] ?? 'N/A') . "\n";
        echo "Number: " . ($result['number'] ?? 'N/A') . "\n";
        echo "\n✅ SMS sent successfully! Check your phone for the message.\n";
    } else {
        echo "Error: " . ($result['message'] ?? 'Unknown error') . "\n";
        if (isset($result['details'])) {
            echo "\nError Details:\n";
            print_r($result['details']);
        }
        echo "\n❌ SMS sending failed. See error details above.\n";
    }
} else {
    echo "To send the SMS, add ?send=1 to the URL\n";
    echo "Example: <a href='?phone=$testPhone&send=1'>test_direct_sms.php?phone=$testPhone&send=1</a>\n";
}

echo "</pre>";
?> 