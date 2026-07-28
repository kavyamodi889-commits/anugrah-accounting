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
    
    // Get form data
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $company_name = trim($_POST['company_name']);
    $service_type = trim($_POST['service_type']);
    $period_from = trim($_POST['period_from']);
    $period_to = trim($_POST['period_to']);
    $frequency = trim($_POST['frequency']);
    $business_type = trim($_POST['business_type']);
    $number_of_transactions = filter_var($_POST['number_of_transactions'], FILTER_VALIDATE_INT);
    $software_used = trim($_POST['software_used']);
    $notes = trim($_POST['notes']);
    $urgency = trim($_POST['urgency']);
    
    // Validation
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format";
    }
    
    if (strtotime($period_from) > strtotime($period_to)) {
        $errors[] = "Period 'From' date cannot be after 'To' date";
    }
    
    if (empty($errors)) {
        // Get user_id if logged in
        $db_user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        // Insert into database (without amount field)
        $sql = "INSERT INTO accounting_services (user_id, user_name, user_email, user_phone, company_name, 
                service_type, period_from, period_to, frequency, business_type, 
                number_of_transactions, software_used, notes, urgency, status, 
                created_at, submitted_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW(), NOW())";
        
        $stmt = $conn->prepare($sql);
        
        if ($stmt === false) {
            $error_message = "Database error: " . $conn->error;
        } else {
            // Bind parameters: i=integer, s=string
            // Total 14 parameters: user_id, user_name, user_email, user_phone, company_name, service_type, 
            // period_from, period_to, frequency, business_type, number_of_transactions, software_used, notes, urgency
            $stmt->bind_param("isssssssssisss", 
                $db_user_id,                 // i - integer (user_id)
                $name,                       // s - string (user_name)
                $email,                      // s - string (user_email)
                $phone,                      // s - string (user_phone)
                $company_name,               // s - string (company_name)
                $service_type,               // s - string (service_type)
                $period_from,                // s - string (period_from - date)
                $period_to,                  // s - string (period_to - date)
                $frequency,                  // s - string (frequency)
                $business_type,              // s - string (business_type)
                $number_of_transactions,     // i - integer (number_of_transactions/turnover)
                $software_used,              // s - string (software_used)
                $notes,                      // s - string (notes)
                $urgency                     // s - string (urgency)
            );
            
            if ($stmt->execute()) {
                $success_message = "Accounting Service request submitted successfully! We'll contact you within 24 hours.";
                
                $last_id = $stmt->insert_id;
                
                // Log activity
                $log_sql = "INSERT INTO activity_log (user_id, user_email, action, entity_type, entity_id, description, 
                           ip_address, user_agent) 
                           VALUES (?, ?, 'ACCOUNTING_SERVICE', 'accounting_services', ?, 
                           'Accounting service requested', ?, ?)";
                $log_stmt = $conn->prepare($log_sql);
                if ($log_stmt) {
                    $ip = $_SERVER['REMOTE_ADDR'];
                    $user_agent = $_SERVER['HTTP_USER_AGENT'];
                    $log_stmt->bind_param("isiss", $db_user_id, $email, $last_id, $ip, $user_agent);
                    $log_stmt->execute();
                }
            } else {
                $error_message = "Error submitting request: " . $stmt->error;
            }
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
    <title>Accounting Services - Anugrah Accounting</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
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
        
        .header-content {
            position: relative;
            z-index: 1;
        }
        
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
        
        .form-container {
            padding: 50px;
        }
        
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
        
        .section-title:first-child {
            margin-top: 0;
        }
        
        .section-title::before {
            content: '';
            width: 4px;
            height: 28px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 2px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #475569;
            font-weight: 500;
            font-size: 0.875rem;
            letter-spacing: 0.01em;
        }
        
        label .required {
            color: #dc2626;
            margin-left: 2px;
        }
        
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
        
        input::placeholder, textarea::placeholder {
            color: #94a3b8;
        }
        
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
        
        .btn:active {
            transform: translateY(0);
        }
        
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
        
        .alert::before {
            font-size: 1.25rem;
            flex-shrink: 0;
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
        
        .service-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 12px;
        }
        
        .service-card {
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
        
        .service-card::before {
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
        
        .service-card:hover {
            border-color: #3b82f6;
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.2);
        }
        
        .service-card:hover::before {
            transform: scaleX(1);
        }
        
        .service-card.selected {
            border-color: #3b82f6;
            background: linear-gradient(135deg, #eff6ff 0%, #f5f3ff 100%);
        }
        
        .service-card.selected::before {
            transform: scaleX(1);
        }
        
        .service-icon {
            font-size: 2.5rem;
            margin-bottom: 12px;
        }
        
        .service-card .title {
            font-weight: 600;
            font-size: 1rem;
            margin-top: 8px;
            color: #0f172a;
        }
        
        .service-card.selected .title {
            color: #1e40af;
        }
        
        .service-card .description {
            font-size: 0.75rem;
            color: #64748b;
            margin-top: 6px;
        }
        
        .price-estimate {
            background: linear-gradient(135deg, #f0f9ff 0%, #faf5ff 100%);
            padding: 28px;
            border-radius: 14px;
            margin: 24px 0;
            border: 2px solid #e0e7ff;
        }
        
        .price-estimate h3 {
            color: #1e40af;
            margin-bottom: 16px;
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        .price-estimate .amount {
            font-size: 2.5rem;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-weight: bold;
            margin-bottom: 8px;
        }
        
        .price-estimate small {
            color: #64748b;
            font-size: 0.8125rem;
        }
        
        .price-breakdown {
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #e0e7ff;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-size: 0.875rem;
            color: #475569;
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        
        select {
            cursor: pointer;
            appearance: none;
            background-image: url("data:image/svg+xml,%3Csvg width='12' height='8' viewBox='0 0 12 8' fill='none' xmlns='http://www.w3.org/2000/svg'%3E%3Cpath d='M1 1.5L6 6.5L11 1.5' stroke='%2364748b' stroke-width='1.5' stroke-linecap='round' stroke-linejoin='round'/%3E%3C/svg%3E");
            background-repeat: no-repeat;
            background-position: right 18px center;
            padding-right: 45px;
        }
        
        .urgency-badges {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 12px;
            margin-top: 12px;
        }
        
        .urgency-badge {
            padding: 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.2s ease;
            font-weight: 500;
        }
        
        .urgency-badge:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }
        
        .urgency-badge.selected {
            border-color: #3b82f6;
            background: #eff6ff;
            color: #1e40af;
        }
        
        .urgency-badge.normal { border-color: #22c55e; }
        .urgency-badge.normal.selected { background: #f0fdf4; border-color: #22c55e; color: #166534; }
        
        .urgency-badge.urgent { border-color: #f59e0b; }
        .urgency-badge.urgent.selected { background: #fffbeb; border-color: #f59e0b; color: #92400e; }
        
        .urgency-badge.critical { border-color: #ef4444; }
        .urgency-badge.critical.selected { background: #fef2f2; border-color: #ef4444; color: #991b1b; }
        
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
        
        .feature-highlight::before {
            content: '⭐';
            font-size: 1.5rem;
        }
        
        .feature-highlight-text {
            font-size: 0.9375rem;
            color: #78350f;
            font-weight: 500;
        }
        
        @media (max-width: 768px) {
            .form-container {
                padding: 32px 24px;
            }
            
            .header {
                padding: 40px 24px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
            
            .row {
                grid-template-columns: 1fr;
            }
            
            .service-cards {
                grid-template-columns: 1fr;
            }
            
            .urgency-badges {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="header-content">
                <h1>✨ Accounting Services</h1>
                <p>Professional bookkeeping and financial management tailored for your business</p>
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
                    Get a personalized quote instantly! Our team will review and contact you within 24 hours.
                </div>
            </div>
            
            <form method="POST" action="" id="accountingForm">
                <!-- Contact Information -->
                <div class="section-title">Contact Information</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="name">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" id="name" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['name']) : ''; ?>"
                               placeholder="John Doe">
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address <span class="required">*</span></label>
                        <input type="email" name="email" id="email" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['email']) : ''; ?>"
                               <?php echo $user_data ? 'disabled' : ''; ?>
                               placeholder="john@example.com">
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="phone">Phone Number <span class="required">*</span></label>
                        <input type="tel" name="phone" id="phone" required 
                               value="<?php echo $user_data ? htmlspecialchars($user_data['phone']) : ''; ?>"
                               placeholder="+91 98765 43210">
                    </div>
                    
                    <div class="form-group">
                        <label for="company_name">Company Name <span class="required">*</span></label>
                        <input type="text" name="company_name" id="company_name" required 
                               placeholder="ABC Enterprises">
                    </div>
                </div>
                
                <!-- Service Type -->
                <div class="section-title">Choose Your Service</div>
                
                <div class="form-group">
                    <label>Select Service Type <span class="required">*</span></label>
                    <div class="service-cards">
                        <div class="service-card" data-service="Bookkeeping">
                            <div class="service-icon">📊</div>
                            <div class="title">Bookkeeping</div>
                            <div class="description">Daily transaction recording</div>
                        </div>
                        <div class="service-card" data-service="Payroll">
                            <div class="service-icon">💰</div>
                            <div class="title">Payroll</div>
                            <div class="description">Salary & tax management</div>
                        </div>
                        <div class="service-card" data-service="Financial Statements">
                            <div class="service-icon">📈</div>
                            <div class="title">Financial Statements</div>
                            <div class="description">P&L & Balance Sheet</div>
                        </div>
                        <div class="service-card" data-service="Audit Support">
                            <div class="service-icon">🔍</div>
                            <div class="title">Audit Support</div>
                            <div class="description">Audit preparation</div>
                        </div>
                        <div class="service-card" data-service="Tax Filing">
                            <div class="service-icon">📑</div>
                            <div class="title">Tax Filing</div>
                            <div class="description">GST & Income Tax</div>
                        </div>
                        <div class="service-card" data-service="CFO Services">
                            <div class="service-icon">👔</div>
                            <div class="title">CFO Services</div>
                            <div class="description">Strategic planning</div>
                        </div>
                    </div>
                    <input type="hidden" name="service_type" id="service_type" required>
                </div>
                
                <div class="form-group">
                    <label>Service Urgency <span class="required">*</span></label>
                    <div class="urgency-badges">
                        <div class="urgency-badge normal selected" data-urgency="Normal">
                            <div style="font-size: 1.5rem; margin-bottom: 4px;">🕐</div>
                            <div>Normal</div>
                            <small style="font-size: 0.75rem; color: #64748b;">3-5 days</small>
                        </div>
                        <div class="urgency-badge urgent" data-urgency="Urgent">
                            <div style="font-size: 1.5rem; margin-bottom: 4px;">⚡</div>
                            <div>Urgent</div>
                            <small style="font-size: 0.75rem; color: #64748b;">1-2 days</small>
                        </div>
                        <div class="urgency-badge critical" data-urgency="Critical">
                            <div style="font-size: 1.5rem; margin-bottom: 4px;">🚨</div>
                            <div>Critical</div>
                            <small style="font-size: 0.75rem; color: #64748b;">Same day</small>
                        </div>
                    </div>
                    <input type="hidden" name="urgency" id="urgency" value="Normal" required>
                </div>
                
                <!-- Business Details -->
                <div class="section-title">Business Details</div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="period_from">Service Period From <span class="required">*</span></label>
                        <input type="date" name="period_from" id="period_from" required>
                    </div>
                    
                    <div class="form-group">
                        <label for="period_to">Service Period To <span class="required">*</span></label>
                        <input type="date" name="period_to" id="period_to" required>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="frequency">Service Frequency <span class="required">*</span></label>
                        <select name="frequency" id="frequency" required>
                            <option value="">-- Select Frequency --</option>
                            <option value="Daily">Daily</option>
                            <option value="Weekly">Weekly</option>
                            <option value="Monthly">Monthly</option>
                            <option value="Quarterly">Quarterly</option>
                            <option value="Yearly">Yearly</option>
                            <option value="One-time">One-time</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="business_type">Business Type <span class="required">*</span></label>
                        <select name="business_type" id="business_type" required>
                            <option value="">-- Select Type --</option>
                            <option value="Sole Proprietorship">Sole Proprietorship</option>
                            <option value="Partnership">Partnership</option>
                            <option value="LLP">LLP</option>
                            <option value="Private Limited">Private Limited</option>
                            <option value="Public Limited">Public Limited</option>
                            <option value="Startup">Startup</option>
                            <option value="NGO/Trust">NGO/Trust</option>
                        </select>
                    </div>
                </div>
                
                <div class="row">
                    <div class="form-group">
                        <label for="number_of_transactions">Business Turnover (Annual)</label>
                        <input type="number" name="number_of_transactions" id="number_of_transactions" 
                               min="0" value="0" placeholder="e.g., 1000000">
                    </div>
                    
                    <div class="form-group">
                        <label for="software_used">Accounting Software</label>
                        <select name="software_used" id="software_used">
                            <option value="">-- Select Software --</option>
                            <option value="Tally">Tally</option>
                            <option value="QuickBooks">QuickBooks</option>
                            <option value="Zoho Books">Zoho Books</option>
                            <option value="Other">Other</option>
                            <option value="None">None (Need recommendation)</option>
                        </select>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="notes">Additional Requirements</label>
                    <textarea name="notes" id="notes" 
                              placeholder="Describe your specific accounting needs, special requirements, or any questions you have..."></textarea>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    Submit Request ✓
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Service card selection
        document.querySelectorAll('.service-card').forEach(card => {
            card.addEventListener('click', function() {
                document.querySelectorAll('.service-card').forEach(c => c.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('service_type').value = this.dataset.service;
            });
        });
        
        // Urgency badge selection
        document.querySelectorAll('.urgency-badge').forEach(badge => {
            badge.addEventListener('click', function() {
                document.querySelectorAll('.urgency-badge').forEach(b => b.classList.remove('selected'));
                this.classList.add('selected');
                document.getElementById('urgency').value = this.dataset.urgency;
            });
        });
        
        // Auto-calculate when fields change
        document.getElementById('number_of_transactions').addEventListener('input', function() {
            // Input listener for turnover field (no calculation needed now)
        });
        document.getElementById('frequency').addEventListener('change', function() {
            autoSetDates(this.value);
        });
        
        // Function to automatically set dates based on frequency
        function autoSetDates(frequency) {
            const today = new Date();
            const periodFrom = document.getElementById('period_from');
            const periodTo = document.getElementById('period_to');
            
            // Set from date to today
            const fromDate = new Date(today);
            periodFrom.value = fromDate.toISOString().split('T')[0];
            
            // Calculate to date based on frequency
            let toDate = new Date(today);
            
            switch(frequency) {
                case 'Daily':
                    toDate.setDate(toDate.getDate() + 30); // 30 days
                    break;
                case 'Weekly':
                    toDate.setMonth(toDate.getMonth() + 3); // 3 months
                    break;
                case 'Monthly':
                    toDate.setFullYear(toDate.getFullYear() + 1); // 1 year
                    break;
                case 'Quarterly':
                    toDate.setFullYear(toDate.getFullYear() + 1); // 1 year
                    break;
                case 'Yearly':
                    toDate.setFullYear(toDate.getFullYear() + 1); // 1 year
                    break;
                case 'One-time':
                    toDate.setMonth(toDate.getMonth() + 1); // 1 month
                    break;
                default:
                    toDate.setFullYear(toDate.getFullYear() + 1); // Default 1 year
            }
            
            periodTo.value = toDate.toISOString().split('T')[0];
        }
        
        // Form submission validation
        document.getElementById('accountingForm').addEventListener('submit', function(e) {
            const serviceType = document.getElementById('service_type').value;
            if (!serviceType) {
                e.preventDefault();
                alert('Please select a service type');
                return false;
            }
            
            const fromDate = new Date(document.getElementById('period_from').value);
            const toDate = new Date(document.getElementById('period_to').value);
            
            if (fromDate > toDate) {
                e.preventDefault();
                alert('Period "From" date cannot be after "To" date');
                return false;
            }
        });
        
        // If email is disabled, we need to include it in form submission
        <?php if ($user_data): ?>
        document.getElementById('accountingForm').addEventListener('submit', function() {
            // Re-enable email field before submission so its value is sent
            document.getElementById('email').disabled = false;
        });
        <?php endif; ?>
    </script>
</body>
</html>