<?php
/**
 * SMS Server Connection Test
 * This script tests the connection to the Node.js SMS service
 */

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<pre>";
echo "🔄 SMS SERVER CONNECTION TEST 🔄\n";
echo "===============================\n\n";

// Define the SMS service URL
$smsServiceUrl = 'http://localhost:3000/health';

echo "Testing connection to Node.js SMS service...\n";
echo "URL: $smsServiceUrl\n\n";

// Use cURL to make a request to the server's health endpoint
$ch = curl_init($smsServiceUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout for quick feedback
curl_setopt($ch, CURLOPT_VERBOSE, true);
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$info = curl_getinfo($ch);

// Get verbose information
rewind($verbose);
$verboseLog = stream_get_contents($verbose);

curl_close($ch);

// Display the results
echo "CONNECTION TEST RESULTS:\n";
echo "------------------------\n";

if ($error) {
    echo "❌ Connection FAILED\n";
    echo "Error: $error\n";
    echo "\nVerbose Log:\n$verboseLog\n";
    
    echo "\nPossible issues:\n";
    echo "1. The Node.js SMS service is not running\n";
    echo "2. The service is running on a different port (not 3000)\n";
    echo "3. Firewall is blocking the connection\n";
    
    echo "\nRecommended actions:\n";
    echo "1. Start the SMS service by running 'node server.js' in the sms-service directory\n";
    echo "2. Check if the port is correct in sms-adapter.php\n";
    echo "3. Check firewall settings\n";
} else {
    if ($httpCode == 200) {
        echo "✅ Connection SUCCESSFUL (HTTP $httpCode)\n";
        echo "Response: $response\n";
        
        // Test a basic SMS API call
        echo "\nTesting SMS POST endpoint...\n";
        
        $testEndpoint = 'http://localhost:3000/send-sms';
        $testData = [
            'to' => '+254750014181', // Test number
            'message' => 'Test message ' . date('H:i:s'),
            'messageType' => 'test'
        ];
        
        $ch2 = curl_init($testEndpoint);
        curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch2, CURLOPT_POST, true);
        curl_setopt($ch2, CURLOPT_POSTFIELDS, json_encode($testData));
        curl_setopt($ch2, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        curl_setopt($ch2, CURLOPT_TIMEOUT, 5);
        
        $response2 = curl_exec($ch2);
        $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
        $error2 = curl_error($ch2);
        
        curl_close($ch2);
        
        if ($error2) {
            echo "❌ SMS endpoint test FAILED\n";
            echo "Error: $error2\n";
        } else {
            echo "Response code: HTTP $httpCode2\n";
            echo "Response: $response2\n";
            
            if ($httpCode2 >= 200 && $httpCode2 < 300) {
                echo "✅ SMS endpoint test SUCCESSFUL\n";
            } else {
                echo "❌ SMS endpoint test FAILED with HTTP $httpCode2\n";
            }
        }
    } else {
        echo "❌ Connection FAILED (HTTP $httpCode)\n";
        echo "Response: $response\n";
        echo "\nVerbose Log:\n$verboseLog\n";
    }
}

echo "\nCURL Information:\n";
echo "----------------\n";
echo "Total time: " . $info['total_time'] . " seconds\n";
echo "Primary IP: " . $info['primary_ip'] . "\n";
echo "Primary port: " . $info['primary_port'] . "\n";
echo "Local IP: " . $info['local_ip'] . "\n";
echo "Local port: " . $info['local_port'] . "\n";

echo "\n";
echo "Check that the Node.js SMS service is running by executing:\n";
echo "cd sms-service && node server.js\n";
echo "</pre>";
?> 