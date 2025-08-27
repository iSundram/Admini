#!/usr/bin/env php
<?php
/**
 * Workflow Engine Worker
 * Processes workflow executions and scheduled workflows
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/EventStream.php';
require_once __DIR__ . '/../workflows/WorkflowEngine.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$eventStream = new EventStream($redis, $database->getConnection());
$workflowEngine = new WorkflowEngine($database->getConnection(), $redis, $eventStream);

echo "Starting Workflow Engine Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping workflow processing...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

try {
    while ($running) {
        // Process scheduled workflows
        echo "Processing scheduled workflows...\n";
        $workflowEngine->processScheduledWorkflows();
        
        // Process pending workflow executions
        $query = "SELECT * FROM workflow_executions WHERE status = 'running' ORDER BY started_at ASC LIMIT 10";
        $result = $database->getConnection()->query($query);
        
        while ($row = $result->fetch_assoc()) {
            echo "Resuming workflow execution: {$row['id']}\n";
            
            try {
                // Resume workflow execution
                $result = $workflowEngine->executeWorkflow(
                    $row['workflow_id'],
                    json_decode($row['trigger_data'], true),
                    json_decode($row['context'], true)
                );
                
                echo "Workflow execution completed: {$row['id']}\n";
                
            } catch (Exception $e) {
                echo "Workflow execution failed: " . $e->getMessage() . "\n";
                
                // Update execution status to failed
                $updateQuery = "UPDATE workflow_executions SET status = 'failed', completed_at = NOW() WHERE id = ?";
                $stmt = $database->getConnection()->prepare($updateQuery);
                $stmt->bind_param('s', $row['id']);
                $stmt->execute();
            }
        }
        
        // Clean up old workflow executions
        $cleanupQuery = "DELETE FROM workflow_executions 
                        WHERE status IN ('completed', 'failed') 
                        AND completed_at < DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $database->getConnection()->query($cleanupQuery);
        
        sleep(30); // Process every 30 seconds
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Workflow worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Workflow worker stopped\n";