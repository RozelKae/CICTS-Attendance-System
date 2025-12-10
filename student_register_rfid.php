<?php
require_once 'config.php';
require_once 'Auth.php';
require_once 'StudentManager.php';

$auth = new Auth();
$auth->requireRole(['student']);

$studentManager = new StudentManager();
$studentId = $auth->getRoleId();
$student = $studentManager->getStudentById($studentId);
$rfidStatus = $studentManager->getRFIDStatus($studentId);

$message = '';
$messageType = '';

// Handle RFID registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['register_rfid'])) {
    $rfidUid = strtoupper(trim($_POST['rfid_uid'] ?? ''));
    
    if (empty($rfidUid)) {
        $message = 'Please tap your RFID card or enter the UID';
        $messageType = 'error';
    } else {
        $result = $studentManager->registerRFID($studentId, $rfidUid);
        $message = $result['message'];
        $messageType = $result['success'] ? 'success' : 'error';
        
        if ($result['success']) {
            // Refresh RFID status
            $rfidStatus = $studentManager->getRFIDStatus($studentId);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register RFID Card - <?php echo SITE_NAME; ?></title>
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
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            max-width: 600px;
            width: 100%;
        }
        
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .header h1 {
            color: #667eea;
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header p {
            color: #666;
            font-size: 14px;
        }
        
        .student-info {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        
        .student-info h3 {
            color: #333;
            margin-bottom: 10px;
            font-size: 18px;
        }
        
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .info-row:last-child {
            border-bottom: none;
        }
        
        .info-label {
            font-weight: 600;
            color: #555;
        }
        
        .info-value {
            color: #333;
        }
        
        .alert {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            animation: slideIn 0.3s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        .rfid-status {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .rfid-status.registered {
            background: #d4edda;
            border: 2px solid #28a745;
        }
        
        .rfid-status.not-registered {
            background: #fff3cd;
            border: 2px solid #ffc107;
        }
        
        .rfid-status.deactivated {
            background: #f8d7da;
            border: 2px solid #dc3545;
        }
        
        .status-icon {
            font-size: 48px;
            margin-bottom: 10px;
        }
        
        .status-text {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .status-detail {
            font-size: 14px;
            color: #666;
        }
        
        .registration-form {
            background: #f8f9fa;
            padding: 30px;
            border-radius: 12px;
            margin-bottom: 20px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 600;
        }
        
        .form-group input {
            width: 100%;
            padding: 14px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 16px;
            font-family: monospace;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #667eea;
            background: #f0f7ff;
        }
        
        .input-hint {
            font-size: 13px;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 10px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s, box-shadow 0.2s;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
        }
        
        .btn-secondary {
            background: #6c757d;
            color: white;
            margin-top: 10px;
        }
        
        .btn-secondary:hover {
            background: #5a6268;
        }
        
        .instructions {
            background: #e7f3ff;
            padding: 20px;
            border-radius: 12px;
            border-left: 4px solid #667eea;
        }
        
        .instructions h3 {
            color: #667eea;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .instructions ol {
            padding-left: 20px;
        }
        
        .instructions li {
            margin-bottom: 10px;
            color: #555;
            line-height: 1.6;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .back-link a:hover {
            text-decoration: underline;
        }
        
        .waiting-indicator {
            text-align: center;
            padding: 20px;
            color: #667eea;
            font-weight: 600;
        }
        
        .pulse {
            animation: pulse 1.5s infinite;
        }
        
        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }
    </style>
    <script>
        let rfidBuffer = '';
        let rfidTimeout = null;
        
        document.addEventListener('DOMContentLoaded', function() {
            const rfidInput = document.getElementById('rfid_uid');
            
            if (rfidInput) {
                rfidInput.focus();
                
                // Detect RFID scan (Enter key or timeout method)
                rfidInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        // RFID reader sent Enter, submit form
                        if (rfidInput.value.trim().length > 0) {
                            document.getElementById('rfidForm').submit();
                        }
                    }
                });
                
                // Alternative: timeout-based detection
                rfidInput.addEventListener('input', function(e) {
                    clearTimeout(rfidTimeout);
                    
                    rfidTimeout = setTimeout(function() {
                        // If input stopped for 500ms and has content, assume RFID scan complete
                        if (rfidInput.value.trim().length >= 8) {
                            document.getElementById('rfidForm').submit();
                        }
                    }, 500);
                });
            }
        });
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🎴 Register RFID Card</h1>
            <p>Link your RFID card to your student account</p>
        </div>
        
        <div class="student-info">
            <h3><?php echo htmlspecialchars($student['full_name']); ?></h3>
            <div class="info-row">
                <span class="info-label">Student Number:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['student_number']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Program:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['program'] . ' ' . $student['year_level']); ?></span>
            </div>
            <div class="info-row">
                <span class="info-label">Section:</span>
                <span class="info-value"><?php echo htmlspecialchars($student['section'] ?? 'N/A'); ?></span>
            </div>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <?php if ($rfidStatus['rfid_status'] === 'active' && $rfidStatus['rfid_uid']): ?>
            <!-- Already Registered -->
            <div class="rfid-status registered">
                <div class="status-icon">✅</div>
                <div class="status-text">RFID Card Registered</div>
                <div class="status-detail">
                    Card UID: <strong><?php echo htmlspecialchars($rfidStatus['rfid_uid']); ?></strong><br>
                    Registered: <?php echo date('F d, Y h:i A', strtotime($rfidStatus['rfid_registered_at'])); ?>
                </div>
            </div>
            
            <div class="instructions">
                <h3>✓ You're all set!</h3>
                <p>Your RFID card is registered and active. You can now use it for attendance.</p>
                <p style="margin-top: 10px;"><strong>Lost your card?</strong> Contact the secretary to deactivate it and register a new one.</p>
            </div>
            
        <?php elseif ($rfidStatus['rfid_status'] === 'deactivated'): ?>
            <!-- Deactivated Card -->
            <div class="rfid-status deactivated">
                <div class="status-icon">⚠️</div>
                <div class="status-text">Card Deactivated</div>
                <div class="status-detail">
                    Reason: <?php echo htmlspecialchars($rfidStatus['rfid_deactivation_reason'] ?? 'N/A'); ?><br>
                    Deactivated: <?php echo date('F d, Y h:i A', strtotime($rfidStatus['rfid_deactivated_at'])); ?>
                </div>
            </div>
            
            <div class="registration-form">
                <h3 style="margin-bottom: 15px; color: #333;">Register New Card</h3>
                <form method="POST" action="" id="rfidForm">
                    <div class="form-group">
                        <label for="rfid_uid">Tap Your New RFID Card</label>
                        <input type="text" 
                               id="rfid_uid" 
                               name="rfid_uid" 
                               placeholder="Waiting for card tap..." 
                               required 
                               autofocus
                               autocomplete="off">
                        <div class="input-hint">Tap your card on the RFID reader or enter UID manually</div>
                    </div>
                    
                    <button type="submit" name="register_rfid" class="btn btn-primary">Register Card</button>
                </form>
            </div>
            
        <?php else: ?>
            <!-- Not Registered Yet -->
            <div class="rfid-status not-registered">
                <div class="status-icon">📝</div>
                <div class="status-text">No RFID Card Registered</div>
                <div class="status-detail">Register your card to use it for attendance</div>
            </div>
            
            <div class="registration-form">
                <form method="POST" action="" id="rfidForm">
                    <div class="form-group">
                        <label for="rfid_uid">Tap Your RFID Card</label>
                        <input type="text" 
                               id="rfid_uid" 
                               name="rfid_uid" 
                               placeholder="Waiting for card tap..." 
                               required 
                               autofocus
                               autocomplete="off">
                        <div class="input-hint">
                            <span class="pulse">●</span> Waiting for RFID card tap...
                        </div>
                    </div>
                    
                    <button type="submit" name="register_rfid" class="btn btn-primary">Register Card</button>
                </form>
            </div>
            
            <div class="instructions">
                <h3>📋 How to Register:</h3>
                <ol>
                    <li>Make sure the input field above is focused (cursor is blinking)</li>
                    <li>Tap your RFID card on the reader</li>
                    <li>The system will automatically detect and register your card</li>
                    <li>You'll see a confirmation message when registration is complete</li>
                </ol>
                <p style="margin-top: 15px;"><strong>Note:</strong> Each student can only register one RFID card at a time.</p>
            </div>
        <?php endif; ?>
        
        <div class="back-link">
            <a href="student_dashboard.php">← Back to Dashboard</a>
        </div>
    </div>
</body>
</html>