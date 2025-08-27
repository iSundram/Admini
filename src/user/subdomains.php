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

// Handle subdomain operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_subdomain') {
        $subdomain = $_POST['subdomain'] ?? '';
        $domain_id = $_POST['domain_id'] ?? '';
        $document_root = $_POST['document_root'] ?? '';
        
        if ($subdomain && $domain_id) {
            // Validate subdomain format
            if (!preg_match('/^[a-zA-Z0-9-]+$/', $subdomain)) {
                $error = "Invalid subdomain format. Use only letters, numbers, and hyphens.";
            } else {
                // Check if subdomain already exists
                $existing = $db->fetch(
                    "SELECT id FROM subdomains WHERE subdomain = ? AND domain_id = ?",
                    [$subdomain, $domain_id]
                );
                
                if (!$existing) {
                    // Set document root if not provided
                    if (!$document_root) {
                        $document_root = "/home/" . $user['username'] . "/public_html/$subdomain";
                    }
                    
                    $stmt = $db->prepare(
                        "INSERT INTO subdomains (user_id, subdomain, domain_id, document_root, created_at) 
                         VALUES (?, ?, ?, ?, NOW())"
                    );
                    $stmt->execute([$user['id'], $subdomain, $domain_id, $document_root]);
                    
                    // Create directory if it doesn't exist
                    $fullPath = $document_root;
                    if (!file_exists($fullPath)) {
                        mkdir($fullPath, 0755, true);
                        
                        // Create a simple index.html
                        $domain = $db->fetch("SELECT domain_name FROM domains WHERE id = ?", [$domain_id]);
                        $indexContent = "<!DOCTYPE html>
<html>
<head>
    <title>$subdomain.{$domain['domain_name']}</title>
    <style>
        body { font-family: Arial, sans-serif; text-align: center; padding: 50px; }
        .container { max-width: 600px; margin: 0 auto; }
        h1 { color: #3b82f6; }
    </style>
</head>
<body>
    <div class=\"container\">
        <h1>Welcome to $subdomain.{$domain['domain_name']}</h1>
        <p>Your subdomain has been created successfully!</p>
        <p>Upload your website files to get started.</p>
    </div>
</body>
</html>";
                        file_put_contents($fullPath . '/index.html', $indexContent);
                    }
                    
                    $success = "Subdomain created successfully";
                } else {
                    $error = "Subdomain already exists";
                }
            }
        } else {
            $error = "Subdomain name and domain are required";
        }
    }
    
    if ($action === 'delete_subdomain') {
        $subdomain_id = $_POST['subdomain_id'] ?? '';
        
        // Get subdomain info before deletion
        $subdomain = $db->fetch(
            "SELECT * FROM subdomains WHERE id = ? AND user_id = ?",
            [$subdomain_id, $user['id']]
        );
        
        if ($subdomain) {
            $stmt = $db->prepare("DELETE FROM subdomains WHERE id = ? AND user_id = ?");
            $stmt->execute([$subdomain_id, $user['id']]);
            $success = "Subdomain deleted successfully";
        } else {
            $error = "Subdomain not found";
        }
    }
    
    if ($action === 'toggle_status') {
        $subdomain_id = $_POST['subdomain_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        $stmt = $db->prepare("UPDATE subdomains SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $subdomain_id, $user['id']]);
        $success = "Subdomain status updated";
    }
}

// Get user's domains
$userDomains = $db->fetchAll(
    "SELECT * FROM domains WHERE user_id = ? AND status = 'active' ORDER BY domain_name",
    [$user['id']]
);

// Get user's subdomains with domain information
$subdomains = $db->fetchAll(
    "SELECT s.*, d.domain_name 
     FROM subdomains s 
     INNER JOIN domains d ON s.domain_id = d.id 
     WHERE s.user_id = ? 
     ORDER BY s.created_at DESC",
    [$user['id']]
);

// Get user's package limits
$packageLimits = $db->fetch(
    "SELECT p.subdomains FROM packages p 
     INNER JOIN users u ON p.id = u.package_id 
     WHERE u.id = ?",
    [$user['id']]
);

$subdomainLimit = $packageLimits['subdomains'] ?? 10;
$currentSubdomainCount = count($subdomains);
$canCreateMore = ($subdomainLimit == -1) || ($currentSubdomainCount < $subdomainLimit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subdomain Manager - Admini Control Panel</title>
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
                <a href="subdomains.php" class="nav-link active">
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
                    <h1><i class="fas fa-sitemap me-3"></i>Subdomain Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Subdomains</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary">
                        <?= $currentSubdomainCount ?> / <?= $subdomainLimit == -1 ? '∞' : $subdomainLimit ?> subdomains
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

            <!-- Create New Subdomain -->
            <?php if ($canCreateMore && !empty($userDomains)): ?>
                <div class="da-panel mb-4">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-plus"></i>Create New Subdomain
                        </h5>
                    </div>
                    <div class="da-panel-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create_subdomain">
                            <div class="col-md-4">
                                <label class="da-label">Subdomain Name</label>
                                <input type="text" name="subdomain" class="da-input" required 
                                       pattern="[a-zA-Z0-9-]+" 
                                       title="Only letters, numbers, and hyphens"
                                       placeholder="blog, shop, app">
                            </div>
                            <div class="col-md-4">
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
                            <div class="col-md-4">
                                <label class="da-label">Document Root (optional)</label>
                                <input type="text" name="document_root" class="da-input" 
                                       placeholder="Default: /home/<?= $user['username'] ?>/public_html/subdomain">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="da-btn da-btn-primary">
                                    <i class="fas fa-plus"></i>Create Subdomain
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php elseif (empty($userDomains)): ?>
                <div class="da-alert da-alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>You need to have at least one active domain before creating subdomains.</div>
                </div>
            <?php elseif (!$canCreateMore): ?>
                <div class="da-alert da-alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>You have reached the maximum number of subdomains (<?= $subdomainLimit ?>) for your package.</div>
                </div>
            <?php endif; ?>

            <!-- Subdomain Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>Subdomain Information
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">What are subdomains?</h6>
                            <p>Subdomains are extensions of your main domain that allow you to create separate sections of your website. For example:</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>blog.yourdomain.com</li>
                                <li><i class="fas fa-check text-success me-2"></i>shop.yourdomain.com</li>
                                <li><i class="fas fa-check text-success me-2"></i>support.yourdomain.com</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">Benefits</h6>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-star text-warning me-2"></i>Organize content by category</li>
                                <li><i class="fas fa-star text-warning me-2"></i>Create separate applications</li>
                                <li><i class="fas fa-star text-warning me-2"></i>Improve SEO structure</li>
                                <li><i class="fas fa-star text-warning me-2"></i>Easy to manage and maintain</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Subdomains List -->
            <div class="da-panel">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-list"></i>Your Subdomains (<?= count($subdomains) ?>)
                    </h5>
                </div>
                <div class="da-panel-body p-0">
                    <?php if (empty($subdomains)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-sitemap fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No subdomains created</h5>
                            <p class="text-muted">Create your first subdomain to organize your website content.</p>
                        </div>
                    <?php else: ?>
                        <div class="da-table">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Subdomain</th>
                                        <th>Full URL</th>
                                        <th>Document Root</th>
                                        <th>Status</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($subdomains as $subdomain): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($subdomain['subdomain']) ?></strong>
                                        </td>
                                        <td>
                                            <a href="http://<?= htmlspecialchars($subdomain['subdomain'] . '.' . $subdomain['domain_name']) ?>" 
                                               target="_blank" class="text-decoration-none">
                                                <?= htmlspecialchars($subdomain['subdomain'] . '.' . $subdomain['domain_name']) ?>
                                                <i class="fas fa-external-link-alt ms-1 small"></i>
                                            </a>
                                        </td>
                                        <td>
                                            <code class="small"><?= htmlspecialchars($subdomain['document_root']) ?></code>
                                        </td>
                                        <td>
                                            <?php if ($subdomain['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Suspended</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?= date('M j, Y', strtotime($subdomain['created_at'])) ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="filemanager.php?path=<?= urlencode($subdomain['document_root']) ?>" 
                                                   class="da-btn da-btn-sm da-btn-secondary" title="Manage Files">
                                                    <i class="fas fa-folder"></i>
                                                </a>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="subdomain_id" value="<?= $subdomain['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $subdomain['status'] === 'active' ? 'suspended' : 'active' ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-warning" 
                                                            title="<?= $subdomain['status'] === 'active' ? 'Suspend' : 'Activate' ?>">
                                                        <i class="fas fa-<?= $subdomain['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this subdomain? This action cannot be undone.')">
                                                    <input type="hidden" name="action" value="delete_subdomain">
                                                    <input type="hidden" name="subdomain_id" value="<?= $subdomain['id'] ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>