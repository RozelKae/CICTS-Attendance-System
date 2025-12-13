<?php
/**
 * Utility Helper Functions
 * Common functions used across the system
 */

class Utils {
    
    /**
     * Sanitize input string
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * Format date for display
     */
    public static function formatDate($date, $format = 'F d, Y') {
        return date($format, strtotime($date));
    }
    
    /**
     * Format time for display
     */
    public static function formatTime($time, $format = 'h:i A') {
        return date($format, strtotime($time));
    }
    
    /**
     * Format datetime for display
     */
    public static function formatDateTime($datetime, $format = 'F d, Y h:i A') {
        return date($format, strtotime($datetime));
    }
    
    /**
     * Get day of week name
     */
    public static function getDayName($date) {
        return date('l', strtotime($date));
    }
    
    /**
     * Calculate attendance percentage
     */
    public static function calculatePercentage($present, $total) {
        if ($total == 0) return 0;
        return round(($present / $total) * 100, 2);
    }
    
    /**
     * Get status badge HTML
     */
    public static function getStatusBadge($status) {
        $badges = [
            'present' => '<span class="badge badge-success">Present</span>',
            'late' => '<span class="badge badge-warning">Late</span>',
            'absent' => '<span class="badge badge-danger">Absent</span>',
            'excused' => '<span class="badge badge-info">Excused</span>',
            'active' => '<span class="badge badge-success">Active</span>',
            'inactive' => '<span class="badge badge-secondary">Inactive</span>',
            'open' => '<span class="badge badge-success">Open</span>',
            'closed' => '<span class="badge badge-secondary">Closed</span>',
            'scheduled' => '<span class="badge badge-info">Scheduled</span>',
            'cancelled' => '<span class="badge badge-danger">Cancelled</span>',
            'pending' => '<span class="badge badge-warning">Pending</span>',
            'approved' => '<span class="badge badge-success">Approved</span>',
            'rejected' => '<span class="badge badge-danger">Rejected</span>',
            'enrolled' => '<span class="badge badge-success">Enrolled</span>',
            'dropped' => '<span class="badge badge-secondary">Dropped</span>'
        ];
        
        return $badges[strtolower($status)] ?? '<span class="badge badge-secondary">' . ucfirst($status) . '</span>';
    }
    
    /**
     * Generate random string
     */
    public static function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2));
    }
    
    /**
     * Validate email
     */
    public static function isValidEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * Validate student number format (flexible)
     */
    public static function isValidStudentNumber($studentNumber) {
        // Allow alphanumeric, hyphens, and underscores, 5-20 characters
        return preg_match('/^[a-zA-Z0-9_-]{5,20}$/', $studentNumber);
    }
    
    /**
     * Validate password strength
     */
    public static function isStrongPassword($password) {
        return strlen($password) >= 8;
    }
    
    /**
     * Get file extension
     */
    public static function getFileExtension($filename) {
        return strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    }
    
    /**
     * Get MIME type from file
     */
    public static function getMimeType($filepath) {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $filepath);
        finfo_close($finfo);
        return $mimeType;
    }
    
    /**
     * Format file size for display
     */
    public static function formatFileSize($bytes) {
        if ($bytes >= 1073741824) {
            return number_format($bytes / 1073741824, 2) . ' GB';
        } elseif ($bytes >= 1048576) {
            return number_format($bytes / 1048576, 2) . ' MB';
        } elseif ($bytes >= 1024) {
            return number_format($bytes / 1024, 2) . ' KB';
        } else {
            return $bytes . ' bytes';
        }
    }
    
    /**
     * Create breadcrumb navigation
     */
    public static function breadcrumb($items) {
        $html = '<nav class="breadcrumb"><ol>';
        foreach ($items as $label => $url) {
            if ($url) {
                $html .= '<li><a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a></li>';
            } else {
                $html .= '<li class="active">' . htmlspecialchars($label) . '</li>';
            }
        }
        $html .= '</ol></nav>';
        return $html;
    }
    
    /**
     * Generate alert HTML
     */
    public static function alert($message, $type = 'info') {
        $types = ['success', 'error', 'warning', 'info'];
        $type = in_array($type, $types) ? $type : 'info';
        
        return '<div class="alert alert-' . $type . '">' . htmlspecialchars($message) . '</div>';
    }
    
    /**
     * Generate pagination HTML
     */
    public static function pagination($currentPage, $totalPages, $baseUrl) {
        if ($totalPages <= 1) return '';
        
        $html = '<div class="pagination">';
        
        // Previous button
        if ($currentPage > 1) {
            $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage - 1) . '">&laquo; Previous</a>';
        }
        
        // Page numbers
        for ($i = 1; $i <= $totalPages; $i++) {
            if ($i == $currentPage) {
                $html .= '<span class="current">' . $i . '</span>';
            } else {
                $html .= '<a href="' . $baseUrl . '&page=' . $i . '">' . $i . '</a>';
            }
        }
        
        // Next button
        if ($currentPage < $totalPages) {
            $html .= '<a href="' . $baseUrl . '&page=' . ($currentPage + 1) . '">Next &raquo;</a>';
        }
        
        $html .= '</div>';
        return $html;
    }
    
    /**
     * Redirect with message
     */
    public static function redirect($url, $message = null, $type = 'success') {
        if ($message) {
            $_SESSION['flash_message'] = $message;
            $_SESSION['flash_type'] = $type;
        }
        header('Location: ' . $url);
        exit;
    }
    
    /**
     * Get and clear flash message
     */
    public static function getFlashMessage() {
        if (isset($_SESSION['flash_message'])) {
            $message = $_SESSION['flash_message'];
            $type = $_SESSION['flash_type'] ?? 'info';
            unset($_SESSION['flash_message']);
            unset($_SESSION['flash_type']);
            return self::alert($message, $type);
        }
        return '';
    }
    
    /**
     * Time ago format
     */
    public static function timeAgo($datetime) {
        $timestamp = strtotime($datetime);
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'just now';
        } elseif ($diff < 3600) {
            $mins = floor($diff / 60);
            return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } elseif ($diff < 604800) {
            $days = floor($diff / 86400);
            return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        } else {
            return date('M d, Y', $timestamp);
        }
    }
    
    /**
     * Get current academic year
     */
    public static function getCurrentAcademicYear() {
        $year = date('Y');
        $month = date('n');
        
        // Academic year typically starts in August
        if ($month >= 8) {
            return $year . '-' . ($year + 1);
        } else {
            return ($year - 1) . '-' . $year;
        }
    }
    
    /**
     * Get current semester
     */
    public static function getCurrentSemester() {
        $month = date('n');
        
        if ($month >= 8 && $month <= 12) {
            return '1'; // First semester
        } elseif ($month >= 1 && $month <= 5) {
            return '2'; // Second semester
        } else {
            return 'Summer'; // Summer
        }
    }
    
    /**
     * CSV export helper
     */
    public static function exportCSV($data, $filename, $headers = []) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // Write headers if provided
        if (!empty($headers)) {
            fputcsv($output, $headers);
        } elseif (!empty($data)) {
            // Use first row keys as headers
            fputcsv($output, array_keys($data[0]));
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Debug helper (only in development)
     */
    public static function debug($data, $die = false) {
        if (defined('DEBUG_MODE') && DEBUG_MODE) {
            echo '<pre>';
            print_r($data);
            echo '</pre>';
            if ($die) die();
        }
    }
    
    /**
     * Log to file
     */
    public static function log($message, $filename = 'app.log') {
        $logDir = __DIR__ . '/logs';
        if (!file_exists($logDir)) {
            mkdir($logDir, 0755, true);
        }
        
        $logFile = $logDir . '/' . $filename;
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] {$message}\n";
        
        file_put_contents($logFile, $logMessage, FILE_APPEND);
    }
}
?>