<?php
/**
 * Excuse Letter Management
 * Handles excuse letter submissions and approvals
 */

require_once 'config.php';
require_once 'Database.php';

class ExcuseManager {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Submit excuse letter
     */
    public function submitExcuse($studentId, $excuseType, $description, $attendanceIds, $files = []) {
        $this->db->beginTransaction();
        
        try {
            // Insert excuse letter
            $sql = "INSERT INTO excuse_letters (student_id, excuse_type, description) 
                    VALUES (?, ?, ?)";
            
            $this->db->query($sql, [$studentId, $excuseType, $description]);
            $excuseId = $this->db->lastInsertId();
            
            // Link to attendance records
            if (!empty($attendanceIds)) {
                $dateSql = "INSERT INTO excuse_dates (excuse_id, attendance_id) VALUES (?, ?)";
                foreach ($attendanceIds as $attendanceId) {
                    $this->db->query($dateSql, [$excuseId, $attendanceId]);
                }
            }
            
            // Handle file uploads
            if (!empty($files) && isset($files['tmp_name'])) {
                $uploadResult = $this->uploadFiles($excuseId, $files);
                if (!$uploadResult['success']) {
                    throw new Exception($uploadResult['message']);
                }
            }
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Excuse letter submitted successfully', 'excuse_id' => $excuseId];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error submitting excuse: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    /**
     * Upload files for excuse letter
     */
    private function uploadFiles($excuseId, $files) {
        $uploadedCount = 0;
        $fileCount = is_array($files['name']) ? count($files['name']) : 1;
        
        // Check file count limit
        if ($fileCount > MAX_FILES_PER_EXCUSE) {
            return ['success' => false, 'message' => 'Maximum ' . MAX_FILES_PER_EXCUSE . ' files allowed'];
        }
        
        // Normalize files array
        if (!is_array($files['name'])) {
            $files = [
                'name' => [$files['name']],
                'type' => [$files['type']],
                'tmp_name' => [$files['tmp_name']],
                'error' => [$files['error']],
                'size' => [$files['size']]
            ];
        }
        
        for ($i = 0; $i < $fileCount; $i++) {
            // Skip if no file
            if ($files['error'][$i] === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            
            // Check for upload errors
            if ($files['error'][$i] !== UPLOAD_ERR_OK) {
                return ['success' => false, 'message' => 'File upload error'];
            }
            
            // Validate file size
            if ($files['size'][$i] > MAX_FILE_SIZE) {
                return ['success' => false, 'message' => 'File size exceeds 5MB limit'];
            }
            
            // Validate file type
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $files['tmp_name'][$i]);
            finfo_close($finfo);
            
            if (!in_array($mimeType, ALLOWED_FILE_TYPES)) {
                return ['success' => false, 'message' => 'Invalid file type. Only PDF and images allowed'];
            }
            
            // Validate file extension
            $extension = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            if (!in_array($extension, ALLOWED_EXTENSIONS)) {
                return ['success' => false, 'message' => 'Invalid file extension'];
            }
            
            // Generate unique filename
            $newFilename = 'excuse_' . $excuseId . '_' . time() . '_' . uniqid() . '.' . $extension;
            $destination = UPLOAD_DIR . $newFilename;
            
            // Move uploaded file
            if (move_uploaded_file($files['tmp_name'][$i], $destination)) {
                // Save to database
                $sql = "INSERT INTO excuse_attachments (excuse_id, file_name, file_path, file_type, file_size) 
                        VALUES (?, ?, ?, ?, ?)";
                
                $this->db->query($sql, [
                    $excuseId,
                    $files['name'][$i],
                    $newFilename,
                    $mimeType,
                    $files['size'][$i]
                ]);
                
                $uploadedCount++;
            } else {
                return ['success' => false, 'message' => 'Failed to upload file'];
            }
        }
        
        return ['success' => true, 'uploaded' => $uploadedCount];
    }
    
    /**
     * Get excuse letters (with filters)
     */
    public function getExcuses($filters = []) {
        $sql = "SELECT e.*, 
                s.student_number,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                s.program, s.section,
                CONCAT(approver.first_name, ' ', approver.last_name) as approver_name,
                (SELECT COUNT(*) FROM excuse_dates WHERE excuse_id = e.excuse_id) as affected_dates_count,
                (SELECT COUNT(*) FROM excuse_attachments WHERE excuse_id = e.excuse_id) as attachment_count
                FROM excuse_letters e
                JOIN students s ON e.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN users approver ON e.approved_by = approver.user_id
                WHERE 1=1";
        
        $params = [];
        
        if (!empty($filters['student_id'])) {
            $sql .= " AND e.student_id = ?";
            $params[] = $filters['student_id'];
        }
        
        if (!empty($filters['status'])) {
            $sql .= " AND e.status = ?";
            $params[] = $filters['status'];
        }
        
        if (!empty($filters['excuse_type'])) {
            $sql .= " AND e.excuse_type = ?";
            $params[] = $filters['excuse_type'];
        }
        
        $sql .= " ORDER BY e.submitted_date DESC";
        
        return $this->db->all($sql, $params);
    }
    
    /**
     * Get excuse by ID with full details
     */
    public function getExcuseById($excuseId) {
        $sql = "SELECT e.*, 
                s.student_id, s.student_number,
                CONCAT(u.first_name, ' ', u.last_name) as student_name,
                u.email as student_email,
                s.program, s.section, s.year_level,
                CONCAT(approver.first_name, ' ', approver.last_name) as approver_name
                FROM excuse_letters e
                JOIN students s ON e.student_id = s.student_id
                JOIN users u ON s.user_id = u.user_id
                LEFT JOIN users approver ON e.approved_by = approver.user_id
                WHERE e.excuse_id = ?";
        
        return $this->db->single($sql, [$excuseId]);
    }
    
    /**
     * Get excuse dates (affected absences)
     */
    public function getExcuseDates($excuseId) {
        $sql = "SELECT ed.*, ar.session_id, ar.status as attendance_status,
                asess.session_date, asess.start_time, asess.end_time,
                c.section, co.course_code, co.course_name
                FROM excuse_dates ed
                JOIN attendance_records ar ON ed.attendance_id = ar.attendance_id
                JOIN attendance_sessions asess ON ar.session_id = asess.session_id
                JOIN classes c ON asess.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                WHERE ed.excuse_id = ?
                ORDER BY asess.session_date, asess.start_time";
        
        return $this->db->all($sql, [$excuseId]);
    }
    
    /**
     * Get excuse attachments
     */
    public function getAttachments($excuseId) {
        return $this->db->all(
            "SELECT * FROM excuse_attachments WHERE excuse_id = ? ORDER BY uploaded_at",
            [$excuseId]
        );
    }
    
    /**
     * Approve excuse letter
     */
    public function approveExcuse($excuseId, $approverId) {
        $this->db->beginTransaction();
        
        try {
            // Update excuse status
            $sql = "UPDATE excuse_letters 
                    SET status = 'approved', approved_by = ?, approval_date = NOW() 
                    WHERE excuse_id = ? AND status = 'pending'";
            
            if (!$this->db->query($sql, [$approverId, $excuseId])) {
                throw new Exception("Failed to update excuse status");
            }
            
            // Update affected attendance records to 'excused'
            $updateAttendance = "UPDATE attendance_records ar
                                 JOIN excuse_dates ed ON ar.attendance_id = ed.attendance_id
                                 SET ar.status = 'excused', ar.updated_at = NOW()
                                 WHERE ed.excuse_id = ?";
            
            $this->db->query($updateAttendance, [$excuseId]);
            
            // Log audit
            $this->logAudit($approverId, 'update', 'excuse_letters', $excuseId, 
                ['status' => 'pending'], 
                ['status' => 'approved', 'approved_by' => $approverId]
            );
            
            $this->db->commit();
            return ['success' => true, 'message' => 'Excuse letter approved'];
            
        } catch (Exception $e) {
            $this->db->rollback();
            error_log("Error approving excuse: " . $e->getMessage());
            return ['success' => false, 'message' => 'Failed to approve excuse'];
        }
    }
    
    /**
     * Reject excuse letter
     */
    public function rejectExcuse($excuseId, $approverId, $reason) {
        $sql = "UPDATE excuse_letters 
                SET status = 'rejected', approved_by = ?, approval_date = NOW(), rejection_reason = ? 
                WHERE excuse_id = ? AND status = 'pending'";
        
        if ($this->db->query($sql, [$approverId, $reason, $excuseId])) {
            $this->logAudit($approverId, 'update', 'excuse_letters', $excuseId, 
                ['status' => 'pending'], 
                ['status' => 'rejected', 'rejection_reason' => $reason]
            );
            
            return ['success' => true, 'message' => 'Excuse letter rejected'];
        }
        
        return ['success' => false, 'message' => 'Failed to reject excuse'];
    }
    
    /**
     * Get student's absent records for excuse submission
     */
    public function getStudentAbsentRecords($studentId) {
        $sql = "SELECT ar.attendance_id, ar.session_id, ar.status,
                asess.session_date, asess.start_time, asess.end_time,
                c.class_id, c.section, co.course_code, co.course_name,
                CASE 
                    WHEN ed.excuse_date_id IS NOT NULL THEN 1
                    ELSE 0
                END as has_excuse
                FROM attendance_records ar
                JOIN attendance_sessions asess ON ar.session_id = asess.session_id
                JOIN classes c ON asess.class_id = c.class_id
                JOIN courses co ON c.course_id = co.course_id
                LEFT JOIN excuse_dates ed ON ar.attendance_id = ed.attendance_id
                WHERE ar.student_id = ? AND ar.status = 'absent' AND asess.status = 'closed'
                ORDER BY asess.session_date DESC, asess.start_time DESC";
        
        return $this->db->all($sql, [$studentId]);
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