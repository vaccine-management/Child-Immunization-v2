require('dotenv').config();
const express = require('express');
const bodyParser = require('body-parser');
const cors = require('cors');
const africastalking = require('africastalking');

// Initialize Africa's Talking
const africastalkingConfig = {
  apiKey: process.env.AT_API_KEY,
  username: process.env.AT_USERNAME
};

// Initialize the SDK
const africastalkingSDK = africastalking(africastalkingConfig);

// Get the SMS service
const sms = africastalkingSDK.SMS;

// Initialize the server
const app = express();
const PORT = process.env.PORT || 3000;

// Enable CORS to allow the PHP application to make requests
app.use(cors());

// Parse JSON bodies
app.use(bodyParser.json());
app.use(bodyParser.urlencoded({ extended: true }));

// Route for health check
app.get('/health', (req, res) => {
  res.status(200).json({ status: 'OK', message: 'SMS Service is running' });
});

/**
 * Route for sending SMS
 * 
 * Expected request body:
 * {
 *   to: "phone_number",
 *   message: "message_content",
 *   messageType: "reminder|missed|registration|rescheduled"
 * }
 */
app.post('/send-sms', async (req, res) => {
  const { to, message, messageType } = req.body;
  
  // Validate request
  if (!to || !message) {
    return res.status(400).json({
      status: 'error',
      message: 'Phone number and message are required'
    });
  }
  
  // Format phone number (ensure it starts with +)
  const formattedNumber = formatPhoneNumber(to);
  
  if (!formattedNumber) {
    return res.status(400).json({
      status: 'error',
      message: 'Invalid phone number format'
    });
  }
  
  // Log the SMS attempt
  console.log(`Sending ${messageType || 'general'} SMS to ${formattedNumber}: ${message}`);
  
  try {
    // Send the message
    const result = await sms.send({
      to: formattedNumber,
      message
    });
    
    console.log('SMS Result:', JSON.stringify(result, null, 2));
    
    // Check if the message was sent successfully
    if (result && result.SMSMessageData && result.SMSMessageData.Recipients && result.SMSMessageData.Recipients.length > 0) {
      const recipient = result.SMSMessageData.Recipients[0];
      
      if (recipient.status === 'Success') {
        return res.status(200).json({
          status: 'success',
          messageId: recipient.messageId,
          cost: recipient.cost,
          number: formattedNumber
        });
      } else {
        return res.status(400).json({
          status: 'error',
          message: `Failed to send SMS: ${recipient.status}`,
          statusCode: recipient.statusCode,
          number: formattedNumber
        });
      }
    }
    
    // If we get here, the result format was unexpected
    return res.status(500).json({
      status: 'error',
      message: 'Unexpected response format from Africa\'s Talking API',
      result
    });
    
  } catch (error) {
    console.error('SMS Error:', error);
    
    return res.status(500).json({
      status: 'error',
      message: `Error sending SMS: ${error.message}`,
      details: error
    });
  }
});

/**
 * Format phone number to ensure it has the correct country code
 * Returns null if the format is invalid
 */
function formatPhoneNumber(phoneNumber) {
  // Remove any non-numeric characters except plus sign
  phoneNumber = phoneNumber.replace(/[^0-9+]/g, '');
  
  // If empty after cleaning, return null
  if (!phoneNumber) return null;
  
  // Already in international format
  if (phoneNumber.startsWith('+254')) {
    // Validate length for +254 numbers (should be 13 characters)
    return phoneNumber.length === 13 ? phoneNumber : null;
  }
  
  // Starts with 254
  if (phoneNumber.startsWith('254')) {
    // Validate length for 254 numbers (should be 12 characters)
    return phoneNumber.length === 12 ? `+${phoneNumber}` : null;
  }
  
  // Starts with 0
  if (phoneNumber.startsWith('0')) {
    // Validate length for 0 numbers (should be 10 characters)
    return phoneNumber.length === 10 ? `+254${phoneNumber.substring(1)}` : null;
  }
  
  // Starts with 7
  if (phoneNumber.startsWith('7')) {
    // Validate length for numbers starting with 7 (should be 9 characters)
    return phoneNumber.length === 9 ? `+254${phoneNumber}` : null;
  }
  
  // Invalid format
  return null;
}

// Start the server
app.listen(PORT, () => {
  console.log(`SMS Service running on port ${PORT}`);
  console.log(`Africa's Talking API credentials loaded for: ${africastalkingConfig.username}`);
}); 