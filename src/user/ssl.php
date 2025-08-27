<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Handle SSL operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'upload_ssl') {
        $domain_id = $_POST['domain_id'] ?? '';
        $certificate = $_POST['certificate'] ?? '';
        $private_key = $_POST['private_key'] ?? '';
        $certificate_chain = $_POST['certificate_chain'] ?? '';
        $ca = $_POST['ca'] ?? 'Manual Upload';
        
        if ($domain_id && $certificate && $private_key) {
            // Basic certificate validation
            if (strpos($certificate, '-----BEGIN CERTIFICATE-----') === false) {
                $error = "Invalid certificate format";
            } elseif (strpos($private_key, '-----BEGIN PRIVATE KEY-----') === false && 
                      strpos($private_key, '-----BEGIN RSA PRIVATE KEY-----') === false) {
                $error = "Invalid private key format";
            } else {
                // Calculate expiry date (simplified - in production you'd parse the certificate)
                $expiryDate = date('Y-m-d H:i:s', strtotime('+1 year'));
                
                $stmt = $db->prepare(
                    "INSERT INTO ssl_certificates (domain_id, certificate_authority, certificate, private_key, 
                     certificate_chain, expiry_date, created_at) 
                     VALUES (?, ?, ?, ?, ?, ?, NOW())"
                );
                $stmt->execute([$domain_id, $ca, $certificate, $private_key, $certificate_chain, $expiryDate]);
                
                // Update domain SSL status
                $db->prepare("UPDATE domains SET ssl_enabled = 1 WHERE id = ?")->execute([$domain_id]);
                
                $success = "SSL certificate installed successfully";
            }
        } else {
            $error = "Domain, certificate, and private key are required";
        }
    }
    
    if ($action === 'request_letsencrypt') {
        $domain_id = $_POST['domain_id'] ?? '';
        $email = $_POST['email'] ?? '';
        
        if ($domain_id && $email) {
            // Get domain info
            $domain = $db->fetch(
                "SELECT * FROM domains WHERE id = ? AND user_id = ?",
                [$domain_id, $user['id']]
            );
            
            if ($domain) {
                // Simulate Let's Encrypt certificate generation
                // In production, you'd use ACME client like Certbot
                $certificate = "-----BEGIN CERTIFICATE-----\n";
                $certificate .= "MIIFXTCCBEWgAwIBAgISA...[Let's Encrypt Certificate Content]...\n";
                $certificate .= "-----END CERTIFICATE-----\n";
                
                $privateKey = "-----BEGIN PRIVATE KEY-----\n";
                $privateKey .= "MIIEvgIBADANBgkqhkiG...[Private Key Content]...\n";
                $privateKey .= "-----END PRIVATE KEY-----\n";
                
                $expiryDate = date('Y-m-d H:i:s', strtotime('+90 days')); // Let's Encrypt 90-day expiry
                
                $stmt = $db->prepare(
                    "INSERT INTO ssl_certificates (domain_id, certificate_authority, certificate, private_key, 
                     expiry_date, auto_renew, created_at) 
                     VALUES (?, 'Let''s Encrypt', ?, ?, ?, 1, NOW())"
                );
                $stmt->execute([$domain_id, $certificate, $privateKey, $expiryDate]);
                
                // Update domain SSL status
                $db->prepare("UPDATE domains SET ssl_enabled = 1 WHERE id = ?")->execute([$domain_id]);
                
                $success = "Let's Encrypt certificate requested successfully (simulated)";
            } else {
                $error = "Domain not found";
            }
        } else {
            $error = "Domain and email are required";
        }
    }
    
    if ($action === 'delete_ssl') {
        $ssl_id = $_POST['ssl_id'] ?? '';
        
        $ssl = $db->fetch(
            "SELECT sc.*, d.user_id FROM ssl_certificates sc 
             INNER JOIN domains d ON sc.domain_id = d.id 
             WHERE sc.id = ? AND d.user_id = ?",
            [$ssl_id, $user['id']]
        );
        
        if ($ssl) {
            $db->prepare("DELETE FROM ssl_certificates WHERE id = ?")->execute([$ssl_id]);
            $db->prepare("UPDATE domains SET ssl_enabled = 0 WHERE id = ?")->execute([$ssl['domain_id']]);
            $success = "SSL certificate removed successfully";
        } else {
            $error = "SSL certificate not found";
        }
    }
    
    if ($action === 'toggle_auto_renew') {
        $ssl_id = $_POST['ssl_id'] ?? '';
        $auto_renew = isset($_POST['auto_renew']) ? 1 : 0;
        
        $stmt = $db->prepare(
            "UPDATE ssl_certificates sc 
             INNER JOIN domains d ON sc.domain_id = d.id 
             SET sc.auto_renew = ? 
             WHERE sc.id = ? AND d.user_id = ?"
        );
        $stmt->execute([$auto_renew, $ssl_id, $user['id']]);
        $success = "Auto-renewal setting updated";
    }
}

// Get user's domains
$userDomains = $db->fetchAll(
    "SELECT d.*, sc.id as ssl_id, sc.status as ssl_status, sc.expiry_date, sc.certificate_authority, sc.auto_renew
     FROM domains d 
     LEFT JOIN ssl_certificates sc ON d.id = sc.domain_id 
     WHERE d.user_id = ? AND d.status = 'active' 
     ORDER BY d.domain_name",
    [$user['id']]
);

// Get SSL certificates with domain info
$sslCertificates = $db->fetchAll(
    "SELECT sc.*, d.domain_name 
     FROM ssl_certificates sc 
     INNER JOIN domains d ON sc.domain_id = d.id 
     WHERE d.user_id = ? 
     ORDER BY sc.expiry_date ASC",
    [$user['id']]
);

// Count certificates by status
$totalCerts = count($sslCertificates);
$activeCerts = count(array_filter($sslCertificates, function($cert) { return $cert['status'] === 'active'; }));
$expiringSoon = count(array_filter($sslCertificates, function($cert) { 
    return $cert['status'] === 'active' && strtotime($cert['expiry_date']) < strtotime('+30 days');
}));

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSL Certificate Manager - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <link href="../assets/css/directadmin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>User Panel</small>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>Overview
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Website</div>
                <a href="filemanager.php" class="nav-link">
                    <i class="fas fa-folder"></i>File Manager
                </a>
                <a href="ftp-manager.php" class="nav-link">
                    <i class="fas fa-upload"></i>FTP Manager
                </a>
                <a href="subdomains.php" class="nav-link">
                    <i class="fas fa-sitemap"></i>Subdomains
                </a>
                <a href="ssl.php" class="nav-link active">
                    <i class="fas fa-shield-alt"></i>SSL Certificates
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Email</div>
                <a href="email.php" class="nav-link">
                    <i class="fas fa-envelope"></i>Email Accounts
                </a>
                <a href="forwarders.php" class="nav-link">
                    <i class="fas fa-share"></i>Forwarders
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Databases</div>
                <a href="databases.php" class="nav-link">
                    <i class="fas fa-database"></i>MySQL Databases
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Advanced</div>
                <a href="cron-jobs.php" class="nav-link">
                    <i class="fas fa-clock"></i>Cron Jobs
                </a>
                <a href="backups.php" class="nav-link">
                    <i class="fas fa-download"></i>Backups
                </a>
            </div>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-shield-alt me-3"></i>SSL Certificate Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">SSL Certificates</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <?php if ($expiringSoon > 0): ?>
                        <span class="badge bg-warning">
                            <i class="fas fa-exclamation-triangle"></i> <?= $expiringSoon ?> expiring soon
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if (isset($success)): ?>
                <div class="da-alert da-alert-success">
                    <i class="fas fa-check-circle"></i>
                    <div><?= htmlspecialchars($success) ?></div>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="da-alert da-alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?= htmlspecialchars($error) ?></div>
                </div>
            <?php endif; ?>

            <!-- SSL Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <div class="da-stat-value"><?= $totalCerts ?></div>
                        <div class="da-stat-label">Total Certificates</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="da-stat-value"><?= $activeCerts ?></div>
                        <div class="da-stat-label">Active Certificates</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="da-stat-value"><?= $expiringSoon ?></div>
                        <div class="da-stat-label">Expiring Soon</div>
                    </div>
                </div>
            </div>

            <!-- SSL Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>SSL Certificate Information
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">Why SSL Certificates?</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Encrypt data between server and visitors</li>
                                <li><i class="fas fa-check text-success me-2"></i>Improve search engine rankings</li>
                                <li><i class="fas fa-check text-success me-2"></i>Build trust with secure padlock icon</li>
                                <li><i class="fas fa-check text-success me-2"></i>Enable HTTPS for modern web features</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">Certificate Types</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-star text-warning me-2"></i><strong>Let's Encrypt:</strong> Free, automated certificates</li>
                                <li><i class="fas fa-star text-warning me-2"></i><strong>Manual Upload:</strong> Commercial certificates</li>
                                <li><i class="fas fa-star text-warning me-2"></i><strong>Self-Signed:</strong> For development/testing</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- SSL Methods Tabs -->
            <div class="da-tabs mb-4">
                <a href="#letsencrypt" class="da-tab active" onclick="showSSLTab('letsencrypt', this)">
                    Let's Encrypt (Free)
                </a>
                <a href="#upload" class="da-tab" onclick="showSSLTab('upload', this)">
                    Upload Certificate
                </a>
                <a href="#domains" class="da-tab" onclick="showSSLTab('domains', this)">
                    Domain Status
                </a>
            </div>

            <!-- Let's Encrypt Tab -->
            <div id="letsencrypt-content" class="ssl-tab-content">
                <div class="da-panel mb-4">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-magic"></i>Request Let's Encrypt Certificate
                        </h5>
                    </div>
                    <div class="da-panel-body">
                        <div class="da-alert da-alert-info mb-3">
                            <i class="fas fa-info-circle"></i>
                            <div>Let's Encrypt provides free SSL certificates that are automatically renewed every 90 days.</div>
                        </div>
                        
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="request_letsencrypt">
                            <div class="col-md-6">
                                <label class="da-label">Domain</label>
                                <select name="domain_id" class="da-select" required>
                                    <option value="">Select Domain</option>
                                    <?php foreach ($userDomains as $domain): ?>
                                        <?php if (!$domain['ssl_id']): ?>
                                            <option value="<?= $domain['id'] ?>">
                                                <?= htmlspecialchars($domain['domain_name']) ?>
                                            </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Email for Notifications</label>
                                <input type="email" name="email" class="da-input" value="<?= htmlspecialchars($user['email']) ?>" required>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="da-btn da-btn-primary">
                                    <i class="fas fa-magic"></i>Request Free SSL Certificate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Upload Certificate Tab -->
            <div id="upload-content" class="ssl-tab-content" style="display: none;">
                <div class="da-panel mb-4">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-upload"></i>Upload SSL Certificate
                        </h5>
                    </div>
                    <div class="da-panel-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="upload_ssl">
                            <div class="col-md-6">
                                <label class="da-label">Domain</label>
                                <select name="domain_id" class="da-select" required>
                                    <option value="">Select Domain</option>
                                    <?php foreach ($userDomains as $domain): ?>
                                        <option value="<?= $domain['id'] ?>">
                                            <?= htmlspecialchars($domain['domain_name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Certificate Authority</label>
                                <input type="text" name="ca" class="da-input" placeholder="e.g., Comodo, DigiCert, GoDaddy">
                            </div>
                            <div class="col-12">
                                <label class="da-label">Certificate (CRT)</label>
                                <textarea name="certificate" class="da-textarea" rows="8" required 
                                          placeholder="-----BEGIN CERTIFICATE-----
...certificate content...
-----END CERTIFICATE-----"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="da-label">Private Key</label>
                                <textarea name="private_key" class="da-textarea" rows="8" required 
                                          placeholder="-----BEGIN PRIVATE KEY-----
...private key content...
-----END PRIVATE KEY-----"></textarea>
                            </div>
                            <div class="col-12">
                                <label class="da-label">Certificate Chain (Optional)</label>
                                <textarea name="certificate_chain" class="da-textarea" rows="6" 
                                          placeholder="-----BEGIN CERTIFICATE-----
...intermediate certificate...
-----END CERTIFICATE-----"></textarea>
                                <div class="form-text">Include intermediate certificates if provided by your CA</div>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="da-btn da-btn-primary">
                                    <i class="fas fa-upload"></i>Install SSL Certificate
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Domain Status Tab -->
            <div id="domains-content" class="ssl-tab-content" style="display: none;">
                <div class="da-panel">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-globe"></i>Domain SSL Status
                        </h5>
                    </div>
                    <div class="da-panel-body p-0">
                        <?php if (empty($userDomains)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-globe fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No domains found</h5>
                                <p class="text-muted">Add domains to manage SSL certificates.</p>
                            </div>
                        <?php else: ?>
                            <div class="da-table">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Domain</th>
                                            <th>SSL Status</th>
                                            <th>Certificate Authority</th>
                                            <th>Expiry Date</th>
                                            <th>Auto Renew</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($userDomains as $domain): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($domain['domain_name']) ?></strong>
                                                <div class="small">
                                                    <a href="https://<?= htmlspecialchars($domain['domain_name']) ?>" 
                                                       target="_blank" class="text-decoration-none">
                                                        <i class="fas fa-external-link-alt"></i> Test SSL
                                                    </a>
                                                </div>
                                            </td>
                                            <td>
                                                <?php if ($domain['ssl_id']): ?>
                                                    <?php if ($domain['ssl_status'] === 'active'): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-shield-alt"></i> Active
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-exclamation-triangle"></i> <?= ucfirst($domain['ssl_status']) ?>
                                                        </span>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">
                                                        <i class="fas fa-times"></i> No SSL
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?= $domain['certificate_authority'] ? htmlspecialchars($domain['certificate_authority']) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($domain['expiry_date']): ?>
                                                    <?php 
                                                    $expiryTime = strtotime($domain['expiry_date']);
                                                    $daysUntilExpiry = ceil(($expiryTime - time()) / 86400);
                                                    $expiryClass = $daysUntilExpiry <= 30 ? 'text-danger' : ($daysUntilExpiry <= 60 ? 'text-warning' : 'text-success');
                                                    ?>
                                                    <span class="<?= $expiryClass ?>" title="<?= date('Y-m-d H:i:s', $expiryTime) ?>">
                                                        <?= date('M j, Y', $expiryTime) ?>
                                                        <div class="small">(<?= $daysUntilExpiry ?> days)</div>
                                                    </span>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($domain['ssl_id']): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="toggle_auto_renew">
                                                        <input type="hidden" name="ssl_id" value="<?= $domain['ssl_id'] ?>">
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" name="auto_renew" 
                                                                   <?= $domain['auto_renew'] ? 'checked' : '' ?>
                                                                   onchange="this.form.submit()">
                                                        </div>
                                                    </form>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($domain['ssl_id']): ?>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Remove SSL certificate from this domain?')">
                                                        <input type="hidden" name="action" value="delete_ssl">
                                                        <input type="hidden" name="ssl_id" value="<?= $domain['ssl_id'] ?>">
                                                        <button type="submit" class="da-btn da-btn-sm da-btn-danger">
                                                            <i class="fas fa-trash"></i>Remove
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">No SSL installed</span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching for SSL methods
        function showSSLTab(tabName, element) {
            // Hide all tab contents
            document.querySelectorAll('.ssl-tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.da-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').style.display = 'block';
            
            // Add active class to selected tab
            element.classList.add('active');
        }
    </script>
</body>
</html>