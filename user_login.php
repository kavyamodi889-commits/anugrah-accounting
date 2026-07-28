<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isUserLoggedIn()) {
    header("Location: user_dashboard.php");
    exit();
}

$error_message = '';
$success_message = '';

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    
    if (empty($email) || empty($password)) {
        $error_message = 'Please enter both email and password';
    } else {
        // Check if it's an admin email first
        $stmt = $conn->prepare("SELECT id, username, full_name, email, role, password_hash FROM admin_users WHERE email = ? AND is_active = 1");
        
        if ($stmt === false) {
            $error_message = 'Database query error';
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows === 1) {
                $admin = $result->fetch_assoc();
                $storedHash = $admin['password_hash'];
                $passwordValid = false;

                if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
                    $passwordValid = verifyPassword($password, $storedHash);
                } elseif ($password === $storedHash) {
                    $passwordValid = true;
                    // Auto-upgrade plain text to bcrypt
                    $newHash = hashPassword($password);
                    $upg = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                    $upg->bind_param("si", $newHash, $admin['id']);
                    $upg->execute();
                    $upg->close();
                }

                if ($passwordValid) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_email'] = $admin['email'];
                    
                    $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $admin['id']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    
                    logActivity($conn, null, 'LOGIN', 'Admin logged in via user login portal');
                    
                    header("Location: admin_dashboard.php");
                    exit();
                } else {
                    $error_message = 'Invalid email or password';
                }
            } else {
                $stmt->close();
                // Check users table
                $stmt = $conn->prepare("SELECT id, name, email, password FROM users WHERE email = ?");
                
                if ($stmt === false) {
                    $error_message = 'Database query error';
                } else {
                    $stmt->bind_param("s", $email);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    
                    if ($result->num_rows === 1) {
                        $user = $result->fetch_assoc();
                        
                        if (empty($user['password'])) {
                            $error_message = 'Account exists but password is not set. Please re-register or reset password.';
                        } else {
                            $storedHash = $user['password'];
                            $passwordValid = false;

                            // Standard bcrypt check
                            if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
                                $passwordValid = verifyPassword($password, $storedHash);
                            } 
                            // Legacy MD5 fallback check (auto-upgrades to bcrypt upon success)
                            elseif (md5($password) === $storedHash) {
                                $passwordValid = true;
                                $newBcryptHash = hashPassword($password);
                                $upg = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
                                $upg->bind_param("si", $newBcryptHash, $user['id']);
                                $upg->execute();
                                $upg->close();
                            }

                            if ($passwordValid) {
                                $_SESSION['user_id'] = $user['id'];
                                $_SESSION['user_name'] = $user['name'];
                                $_SESSION['user_email'] = $user['email'];
                                
                                logActivity($conn, $user['id'], 'LOGIN', 'User logged in successfully');
                                
                                $redirect = isset($_GET['redirect']) ? sanitizeInput($_GET['redirect']) : 'user_dashboard.php';
                                header("Location: " . $redirect);
                                exit();
                            } else {
                                $error_message = 'Invalid email or password';
                            }
                        }
                    } else {
                        $error_message = 'Invalid email or password';
                    }
                }
            }
            $stmt->close();
        }
    }
}

// Handle Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register'])) {
    $name = trim($_POST['name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($name) || empty($email) || empty($phone) || empty($password)) {
        $error_message = 'All fields are required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address';
    } elseif ($password !== $confirm_password) {
        $error_message = 'Passwords do not match';
    } elseif (strlen($password) < 6) {
        $error_message = 'Password must be at least 6 characters';
    } else {
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
        
        if ($stmt === false) {
            $error_message = 'Database query error';
        } else {
            $stmt->bind_param("s", $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error_message = 'Email address is already registered';
            } else {
                $stmt->close();
                // Secure password hashing with bcrypt
                $hashed_password = hashPassword($password);
                $stmt = $conn->prepare("INSERT INTO users (name, email, phone, password) VALUES (?, ?, ?, ?)");
                
                if ($stmt === false) {
                    $error_message = 'Database query error';
                } else {
                    $stmt->bind_param("ssss", $name, $email, $phone, $hashed_password);
                    
                    if ($stmt->execute()) {
                        $newUserId = $stmt->insert_id;
                        $_SESSION['user_id'] = $newUserId;
                        $_SESSION['user_name'] = $name;
                        $_SESSION['user_email'] = $email;
                        
                        logActivity($conn, $newUserId, 'REGISTRATION', 'New user registered');
                        
                        $redirect = isset($_GET['redirect']) ? sanitizeInput($_GET['redirect']) : 'user_dashboard.php';
                        header("Location: " . $redirect);
                        exit();
                    } else {
                        $error_message = 'Registration failed. Please try again.';
                    }
                }
            }
            $stmt->close();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login & Registration - Anugrah Accounting</title>
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
        }
        
        .auth-container {
            max-width: 450px;
            margin: 50px auto;
        }
        
        .auth-card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }
        
        .auth-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 6px;
            background: linear-gradient(90deg, var(--primary-orange), #e67e3c);
        }
        
        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .auth-logo {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 10px 30px rgba(255, 140, 66, 0.4);
        }
        
        .auth-logo i {
            font-size: 2.5rem;
            color: white;
        }
        
        .auth-header h2 {
            color: var(--dark-bg);
            font-weight: 700;
            margin-bottom: 10px;
        }
        
        .auth-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            background: #f8f9fa;
            padding: 5px;
            border-radius: 50px;
        }
        
        .auth-tab {
            flex: 1;
            padding: 12px;
            border: none;
            background: transparent;
            border-radius: 50px;
            font-weight: 600;
            color: #666;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .auth-tab.active {
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 140, 66, 0.3);
        }
        
        .auth-form {
            display: none;
        }
        
        .auth-form.active {
            display: block;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--dark-bg);
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-label i {
            color: var(--primary-orange);
        }
        
        .form-control {
            padding: 12px 15px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--primary-orange);
            box-shadow: 0 0 0 4px rgba(255, 140, 66, 0.1);
        }
        
        .btn-auth {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            color: white;
            border: none;
            border-radius: 50px;
            font-weight: 600;
            font-size: 1.1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
        }
        
        .btn-auth:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 140, 66, 0.4);
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 10px;
            margin-bottom: 15px;
        }
        
        .forgot-password a {
            color: var(--primary-orange);
            text-decoration: none;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: var(--primary-orange);
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .alert {
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <div class="auth-logo">
                        <i class="fas fa-calculator"></i>
                    </div>
                    <h2>Welcome Back!</h2>
                    <p class="text-muted">Sign in to access your dashboard</p>
                </div>
                
                <?php if(!empty($error_message)): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo htmlspecialchars($error_message); ?>
                    </div>
                <?php endif; ?>
                
                <?php if(!empty($success_message)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
                    </div>
                <?php endif; ?>
                
                <div class="auth-tabs">
                    <button class="auth-tab active" onclick="switchTab('login')">Login</button>
                    <button class="auth-tab" onclick="switchTab('register')">Register</button>
                </div>
                
                <!-- Login Form -->
                <form id="loginForm" class="auth-form active" method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" class="form-control" placeholder="your.email@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your password" required>
                    </div>
                    
                    <div class="forgot-password">
                        <a href="forgot_password.php">
                            <i class="fas fa-key"></i> Forgot Password?
                        </a>
                    </div>
                    
                    <button type="submit" name="login" class="btn-auth">
                        <i class="fas fa-sign-in-alt"></i> Login
                    </button>
                </form>
                
                <!-- Register Form -->
                <form id="registerForm" class="auth-form" method="POST">
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-user"></i> Full Name
                        </label>
                        <input type="text" name="name" class="form-control" placeholder="Enter your full name" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-envelope"></i> Email Address
                        </label>
                        <input type="email" name="email" class="form-control" placeholder="your.email@example.com" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-phone"></i> Phone Number
                        </label>
                        <input type="tel" name="phone" class="form-control" placeholder="10 digit phone number" pattern="[0-9]{10}" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Password
                        </label>
                        <input type="password" name="password" class="form-control" placeholder="Minimum 6 characters" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-lock"></i> Confirm Password
                        </label>
                        <input type="password" name="confirm_password" class="form-control" placeholder="Re-enter password" required>
                    </div>
                    
                    <button type="submit" name="register" class="btn-auth">
                        <i class="fas fa-user-plus"></i> Register
                    </button>
                </form>
                
                <div class="back-link">
                    <a href="index.php">
                        <i class="fas fa-arrow-left"></i> Back to Home
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        function switchTab(tab) {
            const loginTab = document.querySelector('.auth-tab:first-child');
            const registerTab = document.querySelector('.auth-tab:last-child');
            const loginForm = document.getElementById('loginForm');
            const registerForm = document.getElementById('registerForm');
            
            if (tab === 'login') {
                loginTab.classList.add('active');
                registerTab.classList.remove('active');
                loginForm.classList.add('active');
                registerForm.classList.remove('active');
            } else {
                registerTab.classList.add('active');
                loginTab.classList.remove('active');
                registerForm.classList.add('active');
                loginForm.classList.remove('active');
            }
        }
    </script>
</body>
</html>