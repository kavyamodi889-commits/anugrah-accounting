<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
// db_config.php unified in includes/db.php

$success_message = '';
$error_message = '';
$user_logged_in = false;
$user_data = null;

// Check if user is logged in
if (isset($_SESSION['user_id'])) {
    $user_logged_in = true;
    $user_id = $_SESSION['user_id'];
    
    // Fetch user data
    $user_sql = "SELECT * FROM users WHERE id = ?";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $user_result = $user_stmt->get_result();
    $user_data = $user_result->fetch_assoc();
    $user_stmt->close();
}

// Email configuration
define('ANUGRAH_EMAIL', 'anugrah0369@gmail.com');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Determine request type
    $request_type = isset($_POST['request_type']) ? $_POST['request_type'] : 'document_submission';
    
    // User details
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    
    // Business Information
    $business_name = trim($_POST['business_name']);
    $pan_number = strtoupper(trim($_POST['pan_number']));
    $financial_year = trim($_POST['financial_year']);
    $upload_option = isset($_POST['upload_option']) ? $_POST['upload_option'] : 'all_years';
    $notes = trim($_POST['notes']);
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (empty($business_name)) $errors[] = "Business name is required";
    if (empty($financial_year)) $errors[] = "Financial year is required";
    
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) {
        $errors[] = "Invalid PAN format. Must be in format: ABCDE1234F";
    }
    
    // Check for duplicate PAN number for the same financial year
    $check_pan_sql = "SELECT id FROM cma_data WHERE pan_number = ? AND financial_year = ?";
    $check_stmt = $conn->prepare($check_pan_sql);
    $check_stmt->bind_param("ss", $pan_number, $financial_year);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        $errors[] = "CMA data already exists for PAN number $pan_number for financial year $financial_year";
    }
    $check_stmt->close();
    
    // Handle file uploads
    $upload_dir = 'uploads/cma_documents/';
    $itr_dir = $upload_dir . 'itr/';
    $loan_dir = $upload_dir . 'loan_statements/';
    
    // Create directories if they don't exist
    if (!file_exists($itr_dir)) {
        mkdir($itr_dir, 0755, true);
    }
    if (!file_exists($loan_dir)) {
        mkdir($loan_dir, 0755, true);
    }
    
    $uploaded_files = array();
    
    // Process ITR files based on upload option
    if ($upload_option === 'selected_year') {
        // Only one file for selected year
        if (isset($_FILES['itr_selected_year']) && $_FILES['itr_selected_year']['error'] === UPLOAD_ERR_OK) {
            $file_tmp = $_FILES['itr_selected_year']['tmp_name'];
            $file_name = $_FILES['itr_selected_year']['name'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            $allowed_ext = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
            
            if (in_array($file_ext, $allowed_ext)) {
                $new_filename = $pan_number . '_ITR_' . $financial_year . '_' . time() . '.' . $file_ext;
                $destination = $itr_dir . $new_filename;
                
                if (move_uploaded_file($file_tmp, $destination)) {
                    $uploaded_files['itr_year1'] = $new_filename;
                } else {
                    $errors[] = "Failed to upload ITR file";
                }
            } else {
                $errors[] = "Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
            }
        }
    } else {
        // All three years
        for ($i = 1; $i <= 3; $i++) {
            $file_key = 'itr_year' . $i;
            if (isset($_FILES[$file_key]) && $_FILES[$file_key]['error'] === UPLOAD_ERR_OK) {
                $file_tmp = $_FILES[$file_key]['tmp_name'];
                $file_name = $_FILES[$file_key]['name'];
                $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
                
                $allowed_ext = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
                
                if (in_array($file_ext, $allowed_ext)) {
                    $new_filename = $pan_number . '_ITR_Year' . $i . '_' . time() . '.' . $file_ext;
                    $destination = $itr_dir . $new_filename;
                    
                    if (move_uploaded_file($file_tmp, $destination)) {
                        $uploaded_files[$file_key] = $new_filename;
                    } else {
                        $errors[] = "Failed to upload ITR Year $i file";
                    }
                } else {
                    $errors[] = "ITR Year $i: Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
                }
            }
        }
    }
    
    // Process Loan Statement file
    if (isset($_FILES['loan_statement']) && $_FILES['loan_statement']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['loan_statement']['tmp_name'];
        $file_name = $_FILES['loan_statement']['name'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
        
        if (in_array($file_ext, $allowed_ext)) {
            $new_filename = $pan_number . '_LoanStatement_' . time() . '.' . $file_ext;
            $destination = $loan_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                $uploaded_files['loan_statement'] = $new_filename;
            } else {
                $errors[] = "Failed to upload Loan Statement file";
            }
        } else {
            $errors[] = "Loan Statement: Invalid file type. Allowed: PDF, JPG, PNG, DOC, DOCX";
        }
    }
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Get or create user_id
            if ($user_logged_in) {
                $user_id = $_SESSION['user_id'];
            } else {
                $user_sql = "INSERT INTO users (name, email, phone) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("sss", $name, $email, $phone);
                $user_stmt->execute();
                
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
            }
            
            // Prepare file paths
            $itr1_file = isset($uploaded_files['itr_year1']) ? $uploaded_files['itr_year1'] : NULL;
            $itr2_file = isset($uploaded_files['itr_year2']) ? $uploaded_files['itr_year2'] : NULL;
            $itr3_file = isset($uploaded_files['itr_year3']) ? $uploaded_files['itr_year3'] : NULL;
            $loan_file = isset($uploaded_files['loan_statement']) ? $uploaded_files['loan_statement'] : NULL;
            
            if ($request_type === 'request_details') {
                $detail_request = 1;
                $detail_request_date = date('Y-m-d H:i:s');
            } else {
                $detail_request = 0;
                $detail_request_date = NULL;
            }
            
            $sql = "INSERT INTO cma_data (user_id, user_name, user_email, user_phone, business_name, pan_number, 
                    financial_year, notes, itr_year1_file, itr_year2_file, itr_year3_file, loan_statement_file,
                    request_type, detail_request_sent, detail_request_date, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("issssssssssssis", 
                              $user_id, $name, $email, $phone, $business_name, $pan_number,
                              $financial_year, $notes, $itr1_file, $itr2_file, $itr3_file, $loan_file,
                              $request_type, $detail_request, $detail_request_date);
            
            if ($stmt->execute()) {
                $cma_id = $stmt->insert_id;
                
                $log_sql = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) 
                           VALUES (?, 'CMA_SUBMISSION', 'cma_data', ?, 'CMA Data submitted')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ii", $user_id, $cma_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                // Send email
                if ($request_type === 'request_details') {
                    $subject = "CMA Data Request - Need More Details - Ref: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT);
                    $message = "Dear Anugrah Accounting Team,\n\n";
                    $message .= "A new CMA data request has been submitted with a request for more details.\n\n";
                    $message .= "Reference ID: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT) . "\n\n";
                    $message .= "Client Details:\n";
                    $message .= "Name: $name\n";
                    $message .= "Email: $email\n";
                    $message .= "Phone: $phone\n";
                    $message .= "Business Name: $business_name\n";
                    $message .= "PAN Number: $pan_number\n";
                    $message .= "Financial Year: $financial_year\n\n";
                    $message .= "Additional Notes:\n$notes\n\n";
                    $message .= "The client has requested more details about the CMA preparation process.\n";
                    $message .= "Please contact them at the earliest.\n\n";
                    $message .= "Best regards,\nAnugrah Accounting System";
                    
                    $headers = "From: $email\r\n";
                    $headers .= "Reply-To: $email\r\n";
                    
                    mail(ANUGRAH_EMAIL, $subject, $message, $headers);
                    
                    $success_message = "Your request for more details has been sent successfully! Reference ID: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT) . ". Our team will contact you shortly.";
                } else {
                    $subject = "New CMA Data Submission - Ref: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT);
                    $message = "Dear Anugrah Accounting Team,\n\n";
                    $message .= "A new CMA data submission has been received.\n\n";
                    $message .= "Reference ID: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT) . "\n\n";
                    $message .= "Client Details:\n";
                    $message .= "Name: $name\n";
                    $message .= "Email: $email\n";
                    $message .= "Phone: $phone\n";
                    $message .= "Business Name: $business_name\n";
                    $message .= "PAN Number: $pan_number\n";
                    $message .= "Financial Year: $financial_year\n\n";
                    $message .= "Upload Option: " . ($upload_option === 'selected_year' ? 'Selected Year Only' : 'All 3 Years') . "\n\n";
                    $message .= "Documents Submitted:\n";
                    $message .= "- ITR Year 1: " . ($itr1_file ? "Yes" : "No") . "\n";
                    $message .= "- ITR Year 2: " . ($itr2_file ? "Yes" : "No") . "\n";
                    $message .= "- ITR Year 3: " . ($itr3_file ? "Yes" : "No") . "\n";
                    $message .= "- Loan Statement: " . ($loan_file ? "Yes" : "No") . "\n\n";
                    $message .= "Additional Notes:\n$notes\n\n";
                    $message .= "Please review and process this submission.\n\n";
                    $message .= "Best regards,\nAnugrah Accounting System";
                    
                    $headers = "From: $email\r\n";
                    $headers .= "Reply-To: $email\r\n";
                    
                    mail(ANUGRAH_EMAIL, $subject, $message, $headers);
                    
                    $success_message = "CMA Data submitted successfully! Reference ID: CMA" . str_pad($cma_id, 6, '0', STR_PAD_LEFT);
                }
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&ref=" . $cma_id . "&type=" . $request_type);
                exit();
            } else {
                throw new Exception("Error executing CMA insert: " . $stmt->error);
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
    $type = isset($_GET['type']) ? $_GET['type'] : 'document_submission';
    
    if ($type === 'request_details') {
        $success_message = "Your request for more details has been sent successfully! Reference ID: CMA" . $ref_id . ". Our team will contact you shortly.";
    } else {
        $success_message = "CMA Data submitted successfully! Reference ID: CMA" . $ref_id;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMA Data Preparation - Anugrah Accounting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #0f766e 0%, #14b8a6 100%);
            min-height: 100vh;
            padding: 30px 15px;
            color: #1e293b;
        }
        
        .container {
            max-width: 850px;
            margin: 0 auto;
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 35px 30px;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 350px;
            height: 350px;
            background: rgba(255, 255, 255, 0.08);
            border-radius: 50%;
        }
        
        .header-content { position: relative; z-index: 1; }
        
        .header h1 {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.025em;
        }
        
        .header p {
            font-size: 0.95rem;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .form-container { padding: 35px 30px; }
        
        .section-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 18px;
            margin-top: 28px;
            padding-bottom: 10px;
            border-bottom: 3px solid #e2e8f0;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-title:first-child { margin-top: 0; }
        
        .section-icon {
            font-size: 1.5rem;
        }
        
        .form-group { margin-bottom: 18px; }
        
        label {
            display: block;
            margin-bottom: 7px;
            color: #475569;
            font-weight: 600;
            font-size: 0.875rem;
            letter-spacing: 0.01em;
        }
        
        label .required { color: #dc2626; margin-left: 2px; }
        
        input, select, textarea {
            width: 100%;
            padding: 11px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-family: inherit;
            transition: all 0.2s ease;
            background: white;
            color: #1e293b;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        input::placeholder, textarea::placeholder { color: #94a3b8; }
        input:disabled, select:disabled { 
            background: #f8fafc; 
            cursor: not-allowed; 
            color: #64748b;
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }
        
        .btn-group { 
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px; 
            margin-top: 30px;
        }
        
        .btn {
            padding: 13px 24px;
            border: none;
            border-radius: 8px;
            font-size: 0.9375rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
            text-align: center;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #14b8a6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #0f766e 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(59, 130, 246, 0.35);
        }
        
        .btn-secondary { 
            background: linear-gradient(135deg, #f59e0b 0%, #f97316 100%); 
            color: white; 
        }
        
        .btn-secondary:hover { 
            background: linear-gradient(135deg, #d97706 0%, #ea580c 100%);
            transform: translateY(-1px);
            box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35);
        }
        
        .btn:active { transform: translateY(0); }
        .btn:disabled { opacity: 0.5; cursor: not-allowed; }
        
        .alert {
            padding: 14px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9375rem;
            font-weight: 500;
            display: flex;
            align-items: flex-start;
            gap: 10px;
            line-height: 1.6;
        }
        
        .alert::before { font-size: 1.15rem; flex-shrink: 0; margin-top: 2px; }
        
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
            width: 22px;
            height: 22px;
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
            width: 22px;
            height: 22px;
            background: #ef4444;
            color: white;
            border-radius: 50%;
            font-weight: bold;
        }
        
        .info-box {
            background: linear-gradient(135deg, #eff6ff 0%, #dbeafe 100%);
            padding: 16px 18px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 4px solid #3b82f6;
        }
        
        .info-box h4 {
            color: #1e40af;
            margin-bottom: 10px;
            font-size: 1rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .info-box ul {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .info-box li {
            padding: 4px 0;
            padding-left: 24px;
            position: relative;
            color: #475569;
            font-size: 0.875rem;
        }
        
        .info-box li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #3b82f6;
            font-weight: bold;
            font-size: 1rem;
        }
        
        .upload-option-group {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .upload-option {
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            padding: 15px;
            cursor: pointer;
            transition: all 0.2s ease;
            background: white;
        }
        
        .upload-option:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .upload-option.active {
            border-color: #3b82f6;
            background: #eff6ff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .upload-option input[type="radio"] {
            width: auto;
            margin-right: 8px;
        }
        
        .upload-option-label {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #0f172a;
            font-size: 0.9375rem;
            margin-bottom: 5px;
        }
        
        .upload-option-desc {
            font-size: 0.8125rem;
            color: #64748b;
            margin-left: 28px;
        }
        
        .file-upload-container {
            display: none;
        }
        
        .file-upload-container.active {
            display: block;
        }
        
        .file-upload-box {
            border: 2px dashed #cbd5e1;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8fafc;
            transition: all 0.3s ease;
            cursor: pointer;
            margin-bottom: 15px;
        }
        
        .file-upload-box:hover {
            border-color: #3b82f6;
            background: #eff6ff;
        }
        
        .file-upload-box.has-file {
            border-color: #22c55e;
            background: #f0fdf4;
            border-style: solid;
        }
        
        .file-upload-icon {
            font-size: 2.5rem;
            margin-bottom: 8px;
        }
        
        .file-upload-box.has-file .file-upload-icon {
            color: #22c55e;
        }
        
        .file-upload-text {
            color: #64748b;
            font-size: 0.875rem;
            font-weight: 500;
        }
        
        .file-name {
            color: #0f172a;
            font-weight: 600;
            margin-top: 6px;
            word-break: break-word;
            font-size: 0.875rem;
        }
        
        .file-input-hidden {
            display: none;
        }
        
        textarea { 
            resize: vertical; 
            min-height: 90px; 
            line-height: 1.6; 
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 15px center;
            padding-right: 40px;
        }
        
        @media (max-width: 768px) {
            .form-container { padding: 25px 20px; }
            .header { padding: 28px 20px; }
            .header h1 { font-size: 1.65rem; }
            .row, .upload-option-group, .btn-group { 
                grid-template-columns: 1fr; 
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>📊 CMA Data Preparation</h1>
                <p>Credit Monitoring Arrangement - Submit Your Documents</p>
            </div>
        </div>
        
        <div class="form-container">
            <?php if ($success_message): ?>
                <div class="alert alert-success"><?php echo $success_message; ?></div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error"><?php echo $error_message; ?></div>
            <?php endif; ?>
            
            <div class="info-box">
                <h4><span>📋</span> What is CMA Data?</h4>
                <ul>
                    <li>Required for business loan applications from banks</li>
                    <li>Includes projected Balance Sheet & P&L for 3-5 years</li>
                    <li>Cash Flow, Fund Flow Statements & Financial Ratios</li>
                </ul>
            </div>
            
            <form method="POST" action="" id="cmaForm" enctype="multipart/form-data">
                <div class="section-title">
                    <span class="section-icon">👤</span> Contact Information
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required placeholder="Your full name"
                               value="<?php echo $user_logged_in ? htmlspecialchars($user_data['name']) : ''; ?>"
                               <?php echo $user_logged_in ? 'readonly' : ''; ?>>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required placeholder="your.email@example.com"
                               value="<?php echo $user_logged_in ? htmlspecialchars($user_data['email']) : ''; ?>"
                               <?php echo $user_logged_in ? 'readonly' : ''; ?>>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" id="phone" required placeholder="10-digit mobile number" 
                           pattern="[0-9]{10}" maxlength="10"
                           value="<?php echo $user_logged_in ? htmlspecialchars($user_data['phone']) : ''; ?>"
                           <?php echo $user_logged_in ? 'readonly' : ''; ?>>
                </div>
                
                <div class="section-title">
                    <span class="section-icon">🏢</span> Business Information
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="business_name">Business Name <span class="required">*</span></label>
                        <input type="text" name="business_name" id="business_name" required placeholder="Enter business name">
                    </div>
                    
                    <div class="form-group">
                        <label for="pan_number">PAN Number <span class="required">*</span></label>
                        <input type="text" name="pan_number" id="pan_number" required placeholder="ABCDE1234F" 
                               maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="financial_year">Financial Year <span class="required">*</span></label>
                    <select name="financial_year" id="financial_year" required onchange="updateYearLabels()">
                        <option value="">-- Select Financial Year --</option>
                        <option value="2023-24">2023-24</option>
                        <option value="2024-25">2024-25</option>
                        <option value="2025-26">2025-26</option>
                        <option value="2026-27">2026-27</option>
                    </select>
                </div>
                
                <div class="section-title">
                    <span class="section-icon">📁</span> ITR Upload Options
                </div>
                
                <div class="upload-option-group">
                    <div class="upload-option active" onclick="selectUploadOption('selected_year')">
                        <div class="upload-option-label">
                            <input type="radio" name="upload_option" value="selected_year" id="opt_selected" checked>
                            📄 Selected Year Only
                        </div>
                        <div class="upload-option-desc">Upload ITR for selected financial year</div>
                    </div>
                    
                    <div class="upload-option" onclick="selectUploadOption('all_years')">
                        <div class="upload-option-label">
                            <input type="radio" name="upload_option" value="all_years" id="opt_all">
                            📚 Last 3 Years
                        </div>
                        <div class="upload-option-desc">Upload ITR for last 3 consecutive years</div>
                    </div>
                </div>
                
                <!-- Selected Year Upload -->
                <div id="upload_selected_year" class="file-upload-container active">
                    <div class="form-group">
                        <label for="itr_selected_year">ITR for <span id="selected_year_label">Selected Year</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('itr_selected_year').click()">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">Click to upload ITR document</div>
                            <div class="file-name" id="itr_selected_year_name"></div>
                        </div>
                        <input type="file" name="itr_selected_year" id="itr_selected_year" class="file-input-hidden" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileName(this, 'itr_selected_year_name')">
                    </div>
                </div>
                
                <!-- All 3 Years Upload -->
                <div id="upload_all_years" class="file-upload-container">
                    <div class="form-group">
                        <label for="itr_year1">ITR - <span id="year1_label">Year 1 (Most Recent)</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('itr_year1').click()">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">Click to upload Year 1 ITR</div>
                            <div class="file-name" id="itr_year1_name"></div>
                        </div>
                        <input type="file" name="itr_year1" id="itr_year1" class="file-input-hidden" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileName(this, 'itr_year1_name')">
                    </div>
                    
                    <div class="form-group">
                        <label for="itr_year2">ITR - <span id="year2_label">Year 2</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('itr_year2').click()">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">Click to upload Year 2 ITR</div>
                            <div class="file-name" id="itr_year2_name"></div>
                        </div>
                        <input type="file" name="itr_year2" id="itr_year2" class="file-input-hidden" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileName(this, 'itr_year2_name')">
                    </div>
                    
                    <div class="form-group">
                        <label for="itr_year3">ITR - <span id="year3_label">Year 3 (Oldest)</span></label>
                        <div class="file-upload-box" onclick="document.getElementById('itr_year3').click()">
                            <div class="file-upload-icon">📄</div>
                            <div class="file-upload-text">Click to upload Year 3 ITR</div>
                            <div class="file-name" id="itr_year3_name"></div>
                        </div>
                        <input type="file" name="itr_year3" id="itr_year3" class="file-input-hidden" 
                               accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileName(this, 'itr_year3_name')">
                    </div>
                </div>
                
                <div class="section-title">
                    <span class="section-icon">💳</span> Loan Statement (Optional)
                </div>
                
                <div class="form-group">
                    <label for="loan_statement">Outstanding Loan Statement</label>
                    <div class="file-upload-box" onclick="document.getElementById('loan_statement').click()">
                        <div class="file-upload-icon">📑</div>
                        <div class="file-upload-text">Click to upload Loan Statement (if any)</div>
                        <div class="file-name" id="loan_statement_name"></div>
                    </div>
                    <input type="file" name="loan_statement" id="loan_statement" class="file-input-hidden" 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" onchange="updateFileName(this, 'loan_statement_name')">
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes (Optional)</label>
                    <textarea name="notes" id="notes" rows="4" 
                              placeholder="Any specific requirements or questions..."></textarea>
                </div>
                
                <div class="info-box" style="background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%); border-color: #f59e0b;">
                    <h4 style="color: #92400e;"><span>💡</span> Need Help?</h4>
                    <ul>
                        <li style="color: #92400e;">Not sure about what documents to upload?</li>
                        <li style="color: #92400e;">Click "Request More Details" to get expert guidance</li>
                        <li style="color: #92400e;">Our team will contact you within 24 hours</li>
                    </ul>
                </div>
                
                <input type="hidden" name="request_type" id="request_type" value="document_submission">
                
                <div class="btn-group">
                    <button type="button" class="btn btn-secondary" onclick="submitWithEmailRequest()">
                        📧 Request More Details
                    </button>
                    <button type="submit" class="btn btn-primary">
                        ✓ Submit Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        function selectUploadOption(option) {
            // Update radio buttons
            if (option === 'selected_year') {
                document.getElementById('opt_selected').checked = true;
                document.getElementById('opt_all').checked = false;
            } else {
                document.getElementById('opt_selected').checked = false;
                document.getElementById('opt_all').checked = true;
            }
            
            // Update visual styling
            document.querySelectorAll('.upload-option').forEach(opt => opt.classList.remove('active'));
            event.currentTarget.classList.add('active');
            
            // Show/hide upload containers
            if (option === 'selected_year') {
                document.getElementById('upload_selected_year').classList.add('active');
                document.getElementById('upload_all_years').classList.remove('active');
            } else {
                document.getElementById('upload_selected_year').classList.remove('active');
                document.getElementById('upload_all_years').classList.add('active');
            }
        }
        
        function updateYearLabels() {
            const selectedYear = document.getElementById('financial_year').value;
            if (!selectedYear) return;
            
            // Parse the year
            const years = selectedYear.split('-');
            const year1 = parseInt(years[0]);
            const year2 = parseInt(years[1]);
            
            // Update selected year label
            document.getElementById('selected_year_label').textContent = selectedYear;
            
            // Update 3-year labels
            document.getElementById('year1_label').textContent = `${year1}-${year2} (Selected Year)`;
            document.getElementById('year2_label').textContent = `${year1-1}-${year1}`;
            document.getElementById('year3_label').textContent = `${year1-2}-${year1-1}`;
        }
        
        function updateFileName(input, displayId) {
            const display = document.getElementById(displayId);
            const uploadBox = input.previousElementSibling;
            
            if (input.files && input.files[0]) {
                const fileName = input.files[0].name;
                const fileSize = (input.files[0].size / 1024 / 1024).toFixed(2);
                
                if (fileSize > 5) {
                    alert('File size exceeds 5MB. Please upload a smaller file.');
                    input.value = '';
                    display.textContent = '';
                    uploadBox.classList.remove('has-file');
                    return;
                }
                
                display.textContent = fileName + ' (' + fileSize + ' MB)';
                uploadBox.classList.add('has-file');
            } else {
                display.textContent = '';
                uploadBox.classList.remove('has-file');
            }
        }
        
        function submitWithEmailRequest() {
            if (confirm('This will send an email to Anugrah Accounting requesting more details about CMA preparation. Continue?')) {
                document.getElementById('request_type').value = 'request_details';
                document.getElementById('cmaForm').submit();
            }
        }
        
        document.getElementById('pan_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        document.getElementById('cmaForm').addEventListener('submit', function(e) {
            const pan = document.getElementById('pan_number').value;
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            
            if (!panRegex.test(pan)) {
                e.preventDefault();
                alert('Please enter a valid PAN number (e.g., ABCDE1234F)');
                return false;
            }
            
            return true;
        });
    </script>
</body>
</html>