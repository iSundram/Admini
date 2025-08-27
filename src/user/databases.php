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

// Handle database operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_database') {
        $database_name = $_POST['database_name'] ?? '';
        $database_type = $_POST['database_type'] ?? 'mysql';
        
        if ($database_name) {
            // Create full database name with user prefix
            $fullDbName = $user['username'] . '_' . $database_name;
            
            // Check if database already exists
            $existing = $db->fetch("SELECT id FROM databases WHERE database_name = ?", [$fullDbName]);
            
            if (!$existing) {
                $stmt = $db->prepare(
                    "INSERT INTO databases (user_id, database_name, database_type, created_at) 
                     VALUES (?, ?, ?, NOW())"
                );
                $stmt->execute([$user['id'], $fullDbName, $database_type]);
                $success = "Database created successfully: $fullDbName";
            } else {
                $error = "Database already exists";
            }
        } else {
            $error = "Database name is required";
        }
    }
    
    if ($action === 'delete_database') {
        $database_id = $_POST['database_id'] ?? '';
        
        $database = $db->fetch(
            "SELECT * FROM databases WHERE id = ? AND user_id = ?",
            [$database_id, $user['id']]
        );
        
        if ($database) {
            // Delete database users first
            $db->prepare("DELETE FROM database_users WHERE database_id = ?")->execute([$database_id]);
            // Delete database
            $db->prepare("DELETE FROM databases WHERE id = ? AND user_id = ?")->execute([$database_id, $user['id']]);
            $success = "Database deleted successfully";
        } else {
            $error = "Database not found";
        }
    }
    
    if ($action === 'create_db_user') {
        $database_id = $_POST['database_id'] ?? '';
        $username = $_POST['db_username'] ?? '';
        $password = $_POST['db_password'] ?? '';
        $privileges = $_POST['privileges'] ?? [];
        
        if ($username && $password && $database_id) {
            // Create full username with prefix
            $fullUsername = $user['username'] . '_' . $username;
            
            // Check if user already exists for this database
            $existing = $db->fetch(
                "SELECT id FROM database_users WHERE database_id = ? AND username = ?",
                [$database_id, $fullUsername]
            );
            
            if (!$existing) {
                $stmt = $db->prepare(
                    "INSERT INTO database_users (database_id, username, password, privileges, created_at) 
                     VALUES (?, ?, ?, ?, NOW())"
                );
                $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                $stmt->execute([$database_id, $fullUsername, $hashedPassword, json_encode($privileges)]);
                $success = "Database user created successfully: $fullUsername";
            } else {
                $error = "Database user already exists";
            }
        } else {
            $error = "Username, password, and database are required";
        }
    }
    
    if ($action === 'delete_db_user') {
        $user_id = $_POST['user_id'] ?? '';
        
        $stmt = $db->prepare(
            "DELETE FROM database_users WHERE id = ? AND database_id IN (SELECT id FROM databases WHERE user_id = ?)"
        );
        $stmt->execute([$user_id, $user['id']]);
        $success = "Database user deleted successfully";
    }
}

// Get user's databases with user count
$databases = $db->fetchAll(
    "SELECT d.*, COUNT(du.id) as user_count 
     FROM databases d 
     LEFT JOIN database_users du ON d.id = du.database_id 
     WHERE d.user_id = ? 
     GROUP BY d.id 
     ORDER BY d.created_at DESC",
    [$user['id']]
);

// Get database users for all user's databases
$databaseUsers = $db->fetchAll(
    "SELECT du.*, d.database_name 
     FROM database_users du 
     INNER JOIN databases d ON du.database_id = d.id 
     WHERE d.user_id = ? 
     ORDER BY du.created_at DESC",
    [$user['id']]
);

// Get user's package limits
$packageLimits = $db->fetch(
    "SELECT p.databases FROM packages p 
     INNER JOIN users u ON p.id = u.package_id 
     WHERE u.id = ?",
    [$user['id']]
);

$databaseLimit = $packageLimits['databases'] ?? 5;
$currentDatabaseCount = count($databases);
$canCreateMore = ($databaseLimit == -1) || ($currentDatabaseCount < $databaseLimit);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Manager - Admini Control Panel</title>
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
                <a href="databases.php" class="nav-link active">
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
                    <h1><i class="fas fa-database me-3"></i>Database Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">MySQL Databases</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary">
                        <?= $currentDatabaseCount ?> / <?= $databaseLimit == -1 ? '∞' : $databaseLimit ?> databases
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

            <!-- Database Connection Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>Connection Information
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="connection-info">
                                <div class="info-item">
                                    <label>Database Host:</label>
                                    <div class="value">
                                        <code>localhost</code>
                                        <button class="btn btn-sm btn-outline-secondary ms-2" onclick="copyToClipboard('localhost')">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <label>Port:</label>
                                    <div class="value">
                                        <code>3306</code> (MySQL)
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="connection-info">
                                <div class="info-item">
                                    <label>Database Prefix:</label>
                                    <div class="value">
                                        <code><?= htmlspecialchars($user['username']) ?>_</code>
                                    </div>
                                </div>
                                <div class="info-item">
                                    <label>phpMyAdmin:</label>
                                    <div class="value">
                                        <a href="/phpmyadmin" target="_blank" class="da-btn da-btn-sm da-btn-secondary">
                                            <i class="fas fa-external-link-alt"></i>Access phpMyAdmin
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Database -->
            <?php if ($canCreateMore): ?>
                <div class="da-panel mb-4">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-plus"></i>Create New Database
                        </h5>
                    </div>
                    <div class="da-panel-body">
                        <form method="POST" class="row g-3">
                            <input type="hidden" name="action" value="create_database">
                            <div class="col-md-4">
                                <label class="da-label">Database Name</label>
                                <div class="input-group">
                                    <span class="input-group-text"><?= htmlspecialchars($user['username']) ?>_</span>
                                    <input type="text" name="database_name" class="da-input" required 
                                           pattern="[a-zA-Z0-9_]+" 
                                           title="Only letters, numbers, and underscores"
                                           placeholder="my_app">
                                </div>
                            </div>
                            <div class="col-md-4">
                                <label class="da-label">Database Type</label>
                                <select name="database_type" class="da-select">
                                    <option value="mysql">MySQL</option>
                                    <option value="postgresql">PostgreSQL</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="da-label">&nbsp;</label>
                                <button type="submit" class="da-btn da-btn-primary w-100">
                                    <i class="fas fa-plus"></i>Create Database
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php else: ?>
                <div class="da-alert da-alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>You have reached the maximum number of databases (<?= $databaseLimit ?>) for your package.</div>
                </div>
            <?php endif; ?>

            <!-- Databases and Users Tabs -->
            <div class="da-tabs mb-4">
                <a href="#databases" class="da-tab active" onclick="showTab('databases', this)">
                    Databases (<?= count($databases) ?>)
                </a>
                <a href="#users" class="da-tab" onclick="showTab('users', this)">
                    Database Users (<?= count($databaseUsers) ?>)
                </a>
            </div>

            <!-- Databases Tab -->
            <div id="databases-content" class="tab-content">
                <div class="da-panel">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-database"></i>Your Databases
                        </h5>
                    </div>
                    <div class="da-panel-body p-0">
                        <?php if (empty($databases)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-database fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No databases created</h5>
                                <p class="text-muted">Create your first database to store your application data.</p>
                            </div>
                        <?php else: ?>
                            <div class="da-table">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Database Name</th>
                                            <th>Type</th>
                                            <th>Users</th>
                                            <th>Size</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($databases as $database): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($database['database_name']) ?></strong>
                                                <div class="small text-muted">
                                                    Status: <span class="badge bg-<?= $database['status'] === 'active' ? 'success' : 'danger' ?>">
                                                        <?= ucfirst($database['status']) ?>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="badge bg-info">
                                                    <?= strtoupper($database['database_type']) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <?= $database['user_count'] ?> user(s)
                                                </span>
                                            </td>
                                            <td>
                                                <?= $database['size'] > 0 ? number_format($database['size'] / 1024 / 1024, 2) . ' MB' : '0 MB' ?>
                                            </td>
                                            <td>
                                                <?= date('M j, Y', strtotime($database['created_at'])) ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <button type="button" class="da-btn da-btn-sm da-btn-primary" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#createUserModal" 
                                                            data-database-id="<?= $database['id'] ?>" 
                                                            data-database-name="<?= htmlspecialchars($database['database_name']) ?>">
                                                        <i class="fas fa-user-plus"></i>
                                                    </button>
                                                    <a href="/phpmyadmin" target="_blank" class="da-btn da-btn-sm da-btn-secondary" title="Open in phpMyAdmin">
                                                        <i class="fas fa-external-link-alt"></i>
                                                    </a>
                                                    <form method="POST" class="d-inline" onsubmit="return confirm('Delete this database and all its data? This action cannot be undone.')">
                                                        <input type="hidden" name="action" value="delete_database">
                                                        <input type="hidden" name="database_id" value="<?= $database['id'] ?>">
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

            <!-- Database Users Tab -->
            <div id="users-content" class="tab-content" style="display: none;">
                <div class="da-panel">
                    <div class="da-panel-header">
                        <h5 class="da-panel-title">
                            <i class="fas fa-users"></i>Database Users
                        </h5>
                    </div>
                    <div class="da-panel-body p-0">
                        <?php if (empty($databaseUsers)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-users fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">No database users</h5>
                                <p class="text-muted">Create database users to access your databases from applications.</p>
                            </div>
                        <?php else: ?>
                            <div class="da-table">
                                <table class="table table-hover mb-0">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Database</th>
                                            <th>Privileges</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($databaseUsers as $dbUser): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($dbUser['username']) ?></strong>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($dbUser['database_name']) ?>
                                            </td>
                                            <td>
                                                <?php 
                                                $privileges = json_decode($dbUser['privileges'], true) ?: [];
                                                if (empty($privileges)) {
                                                    echo '<span class="badge bg-warning">All Privileges</span>';
                                                } else {
                                                    foreach (array_slice($privileges, 0, 3) as $privilege) {
                                                        echo '<span class="badge bg-info me-1">' . htmlspecialchars($privilege) . '</span>';
                                                    }
                                                    if (count($privileges) > 3) {
                                                        echo '<span class="badge bg-secondary">+' . (count($privileges) - 3) . ' more</span>';
                                                    }
                                                }
                                                ?>
                                            </td>
                                            <td>
                                                <?= date('M j, Y', strtotime($dbUser['created_at'])) ?>
                                            </td>
                                            <td>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this database user?')">
                                                    <input type="hidden" name="action" value="delete_db_user">
                                                    <input type="hidden" name="user_id" value="<?= $dbUser['id'] ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-danger">
                                                        <i class="fas fa-trash"></i>Delete
                                                    </button>
                                                </form>
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
    </div>

    <!-- Create Database User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create Database User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_db_user">
                        <input type="hidden" name="database_id" id="user_database_id">
                        
                        <div class="mb-3">
                            <label class="da-label">Database</label>
                            <input type="text" id="user_database_name" class="da-input" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">Username</label>
                            <div class="input-group">
                                <span class="input-group-text"><?= htmlspecialchars($user['username']) ?>_</span>
                                <input type="text" name="db_username" class="da-input" required 
                                       pattern="[a-zA-Z0-9_]+" 
                                       title="Only letters, numbers, and underscores">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">Password</label>
                            <div class="input-group">
                                <input type="password" name="db_password" id="dbUserPassword" class="da-input" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generatePassword('dbUserPassword')">
                                    <i class="fas fa-random"></i>
                                </button>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">Privileges</label>
                            <div class="row">
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="SELECT" id="priv_select" checked>
                                        <label class="form-check-label" for="priv_select">SELECT</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="INSERT" id="priv_insert" checked>
                                        <label class="form-check-label" for="priv_insert">INSERT</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="UPDATE" id="priv_update" checked>
                                        <label class="form-check-label" for="priv_update">UPDATE</label>
                                    </div>
                                </div>
                                <div class="col-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="DELETE" id="priv_delete" checked>
                                        <label class="form-check-label" for="priv_delete">DELETE</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="CREATE" id="priv_create">
                                        <label class="form-check-label" for="priv_create">CREATE</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="privileges[]" value="DROP" id="priv_drop">
                                        <label class="form-check-label" for="priv_drop">DROP</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="da-btn da-btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle create user modal
        document.getElementById('createUserModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const databaseId = button.getAttribute('data-database-id');
            const databaseName = button.getAttribute('data-database-name');
            
            document.getElementById('user_database_id').value = databaseId;
            document.getElementById('user_database_name').value = databaseName;
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
                const button = event.target.closest('button');
                const icon = button.querySelector('i');
                icon.className = 'fas fa-check';
                setTimeout(() => {
                    icon.className = 'fas fa-copy';
                }, 2000);
            });
        }

        // Tab switching
        function showTab(tabName, element) {
            // Hide all tab contents
            document.querySelectorAll('.tab-content').forEach(content => {
                content.style.display = 'none';
            });
            
            // Remove active class from all tabs
            document.querySelectorAll('.da-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab content
            document.getElementById(tabName + '-content').style.display = 'block';
            
            // Add active class to selected tab
            element.classList.add('active');
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
        
        .input-group .input-group-text {
            background: var(--da-slate-100);
            border: 2px solid var(--border);
            color: var(--text-secondary);
            font-weight: 600;
        }
        
        .input-group .da-input {
            border-left: none;
        }
        
        .input-group .btn {
            border: 2px solid var(--border);
            border-left: none;
        }
    </style>
</body>
</html>