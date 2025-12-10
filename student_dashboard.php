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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/dashboard.css">
</head>
<body>
    <?php include 'includes/student_header.php'; ?>
    
    <div class="dashboard-container">
        <aside class="sidebar">
            <div class="user-info">
                <div class="avatar"><?php echo strtoupper(substr($student['first_name'], 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
                <p><?php echo htmlspecialchars($student['student_number']); ?></p>
                <p><?php echo htmlspecialchars($student['program'] . ' ' . $student['year_level']); ?></p>
            </div>
            
            <nav class="nav-menu">
                <a href="student_dashboard.php" class="active">📊 Dashboard</a>
                <a href="student_classes.php">📚 My Classes</a>
                <a href="student_attendance.php">✓ My Attendance</a>
                <a href="student_register_rfid.php">🎴 Register RFID Card</a>
                <a href="student_excuse.php">📝 Submit Excuse</a>
                <a href="logout.php">🚪 Logout</a>
            </nav>
        </aside>
        
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
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px 20px;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .user-info {
            text-align: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.2);
        }
        
        .avatar {
            width: 80px;
            height: 80px;
            background: rgba(255,255,255,0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            margin: 0 auto 15px;
        }
        
        .user-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .user-info p {
            font-size: 13px;
            opacity: 0.9;
        }
        
        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            margin-bottom: 5px;
            transition: background 0.3s;
        }
        
        .nav-menu a:hover, .nav-menu a.active {
            background: rgba(255,255,255,0.2);
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