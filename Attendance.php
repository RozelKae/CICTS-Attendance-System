<?php
/**
 * Attendance Management Class (RFID VERSION)
 * Handles all attendance-related operations using RFID
 */

require_once 'config.php';
require_once 'Database.php';

class Attendance {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Create attendance session (auto-open system) - NO QR CODE
     */
    public function createSession($classId, $scheduleId, $date, $startTime, $endTime) {
        $sql = "INSERT INTO attendance_sessions 
                (class_id, schedule_id, session_date, start_time, end_time, status) 
                VALUES (?, ?, ?, ?, ?, 'scheduled')";
        
        if ($this->db->query($sql, [$classId, $scheduleId, $date, $startTime, $endTime])) {
            return $this->db->lastInsertId();
        }
        
        return false;
    }
    
    /**
     * Open session (called by cron or manual)
     */
    public function openSession($sessionId) {
        $sql = "UPDATE attendance_sessions 
                SET status = 'open', opened_at = NOW() 
                WHERE session_id = ? AND status = 'scheduled'";
        
        return $this->db->query($sql, [$sessionId]);
    }
    
    /**
     * Close session and mark absent students
     */
    public function closeSession($sessionId) {
        $this->db->beginTransaction();
        
        try {
            // Get session details
            $session = $this->db->single(
                "SELECT class_id FROM attendance_sessions WHERE session_id = ?", 
                [$sessionId]
            );
            
            if (!$session) {
                throw new Exception("Session not found");
            }
            
            // Get enrolled students who haven't been marked
            $sql = "SELECT ce.student_id 
                    FROM class_enrollments ce
                    WHERE ce.class_id = ? 
                    AND ce.status = 'enrolled'
                    AND ce.student_id NOT IN (
                        SELECT student_id FROM attendance_records WHERE session_id = ?
                    )";
            
            $absentStudents = $this->db->all($sql, [$session['class_id'], $sessionId]);
            
            // Mark them as absent
            if ($absentStudents) {
                $insertSql = "INSERT INTO attendance_records 
                              (session_id, student_id, status, is_manual) 
                              VALUES (?, ?, 'absent', FALSE)";
                
                foreach ($absentStudents as $student) {
                    $this->db->query($insertSql, [$sessionId, $student['student_id']]);
                }
            }
            
            // Close the session
            $updateSql = "UPDATE attendance_sessions 
                          SET status = 'closed', closed_at = NOW() 
                          WHERE session_id = ?";
            
            $this->db->query($updateSql, [$sessionId]);
            
            $this->db->commit();
            return true;
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error closing session: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Cancel session (by professor)
     */
    public function cancelSession($sessionId, $userId) {
        $sql = "UPDATE attendance_sessions 
                SET status = 'cancelled', cancelled_by = ?, cancelled_at = NOW() 
                WHERE session_id = ? AND status = 'scheduled'";
        
        return $this->db->query($sql, [$userId, $sessionId]);
    }
    
    /**
     * Record attendance via RFID card tap
     */
    public function recordAttendanceRFID($sessionId, $rfidUid) {
        $this->db->beginTransaction();
        
        try {
            // Validate session is open
            $session = $this->db->single(
                "SELECT * FROM attendance_sessions WHERE session_id = ? AND status = 'open'",
                [$sessionId]
            );
            
            if (!$session) {
                return ['success' => false, 'message' => 'Session is not currently open'];
            }
            
            // Get student by RFID UID
            $student = $this->db->single(
                "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as full_name, s.student_number
                 FROM students s
                 JOIN users u ON s.user_id = u.user_id
                 WHERE s.rfid_uid = ? AND s.rfid_status = 'active' AND u.status = 'active'",
                [$rfidUid]
            );
            
            if (!$student) {
                return ['success' => false, 'message' => 'Unknown card - Please register your RFID card in your profile'];
            }
            
            // Check if student is enrolled in this class
            $enrollment = $this->db->single(
                "SELECT enrollment_id FROM class_enrollments 
                 WHERE class_id = ? AND student_id = ? AND status = 'enrolled'",
                [$session['class_id'], $student['student_id']]
            );
            
            if (!$enrollment) {
                return ['success' => false, 'message' => 'You are not enrolled in this class'];
            }
            
            // Check if already recorded (prevent duplicate scans)
            $existing = $this->db->single(
                "SELECT attendance_id, status, TIME_FORMAT(time_in, '%h:%i %p') as formatted_time 
                 FROM attendance_records 
                 WHERE session_id = ? AND student_id = ?",
                [$sessionId, $student['student_id']]
            );
            
            if ($existing) {
                return [
                    'success' => false, 
                    'message' => 'Already recorded at ' . $existing['formatted_time'],
                    'student' => $student
                ];
            }
            
            // Determine if late
            $timeIn = new DateTime();
            $sessionStart = new DateTime($session['session_date'] . ' ' . $session['start_time']);
            $lateThreshold = clone $sessionStart;
            $lateThreshold->modify('+' . LATE_THRESHOLD_MINUTES . ' minutes');
            
            $status = ($timeIn > $lateThreshold) ? 'late' : 'present';
            
            // Insert attendance record
            $sql = "INSERT INTO attendance_records 
                    (session_id, student_id, time_in, status, is_manual) 
                    VALUES (?, ?, NOW(), ?, FALSE)";
            
            $this->db->query($sql, [$sessionId, $student['student_id'], $status]);
            
            $this->db->commit();
            
            return [
                'success' => true, 
                'message' => 'Attendance recorded successfully',
                'status' => $status,
                'student' => $student
            ];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error recording attendance: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to record attendance'];
        }
    }
    
    /**
     * Manually edit attendance (professor/secretary)
     */
    public function editAttendance($attendanceId, $newStatus, $userId, $remarks = null) {
        // Get old record for audit
        $old = $this->db->single(
            "SELECT * FROM attendance_records WHERE attendance_id = ?",
            [$attendanceId]
        );
        
        if (!$old) {
            return ['success' => false, 'message' => 'Attendance record not found'];
        }
        
        $sql = "UPDATE attendance_records 
                SET status = ?, is_manual = TRUE, recorded_by = ?, remarks = ?, updated_at = NOW() 
                WHERE attendance_id = ?";
        
        if ($this->db->query($sql, [$newStatus, $userId, $remarks, $attendanceId])) {
            // Log audit
            $this->logAudit($userId, 'update', 'attendance_records', $attendanceId, $old, [
                'status' => $newStatus,
                'remarks' => $remarks
            ]);
            
            return ['success' => true, 'message' => 'Attendance updated successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to update attendance'];
    }
    
    /**
     * Manually add attendance record (after session closed)
     */
    public function addManualAttendance($sessionId, $studentId, $status, $userId, $remarks = null) {
        // Check if already exists
        $existing = $this->db->single(
            "SELECT attendance_id FROM attendance_records 
             WHERE session_id = ? AND student_id = ?",
            [$sessionId, $studentId]
        );
        
        if ($existing) {
            return $this->editAttendance($existing['attendance_id'], $status, $userId, $remarks);
        }
        
        $sql = "INSERT INTO attendance_records 
                (session_id, student_id, time_in, status, is_manual, recorded_by, remarks) 
                VALUES (?, ?, NOW(), ?, TRUE, ?, ?)";
        
        if ($this->db->query($sql, [$sessionId, $studentId, $status, $userId, $remarks])) {
            return ['success' => true, 'message' => 'Attendance added successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to add attendance'];
    }
    
    /**
     * Get session details
     */
    public function getSession($sessionId) {
        $sql = "SELECT s.*, c.section, co.course_code, co.course_name, c.room,
                CONCAT(u.first_name, ' ', u.last_name) as professor_name,
                DATE_FORMAT(s.session_date, '%W, %M %d, %Y') as formatted_date,
                DATE_FORMAT(s.start_time, '%h:%i %p') as formatted_start,
                DATE_FORMAT(s.end_time, '%h:%i %p') as formatted_end
                FROM attendance_sessions s
                JOIN classes c ON s.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                JOIN professors p ON c.professor_id = p.professor_id
                JOIN users u ON p.user_id = u.user_id
                WHERE s.session_id = ?";
        
        return $this->db->single($sql, [$sessionId]);
    }
    
    /**
     * Get attendance records for a session
     */
    public function getSessionAttendance($sessionId) {
        $sql = "SELECT ar.*, s.student_number, 
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                s.year_level, s.program, s.section,
                TIME_FORMAT(ar.time_in, '%h:%i %p') as formatted_time
                FROM attendance_records ar
                JOIN students s ON ar.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                WHERE ar.session_id = ?
                ORDER BY ar.time_in DESC";
        
        return $this->db->all($sql, [$sessionId]);
    }
    
    /**
     * Get session attendance count
     */
    public function getSessionCount($sessionId) {
        $sql = "SELECT 
                COUNT(*) as scanned_count,
                (SELECT COUNT(*) FROM class_enrollments ce 
                 JOIN attendance_sessions s ON ce.class_id = s.class_id
                 WHERE s.session_id = ? AND ce.status = 'enrolled') as total_students
                FROM attendance_records 
                WHERE session_id = ?";
        
        return $this->db->single($sql, [$sessionId, $sessionId]);
    }
    
    /**
     * Get student attendance summary for a class
     */
    public function getStudentClassSummary($studentId, $classId) {
        $sql = "SELECT 
                COUNT(*) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                ROUND((SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) / COUNT(*)) * 100, 2) as attendance_percentage
                FROM attendance_records ar
                JOIN attendance_sessions asess ON ar.session_id = asess.session_id
                WHERE ar.student_id = ? AND asess.class_id = ? AND asess.status = 'closed'";
        
        return $this->db->single($sql, [$studentId, $classId]);
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