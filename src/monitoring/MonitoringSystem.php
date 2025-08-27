<?php
/**
 * Advanced Real-Time Monitoring System
 * Comprehensive system monitoring with alerting and analytics
 */

class MonitoringSystem {
    private $database;
    private $cache;
    private $eventStream;
    private $alertManager;
    private $collectors = [];
    
    public function __construct($database, $cache, $eventStream) {
        $this->database = $database;
        $this->cache = $cache;
        $this->eventStream = $eventStream;
        $this->alertManager = new AlertManager($database, $cache);
        $this->initializeCollectors();
    }
    
    /**
     * Start monitoring system
     */
    public function start() {
        echo "Starting monitoring system...\n";
        
        // Start metric collection
        $this->startMetricCollection();
        
        // Start alert processing
        $this->alertManager->start();
        
        // Start real-time dashboard updates
        $this->startDashboardUpdates();
    }
    
    /**
     * Collect and process metrics
     */
    public function collectMetrics() {
        $timestamp = time();
        $allMetrics = [];
        
        foreach ($this->collectors as $name => $collector) {
            try {
                $metrics = $collector->collect();
                $allMetrics[$name] = $metrics;
                
                // Store metrics
                $this->storeMetrics($name, $metrics, $timestamp);
                
                // Check for alerts
                $this->checkAlerts($name, $metrics);
                
                // Publish real-time updates
                $this->publishMetrics($name, $metrics);
                
            } catch (Exception $e) {
                error_log("Metric collection error for {$name}: " . $e->getMessage());
            }
        }
        
        return $allMetrics;
    }
    
    /**
     * Get historical metrics
     */
    public function getHistoricalMetrics($metric, $startTime, $endTime, $interval = '5m') {
        $query = "SELECT 
                    timestamp,
                    AVG(value) as avg_value,
                    MIN(value) as min_value,
                    MAX(value) as max_value,
                    COUNT(*) as data_points
                  FROM metric_data 
                  WHERE metric_name = ? 
                    AND timestamp BETWEEN ? AND ?
                  GROUP BY FLOOR(UNIX_TIMESTAMP(timestamp) / ?)
                  ORDER BY timestamp";
        
        $intervalSeconds = $this->parseInterval($interval);
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sssi', $metric, 
            date('Y-m-d H:i:s', $startTime),
            date('Y-m-d H:i:s', $endTime),
            $intervalSeconds
        );
        $stmt->execute();
        
        $result = $stmt->get_result();
        $data = [];
        
        while ($row = $result->fetch_assoc()) {
            $data[] = [
                'timestamp' => strtotime($row['timestamp']),
                'avg' => (float)$row['avg_value'],
                'min' => (float)$row['min_value'],
                'max' => (float)$row['max_value'],
                'points' => (int)$row['data_points']
            ];
        }
        
        return $data;
    }
    
    /**
     * Get real-time metrics
     */
    public function getRealTimeMetrics() {
        $metrics = [];
        
        foreach ($this->collectors as $name => $collector) {
            $cacheKey = "realtime_metrics:{$name}";
            $cached = $this->cache->get($cacheKey);
            
            if ($cached) {
                $metrics[$name] = json_decode($cached, true);
            }
        }
        
        return $metrics;
    }
    
    /**
     * Create custom dashboard
     */
    public function createDashboard($name, $config) {
        $dashboardId = uniqid('dash_');
        
        $dashboard = [
            'id' => $dashboardId,
            'name' => $name,
            'config' => json_encode($config),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        $query = "INSERT INTO monitoring_dashboards (id, name, config, created_at, updated_at) 
                  VALUES (?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sssss',
            $dashboard['id'],
            $dashboard['name'],
            $dashboard['config'],
            $dashboard['created_at'],
            $dashboard['updated_at']
        );
        
        $stmt->execute();
        
        return $dashboardId;
    }
    
    private function initializeCollectors() {
        $this->collectors = [
            'system' => new SystemMetricsCollector(),
            'application' => new ApplicationMetricsCollector($this->database),
            'network' => new NetworkMetricsCollector(),
            'security' => new SecurityMetricsCollector($this->database),
            'performance' => new PerformanceMetricsCollector(),
            'business' => new BusinessMetricsCollector($this->database)
        ];
    }
    
    private function startMetricCollection() {
        // Run metric collection in background
        $pid = pcntl_fork();
        
        if ($pid === 0) {
            while (true) {
                $this->collectMetrics();
                sleep(30); // Collect every 30 seconds
            }
        }
    }
    
    private function startDashboardUpdates() {
        // Push real-time updates to connected clients
        $pid = pcntl_fork();
        
        if ($pid === 0) {
            while (true) {
                $this->pushDashboardUpdates();
                sleep(5); // Update every 5 seconds
            }
        }
    }
    
    private function storeMetrics($collectorName, $metrics, $timestamp) {
        foreach ($metrics as $metricName => $value) {
            if (is_numeric($value)) {
                $query = "INSERT INTO metric_data (collector, metric_name, value, timestamp) 
                          VALUES (?, ?, ?, ?)";
                
                $stmt = $this->database->prepare($query);
                $stmt->bind_param('ssds',
                    $collectorName,
                    $metricName,
                    $value,
                    date('Y-m-d H:i:s', $timestamp)
                );
                
                $stmt->execute();
            }
        }
    }
    
    private function checkAlerts($collectorName, $metrics) {
        $this->alertManager->checkMetrics($collectorName, $metrics);
    }
    
    private function publishMetrics($collectorName, $metrics) {
        // Cache for real-time access
        $cacheKey = "realtime_metrics:{$collectorName}";
        $this->cache->set($cacheKey, json_encode($metrics), 60);
        
        // Publish to event stream
        $this->eventStream->publishEvent(
            'monitoring',
            'metrics_collected',
            [
                'collector' => $collectorName,
                'metrics' => $metrics,
                'timestamp' => time()
            ]
        );
    }
    
    private function pushDashboardUpdates() {
        $realTimeMetrics = $this->getRealTimeMetrics();
        
        // Push to WebSocket clients (would need WebSocket server)
        $this->cache->set('dashboard_update', json_encode([
            'type' => 'metrics_update',
            'data' => $realTimeMetrics,
            'timestamp' => time()
        ]), 10);
    }
    
    private function parseInterval($interval) {
        preg_match('/(\d+)([smhd])/', $interval, $matches);
        $value = (int)$matches[1];
        $unit = $matches[2];
        
        switch ($unit) {
            case 's': return $value;
            case 'm': return $value * 60;
            case 'h': return $value * 3600;
            case 'd': return $value * 86400;
            default: return 300; // 5 minutes
        }
    }
}

/**
 * System Metrics Collector
 */
class SystemMetricsCollector {
    public function collect() {
        return [
            'cpu_usage' => $this->getCpuUsage(),
            'memory_usage' => $this->getMemoryUsage(),
            'disk_usage' => $this->getDiskUsage(),
            'load_average_1m' => $this->getLoadAverage()[0],
            'load_average_5m' => $this->getLoadAverage()[1],
            'load_average_15m' => $this->getLoadAverage()[2],
            'network_rx_bytes' => $this->getNetworkStats()['rx_bytes'],
            'network_tx_bytes' => $this->getNetworkStats()['tx_bytes'],
            'uptime' => $this->getUptime()
        ];
    }
    
    private function getCpuUsage() {
        $load = sys_getloadavg();
        $cores = (int)shell_exec('nproc');
        return min(100, ($load[0] / $cores) * 100);
    }
    
    private function getMemoryUsage() {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        
        $total = $total[1] * 1024;
        $available = $available[1] * 1024;
        
        return (($total - $available) / $total) * 100;
    }
    
    private function getDiskUsage() {
        $output = shell_exec('df / | tail -1');
        $parts = preg_split('/\s+/', $output);
        return (int)str_replace('%', '', $parts[4]);
    }
    
    private function getLoadAverage() {
        return sys_getloadavg();
    }
    
    private function getNetworkStats() {
        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        
        $totalRx = 0;
        $totalTx = 0;
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:]+):\s*(.+)$/', $line, $matches)) {
                $interface = trim($matches[1]);
                if ($interface !== 'lo') {
                    $values = preg_split('/\s+/', trim($matches[2]));
                    $totalRx += $values[0];
                    $totalTx += $values[8];
                }
            }
        }
        
        return ['rx_bytes' => $totalRx, 'tx_bytes' => $totalTx];
    }
    
    private function getUptime() {
        $uptime = file_get_contents('/proc/uptime');
        return (float)explode(' ', $uptime)[0];
    }
}

/**
 * Application Metrics Collector
 */
class ApplicationMetricsCollector {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    public function collect() {
        return [
            'active_users' => $this->getActiveUsers(),
            'total_users' => $this->getTotalUsers(),
            'active_domains' => $this->getActiveDomains(),
            'email_queue_size' => $this->getEmailQueueSize(),
            'disk_usage_total' => $this->getTotalDiskUsage(),
            'backup_jobs_pending' => $this->getPendingBackupJobs(),
            'security_alerts_open' => $this->getOpenSecurityAlerts(),
            'api_requests_per_minute' => $this->getApiRequestsPerMinute(),
            'database_connections' => $this->getDatabaseConnections(),
            'cache_hit_rate' => $this->getCacheHitRate()
        ];
    }
    
    private function getActiveUsers() {
        $query = "SELECT COUNT(*) as count FROM users WHERE last_login > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getTotalUsers() {
        $query = "SELECT COUNT(*) as count FROM users WHERE status = 'active'";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getActiveDomains() {
        $query = "SELECT COUNT(*) as count FROM domains WHERE status = 'active'";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getEmailQueueSize() {
        $query = "SELECT COUNT(*) as count FROM email_queue WHERE status = 'pending'";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getTotalDiskUsage() {
        $query = "SELECT SUM(disk_used) as total FROM users";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['total'] ?? 0;
    }
    
    private function getPendingBackupJobs() {
        $query = "SELECT COUNT(*) as count FROM backup_jobs WHERE status = 'pending'";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getOpenSecurityAlerts() {
        $query = "SELECT COUNT(*) as count FROM security_alerts WHERE status = 'open'";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getApiRequestsPerMinute() {
        $query = "SELECT COUNT(*) as count FROM api_logs WHERE timestamp > DATE_SUB(NOW(), INTERVAL 1 MINUTE)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getDatabaseConnections() {
        $query = "SHOW STATUS LIKE 'Threads_connected'";
        $result = $this->database->query($query);
        $row = $result->fetch_assoc();
        return (int)$row['Value'];
    }
    
    private function getCacheHitRate() {
        // This would depend on your cache implementation
        return 95.5; // Placeholder
    }
}

/**
 * Network Metrics Collector
 */
class NetworkMetricsCollector {
    public function collect() {
        return [
            'tcp_connections' => $this->getTcpConnections(),
            'udp_connections' => $this->getUdpConnections(),
            'listening_ports' => $this->getListeningPorts(),
            'network_errors' => $this->getNetworkErrors(),
            'bandwidth_usage' => $this->getBandwidthUsage()
        ];
    }
    
    private function getTcpConnections() {
        $output = shell_exec('netstat -nt | grep ESTABLISHED | wc -l');
        return (int)trim($output);
    }
    
    private function getUdpConnections() {
        $output = shell_exec('netstat -nu | wc -l');
        return (int)trim($output);
    }
    
    private function getListeningPorts() {
        $output = shell_exec('netstat -ln | grep LISTEN | wc -l');
        return (int)trim($output);
    }
    
    private function getNetworkErrors() {
        // Parse /proc/net/dev for error counters
        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        
        $totalErrors = 0;
        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:]+):\s*(.+)$/', $line, $matches)) {
                $interface = trim($matches[1]);
                if ($interface !== 'lo') {
                    $values = preg_split('/\s+/', trim($matches[2]));
                    $totalErrors += $values[2] + $values[10]; // RX errors + TX errors
                }
            }
        }
        
        return $totalErrors;
    }
    
    private function getBandwidthUsage() {
        // This would require tracking over time
        return ['rx_mbps' => 10.5, 'tx_mbps' => 8.2];
    }
}

/**
 * Security Metrics Collector
 */
class SecurityMetricsCollector {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    public function collect() {
        return [
            'failed_login_attempts' => $this->getFailedLoginAttempts(),
            'active_sessions' => $this->getActiveSessions(),
            'suspicious_ips' => $this->getSuspiciousIPs(),
            'malware_detections' => $this->getMalwareDetections(),
            'firewall_blocks' => $this->getFirewallBlocks(),
            'ssl_certificates_expiring' => $this->getExpiringSSLCertificates(),
            'security_scan_score' => $this->getSecurityScanScore()
        ];
    }
    
    private function getFailedLoginAttempts() {
        $query = "SELECT COUNT(*) as count FROM login_attempts 
                  WHERE success = 0 AND timestamp > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getActiveSessions() {
        $query = "SELECT COUNT(*) as count FROM user_sessions WHERE expires_at > NOW()";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getSuspiciousIPs() {
        $query = "SELECT COUNT(DISTINCT ip) as count FROM security_events 
                  WHERE event_type = 'suspicious_activity' AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getMalwareDetections() {
        $query = "SELECT COUNT(*) as count FROM security_scans 
                  WHERE scan_type = 'malware' AND threats_found > 0 AND timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getFirewallBlocks() {
        // This would read from firewall logs
        return 42; // Placeholder
    }
    
    private function getExpiringSSLCertificates() {
        $query = "SELECT COUNT(*) as count FROM ssl_certificates 
                  WHERE expires_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 30 DAY)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getSecurityScanScore() {
        $query = "SELECT AVG(security_score) as score FROM security_scans 
                  WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['score'] ?? 0;
    }
}

/**
 * Performance Metrics Collector
 */
class PerformanceMetricsCollector {
    public function collect() {
        return [
            'response_time_avg' => $this->getAverageResponseTime(),
            'response_time_p95' => $this->getPercentileResponseTime(95),
            'response_time_p99' => $this->getPercentileResponseTime(99),
            'error_rate' => $this->getErrorRate(),
            'throughput' => $this->getThroughput(),
            'cache_performance' => $this->getCachePerformance(),
            'database_performance' => $this->getDatabasePerformance()
        ];
    }
    
    private function getAverageResponseTime() {
        // This would come from application logs or APM
        return 125.5; // milliseconds
    }
    
    private function getPercentileResponseTime($percentile) {
        // Calculate percentile response times
        return 250.0; // milliseconds
    }
    
    private function getErrorRate() {
        // HTTP 5xx error rate
        return 0.1; // percent
    }
    
    private function getThroughput() {
        // Requests per second
        return 150;
    }
    
    private function getCachePerformance() {
        return [
            'hit_rate' => 95.5,
            'miss_rate' => 4.5,
            'avg_get_time' => 1.2
        ];
    }
    
    private function getDatabasePerformance() {
        return [
            'query_time_avg' => 15.5,
            'slow_queries' => 2,
            'connections_used' => 45
        ];
    }
}

/**
 * Business Metrics Collector
 */
class BusinessMetricsCollector {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    public function collect() {
        return [
            'new_signups_today' => $this->getNewSignupsToday(),
            'revenue_today' => $this->getRevenueToday(),
            'support_tickets_open' => $this->getOpenSupportTickets(),
            'avg_resolution_time' => $this->getAverageResolutionTime(),
            'customer_satisfaction' => $this->getCustomerSatisfaction(),
            'churn_rate' => $this->getChurnRate(),
            'feature_usage' => $this->getFeatureUsage()
        ];
    }
    
    private function getNewSignupsToday() {
        $query = "SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = CURDATE()";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getRevenueToday() {
        $query = "SELECT SUM(amount) as total FROM payments WHERE DATE(created_at) = CURDATE()";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['total'] ?? 0;
    }
    
    private function getOpenSupportTickets() {
        $query = "SELECT COUNT(*) as count FROM support_tickets WHERE status IN ('open', 'pending')";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['count'];
    }
    
    private function getAverageResolutionTime() {
        $query = "SELECT AVG(TIMESTAMPDIFF(HOUR, created_at, resolved_at)) as avg_hours 
                  FROM support_tickets 
                  WHERE status = 'resolved' AND resolved_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['avg_hours'] ?? 0;
    }
    
    private function getCustomerSatisfaction() {
        $query = "SELECT AVG(rating) as avg_rating FROM feedback WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $result = $this->database->query($query);
        return $result->fetch_assoc()['avg_rating'] ?? 0;
    }
    
    private function getChurnRate() {
        // Calculate monthly churn rate
        return 2.5; // percent
    }
    
    private function getFeatureUsage() {
        $query = "SELECT feature_name, COUNT(*) as usage_count 
                  FROM feature_usage 
                  WHERE timestamp > DATE_SUB(NOW(), INTERVAL 24 HOUR) 
                  GROUP BY feature_name 
                  ORDER BY usage_count DESC 
                  LIMIT 10";
        
        $result = $this->database->query($query);
        $usage = [];
        
        while ($row = $result->fetch_assoc()) {
            $usage[$row['feature_name']] = $row['usage_count'];
        }
        
        return $usage;
    }
}

/**
 * Alert Manager
 */
class AlertManager {
    private $database;
    private $cache;
    private $rules = [];
    private $notificationChannels = [];
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
        $this->loadAlertRules();
        $this->initializeNotificationChannels();
    }
    
    public function start() {
        // Start alert processing in background
        $pid = pcntl_fork();
        
        if ($pid === 0) {
            while (true) {
                $this->processAlerts();
                sleep(10);
            }
        }
    }
    
    public function checkMetrics($collector, $metrics) {
        foreach ($this->rules as $rule) {
            if ($rule['collector'] === $collector || $rule['collector'] === '*') {
                $this->evaluateRule($rule, $collector, $metrics);
            }
        }
    }
    
    private function evaluateRule($rule, $collector, $metrics) {
        $metricName = $rule['metric'];
        $condition = $rule['condition'];
        $threshold = $rule['threshold'];
        
        if (!isset($metrics[$metricName])) {
            return;
        }
        
        $value = $metrics[$metricName];
        $triggered = false;
        
        switch ($condition) {
            case '>':
                $triggered = $value > $threshold;
                break;
            case '<':
                $triggered = $value < $threshold;
                break;
            case '>=':
                $triggered = $value >= $threshold;
                break;
            case '<=':
                $triggered = $value <= $threshold;
                break;
            case '==':
                $triggered = $value == $threshold;
                break;
            case '!=':
                $triggered = $value != $threshold;
                break;
        }
        
        if ($triggered) {
            $this->triggerAlert($rule, $collector, $metricName, $value);
        } else {
            $this->resolveAlert($rule, $collector);
        }
    }
    
    private function triggerAlert($rule, $collector, $metric, $value) {
        $alertKey = "alert:{$rule['id']}:{$collector}";
        
        // Check if alert is already active
        if ($this->cache->exists($alertKey)) {
            return;
        }
        
        $alert = [
            'id' => uniqid('alert_'),
            'rule_id' => $rule['id'],
            'collector' => $collector,
            'metric' => $metric,
            'value' => $value,
            'threshold' => $rule['threshold'],
            'condition' => $rule['condition'],
            'severity' => $rule['severity'],
            'message' => $this->formatAlertMessage($rule, $collector, $metric, $value),
            'triggered_at' => time(),
            'status' => 'active'
        ];
        
        // Store alert
        $this->storeAlert($alert);
        
        // Cache alert to prevent duplicates
        $this->cache->set($alertKey, json_encode($alert), $rule['cooldown'] ?? 300);
        
        // Send notifications
        $this->sendNotifications($alert);
    }
    
    private function resolveAlert($rule, $collector) {
        $alertKey = "alert:{$rule['id']}:{$collector}";
        
        if ($this->cache->exists($alertKey)) {
            $alertData = json_decode($this->cache->get($alertKey), true);
            
            // Update alert status
            $this->updateAlertStatus($alertData['id'], 'resolved');
            
            // Remove from cache
            $this->cache->del($alertKey);
            
            // Send resolution notification
            $this->sendResolutionNotification($alertData);
        }
    }
    
    private function loadAlertRules() {
        $query = "SELECT * FROM alert_rules WHERE status = 'active'";
        $result = $this->database->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $this->rules[] = $row;
        }
        
        // Add default rules if none exist
        if (empty($this->rules)) {
            $this->createDefaultAlertRules();
        }
    }
    
    private function createDefaultAlertRules() {
        $defaultRules = [
            [
                'name' => 'High CPU Usage',
                'collector' => 'system',
                'metric' => 'cpu_usage',
                'condition' => '>',
                'threshold' => 80,
                'severity' => 'warning',
                'cooldown' => 300
            ],
            [
                'name' => 'High Memory Usage',
                'collector' => 'system',
                'metric' => 'memory_usage',
                'condition' => '>',
                'threshold' => 85,
                'severity' => 'warning',
                'cooldown' => 300
            ],
            [
                'name' => 'Disk Space Critical',
                'collector' => 'system',
                'metric' => 'disk_usage',
                'condition' => '>',
                'threshold' => 90,
                'severity' => 'critical',
                'cooldown' => 600
            ],
            [
                'name' => 'Failed Login Attempts',
                'collector' => 'security',
                'metric' => 'failed_login_attempts',
                'condition' => '>',
                'threshold' => 10,
                'severity' => 'warning',
                'cooldown' => 900
            ]
        ];
        
        foreach ($defaultRules as $rule) {
            $this->createAlertRule($rule);
        }
    }
    
    private function createAlertRule($rule) {
        $rule['id'] = uniqid('rule_');
        $rule['status'] = 'active';
        $rule['created_at'] = date('Y-m-d H:i:s');
        
        $query = "INSERT INTO alert_rules (id, name, collector, metric, condition, threshold, severity, cooldown, status, created_at) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sssssissss',
            $rule['id'],
            $rule['name'],
            $rule['collector'],
            $rule['metric'],
            $rule['condition'],
            $rule['threshold'],
            $rule['severity'],
            $rule['cooldown'],
            $rule['status'],
            $rule['created_at']
        );
        
        $stmt->execute();
        
        $this->rules[] = $rule;
    }
    
    private function initializeNotificationChannels() {
        $this->notificationChannels = [
            'email' => new EmailNotificationChannel($this->database),
            'webhook' => new WebhookNotificationChannel(),
            'dashboard' => new DashboardNotificationChannel($this->cache)
        ];
    }
    
    private function formatAlertMessage($rule, $collector, $metric, $value) {
        return sprintf(
            "[%s] %s: %s %s %s (current: %s)",
            strtoupper($rule['severity']),
            $rule['name'],
            $metric,
            $rule['condition'],
            $rule['threshold'],
            $value
        );
    }
    
    private function storeAlert($alert) {
        $query = "INSERT INTO alerts (id, rule_id, collector, metric, value, threshold, condition, severity, message, triggered_at, status) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ssssddsssss',
            $alert['id'],
            $alert['rule_id'],
            $alert['collector'],
            $alert['metric'],
            $alert['value'],
            $alert['threshold'],
            $alert['condition'],
            $alert['severity'],
            $alert['message'],
            date('Y-m-d H:i:s', $alert['triggered_at']),
            $alert['status']
        );
        
        $stmt->execute();
    }
    
    private function updateAlertStatus($alertId, $status) {
        $query = "UPDATE alerts SET status = ?, resolved_at = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ss', $status, $alertId);
        $stmt->execute();
    }
    
    private function sendNotifications($alert) {
        foreach ($this->notificationChannels as $channel) {
            try {
                $channel->send($alert);
            } catch (Exception $e) {
                error_log("Notification failed: " . $e->getMessage());
            }
        }
    }
    
    private function sendResolutionNotification($alert) {
        $alert['status'] = 'resolved';
        $alert['message'] = "[RESOLVED] " . $alert['message'];
        
        foreach ($this->notificationChannels as $channel) {
            try {
                $channel->send($alert);
            } catch (Exception $e) {
                error_log("Resolution notification failed: " . $e->getMessage());
            }
        }
    }
    
    private function processAlerts() {
        // Process any pending alert actions
        $query = "SELECT * FROM alert_actions WHERE status = 'pending'";
        $result = $this->database->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $this->executeAlertAction($row);
        }
    }
    
    private function executeAlertAction($action) {
        // Execute automated responses to alerts
        switch ($action['action_type']) {
            case 'restart_service':
                $this->restartService($action['target']);
                break;
            case 'scale_resources':
                $this->scaleResources($action['target'], $action['parameters']);
                break;
            case 'block_ip':
                $this->blockIP($action['target']);
                break;
        }
        
        // Mark action as completed
        $query = "UPDATE alert_actions SET status = 'completed', executed_at = NOW() WHERE id = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('s', $action['id']);
        $stmt->execute();
    }
    
    private function restartService($service) {
        exec("systemctl restart {$service}");
    }
    
    private function scaleResources($resource, $parameters) {
        // Implement resource scaling logic
    }
    
    private function blockIP($ip) {
        exec("iptables -A INPUT -s {$ip} -j DROP");
    }
}

/**
 * Notification Channels
 */
class EmailNotificationChannel {
    private $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    public function send($alert) {
        $recipients = $this->getAlertRecipients($alert['severity']);
        
        foreach ($recipients as $email) {
            mail($email, "Alert: " . $alert['message'], $alert['message']);
        }
    }
    
    private function getAlertRecipients($severity) {
        $query = "SELECT email FROM alert_recipients WHERE severity_level >= ? AND status = 'active'";
        $stmt = $this->database->prepare($query);
        
        $severityLevel = $this->getSeverityLevel($severity);
        $stmt->bind_param('i', $severityLevel);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $recipients = [];
        
        while ($row = $result->fetch_assoc()) {
            $recipients[] = $row['email'];
        }
        
        return $recipients;
    }
    
    private function getSeverityLevel($severity) {
        $levels = ['info' => 1, 'warning' => 2, 'critical' => 3];
        return $levels[$severity] ?? 1;
    }
}

class WebhookNotificationChannel {
    public function send($alert) {
        $webhooks = $this->getWebhooks();
        
        foreach ($webhooks as $webhook) {
            $this->sendWebhook($webhook, $alert);
        }
    }
    
    private function getWebhooks() {
        // Get configured webhooks
        return [];
    }
    
    private function sendWebhook($webhook, $alert) {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook['url']);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($alert));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        curl_exec($ch);
        curl_close($ch);
    }
}

class DashboardNotificationChannel {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function send($alert) {
        // Store alert for dashboard display
        $this->cache->lpush('dashboard_alerts', json_encode($alert));
        $this->cache->ltrim('dashboard_alerts', 0, 99); // Keep last 100 alerts
    }
}