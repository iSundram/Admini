<?php
/**
 * Advanced Event Streaming System
 * Real-time event processing with multiple consumers and event sourcing
 */

class EventStream {
    private $redis;
    private $database;
    private $config;
    private $subscribers = [];
    
    public function __construct($redis, $database, $config = []) {
        $this->redis = $redis;
        $this->database = $database;
        $this->config = array_merge([
            'stream_max_length' => 10000,
            'consumer_group_prefix' => 'admini:',
            'block_timeout' => 1000,
            'batch_size' => 10
        ], $config);
    }
    
    /**
     * Publish event to stream
     */
    public function publishEvent($streamName, $eventType, $data, $metadata = []) {
        $eventId = uniqid('evt_');
        $event = [
            'id' => $eventId,
            'type' => $eventType,
            'timestamp' => microtime(true),
            'data' => json_encode($data),
            'metadata' => json_encode($metadata),
            'version' => 1
        ];
        
        // Add to Redis Stream
        $this->redis->xadd($streamName, '*', $event);
        
        // Persist to database for long-term storage
        $this->persistEvent($streamName, $event);
        
        // Trigger real-time notifications
        $this->notifySubscribers($streamName, $event);
        
        return $eventId;
    }
    
    /**
     * Subscribe to events
     */
    public function subscribe($streamName, $consumerGroup, $consumerName, $callback) {
        // Create consumer group if it doesn't exist
        try {
            $this->redis->xgroup('CREATE', $streamName, $consumerGroup, '0', true);
        } catch (Exception $e) {
            // Group already exists
        }
        
        $this->subscribers[] = [
            'stream' => $streamName,
            'group' => $consumerGroup,
            'consumer' => $consumerName,
            'callback' => $callback
        ];
    }
    
    /**
     * Start consuming events
     */
    public function startConsuming() {
        while (true) {
            foreach ($this->subscribers as $subscriber) {
                $this->processStream($subscriber);
            }
            
            usleep(100000); // 100ms
        }
    }
    
    /**
     * Read events from stream
     */
    public function readEvents($streamName, $start = '0', $count = 100) {
        $events = $this->redis->xrange($streamName, $start, '+', $count);
        
        $processedEvents = [];
        foreach ($events as $id => $data) {
            $processedEvents[] = [
                'stream_id' => $id,
                'id' => $data['id'],
                'type' => $data['type'],
                'timestamp' => $data['timestamp'],
                'data' => json_decode($data['data'], true),
                'metadata' => json_decode($data['metadata'], true)
            ];
        }
        
        return $processedEvents;
    }
    
    /**
     * Get event history for specific aggregate
     */
    public function getEventHistory($aggregateId, $streamName = null) {
        $query = "SELECT * FROM event_store WHERE aggregate_id = ?";
        $params = [$aggregateId];
        
        if ($streamName) {
            $query .= " AND stream_name = ?";
            $params[] = $streamName;
        }
        
        $query .= " ORDER BY timestamp ASC";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param(str_repeat('s', count($params)), ...$params);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $events = [];
        
        while ($row = $result->fetch_assoc()) {
            $events[] = [
                'id' => $row['id'],
                'type' => $row['event_type'],
                'timestamp' => $row['timestamp'],
                'data' => json_decode($row['event_data'], true),
                'metadata' => json_decode($row['metadata'], true),
                'version' => $row['version']
            ];
        }
        
        return $events;
    }
    
    /**
     * Create event projection
     */
    public function createProjection($projectionName, $streams, $projectionHandler) {
        $lastPosition = $this->getProjectionPosition($projectionName);
        
        foreach ($streams as $streamName) {
            $events = $this->readEvents($streamName, $lastPosition, 1000);
            
            foreach ($events as $event) {
                try {
                    $projectionHandler($event, $projectionName);
                    $this->updateProjectionPosition($projectionName, $event['stream_id']);
                } catch (Exception $e) {
                    error_log("Projection error: " . $e->getMessage());
                    break;
                }
            }
        }
    }
    
    /**
     * Replay events from specific point
     */
    public function replayEvents($streamName, $fromTimestamp, $callback) {
        $query = "SELECT * FROM event_store WHERE stream_name = ? AND timestamp >= ? ORDER BY timestamp ASC";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ss', $streamName, date('Y-m-d H:i:s', $fromTimestamp));
        $stmt->execute();
        
        $result = $stmt->get_result();
        
        while ($row = $result->fetch_assoc()) {
            $event = [
                'id' => $row['id'],
                'type' => $row['event_type'],
                'timestamp' => $row['timestamp'],
                'data' => json_decode($row['event_data'], true),
                'metadata' => json_decode($row['metadata'], true)
            ];
            
            $callback($event);
        }
    }
    
    private function processStream($subscriber) {
        $streamName = $subscriber['stream'];
        $consumerGroup = $subscriber['group'];
        $consumerName = $subscriber['consumer'];
        $callback = $subscriber['callback'];
        
        try {
            $events = $this->redis->xreadgroup(
                $consumerGroup,
                $consumerName,
                [$streamName => '>'],
                $this->config['batch_size'],
                $this->config['block_timeout']
            );
            
            if ($events && isset($events[$streamName])) {
                foreach ($events[$streamName] as $id => $data) {
                    try {
                        $event = [
                            'stream_id' => $id,
                            'id' => $data['id'],
                            'type' => $data['type'],
                            'timestamp' => $data['timestamp'],
                            'data' => json_decode($data['data'], true),
                            'metadata' => json_decode($data['metadata'], true)
                        ];
                        
                        $callback($event);
                        
                        // Acknowledge the message
                        $this->redis->xack($streamName, $consumerGroup, $id);
                        
                    } catch (Exception $e) {
                        error_log("Event processing error: " . $e->getMessage());
                        // Optionally move to dead letter queue
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Stream processing error: " . $e->getMessage());
        }
    }
    
    private function persistEvent($streamName, $event) {
        $query = "INSERT INTO event_store (id, stream_name, event_type, event_data, metadata, timestamp, version, aggregate_id) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $aggregateId = $event['metadata']['aggregate_id'] ?? null;
        $timestamp = date('Y-m-d H:i:s', $event['timestamp']);
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ssssssss',
            $event['id'],
            $streamName,
            $event['type'],
            $event['data'],
            $event['metadata'],
            $timestamp,
            $event['version'],
            $aggregateId
        );
        
        $stmt->execute();
    }
    
    private function notifySubscribers($streamName, $event) {
        // Publish to pub/sub for real-time notifications
        $notification = [
            'stream' => $streamName,
            'event' => $event
        ];
        
        $this->redis->publish("events:{$streamName}", json_encode($notification));
    }
    
    private function getProjectionPosition($projectionName) {
        $query = "SELECT last_position FROM projections WHERE name = ?";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('s', $projectionName);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        return $row ? $row['last_position'] : '0';
    }
    
    private function updateProjectionPosition($projectionName, $position) {
        $query = "INSERT INTO projections (name, last_position, updated_at) 
                  VALUES (?, ?, NOW()) 
                  ON DUPLICATE KEY UPDATE last_position = ?, updated_at = NOW()";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sss', $projectionName, $position, $position);
        $stmt->execute();
    }
}