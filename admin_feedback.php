<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();

// Handle feedback response
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['respond'])) {
    $feedbackId = intval($_POST['feedback_id']);
    $adminResponse = trim($_POST['admin_response']);
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    
    if (!empty($adminResponse)) {
        $stmt = $conn->prepare("UPDATE feedback SET admin_response = ?, is_published = ?, responded_by = ?, responded_at = NOW() WHERE id = ?");
        $stmt->bind_param("siii", $adminResponse, $isPublished, $_SESSION['admin_id'], $feedbackId);
        
        if ($stmt->execute()) {
            $stmt->close();
            header('Location: admin_feedback.php?msg=updated');
            exit();
        } else {
            $stmt->close();
            header('Location: admin_feedback.php?msg=error');
            exit();
        }
    } else {
        header('Location: admin_feedback.php?msg=empty_response');
        exit();
    }
}

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['bulk_action'])) {
    $action = $_POST['bulk_action'];
    $selectedIds = isset($_POST['selected_feedback']) ? $_POST['selected_feedback'] : array();
    
    if (!empty($selectedIds) && !empty($action)) {
        $ids = implode(',', array_map('intval', $selectedIds));
        
        if ($action == 'publish') {
            $conn->query("UPDATE feedback SET is_published = 1 WHERE id IN ($ids)");
            header('Location: admin_feedback.php?msg=bulk_published');
            exit();
        } elseif ($action == 'unpublish') {
            $conn->query("UPDATE feedback SET is_published = 0 WHERE id IN ($ids)");
            header('Location: admin_feedback.php?msg=bulk_unpublished');
            exit();
        } elseif ($action == 'delete') {
            $conn->query("DELETE FROM feedback WHERE id IN ($ids)");
            header('Location: admin_feedback.php?msg=bulk_deleted');
            exit();
        }
    } else {
        header('Location: admin_feedback.php?msg=no_selection');
        exit();
    }
}

// Handle delete feedback
if (isset($_GET['delete'])) {
    $feedbackId = intval($_GET['delete']);
    $stmt = $conn->prepare("DELETE FROM feedback WHERE id = ?");
    $stmt->bind_param("i", $feedbackId);
    
    if ($stmt->execute()) {
        $stmt->close();
        header('Location: admin_feedback.php?msg=deleted');
        exit();
    } else {
        $stmt->close();
        header('Location: admin_feedback.php?msg=delete_error');
        exit();
    }
}

// Calculate statistics
$stats = array();
$stats['total'] = $conn->query("SELECT COUNT(*) as count FROM feedback")->fetch_assoc()['count'];
$stats['published'] = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE is_published = 1")->fetch_assoc()['count'];
$stats['responded'] = $conn->query("SELECT COUNT(*) as count FROM feedback WHERE admin_response IS NOT NULL AND admin_response != ''")->fetch_assoc()['count'];
$avgResult = $conn->query("SELECT AVG(rating) as avg FROM feedback")->fetch_assoc();
$stats['avg_rating'] = $avgResult['avg'] ? $avgResult['avg'] : 0;

// Get notification count for unread/pending items
$notificationCount = 0;
$notifQuery = $conn->query("SELECT COUNT(*) as count FROM (
    SELECT id FROM feedback WHERE admin_response IS NULL OR admin_response = ''
    UNION ALL
    SELECT id FROM contact_messages WHERE status = 'New'
) as notifications");
if ($notifQuery && $notifRow = $notifQuery->fetch_assoc()) {
    $notificationCount = $notifRow['count'];
}

// Get new messages count
$newMessagesQuery = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
if ($newMessagesQuery && $msgRow = $newMessagesQuery->fetch_assoc()) {
    $stats['new_messages'] = $msgRow['count'];
} else {
    $stats['new_messages'] = 0;
}

// Rating distribution
$ratingDist = $conn->query("SELECT rating, COUNT(*) as count FROM feedback GROUP BY rating ORDER BY rating DESC");
$ratingData = array();
while($row = $ratingDist->fetch_assoc()) {
    $ratingData[$row['rating']] = $row['count'];
}

// Service-wise feedback
$serviceStats = $conn->query("SELECT service_used, COUNT(*) as count, AVG(rating) as avg_rating FROM feedback WHERE service_used IS NOT NULL AND service_used != '' GROUP BY service_used ORDER BY count DESC LIMIT 5");
$serviceData = array();
while($row = $serviceStats->fetch_assoc()) {
    $serviceData[] = $row;
}

// Recent feedback trends (last 7 days)
$trendQuery = "SELECT DATE(created_at) as date, COUNT(*) as count, AVG(rating) as avg_rating 
    FROM feedback 
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) 
    GROUP BY DATE(created_at) 
    ORDER BY date ASC";
$trendData = $conn->query($trendQuery);
$trendArray = array();
while($row = $trendData->fetch_assoc()) {
    $trendArray[] = $row;
}

// Sentiment analysis keywords
$positiveKeywords = array('excellent', 'great', 'amazing', 'wonderful', 'fantastic', 'best', 'love', 'perfect', 'happy', 'satisfied');
$negativeKeywords = array('bad', 'poor', 'worst', 'terrible', 'horrible', 'disappointing', 'unsatisfied', 'slow', 'rude');

// Fetch all feedback
$feedbacks = $conn->query("SELECT f.*, u.name as user_name, u.email, u.phone, 
    a.full_name as responder_name 
    FROM feedback f 
    LEFT JOIN users u ON f.user_id = u.id 
    LEFT JOIN admin_users a ON f.responded_by = a.id 
    ORDER BY f.created_at DESC");

// Auto-response templates
$responseTemplates = array(
    'positive' => "Thank you so much for your wonderful feedback! We're thrilled to hear that you had a positive experience with our {service}. Your satisfaction is our top priority, and we look forward to serving you again!",
    'negative' => "We sincerely apologize for your experience with our {service}. Your feedback is invaluable to us, and we're committed to improving. Our team will reach out to you shortly to address your concerns and make things right.",
    'neutral' => "Thank you for taking the time to share your feedback about our {service}. We appreciate your input and will use it to enhance our services. If you have any specific suggestions, we'd love to hear them!",
    'thankyou' => "We truly appreciate you taking the time to share your thoughts with us. Your feedback helps us serve you better. Thank you for choosing Anugrah Accounting Services!",
    'improvement' => "Thank you for bringing this to our attention. We take all feedback seriously and are actively working on improvements to our {service}. We value your patience and continued support."
);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback Management - Admin</title>
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
        .sidebar-menu li { margin-bottom: 5px; }
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; position: relative; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu i { width: 20px; margin-right: 12px; font-size: 16px; }
        .notification-badge { position: absolute; right: 20px; background: #ff4444; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        
        .stats-container { display: grid; grid-template-columns: repeat(auto-fit, minmax(240px, 1fr)); gap: 20px; margin-bottom: 25px; }
        .stat-card { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); transition: transform 0.3s; }
        .stat-card:hover { transform: translateY(-5px); box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
        .stat-card .icon { width: 50px; height: 50px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 24px; margin-bottom: 15px; }
        .stat-card.total .icon { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .stat-card.published .icon { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; }
        .stat-card.responded .icon { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white; }
        .stat-card.rating .icon { background: linear-gradient(135deg, #ffd89b 0%, #19547b 100%); color: white; }
        .stat-card h3 { font-size: 32px; font-weight: 700; margin: 10px 0 5px 0; color: #333; }
        .stat-card p { color: #666; font-size: 14px; margin: 0; }
        
        .table-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .table-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .info-label { font-weight: 600; color: #666; margin-bottom: 5px; }
        .info-value { color: #333; margin-bottom: 15px; }
        .star-rating { color: #ffc107; font-size: 18px; }
        
        .chart-container { background: white; border-radius: 12px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); margin-bottom: 25px; }
        .chart-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; }
        
        .sentiment-positive { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .sentiment-negative { background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%); color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        .sentiment-neutral { background: linear-gradient(135deg, #bdc3c7 0%, #2c3e50 100%); color: white; padding: 3px 10px; border-radius: 20px; font-size: 11px; font-weight: 600; }
        
        .bulk-actions-bar { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; display: none; }
        .bulk-actions-bar.show { display: flex; align-items: center; justify-content: space-between; }
        
        .template-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 15px; }
        .template-pill { background: #f8f9fa; border: 2px solid #dee2e6; padding: 8px 15px; border-radius: 20px; cursor: pointer; transition: all 0.3s; font-size: 13px; }
        .template-pill:hover { background: #667eea; color: white; border-color: #667eea; }
        
        .export-btn { background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%); color: white; border: none; }
        .export-btn:hover { background: linear-gradient(135deg, #38ef7d 0%, #11998e 100%); color: white; }
        
        .toast-container { position: fixed; top: 20px; right: 20px; z-index: 9999; }
        .custom-toast { background: white; border-left: 4px solid #667eea; border-radius: 8px; padding: 15px 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); margin-bottom: 10px; min-width: 300px; animation: slideIn 0.3s ease-out; }
        .custom-toast.success { border-left-color: #10b981; }
        .custom-toast.error { border-left-color: #ef4444; }
        .custom-toast.warning { border-left-color: #f59e0b; }
        @keyframes slideIn { from { transform: translateX(400px); opacity: 0; } to { transform: translateX(0); opacity: 1; } }
    </style>
</head>
<body>
    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>
    
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
            <li><a href="admin_feedback.php" class="active"><i class="fas fa-comments"></i> Feedback</a></li>
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
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-comments me-2"></i>Feedback Analytics & Management</h5>
            <div class="admin-info">
                <button class="btn export-btn btn-sm me-2" onclick="exportFeedback()">
                    <i class="fas fa-download me-2"></i>Export Report
                </button>
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg'])): ?>
            <script>
                window.addEventListener('DOMContentLoaded', function() {
                    <?php
                    $messages = array(
                        'updated' => array('Feedback response updated successfully!', 'success'),
                        'deleted' => array('Feedback deleted successfully!', 'success'),
                        'bulk_published' => array('Selected feedback published successfully!', 'success'),
                        'bulk_unpublished' => array('Selected feedback unpublished successfully!', 'success'),
                        'bulk_deleted' => array('Selected feedback deleted successfully!', 'success'),
                        'no_selection' => array('Please select items and choose an action!', 'warning'),
                        'empty_response' => array('Please write a response before submitting!', 'warning'),
                        'error' => array('An error occurred. Please try again!', 'error'),
                        'delete_error' => array('Failed to delete feedback!', 'error')
                    );
                    
                    $msg = $_GET['msg'];
                    if (isset($messages[$msg])) {
                        echo "showToast('{$messages[$msg][0]}', '{$messages[$msg][1]}');";
                    }
                    ?>
                });
            </script>
        <?php endif; ?>
        
        <!-- Statistics Cards -->
        <div class="stats-container">
            <div class="stat-card total">
                <div class="icon"><i class="fas fa-comments"></i></div>
                <h3><?php echo $stats['total']; ?></h3>
                <p>Total Feedback</p>
            </div>
            <div class="stat-card published">
                <div class="icon"><i class="fas fa-check-circle"></i></div>
                <h3><?php echo $stats['published']; ?></h3>
                <p>Published Reviews</p>
            </div>
            <div class="stat-card responded">
                <div class="icon"><i class="fas fa-reply"></i></div>
                <h3><?php echo $stats['responded']; ?></h3>
                <p>Responded</p>
            </div>
            <div class="stat-card rating">
                <div class="icon"><i class="fas fa-star"></i></div>
                <h3><?php echo number_format($stats['avg_rating'], 1); ?></h3>
                <p>Average Rating</p>
            </div>
        </div>
        
        <!-- Charts Section -->
        <div class="chart-grid mb-4">
            <div class="chart-container">
                <h6><i class="fas fa-chart-bar me-2"></i>Rating Distribution</h6>
                <div style="position: relative; height: 250px;">
                    <canvas id="ratingChart"></canvas>
                </div>
            </div>
            
            <div class="chart-container">
                <h6><i class="fas fa-chart-pie me-2"></i>Top Services by Feedback</h6>
                <div style="position: relative; height: 250px;">
                    <canvas id="serviceChart"></canvas>
                </div>
            </div>
        </div>
        
        <div class="chart-container mb-4">
            <h6><i class="fas fa-chart-line me-2"></i>Feedback Trends (Last 7 Days)</h6>
            <div style="position: relative; height: 200px;">
                <canvas id="trendChart"></canvas>
            </div>
        </div>
        
        <!-- Bulk Actions Bar -->
        <div class="bulk-actions-bar" id="bulkActionsBar">
            <div>
                <i class="fas fa-check-circle me-2"></i>
                <span id="selectedCount">0</span> items selected
            </div>
            <div>
                <select id="bulkActionSelect" class="form-select form-select-sm d-inline-block" style="width: auto; margin-right: 10px;">
                    <option value="">Choose Action</option>
                    <option value="publish">Publish Selected</option>
                    <option value="unpublish">Unpublish Selected</option>
                    <option value="delete">Delete Selected</option>
                </select>
                <button type="button" class="btn btn-light btn-sm" onclick="performBulkAction()">Apply</button>
                <button type="button" class="btn btn-outline-light btn-sm" onclick="clearSelection()">Clear</button>
            </div>
        </div>
        
        <!-- Hidden Bulk Actions Form -->
        <form method="POST" id="bulkForm" style="display: none;">
            <input type="hidden" name="bulk_action" id="bulkActionInput">
            <div id="bulkCheckboxesContainer"></div>
        </form>
        
        <!-- Feedback Table -->
        <div class="table-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0"><i class="fas fa-comments me-2"></i>All User Feedback</h6>
                <div>
                    <input type="text" id="searchFeedback" class="form-control form-control-sm" placeholder="Search feedback..." style="width: 250px; display: inline-block;">
                    <select id="filterRating" class="form-select form-select-sm d-inline-block ms-2" style="width: auto;" onchange="filterTable()">
                        <option value="">All Ratings</option>
                        <option value="5">5 Stars</option>
                        <option value="4">4 Stars</option>
                        <option value="3">3 Stars</option>
                        <option value="2">2 Stars</option>
                        <option value="1">1 Star</option>
                    </select>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover" id="feedbackTable">
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>Service Used</th>
                            <th>Rating</th>
                            <th>Sentiment</th>
                            <th>Feedback</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $feedbacks->data_seek(0);
                        while($feedback = $feedbacks->fetch_assoc()): 
                            $feedbackText = strtolower($feedback['feedback_text']);
                            $positiveCount = 0;
                            $negativeCount = 0;
                            
                            foreach($positiveKeywords as $keyword) {
                                if(strpos($feedbackText, $keyword) !== false) $positiveCount++;
                            }
                            foreach($negativeKeywords as $keyword) {
                                if(strpos($feedbackText, $keyword) !== false) $negativeCount++;
                            }
                            
                            if($positiveCount > $negativeCount) {
                                $sentiment = 'positive';
                            } elseif($negativeCount > $positiveCount) {
                                $sentiment = 'negative';
                            } else {
                                $sentiment = 'neutral';
                            }
                        ?>
                        <tr data-rating="<?php echo $feedback['rating']; ?>">
                            <td><input type="checkbox" class="feedback-checkbox" data-id="<?php echo $feedback['id']; ?>" onchange="updateBulkBar()"></td>
                            <td><strong>#<?php echo $feedback['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($feedback['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($feedback['email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($feedback['service_used']); ?></td>
                            <td>
                                <div class="star-rating">
                                    <?php for($i = 1; $i <= 5; $i++): ?>
                                        <?php if($i <= $feedback['rating']): ?>
                                            <i class="fas fa-star"></i>
                                        <?php else: ?>
                                            <i class="far fa-star"></i>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                </div>
                                <small class="text-muted"><?php echo $feedback['rating']; ?>/5</small>
                            </td>
                            <td>
                                <span class="sentiment-<?php echo $sentiment; ?>">
                                    <?php echo ucfirst($sentiment); ?>
                                </span>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($feedback['feedback_text'], 0, 50)) . '...'; ?></small>
                            </td>
                            <td>
                                <?php if($feedback['is_published']): ?>
                                    <span class="badge bg-success">Published</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Draft</span>
                                <?php endif; ?>
                                <?php if($feedback['admin_response']): ?>
                                    <br><span class="badge bg-info mt-1">Responded</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?php echo date('M d, Y', strtotime($feedback['created_at'])); ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $feedback['id']; ?>" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#respondModal<?php echo $feedback['id']; ?>" title="Respond">
                                    <i class="fas fa-reply"></i>
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="confirmDelete(<?php echo $feedback['id']; ?>)" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- Modals Section -->
        <?php 
        $feedbacks->data_seek(0);
        while($feedback = $feedbacks->fetch_assoc()): 
            $feedbackText = strtolower($feedback['feedback_text']);
            $positiveCount = 0;
            $negativeCount = 0;
            
            foreach($positiveKeywords as $keyword) {
                if(strpos($feedbackText, $keyword) !== false) $positiveCount++;
            }
            foreach($negativeKeywords as $keyword) {
                if(strpos($feedbackText, $keyword) !== false) $negativeCount++;
            }
            
            if($positiveCount > $negativeCount) {
                $sentiment = 'positive';
            } elseif($negativeCount > $positiveCount) {
                $sentiment = 'negative';
            } else {
                $sentiment = 'neutral';
            }
        ?>
        
        <!-- View Modal -->
        <div class="modal fade" id="viewModal<?php echo $feedback['id']; ?>">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-comments me-2"></i>Feedback Details #<?php echo $feedback['id']; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="info-label">User</div>
                                <div class="info-value"><?php echo htmlspecialchars($feedback['user_name']); ?></div>
                                
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($feedback['email']); ?></div>
                                
                                <div class="info-label">Service Used</div>
                                <div class="info-value"><?php echo htmlspecialchars($feedback['service_used']); ?></div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-label">Rating</div>
                                <div class="info-value">
                                    <div class="star-rating">
                                        <?php for($i = 1; $i <= 5; $i++): ?>
                                            <?php if($i <= $feedback['rating']): ?>
                                                <i class="fas fa-star"></i>
                                            <?php else: ?>
                                                <i class="far fa-star"></i>
                                            <?php endif; ?>
                                        <?php endfor; ?>
                                        <span class="ms-2"><?php echo $feedback['rating']; ?>/5</span>
                                    </div>
                                </div>
                                
                                <div class="info-label">Sentiment Analysis</div>
                                <div class="info-value">
                                    <span class="sentiment-<?php echo $sentiment; ?>"><?php echo ucfirst($sentiment); ?></span>
                                </div>
                                
                                <div class="info-label">Submitted On</div>
                                <div class="info-value"><?php echo date('F d, Y H:i', strtotime($feedback['created_at'])); ?></div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="info-label">Feedback</div>
                        <div class="info-value p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($feedback['feedback_text'])); ?></div>
                        
                        <?php if($feedback['admin_response']): ?>
                        <hr>
                        <div class="info-label">Admin Response</div>
                        <div class="info-value p-3 bg-light rounded"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></div>
                        
                        <div class="info-label">Responded By</div>
                        <div class="info-value"><?php echo htmlspecialchars($feedback['responder_name']); ?> on <?php echo date('M d, Y H:i', strtotime($feedback['responded_at'])); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Respond Modal -->
        <div class="modal fade" id="respondModal<?php echo $feedback['id']; ?>">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-reply me-2"></i>Respond to Feedback #<?php echo $feedback['id']; ?></h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="admin_feedback.php" onsubmit="return validateResponse(<?php echo $feedback['id']; ?>)">
                        <div class="modal-body">
                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
                            
                            <div class="alert alert-info">
                                <div class="row">
                                    <div class="col-md-6">
                                        <strong><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($feedback['user_name']); ?></strong><br>
                                        <small><?php echo htmlspecialchars($feedback['service_used']); ?></small>
                                    </div>
                                    <div class="col-md-6 text-end">
                                        <div class="star-rating">
                                            <?php for($i = 1; $i <= 5; $i++): ?>
                                                <?php if($i <= $feedback['rating']): ?>
                                                    <i class="fas fa-star"></i>
                                                <?php else: ?>
                                                    <i class="far fa-star"></i>
                                                <?php endif; ?>
                                            <?php endfor; ?>
                                        </div>
                                        <span class="sentiment-<?php echo $sentiment; ?> mt-1"><?php echo ucfirst($sentiment); ?></span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>User Feedback:</strong></label>
                                <div class="p-3 bg-light border rounded">
                                    <?php echo nl2br(htmlspecialchars($feedback['feedback_text'])); ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong><i class="fas fa-robot me-2"></i>Quick Response Templates:</strong></label>
                                <div class="template-pills">
                                    <span class="template-pill" onclick="loadResponseTemplate(<?php echo $feedback['id']; ?>, 'positive', '<?php echo htmlspecialchars($feedback['service_used']); ?>')">
                                        <i class="fas fa-smile me-1"></i>Positive
                                    </span>
                                    <span class="template-pill" onclick="loadResponseTemplate(<?php echo $feedback['id']; ?>, 'negative', '<?php echo htmlspecialchars($feedback['service_used']); ?>')">
                                        <i class="fas fa-frown me-1"></i>Apologize
                                    </span>
                                    <span class="template-pill" onclick="loadResponseTemplate(<?php echo $feedback['id']; ?>, 'neutral', '<?php echo htmlspecialchars($feedback['service_used']); ?>')">
                                        <i class="fas fa-meh me-1"></i>Neutral
                                    </span>
                                    <span class="template-pill" onclick="loadResponseTemplate(<?php echo $feedback['id']; ?>, 'thankyou', '<?php echo htmlspecialchars($feedback['service_used']); ?>')">
                                        <i class="fas fa-heart me-1"></i>Thank You
                                    </span>
                                    <span class="template-pill" onclick="loadResponseTemplate(<?php echo $feedback['id']; ?>, 'improvement', '<?php echo htmlspecialchars($feedback['service_used']); ?>')">
                                        <i class="fas fa-tools me-1"></i>Improvement
                                    </span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label"><strong>Your Response:</strong></label>
                                <textarea name="admin_response" id="response<?php echo $feedback['id']; ?>" class="form-control" rows="6" placeholder="Write your personalized response here..." required><?php echo htmlspecialchars($feedback['admin_response']); ?></textarea>
                                <small class="text-muted">
                                    <i class="fas fa-lightbulb me-1"></i>Tip: Personalize the response by adding specific details about the user's experience
                                </small>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3 form-check">
                                        <input type="checkbox" class="form-check-input" name="is_published" id="publish<?php echo $feedback['id']; ?>" <?php echo $feedback['is_published'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="publish<?php echo $feedback['id']; ?>">
                                            <i class="fas fa-globe me-1"></i>Publish on Website (Testimonial)
                                        </label>
                                    </div>
                                </div>
                                <div class="col-md-6 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="copyResponse(<?php echo $feedback['id']; ?>)">
                                        <i class="fas fa-copy me-1"></i>Copy
                                    </button>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="emailResponse('<?php echo htmlspecialchars($feedback['email']); ?>', <?php echo $feedback['id']; ?>)">
                                        <i class="fas fa-envelope me-1"></i>Email
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" name="respond" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Save Response
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <?php endwhile; ?>
        
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Response templates
        const responseTemplates = <?php echo json_encode($responseTemplates); ?>;
        
        // Toast notification function
        function showToast(message, type = 'success') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `custom-toast ${type}`;
            
            let icon = '<i class="fas fa-check-circle me-2"></i>';
            if (type === 'error') icon = '<i class="fas fa-exclamation-circle me-2"></i>';
            if (type === 'warning') icon = '<i class="fas fa-exclamation-triangle me-2"></i>';
            
            toast.innerHTML = `${icon}${message}`;
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideOut 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }, 3000);
        }
        
        // Validate response form
        function validateResponse(feedbackId) {
            const response = document.getElementById('response' + feedbackId).value.trim();
            if (response === '') {
                showToast('Please write a response before submitting!', 'warning');
                return false;
            }
            return true;
        }
        
        // Perform bulk action
        function performBulkAction() {
            const action = document.getElementById('bulkActionSelect').value;
            const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
            
            if (checkboxes.length === 0) {
                showToast('Please select at least one feedback item!', 'warning');
                return false;
            }
            
            if (action === '') {
                showToast('Please select an action to perform!', 'warning');
                return false;
            }
            
            if (action === 'delete') {
                if (!confirm(`Are you sure you want to delete ${checkboxes.length} feedback item(s)? This action cannot be undone!`)) {
                    return false;
                }
            }
            
            // Create hidden form and submit
            const bulkForm = document.getElementById('bulkForm');
            const bulkCheckboxesContainer = document.getElementById('bulkCheckboxesContainer');
            const bulkActionInput = document.getElementById('bulkActionInput');
            
            // Clear previous checkboxes
            bulkCheckboxesContainer.innerHTML = '';
            
            // Set action
            bulkActionInput.value = action;
            
            // Add selected IDs as hidden inputs
            checkboxes.forEach(cb => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'selected_feedback[]';
                input.value = cb.dataset.id;
                bulkCheckboxesContainer.appendChild(input);
            });
            
            // Submit form
            bulkForm.submit();
        }
        
        // Load response template
        function loadResponseTemplate(feedbackId, type, service) {
            let template = responseTemplates[type];
            template = template.replace(/{service}/g, service);
            document.getElementById('response' + feedbackId).value = template;
            showToast('Template loaded successfully!', 'success');
        }
        
        // Copy response to clipboard
        function copyResponse(feedbackId) {
            const textarea = document.getElementById('response' + feedbackId);
            const response = textarea.value.trim();
            
            if (response === '') {
                showToast('No response text to copy!', 'warning');
                return;
            }
            
            textarea.select();
            textarea.setSelectionRange(0, 99999);
            
            try {
                document.execCommand('copy');
                showToast('Response copied to clipboard!', 'success');
            } catch (err) {
                navigator.clipboard.writeText(response).then(() => {
                    showToast('Response copied to clipboard!', 'success');
                }).catch(() => {
                    showToast('Failed to copy response!', 'error');
                });
            }
            
            window.getSelection().removeAllRanges();
        }
        
        // Email response
        function emailResponse(email, feedbackId) {
            const response = document.getElementById('response' + feedbackId).value.trim();
            
            if (response === '') {
                showToast('Please write a response first!', 'warning');
                return;
            }
            
            const subject = 'Thank you for your feedback - Anugrah Accounting Services';
            const body = 'Dear Valued Customer,\n\n' + response + '\n\nBest Regards,\nAnugrah Accounting Services Team';
            
            const mailtoLink = `mailto:${email}?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
            
            window.location.href = mailtoLink;
            showToast('Opening email client...', 'success');
        }
        
        // Confirm delete
        function confirmDelete(feedbackId) {
            if (confirm('Are you sure you want to delete this feedback? This action cannot be undone!')) {
                window.location.href = `?delete=${feedbackId}`;
            }
        }
        
        // Bulk actions
        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll');
            const checkboxes = document.querySelectorAll('.feedback-checkbox');
            checkboxes.forEach(cb => cb.checked = selectAll.checked);
            updateBulkBar();
        }
        
        function updateBulkBar() {
            const checkboxes = document.querySelectorAll('.feedback-checkbox:checked');
            const bulkBar = document.getElementById('bulkActionsBar');
            const count = document.getElementById('selectedCount');
            
            count.textContent = checkboxes.length;
            
            if (checkboxes.length > 0) {
                bulkBar.classList.add('show');
            } else {
                bulkBar.classList.remove('show');
            }
        }
        
        function clearSelection() {
            document.getElementById('selectAll').checked = false;
            const checkboxes = document.querySelectorAll('.feedback-checkbox');
            checkboxes.forEach(cb => cb.checked = false);
            updateBulkBar();
        }
        
        // Search functionality
        document.getElementById('searchFeedback').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const rows = document.querySelectorAll('#feedbackTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });
        
        // Filter by rating
        function filterTable() {
            const rating = document.getElementById('filterRating').value;
            const searchValue = document.getElementById('searchFeedback').value.toLowerCase();
            const rows = document.querySelectorAll('#feedbackTable tbody tr');
            
            rows.forEach(row => {
                const matchesRating = !rating || row.dataset.rating === rating;
                const matchesSearch = !searchValue || row.textContent.toLowerCase().includes(searchValue);
                row.style.display = (matchesRating && matchesSearch) ? '' : 'none';
            });
        }
        
        // Export functionality
        function exportFeedback() {
            const rows = document.querySelectorAll('#feedbackTable tbody tr');
            const data = [];
            
            rows.forEach(row => {
                if (row.style.display !== 'none') {
                    const cells = row.querySelectorAll('td');
                    const id = cells[1].textContent.trim();
                    const user = cells[2].textContent.trim().replace(/\n/g, ' ').replace(/\s+/g, ' ');
                    const service = cells[3].textContent.trim();
                    const rating = cells[4].querySelector('.text-muted').textContent.trim();
                    const sentiment = cells[5].textContent.trim();
                    const feedback = cells[6].textContent.trim();
                    const status = cells[7].textContent.trim().replace(/\n/g, ' ');
                    const date = cells[8].textContent.trim();
                    
                    data.push({
                        id, user, service, rating, sentiment, feedback, status, date
                    });
                }
            });
            
            if (data.length === 0) {
                showToast('No data to export!', 'warning');
                return;
            }
            
            // Convert to CSV
            const headers = ['ID', 'User', 'Service', 'Rating', 'Sentiment', 'Feedback', 'Status', 'Date'];
            const csvRows = [headers.join(',')];
            
            data.forEach(row => {
                const values = [
                    row.id,
                    `"${row.user.replace(/"/g, '""')}"`,
                    `"${row.service.replace(/"/g, '""')}"`,
                    row.rating,
                    row.sentiment,
                    `"${row.feedback.replace(/"/g, '""')}"`,
                    `"${row.status.replace(/"/g, '""')}"`,
                    row.date
                ];
                csvRows.push(values.join(','));
            });
            
            const csv = csvRows.join('\n');
            
            // Download
            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = `feedback_report_${new Date().toISOString().split('T')[0]}.csv`;
            link.style.display = 'none';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
            
            showToast(`Successfully exported ${data.length} feedback items!`, 'success');
        }
        
        // Charts initialization
        
        // Rating Distribution Chart
        const ratingCtx = document.getElementById('ratingChart');
        if (ratingCtx) {
            new Chart(ratingCtx.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: ['5 Stars', '4 Stars', '3 Stars', '2 Stars', '1 Star'],
                    datasets: [{
                        label: 'Number of Ratings',
                        data: [
                            <?php echo isset($ratingData[5]) ? $ratingData[5] : 0; ?>,
                            <?php echo isset($ratingData[4]) ? $ratingData[4] : 0; ?>,
                            <?php echo isset($ratingData[3]) ? $ratingData[3] : 0; ?>,
                            <?php echo isset($ratingData[2]) ? $ratingData[2] : 0; ?>,
                            <?php echo isset($ratingData[1]) ? $ratingData[1] : 0; ?>
                        ],
                        backgroundColor: [
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(251, 191, 36, 0.8)',
                            'rgba(249, 115, 22, 0.8)',
                            'rgba(239, 68, 68, 0.8)'
                        ],
                        borderColor: [
                            'rgba(16, 185, 129, 1)',
                            'rgba(59, 130, 246, 1)',
                            'rgba(251, 191, 36, 1)',
                            'rgba(249, 115, 22, 1)',
                            'rgba(239, 68, 68, 1)'
                        ],
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    return 'Count: ' + context.parsed.y;
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true, 
                            ticks: { stepSize: 1 },
                            grid: { color: 'rgba(0, 0, 0, 0.05)' }
                        },
                        x: { grid: { display: false } }
                    }
                }
            });
        }
        
        // Service Performance Chart
        const serviceCtx = document.getElementById('serviceChart');
        if (serviceCtx) {
            const serviceLabels = [<?php foreach($serviceData as $service) { echo '"' . htmlspecialchars($service['service_used']) . '",'; } ?>];
            const serviceCounts = [<?php foreach($serviceData as $service) { echo $service['count'] . ','; } ?>];
            
            new Chart(serviceCtx.getContext('2d'), {
                type: 'doughnut',
                data: {
                    labels: serviceLabels,
                    datasets: [{
                        data: serviceCounts,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(237, 100, 166, 0.8)',
                            'rgba(255, 154, 158, 0.8)',
                            'rgba(250, 208, 196, 0.8)'
                        ],
                        borderColor: [
                            'rgba(102, 126, 234, 1)',
                            'rgba(118, 75, 162, 1)',
                            'rgba(237, 100, 166, 1)',
                            'rgba(255, 154, 158, 1)',
                            'rgba(250, 208, 196, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { 
                            position: 'bottom',
                            labels: { padding: 15, font: { size: 11 } }
                        }
                    }
                }
            });
        }
        
        // Trend Chart
        const trendCtx = document.getElementById('trendChart');
        if (trendCtx) {
            const trendDates = [<?php foreach($trendArray as $trend) { echo '"' . date('M d', strtotime($trend['date'])) . '",'; } ?>];
            const feedbackCounts = [<?php foreach($trendArray as $trend) { echo $trend['count'] . ','; } ?>];
            const avgRatings = [<?php foreach($trendArray as $trend) { echo round($trend['avg_rating'], 1) . ','; } ?>];
            
            new Chart(trendCtx.getContext('2d'), {
                type: 'line',
                data: {
                    labels: trendDates,
                    datasets: [{
                        label: 'Feedback Count',
                        data: feedbackCounts,
                        borderColor: 'rgba(102, 126, 234, 1)',
                        backgroundColor: 'rgba(102, 126, 234, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(102, 126, 234, 1)',
                        yAxisID: 'y'
                    }, {
                        label: 'Average Rating',
                        data: avgRatings,
                        borderColor: 'rgba(16, 185, 129, 1)',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        tension: 0.4,
                        fill: true,
                        borderWidth: 3,
                        pointRadius: 5,
                        pointBackgroundColor: 'rgba(16, 185, 129, 1)',
                        yAxisID: 'y1'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: { mode: 'index', intersect: false },
                    plugins: { legend: { position: 'top' } },
                    scales: {
                        y: {
                            type: 'linear',
                            display: true,
                            position: 'left',
                            beginAtZero: true,
                            ticks: { stepSize: 1 }
                        },
                        y1: {
                            type: 'linear',
                            display: true,
                            position: 'right',
                            min: 0,
                            max: 5,
                            ticks: { stepSize: 1 },
                            grid: { drawOnChartArea: false }
                        }
                    }
                }
            });
        }
        
        // Add slide out animation
        const style = document.createElement('style');
        style.textContent = '@keyframes slideOut { to { transform: translateX(400px); opacity: 0; } }';
        document.head.appendChild(style);
    </script>
</body>
</html>