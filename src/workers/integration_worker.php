#!/usr/bin/env php
<?php
/**
 * Integration Worker
 * Handles third-party integrations and data synchronization
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/EventStream.php';
require_once __DIR__ . '/../integrations/IntegrationFramework.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$eventStream = new EventStream($redis, $database->getConnection());
$integrationFramework = new IntegrationFramework($database->getConnection(), $redis, $eventStream);

echo "Starting Integration Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping integration processing...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

try {
    while ($running) {
        // Process scheduled synchronizations
        echo "Checking for scheduled integrations...\n";
        
        $query = "SELECT * FROM integrations WHERE status = 'active' AND 
                  (last_sync IS NULL OR last_sync < DATE_SUB(NOW(), INTERVAL sync_interval MINUTE))";
        
        $result = $database->getConnection()->query($query);
        
        while ($row = $result->fetch_assoc()) {
            echo "Processing integration: {$row['name']}\n";
            
            try {
                $syncResult = $integrationFramework->executeSync($row['id'], [
                    'incremental' => true,
                    'batch_size' => 100
                ]);
                
                echo "Sync completed: {$syncResult['records_processed']} records processed\n";
                
            } catch (Exception $e) {
                echo "Sync failed for {$row['name']}: " . $e->getMessage() . "\n";
            }
        }
        
        // Process pending webhooks
        $query = "SELECT * FROM webhooks WHERE status = 'processing' ORDER BY received_at ASC LIMIT 10";
        $result = $database->getConnection()->query($query);
        
        while ($row = $result->fetch_assoc()) {
            echo "Processing webhook: {$row['id']}\n";
            
            try {
                $payload = json_decode($row['payload'], true);
                $headers = json_decode($row['headers'], true);
                
                $webhookResult = $integrationFramework->processWebhook(
                    $row['provider'],
                    $payload,
                    $headers
                );
                
                echo "Webhook processed: {$row['id']}\n";
                
            } catch (Exception $e) {
                echo "Webhook processing failed: " . $e->getMessage() . "\n";
            }
        }
        
        sleep(60); // Check every minute
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Integration worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Integration worker stopped\n";