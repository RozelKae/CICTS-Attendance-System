<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'StudentManager.php';

$auth = new Auth();
$auth->requireRole(['secretary']);

$studentManager = new StudentManager();

$message = '';
$messageType = '';

// Handle add student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_student'])) {
    $userData = [
        'username' => $_POST['username'] ?? '',
        'password' => $_POST['password'] ?? '',
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? ''
    ];
    
    $studentData = [
        'student_number' => $_POST['student_number'] ?? '',
        'year_level' => $_POST['year_level'] ?? 1,
        'program' => $_POST['program'] ?? '',
        'section' => $_POST['section'] ?? null
    ];
    
    $result = $studentManager->createStudent($userData, $studentData);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle update student
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_student'])) {
    $studentId = $_POST['student_id'] ?? null;
    
    $userData = [
        'email' => $_POST['email'] ?? '',
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? ''
    ];
    
    $studentData = [
        'year_level' => $_POST['year_level'] ?? 1,
        'program' => $_POST['program'] ?? '',
        'section' => $_POST['section'] ?? null
    ];
    
    $result = $studentManager->updateStudent($studentId, $userData, $studentData);
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Handle deactivate/activate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $studentId = $_POST['student_id'] ?? null;
    $currentStatus = $_POST['current_status'] ?? 'active';
    
    if ($currentStatus === 'active') {
        $result = $studentManager->deactivateStudent($studentId);
    } else {
        $result = $studentManager->activateStudent($studentId);
    }
    
    $message = $result['message'];
    $messageType = $result['success'] ? 'success' : 'error';
}

// Get filters
$filterProgram = $_GET['program'] ?? '';
$filterYearLevel = $_GET['year_level'] ?? '';
$filterRfid = $_GET['rfid'] ?? '';
$filterSearch = $_GET['search'] ?? '';

$filters = [
    'program' => $filterProgram,
    'year_level' => $filterYearLevel,
    'search' => $filterSearch
];

$students = $studentManager->getStudents($filters);

// Apply RFID filter
if ($filterRfid === 'registered') {
    $students = array_filter($students, fn($s) => !empty($s['rfid_uid']) && $s['rfid_status'] === 'active');
} elseif ($filterRfid === 'unregistered') {
    $students = array_filter($students, fn($s) => empty($s['rfid_uid']));
}

$pageTitle = 'Student Management';
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
        .btn-success { background: #28a745; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-danger { background: #dc3545; color: white; }
        .btn-secondary { background: #6c757d; color: white; }
        
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); overflow-y: auto; }
        .modal-content { background: white; margin: 5% auto; padding: 0; width: 90%; max-width: 600px; border-radius: 12px; }
        .modal-header { display: flex; justify-content: space-between; padding: 20px 25px; border-bottom: 1px solid #e0e0e0; }
        .modal-header h3 { color: #2c3e50; font-size: 20px; }
        .close { font-size: 28px; font-weight: bold; color: #999; cursor: pointer; }
        .modal-body { padding: 25px; max-height: 70vh; overflow-y: auto; }
        .form-group { margin-bottom: 20px; }
        .form-group label { display: block; font-weight: 600; color: #2c3e50; margin-bottom: 8px; }
        .form-control { width: 100%; padding: 10px 12px; border: 2px solid #e0e0e0; border-radius: 8px; font-size: 14px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .modal-actions { display: flex; gap: 10px; margin-top: 20px; }
        .required { color: #dc3545; }
        
        @media (max-width: 768px) {
            .main-content { margin-left: 0; padding: 20px; }
            .filter-grid { grid-template-columns: 1fr; }
            .form-row { grid-template-columns: 1fr; }
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
                <div>
                    <h1>👥 Student Management</h1>
                    <p>Manage student accounts and information</p>
                </div>
                <button onclick="openAddModal()" class="btn btn-primary">➕ Add New Student</button>
            </div>
            
            <!-- Filters -->
            <div class="filter-section">
                <form method="GET" action="" class="filter-grid">
                    <div class="filter-group">
                        <label>Search</label>
                        <input type="text" name="search" class="filter-input" placeholder="Name, student #, email..." value="<?php echo htmlspecialchars($filterSearch); ?>">
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
                        <label>Year Level</label>
                        <select name="year_level" class="filter-select">
                            <option value="">All Years</option>
                            <option value="1" <?php echo $filterYearLevel === '1' ? 'selected' : ''; ?>>1st Year</option>
                            <option value="2" <?php echo $filterYearLevel === '2' ? 'selected' : ''; ?>>2nd Year</option>
                            <option value="3" <?php echo $filterYearLevel === '3' ? 'selected' : ''; ?>>3rd Year</option>
                            <option value="4" <?php echo $filterYearLevel === '4' ? 'selected' : ''; ?>>4th Year</option>
                        </select>
                    </div>
                    <div class="filter-group">
                        <label>RFID Status</label>
                        <select name="rfid" class="filter-select">
                            <option value="">All</option>
                            <option value="registered" <?php echo $filterRfid === 'registered' ? 'selected' : ''; ?>>Registered</option>
                            <option value="unregistered" <?php echo $filterRfid === 'unregistered' ? 'selected' : ''; ?>>Unregistered</option>
                        </select>
                    </div>
                    <div style="display: flex; gap: 10px;">
                        <button type="submit" class="btn btn-primary">Apply</button>
                        <a href="secretary_students.php" class="btn btn-secondary">Clear</a>
                    </div>
                </form>
            </div>
            
            <!-- Students Table -->
            <div class="section-card">
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Student #</th>
                                <th>Name</th>
                                <th>Program</th>
                                <th>Year</th>
                                <th>Section</th>
                                <th>RFID</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($students as $student): ?>
                                <tr>
                                    <td><strong><?php echo htmlspecialchars($student['student_number']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($student['full_name']); ?></td>
                                    <td><?php echo htmlspecialchars($student['program']); ?></td>
                                    <td><?php echo $student['year_level']; ?></td>
                                    <td><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php if (!empty($student['rfid_uid']) && $student['rfid_status'] === 'active'): ?>
                                            <span class="badge badge-success">✓ Registered</span>
                                        <?php else: ?>
                                            <span class="badge badge-warning">⚠ Not Registered</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($student['status'] === 'active'): ?>
                                            <span class="badge badge-success">Active</span>
                                        <?php else: ?>
                                            <span class="badge badge-secondary">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button onclick='editStudent(<?php echo json_encode($student); ?>)' class="btn btn-sm btn-primary">Edit</button>
                                        <form method="POST" style="display: inline;">
                                            <input type="hidden" name="student_id" value="<?php echo $student['student_id']; ?>">
                                            <input type="hidden" name="current_status" value="<?php echo $student['status']; ?>">
                                            <button type="submit" name="toggle_status" class="btn btn-sm <?php echo $student['status'] === 'active' ? 'btn-warning' : 'btn-success'; ?>" 
                                                    onclick="return confirm('<?php echo $student['status'] === 'active' ? 'Deactivate' : 'Activate'; ?> this student?')">
                                                <?php echo $student['status'] === 'active' ? 'Deactivate' : 'Activate'; ?>
                                            </button>
                                        </form>
                                        <a href="secretary_rfid.php?student_id=<?php echo $student['student_id']; ?>" class="btn btn-sm btn-secondary">RFID</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
    
    <!-- Add Student Modal -->
    <div id="addModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add New Student</h3>
                <span class="close" onclick="closeAddModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <h4 style="margin-bottom: 15px; color: #667eea;">Account Information</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Username <span class="required">*</span></label>
                            <input type="text" name="username" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Password <span class="required">*</span></label>
                            <input type="password" name="password" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" class="form-control" required>
                        </div>
                    </div>
                    
                    <h4 style="margin: 20px 0 15px; color: #667eea;">Student Information</h4>
                    <div class="form-group">
                        <label>Student Number <span class="required">*</span></label>
                        <input type="text" name="student_number" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Program <span class="required">*</span></label>
                            <select name="program" class="form-control" required>
                                <option value="">Select...</option>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level <span class="required">*</span></label>
                            <select name="year_level" class="form-control" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" class="form-control" placeholder="e.g., 1A, 2B (optional)">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="submit" name="add_student" class="btn btn-primary">Add Student</button>
                        <button type="button" class="btn btn-secondary" onclick="closeAddModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Student Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Student</h3>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form method="POST" action="">
                    <input type="hidden" name="student_id" id="edit_student_id">
                    <div class="form-group">
                        <label>Email <span class="required">*</span></label>
                        <input type="email" name="email" id="edit_email" class="form-control" required>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>First Name <span class="required">*</span></label>
                            <input type="text" name="first_name" id="edit_first_name" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label>Last Name <span class="required">*</span></label>
                            <input type="text" name="last_name" id="edit_last_name" class="form-control" required>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Program <span class="required">*</span></label>
                            <select name="program" id="edit_program" class="form-control" required>
                                <option value="BSIT">BSIT</option>
                                <option value="BSCS">BSCS</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Year Level <span class="required">*</span></label>
                            <select name="year_level" id="edit_year_level" class="form-control" required>
                                <option value="1">1st Year</option>
                                <option value="2">2nd Year</option>
                                <option value="3">3rd Year</option>
                                <option value="4">4th Year</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>Section</label>
                        <input type="text" name="section" id="edit_section" class="form-control">
                    </div>
                    
                    <div class="modal-actions">
                        <button type="submit" name="update_student" class="btn btn-primary">Update Student</button>
                        <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function openAddModal() { document.getElementById('addModal').style.display = 'block'; }
        function closeAddModal() { document.getElementById('addModal').style.display = 'none'; }
        function closeEditModal() { document.getElementById('editModal').style.display = 'none'; }
        
        function editStudent(student) {
            document.getElementById('edit_student_id').value = student.student_id;
            document.getElementById('edit_email').value = student.email;
            document.getElementById('edit_first_name').value = student.first_name;
            document.getElementById('edit_last_name').value = student.last_name;
            document.getElementById('edit_program').value = student.program;
            document.getElementById('edit_year_level').value = student.year_level;
            document.getElementById('edit_section').value = student.section || '';
            document.getElementById('editModal').style.display = 'block';
        }
        
        window.onclick = function(e) {
            if (e.target.className === 'modal') e.target.style.display = 'none';
        }
    </script>
</body>
</html>