# SMS Service for Child Immunization System

This is a Node.js microservice for sending SMS notifications using Africa's Talking API. It provides a simple REST API that your PHP application can use to send SMS messages.

## Setup Instructions

1. **Install Node.js and npm**:
   - Download and install Node.js from [nodejs.org](https://nodejs.org/)
   - Verify installation: `node -v` and `npm -v`

2. **Install dependencies**:
   ```bash
   cd sms-service
   npm install
   ```

3. **Configure environment variables**:
   - Update the `.env` file with your Africa's Talking API credentials:
     ```
     AT_USERNAME=your_username
     AT_API_KEY=your_api_key
     PORT=3000
     NODE_ENV=production
     ```
   - For sandbox testing, use:
     ```
     AT_USERNAME=sandbox
     AT_API_KEY=your_sandbox_api_key
     ```

4. **Start the service**:
   ```bash
   npm start
   ```
   - For development with auto-reload:
     ```bash
     npm run dev
     ```

## Test the Service

1. **Run the test script**:
   ```bash
   node test.js
   ```
   - Update the phone number in `test.js` before running

2. **Check the health endpoint**:
   - Open in browser: `http://localhost:3000/health`
   - Or use curl: `curl http://localhost:3000/health`

## API Endpoints

### Send SMS

- **URL**: `/send-sms`
- **Method**: `POST`
- **Body**:
  ```json
  {
    "to": "0722123456",
    "message": "Your message here",
    "messageType": "reminder"  // Optional: reminder, registration, missed, rescheduled
  }
  ```
- **Response**:
  ```json
  {
    "status": "success",
    "messageId": "ATXid_123456789",
    "cost": "KES 1.80",
    "number": "+254722123456"
  }
  ```

## Integration with PHP Application

### Sample PHP Code to Send SMS via the Node.js Service

```php
function sendSMSViaNodeService($phone, $message, $messageType = 'reminder') {
    $apiUrl = 'http://localhost:3000/send-sms';
    
    $data = [
        'to' => $phone,
        'message' => $message,
        'messageType' => $messageType
    ];
    
    $options = [
        'http' => [
            'method' => 'POST',
            'header' => 'Content-Type: application/json',
            'content' => json_encode($data)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($apiUrl, false, $context);
    
    return json_decode($result, true);
}

// Example usage
$result = sendSMSViaNodeService('0722123456', 'Your vaccination reminder message', 'reminder');
```

## Troubleshooting

- **Verify Africa's Talking credentials** are correct in the `.env` file
- **Check the phone number format** - should be a valid Kenyan number
- **Review the logs** for any errors
- **Ensure the service is running** - check the health endpoint
- **For sandbox mode**, ensure the phone number is registered in your Africa's Talking sandbox account 