<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'StudentManager.php';
require_once 'ClassManager.php';
require_once 'ExcuseManager.php';

$auth = new Auth();
$auth->requireRole(['student']);

$studentManager = new StudentManager();
$classManager = new ClassManager();
$excuseManager = new ExcuseManager();

$studentId = $auth->getRoleId();
$student = $studentManager->getStudentById($studentId);
$classes = $classManager->getStudentClasses($studentId);
$attendanceSummary = $studentManager->getStudentAttendanceSummary($studentId);
$excuses = $excuseManager->getExcuses(['student_id' => $studentId]);

// Set page title
$pageTitle = 'Student Dashboard';
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
    <?php include 'includes/student_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>Dashboard</h1>
                <p>Welcome back, <?php echo htmlspecialchars($student['first_name']); ?>!</p>
            </div>
            
            <!-- Overall Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-info">
                        <h3><?php echo count($classes); ?></h3>
                        <p>Enrolled Classes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📝</div>
                    <div class="stat-info">
                        <h3><?php echo count($excuses); ?></h3>
                        <p>Excuse Letters</p>
                    </div>
                </div>
            </div>
            
            <!-- Attendance Summary by Class -->
            <div class="section-card">
                <h2>Attendance Summary</h2>
                
                <?php if (empty($attendanceSummary)): ?>
                    <p class="empty-state">No attendance records yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Course</th>
                                    <th>Section</th>
                                    <th>Total Sessions</th>
                                    <th>Present</th>
                                    <th>Late</th>
                                    <th>Absent</th>
                                    <th>Excused</th>
                                    <th>Attendance %</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($attendanceSummary as $class): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($class['course_code']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($class['course_name']); ?></small>
                                        </td>
                                        <td><?php echo htmlspecialchars($class['section']); ?></td>
                                        <td><?php echo $class['total_sessions']; ?></td>
                                        <td><span class="badge badge-success"><?php echo $class['present_count']; ?></span></td>
                                        <td><span class="badge badge-warning"><?php echo $class['late_count']; ?></span></td>
                                        <td><span class="badge badge-danger"><?php echo $class['absent_count']; ?></span></td>
                                        <td><span class="badge badge-info"><?php echo $class['excused_count']; ?></span></td>
                                        <td>
                                            <strong class="<?php echo $class['attendance_percentage'] >= 75 ? 'text-success' : 'text-danger'; ?>">
                                                <?php echo number_format($class['attendance_percentage'], 2); ?>%
                                            </strong>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Recent Excuse Letters -->
            <div class="section-card">
                <h2>Recent Excuse Letters</h2>
                
                <?php if (empty($excuses)): ?>
                    <p class="empty-state">No excuse letters submitted yet.</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Type</th>
                                    <th>Dates Affected</th>
                                    <th>Submitted</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($excuses, 0, 5) as $excuse): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($excuse['excuse_type']); ?></td>
                                        <td><?php echo $excuse['affected_dates_count']; ?> date(s)</td>
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
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($excuses) > 5): ?>
                        <div style="text-align: center; margin-top: 15px;">
                            <a href="student_excuse.php" class="btn btn-secondary">View All Excuse Letters</a>
                        </div>
                    <?php endif; ?>
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
            margin: 0;
        }
        
        .dashboard-container {
            display: flex;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 30px;
            flex: 1;
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
        
        .badge {
            display: inline-block;
            padding: 4px 10px;
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
        
        .text-success {
            color: #28a745;
        }
        
        .text-danger {
            color: #dc3545;
        }
        
        .empty-state {
            text-align: center;
            color: #7f8c8d;
            padding: 40px 20px;
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
    </style>
</body>
</html>