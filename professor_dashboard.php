<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';
require_once 'Attendance.php';

$auth = new Auth();
$auth->requireRole(['professor']);

$classManager = new ClassManager();
$attendance = new Attendance();

$professorId = $auth->getRoleId();

// Get professor's classes
$classes = $classManager->getClasses(['professor_id' => $professorId, 'status' => 'active']);

// Get today's schedule
$today = date('l'); // Day name (Monday, Tuesday, etc.)
$todayClasses = [];

foreach ($classes as $class) {
    $schedules = $classManager->getClassSchedules($class['class_id']);
    foreach ($schedules as $schedule) {
        if ($schedule['day_of_week'] === $today) {
            $todayClasses[] = [
                'class' => $class,
                'schedule' => $schedule
            ];
        }
    }
}

// Sort today's classes by time
usort($todayClasses, function($a, $b) {
    return strcmp($a['schedule']['start_time'], $b['schedule']['start_time']);
});

// Set page title
$pageTitle = 'Professor Dashboard';
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
    <?php include 'includes/professor_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <div class="page-header">
                <h1>Professor Dashboard</h1>
                <p>Welcome back, Prof. <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</p>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon">📚</div>
                    <div class="stat-info">
                        <h3><?php echo count($classes); ?></h3>
                        <p>Active Classes</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">📅</div>
                    <div class="stat-info">
                        <h3><?php echo count($todayClasses); ?></h3>
                        <p>Classes Today</p>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon">🎴</div>
                    <div class="stat-info">
                        <h3>RFID</h3>
                        <p>Attendance System</p>
                    </div>
                </div>
            </div>
            
            <!-- Today's Schedule -->
            <div class="section-card">
                <h2>📅 Today's Schedule - <?php echo date('F d, Y (l)'); ?></h2>
                
                <?php if (empty($todayClasses)): ?>
                    <p class="empty-state">No classes scheduled for today. Enjoy your day! 🎉</p>
                <?php else: ?>
                    <div class="schedule-list">
                        <?php foreach ($todayClasses as $item): ?>
                            <?php
                            $class = $item['class'];
                            $schedule = $item['schedule'];
                            
                            // Check if there's an open session for this class today
                            $sessionCheck = $attendance->db->single(
                                "SELECT session_id, status FROM attendance_sessions 
                                 WHERE class_id = ? AND session_date = CURDATE() 
                                 ORDER BY session_id DESC LIMIT 1",
                                [$class['class_id']]
                            );
                            ?>
                            <div class="schedule-item">
                                <div class="schedule-time">
                                    <div class="time-badge">
                                        <?php echo date('g:i A', strtotime($schedule['start_time'])); ?>
                                    </div>
                                    <div class="time-duration">
                                        <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                                    </div>
                                </div>
                                
                                <div class="schedule-details">
                                    <h3><?php echo htmlspecialchars($class['course_code']); ?> - <?php echo htmlspecialchars($class['course_name']); ?></h3>
                                    <p>Section: <?php echo htmlspecialchars($class['section']); ?> | Room: <?php echo htmlspecialchars($class['room']); ?> | <?php echo $class['enrolled_count']; ?> students</p>
                                </div>
                                
                                <div class="schedule-actions">
                                    <?php if ($sessionCheck && $sessionCheck['status'] === 'open'): ?>
                                        <a href="professor_rfid_reader.php?session_id=<?php echo $sessionCheck['session_id']; ?>" 
                                           class="btn btn-success" target="_blank">
                                            🎴 Open RFID Reader
                                        </a>
                                        <span class="status-badge status-open">Session Open</span>
                                    <?php else: ?>
                                        <button class="btn btn-primary" onclick="alert('Auto-session management via cron job. Session will open 5 minutes before class time.')">
                                            ⏰ Auto Opens Soon
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- All Classes Overview -->
            <div class="section-card">
                <h2>📚 All My Classes</h2>
                
                <?php if (empty($classes)): ?>
                    <p class="empty-state">No classes assigned yet.</p>
                <?php else: ?>
                    <div class="classes-grid">
                        <?php foreach ($classes as $class): ?>
                            <div class="class-card">
                                <div class="class-header">
                                    <h3><?php echo htmlspecialchars($class['course_code']); ?></h3>
                                    <span class="badge badge-primary"><?php echo htmlspecialchars($class['section']); ?></span>
                                </div>
                                <p class="class-name"><?php echo htmlspecialchars($class['course_name']); ?></p>
                                <div class="class-meta">
                                    <span>🏫 <?php echo htmlspecialchars($class['room']); ?></span>
                                    <span>👥 <?php echo $class['enrolled_count']; ?> students</span>
                                </div>
                                <div class="class-actions">
                                    <a href="professor_classes.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm">View Details</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Quick Links -->
            <div class="quick-links">
                <h3>Quick Actions</h3>
                <div class="links-grid">
                    <a href="professor_classes.php" class="quick-link-card">
                        <span class="link-icon">📚</span>
                        <span class="link-text">Manage Classes</span>
                    </a>
                    <a href="professor_attendance.php" class="quick-link-card">
                        <span class="link-icon">✓</span>
                        <span class="link-text">View Attendance</span>
                    </a>
                    <a href="professor_excuse.php" class="quick-link-card">
                        <span class="link-icon">📝</span>
                        <span class="link-text">Excuse Letters</span>
                    </a>
                    <a href="professor_reports.php" class="quick-link-card">
                        <span class="link-icon">📈</span>
                        <span class="link-text">Generate Reports</span>
                    </a>
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
        
        .schedule-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        
        .schedule-item {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 20px;
            background: #f8f9fa;
            border-radius: 10px;
            border-left: 4px solid #667eea;
        }
        
        .schedule-time {
            text-align: center;
            min-width: 100px;
        }
        
        .time-badge {
            background: #667eea;
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            margin-bottom: 5px;
        }
        
        .time-duration {
            font-size: 12px;
            color: #666;
        }
        
        .schedule-details {
            flex: 1;
        }
        
        .schedule-details h3 {
            color: #2c3e50;
            margin-bottom: 5px;
            font-size: 16px;
        }
        
        .schedule-details p {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .schedule-actions {
            display: flex;
            flex-direction: column;
            gap: 10px;
            align-items: flex-end;
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
            text-align: center;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn-primary {
            background: #667eea;
            color: white;
        }
        
        .btn-success {
            background: #28a745;
            color: white;
        }
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-open {
            background: #d4edda;
            color: #155724;
        }
        
        .classes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 20px;
        }
        
        .class-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            border-top: 4px solid #667eea;
        }
        
        .class-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .class-header h3 {
            color: #2c3e50;
            font-size: 18px;
        }
        
        .class-name {
            color: #666;
            font-size: 14px;
            margin-bottom: 15px;
        }
        
        .class-meta {
            display: flex;
            gap: 15px;
            font-size: 13px;
            color: #7f8c8d;
            margin-bottom: 15px;
        }
        
        .class-actions {
            display: flex;
            gap: 10px;
        }
        
        .badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-primary {
            background: #667eea;
            color: white;
        }
        
        .quick-links {
            margin-top: 30px;
        }
        
        .quick-links h3 {
            margin-bottom: 20px;
            color: #2c3e50;
        }
        
        .links-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .quick-link-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 15px;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .quick-link-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        
        .link-icon {
            font-size: 32px;
        }
        
        .link-text {
            color: #2c3e50;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            color: #7f8c8d;
            padding: 40px 20px;
        }
    </style>
</body>
</html>