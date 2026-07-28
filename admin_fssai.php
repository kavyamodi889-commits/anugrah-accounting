<?php
session_start();
require_once 'db_config.php';

if (!isset($_SESSION['admin_id'])) {
    header('Location: admin_login.php');
    exit();
}

$adminName = $_SESSION['admin_name'];
$adminRole = $_SESSION['admin_role'];

// Fetch statistics for notification badges
$stats = array();
$stats['new_messages'] = 0;

// Get count of new/unread contact messages
$messageCountQuery = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
if ($messageCountQuery) {
    $messageRow = $messageCountQuery->fetch_assoc();
    $stats['new_messages'] = $messageRow['count'];
}

// Get count of pending notifications
$notificationCount = 0;
$notificationQuery = $conn->query("SELECT COUNT(*) as count FROM scheduled_notifications WHERE status = 'pending'");
if ($notificationQuery) {
    $notifRow = $notificationQuery->fetch_assoc();
    $notificationCount = $notifRow['count'];
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $fssaiId = $_POST['fssai_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $fssaiNumber = $_POST['fssai_number'];
    $licenceIssueDate = $_POST['licence_issue_date'];
    $licenceExpiryDate = $_POST['licence_expiry_date'];
    
    $stmt = $conn->prepare("UPDATE fssai_licences SET status = ?, notes = ?, fssai_number = ?, licence_issue_date = ?, licence_expiry_date = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("sssssii", $status, $notes, $fssaiNumber, $licenceIssueDate, $licenceExpiryDate, $_SESSION['admin_id'], $fssaiId);
    $stmt->execute();
    $stmt->close();
    
    header('Location: admin_fssai.php?msg=updated');
    exit();
}

// Fetch all FSSAI licences
$licences = $conn->query("SELECT f.*, u.name as user_name, u.email, u.phone, a.full_name as assigned_name 
    FROM fssai_licences f 
    LEFT JOIN users u ON f.user_id = u.id 
    LEFT JOIN admin_users a ON f.assigned_to = a.id 
    ORDER BY f.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FSSAI Licences - Admin</title>
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
            background: #ff4757; 
            color: white; 
            border-radius: 10px; 
            padding: 2px 6px; 
            font-size: 11px; 
            margin-left: auto;
            font-weight: 600;
        }
        .main-content { margin-left: 260px; padding: 20px; min-height: 100vh; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .table-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .info-label { font-weight: 600; color: #666; margin-bottom: 5px; font-size: 14px; }
        .info-value { color: #333; margin-bottom: 15px; }
        .badge { padding: 6px 12px; font-size: 12px; }
        .empty-state { text-align: center; padding: 60px 20px; color: #999; }
        .empty-state i { font-size: 64px; margin-bottom: 20px; opacity: 0.3; }
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
            <li><a href="admin_fssai.php" class="active"><i class="fas fa-utensils"></i> FSSAI Licences</a></li>
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
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-utensils me-2"></i>FSSAI Licences Management</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if(isset($_GET['msg']) && $_GET['msg'] == 'updated'): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <i class="fas fa-check-circle me-2"></i>Status updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="table-card">
            <h6><i class="fas fa-utensils me-2"></i>All FSSAI Licence Applications</h6>
            
            <?php if($licences->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>Business Name</th>
                            <th>Licence Type</th>
                            <th>Food Category</th>
                            <th>FSSAI Number</th>
                            <th>Turnover</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($licence = $licences->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $licence['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($licence['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($licence['email']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($licence['phone']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($licence['business_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($licence['business_type']); ?></small>
                            </td>
                            <td><span class="badge bg-secondary"><?php echo htmlspecialchars($licence['licence_type']); ?></span></td>
                            <td><?php echo htmlspecialchars($licence['food_category']); ?></td>
                            <td>
                                <?php if($licence['fssai_number']): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($licence['fssai_number']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>₹<?php echo number_format($licence['annual_turnover'], 2); ?></td>
                            <td>
                                <?php
                                $statusClass = 'secondary';
                                if($licence['status'] == 'Completed') $statusClass = 'success';
                                elseif($licence['status'] == 'In Progress') $statusClass = 'warning';
                                elseif($licence['status'] == 'Pending') $statusClass = 'info';
                                elseif($licence['status'] == 'Rejected') $statusClass = 'danger';
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $licence['status']; ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $licence['id']; ?>" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $licence['id']; ?>" title="Update Status">
                                    <i class="fas fa-edit"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- View Modal -->
                        <div class="modal fade" id="viewModal<?php echo $licence['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-utensils me-2"></i>FSSAI Licence Details #<?php echo $licence['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="info-label"><i class="fas fa-user me-2"></i>User Information</div>
                                                <div class="info-value">
                                                    <strong><?php echo htmlspecialchars($licence['user_name']); ?></strong><br>
                                                    <?php echo htmlspecialchars($licence['email']); ?><br>
                                                    <?php echo htmlspecialchars($licence['phone']); ?>
                                                </div>
                                                
                                                <div class="info-label"><i class="fas fa-building me-2"></i>Business Name</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['business_name']); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-briefcase me-2"></i>Business Type</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['business_type']); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-certificate me-2"></i>Licence Type</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['licence_type']); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-hamburger me-2"></i>Food Category</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['food_category']); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-map-marker-alt me-2"></i>State</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['state']); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-mail-bulk me-2"></i>Pincode</div>
                                                <div class="info-value"><?php echo htmlspecialchars($licence['pincode']); ?></div>
                                            </div>
                                            
                                            <div class="col-md-6">
                                                <?php if($licence['fssai_number']): ?>
                                                <div class="info-label"><i class="fas fa-id-card me-2"></i>FSSAI Number</div>
                                                <div class="info-value">
                                                    <span class="badge bg-success" style="font-size: 14px; padding: 8px 12px;">
                                                        <?php echo htmlspecialchars($licence['fssai_number']); ?>
                                                    </span>
                                                </div>
                                                <?php endif; ?>
                                                
                                                <?php if($licence['licence_issue_date']): ?>
                                                <div class="info-label"><i class="fas fa-calendar-check me-2"></i>Licence Issue Date</div>
                                                <div class="info-value"><?php echo date('F d, Y', strtotime($licence['licence_issue_date'])); ?></div>
                                                <?php endif; ?>
                                                
                                                <?php if($licence['licence_expiry_date']): ?>
                                                <div class="info-label"><i class="fas fa-calendar-times me-2"></i>Licence Expiry Date</div>
                                                <div class="info-value"><?php echo date('F d, Y', strtotime($licence['licence_expiry_date'])); ?></div>
                                                <?php endif; ?>
                                                
                                                <div class="info-label"><i class="fas fa-dollar-sign me-2"></i>Annual Turnover</div>
                                                <div class="info-value">₹<?php echo number_format($licence['annual_turnover'], 2); ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-tasks me-2"></i>Status</div>
                                                <div class="info-value">
                                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $licence['status']; ?></span>
                                                </div>
                                                
                                                <div class="info-label"><i class="fas fa-user-tie me-2"></i>Assigned To</div>
                                                <div class="info-value"><?php echo $licence['assigned_name'] ? htmlspecialchars($licence['assigned_name']) : '<span class="text-muted">Unassigned</span>'; ?></div>
                                                
                                                <div class="info-label"><i class="fas fa-clock me-2"></i>Applied On</div>
                                                <div class="info-value"><?php echo date('F d, Y H:i', strtotime($licence['created_at'])); ?></div>
                                            </div>
                                        </div>
                                        
                                        <hr>
                                        
                                        <div class="info-label"><i class="fas fa-map-marked-alt me-2"></i>Business Address</div>
                                        <div class="info-value"><?php echo nl2br(htmlspecialchars($licence['business_address'])); ?></div>
                                        
                                        <?php if($licence['notes']): ?>
                                        <div class="info-label"><i class="fas fa-sticky-note me-2"></i>Admin Notes</div>
                                        <div class="info-value">
                                            <div class="alert alert-info mb-0">
                                                <?php echo nl2br(htmlspecialchars($licence['notes'])); ?>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Update Modal -->
                        <div class="modal fade" id="updateModal<?php echo $licence['id']; ?>" tabindex="-1">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status #<?php echo $licence['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="fssai_id" value="<?php echo $licence['id']; ?>">
                                            
                                            <div class="alert alert-info">
                                                <strong><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($licence['business_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($licence['user_name']); ?> - <?php echo htmlspecialchars($licence['email']); ?></small>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-tasks me-2"></i>Status *</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="Pending" <?php echo $licence['status']=='Pending'?'selected':''; ?>>Pending</option>
                                                    <option value="In Progress" <?php echo $licence['status']=='In Progress'?'selected':''; ?>>In Progress</option>
                                                    <option value="Completed" <?php echo $licence['status']=='Completed'?'selected':''; ?>>Completed</option>
                                                    <option value="Rejected" <?php echo $licence['status']=='Rejected'?'selected':''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-id-card me-2"></i>FSSAI Number</label>
                                                <input type="text" name="fssai_number" class="form-control" value="<?php echo htmlspecialchars($licence['fssai_number']); ?>" placeholder="Enter FSSAI licence number">
                                                <small class="text-muted">14-digit FSSAI registration number</small>
                                            </div>
                                            
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"><i class="fas fa-calendar-check me-2"></i>Issue Date</label>
                                                    <input type="date" name="licence_issue_date" class="form-control" value="<?php echo $licence['licence_issue_date']; ?>">
                                                </div>
                                                
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label"><i class="fas fa-calendar-times me-2"></i>Expiry Date</label>
                                                    <input type="date" name="licence_expiry_date" class="form-control" value="<?php echo $licence['licence_expiry_date']; ?>">
                                                </div>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-sticky-note me-2"></i>Admin Notes</label>
                                                <textarea name="notes" class="form-control" rows="4" placeholder="Add any notes or comments about this application..."><?php echo htmlspecialchars($licence['notes']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                                                <i class="fas fa-times me-2"></i>Cancel
                                            </button>
                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>Update Status
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h5>No FSSAI Licence Applications</h5>
                <p class="text-muted">There are no FSSAI licence applications in the system yet.</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>