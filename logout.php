<?php
/**
 * Logout Handler
 * Destroys session completely and prevents browser cache
 */

require_once 'config.php';
require_once 'Auth.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

$auth = new Auth();
$auth->logout();

// Unset all session variables
$_SESSION = array();

// Destroy the session cookie
if (isset($_COOKIE[session_name()])) {
    setcookie(session_name(), '', time()-3600, '/');
}

// Destroy the session
session_destroy();

// Redirect to login with logout message
header('Location: login.php?logged_out=1');
exit;
?>