<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and admin role
if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Handle package operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_package') {
        $name = $_POST['name'] ?? '';
        $disk_quota = (int)($_POST['disk_quota'] ?? 0);
        $bandwidth_quota = (int)($_POST['bandwidth_quota'] ?? 0);
        $email_accounts = (int)($_POST['email_accounts'] ?? 0);
        $databases = (int)($_POST['databases'] ?? 0);
        $subdomains = (int)($_POST['subdomains'] ?? 0);
        $addon_domains = (int)($_POST['addon_domains'] ?? 0);
        $ftp_accounts = (int)($_POST['ftp_accounts'] ?? 0);
        $ssl_certificates = (int)($_POST['ssl_certificates'] ?? 0);
        $price = (float)($_POST['price'] ?? 0);
        $features = $_POST['features'] ?? [];
        
        if ($name) {
            $stmt = $db->prepare(
                "INSERT INTO packages (name, disk_quota, bandwidth_quota, email_accounts, databases, 
                 subdomains, addon_domains, ftp_accounts, ssl_certificates, price, features, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([
                $name, $disk_quota, $bandwidth_quota, $email_accounts, $databases,
                $subdomains, $addon_domains, $ftp_accounts, $ssl_certificates, $price,
                json_encode($features)
            ]);
            $success = "Package created successfully";
        }
    }
    
    if ($action === 'delete_package') {
        $package_id = $_POST['package_id'] ?? '';
        
        // Check if package is in use
        $inUse = $db->fetch("SELECT COUNT(*) as count FROM users WHERE package_id = ?", [$package_id]);
        
        if ($inUse['count'] > 0) {
            $error = "Cannot delete package - it is currently assigned to users";
        } else {
            $stmt = $db->prepare("DELETE FROM packages WHERE id = ?");
            $stmt->execute([$package_id]);
            $success = "Package deleted successfully";
        }
    }
}

// Get all packages
$packages = $db->fetchAll("SELECT * FROM packages ORDER BY name");

// Get package usage statistics
$packageStats = [];
foreach ($packages as $package) {
    $usage = $db->fetch(
        "SELECT COUNT(*) as user_count FROM users WHERE package_id = ?", 
        [$package['id']]
    );
    $packageStats[$package['id']] = $usage['user_count'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Package Manager - Admini Control Panel</title>
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
            <small>Admin Panel</small>
        </div>
        <nav class="sidebar-nav">
            <div class="nav-section">
                <div class="nav-section-title">Dashboard</div>
                <a href="dashboard.php" class="nav-link">
                    <i class="fas fa-tachometer-alt"></i>Overview
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Management</div>
                <a href="users.php" class="nav-link">
                    <i class="fas fa-users"></i>Users & Resellers
                </a>
                <a href="packages.php" class="nav-link active">
                    <i class="fas fa-box"></i>Packages
                </a>
                <a href="dns.php" class="nav-link">
                    <i class="fas fa-globe"></i>DNS Administration
                </a>
                <a href="ip-manager.php" class="nav-link">
                    <i class="fas fa-network-wired"></i>IP Manager
                </a>
                <a href="mail-queue.php" class="nav-link">
                    <i class="fas fa-envelope"></i>Mail Queue
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <a href="services.php" class="nav-link">
                    <i class="fas fa-cogs"></i>Services
                </a>
                <a href="statistics.php" class="nav-link">
                    <i class="fas fa-chart-bar"></i>Statistics
                </a>
                <a href="security.php" class="nav-link">
                    <i class="fas fa-shield-alt"></i>Security
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
                    <h1><i class="fas fa-box me-3"></i>Package Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Package Manager</li>
                        </ol>
                    </nav>
                </div>
                <button type="button" class="da-btn da-btn-primary" data-bs-toggle="modal" data-bs-target="#createPackageModal">
                    <i class="fas fa-plus"></i>Create Package
                </button>
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

            <!-- Package Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="da-stat-value"><?= count($packages) ?></div>
                        <div class="da-stat-label">Total Packages</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="da-stat-value"><?= array_sum($packageStats) ?></div>
                        <div class="da-stat-label">Active Users</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--info) 0%, #0284c7 100%);">
                            <i class="fas fa-star"></i>
                        </div>
                        <div class="da-stat-value">
                            <?php 
                            $popularPackage = '';
                            $maxUsers = 0;
                            foreach ($packageStats as $packageId => $userCount) {
                                if ($userCount > $maxUsers) {
                                    $maxUsers = $userCount;
                                    foreach ($packages as $p) {
                                        if ($p['id'] == $packageId) {
                                            $popularPackage = $p['name'];
                                            break;
                                        }
                                    }
                                }
                            }
                            echo $maxUsers;
                            ?>
                        </div>
                        <div class="da-stat-label">Most Popular</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="da-stat-value">
                            $<?= number_format(array_sum(array_column($packages, 'price')), 0) ?>
                        </div>
                        <div class="da-stat-label">Total Revenue</div>
                    </div>
                </div>
            </div>

            <!-- Packages Grid -->
            <div class="row">
                <?php foreach ($packages as $package): ?>
                    <?php 
                    $features = json_decode($package['features'], true) ?: [];
                    $userCount = $packageStats[$package['id']];
                    ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="da-panel h-100">
                            <div class="da-panel-header">
                                <h5 class="da-panel-title">
                                    <i class="fas fa-box"></i>
                                    <?= htmlspecialchars($package['name']) ?>
                                    <span class="badge bg-primary ms-auto"><?= $userCount ?> users</span>
                                </h5>
                            </div>
                            <div class="da-panel-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span class="text-gradient fs-3 fw-bold">
                                            $<?= number_format($package['price'], 2) ?>
                                        </span>
                                        <small class="text-muted">/month</small>
                                    </div>
                                </div>

                                <div class="package-features">
                                    <div class="row g-2 mb-3">
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-hdd text-primary"></i>
                                                <div>
                                                    <strong><?= $package['disk_quota'] == -1 ? 'Unlimited' : $package['disk_quota'] . ' MB' ?></strong>
                                                    <small>Disk Space</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-tachometer-alt text-success"></i>
                                                <div>
                                                    <strong><?= $package['bandwidth_quota'] == -1 ? 'Unlimited' : $package['bandwidth_quota'] . ' MB' ?></strong>
                                                    <small>Bandwidth</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-envelope text-info"></i>
                                                <div>
                                                    <strong><?= $package['email_accounts'] == -1 ? 'Unlimited' : $package['email_accounts'] ?></strong>
                                                    <small>Email Accounts</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-database text-warning"></i>
                                                <div>
                                                    <strong><?= $package['databases'] == -1 ? 'Unlimited' : $package['databases'] ?></strong>
                                                    <small>Databases</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-sitemap text-purple"></i>
                                                <div>
                                                    <strong><?= $package['subdomains'] == -1 ? 'Unlimited' : $package['subdomains'] ?></strong>
                                                    <small>Subdomains</small>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="feature-item">
                                                <i class="fas fa-shield-alt text-danger"></i>
                                                <div>
                                                    <strong><?= $package['ssl_certificates'] == -1 ? 'Unlimited' : $package['ssl_certificates'] ?></strong>
                                                    <small>SSL Certificates</small>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <?php if (!empty($features)): ?>
                                        <div class="features-list">
                                            <h6 class="mb-2">Features:</h6>
                                            <?php foreach ($features as $feature): ?>
                                                <span class="badge bg-light text-dark me-1 mb-1">
                                                    <i class="fas fa-check text-success me-1"></i>
                                                    <?= htmlspecialchars($feature) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="card-footer">
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="da-btn da-btn-secondary da-btn-sm" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#editPackageModal"
                                            data-package='<?= json_encode($package) ?>'>
                                        <i class="fas fa-edit"></i>Edit
                                    </button>
                                    <?php if ($userCount == 0): ?>
                                        <form method="POST" class="d-inline" onsubmit="return confirm('Delete this package?')">
                                            <input type="hidden" name="action" value="delete_package">
                                            <input type="hidden" name="package_id" value="<?= $package['id'] ?>">
                                            <button type="submit" class="da-btn da-btn-danger da-btn-sm">
                                                <i class="fas fa-trash"></i>Delete
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small">In use</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Create Package Modal -->
    <div class="modal fade" id="createPackageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Package</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_package">
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="da-label">Package Name</label>
                                <input type="text" name="name" class="da-input" required>
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Price (Monthly)</label>
                                <input type="number" name="price" class="da-input" step="0.01" min="0">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Disk Quota (MB)</label>
                                <input type="number" name="disk_quota" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Bandwidth Quota (MB)</label>
                                <input type="number" name="bandwidth_quota" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Email Accounts</label>
                                <input type="number" name="email_accounts" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Databases</label>
                                <input type="number" name="databases" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Subdomains</label>
                                <input type="number" name="subdomains" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Addon Domains</label>
                                <input type="number" name="addon_domains" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">FTP Accounts</label>
                                <input type="number" name="ftp_accounts" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">SSL Certificates</label>
                                <input type="number" name="ssl_certificates" class="da-input" min="-1" placeholder="-1 for unlimited">
                            </div>
                            <div class="col-12">
                                <label class="da-label">Features</label>
                                <div class="feature-checkboxes">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="PHP Support" id="php">
                                                <label class="form-check-label" for="php">PHP Support</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="MySQL Support" id="mysql">
                                                <label class="form-check-label" for="mysql">MySQL Support</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="SSL Support" id="ssl">
                                                <label class="form-check-label" for="ssl">SSL Support</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="Cron Jobs" id="cron">
                                                <label class="form-check-label" for="cron">Cron Jobs</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="File Manager" id="filemanager">
                                                <label class="form-check-label" for="filemanager">File Manager</label>
                                            </div>
                                        </div>
                                        <div class="col-md-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="features[]" value="Backup Tools" id="backup">
                                                <label class="form-check-label" for="backup">Backup Tools</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="da-btn da-btn-primary">Create Package</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <style>
        .feature-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.5rem;
            background: var(--da-slate-50);
            border-radius: 6px;
            border: 1px solid var(--border);
        }
        
        .feature-item i {
            width: 20px;
            text-align: center;
        }
        
        .feature-item div {
            display: flex;
            flex-direction: column;
            line-height: 1.2;
        }
        
        .feature-item strong {
            font-size: 0.875rem;
        }
        
        .feature-item small {
            color: var(--text-secondary);
            font-size: 0.75rem;
        }
        
        .features-list .badge {
            font-size: 0.75rem;
        }
        
        .text-purple {
            color: #8b5cf6 !important;
        }
    </style>
</body>
</html>