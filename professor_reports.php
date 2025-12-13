<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ReportGenerator.php';
require_once 'ClassManager.php';
require_once 'StudentManager.php';

$auth = new Auth();
$auth->requireRole(['professor', 'secretary']);

$reportGen = new ReportGenerator();
$classManager = new ClassManager();
$studentManager = new StudentManager();

$userType = $auth->getUserType();
$professorId = $userType === 'professor' ? $auth->getRoleId() : null;

// Get classes based on role
$classes = $professorId 
    ? $classManager->getClasses(['professor_id' => $professorId, 'status' => 'active'])
    : $classManager->getClasses(['status' => 'active']);

// Generate report based on type
$reportData = null;
$reportType = $_GET['type'] ?? null;

if ($reportType === 'daily' && !empty($_GET['class_id']) && !empty($_GET['date'])) {
    $reportData = $reportGen->generateDailyReport($_GET['class_id'], $_GET['date']);
} elseif ($reportType === 'student' && !empty($_GET['student_id'])) {
    $reportData = $reportGen->generateStudentSummary(
        $_GET['student_id'],
        $_GET['start_date'] ?? null,
        $_GET['end_date'] ?? null
    );
} elseif ($reportType === 'class' && !empty($_GET['class_id'])) {
    $reportData = $reportGen->generateClassSummary(
        $_GET['class_id'],
        $_GET['start_date'] ?? null,
        $_GET['end_date'] ?? null
    );
}

$pageTitle = 'Reports';
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
        
        .tabs { display: flex; gap: 10px; margin-bottom: 20px; background: white; padding: 15px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .tab { padding: 12px 24px; border: none; background: #f8f9fa; border-radius: 8px; cursor: pointer; font-weight: 600; transition: all 0.2s; }
        .tab.active { background: #667eea; color: white; }
        
        .report-form { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .form-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; align-items: end; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 10px; border: 2px solid #e0e0e0; border-radius: 8px; }
        
        .report-results { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .summary-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px; margin-bottom: 20px; }
        .summary-box { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border-left: 3px solid #667eea; }
        .summary-box h3 { font-size: 24px; color: #2c3e50; margin-bottom: 5px; }
        .summary-box p { font-size: 13px; color: #7f8c8d; }
        
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e0e0e0; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; }
        .data-table tbody tr:hover { background: #f8f9fa; }
        
        .badge { display: inline-block; padding: 5px 12px; border-radius: 20px; font-size: 12px; font-weight: 600; }
        .badge-success { background: #d4edda; color: #155724; }
        .badge-warning { background: #fff3cd; color: #856404; }
        .badge-danger { background: #f8d7da; color: #721c24; }
        .badge-info { background: #d1ecf1; color: #0c5460; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn-primary { background: #667eea; color: white; }
        .btn-primary:hover { background: #5568d3; }
        .empty-state { text-align: center; padding: 40px; color: #7f8c8d; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .tabs { flex-direction: column; }
            .form-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <?php include $userType === 'professor' ? 'includes/professor_sidebar.php' : 'includes/secretary_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>📈 Reports</h1>
                <p>Generate attendance reports and summaries</p>
            </div>
            
            <div class="tabs">
                <button class="tab <?php echo !$reportType || $reportType === 'daily' ? 'active' : ''; ?>" onclick="showTab('daily')">📅 Daily Report</button>
                <button class="tab <?php echo $reportType === 'student' ? 'active' : ''; ?>" onclick="showTab('student')">👤 Student Summary</button>
                <button class="tab <?php echo $reportType === 'class' ? 'active' : ''; ?>" onclick="showTab('class')">📚 Class Summary</button>
            </div>
            
            <!-- Daily Report Form -->
            <div id="daily-form" class="report-form" style="display: <?php echo !$reportType || $reportType === 'daily' ? 'block' : 'none'; ?>">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Daily Attendance Report</h3>
                <form method="GET" action="">
                    <input type="hidden" name="type" value="daily">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Class *</label>
                            <select name="class_id" class="form-control" required>
                                <option value="">Choose class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo ($_GET['class_id'] ?? '') == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Date *</label>
                            <input type="date" name="date" class="form-control" value="<?php echo $_GET['date'] ?? date('Y-m-d'); ?>" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>
            
            <!-- Student Summary Form -->
            <div id="student-form" class="report-form" style="display: <?php echo $reportType === 'student' ? 'block' : 'none'; ?>">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Student Attendance Summary</h3>
                <form method="GET" action="">
                    <input type="hidden" name="type" value="student">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Student Number *</label>
                            <input type="text" name="student_id" class="form-control" placeholder="Enter student ID" value="<?php echo $_GET['student_id'] ?? ''; ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>
            
            <!-- Class Summary Form -->
            <div id="class-form" class="report-form" style="display: <?php echo $reportType === 'class' ? 'block' : 'none'; ?>">
                <h3 style="margin-bottom: 15px; color: #2c3e50;">Class Attendance Summary</h3>
                <form method="GET" action="">
                    <input type="hidden" name="type" value="class">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Select Class *</label>
                            <select name="class_id" class="form-control" required>
                                <option value="">Choose class...</option>
                                <?php foreach ($classes as $class): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo ($_GET['class_id'] ?? '') == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['course_code'] . ' - ' . $class['section']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?php echo $_GET['start_date'] ?? ''; ?>">
                        </div>
                        <div class="form-group">
                            <label>End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?php echo $_GET['end_date'] ?? ''; ?>">
                        </div>
                        <button type="submit" class="btn btn-primary">Generate Report</button>
                    </div>
                </form>
            </div>
            
            <!-- Report Results -->
            <?php if ($reportData && $reportData['success']): ?>
                <div class="report-results">
                    <h3 style="margin-bottom: 20px; color: #2c3e50;">Report Results</h3>
                    
                    <?php if ($reportType === 'student' && isset($reportData['data']['overall_stats'])): ?>
                        <?php $stats = $reportData['data']['overall_stats']; ?>
                        <div class="summary-grid">
                            <div class="summary-box">
                                <h3><?php echo $stats['total_sessions']; ?></h3>
                                <p>Total Sessions</p>
                            </div>
                            <div class="summary-box">
                                <h3><?php echo $stats['total_present']; ?></h3>
                                <p>Present</p>
                            </div>
                            <div class="summary-box">
                                <h3><?php echo $stats['total_late']; ?></h3>
                                <p>Late</p>
                            </div>
                            <div class="summary-box">
                                <h3><?php echo $stats['total_absent']; ?></h3>
                                <p>Absent</p>
                            </div>
                            <div class="summary-box">
                                <h3><?php echo $stats['overall_percentage']; ?>%</h3>
                                <p>Attendance Rate</p>
                            </div>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Class</th>
                                        <th>Sessions</th>
                                        <th>Present</th>
                                        <th>Late</th>
                                        <th>Absent</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['data']['classes'] as $class): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($class['course_code']); ?></strong></td>
                                            <td><?php echo $class['total_sessions']; ?></td>
                                            <td><?php echo $class['present_count']; ?></td>
                                            <td><?php echo $class['late_count']; ?></td>
                                            <td><?php echo $class['absent_count']; ?></td>
                                            <td><strong><?php echo number_format($class['attendance_percentage'], 2); ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($reportType === 'class' && isset($reportData['data']['students'])): ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student #</th>
                                        <th>Name</th>
                                        <th>Sessions</th>
                                        <th>Present</th>
                                        <th>Late</th>
                                        <th>Absent</th>
                                        <th>Percentage</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($reportData['data']['students'] as $student): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                            <td><?php echo $student['total_sessions']; ?></td>
                                            <td><?php echo $student['present_count']; ?></td>
                                            <td><?php echo $student['late_count']; ?></td>
                                            <td><?php echo $student['absent_count']; ?></td>
                                            <td><strong><?php echo number_format($student['attendance_percentage'], 2); ?>%</strong></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            <?php elseif ($reportData): ?>
                <div class="report-results">
                    <div class="empty-state"><?php echo htmlspecialchars($reportData['message'] ?? 'No data found'); ?></div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script>
        function showTab(type) {
            document.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.report-form').forEach(f => f.style.display = 'none');
            
            event.target.classList.add('active');
            document.getElementById(type + '-form').style.display = 'block';
        }
    </script>
</body>
</html>