<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

// Database connection with error handling
$conn = null;
$db_error = false;
$db_error_message = '';

if (file_exists('db_config.php')) {
    // db_config.php unified in includes/db.php
    
    if (!isset($conn) || $conn === null) {
        $db_error = true;
        $db_error_message = "Database connection object not created. Please check db_config.php";
    } elseif ($conn->connect_error) {
        $db_error = true;
        $db_error_message = "Database connection failed: " . $conn->connect_error;
    }
} else {
    $db_error = true;
    $db_error_message = "Database configuration file (db_config.php) not found.";
}

$success_message = '';
$error_message = '';

// Check if user is logged in
$user_data = null;
if (!$db_error && $conn !== null && isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$db_error && $conn !== null) {
    $errors = [];
    
    // Personal details
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    
    // Business details
    $business_name = trim($_POST['business_name']);
    $business_type = trim($_POST['business_type']);
    $pan_number = strtoupper(trim($_POST['pan_number']));
    $aadhaar_number = trim($_POST['aadhaar_number']);
    
    // Business address
    $business_address = trim($_POST['business_address']);
    $state = trim($_POST['state']);
    $pincode = trim($_POST['pincode']);
    $city = trim($_POST['city']);
    
    // Business operations
    $business_activity = trim($_POST['business_activity']);
    $estimated_turnover = filter_var($_POST['estimated_turnover'], FILTER_VALIDATE_FLOAT);
    $business_start_date = trim($_POST['business_start_date']);
    $number_of_employees = filter_var($_POST['number_of_employees'], FILTER_VALIDATE_INT);
    
    // Additional information
    $has_existing_business = trim($_POST['has_existing_business']);
    $existing_registrations = trim($_POST['existing_registrations']);
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc_code = strtoupper(trim($_POST['ifsc_code']));
    $notes = trim($_POST['notes']);
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (!preg_match('/^[A-Z]{5}[0-9]{4}[A-Z]$/', $pan_number)) {
        $errors[] = "Invalid PAN format (e.g., ABCDE1234F)";
    }
    if (!preg_match('/^[0-9]{12}$/', $aadhaar_number)) {
        $errors[] = "Valid 12-digit Aadhaar number required";
    }
    if (!preg_match('/^[0-9]{6}$/', $pincode)) {
        $errors[] = "Valid 6-digit pincode required";
    }
    if (!preg_match('/^[A-Z]{4}0[A-Z0-9]{6}$/', $ifsc_code)) {
        $errors[] = "Invalid IFSC code format (e.g., SBIN0001234)";
    }
    
    // Check for duplicate PAN
    try {
        $check_pan_sql = "SELECT id FROM gst_registrations WHERE pan_number = ?";
        $check_pan_stmt = $conn->prepare($check_pan_sql);
        
        if ($check_pan_stmt !== false) {
            $check_pan_stmt->bind_param("s", $pan_number);
            $check_pan_stmt->execute();
            $check_pan_result = $check_pan_stmt->get_result();
            if ($check_pan_result->num_rows > 0) {
                $errors[] = "GST Registration with this PAN number already exists";
            }
            $check_pan_stmt->close();
        }
    } catch (Exception $e) {
        error_log("Error checking PAN: " . $e->getMessage());
    }
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            $db_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if ($db_user_id) {
                $final_user_id = $db_user_id;
                
                $update_user_sql = "UPDATE users SET name=?, phone=?, address=? WHERE id=?";
                $update_user_stmt = $conn->prepare($update_user_sql);
                
                if ($update_user_stmt === false) {
                    throw new Exception("User update prepare failed: " . $conn->error);
                }
                
                $update_user_stmt->bind_param("sssi", $name, $phone, $address, $final_user_id);
                
                if (!$update_user_stmt->execute()) {
                    throw new Exception("User update failed: " . $update_user_stmt->error);
                }
                
                $update_user_stmt->close();
            } else {
                $user_sql = "INSERT INTO users (name, email, phone, address, is_active) VALUES (?, ?, ?, ?, 1)
                             ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), address=VALUES(address)";
                $user_stmt = $conn->prepare($user_sql);
                
                if ($user_stmt === false) {
                    throw new Exception("User prepare failed: " . $conn->error);
                }
                
                $user_stmt->bind_param("ssss", $name, $email, $phone, $address);
                
                if (!$user_stmt->execute()) {
                    throw new Exception("User insert failed: " . $user_stmt->error);
                }
                
                if ($user_stmt->insert_id > 0) {
                    $final_user_id = $user_stmt->insert_id;
                } else {
                    $get_user_sql = "SELECT id FROM users WHERE email = ?";
                    $get_user_stmt = $conn->prepare($get_user_sql);
                    
                    if ($get_user_stmt === false) {
                        throw new Exception("Get user prepare failed: " . $conn->error);
                    }
                    
                    $get_user_stmt->bind_param("s", $email);
                    $get_user_stmt->execute();
                    $user_result = $get_user_stmt->get_result();
                    $user_row = $user_result->fetch_assoc();
                    $final_user_id = $user_row['id'];
                    $get_user_stmt->close();
                }
                $user_stmt->close();
            }
            
            $additional_data = json_encode([
                'aadhaar_number' => $aadhaar_number,
                'city' => $city,
                'business_start_date' => $business_start_date,
                'number_of_employees' => $number_of_employees,
                'has_existing_business' => $has_existing_business,
                'existing_registrations' => $existing_registrations,
                'bank_name' => $bank_name,
                'account_number' => $account_number,
                'ifsc_code' => $ifsc_code,
                'notes' => $notes
            ]);
            
            $gst_sql = "INSERT INTO gst_registrations 
                       (user_id, business_name, business_type, pan_number, business_address, state, pincode, 
                        business_activity, estimated_turnover, additional_data, status, created_at) 
                       VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', NOW())";
            
            $gst_stmt = $conn->prepare($gst_sql);
            
            if ($gst_stmt === false) {
                throw new Exception("GST registration prepare failed: " . $conn->error);
            }
            
            $gst_stmt->bind_param("isssssssds", 
                $final_user_id, $business_name, $business_type, $pan_number, 
                $business_address, $state, $pincode, $business_activity, 
                $estimated_turnover, $additional_data
            );
            
            if (!$gst_stmt->execute()) {
                throw new Exception("GST registration insert failed: " . $gst_stmt->error);
            }
            
            $gst_stmt->close();
            
            mysqli_commit($conn);
            $success_message = "GST Registration submitted successfully! Your application is under review.";
            
        } catch (Exception $e) {
            mysqli_rollback($conn);
            $error_message = "Registration failed: " . $e->getMessage();
            error_log("GST Registration Error: " . $e->getMessage());
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Registration Form</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 36px;
            margin-bottom: 10px;
        }
        
        .header p {
            font-size: 18px;
            opacity: 0.9;
        }
        
        .user-info {
            background: rgba(255,255,255,0.1);
            padding: 15px 25px;
            border-radius: 10px;
            margin-top: 20px;
            display: inline-flex;
            align-items: center;
            gap: 15px;
        }
        
        .user-info .icon {
            width: 40px;
            height: 40px;
            background: white;
            color: #667eea;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 18px;
        }
        
        .form-content {
            padding: 40px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            font-weight: 500;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeeba;
        }
        
        .form-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            margin-bottom: 30px;
            border-left: 5px solid #667eea;
        }
        
        .form-section h2 {
            color: #667eea;
            font-size: 24px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .section-number {
            width: 40px;
            height: 40px;
            background: #667eea;
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            font-weight: bold;
        }
        
        .form-section p {
            color: #666;
            margin-bottom: 25px;
        }
        
        .form-group {
            margin-bottom: 25px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 25px;
        }
        
        label {
            display: block;
            font-weight: 600;
            color: #333;
            margin-bottom: 8px;
            font-size: 15px;
        }
        
        .required {
            color: #e74c3c;
        }
        
        input[type="text"],
        input[type="email"],
        input[type="tel"],
        input[type="number"],
        input[type="date"],
        select,
        textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 15px;
            font-family: inherit;
            transition: all 0.3s;
        }
        
        input:focus,
        select:focus,
        textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        input.readonly {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        
        textarea {
            resize: vertical;
            min-height: 100px;
        }
        
        small {
            display: block;
            margin-top: 5px;
            color: #666;
            font-size: 13px;
        }
        
        .radio-group {
            display: flex;
            gap: 20px;
            margin-top: 10px;
        }
        
        .radio-group label {
            display: flex;
            align-items: center;
            gap: 8px;
            font-weight: normal;
            cursor: pointer;
        }
        
        .radio-group input[type="radio"] {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .submit-section {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 15px;
            text-align: center;
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 18px 60px;
            font-size: 18px;
            font-weight: 600;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
        }
        
        .btn-submit:active {
            transform: translateY(0);
        }
        
        h3 {
            color: #667eea;
            font-size: 20px;
            margin: 30px 0 20px;
        }
        
        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .form-content {
                padding: 20px;
            }
            
            .header {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>GST Registration Form</h1>
            <p>Complete your GST registration application</p>
            
            <?php if ($user_data && isset($user_data['name']) && !empty($user_data['name'])): ?>
            <div class="user-info">
                <div class="icon"><?php echo isset($user_data['name']) ? strtoupper(substr($user_data['name'], 0, 1)) : 'U'; ?></div>
                <div>
                    <strong>Welcome back, <?php echo isset($user_data['name']) ? htmlspecialchars($user_data['name']) : 'User'; ?>!</strong>
                </div>
            </div>
            <?php endif; ?>
        </div>
        
        <div class="form-content">
            <?php if ($db_error): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Database Connection Warning:</strong> <?php echo htmlspecialchars($db_error_message); ?>
                    <br>The form will still work, but data cannot be saved until the database is configured.
                </div>
            <?php endif; ?>
            
            <?php if ($success_message): ?>
                <div class="alert alert-success">
                    ✓ <?php echo htmlspecialchars($success_message); ?>
                </div>
            <?php endif; ?>
            
            <?php if ($error_message): ?>
                <div class="alert alert-error">
                    ✗ <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <form id="gstForm" method="POST" action="">
                <!-- Section 1: Personal Details -->
                <div class="form-section">
                    <h2>
                        <span class="section-number">1</span>
                        Personal Details
                    </h2>
                    <p>Enter your personal information</p>
                    
                    <div class="form-group">
                        <label>Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" 
                               value="<?php echo ($user_data && isset($user_data['name'])) ? htmlspecialchars($user_data['name']) : ''; ?>" 
                               required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Email Address <span class="required">*</span></label>
                            <input type="email" name="email" id="email" 
                                   value="<?php echo ($user_data && isset($user_data['email'])) ? htmlspecialchars($user_data['email']) : ''; ?>"
                                   <?php echo $user_data ? 'class="readonly" readonly' : ''; ?>
                                   required>
                            <?php if ($user_data): ?>
                            <small>Email cannot be changed for logged-in users</small>
                            <?php endif; ?>
                        </div>
                        
                        <div class="form-group">
                            <label>Phone Number <span class="required">*</span></label>
                            <input type="tel" name="phone" id="phone" 
                                   value="<?php echo ($user_data && isset($user_data['phone'])) ? htmlspecialchars($user_data['phone']) : ''; ?>"
                                   maxlength="10" required>
                            <small>10-digit mobile number</small>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>PAN Number <span class="required">*</span></label>
                            <input type="text" name="pan_number" id="pan_number" maxlength="10" required style="text-transform: uppercase;">
                            <small>Format: ABCDE1234F</small>
                        </div>
                        
                        <div class="form-group">
                            <label>Aadhaar Number <span class="required">*</span></label>
                            <input type="text" name="aadhaar_number" id="aadhaar_number" maxlength="12" required>
                            <small>12-digit Aadhaar number</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Residential Address <span class="required">*</span></label>
                        <textarea name="address" id="address" required><?php echo ($user_data && isset($user_data['address'])) ? htmlspecialchars($user_data['address']) : ''; ?></textarea>
                    </div>
                </div>
                
                <!-- Section 2: Business Details -->
                <div class="form-section">
                    <h2>
                        <span class="section-number">2</span>
                        Business Details
                    </h2>
                    <p>Provide your business information</p>
                    
                    <div class="form-group">
                        <label>Business Name <span class="required">*</span></label>
                        <input type="text" name="business_name" id="business_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Business Type <span class="required">*</span></label>
                            <select name="business_type" id="business_type" required>
                                <option value="">Select Business Type</option>
                                <option value="Sole Proprietorship">Sole Proprietorship</option>
                                <option value="Partnership">Partnership</option>
                                <option value="Limited Liability Partnership">Limited Liability Partnership (LLP)</option>
                                <option value="Private Limited Company">Private Limited Company</option>
                                <option value="Public Limited Company">Public Limited Company</option>
                                <option value="Hindu Undivided Family">Hindu Undivided Family (HUF)</option>
                                <option value="Trust">Trust</option>
                                <option value="Society">Society</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Business Start Date <span class="required">*</span></label>
                            <input type="date" name="business_start_date" id="business_start_date" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Number of Employees</label>
                        <input type="number" name="number_of_employees" id="number_of_employees" min="0" value="0">
                    </div>
                </div>
                
                <!-- Section 3: Business Address -->
                <div class="form-section">
                    <h2>
                        <span class="section-number">3</span>
                        Business Address
                    </h2>
                    <p>Enter your business location details</p>
                    
                    <div class="form-group">
                        <label>Business Address <span class="required">*</span></label>
                        <textarea name="business_address" id="business_address" required></textarea>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>City <span class="required">*</span></label>
                            <input type="text" name="city" id="city" required>
                        </div>
                        
                        <div class="form-group">
                            <label>State <span class="required">*</span></label>
                            <select name="state" id="state" required>
                                <option value="">Select State</option>
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
                                <option value="Andaman and Nicobar Islands">Andaman and Nicobar Islands</option>
                                <option value="Chandigarh">Chandigarh</option>
                                <option value="Dadra and Nagar Haveli and Daman and Diu">Dadra and Nagar Haveli and Daman and Diu</option>
                                <option value="Delhi">Delhi</option>
                                <option value="Jammu and Kashmir">Jammu and Kashmir</option>
                                <option value="Ladakh">Ladakh</option>
                                <option value="Lakshadweep">Lakshadweep</option>
                                <option value="Puducherry">Puducherry</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Pincode <span class="required">*</span></label>
                        <input type="text" name="pincode" id="pincode" maxlength="6" required>
                        <small>6-digit pincode</small>
                    </div>
                </div>
                
                <!-- Section 4: Business Operations & Banking -->
                <div class="form-section">
                    <h2>
                        <span class="section-number">4</span>
                        Business Operations & Banking
                    </h2>
                    <p>Additional business information</p>
                    
                    <div class="form-group">
                        <label>Primary Business Activity <span class="required">*</span></label>
                        <textarea name="business_activity" id="business_activity" required></textarea>
                        <small>Describe your main business activities</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Estimated Annual Turnover (₹) <span class="required">*</span></label>
                        <input type="number" name="estimated_turnover" id="estimated_turnover" step="0.01" min="0" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Do you have existing business registrations? <span class="required">*</span></label>
                        <div class="radio-group">
                            <label>
                                <input type="radio" name="has_existing_business" value="Yes" required>
                                Yes
                            </label>
                            <label>
                                <input type="radio" name="has_existing_business" value="No" required>
                                No
                            </label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Existing Registration Details (if any)</label>
                        <textarea name="existing_registrations" id="existing_registrations"></textarea>
                        <small>E.g., Shop Act, MSME, Import Export Code, etc.</small>
                    </div>
                    
                    <h3>Banking Information</h3>
                    
                    <div class="form-group">
                        <label>Bank Name <span class="required">*</span></label>
                        <input type="text" name="bank_name" id="bank_name" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Account Number <span class="required">*</span></label>
                            <input type="text" name="account_number" id="account_number" required>
                        </div>
                        
                        <div class="form-group">
                            <label>IFSC Code <span class="required">*</span></label>
                            <input type="text" name="ifsc_code" id="ifsc_code" maxlength="11" required style="text-transform: uppercase;">
                            <small>Format: SBIN0001234</small>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Additional Notes</label>
                        <textarea name="notes" id="notes"></textarea>
                    </div>
                </div>
                
                <!-- Submit Section -->
                <div class="submit-section">
                    <button type="submit" class="btn-submit">Submit GST Registration</button>
                    <p style="margin-top: 15px; color: #666;">
                        By submitting this form, you confirm that all information provided is accurate and complete.
                    </p>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Auto-uppercase for PAN and IFSC
        document.getElementById('pan_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        document.getElementById('ifsc_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Numeric validation for phone, aadhaar, pincode
        document.getElementById('phone').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('aadhaar_number').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        document.getElementById('pincode').addEventListener('input', function(e) {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
        
        // Form validation on submit
        document.getElementById('gstForm').addEventListener('submit', function(e) {
            const businessType = document.getElementById('business_type').value;
            if (!businessType) {
                e.preventDefault();
                alert('Please select a business type');
                document.getElementById('business_type').focus();
                return false;
            }
            
            const pan = document.getElementById('pan_number').value;
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]$/;
            if (!panRegex.test(pan)) {
                e.preventDefault();
                alert('Please enter a valid PAN number (e.g., ABCDE1234F)');
                document.getElementById('pan_number').focus();
                return false;
            }
            
            const aadhaar = document.getElementById('aadhaar_number').value;
            if (aadhaar.length !== 12) {
                e.preventDefault();
                alert('Please enter a valid 12-digit Aadhaar number');
                document.getElementById('aadhaar_number').focus();
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
            
            const ifsc = document.getElementById('ifsc_code').value;
            const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            if (!ifscRegex.test(ifsc)) {
                e.preventDefault();
                alert('Please enter a valid IFSC code (e.g., SBIN0001234)');
                document.getElementById('ifsc_code').focus();
                return false;
            }
            
            const hasExisting = document.querySelector('input[name="has_existing_business"]:checked');
            if (!hasExisting) {
                e.preventDefault();
                alert('Please select whether you have existing business registration');
                return false;
            }
        });
        
        // Smooth scroll on page load if there's a success/error message
        window.addEventListener('load', function() {
            const alerts = document.querySelectorAll('.alert');
            if (alerts.length > 0) {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }
        });
    </script>
</body>
</html>