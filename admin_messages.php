<?php
// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];
$adminWhatsApp = '918000687342'; // Admin's WhatsApp number

// Get customer statistics
$customersWithPhoneQuery = "SELECT COUNT(*) as count FROM (
    SELECT DISTINCT phone FROM (
        SELECT DISTINCT phone FROM users WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
        UNION SELECT DISTINCT phone FROM contact_messages WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM gst_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM accounting_services WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM msme_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM fssai_licences WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM cma_data WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
        UNION SELECT DISTINCT user_phone as phone FROM tax_planning WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    ) as all_phones
) as unique_phones";

$customersWithPhoneResult = $conn->query($customersWithPhoneQuery);
$customersWithPhone = 0;
if ($customersWithPhoneResult) {
    $row = $customersWithPhoneResult->fetch_assoc();
    $customersWithPhone = isset($row['count']) ? $row['count'] : 0;
}

// Get all unique customers
$customerQuery = "
SELECT name, email, phone
FROM (
    SELECT DISTINCT name, email, phone FROM users WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
    UNION SELECT DISTINCT name, email, phone FROM contact_messages WHERE phone IS NOT NULL AND phone != '' AND TRIM(phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM gst_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM accounting_services WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM msme_registrations WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM fssai_licences WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM cma_data WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
    UNION SELECT DISTINCT user_name as name, user_email as email, user_phone as phone FROM tax_planning WHERE user_phone IS NOT NULL AND user_phone != '' AND TRIM(user_phone) != ''
) as all_customers
GROUP BY phone
ORDER BY name";

$customerList = $conn->query($customerQuery);

// Get new messages count for sidebar
$stats = [];
$stats['new_messages'] = 0;
$newMsgResult = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
if ($newMsgResult) {
    $row = $newMsgResult->fetch_assoc();
    $stats['new_messages'] = $row['count'];
}

// Get notification count
$notificationCount = 0;
$notifResult = $conn->query("SELECT COUNT(*) as count FROM notifications_log WHERE DATE(created_at) = CURDATE()");
if ($notifResult) {
    $row = $notifResult->fetch_assoc();
    $notificationCount = $row['count'];
}

$templates = array(
    'leave_notice' => array('name' => 'Leave Notice', 'icon' => '📅', 'body' => "🙏 Dear {name},\n\n*LEAVE INTIMATION*\n\nI am on leave from [START_DATE] to [END_DATE].\n\nI AM AVAILABLE FROM [RETURN_DATE]\n\nFOR ASSISTANCE:\n📞 02642-227258\n📱 8000687342\n\n🕐 10:00 AM TO 6:00 PM\n\nREGARDS 🙏\n*Anugrah Accounting*"),
    'general_update' => array('name' => 'General Update', 'icon' => '📢', 'body' => "🙏 Dear {name},\n\nWe have an update from *Anugrah Accounting*.\n\n[YOUR_MESSAGE_HERE]\n\nContact: 📱 8000687342\n\nBest Regards,\n*Anugrah Accounting*"),
    'reminder' => array('name' => 'Reminder', 'icon' => '🔔', 'body' => "🙏 Dear {name},\n\nReminder from *Anugrah Accounting*.\n\n📋 Pending: [DETAILS]\n📅 Due: [DATE]\n\nContact: 📱 8000687342\n\nThank You!"),
    'thank_you' => array('name' => 'Thank You', 'icon' => '🙏', 'body' => "🙏 Dear {name},\n\nThank you for choosing *Anugrah Accounting*!\n\nFor assistance:\n📱 8000687342\n\nBest Regards"),
    'service_complete' => array('name' => 'Service Complete', 'icon' => '✅', 'body' => "✅ Dear {name},\n\nYour [SERVICE_NAME] is completed!\n\nContact: 📱 8000687342\n\nThank you! 🙏")
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WhatsApp Messaging - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f8f9fa;
        }
        
        /* Sidebar Styles */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
            overflow-y: auto;
            transition: all 0.3s;
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }
        
        .sidebar-header h4 {
            color: white;
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: rgba(255,255,255,0.7);
            font-size: 13px;
            margin: 0;
        }
        
        .sidebar-menu {
            list-style: none;
            padding: 0;
        }
        
        .sidebar-menu li {
            margin-bottom: 5px;
        }
        
        .sidebar-menu a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: all 0.3s;
            position: relative;
        }
        
        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.1);
            color: white;
        }
        
        .sidebar-menu i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }
        
        .notification-badge {
            position: absolute;
            right: 15px;
            background: #dc3545;
            color: white;
            border-radius: 10px;
            padding: 2px 6px;
            font-size: 11px;
            font-weight: 600;
        }
        
        /* Main Content */
        .main-content {
            margin-left: 260px;
            padding: 20px;
            transition: all 0.3s;
        }
        
        /* Top Navigation */
        .top-nav {
            background: white;
            padding: 15px 25px;
            border-radius: 10px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-nav h5 {
            margin: 0;
            color: #333;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        
        .stats-icon {
            width: 60px;
            height: 60px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            margin-bottom: 15px;
        }
        
        .stats-icon.green {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
        }
        
        .stats-icon.blue {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stats-icon.purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-number {
            font-size: 32px;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }
        
        .stats-label {
            color: #666;
            font-size: 14px;
        }
        
        /* Main Card */
        .main-card {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .btn-whatsapp {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            border: none;
            font-weight: 600;
            padding: 12px 24px;
            border-radius: 8px;
            transition: all 0.3s;
        }
        
        .btn-whatsapp:hover {
            background: linear-gradient(135deg, #128C7E 0%, #075E54 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(37, 211, 102, 0.4);
        }
        
        .template-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .template-card {
            background: linear-gradient(135deg, #25D366 0%, #128C7E 100%);
            color: white;
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            text-align: center;
            transition: all 0.3s;
        }
        
        .template-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(37, 211, 102, 0.4);
        }
        
        .template-icon {
            font-size: 32px;
            margin-bottom: 10px;
        }
        
        .template-name {
            font-weight: 600;
            font-size: 13px;
        }
        
        .customer-table {
            width: 100%;
        }
        
        .customer-table th {
            background: #f8f9fa;
            font-weight: 600;
            padding: 12px;
            border-bottom: 2px solid #dee2e6;
        }
        
        .customer-table td {
            padding: 12px;
            border-bottom: 1px solid #dee2e6;
        }
        
        .customer-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .selection-bar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: none;
            align-items: center;
            justify-content: space-between;
        }
        
        .selection-bar.active {
            display: flex;
        }
        
        .admin-number-badge {
            background: #25D366;
            color: white;
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .alert-info-custom {
            background: #e7f3ff;
            border: 1px solid #b3d9ff;
            color: #004085;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }
        
        /* Scrollbar */
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255,255,255,0.1);
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                left: -260px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
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
                <a href="admin_messages.php" class="active">
                    <i class="fab fa-whatsapp"></i> WhatsApp Messaging
                </a>
            </li>
            <li>
                <a href="admin_contact.php">
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
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <h5><i class="fab fa-whatsapp me-2"></i>WhatsApp Messaging Center</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                </div>
            </div>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon green">
                        <i class="fab fa-whatsapp"></i>
                    </div>
                    <div class="stats-number"><?php echo $customersWithPhone; ?></div>
                    <div class="stats-label">Total WhatsApp Contacts</div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon blue">
                        <i class="fas fa-mobile-alt"></i>
                    </div>
                    <div class="stats-number">Direct</div>
                    <div class="stats-label">Sending Method</div>
                </div>
            </div>
            
            <div class="col-lg-4 col-md-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon purple">
                        <i class="fas fa-user-shield"></i>
                    </div>
                    <div class="admin-number-badge" style="margin-top: 10px;">
                        <i class="fab fa-whatsapp"></i>
                        <span><?php echo $adminWhatsApp; ?></span>
                    </div>
                    <div class="stats-label mt-2">Admin WhatsApp Number</div>
                </div>
            </div>
        </div>

        <!-- Main Card -->
        <div class="main-card">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Send Messages</h5>
                <button class="btn btn-whatsapp" onclick="sendToAllCustomers()">
                    <i class="fab fa-whatsapp me-2"></i>Send to All Customers
                </button>
            </div>

            <div class="alert-info-custom">
                <i class="fas fa-info-circle me-2"></i>
                <strong>How it works:</strong> All messages will be sent from admin's WhatsApp number (<?php echo $adminWhatsApp; ?>). When you click send, WhatsApp will open with the pre-filled message for each customer.
            </div>

            <div class="selection-bar" id="selectionBar">
                <div>
                    <i class="fas fa-check-circle me-2"></i>
                    <strong><span id="selectedCount">0</span> customers selected</strong>
                </div>
                <div>
                    <button class="btn btn-light btn-sm me-2" onclick="clearSelection()">
                        <i class="fas fa-times me-1"></i>Clear
                    </button>
                    <button class="btn btn-whatsapp btn-sm" onclick="sendToSelected()">
                        <i class="fab fa-whatsapp me-1"></i>Send to Selected
                    </button>
                </div>
            </div>

            <div class="mb-3">
                <input type="text" id="searchCustomer" class="form-control" placeholder="🔍 Search customers by name, email, or phone...">
            </div>

            <div class="mb-3">
                <button class="btn btn-sm btn-outline-primary me-2" onclick="selectAllVisible()">
                    <i class="fas fa-check-double me-1"></i>Select All Visible
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                    <i class="fas fa-times me-1"></i>Clear Selection
                </button>
            </div>

            <div style="max-height: 500px; overflow-y: auto;">
                <table class="customer-table" id="customerTable">
                    <thead>
                        <tr>
                            <th style="width: 50px;">
                                <input type="checkbox" class="customer-checkbox" id="selectAllCheckbox" onchange="toggleSelectAll(this)">
                            </th>
                            <th>#</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Phone</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($customerList && $customerList->num_rows > 0): 
                            $i = 1;
                            $seenPhones = array();
                            while($c = $customerList->fetch_assoc()): 
                                if(in_array($c['phone'], $seenPhones)) continue;
                                $seenPhones[] = $c['phone'];
                        ?>
                        <tr>
                            <td>
                                <input type="checkbox" class="customer-checkbox customer-select" 
                                       data-phone="<?php echo htmlspecialchars($c['phone']); ?>" 
                                       data-name="<?php echo htmlspecialchars($c['name']); ?>"
                                       onchange="updateSelection()">
                            </td>
                            <td><?php echo $i++; ?></td>
                            <td><i class="fas fa-user me-2 text-primary"></i><?php echo htmlspecialchars($c['name']); ?></td>
                            <td><i class="fas fa-envelope me-2 text-info"></i><?php echo htmlspecialchars($c['email']); ?></td>
                            <td><i class="fab fa-whatsapp me-2 text-success"></i><?php echo htmlspecialchars($c['phone']); ?></td>
                            <td>
                                <button class="btn btn-sm btn-whatsapp" onclick="sendToSingleCustomer('<?php echo htmlspecialchars($c['phone']); ?>', '<?php echo htmlspecialchars(addslashes($c['name'])); ?>')">
                                    <i class="fab fa-whatsapp me-1"></i>Send
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; else: ?>
                        <tr><td colspan="6" class="text-center text-muted py-4">
                            <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                            No customers found with phone numbers
                        </td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Message Template Modal -->
    <div class="modal fade" id="messageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header" style="background: linear-gradient(135deg, #25D366 0%, #128C7E 100%); color: white;">
                    <h5 class="modal-title"><i class="fab fa-whatsapp me-2"></i>Choose Message Template</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Important:</strong> All messages will be sent from admin's WhatsApp number: <strong><?php echo $adminWhatsApp; ?></strong>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>📋 Quick Templates:</strong></label>
                        <div class="template-grid">
                            <?php foreach($templates as $k => $t): ?>
                            <div class="template-card" onclick="loadTemplate('<?php echo $k; ?>')">
                                <div class="template-icon"><?php echo $t['icon']; ?></div>
                                <div class="template-name"><?php echo $t['name']; ?></div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>💬 Message (use {name} for personalization):</strong></label>
                        <textarea id="messageText" class="form-control" rows="10" placeholder="Type your message here..."></textarea>
                        <small class="text-muted">Use {name} to personalize with customer name</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label"><strong>👁️ Preview:</strong></label>
                        <div style="background: #e5ddd5; padding: 15px; border-radius: 8px;">
                            <div style="background: #dcf8c6; padding: 10px 12px; border-radius: 8px; white-space: pre-wrap;" id="messagePreview">Type to preview...</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-whatsapp" onclick="proceedToSend()">
                        <i class="fab fa-whatsapp me-2"></i>Send via Admin WhatsApp
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const templates = <?php echo json_encode($templates); ?>;
        const adminWhatsApp = '<?php echo $adminWhatsApp; ?>';
        let selectedCustomers = [];
        let messageModalInstance;
        let currentSendMode = '';
        let singleCustomer = null;

        document.addEventListener('DOMContentLoaded', function() {
            messageModalInstance = new bootstrap.Modal(document.getElementById('messageModal'));

            // Real-time preview
            document.getElementById('messageText').addEventListener('input', function(e) {
                document.getElementById('messagePreview').textContent = e.target.value.replace(/{name}/g, '[Customer Name]') || 'Type to preview...';
            });

            // Search functionality
            document.getElementById('searchCustomer').addEventListener('keyup', function(e) {
                const searchValue = e.target.value.toLowerCase();
                const rows = document.querySelectorAll('#customerTable tbody tr');
                rows.forEach(row => {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(searchValue) ? '' : 'none';
                });
            });
        });

        function loadTemplate(key) {
            document.getElementById('messageText').value = templates[key].body;
            document.getElementById('messagePreview').textContent = templates[key].body.replace(/{name}/g, '[Customer Name]');
        }

        function updateSelection() {
            selectedCustomers = [];
            document.querySelectorAll('.customer-select:checked').forEach(cb => {
                selectedCustomers.push({
                    phone: cb.getAttribute('data-phone'),
                    name: cb.getAttribute('data-name')
                });
            });
            
            document.getElementById('selectedCount').textContent = selectedCustomers.length;
            document.getElementById('selectionBar').classList.toggle('active', selectedCustomers.length > 0);
            
            const allCheckboxes = document.querySelectorAll('.customer-select');
            const visibleChecked = Array.from(allCheckboxes).filter(cb => 
                cb.closest('tr').style.display !== 'none' && cb.checked
            ).length;
            const visibleTotal = Array.from(allCheckboxes).filter(cb => 
                cb.closest('tr').style.display !== 'none'
            ).length;
            
            document.getElementById('selectAllCheckbox').checked = visibleTotal > 0 && visibleChecked === visibleTotal;
        }

        function toggleSelectAll(checkbox) {
            document.querySelectorAll('.customer-select').forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = checkbox.checked;
                }
            });
            updateSelection();
        }

        function selectAllVisible() {
            document.querySelectorAll('.customer-select').forEach(cb => {
                if (cb.closest('tr').style.display !== 'none') {
                    cb.checked = true;
                }
            });
            updateSelection();
        }

        function clearSelection() {
            document.querySelectorAll('.customer-select').forEach(cb => {
                cb.checked = false;
            });
            document.getElementById('selectAllCheckbox').checked = false;
            updateSelection();
        }

        function sendToAllCustomers() {
            currentSendMode = 'all';
            document.getElementById('messageText').value = '';
            document.getElementById('messagePreview').textContent = 'Type to preview...';
            messageModalInstance.show();
        }

        function sendToSelected() {
            if (selectedCustomers.length === 0) {
                alert('Please select at least one customer!');
                return;
            }
            currentSendMode = 'selected';
            document.getElementById('messageText').value = '';
            document.getElementById('messagePreview').textContent = 'Type to preview...';
            messageModalInstance.show();
        }

        function sendToSingleCustomer(phone, name) {
            currentSendMode = 'single';
            singleCustomer = { phone: phone, name: name };
            document.getElementById('messageText').value = '';
            document.getElementById('messagePreview').textContent = 'Type to preview...';
            messageModalInstance.show();
        }

        function proceedToSend() {
            const message = document.getElementById('messageText').value;
            
            if (!message.trim()) {
                alert('Please enter a message!');
                return;
            }

            messageModalInstance.hide();

            if (currentSendMode === 'single') {
                openWhatsAppForCustomer(singleCustomer, message, true);
            } else if (currentSendMode === 'selected') {
                openWhatsAppForMultiple(selectedCustomers, message);
            } else if (currentSendMode === 'all') {
                getAllCustomersAndSend(message);
            }
        }

        function openWhatsAppForCustomer(customer, messageTemplate, isSingle = false) {
            // Personalize message with customer name
            const personalizedMessage = messageTemplate.replace(/{name}/g, customer.name);
            
            // Encode message for URL
            const encodedMessage = encodeURIComponent(personalizedMessage);
            
            // Create WhatsApp URL - This will open WhatsApp from ADMIN's phone
            // The admin will manually send to the customer's number
            const whatsappUrl = `https://wa.me/${customer.phone}?text=${encodedMessage}`;
            
            // Open in new tab
            window.open(whatsappUrl, '_blank');
            
            if (isSingle) {
                setTimeout(() => {
                    alert(`WhatsApp opened with message for ${customer.name}.\n\nThe message will be sent from your admin WhatsApp number: ${adminWhatsApp}\n\nPlease click send in WhatsApp.`);
                }, 500);
            }
        }

        function openWhatsAppForMultiple(customers, messageTemplate) {
            if (customers.length === 0) return;
            
            let index = 0;
            
            function sendNext() {
                if (index >= customers.length) {
                    alert(`✅ Completed!\n\nOpened WhatsApp for ${customers.length} customers.\n\nAll messages sent from admin number: ${adminWhatsApp}`);
                    return;
                }

                const customer = customers[index];
                openWhatsAppForCustomer(customer, messageTemplate, false);
                
                index++;

                if (index < customers.length) {
                    setTimeout(() => {
                        const continueMsg = `Sent to ${customer.name} (${index}/${customers.length}).\n\nContinue to next customer?`;
                        if (confirm(continueMsg)) {
                            sendNext();
                        } else {
                            alert(`Stopped at ${index} of ${customers.length} customers.`);
                        }
                    }, 2000);
                } else {
                    setTimeout(() => {
                        alert(`✅ All done!\n\nSent messages to ${customers.length} customers from admin WhatsApp: ${adminWhatsApp}`);
                    }, 1000);
                }
            }

            const startMsg = `This will open WhatsApp for ${customers.length} customers.\n\nAll messages will be sent from admin number: ${adminWhatsApp}\n\nContinue?`;
            if (confirm(startMsg)) {
                sendNext();
            }
        }

        function getAllCustomersAndSend(messageTemplate) {
            const allCustomers = [];
            document.querySelectorAll('#customerTable tbody tr').forEach(row => {
                const checkbox = row.querySelector('.customer-select');
                if (checkbox && row.style.display !== 'none') {
                    allCustomers.push({
                        phone: checkbox.getAttribute('data-phone'),
                        name: checkbox.getAttribute('data-name')
                    });
                }
            });

            if (allCustomers.length === 0) {
                alert('No customers found!');
                return;
            }

            openWhatsAppForMultiple(allCustomers, messageTemplate);
        }
    </script>
</body>
</html>