<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'Attendance.php';

$auth = new Auth();
$auth->requireRole(['professor', 'secretary']);

$attendance = new Attendance();
$sessionId = $_GET['session_id'] ?? null;
$session = null;
$error = '';

if ($sessionId) {
    $session = $attendance->getSession($sessionId);
    
    if (!$session) {
        $error = 'Session not found';
    } elseif ($session['status'] !== 'open') {
        $error = 'This session is not currently open for attendance';
    }
}

// Get current attendance count
$counts = $attendance->getSessionCount($sessionId);
$recentScans = $attendance->getSessionAttendance($sessionId);
$recentScans = array_slice($recentScans, 0, 10); // Last 10 scans
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RFID Attendance Reader - <?php echo SITE_NAME; ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 900px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .session-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
        }
        
        .info-box {
            background: rgba(255,255,255,0.2);
            padding: 15px;
            border-radius: 10px;
            text-align: center;
        }
        
        .info-label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }
        
        .info-value {
            font-size: 16px;
            font-weight: 600;
        }
        
        .status-indicator {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #28a745;
            color: white;
            padding: 10px 20px;
            border-radius: 30px;
            font-weight: 600;
            margin-bottom: 20px;
        }
        
        .status-dot {
            width: 10px;
            height: 10px;
            background: white;
            border-radius: 50%;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }
        
        .rfid-input-section {
            padding: 30px;
            background: #f8f9fa;
            text-align: center;
        }
        
        .rfid-input-section h2 {
            color: #667eea;
            margin-bottom: 20px;
        }
        
        .waiting-indicator {
            font-size: 18px;
            color: #666;
            margin-bottom: 20px;
        }
        
        .pulse-icon {
            animation: pulse 1.5s infinite;
            font-size: 24px;
        }
        
        .counter-section {
            padding: 30px;
            background: white;
            border-top: 3px solid #667eea;
        }
        
        .counter-display {
            display: flex;
            justify-content: center;
            gap: 50px;
            margin-bottom: 30px;
        }
        
        .counter-box {
            text-align: center;
        }
        
        .counter-number {
            font-size: 48px;
            font-weight: bold;
            color: #667eea;
        }
        
        .counter-label {
            font-size: 14px;
            color: #666;
            margin-top: 5px;
        }
        
        .progress-bar {
            width: 100%;
            height: 30px;
            background: #e0e0e0;
            border-radius: 15px;
            overflow: hidden;
            position: relative;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
        }
        
        .recent-scans {
            padding: 30px;
        }
        
        .recent-scans h3 {
            color: #333;
            margin-bottom: 20px;
            font-size: 20px;
        }
        
        .scan-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .scan-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 10px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .scan-icon {
            font-size: 32px;
        }
        
        .scan-details {
            flex: 1;
        }
        
        .scan-name {
            font-weight: 600;
            color: #333;
            font-size: 16px;
        }
        
        .scan-meta {
            font-size: 13px;
            color: #666;
            margin-top: 3px;
        }
        
        .scan-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .badge-present {
            background: #d4edda;
            color: #155724;
        }
        
        .badge-late {
            background: #fff3cd;
            color: #856404;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #999;
        }
        
        .error-box {
            background: #f8d7da;
            color: #721c24;
            padding: 20px;
            border-radius: 10px;
            margin: 20px;
        }
        
        .btn-close {
            background: #6c757d;
            color: white;
            border: none;
            padding: 12px 30px;
            border-radius: 8px;
            font-size: 14px;
            cursor: pointer;
            margin-top: 20px;
        }
        
        .btn-close:hover {
            background: #5a6268;
        }
        
        #notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 20px 30px;
            border-radius: 10px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.3);
            z-index: 1000;
            display: none;
            animation: slideInRight 0.3s ease-out;
        }
        
        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(100px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .notification-success {
            background: #d4edda;
            color: #155724;
            border: 2px solid #28a745;
        }
        
        .notification-error {
            background: #f8d7da;
            color: #721c24;
            border: 2px solid #dc3545;
        }
    </style>
    <script>
        let rfidBuffer = '';
        let rfidTimeout = null;
        let isProcessing = false;
        
        function showNotification(message, type) {
            const notif = document.getElementById('notification');
            notif.textContent = message;
            notif.className = 'notification-' + type;
            notif.style.display = 'block';
            
            setTimeout(() => {
                notif.style.display = 'none';
            }, 3000);
        }
        
        function processRFID(rfidUid) {
            if (isProcessing || !rfidUid || rfidUid.length < 8) return;
            
            isProcessing = true;
            
            // Send AJAX request to record attendance
            fetch('api.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=record_attendance_rfid&session_id=<?php echo $sessionId; ?>&rfid_uid=' + encodeURIComponent(rfidUid)
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification('✓ ' + data.student.full_name + ' - ' + data.status.toUpperCase(), 'success');
                    // Reload page to update counts
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification('✗ ' + data.message, 'error');
                }
                isProcessing = false;
            })
            .catch(error => {
                showNotification('✗ Error recording attendance', 'error');
                isProcessing = false;
            });
        }
        
        document.addEventListener('DOMContentLoaded', function() {
            const rfidInput = document.getElementById('rfid_input');
            
            if (rfidInput) {
                rfidInput.focus();
                
                // Method 1: Enter key detection
                rfidInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        const uid = rfidInput.value.trim().toUpperCase();
                        if (uid.length >= 8) {
                            processRFID(uid);
                            rfidInput.value = '';
                        }
                    }
                });
                
                // Method 2: Timeout-based detection
                rfidInput.addEventListener('input', function(e) {
                    clearTimeout(rfidTimeout);
                    
                    rfidTimeout = setTimeout(function() {
                        const uid = rfidInput.value.trim().toUpperCase();
                        if (uid.length >= 8) {
                            processRFID(uid);
                            rfidInput.value = '';
                        }
                    }, 500);
                });
                
                // Keep focus on input
                setInterval(() => {
                    if (document.activeElement !== rfidInput) {
                        rfidInput.focus();
                    }
                }, 1000);
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <?php if ($error): ?>
            <div class="error-box">
                <strong>Error:</strong> <?php echo htmlspecialchars($error); ?>
                <br><br>
                <button class="btn-close" onclick="window.close()">Close Window</button>
            </div>
        <?php elseif ($session): ?>
            <div class="header">
                <div class="status-indicator">
                    <span class="status-dot"></span>
                    Session Active - Ready for RFID Taps
                </div>
                
                <h1><?php echo htmlspecialchars($session['course_code']); ?> - <?php echo htmlspecialchars($session['course_name']); ?></h1>
                
                <div class="session-info">
                    <div class="info-box">
                        <div class="info-label">Section</div>
                        <div class="info-value"><?php echo htmlspecialchars($session['section']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Room</div>
                        <div class="info-value"><?php echo htmlspecialchars($session['room']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Date</div>
                        <div class="info-value"><?php echo htmlspecialchars($session['formatted_date']); ?></div>
                    </div>
                    <div class="info-box">
                        <div class="info-label">Time</div>
                        <div class="info-value">
                            <?php echo htmlspecialchars($session['formatted_start']); ?> - 
                            <?php echo htmlspecialchars($session['formatted_end']); ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="rfid-input-section">
                <h2>🎴 Waiting for RFID Card...</h2>
                <div class="waiting-indicator">
                    <span class="pulse-icon">●</span> Students should tap their cards now
                </div>
                <!-- Hidden input that receives RFID data -->
                <input type="text" 
                       id="rfid_input" 
                       style="position: absolute; left: -9999px;" 
                       autocomplete="off">
            </div>
            
            <div class="counter-section">
                <div class="counter-display">
                    <div class="counter-box">
                        <div class="counter-number"><?php echo $counts['scanned_count']; ?></div>
                        <div class="counter-label">Scanned</div>
                    </div>
                    <div class="counter-box">
                        <div class="counter-number"><?php echo $counts['total_students']; ?></div>
                        <div class="counter-label">Total Students</div>
                    </div>
                    <div class="counter-box">
                        <div class="counter-number">
                            <?php echo max(0, $counts['total_students'] - $counts['scanned_count']); ?>
                        </div>
                        <div class="counter-label">Remaining</div>
                    </div>
                </div>
                
                <div class="progress-bar">
                    <?php 
                    $percentage = $counts['total_students'] > 0 
                        ? round(($counts['scanned_count'] / $counts['total_students']) * 100) 
                        : 0;
                    ?>
                    <div class="progress-fill" style="width: <?php echo $percentage; ?>%">
                        <?php echo $percentage; ?>%
                    </div>
                </div>
            </div>
            
            <div class="recent-scans">
                <h3>Recent Scans</h3>
                
                <?php if (empty($recentScans)): ?>
                    <div class="empty-state">
                        <p>No scans yet. Waiting for first student...</p>
                    </div>
                <?php else: ?>
                    <div class="scan-list">
                        <?php foreach ($recentScans as $scan): ?>
                            <div class="scan-item">
                                <div class="scan-icon">
                                    <?php echo $scan['status'] === 'present' ? '✅' : '⚠️'; ?>
                                </div>
                                <div class="scan-details">
                                    <div class="scan-name">
                                        <?php echo htmlspecialchars($scan['student_name']); ?>
                                    </div>
                                    <div class="scan-meta">
                                        <?php echo htmlspecialchars($scan['student_number']); ?> • 
                                        <?php echo htmlspecialchars($scan['formatted_time'] ?? 'N/A'); ?>
                                    </div>
                                </div>
                                <span class="scan-badge badge-<?php echo $scan['status']; ?>">
                                    <?php echo strtoupper($scan['status']); ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div style="padding: 20px; text-align: center; background: #f8f9fa; border-top: 1px solid #e0e0e0;">
                <button class="btn-close" onclick="window.close()">Close Reader</button>
                <p style="margin-top: 10px; font-size: 13px; color: #666;">
                    * Page will auto-refresh after each scan *
                </p>
            </div>
        <?php else: ?>
            <div class="error-box">
                No session selected. Please select a session from your dashboard.
                <br><br>
                <button class="btn-close" onclick="window.close()">Close Window</button>
            </div>
        <?php endif; ?>
    </div>
    
    <div id="notification"></div>
</body>
</html>