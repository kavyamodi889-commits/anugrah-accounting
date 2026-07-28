<?php
/**
 * Notification Configuration and Helper Functions
 * Handles Email, SMS, and WhatsApp notifications
 */

// Email Configuration
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com'); // Change this
define('SMTP_PASSWORD', 'your-app-password'); // Change this
define('FROM_EMAIL', 'noreply@anugrahaccounting.com');
define('FROM_NAME', 'Anugrah Accounting');

// SMS Configuration (Using MSG91 or similar service)
define('SMS_API_KEY', 'your-sms-api-key'); // Change this
define('SMS_SENDER_ID', 'ANUACC');
define('SMS_API_URL', 'https://api.msg91.com/api/v5/flow/');

// WhatsApp Configuration (Using WhatsApp Business API or services like Twilio/MSG91)
define('WHATSAPP_API_KEY', 'your-whatsapp-api-key'); // Change this
define('WHATSAPP_API_URL', 'https://api.msg91.com/api/v5/whatsapp/whatsapp-outbound-message/');

// Admin notification emails
define('ADMIN_NOTIFICATION_EMAIL', 'admin@anugrahaccounting.com');

/**
 * Send Email Notification
 */
function sendEmailNotification($to, $subject, $message, $isHTML = true) {
    require_once 'PHPMailer/PHPMailer.php';
    require_once 'PHPMailer/SMTP.php';
    require_once 'PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = SMTP_HOST;
        $mail->SMTPAuth = true;
        $mail->Username = SMTP_USERNAME;
        $mail->Password = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = SMTP_PORT;
        
        // Recipients
        $mail->setFrom(FROM_EMAIL, FROM_NAME);
        $mail->addAddress($to);
        
        // Content
        $mail->isHTML($isHTML);
        $mail->Subject = $subject;
        $mail->Body = $message;
        
        $mail->send();
        
        // Log notification
        logNotification('email', $to, $subject, 'sent');
        return true;
    } catch (Exception $e) {
        logNotification('email', $to, $subject, 'failed', $mail->ErrorInfo);
        return false;
    }
}

/**
 * Send SMS Notification
 */
function sendSMSNotification($phone, $message) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ensure phone number starts with country code
    if (strlen($phone) == 10) {
        $phone = '91' . $phone; // Add India country code
    }
    
    $curl = curl_init();
    
    $postData = array(
        'sender' => SMS_SENDER_ID,
        'route' => '4',
        'country' => '91',
        'sms' => array(
            array(
                'message' => $message,
                'to' => array($phone)
            )
        )
    );
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => SMS_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => array(
            'authkey: ' . SMS_API_KEY,
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        logNotification('sms', $phone, $message, 'failed', $error);
        return false;
    } else {
        logNotification('sms', $phone, $message, 'sent');
        return true;
    }
}

/**
 * Send WhatsApp Notification
 */
function sendWhatsAppNotification($phone, $message) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    
    // Ensure phone number starts with country code
    if (strlen($phone) == 10) {
        $phone = '91' . $phone; // Add India country code
    }
    
    $curl = curl_init();
    
    $postData = array(
        'integrated_number' => 'YOUR_WHATSAPP_NUMBER',
        'content_type' => 'text',
        'payload' => array(
            'to' => $phone,
            'type' => 'text',
            'text' => $message
        )
    );
    
    curl_setopt_array($curl, array(
        CURLOPT_URL => WHATSAPP_API_URL,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($postData),
        CURLOPT_HTTPHEADER => array(
            'authkey: ' . WHATSAPP_API_KEY,
            'Content-Type: application/json'
        )
    ));
    
    $response = curl_exec($curl);
    $error = curl_error($curl);
    curl_close($curl);
    
    if ($error) {
        logNotification('whatsapp', $phone, $message, 'failed', $error);
        return false;
    } else {
        logNotification('whatsapp', $phone, $message, 'sent');
        return true;
    }
}

/**
 * Log notification in database
 */
function logNotification($type, $recipient, $message, $status, $error = null) {
    global $conn;
    
    $stmt = $conn->prepare("INSERT INTO notifications_log (type, recipient, message, status, error_message, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssss", $type, $recipient, $message, $status, $error);
    $stmt->execute();
    $stmt->close();
}

/**
 * Send New Application Alert to Admin
 */
function sendNewApplicationAlert($serviceName, $userName, $userEmail, $applicationId) {
    $subject = "New Application Received - $serviceName";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .info-box { background: white; padding: 15px; margin: 10px 0; border-left: 4px solid #667eea; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>New Application Alert</h2>
            </div>
            <div class='content'>
                <p>A new application has been received:</p>
                <div class='info-box'>
                    <strong>Service:</strong> $serviceName<br>
                    <strong>Client Name:</strong> $userName<br>
                    <strong>Email:</strong> $userEmail<br>
                    <strong>Application ID:</strong> #$applicationId
                </div>
                <p>Please review and process this application at your earliest convenience.</p>
                <p><a href='http://yourdomain.com/admin_dashboard.php' style='background: #667eea; color: white; padding: 10px 20px; text-decoration: none; display: inline-block; margin-top: 10px;'>View Dashboard</a></p>
            </div>
            <div class='footer'>
                <p>Anugrah Accounting Admin System</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification(ADMIN_NOTIFICATION_EMAIL, $subject, $message);
}

/**
 * Send Status Update to Client
 */
function sendStatusUpdateNotification($userId, $serviceName, $oldStatus, $newStatus, $applicationId) {
    global $conn;
    
    // Get user details
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) return;
    
    $userName = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['phone'];
    
    // Email notification
    $subject = "Status Update: $serviceName Application";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .status-box { background: white; padding: 20px; margin: 20px 0; text-align: center; border-radius: 10px; }
            .status { font-size: 24px; font-weight: bold; color: #667eea; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Application Status Update</h2>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>Your application status has been updated:</p>
                <div class='status-box'>
                    <strong>Service:</strong> $serviceName<br>
                    <strong>Application ID:</strong> #$applicationId<br>
                    <strong>Previous Status:</strong> $oldStatus<br>
                    <strong>Current Status:</strong> <span class='status'>$newStatus</span>
                </div>
                <p>If you have any questions, please feel free to contact us.</p>
            </div>
            <div class='footer'>
                <p>Thank you for choosing Anugrah Accounting</p>
                <p>Contact: admin@anugrahaccounting.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification($userEmail, $subject, $message);
    
    // SMS notification
    $smsMessage = "Dear $userName, Your $serviceName application (#$applicationId) status is now: $newStatus. - Anugrah Accounting";
    sendSMSNotification($userPhone, $smsMessage);
    
    // WhatsApp notification
    $whatsappMessage = "🔔 *Status Update*\n\nDear $userName,\n\nYour *$serviceName* application has been updated:\n\n📋 Application ID: #$applicationId\n✅ New Status: *$newStatus*\n\nThank you for choosing Anugrah Accounting!\n\nFor queries: admin@anugrahaccounting.com";
    sendWhatsAppNotification($userPhone, $whatsappMessage);
}

/**
 * Send Expiry Reminder
 */
function sendExpiryReminder($userId, $documentType, $expiryDate, $daysLeft) {
    global $conn;
    
    // Get user details
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) return;
    
    $userName = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['phone'];
    
    $urgencyClass = $daysLeft <= 7 ? 'urgent' : 'warning';
    
    // Email notification
    $subject = "⚠️ Expiry Reminder: $documentType";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #ff6b6b; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .warning-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; border-radius: 10px; }
            .urgent { background: #f8d7da; border-color: #dc3545; }
            .days { font-size: 36px; font-weight: bold; color: #dc3545; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>⚠️ Expiry Reminder</h2>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>This is a reminder that your document is expiring soon:</p>
                <div class='warning-box $urgencyClass'>
                    <strong>Document:</strong> $documentType<br>
                    <strong>Expiry Date:</strong> " . date('F d, Y', strtotime($expiryDate)) . "<br>
                    <div class='days'>$daysLeft Days Left</div>
                </div>
                <p>Please take necessary action to renew your $documentType before it expires.</p>
                <p>Contact us for renewal assistance.</p>
            </div>
            <div class='footer'>
                <p>Anugrah Accounting</p>
                <p>Email: admin@anugrahaccounting.com</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification($userEmail, $subject, $message);
    
    // SMS notification
    $smsMessage = "⚠️ REMINDER: Your $documentType expires in $daysLeft days on " . date('d-M-Y', strtotime($expiryDate)) . ". Please renew soon. - Anugrah Accounting";
    sendSMSNotification($userPhone, $smsMessage);
    
    // WhatsApp notification
    $whatsappMessage = "⚠️ *Expiry Alert*\n\nDear $userName,\n\n Your *$documentType* is expiring soon!\n\n📅 Expiry Date: " . date('d-M-Y', strtotime($expiryDate)) . "\n⏰ Days Left: *$daysLeft days*\n\nPlease renew to avoid service disruption.\n\n📞 Contact us for assistance.\n\n- Anugrah Accounting";
    sendWhatsAppNotification($userPhone, $whatsappMessage);
}

/**
 * Send Deadline Reminder for GST Returns
 */
function sendGSTReturnDeadlineReminder($userId, $returnPeriod, $dueDate, $daysLeft) {
    global $conn;
    
    // Get user details
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) return;
    
    $userName = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['phone'];
    
    // Email notification
    $subject = "GST Return Filing Reminder - $returnPeriod";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #28a745; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .reminder-box { background: white; padding: 20px; margin: 20px 0; border-left: 5px solid #28a745; }
            .deadline { font-size: 28px; font-weight: bold; color: #dc3545; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>GST Return Filing Reminder</h2>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>This is a friendly reminder about your upcoming GST return filing:</p>
                <div class='reminder-box'>
                    <strong>Return Period:</strong> $returnPeriod<br>
                    <strong>Due Date:</strong> " . date('F d, Y', strtotime($dueDate)) . "<br>
                    <strong>Days Remaining:</strong> <span class='deadline'>$daysLeft Days</span>
                </div>
                <p>Please ensure timely filing to avoid penalties.</p>
                <p>Need assistance? Contact us today!</p>
            </div>
            <div class='footer'>
                <p>Anugrah Accounting</p>
                <p>Email: admin@anugrahaccounting.com | Phone: +91-XXXXXXXXXX</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification($userEmail, $subject, $message);
    
    // SMS notification
    $smsMessage = "GST Return Reminder: Period $returnPeriod due on " . date('d-M-Y', strtotime($dueDate)) . " ($daysLeft days left). File now to avoid penalty. - Anugrah Accounting";
    sendSMSNotification($userPhone, $smsMessage);
    
    // WhatsApp notification
    $whatsappMessage = "📊 *GST Return Filing Reminder*\n\nDear $userName,\n\n⏰ Your GST return is due soon:\n\n📅 Period: *$returnPeriod*\n📌 Due Date: " . date('d-M-Y', strtotime($dueDate)) . "\n⚠️ Days Left: *$daysLeft days*\n\nFile on time to avoid penalties!\n\nNeed help? Contact us.\n\n- Anugrah Accounting";
    sendWhatsAppNotification($userPhone, $whatsappMessage);
}

/**
 * Send Welcome Email to New User
 */
function sendWelcomeEmail($userName, $userEmail) {
    $subject = "Welcome to Anugrah Accounting!";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; }
            .content { background: #f8f9fa; padding: 30px; }
            .services { background: white; padding: 20px; margin: 20px 0; border-radius: 10px; }
            .service-item { padding: 10px 0; border-bottom: 1px solid #eee; }
            .footer { text-align: center; padding: 20px; color: #666; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>Welcome to Anugrah Accounting!</h1>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>Thank you for choosing Anugrah Accounting for your financial and compliance needs.</p>
                <div class='services'>
                    <h3>Our Services:</h3>
                    <div class='service-item'>✅ GST Registration & Returns</div>
                    <div class='service-item'>✅ Income Tax Returns</div>
                    <div class='service-item'>✅ MSME Registration</div>
                    <div class='service-item'>✅ FSSAI Licences</div>
                    <div class='service-item'>✅ Accounting Services</div>
                    <div class='service-item'>✅ Tax Planning</div>
                    <div class='service-item'>✅ CMA Data Preparation</div>
                </div>
                <p>We're here to help you with all your accounting and compliance requirements.</p>
            </div>
            <div class='footer'>
                <p><strong>Contact Us:</strong></p>
                <p>Email: admin@anugrahaccounting.com</p>
                <p>Phone: +91-XXXXXXXXXX</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification($userEmail, $subject, $message);
}
?>