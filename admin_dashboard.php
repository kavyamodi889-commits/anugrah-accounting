<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();


// Fetch dashboard statistics
$stats = [];

// Helper function to execute queries safely
function executeCountQuery($conn, $query, $default = 0) {
    $result = $conn->query($query);
    if (!$result) {
        error_log("Query Error: " . $conn->error);
        error_log("Query: " . $query);
        return $default;
    }
    $row = $result->fetch_assoc();
    return $row['count'];
}

// Total Users
$stats['total_users'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM users");

// Total Services
$stats['total_services'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations
    UNION ALL SELECT id FROM gst_returns
    UNION ALL SELECT id FROM income_tax_returns
    UNION ALL SELECT id FROM msme_registrations
    UNION ALL SELECT id FROM fssai_licences
    UNION ALL SELECT id FROM accounting_services
    UNION ALL SELECT id FROM cma_data
    UNION ALL SELECT id FROM tax_planning
) as all_services");

// Pending Services
$stats['pending_services'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations WHERE status = 'Pending'
    UNION ALL SELECT id FROM gst_returns WHERE status = 'Pending'
    UNION ALL SELECT id FROM income_tax_returns WHERE status = 'Pending'
    UNION ALL SELECT id FROM msme_registrations WHERE status = 'Pending'
    UNION ALL SELECT id FROM fssai_licences WHERE status = 'Pending'
    UNION ALL SELECT id FROM accounting_services WHERE status = 'Pending'
    UNION ALL SELECT id FROM cma_data WHERE status = 'Pending'
    UNION ALL SELECT id FROM tax_planning WHERE status = 'Pending'
) as pending_services");

// Completed Services
$stats['completed_services'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations WHERE status = 'Completed'
    UNION ALL SELECT id FROM gst_returns WHERE status = 'Completed'
    UNION ALL SELECT id FROM income_tax_returns WHERE status = 'Completed'
    UNION ALL SELECT id FROM msme_registrations WHERE status = 'Completed'
    UNION ALL SELECT id FROM fssai_licences WHERE status = 'Completed'
    UNION ALL SELECT id FROM accounting_services WHERE status = 'Completed'
    UNION ALL SELECT id FROM cma_data WHERE status = 'Completed'
    UNION ALL SELECT id FROM tax_planning WHERE status = 'Completed'
) as completed_services");

// New Contact Messages
$stats['new_messages'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");

// Total Feedback
$stats['total_feedback'] = executeCountQuery($conn, "SELECT COUNT(*) as count FROM feedback");

// Services by type for chart
$servicesByType = [];
$services = ['GST Registrations' => 'gst_registrations', 
             'GST Returns' => 'gst_returns',
             'Income Tax' => 'income_tax_returns',
             'MSME' => 'msme_registrations',
             'FSSAI' => 'fssai_licences',
             'Accounting' => 'accounting_services',
             'CMA Data' => 'cma_data',
             'Tax Planning' => 'tax_planning'];

foreach ($services as $name => $table) {
    $result = $conn->query("SELECT COUNT(*) as count FROM $table");
    if ($result) {
        $row = $result->fetch_assoc();
        $servicesByType[$name] = $row['count'];
    } else {
        error_log("Query Error for table $table: " . $conn->error);
        $servicesByType[$name] = 0;
    }
}

// Monthly services trend (last 6 months)
$monthlyData = [];
$monthStmt = $conn->prepare("SELECT COUNT(*) as count FROM (
    SELECT created_at FROM gst_registrations WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    UNION ALL SELECT created_at FROM gst_returns WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    UNION ALL SELECT created_at FROM income_tax_returns WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    UNION ALL SELECT created_at FROM msme_registrations WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    UNION ALL SELECT created_at FROM fssai_licences WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
    UNION ALL SELECT created_at FROM accounting_services WHERE DATE_FORMAT(created_at, '%Y-%m') = ?
) as monthly");

for ($i = 5; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $monthName = date('M Y', strtotime("-$i months"));
    
    if ($monthStmt) {
        $monthStmt->bind_param("ssssss", $month, $month, $month, $month, $month, $month);
        $monthStmt->execute();
        $res = $monthStmt->get_result();
        $row = $res->fetch_assoc();
        $monthlyData[$monthName] = (int)$row['count'];
    } else {
        $monthlyData[$monthName] = 0;
    }
}
if ($monthStmt) $monthStmt->close();


// Status distribution
$statusData = [
    'Pending' => $stats['pending_services'],
    'Completed' => $stats['completed_services'],
    'In Progress' => 0
];

$result = $conn->query("SELECT COUNT(*) as count FROM (
    SELECT id FROM gst_registrations WHERE status = 'In Progress'
    UNION ALL SELECT id FROM gst_returns WHERE status = 'In Progress'
    UNION ALL SELECT id FROM income_tax_returns WHERE status = 'In Progress'
    UNION ALL SELECT id FROM msme_registrations WHERE status = 'In Progress'
    UNION ALL SELECT id FROM fssai_licences WHERE status = 'In Progress'
    UNION ALL SELECT id FROM accounting_services WHERE status = 'In Progress'
) as in_progress");

if ($result) {
    $row = $result->fetch_assoc();
    $statusData['In Progress'] = $row['count'];
} else {
    error_log("In Progress query error: " . $conn->error);
    $statusData['In Progress'] = 0;
}

// Recent Activities
$recentActivities = $conn->query("SELECT al.*, u.name as user_name, u.email as user_email 
    FROM activity_log al 
    LEFT JOIN users u ON al.user_id = u.id 
    ORDER BY al.created_at DESC LIMIT 10");

if (!$recentActivities) {
    error_log("Recent activities query error: " . $conn->error);
}

// Recent Contact Messages
$recentMessages = $conn->query("SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 5");

if (!$recentMessages) {
    error_log("Recent messages query error: " . $conn->error);
}

// Notifications count
$notifResult = $conn->query("SELECT COUNT(*) as count FROM notifications_log WHERE DATE(created_at) = CURDATE()");
if ($notifResult) {
    $row = $notifResult->fetch_assoc();
    $notificationCount = $row['count'];
} else {
    error_log("Notification count query error: " . $conn->error);
    $notificationCount = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
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
        
        .stats-icon.blue {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .stats-icon.green {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
        }
        
        .stats-icon.orange {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        }
        
        .stats-icon.purple {
            background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
        }
        
        .stats-icon.yellow {
            background: linear-gradient(135deg, #FA8BFF 0%, #2BD2FF 90%);
        }
        
        .stats-icon.red {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a6f 100%);
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
        
        /* Chart Cards */
        .chart-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }
        
        .chart-card h6 {
            color: #333;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
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
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .badge {
            padding: 5px 10px;
            font-size: 11px;
            font-weight: 600;
        }
        
        .btn-sm {
            padding: 5px 12px;
            font-size: 12px;
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
            <li><a href="admin_dashboard.php" class="active"><i class="fas fa-home"></i> Dashboard</a></li>
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
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Navigation -->
        <div class="top-nav">
            <h5><i class="fas fa-home me-2"></i>Dashboard</h5>
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
        
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_users']; ?></div>
                    <div class="stats-label">Total Users</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon green">
                        <i class="fas fa-file-alt"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['total_services']; ?></div>
                    <div class="stats-label">Total Services</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon orange">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['pending_services']; ?></div>
                    <div class="stats-label">Pending</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon purple">
                        <i class="fas fa-check-circle"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['completed_services']; ?></div>
                    <div class="stats-label">Completed</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon yellow">
                        <i class="fas fa-envelope"></i>
                    </div>
                    <div class="stats-number"><?php echo $stats['new_messages']; ?></div>
                    <div class="stats-label">New Messages</div>
                </div>
            </div>
            
            <div class="col-lg-2 col-md-4 col-sm-6 mb-3">
                <div class="stats-card">
                    <div class="stats-icon red">
                        <i class="fas fa-bell"></i>
                    </div>
                    <div class="stats-number"><?php echo $notificationCount; ?></div>
                    <div class="stats-label">Notifications Today</div>
                </div>
            </div>
        </div>
        
        <!-- Charts Row -->
        <div class="row mb-4">
            <!-- Services Trend Chart -->
            <div class="col-lg-8 mb-4">
                <div class="chart-card">
                    <h6><i class="fas fa-chart-line me-2"></i>Services Trend (Last 6 Months)</h6>
                    <canvas id="trendChart" height="80"></canvas>
                </div>
            </div>
            
            <!-- Status Distribution Chart -->
            <div class="col-lg-4 mb-4">
                <div class="chart-card">
                    <h6><i class="fas fa-chart-pie me-2"></i>Status Distribution</h6>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Services Distribution Chart -->
        <div class="row mb-4">
            <div class="col-lg-12">
                <div class="chart-card">
                    <h6><i class="fas fa-chart-bar me-2"></i>Services by Type</h6>
                    <canvas id="servicesChart" height="60"></canvas>
                </div>
            </div>
        </div>
        
        <!-- Recent Activities and Messages -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="table-card">
                    <h6><i class="fas fa-history me-2"></i>Recent Activities</h6>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Action</th>
                                    <th>Description</th>
                                    <th>Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if ($recentActivities && $recentActivities->num_rows > 0):
                                    while($activity = $recentActivities->fetch_assoc()): 
                                ?>
                                <tr>
                                    <td>
                                        <?php if($activity['user_name']): ?>
                                            <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($activity['user_email']); ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">System</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary"><?php echo htmlspecialchars($activity['action']); ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                                    <td>
                                        <small class="text-muted">
                                            <?php echo date('M d, Y H:i', strtotime($activity['created_at'])); ?>
                                        </small>
                                    </td>
                                </tr>
                                <?php 
                                    endwhile;
                                else:
                                ?>
                                <tr>
                                    <td colspan="4" class="text-center text-muted">No recent activities</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4 mb-4">
                <div class="table-card">
                    <h6><i class="fas fa-envelope me-2"></i>Recent Messages</h6>
                    <div class="list-group list-group-flush">
                        <?php 
                        if ($recentMessages && $recentMessages->num_rows > 0):
                            while($message = $recentMessages->fetch_assoc()): 
                        ?>
                        <div class="list-group-item border-0 px-0">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                    <p class="mb-1 small text-muted">
                                        <?php echo htmlspecialchars(substr($message['message'], 0, 50)) . '...'; ?>
                                    </p>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y', strtotime($message['created_at'])); ?>
                                    </small>
                                </div>
                                <span class="badge bg-success">New</span>
                            </div>
                        </div>
                        <?php 
                            endwhile;
                        else:
                        ?>
                        <div class="text-center text-muted py-3">No recent messages</div>
                        <?php endif; ?>
                    </div>
                    <div class="mt-3">
                        <a href="admin_messages.php" class="btn btn-sm btn-outline-primary w-100">
                            View All Messages
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Trend Chart
        const trendCtx = document.getElementById('trendChart').getContext('2d');
        new Chart(trendCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_keys($monthlyData)); ?>,
                datasets: [{
                    label: 'Applications',
                    data: <?php echo json_encode(array_values($monthlyData)); ?>,
                    borderColor: 'rgb(102, 126, 234)',
                    backgroundColor: 'rgba(102, 126, 234, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
        
        // Status Distribution Pie Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($statusData)); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($statusData)); ?>,
                    backgroundColor: [
                        'rgba(255, 193, 7, 0.8)',
                        'rgba(40, 167, 69, 0.8)',
                        'rgba(23, 162, 184, 0.8)'
                    ],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
        
        // Services by Type Bar Chart
        const servicesCtx = document.getElementById('servicesChart').getContext('2d');
        new Chart(servicesCtx, {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_keys($servicesByType)); ?>,
                datasets: [{
                    label: 'Number of Applications',
                    data: <?php echo json_encode(array_values($servicesByType)); ?>,
                    backgroundColor: [
                        'rgba(102, 126, 234, 0.8)',
                        'rgba(118, 75, 162, 0.8)',
                        'rgba(17, 153, 142, 0.8)',
                        'rgba(56, 239, 125, 0.8)',
                        'rgba(240, 147, 251, 0.8)',
                        'rgba(245, 87, 108, 0.8)',
                        'rgba(79, 172, 254, 0.8)',
                        'rgba(0, 242, 254, 0.8)'
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                }
            }
        });
    </script>
</body>
</html>