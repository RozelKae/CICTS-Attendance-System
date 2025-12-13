<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';
require_once 'StudentManager.php';
require_once 'Database.php';

$auth = new Auth();
$auth->requireRole(['student']);

$classManager = new ClassManager();
$studentManager = new StudentManager();
$db = new Database();

$studentId = $auth->getRoleId();
$classes = $classManager->getStudentClasses($studentId);

// Get filters
$filterClassId = $_GET['class_id'] ?? 'all';
$filterStartDate = $_GET['start_date'] ?? '';
$filterEndDate = $_GET['end_date'] ?? '';

// Build query
$sql = "SELECT ar.*, 
        asess.session_date, asess.start_time, asess.end_time,
        c.section, co.course_code, co.course_name,
        DATE_FORMAT(asess.session_date, '%W, %M %d, %Y') as formatted_date,
        DATE_FORMAT(asess.start_time, '%h:%i %p') as formatted_start,
        DATE_FORMAT(asess.end_time, '%h:%i %p') as formatted_end,
        TIME_FORMAT(ar.time_in, '%h:%i %p') as formatted_time_in
        FROM attendance_records ar
        JOIN attendance_sessions asess ON ar.session_id = asess.session_id
        JOIN classes c ON asess.class_id = c.class_id
        JOIN courses co ON c.course_id = co.course_id
        WHERE ar.student_id = ? AND asess.status = 'closed'";

$params = [$studentId];

if ($filterClassId !== 'all') {
    $sql .= " AND c.class_id = ?";
    $params[] = $filterClassId;
}

if ($filterStartDate) {
    $sql .= " AND asess.session_date >= ?";
    $params[] = $filterStartDate;
}

if ($filterEndDate) {
    $sql .= " AND asess.session_date <= ?";
    $params[] = $filterEndDate;
}

$sql .= " ORDER BY asess.session_date DESC, asess.start_time DESC";

$attendanceRecords = $db->all($sql, $params);

// Calculate summary
$summary = [
    'total' => count($attendanceRecords),
    'present' => 0,
    'late' => 0,
    'absent' => 0,
    'excused' => 0
];

foreach ($attendanceRecords as $record) {
    $summary[$record['status']]++;
}

$pageTitle = 'My Attendance';
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
                <h1>✓ My Attendance</h1>
                <p>View your complete attendance history</p>
            </div>
            
            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <div class="summary-icon">📊</div>
                    <div class="summary-info">
                        <h3><?php echo $summary['total']; ?></h3>
                        <p>Total Sessions</p>
                    </div>
                </div>
                <div class="summary-card card-present">
                    <div class="summary-icon">✅</div>
                    <div class="summary-info">
                        <h3><?php echo $summary['present']; ?></h3>
                        <p>Present</p>
                    </div>
                </div>
                <div class="summary-card card-late">
                    <div class="summary-icon">⚠️</div>
                    <div class="summary-info">
                        <h3><?php echo $summary['late']; ?></h3>
                        <p>Late</p>
                    </div>
                </div>
                <div class="summary-card card-absent">
                    <div class="summary-icon">❌</div>
                    <div class="summary-info">
                        <h3><?php echo $summary['absent']; ?></h3>
                        <p>Absent</p>
                    </div>
                </div>
                <div class="summary-card card-excused">
                    <div class="summary-icon">📝</div>
                    <div class="summary-info">
                        <h3><?php echo $summary['excused']; ?></h3>
                        <p>Excused</p>
                    </div>
                </div>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-form">
                    <div class="filter-group">
                        <label for="class_id">Filter by Class:</label>
                        <select name="class_id" id="class_id" class="filter-select">
                            <option value="all" <?php echo $filterClassId === 'all' ? 'selected' : ''; ?>>All Classes</option>
                            <?php foreach ($classes as $class): ?>
                                <option value="<?php echo $class['class_id']; ?>" <?php echo $filterClassId == $class['class_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['section']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="filter-group">
                        <label for="start_date">Start Date:</label>
                        <input type="date" name="start_date" id="start_date" class="filter-input" value="<?php echo htmlspecialchars($filterStartDate); ?>">
                    </div>
                    
                    <div class="filter-group">
                        <label for="end_date">End Date:</label>
                        <input type="date" name="end_date" id="end_date" class="filter-input" value="<?php echo htmlspecialchars($filterEndDate); ?>">
                    </div>
                    
                    <div class="filter-actions">
                        <button type="submit" class="btn btn-primary">Apply Filters</button>
                        <a href="student_attendance.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Attendance Table -->
            <div class="section-card">
                <h2>Attendance Records</h2>
                
                <?php if (empty($attendanceRecords)): ?>
                    <div class="empty-state">
                        <p>No attendance records found with the current filters.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Class</th>
                                    <th>Session Time</th>
                                    <th>Time In</th>
                                    <th>Status</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceRecords as $record): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo date('M d, Y', strtotime($record['session_date'])); ?></strong><br>
                                            <small class="text-muted"><?php echo date('l', strtotime($record['session_date'])); ?></small>
                                        </td>
                                        <td>
                                            <strong><?php echo htmlspecialchars($record['course_code']); ?></strong><br>
                                            <small class="text-muted"><?php echo htmlspecialchars($record['section']); ?></small>
                                        </td>
                                        <td>
                                            <?php echo htmlspecialchars($record['formatted_start']); ?> - 
                                            <?php echo htmlspecialchars($record['formatted_end']); ?>
                                        </td>
                                        <td>
                                            <?php if ($record['time_in']): ?>
                                                <strong><?php echo htmlspecialchars($record['formatted_time_in']); ?></strong>
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
                                                <span class="remarks"><?php echo htmlspecialchars($record['remarks']); ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">—</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <div class="table-footer">
                        <p>Showing <?php echo count($attendanceRecords); ?> record(s)</p>
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
        
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .summary-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 15px;
            border-left: 4px solid #667eea;
        }
        
        .summary-card.card-present {
            border-left-color: #28a745;
        }
        
        .summary-card.card-late {
            border-left-color: #ffc107;
        }
        
        .summary-card.card-absent {
            border-left-color: #dc3545;
        }
        
        .summary-card.card-excused {
            border-left-color: #17a2b8;
        }
        
        .summary-icon {
            font-size: 32px;
        }
        
        .summary-info h3 {
            font-size: 28px;
            color: #2c3e50;
            margin-bottom: 3px;
        }
        
        .summary-info p {
            color: #7f8c8d;
            font-size: 13px;
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
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
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
            transition: border-color 0.3s;
        }
        
        .filter-select:focus, .filter-input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .filter-actions {
            display: flex;
            gap: 10px;
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
            font-size: 14px;
        }
        
        .data-table td {
            padding: 15px 12px;
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
        
        .remarks {
            font-style: italic;
            color: #666;
        }
        
        .table-footer {
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            color: #7f8c8d;
            font-size: 14px;
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
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .filter-form {
                grid-template-columns: 1fr;
            }
            
            .summary-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>