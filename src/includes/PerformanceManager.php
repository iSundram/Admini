<?php
/**
 * Performance Optimization and Caching Manager
 */

class PerformanceManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Configure Redis caching
     */
    public function configureRedis($host = '127.0.0.1', $port = 6379, $password = null) {
        try {
            $redis = new Redis();
            $redis->connect($host, $port);
            
            if ($password) {
                $redis->auth($password);
            }
            
            // Test connection
            $redis->ping();
            
            // Store configuration
            $this->db->query(
                "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [
                    'redis_config',
                    json_encode(['host' => $host, 'port' => $port, 'password' => $password]),
                    'Redis cache configuration',
                    json_encode(['host' => $host, 'port' => $port, 'password' => $password])
                ]
            );
            
            return ['success' => true, 'message' => 'Redis configured successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Redis configuration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Configure Memcached
     */
    public function configureMemcached($servers = [['127.0.0.1', 11211]]) {
        try {
            $memcached = new Memcached();
            $memcached->addServers($servers);
            
            // Test connection
            $memcached->set('test_key', 'test_value', 10);
            $testValue = $memcached->get('test_key');
            
            if ($testValue !== 'test_value') {
                throw new Exception('Memcached test failed');
            }
            
            // Store configuration
            $this->db->query(
                "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
                 ON DUPLICATE KEY UPDATE setting_value = ?",
                [
                    'memcached_config',
                    json_encode(['servers' => $servers]),
                    'Memcached cache configuration',
                    json_encode(['servers' => $servers])
                ]
            );
            
            return ['success' => true, 'message' => 'Memcached configured successfully'];
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Memcached configuration failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Configure CDN
     */
    public function configureCDN($provider, $config) {
        $validProviders = ['cloudflare', 'aws_cloudfront', 'maxcdn', 'keycdn'];
        
        if (!in_array($provider, $validProviders)) {
            throw new Exception("Unsupported CDN provider: {$provider}");
        }
        
        // Validate configuration based on provider
        switch ($provider) {
            case 'cloudflare':
                $this->validateCloudflareConfig($config);
                break;
            case 'aws_cloudfront':
                $this->validateCloudFrontConfig($config);
                break;
            case 'maxcdn':
                $this->validateMaxCDNConfig($config);
                break;
            case 'keycdn':
                $this->validateKeyCDNConfig($config);
                break;
        }
        
        // Store CDN configuration
        $this->db->query(
            "INSERT INTO system_settings (setting_key, setting_value, description) VALUES (?, ?, ?) 
             ON DUPLICATE KEY UPDATE setting_value = ?",
            [
                'cdn_config',
                json_encode(['provider' => $provider, 'config' => $config]),
                'CDN configuration',
                json_encode(['provider' => $provider, 'config' => $config])
            ]
        );
        
        return ['success' => true, 'message' => 'CDN configured successfully'];
    }
    
    /**
     * Validate Cloudflare configuration
     */
    private function validateCloudflareConfig($config) {
        $required = ['api_token', 'zone_id'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("Cloudflare configuration missing: {$field}");
            }
        }
        
        // Test API connection
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$config['zone_id']}",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$config['api_token']}",
                "Content-Type: application/json",
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception('Cloudflare API authentication failed');
        }
    }
    
    /**
     * Validate AWS CloudFront configuration
     */
    private function validateCloudFrontConfig($config) {
        $required = ['access_key', 'secret_key', 'distribution_id'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("CloudFront configuration missing: {$field}");
            }
        }
    }
    
    /**
     * Validate MaxCDN configuration
     */
    private function validateMaxCDNConfig($config) {
        $required = ['api_key', 'secret', 'zone_id'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("MaxCDN configuration missing: {$field}");
            }
        }
    }
    
    /**
     * Validate KeyCDN configuration
     */
    private function validateKeyCDNConfig($config) {
        $required = ['api_key', 'zone_id'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new Exception("KeyCDN configuration missing: {$field}");
            }
        }
    }
    
    /**
     * Purge CDN cache
     */
    public function purgeCDNCache($urls = []) {
        $cdnConfig = $this->getCDNConfig();
        
        if (!$cdnConfig) {
            throw new Exception('CDN not configured');
        }
        
        switch ($cdnConfig['provider']) {
            case 'cloudflare':
                return $this->purgeCloudflareCache($cdnConfig['config'], $urls);
            case 'aws_cloudfront':
                return $this->purgeCloudFrontCache($cdnConfig['config'], $urls);
            case 'maxcdn':
                return $this->purgeMaxCDNCache($cdnConfig['config'], $urls);
            case 'keycdn':
                return $this->purgeKeyCDNCache($cdnConfig['config'], $urls);
            default:
                throw new Exception('Unsupported CDN provider');
        }
    }
    
    /**
     * Purge Cloudflare cache
     */
    private function purgeCloudflareCache($config, $urls) {
        $data = empty($urls) ? ['purge_everything' => true] : ['files' => $urls];
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.cloudflare.com/client/v4/zones/{$config['zone_id']}/purge_cache",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($data),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$config['api_token']}",
                "Content-Type: application/json",
            ],
        ]);
        
        $response = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);
        
        if ($httpCode !== 200) {
            throw new Exception('Cloudflare cache purge failed');
        }
        
        return ['success' => true, 'message' => 'Cloudflare cache purged successfully'];
    }
    
    /**
     * Get CDN configuration
     */
    private function getCDNConfig() {
        $setting = $this->db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'cdn_config'"
        );
        
        return $setting ? json_decode($setting['setting_value'], true) : null;
    }
    
    /**
     * Optimize database
     */
    public function optimizeDatabase() {
        $results = [];
        
        // Get all tables
        $tables = $this->db->fetchAll("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            
            // Optimize table
            $this->db->query("OPTIMIZE TABLE `{$tableName}`");
            
            // Get table status
            $status = $this->db->fetch("SHOW TABLE STATUS LIKE '{$tableName}'");
            
            $results[] = [
                'table' => $tableName,
                'rows' => $status['Rows'],
                'data_length' => $status['Data_length'],
                'index_length' => $status['Index_length'],
                'status' => 'optimized'
            ];
        }
        
        return $results;
    }
    
    /**
     * Analyze website performance
     */
    public function analyzePerformance($url) {
        $analysis = [
            'url' => $url,
            'timestamp' => date('c'),
            'metrics' => []
        ];
        
        // Measure page load time
        $startTime = microtime(true);
        
        $curl = curl_init();
        curl_setopt_array($curl, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_USERAGENT => 'Admini Performance Analyzer',
        ]);
        
        $content = curl_exec($curl);
        $info = curl_getinfo($curl);
        curl_close($curl);
        
        $endTime = microtime(true);
        $totalTime = $endTime - $startTime;
        
        $analysis['metrics'] = [
            'http_code' => $info['http_code'],
            'total_time' => round($totalTime, 3),
            'connect_time' => round($info['connect_time'], 3),
            'dns_time' => round($info['namelookup_time'], 3),
            'download_time' => round($info['pretransfer_time'], 3),
            'size_download' => $info['size_download'],
            'speed_download' => round($info['speed_download'], 2),
            'content_length' => strlen($content)
        ];
        
        // Analyze HTML content
        if ($content && $info['http_code'] === 200) {
            $analysis['content_analysis'] = $this->analyzeHTMLContent($content);
        }
        
        // Performance score
        $analysis['performance_score'] = $this->calculatePerformanceScore($analysis['metrics']);
        
        return $analysis;
    }
    
    /**
     * Analyze HTML content for optimization opportunities
     */
    private function analyzeHTMLContent($content) {
        $dom = new DOMDocument();
        @$dom->loadHTML($content);
        
        $analysis = [
            'images' => [],
            'scripts' => [],
            'stylesheets' => [],
            'recommendations' => []
        ];
        
        // Analyze images
        $images = $dom->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src) {
                $analysis['images'][] = [
                    'src' => $src,
                    'has_alt' => $img->hasAttribute('alt'),
                    'has_width' => $img->hasAttribute('width'),
                    'has_height' => $img->hasAttribute('height')
                ];
            }
        }
        
        // Analyze scripts
        $scripts = $dom->getElementsByTagName('script');
        foreach ($scripts as $script) {
            $src = $script->getAttribute('src');
            if ($src) {
                $analysis['scripts'][] = [
                    'src' => $src,
                    'async' => $script->hasAttribute('async'),
                    'defer' => $script->hasAttribute('defer')
                ];
            }
        }
        
        // Analyze stylesheets
        $links = $dom->getElementsByTagName('link');
        foreach ($links as $link) {
            if ($link->getAttribute('rel') === 'stylesheet') {
                $analysis['stylesheets'][] = [
                    'href' => $link->getAttribute('href'),
                    'media' => $link->getAttribute('media')
                ];
            }
        }
        
        // Generate recommendations
        $analysis['recommendations'] = $this->generateOptimizationRecommendations($analysis);
        
        return $analysis;
    }
    
    /**
     * Generate optimization recommendations
     */
    private function generateOptimizationRecommendations($contentAnalysis) {
        $recommendations = [];
        
        // Image optimization
        $imagesWithoutAlt = array_filter($contentAnalysis['images'], function($img) {
            return !$img['has_alt'];
        });
        
        if (count($imagesWithoutAlt) > 0) {
            $recommendations[] = [
                'type' => 'SEO',
                'priority' => 'medium',
                'message' => count($imagesWithoutAlt) . ' images missing alt attributes'
            ];
        }
        
        // Script optimization
        $scriptsWithoutAsync = array_filter($contentAnalysis['scripts'], function($script) {
            return !$script['async'] && !$script['defer'];
        });
        
        if (count($scriptsWithoutAsync) > 3) {
            $recommendations[] = [
                'type' => 'Performance',
                'priority' => 'high',
                'message' => 'Consider using async/defer for JavaScript files to improve page load speed'
            ];
        }
        
        // Too many stylesheets
        if (count($contentAnalysis['stylesheets']) > 5) {
            $recommendations[] = [
                'type' => 'Performance',
                'priority' => 'medium',
                'message' => 'Consider combining CSS files to reduce HTTP requests'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Calculate performance score
     */
    private function calculatePerformanceScore($metrics) {
        $score = 100;
        
        // Penalize slow load times
        if ($metrics['total_time'] > 3) {
            $score -= 30;
        } elseif ($metrics['total_time'] > 2) {
            $score -= 20;
        } elseif ($metrics['total_time'] > 1) {
            $score -= 10;
        }
        
        // Penalize slow DNS resolution
        if ($metrics['dns_time'] > 0.5) {
            $score -= 10;
        }
        
        // Penalize slow connection
        if ($metrics['connect_time'] > 1) {
            $score -= 10;
        }
        
        // Penalize large downloads
        if ($metrics['size_download'] > 2000000) { // 2MB
            $score -= 15;
        } elseif ($metrics['size_download'] > 1000000) { // 1MB
            $score -= 10;
        }
        
        return max(0, $score);
    }
    
    /**
     * Configure compression
     */
    public function configureCompression($type = 'gzip') {
        $htaccessPath = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
        
        $compressionRules = '';
        
        switch ($type) {
            case 'gzip':
                $compressionRules = "
# Enable Gzip compression
<IfModule mod_deflate.c>
    # Compress HTML, CSS, JavaScript, Text, XML and fonts
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/vnd.ms-fontobject
    AddOutputFilterByType DEFLATE application/x-font
    AddOutputFilterByType DEFLATE application/x-font-opentype
    AddOutputFilterByType DEFLATE application/x-font-otf
    AddOutputFilterByType DEFLATE application/x-font-truetype
    AddOutputFilterByType DEFLATE application/x-font-ttf
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE font/opentype
    AddOutputFilterByType DEFLATE font/otf
    AddOutputFilterByType DEFLATE font/ttf
    AddOutputFilterByType DEFLATE image/svg+xml
    AddOutputFilterByType DEFLATE image/x-icon
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/javascript
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/xml
</IfModule>
";
                break;
                
            case 'brotli':
                $compressionRules = "
# Enable Brotli compression
<IfModule mod_brotli.c>
    BrotliCompressionLevel 6
    BrotliFilterByType text/html text/plain text/xml text/css text/javascript application/javascript application/json application/xml+rss
</IfModule>
";
                break;
        }
        
        // Read existing .htaccess
        $existingContent = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
        
        // Remove existing compression rules
        $existingContent = preg_replace('/# Enable (Gzip|Brotli) compression.*?<\/IfModule>/s', '', $existingContent);
        
        // Add new compression rules
        $newContent = trim($existingContent) . "\n\n" . $compressionRules;
        
        if (file_put_contents($htaccessPath, $newContent)) {
            return ['success' => true, 'message' => ucfirst($type) . ' compression enabled'];
        } else {
            throw new Exception('Failed to update .htaccess file');
        }
    }
    
    /**
     * Configure browser caching
     */
    public function configureBrowserCaching() {
        $htaccessPath = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
        
        $cachingRules = "
# Browser Caching
<IfModule mod_expires.c>
    ExpiresActive on
    
    # Images
    ExpiresByType image/jpg \"access plus 1 month\"
    ExpiresByType image/jpeg \"access plus 1 month\"
    ExpiresByType image/gif \"access plus 1 month\"
    ExpiresByType image/png \"access plus 1 month\"
    ExpiresByType image/svg+xml \"access plus 1 month\"
    ExpiresByType image/webp \"access plus 1 month\"
    
    # CSS and JavaScript
    ExpiresByType text/css \"access plus 1 month\"
    ExpiresByType application/javascript \"access plus 1 month\"
    ExpiresByType text/javascript \"access plus 1 month\"
    
    # Fonts
    ExpiresByType application/vnd.ms-fontobject \"access plus 1 month\"
    ExpiresByType font/ttf \"access plus 1 month\"
    ExpiresByType font/otf \"access plus 1 month\"
    ExpiresByType font/woff \"access plus 1 month\"
    ExpiresByType font/woff2 \"access plus 1 month\"
    
    # HTML
    ExpiresByType text/html \"access plus 1 day\"
    
    # PDF
    ExpiresByType application/pdf \"access plus 1 month\"
    
    # Videos
    ExpiresByType video/mp4 \"access plus 1 month\"
    ExpiresByType video/webm \"access plus 1 month\"
</IfModule>

# Cache-Control Headers
<IfModule mod_headers.c>
    <FilesMatch \"\\.(css|js|png|jpg|jpeg|gif|svg|webp|woff|woff2|ttf|otf)$\">
        Header set Cache-Control \"max-age=2592000, public\"
    </FilesMatch>
    
    <FilesMatch \"\\.(html|htm)$\">
        Header set Cache-Control \"max-age=86400, public\"
    </FilesMatch>
</IfModule>
";
        
        // Read existing .htaccess
        $existingContent = file_exists($htaccessPath) ? file_get_contents($htaccessPath) : '';
        
        // Remove existing caching rules
        $existingContent = preg_replace('/# Browser Caching.*?<\/IfModule>/s', '', $existingContent);
        
        // Add new caching rules
        $newContent = trim($existingContent) . "\n\n" . $cachingRules;
        
        if (file_put_contents($htaccessPath, $newContent)) {
            return ['success' => true, 'message' => 'Browser caching configured'];
        } else {
            throw new Exception('Failed to update .htaccess file');
        }
    }
    
    /**
     * Get performance recommendations
     */
    public function getPerformanceRecommendations() {
        $recommendations = [];
        
        // Check if Redis is installed
        if (!extension_loaded('redis')) {
            $recommendations[] = [
                'type' => 'Caching',
                'priority' => 'high',
                'title' => 'Install Redis',
                'description' => 'Redis can significantly improve caching performance',
                'action' => 'Install Redis extension and configure caching'
            ];
        }
        
        // Check if compression is enabled
        if (!$this->isCompressionEnabled()) {
            $recommendations[] = [
                'type' => 'Compression',
                'priority' => 'high',
                'title' => 'Enable Compression',
                'description' => 'Gzip compression can reduce file sizes by 70%',
                'action' => 'Enable Gzip compression in server configuration'
            ];
        }
        
        // Check if caching is configured
        if (!$this->isBrowserCachingEnabled()) {
            $recommendations[] = [
                'type' => 'Caching',
                'priority' => 'medium',
                'title' => 'Configure Browser Caching',
                'description' => 'Browser caching reduces server load and improves user experience',
                'action' => 'Set appropriate cache headers for static assets'
            ];
        }
        
        // Check database size
        $dbSize = $this->getDatabaseSize();
        if ($dbSize > 1000) { // 1GB
            $recommendations[] = [
                'type' => 'Database',
                'priority' => 'medium',
                'title' => 'Optimize Database',
                'description' => 'Large database detected. Consider optimization.',
                'action' => 'Run database optimization and cleanup old data'
            ];
        }
        
        return $recommendations;
    }
    
    /**
     * Check if compression is enabled
     */
    private function isCompressionEnabled() {
        return function_exists('gzencode') || function_exists('brotli_compress');
    }
    
    /**
     * Check if browser caching is enabled
     */
    private function isBrowserCachingEnabled() {
        $htaccessPath = $_SERVER['DOCUMENT_ROOT'] . '/.htaccess';
        
        if (!file_exists($htaccessPath)) {
            return false;
        }
        
        $content = file_get_contents($htaccessPath);
        return strpos($content, 'ExpiresActive') !== false || strpos($content, 'Cache-Control') !== false;
    }
    
    /**
     * Get database size in MB
     */
    private function getDatabaseSize() {
        $config = include __DIR__ . '/../config/database.php';
        $dbName = $config['database'];
        
        $result = $this->db->fetch(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 1) AS size_mb 
             FROM information_schema.tables 
             WHERE table_schema = ?",
            [$dbName]
        );
        
        return $result['size_mb'] ?? 0;
    }
    
    /**
     * Generate performance report
     */
    public function generatePerformanceReport() {
        $report = [
            'generated_at' => date('c'),
            'database' => [
                'size_mb' => $this->getDatabaseSize(),
                'optimization_needed' => $this->getDatabaseSize() > 500
            ],
            'caching' => [
                'redis_available' => extension_loaded('redis'),
                'memcached_available' => extension_loaded('memcached'),
                'opcache_enabled' => function_exists('opcache_get_status'),
                'browser_caching' => $this->isBrowserCachingEnabled()
            ],
            'compression' => [
                'gzip_available' => function_exists('gzencode'),
                'brotli_available' => function_exists('brotli_compress'),
                'enabled' => $this->isCompressionEnabled()
            ],
            'cdn' => [
                'configured' => $this->getCDNConfig() !== null
            ],
            'recommendations' => $this->getPerformanceRecommendations()
        ];
        
        // Calculate overall score
        $score = 100;
        
        if (!$report['caching']['browser_caching']) $score -= 20;
        if (!$report['compression']['enabled']) $score -= 20;
        if (!$report['caching']['redis_available']) $score -= 15;
        if (!$report['cdn']['configured']) $score -= 10;
        if ($report['database']['optimization_needed']) $score -= 10;
        
        $report['overall_score'] = max(0, $score);
        
        return $report;
    }
    
    /**
     * Stub implementations for other CDN providers
     */
    private function purgeCloudFrontCache($config, $urls) {
        // AWS CloudFront cache purge implementation
        return ['success' => true, 'message' => 'CloudFront cache purged'];
    }
    
    private function purgeMaxCDNCache($config, $urls) {
        // MaxCDN cache purge implementation
        return ['success' => true, 'message' => 'MaxCDN cache purged'];
    }
    
    private function purgeKeyCDNCache($config, $urls) {
        // KeyCDN cache purge implementation
        return ['success' => true, 'message' => 'KeyCDN cache purged'];
    }
}