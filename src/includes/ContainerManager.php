<?php
/**
 * Container and Application Runtime Manager
 * Supports Node.js, Python, Docker containers
 */

class ContainerManager {
    private $db;
    private $containersPath;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->containersPath = '/opt/admini/containers';
        
        // Ensure containers directory exists
        if (!is_dir($this->containersPath)) {
            mkdir($this->containersPath, 0755, true);
        }
    }
    
    /**
     * Create Node.js application
     */
    public function createNodeJSApp($userId, $appName, $domainId, $nodeVersion = '18', $options = []) {
        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        
        if (!$domain) {
            throw new Exception("Domain not found");
        }
        
        $appPath = $this->containersPath . "/nodejs/{$userId}/{$appName}";
        $port = $this->getAvailablePort();
        
        // Create application directory
        if (!mkdir($appPath, 0755, true)) {
            throw new Exception("Failed to create application directory");
        }
        
        // Create package.json
        $packageJson = [
            'name' => $appName,
            'version' => '1.0.0',
            'description' => $options['description'] ?? 'Node.js application managed by Admini',
            'main' => 'app.js',
            'scripts' => [
                'start' => 'node app.js',
                'dev' => 'nodemon app.js',
                'test' => 'echo "Error: no test specified" && exit 1'
            ],
            'dependencies' => $options['dependencies'] ?? [
                'express' => '^4.18.0'
            ],
            'engines' => [
                'node' => ">= {$nodeVersion}"
            ]
        ];
        
        file_put_contents($appPath . '/package.json', json_encode($packageJson, JSON_PRETTY_PRINT));
        
        // Create basic app.js
        $appJs = $this->generateNodeJSStarter($port, $options);
        file_put_contents($appPath . '/app.js', $appJs);
        
        // Create ecosystem file for PM2
        $ecosystem = $this->generatePM2Config($appName, $appPath, $port, $nodeVersion);
        file_put_contents($appPath . '/ecosystem.config.js', $ecosystem);
        
        // Create Dockerfile
        $dockerfile = $this->generateNodeJSDockerfile($nodeVersion);
        file_put_contents($appPath . '/Dockerfile', $dockerfile);
        
        // Create nginx configuration
        $nginxConfig = $this->generateNginxConfig($domain['domain_name'], $port, $options['ssl'] ?? false);
        $nginxConfigPath = "/etc/nginx/sites-available/{$domain['domain_name']}-nodejs";
        file_put_contents($nginxConfigPath, $nginxConfig);
        
        // Enable site
        symlink($nginxConfigPath, "/etc/nginx/sites-enabled/{$domain['domain_name']}-nodejs");
        
        // Install dependencies
        $this->runCommand("cd {$appPath} && npm install", $appPath);
        
        // Create database record
        $appId = $this->db->query(
            "INSERT INTO user_applications (user_id, application_id, domain_id, install_path, app_url, version, status) 
             VALUES (?, NULL, ?, ?, ?, ?, 'active')",
            [$userId, $domainId, $appName, "http://{$domain['domain_name']}:{$port}", $nodeVersion]
        )->lastInsertId();
        
        // Add container record
        $this->db->query(
            "INSERT INTO containers (user_id, app_id, container_type, container_name, port, status, config) 
             VALUES (?, ?, 'nodejs', ?, ?, 'stopped', ?)",
            [$userId, $appId, $appName, $port, json_encode([
                'node_version' => $nodeVersion,
                'path' => $appPath,
                'domain' => $domain['domain_name']
            ])]
        );
        
        return [
            'app_id' => $appId,
            'app_name' => $appName,
            'path' => $appPath,
            'port' => $port,
            'url' => "http://{$domain['domain_name']}:{$port}",
            'status' => 'created'
        ];
    }
    
    /**
     * Create Python application
     */
    public function createPythonApp($userId, $appName, $domainId, $pythonVersion = '3.11', $framework = 'flask', $options = []) {
        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        
        if (!$domain) {
            throw new Exception("Domain not found");
        }
        
        $appPath = $this->containersPath . "/python/{$userId}/{$appName}";
        $port = $this->getAvailablePort();
        
        // Create application directory
        if (!mkdir($appPath, 0755, true)) {
            throw new Exception("Failed to create application directory");
        }
        
        // Create requirements.txt
        $requirements = $this->generatePythonRequirements($framework, $options);
        file_put_contents($appPath . '/requirements.txt', $requirements);
        
        // Create main application file
        $appContent = $this->generatePythonStarter($framework, $port, $options);
        $mainFile = $framework === 'django' ? 'manage.py' : 'app.py';
        file_put_contents($appPath . '/' . $mainFile, $appContent);
        
        // Create gunicorn configuration
        if ($framework !== 'django') {
            $gunicornConfig = $this->generateGunicornConfig($port);
            file_put_contents($appPath . '/gunicorn.conf.py', $gunicornConfig);
        }
        
        // Create Dockerfile
        $dockerfile = $this->generatePythonDockerfile($pythonVersion, $framework);
        file_put_contents($appPath . '/Dockerfile', $dockerfile);
        
        // Create virtual environment
        $this->runCommand("python{$pythonVersion} -m venv venv", $appPath);
        
        // Install dependencies
        $this->runCommand("source venv/bin/activate && pip install -r requirements.txt", $appPath);
        
        // Create nginx configuration
        $nginxConfig = $this->generateNginxConfig($domain['domain_name'], $port, $options['ssl'] ?? false);
        $nginxConfigPath = "/etc/nginx/sites-available/{$domain['domain_name']}-python";
        file_put_contents($nginxConfigPath, $nginxConfig);
        
        // Enable site
        symlink($nginxConfigPath, "/etc/nginx/sites-enabled/{$domain['domain_name']}-python");
        
        // Create database record
        $appId = $this->db->query(
            "INSERT INTO user_applications (user_id, application_id, domain_id, install_path, app_url, version, status) 
             VALUES (?, NULL, ?, ?, ?, ?, 'active')",
            [$userId, $domainId, $appName, "http://{$domain['domain_name']}:{$port}", $pythonVersion]
        )->lastInsertId();
        
        // Add container record
        $this->db->query(
            "INSERT INTO containers (user_id, app_id, container_type, container_name, port, status, config) 
             VALUES (?, ?, 'python', ?, ?, 'stopped', ?)",
            [$userId, $appId, $appName, $port, json_encode([
                'python_version' => $pythonVersion,
                'framework' => $framework,
                'path' => $appPath,
                'domain' => $domain['domain_name']
            ])]
        );
        
        return [
            'app_id' => $appId,
            'app_name' => $appName,
            'path' => $appPath,
            'port' => $port,
            'url' => "http://{$domain['domain_name']}:{$port}",
            'framework' => $framework,
            'status' => 'created'
        ];
    }
    
    /**
     * Deploy Docker container
     */
    public function deployDockerContainer($userId, $containerName, $domainId, $imageConfig) {
        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        
        if (!$domain) {
            throw new Exception("Domain not found");
        }
        
        $port = $this->getAvailablePort();
        $containerPath = $this->containersPath . "/docker/{$userId}/{$containerName}";
        
        // Create container directory
        if (!mkdir($containerPath, 0755, true)) {
            throw new Exception("Failed to create container directory");
        }
        
        // Create docker-compose.yml
        $dockerCompose = $this->generateDockerCompose($containerName, $imageConfig, $port);
        file_put_contents($containerPath . '/docker-compose.yml', $dockerCompose);
        
        // Create environment file if needed
        if (!empty($imageConfig['environment'])) {
            $envContent = '';
            foreach ($imageConfig['environment'] as $key => $value) {
                $envContent .= "{$key}={$value}\n";
            }
            file_put_contents($containerPath . '/.env', $envContent);
        }
        
        // Create nginx configuration
        $nginxConfig = $this->generateNginxConfig($domain['domain_name'], $port, $imageConfig['ssl'] ?? false);
        $nginxConfigPath = "/etc/nginx/sites-available/{$domain['domain_name']}-docker";
        file_put_contents($nginxConfigPath, $nginxConfig);
        
        // Enable site
        symlink($nginxConfigPath, "/etc/nginx/sites-enabled/{$domain['domain_name']}-docker");
        
        // Create database record
        $appId = $this->db->query(
            "INSERT INTO user_applications (user_id, application_id, domain_id, install_path, app_url, version, status) 
             VALUES (?, NULL, ?, ?, ?, ?, 'active')",
            [$userId, $domainId, $containerName, "http://{$domain['domain_name']}:{$port}", $imageConfig['tag'] ?? 'latest']
        )->lastInsertId();
        
        // Add container record
        $this->db->query(
            "INSERT INTO containers (user_id, app_id, container_type, container_name, port, status, config) 
             VALUES (?, ?, 'docker', ?, ?, 'stopped', ?)",
            [$userId, $appId, $containerName, $port, json_encode([
                'image' => $imageConfig['image'],
                'tag' => $imageConfig['tag'] ?? 'latest',
                'path' => $containerPath,
                'domain' => $domain['domain_name'],
                'environment' => $imageConfig['environment'] ?? []
            ])]
        );
        
        return [
            'app_id' => $appId,
            'container_name' => $containerName,
            'path' => $containerPath,
            'port' => $port,
            'url' => "http://{$domain['domain_name']}:{$port}",
            'image' => $imageConfig['image'],
            'status' => 'created'
        ];
    }
    
    /**
     * Start container
     */
    public function startContainer($containerId) {
        $container = $this->db->fetch("SELECT * FROM containers WHERE id = ?", [$containerId]);
        
        if (!$container) {
            throw new Exception("Container not found");
        }
        
        $config = json_decode($container['config'], true);
        
        switch ($container['container_type']) {
            case 'nodejs':
                return $this->startNodeJSApp($container, $config);
            case 'python':
                return $this->startPythonApp($container, $config);
            case 'docker':
                return $this->startDockerContainer($container, $config);
            default:
                throw new Exception("Unsupported container type");
        }
    }
    
    /**
     * Stop container
     */
    public function stopContainer($containerId) {
        $container = $this->db->fetch("SELECT * FROM containers WHERE id = ?", [$containerId]);
        
        if (!$container) {
            throw new Exception("Container not found");
        }
        
        switch ($container['container_type']) {
            case 'nodejs':
                return $this->stopNodeJSApp($container);
            case 'python':
                return $this->stopPythonApp($container);
            case 'docker':
                return $this->stopDockerContainer($container);
            default:
                throw new Exception("Unsupported container type");
        }
    }
    
    /**
     * Start Node.js application
     */
    private function startNodeJSApp($container, $config) {
        $appPath = $config['path'];
        
        // Start with PM2
        $output = $this->runCommand("cd {$appPath} && pm2 start ecosystem.config.js", $appPath);
        
        // Update status
        $this->db->query("UPDATE containers SET status = 'running' WHERE id = ?", [$container['id']]);
        
        // Reload nginx
        $this->runCommand("nginx -t && nginx -s reload");
        
        return ['success' => true, 'message' => 'Node.js application started', 'output' => $output];
    }
    
    /**
     * Start Python application
     */
    private function startPythonApp($container, $config) {
        $appPath = $config['path'];
        $framework = $config['framework'];
        
        if ($framework === 'django') {
            // Start Django development server
            $command = "cd {$appPath} && source venv/bin/activate && python manage.py runserver 0.0.0.0:{$container['port']} &";
        } else {
            // Start with Gunicorn
            $command = "cd {$appPath} && source venv/bin/activate && gunicorn -c gunicorn.conf.py app:app &";
        }
        
        $output = $this->runCommand($command, $appPath);
        
        // Update status
        $this->db->query("UPDATE containers SET status = 'running' WHERE id = ?", [$container['id']]);
        
        // Reload nginx
        $this->runCommand("nginx -t && nginx -s reload");
        
        return ['success' => true, 'message' => 'Python application started', 'output' => $output];
    }
    
    /**
     * Start Docker container
     */
    private function startDockerContainer($container, $config) {
        $containerPath = $config['path'];
        
        // Start with docker-compose
        $output = $this->runCommand("cd {$containerPath} && docker-compose up -d", $containerPath);
        
        // Update status
        $this->db->query("UPDATE containers SET status = 'running' WHERE id = ?", [$container['id']]);
        
        // Reload nginx
        $this->runCommand("nginx -t && nginx -s reload");
        
        return ['success' => true, 'message' => 'Docker container started', 'output' => $output];
    }
    
    /**
     * Stop Node.js application
     */
    private function stopNodeJSApp($container) {
        $output = $this->runCommand("pm2 stop {$container['container_name']}");
        
        $this->db->query("UPDATE containers SET status = 'stopped' WHERE id = ?", [$container['id']]);
        
        return ['success' => true, 'message' => 'Node.js application stopped', 'output' => $output];
    }
    
    /**
     * Stop Python application
     */
    private function stopPythonApp($container) {
        // Kill processes running on the port
        $port = $container['port'];
        $output = $this->runCommand("pkill -f \":{$port}\"");
        
        $this->db->query("UPDATE containers SET status = 'stopped' WHERE id = ?", [$container['id']]);
        
        return ['success' => true, 'message' => 'Python application stopped', 'output' => $output];
    }
    
    /**
     * Stop Docker container
     */
    private function stopDockerContainer($container) {
        $config = json_decode($container['config'], true);
        $containerPath = $config['path'];
        
        $output = $this->runCommand("cd {$containerPath} && docker-compose down", $containerPath);
        
        $this->db->query("UPDATE containers SET status = 'stopped' WHERE id = ?", [$container['id']]);
        
        return ['success' => true, 'message' => 'Docker container stopped', 'output' => $output];
    }
    
    /**
     * Get container logs
     */
    public function getContainerLogs($containerId, $lines = 100) {
        $container = $this->db->fetch("SELECT * FROM containers WHERE id = ?", [$containerId]);
        
        if (!$container) {
            throw new Exception("Container not found");
        }
        
        switch ($container['container_type']) {
            case 'nodejs':
                return $this->runCommand("pm2 logs {$container['container_name']} --lines {$lines}");
            case 'python':
                // For Python apps, return system logs
                return $this->runCommand("journalctl -u python-{$container['container_name']} -n {$lines}");
            case 'docker':
                $config = json_decode($container['config'], true);
                return $this->runCommand("cd {$config['path']} && docker-compose logs --tail={$lines}");
            default:
                throw new Exception("Unsupported container type");
        }
    }
    
    /**
     * Get available port
     */
    private function getAvailablePort($startPort = 3000) {
        for ($port = $startPort; $port <= 65535; $port++) {
            $connection = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if (!$connection) {
                return $port;
            }
            fclose($connection);
        }
        
        throw new Exception("No available ports found");
    }
    
    /**
     * Run command with proper error handling
     */
    private function runCommand($command, $workingDir = null) {
        if ($workingDir) {
            $command = "cd {$workingDir} && {$command}";
        }
        
        $output = shell_exec($command . ' 2>&1');
        
        // Log command execution
        error_log("Container command executed: {$command}");
        if ($output) {
            error_log("Command output: {$output}");
        }
        
        return $output;
    }
    
    /**
     * Generate Node.js starter code
     */
    private function generateNodeJSStarter($port, $options) {
        $framework = $options['framework'] ?? 'express';
        
        if ($framework === 'express') {
            return "const express = require('express');
const app = express();
const port = {$port};

// Middleware
app.use(express.json());
app.use(express.urlencoded({ extended: true }));

// Routes
app.get('/', (req, res) => {
    res.json({
        message: 'Hello from Node.js application!',
        timestamp: new Date().toISOString(),
        port: port
    });
});

app.get('/health', (req, res) => {
    res.json({ status: 'healthy', uptime: process.uptime() });
});

// Start server
app.listen(port, '0.0.0.0', () => {
    console.log(`Server running on port \${port}`);
});

module.exports = app;";
        }
        
        return "// Basic Node.js server
const http = require('http');

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
        message: 'Hello from Node.js!',
        timestamp: new Date().toISOString(),
        url: req.url
    }));
});

server.listen({$port}, '0.0.0.0', () => {
    console.log(`Server running on port {$port}`);
});";
    }
    
    /**
     * Generate Python starter code
     */
    private function generatePythonStarter($framework, $port, $options) {
        switch ($framework) {
            case 'flask':
                return "from flask import Flask, jsonify
import datetime

app = Flask(__name__)

@app.route('/')
def hello():
    return jsonify({
        'message': 'Hello from Flask application!',
        'timestamp': datetime.datetime.now().isoformat(),
        'port': {$port}
    })

@app.route('/health')
def health():
    return jsonify({'status': 'healthy'})

if __name__ == '__main__':
    app.run(host='0.0.0.0', port={$port}, debug=True)";
    
            case 'fastapi':
                return "from fastapi import FastAPI
from datetime import datetime

app = FastAPI(title='FastAPI Application')

@app.get('/')
async def read_root():
    return {
        'message': 'Hello from FastAPI application!',
        'timestamp': datetime.now().isoformat(),
        'port': {$port}
    }

@app.get('/health')
async def health_check():
    return {'status': 'healthy'}

if __name__ == '__main__':
    import uvicorn
    uvicorn.run(app, host='0.0.0.0', port={$port})";
    
            case 'django':
                return "#!/usr/bin/env python
import os
import sys

if __name__ == '__main__':
    os.environ.setdefault('DJANGO_SETTINGS_MODULE', 'settings')
    try:
        from django.core.management import execute_from_command_line
    except ImportError as exc:
        raise ImportError(
            \"Couldn't import Django. Are you sure it's installed and \"
            \"available on your PYTHONPATH environment variable? Did you \"
            \"forget to activate a virtual environment?\"
        ) from exc
    execute_from_command_line(sys.argv)";
    
            default:
                return "# Basic Python HTTP server
import http.server
import socketserver
import json
from datetime import datetime

class CustomHandler(http.server.SimpleHTTPRequestHandler):
    def do_GET(self):
        if self.path == '/':
            self.send_response(200)
            self.send_header('Content-type', 'application/json')
            self.end_headers()
            response = {
                'message': 'Hello from Python application!',
                'timestamp': datetime.now().isoformat(),
                'port': {$port}
            }
            self.wfile.write(json.dumps(response).encode())
        else:
            super().do_GET()

with socketserver.TCPServer(('0.0.0.0', {$port}), CustomHandler) as httpd:
    print(f'Serving on port {$port}')
    httpd.serve_forever()";
        }
    }
    
    /**
     * Generate PM2 ecosystem configuration
     */
    private function generatePM2Config($appName, $appPath, $port, $nodeVersion) {
        return "module.exports = {
    apps: [{
        name: '{$appName}',
        script: 'app.js',
        cwd: '{$appPath}',
        instances: 1,
        exec_mode: 'fork',
        watch: false,
        max_memory_restart: '1G',
        env: {
            NODE_ENV: 'production',
            PORT: {$port}
        },
        env_development: {
            NODE_ENV: 'development',
            PORT: {$port}
        },
        log_file: '{$appPath}/logs/combined.log',
        out_file: '{$appPath}/logs/out.log',
        error_file: '{$appPath}/logs/error.log',
        time: true
    }]
};";
    }
    
    /**
     * Generate Python requirements
     */
    private function generatePythonRequirements($framework, $options) {
        $requirements = [];
        
        switch ($framework) {
            case 'flask':
                $requirements = [
                    'Flask==2.3.3',
                    'gunicorn==21.2.0'
                ];
                break;
            case 'fastapi':
                $requirements = [
                    'fastapi==0.104.1',
                    'uvicorn==0.24.0'
                ];
                break;
            case 'django':
                $requirements = [
                    'Django==4.2.7',
                    'gunicorn==21.2.0'
                ];
                break;
            default:
                $requirements = ['requests==2.31.0'];
        }
        
        if (!empty($options['additional_packages'])) {
            $requirements = array_merge($requirements, $options['additional_packages']);
        }
        
        return implode("\n", $requirements);
    }
    
    /**
     * Generate Gunicorn configuration
     */
    private function generateGunicornConfig($port) {
        return "import multiprocessing

bind = '0.0.0.0:{$port}'
workers = multiprocessing.cpu_count() * 2 + 1
worker_class = 'sync'
worker_connections = 1000
max_requests = 1000
max_requests_jitter = 100
preload_app = True
timeout = 30
keepalive = 2

# Logging
accesslog = '-'
errorlog = '-'
loglevel = 'info'

# Process naming
proc_name = 'gunicorn_app'

# Server mechanics
daemon = False
pidfile = '/tmp/gunicorn.pid'
user = 'www-data'
group = 'www-data'";
    }
    
    /**
     * Generate Dockerfile for Node.js
     */
    private function generateNodeJSDockerfile($nodeVersion) {
        return "FROM node:{$nodeVersion}-alpine

WORKDIR /app

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --only=production

# Copy application code
COPY . .

# Create non-root user
RUN addgroup -g 1001 -S nodejs
RUN adduser -S nextjs -u 1001

# Change ownership
RUN chown -R nextjs:nodejs /app
USER nextjs

EXPOSE 3000

CMD [\"npm\", \"start\"]";
    }
    
    /**
     * Generate Dockerfile for Python
     */
    private function generatePythonDockerfile($pythonVersion, $framework) {
        $baseImage = "python:{$pythonVersion}-slim";
        
        return "FROM {$baseImage}

WORKDIR /app

# Install system dependencies
RUN apt-get update && apt-get install -y \\
    build-essential \\
    && rm -rf /var/lib/apt/lists/*

# Copy requirements
COPY requirements.txt .

# Install Python dependencies
RUN pip install --no-cache-dir -r requirements.txt

# Copy application code
COPY . .

# Create non-root user
RUN groupadd -r appuser && useradd -r -g appuser appuser
RUN chown -R appuser:appuser /app
USER appuser

EXPOSE 8000

CMD [\"gunicorn\", \"--bind\", \"0.0.0.0:8000\", \"app:app\"]";
    }
    
    /**
     * Generate docker-compose.yml
     */
    private function generateDockerCompose($containerName, $imageConfig, $port) {
        $compose = "version: '3.8'

services:
  {$containerName}:
    image: {$imageConfig['image']}";
    
        if (!empty($imageConfig['tag'])) {
            $compose .= ":{$imageConfig['tag']}";
        }
        
        $compose .= "
    container_name: {$containerName}
    restart: unless-stopped
    ports:
      - \"{$port}:{$imageConfig['internal_port'] ?? 80}\"";
      
        if (!empty($imageConfig['environment'])) {
            $compose .= "
    environment:";
            foreach ($imageConfig['environment'] as $key => $value) {
                $compose .= "
      - {$key}={$value}";
            }
        }
        
        if (!empty($imageConfig['volumes'])) {
            $compose .= "
    volumes:";
            foreach ($imageConfig['volumes'] as $volume) {
                $compose .= "
      - {$volume}";
            }
        }
        
        return $compose;
    }
    
    /**
     * Generate Nginx configuration
     */
    private function generateNginxConfig($domain, $port, $ssl = false) {
        $config = "server {
    listen 80;
    server_name {$domain};";
    
        if ($ssl) {
            $config .= "
    listen 443 ssl http2;
    ssl_certificate /etc/ssl/certs/{$domain}.crt;
    ssl_certificate_key /etc/ssl/private/{$domain}.key;";
        }
        
        $config .= "
    
    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;
        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection 'upgrade';
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;
        proxy_cache_bypass \$http_upgrade;
        proxy_read_timeout 300;
        proxy_connect_timeout 300;
        proxy_send_timeout 300;
    }
}";
        
        return $config;
    }
    
    /**
     * Get user containers
     */
    public function getUserContainers($userId) {
        return $this->db->fetchAll(
            "SELECT c.*, ua.app_url, d.domain_name 
             FROM containers c 
             LEFT JOIN user_applications ua ON c.app_id = ua.id 
             LEFT JOIN domains d ON ua.domain_id = d.id 
             WHERE c.user_id = ? 
             ORDER BY c.created_at DESC",
            [$userId]
        );
    }
    
    /**
     * Delete container
     */
    public function deleteContainer($containerId, $userId) {
        $container = $this->db->fetch(
            "SELECT * FROM containers WHERE id = ? AND user_id = ?",
            [$containerId, $userId]
        );
        
        if (!$container) {
            throw new Exception("Container not found");
        }
        
        // Stop container first
        if ($container['status'] === 'running') {
            $this->stopContainer($containerId);
        }
        
        $config = json_decode($container['config'], true);
        
        // Remove files
        if (isset($config['path']) && is_dir($config['path'])) {
            $this->runCommand("rm -rf {$config['path']}");
        }
        
        // Remove nginx configuration
        $domain = $config['domain'];
        $nginxConfig = "/etc/nginx/sites-available/{$domain}-{$container['container_type']}";
        $nginxEnabled = "/etc/nginx/sites-enabled/{$domain}-{$container['container_type']}";
        
        if (file_exists($nginxEnabled)) {
            unlink($nginxEnabled);
        }
        if (file_exists($nginxConfig)) {
            unlink($nginxConfig);
        }
        
        // Reload nginx
        $this->runCommand("nginx -t && nginx -s reload");
        
        // Remove database records
        $this->db->query("DELETE FROM containers WHERE id = ?", [$containerId]);
        $this->db->query("DELETE FROM user_applications WHERE id = ?", [$container['app_id']]);
        
        return true;
    }
}