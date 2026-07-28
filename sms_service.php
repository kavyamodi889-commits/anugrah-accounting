<?php
/**
 * SMS Service - Fast2SMS Implementation (Most Popular in India)
 * Get your API key from: https://www.fast2sms.com/dashboard/dev-api
 */

// SMS Provider Configuration - Fast2SMS
define('SMS_API_KEY', 'lac3RjmqUgWSovdtJZKiX9HAV41NuLD75xwkETGInFMfBChO8eViXjBFsWOTxrP06DLHhoACgfNM5Stz'); // Replace with your actual API key
define('SMS_SENDER_ID', 'TXTIND'); // Fast2SMS default sender ID
define('SMS_ROUTE', 'v3'); // v3 for OTP route

/**
 * Send SMS using Fast2SMS
 * 
 * @param string $phone Phone number (10 digits)
 * @param string $message SMS message content
 * @return bool Success status
 */
function sendSMS($phone, $message) {
    // Validate phone number
    $phone = formatPhoneNumber($phone);
    if (!isValidPhone($phone)) {
        error_log("Invalid phone number: " . $phone);
        return false;
    }
    
    // Check if API key is configured
    if (SMS_API_KEY === 'lac3RjmqUgWSovdtJZKiX9HAV41NuLD75xwkETGInFMfBChO8eViXjBFsWOTxrP06DLHhoACgfNM5Stz') {
        error_log("SMS API Key not configured. Please update SMS_API_KEY in sms_service.php");
        // In development, log to file
        return logSMSForTesting($phone, $message);
    }
    
    try {
        // Use Fast2SMS
        return sendSMS_Fast2SMS($phone, $message);
        
    } catch (Exception $e) {
        error_log("SMS Send Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send SMS using Fast2SMS (Indian SMS Provider)
 */
function sendSMS_Fast2SMS($phone, $message) {
    $apiKey = SMS_API_KEY;
    
    $url = 'https://www.fast2sms.com/dev/bulkV2';
    
    // Prepare data
    $fields = array(
        'sender_id' => SMS_SENDER_ID,
        'message' => urlencode($message),
        'route' => SMS_ROUTE,
        'numbers' => $phone,
    );
    
    // Initialize cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'authorization: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded'
    ));
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        error_log("SMS cURL Error: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    // Log the response for debugging
    error_log("Fast2SMS Response: " . $response . " | HTTP Code: " . $httpCode);
    
    // Parse response
    $result = json_decode($response, true);
    
    // Check if SMS was sent successfully
    if ($httpCode === 200 && isset($result['return']) && $result['return'] === true) {
        error_log("SMS sent successfully to: " . $phone);
        return true;
    }
    
    error_log("SMS sending failed. Response: " . $response);
    return false;
}

/**
 * Send SMS using MSG91 (Alternative Indian SMS Provider)
 */
function sendSMS_MSG91($phone, $message) {
    $authKey = "YOUR_MSG91_AUTH_KEY";
    $senderId = "ANUACC";
    $route = "4"; // 4 for transactional
    $templateId = "YOUR_TEMPLATE_ID"; // Required for DLT
    
    $url = "https://control.msg91.com/api/v5/flow/";
    
    $postData = array(
        'template_id' => $templateId,
        'short_url' => '0',
        'recipients' => array(
            array(
                'mobiles' => '91' . $phone,
                'var' => $message
            )
        )
    );
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'authkey: ' . $authKey,
        'Content-Type: application/json'
    ));
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("MSG91 Response: " . $response . " | HTTP Code: " . $httpCode);
    
    return $httpCode === 200;
}

/**
 * Send SMS using Twilio (International Provider)
 */
function sendSMS_Twilio($phone, $message) {
    $accountSid = 'YOUR_TWILIO_ACCOUNT_SID';
    $authToken = 'YOUR_TWILIO_AUTH_TOKEN';
    $twilioNumber = 'YOUR_TWILIO_PHONE_NUMBER';
    
    $url = "https://api.twilio.com/2010-04-01/Accounts/$accountSid/Messages.json";
    
    $data = array(
        'From' => $twilioNumber,
        'To' => '+91' . $phone,
        'Body' => $message
    );
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, "$accountSid:$authToken");
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    error_log("Twilio Response: " . $response . " | HTTP Code: " . $httpCode);
    
    return $httpCode >= 200 && $httpCode < 300;
}

/**
 * Log SMS for testing (Development Mode)
 */
function logSMSForTesting($phone, $message) {
    $logDir = __DIR__ . '/logs';
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0777, true);
    }
    
    $logFile = $logDir . '/sms_log.txt';
    $timestamp = date('Y-m-d H:i:s');
    
    $logMessage = "\n=================================\n";
    $logMessage .= "Timestamp: " . $timestamp . "\n";
    $logMessage .= "Phone: " . $phone . "\n";
    $logMessage .= "Message: " . $message . "\n";
    $logMessage .= "=================================\n";
    
    // Append to log file
    file_put_contents($logFile, $logMessage, FILE_APPEND);
    
    // Also log to PHP error log
    error_log("SMS [Testing Mode] - Phone: $phone, Message: $message");
    
    return true;
}

/**
 * Send OTP SMS with specific template
 */
function sendOTPSMS($phone, $otp) {
    $message = "Your Anugrah Accounting OTP is: $otp. Valid for 10 minutes. Do not share with anyone.";
    return sendSMS($phone, $message);
}

/**
 * Send password reset confirmation SMS
 */
function sendPasswordResetConfirmationSMS($phone, $userName) {
    $message = "Dear $userName, your password has been reset successfully. If you didn't request this, please contact us immediately. - ANUGRAH";
    return sendSMS($phone, $message);
}

/**
 * Validate phone number format
 */
function isValidPhone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Check if it's a 10-digit number
    return strlen($phone) === 10 && ctype_digit($phone);
}

/**
 * Format phone number (remove spaces, dashes, +91, etc.)
 */
function formatPhoneNumber($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Remove leading 91 if present (country code)
    if (strlen($phone) === 12 && substr($phone, 0, 2) === '91') {
        $phone = substr($phone, 2);
    }
    
    return $phone;
}

/**
 * Test SMS service configuration
 */
function testSMSService() {
    if (SMS_API_KEY === 'lac3RjmqUgWSovdtJZKiX9HAV41NuLD75xwkETGInFMfBChO8eViXjBFsWOTxrP06DLHhoACgfNM5Stz') {
        return array(
            'status' => 'error',
            'message' => 'SMS API Key not configured. Please update SMS_API_KEY in sms_service.php'
        );
    }
    
    return array(
        'status' => 'success',
        'message' => 'SMS service configured correctly',
        'provider' => 'Fast2SMS'
    );
}
?>