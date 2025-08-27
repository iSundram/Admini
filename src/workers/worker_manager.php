#!/usr/bin/env php
<?php
/**
 * Worker Manager Daemon
 * Central worker process that manages all background jobs
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/MessageQueue.php';
require_once __DIR__ . '/WorkerManager.php';

// Set up signal handling
declare(ticks = 1);

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$messageQueue = new MessageQueue($redis);
$workerManager = new WorkerManager($database->getConnection(), $messageQueue);

// Handle shutdown signals
function signalHandler($signal) {
    global $workerManager;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping workers...\n";
            $workerManager->shutdown();
            exit(0);
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

echo "Starting Admini Worker Manager...\n";
echo "PID: " . getmypid() . "\n";
echo "Starting workers...\n";

try {
    $workerManager->start();
} catch (Exception $e) {
    echo "Worker Manager error: " . $e->getMessage() . "\n";
    exit(1);
}