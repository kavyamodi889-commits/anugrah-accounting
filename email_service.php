<?php
/**
 * Email Service for OTP Delivery
 * Standalone SMTP implementation - works without PHPMailer
 */

/**
 * Send email using direct SMTP connection
 * @param string $to Recipient email address
 * @param string $subject Email subject
 * @param string $otp_code OTP code to send
 * @return bool Success status
 */
function sendEmail($to, $subject, $otp_code) {
    // Validate email address
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log("Invalid email address: " . $to);
        return false;
    }
    
    // SMTP Configuration - UPDATE THESE WITH YOUR GMAIL CREDENTIALS
    $smtp_host = 'smtp.gmail.com';
    $smtp_port = 587;
    
    // 🔴 IMPORTANT: Replace these with your actual Gmail credentials
    // Get App Password from: https://myaccount.google.com/apppasswords
    $smtp_username = 'REPLACE_WITH_YOUR_GMAIL@gmail.com';  // Example: john.doe@gmail.com
    $smtp_password = 'REPLACE_WITH_APP_PASSWORD';            // Example: abcdefghijklmnop (16 characters, no spaces)
    
    $from_email = 'noreply@anugrahaccounting.com';
    $from_name = 'Anugrah Accounting';
    
    // Check if credentials are configured
    if (strpos($smtp_username, 'REPLACE_') !== false || strpos($smtp_password, 'REPLACE_') !== false) {
        error_log("⚠️ SMTP credentials not configured in email_service.php - Please update lines 27-28");
        return false;
    }
    
    // HTML email template
    $html_message = "<!DOCTYPE html>
<html>
<head>
    <meta charset='UTF-8'>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #FF8C42, #e67e3c); padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
        .header h1 { color: white; margin: 0; font-size: 24px; }
        .content { background: #f9f9f9; padding: 30px; border: 1px solid #ddd; }
        .otp-box { background: white; border: 2px dashed #FF8C42; padding: 20px; text-align: center; margin: 20px 0; border-radius: 8px; }
        .otp-code { font-size: 32px; font-weight: bold; color: #FF8C42; letter-spacing: 8px; font-family: 'Courier New', monospace; }
        .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
        .footer { background: #333; color: white; padding: 20px; text-align: center; border-radius: 0 0 10px 10px; font-size: 12px; }
    </style>
</head>
<body>
    <div class='container'>
        <div class='header'>
            <h1>🔐 Anugrah Accounting</h1>
            <p style='color: white; margin: 10px 0 0 0;'>Password Reset Request</p>
        </div>
        <div class='content'>
            <p>Hello,</p>
            <p>We received a request to reset your password. Use the OTP below to complete the password reset process:</p>
            
            <div class='otp-box'>
                <p style='margin: 0 0 10px 0; color: #666;'>Your One-Time Password</p>
                <div class='otp-code'>" . $otp_code . "</div>
                <p style='margin: 10px 0 0 0; color: #666; font-size: 14px;'>Valid for 10 minutes</p>
            </div>
            
            <div class='warning'>
                <strong>⚠️ Security Notice:</strong>
                <ul style='margin: 10px 0 0 0; padding-left: 20px;'>
                    <li>Never share this OTP with anyone</li>
                    <li>Anugrah Accounting will never ask for your OTP</li>
                    <li>If you didn't request this, please ignore this email</li>
                </ul>
            </div>
            
            <p style='margin-top: 20px;'>If you didn't request a password reset, you can safely ignore this email. Your password will remain unchanged.</p>
            
            <p style='margin-top: 20px;'>Best regards,<br><strong>Anugrah Accounting Team</strong></p>
        </div>
        <div class='footer'>
            <p style='margin: 0;'>© 2025 Anugrah Accounting. All rights reserved.</p>
            <p style='margin: 10px 0 0 0;'>This is an automated email. Please do not reply.</p>
        </div>
    </div>
</body>
</html>";

    // Plain text version
    $text_message = "Your Anugrah Accounting password reset OTP is: " . $otp_code . "\n\nValid for 10 minutes.\n\nIf you didn't request this, please ignore this email.";
    
    // Boundary for multipart email
    $boundary = md5(uniqid(time()));
    
    // Email headers
    $headers = "From: " . $from_name . " <" . $smtp_username . ">\r\n";
    $headers .= "Reply-To: " . $smtp_username . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: multipart/alternative; boundary=\"" . $boundary . "\"\r\n";
    
    // Email body
    $body = "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $text_message . "\r\n\r\n";
    $body .= "--" . $boundary . "\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: 7bit\r\n\r\n";
    $body .= $html_message . "\r\n\r\n";
    $body .= "--" . $boundary . "--";
    
    try {
        // Connect to SMTP server
        $smtp = fsockopen($smtp_host, $smtp_port, $errno, $errstr, 30);
        
        if (!$smtp) {
            error_log("SMTP connection failed: $errstr ($errno)");
            return false;
        }
        
        // Read server response
        $response = fgets($smtp, 515);
        if (substr($response, 0, 3) != '220') {
            error_log("SMTP Error: " . $response);
            fclose($smtp);
            return false;
        }
        
        // Say EHLO
        fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $response = fgets($smtp, 515);
        
        // Start TLS
        fputs($smtp, "STARTTLS\r\n");
        $response = fgets($smtp, 515);
        
        // Enable crypto
        stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
        
        // Say EHLO again after TLS
        fputs($smtp, "EHLO " . $_SERVER['HTTP_HOST'] . "\r\n");
        $response = fgets($smtp, 515);
        
        // Authenticate
        fputs($smtp, "AUTH LOGIN\r\n");
        $response = fgets($smtp, 515);
        
        fputs($smtp, base64_encode($smtp_username) . "\r\n");
        $response = fgets($smtp, 515);
        
        fputs($smtp, base64_encode($smtp_password) . "\r\n");
        $response = fgets($smtp, 515);
        
        if (substr($response, 0, 3) != '235') {
            error_log("SMTP Authentication failed: " . $response);
            fclose($smtp);
            return false;
        }
        
        // Send MAIL FROM
        fputs($smtp, "MAIL FROM: <" . $smtp_username . ">\r\n");
        $response = fgets($smtp, 515);
        
        // Send RCPT TO
        fputs($smtp, "RCPT TO: <" . $to . ">\r\n");
        $response = fgets($smtp, 515);
        
        // Send DATA
        fputs($smtp, "DATA\r\n");
        $response = fgets($smtp, 515);
        
        // Send email content
        fputs($smtp, "To: " . $to . "\r\n");
        fputs($smtp, "Subject: " . $subject . "\r\n");
        fputs($smtp, $headers . "\r\n");
        fputs($smtp, $body . "\r\n.\r\n");
        
        $response = fgets($smtp, 515);
        
        // Quit
        fputs($smtp, "QUIT\r\n");
        fclose($smtp);
        
        error_log("Email sent successfully to: " . $to);
        return true;
        
    } catch (Exception $e) {
        error_log("SMTP Error: " . $e->getMessage());
        return false;
    }
}

/**
 * Send OTP via email
 * @param string $email Recipient email
 * @param string $otp OTP code
 * @param string $userName User's name (optional)
 * @return bool Success status
 */
function sendOTPEmail($email, $otp, $userName = '') {
    $subject = "Password Reset OTP - Anugrah Accounting";
    return sendEmail($email, $subject, $otp);
}

/**
 * Test email service configuration
 * @return array Status and message
 */
function testEmailService() {
    // Check if SMTP credentials are configured
    $config_file = file_get_contents(__FILE__);
    
    if (strpos($config_file, "REPLACE_WITH_YOUR_GMAIL") !== false || 
        strpos($config_file, "REPLACE_WITH_APP_PASSWORD") !== false) {
        return [
            'status' => 'error',
            'message' => 'SMTP credentials not configured. Please update email_service.php with your Gmail credentials.'
        ];
    }
    
    return [
        'status' => 'success',
        'message' => 'Email service is configured and ready',
        'method' => 'Direct SMTP'
    ];
}

/**
 * Mask email address for display
 * @param string $email Email address
 * @return string Masked email
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) {
        return $email;
    }
    
    $name = $parts[0];
    $domain = $parts[1];
    
    $nameLength = strlen($name);
    if ($nameLength <= 2) {
        $maskedName = $name[0] . '*';
    } else {
        $visibleChars = min(2, floor($nameLength / 3));
        $maskedName = substr($name, 0, $visibleChars) . str_repeat('*', $nameLength - $visibleChars);
    }
    
    return $maskedName . '@' . $domain;
}
?>