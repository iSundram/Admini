<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/SecurityManager.php';

// Check authentication
if (!Auth::isLoggedIn() || Auth::getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$security = new SecurityManager();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'start_scan':
                $scanType = $_POST['scan_type'] ?? 'malware';
                $targetType = $_POST['target_type'] ?? 'server';
                $targetId = $_POST['target_id'] ?? 'local';
                
                $result = $security->performSecurityScan($scanType, $targetType, $targetId);
                $success = "Security scan started. Scan ID: " . $result['scan_id'];
                break;
                
            case 'setup_2fa':
                $userId = Auth::getUserId();
                $result = $security->generate2FASecret($userId);
                $qrCode = $result['qr_code_url'];
                $secret = $result['secret'];
                $backupCodes = $result['backup_codes'];
                break;
                
            case 'verify_2fa':
                $token = $_POST['token'] ?? '';
                $userId = Auth::getUserId();
                
                if ($security->verify2FA($userId, $token)) {
                    $security->enable2FA($userId);
                    $success = "2FA has been successfully enabled for your account.";
                } else {
                    $error = "Invalid 2FA token. Please try again.";
                }
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get security data
$recentScans = $db->fetchAll("SELECT * FROM security_scans ORDER BY started_at DESC LIMIT 10");
$securityEvents = $db->fetchAll("SELECT * FROM activity_logs WHERE action LIKE '%security%' OR action LIKE '%login%' ORDER BY created_at DESC LIMIT 20");
$user2FA = $db->fetch("SELECT * FROM user_2fa WHERE user_id = ?", [Auth::getUserId()]);
$apiKeys = $db->fetchAll("SELECT id, key_name, permissions, rate_limit_per_minute, last_used, status, created_at FROM api_keys WHERE user_id = ? ORDER BY created_at DESC", [Auth::getUserId()]);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Center - Admini</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .security-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .security-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .security-card h3 {
            color: #3b82f6;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .scan-controls {
            display: grid;
            grid-template-columns: 1fr 1fr auto;
            gap: 10px;
            margin-bottom: 20px;
        }
        
        .scan-history {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .scan-item {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #3b82f6;
        }
        
        .scan-item.completed {
            border-left-color: #10b981;
        }
        
        .scan-item.failed {
            border-left-color: #ef4444;
        }
        
        .scan-item.running {
            border-left-color: #f59e0b;
        }
        
        .threats-badge {
            background: #ef4444;
            color: white;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .threats-badge.safe {
            background: #10b981;
        }
        
        .qr-code-container {
            text-align: center;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            margin: 15px 0;
        }
        
        .backup-codes {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 5px;
            margin: 15px 0;
        }
        
        .backup-code {
            background: rgba(59, 130, 246, 0.2);
            padding: 8px;
            border-radius: 4px;
            text-align: center;
            font-family: monospace;
            color: #3b82f6;
        }
        
        .security-stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-item {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .stat-label {
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 5px;
        }
        
        .event-list {
            max-height: 300px;
            overflow-y: auto;
        }
        
        .event-item {
            padding: 10px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .event-item:last-child {
            border-bottom: none;
        }
        
        .event-time {
            font-size: 12px;
            color: #94a3b8;
        }
        
        .api-key-item {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            justify-content: between;
            align-items: center;
        }
        
        .api-key-info {
            flex: 1;
        }
        
        .api-key-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .api-key-status.active {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .api-key-status.inactive {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            margin: 5% auto;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #fff;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-shield-alt"></i> Security Center</h1>
            <p>Advanced security management and monitoring for your hosting environment</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="security-dashboard">
            <!-- Security Overview -->
            <div class="security-card">
                <h3><i class="fas fa-chart-bar"></i> Security Overview</h3>
                <div class="security-stats">
                    <div class="stat-item">
                        <div class="stat-value"><?= count($recentScans) ?></div>
                        <div class="stat-label">Total Scans</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= array_sum(array_column($recentScans, 'threats_found')) ?></div>
                        <div class="stat-label">Threats Found</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value"><?= $user2FA && $user2FA['enabled'] ? 'ON' : 'OFF' ?></div>
                        <div class="stat-label">2FA Status</div>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button onclick="openScanModal()" class="btn btn-primary">
                        <i class="fas fa-search"></i> Start Security Scan
                    </button>
                    <?php if (!$user2FA || !$user2FA['enabled']): ?>
                    <button onclick="open2FAModal()" class="btn btn-warning">
                        <i class="fas fa-lock"></i> Setup 2FA
                    </button>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Scan History -->
            <div class="security-card">
                <h3><i class="fas fa-history"></i> Scan History</h3>
                <div class="scan-history">
                    <?php foreach ($recentScans as $scan): ?>
                    <div class="scan-item <?= $scan['scan_status'] ?>">
                        <div style="display: flex; justify-content: between; align-items: center;">
                            <div>
                                <strong><?= ucfirst($scan['scan_type']) ?> Scan</strong><br>
                                <small>Target: <?= htmlspecialchars($scan['target_type']) ?> (<?= htmlspecialchars($scan['target_id']) ?>)</small><br>
                                <small><?= date('M j, Y H:i', strtotime($scan['started_at'])) ?></small>
                            </div>
                            <div>
                                <?php if ($scan['threats_found'] > 0): ?>
                                    <span class="threats-badge"><?= $scan['threats_found'] ?> threats</span>
                                <?php else: ?>
                                    <span class="threats-badge safe">Clean</span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php if ($scan['scan_status'] === 'running'): ?>
                        <div style="margin-top: 10px;">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 60%;"></div>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Security Events -->
            <div class="security-card">
                <h3><i class="fas fa-eye"></i> Security Events</h3>
                <div class="event-list">
                    <?php foreach (array_slice($securityEvents, 0, 10) as $event): ?>
                    <div class="event-item">
                        <div><strong><?= htmlspecialchars($event['action']) ?></strong></div>
                        <div><?= htmlspecialchars($event['description']) ?></div>
                        <div class="event-time"><?= date('M j, Y H:i', strtotime($event['created_at'])) ?></div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- API Key Management -->
            <div class="security-card">
                <h3><i class="fas fa-key"></i> API Key Management</h3>
                <button onclick="openAPIKeyModal()" class="btn btn-primary" style="margin-bottom: 15px;">
                    <i class="fas fa-plus"></i> Create New API Key
                </button>
                
                <div class="api-key-list">
                    <?php foreach ($apiKeys as $key): ?>
                    <div class="api-key-item">
                        <div class="api-key-info">
                            <strong><?= htmlspecialchars($key['key_name']) ?></strong><br>
                            <small>Rate limit: <?= $key['rate_limit_per_minute'] ?>/min</small><br>
                            <small>Last used: <?= $key['last_used'] ? date('M j, Y', strtotime($key['last_used'])) : 'Never' ?></small>
                        </div>
                        <span class="api-key-status <?= $key['status'] ?>"><?= ucfirst($key['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Two-Factor Authentication -->
            <?php if ($user2FA && $user2FA['enabled']): ?>
            <div class="security-card">
                <h3><i class="fas fa-mobile-alt"></i> Two-Factor Authentication</h3>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 2FA is enabled for your account
                </div>
                <p>Your account is protected with two-factor authentication. You can disable it at any time.</p>
                <button class="btn btn-danger">
                    <i class="fas fa-times"></i> Disable 2FA
                </button>
            </div>
            <?php endif; ?>
            
            <!-- Security Recommendations -->
            <div class="security-card full-width">
                <h3><i class="fas fa-lightbulb"></i> Security Recommendations</h3>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 15px;">
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-shield-alt"></i> Enable 2FA</h4>
                        <p>Protect your account with two-factor authentication for enhanced security.</p>
                        <?php if (!$user2FA || !$user2FA['enabled']): ?>
                        <button onclick="open2FAModal()" class="btn btn-sm btn-primary">Setup Now</button>
                        <?php else: ?>
                        <span style="color: #10b981;"><i class="fas fa-check"></i> Enabled</span>
                        <?php endif; ?>
                    </div>
                    
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-scan"></i> Regular Scans</h4>
                        <p>Schedule regular security scans to detect malware and vulnerabilities.</p>
                        <button onclick="openScanModal()" class="btn btn-sm btn-primary">Start Scan</button>
                    </div>
                    
                    <div style="background: rgba(59, 130, 246, 0.1); padding: 15px; border-radius: 8px;">
                        <h4><i class="fas fa-key"></i> API Security</h4>
                        <p>Use API keys with proper permissions and rate limiting for automation.</p>
                        <button onclick="openAPIKeyModal()" class="btn btn-sm btn-primary">Manage Keys</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Security Scan Modal -->
    <div id="scanModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeScanModal()">&times;</span>
            <h3><i class="fas fa-search"></i> Start Security Scan</h3>
            <form method="POST">
                <input type="hidden" name="action" value="start_scan">
                
                <div class="form-group">
                    <label>Scan Type:</label>
                    <select name="scan_type" class="form-control">
                        <option value="malware">Malware Scan</option>
                        <option value="vulnerability">Vulnerability Scan</option>
                        <option value="integrity">Integrity Check</option>
                        <option value="blacklist">Blacklist Check</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Target Type:</label>
                    <select name="target_type" class="form-control">
                        <option value="server">Entire Server</option>
                        <option value="domain">Specific Domain</option>
                        <option value="user">User Account</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Target ID:</label>
                    <input type="text" name="target_id" class="form-control" placeholder="e.g., domain name or user ID" value="local">
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-play"></i> Start Scan
                </button>
            </form>
        </div>
    </div>
    
    <!-- 2FA Setup Modal -->
    <div id="twoFAModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="close2FAModal()">&times;</span>
            <h3><i class="fas fa-mobile-alt"></i> Setup Two-Factor Authentication</h3>
            
            <?php if (isset($qrCode)): ?>
            <div class="qr-code-container">
                <p>Scan this QR code with your authenticator app:</p>
                <img src="<?= htmlspecialchars($qrCode) ?>" alt="2FA QR Code" style="max-width: 200px;">
                <p><small>Secret: <code><?= htmlspecialchars($secret) ?></code></small></p>
            </div>
            
            <div>
                <h4>Backup Codes</h4>
                <p>Save these backup codes in a secure location:</p>
                <div class="backup-codes">
                    <?php foreach ($backupCodes as $code): ?>
                    <div class="backup-code"><?= htmlspecialchars($code) ?></div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <form method="POST">
                <input type="hidden" name="action" value="verify_2fa">
                <div class="form-group">
                    <label>Enter verification code from your app:</label>
                    <input type="text" name="token" class="form-control" placeholder="123456" maxlength="6" required>
                </div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-check"></i> Verify & Enable 2FA
                </button>
            </form>
            <?php else: ?>
            <form method="POST">
                <input type="hidden" name="action" value="setup_2fa">
                <p>Two-factor authentication adds an extra layer of security to your account.</p>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-mobile-alt"></i> Generate 2FA Secret
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>
    
    <!-- API Key Modal -->
    <div id="apiKeyModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeAPIKeyModal()">&times;</span>
            <h3><i class="fas fa-key"></i> Create API Key</h3>
            <form>
                <div class="form-group">
                    <label>Key Name:</label>
                    <input type="text" name="key_name" class="form-control" placeholder="My API Key" required>
                </div>
                
                <div class="form-group">
                    <label>Rate Limit (requests per minute):</label>
                    <input type="number" name="rate_limit" class="form-control" value="60" min="1" max="1000">
                </div>
                
                <div class="form-group">
                    <label>Permissions:</label>
                    <div style="display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px;">
                        <label><input type="checkbox" name="permissions[]" value="users"> User Management</label>
                        <label><input type="checkbox" name="permissions[]" value="domains"> Domain Management</label>
                        <label><input type="checkbox" name="permissions[]" value="email"> Email Management</label>
                        <label><input type="checkbox" name="permissions[]" value="databases"> Database Management</label>
                        <label><input type="checkbox" name="permissions[]" value="backups"> Backup Management</label>
                        <label><input type="checkbox" name="permissions[]" value="monitoring"> System Monitoring</label>
                    </div>
                </div>
                
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create API Key
                </button>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openScanModal() {
            document.getElementById('scanModal').style.display = 'block';
        }
        
        function closeScanModal() {
            document.getElementById('scanModal').style.display = 'none';
        }
        
        function open2FAModal() {
            document.getElementById('twoFAModal').style.display = 'block';
        }
        
        function close2FAModal() {
            document.getElementById('twoFAModal').style.display = 'none';
        }
        
        function openAPIKeyModal() {
            document.getElementById('apiKeyModal').style.display = 'block';
        }
        
        function closeAPIKeyModal() {
            document.getElementById('apiKeyModal').style.display = 'none';
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const modals = ['scanModal', 'twoFAModal', 'apiKeyModal'];
            modals.forEach(modalId => {
                const modal = document.getElementById(modalId);
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            });
        }
        
        // Auto-refresh scan status
        setInterval(function() {
            const runningScans = document.querySelectorAll('.scan-item.running');
            if (runningScans.length > 0) {
                location.reload();
            }
        }, 10000);
    </script>
</body>
</html>