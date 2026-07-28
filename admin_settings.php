<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminId = $_SESSION['admin_id'];
$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];

// Fetch admin details
$stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_profile'])) {
    $fullName = sanitizeInput($_POST['full_name']);
    $email = sanitizeInput($_POST['email']);
    $phone = sanitizeInput($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE admin_users SET full_name = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $fullName, $email, $phone, $adminId);
    
    if ($stmt->execute()) {
        $_SESSION['admin_name'] = $fullName;
        $message = "Profile updated successfully!";
        $messageType = "success";
        
        // Refresh admin data
        $stmt = $conn->prepare("SELECT * FROM admin_users WHERE id = ?");
        $stmt->bind_param("i", $adminId);
        $stmt->execute();
        $admin = $stmt->get_result()->fetch_assoc();
    } else {
        $message = "Error updating profile!";
        $messageType = "danger";
    }
    $stmt->close();
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['change_password'])) {
    $currentPassword = $_POST['current_password'];
    $newPassword = $_POST['new_password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Check current password
    if ($admin['password_hash'] == $currentPassword) {
        if ($newPassword == $confirmPassword) {
            $stmt = $conn->prepare("UPDATE admin_users SET password_hash = ? WHERE id = ?");
            $stmt->bind_param("si", $newPassword, $adminId);
            
            if ($stmt->execute()) {
                $message = "Password changed successfully!";
                $messageType = "success";
            } else {
                $message = "Error changing password!";
                $messageType = "danger";
            }
            $stmt->close();
        } else {
            $message = "New passwords do not match!";
            $messageType = "danger";
        }
    } else {
        $message = "Current password is incorrect!";
        $messageType = "danger";
    }
}

// Fetch system statistics
$stats = array();
$stats['total_admins'] = $conn->query("SELECT COUNT(*) as count FROM admin_users WHERE is_active = 1")->fetch_assoc()['count'];
$stats['total_users'] = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1")->fetch_assoc()['count'];
$stats['pending_services'] = $conn->query("SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations WHERE status = 'Pending'
    UNION ALL SELECT id FROM gst_returns WHERE status = 'Pending'
    UNION ALL SELECT id FROM income_tax_returns WHERE status = 'Pending'
    UNION ALL SELECT id FROM msme_registrations WHERE status = 'Pending'
    UNION ALL SELECT id FROM fssai_licences WHERE status = 'Pending'
    UNION ALL SELECT id FROM accounting_services WHERE status = 'Pending'
    UNION ALL SELECT id FROM cma_data WHERE status = 'Pending'
    UNION ALL SELECT id FROM tax_planning WHERE status = 'Pending'
) as pending")->fetch_assoc()['count'];

// Get new messages count for sidebar
$newMessagesQuery = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
if ($newMessagesQuery) {
    $stats['new_messages'] = $newMessagesQuery->fetch_assoc()['count'];
} else {
    $stats['new_messages'] = 0;
}

// Get notification count (failed notifications) for sidebar
$notifCountQuery = $conn->query("SELECT COUNT(*) as count FROM notifications_log WHERE status = 'failed'");
if ($notifCountQuery) {
    $notificationCount = $notifCountQuery->fetch_assoc()['count'];
} else {
    $notificationCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background-color: #f8f9fa; }
        .sidebar { position: fixed; top: 0; left: 0; height: 100vh; width: 260px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 20px 0; overflow-y: auto; z-index: 1000; }
        .sidebar-header { padding: 0 20px 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h4 { color: white; font-size: 18px; font-weight: 600; margin-bottom: 5px; }
        .sidebar-header p { color: rgba(255,255,255,0.7); font-size: 13px; margin: 0; }
        .sidebar-menu { list-style: none; padding: 0; }
        .sidebar-menu li { margin-bottom: 5px; position: relative; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu i { width: 20px; margin-right: 12px; font-size: 16px; }
        .notification-badge { 
            position: absolute; 
            top: 8px; 
            right: 15px; 
            background: #ff4444; 
            color: white; 
            border-radius: 10px; 
            padding: 2px 8px; 
            font-size: 11px; 
            font-weight: bold;
        }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .settings-card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 20px; }
        .settings-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .stat-box { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; text-align: center; }
        .stat-box h3 { font-size: 32px; font-weight: 700; margin-bottom: 5px; }
        .stat-box p { margin: 0; opacity: 0.9; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <div class="text-center">
                <i class="fas fa-calculator" style="font-size: 40px; color: white; margin-bottom: 10px;"></i>
            </div>
            <h4>Anugrah Accounting</h4>
            <p>Admin Panel</p>
        </div>
        <ul class="sidebar-menu">
            <li><a href="admin_dashboard.php"><i class="fas fa-home"></i> Dashboard</a></li>
            <li><a href="admin_users.php"><i class="fas fa-users"></i> Users Management</a></li>
            <li><a href="admin_gst_reg.php"><i class="fas fa-file-invoice"></i> GST Registrations</a></li>
            <li><a href="admin_gst_returns.php"><i class="fas fa-receipt"></i> GST Returns</a></li>
            <li><a href="admin_income_tax.php"><i class="fas fa-money-bill-wave"></i> Income Tax Returns</a></li>
            <li><a href="admin_msme.php"><i class="fas fa-industry"></i> MSME Registrations</a></li>
            <li><a href="admin_fssai.php"><i class="fas fa-utensils"></i> FSSAI Licences</a></li>
            <li><a href="admin_accounting.php"><i class="fas fa-calculator"></i> Accounting Services</a></li>
            <li><a href="admin_cma.php"><i class="fas fa-chart-line"></i> CMA Data</a></li>
            <li><a href="admin_tax_planning.php"><i class="fas fa-piggy-bank"></i> Tax Planning</a></li>
            <li>
                <a href="admin_messages.php">
                    <i class="fas fa-envelope"></i> Contact Messages
                    <?php if($stats['new_messages'] > 0): ?>
                        <span class="notification-badge"><?php echo $stats['new_messages']; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
            <li>
                <a href="admin_notifications.php">
                    <i class="fas fa-bell"></i> Notifications
                    <?php if($notificationCount > 0): ?>
                        <span class="notification-badge"><?php echo $notificationCount; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li><a href="admin_documents.php"><i class="fas fa-folder"></i> Documents</a></li>
            <li><a href="admin_settings.php" class="active"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-cog me-2"></i>Settings</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($message)): ?>
        <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show">
            <?php echo $message; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stat-box">
                    <h3><?php echo $stats['total_admins']; ?></h3>
                    <p><i class="fas fa-user-shield me-2"></i>Active Admins</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h3><?php echo $stats['total_users']; ?></h3>
                    <p><i class="fas fa-users me-2"></i>Total Users</p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stat-box">
                    <h3><?php echo $stats['pending_services']; ?></h3>
                    <p><i class="fas fa-clock me-2"></i>Pending Services</p>
                </div>
            </div>
        </div>
        
        <div class="row">
            <div class="col-lg-6">
                <div class="settings-card">
                    <h6><i class="fas fa-user me-2"></i>Profile Information</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" name="full_name" class="form-control" value="<?php echo htmlspecialchars($admin['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Username</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['username']); ?>" disabled>
                            <small class="text-muted">Username cannot be changed</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($admin['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="<?php echo htmlspecialchars($admin['phone'] ? $admin['phone'] : ''); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Role</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($admin['role']); ?>" disabled>
                        </div>
                        
                        <button type="submit" name="update_profile" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Profile
                        </button>
                    </form>
                </div>
            </div>
            
            <div class="col-lg-6">
                <div class="settings-card">
                    <h6><i class="fas fa-lock me-2"></i>Change Password</h6>
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-control" required>
                            <small class="text-muted">Use a strong password with at least 8 characters</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-control" required>
                        </div>
                        
                        <button type="submit" name="change_password" class="btn btn-warning">
                            <i class="fas fa-key me-2"></i>Change Password
                        </button>
                    </form>
                </div>
                
                <div class="settings-card">
                    <h6><i class="fas fa-info-circle me-2"></i>Account Information</h6>
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td><strong>Account Status:</strong></td>
                            <td>
                                <?php if($admin['is_active']): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-danger">Inactive</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <td><strong>Member Since:</strong></td>
                            <td><?php echo date('F d, Y', strtotime($admin['created_at'])); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Login:</strong></td>
                            <td><?php echo $admin['last_login'] ? date('F d, Y H:i', strtotime($admin['last_login'])) : 'Never'; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Last Updated:</strong></td>
                            <td><?php echo date('F d, Y H:i', strtotime($admin['updated_at'])); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="settings-card">
            <h6><i class="fas fa-database me-2"></i>System Information</h6>
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td><strong>PHP Version:</strong></td>
                            <td><span class="badge bg-info"><?php echo phpversion(); ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Server Software:</strong></td>
                            <td><?php echo $_SERVER['SERVER_SOFTWARE']; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Server Name:</strong></td>
                            <td><?php echo $_SERVER['SERVER_NAME']; ?></td>
                        </tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-borderless mb-0">
                        <tr>
                            <td><strong>Database:</strong></td>
                            <td><span class="badge bg-success">MySQL <?php echo $conn->server_info; ?></span></td>
                        </tr>
                        <tr>
                            <td><strong>Database Name:</strong></td>
                            <td><?php echo DB_NAME; ?></td>
                        </tr>
                        <tr>
                            <td><strong>System Time:</strong></td>
                            <td><?php echo date('F d, Y H:i:s'); ?></td>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-dismiss alerts after 5 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>