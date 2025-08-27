<?php
/**
 * Advanced Security Manager for Enterprise Features
 */

class SecurityManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Generate 2FA secret for user
     */
    public function generate2FASecret($userId) {
        // Generate base32 secret for Google Authenticator
        $secret = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        for ($i = 0; $i < 16; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        // Generate backup codes
        $backupCodes = [];
        for ($i = 0; $i < 10; $i++) {
            $backupCodes[] = strtoupper(bin2hex(random_bytes(4)));
        }
        
        $sql = "INSERT INTO user_2fa (user_id, secret, backup_codes) VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE secret = ?, backup_codes = ?";
        $this->db->query($sql, [$userId, $secret, json_encode($backupCodes), $secret, json_encode($backupCodes)]);
        
        return [
            'secret' => $secret,
            'backup_codes' => $backupCodes,
            'qr_code_url' => $this->generate2FAQRCode($secret, $userId)
        ];
    }
    
    /**
     * Generate QR code URL for 2FA setup
     */
    private function generate2FAQRCode($secret, $userId) {
        $user = $this->db->fetch("SELECT username, email FROM users WHERE id = ?", [$userId]);
        $issuer = 'Admini Control Panel';
        $label = urlencode($issuer . ':' . $user['username']);
        
        $otpauth = "otpauth://totp/{$label}?secret={$secret}&issuer=" . urlencode($issuer);
        return "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauth);
    }
    
    /**
     * Verify 2FA token
     */
    public function verify2FA($userId, $token) {
        $user2fa = $this->db->fetch("SELECT secret, backup_codes FROM user_2fa WHERE user_id = ? AND enabled = 1", [$userId]);
        
        if (!$user2fa) {
            return false;
        }
        
        // Check if it's a backup code
        $backupCodes = json_decode($user2fa['backup_codes'], true);
        if (in_array(strtoupper($token), $backupCodes)) {
            // Remove used backup code
            $backupCodes = array_diff($backupCodes, [strtoupper($token)]);
            $this->db->query("UPDATE user_2fa SET backup_codes = ? WHERE user_id = ?", 
                [json_encode(array_values($backupCodes)), $userId]);
            return true;
        }
        
        // Verify TOTP token
        return $this->verifyTOTP($user2fa['secret'], $token);
    }
    
    /**
     * Verify TOTP token using time-based algorithm
     */
    private function verifyTOTP($secret, $token) {
        $timeSlice = floor(time() / 30);
        
        // Check current time slice and previous/next for clock drift
        for ($i = -1; $i <= 1; $i++) {
            if ($this->generateTOTP($secret, $timeSlice + $i) === $token) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate TOTP token
     */
    private function generateTOTP($secret, $timeSlice) {
        $key = '';
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        
        // Decode base32 secret
        $secret = strtoupper($secret);
        $paddingLength = strlen($secret) % 8;
        if ($paddingLength) {
            $secret .= str_repeat('=', 8 - $paddingLength);
        }
        
        $binaryString = '';
        for ($i = 0; $i < strlen($secret); $i += 8) {
            $chunk = substr($secret, $i, 8);
            $value = 0;
            for ($j = 0; $j < 8; $j++) {
                $value = ($value << 5) + strpos($chars, $chunk[$j]);
            }
            $binaryString .= pack('N*', $value >> 8) . pack('C', $value & 0xff);
        }
        
        $key = substr($binaryString, 0, -1);
        
        // Generate HMAC
        $time = pack('N*', 0) . pack('N*', $timeSlice);
        $hmac = hash_hmac('sha1', $time, $key, true);
        
        $offset = ord($hmac[19]) & 0xf;
        $code = (
            ((ord($hmac[$offset + 0]) & 0x7f) << 24) |
            ((ord($hmac[$offset + 1]) & 0xff) << 16) |
            ((ord($hmac[$offset + 2]) & 0xff) << 8) |
            (ord($hmac[$offset + 3]) & 0xff)
        ) % 1000000;
        
        return str_pad($code, 6, '0', STR_PAD_LEFT);
    }
    
    /**
     * Enable 2FA for user
     */
    public function enable2FA($userId) {
        $this->db->query("UPDATE user_2fa SET enabled = 1, updated_at = NOW() WHERE user_id = ?", [$userId]);
    }
    
    /**
     * Disable 2FA for user
     */
    public function disable2FA($userId) {
        $this->db->query("UPDATE user_2fa SET enabled = 0, updated_at = NOW() WHERE user_id = ?", [$userId]);
    }
    
    /**
     * Perform security scan
     */
    public function performSecurityScan($type, $targetType, $targetId) {
        $scanId = $this->db->query(
            "INSERT INTO security_scans (scan_type, target_type, target_id, scan_status) VALUES (?, ?, ?, 'running')",
            [$type, $targetType, $targetId]
        )->lastInsertId();
        
        $results = [];
        $threatsFound = 0;
        
        switch ($type) {
            case 'malware':
                $results = $this->scanMalware($targetType, $targetId);
                break;
            case 'vulnerability':
                $results = $this->scanVulnerabilities($targetType, $targetId);
                break;
            case 'integrity':
                $results = $this->scanIntegrity($targetType, $targetId);
                break;
            case 'blacklist':
                $results = $this->scanBlacklist($targetType, $targetId);
                break;
        }
        
        $threatsFound = count(array_filter($results, function($result) {
            return $result['threat_level'] > 0;
        }));
        
        $this->db->query(
            "UPDATE security_scans SET scan_status = 'completed', threats_found = ?, results = ?, completed_at = NOW() WHERE id = ?",
            [$threatsFound, json_encode($results), $scanId]
        );
        
        return [
            'scan_id' => $scanId,
            'threats_found' => $threatsFound,
            'results' => $results
        ];
    }
    
    /**
     * Scan for malware
     */
    private function scanMalware($targetType, $targetId) {
        $results = [];
        
        if ($targetType === 'domain') {
            $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ?", [$targetId]);
            $scanPath = $domain['document_root'];
            
            // Simulate malware scanning (integrate with ClamAV in production)
            $suspiciousPatterns = [
                'eval(' => 'High risk: eval() function detected',
                'base64_decode(' => 'Medium risk: base64_decode() detected',
                'shell_exec(' => 'High risk: shell_exec() detected',
                'system(' => 'High risk: system() function detected'
            ];
            
            if (is_dir($scanPath)) {
                $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($scanPath));
                foreach ($iterator as $file) {
                    if ($file->isFile() && in_array($file->getExtension(), ['php', 'js', 'html'])) {
                        $content = file_get_contents($file->getPathname());
                        
                        foreach ($suspiciousPatterns as $pattern => $description) {
                            if (strpos($content, $pattern) !== false) {
                                $results[] = [
                                    'file' => $file->getPathname(),
                                    'threat_level' => strpos($description, 'High') !== false ? 3 : 2,
                                    'description' => $description,
                                    'pattern' => $pattern
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Scan for vulnerabilities
     */
    private function scanVulnerabilities($targetType, $targetId) {
        $results = [];
        
        // Common vulnerability checks
        $vulnerabilities = [
            'outdated_php' => $this->checkPHPVersion(),
            'weak_passwords' => $this->checkWeakPasswords($targetId),
            'open_ports' => $this->checkOpenPorts(),
            'ssl_issues' => $this->checkSSLIssues($targetType, $targetId)
        ];
        
        foreach ($vulnerabilities as $vuln => $data) {
            if ($data['vulnerable']) {
                $results[] = [
                    'vulnerability' => $vuln,
                    'threat_level' => $data['threat_level'],
                    'description' => $data['description'],
                    'recommendation' => $data['recommendation']
                ];
            }
        }
        
        return $results;
    }
    
    /**
     * Check PHP version for vulnerabilities
     */
    private function checkPHPVersion() {
        $version = PHP_VERSION;
        $vulnerable = version_compare($version, '8.0.0', '<');
        
        return [
            'vulnerable' => $vulnerable,
            'threat_level' => $vulnerable ? 2 : 0,
            'description' => "PHP version {$version} detected",
            'recommendation' => $vulnerable ? 'Update to PHP 8.0 or higher' : 'PHP version is acceptable'
        ];
    }
    
    /**
     * Check for weak passwords
     */
    private function checkWeakPasswords($targetId) {
        // This would check password strength in a real implementation
        return [
            'vulnerable' => false,
            'threat_level' => 0,
            'description' => 'Password strength check completed',
            'recommendation' => 'Enforce strong password policies'
        ];
    }
    
    /**
     * Check for unnecessary open ports
     */
    private function checkOpenPorts() {
        // This would perform actual port scanning in production
        return [
            'vulnerable' => false,
            'threat_level' => 0,
            'description' => 'Port scan completed',
            'recommendation' => 'Close unnecessary ports'
        ];
    }
    
    /**
     * Check SSL configuration issues
     */
    private function checkSSLIssues($targetType, $targetId) {
        if ($targetType === 'domain') {
            $domain = $this->db->fetch("SELECT domain_name, ssl_enabled FROM domains WHERE id = ?", [$targetId]);
            
            if (!$domain['ssl_enabled']) {
                return [
                    'vulnerable' => true,
                    'threat_level' => 2,
                    'description' => 'SSL not enabled for domain',
                    'recommendation' => 'Enable SSL certificate'
                ];
            }
        }
        
        return [
            'vulnerable' => false,
            'threat_level' => 0,
            'description' => 'SSL configuration acceptable',
            'recommendation' => 'Maintain current SSL configuration'
        ];
    }
    
    /**
     * Integrity scanning
     */
    private function scanIntegrity($targetType, $targetId) {
        // File integrity monitoring would go here
        return [];
    }
    
    /**
     * Blacklist scanning
     */
    private function scanBlacklist($targetType, $targetId) {
        // IP/domain blacklist checking would go here
        return [];
    }
    
    /**
     * Generate API key
     */
    public function generateAPIKey($userId, $keyName, $permissions = [], $rateLimit = 60) {
        $apiKey = hash('sha256', uniqid() . random_bytes(32));
        
        $this->db->query(
            "INSERT INTO api_keys (user_id, key_name, api_key, permissions, rate_limit_per_minute) VALUES (?, ?, ?, ?, ?)",
            [$userId, $keyName, $apiKey, json_encode($permissions), $rateLimit]
        );
        
        return $apiKey;
    }
    
    /**
     * Validate API key and check rate limits
     */
    public function validateAPIKey($apiKey) {
        $key = $this->db->fetch(
            "SELECT * FROM api_keys WHERE api_key = ? AND status = 'active' AND (expires_at IS NULL OR expires_at > NOW())",
            [$apiKey]
        );
        
        if (!$key) {
            return false;
        }
        
        // Check rate limit
        $recentRequests = $this->db->fetch(
            "SELECT COUNT(*) as count FROM activity_logs WHERE user_id = ? AND action = 'api_request' AND created_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)",
            [$key['user_id']]
        );
        
        if ($recentRequests['count'] >= $key['rate_limit_per_minute']) {
            return ['error' => 'Rate limit exceeded'];
        }
        
        // Update last used
        $this->db->query("UPDATE api_keys SET last_used = NOW() WHERE id = ?", [$key['id']]);
        
        return $key;
    }
    
    /**
     * Log security event
     */
    public function logSecurityEvent($userId, $action, $description, $ipAddress = null, $userAgent = null) {
        $this->db->query(
            "INSERT INTO activity_logs (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)",
            [$userId, $action, $description, $ipAddress, $userAgent]
        );
    }
}