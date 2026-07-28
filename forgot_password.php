<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/email.php';

$error_message = '';
$success_message = '';
$step = 1;

// Handle Step 1: Send OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['send_otp'])) {
    $email = trim($_POST['email']);
    
    if (empty($email)) {
        $error_message = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        $user = null;
        $user_type = 'user';
        
        // Search in users table first
        $stmt = $conn->prepare("SELECT id, email, name FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 1) {
            $user = $res->fetch_assoc();
            $user_type = 'user';
        } else {
            $stmt->close();
            // Search in admin_users table
            $stmt = $conn->prepare("SELECT id, email, full_name as name FROM admin_users WHERE email = ? AND is_active = 1");
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $res = $stmt->get_result();
            if ($res->num_rows === 1) {
                $user = $res->fetch_assoc();
                $user_type = 'admin';
            }
        }
        
        if ($user) {
            // Generate 6-digit OTP & expiration (10 min)
            $otp = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
            $expires = date('Y-m-d H:i:s', strtotime('+10 minutes'));
            
            // Mark existing active OTPs for this email as used
            $upd = $conn->prepare("UPDATE password_otps SET is_used = 1 WHERE email = ?");
            $upd->bind_param("s", $user['email']);
            $upd->execute();
            $upd->close();
            
            // Store new OTP in DB
            $ins = $conn->prepare("INSERT INTO password_otps (email, otp_code, expires_at) VALUES (?, ?, ?)");
            $ins->bind_param("sss", $user['email'], $otp, $expires);
            $ins->execute();
            $ins->close();
            
            $_SESSION['reset_email'] = $user['email'];
            $_SESSION['reset_user_id'] = $user['id'];
            $_SESSION['reset_user_type'] = $user_type;
            
            // Attempt email dispatch
            $sent = sendOTPEmail($user['email'], $otp, $user['name']);
            
            $masked = maskEmail($user['email']);
            if ($sent) {
                $success_message = "An OTP has been sent to " . $masked . ". Please check your inbox.";
            } else {
                $success_message = "OTP generated for " . $masked . ". (Note: Configure SMTP in includes/email.php to deliver emails).";
            }
            $step = 2;
        } else {
            // Generic message for security (don't disclose email existence)
            $success_message = "If that email address is registered, an OTP has been sent.";
            $step = 1;
        }
        
        if (isset($stmt) && $stmt) {
            $stmt->close();
        }
    }
}

// Handle Step 2: Verify OTP
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['verify_otp'])) {
    $entered_otp = trim($_POST['otp']);
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    
    if (empty($entered_otp)) {
        $error_message = 'Please enter the OTP code.';
        $step = 2;
    } elseif (empty($email)) {
        $error_message = 'Session expired. Please request a new OTP.';
        $step = 1;
    } else {
        // Check OTP in database
        $stmt = $conn->prepare("SELECT id FROM password_otps WHERE email = ? AND otp_code = ? AND is_used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1");
        $stmt->bind_param("ss", $email, $entered_otp);
        $stmt->execute();
        $res = $stmt->get_result();
        
        if ($res->num_rows === 1) {
            $otp_row = $res->fetch_assoc();
            
            // Mark OTP as used
            $upd = $conn->prepare("UPDATE password_otps SET is_used = 1, used_at = NOW() WHERE id = ?");
            $upd->bind_param("i", $otp_row['id']);
            $upd->execute();
            $upd->close();
            
            $_SESSION['otp_verified'] = true;
            $success_message = 'OTP verified successfully! Please enter your new password below.';
            $step = 3;
        } else {
            $error_message = 'Invalid or expired OTP. Please try again.';
            $step = 2;
        }
        $stmt->close();
    }
}

// Handle Step 3: Reset Password
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['reset_password'])) {
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    $email = isset($_SESSION['reset_email']) ? $_SESSION['reset_email'] : '';
    $user_type = isset($_SESSION['reset_user_type']) ? $_SESSION['reset_user_type'] : 'user';
    $user_id = isset($_SESSION['reset_user_id']) ? $_SESSION['reset_user_id'] : 0;
    
    if (!isset($_SESSION['otp_verified']) || $_SESSION['otp_verified'] !== true || empty($email) || empty($user_id)) {
        $error_message = 'Unauthorized session. Please start over.';
        $step = 1;
    } elseif (empty($new_password) || empty($confirm_password)) {
        $error_message = 'Please enter and confirm your new password.';
        $step = 3;
    } elseif ($new_password !== $confirm_password) {
        $error_message = 'Passwords do not match.';
        $step = 3;
    } elseif (strlen($new_password) < 6) {
        $error_message = 'Password must be at least 6 characters long.';
        $step = 3;
    } else {
        $new_hash = hashPassword($new_password);
        
        if ($user_type === 'admin') {
            $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
        } else {
            $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        }
        
        $stmt->bind_param("si", $new_hash, $user_id);
        
        if ($stmt->execute()) {
            logActivity($conn, ($user_type === 'user' ? $user_id : null), 'PASSWORD_RESET', 'Password reset successfully via OTP');
            
            // Clean up reset session
            unset($_SESSION['reset_email']);
            unset($_SESSION['reset_user_id']);
            unset($_SESSION['reset_user_type']);
            unset($_SESSION['otp_verified']);
            
            $login_url = ($user_type === 'admin') ? 'admin_login.php' : 'user_login.php';
            $success_message = 'Password reset successfully! You can now log in with your new password.';
            $step = 4;
        } else {
            $error_message = 'Failed to update password. Please try again.';
            $step = 3;
        }
        $stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-orange: #FF8C42;
            --dark-bg: #1a2332;
        }
        body {
            background: linear-gradient(135deg, var(--dark-bg) 0%, #2c3e50 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .reset-card {
            max-width: 480px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
            width: 100%;
        }
        .reset-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-orange), #e67e3c);
        }
        .reset-header { text-align: center; margin-bottom: 30px; }
        .reset-icon {
            width: 70px; height: 70px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            border-radius: 50%;
            display: flex; align-items: center; justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 25px rgba(255, 140, 66, 0.4);
            color: white; font-size: 2rem;
        }
        .btn-reset {
            width: 100%; padding: 12px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            color: white; border: none; border-radius: 50px;
            font-weight: 600; font-size: 1rem;
            transition: all 0.3s ease; margin-top: 15px;
        }
        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(255, 140, 66, 0.4);
            color: white;
        }
        .form-control:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(255, 140, 66, 0.1);
        }
        .otp-input {
            letter-spacing: 8px;
            font-size: 1.5rem;
            text-align: center;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="reset-card">
            <div class="reset-header">
                <div class="reset-icon">
                    <i class="fas fa-lock-open"></i>
                </div>
                <h2>Reset Password</h2>
                <p class="text-muted mb-0">Follow the steps below to recover your account</p>
            </div>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($success_message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Step 1: Email Form -->
            <?php if ($step === 1): ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Registered Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text"><i class="fas fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control" placeholder="enter your email" required autofocus>
                        </div>
                    </div>
                    <button type="submit" name="send_otp" class="btn btn-reset">
                        <i class="fas fa-paper-plane me-2"></i>Send Verification OTP
                    </button>
                </form>
            <?php endif; ?>

            <!-- Step 2: OTP Verification Form -->
            <?php if ($step === 2): ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Enter 6-Digit OTP</label>
                        <input type="text" name="otp" class="form-control otp-input" maxlength="6" placeholder="000000" required autofocus>
                    </div>
                    <button type="submit" name="verify_otp" class="btn btn-reset">
                        <i class="fas fa-check-circle me-2"></i>Verify OTP
                    </button>
                </form>
            <?php endif; ?>

            <!-- Step 3: New Password Form -->
            <?php if ($step === 3): ?>
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">New Password</label>
                        <input type="password" name="new_password" class="form-control" placeholder="Minimum 6 characters" required autofocus>
                    </div>
                    <div class="mb-3">
                        <label class="form-label font-weight-bold">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter new password" required>
                    </div>
                    <button type="submit" name="reset_password" class="btn btn-reset">
                        <i class="fas fa-save me-2"></i>Update Password
                    </button>
                </form>
            <?php endif; ?>

            <!-- Step 4: Success state -->
            <?php if ($step === 4): ?>
                <div class="text-center mt-4">
                    <a href="user_login.php" class="btn btn-reset text-decoration-none d-inline-block w-100">
                        <i class="fas fa-sign-in-alt me-2"></i>Proceed to Login
                    </a>
                </div>
            <?php endif; ?>

            <div class="text-center mt-4 pt-3 border-top">
                <a href="user_login.php" class="text-decoration-none text-muted">
                    <i class="fas fa-arrow-left me-1"></i> Back to Login
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>