<?php
/**
 * CITCS Attendance Monitoring System
 * Configuration File
 */

// Error Reporting (disable in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Timezone
date_default_timezone_set('Asia/Manila');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'citcs_attendance');

// Application Settings
define('SITE_URL', 'http://localhost/citcs_attendance');
define('SITE_NAME', 'CITCS Attendance System');

// File Upload Settings
define('UPLOAD_DIR', __DIR__ . '/uploads/excuse_letters/');
define('MAX_FILE_SIZE', 5242880); // 5MB in bytes
define('MAX_FILES_PER_EXCUSE', 3);
define('ALLOWED_FILE_TYPES', ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf']);
define('ALLOWED_EXTENSIONS', ['jpg', 'jpeg', 'png', 'pdf']);

// Session Settings
define('SESSION_LIFETIME', 3600); // 1 hour
ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
session_set_cookie_params(SESSION_LIFETIME);

// Attendance Settings
define('LATE_THRESHOLD_MINUTES', 15);
define('SESSION_OPEN_BEFORE_MINUTES', 5);

// Security
define('HASH_ALGO', PASSWORD_BCRYPT);
define('HASH_COST', 10);

// Pagination
define('RECORDS_PER_PAGE', 20);

// Create upload directory if it doesn't exist
if (!file_exists(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0755, true);
}

// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>