<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();
$adminId = $_SESSION['admin_id'];

// Fetch statistics for sidebar notifications
$stats = array();
$stats['new_messages'] = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'");
if ($result && $row = $result->fetch_assoc()) {
    $stats['new_messages'] = $row['count'];
}

// Fetch notification count
$notificationCount = 0;
$result = $conn->query("SELECT COUNT(*) as count FROM scheduled_notifications WHERE status = 'pending' AND scheduled_for <= NOW()");
if ($result && $row = $result->fetch_assoc()) {
    $notificationCount = $row['count'];
}

// Handle status update
if (isset($_POST['update_status'])) {
    $id = intval($_POST['id']);
    $status = sanitizeInput($_POST['status']);
    $notes = sanitizeInput($_POST['notes']);
    
    $stmt = $conn->prepare("UPDATE gst_registrations SET status = ?, notes = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("ssii", $status, $notes, $adminId, $id);
    $stmt->execute();
    $stmt->close();
    
    logActivity($conn, null, 'GST_REG_STATUS_UPDATE', "Updated GST registration status for ID: $id");
    $success = "Status updated successfully!";
}

// Handle GSTIN assignment
if (isset($_POST['assign_gstin'])) {
    $id = intval($_POST['id']);
    $gstin = sanitizeInput($_POST['gstin']);
    $regDate = sanitizeInput($_POST['registration_date']);
    
    $stmt = $conn->prepare("UPDATE gst_registrations SET gstin = ?, registration_date = ?, status = 'Completed' WHERE id = ?");
    $stmt->bind_param("ssi", $gstin, $regDate, $id);
    $stmt->execute();
    $stmt->close();
    
    logActivity($conn, null, 'GSTIN_ASSIGNED', "Assigned GSTIN for registration ID: $id");
    $success = "GSTIN assigned successfully!";
}

// Fetch all GST registrations
$registrations = $conn->query("SELECT gr.*, u.name as user_name, u.email as user_email, u.phone as user_phone
    FROM gst_registrations gr
    LEFT JOIN users u ON gr.user_id = u.id
    ORDER BY gr.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GST Registrations - Anugrah Accounting</title>
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
        .notification-badge { position: absolute; top: 10px; right: 20px; background: #ff4757; color: white; border-radius: 10px; padding: 2px 6px; font-size: 10px; font-weight: 600; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-nav { background: white; padding: 15px 25px; border-radius: 10px; margin-bottom: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); display: flex; justify-content: space-between; align-items: center; }
        .top-nav h5 { margin: 0; color: #333; }
        .admin-info { display: flex; align-items: center; gap: 15px; }
        .admin-avatar { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; }
        .table-card { background: white; border-radius: 10px; padding: 25px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        .table-card h6 { color: #333; font-weight: 600; margin-bottom: 20px; padding-bottom: 15px; border-bottom: 2px solid #f0f0f0; }
        .badge { padding: 5px 10px; font-size: 11px; font-weight: 600; }
        .btn-sm { padding: 5px 12px; font-size: 12px; }
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
            <h5><i class="fas fa-file-invoice me-2"></i>GST Registrations</h5>
            <div class="admin-info">
                <div>
                    <div style="font-size: 14px; font-weight: 600; color: #333;"><?php echo htmlspecialchars($adminName); ?></div>
                    <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($adminRole); ?></div>
                </div>
                <div class="admin-avatar"><?php echo strtoupper(substr($adminName, 0, 1)); ?></div>
            </div>
        </div>
        
        <?php if (isset($success)): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?php echo $success; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="table-card">
            <h6><i class="fas fa-file-invoice me-2"></i>All GST Registration Applications</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Business Details</th>
                            <th>User</th>
                            <th>PAN</th>
                            <th>Turnover</th>
                            <th>GSTIN</th>
                            <th>Status</th>
                            <th>Applied On</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while($reg = $registrations->fetch_assoc()): ?>
                        <tr>
                            <td><?php echo $reg['id']; ?></td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['business_name']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($reg['business_type']); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($reg['user_name'] ?: $reg['user_email']); ?></strong><br>
                                <small class="text-muted"><?php echo htmlspecialchars($reg['user_email']); ?></small>
                            </td>
                            <td><?php echo htmlspecialchars($reg['pan_number']); ?></td>
                            <td>₹<?php echo number_format($reg['estimated_turnover'], 2); ?></td>
                            <td>
                                <?php if($reg['gstin']): ?>
                                    <span class="badge bg-success"><?php echo htmlspecialchars($reg['gstin']); ?></span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Not Assigned</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php
                                $statusColors = array(
                                    'Pending' => 'warning',
                                    'In Progress' => 'info',
                                    'Completed' => 'success',
                                    'Rejected' => 'danger'
                                );
                                $color = isset($statusColors[$reg['status']]) ? $statusColors[$reg['status']] : 'secondary';
                                ?>
                                <span class="badge bg-<?php echo $color; ?>"><?php echo htmlspecialchars($reg['status']); ?></span>
                            </td>
                            <td><small class="text-muted"><?php echo date('M d, Y', strtotime($reg['created_at'])); ?></small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#viewReg<?php echo $reg['id']; ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                        </tr>
                        
                        <!-- View Registration Modal -->
                        <div class="modal fade" id="viewReg<?php echo $reg['id']; ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">GST Registration Details</h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row mb-4">
                                            <div class="col-md-6 mb-3">
                                                <strong>Business Name:</strong><br>
                                                <?php echo htmlspecialchars($reg['business_name']); ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Business Type:</strong><br>
                                                <?php echo htmlspecialchars($reg['business_type']); ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>PAN Number:</strong><br>
                                                <?php echo htmlspecialchars($reg['pan_number']); ?>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Estimated Turnover:</strong><br>
                                                ₹<?php echo number_format($reg['estimated_turnover'], 2); ?>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <strong>Business Address:</strong><br>
                                                <?php echo htmlspecialchars($reg['business_address']); ?><br>
                                                <?php echo htmlspecialchars($reg['state']); ?> - <?php echo htmlspecialchars($reg['pincode']); ?>
                                            </div>
                                            <div class="col-md-12 mb-3">
                                                <strong>Business Activity:</strong><br>
                                                <?php echo htmlspecialchars($reg['business_activity']); ?>
                                            </div>
                                            <?php if($reg['gstin']): ?>
                                            <div class="col-md-6 mb-3">
                                                <strong>GSTIN:</strong><br>
                                                <span class="badge bg-success"><?php echo htmlspecialchars($reg['gstin']); ?></span>
                                            </div>
                                            <div class="col-md-6 mb-3">
                                                <strong>Registration Date:</strong><br>
                                                <?php echo date('M d, Y', strtotime($reg['registration_date'])); ?>
                                            </div>
                                            <?php endif; ?>
                                            <?php if($reg['notes']): ?>
                                            <div class="col-md-12 mb-3">
                                                <strong>Notes:</strong><br>
                                                <?php echo nl2br(htmlspecialchars($reg['notes'])); ?>
                                            </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <hr>
                                        
                                        <!-- Update Status Form -->
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?php echo $reg['id']; ?>">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select" required>
                                                        <option value="Pending" <?php echo $reg['status'] == 'Pending' ? 'selected' : ''; ?>>Pending</option>
                                                        <option value="In Progress" <?php echo $reg['status'] == 'In Progress' ? 'selected' : ''; ?>>In Progress</option>
                                                        <option value="Completed" <?php echo $reg['status'] == 'Completed' ? 'selected' : ''; ?>>Completed</option>
                                                        <option value="Rejected" <?php echo $reg['status'] == 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                                    </select>
                                                </div>
                                                <div class="col-md-12 mb-3">
                                                    <label class="form-label">Admin Notes</label>
                                                    <textarea name="notes" class="form-control" rows="3"><?php echo htmlspecialchars($reg['notes']); ?></textarea>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" name="update_status" class="btn btn-primary">
                                                        <i class="fas fa-save me-2"></i>Update Status
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        
                                        <?php if(!$reg['gstin']): ?>
                                        <hr class="mt-4">
                                        
                                        <!-- Assign GSTIN Form -->
                                        <h6>Assign GSTIN</h6>
                                        <form method="POST">
                                            <input type="hidden" name="id" value="<?php echo $reg['id']; ?>">
                                            <div class="row">
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">GSTIN</label>
                                                    <input type="text" name="gstin" class="form-control" required pattern="[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}" placeholder="22AAAAA0000A1Z5">
                                                </div>
                                                <div class="col-md-6 mb-3">
                                                    <label class="form-label">Registration Date</label>
                                                    <input type="date" name="registration_date" class="form-control" required>
                                                </div>
                                                <div class="col-md-12">
                                                    <button type="submit" name="assign_gstin" class="btn btn-success">
                                                        <i class="fas fa-check me-2"></i>Assign GSTIN
                                                    </button>
                                                </div>
                                            </div>
                                        </form>
                                        <?php endif; ?>
                                    </div>
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