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

// Function to check service status
function getServiceStatus($serviceName) {
    $output = [];
    $returnVar = 0;
    exec("systemctl is-active $serviceName 2>/dev/null", $output, $returnVar);
    return $returnVar === 0 ? 'running' : 'stopped';
}

// Function to get service info
function getServiceInfo($serviceName) {
    $output = [];
    exec("systemctl status $serviceName 2>/dev/null", $output);
    return implode("\n", $output);
}

// Handle service operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    $serviceName = $_POST['service_name'] ?? '';
    
    if (in_array($action, ['start', 'stop', 'restart', 'reload']) && $serviceName) {
        $output = [];
        $returnVar = 0;
        exec("sudo systemctl $action $serviceName 2>&1", $output, $returnVar);
        
        if ($returnVar === 0) {
            $success = "Service $serviceName " . ($action === 'stop' ? 'stopped' : ($action === 'start' ? 'started' : $action . 'ed')) . " successfully";
            
            // Update service status in database
            $newStatus = getServiceStatus($serviceName);
            $db->prepare("UPDATE system_services SET status = ?, last_checked = NOW() WHERE service_name = ?")
               ->execute([$newStatus, $serviceName]);
        } else {
            $error = "Failed to $action service $serviceName: " . implode("\n", $output);
        }
    }
    
    if ($action === 'update_auto_start') {
        $autoStart = isset($_POST['auto_start']) ? 1 : 0;
        $db->prepare("UPDATE system_services SET auto_start = ? WHERE service_name = ?")
           ->execute([$autoStart, $serviceName]);
        
        if ($autoStart) {
            exec("sudo systemctl enable $serviceName 2>&1");
        } else {
            exec("sudo systemctl disable $serviceName 2>&1");
        }
        
        $success = "Auto-start setting updated for $serviceName";
    }
}

// Get all services and update their status
$services = $db->fetchAll("SELECT * FROM system_services ORDER BY display_name");

foreach ($services as &$service) {
    $service['status'] = getServiceStatus($service['service_name']);
    // Update database with current status
    $db->prepare("UPDATE system_services SET status = ?, last_checked = NOW() WHERE id = ?")
       ->execute([$service['status'], $service['id']]);
}

// Get system load
$systemLoad = sys_getloadavg();
$uptime = exec("uptime -p");
$memoryInfo = [];
exec("free -m", $memoryInfo);

// Parse memory info
$memoryLine = explode(' ', preg_replace('/\s+/', ' ', $memoryInfo[1]));
$totalMemory = $memoryLine[1];
$usedMemory = $memoryLine[2];
$memoryUsage = round(($usedMemory / $totalMemory) * 100, 1);

// Get disk usage
$diskUsage = exec("df -h / | awk 'NR==2 {print $5}' | sed 's/%//'");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Services - Admini Control Panel</title>
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
                <a href="packages.php" class="nav-link">
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
                <a href="services.php" class="nav-link active">
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
                    <h1><i class="fas fa-cogs me-3"></i>System Services</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">System Services</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="da-btn da-btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>Refresh Status
                    </button>
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

            <!-- System Overview -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon">
                            <i class="fas fa-server"></i>
                        </div>
                        <div class="da-stat-value"><?= count(array_filter($services, function($s) { return $s['status'] === 'running'; })) ?></div>
                        <div class="da-stat-label">Running Services</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="da-stat-value"><?= round($systemLoad[0], 2) ?></div>
                        <div class="da-stat-label">System Load</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--info) 0%, #0284c7 100%);">
                            <i class="fas fa-memory"></i>
                        </div>
                        <div class="da-stat-value"><?= $memoryUsage ?>%</div>
                        <div class="da-stat-label">Memory Usage</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                            <i class="fas fa-hdd"></i>
                        </div>
                        <div class="da-stat-value"><?= $diskUsage ?>%</div>
                        <div class="da-stat-label">Disk Usage</div>
                    </div>
                </div>
            </div>

            <!-- System Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>System Information
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Hostname:</strong></td>
                                    <td><?= gethostname() ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Operating System:</strong></td>
                                    <td><?= php_uname('s') . ' ' . php_uname('r') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Architecture:</strong></td>
                                    <td><?= php_uname('m') ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Uptime:</strong></td>
                                    <td><?= $uptime ?></td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-borderless">
                                <tr>
                                    <td><strong>Load Average:</strong></td>
                                    <td><?= implode(', ', array_map(function($load) { return round($load, 2); }, $systemLoad)) ?></td>
                                </tr>
                                <tr>
                                    <td><strong>Total Memory:</strong></td>
                                    <td><?= $totalMemory ?> MB</td>
                                </tr>
                                <tr>
                                    <td><strong>Used Memory:</strong></td>
                                    <td><?= $usedMemory ?> MB (<?= $memoryUsage ?>%)</td>
                                </tr>
                                <tr>
                                    <td><strong>PHP Version:</strong></td>
                                    <td><?= PHP_VERSION ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Services Grid -->
            <div class="row">
                <?php foreach ($services as $service): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="da-panel h-100">
                            <div class="da-panel-header">
                                <h5 class="da-panel-title">
                                    <i class="fas fa-<?= $service['status'] === 'running' ? 'play-circle text-success' : 'stop-circle text-danger' ?>"></i>
                                    <?= htmlspecialchars($service['display_name']) ?>
                                    <span class="badge <?= $service['status'] === 'running' ? 'bg-success' : 'bg-danger' ?> ms-auto">
                                        <?= ucfirst($service['status']) ?>
                                    </span>
                                </h5>
                            </div>
                            <div class="da-panel-body">
                                <p class="text-muted mb-3"><?= htmlspecialchars($service['description']) ?></p>
                                
                                <div class="service-info mb-3">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Service Name:</span>
                                        <code><?= htmlspecialchars($service['service_name']) ?></code>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <span>Auto Start:</span>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="update_auto_start">
                                            <input type="hidden" name="service_name" value="<?= $service['service_name'] ?>">
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" name="auto_start" 
                                                       <?= $service['auto_start'] ? 'checked' : '' ?>
                                                       onchange="this.form.submit()">
                                            </div>
                                        </form>
                                    </div>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span>Last Checked:</span>
                                        <small class="text-muted">
                                            <?= date('M j, H:i', strtotime($service['last_checked'])) ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="service-actions">
                                    <div class="row g-2">
                                        <?php if ($service['status'] === 'running'): ?>
                                            <div class="col-6">
                                                <form method="POST" class="d-inline w-100">
                                                    <input type="hidden" name="action" value="restart">
                                                    <input type="hidden" name="service_name" value="<?= $service['service_name'] ?>">
                                                    <button type="submit" class="da-btn da-btn-warning da-btn-sm w-100">
                                                        <i class="fas fa-redo"></i>Restart
                                                    </button>
                                                </form>
                                            </div>
                                            <div class="col-6">
                                                <form method="POST" class="d-inline w-100" onsubmit="return confirm('Stop this service?')">
                                                    <input type="hidden" name="action" value="stop">
                                                    <input type="hidden" name="service_name" value="<?= $service['service_name'] ?>">
                                                    <button type="submit" class="da-btn da-btn-danger da-btn-sm w-100">
                                                        <i class="fas fa-stop"></i>Stop
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <div class="col-12">
                                                <form method="POST" class="d-inline w-100">
                                                    <input type="hidden" name="action" value="start">
                                                    <input type="hidden" name="service_name" value="<?= $service['service_name'] ?>">
                                                    <button type="submit" class="da-btn da-btn-success da-btn-sm w-100">
                                                        <i class="fas fa-play"></i>Start Service
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <div class="card-footer">
                                <button type="button" class="da-btn da-btn-secondary da-btn-sm" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#serviceInfoModal"
                                        data-service-name="<?= $service['service_name'] ?>"
                                        data-display-name="<?= htmlspecialchars($service['display_name']) ?>">
                                    <i class="fas fa-info"></i>View Details
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Service Info Modal -->
    <div class="modal fade" id="serviceInfoModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="serviceInfoTitle">Service Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="d-flex justify-content-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                    </div>
                    <pre id="serviceInfoContent" class="mt-3" style="display: none; background: #f8f9fa; padding: 1rem; border-radius: 8px; font-size: 0.875rem;"></pre>
                </div>
                <div class="modal-footer">
                    <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle service info modal
        document.getElementById('serviceInfoModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const serviceName = button.getAttribute('data-service-name');
            const displayName = button.getAttribute('data-display-name');
            
            document.getElementById('serviceInfoTitle').textContent = displayName + ' Details';
            document.getElementById('serviceInfoContent').style.display = 'none';
            document.querySelector('.spinner-border').style.display = 'block';
            
            // Fetch service details
            fetch('services-info.php?service=' + encodeURIComponent(serviceName))
                .then(response => response.text())
                .then(data => {
                    document.querySelector('.spinner-border').style.display = 'none';
                    document.getElementById('serviceInfoContent').textContent = data;
                    document.getElementById('serviceInfoContent').style.display = 'block';
                })
                .catch(error => {
                    document.querySelector('.spinner-border').style.display = 'none';
                    document.getElementById('serviceInfoContent').textContent = 'Error loading service information: ' + error;
                    document.getElementById('serviceInfoContent').style.display = 'block';
                });
        });
    </script>

    <style>
        .service-info {
            background: var(--da-slate-50);
            border-radius: 8px;
            padding: 1rem;
            border: 1px solid var(--border);
        }
        
        .service-actions .da-btn {
            font-size: 0.8rem;
            padding: 0.5rem;
        }
        
        .form-check-input:checked {
            background-color: var(--primary);
            border-color: var(--primary);
        }
    </style>
</body>
</html>