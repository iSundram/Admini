<?php
require_once '../includes/Database.php';
require_once '../includes/Auth.php';

$auth = new Auth();

// Check authentication and admin role
if (!$auth->isLoggedIn() || !$auth->hasPermission('admin')) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$message = '';
$error = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create':
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $password = $_POST['password'];
                $role = $_POST['role'];
                $packageId = $_POST['package_id'] ?? null;
                $resellerId = $_POST['reseller_id'] ?? null;
                
                // Validate input
                if (empty($username) || empty($email) || empty($password)) {
                    throw new Exception('Please fill all required fields');
                }
                
                if (strlen($password) < 8) {
                    throw new Exception('Password must be at least 8 characters long');
                }
                
                // Check if username or email already exists
                $existing = $db->fetch(
                    "SELECT id FROM users WHERE username = ? OR email = ?",
                    [$username, $email]
                );
                
                if ($existing) {
                    throw new Exception('Username or email already exists');
                }
                
                // Hash password
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                
                // Get package details for resource limits
                $package = null;
                if ($packageId) {
                    $package = $db->fetch("SELECT * FROM packages WHERE id = ?", [$packageId]);
                }
                
                // Insert user
                $db->query(
                    "INSERT INTO users (username, email, password, role, status, reseller_id, package_id, 
                     disk_quota, bandwidth_quota, domains_limit, subdomains_limit, email_accounts_limit, 
                     mysql_databases_limit, ftp_accounts_limit, created_at) 
                     VALUES (?, ?, ?, ?, 'active', ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    [
                        $username, $email, $hashedPassword, $role, $resellerId, $packageId,
                        $package['disk_quota'] ?? 1024,
                        $package['bandwidth_quota'] ?? 10240,
                        $package['domains_limit'] ?? 1,
                        $package['subdomains_limit'] ?? 10,
                        $package['email_accounts_limit'] ?? 10,
                        $package['mysql_databases_limit'] ?? 5,
                        $package['ftp_accounts_limit'] ?? 5
                    ]
                );
                
                $message = 'User created successfully';
                break;
                
            case 'update':
                $userId = (int)$_POST['user_id'];
                $username = trim($_POST['username']);
                $email = trim($_POST['email']);
                $role = $_POST['role'];
                $status = $_POST['status'];
                $packageId = $_POST['package_id'] ?? null;
                
                // Update user
                $db->query(
                    "UPDATE users SET username = ?, email = ?, role = ?, status = ?, package_id = ?, updated_at = NOW() WHERE id = ?",
                    [$username, $email, $role, $status, $packageId, $userId]
                );
                
                $message = 'User updated successfully';
                break;
                
            case 'delete':
                $userId = (int)$_POST['user_id'];
                
                // Delete user (will cascade to related records)
                $db->query("DELETE FROM users WHERE id = ? AND role != 'admin'", [$userId]);
                
                $message = 'User deleted successfully';
                break;
                
            case 'suspend':
                $userId = (int)$_POST['user_id'];
                $db->query("UPDATE users SET status = 'suspended', updated_at = NOW() WHERE id = ?", [$userId]);
                $message = 'User suspended successfully';
                break;
                
            case 'unsuspend':
                $userId = (int)$_POST['user_id'];
                $db->query("UPDATE users SET status = 'active', updated_at = NOW() WHERE id = ?", [$userId]);
                $message = 'User unsuspended successfully';
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get users list
$search = $_GET['search'] ?? '';
$role = $_GET['role'] ?? '';
$status = $_GET['status'] ?? '';

$whereConditions = ["role != 'admin'"];
$params = [];

if ($search) {
    $whereConditions[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role) {
    $whereConditions[] = "role = ?";
    $params[] = $role;
}

if ($status) {
    $whereConditions[] = "status = ?";
    $params[] = $status;
}

$whereClause = implode(' AND ', $whereConditions);

$users = $db->fetchAll(
    "SELECT u.*, p.name as package_name, r.username as reseller_name 
     FROM users u 
     LEFT JOIN packages p ON u.package_id = p.id 
     LEFT JOIN users r ON u.reseller_id = r.id 
     WHERE $whereClause 
     ORDER BY u.created_at DESC",
    $params
);

// Get packages for dropdowns
$packages = $db->fetchAll("SELECT * FROM packages WHERE status = 'active' ORDER BY name");

// Get resellers for dropdowns
$resellers = $db->fetchAll("SELECT * FROM users WHERE role = 'reseller' AND status = 'active' ORDER BY username");

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Admini Control Panel</title>
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
            <a href="dashboard.php" class="nav-link">
                <i class="fas fa-tachometer-alt"></i>Dashboard
            </a>
            <a href="users.php" class="nav-link active">
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
                <h1>User Management</h1>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                    <i class="fas fa-user-plus me-2"></i>Create User
                </button>
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

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-4">
                            <input type="text" class="form-control" name="search" placeholder="Search users..." value="<?= htmlspecialchars($search) ?>">
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="role">
                                <option value="">All Roles</option>
                                <option value="reseller" <?= $role === 'reseller' ? 'selected' : '' ?>>Reseller</option>
                                <option value="user" <?= $role === 'user' ? 'selected' : '' ?>>User</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <select class="form-select" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                                <option value="suspended" <?= $status === 'suspended' ? 'selected' : '' ?>>Suspended</option>
                                <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="fas fa-search me-2"></i>Filter
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Users Table -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-users me-2"></i>Users (<?= count($users) ?>)</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Email</th>
                                    <th>Role</th>
                                    <th>Package</th>
                                    <th>Reseller</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($user['username']) ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($user['email']) ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['role'] === 'reseller' ? 'info' : 'secondary' ?>">
                                                <?= ucfirst($user['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars($user['package_name'] ?? 'None') ?></td>
                                        <td><?= htmlspecialchars($user['reseller_name'] ?? '-') ?></td>
                                        <td>
                                            <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : ($user['status'] === 'suspended' ? 'warning' : 'danger') ?>">
                                                <?= ucfirst($user['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('M j, Y', strtotime($user['created_at'])) ?></td>
                                        <td>
                                            <div class="btn-group" role="group">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($user['status'] === 'active'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="suspend">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-warning" data-confirm="Suspend this user?">
                                                            <i class="fas fa-pause"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="unsuspend">
                                                        <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="user_id" value="<?= $user['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete this user? This action cannot be undone.">
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
                </div>
            </div>
        </div>
    </div>

    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="username" class="form-label">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="email" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="email" name="email" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="password" class="form-label">Password</label>
                                    <input type="password" class="form-control" id="password" name="password" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="role" class="form-label">Role</label>
                                    <select class="form-select" id="role" name="role" required>
                                        <option value="user">User</option>
                                        <option value="reseller">Reseller</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="package_id" class="form-label">Package</label>
                                    <select class="form-select" id="package_id" name="package_id">
                                        <option value="">Select Package</option>
                                        <?php foreach ($packages as $package): ?>
                                            <option value="<?= $package['id'] ?>"><?= htmlspecialchars($package['name']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="reseller_id" class="form-label">Reseller (Optional)</label>
                                    <select class="form-select" id="reseller_id" name="reseller_id">
                                        <option value="">No Reseller</option>
                                        <?php foreach ($resellers as $reseller): ?>
                                            <option value="<?= $reseller['id'] ?>"><?= htmlspecialchars($reseller['username']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function editUser(userId) {
            // Implementation for edit user modal
            console.log('Edit user:', userId);
        }
    </script>
</body>
</html>