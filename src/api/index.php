<?php
/**
 * Admini Control Panel API - Enterprise Edition
 * Advanced RESTful API with webhooks, rate limiting, and OAuth2
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-API-Key, X-Rate-Limit');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

require_once '../includes/Database.php';
require_once '../includes/SecurityManager.php';
require_once '../includes/BackupManager.php';
require_once '../includes/MonitoringManager.php';
require_once '../includes/ApplicationManager.php';

class AdminiAPI {
    private $db;
    private $apiKey;
    private $user;
    private $securityManager;
    private $backupManager;
    private $monitoringManager;
    private $applicationManager;
    private $rateLimiter;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->securityManager = new SecurityManager();
        $this->backupManager = new BackupManager();
        $this->monitoringManager = new MonitoringManager();
        $this->applicationManager = new ApplicationManager();
        
        $this->apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        
        if (!$this->authenticateApiKey()) {
            $this->sendError('Invalid API key', 401);
        }
        
        // Check rate limits
        if (!$this->checkRateLimit()) {
            $this->sendError('Rate limit exceeded', 429);
        }
    }
    
    /**
     * Enhanced API key authentication with database lookup
     */
    private function authenticateApiKey() {
        if (empty($this->apiKey)) {
            return false;
        }
        
        $keyInfo = $this->securityManager->validateAPIKey($this->apiKey);
        
        if ($keyInfo === false) {
            return false;
        }
        
        if (isset($keyInfo['error'])) {
            $this->sendError($keyInfo['error'], 429);
            return false;
        }
        
        $this->user = $this->db->fetch("SELECT * FROM users WHERE id = ?", [$keyInfo['user_id']]);
        
        // Log API request
        $this->securityManager->logSecurityEvent(
            $keyInfo['user_id'],
            'api_request',
            "API request: {$_SERVER['REQUEST_METHOD']} {$_SERVER['REQUEST_URI']}",
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        );
        
        return true;
    }
    
    /**
     * Check rate limits
     */
    private function checkRateLimit() {
        // Rate limiting is now handled in SecurityManager::validateAPIKey
        return true;
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
                    
                // Enterprise features
                case 'backups':
                    $this->handleBackups($method, $id);
                    break;
                    
                case 'monitoring':
                    $this->handleMonitoring($method, $id);
                    break;
                    
                case 'security':
                    $this->handleSecurity($method, $id);
                    break;
                    
                case 'applications':
                    $this->handleApplications($method, $id);
                    break;
                    
                case 'webhooks':
                    $this->handleWebhooks($method, $id);
                    break;
                    
                case 'api-keys':
                    $this->handleApiKeys($method, $id);
                    break;
                    
                case 'tickets':
                    $this->handleTickets($method, $id);
                    break;
                    
                case 'invoices':
                    $this->handleInvoices($method, $id);
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
     * Handle backups API endpoints
     */
    private function handleBackups($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getBackupById($id);
                } else {
                    $this->getAllBackups();
                }
                break;
                
            case 'POST':
                $subAction = $_GET['action'] ?? '';
                if ($subAction === 'schedule') {
                    $this->createBackupSchedule();
                } elseif ($subAction === 'execute') {
                    $this->executeBackup();
                } else {
                    $this->createBackup();
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteBackup($id);
                } else {
                    $this->sendError('Backup ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle monitoring API endpoints
     */
    private function handleMonitoring($method, $id) {
        switch ($method) {
            case 'GET':
                $subAction = $_GET['type'] ?? '';
                switch ($subAction) {
                    case 'status':
                        $this->getSystemStatus();
                        break;
                    case 'metrics':
                        $this->getMetrics();
                        break;
                    case 'processes':
                        $this->getProcesses();
                        break;
                    case 'services':
                        $this->getServices();
                        break;
                    case 'report':
                        $this->getSystemReport();
                        break;
                    default:
                        $this->getSystemStatus();
                }
                break;
                
            case 'POST':
                $subAction = $_GET['action'] ?? '';
                if ($subAction === 'service') {
                    $this->manageService();
                } else {
                    $this->collectMetrics();
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle security API endpoints
     */
    private function handleSecurity($method, $id) {
        switch ($method) {
            case 'GET':
                $subAction = $_GET['type'] ?? '';
                switch ($subAction) {
                    case 'scans':
                        $this->getSecurityScans();
                        break;
                    case '2fa':
                        $this->get2FAStatus();
                        break;
                    case 'events':
                        $this->getSecurityEvents();
                        break;
                    default:
                        $this->getSecurityOverview();
                }
                break;
                
            case 'POST':
                $subAction = $_GET['action'] ?? '';
                switch ($subAction) {
                    case 'scan':
                        $this->performSecurityScan();
                        break;
                    case '2fa-setup':
                        $this->setup2FA();
                        break;
                    case '2fa-verify':
                        $this->verify2FA();
                        break;
                    case '2fa-enable':
                        $this->enable2FA();
                        break;
                    default:
                        $this->sendError('Invalid action', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle applications API endpoints
     */
    private function handleApplications($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getApplicationById($id);
                } else {
                    $subAction = $_GET['type'] ?? '';
                    if ($subAction === 'available') {
                        $this->getAvailableApplications();
                    } elseif ($subAction === 'installed') {
                        $this->getInstalledApplications();
                    } else {
                        $this->getAllApplications();
                    }
                }
                break;
                
            case 'POST':
                $subAction = $_GET['action'] ?? '';
                if ($subAction === 'install') {
                    $this->installApplication();
                } elseif ($subAction === 'update') {
                    $this->updateApplication();
                } else {
                    $this->sendError('Invalid action', 400);
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->uninstallApplication($id);
                } else {
                    $this->sendError('Application ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle webhooks API endpoints
     */
    private function handleWebhooks($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getWebhookById($id);
                } else {
                    $this->getAllWebhooks();
                }
                break;
                
            case 'POST':
                $this->createWebhook();
                break;
                
            case 'PUT':
                if ($id) {
                    $this->updateWebhook($id);
                } else {
                    $this->sendError('Webhook ID required', 400);
                }
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteWebhook($id);
                } else {
                    $this->sendError('Webhook ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle API keys endpoints
     */
    private function handleApiKeys($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getApiKeyById($id);
                } else {
                    $this->getAllApiKeys();
                }
                break;
                
            case 'POST':
                $this->createApiKey();
                break;
                
            case 'DELETE':
                if ($id) {
                    $this->deleteApiKey($id);
                } else {
                    $this->sendError('API Key ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle tickets API endpoints
     */
    private function handleTickets($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getTicketById($id);
                } else {
                    $this->getAllTickets();
                }
                break;
                
            case 'POST':
                $subAction = $_GET['action'] ?? '';
                if ($subAction === 'reply' && $id) {
                    $this->replyToTicket($id);
                } else {
                    $this->createTicket();
                }
                break;
                
            case 'PUT':
                if ($id) {
                    $this->updateTicket($id);
                } else {
                    $this->sendError('Ticket ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    /**
     * Handle invoices API endpoints
     */
    private function handleInvoices($method, $id) {
        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getInvoiceById($id);
                } else {
                    $this->getAllInvoices();
                }
                break;
                
            case 'POST':
                $this->createInvoice();
                break;
                
            case 'PUT':
                if ($id) {
                    $subAction = $_GET['action'] ?? '';
                    if ($subAction === 'pay') {
                        $this->payInvoice($id);
                    } else {
                        $this->updateInvoice($id);
                    }
                } else {
                    $this->sendError('Invoice ID required', 400);
                }
                break;
                
            default:
                $this->sendError('Method not allowed', 405);
        }
    }
    
    // Backup API methods
    private function getAllBackups() {
        $backups = $this->backupManager->getBackups($this->user['id']);
        $this->sendSuccess($backups);
    }
    
    private function createBackupSchedule() {
        $data = json_decode(file_get_contents('php://input'), true);
        $scheduleId = $this->backupManager->createBackupSchedule($this->user['id'], $data);
        $this->sendSuccess(['schedule_id' => $scheduleId, 'message' => 'Backup schedule created'], 201);
    }
    
    private function executeBackup() {
        $data = json_decode(file_get_contents('php://input'), true);
        $scheduleId = $data['schedule_id'] ?? null;
        
        if (!$scheduleId) {
            $this->sendError('Schedule ID required', 400);
        }
        
        $result = $this->backupManager->executeBackup($scheduleId);
        $this->sendSuccess($result);
    }
    
    // Monitoring API methods
    private function getSystemStatus() {
        $status = $this->monitoringManager->getSystemStatus();
        $this->sendSuccess($status);
    }
    
    private function getMetrics() {
        $hours = $_GET['hours'] ?? 24;
        $type = $_GET['metric_type'] ?? null;
        $metrics = $this->monitoringManager->getHistoricalMetrics($type, $hours);
        $this->sendSuccess($metrics);
    }
    
    private function getProcesses() {
        $processes = $this->monitoringManager->getProcessInfo();
        $this->sendSuccess($processes);
    }
    
    private function getServices() {
        $services = $this->monitoringManager->getServiceStatuses();
        $this->sendSuccess($services);
    }
    
    private function getSystemReport() {
        $report = $this->monitoringManager->generateSystemReport();
        $this->sendSuccess($report);
    }
    
    private function manageService() {
        $data = json_decode(file_get_contents('php://input'), true);
        $serviceName = $data['service'] ?? '';
        $action = $data['action'] ?? '';
        
        if (empty($serviceName) || empty($action)) {
            $this->sendError('Service name and action required', 400);
        }
        
        $result = $this->monitoringManager->manageService($serviceName, $action);
        $this->sendSuccess($result);
    }
    
    private function collectMetrics() {
        $metrics = $this->monitoringManager->collectSystemMetrics();
        $this->sendSuccess($metrics);
    }
    
    // Security API methods
    private function getSecurityOverview() {
        $overview = [
            'recent_scans' => $this->db->fetchAll("SELECT * FROM security_scans ORDER BY started_at DESC LIMIT 10"),
            'security_events' => $this->securityManager->getSecurityEvents(20),
            'active_2fa_users' => $this->db->fetch("SELECT COUNT(*) as count FROM user_2fa WHERE enabled = 1")['count']
        ];
        $this->sendSuccess($overview);
    }
    
    private function performSecurityScan() {
        $data = json_decode(file_get_contents('php://input'), true);
        $type = $data['type'] ?? 'malware';
        $targetType = $data['target_type'] ?? 'server';
        $targetId = $data['target_id'] ?? 'local';
        
        $result = $this->securityManager->performSecurityScan($type, $targetType, $targetId);
        $this->sendSuccess($result);
    }
    
    private function setup2FA() {
        $result = $this->securityManager->generate2FASecret($this->user['id']);
        $this->sendSuccess($result);
    }
    
    private function verify2FA() {
        $data = json_decode(file_get_contents('php://input'), true);
        $token = $data['token'] ?? '';
        
        if (empty($token)) {
            $this->sendError('Token required', 400);
        }
        
        $valid = $this->securityManager->verify2FA($this->user['id'], $token);
        $this->sendSuccess(['valid' => $valid]);
    }
    
    private function enable2FA() {
        $this->securityManager->enable2FA($this->user['id']);
        $this->sendSuccess(['message' => '2FA enabled successfully']);
    }
    
    // Application API methods
    private function getAvailableApplications() {
        $category = $_GET['category'] ?? null;
        $apps = $this->applicationManager->getAvailableApplications($category);
        $this->sendSuccess($apps);
    }
    
    private function getInstalledApplications() {
        $apps = $this->applicationManager->getUserApplications($this->user['id']);
        $this->sendSuccess($apps);
    }
    
    private function installApplication() {
        $data = json_decode(file_get_contents('php://input'), true);
        $applicationId = $data['application_id'] ?? null;
        $domainId = $data['domain_id'] ?? null;
        $installPath = $data['install_path'] ?? '';
        $options = $data['options'] ?? [];
        
        if (!$applicationId || !$domainId) {
            $this->sendError('Application ID and Domain ID required', 400);
        }
        
        $result = $this->applicationManager->installApplication($this->user['id'], $applicationId, $domainId, $installPath, $options);
        $this->sendSuccess($result, 201);
    }
    
    // Webhook API methods
    private function getAllWebhooks() {
        $webhooks = $this->db->fetchAll("SELECT * FROM webhooks WHERE user_id = ? ORDER BY created_at DESC", [$this->user['id']]);
        $this->sendSuccess($webhooks);
    }
    
    private function createWebhook() {
        $data = json_decode(file_get_contents('php://input'), true);
        $eventType = $data['event_type'] ?? '';
        $url = $data['url'] ?? '';
        $secret = $data['secret'] ?? '';
        
        if (empty($eventType) || empty($url)) {
            $this->sendError('Event type and URL required', 400);
        }
        
        $webhookId = $this->db->query(
            "INSERT INTO webhooks (user_id, event_type, url, secret) VALUES (?, ?, ?, ?)",
            [$this->user['id'], $eventType, $url, $secret]
        )->lastInsertId();
        
        $this->sendSuccess(['id' => $webhookId, 'message' => 'Webhook created successfully'], 201);
    }
    
    // API Key management methods
    private function getAllApiKeys() {
        $keys = $this->db->fetchAll(
            "SELECT id, key_name, permissions, rate_limit_per_minute, last_used, status, created_at FROM api_keys WHERE user_id = ? ORDER BY created_at DESC",
            [$this->user['id']]
        );
        $this->sendSuccess($keys);
    }
    
    private function createApiKey() {
        $data = json_decode(file_get_contents('php://input'), true);
        $keyName = $data['key_name'] ?? '';
        $permissions = $data['permissions'] ?? [];
        $rateLimit = $data['rate_limit'] ?? 60;
        
        if (empty($keyName)) {
            $this->sendError('Key name required', 400);
        }
        
        $apiKey = $this->securityManager->generateAPIKey($this->user['id'], $keyName, $permissions, $rateLimit);
        $this->sendSuccess(['api_key' => $apiKey, 'message' => 'API key created successfully'], 201);
    }
    
    // Support ticket methods
    private function getAllTickets() {
        $tickets = $this->db->fetchAll(
            "SELECT * FROM support_tickets WHERE user_id = ? ORDER BY created_at DESC",
            [$this->user['id']]
        );
        $this->sendSuccess($tickets);
    }
    
    private function createTicket() {
        $data = json_decode(file_get_contents('php://input'), true);
        $subject = $data['subject'] ?? '';
        $department = $data['department'] ?? 'technical';
        $priority = $data['priority'] ?? 'medium';
        $message = $data['message'] ?? '';
        
        if (empty($subject) || empty($message)) {
            $this->sendError('Subject and message required', 400);
        }
        
        $ticketNumber = 'TKT-' . strtoupper(uniqid());
        
        $ticketId = $this->db->query(
            "INSERT INTO support_tickets (user_id, ticket_number, subject, department, priority, status) VALUES (?, ?, ?, ?, ?, 'open')",
            [$this->user['id'], $ticketNumber, $subject, $department, $priority]
        )->lastInsertId();
        
        // Add initial message
        $this->db->query(
            "INSERT INTO support_ticket_messages (ticket_id, user_id, message) VALUES (?, ?, ?)",
            [$ticketId, $this->user['id'], $message]
        );
        
        $this->sendSuccess(['ticket_id' => $ticketId, 'ticket_number' => $ticketNumber, 'message' => 'Ticket created successfully'], 201);
    }
    
    // Invoice methods
    private function getAllInvoices() {
        $invoices = $this->db->fetchAll(
            "SELECT * FROM invoices WHERE user_id = ? ORDER BY created_at DESC",
            [$this->user['id']]
        );
        $this->sendSuccess($invoices);
    }
    
    private function createInvoice() {
        $data = json_decode(file_get_contents('php://input'), true);
        $amount = $data['amount'] ?? 0;
        $items = $data['items'] ?? [];
        $dueDate = $data['due_date'] ?? date('Y-m-d', strtotime('+30 days'));
        
        if ($amount <= 0) {
            $this->sendError('Amount must be greater than 0', 400);
        }
        
        $invoiceNumber = 'INV-' . date('Y') . '-' . strtoupper(uniqid());
        
        $invoiceId = $this->db->query(
            "INSERT INTO invoices (user_id, invoice_number, amount, total_amount, due_date, items) VALUES (?, ?, ?, ?, ?, ?)",
            [$this->user['id'], $invoiceNumber, $amount, $amount, $dueDate, json_encode($items)]
        )->lastInsertId();
        
        $this->sendSuccess(['invoice_id' => $invoiceId, 'invoice_number' => $invoiceNumber, 'message' => 'Invoice created successfully'], 201);
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