<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$message = '';
$message_type = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_settings'])) {
        $smtp_username = trim($_POST['smtp_username']);
        $smtp_password = trim($_POST['smtp_password']);
        $from_email = trim($_POST['from_email']);
        $from_name = trim($_POST['from_name']);
        $smtp_port = intval($_POST['smtp_port']);
        
        if (empty($smtp_username) || empty($smtp_password)) {
            $message = 'Gmail address and App Password are required';
            $message_type = 'error';
        } else {
            // Deactivate all existing settings
            $conn->query("UPDATE email_settings SET is_active = 0");
            
            // Insert new settings
            $stmt = $conn->prepare(
                "INSERT INTO email_settings (smtp_username, smtp_password, from_email, from_name, smtp_port, is_active, updated_by) 
                 VALUES (?, ?, ?, ?, ?, 1, ?)"
            );
            
            $admin_id = $_SESSION['admin_id'];
            $stmt->bind_param("sssiii", $smtp_username, $smtp_password, $from_email, $from_name, $smtp_port, $admin_id);
            
            if ($stmt->execute()) {
                $message = 'Email settings updated successfully!';
                $message_type = 'success';
            } else {
                $message = 'Failed to update settings: ' . $conn->error;
                $message_type = 'error';
            }
            $stmt->close();
        }
    } elseif (isset($_POST['test_email'])) {
        $test_email = trim($_POST['test_email']);
        
        if (empty($test_email)) {
            $message = 'Please enter a test email address';
            $message_type = 'error';
        } else {
            require_once 'send_otp_email_dynamic.php';
            $test_otp = sprintf("%06d", mt_rand(100000, 999999));
            
            if (sendOTPEmailDynamic($test_email, "Test User", $test_otp)) {
                $message = "Test email sent successfully to $test_email! Check your inbox. (Test OTP: $test_otp)";
                $message_type = 'success';
            } else {
                $message = 'Failed to send test email. Please check your settings.';
                $message_type = 'error';
            }
        }
    }
}

// Get current settings
$current_settings = array();
$stmt = $conn->prepare("SELECT * FROM email_settings WHERE is_active = 1 LIMIT 1");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $current_settings = $result->fetch_assoc();
}
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Settings - PCS Admin</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            padding: 20px;
        }
        .container { max-width: 900px; margin: 0 auto; }
        .header {
            background: linear-gradient(135deg, #0d1e42, #036fc7);
            color: white;
            padding: 30px;
            border-radius: 15px 15px 0 0;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .header h1 { font-size: 1.8rem; margin-bottom: 5px; }
        .header p { opacity: 0.9; }
        .card {
            background: white;
            border-radius: 0 0 15px 15px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            padding: 30px;
        }
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideDown 0.3s ease;
        }
        @keyframes slideDown {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .alert-error { background: #fee; color: #c33; border: 2px solid #fcc; }
        .alert-success { background: #d4edda; color: #155724; border: 2px solid #c3e6cb; }
        .info-box {
            background: #e3f2fd;
            border-left: 4px solid #2196F3;
            padding: 20px;
            margin-bottom: 30px;
            border-radius: 5px;
        }
        .info-box h3 {
            color: #1976D2;
            margin-bottom: 10px;
            font-size: 1rem;
        }
        .info-box ol {
            margin-left: 20px;
            color: #333;
            line-height: 1.8;
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }
        .form-group { margin-bottom: 20px; }
        .form-group.full-width { grid-column: 1 / -1; }
        .form-label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
            font-size: 0.9rem;
        }
        .form-control {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s;
        }
        .form-control:focus {
            outline: none;
            border-color: #036fc7;
            box-shadow: 0 0 0 3px rgba(3, 111, 199, 0.1);
        }
        .form-control:disabled {
            background: #f5f5f5;
            cursor: not-allowed;
        }
        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #0d1e42, #036fc7);
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(3, 111, 199, 0.3);
        }
        .btn-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn-secondary:hover { background: #5a6268; }
        .section-divider {
            border: none;
            border-top: 2px dashed #e0e0e0;
            margin: 30px 0;
        }
        .test-section {
            background: #fff3cd;
            border: 2px solid #ffc107;
            padding: 20px;
            border-radius: 10px;
            margin-top: 30px;
        }
        .test-section h3 {
            color: #856404;
            margin-bottom: 15px;
            font-size: 1.1rem;
        }
        .button-group {
            display: flex;
            gap: 15px;
            margin-top: 20px;
        }
        .back-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: #036fc7;
            text-decoration: none;
            font-weight: 600;
            margin-top: 20px;
        }
        .back-link:hover { text-decoration: underline; }
        .password-toggle {
            position: relative;
        }
        .toggle-icon {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #999;
        }
        .toggle-icon:hover { color: #036fc7; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; }
            .button-group { flex-direction: column; }
            .btn { width: 100%; justify-content: center; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-envelope-open-text"></i> Email Settings</h1>
            <p>Configure SMTP settings for OTP and notifications</p>
        </div>
        
        <div class="card">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'error' ? 'exclamation-circle' : 'check-circle'; ?>"></i>
                    <span><?php echo htmlspecialchars($message); ?></span>
                </div>
            <?php endif; ?>

            <div class="info-box">
                <h3><i class="fas fa-info-circle"></i> How to Get Gmail App Password:</h3>
                <ol>
                    <li>Go to your Google Account: <a href="https://myaccount.google.com/security" target="_blank">myaccount.google.com/security</a></li>
                    <li>Enable <strong>2-Step Verification</strong> (if not already enabled)</li>
                    <li>Go to: <a href="https://myaccount.google.com/apppasswords" target="_blank">myaccount.google.com/apppasswords</a></li>
                    <li>Enter app name (e.g., "PCS Admin") and click <strong>Create</strong></li>
                    <li>Copy the 16-character password (remove spaces)</li>
                    <li>Paste it below</li>
                </ol>
            </div>

            <form method="POST" action="">
                <h3 style="margin-bottom: 20px; color: #333;">
                    <i class="fas fa-cog"></i> SMTP Configuration
                </h3>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Gmail Address *
                        </label>
                        <input type="email" name="smtp_username" class="form-control" 
                               value="<?php echo isset($current_settings['smtp_username']) ? htmlspecialchars($current_settings['smtp_username']) : ''; ?>" 
                               placeholder="your-email@gmail.com" required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-key"></i> Gmail App Password *
                        </label>
                        <div class="password-toggle">
                            <input type="password" id="appPassword" name="smtp_password" class="form-control" 
                                   value="<?php echo isset($current_settings['smtp_password']) ? htmlspecialchars($current_settings['smtp_password']) : ''; ?>" 
                                   placeholder="16-character app password" required>
                            <i class="fas fa-eye toggle-icon" onclick="togglePassword()"></i>
                        </div>
                        <small style="color: #666; font-size: 0.85rem;">Remove all spaces from app password</small>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-at"></i> From Email
                        </label>
                        <input type="email" name="from_email" class="form-control" 
                               value="<?php echo isset($current_settings['from_email']) ? htmlspecialchars($current_settings['from_email']) : 'noreply@pcs-cutting.com'; ?>" 
                               placeholder="noreply@pcs-cutting.com">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> From Name
                        </label>
                        <input type="text" name="from_name" class="form-control" 
                               value="<?php echo isset($current_settings['from_name']) ? htmlspecialchars($current_settings['from_name']) : 'PCS Admin Panel'; ?>" 
                               placeholder="PCS Admin Panel">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">
                        <i class="fas fa-network-wired"></i> SMTP Port
                    </label>
                    <select name="smtp_port" class="form-control">
                        <option value="587" <?php echo (isset($current_settings['smtp_port']) && $current_settings['smtp_port'] == 587) ? 'selected' : ''; ?>>587 (TLS - Recommended)</option>
                        <option value="465" <?php echo (isset($current_settings['smtp_port']) && $current_settings['smtp_port'] == 465) ? 'selected' : ''; ?>>465 (SSL)</option>
                    </select>
                    <small style="color: #666; font-size: 0.85rem;">Use 587 for most cases. Try 465 if 587 doesn't work.</small>
                </div>

                <div class="button-group">
                    <button type="submit" name="update_settings" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save Settings
                    </button>
                </div>
            </form>

            <hr class="section-divider">

            <div class="test-section">
                <h3><i class="fas fa-vial"></i> Test Email Configuration</h3>
                <p style="color: #856404; margin-bottom: 15px;">Send a test OTP email to verify your settings are working correctly.</p>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-inbox"></i> Test Email Address
                        </label>
                        <input type="email" name="test_email" class="form-control" 
                               value="<?php echo isset($current_settings['smtp_username']) ? htmlspecialchars($current_settings['smtp_username']) : ''; ?>" 
                               placeholder="Enter email to receive test OTP" required>
                    </div>
                    
                    <button type="submit" name="test_email" class="btn btn-success">
                        <i class="fas fa-paper-plane"></i> Send Test Email
                    </button>
                </form>
            </div>

            <a href="index.php" class="back-link">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <script>
        function togglePassword() {
            const field = document.getElementById('appPassword');
            const icon = document.querySelector('.toggle-icon');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                field.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }

        // Auto-remove spaces from app password
        document.querySelector('input[name="smtp_password"]').addEventListener('input', function(e) {
            this.value = this.value.replace(/\s/g, '');
        });
    </script>
</body>
</html>