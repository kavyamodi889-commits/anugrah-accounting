<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];
$adminId = $_SESSION['admin_id'];

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $itrId = $_POST['itr_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $sendNotification = isset($_POST['send_notification']) ? true : false;
    
    $stmt = $conn->prepare("UPDATE income_tax_returns SET status = ?, notes = ?, updated_at = NOW() WHERE id = ?");
    $stmt->bind_param("ssi", $status, $notes, $itrId);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    $logStmt = $conn->prepare("INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?, 'ITR_STATUS_UPDATE', 'income_tax_returns', ?, ?, ?, ?)");
    $description = "Status updated to: $status";
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $logStmt->bind_param("iisss", $adminId, $itrId, $description, $ip, $agent);
    $logStmt->execute();
    $logStmt->close();
    
    // Send email notification if requested
    if ($sendNotification) {
        // Get user details
        $userQuery = $conn->prepare("SELECT itr.*, u.name, u.email FROM income_tax_returns itr LEFT JOIN users u ON itr.user_id = u.id WHERE itr.id = ?");
        $userQuery->bind_param("i", $itrId);
        $userQuery->execute();
        $userResult = $userQuery->get_result();
        $userData = $userResult->fetch_assoc();
        $userQuery->close();
        
        if ($userData && $userData['email']) {
            $to = $userData['email'];
            $userName = $userData['name'] ? $userData['name'] : 'User';
            $subject = "Income Tax Return Status Update - Return #" . $itrId;
            
            $message = "Dear " . $userName . ",\n\n";
            $message .= "Your income tax return (ID: #" . $itrId . ") status has been updated.\n\n";
            $message .= "Current Status: " . $status . "\n";
            $message .= "PAN: " . $userData['pan_number'] . "\n";
            $message .= "Assessment Year: " . $userData['assessment_year'] . "\n\n";
            
            if (!empty($notes)) {
                $message .= "Notes from our team:\n" . $notes . "\n\n";
            }
            
            $message .= "If you have any questions, please contact us.\n\n";
            $message .= "Best Regards,\n";
            $message .= "Anugrah Accounting Services";
            
            $headers = "From: noreply@anugrahaccounting.com\r\n";
            $headers .= "Reply-To: support@anugrahaccounting.com\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion();
            
            // Send email
            @mail($to, $subject, $message, $headers);
        }
    }
    
    $redirectMsg = $sendNotification ? 'updated_notified' : 'updated';
    header('Location: admin_income_tax.php?msg=' . $redirectMsg);
    exit();
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_itr'])) {
    $itrId = $_POST['itr_id'];
    
    $stmt = $conn->prepare("DELETE FROM income_tax_returns WHERE id = ?");
    $stmt->bind_param("i", $itrId);
    $stmt->execute();
    $stmt->close();
    
    // Log activity
    $logStmt = $conn->prepare("INSERT INTO activity_log (admin_id, action, entity_type, entity_id, description, ip_address, user_agent) VALUES (?, 'ITR_DELETED', 'income_tax_returns', ?, 'Income Tax Return deleted', ?, ?)");
    $ip = $_SERVER['REMOTE_ADDR'];
    $agent = $_SERVER['HTTP_USER_AGENT'];
    $logStmt->bind_param("iiss", $adminId, $itrId, $ip, $agent);
    $logStmt->execute();
    $logStmt->close();
    
    header('Location: admin_income_tax.php?msg=deleted');
    exit();
}

// Handle export to CSV
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="income_tax_returns_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    fputcsv($output, array('ID', 'User Name', 'Email', 'Phone', 'PAN', 'Assessment Year', 'Financial Year', 'Return Type', 'Total Income', 'Total Deductions', 'Tax Payable', 'Tax Paid', 'Status', 'Created At'));
    
    $exportQuery = "SELECT itr.*, u.name as user_name, u.email, u.phone 
        FROM income_tax_returns itr 
        LEFT JOIN users u ON itr.user_id = u.id 
        ORDER BY itr.created_at DESC";
    $exportResult = $conn->query($exportQuery);
    
    while($row = $exportResult->fetch_assoc()) {
        fputcsv($output, array(
            $row['id'],
            $row['user_name'] ? $row['user_name'] : 'N/A',
            $row['email'] ? $row['email'] : 'N/A',
            $row['phone'] ? $row['phone'] : 'N/A',
            $row['pan_number'],
            $row['assessment_year'],
            $row['financial_year'],
            $row['return_type'],
            $row['total_income'],
            $row['total_deductions'],
            $row['tax_payable'],
            $row['tax_paid'],
            $row['status'],
            $row['created_at']
        ));
    }
    
    fclose($output);
    exit();
}

// Handle search and filter
$searchTerm = isset($_GET['search']) ? $conn->real_escape_string($_GET['search']) : '';
$filterStatus = isset($_GET['status_filter']) ? $conn->real_escape_string($_GET['status_filter']) : '';
$filterYear = isset($_GET['year_filter']) ? $conn->real_escape_string($_GET['year_filter']) : '';

$whereConditions = array();
if (!empty($searchTerm)) {
    $whereConditions[] = "(u.name LIKE '%$searchTerm%' OR u.email LIKE '%$searchTerm%' OR itr.pan_number LIKE '%$searchTerm%')";
}
if (!empty($filterStatus)) {
    $whereConditions[] = "itr.status = '$filterStatus'";
}
if (!empty($filterYear)) {
    $whereConditions[] = "itr.assessment_year = '$filterYear'";
}

$whereClause = '';
if (count($whereConditions) > 0) {
    $whereClause = ' WHERE ' . implode(' AND ', $whereConditions);
}

// Fetch all income tax returns with search and filter
$query = "SELECT itr.*, 
    u.name as user_name, 
    u.email as email, 
    u.phone as phone 
    FROM income_tax_returns itr 
    LEFT JOIN users u ON itr.user_id = u.id 
    $whereClause
    ORDER BY itr.created_at DESC";
$returns = $conn->query($query);

if (!$returns) {
    die("Query failed: " . $conn->error);
}

// Get statistics
$statsQuery = "SELECT 
    COUNT(*) as total_returns,
    SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_returns,
    SUM(CASE WHEN status = 'In Progress' THEN 1 ELSE 0 END) as in_progress_returns,
    SUM(CASE WHEN status = 'Completed' THEN 1 ELSE 0 END) as completed_returns,
    SUM(total_income) as total_income_sum,
    SUM(tax_payable) as total_tax_payable,
    SUM(tax_paid) as total_tax_paid
    FROM income_tax_returns";
$statsResult = $conn->query($statsQuery);
$stats = $statsResult->fetch_assoc();

// Get new messages count
$messagesQuery = "SELECT COUNT(*) as new_messages FROM contact_messages WHERE status = 'New'";
$messagesResult = $conn->query($messagesQuery);
$messagesData = $messagesResult->fetch_assoc();
$stats['new_messages'] = $messagesData['new_messages'];

// Get unique assessment years for filter
$yearsQuery = "SELECT DISTINCT assessment_year FROM income_tax_returns ORDER BY assessment_year DESC";
$yearsResult = $conn->query($yearsQuery);

$notificationCount = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Income Tax Returns - Admin</title>
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
            z-index: 1000;
            transition: all 0.3s ease;
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
            background: #ff4757; 
            color: white; 
            border-radius: 10px; 
            padding: 2px 6px; 
            font-size: 11px; 
            margin-left: 5px; 
        }
        
        /* Main Content */
        .main-content { 
            margin-left: 260px; 
            padding: 20px;
            transition: all 0.3s ease;
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
            flex-wrap: wrap;
            gap: 15px;
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
        
        /* Statistics Cards - Redesigned */
        .stats-row {
            margin-bottom: 30px;
        }
        
        .stat-card-wrapper {
            padding: 0 10px;
            margin-bottom: 20px;
        }
        
        .stat-item { 
            display: flex; 
            flex-direction: column;
            padding: 25px 20px; 
            border-radius: 15px; 
            color: white;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            height: 100%;
            min-height: 140px;
        }
        
        .stat-item::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            transition: all 0.5s ease;
        }
        
        .stat-item:hover {
            transform: translateY(-8px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.25);
        }
        
        .stat-item:hover::before {
            top: -30%;
            right: -30%;
        }
        
        .stat-item.pending { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
        }
        
        .stat-item.progress { 
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); 
        }
        
        .stat-item.completed { 
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); 
        }
        
        .stat-item.total { 
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); 
        }
        
        .stat-content {
            position: relative;
            z-index: 2;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }
        
        .stat-icon-circle {
            width: 55px;
            height: 55px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(10px);
        }
        
        .stat-icon {
            font-size: 26px;
            color: white;
        }
        
        .stat-details {
            flex: 1;
        }
        
        .stat-label { 
            font-size: 13px; 
            opacity: 0.95;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .stat-number { 
            font-size: 36px; 
            font-weight: 700;
            line-height: 1;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
        }
        
        .stat-footer {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .stat-percentage {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.9;
        }
        
        .stat-trend {
            display: flex;
            align-items: center;
            gap: 5px;
        }
        
        .stat-trend i {
            font-size: 12px;
        }
        
        /* Filter Section */
        .filter-section { 
            background: white; 
            padding: 20px; 
            border-radius: 10px; 
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .action-buttons { 
            display: flex; 
            gap: 10px; 
            margin-bottom: 15px; 
            flex-wrap: wrap; 
        }
        
        .search-box { 
            position: relative; 
        }
        
        .search-box input { 
            padding-left: 40px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
        }
        
        .search-box input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .search-box i { 
            position: absolute; 
            left: 12px; 
            top: 50%; 
            transform: translateY(-50%); 
            color: #999; 
        }
        
        /* Table Card */
        .table-card { 
            background: white; 
            border-radius: 10px; 
            padding: 20px; 
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow-x: auto;
        }
        
        .table-card h6 { 
            color: #333; 
            font-weight: 600; 
            margin-bottom: 20px; 
            padding-bottom: 10px; 
            border-bottom: 2px solid #f0f0f0; 
        }
        
        /* Table Styles */
        .table {
            margin-bottom: 0;
        }
        
        .table thead {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .table thead th {
            border: none;
            padding: 15px;
            font-weight: 600;
        }
        
        .table tbody tr {
            transition: all 0.3s ease;
        }
        
        .table tbody tr:hover {
            background: #f8f9fa;
            transform: scale(1.01);
        }
        
        /* Badge Styles */
        .badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 0.85rem;
        }
        
        .badge-pending { 
            background: #fff3cd; 
            color: #856404; 
        }
        
        .badge-progress { 
            background: #d1ecf1; 
            color: #0c5460; 
        }
        
        .badge-completed { 
            background: #d4edda; 
            color: #155724; 
        }
        
        /* Modal Styles */
        .modal-header { 
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); 
            color: white; 
        }
        
        .modal-xl {
            max-width: 1200px;
        }
        
        /* Tab Styling */
        .nav-pills .nav-link {
            color: #666;
            border-radius: 10px;
            padding: 10px 20px;
            margin-right: 10px;
            transition: all 0.3s ease;
        }
        
        .nav-pills .nav-link:hover {
            background: #f0f0f0;
            color: #667eea;
        }
        
        .nav-pills .nav-link.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        /* Timeline Styles */
        .timeline {
            position: relative;
            padding: 20px 0;
        }
        
        .timeline::before {
            content: '';
            position: absolute;
            left: 15px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e0e0e0;
        }
        
        .timeline-item {
            position: relative;
            padding-left: 50px;
            margin-bottom: 30px;
        }
        
        .timeline-marker {
            position: absolute;
            left: 8px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            border: 3px solid white;
            box-shadow: 0 0 0 2px #e0e0e0;
        }
        
        .timeline-content {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }
        
        .timeline-content h6 {
            color: #333;
            margin-bottom: 5px;
        }
        
        /* Enhanced Form Controls */
        .form-label.fw-bold {
            color: #333;
            margin-bottom: 8px;
        }
        
        .form-select-lg, 
        .form-control {
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }
        
        .form-select-lg:focus,
        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        /* Document Cards */
        .card {
            border: 2px dashed #e0e0e0;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .card:hover {
            border-color: #667eea;
            background: #f8f9ff;
        }
        
        .card-body {
            padding: 15px;
        }
        
        .card-title {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 10px;
        }
        
        /* Alert Styling */
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .alert-info {
            background: linear-gradient(135deg, #e3f2fd 0%, #bbdefb 100%);
            color: #0d47a1;
        }
        
        .alert-primary {
            background: linear-gradient(135deg, #e8eaf6 0%, #c5cae9 100%);
            color: #283593;
        }
        
        /* Button Enhancements */
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            border: none;
            color: #155724;
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 233, 123, 0.3);
            color: #155724;
        }
        
        .info-label { 
            font-weight: 600; 
            color: #666; 
            margin-top: 10px;
            margin-bottom: 5px; 
        }
        
        .info-value { 
            color: #333; 
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        /* Button Styles */
        .btn-action {
            padding: 6px 12px;
            border-radius: 6px;
            border: none;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 0.85rem;
        }
        
        .btn-view {
            background: #3498db;
            color: white;
        }
        
        .btn-view:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-edit {
            background: #f39c12;
            color: white;
        }
        
        .btn-edit:hover {
            background: #e67e22;
            transform: translateY(-2px);
        }
        
        .btn-delete {
            background: #e74c3c;
            color: white;
        }
        
        .btn-delete:hover {
            background: #c0392b;
            transform: translateY(-2px);
        }
        
        .print-btn { 
            background: #3498db; 
            color: white; 
            border: none; 
            padding: 8px 15px; 
            border-radius: 5px; 
            cursor: pointer; 
        }
        
        .print-btn:hover { 
            background: #2980b9; 
        }
        
        /* Mobile Menu Toggle */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 20px;
            left: 20px;
            z-index: 1001;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 8px;
            cursor: pointer;
        }
        
        /* Responsive Design */
        @media (max-width: 1200px) {
            .stat-number {
                font-size: 32px;
            }
            
            .stat-icon-circle {
                width: 50px;
                height: 50px;
            }
            
            .stat-icon {
                font-size: 24px;
            }
        }
        
        @media (max-width: 992px) {
            .sidebar {
                left: -260px;
            }
            
            .sidebar.active {
                left: 0;
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .mobile-menu-toggle {
                display: block;
            }
            
            .stat-card-wrapper {
                padding: 0 5px;
                margin-bottom: 15px;
            }
            
            .stat-item {
                min-height: 130px;
            }
        }
        
        @media (max-width: 768px) {
            .top-nav {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .admin-info {
                width: 100%;
                justify-content: space-between;
            }
            
            .stat-number {
                font-size: 28px;
            }
            
            .stat-label {
                font-size: 12px;
            }
            
            .stat-icon-circle {
                width: 45px;
                height: 45px;
            }
            
            .stat-icon {
                font-size: 22px;
            }
            
            .stat-item {
                padding: 20px 15px;
                min-height: 120px;
            }
            
            .table-card {
                padding: 15px;
            }
        }
        
        @media (max-width: 576px) {
            .main-content {
                padding: 10px;
            }
            
            .stat-card-wrapper {
                padding: 0;
                margin-bottom: 15px;
            }
            
            .stat-number {
                font-size: 24px;
            }
            
            .stat-footer {
                font-size: 11px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
            }
        }
        
        @media print {
            .sidebar, 
            .top-nav, 
            .action-buttons, 
            .filter-section, 
            .no-print,
            .mobile-menu-toggle { 
                display: none !important; 
            }
            
            .main-content { 
                margin-left: 0; 
            }
            
            .table-card { 
                box-shadow: none; 
            }
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="fas fa-bars"></i>
    </button>

    <!-- Sidebar -->
    <div class="sidebar" id="sidebar">
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
            <li><a href="admin_income_tax.php" class="active"><i class="fas fa-money-bill-wave"></i> Income Tax Returns</a></li>
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
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <h5><i class="fas fa-money-bill-wave me-2"></i>Income Tax Returns Management</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
        <div class="px-4 pt-3">
            <?php if($_GET['msg'] == 'updated'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Status updated successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($_GET['msg'] == 'updated_notified'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Status updated and email notification sent to user successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php elseif($_GET['msg'] == 'deleted'): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i>Return deleted successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="row stats-row">
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="stat-card-wrapper">
                    <div class="stat-item total">
                        <div class="stat-content">
                            <div class="stat-header">
                                <div class="stat-details">
                                    <div class="stat-label">Total Returns</div>
                                    <div class="stat-number"><?php echo number_format($stats['total_returns']); ?></div>
                                </div>
                                <div class="stat-icon-circle">
                                    <i class="fas fa-file-invoice stat-icon"></i>
                                </div>
                            </div>
                            <div class="stat-footer">
                                <span class="stat-percentage">All submissions</span>
                                <div class="stat-trend">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="stat-card-wrapper">
                    <div class="stat-item pending">
                        <div class="stat-content">
                            <div class="stat-header">
                                <div class="stat-details">
                                    <div class="stat-label">Pending Returns</div>
                                    <div class="stat-number"><?php echo number_format($stats['pending_returns']); ?></div>
                                </div>
                                <div class="stat-icon-circle">
                                    <i class="fas fa-clock stat-icon"></i>
                                </div>
                            </div>
                            <div class="stat-footer">
                                <span class="stat-percentage">
                                    <?php 
                                        $pending_percent = $stats['total_returns'] > 0 ? round(($stats['pending_returns'] / $stats['total_returns']) * 100) : 0;
                                        echo $pending_percent . '% of total';
                                    ?>
                                </span>
                                <div class="stat-trend">
                                    <i class="fas fa-exclamation-circle"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="stat-card-wrapper">
                    <div class="stat-item progress">
                        <div class="stat-content">
                            <div class="stat-header">
                                <div class="stat-details">
                                    <div class="stat-label">In Progress</div>
                                    <div class="stat-number"><?php echo number_format($stats['in_progress_returns']); ?></div>
                                </div>
                                <div class="stat-icon-circle">
                                    <i class="fas fa-spinner stat-icon"></i>
                                </div>
                            </div>
                            <div class="stat-footer">
                                <span class="stat-percentage">
                                    <?php 
                                        $progress_percent = $stats['total_returns'] > 0 ? round(($stats['in_progress_returns'] / $stats['total_returns']) * 100) : 0;
                                        echo $progress_percent . '% of total';
                                    ?>
                                </span>
                                <div class="stat-trend">
                                    <i class="fas fa-tasks"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
                <div class="stat-card-wrapper">
                    <div class="stat-item completed">
                        <div class="stat-content">
                            <div class="stat-header">
                                <div class="stat-details">
                                    <div class="stat-label">Completed</div>
                                    <div class="stat-number"><?php echo number_format($stats['completed_returns']); ?></div>
                                </div>
                                <div class="stat-icon-circle">
                                    <i class="fas fa-check-circle stat-icon"></i>
                                </div>
                            </div>
                            <div class="stat-footer">
                                <span class="stat-percentage">
                                    <?php 
                                        $completed_percent = $stats['total_returns'] > 0 ? round(($stats['completed_returns'] / $stats['total_returns']) * 100) : 0;
                                        echo $completed_percent . '% of total';
                                    ?>
                                </span>
                                <div class="stat-trend">
                                    <i class="fas fa-check-double"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Filter Section -->
        <div class="filter-section">
            <div class="action-buttons no-print">
                <a href="admin_income_tax.php?export=csv" class="btn btn-success">
                    <i class="fas fa-download me-2"></i>Export CSV
                </a>
                <button onclick="window.print()" class="print-btn">
                    <i class="fas fa-print me-2"></i>Print
                </button>
            </div>
            
            <form method="GET" class="row g-3">
                <div class="col-lg-4 col-md-6">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" class="form-control" placeholder="Search by name, email, PAN..." value="<?php echo htmlspecialchars($searchTerm); ?>">
                    </div>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select name="status_filter" class="form-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Pending" <?php echo $filterStatus=='Pending'?'selected':''; ?>>Pending</option>
                        <option value="In Progress" <?php echo $filterStatus=='In Progress'?'selected':''; ?>>In Progress</option>
                        <option value="Completed" <?php echo $filterStatus=='Completed'?'selected':''; ?>>Completed</option>
                    </select>
                </div>
                <div class="col-lg-3 col-md-6">
                    <select name="year_filter" class="form-select" onchange="this.form.submit()">
                        <option value="">All Years</option>
                        <?php while($year = $yearsResult->fetch_assoc()): ?>
                            <option value="<?php echo $year['assessment_year']; ?>" <?php echo $filterYear==$year['assessment_year']?'selected':''; ?>>
                                <?php echo $year['assessment_year']; ?>
                            </option>
                        <?php endwhile; ?>
                    </select>
                </div>
                <div class="col-lg-2 col-md-6">
                    <a href="admin_income_tax.php" class="btn btn-secondary w-100">
                        <i class="fas fa-redo me-2"></i>Reset
                    </a>
                </div>
            </form>
        </div>
        
        <!-- Data Table -->
        <div class="table-card">
            <h6><i class="fas fa-table me-2"></i>Income Tax Returns List</h6>
            <?php if($returns->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>PAN</th>
                            <th>Assessment Year</th>
                            <th>Return Type</th>
                            <th>Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($return = $returns->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $return['id']; ?></strong></td>
                            <td>
                                <strong><?php echo $return['user_name'] ? htmlspecialchars($return['user_name']) : 'N/A'; ?></strong><br>
                                <small class="text-muted"><?php echo $return['email'] ? htmlspecialchars($return['email']) : 'N/A'; ?></small><br>
                                <small class="text-muted"><?php echo $return['phone'] ? htmlspecialchars($return['phone']) : 'N/A'; ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($return['pan_number']); ?></td>
                            <td><?php echo htmlspecialchars($return['assessment_year']); ?></td>
                            <td><?php echo htmlspecialchars($return['return_type']); ?></td>
                            <td>
                                <?php 
                                    $badgeClass = 'badge-pending';
                                    if($return['status'] == 'In Progress') $badgeClass = 'badge-progress';
                                    if($return['status'] == 'Completed') $badgeClass = 'badge-completed';
                                ?>
                                <span class="badge <?php echo $badgeClass; ?>">
                                    <?php echo $return['status']; ?>
                                </span>
                            </td>
                            <td><small><?php echo date('d M Y', strtotime($return['created_at'])); ?></small></td>
                            <td>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn-action btn-view" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $return['id']; ?>">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                    <button class="btn-action btn-edit" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $return['id']; ?>">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this return?');">
                                        <input type="hidden" name="itr_id" value="<?php echo $return['id']; ?>">
                                        <button type="submit" name="delete_itr" class="btn-action btn-delete">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>

                        <!-- View Modal -->
                        <div class="modal fade" id="viewModal<?php echo $return['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Income Tax Return #<?php echo $return['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <h6 class="text-primary">Personal Details</h6>
                                                <div class="info-label">User Name</div>
                                                <div class="info-value"><?php echo $return['user_name'] ? htmlspecialchars($return['user_name']) : 'N/A'; ?></div>
                                                <div class="info-label">Email</div>
                                                <div class="info-value"><?php echo $return['email'] ? htmlspecialchars($return['email']) : 'N/A'; ?></div>
                                                <div class="info-label">Phone</div>
                                                <div class="info-value"><?php echo $return['phone'] ? htmlspecialchars($return['phone']) : 'N/A'; ?></div>
                                                <div class="info-label">PAN Number</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['pan_number']); ?></div>
                                                <div class="info-label">Aadhaar Number</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['aadhaar_number']); ?></div>
                                                <div class="info-label">Assessment Year</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['assessment_year']); ?></div>
                                                <div class="info-label">Financial Year</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['financial_year']); ?></div>
                                                <div class="info-label">Return Type</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['return_type']); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <h6 class="text-primary">Income Details</h6>
                                                <div class="info-label">Salary Income</div>
                                                <div class="info-value">₹<?php echo number_format($return['salary_income'], 2); ?></div>
                                                <div class="info-label">Business Income</div>
                                                <div class="info-value">₹<?php echo number_format($return['business_income'], 2); ?></div>
                                                <div class="info-label">Capital Gains</div>
                                                <div class="info-value">₹<?php echo number_format($return['capital_gains'], 2); ?></div>
                                                <div class="info-label">Other Income</div>
                                                <div class="info-value">₹<?php echo number_format($return['other_income'], 2); ?></div>
                                                <div class="info-label"><strong>Total Income</strong></div>
                                                <div class="info-value"><strong>₹<?php echo number_format($return['total_income'], 2); ?></strong></div>
                                                
                                                <h6 class="text-primary mt-3">Deductions</h6>
                                                <div class="info-label">Section 80C</div>
                                                <div class="info-value">₹<?php echo number_format($return['section_80c'], 2); ?></div>
                                                <div class="info-label">Section 80D</div>
                                                <div class="info-value">₹<?php echo number_format($return['section_80d'], 2); ?></div>
                                                <div class="info-label">Home Loan Interest</div>
                                                <div class="info-value">₹<?php echo number_format($return['home_loan_interest'], 2); ?></div>
                                                <div class="info-label">Other Deductions</div>
                                                <div class="info-value">₹<?php echo number_format($return['other_deductions'], 2); ?></div>
                                                <div class="info-label"><strong>Total Deductions</strong></div>
                                                <div class="info-value"><strong>₹<?php echo number_format($return['total_deductions'], 2); ?></strong></div>
                                                
                                                <h6 class="text-primary mt-3">Tax Details</h6>
                                                <div class="info-label">Tax Payable</div>
                                                <div class="info-value">₹<?php echo number_format($return['tax_payable'], 2); ?></div>
                                                <div class="info-label">TDS Deducted</div>
                                                <div class="info-value">₹<?php echo number_format($return['tds_deducted'], 2); ?></div>
                                                <div class="info-label">Advance Tax</div>
                                                <div class="info-value">₹<?php echo number_format($return['advance_tax'], 2); ?></div>
                                                <div class="info-label">Self Assessment Tax</div>
                                                <div class="info-value">₹<?php echo number_format($return['self_assessment_tax'], 2); ?></div>
                                                <div class="info-label"><strong>Total Tax Paid</strong></div>
                                                <div class="info-value"><strong>₹<?php echo number_format($return['tax_paid'], 2); ?></strong></div>
                                            </div>
                                        </div>
                                        
                                        <?php if($return['bank_name']): ?>
                                        <h6 class="text-primary mt-3">Bank Details</h6>
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="info-label">Bank Name</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['bank_name']); ?></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-label">Account Number</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['account_number']); ?></div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="info-label">IFSC Code</div>
                                                <div class="info-value"><?php echo htmlspecialchars($return['ifsc_code']); ?></div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if($return['notes']): ?>
                                        <div class="mt-3">
                                            <div class="info-label">Notes</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($return['notes'])); ?></div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
<!-- SIMPLE UPDATE MODAL -->
<div class="modal fade" id="updateModal<?php echo $return['id']; ?>" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content simple-modal">
            <!-- Modal Header -->
            <div class="modal-header gradient-header">
                <div>
                    <h5 class="modal-title mb-0">
                        <i class="fas fa-edit me-2"></i>Update Return #<?php echo $return['id']; ?>
                    </h5>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>

            <form method="POST" id="updateForm<?php echo $return['id']; ?>">
                <div class="modal-body p-4">
                    <input type="hidden" name="itr_id" value="<?php echo $return['id']; ?>">
                    
                    <!-- User Info Display -->
                    <div class="user-info-bar mb-4">
                        <div class="row g-2">
                            <div class="col-6">
                                <div class="info-chip">
                                    <i class="fas fa-user text-primary me-2"></i>
                                    <span><?php echo $return['user_name'] ? htmlspecialchars($return['user_name']) : 'N/A'; ?></span>
                                </div>
                            </div>
                            <div class="col-6">
                                <div class="info-chip">
                                    <i class="fas fa-id-card text-success me-2"></i>
                                    <span><?php echo htmlspecialchars($return['pan_number']); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Status Selection -->
                    <div class="mb-4">
                        <label class="simple-label">
                            <i class="fas fa-flag text-danger me-2"></i>Change Status *
                        </label>
                        <select name="status" class="simple-select" required>
                            <option value="Pending" <?php echo $return['status']=='Pending'?'selected':''; ?>>⏳ Pending Review</option>
                            <option value="In Progress" <?php echo $return['status']=='In Progress'?'selected':''; ?>>🔄 In Progress</option>
                            <option value="Completed" <?php echo $return['status']=='Completed'?'selected':''; ?>>✅ Completed</option>
                            <option value="On Hold">⏸️ On Hold</option>
                            <option value="Rejected">❌ Rejected</option>
                        </select>
                    </div>

                    <!-- Notes -->
                    <div class="mb-3">
                        <label class="simple-label">
                            <i class="fas fa-comment-dots text-info me-2"></i>Add Notes (Optional)
                        </label>
                        <textarea name="notes" class="simple-textarea" rows="5" 
                                  placeholder="Enter your notes here...&#10;&#10;Examples:&#10;• Documents received and verified&#10;• Called user - no response&#10;• Processing completed&#10;• Follow-up required"><?php echo htmlspecialchars($return['notes']); ?></textarea>
                    </div>
                </div>

                <!-- Modal Footer -->
                <div class="modal-footer simple-footer">
                    <button type="button" class="btn-simple btn-cancel" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="submit" name="update_status" class="btn-simple btn-save">
                        <i class="fas fa-save me-2"></i>Save Changes
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Simple Modal Styling */
.simple-modal {
    border: none;
    border-radius: 20px;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.3);
}

.modal-dialog-centered {
    max-width: 550px;
}

.gradient-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 25px;
    border: none;
}

.gradient-header .modal-title {
    font-size: 1.3rem;
    font-weight: 700;
    color: white;
}

.modal-body {
    background: #f8f9fa;
}

/* User Info Bar */
.user-info-bar {
    padding: 15px;
    background: white;
    border-radius: 12px;
    border: 2px solid #e0e0e0;
}

.info-chip {
    padding: 10px 12px;
    background: #f8f9fa;
    border-radius: 8px;
    font-size: 0.9rem;
    font-weight: 600;
    color: #333;
    display: flex;
    align-items: center;
}

.info-chip i {
    font-size: 1rem;
}

/* Simple Form Controls */
.simple-label {
    display: block;
    font-weight: 700;
    color: #333;
    margin-bottom: 10px;
    font-size: 1rem;
}

.simple-select,
.simple-textarea {
    width: 100%;
    padding: 14px 16px;
    border: 2px solid #e0e0e0;
    border-radius: 12px;
    font-size: 0.95rem;
    transition: all 0.3s ease;
    background: white;
    font-family: inherit;
}

.simple-select {
    cursor: pointer;
    appearance: none;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 12 12'%3E%3Cpath fill='%23333' d='M6 9L1 4h10z'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 40px;
}

.simple-select:focus,
.simple-textarea:focus {
    outline: none;
    border-color: #667eea;
    box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.15);
    background: #ffffff;
}

.simple-textarea {
    resize: vertical;
    min-height: 120px;
    line-height: 1.6;
}

.simple-textarea::placeholder {
    color: #999;
    line-height: 1.6;
}

/* Simple Footer */
.simple-footer {
    background: white;
    padding: 20px 25px;
    border-top: 2px solid #e0e0e0;
    display: flex;
    gap: 10px;
    justify-content: flex-end;
}

/* Simple Buttons */
.btn-simple {
    padding: 12px 28px;
    border-radius: 10px;
    border: none;
    font-weight: 600;
    font-size: 0.95rem;
    cursor: pointer;
    transition: all 0.3s ease;
    display: inline-flex;
    align-items: center;
    gap: 8px;
}

.btn-simple.btn-cancel {
    background: #e0e0e0;
    color: #555;
}

.btn-simple.btn-cancel:hover {
    background: #d0d0d0;
    transform: translateY(-2px);
}

.btn-simple.btn-save {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
}

.btn-simple.btn-save:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
}

/* Required indicator */
.simple-label::after {
    content: '';
}

.simple-label:has(+ .simple-select[required])::after,
.simple-label:has(+ .simple-textarea[required])::after {
    content: ' *';
    color: #e74c3c;
}

/* Responsive */
@media (max-width: 576px) {
    .modal-dialog-centered {
        margin: 10px;
    }
    
    .modal-body {
        padding: 20px 15px !important;
    }
    
    .simple-footer {
        flex-direction: column;
    }
    
    .btn-simple {
        width: 100%;
        justify-content: center;
    }
    
    .info-chip {
        font-size: 0.85rem;
        padding: 8px 10px;
    }
}

/* Animation */
@keyframes slideDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.modal.show .modal-content {
    animation: slideDown 0.3s ease;
}
</style>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="text-center py-5">
                <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                <h5>No Income Tax Returns Found</h5>
                <p class="text-muted">There are no returns matching your criteria.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 992) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Add quick activity to textarea
        function addQuickActivity(activityText, returnId) {
            const textarea = document.getElementById('activity_log_' + returnId);
            const currentValue = textarea.value.trim();
            
            // Add timestamp
            const now = new Date();
            const timestamp = now.toLocaleString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
            
            const newActivity = '[' + timestamp + '] ' + activityText;
            
            if (currentValue) {
                textarea.value = currentValue + '\n' + newActivity;
            } else {
                textarea.value = newActivity;
            }
            
            // Scroll to bottom of textarea
            textarea.scrollTop = textarea.scrollHeight;
            
            // Visual feedback
            textarea.style.borderColor = '#43e97b';
            setTimeout(() => {
                textarea.style.borderColor = '#e0e0e0';
            }, 1000);
        }
        
        // Add tag function
        function addTag(tagName, returnId) {
            const input = document.getElementById('tags_input_' + returnId);
            const currentTags = input.value.trim();
            
            // Check if tag already exists
            const tagsArray = currentTags ? currentTags.split(',').map(t => t.trim()) : [];
            
            if (!tagsArray.includes(tagName)) {
                if (currentTags) {
                    input.value = currentTags + ', ' + tagName;
                } else {
                    input.value = tagName;
                }
                
                // Visual feedback
                input.style.borderColor = '#43e97b';
                setTimeout(() => {
                    input.style.borderColor = '#e0e0e0';
                }, 1000);
            } else {
                // Tag already exists - show feedback
                input.style.borderColor = '#f39c12';
                setTimeout(() => {
                    input.style.borderColor = '#e0e0e0';
                }, 1000);
            }
        }
        
        // Send notification function - FIXED
        function sendNotification(returnId) {
            const form = document.getElementById('updateForm' + returnId);
            
            if (!form) {
                alert('Form not found!');
                return;
            }
            
            // Validate required fields
            const statusSelect = form.querySelector('select[name="status"]');
            if (!statusSelect || !statusSelect.value) {
                alert('Please select a status before saving!');
                statusSelect.focus();
                return;
            }
            
            // Confirm notification
            const confirmed = confirm('This will save the changes and send an email notification to the user. Continue?');
            
            if (confirmed) {
                // Add hidden input for notification flag
                let notifyInput = form.querySelector('input[name="send_notification"]');
                if (!notifyInput) {
                    notifyInput = document.createElement('input');
                    notifyInput.type = 'hidden';
                    notifyInput.name = 'send_notification';
                    form.appendChild(notifyInput);
                }
                notifyInput.value = '1';
                
                // Show loading state
                const btn = event.target;
                const originalHTML = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Sending...';
                btn.disabled = true;
                
                // Submit the form
                form.submit();
            }
        }
        
        // Update status color dynamically
        function updateStatusColor(selectElement, returnId) {
            const value = selectElement.value;
            selectElement.style.borderColor = getStatusColor(value);
            selectElement.style.backgroundColor = getStatusBgColor(value);
        }
        
        function getStatusColor(status) {
            const colors = {
                'Pending': '#f39c12',
                'In Progress': '#3498db',
                'Completed': '#27ae60',
                'On Hold': '#95a5a6',
                'Rejected': '#e74c3c'
            };
            return colors[status] || '#e0e0e0';
        }
        
        function getStatusBgColor(status) {
            const bgColors = {
                'Pending': '#fff3cd',
                'In Progress': '#d1ecf1',
                'Completed': '#d4edda',
                'On Hold': '#f8f9fa',
                'Rejected': '#f8d7da'
            };
            return bgColors[status] || '#ffffff';
        }
        
        // Auto-save draft functionality
        let autoSaveTimer;
        function enableAutoSave(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const inputs = form.querySelectorAll('input, select, textarea');
            
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    clearTimeout(autoSaveTimer);
                    autoSaveTimer = setTimeout(() => {
                        saveDraft(formId);
                    }, 2000);
                });
            });
        }
        
        function saveDraft(formId) {
            const form = document.getElementById(formId);
            if (!form) return;
            
            const formData = new FormData(form);
            
            // Save to localStorage
            const draftData = {};
            for (let [key, value] of formData.entries()) {
                draftData[key] = value;
            }
            localStorage.setItem('draft_' + formId, JSON.stringify(draftData));
            
            // Show saved indicator
            showSavedIndicator();
        }
        
        function showSavedIndicator() {
            // Remove existing indicator if any
            const existing = document.querySelector('.save-indicator');
            if (existing) existing.remove();
            
            const indicator = document.createElement('div');
            indicator.className = 'position-fixed bottom-0 end-0 m-3 alert alert-success save-indicator';
            indicator.style.zIndex = '9999';
            indicator.innerHTML = '<i class="fas fa-check-circle me-2"></i>Draft saved automatically';
            document.body.appendChild(indicator);
            
            setTimeout(() => {
                indicator.remove();
            }, 2000);
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('form').forEach(form => {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let isValid = true;
                    
                    requiredFields.forEach(field => {
                        if(!field.value.trim()) {
                            isValid = false;
                            field.style.borderColor = '#e74c3c';
                            field.classList.add('is-invalid');
                        } else {
                            field.style.borderColor = '#27ae60';
                            field.classList.remove('is-invalid');
                        }
                    });
                    
                    if(!isValid) {
                        e.preventDefault();
                        alert('Please fill in all required fields marked with *');
                        return false;
                    }
                });
            });
        });
        
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });
    </script>
</body>
</html>