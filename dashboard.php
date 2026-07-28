<!-- Statistics Cards -->
<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fas fa-file-alt"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_applications']; ?></div>
            <div class="stat-label">Total Applications</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fas fa-clock"></i>
            </div>
            <div class="stat-value"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fas fa-check-circle"></i>
            </div>
            <div class="stat-value"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card red">
            <div class="stat-icon">
                <i class="fas fa-users"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_users']; ?></div>
            <div class="stat-label">Total Users</div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-xl-3 col-md-6">
        <div class="stat-card purple">
            <div class="stat-icon">
                <i class="fas fa-comments"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_feedback']; ?></div>
            <div class="stat-label">Feedback Received</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card orange">
            <div class="stat-icon">
                <i class="fas fa-envelope"></i>
            </div>
            <div class="stat-value"><?php echo $stats['total_contacts']; ?></div>
            <div class="stat-label">Contact Messages</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card green">
            <div class="stat-icon">
                <i class="fas fa-star"></i>
            </div>
            <div class="stat-value"><?php echo $stats['avg_rating']; ?>/5</div>
            <div class="stat-label">Average Rating</div>
        </div>
    </div>
    <div class="col-xl-3 col-md-6">
        <div class="stat-card blue">
            <div class="stat-icon">
                <i class="fas fa-spinner"></i>
            </div>
            <div class="stat-value"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
    </div>
</div>

<!-- Recent Applications -->
<div class="row mt-4">
    <div class="col-lg-8">
        <div class="data-card">
            <div class="data-card-header">
                <h5><i class="fas fa-file-alt me-2"></i>Recent Applications</h5>
                <a href="?page=applications" class="btn btn-sm btn-light">View All</a>
            </div>
            <div class="data-card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $recent_apps = $pdo->query("
                                SELECT sa.*, u.name, u.email 
                                FROM service_applications sa 
                                JOIN users u ON sa.user_id = u.id 
                                ORDER BY sa.created_at DESC 
                                LIMIT 5
                            ")->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach($recent_apps as $app):
                                $statusClass = 'pending';
                                if($app['status'] === 'Completed') $statusClass = 'completed';
                                elseif($app['status'] === 'In Progress') $statusClass = 'progress';
                                elseif($app['status'] === 'Rejected') $statusClass = 'rejected';
                            ?>
                            <tr>
                                <td>#<?php echo $app['id']; ?></td>
                                <td>
                                    <div><strong><?php echo htmlspecialchars($app['name']); ?></strong></div>
                                    <small class="text-muted"><?php echo htmlspecialchars($app['email']); ?></small>
                                </td>
                                <td><span class="badge bg-primary"><?php echo $app['service_type']; ?></span></td>
                                <td><?php echo date('d M Y', strtotime($app['created_at'])); ?></td>
                                <td><span class="badge badge-<?php echo $statusClass; ?>"><?php echo $app['status']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-4">
        <div class="data-card">
            <div class="data-card-header">
                <h5><i class="fas fa-chart-pie me-2"></i>Service Distribution</h5>
            </div>
            <div class="data-card-body">
                <?php
                $service_stats = $pdo->query("
                    SELECT service_type, COUNT(*) as count 
                    FROM service_applications 
                    GROUP BY service_type 
                    ORDER BY count DESC 
                    LIMIT 5
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                $total = array_sum(array_column($service_stats, 'count'));
                
                foreach($service_stats as $service):
                    $percentage = $total > 0 ? round(($service['count'] / $total) * 100) : 0;
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <span><?php echo $service['service_type']; ?></span>
                        <span><strong><?php echo $service['count']; ?></strong> (<?php echo $percentage; ?>%)</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo $percentage; ?>%; background: linear-gradient(90deg, #FF8C42, #e67e3c);" aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100"></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <div class="data-card mt-3">
            <div class="data-card-header">
                <h5><i class="fas fa-bell me-2"></i>Recent Feedback</h5>
            </div>
            <div class="data-card-body">
                <?php
                $recent_feedback = $pdo->query("
                    SELECT f.*, u.name 
                    FROM feedback f 
                    JOIN users u ON f.user_id = u.id 
                    ORDER BY f.created_at DESC 
                    LIMIT 3
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach($recent_feedback as $fb):
                ?>
                <div class="mb-3 pb-3 border-bottom">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <strong><?php echo htmlspecialchars($fb['name']); ?></strong>
                        <div class="stars" style="color: #f39c12; font-size: 0.9rem;">
                            <?php for($i=0; $i<$fb['rating']; $i++): ?>
                                <i class="fas fa-star"></i>
                            <?php endfor; ?>
                        </div>
                    </div>
                    <p class="text-muted mb-1" style="font-size: 0.9rem;">
                        <?php echo htmlspecialchars(substr($fb['feedback_text'], 0, 100)); ?><?php echo strlen($fb['feedback_text']) > 100 ? '...' : ''; ?>
                    </p>
                    <small class="text-muted"><?php echo date('d M Y', strtotime($fb['created_at'])); ?></small>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>