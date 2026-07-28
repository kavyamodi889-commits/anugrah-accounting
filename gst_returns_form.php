<?php
session_start();
require_once 'db_config.php';

$success_message = '';
$error_message = '';

// Check if user is logged in
$logged_in_user = null;
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $user_stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
    $user_stmt->bind_param("i", $user_id);
    $user_stmt->execute();
    $logged_in_user = $user_stmt->get_result()->fetch_assoc();
    $user_stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $errors = [];
    
    // User details
    $name = trim($_POST['name']);
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);
    $phone = trim($_POST['phone']);
    $business_name = trim($_POST['business_name']);
    $business_address = trim($_POST['business_address']);
    
    // GST details
    $gstin = strtoupper(trim($_POST['gstin']));
    $return_type = trim($_POST['return_type']);
    $financial_year = trim($_POST['financial_year']);
    $return_period = ''; // Empty since field removed
    
    // Sales & Purchase details
    $total_sales = filter_var($_POST['total_sales'], FILTER_VALIDATE_FLOAT);
    $total_purchases = filter_var($_POST['total_purchases'], FILTER_VALIDATE_FLOAT);
    $exempt_sales = filter_var($_POST['exempt_sales'], FILTER_VALIDATE_FLOAT);
    $zero_rated_sales = filter_var($_POST['zero_rated_sales'], FILTER_VALIDATE_FLOAT);
    
    // Tax details - set to 0 since fields removed
    $output_tax = 0;
    $input_tax_credit = 0;
    $tax_payable = 0;
    $interest_amount = 0;
    $late_fee = 0;
    
    $notes = trim($_POST['notes']);
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
        $errors[] = "Invalid GSTIN format (e.g., 22AAAAA0000A1Z5)";
    }
    if (empty($return_type)) $errors[] = "Please select a return type";
    
    // Check for duplicate GST return (without return_period)
    $check_gst_sql = "SELECT id FROM gst_returns WHERE gstin = ? AND return_type = ? AND financial_year = ?";
    $check_gst_stmt = $conn->prepare($check_gst_sql);
    $check_gst_stmt->bind_param("sss", $gstin, $return_type, $financial_year);
    $check_gst_stmt->execute();
    $check_gst_result = $check_gst_stmt->get_result();
    if ($check_gst_result->num_rows > 0) {
        $errors[] = "GST Return for this GSTIN, Return Type, and Financial Year already exists";
    }
    $check_gst_stmt->close();
    
    if (empty($errors)) {
        mysqli_autocommit($conn, FALSE);
        
        try {
            // Get or create user_id
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            if (!$user_id) {
                // Insert or update user
                $user_sql = "INSERT INTO users (name, email, phone, company_name, gstin, is_active) VALUES (?, ?, ?, ?, ?, 1)
                             ON DUPLICATE KEY UPDATE name=VALUES(name), phone=VALUES(phone), company_name=VALUES(company_name), gstin=VALUES(gstin)";
                $user_stmt = $conn->prepare($user_sql);
                
                if (!$user_stmt) {
                    throw new Exception("User prepare failed: " . $conn->error);
                }
                
                $user_stmt->bind_param("sssss", $name, $email, $phone, $business_name, $gstin);
                
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
            
            // Insert GST Return data
            $sql = "INSERT INTO gst_returns (
                        user_id, user_name, user_email, user_phone, gstin, return_type, return_period, financial_year,
                        total_sales, total_purchases, output_tax, input_tax_credit, tax_payable,
                        notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("GST Return prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssssssddddds", 
                $user_id, $name, $email, $phone, $gstin, $return_type, $return_period, $financial_year,
                $total_sales, $total_purchases, $output_tax, $input_tax_credit, $tax_payable,
                $notes
            );
            
            if ($stmt->execute()) {
                $gst_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) 
                           VALUES (?, 'GST_RETURN_FILED', 'gst_returns', ?, 'GST Return filed')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ii", $user_id, $gst_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                $success_message = "GST Return filed successfully! Reference ID: GST" . str_pad($gst_id, 6, '0', STR_PAD_LEFT);
                
                header("Location: " . $_SERVER['PHP_SELF'] . "?success=1&ref=" . $gst_id);
                exit();
            } else {
                throw new Exception("Error executing GST Return insert: " . $stmt->error);
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
    $success_message = "GST Return filed successfully! Reference ID: GST" . $ref_id;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Returns Filing - Anugrah Accounting</title>
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
        
        .form-container { padding: 40px; }
        
        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #0f172a;
            margin: 32px 0 24px 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .section-title:first-of-type { margin-top: 0; }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(135deg, #10b981, #059669);
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
            border-color: #10b981;
            box-shadow: 0 0 0 4px rgba(16, 185, 129, 0.1);
        }
        
        input::placeholder, textarea::placeholder { color: #94a3b8; }
        
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
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
            width: 100%;
            margin-top: 32px;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #059669 0%, #047857 100%);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(16, 185, 129, 0.4);
        }
        
        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            margin-top: 16px;
            width: 100%;
        }
        
        .btn-secondary:hover { background: #e2e8f0; }
        
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
        
        .input-group { position: relative; }
        
        .input-prefix {
            position: absolute;
            left: 18px;
            top: 50%;
            transform: translateY(-50%);
            color: #64748b;
            font-weight: 600;
        }
        
        .input-group input { padding-left: 42px; }
        
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
            border-left: 4px solid #10b981;
            padding: 12px 16px;
            border-radius: 6px;
            margin-top: 8px;
            font-size: 0.8125rem;
            color: #475569;
            line-height: 1.5;
        }
        
        .inquiry-section {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 2px solid #38bdf8;
            border-radius: 14px;
            padding: 28px;
            margin: 32px 0;
        }
        
        .inquiry-section h3 {
            color: #0369a1;
            margin-bottom: 16px;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .divider {
            height: 2px;
            background: linear-gradient(90deg, transparent, #e2e8f0, transparent);
            margin: 32px 0;
        }
        
        @media (max-width: 768px) {
            .form-container { padding: 32px 24px; }
            .header { padding: 40px 24px; }
            .header h1 { font-size: 2rem; }
            .row { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>📊 GST Returns Filing</h1>
                <p>Simplified and accurate GST return filing for your business</p>
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
                    Keep ready: GSTIN, Sales Invoices, Purchase Invoices, GSTR-2A, and Form 26AS for accurate filing.
                </div>
            </div>
            
            <form method="POST" action="" id="gstForm">
                <div class="section-title">Business Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Contact Person Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required placeholder="Full name of authorized person" 
                               value="<?php echo isset($logged_in_user['name']) ? htmlspecialchars($logged_in_user['name']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required placeholder="your.email@example.com"
                               value="<?php echo isset($logged_in_user['email']) ? htmlspecialchars($logged_in_user['email']) : ''; ?>">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" required placeholder="10-digit mobile number" pattern="[0-9]{10}" maxlength="10"
                               value="<?php echo isset($logged_in_user['phone']) ? htmlspecialchars($logged_in_user['phone']) : ''; ?>">
                    </div>
                    
                    <div class="form-group">
                        <label for="business_name">Business/Trade Name <span class="required">*</span></label>
                        <input type="text" name="business_name" id="business_name" required placeholder="As per GST Registration"
                               value="<?php echo isset($logged_in_user['company_name']) ? htmlspecialchars($logged_in_user['company_name']) : ''; ?>">
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="gstin">GSTIN <span class="required">*</span></label>
                    <input type="text" name="gstin" id="gstin" required placeholder="22AAAAA0000A1Z5" maxlength="15" style="text-transform: uppercase;"
                           value="<?php echo isset($logged_in_user['gstin']) ? htmlspecialchars($logged_in_user['gstin']) : ''; ?>">
                    <div class="info-box">15 characters: 2 state code + 10 PAN + 1 entity + 1 Z + 1 checksum</div>
                </div>
                
                <div class="form-group">
                    <label for="business_address">Principal Place of Business <span class="required">*</span></label>
                    <textarea name="business_address" id="business_address" required placeholder="Enter complete business address with PIN code"></textarea>
                </div>
                
                <div class="section-title">Return Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="return_type">Return Type <span class="required">*</span></label>
                        <select name="return_type" id="return_type" required>
                            <option value="">-- Select Return Type --</option>
                            <option value="GSTR-1">GSTR-1 (Outward Supplies)</option>
                            <option value="GSTR-3B">GSTR-3B (Monthly Return)</option>
                            <option value="GSTR-4">GSTR-4 (Composition)</option>
                            <option value="GSTR-9">GSTR-9 (Annual Return)</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="financial_year">Financial Year <span class="required">*</span></label>
                        <select name="financial_year" id="financial_year" required>
                            <option value="">-- Select FY --</option>
                            <option value="2023-24">2023-24</option>
                            <option value="2024-25">2024-25</option>
                            <option value="2025-26">2025-26</option>
                        </select>
                    </div>
                </div>
                
                <div class="section-title">Sales & Purchase Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="total_sales">Total Sales (₹) <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-prefix">₹</span>
                            <input type="number" name="total_sales" id="total_sales" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="total_purchases">Total Purchases (₹) <span class="required">*</span></label>
                        <div class="input-group">
                            <span class="input-prefix">₹</span>
                            <input type="number" name="total_purchases" id="total_purchases" step="0.01" min="0" placeholder="0.00" required>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="exempt_sales">Exempt Sales (₹)</label>
                        <div class="input-group">
                            <span class="input-prefix">₹</span>
                            <input type="number" name="exempt_sales" id="exempt_sales" step="0.01" min="0" placeholder="0.00" value="0">
                        </div>
                        <div class="info-box">Sales exempt from GST</div>
                    </div>
                    
                    <div class="form-group">
                        <label for="zero_rated_sales">Zero Rated Sales (₹)</label>
                        <div class="input-group">
                            <span class="input-prefix">₹</span>
                            <input type="number" name="zero_rated_sales" id="zero_rated_sales" step="0.01" min="0" placeholder="0.00" value="0">
                        </div>
                        <div class="info-box">Exports & SEZ supplies</div>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Notes</label>
                    <textarea name="notes" id="notes" placeholder="Any additional information (optional)"></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">✓ Submit GST Return</button>
            </form>
            
            <div class="divider"></div>
            
            <!-- Inquiry Section -->
            <div class="inquiry-section">
                <h3>📧 Need More Information?</h3>
                <p style="margin-bottom: 20px; color: #475569; line-height: 1.6;">
                    Not sure about the details? Click below to send us an email inquiry directly, and our experts will help you with the GST return filing process.
                </p>
                
                <a href="mailto:anugrah0369@gmail.com?subject=GST Returns Inquiry - Need Information&body=Hello Anugrah Accounting Team,%0D%0A%0D%0AI need assistance with GST Returns filing.%0D%0A%0D%0AMy Details:%0D%0AName: <?php echo isset($logged_in_user['name']) ? urlencode($logged_in_user['name']) : '[Your Name]'; ?>%0D%0AEmail: <?php echo isset($logged_in_user['email']) ? urlencode($logged_in_user['email']) : '[Your Email]'; ?>%0D%0APhone: <?php echo isset($logged_in_user['phone']) ? urlencode($logged_in_user['phone']) : '[Your Phone]'; ?>%0D%0A%0D%0AMy Question:%0D%0A[Please describe what information you need about GST returns]%0D%0A%0D%0AThank you!" 
                   class="btn btn-secondary" 
                   style="display: inline-block; text-decoration: none; text-align: center;">
                    📨 Send Email Inquiry
                </a>
                
                <div style="margin-top: 20px; padding: 12px; background: #fff; border: 1px solid #cbd5e1; border-radius: 8px; font-size: 0.875rem; color: #64748b;">
                    <strong>📧 Email:</strong> anugrah0369@gmail.com<br>
                    <strong>📞 Phone:</strong> 8000687342
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.getElementById('gstin').addEventListener('input', function(e) {
            this.value = this.value.toUpperCase();
        });
        
        document.getElementById('gstForm').addEventListener('submit', function(e) {
            const gstin = document.getElementById('gstin').value;
            const gstinRegex = /^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/;
            
            if (!gstinRegex.test(gstin)) {
                e.preventDefault();
                alert('Please enter a valid GSTIN (e.g., 22AAAAA0000A1Z5)');
                return false;
            }
        });
    </script>
</body>
</html>