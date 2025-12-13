<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'StudentManager.php';
require_once 'ClassManager.php';
require_once 'Database.php';

$auth = new Auth();
$auth->requireRole(['secretary']);

$studentManager = new StudentManager();
$classManager = new ClassManager();
$db = new Database();

// Get system statistics
$totalStudents = $db->single("SELECT COUNT(*) as count FROM students JOIN users ON students.user_id = users.user_id WHERE users.status = 'active'")['count'];
$totalClasses = $db->single("SELECT COUNT(*) as count FROM classes WHERE status = 'active'")['count'];
$studentsWithRFID = $db->single("SELECT COUNT(*) as count FROM students WHERE rfid_uid IS NOT NULL AND rfid_status = 'active'")['count'];
$studentsWithoutRFID = $totalStudents - $studentsWithRFID;

// Get recent registrations (last 10)
$recentRFID = $db->all(
    "SELECT s.student_number, CONCAT(u.first_name, ' ', u.last_name) as student_name, 
     s.rfid_registered_at, s.rfid_uid
     FROM students s
     JOIN users u ON s.user_id = u.user_id
     WHERE s.rfid_uid IS NOT NULL
     ORDER BY s.rfid_registered_at DESC
     LIMIT 10"
);

// Get recent activity from audit logs
$recentActivity = $db->all(
    "SELECT a.*, CONCAT(u.first_name, ' ', u.last_name) as user_name, u.user_type
     FROM audit_logs a
     JOIN users u ON a.user_id = u.user_id
     ORDER BY a.timestamp DESC
     LIMIT 10"
);

// Set page title
$pageTitle = 'Secretary Dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
</head>
<body>
    <?php include 'includes/secretary_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>Secretary Dashboard</h1>
                <p>System Administration & Management</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">👥</div>
                    <div class="stat-info">
                        <h3><?php echo $totalStudents; ?></h3>
                        <p>Total Students</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-info">
                        <h3><?php echo $totalClasses; ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
                
                <div class="stat-card stat-success">
                    <div class="stat-icon">✅</div>
                    <div class="stat-info">
                        <h3><?php echo $studentsWithRFID; ?></h3>
                        <p>RFID Registered</p>
                    </div>
                </div>
                
                <div class="stat-card <?php echo $studentsWithoutRFID > 0 ? 'stat-warning' : ''; ?>">
                    <div class="stat-icon"><?php echo $studentsWithoutRFID > 0 ? '⚠️' : '✓'; ?></div>
                    <div class="stat-info">
                        <h3><?php echo $studentsWithoutRFID; ?></h3>
                        <p>Without RFID</p>
                    </div>
                </div>
            </div>
            
            <!-- Alerts -->
            <?php if ($studentsWithoutRFID > 0): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Action Required:</strong> 
                    <?php echo $studentsWithoutRFID; ?> student(s) haven't registered their RFID cards yet. 
                    <a href="secretary_rfid.php" style="color: #856404; text-decoration: underline; font-weight: 600;">Manage RFID Cards →</a>
                </div>
            <?php endif; ?>
            
            <!-- Quick Actions -->
            <div class="section-card">
                <h2>⚡ Quick Actions</h2>
                <div class="actions-grid">
                    <a href="secretary_students.php" class="action-card">
                        <span class="action-icon">👥</span>
                        <h3>Manage Students</h3>
                        <p>Add, edit, or view student records</p>
                    </a>
                    
                    <a href="secretary_classes.php" class="action-card">
                        <span class="action-icon">📚</span>
                        <h3>Manage Classes</h3>
                        <p>Create and manage class schedules</p>
                    </a>
                    
                    <a href="secretary_rfid.php" class="action-card">
                        <span class="action-icon">🎴</span>
                        <h3>RFID Management</h3>
                        <p>Register & deactivate RFID cards</p>
                    </a>
                    
                    <a href="secretary_reports.php" class="action-card">
                        <span class="action-icon">📈</span>
                        <h3>Generate Reports</h3>
                        <p>View attendance and system reports</p>
                    </a>
                </div>
            </div>
            
            <!-- Recent RFID Registrations -->
            <div class="section-card">
                <h2>🎴 Recent RFID Registrations</h2>
                
                <?php if (empty($recentRFID)): ?>
                    <p class="empty-state">No RFID registrations yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student Number</th>
                                    <th>Student Name</th>
                                    <th>RFID UID</th>
                                    <th>Registered At</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentRFID as $registration): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($registration['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($registration['student_name']); ?></td>
                                        <td><code><?php echo htmlspecialchars($registration['rfid_uid']); ?></code></td>
                                        <td><?php echo date('M d, Y h:i A', strtotime($registration['rfid_registered_at'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="table-footer">
                        <a href="secretary_rfid.php" class="btn btn-secondary">View All RFID Records →</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent System Activity -->
            <div class="section-card">
                <h2>📋 Recent System Activity</h2>
                
                <?php if (empty($recentActivity)): ?>
                    <p class="empty-state">No recent activity.</p>
                <?php else: ?>
                    <div class="activity-list">
                        <?php foreach ($recentActivity as $activity): ?>
                            <div class="activity-item">
                                <div class="activity-icon">
                                    <?php
                                    $icon = '📝';
                                    switch($activity['action_type']) {
                                        case 'create': $icon = '➕'; break;
                                        case 'update': $icon = '✏️'; break;
                                        case 'delete': $icon = '🗑️'; break;
                                    }
                                    echo $icon;
                                    ?>
                                </div>
                                <div class="activity-details">
                                    <p class="activity-text">
                                        <strong><?php echo htmlspecialchars($activity['user_name']); ?></strong>
                                        <span class="activity-action"><?php echo $activity['action_type']; ?>d</span>
                                        <?php echo htmlspecialchars($activity['table_affected']); ?>
                                    </p>
                                    <p class="activity-time"><?php echo date('M d, Y h:i A', strtotime($activity['timestamp'])); ?></p>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- System Information -->
            <div class="info-cards">
                <div class="info-card">
                    <h3>📊 System Status</h3>
                    <div class="info-row">
                        <span>Database:</span>
                        <span class="status-badge status-success">✓ Connected</span>
                    </div>
                    <div class="info-row">
                        <span>RFID System:</span>
                        <span class="status-badge status-success">✓ Active</span>
                    </div>
                    <div class="info-row">
                        <span>Cron Jobs:</span>
                        <span class="status-badge status-success">✓ Running</span>
                    </div>
                </div>
                
                <div class="info-card">
                    <h3>📈 Quick Stats</h3>
                    <div class="info-row">
                        <span>RFID Registration Rate:</span>
                        <span><strong><?php echo $totalStudents > 0 ? round(($studentsWithRFID / $totalStudents) * 100) : 0; ?>%</strong></span>
                    </div>
                    <div class="info-row">
                        <span>Active Sessions Today:</span>
                        <span><strong><?php echo $db->single("SELECT COUNT(*) as count FROM attendance_sessions WHERE session_date = CURDATE() AND status = 'open'")['count']; ?></strong></span>
                    </div>
                    <div class="info-row">
                        <span>System Version:</span>
                        <span><strong>2.0 (RFID)</strong></span>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f6fa;
            margin: 0;
        }
        
        .dashboard-container {
            display: flex;
        }
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .page-header h1 {
            font-size: 32px;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .page-header p {
            color: #7f8c8d;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 20px;
        }
        
        .stat-card.stat-success {
            border-left: 4px solid #28a745;
        }
        
        .stat-card.stat-warning {
            border-left: 4px solid #ffc107;
        }
        
        .stat-icon {
            font-size: 40px;
        }
        
        .stat-info h3 {
            font-size: 32px;
            color: #667eea;
            margin-bottom: 5px;
        }
        
        .stat-info p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 30px;
        }
        
        .alert-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffc107;
        }
        
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .section-card h2 {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
        }
        
        .actions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
        }
        
        .action-card {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 10px;
            text-decoration: none;
            transition: transform 0.2s, box-shadow 0.2s;
            border-top: 3px solid #667eea;
        }
        
        .action-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .action-icon {
            font-size: 40px;
            display: block;
            margin-bottom: 15px;
        }
        
        .action-card h3 {
            color: #2c3e50;
            font-size: 18px;
            margin-bottom: 8px;
        }
        
        .action-card p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        .data-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .data-table th {
            background: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #2c3e50;
            border-bottom: 2px solid #e0e0e0;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .data-table code {
            background: #f0f0f0;
            padding: 4px 8px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 13px;
        }
        
        .table-footer {
            margin-top: 15px;
            text-align: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 600;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .activity-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .activity-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .activity-icon {
            font-size: 24px;
        }
        
        .activity-details {
            flex: 1;
        }
        
        .activity-text {
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .activity-action {
            color: #667eea;
            font-weight: 600;
            margin: 0 5px;
        }
        
        .activity-time {
            font-size: 12px;
            color: #7f8c8d;
        }
        
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-success {
            background: #d4edda;
            color: #155724;
        }
        
        .empty-state {
            text-align: center;
            color: #7f8c8d;
            padding: 40px 20px;
        }
    </style>
</body>
</html>