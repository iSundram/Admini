<?php
/**
 * Advanced API Gateway with Rate Limiting, Authentication, and Request Routing
 */

class ApiGateway {
    private $database;
    private $cache;
    private $routes = [];
    private $middleware = [];
    private $rateLimiter;
    private $requestId;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
        $this->rateLimiter = new RateLimiter($cache);
        $this->requestId = uniqid('req_');
        $this->loadRoutes();
    }
    
    /**
     * Handle incoming API request
     */
    public function handleRequest() {
        $startTime = microtime(true);
        
        try {
            // Get request details
            $method = $_SERVER['REQUEST_METHOD'];
            $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
            $headers = $this->getAllHeaders();
            $body = file_get_contents('php://input');
            
            // Log request
            $this->logRequest($method, $path, $headers, $body);
            
            // Find matching route
            $route = $this->findRoute($method, $path);
            if (!$route) {
                return $this->errorResponse(404, 'Route not found');
            }
            
            // Execute middleware chain
            $context = new RequestContext($method, $path, $headers, $body, $route);
            
            foreach ($this->middleware as $middlewareClass) {
                $middleware = new $middlewareClass($this->database, $this->cache);
                $result = $middleware->handle($context);
                
                if ($result !== true) {
                    return $result; // Middleware blocked the request
                }
            }
            
            // Forward request to service
            $response = $this->forwardRequest($route, $context);
            
            // Log response
            $duration = microtime(true) - $startTime;
            $this->logResponse($response, $duration);
            
            return $response;
            
        } catch (Exception $e) {
            error_log("API Gateway Error: " . $e->getMessage());
            return $this->errorResponse(500, 'Internal server error');
        }
    }
    
    /**
     * Register API route
     */
    public function registerRoute($method, $pattern, $service, $options = []) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'service' => $service,
            'options' => $options,
            'middleware' => $options['middleware'] ?? [],
            'rate_limit' => $options['rate_limit'] ?? null,
            'auth_required' => $options['auth_required'] ?? true,
            'roles' => $options['roles'] ?? [],
            'timeout' => $options['timeout'] ?? 30
        ];
    }
    
    /**
     * Add middleware to the chain
     */
    public function addMiddleware($middlewareClass) {
        $this->middleware[] = $middlewareClass;
    }
    
    private function findRoute($method, $path) {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method || $route['method'] === 'ANY') {
                if ($this->matchesPattern($route['pattern'], $path)) {
                    return $route;
                }
            }
        }
        return null;
    }
    
    private function matchesPattern($pattern, $path) {
        // Convert route pattern to regex
        $regex = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';
        
        return preg_match($regex, $path);
    }
    
    private function forwardRequest($route, $context) {
        $service = $route['service'];
        
        // Build service URL
        $serviceUrl = $this->buildServiceUrl($service, $context->getPath());
        
        // Prepare headers
        $headers = $context->getHeaders();
        $headers['X-Request-ID'] = $this->requestId;
        $headers['X-Forwarded-For'] = $_SERVER['REMOTE_ADDR'];
        $headers['X-Original-URI'] = $context->getPath();
        
        // Make HTTP request to service
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $serviceUrl);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $context->getMethod());
        curl_setopt($ch, CURLOPT_POSTFIELDS, $context->getBody());
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $route['timeout']);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $this->formatHeaders($headers));
        curl_setopt($ch, CURLOPT_HEADER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        
        curl_close($ch);
        
        // Parse response
        $responseHeaders = substr($response, 0, $headerSize);
        $responseBody = substr($response, $headerSize);
        
        return [
            'status' => $httpCode,
            'headers' => $this->parseHeaders($responseHeaders),
            'body' => $responseBody
        ];
    }
    
    private function buildServiceUrl($service, $path) {
        // Get service instance from service registry
        $serviceRegistry = new ServiceRegistry($this->database, $this->cache);
        $instance = $serviceRegistry->getServiceInstance($service);
        
        if (!$instance) {
            throw new Exception("Service '{$service}' not available");
        }
        
        return $instance['protocol'] . '://' . $instance['host'] . ':' . $instance['port'] . $path;
    }
    
    private function loadRoutes() {
        $query = "SELECT * FROM api_routes WHERE status = 'active'";
        $result = $this->database->query($query);
        
        while ($row = $result->fetch_assoc()) {
            $this->routes[] = [
                'method' => $row['method'],
                'pattern' => $row['pattern'],
                'service' => $row['service_name'],
                'options' => json_decode($row['options'], true) ?? [],
                'middleware' => json_decode($row['middleware'], true) ?? [],
                'rate_limit' => $row['rate_limit'],
                'auth_required' => $row['auth_required'],
                'roles' => json_decode($row['allowed_roles'], true) ?? [],
                'timeout' => $row['timeout'] ?? 30
            ];
        }
    }
    
    private function getAllHeaders() {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $header = str_replace(' ', '-', ucwords(str_replace('_', ' ', strtolower(substr($key, 5)))));
                $headers[$header] = $value;
            }
        }
        return $headers;
    }
    
    private function formatHeaders($headers) {
        $formatted = [];
        foreach ($headers as $key => $value) {
            $formatted[] = "{$key}: {$value}";
        }
        return $formatted;
    }
    
    private function parseHeaders($headerString) {
        $headers = [];
        $lines = explode("\r\n", $headerString);
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $headers[trim($key)] = trim($value);
            }
        }
        
        return $headers;
    }
    
    private function logRequest($method, $path, $headers, $body) {
        $logEntry = [
            'request_id' => $this->requestId,
            'timestamp' => date('Y-m-d H:i:s'),
            'method' => $method,
            'path' => $path,
            'ip' => $_SERVER['REMOTE_ADDR'],
            'user_agent' => $headers['User-Agent'] ?? '',
            'content_length' => strlen($body),
            'headers' => json_encode($headers)
        ];
        
        $query = "INSERT INTO api_logs (request_id, timestamp, method, path, ip, user_agent, content_length, headers) 
                  VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('ssssssss',
            $logEntry['request_id'],
            $logEntry['timestamp'],
            $logEntry['method'],
            $logEntry['path'],
            $logEntry['ip'],
            $logEntry['user_agent'],
            $logEntry['content_length'],
            $logEntry['headers']
        );
        
        $stmt->execute();
    }
    
    private function logResponse($response, $duration) {
        $query = "UPDATE api_logs SET 
                  response_status = ?, 
                  response_time = ?, 
                  response_size = ? 
                  WHERE request_id = ?";
        
        $responseSize = strlen($response['body']);
        
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('idis',
            $response['status'],
            $duration,
            $responseSize,
            $this->requestId
        );
        
        $stmt->execute();
    }
    
    private function errorResponse($code, $message) {
        http_response_code($code);
        header('Content-Type: application/json');
        
        return [
            'status' => $code,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => true,
                'code' => $code,
                'message' => $message,
                'request_id' => $this->requestId
            ])
        ];
    }
}

/**
 * Request Context
 */
class RequestContext {
    private $method;
    private $path;
    private $headers;
    private $body;
    private $route;
    private $attributes = [];
    
    public function __construct($method, $path, $headers, $body, $route) {
        $this->method = $method;
        $this->path = $path;
        $this->headers = $headers;
        $this->body = $body;
        $this->route = $route;
    }
    
    public function getMethod() { return $this->method; }
    public function getPath() { return $this->path; }
    public function getHeaders() { return $this->headers; }
    public function getBody() { return $this->body; }
    public function getRoute() { return $this->route; }
    
    public function setAttribute($key, $value) {
        $this->attributes[$key] = $value;
    }
    
    public function getAttribute($key, $default = null) {
        return $this->attributes[$key] ?? $default;
    }
}