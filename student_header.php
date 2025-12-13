<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../Auth.php';

$auth = new Auth();
$auth->requireRole(['student']);

$fullName = $_SESSION['full_name'] ?? 'Student';
?>

<!-- Student Header -->
<header style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center;">
    <div class="logo">
        <h1 style="font-size: 20px; margin: 0;"><?php echo SITE_NAME; ?></h1>
        <p style="margin: 0; font-size: 12px;">College of Information Technology and Computer Studies</p>
    </div>

    <div class="user-info" style="display: flex; align-items: center; gap: 20px;">
        <span>Welcome, <?php echo htmlspecialchars($fullName); ?></span>
        <a href="logout.php" style="background: #fff; color: #667eea; padding: 8px 15px; border-radius: 8px; text-decoration: none; font-weight: 500;">Logout</a>
    </div>
</header>

<!-- Optional Page Container -->
<div style="padding: 20px;">
