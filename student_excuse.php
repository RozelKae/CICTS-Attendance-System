<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ExcuseManager.php';

$auth = new Auth();
$auth->requireRole(['student']);

$excuseManager = new ExcuseManager();
$studentId = $auth->getRoleId();

$message = '';
$messageType = '';

// Handle excuse submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_excuse'])) {
    $excuseType = $_POST['excuse_type'] ?? '';
    $description = $_POST['description'] ?? '';
    $attendanceIds = $_POST['attendance_ids'] ?? [];
    
    if (empty($excuseType) || empty($description) || empty($attendanceIds)) {
        $message = 'Please fill in all required fields and select at least one absence';
        $messageType = 'error';
    } else {
        $result = $excuseManager->submitExcuse(
            $studentId,
            $excuseType,
            $description,
            $attendanceIds,
            $_FILES['attachments'] ?? []
        );
        
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
    }
}

// Get absent records for selection
$absentRecords = $excuseManager->getStudentAbsentRecords($studentId);

// Get excuse history
$excuseHistory = $excuseManager->getExcuses(['student_id' => $studentId]);

$pageTitle = 'Submit Excuse';
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
    <?php include 'includes/student_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>📝 Submit Excuse Letter</h1>
                <p>Submit excuse letters for your absences</p>
            </div>
            
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>
            
            <!-- Submit Excuse Section -->
            <div class="section-card">
                <h2>Submit New Excuse</h2>
                
                <?php if (empty($absentRecords)): ?>
                    <div class="info-box">
                        <span class="info-icon">✓</span>
                        <div>
                            <strong>No absences to excuse!</strong>
                            <p>You don't have any unexcused absences at the moment.</p>
                        </div>
                    </div>
                <?php else: ?>
                    <form method="POST" action="" enctype="multipart/form-data" id="excuseForm">
                        <div class="form-section">
                            <h3>1. Select Absences to Excuse</h3>
                            <p class="form-hint">Select one or more absences that this excuse letter will cover</p>
                            
                            <div class="absence-list">
                                <?php foreach ($absentRecords as $record): ?>
                                    <?php if ($record['has_excuse']): continue; endif; ?>
                                    <label class="absence-item">
                                        <input type="checkbox" 
                                               name="attendance_ids[]" 
                                               value="<?php echo $record['attendance_id']; ?>"
                                               class="absence-checkbox">
                                        <div class="absence-details">
                                            <div class="absence-date">
                                                <?php echo date('M d, Y', strtotime($record['session_date'])); ?>
                                                <span class="absence-day"><?php echo date('l', strtotime($record['session_date'])); ?></span>
                                            </div>
                                            <div class="absence-class">
                                                <strong><?php echo htmlspecialchars($record['course_code']); ?></strong> - 
                                                <?php echo htmlspecialchars($record['section']); ?>
                                            </div>
                                            <div class="absence-time">
                                                <?php echo date('g:i A', strtotime($record['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($record['end_time'])); ?>
                                            </div>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        
                        <div class="form-section">
                            <h3>2. Excuse Details</h3>
                            
                            <div class="form-group">
                                <label for="excuse_type">Excuse Type <span class="required">*</span></label>
                                <select name="excuse_type" id="excuse_type" class="form-control" required>
                                    <option value="">Select excuse type...</option>
                                    <option value="Medical">Medical (Sick, Doctor's appointment)</option>
                                    <option value="Emergency">Emergency (Family emergency, accident)</option>
                                    <option value="School Business">School Business (Competition, official activity)</option>
                                    <option value="Personal">Personal (Family matter, important errand)</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label for="description">Description <span class="required">*</span></label>
                                <textarea name="description" 
                                          id="description" 
                                          rows="5" 
                                          class="form-control" 
                                          placeholder="Please explain the reason for your absence in detail..."
                                          required></textarea>
                                <small class="form-hint">Be specific and honest. This will help in the approval process.</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="attachments">Attachments (Optional)</label>
                                <input type="file" 
                                       name="attachments[]" 
                                       id="attachments" 
                                       class="form-control-file"
                                       accept=".pdf,.jpg,.jpeg,.png"
                                       multiple>
                                <small class="form-hint">
                                    Upload supporting documents (medical certificate, excuse letter, etc.)<br>
                                    Accepted: PDF, JPG, PNG | Max 3 files, 5MB each
                                </small>
                            </div>
                        </div>
                        
                        <div class="form-actions">
                            <button type="submit" name="submit_excuse" class="btn btn-primary">
                                📤 Submit Excuse Letter
                            </button>
                            <button type="reset" class="btn btn-secondary">Clear Form</button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
            
            <!-- Excuse History Section -->
            <div class="section-card">
                <h2>Excuse Letter History</h2>
                
                <?php if (empty($excuseHistory)): ?>
                    <div class="empty-state">
                        <p>No excuse letters submitted yet.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Type</th>
                                    <th>Description</th>
                                    <th>Dates Affected</th>
                                    <th>Attachments</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                    <th>Approver</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($excuseHistory as $excuse): ?>
                                    <tr>
                                        <td><strong>#<?php echo $excuse['excuse_id']; ?></strong></td>
                                        <td><?php echo htmlspecialchars($excuse['excuse_type']); ?></td>
                                        <td>
                                            <div class="excuse-description">
                                                <?php echo htmlspecialchars(substr($excuse['description'], 0, 80)); ?>
                                                <?php if (strlen($excuse['description']) > 80): ?>...<?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="badge badge-secondary">
                                                <?php echo $excuse['affected_dates_count']; ?> date(s)
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($excuse['attachment_count'] > 0): ?>
                                                <span class="badge badge-info">
                                                    📎 <?php echo $excuse['attachment_count']; ?> file(s)
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
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
                                            <?php if ($excuse['approver_name']): ?>
                                                <?php echo htmlspecialchars($excuse['approver_name']); ?>
                                            <?php else: ?>
                                                <span class="text-muted">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php if ($excuse['status'] === 'rejected' && $excuse['rejection_reason']): ?>
                                        <tr class="rejection-row">
                                            <td colspan="8">
                                                <div class="rejection-reason">
                                                    <strong>Rejection Reason:</strong> 
                                                    <?php echo htmlspecialchars($excuse['rejection_reason']); ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
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
        }
        
        .dashboard-container {
            display: flex;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            flex: 1;
            min-height: 100vh;
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
        
        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
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
        
        .info-box {
            display: flex;
            gap: 15px;
            padding: 20px;
            background: #d4edda;
            border: 1px solid #c3e6cb;
            border-radius: 8px;
            color: #155724;
        }
        
        .info-icon {
            font-size: 32px;
        }
        
        .form-section {
            margin-bottom: 30px;
            padding-bottom: 25px;
            border-bottom: 2px solid #f0f0f0;
        }
        
        .form-section:last-of-type {
            border-bottom: none;
        }
        
        .form-section h3 {
            color: #667eea;
            font-size: 18px;
            margin-bottom: 10px;
        }
        
        .form-hint {
            color: #7f8c8d;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .absence-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            max-height: 400px;
            overflow-y: auto;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .absence-item {
            display: flex;
            gap: 15px;
            padding: 15px;
            background: white;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .absence-item:hover {
            border-color: #667eea;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
        }
        
        .absence-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        
        .absence-details {
            flex: 1;
        }
        
        .absence-date {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        
        .absence-day {
            font-weight: normal;
            color: #7f8c8d;
            font-size: 13px;
            margin-left: 8px;
        }
        
        .absence-class {
            color: #667eea;
            margin-bottom: 3px;
            font-size: 14px;
        }
        
        .absence-time {
            color: #666;
            font-size: 13px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
        }
        
        .required {
            color: #dc3545;
        }
        
        .form-control {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
            font-family: inherit;
            transition: border-color 0.3s;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .form-control-file {
            display: block;
            padding: 10px 0;
        }
        
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
        }
        
        .btn {
            padding: 12px 24px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.2s;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
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
            font-size: 13px;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
        }
        
        .data-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .excuse-description {
            max-width: 300px;
            color: #555;
        }
        
        .rejection-row {
            background: #fff3cd !important;
        }
        
        .rejection-reason {
            padding: 10px;
            color: #856404;
        }
        
        .badge {
            display: inline-block;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
        }
        
        .badge-info {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .badge-secondary {
            background: #e2e3e5;
            color: #383d41;
        }
        
        .text-muted {
            color: #999;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .form-actions {
                flex-direction: column;
            }
        }
    </style>
</body>
</html>