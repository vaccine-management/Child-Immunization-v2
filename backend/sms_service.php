<?php
// SMS service using Africa's Talking API
require_once 'db.php';

class SMSService {
    private $username;
    private $apiKey;
    private $conn;
    
    public function __construct($username, $apiKey, $conn) {
        $this->username = $username;
        $this->apiKey = $apiKey;
        $this->conn = $conn;
    }
    
    /**
     * Send SMS using Africa's Talking API
     * 
     * @param string $to Phone number to send the SMS to
     * @param string $message Content of the SMS
     * @return array Response from the API
     */
    public function sendSMS($to, $message) {
        // Format phone number (ensure it includes country code)
        $to = $this->formatPhoneNumber($to);
        
        // Africa's Talking API endpoint
        $url = 'https://api.africastalking.com/version1/messaging';
        
        // Prepare data for the API request
        $data = [
            'username' => $this->username,
            'to' => $to,
            'message' => $message,
        ];
        
        // Initialize cURL session
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
            'apiKey: ' . $this->apiKey
        ]);
        
        // Execute cURL session and get the response
        $response = curl_exec($ch);
        $error = curl_error($ch);
        
        // Close cURL session
        curl_close($ch);
        
        // Log SMS
        $this->logSMS($to, $message, $response);
        
        if ($error) {
            return ['status' => 'error', 'message' => $error];
        }
        
        return ['status' => 'success', 'response' => json_decode($response, true)];
    }
    
    /**
     * Format phone number to include country code
     * 
     * @param string $phoneNumber
     * @return string Formatted phone number
     */
    private function formatPhoneNumber($phoneNumber) {
        // Remove any non-numeric characters
        $phoneNumber = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // If number starts with 0, replace with +254 (Kenya's country code)
        if (substr($phoneNumber, 0, 1) === '0') {
            $phoneNumber = '+254' . substr($phoneNumber, 1);
        }
        
        // If number doesn't have a country code, add +254
        if (substr($phoneNumber, 0, 1) !== '+') {
            $phoneNumber = '+254' . $phoneNumber;
        }
        
        return $phoneNumber;
    }
    
    /**
     * Log SMS to database
     * 
     * @param string $recipient Phone number
     * @param string $message SMS content
     * @param string $response API response
     * @param string $messageType Type of message (registration, reminder, missed, rescheduled)
     * @param int $vaccinationId Optional vaccination ID
     * @param int $childId Optional child ID
     */
    private function logSMS($recipient, $message, $response, $messageType = 'reminder', $vaccinationId = null, $childId = null) {
        try {
            $stmt = $this->conn->prepare("
                INSERT INTO sms_logs (
                    recipient, message, response, sent_at, message_type, vaccination_id, child_id
                ) VALUES (
                    ?, ?, ?, NOW(), ?, ?, ?
                )
            ");
            $stmt->execute([$recipient, $message, $response, $messageType, $vaccinationId, $childId]);
        } catch (PDOException $e) {
            // Log error
            error_log("SMS Log Error: " . $e->getMessage());
        }
    }
    
    /**
     * Send registration notification with vaccination schedule
     * 
     * @param int $childId Child's ID in the database
     * @return array Response from the API
     */
    public function sendRegistrationNotification($childId) {
        try {
            // Get child details
            $stmt = $this->conn->prepare("
                SELECT c.full_name, c.child_id, u.phone 
                FROM children c 
                JOIN users u ON c.guardian_id = u.id 
                WHERE c.id = ?
            ");
            $stmt->execute([$childId]);
            $child = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$child) {
                return ['status' => 'error', 'message' => 'Child not found'];
            }
            
            // Get upcoming vaccinations for the child
            $stmt = $this->conn->prepare("
                SELECT vaccine_name, scheduled_date, scheduled_time 
                FROM vaccinations 
                WHERE child_id = ? AND status = 'Scheduled' 
                ORDER BY scheduled_date ASC, scheduled_time ASC
            ");
            $stmt->execute([$childId]);
            $vaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Construct message
            $message = "Dear Parent, your child {$child['full_name']} (ID: {$child['child_id']}) has been registered in our Immunization System.\n\n";
            $message .= "Upcoming Vaccination Schedule:\n";
            
            foreach ($vaccinations as $vac) {
                $date = date('d M Y', strtotime($vac['scheduled_date']));
                $time = date('h:i A', strtotime($vac['scheduled_time']));
                $message .= "- {$vac['vaccine_name']} on {$date} at {$time}\n";
            }
            
            $message .= "\nThank you for choosing our services.";
            
            // Send SMS
            return $this->sendSMS($child['phone'], $message);
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send reminder for upcoming vaccinations
     * 
     * @param int $days Number of days in advance to send reminder
     * @return array Status of sent SMS messages
     */
    public function sendUpcomingVaccinationReminders($days = 3) {
        try {
            // Get vaccinations scheduled in the specified days
            $stmt = $this->conn->prepare("
                SELECT v.id, v.child_id, v.vaccine_name, v.scheduled_date, v.scheduled_time,
                       c.full_name, c.child_id AS unique_id, u.phone
                FROM vaccinations v
                JOIN children c ON v.child_id = c.id
                JOIN users u ON c.guardian_id = u.id
                WHERE v.status = 'Scheduled' 
                  AND v.scheduled_date = DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY v.scheduled_time ASC
            ");
            $stmt->execute([$days]);
            $upcomingVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            
            foreach ($upcomingVaccinations as $vac) {
                $date = date('d M Y', strtotime($vac['scheduled_date']));
                $time = date('h:i A', strtotime($vac['scheduled_time']));
                
                $message = "Dear Parent, this is a reminder that your child {$vac['full_name']} ";
                $message .= "is scheduled for {$vac['vaccine_name']} vaccination on {$date} at {$time}. ";
                $message .= "Please ensure you arrive on time. Thank you.";
                
                $result = $this->sendSMS($vac['phone'], $message);
                $results[] = [
                    'vaccination_id' => $vac['id'],
                    'child_name' => $vac['full_name'],
                    'sms_result' => $result
                ];
            }
            
            return ['status' => 'success', 'sent' => count($results), 'details' => $results];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send notification for missed vaccinations
     * 
     * @return array Status of sent SMS messages
     */
    public function sendMissedVaccinationNotifications() {
        try {
            // Get missed vaccinations (scheduled date in the past with status still as 'Scheduled')
            $stmt = $this->conn->prepare("
                SELECT v.id, v.child_id, v.vaccine_name, v.scheduled_date, v.scheduled_time,
                       c.full_name, c.child_id AS unique_id, u.phone
                FROM vaccinations v
                JOIN children c ON v.child_id = c.id
                JOIN users u ON c.guardian_id = u.id
                WHERE v.status = 'Scheduled' 
                  AND v.scheduled_date < CURDATE()
                  AND v.id NOT IN (SELECT vaccination_id FROM sms_logs WHERE message LIKE '%missed%' AND sent_at > DATE_SUB(NOW(), INTERVAL 3 DAY))
                ORDER BY v.scheduled_date DESC
            ");
            $stmt->execute();
            $missedVaccinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $results = [];
            
            foreach ($missedVaccinations as $vac) {
                $date = date('d M Y', strtotime($vac['scheduled_date']));
                
                $message = "Dear Parent, we noticed that your child {$vac['full_name']} ";
                $message .= "missed the scheduled {$vac['vaccine_name']} vaccination on {$date}. ";
                $message .= "Please contact our facility to reschedule this important vaccination. Thank you.";
                
                $result = $this->sendSMS($vac['phone'], $message);
                $results[] = [
                    'vaccination_id' => $vac['id'],
                    'child_name' => $vac['full_name'],
                    'sms_result' => $result
                ];
            }
            
            return ['status' => 'success', 'sent' => count($results), 'details' => $results];
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Send notification for rescheduled vaccinations
     * 
     * @param int $vaccinationId ID of the rescheduled vaccination
     * @return array Response from the API
     */
    public function sendRescheduledVaccinationNotification($vaccinationId) {
        try {
            // Get vaccination details
            $stmt = $this->conn->prepare("
                SELECT v.vaccine_name, v.scheduled_date, v.scheduled_time,
                       c.full_name, c.child_id AS unique_id, u.phone
                FROM vaccinations v
                JOIN children c ON v.child_id = c.id
                JOIN users u ON c.guardian_id = u.id
                WHERE v.id = ?
            ");
            $stmt->execute([$vaccinationId]);
            $vaccination = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$vaccination) {
                return ['status' => 'error', 'message' => 'Vaccination not found'];
            }
            
            $date = date('d M Y', strtotime($vaccination['scheduled_date']));
            $time = date('h:i A', strtotime($vaccination['scheduled_time']));
            
            $message = "Dear Parent, we would like to inform you that your child {$vaccination['full_name']}'s ";
            $message .= "{$vaccination['vaccine_name']} vaccination has been rescheduled to {$date} at {$time}. ";
            $message .= "Please make a note of this change. Thank you.";
            
            return $this->sendSMS($vaccination['phone'], $message);
        } catch (PDOException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }
}
?> 