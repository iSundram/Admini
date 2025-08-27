<?php
/**
 * Admini Control Panel API
 * RESTful API for managing hosting accounts
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/Database.php';

class AdminiAPI {
    private $db;
    private $apiKey;
    private $user;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        
        if (!$this->authenticateApiKey()) {
            $this->sendError('Invalid API key', 401);
        }
    }
    
    /**
     * Authenticate API key
     */
    private function authenticateApiKey() {
        if (empty($this->apiKey)) {
            return false;
        }
        
        // For demo purposes, we'll check against a simple hash
        // In production, store API keys in database with proper hashing
        $validApiKey = hash('sha256', 'admini_api_key_2024');
        
        if ($this->apiKey === $validApiKey) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Route API requests
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $pathParts = explode('/', trim($path, '/'));
        
        // Remove 'api' from path
        if ($pathParts[0] === 'api') {
            array_shift($pathParts);
        }
        
        $resource = $pathParts[0] ?? '';
        $id = $pathParts[1] ?? null;
        
        try {
            switch ($resource) {
                case 'users':
                    $this->handleUsers($method, $id);
                    break;
                    
                case 'domains':
                    $this->handleDomains($method, $id);
                    break;
                    
                case 'email':
                    $this->handleEmail($method, $id);
                    break;
                    
                case 'databases':
                    $this->handleDatabases($method, $id);
                    break;
                    
                case 'statistics':
                    $this->handleStatistics($method, $id);
                    break;
                    
                default:
                    $this->sendError('Resource not found', 404);
            }
        } catch (Exception $e) {
            $this->sendError($e->getMessage(), 500);
        }
    }
    
    /**
     * Handle users API endpoints
     */
    private function handleUsers($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getUserById($id);
                } else {
                    $this->getAllUsers();
                }
                break;
                
            case 'POST':
                $this->createUser();
                break;
                
            case 'PUT':
                if ($id) {
                    $this->updateUser($id);
                } else {
                    $this->sendError('User ID required', 400);
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteUser($id);
                } else {
                    $this->sendError('User ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle domains API endpoints
     */
    private function handleDomains($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getDomainById($id);
                } else {
                    $this->getAllDomains();
                }
                break;
                
            case 'POST':
                $this->createDomain();
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteDomain($id);
                } else {
                    $this->sendError('Domain ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle email API endpoints
     */
    private function handleEmail($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getEmailById($id);
                } else {
                    $this->getAllEmailAccounts();
                }
                break;
                
            case 'POST':
                $this->createEmailAccount();
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteEmailAccount($id);
                } else {
                    $this->sendError('Email ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle databases API endpoints
     */
    private function handleDatabases($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getDatabaseById($id);
                } else {
                    $this->getAllDatabases();
                }
                break;
                
            case 'POST':
                $this->createDatabase();
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteDatabase($id);
                } else {
                    $this->sendError('Database ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle statistics API endpoints
     */
    private function handleStatistics($method, $id) {
        switch ($method) {
            case 'GET':
                $this->getStatistics();
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Get all users
     */
    private function getAllUsers() {
        $users = $this->db->fetchAll(
            "SELECT id, username, email, role, status, created_at FROM users ORDER BY username"
        );
        $this->sendSuccess($users);
    }
    
    /**
     * Get user by ID
     */
    private function getUserById($id) {
        $user = $this->db->fetch(
            "SELECT id, username, email, role, status, disk_quota, disk_used, bandwidth_quota, bandwidth_used, created_at FROM users WHERE id = ?",
            [$id]
        );
        
        if (!$user) {
            $this->sendError('User not found', 404);
        }
        
        $this->sendSuccess($user);
    }
    
    /**
     * Create new user
     */
    private function createUser() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $username = $data['username'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $role = $data['role'] ?? 'user';
        
        if (empty($username) || empty($email) || empty($password)) {
            $this->sendError('Username, email, and password are required', 400);
        }
        
        // Check if username or email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM users WHERE username = ? OR email = ?",
            [$username, $email]
        );
        
        if ($existing) {
            $this->sendError('Username or email already exists', 409);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->query(
            "INSERT INTO users (username, email, password, role, status, created_at) VALUES (?, ?, ?, ?, 'active', NOW())",
            [$username, $email, $hashedPassword, $role]
        );
        
        $userId = $this->db->lastInsertId();
        
        $this->sendSuccess(['id' => $userId, 'message' => 'User created successfully'], 201);
    }
    
    /**
     * Update user
     */
    private function updateUser($id) {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $user = $this->db->fetch("SELECT id FROM users WHERE id = ?", [$id]);
        if (!$user) {
            $this->sendError('User not found', 404);
        }
        
        $updates = [];
        $params = [];
        
        if (isset($data['email'])) {
            $updates[] = "email = ?";
            $params[] = $data['email'];
        }
        
        if (isset($data['status'])) {
            $updates[] = "status = ?";
            $params[] = $data['status'];
        }
        
        if (isset($data['disk_quota'])) {
            $updates[] = "disk_quota = ?";
            $params[] = $data['disk_quota'];
        }
        
        if (isset($data['bandwidth_quota'])) {
            $updates[] = "bandwidth_quota = ?";
            $params[] = $data['bandwidth_quota'];
        }
        
        if (empty($updates)) {
            $this->sendError('No fields to update', 400);
        }
        
        $params[] = $id;
        $this->db->query(
            "UPDATE users SET " . implode(', ', $updates) . ", updated_at = NOW() WHERE id = ?",
            $params
        );
        
        $this->sendSuccess(['message' => 'User updated successfully']);
    }
    
    /**
     * Delete user
     */
    private function deleteUser($id) {
        $user = $this->db->fetch("SELECT id FROM users WHERE id = ? AND role != 'admin'", [$id]);
        if (!$user) {
            $this->sendError('User not found or cannot delete admin', 404);
        }
        
        $this->db->query("DELETE FROM users WHERE id = ?", [$id]);
        
        $this->sendSuccess(['message' => 'User deleted successfully']);
    }
    
    /**
     * Get all domains
     */
    private function getAllDomains() {
        $domains = $this->db->fetchAll(
            "SELECT d.*, u.username FROM domains d 
             JOIN users u ON d.user_id = u.id 
             ORDER BY d.domain_name"
        );
        $this->sendSuccess($domains);
    }
    
    /**
     * Get domain by ID
     */
    private function getDomainById($id) {
        $domain = $this->db->fetch(
            "SELECT d.*, u.username FROM domains d 
             JOIN users u ON d.user_id = u.id 
             WHERE d.id = ?",
            [$id]
        );
        
        if (!$domain) {
            $this->sendError('Domain not found', 404);
        }
        
        $this->sendSuccess($domain);
    }
    
    /**
     * Create domain
     */
    private function createDomain() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $userId = $data['user_id'] ?? '';
        $domainName = $data['domain_name'] ?? '';
        $documentRoot = $data['document_root'] ?? '/public_html';
        
        if (empty($userId) || empty($domainName)) {
            $this->sendError('User ID and domain name are required', 400);
        }
        
        // Check if domain already exists
        $existing = $this->db->fetch(
            "SELECT id FROM domains WHERE domain_name = ?",
            [$domainName]
        );
        
        if ($existing) {
            $this->sendError('Domain already exists', 409);
        }
        
        $this->db->query(
            "INSERT INTO domains (user_id, domain_name, document_root, status, created_at) VALUES (?, ?, ?, 'active', NOW())",
            [$userId, $domainName, $documentRoot]
        );
        
        $domainId = $this->db->lastInsertId();
        
        $this->sendSuccess(['id' => $domainId, 'message' => 'Domain created successfully'], 201);
    }
    
    /**
     * Delete domain
     */
    private function deleteDomain($id) {
        $domain = $this->db->fetch("SELECT id FROM domains WHERE id = ?", [$id]);
        if (!$domain) {
            $this->sendError('Domain not found', 404);
        }
        
        $this->db->query("DELETE FROM domains WHERE id = ?", [$id]);
        
        $this->sendSuccess(['message' => 'Domain deleted successfully']);
    }
    
    /**
     * Get all email accounts
     */
    private function getAllEmailAccounts() {
        $emails = $this->db->fetchAll(
            "SELECT ea.id, ea.email, ea.quota, ea.quota_used, ea.status, ea.created_at, d.domain_name, u.username 
             FROM email_accounts ea 
             JOIN domains d ON ea.domain_id = d.id 
             JOIN users u ON ea.user_id = u.id 
             ORDER BY ea.email"
        );
        $this->sendSuccess($emails);
    }
    
    /**
     * Get email by ID
     */
    private function getEmailById($id) {
        $email = $this->db->fetch(
            "SELECT ea.*, d.domain_name, u.username 
             FROM email_accounts ea 
             JOIN domains d ON ea.domain_id = d.id 
             JOIN users u ON ea.user_id = u.id 
             WHERE ea.id = ?",
            [$id]
        );
        
        if (!$email) {
            $this->sendError('Email account not found', 404);
        }
        
        $this->sendSuccess($email);
    }
    
    /**
     * Create email account
     */
    private function createEmailAccount() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $userId = $data['user_id'] ?? '';
        $domainId = $data['domain_id'] ?? '';
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $quota = $data['quota'] ?? 1024;
        
        if (empty($userId) || empty($domainId) || empty($email) || empty($password)) {
            $this->sendError('User ID, domain ID, email, and password are required', 400);
        }
        
        // Check if email already exists
        $existing = $this->db->fetch(
            "SELECT id FROM email_accounts WHERE email = ?",
            [$email]
        );
        
        if ($existing) {
            $this->sendError('Email account already exists', 409);
        }
        
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        $this->db->query(
            "INSERT INTO email_accounts (user_id, domain_id, email, password, quota, status, created_at) VALUES (?, ?, ?, ?, ?, 'active', NOW())",
            [$userId, $domainId, $email, $hashedPassword, $quota]
        );
        
        $emailId = $this->db->lastInsertId();
        
        $this->sendSuccess(['id' => $emailId, 'message' => 'Email account created successfully'], 201);
    }
    
    /**
     * Delete email account
     */
    private function deleteEmailAccount($id) {
        $email = $this->db->fetch("SELECT id FROM email_accounts WHERE id = ?", [$id]);
        if (!$email) {
            $this->sendError('Email account not found', 404);
        }
        
        $this->db->query("DELETE FROM email_accounts WHERE id = ?", [$id]);
        
        $this->sendSuccess(['message' => 'Email account deleted successfully']);
    }
    
    /**
     * Get all databases
     */
    private function getAllDatabases() {
        $databases = $this->db->fetchAll(
            "SELECT db.*, u.username FROM databases db 
             JOIN users u ON db.user_id = u.id 
             ORDER BY db.database_name"
        );
        $this->sendSuccess($databases);
    }
    
    /**
     * Get database by ID
     */
    private function getDatabaseById($id) {
        $database = $this->db->fetch(
            "SELECT db.*, u.username FROM databases db 
             JOIN users u ON db.user_id = u.id 
             WHERE db.id = ?",
            [$id]
        );
        
        if (!$database) {
            $this->sendError('Database not found', 404);
        }
        
        $this->sendSuccess($database);
    }
    
    /**
     * Create database
     */
    private function createDatabase() {
        $data = json_decode(file_get_contents('php://input'), true);
        
        $userId = $data['user_id'] ?? '';
        $databaseName = $data['database_name'] ?? '';
        $databaseType = $data['database_type'] ?? 'mysql';
        
        if (empty($userId) || empty($databaseName)) {
            $this->sendError('User ID and database name are required', 400);
        }
        
        // Check if database already exists
        $existing = $this->db->fetch(
            "SELECT id FROM databases WHERE database_name = ?",
            [$databaseName]
        );
        
        if ($existing) {
            $this->sendError('Database already exists', 409);
        }
        
        $this->db->query(
            "INSERT INTO databases (user_id, database_name, database_type, status, created_at) VALUES (?, ?, ?, 'active', NOW())",
            [$userId, $databaseName, $databaseType]
        );
        
        $databaseId = $this->db->lastInsertId();
        
        $this->sendSuccess(['id' => $databaseId, 'message' => 'Database created successfully'], 201);
    }
    
    /**
     * Delete database
     */
    private function deleteDatabase($id) {
        $database = $this->db->fetch("SELECT id FROM databases WHERE id = ?", [$id]);
        if (!$database) {
            $this->sendError('Database not found', 404);
        }
        
        $this->db->query("DELETE FROM databases WHERE id = ?", [$id]);
        
        $this->sendSuccess(['message' => 'Database deleted successfully']);
    }
    
    /**
     * Get statistics
     */
    private function getStatistics() {
        $stats = [
            'users' => [
                'total' => $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")['count'],
                'active' => $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'active'")['count'],
                'suspended' => $this->db->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin' AND status = 'suspended'")['count'],
            ],
            'domains' => [
                'total' => $this->db->fetch("SELECT COUNT(*) as count FROM domains")['count'],
                'active' => $this->db->fetch("SELECT COUNT(*) as count FROM domains WHERE status = 'active'")['count'],
            ],
            'email_accounts' => [
                'total' => $this->db->fetch("SELECT COUNT(*) as count FROM email_accounts")['count'],
                'active' => $this->db->fetch("SELECT COUNT(*) as count FROM email_accounts WHERE status = 'active'")['count'],
            ],
            'databases' => [
                'total' => $this->db->fetch("SELECT COUNT(*) as count FROM databases")['count'],
                'mysql' => $this->db->fetch("SELECT COUNT(*) as count FROM databases WHERE database_type = 'mysql'")['count'],
                'postgresql' => $this->db->fetch("SELECT COUNT(*) as count FROM databases WHERE database_type = 'postgresql'")['count'],
            ],
            'disk_usage' => [
                'total_allocated' => $this->db->fetch("SELECT SUM(disk_quota) as total FROM users WHERE role != 'admin'")['total'] ?? 0,
                'total_used' => $this->db->fetch("SELECT SUM(disk_used) as total FROM users WHERE role != 'admin'")['total'] ?? 0,
            ],
            'bandwidth_usage' => [
                'total_allocated' => $this->db->fetch("SELECT SUM(bandwidth_quota) as total FROM users WHERE role != 'admin'")['total'] ?? 0,
                'total_used' => $this->db->fetch("SELECT SUM(bandwidth_used) as total FROM users WHERE role != 'admin'")['total'] ?? 0,
            ],
        ];
        
        $this->sendSuccess($stats);
    }
    
    /**
     * Send success response
     */
    private function sendSuccess($data, $statusCode = 200) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => true,
            'data' => $data,
            'timestamp' => date('c')
        ]);
        exit;
    }
    
    /**
     * Send error response
     */
    private function sendError($message, $statusCode = 400) {
        http_response_code($statusCode);
        echo json_encode([
            'success' => false,
            'error' => $message,
            'timestamp' => date('c')
        ]);
        exit;
    }
}

// Initialize and handle API request
try {
    $api = new AdminiAPI();
    $api->handleRequest();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'timestamp' => date('c')
    ]);
}
?>