<?php
/**
 * Cron Job for Automated Attendance Session Management (RFID VERSION)
 * Run this script every minute: * * * * * php /path/to/cron_attendance.php
 * Or via URL: curl http://yoursite.com/cron_attendance.php?key=YOUR_SECRET_KEY
 */

require_once 'config.php';
require_once 'Database.php';
require_once 'Attendance.php';

// Security: Only allow execution from CLI or specific IP
if (php_sapi_name() !== 'cli') {
    // If running via web, check for secret key
    $secretKey = 'YOUR_SECRET_KEY_HERE'; // Change this!
    if (!isset($_GET['key']) || $_GET['key'] !== $secretKey) {
        http_response_code(403);
        die('Unauthorized');
    }
}

$db = new Database();
$attendance = new Attendance();
$now = new DateTime();
$currentDate = $now->format('Y-m-d');
$currentTime = $now->format('H:i:s');
$currentDayOfWeek = $now->format('l');

echo "=== RFID Attendance Cron Job Started at " . $now->format('Y-m-d H:i:s') . " ===\n";

/**
 * TASK 1: Create scheduled sessions for today (NO QR CODE GENERATION)
 * This creates sessions 5 minutes before they should open
 */
$sql = "SELECT cs.*, c.class_id 
        FROM class_schedules cs
        JOIN classes c ON cs.class_id = c.class_id
        WHERE c.status = 'active'
        AND cs.day_of_week = ?
        AND NOT EXISTS (
            SELECT 1 FROM attendance_sessions asess
            WHERE asess.class_id = c.class_id
            AND asess.schedule_id = cs.schedule_id
            AND asess.session_date = ?
        )";

$schedules = $db->all($sql, [$currentDayOfWeek, $currentDate]);

foreach ($schedules as $schedule) {
    $sessionStart = new DateTime($currentDate . ' ' . $schedule['start_time']);
    $createTime = clone $sessionStart;
    $createTime->modify('-' . SESSION_OPEN_BEFORE_MINUTES . ' minutes');
    
    // Only create if we're past the create time but session hasn't been created yet
    if ($now >= $createTime) {
        $sessionId = $attendance->createSession(
            $schedule['class_id'],
            $schedule['schedule_id'],
            $currentDate,
            $schedule['start_time'],
            $schedule['end_time']
        );
        
        if ($sessionId) {
            echo "✓ Created session #{$sessionId} for class {$schedule['class_id']} at {$schedule['start_time']}\n";
        }
    }
}

/**
 * TASK 2: Auto-open scheduled sessions
 * Open sessions that are scheduled to start in the next 5 minutes
 */
$openTime = clone $now;
$openTime->modify('+' . SESSION_OPEN_BEFORE_MINUTES . ' minutes');

$sql = "SELECT * FROM attendance_sessions 
        WHERE status = 'scheduled'
        AND session_date = ?
        AND start_time <= ?";

$sessionsToOpen = $db->all($sql, [$currentDate, $openTime->format('H:i:s')]);

foreach ($sessionsToOpen as $session) {
    if ($attendance->openSession($session['session_id'])) {
        echo "✓ Opened session #{$session['session_id']} - RFID reader is now active\n";
    }
}

/**
 * TASK 3: Auto-close open sessions
 * Close sessions where end_time has passed
 */
$sql = "SELECT * FROM attendance_sessions 
        WHERE status = 'open'
        AND (
            session_date < ? OR 
            (session_date = ? AND end_time <= ?)
        )";

$sessionsToClose = $db->all($sql, [$currentDate, $currentDate, $currentTime]);

foreach ($sessionsToClose as $session) {
    if ($attendance->closeSession($session['session_id'])) {
        echo "✓ Closed session #{$session['session_id']} and marked absent students\n";
    }
}

/**
 * TASK 4: Clean up old scheduled sessions that were missed
 * Cancel sessions that were scheduled but never opened (e.g., class was cancelled)
 */
$cleanupDate = clone $now;
$cleanupDate->modify('-1 day');

$sql = "UPDATE attendance_sessions 
        SET status = 'cancelled', cancelled_at = NOW()
        WHERE status = 'scheduled'
        AND session_date < ?";

$cleaned = $db->query($sql, [$cleanupDate->format('Y-m-d')]);

if ($cleaned) {
    echo "✓ Cleaned up old scheduled sessions\n";
}

echo "=== Cron Job Completed ===\n";

// Optional: Log cron execution
$logFile = __DIR__ . '/logs/cron_attendance.log';
if (!file_exists(dirname($logFile))) {
    mkdir(dirname($logFile), 0755, true);
}

file_put_contents(
    $logFile,
    "[" . $now->format('Y-m-d H:i:s') . "] RFID Cron executed successfully\n",
    FILE_APPEND
);
?>