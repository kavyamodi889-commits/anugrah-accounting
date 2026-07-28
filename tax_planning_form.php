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
    
    // Tax planning details
    $financial_year = trim($_POST['financial_year']);
    $assessment_year = trim($_POST['assessment_year']);
    $consultation_date = trim($_POST['consultation_date']);
    $message = trim($_POST['message']);
    
    // Validations
    if (empty($name)) $errors[] = "Name is required";
    if (!$email) $errors[] = "Valid email is required";
    if (!preg_match('/^[0-9]{10}$/', $phone)) $errors[] = "Valid 10-digit phone number required";
    if (empty($financial_year)) $errors[] = "Financial year is required";
    if (empty($assessment_year)) $errors[] = "Assessment year is required";
    
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
                // Insert or update user
                $user_sql = "INSERT INTO users (name, email, phone) VALUES (?, ?, ?)
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
            
            // Insert tax planning data with user details
            $sql = "INSERT INTO tax_planning (
                        user_id, user_name, user_email, user_phone, 
                        financial_year, assessment_year, consultation_date, notes, status
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending')";
            
            $stmt = $conn->prepare($sql);
            
            if (!$stmt) {
                throw new Exception("Tax planning prepare failed: " . $conn->error);
            }
            
            $stmt->bind_param("isssssss", 
                $db_user_id, $name, $email, $phone,
                $financial_year, $assessment_year, $consultation_date, $message
            );
            
            if ($stmt->execute()) {
                $planning_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, action, entity_type, entity_id, description) 
                           VALUES (?, 'TAX_PLANNING', 'tax_planning', ?, 'Tax planning consultation requested')";
                $log_stmt = $conn->prepare($log_sql);
                $log_stmt->bind_param("ii", $db_user_id, $planning_id);
                $log_stmt->execute();
                $log_stmt->close();
                
                mysqli_commit($conn);
                mysqli_autocommit($conn, TRUE);
                
                $success_message = "Tax Planning consultation request submitted successfully! Reference ID: TP" . str_pad($planning_id, 6, '0', STR_PAD_LEFT) . ". Our team will contact you within 24 hours.";
            } else {
                throw new Exception("Error executing tax planning insert: " . $stmt->error);
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
    <title>Tax Planning Consultation - Anugrah Accounting</title>
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
        
        .contact-info {
            background: linear-gradient(135deg, #ecfdf5 0%, #d1fae5 100%);
            border: 2px solid #6ee7b7;
            border-radius: 14px;
            padding: 28px;
            margin: 24px 0;
        }
        
        .contact-info h3 {
            color: #065f46;
            margin-bottom: 16px;
            font-size: 1.25rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .contact-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 0;
            color: #0f172a;
        }
        
        .contact-icon {
            font-size: 1.5rem;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.2);
        }
        
        .contact-details {
            flex: 1;
        }
        
        .contact-label {
            font-size: 0.875rem;
            color: #64748b;
            font-weight: 500;
        }
        
        .contact-value {
            font-size: 1.0625rem;
            color: #0f172a;
            font-weight: 600;
        }
        
        textarea { resize: vertical; min-height: 120px; line-height: 1.6; }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 45px;
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
                <h1>💰 Tax Planning Consultation</h1>
                <p>Strategic tax planning to maximize your savings and achieve financial goals</p>
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
                    Our expert tax consultants will analyze your financial profile and suggest personalized tax-saving strategies. Fill in your details below and we'll contact you within 24 hours!
                </div>
            </div>
            
            <form method="POST" action="" id="taxPlanningForm">
                <!-- Contact Information -->
                <div class="section-title">Your Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['name']) : ''; ?>"
                               placeholder="Enter your full name">
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
                        <label for="consultation_date">Preferred Consultation Date</label>
                        <input type="date" name="consultation_date" id="consultation_date" 
                               min="<?php echo date('Y-m-d'); ?>">
                        <div class="info-box">Select a date when you'd like our team to contact you</div>
                    </div>
                </div>
                
                <!-- Tax Planning Details -->
                <div class="section-title">Tax Planning Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="financial_year">Financial Year <span class="required">*</span></label>
                        <select name="financial_year" id="financial_year" required>
                            <option value="">-- Select FY --</option>
                            <option value="2024-25" selected>2024-25</option>
                            <option value="2025-26">2025-26</option>
                            <option value="2026-27">2026-27</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="assessment_year">Assessment Year <span class="required">*</span></label>
                        <select name="assessment_year" id="assessment_year" required>
                            <option value="">-- Select AY --</option>
                            <option value="2025-26" selected>2025-26</option>
                            <option value="2026-27">2026-27</option>
                            <option value="2027-28">2027-28</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="message">Your Message / Requirements</label>
                    <textarea name="message" id="message" 
                              placeholder="Please describe your tax planning needs, financial goals, or specific questions you'd like to discuss...

For example:
- Current income sources
- Investment preferences
- Tax-saving goals
- Specific concerns or questions"></textarea>
                    <div class="info-box">Provide as much detail as possible to help our consultants prepare for your session</div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    ✓ Request Tax Planning Consultation
                </button>
            </form>
            
            <!-- Contact Information -->
            <div class="contact-info">
                <h3>📞 Direct Contact Information</h3>
                
                <div class="contact-item">
                    <div class="contact-icon">📧</div>
                    <div class="contact-details">
                        <div class="contact-label">Email Us</div>
                        <div class="contact-value">anugrah0369@gmail.com</div>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">📱</div>
                    <div class="contact-details">
                        <div class="contact-label">Call / WhatsApp</div>
                        <div class="contact-value">+91 8000687342</div>
                    </div>
                </div>
                
                <div class="contact-item">
                    <div class="contact-icon">🕐</div>
                    <div class="contact-details">
                        <div class="contact-label">Working Hours</div>
                        <div class="contact-value">Mon - Sat: 10:00 AM - 6:00 PM</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Auto-sync FY and AY
        document.getElementById('financial_year').addEventListener('change', function() {
            const fy = this.value;
            const aySelect = document.getElementById('assessment_year');
            
            if (fy === '2024-25') aySelect.value = '2025-26';
            else if (fy === '2025-26') aySelect.value = '2026-27';
            else if (fy === '2026-27') aySelect.value = '2027-28';
        });
        
        // Form validation
        document.getElementById('taxPlanningForm').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            
            if (!/^[0-9]{10}$/.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number');
                return false;
            }
        });
        
        // If email is disabled, re-enable before submission
        <?php if ($user_data): ?>
        document.getElementById('taxPlanningForm').addEventListener('submit', function() {
            document.getElementById('email').disabled = false;
        });
        <?php endif; ?>
    </script>
</body>
</html>