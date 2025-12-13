<?php
/**
 * Report Generator
 * Handles all report generation functionality
 */

require_once 'config.php';
require_once 'Database.php';

class ReportGenerator {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Generate Daily Attendance Report for a class
     */
    public function generateDailyReport($classId, $date) {
        // Get class info
        $classInfo = $this->db->single(
            "SELECT c.*, co.course_code, co.course_name, c.section, c.room,
             CONCAT(u.first_name, ' ', u.last_name) as professor_name
             FROM classes c
             JOIN courses co ON c.course_id = co.course_id
             JOIN professors p ON c.professor_id = p.professor_id
             JOIN users u ON p.user_id = u.user_id
             WHERE c.class_id = ?",
            [$classId]
        );
        
        if (!$classInfo) {
            return ['success' => false, 'message' => 'Class not found'];
        }
        
        // Get sessions for the date
        $sessions = $this->db->all(
            "SELECT * FROM attendance_sessions 
             WHERE class_id = ? AND session_date = ? AND status = 'closed'
             ORDER BY start_time",
            [$classId, $date]
        );
        
        if (empty($sessions)) {
            return ['success' => false, 'message' => 'No attendance records found for this date'];
        }
        
        $reportData = [
            'class_info' => $classInfo,
            'date' => $date,
            'sessions' => []
        ];
        
        foreach ($sessions as $session) {
            $attendance = $this->db->all(
                "SELECT ar.*, s.student_number,
                 CONCAT(u.first_name, ' ', u.last_name) as student_name,
                 s.program, s.section as student_section
                 FROM attendance_records ar
                 JOIN students s ON ar.student_id = s.student_id
                 JOIN users u ON s.user_id = u.user_id
                 WHERE ar.session_id = ?
                 ORDER BY student_name",
                [$session['session_id']]
            );
            
            $summary = $this->calculateSummary($attendance);
            
            $reportData['sessions'][] = [
                'session' => $session,
                'attendance' => $attendance,
                'summary' => $summary
            ];
        }
        
        return ['success' => true, 'data' => $reportData];
    }
    
    /**
     * Generate Student Summary Report
     */
    public function generateStudentSummary($studentId, $startDate = null, $endDate = null) {
        // Get student info
        $student = $this->db->single(
            "SELECT s.*, CONCAT(u.first_name, ' ', u.last_name) as full_name,
             u.email, s.student_number, s.program, s.section, s.year_level
             FROM students s
             JOIN users u ON s.user_id = u.user_id
             WHERE s.student_id = ?",
            [$studentId]
        );
        
        if (!$student) {
            return ['success' => false, 'message' => 'Student not found'];
        }
        
        // Get enrolled classes
        $sql = "SELECT c.class_id, co.course_code, co.course_name, c.section,
                CONCAT(u.first_name, ' ', u.last_name) as professor_name,
                COUNT(DISTINCT asess.session_id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                ROUND((SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) / 
                       COUNT(DISTINCT asess.session_id)) * 100, 2) as attendance_percentage
                FROM class_enrollments ce
                JOIN classes c ON ce.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                JOIN professors p ON c.professor_id = p.professor_id
                JOIN users u ON p.user_id = u.user_id
                LEFT JOIN attendance_sessions asess ON c.class_id = asess.class_id 
                    AND asess.status = 'closed'";
        
        $params = [$studentId];
        
        if ($startDate) {
            $sql .= " AND asess.session_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND asess.session_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " LEFT JOIN attendance_records ar ON asess.session_id = ar.session_id 
                  AND ar.student_id = ce.student_id
                  WHERE ce.student_id = ? AND ce.status = 'enrolled'
                  GROUP BY c.class_id, co.course_code, co.course_name, c.section, u.first_name, u.last_name
                  ORDER BY co.course_code";
        
        $classes = $this->db->all($sql, $params);
        
        // Calculate overall statistics
        $overallStats = [
            'total_classes' => count($classes),
            'total_sessions' => 0,
            'total_present' => 0,
            'total_late' => 0,
            'total_absent' => 0,
            'total_excused' => 0,
            'overall_percentage' => 0
        ];
        
        foreach ($classes as $class) {
            $overallStats['total_sessions'] += $class['total_sessions'];
            $overallStats['total_present'] += $class['present_count'];
            $overallStats['total_late'] += $class['late_count'];
            $overallStats['total_absent'] += $class['absent_count'];
            $overallStats['total_excused'] += $class['excused_count'];
        }
        
        if ($overallStats['total_sessions'] > 0) {
            $overallStats['overall_percentage'] = round(
                (($overallStats['total_present'] + $overallStats['total_late']) / $overallStats['total_sessions']) * 100,
                2
            );
        }
        
        return [
            'success' => true,
            'data' => [
                'student' => $student,
                'classes' => $classes,
                'overall_stats' => $overallStats,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]
        ];
    }
    
    /**
     * Generate Class Attendance Summary Report
     */
    public function generateClassSummary($classId, $startDate = null, $endDate = null) {
        // Get class info
        $classInfo = $this->db->single(
            "SELECT c.*, co.course_code, co.course_name, c.section, c.room,
             CONCAT(u.first_name, ' ', u.last_name) as professor_name,
             co.units
             FROM classes c
             JOIN courses co ON c.course_id = co.course_id
             JOIN professors p ON c.professor_id = p.professor_id
             JOIN users u ON p.user_id = u.user_id
             WHERE c.class_id = ?",
            [$classId]
        );
        
        if (!$classInfo) {
            return ['success' => false, 'message' => 'Class not found'];
        }
        
        // Get student attendance data
        $sql = "SELECT s.student_id, s.student_number,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                s.program, s.section as student_section,
                COUNT(DISTINCT asess.session_id) as total_sessions,
                SUM(CASE WHEN ar.status = 'present' THEN 1 ELSE 0 END) as present_count,
                SUM(CASE WHEN ar.status = 'late' THEN 1 ELSE 0 END) as late_count,
                SUM(CASE WHEN ar.status = 'absent' THEN 1 ELSE 0 END) as absent_count,
                SUM(CASE WHEN ar.status = 'excused' THEN 1 ELSE 0 END) as excused_count,
                ROUND((SUM(CASE WHEN ar.status IN ('present', 'late') THEN 1 ELSE 0 END) / 
                       COUNT(DISTINCT asess.session_id)) * 100, 2) as attendance_percentage
                FROM class_enrollments ce
                JOIN students s ON ce.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN attendance_sessions asess ON ce.class_id = asess.class_id 
                    AND asess.status = 'closed'";
        
        $params = [$classId];
        
        if ($startDate) {
            $sql .= " AND asess.session_date >= ?";
            $params[] = $startDate;
        }
        
        if ($endDate) {
            $sql .= " AND asess.session_date <= ?";
            $params[] = $endDate;
        }
        
        $sql .= " LEFT JOIN attendance_records ar ON asess.session_id = ar.session_id 
                  AND ar.student_id = ce.student_id
                  WHERE ce.class_id = ? AND ce.status = 'enrolled'
                  GROUP BY s.student_id, s.student_number, u.first_name, u.last_name, 
                           s.program, s.section
                  ORDER BY student_name";
        
        $students = $this->db->all($sql, $params);
        
        // Calculate class statistics
        $classStats = [
            'total_enrolled' => count($students),
            'avg_attendance_percentage' => 0,
            'highest_attendance' => 0,
            'lowest_attendance' => 100
        ];
        
        $totalPercentage = 0;
        foreach ($students as $student) {
            $percentage = $student['attendance_percentage'] ?? 0;
            $totalPercentage += $percentage;
            
            if ($percentage > $classStats['highest_attendance']) {
                $classStats['highest_attendance'] = $percentage;
            }
            
            if ($percentage < $classStats['lowest_attendance'] && $percentage > 0) {
                $classStats['lowest_attendance'] = $percentage;
            }
        }
        
        if ($classStats['total_enrolled'] > 0) {
            $classStats['avg_attendance_percentage'] = round($totalPercentage / $classStats['total_enrolled'], 2);
        }
        
        return [
            'success' => true,
            'data' => [
                'class_info' => $classInfo,
                'students' => $students,
                'class_stats' => $classStats,
                'date_range' => [
                    'start' => $startDate,
                    'end' => $endDate
                ]
            ]
        ];
    }
    
    /**
     * Calculate attendance summary
     */
    private function calculateSummary($attendanceRecords) {
        $summary = [
            'total' => count($attendanceRecords),
            'present' => 0,
            'late' => 0,
            'absent' => 0,
            'excused' => 0
        ];
        
        foreach ($attendanceRecords as $record) {
            switch ($record['status']) {
                case 'present':
                    $summary['present']++;
                    break;
                case 'late':
                    $summary['late']++;
                    break;
                case 'absent':
                    $summary['absent']++;
                    break;
                case 'excused':
                    $summary['excused']++;
                    break;
            }
        }
        
        if ($summary['total'] > 0) {
            $summary['attendance_rate'] = round(
                (($summary['present'] + $summary['late']) / $summary['total']) * 100,
                2
            );
        } else {
            $summary['attendance_rate'] = 0;
        }
        
        return $summary;
    }
    
    /**
     * Export report to CSV
     */
    public function exportToCSV($reportData, $filename) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // This will be customized based on report type
        // Implementation depends on the specific report structure
        
        fclose($output);
        exit;
    }
}
?>