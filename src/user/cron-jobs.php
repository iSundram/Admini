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

// Handle cron job operations
if ($_POST) {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_cron') {
        $name = $_POST['name'] ?? '';
        $command = $_POST['command'] ?? '';
        $minute = $_POST['minute'] ?? '*';
        $hour = $_POST['hour'] ?? '*';
        $day = $_POST['day'] ?? '*';
        $month = $_POST['month'] ?? '*';
        $weekday = $_POST['weekday'] ?? '*';
        
        if ($name && $command) {
            // Calculate next run time
            $cronExpression = "$minute $hour $day $month $weekday";
            $nextRun = calculateNextRun($cronExpression);
            
            $stmt = $db->prepare(
                "INSERT INTO cron_jobs (user_id, name, command, minute, hour, day, month, weekday, next_run, created_at) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())"
            );
            $stmt->execute([$user['id'], $name, $command, $minute, $hour, $day, $month, $weekday, $nextRun]);
            $success = "Cron job created successfully";
        } else {
            $error = "Name and command are required";
        }
    }
    
    if ($action === 'delete_cron') {
        $cron_id = $_POST['cron_id'] ?? '';
        
        $stmt = $db->prepare("DELETE FROM cron_jobs WHERE id = ? AND user_id = ?");
        $stmt->execute([$cron_id, $user['id']]);
        $success = "Cron job deleted successfully";
    }
    
    if ($action === 'toggle_status') {
        $cron_id = $_POST['cron_id'] ?? '';
        $new_status = $_POST['new_status'] ?? '';
        
        $stmt = $db->prepare("UPDATE cron_jobs SET status = ? WHERE id = ? AND user_id = ?");
        $stmt->execute([$new_status, $cron_id, $user['id']]);
        $success = "Cron job status updated";
    }
}

// Function to calculate next run time (simplified)
function calculateNextRun($cronExpression) {
    // For simplicity, just add 1 hour to current time
    // In production, you'd use a proper cron parser library
    return date('Y-m-d H:i:s', strtotime('+1 hour'));
}

// Get user's cron jobs
$cronJobs = $db->fetchAll(
    "SELECT * FROM cron_jobs WHERE user_id = ? ORDER BY created_at DESC",
    [$user['id']]
);

// Common cron patterns
$commonPatterns = [
    ['name' => 'Every minute', 'cron' => '* * * * *'],
    ['name' => 'Every 5 minutes', 'cron' => '*/5 * * * *'],
    ['name' => 'Every 15 minutes', 'cron' => '*/15 * * * *'],
    ['name' => 'Every 30 minutes', 'cron' => '*/30 * * * *'],
    ['name' => 'Every hour', 'cron' => '0 * * * *'],
    ['name' => 'Every 2 hours', 'cron' => '0 */2 * * *'],
    ['name' => 'Every 6 hours', 'cron' => '0 */6 * * *'],
    ['name' => 'Every 12 hours', 'cron' => '0 */12 * * *'],
    ['name' => 'Daily at midnight', 'cron' => '0 0 * * *'],
    ['name' => 'Daily at noon', 'cron' => '0 12 * * *'],
    ['name' => 'Weekly (Sunday)', 'cron' => '0 0 * * 0'],
    ['name' => 'Monthly (1st)', 'cron' => '0 0 1 * *'],
];

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cron Job Manager - Admini Control Panel</title>
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
                <a href="databases.php" class="nav-link">
                    <i class="fas fa-database"></i>MySQL Databases
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Advanced</div>
                <a href="cron-jobs.php" class="nav-link active">
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
                    <h1><i class="fas fa-clock me-3"></i>Cron Job Manager</h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item active">Cron Jobs</li>
                        </ol>
                    </nav>
                </div>
                <div>
                    <span class="badge bg-primary"><?= count($cronJobs) ?> cron jobs</span>
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

            <!-- Cron Job Information -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-info-circle"></i>About Cron Jobs
                    </h5>
                </div>
                <div class="da-panel-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">What are Cron Jobs?</h6>
                            <p>Cron jobs are scheduled tasks that run automatically at specified times. They're perfect for:</p>
                            <ul class="list-unstyled">
                                <li><i class="fas fa-check text-success me-2"></i>Database backups</li>
                                <li><i class="fas fa-check text-success me-2"></i>Log file cleanup</li>
                                <li><i class="fas fa-check text-success me-2"></i>Email newsletters</li>
                                <li><i class="fas fa-check text-success me-2"></i>Data synchronization</li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6 class="text-gradient mb-3">Cron Expression Format</h6>
                            <div class="cron-format">
                                <code>* * * * *</code>
                                <div class="small text-muted mt-2">
                                    <div>Minute (0-59) Hour (0-23) Day (1-31) Month (1-12) Weekday (0-7)</div>
                                </div>
                            </div>
                            <div class="mt-3">
                                <strong>Examples:</strong>
                                <div class="small">
                                    <div><code>0 2 * * *</code> - Daily at 2:00 AM</div>
                                    <div><code>*/15 * * * *</code> - Every 15 minutes</div>
                                    <div><code>0 0 * * 0</code> - Every Sunday at midnight</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Create New Cron Job -->
            <div class="da-panel mb-4">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-plus"></i>Create New Cron Job
                    </h5>
                </div>
                <div class="da-panel-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="create_cron">
                        
                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label class="da-label">Job Name</label>
                                <input type="text" name="name" class="da-input" required placeholder="e.g., Daily Backup">
                            </div>
                            <div class="col-md-6">
                                <label class="da-label">Quick Select</label>
                                <select class="da-select" onchange="setQuickPattern(this.value)">
                                    <option value="">Choose a common pattern...</option>
                                    <?php foreach ($commonPatterns as $pattern): ?>
                                        <option value="<?= htmlspecialchars($pattern['cron']) ?>"><?= htmlspecialchars($pattern['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col">
                                <label class="da-label">Minute (0-59)</label>
                                <input type="text" name="minute" id="cron_minute" class="da-input" value="*" placeholder="*">
                            </div>
                            <div class="col">
                                <label class="da-label">Hour (0-23)</label>
                                <input type="text" name="hour" id="cron_hour" class="da-input" value="*" placeholder="*">
                            </div>
                            <div class="col">
                                <label class="da-label">Day (1-31)</label>
                                <input type="text" name="day" id="cron_day" class="da-input" value="*" placeholder="*">
                            </div>
                            <div class="col">
                                <label class="da-label">Month (1-12)</label>
                                <input type="text" name="month" id="cron_month" class="da-input" value="*" placeholder="*">
                            </div>
                            <div class="col">
                                <label class="da-label">Weekday (0-7)</label>
                                <input type="text" name="weekday" id="cron_weekday" class="da-input" value="*" placeholder="*">
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="da-label">Command</label>
                            <input type="text" name="command" class="da-input" required 
                                   placeholder="/usr/bin/php /home/<?= $user['username'] ?>/public_html/script.php">
                            <div class="form-text">
                                Enter the full path to your script or command. Use absolute paths for reliability.
                            </div>
                        </div>

                        <div class="cron-preview mb-3">
                            <label class="da-label">Preview</label>
                            <div class="preview-output">
                                <code id="cronPreview">* * * * *</code>
                                <span id="cronDescription" class="text-muted ms-2">Every minute</span>
                            </div>
                        </div>

                        <button type="submit" class="da-btn da-btn-primary">
                            <i class="fas fa-plus"></i>Create Cron Job
                        </button>
                    </form>
                </div>
            </div>

            <!-- Cron Jobs List -->
            <div class="da-panel">
                <div class="da-panel-header">
                    <h5 class="da-panel-title">
                        <i class="fas fa-list"></i>Your Cron Jobs (<?= count($cronJobs) ?>)
                    </h5>
                </div>
                <div class="da-panel-body p-0">
                    <?php if (empty($cronJobs)): ?>
                        <div class="text-center py-5">
                            <i class="fas fa-clock fa-3x text-muted mb-3"></i>
                            <h5 class="text-muted">No cron jobs scheduled</h5>
                            <p class="text-muted">Create your first cron job to automate tasks.</p>
                        </div>
                    <?php else: ?>
                        <div class="da-table">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Name</th>
                                        <th>Schedule</th>
                                        <th>Command</th>
                                        <th>Status</th>
                                        <th>Last Run</th>
                                        <th>Next Run</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($cronJobs as $cron): ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($cron['name']) ?></strong>
                                        </td>
                                        <td>
                                            <code><?= htmlspecialchars($cron['minute'] . ' ' . $cron['hour'] . ' ' . $cron['day'] . ' ' . $cron['month'] . ' ' . $cron['weekday']) ?></code>
                                        </td>
                                        <td>
                                            <code class="small text-truncate" style="max-width: 200px;" title="<?= htmlspecialchars($cron['command']) ?>">
                                                <?= htmlspecialchars($cron['command']) ?>
                                            </code>
                                        </td>
                                        <td>
                                            <?php if ($cron['status'] === 'active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cron['last_run']): ?>
                                                <span title="<?= date('Y-m-d H:i:s', strtotime($cron['last_run'])) ?>">
                                                    <?= date('M j, H:i', strtotime($cron['last_run'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">Never</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cron['next_run'] && $cron['status'] === 'active'): ?>
                                                <span title="<?= date('Y-m-d H:i:s', strtotime($cron['next_run'])) ?>">
                                                    <?= date('M j, H:i', strtotime($cron['next_run'])) ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="text-muted">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="cron_id" value="<?= $cron['id'] ?>">
                                                    <input type="hidden" name="new_status" value="<?= $cron['status'] === 'active' ? 'inactive' : 'active' ?>">
                                                    <button type="submit" class="da-btn da-btn-sm da-btn-warning" 
                                                            title="<?= $cron['status'] === 'active' ? 'Disable' : 'Enable' ?>">
                                                        <i class="fas fa-<?= $cron['status'] === 'active' ? 'pause' : 'play' ?>"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this cron job?')">
                                                    <input type="hidden" name="action" value="delete_cron">
                                                    <input type="hidden" name="cron_id" value="<?= $cron['id'] ?>">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Set quick pattern
        function setQuickPattern(cronPattern) {
            if (!cronPattern) return;
            
            const parts = cronPattern.split(' ');
            document.getElementById('cron_minute').value = parts[0] || '*';
            document.getElementById('cron_hour').value = parts[1] || '*';
            document.getElementById('cron_day').value = parts[2] || '*';
            document.getElementById('cron_month').value = parts[3] || '*';
            document.getElementById('cron_weekday').value = parts[4] || '*';
            
            updateCronPreview();
        }

        // Update cron preview
        function updateCronPreview() {
            const minute = document.getElementById('cron_minute').value || '*';
            const hour = document.getElementById('cron_hour').value || '*';
            const day = document.getElementById('cron_day').value || '*';
            const month = document.getElementById('cron_month').value || '*';
            const weekday = document.getElementById('cron_weekday').value || '*';
            
            const cronExpression = `${minute} ${hour} ${day} ${month} ${weekday}`;
            document.getElementById('cronPreview').textContent = cronExpression;
            
            // Simple description generation
            let description = 'Custom schedule';
            if (cronExpression === '* * * * *') description = 'Every minute';
            else if (cronExpression === '0 * * * *') description = 'Every hour';
            else if (cronExpression === '0 0 * * *') description = 'Daily at midnight';
            else if (cronExpression === '0 0 * * 0') description = 'Weekly on Sunday';
            else if (cronExpression === '0 0 1 * *') description = 'Monthly on the 1st';
            
            document.getElementById('cronDescription').textContent = description;
        }

        // Add event listeners to cron fields
        ['cron_minute', 'cron_hour', 'cron_day', 'cron_month', 'cron_weekday'].forEach(id => {
            document.getElementById(id).addEventListener('input', updateCronPreview);
        });

        // Initialize preview
        updateCronPreview();
    </script>

    <style>
        .cron-format {
            background: var(--da-slate-50);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 1rem;
            text-align: center;
        }
        
        .cron-format code {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
        }
        
        .preview-output {
            background: var(--da-slate-50);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: 0.75rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .preview-output code {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary);
        }
    </style>
</body>
</html>