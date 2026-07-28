<?php
/**
 * Automated Reminders Cron Job
 * This file should be run daily via cron job
 * Example: 0 9 * * * /usr/bin/php /path/to/cron_send_reminders.php
 * (Runs every day at 9:00 AM)
 */

require_once 'db_config.php';
require_once 'notification_config.php';

echo "Starting automated reminders process...\n";
echo "Time: " . date('Y-m-d H:i:s') . "\n\n";

// 1. Check FSSAI Licence Expiry
echo "Checking FSSAI licence expiries...\n";
$fssaiQuery = "SELECT f.*, u.id as user_id, u.name, u.email, u.phone 
               FROM fssai_licences f 
               JOIN users u ON f.user_id = u.id 
               WHERE f.licence_expiry_date IS NOT NULL 
               AND f.status = 'Completed'
               AND DATEDIFF(f.licence_expiry_date, CURDATE()) BETWEEN 1 AND 30";

$fssaiResults = $conn->query($fssaiQuery);
while ($row = $fssaiResults->fetch_assoc()) {
    $daysLeft = floor((strtotime($row['licence_expiry_date']) - time()) / (60 * 60 * 24));
    
    // Send reminder at 30, 15, 7, 3, and 1 days before expiry
    if (in_array($daysLeft, [30, 15, 7, 3, 1])) {
        echo "  Sending expiry reminder to {$row['name']} - {$daysLeft} days left\n";
        sendExpiryReminder($row['user_id'], 'FSSAI Licence', $row['licence_expiry_date'], $daysLeft);
    }
}
echo "FSSAI reminders completed.\n\n";

// 2. Check GST Return Due Dates (assuming monthly returns due on 20th of next month)
echo "Checking GST return deadlines...\n";
$currentMonth = date('n');
$currentYear = date('Y');
$dueDate = date('Y-m-20', strtotime('+1 month')); // 20th of next month
$daysUntilDue = floor((strtotime($dueDate) - time()) / (60 * 60 * 24));

// Get all users with active GSTIN
$gstQuery = "SELECT DISTINCT u.id as user_id, u.name, u.email, u.phone, gr.gstin
             FROM users u
             LEFT JOIN gst_registrations gr ON u.id = gr.user_id
             WHERE gr.gstin IS NOT NULL AND gr.status = 'Completed'";

$gstResults = $conn->query($gstQuery);
while ($row = $gstResults->fetch_assoc()) {
    $returnPeriod = date('F Y', strtotime('-1 month'));
    
    // Send reminders at 15, 10, 5, 3, and 1 days before due date
    if (in_array($daysUntilDue, [15, 10, 5, 3, 1])) {
        echo "  Sending GST reminder to {$row['name']} - {$daysUntilDue} days until deadline\n";
        sendGSTReturnDeadlineReminder($row['user_id'], $returnPeriod, $dueDate, $daysUntilDue);
    }
}
echo "GST reminders completed.\n\n";

// 3. Check Income Tax Return Deadlines (ITR due July 31st for individuals)
echo "Checking Income Tax return deadlines...\n";
$itrDueDate = date('Y') . '-07-31';
$daysUntilITRDue = floor((strtotime($itrDueDate) - time()) / (60 * 60 * 24));

if ($daysUntilITRDue > 0 && $daysUntilITRDue <= 90) {
    // Get all users who haven't filed ITR for current assessment year
    $assessmentYear = date('Y') . '-' . (date('Y') + 1);
    $financialYear = (date('Y') - 1) . '-' . date('Y');
    
    $itrQuery = "SELECT u.id as user_id, u.name, u.email, u.phone, u.pan
                 FROM users u
                 WHERE u.pan IS NOT NULL
                 AND NOT EXISTS (
                     SELECT 1 FROM income_tax_returns itr 
                     WHERE itr.user_id = u.id 
                     AND itr.assessment_year = '$assessmentYear'
                     AND itr.status IN ('Completed', 'In Progress')
                 )";
    
    $itrResults = $conn->query($itrQuery);
    while ($row = $itrResults->fetch_assoc()) {
        // Send reminders at 90, 60, 30, 15, 7, 3, and 1 days before deadline
        if (in_array($daysUntilITRDue, [90, 60, 30, 15, 7, 3, 1])) {
            echo "  Sending ITR reminder to {$row['name']} - {$daysUntilITRDue} days until deadline\n";
            sendITRDeadlineReminder($row['user_id'], $assessmentYear, $itrDueDate, $daysUntilITRDue);
        }
    }
}
echo "Income Tax reminders completed.\n\n";

// 4. Check for pending applications (reminder to admins)
echo "Checking pending applications...\n";
$pendingQuery = "SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM gst_returns WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM income_tax_returns WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM msme_registrations WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM fssai_licences WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM accounting_services WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM cma_data WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
    UNION ALL
    SELECT id FROM tax_planning WHERE status = 'Pending' AND DATEDIFF(CURDATE(), created_at) >= 3
) as pending";

$pendingResult = $conn->query($pendingQuery)->fetch_assoc();
if ($pendingResult['count'] > 0) {
    echo "  Sending pending applications alert to admin - {$pendingResult['count']} pending\n";
    sendPendingApplicationsAlert($pendingResult['count']);
}
echo "Pending applications check completed.\n\n";

echo "All reminders processed successfully!\n";
echo "End Time: " . date('Y-m-d H:i:s') . "\n";

/**
 * Helper function for ITR deadline reminder
 */
function sendITRDeadlineReminder($userId, $assessmentYear, $dueDate, $daysLeft) {
    global $conn;
    
    $stmt = $conn->prepare("SELECT name, email, phone FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if (!$user) return;
    
    $userName = $user['name'];
    $userEmail = $user['email'];
    $userPhone = $user['phone'];
    
    $subject = "Income Tax Return Filing Reminder - AY $assessmentYear";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #17a2b8; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .reminder-box { background: white; padding: 20px; margin: 20px 0; border-left: 5px solid #17a2b8; }
            .deadline { font-size: 28px; font-weight: bold; color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>Income Tax Return Filing Reminder</h2>
            </div>
            <div class='content'>
                <p>Dear $userName,</p>
                <p>This is a reminder to file your Income Tax Return:</p>
                <div class='reminder-box'>
                    <strong>Assessment Year:</strong> $assessmentYear<br>
                    <strong>Due Date:</strong> " . date('F d, Y', strtotime($dueDate)) . "<br>
                    <strong>Days Remaining:</strong> <span class='deadline'>$daysLeft Days</span>
                </div>
                <p>Don't miss the deadline! File your return today to avoid penalties and interest.</p>
                <p>Need assistance? We're here to help!</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification($userEmail, $subject, $message);
    
    $smsMessage = "ITR Filing Reminder: AY $assessmentYear due on " . date('d-M-Y', strtotime($dueDate)) . " ($daysLeft days left). File now! - Anugrah Accounting";
    sendSMSNotification($userPhone, $smsMessage);
    
    $whatsappMessage = "📝 *ITR Filing Reminder*\n\nDear $userName,\n\n⏰ Time to file your Income Tax Return!\n\n📅 Assessment Year: *$assessmentYear*\n📌 Due Date: " . date('d-M-Y', strtotime($dueDate)) . "\n⚠️ Days Left: *$daysLeft days*\n\nAvoid penalties - File today!\n\nNeed help? Contact us.\n\n- Anugrah Accounting";
    sendWhatsAppNotification($userPhone, $whatsappMessage);
}

/**
 * Send pending applications alert to admin
 */
function sendPendingApplicationsAlert($count) {
    $subject = "Daily Alert: $count Pending Applications Require Attention";
    
    $message = "
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
            .header { background: #dc3545; color: white; padding: 20px; text-align: center; }
            .content { background: #f8f9fa; padding: 20px; }
            .alert-box { background: #fff3cd; border: 2px solid #ffc107; padding: 20px; margin: 20px 0; text-align: center; }
            .count { font-size: 48px; font-weight: bold; color: #dc3545; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>⚠️ Pending Applications Alert</h2>
            </div>
            <div class='content'>
                <p>Dear Admin,</p>
                <div class='alert-box'>
                    <div class='count'>$count</div>
                    <p>Applications pending for 3+ days</p>
                </div>
                <p>These applications require immediate attention to maintain service quality.</p>
                <p><a href='http://yourdomain.com/admin_dashboard.php' style='background: #dc3545; color: white; padding: 10px 20px; text-decoration: none; display: inline-block;'>Review Pending Applications</a></p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    sendEmailNotification(ADMIN_NOTIFICATION_EMAIL, $subject, $message);
}
?>