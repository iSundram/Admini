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

// Handle IP operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_ip') {
        $ip_address = $_POST['ip_address'] ?? '';
        $netmask = $_POST['netmask'] ?? '255.255.255.0';
        $gateway = $_POST['gateway'] ?? '';
        $assigned_to = $_POST['assigned_to'] ?? null;
        
        if (filter_var($ip_address, FILTER_VALIDATE_IP)) {
            $stmt = $db->prepare(
                "INSERT INTO ip_addresses (ip_address, netmask, gateway, assigned_to, status, created_at) 
                 VALUES (?, ?, ?, ?, 'available', NOW())"
            );
            $stmt->execute([$ip_address, $netmask, $gateway, $assigned_to]);
            $success = "IP address added successfully";
        } else {
            $error = "Invalid IP address format";
        }
    }
    
    if ($action === 'assign_ip') {
        $ip_id = $_POST['ip_id'] ?? '';
        $user_id = $_POST['user_id'] ?? '';
        
        $stmt = $db->prepare("UPDATE ip_addresses SET assigned_to = ?, status = 'assigned' WHERE id = ?");
        $stmt->execute([$user_id, $ip_id]);
        $success = "IP address assigned successfully";
    }
    
    if ($action === 'delete_ip') {
        $ip_id = $_POST['ip_id'] ?? '';
        
        $stmt = $db->prepare("DELETE FROM ip_addresses WHERE id = ? AND status = 'available'");
        $stmt->execute([$ip_id]);
        $success = "IP address deleted successfully";
    }
}

// Get all IP addresses
$ipAddresses = $db->fetchAll(
    "SELECT ip.*, u.username, u.domain as user_domain 
     FROM ip_addresses ip 
     LEFT JOIN users u ON ip.assigned_to = u.id 
     ORDER BY INET_ATON(ip.ip_address)"
);

// Get available users for assignment
$availableUsers = $db->fetchAll(
    "SELECT id, username, domain FROM users WHERE role IN ('user', 'reseller') ORDER BY username"
);

// Get IP statistics
$totalIPs = count($ipAddresses);
$assignedIPs = count(array_filter($ipAddresses, function($ip) { return $ip['status'] === 'assigned'; }));
$availableIPs = $totalIPs - $assignedIPs;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IP Manager - Admini Control Panel</title>
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
                <a href="ip-manager.php" class="nav-link active">
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
                    <h1><i class="fas fa-network-wired me-3"></i>IP Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">IP Manager</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary me-2">Total: <?= $totalIPs ?></span>
                    <span class="badge bg-success me-2">Available: <?= $availableIPs ?></span>
                    <span class="badge bg-warning">Assigned: <?= $assignedIPs ?></span>
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

            <!-- IP Statistics -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon">
                            <i class="fas fa-network-wired"></i>
                        </div>
                        <div class="da-stat-value"><?= $totalIPs ?></div>
                        <div class="da-stat-label">Total IP Addresses</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="da-stat-value"><?= $availableIPs ?></div>
                        <div class="da-stat-label">Available</div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                            <i class="fas fa-user"></i>
                        </div>
                        <div class="da-stat-value"><?= $assignedIPs ?></div>
                        <div class="da-stat-label">Assigned</div>
                    </div>
                </div>
            </div>

            <!-- Add New IP -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-plus"></i>Add New IP Address
                    </h5>
                </div>
                <div class="da-panel-body">
                    <form method="POST" class="row g-3">
                        <input type="hidden" name="action" value="add_ip">
                        <div class="col-md-3">
                            <label class="da-label">IP Address</label>
                            <input type="text" name="ip_address" class="da-input" placeholder="192.168.1.100" required>
                        </div>
                        <div class="col-md-3">
                            <label class="da-label">Netmask</label>
                            <input type="text" name="netmask" class="da-input" value="255.255.255.0">
                        </div>
                        <div class="col-md-3">
                            <label class="da-label">Gateway</label>
                            <input type="text" name="gateway" class="da-input" placeholder="192.168.1.1">
                        </div>
                        <div class="col-md-3">
                            <label class="da-label">&nbsp;</label>
                            <button type="submit" class="da-btn da-btn-primary w-100">
                                <i class="fas fa-plus"></i>Add IP
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- IP Addresses Table -->
            <div class="da-panel">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-list"></i>IP Addresses
                    </h5>
                </div>
                <div class="da-panel-body p-0">
                    <div class="da-table">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>IP Address</th>
                                    <th>Netmask</th>
                                    <th>Gateway</th>
                                    <th>Status</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($ipAddresses as $ip): ?>
                                <tr>
                                    <td>
                                        <code class="text-primary"><?= htmlspecialchars($ip['ip_address']) ?></code>
                                    </td>
                                    <td><?= htmlspecialchars($ip['netmask']) ?></td>
                                    <td><?= htmlspecialchars($ip['gateway'] ?: '-') ?></td>
                                    <td>
                                        <?php if ($ip['status'] === 'available'): ?>
                                            <span class="badge bg-success">Available</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Assigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($ip['username']): ?>
                                            <strong><?= htmlspecialchars($ip['username']) ?></strong>
                                            <?php if ($ip['user_domain']): ?>
                                                <br><small class="text-muted"><?= htmlspecialchars($ip['user_domain']) ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">Unassigned</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <?php if ($ip['status'] === 'available'): ?>
                                                <button type="button" class="da-btn da-btn-sm da-btn-primary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#assignModal" 
                                                        data-ip-id="<?= $ip['id'] ?>" 
                                                        data-ip-address="<?= htmlspecialchars($ip['ip_address']) ?>">
                                                    <i class="fas fa-user-plus"></i>Assign
                                                </button>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this IP address?')">
                                                    <input type="hidden" name="action" value="delete_ip">
                                                    <input type="hidden" name="ip_id" value="<?= $ip['id'] ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-danger">
                                                        <i class="fas fa-trash"></i>Delete
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Unassign this IP address?')">
                                                    <input type="hidden" name="action" value="assign_ip">
                                                    <input type="hidden" name="ip_id" value="<?= $ip['id'] ?>">
                                                    <input type="hidden" name="user_id" value="">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-secondary">
                                                        <i class="fas fa-user-minus"></i>Unassign
                                                    </button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Assign IP Modal -->
    <div class="modal fade" id="assignModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Assign IP Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="assign_ip">
                        <input type="hidden" name="ip_id" id="assign_ip_id">
                        
                        <div class="mb-3">
                            <label class="da-label">IP Address</label>
                            <input type="text" id="assign_ip_address" class="da-input" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">Assign To User</label>
                            <select name="user_id" class="da-select" required>
                                <option value="">Select User</option>
                                <?php foreach ($availableUsers as $user): ?>
                                    <option value="<?= $user['id'] ?>">
                                        <?= htmlspecialchars($user['username']) ?>
                                        <?php if ($user['domain']): ?>
                                            (<?= htmlspecialchars($user['domain']) ?>)
                                        <?php endif; ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="da-btn da-btn-primary">Assign IP</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle assign modal
        document.getElementById('assignModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const ipId = button.getAttribute('data-ip-id');
            const ipAddress = button.getAttribute('data-ip-address');
            
            document.getElementById('assign_ip_id').value = ipId;
            document.getElementById('assign_ip_address').value = ipAddress;
        });
    </script>
</body>
</html>