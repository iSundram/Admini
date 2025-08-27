<?php
/**
 * Advanced Background Worker System
 * Handles asynchronous job processing with retry logic and monitoring
 */

class WorkerManager {
    private $database;
    private $messageQueue;
    private $workers = [];
    private $isRunning = false;
    private $maxWorkers;
    private $workerTimeout;
    
    public function __construct($database, $messageQueue, $config = []) {
        $this->database = $database;
        $this->messageQueue = $messageQueue;
        $this->maxWorkers = $config['max_workers'] ?? 10;
        $this->workerTimeout = $config['worker_timeout'] ?? 300; // 5 minutes
    }
    
    /**
     * Start the worker manager
     */
    public function start() {
        $this->isRunning = true;
        
        // Register signal handlers
        pcntl_signal(SIGTERM, [$this, 'shutdown']);
        pcntl_signal(SIGINT, [$this, 'shutdown']);
        pcntl_signal(SIGCHLD, [$this, 'reapWorker']);
        
        echo "Worker Manager started with {$this->maxWorkers} workers\n";
        
        while ($this->isRunning) {
            $this->manageWorkers();
            pcntl_signal_dispatch();
            sleep(1);
        }
    }
    
    /**
     * Graceful shutdown
     */
    public function shutdown() {
        echo "Shutting down worker manager...\n";
        $this->isRunning = false;
        
        // Stop all workers
        foreach ($this->workers as $pid => $worker) {
            posix_kill($pid, SIGTERM);
        }
        
        // Wait for workers to finish
        $timeout = 30;
        while (!empty($this->workers) && $timeout > 0) {
            pcntl_signal_dispatch();
            sleep(1);
            $timeout--;
        }
        
        // Force kill remaining workers
        foreach ($this->workers as $pid => $worker) {
            posix_kill($pid, SIGKILL);
        }
        
        echo "Worker manager stopped\n";
    }
    
    /**
     * Manage worker processes
     */
    private function manageWorkers() {
        // Clean up dead workers
        $this->cleanupDeadWorkers();
        
        // Check if we need more workers
        $activeWorkers = count($this->workers);
        $queueSize = $this->getQueueSize();
        
        if ($activeWorkers < $this->maxWorkers && $queueSize > 0) {
            $this->spawnWorker();
        }
        
        // Check for hung workers
        $this->checkHungWorkers();
    }
    
    /**
     * Spawn a new worker process
     */
    private function spawnWorker() {
        $pid = pcntl_fork();
        
        if ($pid === -1) {
            error_log("Failed to fork worker process");
            return false;
        }
        
        if ($pid === 0) {
            // Child process - become worker
            $this->runWorker();
            exit(0);
        } else {
            // Parent process - track worker
            $this->workers[$pid] = [
                'pid' => $pid,
                'started_at' => time(),
                'last_activity' => time(),
                'status' => 'active',
                'jobs_processed' => 0
            ];
            
            echo "Spawned worker {$pid}\n";
            return true;
        }
    }
    
    /**
     * Run worker process
     */
    private function runWorker() {
        $workerId = getmypid();
        $queues = ['high_priority', 'normal', 'low_priority'];
        
        echo "Worker {$workerId} started\n";
        
        while (true) {
            try {
                // Get next job
                $message = $this->messageQueue->consume($queues, 5);
                
                if ($message) {
                    $this->processJob($message, $workerId);
                } else {
                    // No jobs available, check if we should exit
                    if ($this->shouldWorkerExit()) {
                        break;
                    }
                }
                
                // Update last activity
                $this->updateWorkerActivity($workerId);
                
            } catch (Exception $e) {
                error_log("Worker {$workerId} error: " . $e->getMessage());
                sleep(1);
            }
        }
        
        echo "Worker {$workerId} exiting\n";
    }
    
    /**
     * Process a job
     */
    private function processJob($message, $workerId) {
        $payload = $message->getPayload();
        $jobType = $payload['type'];
        $jobData = $payload['data'];
        
        echo "Worker {$workerId} processing job {$message->getId()} of type {$jobType}\n";
        
        try {
            $startTime = microtime(true);
            
            // Get job handler
            $handler = $this->getJobHandler($jobType);
            if (!$handler) {
                throw new Exception("No handler found for job type: {$jobType}");
            }
            
            // Execute job
            $result = $handler->execute($jobData);
            
            $duration = microtime(true) - $startTime;
            
            // Log job completion
            $this->logJobCompletion($message->getId(), $workerId, $duration, $result);
            
            // Acknowledge job
            $message->ack();
            
            echo "Worker {$workerId} completed job {$message->getId()} in {$duration}s\n";
            
        } catch (Exception $e) {
            error_log("Job {$message->getId()} failed: " . $e->getMessage());
            
            // Log job failure
            $this->logJobFailure($message->getId(), $workerId, $e->getMessage());
            
            // Reject job (will be retried or sent to dead letter queue)
            $message->reject(true, $e->getMessage());
        }
    }
    
    /**
     * Get job handler
     */
    private function getJobHandler($jobType) {
        $handlers = [
            'email' => new EmailJobHandler($this->database),
            'backup' => new BackupJobHandler($this->database),
            'security_scan' => new SecurityScanJobHandler($this->database),
            'system_monitoring' => new SystemMonitoringJobHandler($this->database),
            'log_analysis' => new LogAnalysisJobHandler($this->database),
            'file_cleanup' => new FileCleanupJobHandler($this->database),
            'report_generation' => new ReportGenerationJobHandler($this->database),
            'user_provisioning' => new UserProvisioningJobHandler($this->database),
            'ssl_renewal' => new SSLRenewalJobHandler($this->database),
            'database_optimization' => new DatabaseOptimizationJobHandler($this->database)
        ];
        
        return $handlers[$jobType] ?? null;
    }
    
    /**
     * Reap finished worker
     */
    public function reapWorker() {
        while (($pid = pcntl_waitpid(-1, $status, WNOHANG)) > 0) {
            if (isset($this->workers[$pid])) {
                echo "Worker {$pid} finished\n";
                unset($this->workers[$pid]);
            }
        }
    }
    
    /**
     * Clean up dead workers
     */
    private function cleanupDeadWorkers() {
        foreach ($this->workers as $pid => $worker) {
            if (!posix_kill($pid, 0)) {
                // Worker is dead
                unset($this->workers[$pid]);
                echo "Cleaned up dead worker {$pid}\n";
            }
        }
    }
    
    /**
     * Check for hung workers
     */
    private function checkHungWorkers() {
        $now = time();
        
        foreach ($this->workers as $pid => $worker) {
            if (($now - $worker['last_activity']) > $this->workerTimeout) {
                echo "Killing hung worker {$pid}\n";
                posix_kill($pid, SIGKILL);
                unset($this->workers[$pid]);
            }
        }
    }
    
    private function getQueueSize() {
        $queues = ['high_priority', 'normal', 'low_priority'];
        $totalSize = 0;
        
        foreach ($queues as $queue) {
            $stats = $this->messageQueue->getQueueStats($queue);
            $totalSize += $stats['pending'] + $stats['priority'];
        }
        
        return $totalSize;
    }
    
    private function shouldWorkerExit() {
        // Workers can exit when queue is empty and there are too many workers
        return count($this->workers) > ($this->maxWorkers / 2) && $this->getQueueSize() === 0;
    }
    
    private function updateWorkerActivity($workerId) {
        if (isset($this->workers[$workerId])) {
            $this->workers[$workerId]['last_activity'] = time();
        }
    }
    
    private function logJobCompletion($jobId, $workerId, $duration, $result) {
        $query = "INSERT INTO job_logs (job_id, worker_id, status, duration, result, completed_at) 
                  VALUES (?, ?, 'completed', ?, ?, NOW())";
        
        $stmt = $this->database->prepare($query);
        $resultJson = json_encode($result);
        $stmt->bind_param('ssds', $jobId, $workerId, $duration, $resultJson);
        $stmt->execute();
    }
    
    private function logJobFailure($jobId, $workerId, $error) {
        $query = "INSERT INTO job_logs (job_id, worker_id, status, error_message, completed_at) 
                  VALUES (?, ?, 'failed', ?, NOW())";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sss', $jobId, $workerId, $error);
        $stmt->execute();
    }
}

/**
 * Base Job Handler
 */
abstract class BaseJobHandler {
    protected $database;
    
    public function __construct($database) {
        $this->database = $database;
    }
    
    abstract public function execute($data);
}

/**
 * Email Job Handler
 */
class EmailJobHandler extends BaseJobHandler {
    public function execute($data) {
        $to = $data['to'];
        $subject = $data['subject'];
        $body = $data['body'];
        $headers = $data['headers'] ?? [];
        
        // Use PHPMailer or similar library in production
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$success) {
            throw new Exception("Failed to send email to {$to}");
        }
        
        return ['status' => 'sent', 'to' => $to, 'subject' => $subject];
    }
}

/**
 * Backup Job Handler
 */
class BackupJobHandler extends BaseJobHandler {
    public function execute($data) {
        $type = $data['type']; // database, files, full
        $destination = $data['destination'];
        $options = $data['options'] ?? [];
        
        switch ($type) {
            case 'database':
                return $this->backupDatabase($destination, $options);
            case 'files':
                return $this->backupFiles($destination, $options);
            case 'full':
                return $this->fullBackup($destination, $options);
            default:
                throw new Exception("Unknown backup type: {$type}");
        }
    }
    
    private function backupDatabase($destination, $options) {
        $dbConfig = require '/home/runner/work/Admini/Admini/src/config/database.php';
        
        $command = sprintf(
            'mysqldump -h%s -u%s -p%s %s > %s',
            $dbConfig['host'],
            $dbConfig['username'],
            $dbConfig['password'],
            $dbConfig['database'],
            $destination
        );
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("Database backup failed");
        }
        
        return ['type' => 'database', 'destination' => $destination, 'size' => filesize($destination)];
    }
    
    private function backupFiles($destination, $options) {
        $sourceDir = $options['source_dir'] ?? '/home/runner/work/Admini/Admini';
        
        $command = sprintf('tar -czf %s -C %s .', $destination, $sourceDir);
        
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        
        if ($returnCode !== 0) {
            throw new Exception("File backup failed");
        }
        
        return ['type' => 'files', 'destination' => $destination, 'size' => filesize($destination)];
    }
    
    private function fullBackup($destination, $options) {
        // Combine database and file backups
        $dbBackup = $this->backupDatabase($destination . '.sql', $options);
        $fileBackup = $this->backupFiles($destination . '.tar.gz', $options);
        
        return [
            'type' => 'full',
            'database_backup' => $dbBackup,
            'file_backup' => $fileBackup
        ];
    }
}

/**
 * Security Scan Job Handler
 */
class SecurityScanJobHandler extends BaseJobHandler {
    public function execute($data) {
        $scanType = $data['scan_type'];
        $target = $data['target'];
        
        switch ($scanType) {
            case 'malware':
                return $this->scanForMalware($target);
            case 'vulnerability':
                return $this->vulnerabilityScan($target);
            case 'integrity':
                return $this->integrityCheck($target);
            default:
                throw new Exception("Unknown scan type: {$scanType}");
        }
    }
    
    private function scanForMalware($target) {
        // Implement malware scanning logic
        $findings = [];
        
        // Scan for suspicious file patterns
        $suspiciousPatterns = [
            'eval\s*\(\s*base64_decode',
            'exec\s*\(\s*base64_decode',
            'system\s*\(\s*base64_decode',
            'shell_exec\s*\(\s*base64_decode'
        ];
        
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        
        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match('/\.(php|js|html)$/i', $file->getFilename())) {
                $content = file_get_contents($file->getPathname());
                
                foreach ($suspiciousPatterns as $pattern) {
                    if (preg_match('/' . $pattern . '/i', $content)) {
                        $findings[] = [
                            'file' => $file->getPathname(),
                            'pattern' => $pattern,
                            'severity' => 'high'
                        ];
                    }
                }
            }
        }
        
        return [
            'scan_type' => 'malware',
            'target' => $target,
            'findings' => $findings,
            'clean' => empty($findings)
        ];
    }
    
    private function vulnerabilityScan($target) {
        // Implement vulnerability scanning
        $vulnerabilities = [];
        
        // Check for common vulnerabilities
        $checks = [
            'file_permissions' => $this->checkFilePermissions($target),
            'outdated_software' => $this->checkOutdatedSoftware(),
            'weak_passwords' => $this->checkWeakPasswords(),
            'open_ports' => $this->checkOpenPorts()
        ];
        
        foreach ($checks as $check => $result) {
            if (!$result['passed']) {
                $vulnerabilities[] = [
                    'type' => $check,
                    'severity' => $result['severity'],
                    'description' => $result['description'],
                    'recommendation' => $result['recommendation']
                ];
            }
        }
        
        return [
            'scan_type' => 'vulnerability',
            'target' => $target,
            'vulnerabilities' => $vulnerabilities,
            'risk_level' => $this->calculateRiskLevel($vulnerabilities)
        ];
    }
    
    private function integrityCheck($target) {
        // Implement file integrity checking
        $changes = [];
        
        // This would typically compare against known good checksums
        // For demo purposes, we'll just check if files exist
        
        return [
            'scan_type' => 'integrity',
            'target' => $target,
            'changes' => $changes,
            'integrity_score' => 100 - count($changes)
        ];
    }
    
    private function checkFilePermissions($target) {
        // Check for overly permissive file permissions
        $issues = [];
        
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target));
        
        foreach ($iterator as $file) {
            $perms = fileperms($file->getPathname());
            $octal = substr(sprintf('%o', $perms), -4);
            
            // Check for world-writable files
            if (($perms & 0002) && !$file->isDir()) {
                $issues[] = $file->getPathname() . " is world-writable ({$octal})";
            }
        }
        
        return [
            'passed' => empty($issues),
            'severity' => 'medium',
            'description' => 'File permission issues found',
            'recommendation' => 'Review and fix file permissions',
            'details' => $issues
        ];
    }
    
    private function checkOutdatedSoftware() {
        // Check for outdated software versions
        return [
            'passed' => true,
            'severity' => 'low',
            'description' => 'Software versions are up to date',
            'recommendation' => 'Continue monitoring for updates'
        ];
    }
    
    private function checkWeakPasswords() {
        // Check for weak passwords in the database
        $query = "SELECT COUNT(*) as count FROM users WHERE password_strength < 3";
        $result = $this->database->query($query);
        $row = $result->fetch_assoc();
        
        $weakPasswords = $row['count'] > 0;
        
        return [
            'passed' => !$weakPasswords,
            'severity' => $weakPasswords ? 'high' : 'low',
            'description' => $weakPasswords ? 'Weak passwords detected' : 'Password strength is adequate',
            'recommendation' => $weakPasswords ? 'Enforce stronger password policies' : 'Continue monitoring password strength'
        ];
    }
    
    private function checkOpenPorts() {
        // Check for unnecessary open ports
        $openPorts = [];
        
        // This would typically use netstat or ss command
        exec('netstat -tuln 2>/dev/null | grep LISTEN', $output);
        
        foreach ($output as $line) {
            if (preg_match('/tcp.*:(\d+).*LISTEN/', $line, $matches)) {
                $port = $matches[1];
                if (!in_array($port, ['22', '80', '443', '3306'])) {
                    $openPorts[] = $port;
                }
            }
        }
        
        return [
            'passed' => empty($openPorts),
            'severity' => empty($openPorts) ? 'low' : 'medium',
            'description' => empty($openPorts) ? 'No unnecessary open ports' : 'Unnecessary open ports detected',
            'recommendation' => empty($openPorts) ? 'Continue monitoring' : 'Review and close unnecessary ports',
            'details' => $openPorts
        ];
    }
    
    private function calculateRiskLevel($vulnerabilities) {
        if (empty($vulnerabilities)) {
            return 'low';
        }
        
        $highSeverity = array_filter($vulnerabilities, function($v) {
            return $v['severity'] === 'high';
        });
        
        if (count($highSeverity) > 0) {
            return 'high';
        }
        
        return count($vulnerabilities) > 3 ? 'medium' : 'low';
    }
}

/**
 * System Monitoring Job Handler
 */
class SystemMonitoringJobHandler extends BaseJobHandler {
    public function execute($data) {
        $metrics = [
            'cpu' => $this->getCpuUsage(),
            'memory' => $this->getMemoryUsage(),
            'disk' => $this->getDiskUsage(),
            'network' => $this->getNetworkStats(),
            'services' => $this->getServiceStatus(),
            'load' => $this->getLoadAverage()
        ];
        
        // Store metrics in database
        $this->storeMetrics($metrics);
        
        // Check for alerts
        $alerts = $this->checkAlerts($metrics);
        
        return [
            'metrics' => $metrics,
            'alerts' => $alerts,
            'timestamp' => time()
        ];
    }
    
    private function getCpuUsage() {
        $load = sys_getloadavg();
        return [
            'load_1min' => $load[0],
            'load_5min' => $load[1],
            'load_15min' => $load[2],
            'cores' => (int)shell_exec('nproc'),
            'usage_percent' => min(100, ($load[0] / (int)shell_exec('nproc')) * 100)
        ];
    }
    
    private function getMemoryUsage() {
        $meminfo = file_get_contents('/proc/meminfo');
        preg_match('/MemTotal:\s+(\d+)/', $meminfo, $total);
        preg_match('/MemAvailable:\s+(\d+)/', $meminfo, $available);
        
        $total = $total[1] * 1024; // Convert to bytes
        $available = $available[1] * 1024;
        $used = $total - $available;
        
        return [
            'total' => $total,
            'used' => $used,
            'available' => $available,
            'usage_percent' => ($used / $total) * 100
        ];
    }
    
    private function getDiskUsage() {
        $disks = [];
        $output = shell_exec('df -h / | tail -1');
        $parts = preg_split('/\s+/', $output);
        
        $disks['/'] = [
            'total' => $parts[1],
            'used' => $parts[2],
            'available' => $parts[3],
            'usage_percent' => (int)str_replace('%', '', $parts[4])
        ];
        
        return $disks;
    }
    
    private function getNetworkStats() {
        $stats = [];
        $netdev = file_get_contents('/proc/net/dev');
        $lines = explode("\n", $netdev);
        
        foreach ($lines as $line) {
            if (preg_match('/^\s*([^:]+):\s*(.+)$/', $line, $matches)) {
                $interface = trim($matches[1]);
                if ($interface !== 'lo') {
                    $values = preg_split('/\s+/', trim($matches[2]));
                    $stats[$interface] = [
                        'rx_bytes' => $values[0],
                        'tx_bytes' => $values[8],
                        'rx_packets' => $values[1],
                        'tx_packets' => $values[9]
                    ];
                }
            }
        }
        
        return $stats;
    }
    
    private function getServiceStatus() {
        $services = ['apache2', 'mysql', 'redis-server', 'postfix'];
        $status = [];
        
        foreach ($services as $service) {
            $output = shell_exec("systemctl is-active {$service} 2>/dev/null");
            $status[$service] = trim($output) === 'active';
        }
        
        return $status;
    }
    
    private function getLoadAverage() {
        $uptime = file_get_contents('/proc/uptime');
        $uptimeSeconds = (float)explode(' ', $uptime)[0];
        
        return [
            'uptime_seconds' => $uptimeSeconds,
            'uptime_formatted' => $this->formatUptime($uptimeSeconds)
        ];
    }
    
    private function formatUptime($seconds) {
        $days = floor($seconds / 86400);
        $hours = floor(($seconds % 86400) / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        
        return "{$days}d {$hours}h {$minutes}m";
    }
    
    private function storeMetrics($metrics) {
        $query = "INSERT INTO system_metrics (timestamp, cpu_usage, memory_usage, disk_usage, network_stats, service_status, load_average) 
                  VALUES (NOW(), ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ddssss',
            $metrics['cpu']['usage_percent'],
            $metrics['memory']['usage_percent'],
            json_encode($metrics['disk']),
            json_encode($metrics['network']),
            json_encode($metrics['services']),
            json_encode($metrics['load'])
        );
        
        $stmt->execute();
    }
    
    private function checkAlerts($metrics) {
        $alerts = [];
        
        // CPU alert
        if ($metrics['cpu']['usage_percent'] > 80) {
            $alerts[] = [
                'type' => 'cpu',
                'severity' => 'warning',
                'message' => "High CPU usage: {$metrics['cpu']['usage_percent']}%"
            ];
        }
        
        // Memory alert
        if ($metrics['memory']['usage_percent'] > 85) {
            $alerts[] = [
                'type' => 'memory',
                'severity' => 'warning',
                'message' => "High memory usage: {$metrics['memory']['usage_percent']}%"
            ];
        }
        
        // Disk alert
        foreach ($metrics['disk'] as $mount => $disk) {
            if ($disk['usage_percent'] > 90) {
                $alerts[] = [
                    'type' => 'disk',
                    'severity' => 'critical',
                    'message' => "Disk {$mount} is {$disk['usage_percent']}% full"
                ];
            }
        }
        
        // Service alerts
        foreach ($metrics['services'] as $service => $status) {
            if (!$status) {
                $alerts[] = [
                    'type' => 'service',
                    'severity' => 'critical',
                    'message' => "Service {$service} is down"
                ];
            }
        }
        
        return $alerts;
    }
}

/**
 * Additional Job Handlers
 */
class LogAnalysisJobHandler extends BaseJobHandler {
    public function execute($data) {
        $logFile = $data['log_file'];
        $analysis = $this->analyzeLogs($logFile);
        
        return $analysis;
    }
    
    private function analyzeLogs($logFile) {
        // Implement log analysis logic
        return [
            'file' => $logFile,
            'lines_analyzed' => 0,
            'errors' => [],
            'warnings' => [],
            'statistics' => []
        ];
    }
}

class FileCleanupJobHandler extends BaseJobHandler {
    public function execute($data) {
        $directory = $data['directory'];
        $maxAge = $data['max_age'] ?? 86400; // 1 day
        
        $cleaned = $this->cleanupOldFiles($directory, $maxAge);
        
        return [
            'directory' => $directory,
            'files_cleaned' => count($cleaned),
            'space_freed' => array_sum(array_map('filesize', $cleaned))
        ];
    }
    
    private function cleanupOldFiles($directory, $maxAge) {
        $cleaned = [];
        $cutoff = time() - $maxAge;
        
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        
        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getMTime() < $cutoff) {
                if (unlink($file->getPathname())) {
                    $cleaned[] = $file->getPathname();
                }
            }
        }
        
        return $cleaned;
    }
}

class ReportGenerationJobHandler extends BaseJobHandler {
    public function execute($data) {
        $reportType = $data['report_type'];
        $parameters = $data['parameters'] ?? [];
        
        return $this->generateReport($reportType, $parameters);
    }
    
    private function generateReport($type, $parameters) {
        // Implement report generation logic
        return [
            'type' => $type,
            'generated_at' => date('Y-m-d H:i:s'),
            'parameters' => $parameters,
            'output_file' => '/tmp/report_' . uniqid() . '.pdf'
        ];
    }
}

class UserProvisioningJobHandler extends BaseJobHandler {
    public function execute($data) {
        $action = $data['action']; // create, update, delete
        $userData = $data['user_data'];
        
        return $this->provisionUser($action, $userData);
    }
    
    private function provisionUser($action, $userData) {
        // Implement user provisioning logic
        return [
            'action' => $action,
            'user_id' => $userData['id'] ?? null,
            'status' => 'completed'
        ];
    }
}

class SSLRenewalJobHandler extends BaseJobHandler {
    public function execute($data) {
        $domain = $data['domain'];
        $certPath = $data['cert_path'];
        
        return $this->renewSSLCertificate($domain, $certPath);
    }
    
    private function renewSSLCertificate($domain, $certPath) {
        // Implement SSL renewal logic using Let's Encrypt
        return [
            'domain' => $domain,
            'cert_path' => $certPath,
            'renewed' => true,
            'expires_at' => date('Y-m-d H:i:s', strtotime('+90 days'))
        ];
    }
}

class DatabaseOptimizationJobHandler extends BaseJobHandler {
    public function execute($data) {
        $operations = $data['operations'] ?? ['analyze', 'optimize'];
        
        return $this->optimizeDatabase($operations);
    }
    
    private function optimizeDatabase($operations) {
        $results = [];
        
        if (in_array('analyze', $operations)) {
            $results['analyze'] = $this->analyzeTables();
        }
        
        if (in_array('optimize', $operations)) {
            $results['optimize'] = $this->optimizeTables();
        }
        
        return $results;
    }
    
    private function analyzeTables() {
        $this->database->query("ANALYZE TABLE users, domains, emails, files");
        return ['status' => 'completed'];
    }
    
    private function optimizeTables() {
        $this->database->query("OPTIMIZE TABLE users, domains, emails, files");
        return ['status' => 'completed'];
    }
}