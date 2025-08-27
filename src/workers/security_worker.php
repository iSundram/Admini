#!/usr/bin/env php
<?php
/**
 * Security Worker
 * Continuously monitors for security threats and performs scanning
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/EventStream.php';
require_once __DIR__ . '/../security/ThreatDetectionSystem.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$eventStream = new EventStream($redis, $database->getConnection());
$threatDetection = new ThreatDetectionSystem($database->getConnection(), $redis, $eventStream);

echo "Starting Security Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping security monitoring...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

try {
    // Start threat detection
    $threatDetection->startMonitoring();
    
    // Main loop
    while ($running) {
        // Perform scheduled vulnerability scans
        $targets = ['/var/www/html', '/home/*/public_html'];
        
        foreach ($targets as $target) {
            if (is_dir($target)) {
                $assessment = $threatDetection->performVulnerabilityAssessment($target, [
                    'scan_type' => 'quick',
                    'include_web_scan' => true,
                    'include_db_scan' => false
                ]);
                
                if ($assessment['risk_score'] > 7.0) {
                    echo "High risk vulnerabilities found in $target\n";
                }
            }
        }
        
        sleep(300); // Run every 5 minutes
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Security worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Security worker stopped\n";