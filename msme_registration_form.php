<?php
session_start();
require_once 'db_config.php';

// Check if email_config.php exists, if not use inline functions
if (file_exists('email_config.php')) {
    require_once 'email_config.php';
} else {
    // Inline email functions if email_config.php doesn't exist
    define('ADMIN_EMAIL', 'anugrah0369@gmail.com');
    define('FROM_EMAIL', 'noreply@anugrahaccounting.com');
    define('FROM_NAME', 'Anugrah Accounting Services');
    define('ADMIN_PHONE', '8000687342');
    
    function sendEmail($to, $subject, $message, $reply_to = null) {
        $headers = "From: " . FROM_NAME . " <" . FROM_EMAIL . ">\r\n";
        if ($reply_to) {
            $headers .= "Reply-To: " . $reply_to . "\r\n";
        }
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion();
        return @mail($to, $subject, $message, $headers);
    }
    
    function sendMSMEDetailedInfoRequest($msme_id, $applicant_data) {
        $ref_id = "MSME" . str_pad($msme_id, 6, '0', STR_PAD_LEFT);
        
        // Email to Admin
        $admin_subject = "MSME Registration - Request for Detailed Information ({$ref_id})";
        $admin_body = "Dear Anugrah Accounting Team,\n\n";
        $admin_body .= "A new MSME registration application has been submitted with a request for detailed information.\n\n";
        $admin_body .= "=== APPLICATION DETAILS ===\n";
        $admin_body .= "Reference ID: {$ref_id}\n";
        $admin_body .= "Submission Date: " . date('d-m-Y H:i:s') . "\n\n";
        $admin_body .= "=== APPLICANT DETAILS ===\n";
        $admin_body .= "Name: {$applicant_data['name']}\n";
        $admin_body .= "Business Name: {$applicant_data['business_name']}\n";
        if (!empty($applicant_data['trade_name'])) {
            $admin_body .= "Trade Name: {$applicant_data['trade_name']}\n";
        }
        $admin_body .= "Email: {$applicant_data['email']}\n";
        $admin_body .= "Phone: {$applicant_data['phone']}\n";
        $admin_body .= "PAN: {$applicant_data['pan_number']}\n";
        $admin_body .= "Business Type: {$applicant_data['business_type']}\n";
        $admin_body .= "State: {$applicant_data['state']}, {$applicant_data['pincode']}\n\n";
        $admin_body .= "=== INFORMATION REQUESTED ===\n";
        $admin_body .= "- Financial documentation requirements\n";
        $admin_body .= "- Investment and turnover calculation guidance\n";
        $admin_body .= "- Additional documents needed\n";
        $admin_body .= "- Timeline and process details\n\n";
        $admin_body .= "=== ACTION REQUIRED ===\n";
        $admin_body .= "Please contact the applicant within 24-48 hours.\n\n";
        $admin_body .= "Anugrah Accounting System";
        
        $admin_sent = sendEmail(ADMIN_EMAIL, $admin_subject, $admin_body, $applicant_data['email']);
        
        // Email to User
        $user_subject = "MSME Registration Submitted - Request Received ({$ref_id})";
        $user_body = "Dear {$applicant_data['name']},\n\n";
        $user_body .= "Thank you for submitting your MSME/Udyam registration application.\n\n";
        $user_body .= "Reference Number: {$ref_id}\n";
        $user_body .= "Business Name: {$applicant_data['business_name']}\n";
        $user_body .= "Submission Date: " . date('d-m-Y h:i A') . "\n\n";
        $user_body .= "We have received your request for detailed information.\n";
        $user_body .= "Our team will contact you within 24-48 hours.\n\n";
        $user_body .= "Contact Us:\n";
        $user_body .= "Email: " . ADMIN_EMAIL . "\n";
        $user_body .= "Phone: " . ADMIN_PHONE . "\n\n";
        $user_body .= "Best regards,\n" . FROM_NAME;
        
        $user_sent = sendEmail($applicant_data['email'], $user_subject, $user_body);
        
        return ($admin_sent && $user_sent);
    }
    
    function sendMSMEConfirmation($msme_id, $applicant_data) {
        $ref_id = "MSME" . str_pad($msme_id, 6, '0', STR_PAD_LEFT);
        $subject = "MSME Registration Submitted Successfully ({$ref_id})";
        $body = "Dear {$applicant_data['name']},\n\n";
        $body .= "Thank you for submitting your MSME registration application.\n\n";
        $body .= "Application Reference: {$ref_id}\n";
        $body .= "Business Name: {$applicant_data['business_name']}\n\n";
        $body .= "Our team will review your application and contact you shortly.\n\n";
        $body .= "Contact: " . ADMIN_EMAIL . " | " . ADMIN_PHONE . "\n\n";
        $body .= "Best regards,\n" . FROM_NAME;
        
        return sendEmail($applicant_data['email'], $subject, $body);
    }
    
    function logEmailActivity($conn, $type, $recipient, $subject, $message, $status = 'sent') {
        $sql = "INSERT INTO notifications_log (type, recipient, subject, message, status) VALUES (?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            $stmt->bind_param("sssss", $type, $recipient, $subject, $message, $status);
            $result = $stmt->execute();
            $stmt->close();
            return $result;
        }
        return false;
    }
}

$success_message = '';
$error_message = '';
$logged_in_user = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_query = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_query);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $logged_in_user = $user_result->fetch_assoc();
    $user_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // User details
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    
    // Business details
    $business_name = trim($_POST['business_name']);
    $trade_name = trim($_POST['trade_name']);
    $business_type = trim($_POST['business_type']);
    $pan_number = strtoupper(trim($_POST['pan_number']));
    $aadhaar_number = trim($_POST['aadhaar_number']);
    $business_address = trim($_POST['business_address']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    
    // Bank details
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc_code = strtoupper(trim($_POST['ifsc_code']));
    
    // Additional info
    $notes = trim($_POST['notes']);
    $detailed_info_requested = isset($_POST['request_detailed_info']) ? 1 : 0;
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
        $errors[] = "Invalid PAN format (e.g., ABCDE1234F)";
    }
    if (!preg_match('/^[0-9]{12}$/', $aadhaar_number)) {
        $errors[] = "Valid 12-digit Aadhaar number required";
    }
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
        $errors[] = "Invalid IFSC code format";
    }
    if (empty($business_type)) {
        $errors[] = "Please select a business type";
    }
    
    // Handle passport photo upload
    $passport_photo_path = null;
    if (isset($_FILES['passport_photo']) && $_FILES['passport_photo']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
        $file_type = $_FILES['passport_photo']['type'];
        $file_size = $_FILES['passport_photo']['size'];
        
        if (!in_array($file_type, $allowed_types)) {
            $errors[] = "Passport photo must be JPG, JPEG, or PNG format";
        } elseif ($file_size > 2097152) { // 2MB
            $errors[] = "Passport photo size must be less than 2MB";
        } else {
            $upload_dir = 'uploads/msme_photos/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['passport_photo']['name'], PATHINFO_EXTENSION);
            $new_filename = 'passport_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['passport_photo']['tmp_name'], $upload_path)) {
                $passport_photo_path = $upload_path;
            } else {
                $errors[] = "Failed to upload passport photo";
            }
        }
    }
    
    // Check for duplicate PAN
    $pan_check = "SELECT id FROM msme_registrations WHERE pan_number = ? AND status != 'Rejected'";
    $pan_stmt = $conn->prepare($pan_check);
    $pan_stmt->bind_param("s", $pan_number);
    $pan_stmt->execute();
    $pan_result = $pan_stmt->get_result();
    
    if ($pan_result->num_rows > 0) {
        $errors[] = "An MSME registration with this PAN number already exists";
    }
    $pan_stmt->close();
    
    // Check for duplicate Aadhaar
    $aadhaar_check = "SELECT id FROM msme_registrations WHERE aadhaar_number = ? AND status != 'Rejected'";
    $aadhaar_stmt = $conn->prepare($aadhaar_check);
    $aadhaar_stmt->bind_param("s", $aadhaar_number);
    $aadhaar_stmt->execute();
    $aadhaar_result = $aadhaar_stmt->get_result();
    
    if ($aadhaar_result->num_rows > 0) {
        $errors[] = "An MSME registration with this Aadhaar number already exists";
    }
    $aadhaar_stmt->close();
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Insert or update user
            $user_sql = "INSERT INTO users (name, email, phone, is_active) VALUES (?, ?, ?, 1)
                         ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone)";
            $user_stmt = $conn->prepare($user_sql);
            
            if (!$user_stmt) {
                throw new Exception("User prepare failed: " . $conn->error);
            }
            
            $user_stmt->bind_param("sss", $name, $email, $phone);
            
            if (!$user_stmt->execute()) {
                throw new Exception("User insert failed: " . $user_stmt->error);
            }
            
            if ($user_stmt->insert_id > 0) {
                $user_id = $user_stmt->insert_id;
            } else {
                $get_user_sql = "SELECT id FROM users WHERE email = ?";
                $get_user_stmt = $conn->prepare($get_user_sql);
                $get_user_stmt->bind_param("s", $email);
                $get_user_stmt->execute();
                $user_result = $get_user_stmt->get_result();
                $user_row = $user_result->fetch_assoc();
                $user_id = $user_row['id'];
                $get_user_stmt->close();
            }
            $user_stmt->close();
            
            // Prepare additional data as JSON
            $additional_data = json_encode(array(
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'ifsc_code' => $ifsc_code
            ));
            
            $notes_with_data = $notes . "\n\nBank Details: " . $additional_data;
            
            // Insert MSME registration
            $sql = "INSERT INTO msme_registrations (
                        user_id, user_name, user_email, user_phone,
                        business_name, trade_name, business_type, pan_number, aadhaar_number,
                        business_address, state, pincode, passport_photo, notes, 
                        detailed_info_requested, info_request_date, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $info_request_date = $detailed_info_requested ? date('Y-m-d H:i:s') : null;
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("MSME prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("issssssssssssiis", 
                $user_id, $name, $email, $phone,
                $business_name, $trade_name, $business_type, $pan_number, $aadhaar_number,
                $business_address, $state, $pincode, $passport_photo_path, $notes_with_data,
                $detailed_info_requested, $info_request_date
            );
            
            if ($stmt->execute()) {
                $msme_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) 
                           VALUES (?, 'MSME_REGISTRATION', 'msme_registrations', ?, 'MSME Registration submitted')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ii", $user_id, $msme_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                // If user requested detailed info, send email automatically
                if ($detailed_info_requested) {
                    // Prepare applicant data for email
                    $applicant_data = array(
                        'name' => $name,
                        'email' => $email,
                        'phone' => $phone,
                        'business_name' => $business_name,
                        'trade_name' => $trade_name,
                        'pan_number' => $pan_number,
                        'aadhaar_number' => $aadhaar_number,
                        'business_type' => $business_type,
                        'state' => $state,
                        'pincode' => $pincode,
                        'bank_name' => $bank_name,
                        'account_number' => $account_number,
                        'ifsc_code' => $ifsc_code,
                        'notes' => $notes
                    );
                    
                    // Send detailed information request emails
                    $email_sent = sendMSMEDetailedInfoRequest($msme_id, $applicant_data);
                    
                    // Log email activity
                    if ($email_sent) {
                        logEmailActivity($conn, 'email', ADMIN_EMAIL, 'MSME Info Request', 
                                       'Detailed information request for MSME' . str_pad($msme_id, 6, '0', STR_PAD_LEFT), 'sent');
                        logEmailActivity($conn, 'email', $email, 'MSME Confirmation', 
                                       'MSME registration confirmation with info request', 'sent');
                    }
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&ref=" . $msme_id . "&email_sent=" . ($email_sent ? '1' : '0'));
                    exit();
                } else {
                    // Send simple confirmation email
                    $applicant_data = array(
                        'name' => $name,
                        'email' => $email,
                        'business_name' => $business_name
                    );
                    
                    $email_sent = sendMSMEConfirmation($msme_id, $applicant_data);
                    
                    if ($email_sent) {
                        logEmailActivity($conn, 'email', $email, 'MSME Confirmation', 
                                       'MSME registration confirmation', 'sent');
                    }
                    
                    header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&ref=" . $msme_id);
                    exit();
                }
            } else {
                throw new Exception("Error executing MSME insert: " . $stmt->error);
            }
            $stmt->close();
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            mysqli_autocommit($conn, TRUE);
            $error_message = "Error submitting application: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}

if (isset($_GET['success']) && $_GET['success'] == 1) {
    $ref_id = isset($_GET['ref']) ? str_pad($_GET['ref'], 6, '0', STR_PAD_LEFT) : '';
    $success_message = "✅ MSME Registration submitted successfully!<br><strong>Reference ID: MSME" . $ref_id . "</strong>";
    
    if (isset($_GET['email_sent'])) {
        if ($_GET['email_sent'] == '1') {
            $success_message .= "<br><br>📧 <strong>Email notifications sent successfully!</strong><br>";
            $success_message .= "• Detailed information request sent to our team<br>";
            $success_message .= "• Confirmation email sent to your inbox<br>";
            $success_message .= "• Our experts will contact you within 24-48 hours<br><br>";
            $success_message .= "Please check your email (and spam folder) for confirmation.";
        } else {
            $success_message .= "<br><br>⚠️ <strong>Note:</strong> Email notification could not be sent automatically.<br>";
            $success_message .= "Please contact us at: <strong>anugrah0369@gmail.com</strong> or call <strong>8000687342</strong><br>";
            $success_message .= "Quote your reference number: <strong>MSME" . $ref_id . "</strong>";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MSME/Udyam Registration - Anugrah Accounting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 30px 20px;
            color: #1e293b;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
        }
        
        .header-content { position: relative; z-index: 1; }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.025em;
        }
        
        .header p {
            font-size: 1rem;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .form-container { padding: 40px; }
        
        .section-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 20px;
            margin-top: 30px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title:first-child { margin-top: 0; }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 24px;
            background: linear-gradient(135deg, #10b981, #059669);
            border-radius: 2px;
        }
        
        .form-group { margin-bottom: 20px; }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
            font-size: 0.875rem;
        }
        
        label .required { color: #dc2626; margin-left: 2px; }
        
        input, select, textarea {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 0.9375rem;
            font-family: inherit;
            transition: all 0.2s ease;
            background: white;
            color: #1e293b;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        input[type="file"] {
            padding: 10px;
            cursor: pointer;
        }
        
        input::placeholder, textarea::placeholder { color: #94a3b8; }
        
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
            margin-top: 20px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .btn:active { transform: translateY(0); }
        
        .alert {
            padding: 16px 20px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9375rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 12px;
            line-height: 1.6;
        }
        
        .alert::before { font-size: 1.25rem; flex-shrink: 0; }
        
        .alert-success {
            background: #f0fdf4;
            color: #166534;
            border: 2px solid #bbf7d0;
        }
        
        .alert-success::before {
            content: '✓';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: #22c55e;
            color: white;
            border-radius: 50%;
            font-weight: bold;
        }
        
        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 2px solid #fecaca;
        }
        
        .alert-error::before {
            content: '!';
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 24px;
            height: 24px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            font-weight: bold;
        }
        
        .feature-highlight {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 2px solid #fbbf24;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .feature-highlight::before { content: '⭐'; font-size: 1.5rem; }
        
        .feature-highlight-text {
            font-size: 0.9375rem;
            color: #78350f;
            font-weight: 500;
        }
        
        .info-box {
            background: #ecfdf5;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 0.8125rem;
            color: #475569;
            line-height: 1.5;
        }
        
        textarea { resize: vertical; min-height: 100px; line-height: 1.6; }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 45px;
        }
        
        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 16px;
            background: #f8fafc;
            border-radius: 10px;
            margin-top: 20px;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        
        .checkbox-group:hover {
            background: #f1f5f9;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .checkbox-group label {
            margin: 0;
            cursor: pointer;
            flex: 1;
        }
        
        .photo-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.2s ease;
        }
        
        .photo-upload-box:hover {
            border-color: #10b981;
            background: #ecfdf5;
        }
        
        .photo-upload-icon {
            font-size: 3rem;
            margin-bottom: 10px;
        }
        
        @media (max-width: 768px) {
            .form-container { padding: 32px 24px; }
            .header { padding: 32px 24px; }
            .header h1 { font-size: 1.75rem; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>🏭 MSME/Udyam Registration</h1>
                <p>Register your enterprise and unlock government benefits & subsidies</p>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="feature-highlight">
                <div class="feature-highlight-text">
                    Benefits: Easy bank loans, Government tenders access, Tax exemptions, Patent subsidies, and more!
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="msmeForm">
                <!-- Personal Information -->
                <div class="section-title">Personal Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name (Owner/Proprietor) <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required placeholder="As per PAN Card" 
                               value="<?php echo $logged_in_user ? htmlspecialchars($logged_in_user['name']) : ''; ?>"
                               <?php echo $logged_in_user ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required placeholder="your.email@example.com"
                               value="<?php echo $logged_in_user ? htmlspecialchars($logged_in_user['email']) : ''; ?>"
                               <?php echo $logged_in_user ? 'readonly' : ''; ?>>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" required placeholder="10-digit mobile number" 
                               pattern="[0-9]{10}" maxlength="10"
                               value="<?php echo $logged_in_user ? htmlspecialchars($logged_in_user['phone']) : ''; ?>"
                               <?php echo $logged_in_user ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="pan_number">PAN Number <span class="required">*</span></label>
                        <input type="text" name="pan_number" id="pan_number" required placeholder="ABCDE1234F" 
                               maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="aadhaar_number">Aadhaar Number <span class="required">*</span></label>
                        <input type="text" name="aadhaar_number" id="aadhaar_number" required 
                               placeholder="12-digit Aadhaar number" pattern="[0-9]{12}" maxlength="12">
                    </div>
                    
                    <div class="form-group">
                        <label for="passport_photo">Passport Size Photo <span class="required">*</span></label>
                        <div class="photo-upload-box">
                            <div class="photo-upload-icon">📷</div>
                            <input type="file" name="passport_photo" id="passport_photo" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                            <div class="info-box" style="margin-top: 10px; border: none; background: transparent;">
                                JPG, JPEG or PNG (Max 2MB)
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Business Details -->
                <div class="section-title">Business Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="business_name">Business/Enterprise Name <span class="required">*</span></label>
                        <input type="text" name="business_name" id="business_name" required 
                               placeholder="Enter your business name">
                    </div>
                    
                    <div class="form-group">
                        <label for="trade_name">Trade Name (if different)</label>
                        <input type="text" name="trade_name" id="trade_name" 
                               placeholder="Enter trade name (optional)">
                        <div class="info-box">The name under which business operates</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="business_type">Business Type <span class="required">*</span></label>
                    <select name="business_type" id="business_type" required>
                        <option value="">-- Select Business Type --</option>
                        <option value="Manufacturing">Manufacturing</option>
                        <option value="Service">Service</option>
                        <option value="Trading">Trading</option>
                        <option value="Retail">Retail</option>
                        <option value="Wholesale">Wholesale</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="business_address">Business Address <span class="required">*</span></label>
                    <textarea name="business_address" id="business_address" required 
                              placeholder="Enter complete business address with landmark"></textarea>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="state">State <span class="required">*</span></label>
                        <select name="state" id="state" required>
                            <option value="">-- Select State --</option>
                            <option value="Andhra Pradesh">Andhra Pradesh</option>
                            <option value="Arunachal Pradesh">Arunachal Pradesh</option>
                            <option value="Assam">Assam</option>
                            <option value="Bihar">Bihar</option>
                            <option value="Chhattisgarh">Chhattisgarh</option>
                            <option value="Goa">Goa</option>
                            <option value="Gujarat">Gujarat</option>
                            <option value="Haryana">Haryana</option>
                            <option value="Himachal Pradesh">Himachal Pradesh</option>
                            <option value="Jharkhand">Jharkhand</option>
                            <option value="Karnataka">Karnataka</option>
                            <option value="Kerala">Kerala</option>
                            <option value="Madhya Pradesh">Madhya Pradesh</option>
                            <option value="Maharashtra">Maharashtra</option>
                            <option value="Manipur">Manipur</option>
                            <option value="Meghalaya">Meghalaya</option>
                            <option value="Mizoram">Mizoram</option>
                            <option value="Nagaland">Nagaland</option>
                            <option value="Odisha">Odisha</option>
                            <option value="Punjab">Punjab</option>
                            <option value="Rajasthan">Rajasthan</option>
                            <option value="Sikkim">Sikkim</option>
                            <option value="Tamil Nadu">Tamil Nadu</option>
                            <option value="Telangana">Telangana</option>
                            <option value="Tripura">Tripura</option>
                            <option value="Uttar Pradesh">Uttar Pradesh</option>
                            <option value="Uttarakhand">Uttarakhand</option>
                            <option value="West Bengal">West Bengal</option>
                            <option value="Delhi">Delhi</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="pincode">Pincode <span class="required">*</span></label>
                        <input type="text" name="pincode" id="pincode" required placeholder="380001" 
                               pattern="[0-9]{6}" maxlength="6">
                    </div>
                </div>
                
                <!-- Bank Information -->
                <div class="section-title">Bank Account Details</div>
                
                <div class="form-group">
                    <label for="bank_name">Bank Name <span class="required">*</span></label>
                    <input type="text" name="bank_name" id="bank_name" required placeholder="Enter bank name">
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="account_number">Account Number <span class="required">*</span></label>
                        <input type="text" name="account_number" id="account_number" required 
                               placeholder="Enter account number">
                    </div>
                    
                    <div class="form-group">
                        <label for="ifsc_code">IFSC Code <span class="required">*</span></label>
                        <input type="text" name="ifsc_code" id="ifsc_code" required placeholder="SBIN0001234" 
                               maxlength="11" style="text-transform: uppercase;">
                        <div class="info-box">11 characters: 4 letters + 0 + 6 alphanumeric</div>
                    </div>
                </div>
                
                <!-- Additional Information -->
                <div class="section-title">Additional Information</div>
                
                <div class="form-group">
                    <label for="notes">Additional Information / Special Requirements</label>
                    <textarea name="notes" id="notes" 
                              placeholder="Any additional information you'd like to provide (optional)"></textarea>
                </div>
                
                <!-- Request Detailed Info -->
                <div class="checkbox-group">
                    <input type="checkbox" name="request_detailed_info" id="request_detailed_info">
                    <label for="request_detailed_info">
                        📧 <strong>Need help with financial documentation?</strong> Check this box to request detailed 
                        information via email. We'll guide you through investment amount, turnover calculation, and 
                        required documents.
                    </label>
                </div>
                
                <div class="info-box" style="margin-top: 20px;">
                    <strong>💡 Note:</strong> If you're unsure about financial details like investment amount or annual 
                    turnover, check the box above. Your email client will open automatically with a pre-filled message 
                    to our team, and we'll assist you with all the necessary information and documentation.
                </div>
                
                <button type="submit" class="btn btn-primary">✓ Submit MSME Registration</button>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-format PAN number
        document.getElementById('pan_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Auto-format IFSC code
        document.getElementById('ifsc_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Format phone number (allow only digits)
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Format Aadhaar number (allow only digits)
        document.getElementById('aadhaar_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Format pincode (allow only digits)
        document.getElementById('pincode').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Photo upload preview
        document.getElementById('passport_photo').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const fileSize = file.size / 1024 / 1024; // in MB
                if (fileSize > 2) {
                    alert('File size must be less than 2MB');
                    this.value = '';
                    return;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png'];
                if (!allowedTypes.includes(file.type)) {
                    alert('Only JPG, JPEG, and PNG files are allowed');
                    this.value = '';
                    return;
                }
            }
        });
        
        // Form validation
        document.getElementById('msmeForm').addEventListener('submit', function(e) {
            const pan = document.getElementById('pan_number').value;
            const aadhaar = document.getElementById('aadhaar_number').value;
            const ifsc = document.getElementById('ifsc_code').value;
            const phone = document.getElementById('phone').value;
            const photo = document.getElementById('passport_photo').files[0];
            
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            const aadhaarRegex = /^[0-9]{12}$/;
            const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            const phoneRegex = /^[0-9]{10}$/;
            
            if (!panRegex.test(pan)) {
                e.preventDefault();
                alert('Please enter a valid PAN number (e.g., ABCDE1234F)');
                document.getElementById('pan_number').focus();
                return false;
            }
            
            if (!aadhaarRegex.test(aadhaar)) {
                e.preventDefault();
                alert('Please enter a valid 12-digit Aadhaar number');
                document.getElementById('aadhaar_number').focus();
                return false;
            }
            
            if (!phoneRegex.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number');
                document.getElementById('phone').focus();
                return false;
            }
            
            if (!ifscRegex.test(ifsc)) {
                e.preventDefault();
                alert('Please enter a valid IFSC code (11 characters: 4 letters + 0 + 6 alphanumeric)');
                document.getElementById('ifsc_code').focus();
                return false;
            }
            
            if (!photo) {
                e.preventDefault();
                alert('Please upload your passport size photo');
                document.getElementById('passport_photo').focus();
                return false;
            }
        });
    </script>
</body>
</html>