<?php
/**
 * Advanced Message Queue System
 * High-performance Redis-based message queuing with retry logic and dead letter queue
 */

class MessageQueue {
    private $redis;
    private $config;
    private $retryAttempts;
    private $deadLetterQueue;
    
    public function __construct($redis, $config = []) {
        $this->redis = $redis;
        $this->config = array_merge([
            'default_timeout' => 30,
            'max_retries' => 3,
            'retry_delay' => 60,
            'dead_letter_ttl' => 86400 * 7, // 7 days
        ], $config);
        
        $this->retryAttempts = [];
        $this->deadLetterQueue = 'dead_letter_queue';
    }
    
    /**
     * Publish message to queue
     */
    public function publish($queue, $message, $priority = 0, $delay = 0) {
        $messageId = uniqid('msg_');
        $payload = [
            'id' => $messageId,
            'queue' => $queue,
            'payload' => $message,
            'priority' => $priority,
            'published_at' => time(),
            'scheduled_for' => time() + $delay,
            'attempts' => 0,
            'max_retries' => $this->config['max_retries']
        ];
        
        if ($delay > 0) {
            // Delayed message
            $this->redis->zadd("delayed:{$queue}", time() + $delay, json_encode($payload));
        } else {
            // Immediate message
            if ($priority > 0) {
                $this->redis->zadd("priority:{$queue}", $priority, json_encode($payload));
            } else {
                $this->redis->lpush($queue, json_encode($payload));
            }
        }
        
        return $messageId;
    }
    
    /**
     * Consume messages from queue
     */
    public function consume($queues, $timeout = null) {
        $timeout = $timeout ?? $this->config['default_timeout'];
        
        if (!is_array($queues)) {
            $queues = [$queues];
        }
        
        // Process delayed messages first
        $this->processDelayedMessages($queues);
        
        // Process priority queues
        $message = $this->consumePriorityMessage($queues);
        if ($message) {
            return $message;
        }
        
        // Process regular queues
        $result = $this->redis->brpop($queues, $timeout);
        
        if ($result) {
            $queue = $result[0];
            $payload = json_decode($result[1], true);
            
            return new QueueMessage($payload, $this, $queue);
        }
        
        return null;
    }
    
    /**
     * Acknowledge message processing
     */
    public function ack($messageId, $queue) {
        $this->redis->hdel("processing:{$queue}", $messageId);
        unset($this->retryAttempts[$messageId]);
    }
    
    /**
     * Reject message and optionally requeue
     */
    public function reject($messageId, $queue, $requeue = true, $reason = null) {
        $processingKey = "processing:{$queue}";
        $messageData = $this->redis->hget($processingKey, $messageId);
        
        if (!$messageData) {
            return false;
        }
        
        $payload = json_decode($messageData, true);
        $payload['attempts']++;
        $payload['last_error'] = $reason;
        $payload['last_attempt'] = time();
        
        $this->redis->hdel($processingKey, $messageId);
        
        if ($requeue && $payload['attempts'] < $payload['max_retries']) {
            // Requeue with exponential backoff
            $delay = $this->config['retry_delay'] * pow(2, $payload['attempts'] - 1);
            $this->redis->zadd("delayed:{$queue}", time() + $delay, json_encode($payload));
        } else {
            // Send to dead letter queue
            $this->sendToDeadLetterQueue($payload, $reason);
        }
        
        return true;
    }
    
    /**
     * Get queue statistics
     */
    public function getQueueStats($queue) {
        return [
            'pending' => $this->redis->llen($queue),
            'priority' => $this->redis->zcard("priority:{$queue}"),
            'delayed' => $this->redis->zcard("delayed:{$queue}"),
            'processing' => $this->redis->hlen("processing:{$queue}"),
            'dead_letter' => $this->redis->llen($this->deadLetterQueue)
        ];
    }
    
    /**
     * Purge queue
     */
    public function purgeQueue($queue) {
        $this->redis->del($queue);
        $this->redis->del("priority:{$queue}");
        $this->redis->del("delayed:{$queue}");
        $this->redis->del("processing:{$queue}");
    }
    
    /**
     * Get dead letter messages
     */
    public function getDeadLetterMessages($limit = 100) {
        $messages = $this->redis->lrange($this->deadLetterQueue, 0, $limit - 1);
        return array_map('json_decode', $messages, array_fill(0, count($messages), true));
    }
    
    /**
     * Requeue dead letter message
     */
    public function requeueDeadLetter($messageId) {
        $messages = $this->getDeadLetterMessages(1000);
        
        foreach ($messages as $index => $message) {
            if ($message['id'] === $messageId) {
                // Remove from dead letter queue
                $this->redis->lrem($this->deadLetterQueue, 1, json_encode($message));
                
                // Reset attempts and requeue
                $message['attempts'] = 0;
                unset($message['last_error']);
                unset($message['dead_letter_reason']);
                
                $this->redis->lpush($message['queue'], json_encode($message));
                return true;
            }
        }
        
        return false;
    }
    
    private function processDelayedMessages($queues) {
        foreach ($queues as $queue) {
            $delayedKey = "delayed:{$queue}";
            $now = time();
            
            // Get messages ready for processing
            $messages = $this->redis->zrangebyscore($delayedKey, 0, $now);
            
            foreach ($messages as $messageJson) {
                $message = json_decode($messageJson, true);
                
                // Move to appropriate queue
                if ($message['priority'] > 0) {
                    $this->redis->zadd("priority:{$queue}", $message['priority'], $messageJson);
                } else {
                    $this->redis->lpush($queue, $messageJson);
                }
                
                // Remove from delayed queue
                $this->redis->zrem($delayedKey, $messageJson);
            }
        }
    }
    
    private function consumePriorityMessage($queues) {
        foreach ($queues as $queue) {
            $priorityKey = "priority:{$queue}";
            
            // Get highest priority message
            $result = $this->redis->zpopmax($priorityKey);
            
            if ($result) {
                $messageJson = $result[0];
                $payload = json_decode($messageJson, true);
                
                return new QueueMessage($payload, $this, $queue);
            }
        }
        
        return null;
    }
    
    private function sendToDeadLetterQueue($payload, $reason) {
        $payload['dead_letter_reason'] = $reason;
        $payload['dead_letter_timestamp'] = time();
        
        $this->redis->lpush($this->deadLetterQueue, json_encode($payload));
        
        // Set TTL on dead letter queue
        $this->redis->expire($this->deadLetterQueue, $this->config['dead_letter_ttl']);
    }
}

/**
 * Queue Message Wrapper
 */
class QueueMessage {
    private $payload;
    private $queue;
    private $messageQueue;
    
    public function __construct($payload, $messageQueue, $queue) {
        $this->payload = $payload;
        $this->messageQueue = $messageQueue;
        $this->queue = $queue;
        
        // Move to processing queue
        $processingKey = "processing:{$queue}";
        $messageQueue->redis->hset($processingKey, $payload['id'], json_encode($payload));
    }
    
    public function getId() {
        return $this->payload['id'];
    }
    
    public function getPayload() {
        return $this->payload['payload'];
    }
    
    public function getQueue() {
        return $this->queue;
    }
    
    public function getAttempts() {
        return $this->payload['attempts'];
    }
    
    public function getPublishedAt() {
        return $this->payload['published_at'];
    }
    
    public function ack() {
        $this->messageQueue->ack($this->payload['id'], $this->queue);
    }
    
    public function reject($requeue = true, $reason = null) {
        $this->messageQueue->reject($this->payload['id'], $this->queue, $requeue, $reason);
    }
}