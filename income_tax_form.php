<?php
session_start();
require_once 'db_config.php';

$success_message = '';
$error_message = '';

// Check if user is logged in
$user_data = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $user_data = $result->fetch_assoc();
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // User details
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    
    // Tax details
    $assessment_year = trim($_POST['assessment_year']);
    $financial_year = trim($_POST['financial_year']);
    $pan_number = strtoupper(trim($_POST['pan_number']));
    $aadhaar_number = trim($_POST['aadhaar_number']);
    $return_type = trim($_POST['return_type']);
    
    $bank_name = trim($_POST['bank_name']);
    $account_number = trim($_POST['account_number']);
    $ifsc_code = strtoupper(trim($_POST['ifsc_code']));
    $notes = trim($_POST['notes']);
    
    // Handle file upload
    $bank_statement_path = null;
    if (isset($_FILES['bank_statement']) && $_FILES['bank_statement']['error'] === 0) {
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/jpg', 'image/png'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($_FILES['bank_statement']['type'], $allowed_types)) {
            $errors[] = "Bank statement must be PDF, JPG, or PNG format";
        } elseif ($_FILES['bank_statement']['size'] > $max_size) {
            $errors[] = "Bank statement file size must not exceed 5MB";
        } else {
            $upload_dir = 'uploads/bank_statements/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_extension = pathinfo($_FILES['bank_statement']['name'], PATHINFO_EXTENSION);
            $new_filename = 'bank_stmt_' . time() . '_' . uniqid() . '.' . $file_extension;
            $bank_statement_path = $upload_dir . $new_filename;
            
            if (!move_uploaded_file($_FILES['bank_statement']['tmp_name'], $bank_statement_path)) {
                $errors[] = "Failed to upload bank statement";
                $bank_statement_path = null;
            }
        }
    }
    
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
        $errors[] = "Invalid IFSC code format (e.g., SBIN0001234)";
    }
    if (empty($return_type)) {
        $errors[] = "Please select a return type";
    }
    
    // Check for duplicate ITR
    $check_itr_sql = "SELECT id FROM income_tax_returns WHERE pan_number = ? AND assessment_year = ? AND financial_year = ?";
    $check_itr_stmt = $conn->prepare($check_itr_sql);
    $check_itr_stmt->bind_param("sss", $pan_number, $assessment_year, $financial_year);
    $check_itr_stmt->execute();
    $check_itr_result = $check_itr_stmt->get_result();
    if ($check_itr_result->num_rows > 0) {
        $errors[] = "Income Tax Return for this PAN, Assessment Year, and Financial Year already exists";
    }
    $check_itr_stmt->close();
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Get user_id if logged in, otherwise use email-based user
            if (isset($_SESSION['user_id'])) {
                $db_user_id = $_SESSION['user_id'];
                
                // Update user info
                $update_sql = "UPDATE users SET name = ?, phone = ? WHERE id = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ssi", $name, $phone, $db_user_id);
                $update_stmt->execute();
                $update_stmt->close();
            } else {
                // Insert or get user by email
                $user_sql = "INSERT INTO users (name, email, phone) VALUES (?, ?, ?)
                             ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone)";
                $user_stmt = $conn->prepare($user_sql);
                $user_stmt->bind_param("sss", $name, $email, $phone);
                $user_stmt->execute();
                
                if ($user_stmt->insert_id > 0) {
                    $db_user_id = $user_stmt->insert_id;
                } else {
                    $get_user_sql = "SELECT id FROM users WHERE email = ?";
                    $get_user_stmt = $conn->prepare($get_user_sql);
                    $get_user_stmt->bind_param("s", $email);
                    $get_user_stmt->execute();
                    $user_result = $get_user_stmt->get_result();
                    $user_row = $user_result->fetch_assoc();
                    $db_user_id = $user_row['id'];
                    $get_user_stmt->close();
                }
                $user_stmt->close();
            }
            
            // Insert ITR data (simplified - without income/deduction fields)
            $sql = "INSERT INTO income_tax_returns (
                        user_id, assessment_year, financial_year, pan_number, aadhaar_number, return_type,
                        bank_name, account_number, ifsc_code, bank_statement_path, notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("ITR prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("issssssssss", 
                $db_user_id, $assessment_year, $financial_year, $pan_number, $aadhaar_number, $return_type,
                $bank_name, $account_number, $ifsc_code, $bank_statement_path, $notes
            );
            
            if ($stmt->execute()) {
                $itr_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) 
                           VALUES (?, 'ITR_SUBMISSION', 'income_tax_returns', ?, 'Income Tax Return submitted')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ii", $db_user_id, $itr_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                $success_message = "Income Tax Return submitted successfully! Reference ID: ITR" . str_pad($itr_id, 6, '0', STR_PAD_LEFT);
            } else {
                throw new Exception("Error executing ITR insert: " . $stmt->error);
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Tax Return Filing - Anugrah Accounting</title>
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
            max-width: 1100px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 50px 40px;
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
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 12px;
            letter-spacing: -0.025em;
        }
        
        .header p {
            font-size: 1.125rem;
            opacity: 0.95;
            font-weight: 400;
        }
        
        .form-container { padding: 50px; }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 24px;
            margin-top: 32px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title:first-child { margin-top: 0; }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 2px;
        }
        
        .form-group { margin-bottom: 24px; }
        
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
            padding: 14px 18px;
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
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px rgba(59, 130, 246, 0.1);
        }
        
        input::placeholder, textarea::placeholder { color: #94a3b8; }
        
        input:disabled, select:disabled {
            background: #f1f5f9;
            color: #64748b;
            cursor: not-allowed;
        }
        
        .row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
        }
        
        .btn {
            padding: 14px 32px;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.2s ease;
            letter-spacing: 0.01em;
            width: 100%;
            margin-top: 32px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #2563eb 0%, #7c3aed 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
        }
        
        .btn:active { transform: translateY(0); }
        
        .alert {
            padding: 18px 22px;
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
        
        .return-type-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 16px;
            margin-top: 12px;
        }
        
        .return-type-card {
            padding: 20px;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            background: white;
            position: relative;
            overflow: hidden;
        }
        
        .return-type-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .return-type-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
        }
        
        .return-type-card:hover::before { transform: scaleX(1); }
        
        .return-type-card.selected {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);
        }
        
        .return-type-card.selected::before { transform: scaleX(1); }
        
        .return-type-icon { font-size: 2rem; margin-bottom: 8px; }
        
        .return-type-card .title {
            font-weight: 600;
            font-size: 1rem;
            margin-top: 6px;
            color: #0f172a;
        }
        
        .return-type-card.selected .title { color: #1e40af; }
        
        .return-type-card .description {
            font-size: 0.8125rem;
            color: #64748b;
            margin-top: 4px;
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
        
        .info-box {
            background: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 12px 16px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 0.8125rem;
            color: #475569;
            line-height: 1.5;
        }
        
        .file-upload-wrapper {
            position: relative;
            overflow: hidden;
            display: inline-block;
            width: 100%;
        }
        
        .file-upload-wrapper input[type=file] {
            position: absolute;
            left: -9999px;
        }
        
        .file-upload-label {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 14px 18px;
            background: white;
            border: 2px dashed #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            color: #64748b;
        }
        
        .file-upload-label:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }
        
        .file-upload-label.has-file {
            border-color: #22c55e;
            border-style: solid;
            background: #f0fdf4;
            color: #166534;
        }
        
        .file-upload-icon {
            font-size: 1.5rem;
        }
        
        .file-upload-text {
            flex: 1;
            font-weight: 500;
        }
        
        .file-name {
            font-size: 0.875rem;
            color: #166534;
            margin-top: 8px;
            padding: 8px 12px;
            background: #f0fdf4;
            border-radius: 6px;
            display: none;
        }
        
        .file-name.show {
            display: block;
        }
        
        @media (max-width: 768px) {
            .form-container { padding: 32px 24px; }
            .header { padding: 40px 24px; }
            .header h1 { font-size: 2rem; }
            .row { grid-template-columns: 1fr; }
            .return-type-cards { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>📊 Income Tax Return Filing</h1>
                <p>Complete and accurate ITR filing made simple for your peace of mind</p>
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
                    Keep ready: PAN Card, Aadhaar Card, Bank Details, and Bank Statement for accurate filing.
                </div>
            </div>
            
            <form method="POST" action="" id="itrForm" enctype="multipart/form-data">
                <!-- Personal Information -->
                <div class="section-title">Personal Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['name']) : ''; ?>"
                               placeholder="As per PAN Card">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                               <?php echo $user_data ? 'disabled' : ''; ?>
                               placeholder="your.email@example.com">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['phone']) : ''; ?>"
                               placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="pan_number">PAN Number <span class="required">*</span></label>
                        <input type="text" name="pan_number" id="pan_number" required 
                               placeholder="ABCDE1234F" maxlength="10" style="text-transform: uppercase;">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="aadhaar_number">Aadhaar Number <span class="required">*</span></label>
                        <input type="text" name="aadhaar_number" id="aadhaar_number" required 
                               placeholder="12-digit Aadhaar number" pattern="[0-9]{12}" maxlength="12">
                    </div>
                    
                    <div class="form-group">
                        <label for="assessment_year">Assessment Year <span class="required">*</span></label>
                        <select name="assessment_year" id="assessment_year" required>
                            <option value="">-- Select AY --</option>
                            <option value="2025-26">2025-26</option>
                            <option value="2024-25">2024-25</option>
                            <option value="2023-24">2023-24</option>
                            <option value="2022-23">2022-23</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="financial_year">Financial Year <span class="required">*</span></label>
                        <select name="financial_year" id="financial_year" required>
                            <option value="">-- Select FY --</option>
                            <option value="2024-25">2024-25</option>
                            <option value="2023-24">2023-24</option>
                            <option value="2022-23">2022-23</option>
                            <option value="2021-22">2021-22</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label>Return Type <span class="required">*</span></label>
                        <input type="hidden" name="return_type" id="return_type" required>
                        <div class="return-type-cards">
                            <div class="return-type-card" data-type="Individual">
                                <div class="return-type-icon">👤</div>
                                <div class="title">Individual</div>
                                <div class="description">Salaried</div>
                            </div>
                            <div class="return-type-card" data-type="Business">
                                <div class="return-type-icon">💼</div>
                                <div class="title">Business</div>
                                <div class="description">Professional</div>
                            </div>
                            <div class="return-type-card" data-type="Company">
                                <div class="return-type-icon">🏢</div>
                                <div class="title">Company</div>
                                <div class="description">Corporate</div>
                            </div>
                            <div class="return-type-card" data-type="Partnership">
                                <div class="return-type-icon">🤝</div>
                                <div class="title">Partnership</div>
                                <div class="description">Firm</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bank Details -->
                <div class="section-title">Bank Details</div>
                
                <div class="form-group">
                    <label for="bank_name">Bank Name <span class="required">*</span></label>
                    <input type="text" name="bank_name" id="bank_name" required placeholder="Enter bank name">
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="account_number">Account Number <span class="required">*</span></label>
                        <input type="text" name="account_number" id="account_number" required placeholder="Enter account number">
                    </div>
                    
                    <div class="form-group">
                        <label for="ifsc_code">IFSC Code <span class="required">*</span></label>
                        <input type="text" name="ifsc_code" id="ifsc_code" required 
                               placeholder="SBIN0001234" maxlength="11" style="text-transform: uppercase;">
                        <div class="info-box">11 characters: 4 letters + 0 + 6 alphanumeric (e.g., SBIN0001234)</div>
                    </div>
                </div>
                
                <!-- Bank Statement Upload -->
                <div class="form-group">
                    <label for="bank_statement">Bank Statement (Optional)</label>
                    <div class="file-upload-wrapper">
                        <input type="file" name="bank_statement" id="bank_statement" accept=".pdf,.jpg,.jpeg,.png">
                        <label for="bank_statement" class="file-upload-label" id="file-label">
                            <span class="file-upload-icon">📄</span>
                            <span class="file-upload-text">Click to upload bank statement (PDF, JPG, PNG - Max 5MB)</span>
                        </label>
                    </div>
                    <div class="file-name" id="file-name"></div>
                    <div class="info-box">Upload your latest 6-month bank statement for verification purposes</div>
                </div>
                
                <!-- Additional Notes -->
                <div class="form-group">
                    <label for="notes">Additional Notes / Special Circumstances</label>
                    <textarea name="notes" id="notes" placeholder="Any additional information you'd like to provide (optional)"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    ✓ Submit ITR Application
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Return type card selection
        document.querySelectorAll('.return-type-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.return-type-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('return_type').value = this.dataset.type;
            });
        });
        
        // File upload handler
        document.getElementById('bank_statement').addEventListener('change', function(e) {
            const fileLabel = document.getElementById('file-label');
            const fileName = document.getElementById('file-name');
            
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const fileSize = (file.size / 1024 / 1024).toFixed(2); // Convert to MB
                
                fileLabel.classList.add('has-file');
                fileName.textContent = `📎 ${file.name} (${fileSize} MB)`;
                fileName.classList.add('show');
                
                fileLabel.querySelector('.file-upload-text').textContent = 'File selected - Click to change';
            } else {
                fileLabel.classList.remove('has-file');
                fileName.classList.remove('show');
                fileLabel.querySelector('.file-upload-text').textContent = 'Click to upload bank statement (PDF, JPG, PNG - Max 5MB)';
            }
        });
        
        // Auto uppercase for PAN and IFSC
        document.getElementById('pan_number').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        document.getElementById('ifsc_code').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        // Form validation
        document.getElementById('itrForm').addEventListener('submit', function(e) {
            const returnType = document.getElementById('return_type').value;
            if (!returnType) {
                e.preventDefault();
                alert('Please select a return type');
                return false;
            }
            
            const pan = document.getElementById('pan_number').value;
            const aadhaar = document.getElementById('aadhaar_number').value;
            const ifsc = document.getElementById('ifsc_code').value;
            
            const panRegex = /^[A-Z]{5}[0-9]{4}[A-Z]{1}$/;
            const aadhaarRegex = /^[0-9]{12}$/;
            const ifscRegex = /^[A-Z]{4}0[A-Z0-9]{6}$/;
            
            if (!panRegex.test(pan)) {
                e.preventDefault();
                alert('Please enter a valid PAN number (e.g., ABCDE1234F)');
                return false;
            }
            
            if (!aadhaarRegex.test(aadhaar)) {
                e.preventDefault();
                alert('Please enter a valid 12-digit Aadhaar number');
                return false;
            }
            
            if (!ifscRegex.test(ifsc)) {
                e.preventDefault();
                alert('Please enter a valid IFSC code (11 characters: 4 letters + 0 + 6 alphanumeric)');
                return false;
            }
        });
        
        // If email is disabled, re-enable before submission
        <?php if ($user_data): ?>
        document.getElementById('itrForm').addEventListener('submit', function() {
            document.getElementById('email').disabled = false;
        });
        <?php endif; ?>
    </script>
</body>
</html>