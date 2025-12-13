<?php
/**
 * Class Management
 * Handles classes, enrollments, and schedules
 */

require_once 'config.php';
require_once 'Database.php';

class ClassManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Get all classes (with filters)
     */
    public function getClasses($filters = []) {
        $sql = "SELECT c.*, co.course_code, co.course_name, co.units,
                CONCAT(u.first_name, ' ', u.last_name) as professor_name,
                p.employee_number,
                (SELECT COUNT(*) FROM class_enrollments WHERE class_id = c.class_id AND status = 'enrolled') as enrolled_count
                FROM classes c
                JOIN courses co ON c.course_id = co.course_id
                JOIN professors p ON c.professor_id = p.professor_id
                JOIN users u ON p.user_id = u.user_id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['professor_id'])) {
            $sql .= " AND c.professor_id = ?";
            $params[] = $filters['professor_id'];
        }
        
        if (!empty($filters['school_year'])) {
            $sql .= " AND c.school_year = ?";
            $params[] = $filters['school_year'];
        }
        
        if (!empty($filters['semester'])) {
            $sql .= " AND c.semester = ?";
            $params[] = $filters['semester'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND c.status = ?";
            $params[] = $filters['status'];
        }
        
        $sql .= " ORDER BY co.course_code, c.section";
        
        return $this->db->all($sql, $params);
    }
    
    /**
     * Get class by ID with full details
     */
    public function getClassById($classId) {
        $sql = "SELECT c.*, co.course_code, co.course_name, co.course_description, co.units,
                CONCAT(u.first_name, ' ', u.last_name) as professor_name,
                p.professor_id, p.employee_number, u.email as professor_email
                FROM classes c
                JOIN courses co ON c.course_id = co.course_id
                JOIN professors p ON c.professor_id = p.professor_id
                JOIN users u ON p.user_id = u.user_id
                WHERE c.class_id = ?";
        
        return $this->db->single($sql, [$classId]);
    }
    
    /**
     * Get class schedules
     */
    public function getClassSchedules($classId) {
        $sql = "SELECT * FROM class_schedules WHERE class_id = ? ORDER BY 
                FIELD(day_of_week, 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'),
                start_time";
        
        return $this->db->all($sql, [$classId]);
    }
    
    /**
     * Get enrolled students in a class
     */
    public function getEnrolledStudents($classId, $status = 'enrolled') {
        $sql = "SELECT ce.*, s.student_number, s.year_level, s.program, s.section as student_section,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.email
                FROM class_enrollments ce
                JOIN students s ON ce.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                WHERE ce.class_id = ? AND ce.status = ?
                ORDER BY student_name";
        
        return $this->db->all($sql, [$classId, $status]);
    }
    
    /**
     * Enroll student in class
     */
    public function enrollStudent($classId, $studentId) {
        // Check if already enrolled
        $existing = $this->db->single(
            "SELECT * FROM class_enrollments WHERE class_id = ? AND student_id = ?",
            [$classId, $studentId]
        );
        
        if ($existing) {
            if ($existing['status'] == 'enrolled') {
                return ['success' => false, 'message' => 'Student already enrolled'];
            } else {
                // Re-enroll dropped student
                $sql = "UPDATE class_enrollments SET status = 'enrolled', enrollment_date = CURDATE() WHERE enrollment_id = ?";
                if ($this->db->query($sql, [$existing['enrollment_id']])) {
                    return ['success' => true, 'message' => 'Student re-enrolled successfully'];
                }
            }
        }
        
        $sql = "INSERT INTO class_enrollments (class_id, student_id) VALUES (?, ?)";
        
        if ($this->db->query($sql, [$classId, $studentId])) {
            return ['success' => true, 'message' => 'Student enrolled successfully'];
        }
        
        return ['success' => false, 'message' => 'Failed to enroll student'];
    }
    
    /**
     * Drop student from class
     */
    public function dropStudent($enrollmentId) {
        $sql = "UPDATE class_enrollments SET status = 'dropped', dropped_date = CURDATE() WHERE enrollment_id = ?";
        
        if ($this->db->query($sql, [$enrollmentId])) {
            return ['success' => true, 'message' => 'Student dropped from class'];
        }
        
        return ['success' => false, 'message' => 'Failed to drop student'];
    }
    
    /**
     * Get student's enrolled classes
     */
    public function getStudentClasses($studentId) {
        $sql = "SELECT c.class_id, c.section, c.school_year, c.semester, c.room,
                co.course_code, co.course_name, co.units,
                CONCAT(u.first_name, ' ', u.last_name) as professor_name,
                ce.enrollment_date
                FROM class_enrollments ce
                JOIN classes c ON ce.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                JOIN professors p ON c.professor_id = p.professor_id
                JOIN users u ON p.user_id = u.user_id
                WHERE ce.student_id = ? AND ce.status = 'enrolled' AND c.status = 'active'
                ORDER BY co.course_code";
        
        return $this->db->all($sql, [$studentId]);
    }
    
    /**
     * Create new class
     */
    public function createClass($data) {
        $sql = "INSERT INTO classes (course_id, professor_id, section, school_year, semester, room, status) 
                VALUES (?, ?, ?, ?, ?, ?, 'active')";
        
        if ($this->db->query($sql, [
            $data['course_id'],
            $data['professor_id'],
            $data['section'],
            $data['school_year'],
            $data['semester'],
            $data['room']
        ])) {
            return ['success' => true, 'class_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to create class'];
    }
    
    /**
     * Add class schedule
     */
    public function addSchedule($classId, $dayOfWeek, $startTime, $endTime, $professorId = null) {
        // Check for professor conflict if provided
        if ($professorId) {
            $conflict = $this->checkScheduleConflict($professorId, $dayOfWeek, $startTime, $endTime, $classId);
            if ($conflict) {
                return ['success' => false, 'message' => 'Professor has a schedule conflict at this time'];
            }
        }
        
        $sql = "INSERT INTO class_schedules (class_id, day_of_week, start_time, end_time) 
                VALUES (?, ?, ?, ?)";
        
        if ($this->db->query($sql, [$classId, $dayOfWeek, $startTime, $endTime])) {
            return ['success' => true, 'schedule_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Failed to add schedule'];
    }
    
    /**
     * Check for schedule conflicts
     */
    private function checkScheduleConflict($professorId, $dayOfWeek, $startTime, $endTime, $excludeClassId = null) {
        $sql = "SELECT cs.* FROM class_schedules cs
                JOIN classes c ON cs.class_id = c.class_id
                WHERE c.professor_id = ? 
                AND c.status = 'active'
                AND cs.day_of_week = ?
                AND (
                    (cs.start_time <= ? AND cs.end_time > ?) OR
                    (cs.start_time < ? AND cs.end_time >= ?) OR
                    (cs.start_time >= ? AND cs.end_time <= ?)
                )";
        
        $params = [$professorId, $dayOfWeek, $startTime, $startTime, $endTime, $endTime, $startTime, $endTime];
        
        if ($excludeClassId) {
            $sql .= " AND c.class_id != ?";
            $params[] = $excludeClassId;
        }
        
        return $this->db->single($sql, $params);
    }
    
    /**
     * Get upcoming sessions for a class
     */
    public function getUpcomingSessions($classId, $limit = 10) {
        $sql = "SELECT s.*, 
                DATE_FORMAT(s.session_date, '%W, %M %d, %Y') as formatted_date,
                DATE_FORMAT(s.start_time, '%h:%i %p') as formatted_start,
                DATE_FORMAT(s.end_time, '%h:%i %p') as formatted_end
                FROM attendance_sessions s
                WHERE s.class_id = ? 
                AND s.session_date >= CURDATE()
                AND s.status != 'cancelled'
                ORDER BY s.session_date, s.start_time
                LIMIT ?";
        
        return $this->db->all($sql, [$classId, $limit]);
    }
    
    /**
     * Get all courses
     */
    public function getCourses() {
        return $this->db->all("SELECT * FROM courses ORDER BY course_code");
    }
    
    /**
     * Get all professors
     */
    public function getProfessors() {
        $sql = "SELECT p.*, CONCAT(u.first_name, ' ', u.last_name) as full_name, u.email
                FROM professors p
                JOIN users u ON p.user_id = u.user_id
                WHERE u.status = 'active'
                ORDER BY u.last_name, u.first_name";
        
        return $this->db->all($sql);
    }
}
?>