<?php
require_once 'includes/db.php';
require_once 'includes/auth.php';

requireAdminLogin();

$adminName = getAdminName();
$adminRole = getAdminRole();

// Fetch notification counts
$stats = array();
$stats['new_messages'] = 0;

// Count new/unread contact messages
$messageCountQuery = "SELECT COUNT(*) as count FROM contact_messages WHERE status = 'New'";
$messageResult = $conn->query($messageCountQuery);
if ($messageResult && $row = $messageResult->fetch_assoc()) {
    $stats['new_messages'] = $row['count'];
}

// Count unread admin notifications
$notificationCount = 0;
$notifQuery = "SELECT COUNT(*) as count FROM admin_notifications WHERE is_read = 0";
$notifResult = $conn->query($notifQuery);
if ($notifResult && $row = $notifResult->fetch_assoc()) {
    $notificationCount = $row['count'];
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $cmaId = $_POST['cma_id'];
    $status = $_POST['status'];
    $notes = $_POST['notes'];
    
    $stmt = $conn->prepare("UPDATE cma_data SET status = ?, notes = ?, assigned_to = ? WHERE id = ?");
    $stmt->bind_param("ssii", $status, $notes, $_SESSION['admin_id'], $cmaId);
    $stmt->execute();
    $stmt->close();
    
    header('Location: admin_cma.php?msg=updated');
    exit();
}

// Fetch all CMA data
$cmaRecords = $conn->query("SELECT c.*, u.name as user_name, u.email, u.phone, a.full_name as assigned_name 
    FROM cma_data c 
    LEFT JOIN users u ON c.user_id = u.id 
    LEFT JOIN admin_users a ON c.assigned_to = a.id 
    ORDER BY c.created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CMA Data - Admin</title>
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
        .sidebar-menu a { display: flex; align-items: center; padding: 12px 20px; color: rgba(255,255,255,0.8); text-decoration: none; transition: all 0.3s; position: relative; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background: rgba(255,255,255,0.1); color: white; }
        .sidebar-menu i { width: 20px; margin-right: 12px; font-size: 16px; }
        .notification-badge { position: absolute; right: 20px; top: 50%; transform: translateY(-50%); background: #ff4444; color: white; border-radius: 10px; padding: 2px 8px; font-size: 11px; font-weight: 600; }
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
            <li><a href="admin_fssai.php"><i class="fas fa-utensils"></i> FSSAI Licences</a></li>
            <li><a href="admin_accounting.php"><i class="fas fa-calculator"></i> Accounting Services</a></li>
            <li><a href="admin_cma.php" class="active"><i class="fas fa-chart-line"></i> CMA Data</a></li>
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
            <h5><i class="fas fa-chart-line me-2"></i>CMA Data Management</h5>
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
            Status updated successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php endif; ?>
        
        <div class="table-card">
            <h6><i class="fas fa-chart-line me-2"></i>All CMA Data Reports</h6>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User Details</th>
                            <th>Business Name</th>
                            <th>PAN</th>
                            <th>Financial Year</th>
                            <th>Loan Amount</th>
                            <th>Bank Name</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if($cmaRecords->num_rows > 0): ?>
                            <?php while($cma = $cmaRecords->fetch_assoc()): ?>
                            <tr>
                                <td><strong>#<?php echo $cma['id']; ?></strong></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($cma['user_name']); ?></strong><br>
                                    <small class="text-muted"><?php echo htmlspecialchars($cma['email']); ?></small>
                                </td>
                                <td><?php echo htmlspecialchars($cma['business_name']); ?></td>
                                <td><span class="badge bg-info"><?php echo htmlspecialchars($cma['pan_number']); ?></span></td>
                                <td><?php echo htmlspecialchars($cma['financial_year']); ?></td>
                                <td>₹<?php echo number_format($cma['loan_amount'], 2); ?></td>
                                <td><?php echo htmlspecialchars($cma['bank_name']); ?></td>
                                <td>
                                    <?php
                                    $statusClass = 'secondary';
                                    if($cma['status'] == 'Completed') $statusClass = 'success';
                                    elseif($cma['status'] == 'In Progress') $statusClass = 'warning';
                                    elseif($cma['status'] == 'Pending') $statusClass = 'info';
                                    ?>
                                    <span class="badge bg-<?php echo $statusClass; ?>"><?php echo $cma['status']; ?></span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#viewModal<?php echo $cma['id']; ?>">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#updateModal<?php echo $cma['id']; ?>">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                </td>
                            </tr>
                            
                            <!-- View Modal -->
                            <div class="modal fade" id="viewModal<?php echo $cma['id']; ?>">
                                <div class="modal-dialog modal-lg">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">CMA Data Report #<?php echo $cma['id']; ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="info-label">User</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['user_name']); ?></div>
                                                    <div class="info-label">Business Name</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['business_name']); ?></div>
                                                    <div class="info-label">PAN Number</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['pan_number']); ?></div>
                                                    <div class="info-label">Financial Year</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['financial_year']); ?></div>
                                                    <div class="info-label">Bank Name</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['bank_name']); ?></div>
                                                    <div class="info-label">Loan Purpose</div>
                                                    <div class="info-value"><?php echo htmlspecialchars($cma['loan_purpose']); ?></div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="info-label">Loan Amount</div>
                                                    <div class="info-value">₹<?php echo number_format($cma['loan_amount'], 2); ?></div>
                                                    <div class="info-label">Projected Sales</div>
                                                    <div class="info-value">₹<?php echo number_format($cma['projected_sales'], 2); ?></div>
                                                    <div class="info-label">Projected Profit</div>
                                                    <div class="info-value">₹<?php echo number_format($cma['projected_profit'], 2); ?></div>
                                                    <div class="info-label">Working Capital</div>
                                                    <div class="info-value">₹<?php echo number_format($cma['working_capital'], 2); ?></div>
                                                    <div class="info-label">Debt Equity Ratio</div>
                                                    <div class="info-value"><?php echo $cma['debt_equity_ratio']; ?></div>
                                                    <div class="info-label">Assigned To</div>
                                                    <div class="info-value"><?php echo $cma['assigned_name'] ?: 'Unassigned'; ?></div>
                                                </div>
                                            </div>
                                            <?php if($cma['notes']): ?>
                                            <div class="info-label">Notes</div>
                                            <div class="info-value"><?php echo nl2br(htmlspecialchars($cma['notes'])); ?></div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Update Modal -->
                            <div class="modal fade" id="updateModal<?php echo $cma['id']; ?>">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title">Update Status #<?php echo $cma['id']; ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <form method="POST">
                                            <div class="modal-body">
                                                <input type="hidden" name="cma_id" value="<?php echo $cma['id']; ?>">
                                                <div class="mb-3">
                                                    <label class="form-label">Status</label>
                                                    <select name="status" class="form-select">
                                                        <option value="Pending" <?php echo $cma['status']=='Pending'?'selected':''; ?>>Pending</option>
                                                        <option value="In Progress" <?php echo $cma['status']=='In Progress'?'selected':''; ?>>In Progress</option>
                                                        <option value="Completed" <?php echo $cma['status']=='Completed'?'selected':''; ?>>Completed</option>
                                                    </select>
                                                </div>
                                                <div class="mb-3">
                                                    <label class="form-label">Notes</label>
                                                    <textarea name="notes" class="form-control" rows="4"><?php echo htmlspecialchars($cma['notes']); ?></textarea>
                                                </div>
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                                <button type="submit" name="update_status" class="btn btn-primary">Update</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted">No CMA data reports found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>