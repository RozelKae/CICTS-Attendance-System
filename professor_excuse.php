<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ExcuseManager.php';
require_once 'ClassManager.php';
require_once 'Database.php';

$auth = new Auth();
$auth->requireRole(['professor']);

$excuseManager = new ExcuseManager();
$classManager = new ClassManager();
$db = new Database();

$professorId = $auth->getRoleId();

$message = '';
$messageType = '';

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['approve_excuse'])) {
        $excuseId = $_POST['excuse_id'] ?? null;
        $result = $excuseManager->approveExcuse($excuseId, $auth->getUserId());
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    } elseif (isset($_POST['reject_excuse'])) {
        $excuseId = $_POST['excuse_id'] ?? null;
        $reason = $_POST['rejection_reason'] ?? '';
        $result = $excuseManager->rejectExcuse($excuseId, $auth->getUserId(), $reason);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get excuses for professor's students
$sql = "SELECT DISTINCT e.*,
        s.student_number,
        CONCAT(u.first_name, ' ', u.last_name) as student_name,
        s.program, s.section,
        (SELECT COUNT(*) FROM excuse_dates WHERE excuse_id = e.excuse_id) as affected_dates_count
        FROM excuse_letters e
        JOIN students s ON e.student_id = s.student_id
        JOIN users u ON s.user_id = u.user_id
        JOIN excuse_dates ed ON e.excuse_id = ed.excuse_id
        JOIN attendance_records ar ON ed.attendance_id = ar.attendance_id
        JOIN attendance_sessions asess ON ar.session_id = asess.session_id
        JOIN classes c ON asess.class_id = c.class_id
        WHERE c.professor_id = ?
        ORDER BY 
            CASE e.status 
                WHEN 'pending' THEN 1 
                WHEN 'approved' THEN 2 
                WHEN 'rejected' THEN 3 
            END,
            e.submitted_date DESC";

$excuses = $db->all($sql, [$professorId]);

// Get selected excuse details
$selectedExcuseId = $_GET['excuse_id'] ?? null;
$selectedExcuse = null;
$excuseDates = [];
$attachments = [];

if ($selectedExcuseId) {
    $selectedExcuse = $excuseManager->getExcuseById($selectedExcuseId);
    // Verify this excuse is for professor's student
    if ($selectedExcuse) {
        $verifyClass = $db->single(
            "SELECT c.professor_id FROM excuse_dates ed
             JOIN attendance_records ar ON ed.attendance_id = ar.attendance_id
             JOIN attendance_sessions asess ON ar.session_id = asess.session_id
             JOIN classes c ON asess.class_id = c.class_id
             WHERE ed.excuse_id = ? LIMIT 1",
            [$selectedExcuseId]
        );
        
        if ($verifyClass && $verifyClass['professor_id'] == $professorId) {
            $excuseDates = $excuseManager->getExcuseDates($selectedExcuseId);
            $attachments = $excuseManager->getAttachments($selectedExcuseId);
        } else {
            $selectedExcuse = null;
        }
    }
}

$pageTitle = 'Excuse Letters';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
</head>
<body>
    <?php include 'includes/professor_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <?php if (!$selectedExcuseId): ?>
                <!-- Excuse List View -->
                <div class="page-header">
                    <h1>📝 Excuse Letters</h1>
                    <p>Review and approve excuse letters from your students</p>
                </div>
                
                <!-- Summary Cards -->
                <div class="summary-cards">
                    <?php
                    $pending = count(array_filter($excuses, fn($e) => $e['status'] === 'pending'));
                    $approved = count(array_filter($excuses, fn($e) => $e['status'] === 'approved'));
                    $rejected = count(array_filter($excuses, fn($e) => $e['status'] === 'rejected'));
                    ?>
                    <div class="summary-card card-warning">
                        <div class="summary-icon">⏳</div>
                        <div class="summary-info">
                            <h3><?php echo $pending; ?></h3>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="summary-card card-success">
                        <div class="summary-icon">✅</div>
                        <div class="summary-info">
                            <h3><?php echo $approved; ?></h3>
                            <p>Approved</p>
                        </div>
                    </div>
                    <div class="summary-card card-danger">
                        <div class="summary-icon">❌</div>
                        <div class="summary-info">
                            <h3><?php echo $rejected; ?></h3>
                            <p>Rejected</p>
                        </div>
                    </div>
                </div>
                
                <!-- Excuses Table -->
                <div class="section-card">
                    <h2>All Excuse Letters</h2>
                    
                    <?php if (empty($excuses)): ?>
                        <div class="empty-state">
                            <p>No excuse letters submitted yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Student</th>
                                        <th>Type</th>
                                        <th>Dates</th>
                                        <th>Submitted</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($excuses as $excuse): ?>
                                        <tr>
                                            <td><strong>#<?php echo $excuse['excuse_id']; ?></strong></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($excuse['student_name']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($excuse['student_number']); ?></small>
                                            </td>
                                            <td><?php echo htmlspecialchars($excuse['excuse_type']); ?></td>
                                            <td>
                                                <span class="badge badge-secondary">
                                                    <?php echo $excuse['affected_dates_count']; ?> date(s)
                                                </span>
                                            </td>
                                            <td><?php echo date('M d, Y', strtotime($excuse['submitted_date'])); ?></td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'pending' => 'badge-warning',
                                                    'approved' => 'badge-success',
                                                    'rejected' => 'badge-danger'
                                                ][$excuse['status']];
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($excuse['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <a href="professor_excuse.php?excuse_id=<?php echo $excuse['excuse_id']; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Excuse Details View -->
                <?php if ($selectedExcuse): ?>
                    <div class="page-header">
                        <div class="header-flex">
                            <div>
                                <h1>Excuse Letter #<?php echo $selectedExcuse['excuse_id']; ?></h1>
                                <p>Submitted by <?php echo htmlspecialchars($selectedExcuse['student_name']); ?></p>
                            </div>
                            <a href="professor_excuse.php" class="btn btn-secondary">← Back to List</a>
                        </div>
                    </div>
                    
                    <!-- Student Info -->
                    <div class="info-card">
                        <h3>Student Information</h3>
                        <div class="info-grid">
                            <div class="info-item">
                                <span class="info-label">Student Number:</span>
                                <span class="info-value"><?php echo htmlspecialchars($selectedExcuse['student_number']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Name:</span>
                                <span class="info-value"><?php echo htmlspecialchars($selectedExcuse['student_name']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Program:</span>
                                <span class="info-value"><?php echo htmlspecialchars($selectedExcuse['program'] . ' ' . $selectedExcuse['year_level']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Section:</span>
                                <span class="info-value"><?php echo htmlspecialchars($selectedExcuse['section'] ?? 'N/A'); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Excuse Details -->
                    <div class="section-card">
                        <h2>Excuse Details</h2>
                        <div class="excuse-details">
                            <div class="detail-row">
                                <strong>Excuse Type:</strong>
                                <span><?php echo htmlspecialchars($selectedExcuse['excuse_type']); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Submitted Date:</strong>
                                <span><?php echo date('F d, Y h:i A', strtotime($selectedExcuse['submitted_date'])); ?></span>
                            </div>
                            <div class="detail-row">
                                <strong>Status:</strong>
                                <?php
                                $statusClass = [
                                    'pending' => 'badge-warning',
                                    'approved' => 'badge-success',
                                    'rejected' => 'badge-danger'
                                ][$selectedExcuse['status']];
                                ?>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($selectedExcuse['status']); ?>
                                </span>
                            </div>
                            <div class="detail-row full-width">
                                <strong>Description:</strong>
                                <p class="description-text"><?php echo nl2br(htmlspecialchars($selectedExcuse['description'])); ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Affected Dates -->
                    <div class="section-card">
                        <h2>Affected Absences</h2>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Class</th>
                                        <th>Time</th>
                                        <th>Current Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($excuseDates as $date): ?>
                                        <tr>
                                            <td><?php echo date('M d, Y (l)', strtotime($date['session_date'])); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($date['course_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($date['section']); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($date['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($date['end_time'])); ?>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'absent' => 'badge-danger',
                                                    'excused' => 'badge-info'
                                                ][$date['attendance_status']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($date['attendance_status']); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Attachments -->
                    <?php if (!empty($attachments)): ?>
                        <div class="section-card">
                            <h2>Attachments (<?php echo count($attachments); ?>)</h2>
                            <div class="attachments-grid">
                                <?php foreach ($attachments as $attachment): ?>
                                    <div class="attachment-item">
                                        <div class="attachment-icon">
                                            <?php echo strpos($attachment['file_type'], 'pdf') !== false ? '📄' : '🖼️'; ?>
                                        </div>
                                        <div class="attachment-info">
                                            <strong><?php echo htmlspecialchars($attachment['file_name']); ?></strong>
                                            <small><?php echo number_format($attachment['file_size'] / 1024, 2); ?> KB</small>
                                        </div>
                                        <a href="<?php echo UPLOAD_DIR . $attachment['file_path']; ?>" 
                                           class="btn btn-sm btn-secondary" 
                                           target="_blank">
                                            View
                                        </a>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Action Buttons -->
                    <?php if ($selectedExcuse['status'] === 'pending'): ?>
                        <div class="action-section">
                            <h3>Review Actions</h3>
                            <div class="action-buttons">
                                <form method="POST" action="" style="display: inline;">
                                    <input type="hidden" name="excuse_id" value="<?php echo $selectedExcuse['excuse_id']; ?>">
                                    <button type="submit" name="approve_excuse" class="btn btn-success" onclick="return confirm('Approve this excuse letter?')">
                                        ✅ Approve Excuse
                                    </button>
                                </form>
                                
                                <button onclick="showRejectModal()" class="btn btn-danger">
                                    ❌ Reject Excuse
                                </button>
                            </div>
                        </div>
                    <?php elseif ($selectedExcuse['status'] === 'rejected'): ?>
                        <div class="rejection-info">
                            <strong>Rejection Reason:</strong>
                            <p><?php echo nl2br(htmlspecialchars($selectedExcuse['rejection_reason'])); ?></p>
                            <small>Rejected on <?php echo date('F d, Y h:i A', strtotime($selectedExcuse['approval_date'])); ?></small>
                        </div>
                    <?php else: ?>
                        <div class="approval-info">
                            <strong>✅ Approved</strong>
                            <p>This excuse was approved on <?php echo date('F d, Y h:i A', strtotime($selectedExcuse['approval_date'])); ?></p>
                        </div>
                    <?php endif; ?>
                    
                <?php else: ?>
                    <div class="alert alert-error">
                        Excuse not found or you don't have permission to view it.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Reject Modal -->
    <div id="rejectModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Reject Excuse Letter</h3>
                <span class="close" onclick="closeRejectModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="excuse_id" value="<?php echo $selectedExcuse['excuse_id'] ?? ''; ?>">
                <div class="form-group">
                    <label for="rejection_reason">Reason for Rejection: <span class="required">*</span></label>
                    <textarea name="rejection_reason" id="rejection_reason" rows="5" class="form-control" required placeholder="Please explain why this excuse is being rejected..."></textarea>
                </div>
                <div class="modal-actions">
                    <button type="submit" name="reject_excuse" class="btn btn-danger">Reject Excuse</button>
                    <button type="button" class="btn btn-secondary" onclick="closeRejectModal()">Cancel</button>
                </div>
            </form>
        </div>
    </div>
    
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; }
        .dashboard-container { display: flex; }
        .page-header { margin-bottom: 30px; }
        .header-flex { display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 32px; color: #2c3e50; margin-bottom: 5px; }
        .page-header p { color: #7f8c8d; }
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .summary-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; }
        .summary-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); display: flex; align-items: center; gap: 15px; border-left: 4px solid #667eea; }
        .summary-card.card-success { border-left-color: #28a745; }
        .summary-card.card-warning { border-left-color: #ffc107; }
        .summary-card.card-danger { border-left-color: #dc3545; }
        .summary-icon { font-size: 32px; }
        .summary-info h3 { font-size: 28px; color: #2c3e50; margin-bottom: 3px; }
        .summary-info p { color: #7f8c8d; font-size: 13px; }
        
        .section-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .section-card h2 { font-size: 20px; color: #2c3e50; margin-bottom: 20px; }
        .info-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .info-card h3 { color: #2c3e50; margin-bottom: 15px; font-size: 18px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .info-item { display: flex; flex-direction: column; }
        .info-label { color: #7f8c8d; font-size: 13px; margin-bottom: 5px; }
        .info-value { color: #2c3e50; font-weight: 600; }
        
        .excuse-details { display: flex; flex-direction: column; gap: 15px; }
        .detail-row { display: flex; gap: 15px; padding: 12px; background: #f8f9fa; border-radius: 6px; }
        .detail-row.full-width { flex-direction: column; }
        .description-text { margin-top: 10px; line-height: 1.6; color: #555; }
        
        .attachments-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 15px; }
        .attachment-item { display: flex; align-items: center; gap: 15px; padding: 15px; background: #f8f9fa; border-radius: 8px; }
        .attachment-icon { font-size: 32px; }
        .attachment-info { flex: 1; }
        .attachment-info strong { display: block; color: #2c3e50; margin-bottom: 3px; }
        .attachment-info small { color: #7f8c8d; }
        
        .action-section { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .action-section h3 { color: #2c3e50; margin-bottom: 15px; }
        .action-buttons { display: flex; gap: 15px; }
        .approval-info, .rejection-info { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .approval-info { border-left: 4px solid #28a745; background: #d4edda; }
        .rejection-info { border-left: 4px solid #dc3545; background: #f8d7da; }
        
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e0e0e0; font-size: 13px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tbody tr:hover { background: #f8f9fa; }
        .text-muted { color: #999; font-size: 12px; }
        .empty-state { text-align: center; padding: 40px 20px; color: #7f8c8d; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 0; width: 90%; max-width: 500px; border-radius: 12px; }
        .modal-header { display: flex; justify-content: space-between; align-items: center; padding: 20px 25px; border-bottom: 1px solid #e0e0e0; }
        .modal-header h3 { color: #2c3e50; font-size: 20px; }
        .close { font-size: 28px; font-weight: bold; color: #999; cursor: pointer; }
        .modal-content form { padding: 25px; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .required { color: #dc3545; }
        .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: inherit; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .summary-cards { grid-template-columns: 1fr; }
            .info-grid { grid-template-columns: 1fr; }
        }
    </style>
    
    <script>
        function showRejectModal() {
            document.getElementById('rejectModal').style.display = 'block';
        }
        
        function closeRejectModal() {
            document.getElementById('rejectModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('rejectModal');
            if (event.target == modal) {
                closeRejectModal();
            }
        }
    </script>
</body>
</html>