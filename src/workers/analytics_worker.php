#!/usr/bin/env php
<?php
/**
 * Analytics Worker
 * Processes analytics data and generates insights
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../analytics/AnalyticsEngine.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$analyticsEngine = new AnalyticsEngine($database->getConnection(), $redis);

echo "Starting Analytics Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping analytics processing...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

try {
    while ($running) {
        // Process analytics data
        echo "Processing analytics data...\n";
        
        // Generate real-time analytics
        $realtimeData = $analyticsEngine->getRealtimeAnalytics();
        
        // Store processed analytics
        if (!empty($realtimeData)) {
            $redis->set('realtime_analytics', json_encode($realtimeData), 60);
        }
        
        // Generate hourly reports
        if (date('i') == '00') { // Every hour
            echo "Generating hourly analytics report...\n";
            
            $reportConfig = [
                'name' => 'Hourly System Report',
                'data_source' => 'system_metrics',
                'metrics' => ['cpu_usage', 'memory_usage', 'disk_usage'],
                'dimensions' => ['timestamp'],
                'time_range' => '1h',
                'format' => 'json'
            ];
            
            $report = $analyticsEngine->generateReport($reportConfig);
            echo "Hourly report generated: " . strlen($report) . " bytes\n";
        }
        
        // Generate daily forecasts
        if (date('H:i') == '02:00') { // Daily at 2 AM
            echo "Generating daily forecasts...\n";
            
            $predictions = $analyticsEngine->generatePredictions('resource_demand', [
                'forecast_days' => 7,
                'confidence_level' => 0.95
            ]);
            
            if (!empty($predictions)) {
                $redis->set('daily_forecasts', json_encode($predictions), 86400);
            }
        }
        
        sleep(300); // Process every 5 minutes
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Analytics worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Analytics worker stopped\n";