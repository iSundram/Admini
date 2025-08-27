<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/ApplicationManager.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$appManager = new ApplicationManager();

// Handle installation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        switch ($action) {
            case 'install':
                $applicationId = $_POST['application_id'];
                $domainId = $_POST['domain_id'];
                $installPath = $_POST['install_path'] ?? '';
                $options = [
                    'admin_username' => $_POST['admin_username'] ?? 'admin',
                    'admin_password' => $_POST['admin_password'] ?? '',
                    'admin_email' => $_POST['admin_email'] ?? '',
                    'site_url' => $_POST['site_url'] ?? '',
                    'app_name' => $_POST['app_name'] ?? ''
                ];
                
                $result = $appManager->installApplication(Auth::getUserId(), $applicationId, $domainId, $installPath, $options);
                $success = "Application installed successfully! Installation ID: " . $result['installation_id'];
                $installResult = $result;
                break;
                
            case 'uninstall':
                $installationId = $_POST['installation_id'];
                $appManager->uninstallApplication($installationId, Auth::getUserId());
                $success = "Application uninstalled successfully.";
                break;
                
            case 'update':
                $installationId = $_POST['installation_id'];
                $appManager->updateApplication($installationId, Auth::getUserId());
                $success = "Application updated successfully.";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$availableApps = $appManager->getAvailableApplications();
$categories = $appManager->getCategories();
$installedApps = $appManager->getUserApplications(Auth::getUserId());
$userDomains = $db->fetchAll("SELECT * FROM domains WHERE user_id = ? ORDER BY domain_name", [Auth::getUserId()]);
$appStats = $appManager->getApplicationStatistics();

// Filter by category if requested
$selectedCategory = $_GET['category'] ?? '';
if ($selectedCategory) {
    $availableApps = $appManager->getAvailableApplications($selectedCategory);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Installer - Admini</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .app-installer {
            padding: 20px;
        }
        
        .app-tabs {
            display: flex;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            padding: 4px;
            margin-bottom: 20px;
        }
        
        .app-tab {
            flex: 1;
            padding: 12px 20px;
            text-align: center;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            color: #cbd5e1;
        }
        
        .app-tab.active {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.3);
        }
        
        .app-tab:not(.active):hover {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        .tab-content {
            display: none;
        }
        
        .tab-content.active {
            display: block;
        }
        
        .category-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .category-btn {
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
        }
        
        .category-btn:hover, .category-btn.active {
            background: #3b82f6;
            color: white;
        }
        
        .app-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }
        
        .app-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.2);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .app-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(0, 0, 0, 0.3);
        }
        
        .app-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 15px;
            font-size: 24px;
            color: white;
        }
        
        .app-name {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 5px;
        }
        
        .app-version {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 10px;
        }
        
        .app-description {
            color: #cbd5e1;
            font-size: 14px;
            margin-bottom: 15px;
            line-height: 1.4;
        }
        
        .app-category {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            display: inline-block;
            margin-bottom: 15px;
        }
        
        .app-requirements {
            font-size: 12px;
            color: #94a3b8;
            margin-bottom: 15px;
        }
        
        .install-btn {
            background: linear-gradient(135deg, #10b981, #047857);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s;
            width: 100%;
            font-weight: bold;
        }
        
        .install-btn:hover {
            background: linear-gradient(135deg, #047857, #065f46);
            transform: translateY(-1px);
        }
        
        .installed-apps {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }
        
        .installed-app {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(34, 197, 94, 0.3);
        }
        
        .app-status {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .app-status.active {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .app-status.installing {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        
        .app-status.failed {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .app-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        
        .action-btn {
            flex: 1;
            padding: 8px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-update {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        .btn-uninstall {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .btn-manage {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.8;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            margin: 2% auto;
            padding: 20px;
            border-radius: 12px;
            width: 90%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        
        .close {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .close:hover {
            color: #fff;
        }
        
        .progress-bar {
            background: rgba(59, 130, 246, 0.2);
            border-radius: 8px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            background: linear-gradient(90deg, #3b82f6, #1d4ed8);
            height: 8px;
            border-radius: 8px;
            transition: width 0.3s;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-download"></i> Application Installer</h1>
            <p>Install and manage applications with one-click deployment</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success">
                <?= htmlspecialchars($success) ?>
                <?php if (isset($installResult)): ?>
                <div style="margin-top: 10px; padding: 10px; background: rgba(34, 197, 94, 0.1); border-radius: 6px;">
                    <strong>Installation Details:</strong><br>
                    <?php if ($installResult['app_url']): ?>
                    App URL: <a href="<?= htmlspecialchars($installResult['app_url']) ?>" target="_blank"><?= htmlspecialchars($installResult['app_url']) ?></a><br>
                    <?php endif; ?>
                    <?php if ($installResult['admin_username']): ?>
                    Admin Username: <code><?= htmlspecialchars($installResult['admin_username']) ?></code><br>
                    <?php endif; ?>
                    <?php if ($installResult['admin_password']): ?>
                    Admin Password: <code><?= htmlspecialchars($installResult['admin_password']) ?></code><br>
                    <?php endif; ?>
                    <?php if ($installResult['database_name']): ?>
                    Database: <code><?= htmlspecialchars($installResult['database_name']) ?></code>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <!-- Statistics Overview -->
        <div class="stats-overview">
            <div class="stat-card">
                <div class="stat-value"><?= count($availableApps) ?></div>
                <div class="stat-label">Available Apps</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($installedApps) ?></div>
                <div class="stat-label">Installed Apps</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= count($categories) ?></div>
                <div class="stat-label">Categories</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $appStats['total_installations'] ?></div>
                <div class="stat-label">Total Installs</div>
            </div>
        </div>
        
        <div class="app-installer">
            <!-- Tabs -->
            <div class="app-tabs">
                <div class="app-tab active" onclick="switchTab('available')">
                    <i class="fas fa-store"></i> Available Applications
                </div>
                <div class="app-tab" onclick="switchTab('installed')">
                    <i class="fas fa-check-circle"></i> Installed Applications
                </div>
                <div class="app-tab" onclick="switchTab('popular')">
                    <i class="fas fa-star"></i> Popular Apps
                </div>
            </div>
            
            <!-- Available Applications Tab -->
            <div id="available" class="tab-content active">
                <!-- Category Filter -->
                <div class="category-filter">
                    <a href="?tab=available" class="category-btn <?= empty($selectedCategory) ? 'active' : '' ?>">
                        <i class="fas fa-globe"></i> All Categories
                    </a>
                    <?php foreach ($categories as $category): ?>
                    <a href="?tab=available&category=<?= urlencode($category) ?>" 
                       class="category-btn <?= $selectedCategory === $category ? 'active' : '' ?>">
                        <i class="fas fa-<?= $category === 'cms' ? 'edit' : ($category === 'ecommerce' ? 'shopping-cart' : ($category === 'forum' ? 'comments' : ($category === 'blog' ? 'blog' : ($category === 'framework' ? 'code' : 'tools')))) ?>"></i>
                        <?= ucfirst($category) ?>
                    </a>
                    <?php endforeach; ?>
                </div>
                
                <!-- Applications Grid -->
                <div class="app-grid">
                    <?php foreach ($availableApps as $app): ?>
                    <?php $requirements = json_decode($app['requirements'], true); ?>
                    <div class="app-card">
                        <div class="app-icon">
                            <i class="fas fa-<?= $app['category'] === 'cms' ? 'edit' : ($app['category'] === 'ecommerce' ? 'shopping-cart' : ($app['category'] === 'forum' ? 'comments' : ($app['category'] === 'blog' ? 'blog' : ($app['category'] === 'framework' ? 'code' : 'tools')))) ?>"></i>
                        </div>
                        
                        <div class="app-name"><?= htmlspecialchars($app['app_name']) ?></div>
                        <div class="app-version">Version <?= htmlspecialchars($app['app_version']) ?></div>
                        <div class="app-category"><?= ucfirst($app['category']) ?></div>
                        
                        <div class="app-description">
                            <?= htmlspecialchars($app['description']) ?>
                        </div>
                        
                        <div class="app-requirements">
                            <strong>Requirements:</strong><br>
                            PHP <?= $requirements['php'] ?? 'Any' ?>,
                            MySQL <?= $requirements['mysql'] ?? 'Any' ?><br>
                            Extensions: <?= implode(', ', $requirements['extensions'] ?? []) ?>
                        </div>
                        
                        <button class="install-btn" onclick="openInstallModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['app_name']) ?>')">
                            <i class="fas fa-download"></i> Install Now
                        </button>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Installed Applications Tab -->
            <div id="installed" class="tab-content">
                <div class="installed-apps">
                    <?php foreach ($installedApps as $app): ?>
                    <div class="installed-app">
                        <div class="app-status <?= $app['status'] ?>"><?= ucfirst($app['status']) ?></div>
                        
                        <div class="app-name"><?= htmlspecialchars($app['app_name']) ?></div>
                        <div class="app-version">Version <?= htmlspecialchars($app['version']) ?></div>
                        
                        <div style="margin: 10px 0; font-size: 14px; color: #cbd5e1;">
                            <strong>Domain:</strong> <?= htmlspecialchars($app['domain_name']) ?><br>
                            <?php if ($app['install_path']): ?>
                            <strong>Path:</strong> /<?= htmlspecialchars($app['install_path']) ?><br>
                            <?php endif; ?>
                            <?php if ($app['app_url']): ?>
                            <strong>URL:</strong> <a href="<?= htmlspecialchars($app['app_url']) ?>" target="_blank"><?= htmlspecialchars($app['app_url']) ?></a><br>
                            <?php endif; ?>
                            <strong>Installed:</strong> <?= date('M j, Y', strtotime($app['installed_at'])) ?>
                        </div>
                        
                        <?php if ($app['status'] === 'installing'): ?>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: 60%;"></div>
                        </div>
                        <div style="text-align: center; font-size: 12px; color: #fbbf24;">
                            <i class="fas fa-spinner fa-spin"></i> Installing...
                        </div>
                        <?php endif; ?>
                        
                        <?php if ($app['status'] === 'active'): ?>
                        <div class="app-actions">
                            <?php if ($app['app_url']): ?>
                            <a href="<?= htmlspecialchars($app['app_url']) ?>" target="_blank" class="action-btn btn-manage">
                                <i class="fas fa-external-link-alt"></i> Open
                            </a>
                            <?php endif; ?>
                            <button class="action-btn btn-update" onclick="updateApp(<?= $app['id'] ?>)">
                                <i class="fas fa-sync"></i> Update
                            </button>
                            <button class="action-btn btn-uninstall" onclick="uninstallApp(<?= $app['id'] ?>)">
                                <i class="fas fa-trash"></i> Remove
                            </button>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    
                    <?php if (empty($installedApps)): ?>
                    <div style="text-align: center; padding: 40px; color: #94a3b8;">
                        <i class="fas fa-download" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                        <h3>No Applications Installed</h3>
                        <p>Start by installing your first application from the Available Applications tab.</p>
                        <button class="btn btn-primary" onclick="switchTab('available')">
                            <i class="fas fa-store"></i> Browse Applications
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Popular Applications Tab -->
            <div id="popular" class="tab-content">
                <div class="app-grid">
                    <?php foreach (array_slice($appStats['popular_apps'], 0, 10) as $popularApp): ?>
                    <?php 
                    $app = array_filter($availableApps, function($a) use ($popularApp) {
                        return $a['app_name'] === $popularApp['app_name'];
                    });
                    $app = reset($app);
                    if ($app):
                    ?>
                    <div class="app-card">
                        <div class="app-icon">
                            <i class="fas fa-star" style="color: #fbbf24;"></i>
                        </div>
                        
                        <div class="app-name"><?= htmlspecialchars($app['app_name']) ?></div>
                        <div class="app-version">Version <?= htmlspecialchars($app['app_version']) ?></div>
                        <div style="color: #fbbf24; font-size: 12px; margin-bottom: 10px;">
                            <i class="fas fa-download"></i> <?= $popularApp['installations'] ?> installations
                        </div>
                        
                        <div class="app-description">
                            <?= htmlspecialchars($app['description']) ?>
                        </div>
                        
                        <button class="install-btn" onclick="openInstallModal(<?= $app['id'] ?>, '<?= htmlspecialchars($app['app_name']) ?>')">
                            <i class="fas fa-download"></i> Install Now
                        </button>
                    </div>
                    <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Install Modal -->
    <div id="installModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeInstallModal()">&times;</span>
            <h3><i class="fas fa-download"></i> Install Application</h3>
            <form method="POST" id="installForm">
                <input type="hidden" name="action" value="install">
                <input type="hidden" name="application_id" id="install_app_id">
                
                <div class="form-group">
                    <label>Application:</label>
                    <input type="text" id="install_app_name" class="form-control" readonly>
                </div>
                
                <div class="form-group">
                    <label>Install on Domain:</label>
                    <select name="domain_id" class="form-control" required>
                        <option value="">Select Domain</option>
                        <?php foreach ($userDomains as $domain): ?>
                        <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Installation Path (optional):</label>
                    <input type="text" name="install_path" class="form-control" placeholder="e.g., /blog or leave empty for root">
                    <small>Leave empty to install in domain root directory</small>
                </div>
                
                <div class="form-group">
                    <label>Site URL:</label>
                    <input type="url" name="site_url" class="form-control" placeholder="https://yourdomain.com">
                </div>
                
                <div class="form-group">
                    <label>Application Name:</label>
                    <input type="text" name="app_name" class="form-control" placeholder="My Website">
                </div>
                
                <h4 style="color: #3b82f6; margin-top: 20px;">Admin Account</h4>
                
                <div class="form-group">
                    <label>Admin Username:</label>
                    <input type="text" name="admin_username" class="form-control" value="admin" required>
                </div>
                
                <div class="form-group">
                    <label>Admin Password:</label>
                    <input type="password" name="admin_password" class="form-control" id="admin_password" required>
                    <button type="button" onclick="generatePassword()" class="btn btn-sm btn-secondary" style="margin-top: 5px;">
                        <i class="fas fa-random"></i> Generate Password
                    </button>
                </div>
                
                <div class="form-group">
                    <label>Admin Email:</label>
                    <input type="email" name="admin_email" class="form-control" required>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeInstallModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-download"></i> Install Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Tab switching
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            
            document.querySelectorAll('.app-tab').forEach(tab => {
                tab.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }
        
        // Install modal
        function openInstallModal(appId, appName) {
            document.getElementById('install_app_id').value = appId;
            document.getElementById('install_app_name').value = appName;
            document.getElementById('installModal').style.display = 'block';
        }
        
        function closeInstallModal() {
            document.getElementById('installModal').style.display = 'none';
        }
        
        // Generate secure password
        function generatePassword() {
            const chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
            let password = '';
            for (let i = 0; i < 12; i++) {
                password += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            document.getElementById('admin_password').value = password;
        }
        
        // App actions
        function updateApp(installationId) {
            if (confirm('Are you sure you want to update this application?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="installation_id" value="${installationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        function uninstallApp(installationId) {
            if (confirm('Are you sure you want to uninstall this application? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="uninstall">
                    <input type="hidden" name="installation_id" value="${installationId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('installModal');
            if (event.target === modal) {
                modal.style.display = 'none';
            }
        }
        
        // Auto-refresh installing apps
        setInterval(function() {
            const installingApps = document.querySelectorAll('.app-status.installing');
            if (installingApps.length > 0) {
                location.reload();
            }
        }, 15000);
        
        // Update site URL based on domain selection
        document.querySelector('select[name="domain_id"]').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            if (selectedOption.value) {
                const domainName = selectedOption.text;
                const installPath = document.querySelector('input[name="install_path"]').value;
                const siteUrl = `https://${domainName}${installPath ? '/' + installPath.replace(/^\//, '') : ''}`;
                document.querySelector('input[name="site_url"]').value = siteUrl;
            }
        });
        
        document.querySelector('input[name="install_path"]').addEventListener('input', function() {
            const domainSelect = document.querySelector('select[name="domain_id"]');
            const selectedOption = domainSelect.options[domainSelect.selectedIndex];
            if (selectedOption.value) {
                const domainName = selectedOption.text;
                const installPath = this.value;
                const siteUrl = `https://${domainName}${installPath ? '/' + installPath.replace(/^\//, '') : ''}`;
                document.querySelector('input[name="site_url"]').value = siteUrl;
            }
        });
    </script>
</body>
</html>