<?php
// Turn off PHP error reporting for JSON output
error_reporting(0);
ini_set('display_errors', 0);

// Define root path for includes
define('ROOT_PATH', dirname(__FILE__) . '/../');

// Set content type to JSON at the very beginning
header('Content-Type: application/json');

try {
    // Include the auth check file
    require_once ROOT_PATH . 'includes/auth_check.php';
    require_once ROOT_PATH . 'includes/sms.php';

    // Include the database connection file
    require_once ROOT_PATH . 'backend/db.php';

    // Check if user has admin role
    if (!isset($_SESSION['user']) || strtolower($_SESSION['user']['role']) !== 'admin') {
        echo json_encode(['success' => false, 'message' => 'Unauthorized access']);
        exit();
    }

    // Check if it's a POST request
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request method']);
        exit();
    }

    // Get form data
    $message = isset($_POST['message']) ? trim($_POST['message']) : '';
    $recipientType = isset($_POST['recipientType']) ? $_POST['recipientType'] : '';
    $messageType = isset($_POST['messageType']) ? $_POST['messageType'] : 'reminder'; // Default to reminder

    // Validate message
    if (empty($message)) {
        echo json_encode(['success' => false, 'message' => 'Message is required']);
        exit();
    }

    $recipients = [];
    
    if ($recipientType === 'all') {
        // Get all phone numbers with child and guardian names
        $query = "SELECT child_id, full_name, guardian_name, phone FROM children WHERE phone IS NOT NULL AND phone != ''";
        $stmt = $conn->prepare($query);
        $stmt->execute();
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipients[] = [
                'child_id' => $row['child_id'],
                'phone' => $row['phone'],
                'child_name' => $row['full_name'],
                'guardian_name' => $row['guardian_name']
            ];
        }
    } elseif ($recipientType === 'specific' && isset($_POST['recipients']) && is_array($_POST['recipients'])) {
        // Get specific phone numbers with child and guardian names
        $childIds = $_POST['recipients'];
        $placeholders = implode(',', array_fill(0, count($childIds), '?'));
        
        $query = "SELECT child_id, full_name, guardian_name, phone FROM children WHERE child_id IN ($placeholders) AND phone IS NOT NULL AND phone != ''";
        $stmt = $conn->prepare($query);
        $stmt->execute($childIds);
        
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $recipients[] = [
                'child_id' => $row['child_id'],
                'phone' => $row['phone'],
                'child_name' => $row['full_name'],
                'guardian_name' => $row['guardian_name']
            ];
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid recipient type or no recipients selected']);
        exit();
    }
    
    // Check if we have any phone numbers
    if (empty($recipients)) {
        echo json_encode(['success' => false, 'message' => 'No valid phone numbers found']);
        exit();
    }
    
    // Determine message type based on template selection
    if (isset($_POST['template'])) {
        switch ($_POST['template']) {
            case 'missed':
                $messageType = 'missed';
                break;
            case 'upcoming':
                $messageType = 'reminder';
                break;
            case 'rescheduled':
                $messageType = 'rescheduled';
                break;
            default:
                $messageType = 'reminder';
        }
    }
    
    // Process each recipient individually to personalize the message
    $successCount = 0;
    $failureCount = 0;
    $lastError = '';
    
    foreach ($recipients as $recipient) {
        // Replace placeholders with actual values
        $personalizedMessage = $message;
        $personalizedMessage = str_replace('[PARENT_NAME]', $recipient['guardian_name'], $personalizedMessage);
        $personalizedMessage = str_replace('[CHILD_NAME]', $recipient['child_name'], $personalizedMessage);
        
        // Send the SMS to this recipient
        $result = sendSMS($recipient['phone'], $personalizedMessage);
        
        // Log the SMS activity
        $status = $result['success'] ? 'success' : 'failed';
        $response = $result['success'] ? json_encode($result) : json_encode(['error' => $result['message']]);
        
        $logQuery = "INSERT INTO sms_logs (child_id, recipient, message, response, status, message_type) 
                      VALUES (?, ?, ?, ?, ?, ?)";
        $logStmt = $conn->prepare($logQuery);
        $logStmt->execute([
            $recipient['child_id'],
            $recipient['phone'],
            $personalizedMessage,
            $response,
            $status,
            $messageType
        ]);
        
        // Count successes and failures
        if ($result['success']) {
            $successCount++;
        } else {
            $failureCount++;
            $lastError = $result['message'];
        }
    }
    
    // Return the overall result
    if ($failureCount === 0) {
        echo json_encode(['success' => true, 'message' => "SMS sent successfully to $successCount recipients."]);
    } else if ($successCount === 0) {
        echo json_encode(['success' => false, 'message' => "Failed to send SMS to all recipients. Error: $lastError"]);
    } else {
        echo json_encode(['success' => true, 'message' => "SMS sent successfully to $successCount recipients. Failed to send to $failureCount recipients."]);
    }
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
