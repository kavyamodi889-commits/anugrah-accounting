<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();

// Handle user status update
if (isset($_POST['update_status'])) {
    $userId = intval($_POST['user_id']);
    $isActive = intval($_POST['is_active']);
    
    $stmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
    $stmt->bind_param("ii", $isActive, $userId);
    $stmt->execute();
    $stmt->close();
    
    logActivity($conn, getAdminId(), 'USER_STATUS_UPDATE', "Updated user status for user ID: $userId");
    $success = "User status updated successfully!";
}

// Handle bulk actions (using prepared statements)
if (isset($_POST['bulk_action']) && isset($_POST['selected_users'])) {
    $action = $_POST['bulk_action'];
    $selectedUsers = $_POST['selected_users'];
    
    if ($action === 'activate' || $action === 'deactivate') {
        $statusVal = ($action === 'activate') ? 1 : 0;
        $updStmt = $conn->prepare("UPDATE users SET is_active = ? WHERE id = ?");
        
        foreach ($selectedUsers as $userId) {
            $uId = intval($userId);
            $updStmt->bind_param("ii", $statusVal, $uId);
            $updStmt->execute();
        }
        $updStmt->close();
        
        logActivity($conn, getAdminId(), 'BULK_USER_UPDATE', "Bulk action: $action on " . count($selectedUsers) . " users");
        $success = "Bulk action completed successfully!";
    }
}


// Get filter parameters
$statusFilter = isset($_GET['status']) ? $_GET['status'] : 'all';
$searchQuery = isset($_GET['search']) ? $_GET['search'] : '';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'created_at';
$sortOrder = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query with filters
$whereConditions = [];
$params = [];
$types = '';

if ($statusFilter != 'all') {
    $whereConditions[] = "is_active = ?";
    $params[] = ($statusFilter == 'active') ? 1 : 0;
    $types .= 'i';
}

if (!empty($searchQuery)) {
    $whereConditions[] = "(name LIKE ? OR email LIKE ? OR phone LIKE ? OR company_name LIKE ?)";
    $searchParam = "%$searchQuery%";
    $params = array_merge($params, array($searchParam, $searchParam, $searchParam, $searchParam));
    $types .= 'ssss';
}

if (!empty($whereConditions)) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
} else {
    $whereClause = "";
}

// Simplified query - just get basic user data first
$baseQuery = "SELECT * FROM users ";

if (!empty($whereClause)) {
    $baseQuery .= $whereClause . " ";
}

$baseQuery .= "ORDER BY created_at " . $sortOrder;

if (!empty($params)) {
    $stmt = $conn->prepare($baseQuery);
    if ($stmt) {
        if (!empty($types) && !empty($params)) {
            $refs = array();
            $refs[] = $types;
            foreach($params as $key => $value) {
                $refs[] = &$params[$key];
            }
            call_user_func_array(array($stmt, 'bind_param'), $refs);
        }
        $stmt->execute();
        $users = $stmt->get_result();
    } else {
        die("Error preparing statement: " . $conn->error);
    }
} else {
    $users = $conn->query($baseQuery);
    if (!$users) {
        die("Error executing query: " . $conn->error . "<br>Query: " . $baseQuery);
    }
}

// Function to count services for a user
function getUserServiceCount($conn, $userId) {
    $count = 0;
    
    $tables = array(
        'gst_registrations',
        'gst_returns', 
        'income_tax_returns',
        'msme_registrations',
        'fssai_licences',
        'accounting_services',
        'cma_data',
        'tax_planning'
    );
    
    foreach ($tables as $table) {
        $result = $conn->query("SELECT COUNT(*) as cnt FROM $table WHERE user_id = $userId");
        if ($result) {
            $row = $result->fetch_assoc();
            $count += $row['cnt'];
        }
    }
    
    return $count;
}

// Function to get last activity
function getLastActivity($conn, $userId) {
    $lastDate = null;
    
    $queries = array(
        "SELECT MAX(created_at) as max_date FROM gst_registrations WHERE user_id = $userId",
        "SELECT MAX(created_at) as max_date FROM gst_returns WHERE user_id = $userId",
        "SELECT MAX(created_at) as max_date FROM income_tax_returns WHERE user_id = $userId"
    );
    
    foreach ($queries as $query) {
        $result = $conn->query($query);
        if ($result) {
            $row = $result->fetch_assoc();
            if ($row['max_date'] && ($lastDate === null || $row['max_date'] > $lastDate)) {
                $lastDate = $row['max_date'];
            }
        }
    }
    
    return $lastDate;
}

// Get statistics
$totalUsersResult = $conn->query("SELECT COUNT(*) as count FROM users");
$totalUsers = $totalUsersResult ? $totalUsersResult->fetch_assoc()['count'] : 0;

$activeUsersResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 1");
$activeUsers = $activeUsersResult ? $activeUsersResult->fetch_assoc()['count'] : 0;

$inactiveUsersResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_active = 0");
$inactiveUsers = $inactiveUsersResult ? $inactiveUsersResult->fetch_assoc()['count'] : 0;

$newUsersTodayResult = $conn->query("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()");
$newUsersToday = $newUsersTodayResult ? $newUsersTodayResult->fetch_assoc()['count'] : 0;

// Get notification count from scheduled_notifications
$notificationCountResult = $conn->query("SELECT COUNT(*) as count FROM scheduled_notifications WHERE status = 'pending'");
$notificationCount = $notificationCountResult ? $notificationCountResult->fetch_assoc()['count'] : 0;

// Get stats for sidebar
$newMessagesResult = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
$newMessages = $newMessagesResult ? $newMessagesResult->fetch_assoc()['count'] : 0;

$stats = array(
    'new_messages' => $newMessages
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Users Management - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: 260px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            padding: 20px 0;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
        }
        
        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
            margin-bottom: 20px;
        }
        
        .sidebar-header h4 {
            color: white;
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .sidebar-header p {
            color: rgba(255,255,255,0.8);
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
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        
        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: white;
        }
        
        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: #ffd700;
        }
        
        .sidebar-menu i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
        }
        
        .notification-badge {
            background: #ff4444;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 600;
            margin-left: auto;
        }
        
        .main-content {
            margin-left: 260px;
            padding: 25px;
        }
        
        .top-nav {
            background: white;
            padding: 20px 30px;
            border-radius: 15px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .top-nav h5 {
            margin: 0;
            color: #2d3748;
            font-weight: 700;
            font-size: 24px;
        }
        
        .admin-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .admin-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 18px;
            box-shadow: 0 4px 10px rgba(102, 126, 234, 0.4);
        }
        
        .stats-container {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }
        
        .stat-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            display: flex;
            align-items: center;
            justify-content: space-between;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }
        
        .stat-info h3 {
            font-size: 32px;
            font-weight: 700;
            margin: 0;
            color: #2d3748;
        }
        
        .stat-info p {
            margin: 0;
            color: #718096;
            font-size: 14px;
            font-weight: 500;
        }
        
        .stat-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
        }
        
        .stat-icon.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .stat-icon.success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
            color: white;
        }
        
        .stat-icon.danger {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
            color: white;
        }
        
        .stat-icon.warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
        }
        
        .filter-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .filter-row {
            display: flex;
            gap: 15px;
            align-items: end;
            flex-wrap: wrap;
        }
        
        .filter-group {
            flex: 1;
            min-width: 200px;
        }
        
        .filter-group label {
            display: block;
            margin-bottom: 8px;
            color: #4a5568;
            font-weight: 600;
            font-size: 14px;
        }
        
        .filter-group input,
        .filter-group select {
            width: 100%;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            transition: all 0.3s ease;
        }
        
        .filter-group input:focus,
        .filter-group select:focus {
            border-color: #667eea;
            outline: none;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .table-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f7fafc;
        }
        
        .table-header h6 {
            color: #2d3748;
            font-weight: 700;
            font-size: 18px;
            margin: 0;
        }
        
        .bulk-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .custom-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0 8px;
        }
        
        .custom-table thead th {
            background: #f7fafc;
            color: #4a5568;
            font-weight: 600;
            font-size: 13px;
            text-transform: uppercase;
            padding: 12px 15px;
            border: none;
            position: sticky;
            top: 0;
            z-index: 10;
        }
        
        .custom-table thead th:first-child {
            border-radius: 8px 0 0 8px;
        }
        
        .custom-table thead th:last-child {
            border-radius: 0 8px 8px 0;
        }
        
        .custom-table tbody tr {
            background: white;
            transition: all 0.3s ease;
        }
        
        .custom-table tbody tr:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .custom-table tbody td {
            padding: 15px;
            border-top: 1px solid #f7fafc;
            border-bottom: 1px solid #f7fafc;
        }
        
        .custom-table tbody td:first-child {
            border-left: 1px solid #f7fafc;
            border-radius: 8px 0 0 8px;
        }
        
        .custom-table tbody td:last-child {
            border-right: 1px solid #f7fafc;
            border-radius: 0 8px 8px 0;
        }
        
        .user-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            font-size: 16px;
        }
        
        .user-details strong {
            display: block;
            color: #2d3748;
            font-weight: 600;
        }
        
        .user-details small {
            color: #718096;
            font-size: 12px;
        }
        
        .badge {
            padding: 6px 12px;
            font-size: 11px;
            font-weight: 600;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge.bg-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e063 100%);
        }
        
        .badge.bg-danger {
            background: linear-gradient(135deg, #ee0979 0%, #ff6a00 100%);
        }
        
        .badge.bg-info {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .badge.bg-warning {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
        }
        
        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        
        .action-buttons .btn {
            padding: 6px 12px;
            font-size: 13px;
        }
        
        .activity-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 6px;
        }
        
        .activity-indicator.active {
            background: #48bb78;
            box-shadow: 0 0 8px rgba(72, 187, 120, 0.6);
        }
        
        .activity-indicator.inactive {
            background: #cbd5e0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
            }
            
            .stats-container {
                grid-template-columns: 1fr;
            }
            
            .filter-row {
                flex-direction: column;
            }
            
            .filter-group {
                width: 100%;
            }
        }
        
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
        }
        
        .sortable:hover {
            color: #667eea;
        }
        
        .sortable::after {
            content: '⇅';
            margin-left: 5px;
            opacity: 0.5;
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
            <li><a href="admin_users.php" class="active"><i class="fas fa-users"></i> Users Management</a></li>
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
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-users me-2"></i>Users Management</h5>
            <div class="admin-info">
                <div class="text-end">
                    <div style="font-size: 14px; font-weight: 600; color: #2d3748;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar">
                    <?php echo strtoupper(substr($adminName, 0, 1)); ?>
                </div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert" style="border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(72, 187, 120, 0.2);">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $totalUsers; ?></h3>
                    <p>Total Users</p>
                </div>
                <div class="stat-icon primary">
                    <i class="fas fa-users"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $activeUsers; ?></h3>
                    <p>Active Users</p>
                </div>
                <div class="stat-icon success">
                    <i class="fas fa-user-check"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $inactiveUsers; ?></h3>
                    <p>Inactive Users</p>
                </div>
                <div class="stat-icon danger">
                    <i class="fas fa-user-slash"></i>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-info">
                    <h3><?php echo $newUsersToday; ?></h3>
                    <p>New Today</p>
                </div>
                <div class="stat-icon warning">
                    <i class="fas fa-user-plus"></i>
                </div>
            </div>
        </div>
        
        <!-- Filter Card -->
        <div class="filter-card">
            <form method="GET" action="">
                <div class="filter-row">
                    <div class="filter-group">
                        <label><i class="fas fa-search me-1"></i>Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Name, email, phone, company..." value="<?php echo htmlspecialchars($searchQuery); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-filter me-1"></i>Status</label>
                        <select name="status" class="form-select">
                            <option value="all" <?php echo $statusFilter == 'all' ? 'selected' : ''; ?>>All Users</option>
                            <option value="active" <?php echo $statusFilter == 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $statusFilter == 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-sort me-1"></i>Sort By</label>
                        <select name="sort" class="form-select">
                            <option value="created_at" <?php echo $sortBy == 'created_at' ? 'selected' : ''; ?>>Registration Date</option>
                            <option value="name" <?php echo $sortBy == 'name' ? 'selected' : ''; ?>>Name</option>
                            <option value="email" <?php echo $sortBy == 'email' ? 'selected' : ''; ?>>Email</option>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label><i class="fas fa-arrow-down-up-across-line me-1"></i>Order</label>
                        <select name="order" class="form-select">
                            <option value="DESC" <?php echo $sortOrder == 'DESC' ? 'selected' : ''; ?>>Descending</option>
                            <option value="ASC" <?php echo $sortOrder == 'ASC' ? 'selected' : ''; ?>>Ascending</option>
                        </select>
                    </div>
                    
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <label style="opacity: 0;">Filter</label>
                        <button type="submit" class="btn btn-gradient w-100">
                            <i class="fas fa-search me-1"></i> Apply Filters
                        </button>
                    </div>
                    
                    <?php if (!empty($searchQuery) || $statusFilter != 'all'): ?>
                    <div class="filter-group" style="flex: 0 0 auto;">
                        <label style="opacity: 0;">Reset</label>
                        <a href="admin_users.php" class="btn btn-outline-secondary w-100">
                            <i class="fas fa-times me-1"></i> Reset
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <!-- Users Table -->
        <div class="table-card">
            <div class="table-header">
                <h6><i class="fas fa-users me-2"></i>User Directory</h6>
                <div class="bulk-actions">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                        <i class="fas fa-check-double me-1"></i> Select All
                    </button>
                    <button type="button" class="btn btn-sm btn-gradient" onclick="exportUsers()">
                        <i class="fas fa-download me-1"></i> Export CSV
                    </button>
                </div>
            </div>
            
            <form method="POST" id="bulkForm">
                <div class="mb-3" style="display: flex; gap: 10px; align-items: center;">
                    <select name="bulk_action" class="form-select" style="width: auto;">
                        <option value="">Bulk Actions</option>
                        <option value="activate">Activate Selected</option>
                        <option value="deactivate">Deactivate Selected</option>
                    </select>
                    <button type="submit" class="btn btn-sm btn-gradient" onclick="return confirm('Apply bulk action to selected users?')">
                        <i class="fas fa-play me-1"></i> Apply
                    </button>
                    <span id="selectedCount" class="text-muted" style="margin-left: 10px;"></span>
                </div>
                
                <div class="table-responsive">
                    <table class="custom-table">
                        <thead>
                            <tr>
                                <th style="width: 40px;">
                                    <input type="checkbox" id="selectAllCheckbox" onchange="toggleAll(this)">
                                </th>
                                <th>User</th>
                                <th>Contact</th>
                                <th>Company</th>
                                <th>Services</th>
                                <th>Status</th>
                                <th>Last Activity</th>
                                <th>Registered</th>
                                <th style="text-align: center;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $hasResults = false;
                            if ($users && $users->num_rows > 0) {
                                while($user = $users->fetch_assoc()): 
                                    $hasResults = true;
                                    $totalServices = getUserServiceCount($conn, $user['id']);
                                    $lastActivity = getLastActivity($conn, $user['id']);
                            ?>
                            <tr>
                                <td>
                                    <input type="checkbox" name="selected_users[]" value="<?php echo $user['id']; ?>" class="user-checkbox" onchange="updateSelectedCount()">
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                        </div>
                                        <div class="user-details">
                                            <strong><?php echo htmlspecialchars($user['name']); ?></strong>
                                            <?php if(isset($user['pan']) && !empty($user['pan'])): ?>
                                                <small><i class="fas fa-id-card me-1"></i><?php echo htmlspecialchars($user['pan']); ?></small>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div>
                                        <i class="fas fa-envelope text-muted me-1"></i>
                                        <small><?php echo htmlspecialchars($user['email']); ?></small>
                                    </div>
                                    <div>
                                        <i class="fas fa-phone text-muted me-1"></i>
                                        <small><?php echo htmlspecialchars($user['phone']); ?></small>
                                    </div>
                                </td>
                                <td><?php echo htmlspecialchars($user['company_name']); ?></td>
                                <td>
                                    <span class="badge bg-info">
                                        <i class="fas fa-briefcase me-1"></i><?php echo $totalServices; ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if($user['is_active']): ?>
                                        <span class="activity-indicator active"></span>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="activity-indicator inactive"></span>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if($lastActivity): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-clock me-1"></i>
                                            <?php echo date('M d, Y', strtotime($lastActivity)); ?>
                                        </small>
                                    <?php else: ?>
                                        <small class="text-muted">No activity</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?php echo date('M d, Y', strtotime($user['created_at'])); ?>
                                    </small>
                                </td>
                                <td>
                                    <div class="action-buttons" style="justify-content: center;">
                                        <button type="button" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewUser<?php echo $user['id']; ?>" title="View Details">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                            <input type="hidden" name="is_active" value="<?php echo $user['is_active'] ? 0 : 1; ?>">
                                            <button type="submit" name="update_status" class="btn btn-sm btn-outline-<?php echo $user['is_active'] ? 'danger' : 'success'; ?>" 
                                                    title="<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?>" 
                                                    onclick="return confirm('<?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?> this user?')">
                                                <i class="fas fa-<?php echo $user['is_active'] ? 'ban' : 'check'; ?>"></i>
                                            </button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- View User Modal -->
                            <div class="modal fade" id="viewUser<?php echo $user['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content" style="border-radius: 15px; border: none;">
                                        <div class="modal-header" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 15px 15px 0 0;">
                                            <h5 class="modal-title"><i class="fas fa-user-circle me-2"></i>User Details</h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body" style="padding: 30px;">
                                            <div class="text-center mb-4">
                                                <div class="user-avatar" style="width: 80px; height: 80px; font-size: 32px; margin: 0 auto;">
                                                    <?php echo strtoupper(substr($user['name'], 0, 1)); ?>
                                                </div>
                                                <h4 class="mt-3"><?php echo htmlspecialchars($user['name']); ?></h4>
                                                <?php if($user['is_active']): ?>
                                                    <span class="badge bg-success">Active User</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Inactive User</span>
                                                <?php endif; ?>
                                            </div>
                                            
                                            <div class="row g-3">
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Email</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-envelope me-2"></i><?php echo htmlspecialchars($user['email']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Phone</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($user['phone']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Company</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-building me-2"></i><?php echo htmlspecialchars($user['company_name']); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Total Services</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-briefcase me-2"></i><?php echo $totalServices; ?> Services
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">PAN</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-id-card me-2"></i><?php echo !empty($user['pan']) ? htmlspecialchars($user['pan']) : 'Not Provided'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">GSTIN</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-file-invoice me-2"></i><?php echo !empty($user['gstin']) ? htmlspecialchars($user['gstin']) : 'Not Provided'; ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-12">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Address</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-map-marker-alt me-2"></i>
                                                            <?php 
                                                            $address_parts = array_filter([
                                                                isset($user['address']) ? $user['address'] : '',
                                                                isset($user['city']) ? $user['city'] : '',
                                                                isset($user['state']) ? $user['state'] : '',
                                                                isset($user['pincode']) ? $user['pincode'] : ''
                                                            ]);
                                                            echo !empty($address_parts) ? htmlspecialchars(implode(', ', $address_parts)) : 'Not Provided';
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Registered On</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-calendar me-2"></i><?php echo date('F d, Y', strtotime($user['created_at'])); ?>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div style="background: #f7fafc; padding: 15px; border-radius: 8px;">
                                                        <strong style="color: #4a5568; font-size: 12px; text-transform: uppercase;">Last Activity</strong>
                                                        <div style="color: #2d3748; margin-top: 5px;">
                                                            <i class="fas fa-clock me-2"></i>
                                                            <?php 
                                                            echo $lastActivity ? 
                                                                date('F d, Y', strtotime($lastActivity)) : 
                                                                'No activity yet';
                                                            ?>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="modal-footer" style="background: #f7fafc; border-radius: 0 0 15px 15px;">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php 
                                endwhile;
                            }
                            ?>
                            
                            <?php if (!$hasResults): ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <i class="fas fa-users-slash" style="font-size: 48px; color: #cbd5e0; margin-bottom: 15px;"></i>
                                    <h5 style="color: #718096;">No users found</h5>
                                    <p style="color: #a0aec0;">Try adjusting your filters or search criteria</p>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </form>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleAll(checkbox) {
            const checkboxes = document.querySelectorAll('.user-checkbox');
            checkboxes.forEach(cb => cb.checked = checkbox.checked);
            updateSelectedCount();
        }
        
        function selectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            selectAllCheckbox.checked = true;
            toggleAll(selectAllCheckbox);
        }
        
        function updateSelectedCount() {
            const checked = document.querySelectorAll('.user-checkbox:checked').length;
            const countEl = document.getElementById('selectedCount');
            if (checked > 0) {
                countEl.textContent = `${checked} user(s) selected`;
                countEl.style.color = '#667eea';
                countEl.style.fontWeight = '600';
            } else {
                countEl.textContent = '';
            }
        }
        
        function exportUsers() {
            // Get current filters
            const params = new URLSearchParams(window.location.search);
            params.append('export', 'csv');
            window.location.href = 'export_users.php?' + params.toString();
        }
    </script>
</body>
</html>