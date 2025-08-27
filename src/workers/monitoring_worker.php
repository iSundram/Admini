#!/usr/bin/env php
<?php
/**
 * Monitoring System Worker
 * Continuously collects system metrics and triggers alerts
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/EventStream.php';
require_once __DIR__ . '/../monitoring/MonitoringSystem.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$eventStream = new EventStream($redis, $database->getConnection());
$monitoringSystem = new MonitoringSystem($database->getConnection(), $redis, $eventStream);

echo "Starting Monitoring System Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping monitoring...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

try {
    // Start monitoring system
    $monitoringSystem->start();
    
    // Main loop
    while ($running) {
        // Collect metrics every 30 seconds
        $monitoringSystem->collectMetrics();
        sleep(30);
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Monitoring worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Monitoring worker stopped\n";