<?php
/**
 * Authentication class
 */
class Auth {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Login user
     */
    public function login($username, $password) {
        // Check login attempts
        if ($this->isLockedOut($username)) {
            throw new Exception("Account temporarily locked due to too many failed attempts");
        }
        
        $user = $this->db->fetch(
            "SELECT * FROM users WHERE username = ? AND status = 'active'",
            [$username]
        );
        
        if (!$user || !password_verify($password, $user['password'])) {
            $this->recordFailedAttempt($username);
            throw new Exception("Invalid username or password");
        }
        
        // Clear failed attempts
        $this->clearFailedAttempts($username);
        
        // Update last login
        $this->db->query(
            "UPDATE users SET last_login = NOW() WHERE id = ?",
            [$user['id']]
        );
        
        // Start session
        session_start();
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        
        // Log activity
        $this->logActivity($user['id'], 'login', 'User logged in');
        
        return $user;
    }
    
    /**
     * Logout user
     */
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $this->logActivity($_SESSION['user_id'], 'logout', 'User logged out');
        }
        
        session_start();
        session_destroy();
    }
    
    /**
     * Check if user is logged in
     */
    public function isLoggedIn() {
        session_start();
        
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['last_activity'])) {
            return false;
        }
        
        // Check session timeout
        $config = include __DIR__ . '/../config/app.php';
        if (time() - $_SESSION['last_activity'] > $config['session_timeout']) {
            $this->logout();
            return false;
        }
        
        $_SESSION['last_activity'] = time();
        return true;
    }
    
    /**
     * Get current user
     */
    public function getCurrentUser() {
        if (!$this->isLoggedIn()) {
            return null;
        }
        
        return $this->db->fetch(
            "SELECT * FROM users WHERE id = ?",
            [$_SESSION['user_id']]
        );
    }
    
    /**
     * Check if user has permission
     */
    public function hasPermission($role) {
        if (!$this->isLoggedIn()) {
            return false;
        }
        
        $userRole = $_SESSION['role'];
        
        if ($userRole === 'admin') {
            return true;
        }
        
        if ($userRole === 'reseller' && in_array($role, ['reseller', 'user'])) {
            return true;
        }
        
        if ($userRole === 'user' && $role === 'user') {
            return true;
        }
        
        return false;
    }
    
    /**
     * Record failed login attempt
     */
    private function recordFailedAttempt($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        // Clean up old attempts (older than 1 hour)
        $this->db->query(
            "DELETE FROM activity_logs WHERE action = 'failed_login' AND ip_address = ? AND created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        
        // Record new attempt
        $this->db->query(
            "INSERT INTO activity_logs (action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, NOW())",
            ['failed_login', "Failed login attempt for username: $username", $ip, $_SERVER['HTTP_USER_AGENT'] ?? '']
        );
    }
    
    /**
     * Check if IP is locked out
     */
    private function isLockedOut($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $config = include __DIR__ . '/../config/app.php';
        
        $attempts = $this->db->fetch(
            "SELECT COUNT(*) as count FROM activity_logs WHERE action = 'failed_login' AND ip_address = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)",
            [$ip]
        );
        
        return $attempts['count'] >= $config['max_login_attempts'];
    }
    
    /**
     * Clear failed attempts
     */
    private function clearFailedAttempts($username) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        
        $this->db->query(
            "DELETE FROM activity_logs WHERE action = 'failed_login' AND ip_address = ?",
            [$ip]
        );
    }
    
    /**
     * Log user activity
     */
    private function logActivity($userId, $action, $description) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        $this->db->query(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
            [$userId, $action, $description, $ip, $userAgent]
        );
    }
}