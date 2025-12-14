<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';

$auth = new Auth();
$auth->requireRole(['professor']);

$classManager = new ClassManager();
$professorId = $auth->getRoleId();

// Get selected class details if viewing
$selectedClassId = $_GET['class_id'] ?? null;
$selectedClass = null;
$schedules = [];
$enrolledStudents = [];

if ($selectedClassId) {
    $selectedClass = $classManager->getClassById($selectedClassId);
    if ($selectedClass && $selectedClass['professor_id'] == $professorId) {
        $schedules = $classManager->getClassSchedules($selectedClassId);
        $enrolledStudents = $classManager->getEnrolledStudents($selectedClassId);
    } else {
        $selectedClass = null; // Unauthorized access
    }
}

// Get all professor's classes
$classes = $classManager->getClasses(['professor_id' => $professorId, 'status' => 'active']);

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
    <?php include 'includes/professor_sidebar.php'; ?>
    
    <div class="dashboard-container">
        <main class="main-content">
            <?php if (!$selectedClass): ?>
                <!-- Classes List View -->
                <div class="page-header">
                    <h1>📚 My Classes</h1>
                    <p>Manage your classes and view enrolled students</p>
                </div>
                
                <?php if (empty($classes)): ?>
                    <div class="empty-state">
                        <div class="empty-icon">📚</div>
                        <h3>No Classes Assigned</h3>
                        <p>You don't have any classes assigned yet.</p>
                        <p>Please contact the secretary if you think this is an error.</p>
                    </div>
                <?php else: ?>
                    <div class="classes-grid">
                        <?php foreach ($classes as $class): ?>
                            <div class="class-card">
                                <div class="class-header">
                                    <h3><?php echo htmlspecialchars($class['course_code']); ?></h3>
                                    <span class="class-section"><?php echo htmlspecialchars($class['section']); ?></span>
                                </div>
                                
                                <div class="class-body">
                                    <h4 class="course-name"><?php echo htmlspecialchars($class['course_name']); ?></h4>
                                    
                                    <div class="class-meta">
                                        <div class="meta-item">
                                            <span class="meta-icon">🏫</span>
                                            <span>Room <?php echo htmlspecialchars($class['room']); ?></span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon">👥</span>
                                            <span><?php echo $class['enrolled_count']; ?> students</span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon">📖</span>
                                            <span><?php echo htmlspecialchars($class['units']); ?> units</span>
                                        </div>
                                        <div class="meta-item">
                                            <span class="meta-icon">📅</span>
                                            <span><?php echo htmlspecialchars($class['school_year']); ?> - Sem <?php echo $class['semester']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="class-footer">
                                    <a href="professor_classes.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-primary btn-sm">
                                        View Details →
                                    </a>
                                    <a href="professor_attendance.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-secondary btn-sm">
                                        Attendance
                                    </a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
                
            <?php else: ?>
                <!-- Class Details View -->
                <div class="page-header">
                    <div class="header-flex">
                        <div>
                            <h1><?php echo htmlspecialchars($selectedClass['course_code']); ?> - <?php echo htmlspecialchars($selectedClass['course_name']); ?></h1>
                            <p>Section: <?php echo htmlspecialchars($selectedClass['section']); ?> | Room: <?php echo htmlspecialchars($selectedClass['room']); ?></p>
                        </div>
                        <a href="professor_classes.php" class="btn btn-secondary">← Back to Classes</a>
                    </div>
                </div>
                
                <!-- Class Info Card -->
                <div class="info-cards">
                    <div class="info-card">
                        <h3>Class Information</h3>
                        <div class="info-row">
                            <span class="info-label">Course Code:</span>
                            <span><strong><?php echo htmlspecialchars($selectedClass['course_code']); ?></strong></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Course Name:</span>
                            <span><?php echo htmlspecialchars($selectedClass['course_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Units:</span>
                            <span><?php echo htmlspecialchars($selectedClass['units']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Section:</span>
                            <span><?php echo htmlspecialchars($selectedClass['section']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Room:</span>
                            <span><?php echo htmlspecialchars($selectedClass['room']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">School Year:</span>
                            <span><?php echo htmlspecialchars($selectedClass['school_year']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="info-label">Semester:</span>
                            <span><?php echo htmlspecialchars($selectedClass['semester']); ?></span>
                        </div>
                    </div>
                    
                    <div class="info-card">
                        <h3>Class Schedule</h3>
                        <?php foreach ($schedules as $schedule): ?>
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
                
                <!-- Enrolled Students -->
                <div class="section-card">
                    <h2>Enrolled Students (<?php echo count($enrolledStudents); ?>)</h2>
                    
                    <?php if (empty($enrolledStudents)): ?>
                        <div class="empty-state">
                            <p>No students enrolled yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Student Number</th>
                                        <th>Name</th>
                                        <th>Program</th>
                                        <th>Year Level</th>
                                        <th>Section</th>
                                        <th>Email</th>
                                        <th>Enrolled Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($enrolledStudents as $student): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($student['student_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                            <td><?php echo htmlspecialchars($student['program']); ?></td>
                                            <td><?php echo htmlspecialchars($student['year_level']); ?></td>
                                            <td><?php echo htmlspecialchars($student['student_section'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($student['email']); ?></td>
                                            <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
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
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
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
        
        .class-header h3 {
            color: white;
            font-size: 20px;
        }
        
        .class-section {
            background: rgba(255,255,255,0.3);
            color: white;
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .class-body {
            padding: 20px;
        }
        
        .course-name {
            color: #2c3e50;
            font-size: 16px;
            margin-bottom: 15px;
        }
        
        .class-meta {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }
        
        .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
            color: #555;
            font-size: 14px;
        }
        
        .meta-icon {
            font-size: 18px;
        }
        
        .class-footer {
            padding: 15px 20px;
            background: #f8f9fa;
            border-top: 1px solid #e0e0e0;
            display: flex;
            gap: 10px;
        }
        
        .info-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        
        .info-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .info-card h3 {
            color: #2c3e50;
            margin-bottom: 15px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            color: #7f8c8d;
            font-size: 14px;
        }
        
        .schedule-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 12px;
            background: #f8f9fa;
            border-radius: 6px;
            margin-bottom: 8px;
        }
        
        .schedule-day {
            font-weight: 600;
            color: #667eea;
        }
        
        .schedule-time {
            color: #555;
            font-size: 14px;
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
            padding: 8px 16px;
            font-size: 13px;
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
            
            .header-flex {
                flex-direction: column;
                align-items: flex-start;
                gap: 15px;
            }
            
            .classes-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</body>
</html>