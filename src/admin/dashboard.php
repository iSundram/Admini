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

// Get statistics
$totalUsers = $db->fetch("SELECT COUNT(*) as count FROM users WHERE role != 'admin'")['count'];
$totalDomains = $db->fetch("SELECT COUNT(*) as count FROM domains")['count'];
$totalEmailAccounts = $db->fetch("SELECT COUNT(*) as count FROM email_accounts")['count'];
$totalDatabases = $db->fetch("SELECT COUNT(*) as count FROM databases")['count'];

// Get recent activities
$recentActivities = $db->fetchAll(
    "SELECT al.*, u.username FROM activity_logs al 
     LEFT JOIN users u ON al.user_id = u.id 
     ORDER BY al.created_at DESC LIMIT 10"
);

// Get system info
$systemLoad = sys_getloadavg();
$diskUsage = disk_free_space('/') / disk_total_space('/') * 100;
$memoryUsage = memory_get_usage(true) / 1024 / 1024; // MB

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>Admin Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link active">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="users.php" class="nav-link">
                <i class="fas fa-users"></i>Users
            </a>
            <a href="resellers.php" class="nav-link">
                <i class="fas fa-user-tie"></i>Resellers
            </a>
            <a href="packages.php" class="nav-link">
                <i class="fas fa-box"></i>Packages
            </a>
            <a href="domains.php" class="nav-link">
                <i class="fas fa-globe"></i>Domains
            </a>
            <a href="dns.php" class="nav-link">
                <i class="fas fa-network-wired"></i>DNS Management
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
            <a href="ssl.php" class="nav-link">
                <i class="fas fa-lock"></i>SSL Certificates
            </a>
            <a href="statistics.php" class="nav-link">
                <i class="fas fa-chart-bar"></i>Statistics
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
                <h1>Dashboard</h1>
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

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Total Users</h5>
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

        <!-- System Information -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-server me-2"></i>System Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label">CPU Load Average</label>
                            <div class="progress">
                                <div class="progress-bar" style="width: <?= min($systemLoad[0] * 100, 100) ?>%">
                                    <?= round($systemLoad[0], 2) ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Disk Usage</label>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: <?= 100 - $diskUsage ?>%">
                                    <?= round(100 - $diskUsage, 1) ?>% Free
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Memory Usage</label>
                            <div class="progress">
                                <div class="progress-bar bg-info" style="width: 50%">
                                    <?= round($memoryUsage, 1) ?> MB
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
                                        <strong><?= htmlspecialchars($activity['username'] ?? 'System') ?></strong>
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
                                <a href="users.php?action=create" class="btn btn-primary w-100">
                                    <i class="fas fa-user-plus me-2"></i>Create User
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="resellers.php?action=create" class="btn btn-success w-100">
                                    <i class="fas fa-user-tie me-2"></i>Create Reseller
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="packages.php?action=create" class="btn btn-info w-100">
                                    <i class="fas fa-box me-2"></i>Create Package
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
</body>
</html>