<?php
/**
 * Authentication and Authorization Middleware
 */

class AuthMiddleware {
    private $database;
    private $cache;
    private $jwtSecret;
    
    public function __construct($database, $cache) {
        $this->database = $database;
        $this->cache = $cache;
        $this->jwtSecret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
    }
    
    public function handle($context) {
        $route = $context->getRoute();
        
        // Skip auth for public routes
        if (!$route['auth_required']) {
            return true;
        }
        
        // Extract token from header
        $headers = $context->getHeaders();
        $authHeader = $headers['Authorization'] ?? '';
        
        if (!$authHeader || !preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
            return $this->unauthorizedResponse('Missing or invalid authorization header');
        }
        
        $token = $matches[1];
        
        // Validate token
        $payload = $this->validateToken($token);
        if (!$payload) {
            return $this->unauthorizedResponse('Invalid or expired token');
        }
        
        // Check user status
        $user = $this->getUser($payload['user_id']);
        if (!$user || $user['status'] !== 'active') {
            return $this->unauthorizedResponse('User account is inactive');
        }
        
        // Check role permissions
        if (!empty($route['roles']) && !in_array($user['role'], $route['roles'])) {
            return $this->forbiddenResponse('Insufficient permissions');
        }
        
        // Store user in context
        $context->setAttribute('user', $user);
        $context->setAttribute('token_payload', $payload);
        
        return true;
    }
    
    private function validateToken($token) {
        try {
            // Simple JWT validation (in production, use a proper JWT library)
            $parts = explode('.', $token);
            if (count($parts) !== 3) {
                return false;
            }
            
            $header = json_decode(base64_decode($parts[0]), true);
            $payload = json_decode(base64_decode($parts[1]), true);
            $signature = $parts[2];
            
            // Verify signature
            $expectedSignature = base64_encode(hash_hmac('sha256', $parts[0] . '.' . $parts[1], $this->jwtSecret, true));
            if (!hash_equals($expectedSignature, $signature)) {
                return false;
            }
            
            // Check expiration
            if (isset($payload['exp']) && $payload['exp'] < time()) {
                return false;
            }
            
            // Check if token is blacklisted
            if ($this->isTokenBlacklisted($token)) {
                return false;
            }
            
            return $payload;
            
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getUser($userId) {
        $cacheKey = "user:{$userId}";
        $user = $this->cache->get($cacheKey);
        
        if ($user) {
            return json_decode($user, true);
        }
        
        $query = "SELECT * FROM users WHERE id = ? AND status = 'active'";
        $stmt = $this->database->prepare($query);
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        
        $result = $stmt->get_result();
        $user = $result->fetch_assoc();
        
        if ($user) {
            $this->cache->set($cacheKey, json_encode($user), 300); // Cache for 5 minutes
        }
        
        return $user;
    }
    
    private function isTokenBlacklisted($token) {
        return $this->cache->exists("blacklisted_token:" . hash('sha256', $token));
    }
    
    private function unauthorizedResponse($message) {
        return [
            'status' => 401,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => true,
                'code' => 401,
                'message' => $message
            ])
        ];
    }
    
    private function forbiddenResponse($message) {
        return [
            'status' => 403,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => true,
                'code' => 403,
                'message' => $message
            ])
        ];
    }
}

/**
 * Rate Limiting Middleware
 */
class RateLimitMiddleware {
    private $rateLimiter;
    
    public function __construct($database, $cache) {
        $this->rateLimiter = new RateLimiter($cache);
    }
    
    public function handle($context) {
        $route = $context->getRoute();
        $rateLimit = $route['rate_limit'];
        
        if (!$rateLimit) {
            return true; // No rate limiting configured
        }
        
        // Get identifier (IP or user ID)
        $user = $context->getAttribute('user');
        $identifier = $user ? "user:{$user['id']}" : "ip:{$_SERVER['REMOTE_ADDR']}";
        
        // Check rate limit
        if (!$this->rateLimiter->isAllowed($identifier, $rateLimit)) {
            $status = $this->rateLimiter->getStatus($identifier, $rateLimit);
            
            return [
                'status' => 429,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'X-RateLimit-Remaining' => $status['remaining'],
                    'X-RateLimit-Reset' => $status['reset_time'],
                    'Retry-After' => $status['retry_after']
                ],
                'body' => json_encode([
                    'error' => true,
                    'code' => 429,
                    'message' => 'Rate limit exceeded',
                    'retry_after' => $status['retry_after']
                ])
            ];
        }
        
        return true;
    }
}

/**
 * CORS Middleware
 */
class CorsMiddleware {
    private $config;
    
    public function __construct($database, $cache) {
        $this->config = [
            'allowed_origins' => ['*'],
            'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'OPTIONS'],
            'allowed_headers' => ['Content-Type', 'Authorization', 'X-Requested-With'],
            'max_age' => 86400
        ];
    }
    
    public function handle($context) {
        $origin = $context->getHeaders()['Origin'] ?? '';
        
        // Check if origin is allowed
        if (!$this->isOriginAllowed($origin)) {
            return [
                'status' => 403,
                'headers' => ['Content-Type' => 'application/json'],
                'body' => json_encode([
                    'error' => true,
                    'code' => 403,
                    'message' => 'CORS policy violation'
                ])
            ];
        }
        
        // Handle preflight request
        if ($context->getMethod() === 'OPTIONS') {
            return [
                'status' => 200,
                'headers' => [
                    'Access-Control-Allow-Origin' => $origin ?: '*',
                    'Access-Control-Allow-Methods' => implode(', ', $this->config['allowed_methods']),
                    'Access-Control-Allow-Headers' => implode(', ', $this->config['allowed_headers']),
                    'Access-Control-Max-Age' => $this->config['max_age']
                ],
                'body' => ''
            ];
        }
        
        // Add CORS headers to context for later use
        $context->setAttribute('cors_headers', [
            'Access-Control-Allow-Origin' => $origin ?: '*',
            'Access-Control-Allow-Credentials' => 'true'
        ]);
        
        return true;
    }
    
    private function isOriginAllowed($origin) {
        if (in_array('*', $this->config['allowed_origins'])) {
            return true;
        }
        
        return in_array($origin, $this->config['allowed_origins']);
    }
}

/**
 * Request Validation Middleware
 */
class ValidationMiddleware {
    private $database;
    
    public function __construct($database, $cache) {
        $this->database = $database;
    }
    
    public function handle($context) {
        $route = $context->getRoute();
        $validation = $route['options']['validation'] ?? null;
        
        if (!$validation) {
            return true; // No validation configured
        }
        
        $method = $context->getMethod();
        $body = $context->getBody();
        
        // Parse request body
        $data = [];
        if ($body) {
            $contentType = $context->getHeaders()['Content-Type'] ?? '';
            
            if (strpos($contentType, 'application/json') !== false) {
                $data = json_decode($body, true);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    return $this->validationError('Invalid JSON format');
                }
            } elseif (strpos($contentType, 'application/x-www-form-urlencoded') !== false) {
                parse_str($body, $data);
            }
        }
        
        // Validate required fields
        if (isset($validation['required'])) {
            foreach ($validation['required'] as $field) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    return $this->validationError("Field '{$field}' is required");
                }
            }
        }
        
        // Validate field types
        if (isset($validation['types'])) {
            foreach ($validation['types'] as $field => $type) {
                if (isset($data[$field])) {
                    if (!$this->validateType($data[$field], $type)) {
                        return $this->validationError("Field '{$field}' must be of type {$type}");
                    }
                }
            }
        }
        
        // Custom validation rules
        if (isset($validation['rules'])) {
            foreach ($validation['rules'] as $field => $rules) {
                if (isset($data[$field])) {
                    foreach ($rules as $rule) {
                        if (!$this->validateRule($data[$field], $rule)) {
                            return $this->validationError("Field '{$field}' failed validation rule: {$rule}");
                        }
                    }
                }
            }
        }
        
        // Store validated data in context
        $context->setAttribute('validated_data', $data);
        
        return true;
    }
    
    private function validateType($value, $type) {
        switch ($type) {
            case 'string':
                return is_string($value);
            case 'integer':
                return is_int($value) || (is_string($value) && ctype_digit($value));
            case 'float':
                return is_float($value) || is_numeric($value);
            case 'boolean':
                return is_bool($value);
            case 'array':
                return is_array($value);
            case 'email':
                return filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
            case 'url':
                return filter_var($value, FILTER_VALIDATE_URL) !== false;
            default:
                return true;
        }
    }
    
    private function validateRule($value, $rule) {
        if (strpos($rule, 'min:') === 0) {
            $min = (int)substr($rule, 4);
            return strlen($value) >= $min;
        }
        
        if (strpos($rule, 'max:') === 0) {
            $max = (int)substr($rule, 4);
            return strlen($value) <= $max;
        }
        
        if ($rule === 'alpha') {
            return ctype_alpha($value);
        }
        
        if ($rule === 'alphanumeric') {
            return ctype_alnum($value);
        }
        
        if (strpos($rule, 'regex:') === 0) {
            $pattern = substr($rule, 6);
            return preg_match($pattern, $value);
        }
        
        return true;
    }
    
    private function validationError($message) {
        return [
            'status' => 400,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode([
                'error' => true,
                'code' => 400,
                'message' => $message
            ])
        ];
    }
}