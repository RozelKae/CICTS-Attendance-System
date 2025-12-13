<?php
/**
 * Professor Sidebar Navigation
 */

// Get current page for active menu item
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="sidebar-header">
        <div class="avatar"><?php echo strtoupper(substr($_SESSION['full_name'], 0, 1)); ?></div>
        <h3><?php echo htmlspecialchars($_SESSION['full_name']); ?></h3>
        <p><?php echo htmlspecialchars($_SESSION['identifier'] ?? 'Professor'); ?></p>
        <p class="user-role">Professor</p>
    </div>
    
    <nav class="nav-menu">
        <a href="professor_dashboard.php" class="<?php echo $currentPage == 'professor_dashboard.php' ? 'active' : ''; ?>">
            📊 Dashboard
        </a>
        <a href="professor_classes.php" class="<?php echo $currentPage == 'professor_classes.php' ? 'active' : ''; ?>">
            📚 My Classes
        </a>
        <a href="professor_attendance.php" class="<?php echo $currentPage == 'professor_attendance.php' ? 'active' : ''; ?>">
            ✓ Attendance Records
        </a>
        <a href="professor_excuse.php" class="<?php echo $currentPage == 'professor_excuse.php' ? 'active' : ''; ?>">
            📝 Excuse Letters
        </a>
        <a href="professor_reports.php" class="<?php echo $currentPage == 'professor_reports.php' ? 'active' : ''; ?>">
            📈 Reports
        </a>
        <a href="logout.php" style="margin-top: 20px; border-top: 1px solid rgba(255,255,255,0.2); padding-top: 15px;">
            🚪 Logout
        </a>
    </nav>
</aside>

<style>
.sidebar {
    width: 280px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 30px 20px;
    position: fixed;
    height: 100vh;
    overflow-y: auto;
    left: 0;
    top: 0;
}

.sidebar-header {
    text-align: center;
    margin-bottom: 30px;
    padding-bottom: 20px;
    border-bottom: 1px solid rgba(255,255,255,0.2);
}

.avatar {
    width: 80px;
    height: 80px;
    background: rgba(255,255,255,0.3);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: bold;
    margin: 0 auto 15px;
}

.sidebar-header h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.sidebar-header p {
    font-size: 13px;
    opacity: 0.9;
}

.user-role {
    font-size: 11px !important;
    text-transform: uppercase;
    letter-spacing: 1px;
    margin-top: 5px !important;
}

.nav-menu a {
    display: block;
    padding: 12px 15px;
    color: white;
    text-decoration: none;
    border-radius: 8px;
    margin-bottom: 5px;
    transition: background 0.3s;
}

.nav-menu a:hover, .nav-menu a.active {
    background: rgba(255,255,255,0.2);
}

.main-content {
    margin-left: 280px;
    padding: 30px;
    min-height: 100vh;
}
</style>