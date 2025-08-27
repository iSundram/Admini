#!/usr/bin/env php
<?php
/**
 * Event Stream Worker
 * Processes events from the event stream and updates projections
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/EventStream.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$eventStream = new EventStream($redis, $database->getConnection());

echo "Starting Event Stream Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping event processing...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

// Subscribe to event streams
$eventStream->subscribe('user_events', 'event_group', 'event_worker', function($event) {
    echo "Processing user event: {$event['type']}\n";
    
    // Update user activity projection
    if ($event['type'] === 'user_logged_in') {
        updateUserActivityProjection($event);
    }
});

$eventStream->subscribe('system_events', 'event_group', 'event_worker', function($event) {
    echo "Processing system event: {$event['type']}\n";
    
    // Update system metrics projection
    if ($event['type'] === 'metrics_collected') {
        updateSystemMetricsProjection($event);
    }
});

$eventStream->subscribe('security_events', 'event_group', 'event_worker', function($event) {
    echo "Processing security event: {$event['type']}\n";
    
    // Update security events projection
    updateSecurityProjection($event);
});

function updateUserActivityProjection($event) {
    global $database;
    
    $data = $event['data'];
    $userId = $data['user_id'];
    $activity = $data['activity'];
    
    $query = "INSERT INTO user_activity_projection (user_id, activity_type, count, last_updated) 
              VALUES (?, ?, 1, NOW()) 
              ON DUPLICATE KEY UPDATE count = count + 1, last_updated = NOW()";
    
    $stmt = $database->getConnection()->prepare($query);
    $stmt->bind_param('is', $userId, $activity);
    $stmt->execute();
}

function updateSystemMetricsProjection($event) {
    global $database;
    
    $data = $event['data'];
    $metrics = $data['metrics'];
    
    foreach ($metrics as $metric => $value) {
        $query = "INSERT INTO system_metrics_projection (metric_name, avg_value, min_value, max_value, sample_count, last_updated) 
                  VALUES (?, ?, ?, ?, 1, NOW()) 
                  ON DUPLICATE KEY UPDATE 
                  avg_value = (avg_value * sample_count + ?) / (sample_count + 1),
                  min_value = LEAST(min_value, ?),
                  max_value = GREATEST(max_value, ?),
                  sample_count = sample_count + 1,
                  last_updated = NOW()";
        
        $stmt = $database->getConnection()->prepare($query);
        $stmt->bind_param('sdddddd', $metric, $value, $value, $value, $value, $value, $value);
        $stmt->execute();
    }
}

function updateSecurityProjection($event) {
    global $database;
    
    $data = $event['data'];
    
    $query = "INSERT INTO security_events_projection (event_type, severity, count, last_occurrence) 
              VALUES (?, ?, 1, NOW()) 
              ON DUPLICATE KEY UPDATE count = count + 1, last_occurrence = NOW()";
    
    $stmt = $database->getConnection()->prepare($query);
    $stmt->bind_param('ss', $data['event_type'], $data['severity']);
    $stmt->execute();
}

try {
    // Start consuming events
    $eventStream->startConsuming();
    
} catch (Exception $e) {
    echo "Event worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Event worker stopped\n";