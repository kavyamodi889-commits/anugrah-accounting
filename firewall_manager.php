<?php
/**
 * Windows Firewall Manager for SMTP
 * Automatically enables firewall rules when sending emails
 * Compatible with PHP 5.4.12
 */

class FirewallManager {
    
    private $ruleName = "WAMP_SMTP_AUTO";
    private $logFile = "firewall_log.txt";
    
    /**
     * Check if running on Windows
     */
    private function isWindows() {
        return strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
    }
    
    /**
     * Check if running as Administrator
     */
    private function isAdmin() {
        if (!$this->isWindows()) return false;
        
        $output = array();
        exec('net session 2>&1', $output);
        
        foreach ($output as $line) {
            if (stripos($line, 'Access is denied') !== false) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Log firewall actions
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[$timestamp] $message\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND);
    }
    
    /**
     * Check if firewall rule exists
     */
    public function ruleExists() {
        if (!$this->isWindows()) return false;
        
        $output = array();
        $command = 'netsh advfirewall firewall show rule name="' . $this->ruleName . '" 2>&1';
        exec($command, $output);
        
        foreach ($output as $line) {
            if (stripos($line, 'No rules match') !== false) {
                return false;
            }
            if (stripos($line, $this->ruleName) !== false) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Enable SMTP firewall rules
     */
    public function enableSMTP() {
        if (!$this->isWindows()) {
            $this->log("Not Windows - firewall not needed");
            return true;
        }
        
        if (!$this->isAdmin()) {
            $this->log("WARNING: Not running as Administrator - firewall may block");
            return false;
        }
        
        // Check if rule already exists
        if ($this->ruleExists()) {
            $this->log("Firewall rule already exists - OK");
            return true;
        }
        
        // Add firewall rules for SMTP ports 587 and 465
        $commands = array(
            'netsh advfirewall firewall add rule name="' . $this->ruleName . '" dir=out action=allow protocol=TCP remoteport=587,465',
            'netsh advfirewall firewall add rule name="' . $this->ruleName . '_IN" dir=in action=allow protocol=TCP localport=587,465'
        );
        
        foreach ($commands as $command) {
            exec($command . ' 2>&1', $output, $return);
            if ($return !== 0) {
                $this->log("Failed to add firewall rule: " . implode(' ', $output));
                return false;
            }
        }
        
        $this->log("✓ Firewall rules added successfully");
        return true;
    }
    
    /**
     * Disable/Remove SMTP firewall rules
     */
    public function disableSMTP() {
        if (!$this->isWindows()) {
            return true;
        }
        
        if (!$this->isAdmin()) {
            $this->log("Not admin - cannot remove firewall rules");
            return false;
        }
        
        if (!$this->ruleExists()) {
            $this->log("No firewall rule to remove");
            return true;
        }
        
        // Remove firewall rules
        $commands = array(
            'netsh advfirewall firewall delete rule name="' . $this->ruleName . '"',
            'netsh advfirewall firewall delete rule name="' . $this->ruleName . '_IN"'
        );
        
        foreach ($commands as $command) {
            exec($command . ' 2>&1', $output, $return);
        }
        
        $this->log("✓ Firewall rules removed");
        return true;
    }
    
    /**
     * Create a Windows batch file to enable firewall on startup
     */
    public function createStartupScript() {
        if (!$this->isWindows()) {
            return false;
        }
        
        $scriptPath = __DIR__ . '/enable_smtp_firewall.bat';
        
        $batchContent = '@echo off
REM Auto-enable SMTP firewall for WAMP
echo Enabling SMTP firewall rules...

netsh advfirewall firewall delete rule name="' . $this->ruleName . '" >nul 2>&1
netsh advfirewall firewall delete rule name="' . $this->ruleName . '_IN" >nul 2>&1

netsh advfirewall firewall add rule name="' . $this->ruleName . '" dir=out action=allow protocol=TCP remoteport=587,465
netsh advfirewall firewall add rule name="' . $this->ruleName . '_IN" dir=in action=allow protocol=TCP localport=587,465

echo SMTP firewall rules enabled successfully!
pause
';
        
        if (file_put_contents($scriptPath, $batchContent)) {
            $this->log("✓ Startup script created: $scriptPath");
            return $scriptPath;
        }
        
        return false;
    }
    
    /**
     * Get firewall status for display
     */
    public function getStatus() {
        $status = array(
            'is_windows' => $this->isWindows(),
            'is_admin' => $this->isAdmin(),
            'rule_exists' => $this->ruleExists(),
            'rule_name' => $this->ruleName
        );
        
        return $status;
    }
}

/**
 * Helper function to use in send_otp_email.php
 */
function ensureFirewallForEmail() {
    $fw = new FirewallManager();
    return $fw->enableSMTP();
}

/**
 * Helper function to cleanup after email sent
 */
function cleanupFirewallAfterEmail() {
    // Optional: Remove rules after email sent
    // Uncomment if you want to remove rules after each email
    // $fw = new FirewallManager();
    // return $fw->disableSMTP();
    return true;
}

// ============================================
// TESTING & STATUS PAGE
// ============================================
if (basename($_SERVER['PHP_SELF']) == 'firewall_manager.php') {
    
    $fw = new FirewallManager();
    $status = $fw->getStatus();
    $action = isset($_GET['action']) ? $_GET['action'] : '';
    
    $message = '';
    $messageType = '';
    
    // Handle actions
    if ($action == 'enable') {
        if ($fw->enableSMTP()) {
            $message = "✓ Firewall rules enabled successfully!";
            $messageType = 'success';
        } else {
            $message = "✗ Failed to enable firewall rules. Run as Administrator.";
            $messageType = 'error';
        }
        $status = $fw->getStatus();
    }
    
    if ($action == 'disable') {
        if ($fw->disableSMTP()) {
            $message = "✓ Firewall rules removed successfully!";
            $messageType = 'success';
        } else {
            $message = "✗ Failed to remove firewall rules.";
            $messageType = 'error';
        }
        $status = $fw->getStatus();
    }
    
    if ($action == 'create_script') {
        $scriptPath = $fw->createStartupScript();
        if ($scriptPath) {
            $message = "✓ Startup script created: $scriptPath<br>Right-click and 'Run as Administrator'";
            $messageType = 'success';
        } else {
            $message = "✗ Failed to create startup script.";
            $messageType = 'error';
        }
    }
    
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Firewall Manager - PCS Admin</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            * { margin: 0; padding: 0; box-sizing: border-box; }
            body {
                font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                padding: 20px;
                min-height: 100vh;
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
                background: linear-gradient(135deg, #0d1e42, #036fc7);
                color: white;
                padding: 40px;
                text-align: center;
            }
            .header i { font-size: 3rem; margin-bottom: 15px; }
            .header h1 { font-size: 2rem; margin-bottom: 10px; }
            .body { padding: 40px; }
            .alert {
                padding: 15px 20px;
                border-radius: 10px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 15px;
                font-size: 1rem;
            }
            .alert i { font-size: 1.5rem; }
            .alert-success { background: #d4edda; color: #155724; border-left: 5px solid #28a745; }
            .alert-error { background: #f8d7da; color: #721c24; border-left: 5px solid #dc3545; }
            .alert-warning { background: #fff3cd; color: #856404; border-left: 5px solid #ffc107; }
            .alert-info { background: #d1ecf1; color: #0c5460; border-left: 5px solid #17a2b8; }
            .status-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 20px;
                margin: 30px 0;
            }
            .status-card {
                background: #f8f9fa;
                padding: 25px;
                border-radius: 15px;
                border: 2px solid #e0e0e0;
                text-align: center;
            }
            .status-card i {
                font-size: 3rem;
                margin-bottom: 15px;
            }
            .status-card.active { border-color: #28a745; background: #d4edda; }
            .status-card.active i { color: #28a745; }
            .status-card.inactive { border-color: #dc3545; background: #f8d7da; }
            .status-card.inactive i { color: #dc3545; }
            .status-card h3 { margin: 10px 0; font-size: 1.2rem; }
            .status-card p { color: #666; font-size: 0.9rem; }
            .btn-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
                gap: 15px;
                margin: 30px 0;
            }
            .btn {
                padding: 15px 25px;
                border: none;
                border-radius: 10px;
                font-size: 1rem;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                transition: all 0.3s;
            }
            .btn-primary {
                background: linear-gradient(135deg, #28a745, #20c997);
                color: white;
            }
            .btn-primary:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(40, 167, 69, 0.3);
            }
            .btn-danger {
                background: linear-gradient(135deg, #dc3545, #c82333);
                color: white;
            }
            .btn-danger:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(220, 53, 69, 0.3);
            }
            .btn-info {
                background: linear-gradient(135deg, #17a2b8, #138496);
                color: white;
            }
            .btn-info:hover {
                transform: translateY(-2px);
                box-shadow: 0 8px 20px rgba(23, 162, 184, 0.3);
            }
            .btn-secondary {
                background: linear-gradient(135deg, #6c757d, #5a6268);
                color: white;
            }
            .info-box {
                background: #f0f8ff;
                border-left: 4px solid #036fc7;
                padding: 20px;
                margin: 20px 0;
                border-radius: 5px;
            }
            .info-box h3 {
                color: #036fc7;
                margin-bottom: 15px;
                font-size: 1.3rem;
            }
            .info-box ol {
                margin-left: 20px;
                line-height: 1.8;
            }
            .info-box ol li {
                margin: 10px 0;
            }
            .code-box {
                background: #2d2d2d;
                color: #61dafb;
                padding: 15px;
                border-radius: 8px;
                font-family: 'Courier New', monospace;
                margin: 10px 0;
                overflow-x: auto;
            }
        </style>
    </head>
    <body>
        <div class="container">
            <div class="header">
                <i class="fas fa-shield-alt"></i>
                <h1>Windows Firewall Manager</h1>
                <p>Automatic SMTP Firewall Configuration</p>
            </div>
            
            <div class="body">
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <i class="fas fa-<?php echo $messageType == 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                        <span><?php echo $message; ?></span>
                    </div>
                <?php endif; ?>
                
                <h2 style="margin-bottom: 20px;">System Status</h2>
                
                <div class="status-grid">
                    <div class="status-card <?php echo $status['is_windows'] ? 'active' : 'inactive'; ?>">
                        <i class="fab fa-windows"></i>
                        <h3>Windows OS</h3>
                        <p><?php echo $status['is_windows'] ? 'Detected' : 'Not Windows'; ?></p>
                    </div>
                    
                    <div class="status-card <?php echo $status['is_admin'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-user-shield"></i>
                        <h3>Administrator</h3>
                        <p><?php echo $status['is_admin'] ? 'Running as Admin' : 'Not Admin'; ?></p>
                    </div>
                    
                    <div class="status-card <?php echo $status['rule_exists'] ? 'active' : 'inactive'; ?>">
                        <i class="fas fa-fire"></i>
                        <h3>Firewall Rule</h3>
                        <p><?php echo $status['rule_exists'] ? 'Enabled' : 'Disabled'; ?></p>
                    </div>
                </div>
                
                <?php if (!$status['is_admin']): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <span><strong>Not running as Administrator!</strong> To manage firewall rules, you need to run your browser or WAMP as Administrator.</span>
                    </div>
                <?php endif; ?>
                
                <h2 style="margin: 30px 0 20px 0;">Actions</h2>
                
                <div class="btn-grid">
                    <a href="?action=enable" class="btn btn-primary">
                        <i class="fas fa-check-circle"></i>
                        Enable Firewall
                    </a>
                    
                    <a href="?action=disable" class="btn btn-danger">
                        <i class="fas fa-times-circle"></i>
                        Disable Firewall
                    </a>
                    
                    <a href="?action=create_script" class="btn btn-info">
                        <i class="fas fa-file-code"></i>
                        Create Startup Script
                    </a>
                    
                    <a href="forgot_password_otp.php" class="btn btn-secondary">
                        <i class="fas fa-key"></i>
                        Test OTP Email
                    </a>
                </div>
                
                <div class="info-box">
                    <h3><i class="fas fa-info-circle"></i> Setup Instructions</h3>
                    <ol>
                        <li><strong>ONE-TIME SETUP:</strong> Click "Enable Firewall" button above (requires Admin)</li>
                        <li><strong>After Restart:</strong> Run the created batch file as Administrator</li>
                        <li><strong>Automatic Method:</strong> The system will try to enable firewall automatically when sending emails</li>
                    </ol>
                </div>
                
                <div class="info-box" style="background: #fff3cd; border-color: #ffc107;">
                    <h3 style="color: #856404;"><i class="fas fa-lightbulb"></i> Alternative: Manual Setup (One Time)</h3>
                    <p>Run this command <strong>ONCE</strong> in Command Prompt (as Administrator):</p>
                    <div class="code-box">
netsh advfirewall firewall add rule name="<?php echo $status['rule_name']; ?>" dir=out action=allow protocol=TCP remoteport=587,465
                    </div>
                    <p style="margin-top: 15px;">This will persist across restarts and work automatically!</p>
                </div>
                
                <div style="margin-top: 30px; text-align: center;">
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i>
                        Back to Login
                    </a>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
}
?>