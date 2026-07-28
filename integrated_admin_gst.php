<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';
require_once 'includes/email.php';

requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();

// Handle status update WITH NOTIFICATIONS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $gstId = $_POST['gst_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    $gstin = $_POST['gstin'];
    $registrationDate = $_POST['registration_date'];
    
    // Get old status before update
    $oldStatusQuery = $conn->prepare("SELECT status, user_id FROM gst_registrations WHERE id = ?");
    $oldStatusQuery->bind_param("i", $gstId);
    $oldStatusQuery->execute();
    $oldData = $oldStatusQuery->get_result()->fetch_assoc();
    $oldStatus = $oldData['status'];
    $userId = $oldData['user_id'];
    $oldStatusQuery->close();
    
    // Update the record
    $stmt = $conn->prepare("UPDATE gst_registrations SET status = ?, notes = ?, gstin = ?, registration_date = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("ssssii", $status, $notes, $gstin, $registrationDate, $_SESSION['admin_id'], $gstId);
    $stmt->execute();
    $stmt->close();
    
    // Send notification if status changed
    if ($oldStatus != $status) {
        sendStatusUpdateNotification($userId, 'GST Registration', $oldStatus, $status, $gstId);
    }
    
    header('Location: admin_gst_reg.php?msg=updated');
    exit();
}

// Fetch all GST registrations
$registrations = $conn->query("SELECT gr.*, u.name as user_name, u.email, u.phone, a.full_name as assigned_name 
    FROM gst_registrations gr 
    LEFT JOIN users u ON gr.user_id = u.id 
    LEFT JOIN admin_users a ON gr.assigned_to = a.id 
    ORDER BY gr.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Registrations - Admin</title>
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
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu i { width: 20px; margin-right: 12px; font-size: 16px; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .table-card { background: white; border-radius: 10px; padding: 20px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 10px; border-bottom: 2px solid #f0f0f0; }
        .modal-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; }
        .info-label { font-weight: 600; color: #666; margin-bottom: 5px; }
        .info-value { color: #333; margin-bottom: 15px; }
        .notification-badge { position: absolute; top: -5px; right: -5px; background: #dc3545; color: white; border-radius: 50%; width: 18px; height: 18px; font-size: 10px; display: flex; align-items: center; justify-content: center; }
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
            <li><a href="admin_gst_reg.php" class="active"><i class="fas fa-file-invoice"></i> GST Registrations</a></li>
            <li><a href="admin_gst_returns.php"><i class="fas fa-receipt"></i> GST Returns</a></li>
            <li><a href="admin_income_tax.php"><i class="fas fa-money-bill-wave"></i> Income Tax Returns</a></li>
            <li><a href="admin_msme.php"><i class="fas fa-industry"></i> MSME Registrations</a></li>
            <li><a href="admin_fssai.php"><i class="fas fa-utensils"></i> FSSAI Licences</a></li>
            <li><a href="admin_accounting.php"><i class="fas fa-calculator"></i> Accounting Services</a></li>
            <li><a href="admin_cma.php"><i class="fas fa-chart-line"></i> CMA Data</a></li>
            <li><a href="admin_tax_planning.php"><i class="fas fa-piggy-bank"></i> Tax Planning</a></li>
            <li><a href="admin_messages.php"><i class="fas fa-envelope"></i> Contact Messages</a></li>
            <li><a href="admin_feedback.php"><i class="fas fa-comments"></i> Feedback</a></li>
            <li><a href="admin_notifications.php"><i class="fas fa-bell" style="position: relative;"></i> Notifications</a></li>
            <li><a href="admin_settings.php"><i class="fas fa-cog"></i> Settings</a></li>
            <li><a href="admin_logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </div>
    
    <div class="main-content">
        <div class="top-nav">
            <h5><i class="fas fa-file-invoice me-2"></i>GST Registrations Management</h5>
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
            <i class="fas fa-check-circle me-2"></i>Status updated successfully! Client has been notified via Email, SMS & WhatsApp.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="table-card">
            <h6><i class="fas fa-file-invoice me-2"></i>All GST Registrations</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>Business Name</th>
                            <th>PAN</th>
                            <th>GSTIN</th>
                            <th>Turnover</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($reg = $registrations->fetch_assoc()): ?>
                        <tr>
                            <td><strong>#<?php echo $reg['id']; ?></strong></td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['user_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($reg['email']); ?></small><br>
                                <small class="text-muted"><i class="fas fa-phone"></i> <?php echo htmlspecialchars($reg['phone']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($reg['business_name']); ?></td>
                            <td><span class="badge bg-info"><?php echo htmlspecialchars($reg['pan_number']); ?></span></td>
                            <td>
                                <?php if($reg['gstin']): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($reg['gstin']); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>₹<?php echo number_format($reg['estimated_turnover'], 2); ?></td>
                            <td>
                                <?php
                                $statusClass = 'secondary';
                                if($reg['status'] == 'Completed') $statusClass = 'success';
                                elseif($reg['status'] == 'In Progress') $statusClass = 'warning';
                                elseif($reg['status'] == 'Pending') $statusClass = 'info';
                                ?>
                                <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $reg['status']; ?></span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $reg['id']; ?>" title="View">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $reg['id']; ?>" title="Update & Notify">
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </td>
                        </tr>
                        
                        <!-- Update Modal with Notification Info -->
                        <div class="modal fade" id="updateModal<?php echo $reg['id']; ?>">
                            <div class="modal-dialog">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title"><i class="fas fa-edit me-2"></i>Update Status #<?php echo $reg['id']; ?></h5>
                                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                    </div>
                                    <form method="POST">
                                        <div class="modal-body">
                                            <input type="hidden" name="gst_id" value="<?php echo $reg['id']; ?>">
                                            
                                            <div class="alert alert-info">
                                                <strong><i class="fas fa-building me-2"></i><?php echo htmlspecialchars($reg['business_name']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($reg['user_name']); ?> - <?php echo htmlspecialchars($reg['email']); ?></small>
                                            </div>
                                            
                                            <div class="alert alert-warning">
                                                <i class="fas fa-bell me-2"></i><strong>Auto-Notification:</strong> Client will receive Email, SMS & WhatsApp notification when status is updated.
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-tasks me-2"></i>Status *</label>
                                                <select name="status" class="form-select" required>
                                                    <option value="Pending" <?php echo $reg['status']=='Pending'?'selected':''; ?>>Pending</option>
                                                    <option value="In Progress" <?php echo $reg['status']=='In Progress'?'selected':''; ?>>In Progress</option>
                                                    <option value="Completed" <?php echo $reg['status']=='Completed'?'selected':''; ?>>Completed</option>
                                                    <option value="Rejected" <?php echo $reg['status']=='Rejected'?'selected':''; ?>>Rejected</option>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-id-card me-2"></i>GSTIN</label>
                                                <input type="text" name="gstin" class="form-control" value="<?php echo htmlspecialchars($reg['gstin']); ?>" placeholder="Enter GSTIN">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-calendar me-2"></i>Registration Date</label>
                                                <input type="date" name="registration_date" class="form-control" value="<?php echo $reg['registration_date']; ?>">
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label"><i class="fas fa-sticky-note me-2"></i>Notes</label>
                                                <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($reg['notes']); ?></textarea>
                                            </div>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                            <button type="submit" name="update_status" class="btn btn-primary">
                                                <i class="fas fa-paper-plane me-2"></i>Update & Notify Client
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
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>