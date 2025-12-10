<?php
/**
 * Student Management
 * Handles student-related operations
 */

require_once 'config.php';
require_once 'Database.php';

class StudentManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all students with filters
     */
    public function getStudents($filters = []) {
        $sql = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.status,
                CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['program'])) {
            $sql .= " AND s.program = ?";
            $params[] = $filters['program'];
        }
        
        if (!empty($filters['year_level'])) {
            $sql .= " AND s.year_level = ?";
            $params[] = $filters['year_level'];
        }
        
        if (!empty($filters['section'])) {
            $sql .= " AND s.section = ?";
            $params[] = $filters['section'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND u.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['search'])) {
            $sql .= " AND (s.student_number LIKE ? OR u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ?)";
            $searchTerm = '%' . $filters['search'] . '%';
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
            $params[] = $searchTerm;
        }
        
        $sql .= " ORDER BY u.last_name, u.first_name";
        
        return $this->db->all($sql, $params);
    }
    
    /**
     * Get student by ID
     */
    public function getStudentById($studentId) {
        $sql = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.status, u.created_at,
                CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.student_id = ?";
        
        return $this->db->single($sql, [$studentId]);
    }
    
    /**
     * Get student by student number
     */
    public function getStudentByNumber($studentNumber) {
        $sql = "SELECT s.*, u.username, u.email, u.first_name, u.last_name, u.status,
                CONCAT(u.first_name, ' ', u.last_name) as full_name
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                WHERE s.student_number = ?";
        
        return $this->db->single($sql, [$studentNumber]);
    }
    
    /**
     * Create new student
     */
    public function createStudent($userData, $studentData) {
        $this->db->beginTransaction();
        
        try {
            // Validate student number uniqueness
            if ($this->getStudentByNumber($studentData['student_number'])) {
                throw new Exception("Student number already exists");
            }
            
            // Create user account
            $hashedPassword = password_hash($userData['password'], HASH_ALGO, ['cost' => HASH_COST]);
            
            $userSql = "INSERT INTO users (username, password, email, first_name, last_name, user_type) 
                        VALUES (?, ?, ?, ?, ?, 'student')";
            
            $this->db->query($userSql, [
                $userData['username'],
                $hashedPassword,
                $userData['email'],
                $userData['first_name'],
                $userData['last_name']
            ]);
            
            $userId = $this->db->lastInsertId();
            
            // Create student record
            $studentSql = "INSERT INTO students (user_id, student_number, year_level, program, section) 
                           VALUES (?, ?, ?, ?, ?)";
            
            $this->db->query($studentSql, [
                $userId,
                $studentData['student_number'],
                $studentData['year_level'],
                $studentData['program'],
                $studentData['section'] ?? null
            ]);
            
            $studentId = $this->db->lastInsertId();
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'message' => 'Student created successfully',
                'student_id' => $studentId,
                'user_id' => $userId
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error creating student: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Update student information
     */
    public function updateStudent($studentId, $userData, $studentData) {
        $this->db->beginTransaction();
        
        try {
            // Get current student
            $student = $this->getStudentById($studentId);
            if (!$student) {
                throw new Exception("Student not found");
            }
            
            // Update user info
            $userSql = "UPDATE users SET email = ?, first_name = ?, last_name = ? WHERE user_id = ?";
            $this->db->query($userSql, [
                $userData['email'],
                $userData['first_name'],
                $userData['last_name'],
                $student['user_id']
            ]);
            
            // Update student info
            $studentSql = "UPDATE students SET year_level = ?, program = ?, section = ? WHERE student_id = ?";
            $this->db->query($studentSql, [
                $studentData['year_level'],
                $studentData['program'],
                $studentData['section'] ?? null,
                $studentId
            ]);
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Student updated successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error updating student: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Get student attendance summary across all classes
     */
    public function getStudentAttendanceSummary($studentId) {
        $sql = "SELECT 
                c.class_id,
                co.course_code,
                co.course_name,
                c.section,
                COUNT(ar.attendance_id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                ROUND((SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(ar.attendance_id)) * 100, 2) as attendance_percentage
                FROM class_enrollments ce
                JOIN classes c ON ce.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                LEFT JOIN attendance_sessions asess ON c.class_id = asess.class_id AND asess.status = 'closed'
                LEFT JOIN attendance_records ar ON asess.session_id = ar.session_id AND ar.student_id = ce.student_id
                WHERE ce.student_id = ? AND ce.status = 'enrolled'
                GROUP BY c.class_id, co.course_code, co.course_name, c.section
                ORDER BY co.course_code";
        
        return $this->db->all($sql, [$studentId]);
    }
    
    /**
     * Get student attendance history for a specific class
     */
    public function getStudentClassAttendance($studentId, $classId, $startDate = null, $endDate = null) {
        $sql = "SELECT ar.*, 
                asess.session_date, asess.start_time, asess.end_time,
                DATE_FORMAT(asess.session_date, '%W, %M %d, %Y') as formatted_date,
                DATE_FORMAT(asess.start_time, '%h:%i %p') as formatted_start,
                DATE_FORMAT(ar.time_in, '%h:%i %p') as formatted_time_in
                FROM attendance_records ar
                JOIN attendance_sessions asess ON ar.session_id = asess.session_id
                WHERE ar.student_id = ? 
                AND asess.class_id = ?
                AND asess.status = 'closed'";
        
        $params = [$studentId, $classId];
        
        if ($startDate) {
            $sql .= " AND asess.session_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND asess.session_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " ORDER BY asess.session_date DESC, asess.start_time DESC";
        
        return $this->db->all($sql, $params);
    }
    
    /**
     * Search students (for enrollment, etc.)
     */
    public function searchStudents($query, $limit = 20) {
        $sql = "SELECT s.student_id, s.student_number, s.year_level, s.program, s.section,
                CONCAT(u.first_name, ' ', u.last_name) as full_name,
                u.email
                FROM students s
                JOIN users u ON s.user_id = u.user_id
                WHERE u.status = 'active'
                AND (s.student_number LIKE ? 
                     OR u.first_name LIKE ? 
                     OR u.last_name LIKE ? 
                     OR CONCAT(u.first_name, ' ', u.last_name) LIKE ?)
                ORDER BY u.last_name, u.first_name
                LIMIT ?";
        
        $searchTerm = '%' . $query . '%';
        return $this->db->all($sql, [$searchTerm, $searchTerm, $searchTerm, $searchTerm, $limit]);
    }
    
    /**
     * Deactivate student account
     */
    public function deactivateStudent($studentId) {
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        $sql = "UPDATE users SET status = 'inactive' WHERE user_id = ?";
        
        if ($this->db->query($sql, [$student['user_id']])) {
            return ['success' => true, 'message' => 'Student deactivated'];
        }
        
        return ['success' => false, 'message' => 'Failed to deactivate student'];
    }
    
    /**
     * Activate student account
     */
    public function activateStudent($studentId) {
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        $sql = "UPDATE users SET status = 'active' WHERE user_id = ?";
        
        if ($this->db->query($sql, [$student['user_id']])) {
            return ['success' => true, 'message' => 'Student activated'];
        }
        
        return ['success' => false, 'message' => 'Failed to activate student'];
    }
    
    /**
     * Register RFID card for student (self-registration)
     */
    public function registerRFID($studentId, $rfidUid) {
        // Validate RFID UID format (8-50 characters, alphanumeric with hyphens)
        if (!preg_match('/^[A-Z0-9-]{8,50}$/i', $rfidUid)) {
            return ['success' => false, 'message' => 'Invalid RFID format'];
        }
        
        // Check if RFID is already registered to another student
        $existing = $this->db->single(
            "SELECT s.student_id, s.student_number, CONCAT(u.first_name, ' ', u.last_name) as student_name
             FROM students s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.rfid_uid = ? AND s.student_id != ?",
            [$rfidUid, $studentId]
        );
        
        if ($existing) {
            return ['success' => false, 'message' => 'This card is already registered to another student'];
        }
        
        // Get student's user_id for registration tracking
        $student = $this->getStudentById($studentId);
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        // Register RFID
        $sql = "UPDATE students 
                SET rfid_uid = ?, 
                    rfid_registered_at = NOW(), 
                    rfid_registered_by = ?,
                    rfid_status = 'active'
                WHERE student_id = ?";
        
        if ($this->db->query($sql, [$rfidUid, $student['user_id'], $studentId])) {
            // Log the registration
            $this->logAudit($student['user_id'], 'update', 'students', $studentId, 
                ['rfid_uid' => null], 
                ['rfid_uid' => $rfidUid, 'registered_at' => date('Y-m-d H:i:s')]
            );
            
            return ['success' => true, 'message' => 'RFID card registered successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to register RFID card'];
    }
    
    /**
     * Deactivate RFID card (for lost/stolen cards)
     */
    public function deactivateRFID($studentId, $reason, $deactivatedBy) {
        $sql = "UPDATE students 
                SET rfid_status = 'deactivated',
                    rfid_deactivated_at = NOW(),
                    rfid_deactivation_reason = ?
                WHERE student_id = ?";
        
        if ($this->db->query($sql, [$reason, $studentId])) {
            // Log the deactivation
            $this->logAudit($deactivatedBy, 'update', 'students', $studentId, 
                ['rfid_status' => 'active'], 
                ['rfid_status' => 'deactivated', 'reason' => $reason]
            );
            
            return ['success' => true, 'message' => 'RFID card deactivated. Student can register a new card.'];
        }
        
        return ['success' => false, 'message' => 'Failed to deactivate RFID card'];
    }
    
    /**
     * Replace RFID card (deactivate old, allow new registration)
     */
    public function replaceRFID($studentId, $newRfidUid, $reason, $replacedBy) {
        $this->db->beginTransaction();
        
        try {
            // First deactivate old card
            $result = $this->deactivateRFID($studentId, $reason, $replacedBy);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            // Then register new card
            $result = $this->registerRFID($studentId, $newRfidUid);
            if (!$result['success']) {
                throw new Exception($result['message']);
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'RFID card replaced successfully'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Check if student has registered RFID
     */
    public function hasRFID($studentId) {
        $student = $this->db->single(
            "SELECT rfid_uid, rfid_status FROM students WHERE student_id = ?",
            [$studentId]
        );
        
        return $student && $student['rfid_uid'] !== null && $student['rfid_status'] === 'active';
    }
    
    /**
     * Get RFID status for student
     */
    public function getRFIDStatus($studentId) {
        return $this->db->single(
            "SELECT rfid_uid, rfid_registered_at, rfid_status, 
                    rfid_deactivated_at, rfid_deactivation_reason
             FROM students WHERE student_id = ?",
            [$studentId]
        );
    }
    
    /**
     * Log audit trail
     */
    private function logAudit($userId, $action, $table, $recordId, $oldValues = null, $newValues = null) {
        $sql = "INSERT INTO audit_logs (user_id, action_type, table_affected, record_id, old_values, new_values, ip_address) 
                VALUES (?, ?, ?, ?, ?, ?, ?)";
        
        $this->db->query($sql, [
            $userId,
            $action,
            $table,
            $recordId,
            $oldValues ? json_encode($oldValues) : null,
            $newValues ? json_encode($newValues) : null,
            $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}
?>