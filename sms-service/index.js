require('dotenv').config();
const AfricasTalking = require('africastalking');
const express = require('express');
const winston = require('winston');
const app = express();
const fs = require('fs');
const path = require('path');

// Ensure logs directory exists
const logDir = path.join(__dirname, 'logs');
if (!fs.existsSync(logDir)) {
    fs.mkdirSync(logDir, { recursive: true });
}

// Logger setup
const logger = winston.createLogger({
    level: 'info',
    format: winston.format.combine(
        winston.format.timestamp(),
        winston.format.json()
    ),
    transports: [
        new winston.transports.File({ filename: path.join(logDir, 'error.log'), level: 'error' }),
        new winston.transports.File({ filename: path.join(logDir, 'combined.log') }),
        new winston.transports.File({ filename: path.join(logDir, 'debug.log') })
    ]
});
if (process.env.NODE_ENV !== 'production') {
    logger.add(new winston.transports.Console({ format: winston.format.simple() }));
}

// Direct debug logging helper
function debugLog(message, data = {}) {
    const logEntry = JSON.stringify({
        timestamp: new Date().toISOString(),
        message,
        ...data
    }) + '\n';
    fs.appendFileSync(path.join(logDir, 'debug.log'), logEntry);
}

// Log all requests middleware
app.use((req, res, next) => {
    const startTime = Date.now();
    
    // Log request details
    debugLog('Incoming request', {
        method: req.method,
        url: req.url,
        headers: req.headers,
        body: req.body
    });
    
    // Capture response
    const originalSend = res.send;
    res.send = function(body) {
        debugLog('Outgoing response', {
            statusCode: res.statusCode,
            responseTime: Date.now() - startTime,
            body
        });
        return originalSend.call(this, body);
    };
    
    next();
});

// Africa's Talking setup
const africastalking = new AfricasTalking({
    apiKey: process.env.AFRICASTALKING_API_KEY || 'test-api-key',
    username: process.env.AFRICASTALKING_USERNAME || 'sandbox',
});

// Middleware for parsing JSON body
app.use(express.json());

// Allow CORS
app.use((req, res, next) => {
    res.header('Access-Control-Allow-Origin', '*');
    res.header('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, Accept');
    next();
});

// Health check endpoint to verify the service is running
app.get('/health', (req, res) => {
    res.status(200).json({ status: 'success', message: 'SMS service is running' });
});

// SMS sending function (single recipient)
async function sendSingleSMS(phoneNumber, message) {
    debugLog('Sending single SMS', { phoneNumber, message });
    const sms = africastalking.SMS;
    try {
        // For development/testing, simulate success if API keys aren't set
        if (!process.env.AFRICASTALKING_API_KEY || 
            process.env.AFRICASTALKING_API_KEY === 'test-api-key') {
            debugLog('Using mock SMS response (API keys not configured)', { phoneNumber });
            return { 
                success: true, 
                result: { 
                    messageId: 'mock-' + Date.now(),
                    status: 'success'
                } 
            };
        }
        
        const result = await sms.send({
            to: phoneNumber,
            message: message,
        });
        logger.info('SMS sent', { phoneNumber, message, result });
        debugLog('SMS sent successfully', { phoneNumber, result });
        return { success: true, result };
    } catch (error) {
        logger.error('SMS failed', { phoneNumber, message, error: error.message, details: error.response?.data });
        debugLog('SMS failed', { phoneNumber, error: error.message });
        return { success: false, error: error.message };
    }
}

// Bulk SMS sending function
async function sendBulkSMS(recipients) {
    debugLog('Starting bulk SMS', { recipientCount: recipients?.length || 0 });
    
    if (!recipients || !Array.isArray(recipients) || recipients.length === 0) {
        debugLog('Invalid recipients format', { recipients });
        return [];
    }
    
    const results = [];
    for (const recipient of recipients) {
        const { phoneNumber, message } = recipient;
        if (!phoneNumber || !message) {
            debugLog('Skipping invalid recipient', { recipient });
            results.push({ 
                phoneNumber: phoneNumber || 'unknown', 
                success: false, 
                error: 'Missing required fields' 
            });
            continue;
        }
        
        const result = await sendSingleSMS(phoneNumber, message);
        results.push({ phoneNumber, ...result });
    }
    
    debugLog('Bulk SMS completed', { 
        total: recipients.length,
        success: results.filter(r => r.success).length,
        failure: results.filter(r => !r.success).length
    });
    
    return results;
}

// API endpoint for bulk sending
app.post('/send-bulk-sms', async (req, res) => {
    debugLog('Received /send-bulk-sms request', {
        contentType: req.headers['content-type'],
        bodySize: req.body ? JSON.stringify(req.body).length : 0,
    });
    
    // Validate request body
    if (!req.body) {
        debugLog('Missing request body');
        return res.status(400).json({ success: false, error: 'Request body is required' });
    }
    
    const { recipients } = req.body;
    debugLog('Parsed recipients', { 
        isArray: Array.isArray(recipients),
        length: recipients?.length || 0,
        sample: recipients && recipients.length > 0 ? recipients[0] : null
    });
    
    if (!Array.isArray(recipients) || recipients.length === 0) {
        logger.warn('Invalid recipients', { recipients });
        return res.status(400).json({ success: false, error: 'recipients must be a non-empty array' });
    }
    
    try {
        const results = await sendBulkSMS(recipients);
        const response = { success: true, results };
        debugLog('Sending success response', { response });
        return res.status(200).json(response);
    } catch (error) {
        const errorResponse = { success: false, error: error.message };
        logger.error('Error in /send-bulk-sms', { error: error.message });
        debugLog('Sending error response', { errorResponse });
        return res.status(500).json(errorResponse);
    }
});

// Start server
const PORT = process.env.PORT || 3000;
app.listen(PORT, () => {
    logger.info(`SMS service running on port ${PORT}`);
    debugLog(`SMS service started on port ${PORT}`);
});