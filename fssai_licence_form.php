<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
// db_config.php unified in includes/db.php

// Check if user is logged in
$user_logged_in = false;
$user_data = null;

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

$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // Personal details (auto-filled if logged in)
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    
    // Business details
    $business_name = trim($_POST['business_name']);
    $pan_number = strtoupper(trim($_POST['pan_number']));
    $business_type = trim($_POST['business_type']);
    $food_category = trim($_POST['food_category']);
    $business_address = trim($_POST['business_address']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    
    // Financial details
    $annual_turnover = filter_var($_POST['annual_turnover'], FILTER_VALIDATE_FLOAT);
    
    // Additional details
    $number_of_employees = filter_var($_POST['number_of_employees'], FILTER_VALIDATE_INT);
    $water_source = trim($_POST['water_source']);
    $waste_disposal = trim($_POST['waste_disposal']);
    $notes = trim($_POST['notes']);
    
    // Determine licence type based on turnover
    $licence_type = '';
    if ($annual_turnover < 1200000) {
        $licence_type = 'Basic';
    } elseif ($annual_turnover >= 1200000 && $annual_turnover <= 20000000) {
        $licence_type = 'State';
    } else {
        $licence_type = 'Central';
    }
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]{1}$/', $pan_number)) $errors[] = "Invalid PAN number format (e.g., ABCDE1234F)";
    if (!preg_match('/^[0-9]{6}$/', $pincode)) $errors[] = "Invalid pincode format";
    if (empty($business_name)) $errors[] = "Business name is required";
    if (empty($business_type)) $errors[] = "Business type is required";
    if (empty($food_category)) $errors[] = "Food category is required";
    if ($annual_turnover === false || $annual_turnover < 0) $errors[] = "Valid annual turnover is required";
    
    // Handle file upload (Rent Agreement)
    $uploaded_file_path = null;
    if (isset($_FILES['rent_agreement']) && $_FILES['rent_agreement']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/fssai_documents/';
        
        // Create directory if it doesn't exist
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $file_name = $_FILES['rent_agreement']['name'];
        $file_tmp = $_FILES['rent_agreement']['tmp_name'];
        $file_size = $_FILES['rent_agreement']['size'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_extensions = array('pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx');
        
        if (!in_array($file_ext, $allowed_extensions)) {
            $errors[] = "Invalid file type for rent agreement. Allowed: PDF, JPG, PNG, DOC, DOCX";
        } elseif ($file_size > 5242880) { // 5MB limit
            $errors[] = "Rent agreement file size must be less than 5MB";
        } else {
            $new_file_name = 'rent_agreement_' . time() . '_' . uniqid() . '.' . $file_ext;
            $upload_path = $upload_dir . $new_file_name;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                $uploaded_file_path = $upload_path;
            } else {
                $errors[] = "Failed to upload rent agreement";
            }
        }
    }
    
    // Check for duplicate FSSAI application
    if (empty($errors)) {
        $check_sql = "SELECT id FROM fssai_licences 
                     WHERE pan_number = ? AND status != 'Rejected'";
        $check_stmt = $conn->prepare($check_sql);
        $check_stmt->bind_param("s", $pan_number);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        
        if ($check_result->num_rows > 0) {
            $errors[] = "An active FSSAI licence application already exists with this PAN number";
        }
        $check_stmt->close();
    }
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Get or create user ID
            if ($user_logged_in) {
                $user_id = $_SESSION['user_id'];
            } else {
                // Create/update user
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
            }
            
            // Prepare variables - ensure they're not null
            $status = 'Pending';
            if ($number_of_employees === false || $number_of_employees === null) {
                $number_of_employees = 0;
            }
            if (empty($water_source)) {
                $water_source = '';
            }
            if (empty($waste_disposal)) {
                $waste_disposal = '';
            }
            
            // Prepare documents JSON
            $documents_array = array();
            if ($uploaded_file_path) {
                $documents_array['rent_agreement'] = $uploaded_file_path;
            }
            $documents_json = json_encode($documents_array);
            
            // Insert FSSAI application
            // Columns: 19 total
            $fssai_sql = "INSERT INTO fssai_licences (
                user_id, 
                user_name, 
                user_email, 
                user_phone, 
                business_name, 
                pan_number,
                business_type, 
                licence_type, 
                food_category, 
                business_address, 
                state, 
                pincode,
                annual_turnover, 
                number_of_employees, 
                water_source, 
                waste_disposal,
                documents, 
                notes, 
                status
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt = $conn->prepare($fssai_sql);
            
            if (!$stmt) {
                throw new Exception("FSSAI prepare failed: " . $conn->error);
            }
            
            // Bind parameters - 19 total
            // Type: i=integer, s=string, d=double
            // Position by position count:
            // 1=i, 2-12=s(11), 13=d, 14=i, 15-19=s(5)
            // Type string: i + sssssssssss + d + i + sssss = 19 chars
            $stmt->bind_param(
                "isssssssssssdisssss",
                $user_id,              // 1
                $name,                 // 2
                $email,                // 3
                $phone,                // 4
                $business_name,        // 5
                $pan_number,           // 6
                $business_type,        // 7
                $licence_type,         // 8
                $food_category,        // 9
                $business_address,     // 10
                $state,                // 11
                $pincode,              // 12
                $annual_turnover,      // 13
                $number_of_employees,  // 14
                $water_source,         // 15
                $waste_disposal,       // 16
                $documents_json,       // 17
                $notes,                // 18
                $status                // 19
            );
            
            if ($stmt->execute()) {
                $fssai_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, user_email, action, entity_type, entity_id, description) 
                           VALUES (?, ?, 'FSSAI_APPLICATION', 'fssai_licences', ?, 'FSSAI Licence applied')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("isi", $user_id, $email, $fssai_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                $ref_id = str_pad($fssai_id, 6, '0', STR_PAD_LEFT);
                $success_message = "FSSAI Licence application submitted successfully! Reference ID: FSSAI" . $ref_id;
                
                // Redirect to email for more details if requested
                if (isset($_POST['send_email_details'])) {
                    $admin_email = "anugrah0369@gmail.com";
                    $subject = "FSSAI Application Details Request - Ref: FSSAI" . $ref_id;
                    $body = "I need more information about FSSAI licence application.\n\n";
                    $body .= "Application Reference: FSSAI" . $ref_id . "\n";
                    $body .= "Name: " . $name . "\n";
                    $body .= "Email: " . $email . "\n";
                    $body .= "Phone: " . $phone . "\n";
                    $body .= "Business: " . $business_name . "\n\n";
                    $body .= "Please provide detailed information about the FSSAI licence process.";
                    
                    $mailto_link = "mailto:" . $admin_email . "?subject=" . rawurlencode($subject) . "&body=" . rawurlencode($body);
                    header("Location: " . $mailto_link);
                    exit();
                }
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&ref=" . $fssai_id);
                exit();
            } else {
                throw new Exception("Error executing FSSAI insert: " . $stmt->error);
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
    $success_message = "FSSAI Licence application submitted successfully! Reference ID: FSSAI" . $ref_id;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSSAI Licence Application - Anugrah Accounting</title>
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
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
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
            margin: 32px 0 20px 0;
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
            letter-spacing: 0.01em;
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
        
        input::placeholder, textarea::placeholder { color: #94a3b8; }
        
        input:disabled, select:disabled {
            background: #f1f5f9;
            cursor: not-allowed;
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
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
        
        .file-upload-wrapper {
            position: relative;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-input {
            padding: 12px 16px;
        }
        
        .file-upload-label {
            display: block;
            padding: 12px 16px;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            text-align: center;
            cursor: pointer;
            transition: all 0.2s ease;
            background: #f8fafc;
        }
        
        .file-upload-label:hover {
            border-color: #10b981;
            background: #f0fdf4;
        }
        
        .file-upload-label .icon {
            font-size: 2rem;
            margin-bottom: 8px;
        }
        
        .file-upload-label .text {
            color: #64748b;
            font-size: 0.875rem;
        }
        
        .file-upload-label .subtext {
            color: #94a3b8;
            font-size: 0.75rem;
            margin-top: 4px;
        }
        
        .file-name-display {
            margin-top: 8px;
            padding: 8px 12px;
            background: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 6px;
            font-size: 0.875rem;
            color: #059669;
            display: none;
        }
        
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
        
        .info-box {
            background: #f0fdf4;
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 0.8125rem;
            color: #059669;
            line-height: 1.5;
        }
        
        .feature-highlight {
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            border: 2px solid #3b82f6;
            border-radius: 10px;
            padding: 16px 20px;
            margin: 20px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .feature-highlight::before { content: '💡'; font-size: 1.5rem; }
        
        .feature-highlight-text {
            font-size: 0.9375rem;
            color: #1e40af;
            font-weight: 500;
        }
        
        .btn-group {
            display: flex;
            gap: 16px;
            margin-top: 32px;
            flex-wrap: wrap;
        }
        
        .btn {
            padding: 14px 28px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
            flex: 1;
            min-width: 200px;
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
        
        .btn-secondary {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        
        .btn-secondary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn:active { transform: translateY(0); }
        
        @media (max-width: 768px) {
            .form-container { padding: 24px; }
            .header { padding: 32px 24px; }
            .header h1 { font-size: 1.5rem; }
            .row { grid-template-columns: 1fr; }
            .btn { min-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>🍽️ FSSAI Licence Application</h1>
                <p>Get your Food Safety and Standards Authority of India Licence</p>
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
                    Keep ready: PAN Card, Aadhaar Card, Business registration documents, Rent Agreement, and food category details.
                </div>
            </div>
            
            <form method="POST" action="" enctype="multipart/form-data" id="fssaiForm">
                <div class="section-title">Personal Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['name']) : ''; ?>"
                               <?php echo $user_logged_in ? 'readonly' : ''; ?>
                               placeholder="As per official documents">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                               <?php echo $user_logged_in ? 'readonly' : ''; ?>
                               placeholder="your.email@example.com">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" name="phone" id="phone" required 
                           value="<?php echo $user_data ? htmlspecialchars($user_data['phone']) : ''; ?>"
                           <?php echo $user_logged_in ? 'readonly' : ''; ?>
                           placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10">
                </div>
                
                <div class="section-title">Business Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="business_name">Business Name <span class="required">*</span></label>
                        <input type="text" name="business_name" id="business_name" required placeholder="Enter your business name">
                    </div>
                    
                    <div class="form-group">
                        <label for="pan_number">PAN Number <span class="required">*</span></label>
                        <input type="text" name="pan_number" id="pan_number" required 
                               placeholder="ABCDE1234F" pattern="[A-Z]{5}[0-9]{4}[A-Z]{1}" 
                               maxlength="10" style="text-transform: uppercase;">
                        <div class="info-box">Format: ABCDE1234F (5 letters, 4 digits, 1 letter)</div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="business_type">Food Business Type <span class="required">*</span></label>
                        <select name="business_type" id="business_type" required>
                            <option value="">-- Select Food Business Type --</option>
                            <option value="Food Manufacturer">🏭 Food Manufacturer</option>
                            <option value="Food Trader/Retailer">🛒 Food Trader/Retailer</option>
                            <option value="Restaurant/Eatery">🍽️ Restaurant/Eatery</option>
                            <option value="Food Distributor">🚚 Food Distributor</option>
                            <option value="Catering Service">🎉 Catering Service</option>
                            <option value="Food Storage/Warehouse">📦 Food Storage/Warehouse</option>
                            <option value="Bakery">🍞 Bakery</option>
                            <option value="Cloud Kitchen">👨‍🍳 Cloud Kitchen</option>
                            <option value="Sweet Shop">🍬 Sweet Shop/Mithai Shop</option>
                            <option value="Food Processing Unit">⚙️ Food Processing Unit</option>
                            <option value="Dairy Unit">🥛 Dairy/Milk Processing Unit</option>
                            <option value="Food Packaging Unit">📦 Food Packaging Unit</option>
                            <option value="Mobile Food Vendor">🚐 Mobile Food Vendor/Cart</option>
                            <option value="Food Import/Export">✈️ Food Import/Export</option>
                        </select>
                        <div class="info-box">This licence is only for food-related businesses</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="food_category">Food Category <span class="required">*</span></label>
                        <select name="food_category" id="food_category" required>
                            <option value="">-- Select Category --</option>
                            <option value="Dairy Products">Dairy Products</option>
                            <option value="Bakery Products">Bakery Products</option>
                            <option value="Confectionery">Confectionery</option>
                            <option value="Snack Foods">Snack Foods</option>
                            <option value="Beverages">Beverages</option>
                            <option value="Packaged Foods">Packaged Foods</option>
                            <option value="Cooked Food Service">Cooked Food Service</option>
                            <option value="Ice Cream & Desserts">Ice Cream & Desserts</option>
                            <option value="Spices & Condiments">Spices & Condiments</option>
                            <option value="Fruits & Vegetables">Fruits & Vegetables</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="annual_turnover">Annual Turnover (₹) <span class="required">*</span></label>
                        <input type="number" name="annual_turnover" id="annual_turnover" step="0.01" min="0" required placeholder="Enter annual turnover">
                        <div class="info-box">This determines your licence type automatically</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="number_of_employees">Number of Employees</label>
                        <input type="number" name="number_of_employees" id="number_of_employees" min="0" placeholder="Total workforce">
                    </div>
                </div>
                
                <div class="section-title">Location & Compliance</div>
                
                <div class="form-group">
                    <label for="business_address">Business Address <span class="required">*</span></label>
                    <textarea name="business_address" id="business_address" required placeholder="Enter complete business address with landmark"></textarea>
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
                        <input type="text" name="pincode" id="pincode" required pattern="[0-9]{6}" maxlength="6" placeholder="6-digit PIN code">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="water_source">Water Source</label>
                        <select name="water_source" id="water_source">
                            <option value="">-- Select Water Source --</option>
                            <option value="Municipal Supply">Municipal Supply</option>
                            <option value="Borewell">Borewell</option>
                            <option value="Tanker">Tanker</option>
                            <option value="Mixed">Mixed Sources</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="waste_disposal">Waste Disposal Method</label>
                        <select name="waste_disposal" id="waste_disposal">
                            <option value="">-- Select Method --</option>
                            <option value="Municipal Collection">Municipal Collection</option>
                            <option value="Private Agency">Private Agency</option>
                            <option value="Own Arrangement">Own Arrangement</option>
                            <option value="Biogas Plant">Biogas Plant</option>
                        </select>
                    </div>
                </div>
                
                <div class="section-title">Document Upload</div>
                
                <div class="form-group">
                    <label for="rent_agreement">Rent Agreement / Ownership Document</label>
                    <input type="file" name="rent_agreement" id="rent_agreement" 
                           accept=".pdf,.jpg,.jpeg,.png,.doc,.docx" 
                           style="display: none;" onchange="displayFileName(this)">
                    <label for="rent_agreement" class="file-upload-label">
                        <div class="icon">📄</div>
                        <div class="text">Click to upload rent agreement or ownership proof</div>
                        <div class="subtext">Supported: PDF, JPG, PNG, DOC, DOCX (Max 5MB)</div>
                    </label>
                    <div class="file-name-display" id="file-display"></div>
                    <div class="info-box">Upload your business premises rent agreement or property ownership documents</div>
                </div>
                
                <div class="section-title">Additional Information</div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes / Special Requirements</label>
                    <textarea name="notes" id="notes" placeholder="Any additional information you'd like to provide (optional)"></textarea>
                </div>
                
                <div class="info-box" style="margin-top: 24px;">
                    <strong>What happens next?</strong><br>
                    • Your application will be reviewed within 2-3 business days<br>
                    • You'll receive updates via email and SMS<br>
                    • Processing time: 7-60 days depending on licence type<br>
                    • Licence type is automatically determined based on annual turnover
                </div>
                
                <div class="btn-group">
                    <button type="submit" class="btn btn-primary">✓ Submit Application</button>
                    <button type="submit" name="send_email_details" value="1" class="btn btn-secondary">
                        📧 Submit & Email for Details
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // PAN number auto-uppercase
        document.getElementById('pan_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Phone validation
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Pincode validation
        document.getElementById('pincode').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Display uploaded file name
        function displayFileName(input) {
            const display = document.getElementById('file-display');
            if (input.files && input.files[0]) {
                display.textContent = '📎 ' + input.files[0].name;
                display.style.display = 'block';
            } else {
                display.style.display = 'none';
            }
        }
        
        // Auto-determine licence type based on turnover
        document.getElementById('annual_turnover').addEventListener('input', function() {
            const turnover = parseFloat(this.value) || 0;
            let licenceType = '';
            
            if (turnover < 1200000) {
                licenceType = 'Basic Registration (< ₹12 Lakh)';
            } else if (turnover >= 1200000 && turnover <= 20000000) {
                licenceType = 'State Licence (₹12L - ₹20Cr)';
            } else {
                licenceType = 'Central Licence (> ₹20 Crore)';
            }
            
            // Show info about auto-determined licence type
            const existingInfo = document.querySelector('.licence-type-info');
            if (existingInfo) {
                existingInfo.remove();
            }
            
            if (turnover > 0) {
                const infoBox = document.createElement('div');
                infoBox.className = 'info-box licence-type-info';
                infoBox.innerHTML = '<strong>Auto-determined Licence Type:</strong> ' + licenceType;
                this.parentElement.appendChild(infoBox);
            }
        });
        
        // Auto-fill Food Category based on Business Type
        document.getElementById('business_type').addEventListener('change', function() {
            const businessType = this.value;
            const foodCategorySelect = document.getElementById('food_category');
            
            // Define mapping between business types and suggested food categories
            const categoryMapping = {
                'Food Manufacturer': 'Packaged Foods',
                'Food Trader/Retailer': 'Packaged Foods',
                'Restaurant/Eatery': 'Cooked Food Service',
                'Food Distributor': 'Packaged Foods',
                'Catering Service': 'Cooked Food Service',
                'Food Storage/Warehouse': 'Packaged Foods',
                'Bakery': 'Bakery Products',
                'Cloud Kitchen': 'Cooked Food Service',
                'Sweet Shop': 'Confectionery',
                'Food Processing Unit': 'Packaged Foods',
                'Dairy Unit': 'Dairy Products',
                'Food Packaging Unit': 'Packaged Foods',
                'Mobile Food Vendor': 'Cooked Food Service',
                'Food Import/Export': 'Packaged Foods'
            };
            
            // Auto-select the food category if mapping exists
            if (categoryMapping[businessType]) {
                foodCategorySelect.value = categoryMapping[businessType];
                
                // Add visual feedback with animation
                foodCategorySelect.style.borderColor = '#10b981';
                foodCategorySelect.style.background = '#f0fdf4';
                foodCategorySelect.style.transition = 'all 0.3s ease';
                
                // Show a notification that category was auto-filled
                const existingNotif = document.querySelector('.auto-fill-notification');
                if (existingNotif) {
                    existingNotif.remove();
                }
                
                const notification = document.createElement('div');
                notification.className = 'info-box auto-fill-notification';
                notification.style.marginTop = '8px';
                notification.innerHTML = '✓ Food Category automatically filled based on your business type';
                foodCategorySelect.parentElement.appendChild(notification);
                
                // Reset styling after 3 seconds
                setTimeout(function() {
                    foodCategorySelect.style.borderColor = '#e2e8f0';
                    foodCategorySelect.style.background = 'white';
                    if (notification.parentElement) {
                        notification.remove();
                    }
                }, 3000);
            } else {
                // Reset if no business type selected
                foodCategorySelect.value = '';
                const existingNotif = document.querySelector('.auto-fill-notification');
                if (existingNotif) {
                    existingNotif.remove();
                }
            }
        });
        
        // Form validation
        document.getElementById('fssaiForm').addEventListener('submit', function(e) {
            const pan = document.getElementById('pan_number').value;
            const panPattern = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            
            if (!panPattern.test(pan)) {
                e.preventDefault();
                alert('Please enter a valid PAN number (Format: ABCDE1234F)');
                document.getElementById('pan_number').focus();
                return false;
            }
            
            const phone = document.getElementById('phone').value;
            if (phone.length !== 10) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number');
                document.getElementById('phone').focus();
                return false;
            }
            
            const pincode = document.getElementById('pincode').value;
            if (pincode.length !== 6) {
                e.preventDefault();
                alert('Please enter a valid 6-digit pincode');
                document.getElementById('pincode').focus();
                return false;
            }
        });
    </script>
</body>
</html>