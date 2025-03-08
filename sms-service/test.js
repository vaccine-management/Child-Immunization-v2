require('dotenv').config();
const africastalking = require('africastalking');

// Africa's Talking configuration
const credentials = {
  apiKey: process.env.AT_API_KEY,
  username: process.env.AT_USERNAME
};

// Initialize the SDK
const africastalkingSDK = africastalking(credentials);

// Get the SMS service
const sms = africastalkingSDK.SMS;

// Phone number to test with
const testNumber = '+254728063583'; 

// Test message
const message = `This is a test SMS from the Node.js Africa's Talking SMS test. Time: ${new Date().toLocaleTimeString()}`;

console.log('Africa\'s Talking Test Script');
console.log('=============================');
console.log(`Username: ${credentials.username}`);
console.log(`API Key: ${credentials.apiKey.substring(0, 10)}...`);
console.log(`Sending to: ${testNumber}`);
console.log(`Message: ${message}`);
console.log('=============================\n');

// Function to send SMS
async function sendSMS() {
  try {
    console.log('Sending SMS...');
    
    const result = await sms.send({
      to: testNumber,
      message
    });
    
    console.log('SMS Result:');
    console.log(JSON.stringify(result, null, 2));
    
    return result;
  } catch (error) {
    console.error('Error sending SMS:');
    console.error(error);
    return null;
  }
}

// Run the test
sendSMS()
  .then(result => {
    if (result) {
      console.log('\nSMS test completed.');
      
      if (result.SMSMessageData && result.SMSMessageData.Recipients && result.SMSMessageData.Recipients.length > 0) {
        const recipient = result.SMSMessageData.Recipients[0];
        
        if (recipient.status === 'Success') {
          console.log('✅ SMS sent successfully!');
          console.log(`Message ID: ${recipient.messageId}`);
          console.log(`Cost: ${recipient.cost}`);
        } else {
          console.log('❌ SMS failed to send.');
          console.log(`Status: ${recipient.status}`);
          console.log(`Status Code: ${recipient.statusCode}`);
        }
      } else {
        console.log('❌ Unexpected response format.');
      }
    }
    process.exit(0);
  })
  .catch(error => {
    console.error('Fatal error during test:');
    console.error(error);
    process.exit(1);
  }); 