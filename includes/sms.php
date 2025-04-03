<?php
require_once __DIR__ . '/../vendor/autoload.php';
use AfricasTalking\SDK\AfricasTalking;

function sendSMS($phoneNumbers, $message) {
    // Check for environment variables first, then use fallback values
    // You should replace these values with your actual AfricasTalking credentials
    $username = isset($_ENV['AT_USERNAME']) ? $_ENV['AT_USERNAME'] : '';
    $apiKey = isset($_ENV['AT_API_KEY']) ? $_ENV['AT_API_KEY'] : '';
    $shortCode = isset($_ENV['AT_SHORTCODE']) ? $_ENV['AT_SHORTCODE'] : null;

    // Ensure credentials are available
    if (empty($username) || $username === '') {
        error_log("AfricasTalking Username not set. Using sandbox mode.");
    }
    
    if (empty($apiKey) || $apiKey === 'your-api-key') {
        return ['success' => false, 'message' => 'AfricasTalking API Key not configured. Please check your configuration.'];
    }

    // Ensure phoneNumbers is an array
    if (!is_array($phoneNumbers)) {
        $phoneNumbers = [$phoneNumbers];
    }

    // Validate and format phone numbers
    $formattedNumbers = [];
    foreach ($phoneNumbers as $phone) {
        $phone = trim($phone);
        if (empty($phone)) continue;
        if (strpos($phone, '+') !== 0) {
            $phone = '+' . $phone; // Assume international format if no + prefix
        }
        if (preg_match('/^\+\d{10,15}$/', $phone)) {
            $formattedNumbers[] = $phone;
        } else {
            error_log("Invalid phone number format: $phone");
        }
    }

    if (empty($formattedNumbers)) {
        return ['success' => false, 'message' => 'No valid phone numbers provided'];
    }

    try {
        $AT = new AfricasTalking($username, $apiKey);
        $sms = $AT->sms();

        $options = [
            'to' => implode(',', $formattedNumbers),
            'message' => $message
        ];
        if ($shortCode) {
            $options['from'] = $shortCode;
        }

        $result = $sms->send($options);

        // Log the result for debugging
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }
        file_put_contents(
            "$logDir/sms_debug.log",
            '[' . date('Y-m-d H:i:s') . '] SMS Request: ' . json_encode(['username' => $username, 'to' => implode(',', $formattedNumbers), 'message' => $message]) . PHP_EOL .  
            '[' . date('Y-m-d H:i:s') . '] SMS Result: ' . json_encode($result) . PHP_EOL,
            FILE_APPEND
        );

        if (isset($result['status']) && $result['status'] === 'success') {
            return ['success' => true, 'message' => 'SMS sent successfully'];
        } else {
            return ['success' => false, 'message' => 'Failed to send SMS: ' . json_encode($result)];
        }
    } catch (Exception $e) {
        error_log("SMS Sending Error: " . $e->getMessage());
        return ['success' => false, 'message' => 'Error sending SMS: ' . $e->getMessage()];
    }
}
?>