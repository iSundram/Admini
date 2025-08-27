<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication
if (!$auth->isLoggedIn() || !$auth->hasPermission('user')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_email':
                $domainId = (int)$_POST['domain_id'];
                $emailPrefix = trim($_POST['email_prefix']);
                $password = $_POST['password'];
                $quota = (int)$_POST['quota'];
                
                // Verify domain belongs to user
                $domain = $db->fetch(
                    "SELECT * FROM domains WHERE id = ? AND user_id = ?",
                    [$domainId, $user['id']]
                );
                
                if (!$domain) {
                    throw new Exception('Invalid domain selected');
                }
                
                $fullEmail = $emailPrefix . '@' . $domain['domain_name'];
                
                // Check if email already exists
                $existing = $db->fetch(
                    "SELECT id FROM email_accounts WHERE email = ?",
                    [$fullEmail]
                );
                
                if ($existing) {
                    throw new Exception('Email account already exists');
                }
                
                // Check email accounts limit
                $currentCount = $db->fetch(
                    "SELECT COUNT(*) as count FROM email_accounts WHERE user_id = ?",
                    [$user['id']]
                )['count'];
                
                if ($currentCount >= $user['email_accounts_limit']) {
                    throw new Exception('Email accounts limit reached');
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Create email account
                $db->query(
                    "INSERT INTO email_accounts (user_id, domain_id, email, password, quota, created_at) VALUES (?, ?, ?, ?, ?, NOW())",
                    [$user['id'], $domainId, $fullEmail, $hashedPassword, $quota]
                );
                
                $message = 'Email account created successfully';
                break;
                
            case 'update_email':
                $emailId = (int)$_POST['email_id'];
                $quota = (int)$_POST['quota'];
                $status = $_POST['status'];
                
                // Verify email belongs to user
                $email = $db->fetch(
                    "SELECT * FROM email_accounts WHERE id = ? AND user_id = ?",
                    [$emailId, $user['id']]
                );
                
                if (!$email) {
                    throw new Exception('Email account not found');
                }
                
                // Update email account
                $db->query(
                    "UPDATE email_accounts SET quota = ?, status = ?, updated_at = NOW() WHERE id = ?",
                    [$quota, $status, $emailId]
                );
                
                $message = 'Email account updated successfully';
                break;
                
            case 'change_password':
                $emailId = (int)$_POST['email_id'];
                $password = $_POST['password'];
                
                // Verify email belongs to user
                $email = $db->fetch(
                    "SELECT * FROM email_accounts WHERE id = ? AND user_id = ?",
                    [$emailId, $user['id']]
                );
                
                if (!$email) {
                    throw new Exception('Email account not found');
                }
                
                // Hash new password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Update password
                $db->query(
                    "UPDATE email_accounts SET password = ?, updated_at = NOW() WHERE id = ?",
                    [$hashedPassword, $emailId]
                );
                
                $message = 'Email password changed successfully';
                break;
                
            case 'delete_email':
                $emailId = (int)$_POST['email_id'];
                
                // Verify email belongs to user
                $email = $db->fetch(
                    "SELECT * FROM email_accounts WHERE id = ? AND user_id = ?",
                    [$emailId, $user['id']]
                );
                
                if (!$email) {
                    throw new Exception('Email account not found');
                }
                
                // Delete email account
                $db->query("DELETE FROM email_accounts WHERE id = ?", [$emailId]);
                
                $message = 'Email account deleted successfully';
                break;
                
            case 'create_forwarder':
                $domainId = (int)$_POST['domain_id'];
                $sourceEmail = trim($_POST['source_email']);
                $destinationEmail = trim($_POST['destination_email']);
                
                // Verify domain belongs to user
                $domain = $db->fetch(
                    "SELECT * FROM domains WHERE id = ? AND user_id = ?",
                    [$domainId, $user['id']]
                );
                
                if (!$domain) {
                    throw new Exception('Invalid domain selected');
                }
                
                // Create forwarder
                $db->query(
                    "INSERT INTO email_forwarders (user_id, domain_id, source_email, destination_email, created_at) VALUES (?, ?, ?, ?, NOW())",
                    [$user['id'], $domainId, $sourceEmail, $destinationEmail]
                );
                
                $message = 'Email forwarder created successfully';
                break;
                
            case 'delete_forwarder':
                $forwarderId = (int)$_POST['forwarder_id'];
                
                // Verify forwarder belongs to user
                $forwarder = $db->fetch(
                    "SELECT * FROM email_forwarders WHERE id = ? AND user_id = ?",
                    [$forwarderId, $user['id']]
                );
                
                if (!$forwarder) {
                    throw new Exception('Email forwarder not found');
                }
                
                // Delete forwarder
                $db->query("DELETE FROM email_forwarders WHERE id = ?", [$forwarderId]);
                
                $message = 'Email forwarder deleted successfully';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get user's email accounts
$emailAccounts = $db->fetchAll(
    "SELECT ea.*, d.domain_name FROM email_accounts ea 
     JOIN domains d ON ea.domain_id = d.id 
     WHERE ea.user_id = ? 
     ORDER BY ea.email",
    [$user['id']]
);

// Get user's email forwarders
$emailForwarders = $db->fetchAll(
    "SELECT ef.*, d.domain_name FROM email_forwarders ef 
     JOIN domains d ON ef.domain_id = d.id 
     WHERE ef.user_id = ? 
     ORDER BY ef.source_email",
    [$user['id']]
);

// Get user's domains for dropdowns
$domains = $db->fetchAll(
    "SELECT * FROM domains WHERE user_id = ? ORDER BY domain_name",
    [$user['id']]
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Management - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>User Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="domains.php" class="nav-link">
                <i class="fas fa-globe"></i>Domains
            </a>
            <a href="subdomains.php" class="nav-link">
                <i class="fas fa-sitemap"></i>Subdomains
            </a>
            <a href="dns.php" class="nav-link">
                <i class="fas fa-network-wired"></i>DNS Records
            </a>
            <a href="email.php" class="nav-link active">
                <i class="fas fa-envelope"></i>Email
            </a>
            <a href="ftp.php" class="nav-link">
                <i class="fas fa-folder"></i>FTP Accounts
            </a>
            <a href="filemanager.php" class="nav-link">
                <i class="fas fa-file-alt"></i>File Manager
            </a>
            <a href="databases.php" class="nav-link">
                <i class="fas fa-database"></i>Databases
            </a>
            <a href="ssl.php" class="nav-link">
                <i class="fas fa-lock"></i>SSL Certificates
            </a>
            <a href="backups.php" class="nav-link">
                <i class="fas fa-download"></i>Backups
            </a>
            <a href="cronjobs.php" class="nav-link">
                <i class="fas fa-clock"></i>Cron Jobs
            </a>
            <a href="statistics.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Statistics
            </a>
            <a href="applications.php" class="nav-link">
                <i class="fas fa-puzzle-piece"></i>Applications
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Email Management</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createEmailModal">
                        <i class="fas fa-plus me-2"></i>Create Email
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createForwarderModal">
                        <i class="fas fa-arrow-right me-2"></i>Create Forwarder
                    </button>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <?php if ($message): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle me-2"></i><?= htmlspecialchars($message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <i class="fas fa-exclamation-triangle me-2"></i><?= htmlspecialchars($error) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Email Accounts -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5><i class="fas fa-envelope me-2"></i>Email Accounts (<?= count($emailAccounts) ?>/<?= $user['email_accounts_limit'] ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Email Address</th>
                                    <th>Domain</th>
                                    <th>Quota</th>
                                    <th>Used</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($emailAccounts)): ?>
                                    <tr>
                                        <td colspan="7" class="text-center text-muted">No email accounts found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emailAccounts as $email): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($email['email']) ?></strong></td>
                                            <td><?= htmlspecialchars($email['domain_name']) ?></td>
                                            <td>
                                                <?php if ($email['quota'] == 0): ?>
                                                    Unlimited
                                                <?php else: ?>
                                                    <?= round($email['quota'] / 1024, 2) ?> GB
                                                <?php endif; ?>
                                            </td>
                                            <td><?= round($email['quota_used'] / 1024, 2) ?> GB</td>
                                            <td>
                                                <span class="badge bg-<?= $email['status'] === 'active' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($email['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($email['created_at'])) ?></td>
                                            <td>
                                                <div class="btn-group" role="group">
                                                    <button class="btn btn-sm btn-outline-primary" onclick="editEmail(<?= $email['id'] ?>)">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" onclick="changeEmailPassword(<?= $email['id'] ?>)">
                                                        <i class="fas fa-key"></i>
                                                    </button>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="delete_email">
                                                        <input type="hidden" name="email_id" value="<?= $email['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this email account?">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Email Forwarders -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-arrow-right me-2"></i>Email Forwarders (<?= count($emailForwarders) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Source Email</th>
                                    <th>Destination Email</th>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($emailForwarders)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">No email forwarders found</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($emailForwarders as $forwarder): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($forwarder['source_email']) ?></strong></td>
                                            <td><?= htmlspecialchars($forwarder['destination_email']) ?></td>
                                            <td><?= htmlspecialchars($forwarder['domain_name']) ?></td>
                                            <td>
                                                <span class="badge bg-<?= $forwarder['status'] === 'active' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($forwarder['status']) ?>
                                                </span>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($forwarder['created_at'])) ?></td>
                                            <td>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_forwarder">
                                                    <input type="hidden" name="forwarder_id" value="<?= $forwarder['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this email forwarder?">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Server Configuration Info -->
            <div class="row mt-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-cog me-2"></i>Email Server Configuration</h5>
                        </div>
                        <div class="card-body">
                            <h6>Incoming Mail (IMAP/POP3):</h6>
                            <ul class="list-unstyled">
                                <li><strong>IMAP Server:</strong> mail.<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?></li>
                                <li><strong>IMAP Port:</strong> 993 (SSL) / 143 (TLS)</li>
                                <li><strong>POP3 Server:</strong> mail.<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?></li>
                                <li><strong>POP3 Port:</strong> 995 (SSL) / 110 (TLS)</li>
                            </ul>
                            
                            <h6>Outgoing Mail (SMTP):</h6>
                            <ul class="list-unstyled">
                                <li><strong>SMTP Server:</strong> mail.<?= htmlspecialchars($_SERVER['HTTP_HOST'] ?? 'yourdomain.com') ?></li>
                                <li><strong>SMTP Port:</strong> 465 (SSL) / 587 (TLS)</li>
                                <li><strong>Authentication:</strong> Required</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-globe me-2"></i>Webmail Access</h5>
                        </div>
                        <div class="card-body">
                            <p>Access your email through the web interface:</p>
                            <a href="/webmail" class="btn btn-primary" target="_blank">
                                <i class="fas fa-envelope me-2"></i>Open Webmail
                            </a>
                            
                            <hr>
                            
                            <h6>Quick Setup Links:</h6>
                            <div class="d-grid gap-2">
                                <a href="#" class="btn btn-outline-secondary">
                                    <i class="fab fa-apple me-2"></i>Download iOS/macOS Profile
                                </a>
                                <a href="#" class="btn btn-outline-secondary">
                                    <i class="fab fa-android me-2"></i>Download Android Settings
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Email Modal -->
    <div class="modal fade" id="createEmailModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Email Account</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_email">
                        
                        <div class="mb-3">
                            <label for="domain_id" class="form-label">Domain</label>
                            <select class="form-select" id="domain_id" name="domain_id" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email_prefix" class="form-label">Email Address</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="email_prefix" name="email_prefix" required>
                                <span class="input-group-text">@domain.com</span>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="quota" class="form-label">Quota (MB, 0 = Unlimited)</label>
                            <input type="number" class="form-control" id="quota" name="quota" value="1024" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Email</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Create Forwarder Modal -->
    <div class="modal fade" id="createForwarderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Email Forwarder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_forwarder">
                        
                        <div class="mb-3">
                            <label for="forwarder_domain_id" class="form-label">Domain</label>
                            <select class="form-select" id="forwarder_domain_id" name="domain_id" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($domains as $domain): ?>
                                    <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label for="source_email" class="form-label">Source Email</label>
                            <input type="email" class="form-control" id="source_email" name="source_email" required>
                            <small class="text-muted">Email address to forward from</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="destination_email" class="form-label">Destination Email</label>
                            <input type="email" class="form-control" id="destination_email" name="destination_email" required>
                            <small class="text-muted">Email address to forward to</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Forwarder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function editEmail(emailId) {
            // Implementation for edit email modal
            console.log('Edit email:', emailId);
        }
        
        function changeEmailPassword(emailId) {
            // Implementation for change password modal
            console.log('Change password for email:', emailId);
        }
    </script>
</body>
</html>