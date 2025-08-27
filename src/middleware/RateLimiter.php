<?php
/**
 * Advanced Rate Limiter with Multiple Strategies
 */

class RateLimiter {
    private $cache;
    private $strategies;
    
    public function __construct($cache) {
        $this->cache = $cache;
        $this->strategies = [
            'token_bucket' => new TokenBucketStrategy($cache),
            'sliding_window' => new SlidingWindowStrategy($cache),
            'fixed_window' => new FixedWindowStrategy($cache),
            'leaky_bucket' => new LeakyBucketStrategy($cache)
        ];
    }
    
    /**
     * Check if request is within rate limit
     */
    public function isAllowed($identifier, $config) {
        $strategy = $config['strategy'] ?? 'token_bucket';
        
        if (!isset($this->strategies[$strategy])) {
            throw new Exception("Unknown rate limiting strategy: {$strategy}");
        }
        
        return $this->strategies[$strategy]->isAllowed($identifier, $config);
    }
    
    /**
     * Get rate limit status
     */
    public function getStatus($identifier, $config) {
        $strategy = $config['strategy'] ?? 'token_bucket';
        
        if (!isset($this->strategies[$strategy])) {
            throw new Exception("Unknown rate limiting strategy: {$strategy}");
        }
        
        return $this->strategies[$strategy]->getStatus($identifier, $config);
    }
}

/**
 * Token Bucket Rate Limiting Strategy
 */
class TokenBucketStrategy {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function isAllowed($identifier, $config) {
        $capacity = $config['capacity']; // Maximum tokens
        $refillRate = $config['refill_rate']; // Tokens per second
        $tokensRequested = $config['tokens'] ?? 1;
        
        $key = "rate_limit:token_bucket:{$identifier}";
        $now = microtime(true);
        
        // Get current bucket state
        $bucket = $this->cache->get($key);
        if (!$bucket) {
            $bucket = [
                'tokens' => $capacity,
                'last_refill' => $now
            ];
        } else {
            $bucket = json_decode($bucket, true);
        }
        
        // Calculate tokens to add based on time elapsed
        $timeElapsed = $now - $bucket['last_refill'];
        $tokensToAdd = floor($timeElapsed * $refillRate);
        
        // Add tokens, but don't exceed capacity
        $bucket['tokens'] = min($capacity, $bucket['tokens'] + $tokensToAdd);
        $bucket['last_refill'] = $now;
        
        // Check if we have enough tokens
        if ($bucket['tokens'] >= $tokensRequested) {
            $bucket['tokens'] -= $tokensRequested;
            $this->cache->set($key, json_encode($bucket), 3600);
            return true;
        }
        
        // Not enough tokens, update bucket without consuming
        $this->cache->set($key, json_encode($bucket), 3600);
        return false;
    }
    
    public function getStatus($identifier, $config) {
        $key = "rate_limit:token_bucket:{$identifier}";
        $bucket = $this->cache->get($key);
        
        if (!$bucket) {
            return [
                'remaining' => $config['capacity'],
                'reset_time' => null,
                'retry_after' => 0
            ];
        }
        
        $bucket = json_decode($bucket, true);
        $refillTime = (($config['capacity'] - $bucket['tokens']) / $config['refill_rate']);
        
        return [
            'remaining' => $bucket['tokens'],
            'reset_time' => time() + $refillTime,
            'retry_after' => $bucket['tokens'] > 0 ? 0 : ceil($refillTime)
        ];
    }
}

/**
 * Sliding Window Rate Limiting Strategy
 */
class SlidingWindowStrategy {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function isAllowed($identifier, $config) {
        $limit = $config['limit'];
        $window = $config['window']; // in seconds
        
        $key = "rate_limit:sliding:{$identifier}";
        $now = time();
        $windowStart = $now - $window;
        
        // Remove old entries
        $this->cache->zremrangebyscore($key, 0, $windowStart);
        
        // Count current requests
        $currentCount = $this->cache->zcard($key);
        
        if ($currentCount < $limit) {
            // Add current request
            $this->cache->zadd($key, $now, uniqid());
            $this->cache->expire($key, $window);
            return true;
        }
        
        return false;
    }
    
    public function getStatus($identifier, $config) {
        $key = "rate_limit:sliding:{$identifier}";
        $window = $config['window'];
        $now = time();
        $windowStart = $now - $window;
        
        $this->cache->zremrangebyscore($key, 0, $windowStart);
        $currentCount = $this->cache->zcard($key);
        $remaining = max(0, $config['limit'] - $currentCount);
        
        // Get oldest request in current window
        $oldest = $this->cache->zrange($key, 0, 0, 'WITHSCORES');
        $resetTime = $oldest ? $oldest[0][1] + $window : $now;
        
        return [
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'retry_after' => $remaining > 0 ? 0 : ($resetTime - $now)
        ];
    }
}

/**
 * Fixed Window Rate Limiting Strategy
 */
class FixedWindowStrategy {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function isAllowed($identifier, $config) {
        $limit = $config['limit'];
        $window = $config['window']; // in seconds
        
        $now = time();
        $windowStart = floor($now / $window) * $window;
        $key = "rate_limit:fixed:{$identifier}:{$windowStart}";
        
        $currentCount = $this->cache->get($key) ?? 0;
        
        if ($currentCount < $limit) {
            $this->cache->incr($key);
            $this->cache->expire($key, $window);
            return true;
        }
        
        return false;
    }
    
    public function getStatus($identifier, $config) {
        $window = $config['window'];
        $now = time();
        $windowStart = floor($now / $window) * $window;
        $key = "rate_limit:fixed:{$identifier}:{$windowStart}";
        
        $currentCount = $this->cache->get($key) ?? 0;
        $remaining = max(0, $config['limit'] - $currentCount);
        $resetTime = $windowStart + $window;
        
        return [
            'remaining' => $remaining,
            'reset_time' => $resetTime,
            'retry_after' => $remaining > 0 ? 0 : ($resetTime - $now)
        ];
    }
}

/**
 * Leaky Bucket Rate Limiting Strategy
 */
class LeakyBucketStrategy {
    private $cache;
    
    public function __construct($cache) {
        $this->cache = $cache;
    }
    
    public function isAllowed($identifier, $config) {
        $capacity = $config['capacity'];
        $leakRate = $config['leak_rate']; // requests per second
        
        $key = "rate_limit:leaky:{$identifier}";
        $now = microtime(true);
        
        $bucket = $this->cache->get($key);
        if (!$bucket) {
            $bucket = [
                'volume' => 0,
                'last_leak' => $now
            ];
        } else {
            $bucket = json_decode($bucket, true);
        }
        
        // Calculate leaked amount
        $timeElapsed = $now - $bucket['last_leak'];
        $leaked = $timeElapsed * $leakRate;
        
        // Update volume
        $bucket['volume'] = max(0, $bucket['volume'] - $leaked);
        $bucket['last_leak'] = $now;
        
        // Check if we can add the request
        if ($bucket['volume'] < $capacity) {
            $bucket['volume'] += 1;
            $this->cache->set($key, json_encode($bucket), 3600);
            return true;
        }
        
        $this->cache->set($key, json_encode($bucket), 3600);
        return false;
    }
    
    public function getStatus($identifier, $config) {
        $key = "rate_limit:leaky:{$identifier}";
        $bucket = $this->cache->get($key);
        
        if (!$bucket) {
            return [
                'remaining' => $config['capacity'],
                'reset_time' => null,
                'retry_after' => 0
            ];
        }
        
        $bucket = json_decode($bucket, true);
        $remaining = $config['capacity'] - $bucket['volume'];
        $resetTime = time() + ($bucket['volume'] / $config['leak_rate']);
        
        return [
            'remaining' => max(0, $remaining),
            'reset_time' => $resetTime,
            'retry_after' => $remaining > 0 ? 0 : ceil($resetTime - time())
        ];
    }
}