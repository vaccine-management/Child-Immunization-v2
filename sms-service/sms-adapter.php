<?php
/**
 * SMS Adapter for Child Immunization System
 * This file provides functions to send SMS using the Node.js SMS service
 */

// Initialize error logging for SMS service
if (!defined('SMS_ADAPTER_INITIALIZED')) {
    define('SMS_ADAPTER_INITIALIZED', true);
    error_log("SMS Adapter initialized at " . date('Y-m-d H:i:s'));
    
    // Check for the .env file
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        error_log("Warning: SMS Service .env file not found at {$envFile}");
    } else {
        error_log("SMS Service .env file found at {$envFile}");
    }
}

/**
 * Check if the SMS service is running
 * 
 * @return boolean True if the service is running, false otherwise
 */
function isSMSServiceRunning() {
    $apiUrl = 'http://localhost:3000/health';
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // Short timeout for quick feedback
    curl_setopt($ch, CURLOPT_NOBODY, false);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error || $httpCode !== 200) {
        error_log("SMS Service Check Failed: " . ($error ?: "HTTP Code $httpCode"));
        return false;
    }
    
    return true;
}

/**
 * Send SMS via Node.js SMS Service
 * 
 * @param string $phone The recipient's phone number
 * @param string $message The message to send
 * @param string $messageType The type of message (reminder, registration, missed, rescheduled)
 * @return array The response from the SMS service
 */
function sendSMSViaNodeService($phone, $message, $messageType = 'reminder') {
    $apiUrl = 'http://localhost:3000/send-sms';
    
    // Check if phone number is empty or invalid
    if (empty($phone)) {
        error_log("SMS Service Error: Empty phone number provided");
        return [
            'status' => 'error',
            'message' => 'Phone number is required'
        ];
    }
    
    // Ensure phone starts with +
    if (strpos($phone, '+') !== 0 && is_numeric(substr($phone, 0, 1))) {
        $phone = '+' . $phone;
        error_log("SMS Service: Added + prefix to phone number: $phone");
    }
    
    // Check if SMS service is running
    if (!isSMSServiceRunning()) {
        error_log("SMS Service Error: Service is not running or unreachable");
        return [
            'status' => 'error',
            'message' => 'SMS service is not running. Please start the service using start_sms_service.bat'
        ];
    }
    
    $data = [
        'to' => $phone,
        'message' => $message,
        'messageType' => $messageType
    ];
    
    // Log the attempt before making the request
    error_log("SMS Service: Attempting to send $messageType SMS to $phone");
    error_log("SMS Service: Message content: " . substr($message, 0, 100) . (strlen($message) > 100 ? '...' : ''));
    
    // Use cURL to make the request
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Accept: application/json'
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Add additional error info
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
    
    if ($error) {
        error_log("SMS Service Error: " . $error);
        error_log("SMS Service Verbose: " . $verboseLog);
        return [
            'status' => 'error',
            'message' => 'cURL Error: ' . $error,
            'httpCode' => $httpCode,
            'verboseLog' => $verboseLog,
            'curlInfo' => $info
        ];
    }
    
    // Parse the response
    $result = json_decode($response, true);
    
    error_log("SMS Service Response: " . $response);
    
    if ($httpCode >= 400 || $httpCode === 0) {
        error_log("SMS Service Error: HTTP $httpCode - " . ($result['message'] ?? 'Unknown error'));
        error_log("SMS Service Verbose: " . $verboseLog);
        return [
            'status' => 'error',
            'message' => ($result['message'] ?? 'Unknown error') . ' (HTTP ' . $httpCode . ')',
            'httpCode' => $httpCode,
            'details' => $result,
            'verboseLog' => $verboseLog,
            'curlInfo' => $info
        ];
    }
    
    return $result;
}

/**
 * Send a registration notification SMS
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @return array The response from the SMS service
 */
function sendRegistrationSMS($phone, $childName, $guardianName) {
    $message = "Dear $guardianName, thank you for registering $childName in the Immunization Program. We will send you reminders for upcoming vaccinations.";
    
    return sendSMSViaNodeService($phone, $message, 'registration');
}

/**
 * Send an upcoming vaccination reminder SMS
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @param string $vaccineName The vaccine name
 * @param string $doseNumber The dose number
 * @param string $dueDate The due date (formatted as d-m-Y)
 * @return array The response from the SMS service
 */
function sendVaccinationReminderSMS($phone, $childName, $guardianName, $vaccineName, $doseNumber, $dueDate) {
    $message = "Dear $guardianName, $childName is due for $vaccineName dose $doseNumber on $dueDate. Please visit your nearest health facility.";
    
    return sendSMSViaNodeService($phone, $message, 'reminder');
}

/**
 * Send a missed vaccination notification SMS
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @param string $vaccineName The vaccine name
 * @param string $doseNumber The dose number
 * @param string $dueDate The due date (formatted as d-m-Y)
 * @return array The response from the SMS service
 */
function sendMissedVaccinationSMS($phone, $childName, $guardianName, $vaccineName, $doseNumber, $dueDate) {
    $message = "Dear $guardianName, $childName has missed $vaccineName dose $doseNumber scheduled for $dueDate. Please visit your nearest health facility as soon as possible.";
    
    return sendSMSViaNodeService($phone, $message, 'missed');
}

/**
 * Send a rescheduled vaccination notification SMS
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @param string $vaccineName The vaccine name
 * @param string $doseNumber The dose number
 * @param string $newDate The new date (formatted as d-m-Y)
 * @return array The response from the SMS service
 */
function sendRescheduledVaccinationSMS($phone, $childName, $guardianName, $vaccineName, $doseNumber, $newDate) {
    $message = "Dear $guardianName, $childName's appointment for $vaccineName dose $doseNumber has been rescheduled. Please visit your nearest health facility on $newDate.";
    
    return sendSMSViaNodeService($phone, $message, 'rescheduled');
}

/**
 * Send a registration notification SMS with vaccination schedule
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @param array $vaccineSchedule Array of scheduled vaccines with dates
 * @param string $childID The child's ID
 * @return array The response from the SMS service
 */
function sendRegistrationWithScheduleSMS($phone, $childName, $guardianName, $vaccineSchedule, $childID = '') {
    $message = "Dear $guardianName, thank you for registering $childName";
    
    // Add child ID if provided
    if (!empty($childID)) {
        $message .= " (ID: $childID)";
    }
    
    $message .= " in the Immunization Program.\n\nUPCOMING VACCINATIONS:";
    
    // Check if the vaccination schedule exists and is not empty
    if (empty($vaccineSchedule)) {
        $message .= "\nYour child's vaccination schedule will be provided by a healthcare worker.";
    } else {
        // Sort dates to ensure chronological order
        $dates = array_keys($vaccineSchedule);
        sort($dates);
        
        // Filter for future dates
        $today = date('Y-m-d');
        $futureDates = array_filter($dates, function($date) use ($today) {
            return $date >= $today;
        });
        
        if (empty($futureDates)) {
            $message .= "\nAll scheduled vaccinations are in the past. Please consult your healthcare provider.";
        } else {
            // Add up to 2 upcoming vaccination appointments with more details
            $count = 0;
            foreach ($futureDates as $date) {
                if ($count >= 2) break; // Limit to 2 dates to keep SMS within length limits
                
                $formattedDate = date('d-m-Y', strtotime($date));
                $message .= "\n\n📅 " . $formattedDate . ":";
                
                // Make sure the vaccine array is properly formatted
                if (!empty($vaccineSchedule[$date]) && is_array($vaccineSchedule[$date])) {
                    foreach ($vaccineSchedule[$date] as $vaccine) {
                        if (is_array($vaccine) && isset($vaccine['vaccine_name']) && isset($vaccine['dose_number'])) {
                            $message .= "\n• " . $vaccine['vaccine_name'] . " (Dose " . $vaccine['dose_number'] . ")";
                            
                            // Add target disease if available
                            if (isset($vaccine['target_disease']) && !empty($vaccine['target_disease'])) {
                                $message .= " - Protects against: " . $vaccine['target_disease'];
                            }
                        }
                    }
                }
                $count++;
            }
            
            // Add information about additional dates if there are more than 2
            if (count($futureDates) > 2) {
                $message .= "\n\n+" . (count($futureDates) - 2) . " more vaccination dates. We'll send reminders before each appointment.";
            }
        }
    }
    
    $message .= "\n\nPlease visit your health facility on these dates. For questions, call your healthcare provider.";
    
    return sendSMSViaNodeService($phone, $message, 'registration');
}

/**
 * Send SMS directly using Africa's Talking API (bypassing Node.js service)
 * Use this as a fallback when the Node.js service is not available
 * 
 * @param string $phone The recipient's phone number
 * @param string $message The message to send
 * @return array The response from the API
 */
function sendSMSDirectly($phone, $message) {
    // Read credentials from .env file
    $envFile = __DIR__ . '/.env';
    if (!file_exists($envFile)) {
        error_log("Direct SMS: .env file not found");
        return [
            'status' => 'error',
            'message' => '.env file not found. Cannot send SMS directly.'
        ];
    }
    
    // Parse .env file
    $envContent = file_get_contents($envFile);
    preg_match('/AT_API_KEY=([^\n]+)/', $envContent, $apiKeyMatches);
    preg_match('/AT_USERNAME=([^\n]+)/', $envContent, $usernameMatches);
    
    $apiKey = isset($apiKeyMatches[1]) ? trim($apiKeyMatches[1]) : '';
    $username = isset($usernameMatches[1]) ? trim($usernameMatches[1]) : '';
    
    if (empty($apiKey) || empty($username)) {
        error_log("Direct SMS: Missing API credentials in .env file");
        return [
            'status' => 'error',
            'message' => 'Missing API credentials in .env file'
        ];
    }
    
    // Ensure phone starts with +
    if (strpos($phone, '+') !== 0 && is_numeric(substr($phone, 0, 1))) {
        $phone = '+' . $phone;
        error_log("Direct SMS: Added + prefix to phone number: $phone");
    }
    
    // Prepare API request
    $url = 'https://api.africastalking.com/version1/messaging';
    $data = [
        'username' => $username,
        'to' => $phone,
        'message' => $message,
    ];
    
    // Log attempt
    error_log("Direct SMS: Attempting to send SMS to $phone directly via Africa's Talking API");
    
    // Send request
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'apiKey: ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    if ($error) {
        error_log("Direct SMS Error: " . $error);
        return [
            'status' => 'error',
            'message' => 'cURL Error: ' . $error,
            'httpCode' => $httpCode
        ];
    }
    
    // Parse response
    $result = json_decode($response, true);
    error_log("Direct SMS Response: " . $response);
    
    if (isset($result['SMSMessageData']['Recipients'][0]['status']) && 
        $result['SMSMessageData']['Recipients'][0]['status'] === 'Success') {
        return [
            'status' => 'success',
            'messageId' => $result['SMSMessageData']['Recipients'][0]['messageId'],
            'number' => $phone,
            'message' => 'SMS sent directly via Africa\'s Talking API'
        ];
    } else {
        error_log("Direct SMS API Error: " . ($result['SMSMessageData']['Message'] ?? 'Unknown error'));
        return [
            'status' => 'error',
            'message' => $result['SMSMessageData']['Message'] ?? 'Unknown error',
            'details' => $result
        ];
    }
}

/**
 * Send registration with schedule SMS with fallback options
 * This function will try multiple methods to ensure the SMS is sent
 * 
 * @param string $phone The recipient's phone number
 * @param string $childName The child's name
 * @param string $guardianName The guardian's name
 * @param array $vaccineSchedule Array of scheduled vaccines with dates
 * @param string $childID The child's ID
 * @return array The response from the SMS service
 */
function sendRegistrationWithScheduleSMS_Reliable($phone, $childName, $guardianName, $vaccineSchedule, $childID = '') {
    // Make sure we don't throw exceptions
    try {
        // First try using the Node.js service
        $result = sendRegistrationWithScheduleSMS($phone, $childName, $guardianName, $vaccineSchedule, $childID);
        
        // If that fails, try sending directly via the API
        if ($result['status'] !== 'success') {
            error_log("SMS Service failed. Trying direct API method...");
            
            // Prepare the same detailed message as in the sendRegistrationWithScheduleSMS function
            $message = "Dear $guardianName, thank you for registering $childName";
            
            // Add child ID if provided
            if (!empty($childID)) {
                $message .= " (ID: $childID)";
            }
            
            $message .= " in the Immunization Program.\n\nUPCOMING VACCINATIONS:";
            
            // Check if the vaccination schedule exists and is not empty
            if (empty($vaccineSchedule)) {
                $message .= "\nYour child's vaccination schedule will be provided by a healthcare worker.";
            } else {
                // Sort dates to ensure chronological order
                $dates = array_keys($vaccineSchedule);
                sort($dates);
                
                // Filter for future dates
                $today = date('Y-m-d');
                $futureDates = array_filter($dates, function($date) use ($today) {
                    return $date >= $today;
                });
                
                if (empty($futureDates)) {
                    $message .= "\nAll scheduled vaccinations are in the past. Please consult your healthcare provider.";
                } else {
                    // Add up to 2 upcoming vaccination appointments with more details
                    $count = 0;
                    foreach ($futureDates as $date) {
                        if ($count >= 2) break; // Limit to 2 dates to keep SMS within length limits
                        
                        $formattedDate = date('d-m-Y', strtotime($date));
                        $message .= "\n\n📅 " . $formattedDate . ":";
                        
                        // Make sure the vaccine array is properly formatted
                        if (!empty($vaccineSchedule[$date]) && is_array($vaccineSchedule[$date])) {
                            foreach ($vaccineSchedule[$date] as $vaccine) {
                                if (is_array($vaccine) && isset($vaccine['vaccine_name']) && isset($vaccine['dose_number'])) {
                                    $message .= "\n• " . $vaccine['vaccine_name'] . " (Dose " . $vaccine['dose_number'] . ")";
                                    
                                    // Add target disease if available
                                    if (isset($vaccine['target_disease']) && !empty($vaccine['target_disease'])) {
                                        $message .= " - Protects against: " . $vaccine['target_disease'];
                                    }
                                }
                            }
                        }
                        $count++;
                    }
                    
                    // Add information about additional dates if there are more than 2
                    if (count($futureDates) > 2) {
                        $message .= "\n\n+" . (count($futureDates) - 2) . " more vaccination dates. We'll send reminders before each appointment.";
                    }
                }
            }
            
            $message .= "\n\nPlease visit your health facility on these dates. For questions, call your healthcare provider.";
            
            // Try sending directly
            return sendSMSDirectly($phone, $message);
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("Exception in sendRegistrationWithScheduleSMS_Reliable: " . $e->getMessage());
        return [
            'status' => 'error',
            'message' => 'Exception in SMS sending: ' . $e->getMessage()
        ];
    }
}
?> 