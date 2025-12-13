<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'ClassManager.php';
require_once 'Database.php';

$auth = new Auth();
$auth->requireRole(['secretary']);

$classManager = new ClassManager();
$db = new Database();

$message = '';
$messageType = '';

// Handle add class
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_class'])) {
    $classData = [
        'course_id' => $_POST['course_id'] ?? null,
        'professor_id' => $_POST['professor_id'] ?? null,
        'section' => $_POST['section'] ?? '',
        'school_year' => $_POST['school_year'] ?? '',
        'semester' => $_POST['semester'] ?? '',
        'room' => $_POST['room'] ?? ''
    ];
    
    $result = $classManager->createClass($classData);
    
    if ($result['success']) {
        // Add schedules
        $schedules = $_POST['schedules'] ?? [];
        foreach ($schedules as $schedule) {
            if (!empty($schedule['day']) && !empty($schedule['start']) && !empty($schedule['end'])) {
                $classManager->addSchedule(
                    $result['class_id'],
                    $schedule['day'],
                    $schedule['start'],
                    $schedule['end'],
                    $classData['professor_id']
                );
            }
        }
        $message = 'Class created successfully';
        $messageType = 'success';
    } else {
        $message = $result['message'];
        $messageType = 'error';
    }
}

// Handle enroll student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['enroll_student'])) {
    $result = $classManager->enrollStudent($_POST['class_id'], $_POST['student_id']);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle drop student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['drop_student'])) {
    $result = $classManager->dropStudent($_POST['enrollment_id']);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

$classes = $classManager->getClasses(['status' => 'active']);
$courses = $classManager->getCourses();
$professors = $classManager->getProfessors();

// Get selected class details
$selectedClassId = $_GET['class_id'] ?? null;
$selectedClass = null;
$schedules = [];
$enrolledStudents = [];

if ($selectedClassId) {
    $selectedClass = $classManager->getClassById($selectedClassId);
    $schedules = $classManager->getClassSchedules($selectedClassId);
    $enrolledStudents = $classManager->getEnrolledStudents($selectedClassId);
}

$pageTitle = 'Class Management';
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
        .page-header { margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header h1 { font-size: 32px; color: #2c3e50; }
        
        .alert { padding: 15px 20px; border-radius: 10px; margin-bottom: 20px; }
        .alert-success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .alert-error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        
        .section-card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .info-card { background: white; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .info-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; }
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        
        .table-responsive { overflow-x: auto; }
        .data-table { width: 100%; border-collapse: collapse; }
        .data-table th { background: #f8f9fa; padding: 12px; text-align: left; font-weight: 600; color: #2c3e50; border-bottom: 2px solid #e0e0e0; font-size: 13px; }
        .data-table td { padding: 12px; border-bottom: 1px solid #f0f0f0; font-size: 14px; }
        .data-table tbody tr:hover { background: #f8f9fa; }
        
        .btn { padding: 10px 20px; border: none; border-radius: 8px; cursor: pointer; text-decoration: none; display: inline-block; font-size: 14px; font-weight: 600; transition: all 0.2s; }
        .btn:hover { transform: translateY(-2px); }
        .btn-sm { padding: 6px 12px; font-size: 12px; }
        .btn-primary { background: #667eea; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background: white; margin: 5% auto; padding: 0; width: 90%; max-width: 700px; border-radius: 12px; }
        .modal-header { display: flex; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid #e0e0e0; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .close { font-size: 28px; font-weight: bold; color: #999; cursor: pointer; }
        
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .required { color: #dc3545; }
        
        .schedule-item { background: #f8f9fa; padding: 15px; border-radius: 8px; margin-bottom: 10px; }
        .schedule-inputs { display: grid; grid-template-columns: 2fr 1fr 1fr auto; gap: 10px; align-items: end; }
        
        #searchResults { position: absolute; background: white; border: 2px solid #e0e0e0; border-radius: 8px; max-height: 200px; overflow-y: auto; width: calc(100% - 4px); z-index: 100; display: none; }
        .search-item { padding: 10px; cursor: pointer; border-bottom: 1px solid #f0f0f0; }
        .search-item:hover { background: #f8f9fa; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .form-row, .info-grid { grid-template-columns: 1fr; }
            .schedule-inputs { grid-template-columns: 1fr; }
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
            
            <?php if (!$selectedClassId): ?>
                <div class="page-header">
                    <div>
                        <h1>📚 Class Management</h1>
                        <p>Manage classes, schedules, and enrollments</p>
                    </div>
                    <button onclick="openAddModal()" class="btn btn-primary">➕ Add New Class</button>
                </div>
                
                <div class="section-card">
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Course Code</th>
                                    <th>Course Name</th>
                                    <th>Section</th>
                                    <th>Professor</th>
                                    <th>Room</th>
                                    <th>Enrolled</th>
                                    <th>Term</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($classes as $class): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($class['course_code']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($class['course_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['section']); ?></td>
                                        <td><?php echo htmlspecialchars($class['professor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($class['room']); ?></td>
                                        <td><?php echo $class['enrolled_count']; ?></td>
                                        <td><?php echo htmlspecialchars($class['school_year'] . ' Sem ' . $class['semester']); ?></td>
                                        <td>
                                            <a href="secretary_classes.php?class_id=<?php echo $class['class_id']; ?>" class="btn btn-sm btn-primary">View</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                
            <?php else: ?>
                <div class="page-header">
                    <div>
                        <h1><?php echo htmlspecialchars($selectedClass['course_code']); ?> - <?php echo htmlspecialchars($selectedClass['section']); ?></h1>
                        <p><?php echo htmlspecialchars($selectedClass['course_name']); ?></p>
                    </div>
                    <a href="secretary_classes.php" class="btn btn-secondary">← Back</a>
                </div>
                
                <div class="info-card">
                    <h3 style="margin-bottom: 15px;">Class Information</h3>
                    <div class="info-grid">
                        <div class="info-row">
                            <strong>Professor:</strong>
                            <span><?php echo htmlspecialchars($selectedClass['professor_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Room:</strong>
                            <span><?php echo htmlspecialchars($selectedClass['room']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>School Year:</strong>
                            <span><?php echo htmlspecialchars($selectedClass['school_year']); ?></span>
                        </div>
                        <div class="info-row">
                            <strong>Semester:</strong>
                            <span><?php echo htmlspecialchars($selectedClass['semester']); ?></span>
                        </div>
                    </div>
                    
                    <h4 style="margin: 20px 0 10px;">Schedule:</h4>
                    <?php foreach ($schedules as $schedule): ?>
                        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 5px;">
                            <strong><?php echo htmlspecialchars($schedule['day_of_week']); ?>:</strong>
                            <?php echo date('g:i A', strtotime($schedule['start_time'])); ?> - 
                            <?php echo date('g:i A', strtotime($schedule['end_time'])); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                
                <div class="section-card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                        <h2>Enrolled Students (<?php echo count($enrolledStudents); ?>)</h2>
                        <button onclick="openEnrollModal()" class="btn btn-success">➕ Enroll Student</button>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Student #</th>
                                    <th>Name</th>
                                    <th>Program</th>
                                    <th>Year</th>
                                    <th>Enrolled Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($enrolledStudents as $student): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($student['student_number']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['program']); ?></td>
                                        <td><?php echo $student['year_level']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($student['enrollment_date'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="enrollment_id" value="<?php echo $student['enrollment_id']; ?>">
                                                <button type="submit" name="drop_student" class="btn btn-sm btn-danger" onclick="return confirm('Drop this student?')">Drop</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <!-- Add Class Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Class</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <div class="form-row">
                        <div class="form-group">
                            <label>Course <span class="required">*</span></label>
                            <select name="course_id" class="form-control" required>
                                <option value="">Select course...</option>
                                <?php foreach ($courses as $course): ?>
                                    <option value="<?php echo $course['course_id']; ?>">
                                        <?php echo htmlspecialchars($course['course_code'] . ' - ' . $course['course_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Professor <span class="required">*</span></label>
                            <select name="professor_id" class="form-control" required>
                                <option value="">Select professor...</option>
                                <?php foreach ($professors as $prof): ?>
                                    <option value="<?php echo $prof['professor_id']; ?>">
                                        <?php echo htmlspecialchars($prof['full_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Section <span class="required">*</span></label>
                            <input type="text" name="section" class="form-control" placeholder="e.g., BSIT-3A" required>
                        </div>
                        <div class="form-group">
                            <label>Room <span class="required">*</span></label>
                            <input type="text" name="room" class="form-control" placeholder="e.g., LAB-301" required>
                        </div>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>School Year <span class="required">*</span></label>
                            <input type="text" name="school_year" class="form-control" placeholder="e.g., 2024-2025" required>
                        </div>
                        <div class="form-group">
                            <label>Semester <span class="required">*</span></label>
                            <select name="semester" class="form-control" required>
                                <option value="1">1st Semester</option>
                                <option value="2">2nd Semester</option>
                                <option value="Summer">Summer</option>
                            </select>
                        </div>
                    </div>
                    
                    <h4 style="margin: 20px 0 10px; color: #667eea;">Schedules</h4>
                    <div id="schedules">
                        <div class="schedule-item">
                            <div class="schedule-inputs">
                                <select name="schedules[0][day]" class="form-control" required>
                                    <option value="">Day...</option>
                                    <option value="Monday">Monday</option>
                                    <option value="Tuesday">Tuesday</option>
                                    <option value="Wednesday">Wednesday</option>
                                    <option value="Thursday">Thursday</option>
                                    <option value="Friday">Friday</option>
                                    <option value="Saturday">Saturday</option>
                                </select>
                                <input type="time" name="schedules[0][start]" class="form-control" required>
                                <input type="time" name="schedules[0][end]" class="form-control" required>
                                <button type="button" onclick="removeSchedule(this)" class="btn btn-sm btn-danger">×</button>
                            </div>
                        </div>
                    </div>
                    <button type="button" onclick="addSchedule()" class="btn btn-sm btn-secondary" style="margin-top: 10px;">+ Add Another Day</button>
                    
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="add_class" class="btn btn-primary">Create Class</button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Enroll Student Modal -->
    <div id="enrollModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Enroll Student</h3>
                <span class="close" onclick="closeEnrollModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="class_id" value="<?php echo $selectedClassId; ?>">
                    <div class="form-group" style="position: relative;">
                        <label>Search Student <span class="required">*</span></label>
                        <input type="text" id="studentSearch" class="form-control" placeholder="Type student name or number..." autocomplete="off">
                        <input type="hidden" name="student_id" id="selectedStudentId" required>
                        <div id="searchResults"></div>
                    </div>
                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button type="submit" name="enroll_student" class="btn btn-primary">Enroll Student</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEnrollModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        let scheduleCount = 1;
        
        function openAddModal() { document.getElementById('addModal').style.display = 'block'; }
        function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }
        function openEnrollModal() { document.getElementById('enrollModal').style.display = 'block'; }
        function closeEnrollModal() { document.getElementById('enrollModal').style.display = 'none'; }
        
        function addSchedule() {
            const container = document.getElementById('schedules');
            const div = document.createElement('div');
            div.className = 'schedule-item';
            div.innerHTML = `
                <div class="schedule-inputs">
                    <select name="schedules[${scheduleCount}][day]" class="form-control" required>
                        <option value="">Day...</option>
                        <option value="Monday">Monday</option>
                        <option value="Tuesday">Tuesday</option>
                        <option value="Wednesday">Wednesday</option>
                        <option value="Thursday">Thursday</option>
                        <option value="Friday">Friday</option>
                        <option value="Saturday">Saturday</option>
                    </select>
                    <input type="time" name="schedules[${scheduleCount}][start]" class="form-control" required>
                    <input type="time" name="schedules[${scheduleCount}][end]" class="form-control" required>
                    <button type="button" onclick="removeSchedule(this)" class="btn btn-sm btn-danger">×</button>
                </div>
            `;
            container.appendChild(div);
            scheduleCount++;
        }
        
        function removeSchedule(btn) {
            if (document.querySelectorAll('.schedule-item').length > 1) {
                btn.closest('.schedule-item').remove();
            } else {
                alert('At least one schedule is required');
            }
        }
        
        // Student search
        document.getElementById('studentSearch')?.addEventListener('input', function(e) {
            const query = e.target.value;
            if (query.length < 2) {
                document.getElementById('searchResults').style.display = 'none';
                return;
            }
            
            fetch('api.php?action=search_students&query=' + encodeURIComponent(query))
                .then(r => r.json())
                .then(data => {
                    const results = document.getElementById('searchResults');
                    if (data.success && data.students.length > 0) {
                        results.innerHTML = data.students.map(s => 
                            `<div class="search-item" onclick="selectStudent(${s.student_id}, '${s.full_name}', '${s.student_number}')">
                                <strong>${s.student_number}</strong> - ${s.full_name}<br>
                                <small>${s.program} ${s.year_level}</small>
                            </div>`
                        ).join('');
                        results.style.display = 'block';
                    } else {
                        results.innerHTML = '<div class="search-item">No students found</div>';
                        results.style.display = 'block';
                    }
                });
        });
        
        function selectStudent(id, name, number) {
            document.getElementById('selectedStudentId').value = id;
            document.getElementById('studentSearch').value = number + ' - ' + name;
            document.getElementById('searchResults').style.display = 'none';
        }
        
        window.onclick = function(e) {
            if (e.target.className === 'modal') e.target.style.display = 'none';
        }
    </script>
</body>
</html>