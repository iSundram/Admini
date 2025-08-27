<?php
/**
 * System Monitoring and Performance Manager
 */

class MonitoringManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Collect system metrics
     */
    public function collectSystemMetrics() {
        $metrics = [
            'cpu' => $this->getCPUUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'network' => $this->getNetworkUsage(),
            'load' => $this->getLoadAverage()
        ];
        
        // Store metrics in database
        foreach ($metrics as $type => $value) {
            $this->storeMetric($type, $value);
        }
        
        // Check thresholds and trigger alerts
        $this->checkThresholds($metrics);
        
        return $metrics;
    }
    
    /**
     * Get CPU usage percentage
     */
    private function getCPUUsage() {
        if (PHP_OS_FAMILY === 'Linux') {
            $load = sys_getloadavg();
            $cores = $this->getCPUCores();
            return min(100, ($load[0] / $cores) * 100);
        }
        
        // Fallback for other systems
        return 0;
    }
    
    /**
     * Get number of CPU cores
     */
    private function getCPUCores() {
        if (PHP_OS_FAMILY === 'Linux') {
            $cpuinfo = file_get_contents('/proc/cpuinfo');
            preg_match_all('/^processor/m', $cpuinfo, $matches);
            return count($matches[0]);
        }
        
        return 1;
    }
    
    /**
     * Get memory usage percentage
     */
    private function getMemoryUsage() {
        if (PHP_OS_FAMILY === 'Linux') {
            $meminfo = file_get_contents('/proc/meminfo');
            
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $totalMatches);
            preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $availableMatches);
            
            if ($totalMatches && $availableMatches) {
                $total = intval($totalMatches[1]);
                $available = intval($availableMatches[1]);
                $used = $total - $available;
                
                return ($used / $total) * 100;
            }
        }
        
        return 0;
    }
    
    /**
     * Get disk usage for main partition
     */
    private function getDiskUsage() {
        $total = disk_total_space('/');
        $free = disk_free_space('/');
        
        if ($total && $free) {
            $used = $total - $free;
            return ($used / $total) * 100;
        }
        
        return 0;
    }
    
    /**
     * Get network usage (simplified)
     */
    private function getNetworkUsage() {
        // This is a simplified implementation
        // In production, you'd track network interface statistics
        return 0;
    }
    
    /**
     * Get system load average
     */
    private function getLoadAverage() {
        if (function_exists('sys_getloadavg')) {
            $load = sys_getloadavg();
            return $load[0]; // 1-minute load average
        }
        
        return 0;
    }
    
    /**
     * Store metric in database
     */
    private function storeMetric($type, $value) {
        $sql = "INSERT INTO system_monitoring (metric_type, metric_value, status) VALUES (?, ?, ?)";
        
        // Determine status based on thresholds
        $status = 'normal';
        if ($value > 95) {
            $status = 'critical';
        } elseif ($value > 80) {
            $status = 'warning';
        }
        
        $this->db->query($sql, [$type, $value, $status]);
    }
    
    /**
     * Check thresholds and send alerts
     */
    private function checkThresholds($metrics) {
        foreach ($metrics as $type => $value) {
            if ($value > 95) {
                $this->sendAlert('critical', $type, $value);
            } elseif ($value > 80) {
                $this->sendAlert('warning', $type, $value);
            }
        }
    }
    
    /**
     * Send alert notification
     */
    private function sendAlert($level, $type, $value) {
        $message = "System {$level}: {$type} usage is at {$value}%";
        
        // Log the alert
        error_log("[ALERT] {$message}");
        
        // In production, send email/SMS/webhook notifications
        $this->notifyAdministrators($level, $message);
    }
    
    /**
     * Notify administrators
     */
    private function notifyAdministrators($level, $message) {
        $admins = $this->db->fetchAll("SELECT email FROM users WHERE role = 'admin'");
        
        foreach ($admins as $admin) {
            // Send email notification (implement email sending)
            $this->sendEmailAlert($admin['email'], $level, $message);
        }
    }
    
    /**
     * Send email alert
     */
    private function sendEmailAlert($email, $level, $message) {
        // Implement email sending logic
        // For now, just log it
        error_log("Email alert to {$email}: {$message}");
    }
    
    /**
     * Get historical metrics
     */
    public function getHistoricalMetrics($type = null, $hours = 24) {
        $whereClause = $type ? "WHERE metric_type = ?" : "";
        $params = $type ? [$type] : [];
        
        $sql = "SELECT * FROM system_monitoring {$whereClause} 
                AND recorded_at > DATE_SUB(NOW(), INTERVAL ? HOUR) 
                ORDER BY recorded_at ASC";
        
        $params[] = $hours;
        
        return $this->db->fetchAll($sql, $params);
    }
    
    /**
     * Get current system status
     */
    public function getSystemStatus() {
        // Get latest metrics for each type
        $status = [];
        $types = ['cpu', 'memory', 'disk', 'network', 'load'];
        
        foreach ($types as $type) {
            $latest = $this->db->fetch(
                "SELECT * FROM system_monitoring WHERE metric_type = ? ORDER BY recorded_at DESC LIMIT 1",
                [$type]
            );
            
            $status[$type] = $latest ? [
                'value' => $latest['metric_value'],
                'status' => $latest['status'],
                'recorded_at' => $latest['recorded_at']
            ] : null;
        }
        
        // Get service statuses
        $services = $this->getServiceStatuses();
        
        // Overall system health
        $overallStatus = $this->calculateOverallStatus($status, $services);
        
        return [
            'metrics' => $status,
            'services' => $services,
            'overall_status' => $overallStatus,
            'last_updated' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Get service statuses
     */
    public function getServiceStatuses() {
        $services = $this->db->fetchAll("SELECT * FROM system_services");
        $statuses = [];
        
        foreach ($services as $service) {
            $statuses[$service['service_name']] = [
                'display_name' => $service['display_name'],
                'status' => $this->checkServiceStatus($service['service_name']),
                'auto_start' => $service['auto_start'],
                'last_checked' => $service['last_checked']
            ];
        }
        
        return $statuses;
    }
    
    /**
     * Check individual service status
     */
    private function checkServiceStatus($serviceName) {
        if (PHP_OS_FAMILY === 'Linux') {
            // Use systemctl to check service status
            $output = shell_exec("systemctl is-active {$serviceName} 2>/dev/null");
            $status = trim($output);
            
            if ($status === 'active') {
                return 'running';
            } elseif ($status === 'inactive') {
                return 'stopped';
            } else {
                return 'failed';
            }
        }
        
        return 'unknown';
    }
    
    /**
     * Calculate overall system status
     */
    private function calculateOverallStatus($metrics, $services) {
        $criticalCount = 0;
        $warningCount = 0;
        
        // Check metrics
        foreach ($metrics as $metric) {
            if ($metric && $metric['status'] === 'critical') {
                $criticalCount++;
            } elseif ($metric && $metric['status'] === 'warning') {
                $warningCount++;
            }
        }
        
        // Check services
        foreach ($services as $service) {
            if ($service['status'] === 'failed') {
                $criticalCount++;
            } elseif ($service['status'] === 'stopped' && $service['auto_start']) {
                $warningCount++;
            }
        }
        
        if ($criticalCount > 0) {
            return 'critical';
        } elseif ($warningCount > 0) {
            return 'warning';
        } else {
            return 'healthy';
        }
    }
    
    /**
     * Start/stop/restart service
     */
    public function manageService($serviceName, $action) {
        if (!in_array($action, ['start', 'stop', 'restart', 'reload'])) {
            throw new Exception("Invalid action: {$action}");
        }
        
        if (PHP_OS_FAMILY === 'Linux') {
            $command = "systemctl {$action} {$serviceName} 2>&1";
            $output = shell_exec($command);
            $exitCode = 0; // In real implementation, capture exit code
            
            // Update service status in database
            $status = $this->checkServiceStatus($serviceName);
            $this->db->query(
                "UPDATE system_services SET status = ?, last_checked = NOW() WHERE service_name = ?",
                [$status, $serviceName]
            );
            
            return [
                'success' => $exitCode === 0,
                'output' => trim($output),
                'new_status' => $status
            ];
        }
        
        throw new Exception("Service management not supported on this platform");
    }
    
    /**
     * Get process information
     */
    public function getProcessInfo() {
        if (PHP_OS_FAMILY !== 'Linux') {
            return [];
        }
        
        $processes = [];
        
        // Get process information using ps command
        $output = shell_exec("ps aux --sort=-%cpu | head -20");
        $lines = explode("\n", trim($output));
        
        // Skip header line
        array_shift($lines);
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $fields = preg_split('/\s+/', trim($line), 11);
            
            if (count($fields) >= 11) {
                $processes[] = [
                    'user' => $fields[0],
                    'pid' => $fields[1],
                    'cpu' => floatval($fields[2]),
                    'memory' => floatval($fields[3]),
                    'vsz' => intval($fields[4]),
                    'rss' => intval($fields[5]),
                    'tty' => $fields[6],
                    'stat' => $fields[7],
                    'start' => $fields[8],
                    'time' => $fields[9],
                    'command' => $fields[10]
                ];
            }
        }
        
        return $processes;
    }
    
    /**
     * Get disk usage by directory
     */
    public function getDiskUsageByDirectory($path = '/') {
        if (!is_dir($path)) {
            throw new Exception("Invalid directory: {$path}");
        }
        
        $usage = [];
        $command = "du -sh {$path}/* 2>/dev/null | sort -hr | head -20";
        $output = shell_exec($command);
        
        if ($output) {
            $lines = explode("\n", trim($output));
            
            foreach ($lines as $line) {
                if (empty(trim($line))) continue;
                
                $parts = preg_split('/\s+/', trim($line), 2);
                if (count($parts) === 2) {
                    $usage[] = [
                        'size' => $parts[0],
                        'path' => $parts[1]
                    ];
                }
            }
        }
        
        return $usage;
    }
    
    /**
     * Get network connections
     */
    public function getNetworkConnections() {
        if (PHP_OS_FAMILY !== 'Linux') {
            return [];
        }
        
        $connections = [];
        $output = shell_exec("netstat -tuln 2>/dev/null");
        
        if ($output) {
            $lines = explode("\n", trim($output));
            
            foreach ($lines as $line) {
                if (strpos($line, 'LISTEN') !== false) {
                    $fields = preg_split('/\s+/', trim($line));
                    
                    if (count($fields) >= 4) {
                        $connections[] = [
                            'protocol' => $fields[0],
                            'local_address' => $fields[3],
                            'state' => $fields[5] ?? 'LISTEN'
                        ];
                    }
                }
            }
        }
        
        return $connections;
    }
    
    /**
     * Get security events
     */
    public function getSecurityEvents($limit = 100) {
        return $this->db->fetchAll(
            "SELECT * FROM activity_logs WHERE action LIKE '%security%' OR action LIKE '%login%' OR action LIKE '%fail%' 
             ORDER BY created_at DESC LIMIT ?",
            [$limit]
        );
    }
    
    /**
     * Generate system report
     */
    public function generateSystemReport() {
        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'system_info' => $this->getSystemInfo(),
            'current_status' => $this->getSystemStatus(),
            'metrics_24h' => $this->getHistoricalMetrics(null, 24),
            'top_processes' => $this->getProcessInfo(),
            'disk_usage' => $this->getDiskUsageByDirectory('/'),
            'network_connections' => $this->getNetworkConnections(),
            'recent_security_events' => $this->getSecurityEvents(50)
        ];
        
        return $report;
    }
    
    /**
     * Get basic system information
     */
    private function getSystemInfo() {
        return [
            'hostname' => gethostname(),
            'os' => PHP_OS,
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'uptime' => $this->getSystemUptime(),
            'cpu_cores' => $this->getCPUCores(),
            'total_memory' => $this->getTotalMemory()
        ];
    }
    
    /**
     * Get system uptime
     */
    private function getSystemUptime() {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/uptime')) {
            $uptime = file_get_contents('/proc/uptime');
            $seconds = floatval(explode(' ', $uptime)[0]);
            
            $days = floor($seconds / 86400);
            $hours = floor(($seconds % 86400) / 3600);
            $minutes = floor(($seconds % 3600) / 60);
            
            return "{$days} days, {$hours} hours, {$minutes} minutes";
        }
        
        return 'Unknown';
    }
    
    /**
     * Get total memory in MB
     */
    private function getTotalMemory() {
        if (PHP_OS_FAMILY === 'Linux' && file_exists('/proc/meminfo')) {
            $meminfo = file_get_contents('/proc/meminfo');
            preg_match('/MemTotal:\s+(\d+)/', $meminfo, $matches);
            
            if ($matches) {
                return round(intval($matches[1]) / 1024, 2) . ' MB';
            }
        }
        
        return 'Unknown';
    }
    
    /**
     * Clean old monitoring data
     */
    public function cleanOldData($retentionDays = 30) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        $this->db->query(
            "DELETE FROM system_monitoring WHERE recorded_at < ?",
            [$cutoffDate]
        );
        
        return $this->db->query("SELECT ROW_COUNT()")->fetchColumn();
    }
    
    /**
     * Set monitoring thresholds
     */
    public function setThresholds($type, $warning, $critical) {
        $this->db->query(
            "UPDATE system_monitoring SET threshold_warning = ?, threshold_critical = ? WHERE metric_type = ?",
            [$warning, $critical, $type]
        );
    }
    
    /**
     * Get monitoring configuration
     */
    public function getMonitoringConfig() {
        return [
            'collection_interval' => 60, // seconds
            'retention_days' => 30,
            'alert_email' => true,
            'alert_webhook' => false,
            'thresholds' => [
                'cpu' => ['warning' => 80, 'critical' => 95],
                'memory' => ['warning' => 80, 'critical' => 95],
                'disk' => ['warning' => 80, 'critical' => 95],
                'load' => ['warning' => 80, 'critical' => 95]
            ]
        ];
    }
}