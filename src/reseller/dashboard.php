<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and reseller role
if (!$auth->isLoggedIn() || !$auth->hasPermission('reseller')) {
    header('Location: ../login.php');
    exit;
}

$user = $auth->getCurrentUser();
$db = Database::getInstance();

// Get reseller statistics
$totalUsers = $db->fetch(
    "SELECT COUNT(*) as count FROM users WHERE reseller_id = ? AND role = 'user'",
    [$user['id']]
)['count'];

$totalDomains = $db->fetch(
    "SELECT COUNT(*) as count FROM domains d 
     JOIN users u ON d.user_id = u.id 
     WHERE u.reseller_id = ?",
    [$user['id']]
)['count'];

$totalEmailAccounts = $db->fetch(
    "SELECT COUNT(*) as count FROM email_accounts ea 
     JOIN users u ON ea.user_id = u.id 
     WHERE u.reseller_id = ?",
    [$user['id']]
)['count'];

$totalDatabases = $db->fetch(
    "SELECT COUNT(*) as count FROM databases db 
     JOIN users u ON db.user_id = u.id 
     WHERE u.reseller_id = ?",
    [$user['id']]
)['count'];

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.username FROM activity_logs al 
     JOIN users u ON al.user_id = u.id 
     WHERE u.reseller_id = ? OR al.user_id = ?
     ORDER BY al.created_at DESC LIMIT 10",
    [$user['id'], $user['id']]
);

// Get resource usage
$diskUsed = $db->fetch(
    "SELECT SUM(disk_used) as total FROM users WHERE reseller_id = ?",
    [$user['id']]
)['total'] ?? 0;

$bandwidthUsed = $db->fetch(
    "SELECT SUM(bandwidth_used) as total FROM users WHERE reseller_id = ?",
    [$user['id']]
)['total'] ?? 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reseller Dashboard - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>Reseller Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="customers.php" class="nav-link">
                <i class="fas fa-users"></i>Customers
            </a>
            <a href="packages.php" class="nav-link">
                <i class="fas fa-box"></i>Packages
            </a>
            <a href="domains.php" class="nav-link">
                <i class="fas fa-globe"></i>Domains
            </a>
            <a href="email.php" class="nav-link">
                <i class="fas fa-envelope"></i>Email
            </a>
            <a href="databases.php" class="nav-link">
                <i class="fas fa-database"></i>Databases
            </a>
            <a href="backups.php" class="nav-link">
                <i class="fas fa-download"></i>Backups
            </a>
            <a href="statistics.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Statistics
            </a>
            <a href="billing.php" class="nav-link">
                <i class="fas fa-dollar-sign"></i>Billing
            </a>
            <a href="settings.php" class="nav-link">
                <i class="fas fa-cog"></i>Settings
            </a>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Top Bar -->
        <div class="top-bar">
            <div class="d-flex justify-content-between align-items-center">
                <h1>Reseller Dashboard</h1>
                <div class="dropdown">
                    <button class="btn btn-outline-primary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user me-2"></i><?= htmlspecialchars($user['username']) ?>
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="profile.php"><i class="fas fa-user me-2"></i>Profile</a></li>
                        <li><a class="dropdown-item" href="settings.php"><i class="fas fa-cog me-2"></i>Settings</a></li>
                        <li><hr class="dropdown-divider"></li>
                        <li><a class="dropdown-item" href="../logout.php"><i class="fas fa-sign-out-alt me-2"></i>Logout</a></li>
                    </ul>
                </div>
            </div>
        </div>

        <div class="container-fluid">
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card stat-card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between">
                                <div>
                                    <h5 class="card-title">Total Customers</h5>
                                    <h2 class="text-primary"><?= $totalUsers ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-users"></i>
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
                                    <h5 class="card-title">Total Domains</h5>
                                    <h2 class="text-success"><?= $totalDomains ?></h2>
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
                                    <h2 class="text-info"><?= $totalEmailAccounts ?></h2>
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
                                    <h2 class="text-warning"><?= $totalDatabases ?></h2>
                                </div>
                                <div class="stat-icon">
                                    <i class="fas fa-database"></i>
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
                            <h5><i class="fas fa-chart-pie me-2"></i>Resource Usage</h5>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">Disk Space Usage</label>
                                <div class="progress">
                                    <div class="progress-bar bg-primary" style="width: <?= min(($diskUsed / $user['disk_quota']) * 100, 100) ?>%">
                                        <?= round($diskUsed / 1024, 2) ?> GB / <?= round($user['disk_quota'] / 1024, 2) ?> GB
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Bandwidth Usage</label>
                                <div class="progress">
                                    <div class="progress-bar bg-success" style="width: <?= min(($bandwidthUsed / $user['bandwidth_quota']) * 100, 100) ?>%">
                                        <?= round($bandwidthUsed / 1024, 2) ?> GB / <?= round($user['bandwidth_quota'] / 1024, 2) ?> GB
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Customers</label>
                                <div class="progress">
                                    <div class="progress-bar bg-info" style="width: 60%">
                                        <?= $totalUsers ?> / Unlimited
                                    </div>
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
                                <?php foreach ($recentActivities as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-content">
                                            <strong><?= htmlspecialchars($activity['username']) ?></strong>
                                            <span class="text-muted"><?= htmlspecialchars($activity['description']) ?></span>
                                        </div>
                                        <small class="text-muted"><?= date('M j, Y H:i', strtotime($activity['created_at'])) ?></small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-bolt me-2"></i>Quick Actions</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-3 mb-3">
                                    <a href="customers.php?action=create" class="btn btn-primary w-100">
                                        <i class="fas fa-user-plus me-2"></i>Create Customer
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="packages.php?action=create" class="btn btn-success w-100">
                                        <i class="fas fa-box me-2"></i>Create Package
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="domains.php?action=create" class="btn btn-info w-100">
                                        <i class="fas fa-globe me-2"></i>Add Domain
                                    </a>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <a href="backups.php?action=create" class="btn btn-warning w-100">
                                        <i class="fas fa-download me-2"></i>Create Backup
                                    </a>
                                </div>
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