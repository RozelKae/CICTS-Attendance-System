<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';
require_once 'Attendance.php';
require_once 'Database.php';

$auth = new Auth();
$auth->requireRole(['professor']);

$classManager = new ClassManager();
$attendance = new Attendance();
$db = new Database();

$professorId = $auth->getRoleId();
$classes = $classManager->getClasses(['professor_id' => $professorId, 'status' => 'active']);

// Get filters
$filterClassId = $_GET['class_id'] ?? '';
$filterDate = $_GET['date'] ?? '';

// Get sessions based on filters
$sessions = [];
if ($filterClassId || !isset($_GET['class_id'])) {
    // Show all professor's sessions if no filter
    if (empty($filterClassId)) {
        $sql = "SELECT asess.*, 
                c.section, co.course_code, co.course_name,
                (SELECT COUNT(*) FROM attendance_records WHERE session_id = asess.session_id) as recorded_count,
                (SELECT COUNT(*) FROM class_enrollments WHERE class_id = asess.class_id AND status = 'enrolled') as total_students
                FROM attendance_sessions asess
                JOIN classes c ON asess.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                WHERE c.professor_id = ?";
        
        $params = [$professorId];
        
        if ($filterDate) {
            $sql .= " AND asess.session_date = ?";
            $params[] = $filterDate;
        }
        
        $sql .= " ORDER BY asess.session_date DESC, asess.start_time DESC LIMIT 100";
        $sessions = $db->all($sql, $params);
    } elseif ($filterClassId) {
        $sql = "SELECT asess.*, 
                c.section, co.course_code, co.course_name,
                (SELECT COUNT(*) FROM attendance_records WHERE session_id = asess.session_id) as recorded_count,
                (SELECT COUNT(*) FROM class_enrollments WHERE class_id = asess.class_id AND status = 'enrolled') as total_students
                FROM attendance_sessions asess
                JOIN classes c ON asess.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                WHERE asess.class_id = ?";
        
        $params = [$filterClassId];
        
        if ($filterDate) {
            $sql .= " AND asess.session_date = ?";
            $params[] = $filterDate;
        }
        
        $sql .= " ORDER BY asess.session_date DESC, asess.start_time DESC LIMIT 50";
        $sessions = $db->all($sql, $params);
    }
}

// Handle edit attendance
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_attendance'])) {
    $attendanceId = $_POST['attendance_id'] ?? null;
    $newStatus = $_POST['status'] ?? '';
    $remarks = $_POST['remarks'] ?? '';
    
    $result = $attendance->editAttendance($attendanceId, $newStatus, $auth->getUserId(), $remarks);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Get selected session details if viewing
$selectedSessionId = $_GET['session_id'] ?? null;
$sessionDetails = null;
$sessionAttendance = [];

if ($selectedSessionId) {
    $sessionDetails = $attendance->getSession($selectedSessionId);
    // Verify professor owns this class
    $classCheck = $db->single("SELECT professor_id FROM classes WHERE class_id = ?", [$sessionDetails['class_id'] ?? 0]);
    if ($classCheck && $classCheck['professor_id'] == $professorId) {
        $sessionAttendance = $attendance->getSessionAttendance($selectedSessionId);
    } else {
        $sessionDetails = null;
    }
}

$pageTitle = 'Attendance Records';
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
            
            <?php if (!$selectedSessionId): ?>
                <!-- Sessions List View -->
                <div class="page-header">
                    <h1>✓ Attendance Records</h1>
                    <p>View and manage attendance for your classes</p>
                </div>
                
                <!-- Filters -->
                <div class="filter-section">
                    <form method="GET" action="" class="filter-form">
                        <div class="filter-group">
                            <label for="class_id">Select Class (Optional):</label>
                            <select name="class_id" id="class_id" class="filter-select">
                                <option value="">All My Classes</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo $filterClassId == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="filter-group">
                            <label for="date">Filter by Date (Optional):</label>
                            <input type="date" name="date" id="date" class="filter-input" value="<?php echo htmlspecialchars($filterDate); ?>">
                        </div>
                        
                        <div class="filter-actions">
                            <button type="submit" class="btn btn-primary">View Sessions</button>
                            <a href="professor_attendance.php" class="btn btn-secondary">Clear</a>
                        </div>
                    </form>
                </div>
                
                <!-- Sessions Table -->
                <div class="section-card">
                    <h2>Attendance Sessions <?php echo empty($filterClassId) ? '(All My Classes)' : ''; ?></h2>
                    
                    <?php if (empty($sessions)): ?>
                        <div class="empty-state">
                            <p>No attendance sessions found for the selected filters.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Time</th>
                                        <th>Class</th>
                                        <th>Status</th>
                                        <th>Recorded</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo date('M d, Y', strtotime($session['session_date'])); ?></strong><br>
                                                <small class="text-muted"><?php echo date('l', strtotime($session['session_date'])); ?></small>
                                            </td>
                                            <td>
                                                <?php echo date('g:i A', strtotime($session['start_time'])); ?> - 
                                                <?php echo date('g:i A', strtotime($session['end_time'])); ?>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($session['course_code']); ?></strong><br>
                                                <small class="text-muted"><?php echo htmlspecialchars($session['section']); ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $statusClass = [
                                                    'scheduled' => 'badge-info',
                                                    'open' => 'badge-success',
                                                    'closed' => 'badge-secondary',
                                                    'cancelled' => 'badge-danger'
                                                ][$session['status']] ?? 'badge-secondary';
                                                ?>
                                                <span class="badge <?php echo $statusClass; ?>">
                                                    <?php echo ucfirst($session['status']); ?>
                                                </span>
                                            </td>
                                            <td>
                                                <strong><?php echo $session['recorded_count']; ?></strong> / <?php echo $session['total_students']; ?>
                                                <?php if ($session['total_students'] > 0): ?>
                                                    <small class="text-muted">
                                                        (<?php echo round(($session['recorded_count'] / $session['total_students']) * 100); ?>%)
                                                    </small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="professor_attendance.php?session_id=<?php echo $session['session_id']; ?>" class="btn btn-sm btn-primary">
                                                    View Details
                                                </a>
                                                <?php if ($session['status'] === 'open'): ?>
                                                    <a href="professor_rfid_reader.php?session_id=<?php echo $session['session_id']; ?>" 
                                                       class="btn btn-sm btn-success" target="_blank">
                                                        RFID Reader
                                                    </a>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                
            <?php else: ?>
                <!-- Session Details View -->
                <?php if ($sessionDetails): ?>
                    <div class="page-header">
                        <div class="header-flex">
                            <div>
                                <h1>Session Details</h1>
                                <p><?php echo htmlspecialchars($sessionDetails['course_code']); ?> - <?php echo htmlspecialchars($sessionDetails['section']); ?></p>
                            </div>
                            <a href="professor_attendance.php?class_id=<?php echo $sessionDetails['class_id']; ?>" class="btn btn-secondary">
                                ← Back to Sessions
                            </a>
                        </div>
                    </div>
                    
                    <!-- Session Info -->
                    <div class="info-card">
                        <div class="session-info-grid">
                            <div class="info-item">
                                <span class="info-label">Date:</span>
                                <span class="info-value"><?php echo htmlspecialchars($sessionDetails['formatted_date']); ?></span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Time:</span>
                                <span class="info-value">
                                    <?php echo htmlspecialchars($sessionDetails['formatted_start']); ?> - 
                                    <?php echo htmlspecialchars($sessionDetails['formatted_end']); ?>
                                </span>
                            </div>
                            <div class="info-item">
                                <span class="info-label">Status:</span>
                                <span class="info-value">
                                    <?php
                                    $statusClass = [
                                        'scheduled' => 'badge-info',
                                        'open' => 'badge-success',
                                        'closed' => 'badge-secondary',
                                        'cancelled' => 'badge-danger'
                                    ][$sessionDetails['status']] ?? 'badge-secondary';
                                    ?>
                                    <span class="badge <?php echo $statusClass; ?>">
                                        <?php echo ucfirst($sessionDetails['status']); ?>
                                    </span>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Attendance Records -->
                    <div class="section-card">
                        <h2>Attendance Records</h2>
                        
                        <?php if (empty($sessionAttendance)): ?>
                            <div class="empty-state">
                                <p>No attendance records yet.</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="data-table">
                                    <thead>
                                        <tr>
                                            <th>Student Number</th>
                                            <th>Name</th>
                                            <th>Time In</th>
                                            <th>Status</th>
                                            <th>Remarks</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessionAttendance as $record): ?>
                                            <tr>
                                                <td><strong><?php echo htmlspecialchars($record['student_number']); ?></strong></td>
                                                <td><?php echo htmlspecialchars($record['student_name']); ?></td>
                                                <td>
                                                    <?php if ($record['time_in']): ?>
                                                        <?php echo htmlspecialchars($record['formatted_time']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php
                                                    $statusClass = [
                                                        'present' => 'badge-success',
                                                        'late' => 'badge-warning',
                                                        'absent' => 'badge-danger',
                                                        'excused' => 'badge-info'
                                                    ][$record['status']] ?? 'badge-secondary';
                                                    ?>
                                                    <span class="badge <?php echo $statusClass; ?>">
                                                        <?php echo ucfirst($record['status']); ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($record['remarks']): ?>
                                                        <?php echo htmlspecialchars($record['remarks']); ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">—</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button onclick="editAttendance(<?php echo $record['attendance_id']; ?>, '<?php echo $record['status']; ?>', '<?php echo htmlspecialchars($record['remarks'] ?? '', ENT_QUOTES); ?>')" 
                                                            class="btn btn-sm btn-primary">
                                                        Edit
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-error">
                        Session not found or you don't have permission to view it.
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Edit Attendance Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Attendance</h3>
                <span class="close" onclick="closeModal()">&times;</span>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="attendance_id" id="edit_attendance_id">
                <input type="hidden" name="edit_attendance" value="1">
                
                <div class="form-group">
                    <label for="edit_status">Status:</label>
                    <select name="status" id="edit_status" class="form-control" required>
                        <option value="present">Present</option>
                        <option value="late">Late</option>
                        <option value="absent">Absent</option>
                        <option value="excused">Excused</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label for="edit_remarks">Remarks (Optional):</label>
                    <textarea name="remarks" id="edit_remarks" rows="3" class="form-control"></textarea>
                </div>
                
                <div class="modal-actions">
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                </div>
            </form>
        </div>
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
        
        .page-header {
            margin-bottom: 30px;
        }
        
        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
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
        
        .filter-section {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .filter-form {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 20px;
            align-items: end;
        }
        
        .filter-group {
            display: flex;
            flex-direction: column;
        }
        
        .filter-group label {
            font-weight: 600;
            color: #2c3e50;
            margin-bottom: 8px;
            font-size: 14px;
        }
        
        .filter-select, .filter-input {
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
        }
        
        .info-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        
        .session-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
        }
        
        .info-item {
            display: flex;
            flex-direction: column;
        }
        
        .info-label {
            color: #7f8c8d;
            font-size: 13px;
            margin-bottom: 5px;
        }
        
        .info-value {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .section-card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .section-card h2 {
            font-size: 20px;
            color: #2c3e50;
            margin-bottom: 20px;
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
        
        .text-muted {
            color: #999;
            font-size: 12px;
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
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #7f8c8d;
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
            transition: all 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background: white;
            margin: 10% auto;
            padding: 0;
            width: 90%;
            max-width: 500px;
            border-radius: 12px;
            box-shadow: 0 5px 30px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 25px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .modal-header h3 {
            color: #2c3e50;
            font-size: 20px;
        }
        
        .close {
            font-size: 28px;
            font-weight: bold;
            color: #999;
            cursor: pointer;
        }
        
        .close:hover {
            color: #333;
        }
        
        .modal-content form {
            padding: 25px;
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
        
        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 2px solid #e0e0e0;
            border-radius: 8px;
            font-size: 14px;
        }
        
        .modal-actions {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
        }
    </style>
    
    <script>
        function editAttendance(attendanceId, status, remarks) {
            document.getElementById('edit_attendance_id').value = attendanceId;
            document.getElementById('edit_status').value = status;
            document.getElementById('edit_remarks').value = remarks;
            document.getElementById('editModal').style.display = 'block';
        }
        
        function closeModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('editModal');
            if (event.target == modal) {
                closeModal();
            }
        }
    </script>
</body>
</html>