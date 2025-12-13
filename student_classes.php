<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';
require_once 'Attendance.php';

$auth = new Auth();
$auth->requireRole(['student']);

$classManager = new ClassManager();
$attendance = new Attendance();

$studentId = $auth->getRoleId();
$classes = $classManager->getStudentClasses($studentId);

// Get attendance summary for each class
foreach ($classes as &$class) {
    $summary = $attendance->getStudentClassSummary($studentId, $class['class_id']);
    $class['attendance_summary'] = $summary;
    
    // Get schedules
    $class['schedules'] = $classManager->getClassSchedules($class['class_id']);
}

$pageTitle = 'My Classes';
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
                <h1>📚 My Classes</h1>
                <p>View your enrolled classes and attendance summary</p>
            </div>
            
            <?php if (empty($classes)): ?>
                <div class="empty-state">
                    <div class="empty-icon">📚</div>
                    <h3>No Classes Enrolled</h3>
                    <p>You are not currently enrolled in any classes.</p>
                    <p>Please contact the secretary if you think this is an error.</p>
                </div>
            <?php else: ?>
                <div class="classes-grid">
                    <?php foreach ($classes as $class): ?>
                        <?php 
                        $summary = $class['attendance_summary'] ?? null;
                        $percentage = $summary ? $summary['attendance_percentage'] : 0;
                        $percentageClass = $percentage >= 75 ? 'good' : ($percentage >= 60 ? 'warning' : 'danger');
                        ?>
                        <div class="class-card">
                            <div class="class-header">
                                <div class="class-title">
                                    <h3><?php echo htmlspecialchars($class['course_code']); ?></h3>
                                    <span class="class-section"><?php echo htmlspecialchars($class['section']); ?></span>
                                </div>
                                <div class="attendance-badge attendance-<?php echo $percentageClass; ?>">
                                    <?php echo number_format($percentage, 1); ?>%
                                </div>
                            </div>
                            
                            <div class="class-body">
                                <h4 class="course-name"><?php echo htmlspecialchars($class['course_name']); ?></h4>
                                
                                <div class="class-info">
                                    <div class="info-item">
                                        <span class="info-icon">👨‍🏫</span>
                                        <span><?php echo htmlspecialchars($class['professor_name']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">🏫</span>
                                        <span>Room <?php echo htmlspecialchars($class['room']); ?></span>
                                    </div>
                                    <div class="info-item">
                                        <span class="info-icon">📖</span>
                                        <span><?php echo htmlspecialchars($class['units']); ?> Units</span>
                                    </div>
                                </div>
                                
                                <div class="schedule-section">
                                    <h5>📅 Schedule:</h5>
                                    <div class="schedule-list">
                                        <?php foreach ($class['schedules'] as $schedule): ?>
                                            <div class="schedule-item">
                                                <span class="schedule-day"><?php echo htmlspecialchars($schedule['day_of_week']); ?></span>
                                                <span class="schedule-time">
                                                    <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                                                    <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                
                                <?php if ($summary): ?>
                                    <div class="attendance-summary">
                                        <h5>Attendance Summary:</h5>
                                        <div class="summary-stats">
                                            <div class="stat-item">
                                                <span class="stat-label">Total Sessions</span>
                                                <span class="stat-value"><?php echo $summary['total_sessions']; ?></span>
                                            </div>
                                            <div class="stat-item stat-present">
                                                <span class="stat-label">Present</span>
                                                <span class="stat-value"><?php echo $summary['present_count']; ?></span>
                                            </div>
                                            <div class="stat-item stat-late">
                                                <span class="stat-label">Late</span>
                                                <span class="stat-value"><?php echo $summary['late_count']; ?></span>
                                            </div>
                                            <div class="stat-item stat-absent">
                                                <span class="stat-label">Absent</span>
                                                <span class="stat-value"><?php echo $summary['absent_count']; ?></span>
                                            </div>
                                            <div class="stat-item stat-excused">
                                                <span class="stat-label">Excused</span>
                                                <span class="stat-value"><?php echo $summary['excused_count']; ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: ?>
                                    <div class="no-attendance">
                                        <p>No attendance records yet</p>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="class-footer">
                                <a href="student_attendance.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-primary">
                                    View Attendance Records →
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
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
        
        .empty-state {
            background: white;
            padding: 60px 40px;
            border-radius: 12px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .empty-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        
        .empty-state h3 {
            color: #2c3e50;
            margin-bottom: 10px;
            font-size: 24px;
        }
        
        .empty-state p {
            color: #7f8c8d;
            margin: 5px 0;
        }
        
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 25px;
        }
        
        .class-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .class-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .class-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .class-title {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .class-title h3 {
            color: white;
            font-size: 20px;
            margin: 0;
        }
        
        .class-section {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .attendance-badge {
            font-size: 24px;
            font-weight: bold;
            color: white;
            padding: 8px 16px;
            border-radius: 8px;
            background: rgba(255,255,255,0.2);
        }
        
        .attendance-good {
            background: rgba(40, 167, 69, 0.3) !important;
        }
        
        .attendance-warning {
            background: rgba(255, 193, 7, 0.3) !important;
        }
        
        .attendance-danger {
            background: rgba(220, 53, 69, 0.3) !important;
        }
        
        .class-body {
            padding: 20px;
        }
        
        .course-name {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .class-info {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 14px;
        }
        
        .info-icon {
            font-size: 18px;
        }
        
        .schedule-section {
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .schedule-section h5 {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 10px;
        }
        
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        
        .schedule-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 6px;
        }
        
        .schedule-day {
            font-weight: 600;
            color: #667eea;
            min-width: 90px;
            font-size: 13px;
        }
        
        .schedule-time {
            color: #555;
            font-size: 13px;
        }
        
        .attendance-summary h5 {
            color: #2c3e50;
            font-size: 14px;
            margin-bottom: 12px;
        }
        
        .summary-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        
        .stat-item {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 8px;
            text-align: center;
            border-left: 3px solid #667eea;
        }
        
        .stat-item.stat-present {
            border-left-color: #28a745;
        }
        
        .stat-item.stat-late {
            border-left-color: #ffc107;
        }
        
        .stat-item.stat-absent {
            border-left-color: #dc3545;
        }
        
        .stat-item.stat-excused {
            border-left-color: #17a2b8;
        }
        
        .stat-label {
            display: block;
            font-size: 11px;
            color: #666;
            margin-bottom: 4px;
        }
        
        .stat-value {
            display: block;
            font-size: 18px;
            font-weight: bold;
            color: #2c3e50;
        }
        
        .no-attendance {
            text-align: center;
            padding: 20px;
            color: #999;
            font-style: italic;
        }
        
        .class-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
        }
        
        .btn {
            display: inline-block;
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            text-align: center;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background: #5568d3;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
        }
        
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
                padding: 20px;
            }
            
            .classes-grid {
                grid-template-columns: 1fr;
            }
            
            .summary-stats {
                grid-template-columns: repeat(2, 1fr);
            }
        }
    </style>
</body>
</html>