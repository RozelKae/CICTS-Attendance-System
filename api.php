<?php
/**
 * API Handler for AJAX Requests
 * Handles all frontend AJAX calls
 */

header('Content-Type: application/json');

require_once 'config.php';
require_once 'Auth.php';
require_once 'Database.php';
require_once 'Attendance.php';
require_once 'ClassManager.php';
require_once 'StudentManager.php';
require_once 'ExcuseManager.php';
require_once 'ReportGenerator.php';

$auth = new Auth();
$response = ['success' => false, 'message' => 'Invalid request'];

try {
    $action = $_POST['action'] ?? $_GET['action'] ?? null;
    
    if (!$action) {
        throw new Exception('No action specified');
    }
    
    // Public actions (no auth required)
    $publicActions = ['record_attendance', 'get_session_info'];
    
    if (!in_array($action, $publicActions) && !$auth->isLoggedIn()) {
        throw new Exception('Authentication required');
    }
    
    switch ($action) {
        // ========================================
        // ATTENDANCE ACTIONS (RFID VERSION)
        // ========================================
        
        case 'record_attendance_rfid':
            // No auth required - public endpoint for RFID scanning
            $sessionId = $_POST['session_id'] ?? null;
            $rfidUid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
            
            if (!$sessionId || !$rfidUid) {
                throw new Exception('Session ID and RFID UID are required');
            }
            
            $attendance = new Attendance();
            $response = $attendance->recordAttendanceRFID($sessionId, $rfidUid);
            break;
        
        case 'get_session_info':
            $sessionId = $_POST['session_id'] ?? $_GET['session_id'] ?? null;
            
            $attendance = new Attendance();
            $session = $attendance->getSession($sessionId);
            
            if ($session && $session['status'] === 'open') {
                $response = ['success' => true, 'session' => $session];
            } else {
                $response = ['success' => false, 'message' => 'Session not found or not open'];
            }
            break;
        
        case 'edit_attendance':
            $auth->requireRole(['professor', 'secretary']);
            
            $attendanceId = $_POST['attendance_id'] ?? null;
            $status = $_POST['status'] ?? '';
            $remarks = $_POST['remarks'] ?? null;
            
            $attendance = new Attendance();
            $response = $attendance->editAttendance(
                $attendanceId,
                $status,
                $auth->getUserId(),
                $remarks
            );
            break;
        
        case 'add_manual_attendance':
            $auth->requireRole(['professor', 'secretary']);
            
            $sessionId = $_POST['session_id'] ?? null;
            $studentId = $_POST['student_id'] ?? null;
            $status = $_POST['status'] ?? 'present';
            $remarks = $_POST['remarks'] ?? null;
            
            $attendance = new Attendance();
            $response = $attendance->addManualAttendance(
                $sessionId,
                $studentId,
                $status,
                $auth->getUserId(),
                $remarks
            );
            break;
        
        case 'cancel_session':
            $auth->requireRole(['professor', 'secretary']);
            
            $sessionId = $_POST['session_id'] ?? null;
            
            $attendance = new Attendance();
            $response = $attendance->cancelSession($sessionId, $auth->getUserId());
            break;
        
        // ========================================
        // CLASS MANAGEMENT ACTIONS
        // ========================================
        
        case 'enroll_student':
            $auth->requireRole(['secretary']);
            
            $classId = $_POST['class_id'] ?? null;
            $studentId = $_POST['student_id'] ?? null;
            
            $classManager = new ClassManager();
            $response = $classManager->enrollStudent($classId, $studentId);
            break;
        
        case 'drop_student':
            $auth->requireRole(['secretary']);
            
            $enrollmentId = $_POST['enrollment_id'] ?? null;
            
            $classManager = new ClassManager();
            $response = $classManager->dropStudent($enrollmentId);
            break;
        
        case 'search_students':
            $auth->requireRole(['professor', 'secretary']);
            
            $query = $_GET['query'] ?? '';
            
            $studentManager = new StudentManager();
            $students = $studentManager->searchStudents($query);
            
            $response = ['success' => true, 'students' => $students];
            break;
        
        // ========================================
        // EXCUSE LETTER ACTIONS
        // ========================================
        
        case 'submit_excuse':
            $auth->requireRole(['student']);
            
            $excuseType = $_POST['excuse_type'] ?? '';
            $description = $_POST['description'] ?? '';
            $attendanceIds = json_decode($_POST['attendance_ids'] ?? '[]', true);
            
            $excuseManager = new ExcuseManager();
            $response = $excuseManager->submitExcuse(
                $auth->getRoleId(),
                $excuseType,
                $description,
                $attendanceIds,
                $_FILES['attachments'] ?? []
            );
            break;
        
        case 'approve_excuse':
            $auth->requireRole(['professor', 'secretary']);
            
            $excuseId = $_POST['excuse_id'] ?? null;
            
            $excuseManager = new ExcuseManager();
            $response = $excuseManager->approveExcuse($excuseId, $auth->getUserId());
            break;
        
        case 'reject_excuse':
            $auth->requireRole(['professor', 'secretary']);
            
            $excuseId = $_POST['excuse_id'] ?? null;
            $reason = $_POST['reason'] ?? '';
            
            $excuseManager = new ExcuseManager();
            $response = $excuseManager->rejectExcuse($excuseId, $auth->getUserId(), $reason);
            break;
        
        case 'get_student_absences':
            $auth->requireRole(['student']);
            
            $excuseManager = new ExcuseManager();
            $absences = $excuseManager->getStudentAbsentRecords($auth->getRoleId());
            
            $response = ['success' => true, 'absences' => $absences];
            break;
        
        // ========================================
        // REPORT ACTIONS
        // ========================================
        
        case 'generate_daily_report':
            $auth->requireRole(['professor', 'secretary']);
            
            $classId = $_POST['class_id'] ?? $_GET['class_id'] ?? null;
            $date = $_POST['date'] ?? $_GET['date'] ?? date('Y-m-d');
            
            $reportGen = new ReportGenerator();
            $response = $reportGen->generateDailyReport($classId, $date);
            break;
        
        case 'generate_student_summary':
            $studentId = $_POST['student_id'] ?? $_GET['student_id'] ?? null;
            $startDate = $_POST['start_date'] ?? $_GET['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? $_GET['end_date'] ?? null;
            
            // Students can only view their own summary
            if ($auth->getUserType() === 'student') {
                $studentId = $auth->getRoleId();
            }
            
            $reportGen = new ReportGenerator();
            $response = $reportGen->generateStudentSummary($studentId, $startDate, $endDate);
            break;
        
        case 'generate_class_summary':
            $auth->requireRole(['professor', 'secretary']);
            
            $classId = $_POST['class_id'] ?? $_GET['class_id'] ?? null;
            $startDate = $_POST['start_date'] ?? $_GET['start_date'] ?? null;
            $endDate = $_POST['end_date'] ?? $_GET['end_date'] ?? null;
            
            $reportGen = new ReportGenerator();
            $response = $reportGen->generateClassSummary($classId, $startDate, $endDate);
            break;
        
        // ========================================
        // STUDENT MANAGEMENT ACTIONS
        // ========================================
        
        case 'create_student':
            $auth->requireRole(['secretary']);
            
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
            
            $studentManager = new StudentManager();
            $response = $studentManager->createStudent($userData, $studentData);
            break;
        
        case 'update_student':
            $auth->requireRole(['secretary']);
            
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
            
            $studentManager = new StudentManager();
            $response = $studentManager->updateStudent($studentId, $userData, $studentData);
            break;
        
        case 'deactivate_student':
            $auth->requireRole(['secretary']);
            
            $studentId = $_POST['student_id'] ?? null;
            
            $studentManager = new StudentManager();
            $response = $studentManager->deactivateStudent($studentId);
            break;
        
        case 'activate_student':
            $auth->requireRole(['secretary']);
            
            $studentId = $_POST['student_id'] ?? null;
            
            $studentManager = new StudentManager();
            $response = $studentManager->activateStudent($studentId);
            break;
        
        // ========================================
        // RFID MANAGEMENT ACTIONS
        // ========================================
        
        case 'register_rfid':
            $auth->requireRole(['student']);
            
            $rfidUid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
            
            $studentManager = new StudentManager();
            $response = $studentManager->registerRFID($auth->getRoleId(), $rfidUid);
            break;
        
        case 'deactivate_rfid':
            $auth->requireRole(['secretary']);
            
            $studentId = $_POST['student_id'] ?? null;
            $reason = $_POST['reason'] ?? 'Lost/Stolen';
            
            $studentManager = new StudentManager();
            $response = $studentManager->deactivateRFID($studentId, $reason, $auth->getUserId());
            break;
        
        case 'replace_rfid':
            $auth->requireRole(['secretary']);
            
            $studentId = $_POST['student_id'] ?? null;
            $newRfidUid = strtoupper(trim($_POST['new_rfid_uid'] ?? ''));
            $reason = $_POST['reason'] ?? 'Card replacement';
            
            $studentManager = new StudentManager();
            $response = $studentManager->replaceRFID($studentId, $newRfidUid, $reason, $auth->getUserId());
            break;
        
        case 'get_rfid_status':
            $studentId = $_POST['student_id'] ?? $_GET['student_id'] ?? null;
            
            // Students can only check their own status
            if ($auth->getUserType() === 'student') {
                $studentId = $auth->getRoleId();
            }
            
            $studentManager = new StudentManager();
            $status = $studentManager->getRFIDStatus($studentId);
            
            $response = ['success' => true, 'rfid_status' => $status];
            break;
        
        // ========================================
        // DEFAULT
        // ========================================
        
        default:
            throw new Exception('Unknown action: ' . $action);
    }
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'message' => $e->getMessage()
    ];
    
    // Log error
    error_log('API Error: ' . $e->getMessage());
}

// Output JSON response
echo json_encode($response);
exit;
?>