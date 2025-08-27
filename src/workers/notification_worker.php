#!/usr/bin/env php
<?php
/**
 * Notification Worker
 * Processes and sends notifications via various channels
 */

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../services/MessageQueue.php';

$database = new Database();
$redis = new Redis();
$redis->connect('localhost', 6379);

$messageQueue = new MessageQueue($redis);

echo "Starting Notification Worker...\n";
echo "PID: " . getmypid() . "\n";

// Set up signal handling
declare(ticks = 1);

$running = true;

function signalHandler($signal) {
    global $running;
    
    switch ($signal) {
        case SIGTERM:
        case SIGINT:
            echo "Received shutdown signal, stopping notification processing...\n";
            $running = false;
            break;
    }
}

pcntl_signal(SIGTERM, 'signalHandler');
pcntl_signal(SIGINT, 'signalHandler');

// Notification handlers
class EmailNotificationHandler {
    public function send($notification) {
        $to = $notification['to'];
        $subject = $notification['subject'];
        $body = $notification['body'];
        $headers = $notification['headers'] ?? [];
        
        echo "Sending email to: $to\n";
        
        // Use PHPMailer or similar in production
        $success = mail($to, $subject, $body, implode("\r\n", $headers));
        
        if (!$success) {
            throw new Exception("Failed to send email to $to");
        }
        
        echo "Email sent successfully to: $to\n";
        return true;
    }
}

class SlackNotificationHandler {
    public function send($notification) {
        $webhook_url = $notification['webhook_url'];
        $message = $notification['message'];
        $channel = $notification['channel'] ?? '#general';
        
        echo "Sending Slack notification to: $channel\n";
        
        $payload = [
            'channel' => $channel,
            'text' => $message,
            'username' => 'Admini',
            'icon_emoji' => ':computer:'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $webhook_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Failed to send Slack notification: HTTP $httpCode");
        }
        
        echo "Slack notification sent successfully\n";
        return true;
    }
}

class SMSNotificationHandler {
    public function send($notification) {
        $to = $notification['to'];
        $message = $notification['message'];
        
        echo "Sending SMS to: $to\n";
        
        // Integrate with Twilio or similar service
        // For demo purposes, we'll just log it
        echo "SMS would be sent to $to: $message\n";
        
        return true;
    }
}

class WebhookNotificationHandler {
    public function send($notification) {
        $url = $notification['url'];
        $data = $notification['data'];
        
        echo "Sending webhook notification to: $url\n";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error || $httpCode >= 400) {
            throw new Exception("Failed to send webhook: $error (HTTP $httpCode)");
        }
        
        echo "Webhook notification sent successfully\n";
        return true;
    }
}

// Initialize notification handlers
$handlers = [
    'email' => new EmailNotificationHandler(),
    'slack' => new SlackNotificationHandler(),
    'sms' => new SMSNotificationHandler(),
    'webhook' => new WebhookNotificationHandler()
];

try {
    while ($running) {
        // Consume notification jobs
        $message = $messageQueue->consume(['notifications'], 5);
        
        if ($message) {
            $payload = $message->getPayload();
            $notificationType = $payload['type'];
            $notificationData = $payload['data'];
            
            echo "Processing notification: {$message->getId()} of type: $notificationType\n";
            
            try {
                if (isset($handlers[$notificationType])) {
                    $handlers[$notificationType]->send($notificationData);
                    $message->ack();
                    echo "Notification processed successfully\n";
                } else {
                    throw new Exception("Unknown notification type: $notificationType");
                }
                
            } catch (Exception $e) {
                echo "Notification failed: " . $e->getMessage() . "\n";
                $message->reject(true, $e->getMessage());
            }
        }
        
        // Process pending email queue
        $query = "SELECT * FROM email_queue WHERE status = 'pending' ORDER BY priority DESC, scheduled_at ASC LIMIT 10";
        $result = $database->getConnection()->query($query);
        
        while ($row = $result->fetch_assoc()) {
            echo "Processing queued email: {$row['id']}\n";
            
            try {
                $success = mail(
                    $row['to_email'],
                    $row['subject'],
                    $row['body_text'] ?: $row['body_html'],
                    "From: {$row['from_email']}\r\n" . 
                    "Content-Type: " . ($row['body_html'] ? 'text/html' : 'text/plain') . "\r\n"
                );
                
                if ($success) {
                    $updateQuery = "UPDATE email_queue SET status = 'sent', sent_at = NOW() WHERE id = ?";
                    $stmt = $database->getConnection()->prepare($updateQuery);
                    $stmt->bind_param('i', $row['id']);
                    $stmt->execute();
                    
                    echo "Email sent successfully: {$row['id']}\n";
                } else {
                    throw new Exception("Failed to send email");
                }
                
            } catch (Exception $e) {
                $attempts = $row['attempts'] + 1;
                
                if ($attempts >= $row['max_attempts']) {
                    $updateQuery = "UPDATE email_queue SET status = 'failed', error_message = ?, attempts = ? WHERE id = ?";
                    $stmt = $database->getConnection()->prepare($updateQuery);
                    $stmt->bind_param('sii', $e->getMessage(), $attempts, $row['id']);
                    $stmt->execute();
                    
                    echo "Email failed permanently: {$row['id']}\n";
                } else {
                    $updateQuery = "UPDATE email_queue SET attempts = ?, error_message = ?, scheduled_at = DATE_ADD(NOW(), INTERVAL ? MINUTE) WHERE id = ?";
                    $stmt = $database->getConnection()->prepare($updateQuery);
                    $retryDelay = pow(2, $attempts) * 5; // Exponential backoff
                    $stmt->bind_param('isii', $attempts, $e->getMessage(), $retryDelay, $row['id']);
                    $stmt->execute();
                    
                    echo "Email retry scheduled: {$row['id']} (attempt $attempts)\n";
                }
            }
        }
        
        pcntl_signal_dispatch();
    }
    
} catch (Exception $e) {
    echo "Notification worker error: " . $e->getMessage() . "\n";
    exit(1);
}

echo "Notification worker stopped\n";