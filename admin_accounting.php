<?php
session_start();
require_once 'db_config.php';

// Check if admin is logged in
if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : 'Admin';
$adminRole = isset($_SESSION['admin_role']) ? $_SESSION['admin_role'] : 'Administrator';
$adminId = $_SESSION['admin_id'];

// FORCE DISPLAY MODE - Add ?force=1 to URL to bypass all checks and show raw data
$forceDisplay = isset($_GET['force']) ? true : false;

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    switch($_POST['action']) {
        case 'update_status':
            $serviceId = intval($_POST['service_id']);
            $newStatus = $conn->real_escape_string($_POST['status']);
            
            $sql = "UPDATE accounting_services SET status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $newStatus, $serviceId);
            
            if ($stmt->execute()) {
                $logSql = "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description) 
                          VALUES (?, 'STATUS_UPDATE', 'accounting_service', ?, ?)";
                $logStmt = $conn->prepare($logSql);
                $desc = "Status changed to: $newStatus";
                $logStmt->bind_param("iis", $adminId, $serviceId, $desc);
                $logStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update status']);
            }
            exit();
            
        case 'update_payment':
            $serviceId = intval($_POST['service_id']);
            $paymentStatus = $conn->real_escape_string($_POST['payment_status']);
            
            $sql = "UPDATE accounting_services SET payment_status = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $paymentStatus, $serviceId);
            
            if ($stmt->execute()) {
                $logSql = "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description) 
                          VALUES (?, 'PAYMENT_UPDATE', 'accounting_service', ?, ?)";
                $logStmt = $conn->prepare($logSql);
                $desc = "Payment status changed to: $paymentStatus";
                $logStmt->bind_param("iis", $adminId, $serviceId, $desc);
                $logStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Payment status updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update payment status']);
            }
            exit();
            
        case 'assign_service':
            $serviceId = intval($_POST['service_id']);
            $assignTo = intval($_POST['assign_to']);
            
            $sql = "UPDATE accounting_services SET assigned_to = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("ii", $assignTo, $serviceId);
            
            if ($stmt->execute()) {
                $logSql = "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description) 
                          VALUES (?, 'SERVICE_ASSIGNED', 'accounting_service', ?, ?)";
                $logStmt = $conn->prepare($logSql);
                $desc = "Service assigned to admin ID: $assignTo";
                $logStmt->bind_param("iis", $adminId, $serviceId, $desc);
                $logStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Service assigned successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to assign service']);
            }
            exit();
            
        case 'update_notes':
            $serviceId = intval($_POST['service_id']);
            $notes = $conn->real_escape_string($_POST['notes']);
            
            $sql = "UPDATE accounting_services SET notes = ?, updated_at = NOW() WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("si", $notes, $serviceId);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Notes updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to update notes']);
            }
            exit();
            
        case 'delete_service':
            $serviceId = intval($_POST['service_id']);
            
            $sql = "DELETE FROM accounting_services WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $serviceId);
            
            if ($stmt->execute()) {
                $logSql = "INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description) 
                          VALUES (?, 'SERVICE_DELETED', 'accounting_service', ?, 'Service deleted')";
                $logStmt = $conn->prepare($logSql);
                $logStmt->bind_param("ii", $adminId, $serviceId);
                $logStmt->execute();
                
                echo json_encode(['success' => true, 'message' => 'Service deleted successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Failed to delete service']);
            }
            exit();
    }
}

// Fetch statistics
$statsQuery = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN payment_status = 'Paid' THEN 1 ELSE 0 END) as paid,
    SUM(CASE WHEN payment_status = 'Pending' THEN 1 ELSE 0 END) as payment_pending,
    SUM(CASE WHEN urgency = 'Critical' THEN 1 ELSE 0 END) as critical,
    SUM(CASE WHEN urgency = 'Urgent' THEN 1 ELSE 0 END) as urgent
    FROM accounting_services";
    
$statsResult = $conn->query($statsQuery);
if ($statsResult) {
    $stats = $statsResult->fetch_assoc();
    // Handle null case when table is empty
    if ($stats['total'] === null) {
        $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 
                  'paid' => 0, 'payment_pending' => 0, 'critical' => 0, 'urgent' => 0];
    }
} else {
    $stats = ['total' => 0, 'pending' => 0, 'in_progress' => 0, 'completed' => 0, 
              'paid' => 0, 'payment_pending' => 0, 'critical' => 0, 'urgent' => 0];
}

// Filters
$whereConditions = [];
$filterStatus = isset($_GET['status']) ? $_GET['status'] : '';
$filterPayment = isset($_GET['payment']) ? $_GET['payment'] : '';
$filterService = isset($_GET['service_type']) ? $_GET['service_type'] : '';
$filterUrgency = isset($_GET['urgency']) ? $_GET['urgency'] : '';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';

if ($filterStatus && !$forceDisplay) {
    $whereConditions[] = "status = '" . $conn->real_escape_string($filterStatus) . "'";
}
if ($filterPayment && !$forceDisplay) {
    $whereConditions[] = "payment_status = '" . $conn->real_escape_string($filterPayment) . "'";
}
if ($filterService && !$forceDisplay) {
    $whereConditions[] = "service_type = '" . $conn->real_escape_string($filterService) . "'";
}
if ($filterUrgency && !$forceDisplay) {
    $whereConditions[] = "urgency = '" . $conn->real_escape_string($filterUrgency) . "'";
}
if ($searchQuery && !$forceDisplay) {
    $search = $conn->real_escape_string($searchQuery);
    $whereConditions[] = "(user_name LIKE '%$search%' OR user_email LIKE '%$search%' OR company_name LIKE '%$search%' OR user_phone LIKE '%$search%')";
}

$whereClause = !empty($whereConditions) ? "WHERE " . implode(" AND ", $whereConditions) : "";

// Pagination
$page = isset($_GET['page']) ? intval($_GET['page']) : 1;
$perPage = 15;
$offset = ($page - 1) * $perPage;

// Get total records
$countQuery = "SELECT COUNT(*) as total FROM accounting_services $whereClause";
$countResult = $conn->query($countQuery);
if ($countResult) {
    $totalRecords = $countResult->fetch_assoc()['total'];
} else {
    $totalRecords = 0;
}
$totalPages = $totalRecords > 0 ? ceil($totalRecords / $perPage) : 1;

// Fetch accounting services - SIMPLIFIED QUERY FIRST
$query = "SELECT * FROM accounting_services $whereClause ORDER BY created_at DESC LIMIT $perPage OFFSET $offset";
$result = $conn->query($query);

// Store all data in array to avoid pointer issues
$allServices = [];
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $allServices[] = $row;
    }
}

// Fetch admin names separately
$adminNames = [];
$adminsQuery = "SELECT id, name FROM admin_users WHERE status = 'Active' ORDER BY name";
$adminsResult = $conn->query($adminsQuery);
if ($adminsResult) {
    while ($admin = $adminsResult->fetch_assoc()) {
        $adminNames[$admin['id']] = $admin['name'];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accounting Services Management - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
        
        .stats-icon.blue { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-icon.green { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); }
        .stats-icon.orange { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
        .stats-icon.purple { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
        .stats-icon.yellow { background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 90%); }
        .stats-icon.red { background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%); }
        
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
        
        /* Filter Section */
        .filter-section {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .filter-section h6 {
            margin: 0 0 15px;
            font-size: 16px;
            font-weight: 600;
            color: #333;
        }
        
        /* Tables */
        .table-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .table-card h6 {
            color: #333;
            font-weight: 600;
            margin-bottom: 15px;
        }
        
        .table {
            margin: 0;
        }
        
        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 12px;
            color: #6c757d;
            padding: 15px;
            white-space: nowrap;
        }
        
        .table tbody td {
            padding: 15px;
            vertical-align: middle;
        }
        
        .table tbody tr:hover {
            background-color: #f8f9fa;
        }
        
        .badge {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 20px;
        }
        
        .urgency-critical {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            animation: pulse 2s infinite;
        }
        
        .urgency-urgent {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        
        .urgency-normal {
            background: #6c757d;
            color: white;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .action-buttons {
            display: flex;
            gap: 5px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            padding: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 64px;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .btn-export {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            border: none;
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
            <li><a href="admin_accounting.php" class="active"><i class="fas fa-calculator"></i> Accounting Services</a></li>
            <li><a href="admin_cma.php"><i class="fas fa-chart-line"></i> CMA Data</a></li>
            <li><a href="admin_tax_planning.php"><i class="fas fa-piggy-bank"></i> Tax Planning</a></li>
            <li><a href="admin_messages.php"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
            <li><a href="admin_notifications.php"><i class="fas fa-bell"></i> Notifications</a></li>
            <li><a href="admin_documents.php"><i class="fas fa-folder"></i> Documents</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <h5><i class="fas fa-calculator me-2"></i>Accounting Services Management</h5>
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
        
        <!-- CRITICAL DEBUG ALERT -->
        <?php if ($forceDisplay): ?>
        <div class="alert alert-info alert-dismissible fade show">
            <strong>🔧 FORCE DISPLAY MODE ACTIVE</strong><br>
            Showing all data without filters. Remove ?force=1 from URL to return to normal mode.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <?php if (!$forceDisplay && $result && $totalRecords > 0 && count($allServices) == 0): ?>
        <div class="alert alert-danger">
            <h5>⚠️ DATA DISPLAY ERROR DETECTED!</h5>
            <strong>Database says:</strong> <?php echo $totalRecords; ?> records exist<br>
            <strong>Actually displaying:</strong> 0 records<br>
            <strong>Result object exists:</strong> <?php echo $result ? 'Yes' : 'No'; ?><br>
            <strong>Query error:</strong> <?php echo $conn->error ? $conn->error : 'None'; ?><br>
            <a href="?force=1" class="btn btn-warning btn-sm mt-2">
                <i class="fas fa-bolt me-1"></i>FORCE DISPLAY ALL DATA
            </a>
            <a href="simple_data_view.php" class="btn btn-info btn-sm mt-2">
                <i class="fas fa-eye me-1"></i>View Simple Data Page
            </a>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon blue">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total']; ?></div>
                    <div class="stats-label">Total Services</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['pending']; ?></div>
                    <div class="stats-label">Pending</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon purple">
                        <i class="fas fa-spinner"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['in_progress']; ?></div>
                    <div class="stats-label">In Progress</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon green">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['completed']; ?></div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon red">
                        <i class="fas fa-exclamation-triangle"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['critical'] + $stats['urgent']; ?></div>
                    <div class="stats-label">Urgent Services</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon yellow">
                        <i class="fas fa-dollar-sign"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['paid']; ?></div>
                    <div class="stats-label">Paid</div>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <?php if (!$forceDisplay): ?>
        <div class="filter-section">
            <h6><i class="fas fa-filter me-2"></i>Filters & Search</h6>
            <form method="GET" action="" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" placeholder="Name, email, phone, company..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $filterStatus == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="In Progress" <?php echo $filterStatus == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                        <option value="Completed" <?php echo $filterStatus == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Payment</label>
                    <select name="payment" class="form-select">
                        <option value="">All Payments</option>
                        <option value="Pending" <?php echo $filterPayment == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                        <option value="Paid" <?php echo $filterPayment == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                        <option value="Partial" <?php echo $filterPayment == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Service Type</label>
                    <select name="service_type" class="form-select">
                        <option value="">All Services</option>
                        <option value="Bookkeeping" <?php echo $filterService == 'Bookkeeping' ? 'selected' : ''; ?>>Bookkeeping</option>
                        <option value="Financial Statements" <?php echo $filterService == 'Financial Statements' ? 'selected' : ''; ?>>Financial Statements</option>
                        <option value="Payroll" <?php echo $filterService == 'Payroll' ? 'selected' : ''; ?>>Payroll</option>
                        <option value="Audit Support" <?php echo $filterService == 'Audit Support' ? 'selected' : ''; ?>>Audit Support</option>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Urgency</label>
                    <select name="urgency" class="form-select">
                        <option value="">All Urgency</option>
                        <option value="Critical" <?php echo $filterUrgency == 'Critical' ? 'selected' : ''; ?>>Critical</option>
                        <option value="Urgent" <?php echo $filterUrgency == 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                        <option value="Normal" <?php echo $filterUrgency == 'Normal' ? 'selected' : ''; ?>>Normal</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-search"></i></button>
                </div>
            </form>
            
            <div class="mt-3">
                <a href="admin_accounting.php" class="btn btn-secondary btn-sm"><i class="fas fa-redo me-1"></i>Reset Filters</a>
                <a href="?force=1" class="btn btn-warning btn-sm"><i class="fas fa-bolt me-1"></i>Force Display</a>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Services Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6><i class="fas fa-list me-2"></i>Accounting Services List (<?php echo count($allServices); ?> records)</h6>
                <span class="text-muted">Total in DB: <?php echo $totalRecords; ?></span>
            </div>
            
            <?php if (count($allServices) > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover" id="servicesTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Client Details</th>
                            <th>Service Type</th>
                            <th>Period</th>
                            <th>Business Details</th>
                            <th>Urgency</th>
                            <th>Status</th>
                            <th>Payment</th>
                            <th>Assigned To</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($allServices as $row): ?>
                        <tr>
                            <td><strong>#<?php echo $row['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($row['user_name'] ?: 'N/A'); ?></strong><br>
                                <small class="text-muted">
                                    <?php if($row['user_email']): ?>
                                        <i class="fas fa-envelope me-1"></i><?php echo htmlspecialchars($row['user_email']); ?><br>
                                    <?php endif; ?>
                                    <?php if($row['user_phone']): ?>
                                        <i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($row['user_phone']); ?>
                                    <?php endif; ?>
                                </small>
                            </td>
                            <td>
                                <span class="badge bg-info"><?php echo htmlspecialchars($row['service_type'] ?: 'Not Specified'); ?></span>
                                <?php if($row['company_name']): ?>
                                    <br><small class="text-muted"><?php echo htmlspecialchars($row['company_name']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['period_from'] && $row['period_to']): ?>
                                    <small>
                                        <?php echo date('M d, Y', strtotime($row['period_from'])); ?><br>
                                        to<br>
                                        <?php echo date('M d, Y', strtotime($row['period_to'])); ?>
                                    </small>
                                <?php else: ?>
                                    <small class="text-muted">Not specified</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if($row['business_type']): ?>
                                    <small><strong>Type:</strong> <?php echo htmlspecialchars($row['business_type']); ?></small><br>
                                <?php endif; ?>
                                <?php if($row['software_used']): ?>
                                    <small><strong>Software:</strong> <?php echo htmlspecialchars($row['software_used']); ?></small><br>
                                <?php endif; ?>
                                <?php if($row['frequency']): ?>
                                    <small><strong>Frequency:</strong> <?php echo htmlspecialchars($row['frequency']); ?></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $urgencyClass = 'urgency-normal';
                                if($row['urgency'] == 'Critical') $urgencyClass = 'urgency-critical';
                                elseif($row['urgency'] == 'Urgent') $urgencyClass = 'urgency-urgent';
                                ?>
                                <span class="badge <?php echo $urgencyClass; ?>">
                                    <?php echo $row['urgency']; ?>
                                </span>
                            </td>
                            <td>
                                <select class="form-select form-select-sm status-select" 
                                        data-id="<?php echo $row['id']; ?>" 
                                        style="width: 120px;">
                                    <option value="Pending" <?php echo $row['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="In Progress" <?php echo $row['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="Completed" <?php echo $row['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm payment-select" 
                                        data-id="<?php echo $row['id']; ?>"
                                        style="width: 110px;">
                                    <option value="Pending" <?php echo $row['payment_status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="Partial" <?php echo $row['payment_status'] == 'Partial' ? 'selected' : ''; ?>>Partial</option>
                                    <option value="Paid" <?php echo $row['payment_status'] == 'Paid' ? 'selected' : ''; ?>>Paid</option>
                                </select>
                            </td>
                            <td>
                                <select class="form-select form-select-sm assign-select" 
                                        data-id="<?php echo $row['id']; ?>"
                                        style="width: 130px;">
                                    <option value="">Not Assigned</option>
                                    <?php foreach($adminNames as $adminId => $adminName): ?>
                                        <option value="<?php echo $adminId; ?>" 
                                                <?php echo $row['assigned_to'] == $adminId ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($adminName); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <small class="text-muted">
                                    <?php echo date('M d, Y', strtotime($row['created_at'])); ?><br>
                                    <?php echo date('h:i A', strtotime($row['created_at'])); ?>
                                </small>
                            </td>
                            <td>
                                <div class="action-buttons">
                                    <button class="btn btn-sm btn-info btn-icon" 
                                            onclick="alert('View details for service #<?php echo $row['id']; ?>')" 
                                            title="View Details">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-warning btn-icon" 
                                            onclick="alert('Edit notes for service #<?php echo $row['id']; ?>')" 
                                            title="Edit Notes">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-sm btn-danger btn-icon" 
                                            onclick="if(confirm('Delete service #<?php echo $row['id']; ?>?')) alert('Delete function')" 
                                            title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-calculator"></i>
                <h5>No accounting services found</h5>
                <p>Database shows <?php echo $totalRecords; ?> total records.</p>
                <?php if ($totalRecords > 0): ?>
                    <div class="alert alert-danger mt-3">
                        <strong>ERROR: Data exists but is not displaying!</strong><br>
                        Try these options:
                    </div>
                    <a href="?force=1" class="btn btn-warning mt-2">
                        <i class="fas fa-bolt me-1"></i>FORCE DISPLAY ALL DATA
                    </a>
                    <a href="simple_data_view.php" class="btn btn-info mt-2">
                        <i class="fas fa-eye me-1"></i>Simple Data View
                    </a>
                <?php else: ?>
                    <p class="text-muted">The database table is empty or no records match your filters.</p>
                <?php endif; ?>
                <a href="admin_accounting.php" class="btn btn-primary mt-3">
                    <i class="fas fa-redo me-2"></i>Reset Filters
                </a>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Status Update
        document.querySelectorAll('.status-select').forEach(select => {
            select.addEventListener('change', function() {
                const serviceId = this.dataset.id;
                const newStatus = this.value;
                
                fetch('admin_accounting.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_status&service_id=${serviceId}&status=${newStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message
                        });
                    }
                });
            });
        });
        
        // Payment Status Update
        document.querySelectorAll('.payment-select').forEach(select => {
            select.addEventListener('change', function() {
                const serviceId = this.dataset.id;
                const paymentStatus = this.value;
                
                fetch('admin_accounting.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=update_payment&service_id=${serviceId}&payment_status=${paymentStatus}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message
                        });
                    }
                });
            });
        });
        
        // Assign Service
        document.querySelectorAll('.assign-select').forEach(select => {
            select.addEventListener('change', function() {
                const serviceId = this.dataset.id;
                const assignTo = this.value;
                
                fetch('admin_accounting.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=assign_service&service_id=${serviceId}&assign_to=${assignTo}`
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: 'Success!',
                            text: data.message,
                            timer: 2000,
                            showConfirmButton: false
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: 'Error!',
                            text: data.message
                        });
                    }
                });
            });
        });
    </script>
</body>
</html>