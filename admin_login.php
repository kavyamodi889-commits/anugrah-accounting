<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

if (isAdminLoggedIn()) {
    header('Location: admin_dashboard.php');
    exit();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? sanitizeInput($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password';
    } else {
        $stmt = $conn->prepare("SELECT id, username, password_hash, full_name, email, role, is_active FROM admin_users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $admin = $result->fetch_assoc();

            if ((int)$admin['is_active'] !== 1) {
                $error = 'Your account has been deactivated. Please contact the system administrator.';
            } else {
                $passwordValid = false;
                $storedHash = $admin['password_hash'];

                // Verify bcrypt hash or fallback to legacy plain text check (with auto-upgrade)
                if (strpos($storedHash, '$2y$') === 0 || strpos($storedHash, '$2a$') === 0) {
                    $passwordValid = verifyPassword($password, $storedHash);
                } else {
                    // Legacy check (if plain text was in DB)
                    if ($password === $storedHash) {
                        $passwordValid = true;
                        // Auto-upgrade plain text to bcrypt hash
                        $newHash = hashPassword($password);
                        $upg = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
                        $upg->bind_param("si", $newHash, $admin['id']);
                        $upg->execute();
                        $upg->close();
                    }
                }

                if ($passwordValid) {
                    $_SESSION['admin_id'] = $admin['id'];
                    $_SESSION['admin_name'] = $admin['full_name'];
                    $_SESSION['admin_role'] = $admin['role'];
                    $_SESSION['admin_email'] = $admin['email'];

                    // Update last login timestamp
                    $updateStmt = $conn->prepare("UPDATE admin_users SET last_login = NOW() WHERE id = ?");
                    $updateStmt->bind_param("i", $admin['id']);
                    $updateStmt->execute();
                    $updateStmt->close();

                    // Log activity
                    logActivity($conn, $admin['id'], 'LOGIN', 'Admin logged in successfully');

                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    $error = 'Invalid username or password';
                }
            }
        } else {
            $error = 'Invalid username or password';
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
    <title>Admin Login - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 20px;
        }
        .login-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
            max-width: 420px;
            width: 100%;
        }
        .login-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
        }
        .login-header i {
            font-size: 50px;
            margin-bottom: 15px;
        }
        .login-body {
            padding: 40px 30px;
        }
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px;
            font-weight: 600;
            letter-spacing: 0.5px;
        }
        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        .input-group-text {
            background-color: #f8f9fa;
            border-right: none;
        }
        .form-control {
            border-left: none;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <i class="fas fa-calculator"></i>
            <h2 class="h3 mb-0">Anugrah Accounting</h2>
            <p class="mb-0 mt-1 opacity-75">Admin Control Panel</p>
        </div>
        <div class="login-body">
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-circle me-2"></i><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label class="form-label font-weight-bold">Username</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-user text-muted"></i></span>
                        <input type="text" class="form-control" name="username" required autofocus placeholder="Enter username">
                    </div>
                </div>
                
                <div class="mb-4">
                    <label class="form-label font-weight-bold">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-lock text-muted"></i></span>
                        <input type="password" class="form-control" name="password" required placeholder="Enter password">
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary btn-login w-100 py-2">
                    <i class="fas fa-sign-in-alt me-2"></i>Sign In
                </button>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>