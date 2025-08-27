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

// Simple file manager - in production, this would be more sophisticated
$currentPath = $_GET['path'] ?? '/';
$currentPath = '/' . trim($currentPath, '/');

// For security, limit to user's home directory
$userHome = "/home/{$user['username']}";
$fullPath = $userHome . $currentPath;

// Ensure path is within user's directory
if (strpos($fullPath, $userHome) !== 0) {
    $currentPath = '/';
    $fullPath = $userHome;
}

$message = '';
$error = '';

// Handle file operations
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'create_folder':
                $folderName = trim($_POST['folder_name']);
                if (!empty($folderName) && preg_match('/^[a-zA-Z0-9_-]+$/', $folderName)) {
                    $newPath = $fullPath . '/' . $folderName;
                    if (!file_exists($newPath)) {
                        mkdir($newPath, 0755, true);
                        $message = 'Folder created successfully';
                    } else {
                        $error = 'Folder already exists';
                    }
                } else {
                    $error = 'Invalid folder name';
                }
                break;
                
            case 'upload_file':
                if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
                    $fileName = basename($_FILES['file']['name']);
                    $targetPath = $fullPath . '/' . $fileName;
                    
                    if (move_uploaded_file($_FILES['file']['tmp_name'], $targetPath)) {
                        chmod($targetPath, 0644);
                        $message = 'File uploaded successfully';
                    } else {
                        $error = 'Failed to upload file';
                    }
                } else {
                    $error = 'Please select a file to upload';
                }
                break;
                
            case 'delete_item':
                $itemName = $_POST['item_name'];
                $itemPath = $fullPath . '/' . $itemName;
                
                if (file_exists($itemPath) && strpos($itemPath, $userHome) === 0) {
                    if (is_dir($itemPath)) {
                        rmdir($itemPath);
                    } else {
                        unlink($itemPath);
                    }
                    $message = 'Item deleted successfully';
                } else {
                    $error = 'Item not found';
                }
                break;
        }
    } catch (Exception $e) {
        $error = 'Operation failed: ' . $e->getMessage();
    }
}

// Get directory contents
$items = [];
if (is_dir($fullPath)) {
    $files = scandir($fullPath);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $filePath = $fullPath . '/' . $file;
        $items[] = [
            'name' => $file,
            'type' => is_dir($filePath) ? 'directory' : 'file',
            'size' => is_file($filePath) ? filesize($filePath) : 0,
            'modified' => filemtime($filePath),
            'permissions' => substr(sprintf('%o', fileperms($filePath)), -4)
        ];
    }
}

// Sort items: directories first, then files
usort($items, function($a, $b) {
    if ($a['type'] !== $b['type']) {
        return $a['type'] === 'directory' ? -1 : 1;
    }
    return strcmp($a['name'], $b['name']);
});

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>File Manager - Admini Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="../assets/css/admin.css" rel="stylesheet">
    <style>
        .file-item {
            cursor: pointer;
            transition: background-color 0.2s;
        }
        .file-item:hover {
            background-color: #f8f9fa;
        }
        .file-icon {
            width: 20px;
            text-align: center;
        }
        .breadcrumb-item a {
            text-decoration: none;
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fas fa-server me-2"></i>Admini</h4>
            <small>User Panel</small>
        </div>
        
        <nav class="sidebar-nav">
            <a href="dashboard.php" class="nav-link">
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
            <a href="filemanager.php" class="nav-link active">
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
                <h1>File Manager</h1>
                <div class="btn-group">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createFolderModal">
                        <i class="fas fa-folder-plus me-2"></i>New Folder
                    </button>
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#uploadFileModal">
                        <i class="fas fa-upload me-2"></i>Upload File
                    </button>
                </div>
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

            <!-- Breadcrumb -->
            <nav aria-label="breadcrumb" class="mb-3">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="filemanager.php?path=/">
                            <i class="fas fa-home me-1"></i>Home
                        </a>
                    </li>
                    <?php
                    $pathParts = explode('/', trim($currentPath, '/'));
                    $buildPath = '';
                    foreach ($pathParts as $part) {
                        if (empty($part)) continue;
                        $buildPath .= '/' . $part;
                        echo '<li class="breadcrumb-item">';
                        echo '<a href="filemanager.php?path=' . urlencode($buildPath) . '">' . htmlspecialchars($part) . '</a>';
                        echo '</li>';
                    }
                    ?>
                </ol>
            </nav>

            <!-- File Browser -->
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-folder me-2"></i>Directory: <?= htmlspecialchars($currentPath) ?></h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Modified</th>
                                    <th>Permissions</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($currentPath !== '/'): ?>
                                    <tr class="file-item" onclick="navigateToPath('<?= htmlspecialchars(dirname($currentPath)) ?>')">
                                        <td>
                                            <i class="fas fa-level-up-alt file-icon text-primary me-2"></i>
                                            <strong>..</strong>
                                        </td>
                                        <td>Directory</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                        <td>-</td>
                                    </tr>
                                <?php endif; ?>
                                
                                <?php if (empty($items)): ?>
                                    <tr>
                                        <td colspan="6" class="text-center text-muted">Directory is empty</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($items as $item): ?>
                                        <tr class="file-item" <?= $item['type'] === 'directory' ? 'onclick="navigateToPath(\'' . htmlspecialchars($currentPath . '/' . $item['name']) . '\')"' : '' ?>>
                                            <td>
                                                <?php if ($item['type'] === 'directory'): ?>
                                                    <i class="fas fa-folder file-icon text-warning me-2"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file file-icon text-secondary me-2"></i>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($item['name']) ?>
                                            </td>
                                            <td><?= ucfirst($item['type']) ?></td>
                                            <td>
                                                <?php if ($item['type'] === 'file'): ?>
                                                    <?= formatFileSize($item['size']) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td><?= date('M j, Y H:i', $item['modified']) ?></td>
                                            <td><?= $item['permissions'] ?></td>
                                            <td>
                                                <?php if ($item['type'] === 'file'): ?>
                                                    <a href="filemanager.php?action=download&path=<?= urlencode($currentPath) ?>&file=<?= urlencode($item['name']) ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="delete_item">
                                                    <input type="hidden" name="item_name" value="<?= htmlspecialchars($item['name']) ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Delete <?= htmlspecialchars($item['name']) ?>?">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Folder Modal -->
    <div class="modal fade" id="createFolderModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Create New Folder</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" data-validate="true">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_folder">
                        
                        <div class="mb-3">
                            <label for="folder_name" class="form-label">Folder Name</label>
                            <input type="text" class="form-control" id="folder_name" name="folder_name" required pattern="[a-zA-Z0-9_-]+">
                            <small class="text-muted">Only letters, numbers, underscores, and hyphens allowed</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create Folder</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload File Modal -->
    <div class="modal fade" id="uploadFileModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="upload_file">
                        
                        <div class="mb-3">
                            <label for="file" class="form-label">Select File</label>
                            <input type="file" class="form-control" id="file" name="file" required>
                            <small class="text-muted">Maximum file size: 50MB</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            File will be uploaded to: <?= htmlspecialchars($currentPath) ?>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Upload File</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="../assets/js/admin.js"></script>
    <script>
        function navigateToPath(path) {
            window.location.href = 'filemanager.php?path=' + encodeURIComponent(path);
        }
    </script>
</body>
</html>

<?php
function formatFileSize($bytes) {
    if ($bytes >= 1073741824) {
        return round($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return round($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return round($bytes / 1024, 2) . ' KB';
    } else {
        return $bytes . ' bytes';
    }
}
?>