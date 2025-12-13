<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'StudentManager.php';

$auth = new Auth();
$auth->requireRole(['secretary']);

$studentManager = new StudentManager();

$message = '';
$messageType = '';

// Handle deactivate RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deactivate_rfid'])) {
    $studentId = $_POST['student_id'] ?? null;
    $reason = $_POST['reason'] ?? '';
    $result = $studentManager->deactivateRFID($studentId, $reason, $auth->getUserId());
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle replace RFID
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['replace_rfid'])) {
    $studentId = $_POST['student_id'] ?? null;
    $newRfidUid = strtoupper(trim($_POST['new_rfid_uid'] ?? ''));
    $reason = $_POST['replace_reason'] ?? '';
    $result = $studentManager->replaceRFID($studentId, $newRfidUid, $reason, $auth->getUserId());
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Get filters
$filterProgram = $_GET['program'] ?? '';
$filterSection = $_GET['section'] ?? '';
$filterStatus = $_GET['status'] ?? '';
$filterSearch = $_GET['search'] ?? '';

$filters = [
    'program' => $filterProgram,
    'section' => $filterSection,
    'search' => $filterSearch
];

$students = $studentManager->getStudents($filters);

// Filter by RFID status
if ($filterStatus === 'registered') {
    $students = array_filter($students, fn($s) => !empty($s['rfid_uid']) && $s['rfid_status'] === 'active');
} elseif ($filterStatus === 'unregistered') {
    $students = array_filter($students, fn($s) => empty($s['rfid_uid']));
} elseif ($filterStatus === 'deactivated') {
    $students = array_filter($students, fn($s) => !empty($s['rfid_uid']) && $s['rfid_status'] === 'deactivated');
}

$unregisteredCount = count(array_filter($studentManager->getStudents([]), fn($s) => empty($s['rfid_uid'])));

$pageTitle = 'RFID Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle . ' - ' . SITE_NAME; ?></title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f6fa; }
        .dashboard-container { display: flex; }
        .page-header { margin-bottom: 30px; }
        .page-header h1 { font-size: 32px; color: #2c3e50; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .alert-warning { background: #fff3cd; color: #856404; border: 1px solid #ffc107; }
        
        .filter-section { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .filter-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .filter-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; font-size: 14px; }
        .filter-select, .filter-input { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        
        .section-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e0e0e0; font-size: 13px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tbody tr:hover { background: #f8f9fa; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-secondary { background: #e2e3e5; color: #383d41; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); }
        .modal-content { background: white; margin: 10% auto; padding: 0; width: 90%; max-width: 500px; border-radius: 12px; }
        .modal-header { display: flex; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid #e0e0e0; }
        .modal-body { padding: 25px; }
        .close { font-size: 28px; font-weight: bold; color: #999; cursor: pointer; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; font-family: monospace; }
        .required { color: #dc3545; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include 'includes/secretary_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType; ?>"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>
            
            <div class="page-header">
                <h1>🎴 RFID Management</h1>
                <p>Manage student RFID cards and registrations</p>
            </div>
            
            <?php if ($unregisteredCount > 0): ?>
                <div class="alert alert-warning">
                    <strong>⚠️ Alert:</strong> <?php echo $unregisteredCount; ?> student(s) haven't registered their RFID cards yet.
                    <a href="secretary_rfid.php?status=unregistered" style="text-decoration: underline; font-weight: 600; color: #856404; margin-left: 10px;">View Unregistered →</a>
                </div>
            <?php endif; ?>
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-grid">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Name or student number..." value="<?php echo htmlspecialchars($filterSearch); ?>">
                    </div>
                    <div class="filter-group">
                        <label>Program</label>
                        <select name="program" class="filter-select">
                            <option value="">All Programs</option>
                            <option value="BSIT" <?php echo $filterProgram === 'BSIT' ? 'selected' : ''; ?>>BSIT</option>
                            <option value="BSCS" <?php echo $filterProgram === 'BSCS' ? 'selected' : ''; ?>>BSCS</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>Section</label>
                        <input type="text" name="section" class="filter-input" placeholder="e.g., 3A" value="<?php echo htmlspecialchars($filterSection); ?>">
                    </div>
                    <div class="filter-group">
                        <label>RFID Status</label>
                        <select name="status" class="filter-select">
                            <option value="">All Status</option>
                            <option value="registered" <?php echo $filterStatus === 'registered' ? 'selected' : ''; ?>>✓ Registered</option>
                            <option value="unregistered" <?php echo $filterStatus === 'unregistered' ? 'selected' : ''; ?>>⚠ Not Registered</option>
                            <option value="deactivated" <?php echo $filterStatus === 'deactivated' ? 'selected' : ''; ?>>❌ Deactivated</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="secretary_rfid.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- RFID Table -->
            <div class="section-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student #</th>
                                <th>Name</th>
                                <th>Program/Year</th>
                                <th>Section</th>
                                <th>RFID UID</th>
                                <th>Status</th>
                                <th>Registered</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['student_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program'] . ' ' . $student['year_level']); ?></td>
                                    <td><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($student['rfid_uid'])): ?>
                                            <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 12px;">
                                                <?php echo htmlspecialchars($student['rfid_uid']); ?>
                                            </code>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['rfid_uid']) && $student['rfid_status'] === 'active'): ?>
                                            <span class="badge badge-success">✓ Active</span>
                                        <?php elseif (!empty($student['rfid_uid']) && $student['rfid_status'] === 'deactivated'): ?>
                                            <span class="badge badge-danger">❌ Deactivated</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⚠ Not Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['rfid_registered_at'])): ?>
                                            <?php echo date('M d, Y', strtotime($student['rfid_registered_at'])); ?>
                                        <?php else: ?>
                                            <span style="color: #999;">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($student['rfid_uid']) && $student['rfid_status'] === 'active'): ?>
                                            <button onclick="deactivateRFID(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>')" class="btn btn-sm btn-warning">
                                                Deactivate
                                            </button>
                                            <button onclick="replaceRFID(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>')" class="btn btn-sm btn-primary">
                                                Replace
                                            </button>
                                        <?php elseif (!empty($student['rfid_uid']) && $student['rfid_status'] === 'deactivated'): ?>
                                            <button onclick="replaceRFID(<?php echo $student['student_id']; ?>, '<?php echo htmlspecialchars($student['full_name'], ENT_QUOTES); ?>')" class="btn btn-sm btn-primary">
                                                Register New
                                            </button>
                                        <?php else: ?>
                                            <span style="color: #999; font-size: 12px;">Student must register</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Deactivate Modal -->
    <div id="deactivateModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Deactivate RFID Card</h3>
                <span class="close" onclick="closeDeactivateModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; color: #666;">
                    Deactivating for: <strong id="deactivate_student_name"></strong>
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="student_id" id="deactivate_student_id">
                    <div class="form-group">
                        <label>Reason for Deactivation <span class="required">*</span></label>
                        <select name="reason" class="form-control" required>
                            <option value="">Select reason...</option>
                            <option value="Lost Card">Lost Card</option>
                            <option value="Stolen Card">Stolen Card</option>
                            <option value="Damaged Card">Damaged Card</option>
                            <option value="Card Malfunction">Card Malfunction</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="deactivate_rfid" class="btn btn-danger">Deactivate Card</button>
                        <button type="button" class="btn btn-secondary" onclick="closeDeactivateModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Replace Modal -->
    <div id="replaceModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Replace RFID Card</h3>
                <span class="close" onclick="closeReplaceModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p style="margin-bottom: 15px; color: #666;">
                    Replacing card for: <strong id="replace_student_name"></strong>
                </p>
                <form method="POST" action="">
                    <input type="hidden" name="student_id" id="replace_student_id">
                    <div class="form-group">
                        <label>New RFID UID <span class="required">*</span></label>
                        <input type="text" name="new_rfid_uid" id="new_rfid_input" class="form-control" placeholder="Tap the new RFID card..." required autocomplete="off">
                        <small style="color: #666; font-size: 12px;">Tap the new RFID card or enter UID manually</small>
                    </div>
                    <div class="form-group">
                        <label>Reason for Replacement</label>
                        <input type="text" name="replace_reason" class="form-control" placeholder="e.g., Lost card, damaged, etc.">
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" name="replace_rfid" class="btn btn-primary">Replace Card</button>
                        <button type="button" class="btn btn-secondary" onclick="closeReplaceModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function deactivateRFID(studentId, studentName) {
            document.getElementById('deactivate_student_id').value = studentId;
            document.getElementById('deactivate_student_name').textContent = studentName;
            document.getElementById('deactivateModal').style.display = 'block';
        }
        
        function closeDeactivateModal() {
            document.getElementById('deactivateModal').style.display = 'none';
        }
        
        function replaceRFID(studentId, studentName) {
            document.getElementById('replace_student_id').value = studentId;
            document.getElementById('replace_student_name').textContent = studentName;
            document.getElementById('replaceModal').style.display = 'block';
            
            // Auto-focus on RFID input
            setTimeout(() => {
                document.getElementById('new_rfid_input').focus();
            }, 100);
        }
        
        function closeReplaceModal() {
            document.getElementById('replaceModal').style.display = 'none';
        }
        
        // Auto-submit when RFID is scanned (Enter key detection)
        document.getElementById('new_rfid_input')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                if (this.value.trim().length >= 8) {
                    this.form.submit();
                }
            }
        });
        
        window.onclick = function(e) {
            if (e.target.className === 'modal') {
                e.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>