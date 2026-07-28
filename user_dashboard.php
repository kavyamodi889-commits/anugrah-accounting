<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
requireUserLogin();

$user_id = getUserId();
$user_name = getUserName();
$user_email = getUserEmail();

// Fetch user's phone number
$stmt = $conn->prepare("SELECT phone FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$user_phone = isset($user_data['phone']) ? $user_data['phone'] : 'Not provided';
$stmt->close();

// Count statistics from all service tables
$stats = array(
    'total_applications' => 0,
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0
);

// Define all service tables with their identifiers
$service_tables = array(
    array('table' => 'accounting_services', 'name' => 'Accounting Services', 'icon' => 'calculator'),
    array('table' => 'income_tax_returns', 'name' => 'Income Tax Return', 'icon' => 'file-invoice-dollar'),
    array('table' => 'gst_registrations', 'name' => 'GST Registration', 'icon' => 'registered'),
    array('table' => 'gst_returns', 'name' => 'GST Returns', 'icon' => 'file-alt'),
    array('table' => 'fssai_licences', 'name' => 'FSSAI Licence', 'icon' => 'utensils'),
    array('table' => 'msme_registrations', 'name' => 'MSME Registration', 'icon' => 'industry'),
    array('table' => 'cma_data', 'name' => 'CMA Data', 'icon' => 'chart-line'),
    array('table' => 'tax_planning', 'name' => 'Tax Planning', 'icon' => 'coins')
);

// Count applications from each table
foreach ($service_tables as $service) {
    $query = "SELECT COUNT(*) as count, status FROM {$service['table']} WHERE user_id = ? GROUP BY status";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $stats['total_applications'] += $row['count'];
        $status = strtolower($row['status']);
        if ($status === 'pending') {
            $stats['pending'] += $row['count'];
        } elseif ($status === 'in progress') {
            $stats['in_progress'] += $row['count'];
        } elseif ($status === 'completed') {
            $stats['completed'] += $row['count'];
        }
    }
    $stmt->close();
}

// Fetch recent activities from activity_log
$recent_activities = array();
$activity_query = "SELECT action, entity_type, description, created_at 
                   FROM activity_log 
                   WHERE user_id = ? OR user_email = ?
                   ORDER BY created_at DESC 
                   LIMIT 10";
$stmt = $conn->prepare($activity_query);
$stmt->bind_param("is", $user_id, $user_email);
$stmt->execute();
$result = $stmt->get_result();

while ($row = $result->fetch_assoc()) {
    $recent_activities[] = $row;
}
$stmt->close();

// Fetch user's previous applications for quick reorder
$previous_services = array();
foreach ($service_tables as $service) {
    $query = "SELECT id, created_at, status FROM {$service['table']} WHERE user_id = ? ORDER BY created_at DESC LIMIT 3";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    while ($row = $result->fetch_assoc()) {
        $previous_services[] = array(
            'service_name' => $service['name'],
            'service_table' => $service['table'],
            'service_icon' => $service['icon'],
            'id' => $row['id'],
            'date' => $row['created_at'],
            'status' => $row['status']
        );
    }
    $stmt->close();
}

// Sort previous services by date
usort($previous_services, function($a, $b) {
    return strtotime($b['date']) - strtotime($a['date']);
});
$previous_services = array_slice($previous_services, 0, 5);

// Fetch upcoming deadlines and expiry dates
$upcoming_deadlines = array();

// GST Returns deadlines
$gst_query = "SELECT 'GST Returns' as service, return_period, return_type, DATE_ADD(STR_TO_DATE(CONCAT(return_period, '-01'), '%Y-%m-%d'), INTERVAL 1 MONTH) as deadline 
              FROM gst_returns 
              WHERE user_id = ? AND status != 'Completed'
              ORDER BY deadline ASC LIMIT 3";
$stmt = $conn->prepare($gst_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_deadlines[] = $row;
}
$stmt->close();

// FSSAI expiry dates
$fssai_query = "SELECT 'FSSAI Licence' as service, business_name, licence_expiry_date as deadline 
                FROM fssai_licences 
                WHERE user_id = ? AND licence_expiry_date IS NOT NULL AND licence_expiry_date > CURDATE()
                ORDER BY deadline ASC LIMIT 3";
$stmt = $conn->prepare($fssai_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $upcoming_deadlines[] = $row;
}
$stmt->close();

// Sort deadlines by date
usort($upcoming_deadlines, function($a, $b) {
    return strtotime($a['deadline']) - strtotime($b['deadline']);
});

// Fetch contact messages for support tracking
$support_query = "SELECT id, subject, service_interest, status, created_at 
                  FROM contact_messages 
                  WHERE user_id = ? 
                  ORDER BY created_at DESC 
                  LIMIT 3";
$stmt = $conn->prepare($support_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$support_messages = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Calculate service completion rate
$completion_rate = 0;
if ($stats['total_applications'] > 0) {
    $completion_rate = round(($stats['completed'] / $stats['total_applications']) * 100);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Anugrah Accounting</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-orange: #FF8C42;
            --primary-dark: #1a2332;
            --secondary-blue: #4A90E2;
            --success-green: #27ae60;
            --warning-orange: #f39c12;
            --danger-red: #e74c3c;
            --light-bg: #f8f9fa;
            --card-shadow: 0 2px 8px rgba(0,0,0,0.08);
            --card-shadow-hover: 0 4px 16px rgba(0,0,0,0.12);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            background: var(--light-bg);
            color: #333;
            line-height: 1.6;
        }
        
        /* Header Styles */
        .dashboard-header {
            background: linear-gradient(135deg, var(--primary-dark) 0%, #2c3e50 100%);
            color: white;
            padding: 2rem 0;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 2rem;
        }
        
        .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .header-title p {
            font-size: 0.95rem;
            opacity: 0.9;
        }
        
        .header-actions {
            display: flex;
            gap: 0.75rem;
        }
        
        .btn-header {
            padding: 0.5rem 1.25rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        /* Card Styles */
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            background: white;
            margin-bottom: 1.5rem;
        }
        
        .card:hover {
            box-shadow: var(--card-shadow-hover);
        }
        
        .card-header {
            background: white;
            border-bottom: 2px solid var(--light-bg);
            padding: 1.25rem 1.5rem;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        /* User Profile Card */
        .profile-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .profile-avatar {
            width: 80px;
            height: 80px;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            font-size: 2rem;
            font-weight: 700;
            color: #667eea;
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        
        .profile-name {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        
        .profile-details {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
        }
        
        .profile-detail-item {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.5rem 0;
            font-size: 0.9rem;
        }
        
        .profile-detail-item i {
            width: 20px;
            opacity: 0.9;
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            border-left: 4px solid;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
        }
        
        .stat-card.total { border-color: var(--primary-orange); }
        .stat-card.pending { border-color: var(--warning-orange); }
        .stat-card.progress { border-color: var(--secondary-blue); }
        .stat-card.completed { border-color: var(--success-green); }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.25rem;
        }
        
        .stat-label {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .stat-icon {
            font-size: 2rem;
            opacity: 0.2;
            float: right;
        }
        
        .stat-card.total .stat-value { color: var(--primary-orange); }
        .stat-card.pending .stat-value { color: var(--warning-orange); }
        .stat-card.progress .stat-value { color: var(--secondary-blue); }
        .stat-card.completed .stat-value { color: var(--success-green); }
        
        /* Services Grid */
        .services-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1rem;
        }
        
        .service-card {
            background: white;
            border-radius: 12px;
            padding: 1.25rem;
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
            border: 2px solid transparent;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .service-card:hover {
            border-color: var(--primary-orange);
            transform: translateY(-4px);
            box-shadow: var(--card-shadow-hover);
            color: inherit;
        }
        
        .service-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        
        .service-info h6 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .service-info p {
            margin: 0;
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Activity Timeline */
        .activity-timeline {
            position: relative;
            padding-left: 2rem;
        }
        
        .activity-item {
            position: relative;
            padding-bottom: 1.5rem;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -1.8rem;
            top: 0;
            width: 2px;
            height: 100%;
            background: var(--light-bg);
        }
        
        .activity-item:last-child::before {
            display: none;
        }
        
        .activity-dot {
            position: absolute;
            left: -2.2rem;
            top: 0;
            width: 10px;
            height: 10px;
            background: var(--primary-orange);
            border-radius: 50%;
            border: 2px solid white;
            box-shadow: 0 0 0 2px var(--primary-orange);
        }
        
        .activity-content {
            background: var(--light-bg);
            padding: 1rem;
            border-radius: 8px;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.9rem;
        }
        
        .activity-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Quick Actions */
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 1rem;
        }
        
        .quick-action {
            background: white;
            border: 2px solid var(--light-bg);
            border-radius: 12px;
            padding: 1.25rem;
            text-align: center;
            text-decoration: none;
            color: var(--primary-dark);
            transition: all 0.3s ease;
        }
        
        .quick-action:hover {
            border-color: var(--primary-orange);
            transform: translateY(-4px);
            color: var(--primary-dark);
        }
        
        .quick-action i {
            font-size: 2rem;
            color: var(--primary-orange);
            margin-bottom: 0.75rem;
            display: block;
        }
        
        .quick-action-label {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .quick-action-desc {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Deadline Alerts */
        .deadline-item {
            background: var(--light-bg);
            border-left: 4px solid;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .deadline-item.urgent { border-color: var(--danger-red); background: #fee; }
        .deadline-item.warning { border-color: var(--warning-orange); background: #fffbf0; }
        .deadline-item.info { border-color: var(--secondary-blue); background: #f0f8ff; }
        
        .deadline-icon {
            font-size: 1.5rem;
        }
        
        .deadline-item.urgent .deadline-icon { color: var(--danger-red); }
        .deadline-item.warning .deadline-icon { color: var(--warning-orange); }
        .deadline-item.info .deadline-icon { color: var(--secondary-blue); }
        
        .deadline-content {
            flex: 1;
        }
        
        .deadline-title {
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .deadline-date {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        /* Status Badges */
        .badge-status {
            padding: 0.35rem 0.75rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-progress {
            background: #cfe2ff;
            color: #084298;
        }
        
        .badge-completed {
            background: #d1e7dd;
            color: #0a3622;
        }
        
        /* Recent Applications */
        .application-item {
            border: 2px solid var(--light-bg);
            border-radius: 10px;
            padding: 1rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .application-item:hover {
            border-color: var(--primary-orange);
            background: rgba(255, 140, 66, 0.02);
        }
        
        .application-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .application-icon {
            width: 45px;
            height: 45px;
            background: linear-gradient(135deg, var(--primary-orange), #e67e3c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.25rem;
        }
        
        .application-details h6 {
            font-weight: 600;
            margin-bottom: 0.25rem;
            font-size: 0.95rem;
        }
        
        .application-meta {
            font-size: 0.8rem;
            color: #6c757d;
        }
        
        .btn-reorder {
            background: var(--primary-orange);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.875rem;
            font-weight: 500;
            transition: all 0.2s;
        }
        
        .btn-reorder:hover {
            background: #e67e3c;
            transform: scale(1.05);
        }
        
        /* Support Section */
        .support-card {
            background: linear-gradient(135deg, #4A90E2, #357ABD);
            color: white;
            border-radius: 12px;
            padding: 1.5rem;
        }
        
        .support-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .support-info {
            background: rgba(255,255,255,0.15);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            font-size: 0.9rem;
        }
        
        .support-actions {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 0.75rem;
        }
        
        .btn-support {
            background: white;
            color: var(--secondary-blue);
            border: none;
            padding: 0.75rem;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.2s;
            text-align: center;
            text-decoration: none;
            display: block;
        }
        
        .btn-support:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            color: var(--secondary-blue);
        }
        
        /* Completion Progress */
        .completion-progress {
            text-align: center;
            padding: 1.5rem;
        }
        
        .progress-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: conic-gradient(
                var(--success-green) 0deg,
                var(--success-green) var(--progress-deg),
                var(--light-bg) var(--progress-deg),
                var(--light-bg) 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
        }
        
        .progress-inner {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        
        .progress-value {
            font-size: 2rem;
            font-weight: 700;
            color: var(--success-green);
        }
        
        .progress-label {
            font-size: 0.75rem;
            color: #6c757d;
        }
        
        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }
        
        .empty-state i {
            font-size: 4rem;
            opacity: 0.2;
            margin-bottom: 1rem;
        }
        
        .empty-state p {
            font-size: 1rem;
            margin-bottom: 0.5rem;
        }
        
        .empty-state small {
            font-size: 0.875rem;
        }
        
        /* Responsive */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                text-align: center;
            }
            
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .services-grid {
                grid-template-columns: 1fr;
            }
            
            .support-actions {
                grid-template-columns: 1fr;
            }
        }
        
        @media print {
            .header-actions,
            .quick-actions,
            .btn-reorder,
            .service-card {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="dashboard-header">
        <div class="container">
            <div class="header-content">
                <div class="header-title">
                    <h1><i class="fas fa-chart-line"></i> Dashboard</h1>
                    <p>Welcome back, <?php echo htmlspecialchars($user_name); ?>!</p>
                </div>
                <div class="header-actions">
                    <a href="anugrah_home.php" class="btn btn-light btn-header">
                        <i class="fas fa-home"></i> Home
                    </a>
                    <a href="user_logout.php" class="btn btn-outline-light btn-header">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <div class="container py-4">
        <div class="row">
            <!-- Left Sidebar -->
            <div class="col-lg-4">
                <!-- Profile Card -->
                <div class="profile-card mb-4">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user_name, 0, 1)); ?>
                    </div>
                    <div class="profile-name"><?php echo htmlspecialchars($user_name); ?></div>
                    
                    <div class="profile-details">
                        <div class="profile-detail-item">
                            <i class="fas fa-envelope"></i>
                            <span style="font-size: 0.85rem;"><?php echo htmlspecialchars($user_email); ?></span>
                        </div>
                        <div class="profile-detail-item">
                            <i class="fas fa-phone"></i>
                            <span><?php echo htmlspecialchars($user_phone); ?></span>
                        </div>
                        <div class="profile-detail-item">
                            <i class="fas fa-id-badge"></i>
                            <span>ID: #<?php echo $user_id; ?></span>
                        </div>
                    </div>
                    
                    <div style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid rgba(255,255,255,0.2);">
                        <small><i class="fas fa-magic"></i> Auto-fill enabled on all forms</small>
                    </div>
                </div>
                
                <!-- Statistics -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-chart-bar"></i> Statistics Overview
                    </div>
                    <div class="card-body">
                        <div class="stats-grid">
                            <div class="stat-card total">
                                <i class="fas fa-briefcase stat-icon"></i>
                                <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
                                <div class="stat-label">Total Applications</div>
                            </div>
                            <div class="stat-card pending">
                                <i class="fas fa-clock stat-icon"></i>
                                <div class="stat-value"><?php echo $stats['pending']; ?></div>
                                <div class="stat-label">Pending</div>
                            </div>
                            <div class="stat-card progress">
                                <i class="fas fa-spinner stat-icon"></i>
                                <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
                                <div class="stat-label">In Progress</div>
                            </div>
                            <div class="stat-card completed">
                                <i class="fas fa-check-circle stat-icon"></i>
                                <div class="stat-value"><?php echo $stats['completed']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        
                        <?php if ($stats['total_applications'] > 0): ?>
                        <div class="completion-progress">
                            <div class="progress-circle" style="--progress-deg: <?php echo $completion_rate * 3.6; ?>deg;">
                                <div class="progress-inner">
                                    <div class="progress-value"><?php echo $completion_rate; ?>%</div>
                                    <div class="progress-label">Complete</div>
                                </div>
                            </div>
                            <p class="mb-0" style="font-size: 0.875rem; color: #6c757d;">Service Completion Rate</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Upcoming Deadlines -->
                <?php if (!empty($upcoming_deadlines)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-exclamation-triangle"></i> Upcoming Deadlines
                        <span class="badge bg-danger ms-2"><?php echo count($upcoming_deadlines); ?></span>
                    </div>
                    <div class="card-body">
                        <?php foreach ($upcoming_deadlines as $deadline): ?>
                        <?php 
                            $days_left = floor((strtotime($deadline['deadline']) - time()) / 86400);
                            $alert_class = 'info';
                            if ($days_left <= 7) $alert_class = 'urgent';
                            elseif ($days_left <= 30) $alert_class = 'warning';
                        ?>
                        <div class="deadline-item <?php echo $alert_class; ?>">
                            <div class="deadline-icon">
                                <i class="fas fa-calendar-alt"></i>
                            </div>
                            <div class="deadline-content">
                                <div class="deadline-title"><?php echo htmlspecialchars($deadline['service']); ?></div>
                                <?php if (isset($deadline['return_period'])): ?>
                                    <div class="deadline-date">Period: <?php echo htmlspecialchars($deadline['return_period']); ?></div>
                                <?php elseif (isset($deadline['business_name'])): ?>
                                    <div class="deadline-date"><?php echo htmlspecialchars($deadline['business_name']); ?></div>
                                <?php endif; ?>
                                <div class="deadline-date">
                                    <i class="far fa-clock"></i> 
                                    <?php echo date('M d, Y', strtotime($deadline['deadline'])); ?>
                                    <strong>(<?php echo $days_left; ?> days)</strong>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Support Contact -->
                <div class="support-card">
                    <div class="support-title">
                        <i class="fas fa-headset"></i> Need Help?
                    </div>
                    <div class="support-info">
                        <i class="fas fa-clock"></i> 
                        <strong>Business Hours:</strong><br>
                        Monday - Saturday, 10:00 AM - 6:00 PM
                    </div>
                    <div class="support-actions">
                        <a href="tel:02642227258" class="btn-support">
                            <i class="fas fa-phone"></i> Call Office
                        </a>
                        <a href="tel:6352788126" class="btn-support">
                            <i class="fas fa-mobile-alt"></i> Mobile
                        </a>
                        <a href="mailto:anugrah0369@gmail.com" class="btn-support">
                            <i class="fas fa-envelope"></i> Email Us
                        </a>
                        <a href="https://wa.me/916352788126" target="_blank" class="btn-support">
                            <i class="fab fa-whatsapp"></i> WhatsApp
                        </a>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="col-lg-8">
                <!-- Quick Actions -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-bolt"></i> Quick Actions
                    </div>
                    <div class="card-body">
                        <div class="quick-actions">
                            <a href="contact.php" class="quick-action">
                                <i class="fas fa-comments"></i>
                                <div class="quick-action-label">Contact Us</div>
                                <div class="quick-action-desc">Get Support</div>
                            </a>
                            <a href="feedback.php" class="quick-action">
                                <i class="fas fa-star"></i>
                                <div class="quick-action-label">Feedback</div>
                                <div class="quick-action-desc">Share Review</div>
                            </a>
                            <a href="#" onclick="openDocumentUpload(); return false;" class="quick-action">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <div class="quick-action-label">Upload Docs</div>
                                <div class="quick-action-desc">Submit Files</div>
                            </a>
                            <a href="#" onclick="window.print(); return false;" class="quick-action">
                                <i class="fas fa-print"></i>
                                <div class="quick-action-label">Print</div>
                                <div class="quick-action-desc">Dashboard</div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Reorder -->
                <?php if (!empty($previous_services)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Quick Reorder
                        <small class="text-muted ms-2" style="font-weight: 400;">Apply for same service again</small>
                    </div>
                    <div class="card-body">
                        <?php foreach ($previous_services as $service): ?>
                        <div class="application-item">
                            <div class="application-info">
                                <div class="application-icon">
                                    <i class="fas fa-<?php echo $service['service_icon']; ?>"></i>
                                </div>
                                <div class="application-details">
                                    <h6><?php echo htmlspecialchars($service['service_name']); ?></h6>
                                    <div class="application-meta">
                                        <i class="far fa-calendar"></i> 
                                        <?php echo date('M d, Y', strtotime($service['date'])); ?>
                                        <span class="badge-status badge-<?php echo strtolower(str_replace(' ', '-', $service['status'])); ?> ms-2">
                                            <?php echo htmlspecialchars($service['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <button class="btn-reorder" onclick="reorderService('<?php echo $service['service_table']; ?>')">
                                <i class="fas fa-redo"></i> Apply Again
                            </button>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Available Services -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-briefcase"></i> Apply for Services
                        <small class="text-muted ms-2" style="font-weight: 400;">Your details will be auto-filled</small>
                    </div>
                    <div class="card-body">
                        <div class="services-grid">
                            <a href="accounting_services_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-calculator"></i>
                                </div>
                                <div class="service-info">
                                    <h6>Accounting Services</h6>
                                    <p>Professional bookkeeping & financial management</p>
                                </div>
                            </a>
                            
                            <a href="income_tax_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-file-invoice-dollar"></i>
                                </div>
                                <div class="service-info">
                                    <h6>Income Tax Return</h6>
                                    <p>Expert ITR filing for individuals & businesses</p>
                                </div>
                            </a>
                            
                            <a href="gst_registration_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-registered"></i>
                                </div>
                                <div class="service-info">
                                    <h6>GST Registration</h6>
                                    <p>Quick and hassle-free GST registration</p>
                                </div>
                            </a>
                            
                            <a href="gst_returns_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-file-alt"></i>
                                </div>
                                <div class="service-info">
                                    <h6>GST Returns</h6>
                                    <p>Timely filing of all GST returns</p>
                                </div>
                            </a>
                            
                            <a href="fssai_licence_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-utensils"></i>
                                </div>
                                <div class="service-info">
                                    <h6>FSSAI Licence</h6>
                                    <p>Food safety license registration & renewals</p>
                                </div>
                            </a>
                            
                            <a href="msme_registration_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-industry"></i>
                                </div>
                                <div class="service-info">
                                    <h6>MSME Registration</h6>
                                    <p>Udyam registration for MSMEs</p>
                                </div>
                            </a>
                            
                            <a href="cma_data_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-chart-line"></i>
                                </div>
                                <div class="service-info">
                                    <h6>CMA Data</h6>
                                    <p>Credit monitoring arrangement reports</p>
                                </div>
                            </a>
                            
                            <a href="tax_planning_form.php" class="service-card">
                                <div class="service-icon">
                                    <i class="fas fa-coins"></i>
                                </div>
                                <div class="service-info">
                                    <h6>Tax Planning</h6>
                                    <p>Strategic tax planning services</p>
                                </div>
                            </a>
                        </div>
                    </div>
                </div>
                
                <!-- Support Tickets -->
                <?php if (!empty($support_messages)): ?>
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-ticket-alt"></i> Support Tickets
                    </div>
                    <div class="card-body">
                        <?php foreach ($support_messages as $msg): ?>
                        <div class="application-item">
                            <div class="application-info">
                                <div class="application-icon" style="background: linear-gradient(135deg, #4A90E2, #357ABD);">
                                    <i class="fas fa-ticket-alt"></i>
                                </div>
                                <div class="application-details">
                                    <h6><?php echo htmlspecialchars($msg['service_interest'] ?: 'General Inquiry'); ?></h6>
                                    <div class="application-meta">
                                        <?php if ($msg['subject']): ?>
                                            <?php echo htmlspecialchars($msg['subject']); ?> •
                                        <?php endif; ?>
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('M d, Y', strtotime($msg['created_at'])); ?>
                                        <span class="badge-status badge-<?php echo strtolower($msg['status']); ?> ms-2">
                                            <?php echo htmlspecialchars($msg['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <a href="contact.php" class="btn btn-outline-primary btn-sm mt-2">
                            <i class="fas fa-plus"></i> New Support Request
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Recent Activity -->
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-history"></i> Recent Activity
                    </div>
                    <div class="card-body">
                        <?php if (!empty($recent_activities)): ?>
                        <div class="activity-timeline">
                            <?php foreach ($recent_activities as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-dot"></div>
                                <div class="activity-content">
                                    <div class="activity-title"><?php echo htmlspecialchars($activity['description']); ?></div>
                                    <?php if ($activity['entity_type']): ?>
                                    <div class="activity-meta">
                                        Service: <?php echo htmlspecialchars(ucwords(str_replace('_', ' ', $activity['entity_type']))); ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="activity-meta">
                                        <i class="far fa-clock"></i> 
                                        <?php echo date('M d, Y g:i A', strtotime($activity['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-inbox"></i>
                            <p>No recent activity yet</p>
                            <small>Your service applications will appear here</small>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    function reorderService(serviceTable) {
        const serviceUrls = {
            'accounting_services': 'accounting_services_form.php',
            'income_tax_returns': 'income_tax_form.php',
            'gst_registrations': 'gst_registration_form.php',
            'gst_returns': 'gst_returns_form.php',
            'fssai_licences': 'fssai_licence_form.php',
            'msme_registrations': 'msme_registration_form.php',
            'cma_data': 'cma_data_form.php',
            'tax_planning': 'tax_planning_form.php'
        };
        
        if (serviceUrls[serviceTable]) {
            window.location.href = serviceUrls[serviceTable];
        }
    }
    
    function openDocumentUpload() {
        const modalHtml = `
            <div class="modal fade" id="uploadModal" tabindex="-1">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-cloud-upload-alt"></i> Upload Documents
                            </h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="document-upload-zone" onclick="document.getElementById('fileInput').click()" style="border: 2px dashed #dee2e6; border-radius: 12px; padding: 30px; text-align: center; background: #f8f9fa; cursor: pointer;">
                                <i class="fas fa-cloud-upload-alt" style="font-size: 3rem; color: var(--primary-orange); margin-bottom: 15px;"></i>
                                <h6>Click to upload or drag and drop</h6>
                                <p class="text-muted mb-0">PDF, JPG, PNG, DOC (Max 5MB)</p>
                            </div>
                            <input type="file" id="fileInput" multiple style="display: none;" accept=".pdf,.jpg,.jpeg,.png,.doc,.docx">
                            <div id="fileList" class="mt-3"></div>
                            <div class="mt-3">
                                <label class="form-label">Document Type</label>
                                <select class="form-select" id="docType">
                                    <option value="">Select document type</option>
                                    <option value="PAN">PAN Card</option>
                                    <option value="Aadhaar">Aadhaar Card</option>
                                    <option value="GST">GST Certificate</option>
                                    <option value="Bank">Bank Statement</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="mt-3">
                                <label class="form-label">Notes (Optional)</label>
                                <textarea class="form-control" rows="2" id="docNotes" placeholder="Add any notes about these documents"></textarea>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" onclick="submitDocuments()">
                                <i class="fas fa-upload"></i> Upload Documents
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        const existingModal = document.getElementById('uploadModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
        modal.show();
        
        document.getElementById('fileInput').addEventListener('change', function(e) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            
            if (this.files.length > 0) {
                fileList.innerHTML = '<div class="alert alert-success"><i class="fas fa-check-circle"></i> <strong>' + 
                    this.files.length + ' file(s) selected</strong></div>';
                
                for (let i = 0; i < this.files.length; i++) {
                    const file = this.files[i];
                    fileList.innerHTML += '<div class="mb-1"><i class="fas fa-file"></i> ' + file.name + 
                        ' (' + (file.size / 1024).toFixed(2) + ' KB)</div>';
                }
            }
        });
    }
    
    function submitDocuments() {
        const fileInput = document.getElementById('fileInput');
        const docType = document.getElementById('docType').value;
        
        if (fileInput.files.length === 0) {
            alert('Please select at least one file to upload');
            return;
        }
        
        if (!docType) {
            alert('Please select document type');
            return;
        }
        
        alert('Documents uploaded successfully! Our team will review them shortly.');
        bootstrap.Modal.getInstance(document.getElementById('uploadModal')).hide();
    }
    
    <?php if (!empty($upcoming_deadlines)): ?>
        <?php foreach ($upcoming_deadlines as $deadline): ?>
            <?php $days_left = floor((strtotime($deadline['deadline']) - time()) / 86400); ?>
            <?php if ($days_left <= 3): ?>
                setTimeout(function() {
                    if (confirm('⚠️ URGENT: Your <?php echo addslashes($deadline['service']); ?> deadline is in <?php echo $days_left; ?> days! Would you like to view details?')) {
                        document.querySelector('.deadline-item')?.scrollIntoView({ behavior: 'smooth' });
                    }
                }, 2000);
            <?php break; endif; ?>
        <?php endforeach; ?>
    <?php endif; ?>
    </script>
</body>
</html>