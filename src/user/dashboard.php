<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and user role
if (!$auth->isLoggedIn() || !$auth->hasPermission('user')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Get user statistics
$totalDomains = $db->fetch(
    "SELECT COUNT(*) as count FROM domains WHERE user_id = ?",
    [$user['id']]
)['count'];

$totalEmailAccounts = $db->fetch(
    "SELECT COUNT(*) as count FROM email_accounts WHERE user_id = ?",
    [$user['id']]
)['count'];

$totalDatabases = $db->fetch(
    "SELECT COUNT(*) as count FROM databases WHERE user_id = ?",
    [$user['id']]
)['count'];

$totalFtpAccounts = $db->fetch(
    "SELECT COUNT(*) as count FROM ftp_accounts WHERE user_id = ?",
    [$user['id']]
)['count'];

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT * FROM activity_logs WHERE user_id = ? ORDER BY created_at DESC LIMIT 10",
    [$user['id']]
);

// Get disk and bandwidth usage
$diskUsagePercent = ($user['disk_used'] / $user['disk_quota']) * 100;
$bandwidthUsagePercent = ($user['bandwidth_used'] / $user['bandwidth_quota']) * 100;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Dashboard - Admini Control Panel</title>
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
            <a href="dashboard.php" class="nav-link active">
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
            <a href="email.php" class="nav-link">
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
                <h1>Dashboard</h1>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?= htmlspecialchars($user['username']) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="password.php"><i class="fas fa-key me-2"></i>Change Password</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Account Info -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-info-circle me-2"></i>Account Information</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Username:</strong></td>
                                            <td><?= htmlspecialchars($user['username']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email:</strong></td>
                                            <td><?= htmlspecialchars($user['email']) ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Account Status:</strong></td>
                                            <td>
                                                <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'warning' ?>">
                                                    <?= ucfirst($user['status']) ?>
                                                </span>
                                            </td>
                                        </tr>
                                        <tr>
                                            <td><strong>Created:</strong></td>
                                            <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        </tr>
                                    </table>
                                </div>
                                <div class="col-md-6">
                                    <table class="table table-borderless">
                                        <tr>
                                            <td><strong>Domains:</strong></td>
                                            <td><?= $totalDomains ?> / <?= $user['domains_limit'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Email Accounts:</strong></td>
                                            <td><?= $totalEmailAccounts ?> / <?= $user['email_accounts_limit'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>Databases:</strong></td>
                                            <td><?= $totalDatabases ?> / <?= $user['mysql_databases_limit'] ?></td>
                                        </tr>
                                        <tr>
                                            <td><strong>FTP Accounts:</strong></td>
                                            <td><?= $totalFtpAccounts ?> / <?= $user['ftp_accounts_limit'] ?></td>
                                        </tr>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Resource Usage -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-chart-pie me-2"></i>Disk Usage</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar" style="width: <?= min($diskUsagePercent, 100) ?>%">
                                    <?= round($diskUsagePercent, 1) ?>%
                                </div>
                            </div>
                            <p class="text-muted">
                                <strong><?= round($user['disk_used'] / 1024, 2) ?> GB</strong> of 
                                <strong><?= round($user['disk_quota'] / 1024, 2) ?> GB</strong> used
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-exchange-alt me-2"></i>Bandwidth Usage</h5>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3" style="height: 20px;">
                                <div class="progress-bar bg-success" style="width: <?= min($bandwidthUsagePercent, 100) ?>%">
                                    <?= round($bandwidthUsagePercent, 1) ?>%
                                </div>
                            </div>
                            <p class="text-muted">
                                <strong><?= round($user['bandwidth_used'] / 1024, 2) ?> GB</strong> of 
                                <strong><?= round($user['bandwidth_quota'] / 1024, 2) ?> GB</strong> used this month
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Domains</h5>
                                    <h2 class="text-primary"><?= $totalDomains ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-globe"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Email Accounts</h5>
                                    <h2 class="text-success"><?= $totalEmailAccounts ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-envelope"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Databases</h5>
                                    <h2 class="text-info"><?= $totalDatabases ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-database"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">FTP Accounts</h5>
                                    <h2 class="text-warning"><?= $totalFtpAccounts ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-folder"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions & Recent Activities -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <a href="domains.php?action=create" class="btn btn-primary w-100">
                                        <i class="fas fa-globe me-2"></i>Add Domain
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="email.php?action=create" class="btn btn-success w-100">
                                        <i class="fas fa-envelope me-2"></i>Create Email
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="databases.php?action=create" class="btn btn-info w-100">
                                        <i class="fas fa-database me-2"></i>Create Database
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="filemanager.php" class="btn btn-warning w-100">
                                        <i class="fas fa-file-alt me-2"></i>File Manager
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="ssl.php?action=create" class="btn btn-secondary w-100">
                                        <i class="fas fa-lock me-2"></i>Install SSL
                                    </a>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <a href="backups.php?action=create" class="btn btn-dark w-100">
                                        <i class="fas fa-download me-2"></i>Create Backup
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-history me-2"></i>Recent Activities</h5>
                        </div>
                        <div class="card-body">
                            <div class="activity-list">
                                <?php if (empty($recentActivities)): ?>
                                    <p class="text-muted text-center">No recent activities</p>
                                <?php else: ?>
                                    <?php foreach ($recentActivities as $activity): ?>
                                        <div class="activity-item">
                                            <div class="activity-content">
                                                <strong><?= htmlspecialchars($activity['action']) ?></strong>
                                                <span class="text-muted"><?= htmlspecialchars($activity['description']) ?></span>
                                            </div>
                                            <small class="text-muted"><?= date('M j, Y H:i', strtotime($activity['created_at'])) ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>