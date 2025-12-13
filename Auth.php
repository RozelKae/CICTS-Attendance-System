<?php
/**
 * Authentication Class
 * Handles user login, logout, and session management
 */

require_once 'config.php';
require_once 'Database.php';

class Auth {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        $sql = "SELECT u.*, 
                CASE 
                    WHEN u.user_type = 'student' THEN s.student_id
                    WHEN u.user_type = 'professor' THEN p.professor_id
                    ELSE NULL
                END as role_id,
                CASE 
                    WHEN u.user_type = 'student' THEN s.student_number
                    WHEN u.user_type = 'professor' THEN p.employee_number
                    ELSE NULL
                END as identifier
                FROM users u
                LEFT JOIN students s ON u.user_id = s.user_id
                LEFT JOIN professors p ON u.user_id = p.user_id
                WHERE u.username = ? AND u.status = 'active'";
        
        $user = $this->db->single($sql, [$username]);
        
        if ($user && password_verify($password, $user['password'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['user_type'] = $user['user_type'];
            $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
            $_SESSION['role_id'] = $user['role_id'];
            $_SESSION['identifier'] = $user['identifier'];
            $_SESSION['logged_in'] = true;
            $_SESSION['last_activity'] = time();
            
            // Log the login
            $this->logAudit($user['user_id'], 'login', 'users', $user['user_id']);
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logAudit($_SESSION['user_id'], 'logout', 'users', $_SESSION['user_id']);
        }
        
        session_unset();
        session_destroy();
        return true;
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
            return false;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Check user type
     */
    public function getUserType() {
        return $_SESSION['user_type'] ?? null;
    }
    
    /**
     * Require login
     */
    public function requireLogin() {
        // Add cache control headers
        header("Cache-Control: no-cache, no-store, must-revalidate");
        header("Pragma: no-cache");
        header("Expires: 0");
        
        if (!$this->isLoggedIn()) {
            header('Location: login.php');
            exit;
        }
    }
    
    /**
     * Require specific user type
     */
    public function requireRole($allowedRoles) {
        $this->requireLogin();
        
        if (!in_array($_SESSION['user_type'], $allowedRoles)) {
            header('Location: unauthorized.php');
            exit;
        }
    }
    
    /**
     * Check if user can edit (professor or secretary)
     */
    public function canEdit() {
        return in_array($_SESSION['user_type'] ?? '', ['professor', 'secretary']);
    }
    
    /**
     * Get current user ID
     */
    public function getUserId() {
        return $_SESSION['user_id'] ?? null;
    }
    
    /**
     * Get current role ID (student_id or professor_id)
     */
    public function getRoleId() {
        return $_SESSION['role_id'] ?? null;
    }
    
    /**
     * Register new user
     */
    public function register($data) {
        // Validate required fields
        $required = ['username', 'password', 'email', 'first_name', 'last_name', 'user_type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Field {$field} is required"];
            }
        }
        
        // Validate password
        if (strlen($data['password']) < 8) {
            return ['success' => false, 'message' => 'Password must be at least 8 characters'];
        }
        
        // Check if username exists
        $check = $this->db->single("SELECT user_id FROM users WHERE username = ?", [$data['username']]);
        if ($check) {
            return ['success' => false, 'message' => 'Username already exists'];
        }
        
        // Check if email exists
        $check = $this->db->single("SELECT user_id FROM users WHERE email = ?", [$data['email']]);
        if ($check) {
            return ['success' => false, 'message' => 'Email already exists'];
        }
        
        // Hash password
        $hashedPassword = password_hash($data['password'], HASH_ALGO, ['cost' => HASH_COST]);
        
        // Insert user
        $sql = "INSERT INTO users (username, password, email, first_name, last_name, user_type) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($this->db->query($sql, [
            $data['username'],
            $hashedPassword,
            $data['email'],
            $data['first_name'],
            $data['last_name'],
            $data['user_type']
        ])) {
            return ['success' => true, 'message' => 'User registered successfully', 'user_id' => $this->db->lastInsertId()];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
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