<?php
session_start();
require_once '../includes/Database.php';
require_once '../includes/Auth.php';
require_once '../includes/PerformanceManager.php';

// Check authentication
if (!Auth::isLoggedIn() || Auth::getUserRole() !== 'admin') {
    header('Location: ../login.php');
    exit;
}

$db = Database::getInstance();
$performance = new PerformanceManager();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    try {
        switch ($action) {
            case 'configure_redis':
                $result = $performance->configureRedis(
                    $_POST['redis_host'] ?? '127.0.0.1',
                    $_POST['redis_port'] ?? 6379,
                    $_POST['redis_password'] ?? null
                );
                $success = $result['message'];
                break;
                
            case 'configure_memcached':
                $servers = [];
                $serverList = explode("\n", $_POST['memcached_servers'] ?? '127.0.0.1:11211');
                foreach ($serverList as $server) {
                    $server = trim($server);
                    if ($server) {
                        $parts = explode(':', $server);
                        $servers[] = [$parts[0], intval($parts[1] ?? 11211)];
                    }
                }
                
                $result = $performance->configureMemcached($servers);
                $success = $result['message'];
                break;
                
            case 'configure_cdn':
                $provider = $_POST['cdn_provider'];
                $config = [];
                
                switch ($provider) {
                    case 'cloudflare':
                        $config = [
                            'api_token' => $_POST['cf_api_token'],
                            'zone_id' => $_POST['cf_zone_id']
                        ];
                        break;
                    case 'aws_cloudfront':
                        $config = [
                            'access_key' => $_POST['aws_access_key'],
                            'secret_key' => $_POST['aws_secret_key'],
                            'distribution_id' => $_POST['aws_distribution_id']
                        ];
                        break;
                }
                
                $result = $performance->configureCDN($provider, $config);
                $success = $result['message'];
                break;
                
            case 'purge_cdn':
                $urls = [];
                if (!empty($_POST['purge_urls'])) {
                    $urls = array_filter(array_map('trim', explode("\n", $_POST['purge_urls'])));
                }
                
                $result = $performance->purgeCDNCache($urls);
                $success = $result['message'];
                break;
                
            case 'optimize_database':
                $results = $performance->optimizeDatabase();
                $success = "Database optimized successfully. " . count($results) . " tables processed.";
                break;
                
            case 'enable_compression':
                $type = $_POST['compression_type'] ?? 'gzip';
                $result = $performance->configureCompression($type);
                $success = $result['message'];
                break;
                
            case 'enable_caching':
                $result = $performance->configureBrowserCaching();
                $success = $result['message'];
                break;
                
            case 'analyze_performance':
                $url = $_POST['analyze_url'];
                $analysisResult = $performance->analyzePerformance($url);
                break;
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Get performance data
$performanceReport = $performance->generatePerformanceReport();
$recommendations = $performance->getPerformanceRecommendations();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Performance Optimization - Admini</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .performance-dashboard {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
            padding: 20px;
        }
        
        .perf-card {
            background: linear-gradient(135deg, #1e293b 0%, #334155 100%);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(59, 130, 246, 0.2);
        }
        
        .perf-card h3 {
            color: #3b82f6;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .performance-score {
            text-align: center;
            margin: 20px 0;
        }
        
        .score-circle {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 24px;
            font-weight: bold;
            color: white;
            position: relative;
        }
        
        .score-excellent {
            background: conic-gradient(#10b981 0%, #10b981 90%, #374151 90%);
        }
        
        .score-good {
            background: conic-gradient(#3b82f6 0%, #3b82f6 70%, #374151 70%);
        }
        
        .score-needs-improvement {
            background: conic-gradient(#f59e0b 0%, #f59e0b 50%, #374151 50%);
        }
        
        .score-poor {
            background: conic-gradient(#ef4444 0%, #ef4444 30%, #374151 30%);
        }
        
        .feature-status {
            display: flex;
            justify-content: between;
            align-items: center;
            padding: 10px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .feature-status:last-child {
            border-bottom: none;
        }
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: bold;
        }
        
        .status-enabled {
            background: rgba(34, 197, 94, 0.2);
            color: #4ade80;
        }
        
        .status-disabled {
            background: rgba(239, 68, 68, 0.2);
            color: #f87171;
        }
        
        .recommendation-card {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            border-left: 4px solid #3b82f6;
        }
        
        .recommendation-card.high {
            border-left-color: #ef4444;
            background: rgba(239, 68, 68, 0.1);
        }
        
        .recommendation-card.medium {
            border-left-color: #f59e0b;
            background: rgba(245, 158, 11, 0.1);
        }
        
        .priority-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 10px;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
        }
        
        .priority-high {
            background: #ef4444;
            color: white;
        }
        
        .priority-medium {
            background: #f59e0b;
            color: white;
        }
        
        .priority-low {
            background: #10b981;
            color: white;
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
        
        .config-section {
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(59, 130, 246, 0.1);
            border-radius: 8px;
        }
        
        .config-section h4 {
            color: #3b82f6;
            margin-bottom: 10px;
        }
        
        .full-width {
            grid-column: 1 / -1;
        }
        
        .analysis-results {
            background: rgba(59, 130, 246, 0.1);
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .metric-row {
            display: flex;
            justify-content: between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(59, 130, 246, 0.1);
        }
        
        .metric-row:last-child {
            border-bottom: none;
        }
        
        .metric-value {
            font-weight: bold;
            color: #3b82f6;
        }
        
        .cdn-controls {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 10px;
            align-items: end;
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            margin-top: 15px;
        }
        
        .quick-action {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            color: white;
            text-decoration: none;
            transition: transform 0.2s;
            cursor: pointer;
            border: none;
            font-size: 14px;
        }
        
        .quick-action:hover {
            transform: translateY(-2px);
            color: white;
        }
        
        .database-size {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
            text-align: center;
            margin: 10px 0;
        }
    </style>
</head>
<body class="admin-body">
    <?php include '../includes/admin_nav.php'; ?>
    
    <div class="admin-content">
        <div class="admin-header">
            <h1><i class="fas fa-tachometer-alt"></i> Performance Optimization</h1>
            <p>Optimize your hosting environment for maximum performance</p>
        </div>
        
        <?php if (isset($success)): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        
        <?php if (isset($error)): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
        
        <div class="performance-dashboard">
            <!-- Overall Performance Score -->
            <div class="perf-card">
                <h3><i class="fas fa-gauge-high"></i> Performance Score</h3>
                <div class="performance-score">
                    <?php
                    $score = $performanceReport['overall_score'];
                    $scoreClass = $score >= 80 ? 'score-excellent' : ($score >= 60 ? 'score-good' : ($score >= 40 ? 'score-needs-improvement' : 'score-poor'));
                    ?>
                    <div class="score-circle <?= $scoreClass ?>">
                        <span><?= $score ?></span>
                    </div>
                    <div style="color: #cbd5e1;">
                        <?php if ($score >= 80): ?>
                            Excellent Performance
                        <?php elseif ($score >= 60): ?>
                            Good Performance
                        <?php elseif ($score >= 40): ?>
                            Needs Improvement
                        <?php else: ?>
                            Poor Performance
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="quick-actions">
                    <button onclick="openAnalysisModal()" class="quick-action">
                        <i class="fas fa-chart-line"></i><br>
                        Analyze Website
                    </button>
                    <button onclick="openOptimizationModal()" class="quick-action">
                        <i class="fas fa-magic"></i><br>
                        Auto Optimize
                    </button>
                </div>
            </div>
            
            <!-- Caching Status -->
            <div class="perf-card">
                <h3><i class="fas fa-memory"></i> Caching</h3>
                <div class="feature-status">
                    <span>Redis Cache</span>
                    <span class="status-badge <?= $performanceReport['caching']['redis_available'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['caching']['redis_available'] ? 'Available' : 'Not Available' ?>
                    </span>
                </div>
                <div class="feature-status">
                    <span>Memcached</span>
                    <span class="status-badge <?= $performanceReport['caching']['memcached_available'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['caching']['memcached_available'] ? 'Available' : 'Not Available' ?>
                    </span>
                </div>
                <div class="feature-status">
                    <span>OPcache</span>
                    <span class="status-badge <?= $performanceReport['caching']['opcache_enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['caching']['opcache_enabled'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                <div class="feature-status">
                    <span>Browser Caching</span>
                    <span class="status-badge <?= $performanceReport['caching']['browser_caching'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['caching']['browser_caching'] ? 'Enabled' : 'Disabled' ?>
                    </span>
                </div>
                
                <div class="quick-actions">
                    <button onclick="openCacheModal('redis')" class="quick-action">
                        <i class="fab fa-redis"></i><br>
                        Configure Redis
                    </button>
                    <button onclick="openCacheModal('memcached')" class="quick-action">
                        <i class="fas fa-server"></i><br>
                        Configure Memcached
                    </button>
                </div>
            </div>
            
            <!-- Compression -->
            <div class="perf-card">
                <h3><i class="fas fa-compress-alt"></i> Compression</h3>
                <div class="feature-status">
                    <span>Gzip Compression</span>
                    <span class="status-badge <?= $performanceReport['compression']['gzip_available'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['compression']['gzip_available'] ? 'Available' : 'Not Available' ?>
                    </span>
                </div>
                <div class="feature-status">
                    <span>Brotli Compression</span>
                    <span class="status-badge <?= $performanceReport['compression']['brotli_available'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['compression']['brotli_available'] ? 'Available' : 'Not Available' ?>
                    </span>
                </div>
                <div class="feature-status">
                    <span>Compression Enabled</span>
                    <span class="status-badge <?= $performanceReport['compression']['enabled'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['compression']['enabled'] ? 'Yes' : 'No' ?>
                    </span>
                </div>
                
                <div class="quick-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="enable_compression">
                        <input type="hidden" name="compression_type" value="gzip">
                        <button type="submit" class="quick-action">
                            <i class="fas fa-compress"></i><br>
                            Enable Gzip
                        </button>
                    </form>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="enable_caching">
                        <button type="submit" class="quick-action">
                            <i class="fas fa-clock"></i><br>
                            Enable Browser Cache
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- CDN -->
            <div class="perf-card">
                <h3><i class="fas fa-globe"></i> Content Delivery Network</h3>
                <div class="feature-status">
                    <span>CDN Status</span>
                    <span class="status-badge <?= $performanceReport['cdn']['configured'] ? 'status-enabled' : 'status-disabled' ?>">
                        <?= $performanceReport['cdn']['configured'] ? 'Configured' : 'Not Configured' ?>
                    </span>
                </div>
                
                <?php if ($performanceReport['cdn']['configured']): ?>
                <div class="cdn-controls">
                    <form method="POST">
                        <input type="hidden" name="action" value="purge_cdn">
                        <div class="form-group">
                            <label>URLs to purge (leave empty for all):</label>
                            <textarea name="purge_urls" class="form-control" rows="3" placeholder="https://example.com/file1.css&#10;https://example.com/file2.js"></textarea>
                        </div>
                        <button type="submit" class="btn btn-warning">
                            <i class="fas fa-broom"></i> Purge Cache
                        </button>
                    </form>
                </div>
                <?php else: ?>
                <div class="quick-actions">
                    <button onclick="openCDNModal()" class="quick-action">
                        <i class="fab fa-cloudflare"></i><br>
                        Setup CDN
                    </button>
                </div>
                <?php endif; ?>
            </div>
            
            <!-- Database Optimization -->
            <div class="perf-card">
                <h3><i class="fas fa-database"></i> Database</h3>
                <div class="database-size">
                    Database Size: <?= $performanceReport['database']['size_mb'] ?> MB
                </div>
                <div class="feature-status">
                    <span>Optimization Needed</span>
                    <span class="status-badge <?= $performanceReport['database']['optimization_needed'] ? 'status-disabled' : 'status-enabled' ?>">
                        <?= $performanceReport['database']['optimization_needed'] ? 'Yes' : 'No' ?>
                    </span>
                </div>
                
                <div class="quick-actions">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="optimize_database">
                        <button type="submit" class="quick-action" onclick="return confirm('This will optimize all database tables. Continue?')">
                            <i class="fas fa-wrench"></i><br>
                            Optimize Database
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Performance Analysis Results -->
            <?php if (isset($analysisResult)): ?>
            <div class="perf-card full-width">
                <h3><i class="fas fa-chart-bar"></i> Performance Analysis Results</h3>
                <div class="analysis-results">
                    <h4>Metrics for: <?= htmlspecialchars($analysisResult['url']) ?></h4>
                    <div class="metric-row">
                        <span>Performance Score</span>
                        <span class="metric-value"><?= $analysisResult['performance_score'] ?>/100</span>
                    </div>
                    <div class="metric-row">
                        <span>Total Load Time</span>
                        <span class="metric-value"><?= $analysisResult['metrics']['total_time'] ?>s</span>
                    </div>
                    <div class="metric-row">
                        <span>DNS Resolution</span>
                        <span class="metric-value"><?= $analysisResult['metrics']['dns_time'] ?>s</span>
                    </div>
                    <div class="metric-row">
                        <span>Connection Time</span>
                        <span class="metric-value"><?= $analysisResult['metrics']['connect_time'] ?>s</span>
                    </div>
                    <div class="metric-row">
                        <span>Download Size</span>
                        <span class="metric-value"><?= number_format($analysisResult['metrics']['size_download'] / 1024, 2) ?> KB</span>
                    </div>
                    <div class="metric-row">
                        <span>Download Speed</span>
                        <span class="metric-value"><?= number_format($analysisResult['metrics']['speed_download'] / 1024, 2) ?> KB/s</span>
                    </div>
                    
                    <?php if (isset($analysisResult['content_analysis']['recommendations'])): ?>
                    <h4 style="margin-top: 20px;">Recommendations</h4>
                    <?php foreach ($analysisResult['content_analysis']['recommendations'] as $rec): ?>
                    <div class="recommendation-card <?= strtolower($rec['priority']) ?>">
                        <span class="priority-badge priority-<?= strtolower($rec['priority']) ?>"><?= $rec['priority'] ?></span>
                        <strong><?= $rec['type'] ?>:</strong> <?= htmlspecialchars($rec['message']) ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Recommendations -->
            <div class="perf-card full-width">
                <h3><i class="fas fa-lightbulb"></i> Performance Recommendations</h3>
                <?php foreach ($recommendations as $rec): ?>
                <div class="recommendation-card <?= strtolower($rec['priority']) ?>">
                    <div style="display: flex; justify-content: between; align-items: center; margin-bottom: 5px;">
                        <strong><?= htmlspecialchars($rec['title']) ?></strong>
                        <span class="priority-badge priority-<?= strtolower($rec['priority']) ?>"><?= $rec['priority'] ?></span>
                    </div>
                    <div style="margin-bottom: 8px; color: #cbd5e1;"><?= htmlspecialchars($rec['description']) ?></div>
                    <div style="font-size: 12px; color: #94a3b8;"><strong>Action:</strong> <?= htmlspecialchars($rec['action']) ?></div>
                </div>
                <?php endforeach; ?>
                
                <?php if (empty($recommendations)): ?>
                <div style="text-align: center; padding: 20px; color: #10b981;">
                    <i class="fas fa-check-circle" style="font-size: 32px; margin-bottom: 10px;"></i>
                    <h4>Excellent!</h4>
                    <p>Your hosting environment is well-optimized. No immediate recommendations.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Performance Analysis Modal -->
    <div id="analysisModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('analysisModal')">&times;</span>
            <h3><i class="fas fa-chart-line"></i> Website Performance Analysis</h3>
            <form method="POST">
                <input type="hidden" name="action" value="analyze_performance">
                <div class="form-group">
                    <label>Website URL to Analyze:</label>
                    <input type="url" name="analyze_url" class="form-control" placeholder="https://example.com" required>
                    <small>Enter the full URL of the website you want to analyze</small>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeModal('analysisModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-chart-line"></i> Analyze Performance
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- CDN Configuration Modal -->
    <div id="cdnModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('cdnModal')">&times;</span>
            <h3><i class="fas fa-globe"></i> Configure CDN</h3>
            <form method="POST" id="cdnForm">
                <input type="hidden" name="action" value="configure_cdn">
                
                <div class="form-group">
                    <label>CDN Provider:</label>
                    <select name="cdn_provider" class="form-control" onchange="showCDNConfig(this.value)" required>
                        <option value="">Select Provider</option>
                        <option value="cloudflare">Cloudflare</option>
                        <option value="aws_cloudfront">AWS CloudFront</option>
                        <option value="maxcdn">MaxCDN</option>
                        <option value="keycdn">KeyCDN</option>
                    </select>
                </div>
                
                <div id="cloudflareConfig" class="config-section" style="display: none;">
                    <h4>Cloudflare Configuration</h4>
                    <div class="form-group">
                        <label>API Token:</label>
                        <input type="text" name="cf_api_token" class="form-control" placeholder="Your Cloudflare API token">
                    </div>
                    <div class="form-group">
                        <label>Zone ID:</label>
                        <input type="text" name="cf_zone_id" class="form-control" placeholder="Your Cloudflare zone ID">
                    </div>
                </div>
                
                <div id="awsConfig" class="config-section" style="display: none;">
                    <h4>AWS CloudFront Configuration</h4>
                    <div class="form-group">
                        <label>Access Key:</label>
                        <input type="text" name="aws_access_key" class="form-control" placeholder="AWS access key">
                    </div>
                    <div class="form-group">
                        <label>Secret Key:</label>
                        <input type="password" name="aws_secret_key" class="form-control" placeholder="AWS secret key">
                    </div>
                    <div class="form-group">
                        <label>Distribution ID:</label>
                        <input type="text" name="aws_distribution_id" class="form-control" placeholder="CloudFront distribution ID">
                    </div>
                </div>
                
                <div style="display: flex; gap: 10px; margin-top: 20px;">
                    <button type="button" onclick="closeModal('cdnModal')" class="btn btn-secondary">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Configure CDN
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Cache Configuration Modal -->
    <div id="cacheModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('cacheModal')">&times;</span>
            <h3 id="cacheModalTitle"><i class="fas fa-memory"></i> Configure Cache</h3>
            <div id="cacheModalContent">
                <!-- Content will be populated by JavaScript -->
            </div>
        </div>
    </div>
    
    <script>
        function openAnalysisModal() {
            document.getElementById('analysisModal').style.display = 'block';
        }
        
        function openCDNModal() {
            document.getElementById('cdnModal').style.display = 'block';
        }
        
        function openCacheModal(type) {
            const modal = document.getElementById('cacheModal');
            const title = document.getElementById('cacheModalTitle');
            const content = document.getElementById('cacheModalContent');
            
            if (type === 'redis') {
                title.innerHTML = '<i class="fab fa-redis"></i> Configure Redis Cache';
                content.innerHTML = `
                    <form method="POST">
                        <input type="hidden" name="action" value="configure_redis">
                        <div class="form-group">
                            <label>Redis Host:</label>
                            <input type="text" name="redis_host" class="form-control" value="127.0.0.1" required>
                        </div>
                        <div class="form-group">
                            <label>Redis Port:</label>
                            <input type="number" name="redis_port" class="form-control" value="6379" required>
                        </div>
                        <div class="form-group">
                            <label>Password (optional):</label>
                            <input type="password" name="redis_password" class="form-control" placeholder="Leave empty if no password">
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="button" onclick="closeModal('cacheModal')" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fab fa-redis"></i> Configure Redis
                            </button>
                        </div>
                    </form>
                `;
            } else if (type === 'memcached') {
                title.innerHTML = '<i class="fas fa-server"></i> Configure Memcached';
                content.innerHTML = `
                    <form method="POST">
                        <input type="hidden" name="action" value="configure_memcached">
                        <div class="form-group">
                            <label>Memcached Servers (one per line):</label>
                            <textarea name="memcached_servers" class="form-control" rows="4" placeholder="127.0.0.1:11211" required>127.0.0.1:11211</textarea>
                            <small>Format: host:port (one per line)</small>
                        </div>
                        <div style="display: flex; gap: 10px; margin-top: 20px;">
                            <button type="button" onclick="closeModal('cacheModal')" class="btn btn-secondary">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-server"></i> Configure Memcached
                            </button>
                        </div>
                    </form>
                `;
            }
            
            modal.style.display = 'block';
        }
        
        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }
        
        function showCDNConfig(provider) {
            // Hide all config sections
            document.querySelectorAll('.config-section').forEach(section => {
                section.style.display = 'none';
            });
            
            // Show selected provider config
            if (provider === 'cloudflare') {
                document.getElementById('cloudflareConfig').style.display = 'block';
            } else if (provider === 'aws_cloudfront') {
                document.getElementById('awsConfig').style.display = 'block';
            }
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }
    </script>
</body>
</html>