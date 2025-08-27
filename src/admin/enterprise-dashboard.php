<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/MonitoringManager.php';
require_once '../includes/SecurityManager.php';
require_once '../includes/BackupManager.php';
require_once '../includes/ApplicationManager.php';

// Check authentication
if (!Auth::isLoggedIn() || Auth::getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$monitoring = new MonitoringManager();
$security = new SecurityManager();
$backup = new BackupManager();
$applications = new ApplicationManager();

// Get system overview data
$systemStatus = $monitoring->getSystemStatus();
$recentScans = $db->fetchAll("SELECT * FROM security_scans ORDER BY started_at DESC LIMIT 5");
$recentBackups = $backup->getBackups(Auth::getUserId(), 5);
$appStats = $applications->getApplicationStatistics();

// Get alerts and notifications
$alerts = [];
if ($systemStatus['overall_status'] === 'critical') {
    $alerts[] = ['type' => 'critical', 'message' => 'Critical system issues detected'];
} elseif ($systemStatus['overall_status'] === 'warning') {
    $alerts[] = ['type' => 'warning', 'message' => 'System warnings detected'];
}

$recentTickets = $db->fetchAll("SELECT COUNT(*) as count FROM support_tickets WHERE status = 'open'");
$openTickets = $recentTickets[0]['count'] ?? 0;
if ($openTickets > 0) {
    $alerts[] = ['type' => 'info', 'message' => "{$openTickets} open support tickets"];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enterprise Admin Dashboard - Admini</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .enterprise-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .dashboard-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .dashboard-card h3 {
            color: #3b82f6;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .status-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        
        .status-item {
            background: rgba(59, 130, 246, 0.1);
            padding: 10px;
            border-radius: 8px;
            text-align: center;
        }
        
        .status-value {
            font-size: 24px;
            font-weight: bold;
            color: #3b82f6;
        }
        
        .status-label {
            font-size: 12px;
            color: #cbd5e1;
            margin-top: 5px;
        }
        
        .alert {
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .alert.critical {
            background: rgba(239, 68, 68, 0.2);
            border-left: 4px solid #ef4444;
            color: #fca5a5;
        }
        
        .alert.warning {
            background: rgba(245, 158, 11, 0.2);
            border-left: 4px solid #f59e0b;
            color: #fde68a;
        }
        
        .alert.info {
            background: rgba(59, 130, 246, 0.2);
            border-left: 4px solid #3b82f6;
            color: #93c5fd;
        }
        
        .metric-chart {
            height: 200px;
            margin-top: 15px;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 10px;
        }
        
        .quick-action {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            text-decoration: none;
            color: white;
            transition: transform 0.2s;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .service-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .service-item {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .service-status {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .service-status.running {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .service-status.stopped {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .recent-list {
            max-height: 200px;
            overflow-y: auto;
        }
        
        .recent-item {
            padding: 8px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
            font-size: 14px;
        }
        
        .recent-item:last-child {
            border-bottom: none;
        }
        
        .enterprise-features {
            grid-column: 1 / -1;
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            border: 2px solid #3b82f6;
        }
        
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        
        .feature-card {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }
        
        .feature-icon {
            font-size: 32px;
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .auto-refresh {
            position: fixed;
            top: 20px;
            right: 20px;
            background: rgba(59, 130, 246, 0.9);
            color: white;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="auto-refresh">
        <i class="fas fa-sync-alt fa-spin"></i> Auto-refresh enabled
    </div>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-crown"></i> Enterprise Admin Dashboard</h1>
            <p>Advanced monitoring and management for enterprise hosting environments</p>
        </div>
        
        <!-- Alerts Section -->
        <?php if (!empty($alerts)): ?>
        <div class="dashboard-card">
            <h3><i class="fas fa-exclamation-triangle"></i> System Alerts</h3>
            <?php foreach ($alerts as $alert): ?>
            <div class="alert <?= $alert['type'] ?>">
                <i class="fas fa-<?= $alert['type'] === 'critical' ? 'times-circle' : ($alert['type'] === 'warning' ? 'exclamation-triangle' : 'info-circle') ?>"></i>
                <?= htmlspecialchars($alert['message']) ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
        
        <!-- Enterprise Features Overview -->
        <div class="dashboard-card enterprise-features">
            <h3><i class="fas fa-rocket"></i> Enterprise Features Active</h3>
            <div class="feature-grid">
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-shield-alt"></i></div>
                    <h4>Advanced Security</h4>
                    <p>2FA, Security Scanning, Intrusion Detection</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-cloud-upload-alt"></i></div>
                    <h4>Cloud Backups</h4>
                    <p>S3, Google Cloud, Automated Scheduling</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-chart-line"></i></div>
                    <h4>Real-time Monitoring</h4>
                    <p>System Metrics, Performance Analytics</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-download"></i></div>
                    <h4>App Installer</h4>
                    <p>WordPress, Joomla, PrestaShop, Magento</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-code-branch"></i></div>
                    <h4>API & Webhooks</h4>
                    <p>REST API, Rate Limiting, OAuth2</p>
                </div>
                <div class="feature-card">
                    <div class="feature-icon"><i class="fas fa-ticket-alt"></i></div>
                    <h4>Support System</h4>
                    <p>Ticketing, Live Chat, Customer Portal</p>
                </div>
            </div>
        </div>
        
        <div class="enterprise-dashboard">
            <!-- System Status -->
            <div class="dashboard-card">
                <h3><i class="fas fa-server"></i> System Status</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-value"><?= number_format($systemStatus['metrics']['cpu']['value'] ?? 0, 1) ?>%</div>
                        <div class="status-label">CPU Usage</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value"><?= number_format($systemStatus['metrics']['memory']['value'] ?? 0, 1) ?>%</div>
                        <div class="status-label">Memory Usage</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value"><?= number_format($systemStatus['metrics']['disk']['value'] ?? 0, 1) ?>%</div>
                        <div class="status-label">Disk Usage</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value" style="color: <?= $systemStatus['overall_status'] === 'healthy' ? '#4ade80' : ($systemStatus['overall_status'] === 'warning' ? '#f59e0b' : '#ef4444') ?>">
                            <?= ucfirst($systemStatus['overall_status']) ?>
                        </div>
                        <div class="status-label">Overall Status</div>
                    </div>
                </div>
                
                <div class="metric-chart">
                    <canvas id="systemMetricsChart"></canvas>
                </div>
            </div>
            
            <!-- Services Status -->
            <div class="dashboard-card">
                <h3><i class="fas fa-cogs"></i> System Services</h3>
                <div class="service-list">
                    <?php foreach ($systemStatus['services'] as $name => $service): ?>
                    <div class="service-item">
                        <span><?= htmlspecialchars($service['display_name']) ?></span>
                        <span class="service-status <?= $service['status'] ?>"><?= ucfirst($service['status']) ?></span>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Security Overview -->
            <div class="dashboard-card">
                <h3><i class="fas fa-shield-alt"></i> Security Center</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-value"><?= count($recentScans) ?></div>
                        <div class="status-label">Recent Scans</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value"><?= array_sum(array_column($recentScans, 'threats_found')) ?></div>
                        <div class="status-label">Threats Found</div>
                    </div>
                </div>
                
                <div class="recent-list">
                    <?php foreach (array_slice($recentScans, 0, 3) as $scan): ?>
                    <div class="recent-item">
                        <strong><?= ucfirst($scan['scan_type']) ?> Scan</strong><br>
                        <small><?= $scan['threats_found'] ?> threats found - <?= date('M j, H:i', strtotime($scan['started_at'])) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Backup Status -->
            <div class="dashboard-card">
                <h3><i class="fas fa-cloud-upload-alt"></i> Backup Center</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-value"><?= count($recentBackups) ?></div>
                        <div class="status-label">Recent Backups</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value"><?= number_format(array_sum(array_column($recentBackups, 'file_size')) / 1024 / 1024, 1) ?>MB</div>
                        <div class="status-label">Total Size</div>
                    </div>
                </div>
                
                <div class="recent-list">
                    <?php foreach (array_slice($recentBackups, 0, 3) as $backup): ?>
                    <div class="recent-item">
                        <strong><?= htmlspecialchars($backup['backup_name']) ?></strong><br>
                        <small><?= ucfirst($backup['status']) ?> - <?= date('M j, H:i', strtotime($backup['created_at'])) ?></small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Application Statistics -->
            <div class="dashboard-card">
                <h3><i class="fas fa-download"></i> Application Manager</h3>
                <div class="status-grid">
                    <div class="status-item">
                        <div class="status-value"><?= $appStats['total_installations'] ?></div>
                        <div class="status-label">Total Installations</div>
                    </div>
                    <div class="status-item">
                        <div class="status-value"><?= count($appStats['popular_apps']) ?></div>
                        <div class="status-label">Available Apps</div>
                    </div>
                </div>
                
                <div class="recent-list">
                    <h4 style="color: #3b82f6; margin: 10px 0;">Popular Applications</h4>
                    <?php foreach (array_slice($appStats['popular_apps'], 0, 3) as $app): ?>
                    <div class="recent-item">
                        <strong><?= htmlspecialchars($app['app_name']) ?></strong><br>
                        <small><?= $app['installations'] ?> installations</small>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="dashboard-card">
                <h3><i class="fas fa-bolt"></i> Quick Actions</h3>
                <div class="quick-actions">
                    <a href="security-scanner.php" class="quick-action">
                        <i class="fas fa-scan"></i><br>
                        Security Scan
                    </a>
                    <a href="backup-manager.php" class="quick-action">
                        <i class="fas fa-cloud-upload-alt"></i><br>
                        Create Backup
                    </a>
                    <a href="app-installer.php" class="quick-action">
                        <i class="fas fa-download"></i><br>
                        Install App
                    </a>
                    <a href="monitoring.php" class="quick-action">
                        <i class="fas fa-chart-line"></i><br>
                        View Metrics
                    </a>
                    <a href="api-manager.php" class="quick-action">
                        <i class="fas fa-code"></i><br>
                        API Keys
                    </a>
                    <a href="support-tickets.php" class="quick-action">
                        <i class="fas fa-ticket-alt"></i><br>
                        Support
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // System metrics chart
        const ctx = document.getElementById('systemMetricsChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['CPU', 'Memory', 'Disk', 'Network'],
                datasets: [{
                    label: 'Usage %',
                    data: [
                        <?= $systemStatus['metrics']['cpu']['value'] ?? 0 ?>,
                        <?= $systemStatus['metrics']['memory']['value'] ?? 0 ?>,
                        <?= $systemStatus['metrics']['disk']['value'] ?? 0 ?>,
                        <?= $systemStatus['metrics']['network']['value'] ?? 0 ?>
                    ],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(59, 130, 246, 0.1)'
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    },
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(59, 130, 246, 0.1)'
                        },
                        ticks: {
                            color: '#cbd5e1'
                        }
                    }
                }
            }
        });
        
        // Auto-refresh dashboard every 30 seconds
        setInterval(function() {
            location.reload();
        }, 30000);
        
        // Add real-time updates for critical metrics
        function updateMetrics() {
            fetch('../api/index.php/monitoring?type=status')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update status indicators
                        const metrics = data.data.metrics;
                        if (metrics.cpu) {
                            document.querySelector('.status-grid .status-item:nth-child(1) .status-value').textContent = 
                                parseFloat(metrics.cpu.value).toFixed(1) + '%';
                        }
                        if (metrics.memory) {
                            document.querySelector('.status-grid .status-item:nth-child(2) .status-value').textContent = 
                                parseFloat(metrics.memory.value).toFixed(1) + '%';
                        }
                        if (metrics.disk) {
                            document.querySelector('.status-grid .status-item:nth-child(3) .status-value').textContent = 
                                parseFloat(metrics.disk.value).toFixed(1) + '%';
                        }
                    }
                })
                .catch(error => console.error('Error updating metrics:', error));
        }
        
        // Update metrics every 10 seconds
        setInterval(updateMetrics, 10000);
    </script>
</body>
</html>