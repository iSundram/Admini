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

// Handle mail queue operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete_mail') {
        $mail_id = $_POST['mail_id'] ?? '';
        $stmt = $db->prepare("DELETE FROM mail_queue WHERE id = ?");
        $stmt->execute([$mail_id]);
        $success = "Mail deleted from queue";
    }
    
    if ($action === 'retry_mail') {
        $mail_id = $_POST['mail_id'] ?? '';
        $stmt = $db->prepare("UPDATE mail_queue SET status = 'pending', attempts = 0 WHERE id = ?");
        $stmt->execute([$mail_id]);
        $success = "Mail marked for retry";
    }
    
    if ($action === 'flush_queue') {
        $status = $_POST['status'] ?? '';
        if ($status === 'all') {
            $db->query("DELETE FROM mail_queue");
        } else {
            $stmt = $db->prepare("DELETE FROM mail_queue WHERE status = ?");
            $stmt->execute([$status]);
        }
        $success = "Mail queue flushed";
    }
}

// Get mail queue statistics
$queueStats = [
    'pending' => $db->fetch("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'pending'")['count'],
    'sent' => $db->fetch("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'sent'")['count'],
    'failed' => $db->fetch("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'failed'")['count'],
    'deferred' => $db->fetch("SELECT COUNT(*) as count FROM mail_queue WHERE status = 'deferred'")['count'],
];

$totalMails = array_sum($queueStats);

// Get recent mail queue entries
$filter = $_GET['filter'] ?? 'all';
$whereClause = '';
$params = [];

if ($filter !== 'all') {
    $whereClause = 'WHERE status = ?';
    $params[] = $filter;
}

$mailQueue = $db->fetchAll(
    "SELECT * FROM mail_queue $whereClause ORDER BY created_at DESC LIMIT 100",
    $params
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Queue Manager - Admini Control Panel</title>
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
                <a href="mail-queue.php" class="nav-link active">
                    <i class="fas fa-envelope"></i>Mail Queue
                    <?php if ($queueStats['pending'] > 0): ?>
                        <span class="badge bg-warning"><?= $queueStats['pending'] ?></span>
                    <?php endif; ?>
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
                    <h1><i class="fas fa-envelope me-3"></i>Mail Queue Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Mail Queue</li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="da-btn da-btn-warning" data-bs-toggle="modal" data-bs-target="#flushModal">
                        <i class="fas fa-trash"></i>Flush Queue
                    </button>
                    <button type="button" class="da-btn da-btn-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt"></i>Refresh
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

            <!-- Mail Queue Statistics -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="da-stat-value"><?= $totalMails ?></div>
                        <div class="da-stat-label">Total Messages</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--warning) 0%, #d97706 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                        <div class="da-stat-value"><?= $queueStats['pending'] ?></div>
                        <div class="da-stat-label">Pending</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                            <i class="fas fa-check"></i>
                        </div>
                        <div class="da-stat-value"><?= $queueStats['sent'] ?></div>
                        <div class="da-stat-label">Sent</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="da-stat-card">
                        <div class="da-stat-icon" style="background: linear-gradient(135deg, var(--danger) 0%, #dc2626 100%);">
                            <i class="fas fa-times"></i>
                        </div>
                        <div class="da-stat-value"><?= $queueStats['failed'] + $queueStats['deferred'] ?></div>
                        <div class="da-stat-label">Failed/Deferred</div>
                    </div>
                </div>
            </div>

            <!-- Filter Tabs -->
            <div class="da-tabs">
                <a href="?filter=all" class="da-tab <?= $filter === 'all' ? 'active' : '' ?>">
                    All Messages (<?= $totalMails ?>)
                </a>
                <a href="?filter=pending" class="da-tab <?= $filter === 'pending' ? 'active' : '' ?>">
                    Pending (<?= $queueStats['pending'] ?>)
                </a>
                <a href="?filter=sent" class="da-tab <?= $filter === 'sent' ? 'active' : '' ?>">
                    Sent (<?= $queueStats['sent'] ?>)
                </a>
                <a href="?filter=failed" class="da-tab <?= $filter === 'failed' ? 'active' : '' ?>">
                    Failed (<?= $queueStats['failed'] ?>)
                </a>
                <a href="?filter=deferred" class="da-tab <?= $filter === 'deferred' ? 'active' : '' ?>">
                    Deferred (<?= $queueStats['deferred'] ?>)
                </a>
            </div>

            <!-- Mail Queue Table -->
            <div class="da-panel">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-list"></i>Mail Queue Entries
                    </h5>
                </div>
                <div class="da-panel-body p-0">
                    <?php if (empty($mailQueue)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No messages in queue</h5>
                            <p class="text-muted">The mail queue is empty for the selected filter.</p>
                        </div>
                    <?php else: ?>
                        <div class="da-table">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>From</th>
                                        <th>To</th>
                                        <th>Subject</th>
                                        <th>Status</th>
                                        <th>Attempts</th>
                                        <th>Created</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($mailQueue as $mail): ?>
                                    <tr>
                                        <td>
                                            <code><?= $mail['id'] ?></code>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($mail['from_address']) ?>">
                                                <?= htmlspecialchars($mail['from_address']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($mail['to_address']) ?>">
                                                <?= htmlspecialchars($mail['to_address']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="text-truncate" style="max-width: 250px;" title="<?= htmlspecialchars($mail['subject']) ?>">
                                                <?= htmlspecialchars($mail['subject']) ?>
                                            </div>
                                        </td>
                                        <td>
                                            <?php
                                            $statusClass = '';
                                            $statusIcon = '';
                                            switch ($mail['status']) {
                                                case 'pending':
                                                    $statusClass = 'bg-warning';
                                                    $statusIcon = 'fas fa-clock';
                                                    break;
                                                case 'sent':
                                                    $statusClass = 'bg-success';
                                                    $statusIcon = 'fas fa-check';
                                                    break;
                                                case 'failed':
                                                    $statusClass = 'bg-danger';
                                                    $statusIcon = 'fas fa-times';
                                                    break;
                                                case 'deferred':
                                                    $statusClass = 'bg-info';
                                                    $statusIcon = 'fas fa-pause';
                                                    break;
                                            }
                                            ?>
                                            <span class="badge <?= $statusClass ?>">
                                                <i class="<?= $statusIcon ?>"></i>
                                                <?= ucfirst($mail['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?= $mail['attempts'] ?>
                                            <?php if ($mail['attempts'] > 0 && $mail['last_attempt']): ?>
                                                <br><small class="text-muted">
                                                    Last: <?= date('M j, H:i', strtotime($mail['last_attempt'])) ?>
                                                </small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span title="<?= date('Y-m-d H:i:s', strtotime($mail['created_at'])) ?>">
                                                <?= date('M j, H:i', strtotime($mail['created_at'])) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="da-btn da-btn-sm da-btn-secondary" 
                                                        data-bs-toggle="modal" 
                                                        data-bs-target="#viewMailModal" 
                                                        data-mail='<?= json_encode($mail) ?>'>
                                                    <i class="fas fa-eye"></i>
                                                </button>
                                                <?php if ($mail['status'] === 'failed' || $mail['status'] === 'deferred'): ?>
                                                    <form method="POST" class="d-inline">
                                                        <input type="hidden" name="action" value="retry_mail">
                                                        <input type="hidden" name="mail_id" value="<?= $mail['id'] ?>">
                                                        <button type="submit" class="da-btn da-btn-sm da-btn-warning" title="Retry">
                                                            <i class="fas fa-redo"></i>
                                                        </button>
                                                    </form>
                                                <?php endif; ?>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this mail?')">
                                                    <input type="hidden" name="action" value="delete_mail">
                                                    <input type="hidden" name="mail_id" value="<?= $mail['id'] ?>">
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
    </div>

    <!-- View Mail Modal -->
    <div class="modal fade" id="viewMailModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Mail Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="da-label">From</label>
                            <input type="text" id="mail_from" class="da-input" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="da-label">To</label>
                            <input type="text" id="mail_to" class="da-input" readonly>
                        </div>
                        <div class="col-12">
                            <label class="da-label">Subject</label>
                            <input type="text" id="mail_subject" class="da-input" readonly>
                        </div>
                        <div class="col-12">
                            <label class="da-label">Message</label>
                            <textarea id="mail_message" class="da-textarea" rows="10" readonly></textarea>
                        </div>
                        <div class="col-md-6">
                            <label class="da-label">Status</label>
                            <input type="text" id="mail_status" class="da-input" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="da-label">Attempts</label>
                            <input type="text" id="mail_attempts" class="da-input" readonly>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Flush Queue Modal -->
    <div class="modal fade" id="flushModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Flush Mail Queue</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="flush_queue">
                        
                        <div class="da-alert da-alert-warning">
                            <i class="fas fa-exclamation-triangle"></i>
                            <div>This action will permanently delete mail messages from the queue.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="da-label">What to flush</label>
                            <select name="status" class="da-select" required>
                                <option value="">Select...</option>
                                <option value="all">All messages</option>
                                <option value="sent">Sent messages only</option>
                                <option value="failed">Failed messages only</option>
                                <option value="deferred">Deferred messages only</option>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="da-btn da-btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="da-btn da-btn-danger">Flush Queue</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Handle view mail modal
        document.getElementById('viewMailModal').addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            const mail = JSON.parse(button.getAttribute('data-mail'));
            
            document.getElementById('mail_from').value = mail.from_address;
            document.getElementById('mail_to').value = mail.to_address;
            document.getElementById('mail_subject').value = mail.subject;
            document.getElementById('mail_message').value = mail.message;
            document.getElementById('mail_status').value = mail.status.charAt(0).toUpperCase() + mail.status.slice(1);
            document.getElementById('mail_attempts').value = mail.attempts;
        });
    </script>
</body>
</html>