<?php
/**
 * Advanced Service Registry for Microservices Architecture
 * Manages service discovery, health checks, and load balancing
 */

class ServiceRegistry {
    private $services = [];
    private $healthChecks = [];
    private $database;
    private $cache;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
        $this->loadServices();
    }
    
    /**
     * Register a new service
     */
    public function registerService($name, $config) {
        $serviceId = uniqid('srv_');
        $service = [
            'id' => $serviceId,
            'name' => $name,
            'host' => $config['host'],
            'port' => $config['port'],
            'protocol' => $config['protocol'] ?? 'http',
            'health_endpoint' => $config['health_endpoint'] ?? '/health',
            'weight' => $config['weight'] ?? 100,
            'status' => 'active',
            'registered_at' => date('Y-m-d H:i:s'),
            'last_health_check' => null,
            'metadata' => json_encode($config['metadata'] ?? [])
        ];
        
        $this->services[$serviceId] = $service;
        $this->saveService($service);
        $this->scheduleHealthCheck($serviceId);
        
        return $serviceId;
    }
    
    /**
     * Discover available services
     */
    public function discoverServices($serviceName = null) {
        if ($serviceName) {
            return array_filter($this->services, function($service) use ($serviceName) {
                return $service['name'] === $serviceName && $service['status'] === 'active';
            });
        }
        
        return array_filter($this->services, function($service) {
            return $service['status'] === 'active';
        });
    }
    
    /**
     * Get service instance with load balancing
     */
    public function getServiceInstance($serviceName, $strategy = 'round_robin') {
        $services = $this->discoverServices($serviceName);
        
        if (empty($services)) {
            return null;
        }
        
        switch ($strategy) {
            case 'random':
                return $services[array_rand($services)];
            case 'weighted':
                return $this->getWeightedService($services);
            case 'least_connections':
                return $this->getLeastConnectedService($services);
            default: // round_robin
                return $this->getRoundRobinService($serviceName, $services);
        }
    }
    
    /**
     * Perform health checks on all services
     */
    public function performHealthChecks() {
        foreach ($this->services as $serviceId => $service) {
            $this->checkServiceHealth($serviceId);
        }
    }
    
    /**
     * Check individual service health
     */
    private function checkServiceHealth($serviceId) {
        $service = $this->services[$serviceId];
        $url = $service['protocol'] . '://' . $service['host'] . ':' . $service['port'] . $service['health_endpoint'];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $isHealthy = ($httpCode >= 200 && $httpCode < 300);
        
        $this->services[$serviceId]['status'] = $isHealthy ? 'active' : 'unhealthy';
        $this->services[$serviceId]['last_health_check'] = date('Y-m-d H:i:s');
        
        $this->updateServiceHealth($serviceId, $isHealthy);
        
        if (!$isHealthy) {
            $this->notifyServiceDown($serviceId);
        }
    }
    
    private function getRoundRobinService($serviceName, $services) {
        $key = "service_rr_{$serviceName}";
        $index = $this->cache->get($key) ?? 0;
        $serviceKeys = array_keys($services);
        
        if ($index >= count($serviceKeys)) {
            $index = 0;
        }
        
        $this->cache->set($key, $index + 1, 3600);
        return $services[$serviceKeys[$index]];
    }
    
    private function getWeightedService($services) {
        $totalWeight = array_sum(array_column($services, 'weight'));
        $random = rand(1, $totalWeight);
        $currentWeight = 0;
        
        foreach ($services as $service) {
            $currentWeight += $service['weight'];
            if ($random <= $currentWeight) {
                return $service;
            }
        }
        
        return reset($services);
    }
    
    private function getLeastConnectedService($services) {
        $connections = [];
        foreach ($services as $service) {
            $connections[$service['id']] = $this->getActiveConnections($service['id']);
        }
        
        $minConnections = min($connections);
        $serviceId = array_search($minConnections, $connections);
        
        return $this->services[$serviceId];
    }
    
    private function getActiveConnections($serviceId) {
        return $this->cache->get("service_connections_{$serviceId}") ?? 0;
    }
    
    private function loadServices() {
        $query = "SELECT * FROM services WHERE status != 'deleted'";
        $result = $this->database->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $this->services[$row['id']] = $row;
        }
    }
    
    private function saveService($service) {
        $query = "INSERT INTO services (id, name, host, port, protocol, health_endpoint, weight, status, registered_at, metadata) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('sssissssss', 
            $service['id'], $service['name'], $service['host'], $service['port'], 
            $service['protocol'], $service['health_endpoint'], $service['weight'],
            $service['status'], $service['registered_at'], $service['metadata']
        );
        
        $stmt->execute();
    }
    
    private function updateServiceHealth($serviceId, $isHealthy) {
        $status = $isHealthy ? 'active' : 'unhealthy';
        $query = "UPDATE services SET status = ?, last_health_check = NOW() WHERE id = ?";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ss', $status, $serviceId);
        $stmt->execute();
    }
    
    private function scheduleHealthCheck($serviceId) {
        // Add to health check queue
        $this->cache->lpush('health_check_queue', $serviceId);
    }
    
    private function notifyServiceDown($serviceId) {
        $service = $this->services[$serviceId];
        
        // Log the event
        error_log("Service {$service['name']} ({$serviceId}) is down");
        
        // Add to notification queue
        $notification = [
            'type' => 'service_down',
            'service_id' => $serviceId,
            'service_name' => $service['name'],
            'timestamp' => time(),
            'details' => "Service {$service['name']} at {$service['host']}:{$service['port']} is not responding"
        ];
        
        $this->cache->lpush('notification_queue', json_encode($notification));
    }
}