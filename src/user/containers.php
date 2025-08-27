<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/ContainerManager.php';

// Check authentication
if (!Auth::isLoggedIn()) {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$containerManager = new ContainerManager();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_nodejs':
                $result = $containerManager->createNodeJSApp(
                    Auth::getUserId(),
                    $_POST['app_name'],
                    $_POST['domain_id'],
                    $_POST['node_version'] ?? '18',
                    $_POST
                );
                $success = "Node.js application created successfully! Port: {$result['port']}";
                break;
                
            case 'create_python':
                $result = $containerManager->createPythonApp(
                    Auth::getUserId(),
                    $_POST['app_name'],
                    $_POST['domain_id'],
                    $_POST['python_version'] ?? '3.11',
                    $_POST['framework'] ?? 'flask',
                    $_POST
                );
                $success = "Python application created successfully! Port: {$result['port']}";
                break;
                
            case 'create_docker':
                $imageConfig = [
                    'image' => $_POST['docker_image'],
                    'tag' => $_POST['docker_tag'] ?? 'latest',
                    'internal_port' => $_POST['internal_port'] ?? 80,
                    'environment' => [],
                    'volumes' => []
                ];
                
                // Parse environment variables
                if (!empty($_POST['env_vars'])) {
                    $envLines = explode("\n", $_POST['env_vars']);
                    foreach ($envLines as $line) {
                        $line = trim($line);
                        if (strpos($line, '=') !== false) {
                            list($key, $value) = explode('=', $line, 2);
                            $imageConfig['environment'][trim($key)] = trim($value);
                        }
                    }
                }
                
                $result = $containerManager->deployDockerContainer(
                    Auth::getUserId(),
                    $_POST['container_name'],
                    $_POST['domain_id'],
                    $imageConfig
                );
                $success = "Docker container created successfully! Port: {$result['port']}";
                break;
                
            case 'start':
                $containerManager->startContainer($_POST['container_id']);
                $success = "Container started successfully.";
                break;
                
            case 'stop':
                $containerManager->stopContainer($_POST['container_id']);
                $success = "Container stopped successfully.";
                break;
                
            case 'delete':
                $containerManager->deleteContainer($_POST['container_id'], Auth::getUserId());
                $success = "Container deleted successfully.";
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get data
$containers = $containerManager->getUserContainers(Auth::getUserId());
$userDomains = $db->fetchAll("SELECT * FROM domains WHERE user_id = ? ORDER BY domain_name", [Auth::getUserId()]);

// Get container logs if requested
$selectedContainer = null;
$containerLogs = '';
if (isset($_GET['logs']) && is_numeric($_GET['logs'])) {
    $selectedContainer = $_GET['logs'];
    try {
        $containerLogs = $containerManager->getContainerLogs($selectedContainer, 50);
    } catch (Exception $e) {
        $containerLogs = "Error fetching logs: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Container Manager - Admini</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .container-dashboard {
            padding: 20px;
        }
        
        .container-types {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .container-type-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .container-type-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 40px rgba(59, 130, 246, 0.3);
            border-color: #3b82f6;
        }
        
        .container-type-icon {
            font-size: 48px;
            margin-bottom: 15px;
            color: #3b82f6;
        }
        
        .container-type-name {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .container-type-desc {
            color: #cbd5e1;
            font-size: 14px;
        }
        
        .containers-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .container-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .container-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .container-name {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .container-status {
            padding: 4px 12px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .container-status.running {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .container-status.stopped {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .container-status.failed {
            background: rgba(245, 158, 11, 0.2);
            color: #fbbf24;
        }
        
        .container-info {
            margin-bottom: 15px;
            font-size: 14px;
            color: #cbd5e1;
        }
        
        .container-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .action-btn {
            padding: 6px 12px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-size: 12px;
            font-weight: bold;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }
        
        .btn-start {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .btn-stop {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .btn-logs {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
        }
        
        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .action-btn:hover {
            transform: translateY(-1px);
            opacity: 0.8;
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
        
        .logs-container {
            background: #000;
            color: #0f0;
            padding: 15px;
            border-radius: 8px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            margin-top: 15px;
        }
        
        .container-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }
        
        .stat-card {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .stat-label {
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 5px;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .form-section {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
            border-left: 4px solid #3b82f6;
        }
        
        .form-section h4 {
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .env-vars-textarea {
            height: 100px;
            font-family: monospace;
            font-size: 12px;
        }
        
        .container-type-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            flex-wrap: wrap;
        }
        
        .filter-btn {
            padding: 8px 16px;
            border-radius: 20px;
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #3b82f6;
            text-decoration: none;
            transition: all 0.3s;
            font-size: 14px;
            cursor: pointer;
        }
        
        .filter-btn:hover, .filter-btn.active {
            background: #3b82f6;
            color: white;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-cube"></i> Container Manager</h1>
            <p>Deploy and manage Node.js, Python, and Docker applications</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="container-dashboard">
            <!-- Statistics -->
            <div class="container-stats">
                <div class="stat-card">
                    <div class="stat-value"><?= count($containers) ?></div>
                    <div class="stat-label">Total Containers</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($containers, fn($c) => $c['status'] === 'running')) ?></div>
                    <div class="stat-label">Running</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($containers, fn($c) => $c['container_type'] === 'nodejs')) ?></div>
                    <div class="stat-label">Node.js Apps</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($containers, fn($c) => $c['container_type'] === 'python')) ?></div>
                    <div class="stat-label">Python Apps</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value"><?= count(array_filter($containers, fn($c) => $c['container_type'] === 'docker')) ?></div>
                    <div class="stat-label">Docker Containers</div>
                </div>
            </div>
            
            <!-- Container Types -->
            <h3><i class="fas fa-plus"></i> Create New Container</h3>
            <div class="container-types">
                <div class="container-type-card" onclick="openCreateModal('nodejs')">
                    <div class="container-type-icon">
                        <i class="fab fa-node-js"></i>
                    </div>
                    <div class="container-type-name">Node.js</div>
                    <div class="container-type-desc">Deploy Node.js applications with Express, Next.js, or custom frameworks</div>
                </div>
                
                <div class="container-type-card" onclick="openCreateModal('python')">
                    <div class="container-type-icon">
                        <i class="fab fa-python"></i>
                    </div>
                    <div class="container-type-name">Python</div>
                    <div class="container-type-desc">Deploy Python applications with Flask, Django, or FastAPI</div>
                </div>
                
                <div class="container-type-card" onclick="openCreateModal('docker')">
                    <div class="container-type-icon">
                        <i class="fab fa-docker"></i>
                    </div>
                    <div class="container-type-name">Docker</div>
                    <div class="container-type-desc">Deploy any Docker image from Docker Hub or private registries</div>
                </div>
            </div>
            
            <!-- Container Filter -->
            <div class="container-type-filter">
                <button class="filter-btn active" onclick="filterContainers('all')">
                    <i class="fas fa-globe"></i> All Containers
                </button>
                <button class="filter-btn" onclick="filterContainers('nodejs')">
                    <i class="fab fa-node-js"></i> Node.js
                </button>
                <button class="filter-btn" onclick="filterContainers('python')">
                    <i class="fab fa-python"></i> Python
                </button>
                <button class="filter-btn" onclick="filterContainers('docker')">
                    <i class="fab fa-docker"></i> Docker
                </button>
                <button class="filter-btn" onclick="filterContainers('running')">
                    <i class="fas fa-play"></i> Running
                </button>
                <button class="filter-btn" onclick="filterContainers('stopped')">
                    <i class="fas fa-stop"></i> Stopped
                </button>
            </div>
            
            <!-- Existing Containers -->
            <h3><i class="fas fa-cubes"></i> Your Containers</h3>
            <div class="containers-grid">
                <?php foreach ($containers as $container): ?>
                <?php $config = json_decode($container['config'], true); ?>
                <div class="container-card" data-type="<?= $container['container_type'] ?>" data-status="<?= $container['status'] ?>">
                    <div class="container-header">
                        <div>
                            <div class="container-name">
                                <i class="<?= $container['container_type'] === 'nodejs' ? 'fab fa-node-js' : ($container['container_type'] === 'python' ? 'fab fa-python' : 'fab fa-docker') ?>"></i>
                                <?= htmlspecialchars($container['container_name']) ?>
                            </div>
                            <div style="font-size: 12px; color: #94a3b8;">
                                <?= ucfirst($container['container_type']) ?>
                                <?php if ($container['container_type'] === 'nodejs'): ?>
                                    - Node.js <?= $config['node_version'] ?? 'latest' ?>
                                <?php elseif ($container['container_type'] === 'python'): ?>
                                    - Python <?= $config['python_version'] ?? 'latest' ?> (<?= ucfirst($config['framework'] ?? 'flask') ?>)
                                <?php elseif ($container['container_type'] === 'docker'): ?>
                                    - <?= $config['image'] ?? 'Unknown' ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        <span class="container-status <?= $container['status'] ?>"><?= ucfirst($container['status']) ?></span>
                    </div>
                    
                    <div class="container-info">
                        <strong>Domain:</strong> <?= htmlspecialchars($container['domain_name']) ?><br>
                        <strong>Port:</strong> <?= $container['port'] ?><br>
                        <?php if ($container['app_url']): ?>
                        <strong>URL:</strong> <a href="<?= htmlspecialchars($container['app_url']) ?>" target="_blank"><?= htmlspecialchars($container['app_url']) ?></a><br>
                        <?php endif; ?>
                        <strong>Created:</strong> <?= date('M j, Y', strtotime($container['created_at'])) ?>
                    </div>
                    
                    <div class="container-actions">
                        <?php if ($container['status'] === 'stopped'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="start">
                            <input type="hidden" name="container_id" value="<?= $container['id'] ?>">
                            <button type="submit" class="action-btn btn-start">
                                <i class="fas fa-play"></i> Start
                            </button>
                        </form>
                        <?php elseif ($container['status'] === 'running'): ?>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="stop">
                            <input type="hidden" name="container_id" value="<?= $container['id'] ?>">
                            <button type="submit" class="action-btn btn-stop">
                                <i class="fas fa-stop"></i> Stop
                            </button>
                        </form>
                        <?php endif; ?>
                        
                        <a href="?logs=<?= $container['id'] ?>" class="action-btn btn-logs">
                            <i class="fas fa-file-alt"></i> Logs
                        </a>
                        
                        <?php if ($container['app_url']): ?>
                        <a href="<?= htmlspecialchars($container['app_url']) ?>" target="_blank" class="action-btn btn-logs">
                            <i class="fas fa-external-link-alt"></i> Open
                        </a>
                        <?php endif; ?>
                        
                        <form method="POST" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this container?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="container_id" value="<?= $container['id'] ?>">
                            <button type="submit" class="action-btn btn-delete">
                                <i class="fas fa-trash"></i> Delete
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($containers)): ?>
                <div style="grid-column: 1 / -1; text-align: center; padding: 40px; color: #94a3b8;">
                    <i class="fas fa-cube" style="font-size: 48px; margin-bottom: 20px; opacity: 0.5;"></i>
                    <h3>No Containers Yet</h3>
                    <p>Create your first container by clicking on one of the options above.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Container Logs Modal -->
    <?php if ($selectedContainer): ?>
    <div id="logsModal" class="modal" style="display: block;">
        <div class="modal-content">
            <span class="close" onclick="closeLogsModal()">&times;</span>
            <h3><i class="fas fa-file-alt"></i> Container Logs</h3>
            <div class="logs-container"><?= htmlspecialchars($containerLogs) ?></div>
            <div style="margin-top: 15px;">
                <button onclick="refreshLogs()" class="btn btn-primary">
                    <i class="fas fa-sync"></i> Refresh Logs
                </button>
                <button onclick="closeLogsModal()" class="btn btn-secondary">Close</button>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <!-- Create Node.js Modal -->
    <div id="nodejsModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h3><i class="fab fa-node-js"></i> Create Node.js Application</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_nodejs">
                
                <div class="form-section">
                    <h4>Basic Configuration</h4>
                    <div class="form-group">
                        <label>Application Name:</label>
                        <input type="text" name="app_name" class="form-control" placeholder="my-nodejs-app" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Domain:</label>
                            <select name="domain_id" class="form-control" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($userDomains as $domain): ?>
                                <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Node.js Version:</label>
                            <select name="node_version" class="form-control">
                                <option value="18">Node.js 18 (LTS)</option>
                                <option value="20">Node.js 20 (Latest)</option>
                                <option value="16">Node.js 16</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Framework:</label>
                        <select name="framework" class="form-control">
                            <option value="express">Express.js</option>
                            <option value="none">None (Basic HTTP server)</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fab fa-node-js"></i> Create Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Python Modal -->
    <div id="pythonModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h3><i class="fab fa-python"></i> Create Python Application</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_python">
                
                <div class="form-section">
                    <h4>Basic Configuration</h4>
                    <div class="form-group">
                        <label>Application Name:</label>
                        <input type="text" name="app_name" class="form-control" placeholder="my-python-app" required>
                    </div>
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label>Domain:</label>
                            <select name="domain_id" class="form-control" required>
                                <option value="">Select Domain</option>
                                <?php foreach ($userDomains as $domain): ?>
                                <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="form-group">
                            <label>Python Version:</label>
                            <select name="python_version" class="form-control">
                                <option value="3.11">Python 3.11</option>
                                <option value="3.10">Python 3.10</option>
                                <option value="3.9">Python 3.9</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Framework:</label>
                        <select name="framework" class="form-control">
                            <option value="flask">Flask</option>
                            <option value="fastapi">FastAPI</option>
                            <option value="django">Django</option>
                        </select>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fab fa-python"></i> Create Application
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Create Docker Modal -->
    <div id="dockerModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeCreateModal()">&times;</span>
            <h3><i class="fab fa-docker"></i> Deploy Docker Container</h3>
            <form method="POST">
                <input type="hidden" name="action" value="create_docker">
                
                <div class="form-section">
                    <h4>Basic Configuration</h4>
                    <div class="form-group">
                        <label>Container Name:</label>
                        <input type="text" name="container_name" class="form-control" placeholder="my-container" required>
                    </div>
                    
                    <div class="form-group">
                        <label>Domain:</label>
                        <select name="domain_id" class="form-control" required>
                            <option value="">Select Domain</option>
                            <?php foreach ($userDomains as $domain): ?>
                            <option value="<?= $domain['id'] ?>"><?= htmlspecialchars($domain['domain_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Docker Image</h4>
                    <div class="form-row">
                        <div class="form-group">
                            <label>Docker Image:</label>
                            <input type="text" name="docker_image" class="form-control" placeholder="nginx" required>
                        </div>
                        
                        <div class="form-group">
                            <label>Tag:</label>
                            <input type="text" name="docker_tag" class="form-control" placeholder="latest" value="latest">
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Internal Port:</label>
                        <input type="number" name="internal_port" class="form-control" placeholder="80" value="80">
                        <small>The port your application listens on inside the container</small>
                    </div>
                </div>
                
                <div class="form-section">
                    <h4>Environment Variables</h4>
                    <div class="form-group">
                        <label>Environment Variables (one per line):</label>
                        <textarea name="env_vars" class="form-control env-vars-textarea" placeholder="NODE_ENV=production&#10;PORT=3000&#10;DATABASE_URL=postgres://..."></textarea>
                        <small>Format: KEY=VALUE (one per line)</small>
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px;">
                    <button type="button" onclick="closeCreateModal()" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fab fa-docker"></i> Deploy Container
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <script>
        // Modal functions
        function openCreateModal(type) {
            document.getElementById(type + 'Modal').style.display = 'block';
        }
        
        function closeCreateModal() {
            document.querySelectorAll('.modal').forEach(modal => {
                modal.style.display = 'none';
            });
        }
        
        function closeLogsModal() {
            window.location.href = window.location.pathname;
        }
        
        function refreshLogs() {
            window.location.reload();
        }
        
        // Container filtering
        function filterContainers(type) {
            // Update filter buttons
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            event.target.classList.add('active');
            
            // Filter containers
            document.querySelectorAll('.container-card').forEach(card => {
                const containerType = card.dataset.type;
                const containerStatus = card.dataset.status;
                
                let show = false;
                
                switch (type) {
                    case 'all':
                        show = true;
                        break;
                    case 'running':
                        show = containerStatus === 'running';
                        break;
                    case 'stopped':
                        show = containerStatus === 'stopped';
                        break;
                    default:
                        show = containerType === type;
                }
                
                card.style.display = show ? 'block' : 'none';
            });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
        
        // Auto-refresh container status
        setInterval(function() {
            // Only refresh if there are running containers
            const runningContainers = document.querySelectorAll('.container-status.running');
            if (runningContainers.length > 0) {
                // Check if user is not in a modal
                const modalsOpen = Array.from(document.querySelectorAll('.modal')).some(modal => 
                    modal.style.display === 'block'
                );
                
                if (!modalsOpen) {
                    location.reload();
                }
            }
        }, 30000);
    </script>
</body>
</html>