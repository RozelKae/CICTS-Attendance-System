<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/Database.php';


echo "<h1>Database Connection Test</h1>";

try {
    $db = new Database();
    echo "<p style='color: green; font-weight: bold;'>✓ Database connection successful!</p>";
    
    // Test query
    $result = $db->single("SELECT COUNT(*) as count FROM users");
    echo "<p>✓ Found {$result['count']} users in database</p>";
    
    // Test students with RFID
    $result = $db->single("SELECT COUNT(*) as count FROM students WHERE rfid_uid IS NOT NULL");
    echo "<p>✓ Found {$result['count']} students with registered RFID cards</p>";
    
    echo "<p style='color: green;'>✓ Everything is working! You can delete this test file now.</p>";
    
} catch (Exception $e) {
    echo "<p style='color: red; font-weight: bold;'>✗ Connection failed: " . $e->getMessage() . "</p>";
    echo "<p>Check your config.php settings!</p>";
}
?>