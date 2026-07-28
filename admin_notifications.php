<?php
/**
 * ============================================================
 * ANUGRAH ACCOUNTING - COMPLETE NOTIFICATION SYSTEM
 * ============================================================
 * File 1: notification_system.php
 * Core notification handling class
 * ============================================================
 */

class NotificationSystem {
    private $conn;
    
    public function __construct($conn) {
        $this->conn = $conn;
    }
    
    /**
     * Notify admin about any activity
     */
    public function notifyAdmin($title, $message, $type = 'info', $actionUrl = null, $userId = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO admin_notifications (title, message, type, action_url, user_id, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("ssssi", $title, $message, $type, $actionUrl, $userId);
        $result = $stmt->execute();
        $stmt->close();
        
        // Also log to notifications_log for tracking
        $this->logNotification('admin', 'Admin System', $title, $message, 'sent');
        
        return $result;
    }
    
    /**
     * Notify specific user
     */
    public function notifyUser($userId, $title, $message, $type = 'info', $actionUrl = null) {
        $stmt = $this->conn->prepare(
            "INSERT INTO user_notifications (user_id, title, message, type, action_url, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("issss", $userId, $title, $message, $type, $actionUrl);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    /**
     * Send email notification
     */
    public function sendEmail($recipient, $subject, $message) {
        $headers = "From: Anugrah Accounting <noreply@anugrah.com>\r\n";
        $headers .= "Reply-To: anugrah0369@gmail.com\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        
        $emailBody = $this->getEmailTemplate($subject, $message);
        
        $sent = mail($recipient, $subject, $emailBody, $headers);
        
        $this->logNotification('email', $recipient, $subject, $message, $sent ? 'sent' : 'failed');
        
        return $sent;
    }
    
    /**
     * Send SMS (placeholder for SMS gateway integration)
     */
    public function sendSMS($phoneNumber, $message) {
        // TODO: Integrate with SMS gateway (MSG91, Twilio, etc.)
        $status = 'pending';
        $this->logNotification('sms', $phoneNumber, null, $message, $status);
        return true;
    }
    
    /**
     * Send WhatsApp (placeholder for WhatsApp API)
     */
    public function sendWhatsApp($phoneNumber, $message) {
        // TODO: Integrate with WhatsApp Business API
        $status = 'pending';
        $this->logNotification('whatsapp', $phoneNumber, null, $message, $status);
        return true;
    }
    
    /**
     * Log notification to database
     */
    private function logNotification($type, $recipient, $subject, $message, $status) {
        $stmt = $this->conn->prepare(
            "INSERT INTO notifications_log (type, recipient, subject, message, status, created_at) 
             VALUES (?, ?, ?, ?, ?, NOW())"
        );
        $stmt->bind_param("sssss", $type, $recipient, $subject, $message, $status);
        $stmt->execute();
        $stmt->close();
    }
    
    /**
     * Email template
     */
    private function getEmailTemplate($subject, $message) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 0; }
                .container { max-width: 600px; margin: 0 auto; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px 20px; text-align: center; }
                .header h2 { margin: 0; font-size: 24px; }
                .content { background: #ffffff; padding: 30px; border: 1px solid #e0e0e0; }
                .content h3 { color: #667eea; margin-top: 0; }
                .footer { background: #333; color: white; padding: 20px; text-align: center; font-size: 12px; }
                .footer p { margin: 5px 0; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🧮 Anugrah Accounting Services</h2>
                    <p style='margin: 10px 0 0 0; opacity: 0.9;'>A HOPE FOR EVERY FAMILY TO BE SECURED AND WEALTHY</p>
                </div>
                <div class='content'>
                    <h3>{$subject}</h3>
                    <p>{$message}</p>
                </div>
                <div class='footer'>
                    <p><strong>Anugrah Accounting Services</strong></p>
                    <p>📧 Email: anugrah0369@gmail.com | 📞 Phone: 8000687342</p>
                    <p>📍 Address: Your Office Address Here</p>
                    <p style='margin-top: 15px;'>&copy; " . date('Y') . " Anugrah Accounting. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
    }
    
    /**
     * Get unread admin notification count
     */
    public function getUnreadAdminCount() {
        $result = $this->conn->query("SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0");
        $row = $result->fetch_assoc();
        return $row['count'];
    }
    
    /**
     * Get unread user notification count
     */
    public function getUnreadUserCount($userId) {
        $stmt = $this->conn->prepare("SELECT COUNT(*) as count FROM user_notifications WHERE user_id = ? AND is_read = 0");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        $stmt->close();
        return $row['count'];
    }
    
    /**
     * Mark notification as read
     */
    public function markAsRead($notificationId, $isAdmin = true) {
        $table = $isAdmin ? 'admin_notifications' : 'user_notifications';
        $stmt = $this->conn->prepare("UPDATE {$table} SET is_read = 1, read_at = NOW() WHERE id = ?");
        $stmt->bind_param("i", $notificationId);
        $result = $stmt->execute();
        $stmt->close();
        return $result;
    }
    
    /**
     * Get admin notifications
     */
    public function getAdminNotifications($limit = 10) {
        $result = $this->conn->query(
            "SELECT * FROM admin_notifications 
             ORDER BY created_at DESC 
             LIMIT {$limit}"
        );
        return $result->fetch_all(MYSQLI_ASSOC);
    }
    
    /**
     * Get user notifications
     */
    public function getUserNotifications($userId, $limit = 10) {
        $stmt = $this->conn->prepare(
            "SELECT * FROM user_notifications 
             WHERE user_id = ? 
             ORDER BY created_at DESC 
             LIMIT ?"
        );
        $stmt->bind_param("ii", $userId, $limit);
        $stmt->execute();
        $result = $stmt->get_result();
        $notifications = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();
        return $notifications;
    }
    
    /**
     * =====================================================
     * AUTOMATED NOTIFICATION HANDLERS
     * =====================================================
     */
    
    /**
     * Handle form submission - Universal handler for all forms
     */
    public function handleFormSubmission($formType, $userData, $formId) {
        $userName = $userData['name'];
        $userEmail = $userData['email'];
        $userPhone = isset($userData['phone']) ? $userData['phone'] : '';
        $userId = isset($userData['user_id']) ? $userData['user_id'] : null;
        
        $formNames = array(
            'gst_registration' => 'GST Registration',
            'gst_return' => 'GST Return',
            'income_tax' => 'Income Tax Return',
            'msme' => 'MSME Registration',
            'fssai' => 'FSSAI Licence',
            'accounting' => 'Accounting Service',
            'cma' => 'CMA Data',
            'tax_planning' => 'Tax Planning',
            'contact_message' => 'Contact Message'
        );
        
        $formName = isset($formNames[$formType]) ? $formNames[$formType] : ucfirst($formType);
        
        // Notify admin
        $this->notifyAdmin(
            "New {$formName} Submission",
            "{$userName} ({$userEmail}) has submitted a new {$formName} application. Please review and take necessary action.",
            'form_submission',
            "admin_" . str_replace('_', '', $formType) . ".php?id={$formId}",
            $userId
        );
        
        // Send email to admin
        $this->sendEmail(
            'anugrah0369@gmail.com',
            "New {$formName} - {$userName}",
            "Dear Admin,<br><br>
             A new {$formName} application has been submitted.<br><br>
             <strong>Customer Details:</strong><br>
             Name: {$userName}<br>
             Email: {$userEmail}<br>
             Phone: {$userPhone}<br>
             Application ID: #{$formId}<br><br>
             Please log in to the admin panel to review this application.<br><br>
             Best regards,<br>
             Anugrah Accounting System"
        );
        
        // Notify user
        if ($userId) {
            $this->notifyUser(
                $userId,
                "{$formName} Submitted Successfully",
                "Your {$formName} application has been submitted successfully. Our team will review it shortly and get back to you.",
                'success'
            );
        }
        
        // Send confirmation email to user
        $this->sendEmail(
            $userEmail,
            "Application Received - {$formName}",
            "Dear {$userName},<br><br>
             Thank you for submitting your {$formName} application with Anugrah Accounting Services.<br><br>
             <strong>Application Details:</strong><br>
             Application ID: #{$formId}<br>
             Service Type: {$formName}<br>
             Submitted on: " . date('F d, Y H:i A') . "<br><br>
             Our team will review your application and contact you shortly.<br><br>
             If you have any questions, feel free to contact us at:<br>
             📞 8000687342<br>
             📧 anugrah0369@gmail.com<br><br>
             Best regards,<br>
             Anugrah Accounting Team"
        );
        
        // Send SMS notification
        if ($userPhone) {
            $this->sendSMS(
                $userPhone,
                "Dear {$userName}, your {$formName} application #{$formId} has been received. We'll contact you shortly. - Anugrah Accounting"
            );
        }
    }
    
    /**
     * Handle user login notification
     */
    public function handleUserLogin($userId, $userName, $userEmail) {
        // Notify admin
        $this->notifyAdmin(
            "User Login",
            "{$userName} ({$userEmail}) has logged into the system at " . date('H:i A'),
            'user_activity',
            null,
            $userId
        );
        
        // Welcome notification to user
        $this->notifyUser(
            $userId,
            "Welcome Back!",
            "You've successfully logged in to your Anugrah Accounting account.",
            'success'
        );
    }
    
    /**
     * Handle user registration
     */
    public function handleUserRegistration($userId, $userName, $userEmail, $userPhone) {
        // Notify admin
        $this->notifyAdmin(
            "New User Registration",
            "{$userName} ({$userEmail}) has registered on the platform.",
            'user_activity',
            "admin_users.php?id={$userId}",
            $userId
        );
        
        // Send welcome email
        $this->sendEmail(
            $userEmail,
            "Welcome to Anugrah Accounting Services!",
            "Dear {$userName},<br><br>
             Welcome to Anugrah Accounting Services! We're excited to have you on board.<br><br>
             Your account has been created successfully. You can now access all our services:<br>
             ✅ GST Registration & Returns<br>
             ✅ Income Tax Return Filing<br>
             ✅ MSME Registration<br>
             ✅ FSSAI Licence<br>
             ✅ Accounting Services<br>
             ✅ Tax Planning<br><br>
             If you have any questions, our team is here to help!<br><br>
             Contact Us:<br>
             📞 8000687342<br>
             📧 anugrah0369@gmail.com<br><br>
             Best regards,<br>
             Anugrah Accounting Team"
        );
        
        // Send welcome SMS
        $this->sendSMS(
            $userPhone,
            "Welcome to Anugrah Accounting! Your account has been created. Visit our website to explore our services. - Anugrah Accounting"
        );
    }
    
    /**
     * Handle status change
     */
    public function handleStatusChange($formType, $formId, $oldStatus, $newStatus, $userData) {
        $userName = $userData['name'];
        $userEmail = $userData['email'];
        $userId = isset($userData['user_id']) ? $userData['user_id'] : null;
        
        $formNames = array(
            'gst_registration' => 'GST Registration',
            'gst_return' => 'GST Return',
            'income_tax' => 'Income Tax Return',
            'msme' => 'MSME Registration',
            'fssai' => 'FSSAI Licence',
            'accounting' => 'Accounting Service',
            'cma' => 'CMA Data',
            'tax_planning' => 'Tax Planning'
        );
        
        $formName = isset($formNames[$formType]) ? $formNames[$formType] : ucfirst($formType);
        
        // Notify user
        if ($userId) {
            $this->notifyUser(
                $userId,
                "{$formName} Status Updated",
                "Your {$formName} application (ID: #{$formId}) status has been updated from '{$oldStatus}' to '{$newStatus}'.",
                'status_update'
            );
        }
        
        // Send email
        $statusMessage = "";
        if ($newStatus == 'Approved' || $newStatus == 'Completed') {
            $statusMessage = "Great news! Your application has been {$newStatus}.";
        } elseif ($newStatus == 'In Progress') {
            $statusMessage = "Your application is currently being processed.";
        } elseif ($newStatus == 'Rejected') {
            $statusMessage = "Unfortunately, your application has been rejected. Please contact us for more details.";
        }
        
        $this->sendEmail(
            $userEmail,
            "{$formName} - Status Update",
            "Dear {$userName},<br><br>
             {$statusMessage}<br><br>
             <strong>Application Details:</strong><br>
             Application ID: #{$formId}<br>
             Service: {$formName}<br>
             Previous Status: {$oldStatus}<br>
             Current Status: <strong>{$newStatus}</strong><br><br>
             Please log in to your account for more details.<br><br>
             Best regards,<br>
             Anugrah Accounting Team"
        );
    }
    
    /**
     * Handle payment received
     */
    public function handlePaymentReceived($formType, $formId, $amount, $userData) {
        $userName = $userData['name'];
        $userEmail = $userData['email'];
        $userId = isset($userData['user_id']) ? $userData['user_id'] : null;
        
        // Notify admin
        $this->notifyAdmin(
            "Payment Received",
            "Payment of ₹" . number_format($amount, 2) . " received from {$userName} for {$formType} (ID: #{$formId})",
            'payment',
            null,
            $userId
        );
        
        // Notify user
        if ($userId) {
            $this->notifyUser(
                $userId,
                "Payment Received",
                "We have received your payment of ₹" . number_format($amount, 2) . ". Thank you!",
                'success'
            );
        }
        
        // Send receipt email
        $this->sendEmail(
            $userEmail,
            "Payment Receipt - Anugrah Accounting",
            "Dear {$userName},<br><br>
             Thank you for your payment!<br><br>
             <strong>Payment Details:</strong><br>
             Amount: ₹" . number_format($amount, 2) . "<br>
             Service: {$formType}<br>
             Application ID: #{$formId}<br>
             Date: " . date('F d, Y H:i A') . "<br><br>
             This is an auto-generated receipt for your records.<br><br>
             Best regards,<br>
             Anugrah Accounting Team"
        );
    }
    
    /**
     * Handle document upload
     */
    public function handleDocumentUpload($formType, $formId, $documentType, $userData) {
        $userName = $userData['name'];
        $userId = isset($userData['user_id']) ? $userData['user_id'] : null;
        
        // Notify admin
        $this->notifyAdmin(
            "Document Uploaded",
            "{$userName} has uploaded {$documentType} for {$formType} (ID: #{$formId})",
            'document',
            null,
            $userId
        );
    }
    
    /**
     * Send bulk notification to all users
     */
    public function sendBulkNotification($title, $message, $type = 'info') {
        $users = $this->conn->query("SELECT id, name, email, phone FROM users");
        
        $successCount = 0;
        while ($user = $users->fetch_assoc()) {
            // Send in-app notification
            if ($this->notifyUser($user['id'], $title, $message, $type)) {
                $successCount++;
            }
            
            // Send email
            $personalizedMessage = str_replace('{name}', $user['name'], $message);
            $this->sendEmail($user['email'], $title, $personalizedMessage);
            
            // Send SMS
            if ($user['phone']) {
                $this->sendSMS($user['phone'], strip_tags($personalizedMessage));
            }
        }
        
        return $successCount;
    }
}

/**
 * ============================================================
 * QUICK HELPER FUNCTIONS
 * Add these to your existing files for easy integration
 * ============================================================
 */

// Initialize notification system globally
function getNotificationSystem() {
    global $conn;
    return new NotificationSystem($conn);
}

// Quick notification functions
function notifyAdminQuick($title, $message, $type = 'info') {
    $notif = getNotificationSystem();
    return $notif->notifyAdmin($title, $message, $type);
}

function notifyUserQuick($userId, $title, $message, $type = 'info') {
    $notif = getNotificationSystem();
    return $notif->notifyUser($userId, $title, $message, $type);
}
?>

<!-- 
============================================================
INTEGRATION INSTRUCTIONS
============================================================

1. SAVE THIS FILE AS: notification_system.php

2. CREATE DATABASE TABLES: Run the SQL below in your database

3. INTEGRATE WITH EXISTING FORMS:
   Add this code after successful form submission:
   
   require_once 'notification_system.php';
   $notificationSystem = new NotificationSystem($conn);
   
   $userData = array(
       'name' => $userName,
       'email' => $userEmail,
       'phone' => $userPhone,
       'user_id' => $userId
   );
   
   $notificationSystem->handleFormSubmission('form_type', $userData, $insertedId);

4. FORM TYPES:
   - 'gst_registration'
   - 'income_tax'
   - 'msme'
   - 'fssai'
   - 'accounting'
   - 'cma'
   - 'tax_planning'
   - 'contact_message'

============================================================
SQL FOR DATABASE TABLES
============================================================
-->