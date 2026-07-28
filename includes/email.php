<?php
/**
 * includes/email.php
 * Email service using PHPMailer v6 (PHPMailer-master/).
 * PHP 5.4+ compatible.
 */

// ============================================================
// SMTP CONFIGURATION — update with your real credentials
// ============================================================
if (!defined('SMTP_HOST'))     define('SMTP_HOST',     'smtp.gmail.com');
if (!defined('SMTP_PORT'))     define('SMTP_PORT',     587);
if (!defined('SMTP_SECURE'))   define('SMTP_SECURE',   'tls');
if (!defined('SMTP_USERNAME')) define('SMTP_USERNAME', '');
if (!defined('SMTP_PASSWORD')) define('SMTP_PASSWORD', '');
if (!defined('MAIL_FROM'))     define('MAIL_FROM',     '');
if (!defined('MAIL_FROM_NAME'))define('MAIL_FROM_NAME','Anugrah Accounting');
if (!defined('ADMIN_EMAIL'))   define('ADMIN_EMAIL',   '');

// ============================================================
// PHPMailer Loader
// ============================================================
function _loadPHPMailer() {
    $srcPath = __DIR__ . '/../PHPMailer-master/src/';
    require_once $srcPath . 'Exception.php';
    require_once $srcPath . 'PHPMailer.php';
    require_once $srcPath . 'SMTP.php';
}

/**
 * Sends an email using PHPMailer / SMTP.
 *
 * @param string $to       Recipient email address
 * @param string $subject  Email subject
 * @param string $htmlBody HTML body content
 * @param string $textBody Plain-text fallback (optional)
 * @return bool            True on success, false on failure
 */
function sendMail($to, $subject, $htmlBody, $textBody = '') {
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        error_log('[Email] Invalid recipient address: ' . $to);
        return false;
    }

    $smtpUser = SMTP_USERNAME;
    $smtpPass = SMTP_PASSWORD;

    if (empty($smtpUser) || empty($smtpPass)) {
        error_log('[Email] SMTP credentials not configured in includes/email.php');
        return false;
    }

    try {
        _loadPHPMailer();

        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        // SMTP settings
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = $smtpUser;
        $mail->Password   = $smtpPass;
        $mail->SMTPSecure = 'tls';
        $mail->Port       = SMTP_PORT;

        // Sender / recipient
        $customFrom = MAIL_FROM;
        $fromEmail = !empty($customFrom) ? $customFrom : $smtpUser;
        $mail->setFrom($fromEmail, MAIL_FROM_NAME);
        $mail->addAddress($to);

        // Content
        $mail->isHTML(true);
        $mail->CharSet = 'UTF-8';
        $mail->Subject = $subject;
        $mail->Body    = $htmlBody;
        if (!empty($textBody)) {
            $mail->AltBody = $textBody;
        }

        $mail->send();
        error_log('[Email] Sent successfully to: ' . $to);
        return true;

    } catch (PHPMailer\PHPMailer\Exception $e) {
        error_log('[Email] PHPMailer error: ' . $e->getMessage());
        return false;
    } catch (Exception $e) {
        error_log('[Email] General error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Sends an OTP password-reset email.
 *
 * @param string $to       Recipient email
 * @param string $otp      OTP code
 * @param string $userName Recipient's name (optional)
 * @return bool
 */
function sendOTPEmail($to, $otp, $userName = '') {
    $greeting = !empty($userName) ? 'Hello ' . htmlspecialchars($userName, ENT_QUOTES, 'UTF-8') . ',' : 'Hello,';
    $subject  = 'Password Reset OTP — Anugrah Accounting';

    $htmlBody = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"></head>
<body style="margin:0;padding:0;background:#f4f6f9;font-family:'Segoe UI',Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="padding:40px 20px;">
  <tr><td align="center">
    <table width="600" cellpadding="0" cellspacing="0" style="background:#fff;border-radius:12px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">
      <tr>
        <td style="background:linear-gradient(135deg,#FF8C42,#e67e3c);padding:36px 40px;text-align:center;">
          <h1 style="color:#fff;margin:0;font-size:26px;font-weight:700;">🔐 Anugrah Accounting</h1>
          <p style="color:rgba(255,255,255,.85);margin:8px 0 0;font-size:15px;">Password Reset Request</p>
        </td>
      </tr>
      <tr>
        <td style="padding:40px;">
          <p style="color:#444;font-size:15px;margin:0 0 16px;">{$greeting}</p>
          <p style="color:#444;font-size:15px;margin:0 0 24px;">We received a request to reset your password. Use the OTP below to complete the process:</p>
          <div style="background:#fff8f4;border:2px dashed #FF8C42;border-radius:10px;padding:24px;text-align:center;margin:0 0 24px;">
            <p style="color:#888;font-size:13px;margin:0 0 10px;text-transform:uppercase;letter-spacing:1px;">Your One-Time Password</p>
            <div style="font-size:38px;font-weight:800;color:#FF8C42;letter-spacing:10px;font-family:'Courier New',monospace;">{$otp}</div>
            <p style="color:#888;font-size:13px;margin:12px 0 0;">Valid for <strong>10 minutes</strong></p>
          </div>
          <div style="background:#fff3cd;border-left:4px solid #ffc107;padding:16px;border-radius:6px;margin:0 0 24px;">
            <strong style="color:#856404;">⚠️ Security Notice:</strong>
            <ul style="color:#856404;margin:10px 0 0;padding-left:20px;">
              <li>Never share this OTP with anyone</li>
              <li>Anugrah Accounting will never ask for your OTP</li>
              <li>If you didn't request this, you can safely ignore this email</li>
            </ul>
          </div>
          <p style="color:#888;font-size:13px;margin:0;">Best regards,<br><strong style="color:#333;">Anugrah Accounting Team</strong></p>
        </td>
      </tr>
      <tr>
        <td style="background:#1a2332;padding:20px 40px;text-align:center;">
          <p style="color:rgba(255,255,255,.6);font-size:12px;margin:0;">© 2025 Anugrah Accounting. All rights reserved.</p>
          <p style="color:rgba(255,255,255,.4);font-size:11px;margin:6px 0 0;">This is an automated email. Please do not reply.</p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;

    $textBody = "Your Anugrah Accounting password reset OTP is: {$otp}\n\nValid for 10 minutes.\n\nIf you didn't request this, please ignore this email.";

    return sendMail($to, $subject, $htmlBody, $textBody);
}

/**
 * Masks an email address for display (e.g. ka***@gmail.com).
 */
function maskEmail($email) {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;

    $name   = $parts[0];
    $domain = $parts[1];
    $len    = strlen($name);

    if ($len <= 2) {
        $masked = $name[0] . str_repeat('*', $len - 1);
    } else {
        $visible = max(2, (int) floor($len / 3));
        $masked  = substr($name, 0, $visible) . str_repeat('*', $len - $visible);
    }

    return $masked . '@' . $domain;
}

/**
 * Checks whether SMTP credentials have been configured.
 *
 * @return bool
 */
function isEmailConfigured() {
    $user = SMTP_USERNAME;
    $pass = SMTP_PASSWORD;
    return !empty($user) && !empty($pass);
}
?>
