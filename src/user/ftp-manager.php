<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and user role
if (!$auth->isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Handle FTP account operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_ftp') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $directory = $_POST['directory'] ?? '';
        $quota = (int)($_POST['quota'] ?? 0);
        
        if ($username && $password) {
            // Check if username already exists
            $existing = $db->fetch("SELECT id FROM ftp_accounts WHERE username = ?", [$username]);
            
            if (!$existing) {
                // Create home directory path
                $homeDir = "/home/" . $user['username'] . "/public_html" . ($directory ? "/$directory" : "");
                
                $stmt = $db->prepare(
                    "INSERT INTO ftp_accounts (user_id, username, password, home_directory, quota, created_at) 
                     VALUES (?, ?, ?, ?, ?, NOW())"
                );
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$user['id'], $username, $hashedPassword, $homeDir, $quota]);
                
                $success = "FTP account created successfully";
            } else {
                $error = "Username already exists";
            }
        } else {
            $error = "Username and password are required";
        }
    }
    
    if ($action === 'delete_ftp') {
        $ftp_id = $_POST['ftp_id'] ?? '';
        
        $stmt = $db->prepare("DELETE FROM ftp_accounts WHERE id = ? AND user_id = ?");
        $stmt->execute([$ftp_id, $user['id']]);
        $success = "FTP account deleted successfully";
    }
    
    if ($action === 'change_password') {
        $ftp_id = $_POST['ftp_id'] ?? '';
        $new_password = $_POST['new_password'] ?? '';
        
        if ($new_password) {
            $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $db->prepare("UPDATE ftp_accounts SET password = ? WHERE id = ? AND user_id = ?");
            $stmt->execute([$hashedPassword, $ftp_id, $user['id']]);
            $success = "Password changed successfully";
        } else {
            $error = "Password is required";
        }
    }
    
    if ($action === 'toggle_status') {
        $ftp_id = $_POST['ftp_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        $stmt = $db->prepare("UPDATE ftp_accounts SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $ftp_id, $user['id']]);
        $success = "FTP account status updated";
    }
}

// Get user's FTP accounts
$ftpAccounts = $db->fetchAll(
    "SELECT * FROM ftp_accounts WHERE user_id = ? ORDER BY created_at DESC",
    [$user['id']]
);

// Get user's package limits
$packageLimits = $db->fetch(
    "SELECT p.ftp_accounts FROM packages p 
     INNER JOIN users u ON p.id = u.package_id 
     WHERE u.id = ?",
    [$user['id']]
);

$ftpLimit = $packageLimits['ftp_accounts'] ?? 5;
$currentFtpCount = count($ftpAccounts);
$canCreateMore = ($ftpLimit == -1) || ($currentFtpCount < $ftpLimit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FTP Manager - Admini Control Panel</title>
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
                <a href="ftp-manager.php" class="nav-link active">
                    <i class="fas fa-upload"></i>FTP Manager
                </a>
                <a href="subdomains.php" class="nav-link">
                    <i class="fas fa-sitemap"></i>Subdomains
                </a>
                <a href="ssl.php" class="nav-link">
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
                    <h1><i class="fas fa-upload me-3"></i>FTP Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">FTP Manager</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary">
                        <?= $currentFtpCount ?> / <?= $ftpLimit == -1 ? '∞' : $ftpLimit ?> accounts
                    </span>
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

            <!-- FTP Connection Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>FTP Connection Information
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="connection-info">
                                <div class="info-item">
                                    <label>FTP Server:</label>
                                    <div class="value">
                                        <code><?= $_SERVER['HTTP_HOST'] ?></code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('<?= $_SERVER['HTTP_HOST'] ?>')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <label>Port:</label>
                                    <div class="value">
                                        <code>21</code> (Standard) or <code>22</code> (SFTP)
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="connection-info">
                                <div class="info-item">
                                    <label>Passive Mode:</label>
                                    <div class="value">Recommended (Enable in your FTP client)</div>
                                </div>
                                <div class="info-item">
                                    <label>Encryption:</label>
                                    <div class="value">Use SFTP or FTPS for secure connections</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New FTP Account -->
            <?php if ($canCreateMore): ?>
                <div class="da-panel mb-4">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-plus"></i>Create New FTP Account
                        </h5>
                    </div>
                    <div class="da-panel-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create_ftp">
                            <div class="col-md-3">
                                <label class="da-label">Username</label>
                                <input type="text" name="username" class="da-input" required pattern="[a-zA-Z0-9_]+" title="Only letters, numbers, and underscores">
                            </div>
                            <div class="col-md-3">
                                <label class="da-label">Password</label>
                                <div class="input-group">
                                    <input type="password" name="password" id="ftpPassword" class="da-input" required>
                                    <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('ftpPassword')">
                                        <i class="fas fa-random"></i>
                                    </button>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <label class="da-label">Directory (optional)</label>
                                <input type="text" name="directory" class="da-input" placeholder="e.g., images, downloads">
                            </div>
                            <div class="col-md-3">
                                <label class="da-label">Quota (MB, 0 = unlimited)</label>
                                <input type="number" name="quota" class="da-input" min="0" value="0">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="da-btn da-btn-primary">
                                    <i class="fas fa-plus"></i>Create FTP Account
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="da-alert da-alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>You have reached the maximum number of FTP accounts (<?= $ftpLimit ?>) for your package.</div>
                </div>
            <?php endif; ?>

            <!-- FTP Accounts List -->
            <div class="da-panel">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-list"></i>FTP Accounts (<?= count($ftpAccounts) ?>)
                    </h5>
                </div>
                <div class="da-panel-body p-0">
                    <?php if (empty($ftpAccounts)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-upload fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No FTP accounts</h5>
                            <p class="text-muted">Create your first FTP account to get started.</p>
                        </div>
                    <?php else: ?>
                        <div class="da-table">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Username</th>
                                        <th>Home Directory</th>
                                        <th>Quota</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ftpAccounts as $ftp): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($ftp['username']) ?></strong>
                                            <div class="small text-muted">
                                                Server: <?= $_SERVER['HTTP_HOST'] ?>
                                            </div>
                                        </td>
                                        <td>
                                            <code class="small"><?= htmlspecialchars($ftp['home_directory']) ?></code>
                                        </td>
                                        <td>
                                            <?= $ftp['quota'] == 0 ? 'Unlimited' : $ftp['quota'] . ' MB' ?>
                                        </td>
                                        <td>
                                            <?php if ($ftp['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($ftp['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="da-btn da-btn-sm da-btn-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#changePasswordModal" 
                                                        data-ftp-id="<?= $ftp['id'] ?>" 
                                                        data-username="<?= htmlspecialchars($ftp['username']) ?>">
                                                    <i class="fas fa-key"></i>
                                                </button>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="ftp_id" value="<?= $ftp['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $ftp['status'] === 'active' ? 'suspended' : 'active' ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-warning" 
                                                            title="<?= $ftp['status'] === 'active' ? 'Suspend' : 'Activate' ?>">
                                                        <i class="fas fa-<?= $ftp['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this FTP account?')">
                                                    <input type="hidden" name="action" value="delete_ftp">
                                                    <input type="hidden" name="ftp_id" value="<?= $ftp['id'] ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-danger">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
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

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Change FTP Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="change_password">
                        <input type="hidden" name="ftp_id" id="change_ftp_id">
                        
                        <div class="mb-3">
                            <label class="da-label">Username</label>
                            <input type="text" id="change_username" class="da-input" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">New Password</label>
                            <div class="input-group">
                                <input type="password" name="new_password" id="newPassword" class="da-input" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('newPassword')">
                                    <i class="fas fa-random"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="da-btn da-btn-primary">Change Password</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle change password modal
        document.getElementById('changePasswordModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ftpId = button.getAttribute('data-ftp-id');
            const username = button.getAttribute('data-username');
            
            document.getElementById('change_ftp_id').value = ftpId;
            document.getElementById('change_username').value = username;
            document.getElementById('newPassword').value = '';
        });

        // Generate random password
        function generatePassword(inputId) {
            const length = 12;
            const charset = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < length; i++) {
                password += charset.charAt(Math.floor(Math.random() * charset.length));
            }
            document.getElementById(inputId).value = password;
        }

        // Copy to clipboard
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // You could show a toast notification here
                const button = event.target.closest('button');
                const icon = button.querySelector('i');
                icon.className = 'fas fa-check';
                setTimeout(() => {
                    icon.className = 'fas fa-copy';
                }, 2000);
            });
        }
    </script>

    <style>
        .connection-info .info-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border);
        }
        
        .connection-info .info-item:last-child {
            border-bottom: none;
        }
        
        .connection-info label {
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
        }
        
        .connection-info .value {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .input-group .btn {
            border: 2px solid var(--border);
        }
    </style>
</body>
</html>