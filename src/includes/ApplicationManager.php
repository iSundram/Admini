<?php
/**
 * Application Installer and Management System
 */

class ApplicationManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Get available applications
     */
    public function getAvailableApplications($category = null) {
        $whereClause = $category ? "WHERE category = ? AND status = 'active'" : "WHERE status = 'active'";
        $params = $category ? [$category] : [];
        
        return $this->db->fetchAll(
            "SELECT * FROM applications {$whereClause} ORDER BY app_name ASC",
            $params
        );
    }
    
    /**
     * Get application categories
     */
    public function getCategories() {
        $categories = $this->db->fetchAll(
            "SELECT DISTINCT category FROM applications WHERE status = 'active' ORDER BY category"
        );
        
        return array_column($categories, 'category');
    }
    
    /**
     * Install application
     */
    public function installApplication($userId, $applicationId, $domainId, $installPath = '', $options = []) {
        $application = $this->db->fetch("SELECT * FROM applications WHERE id = ?", [$applicationId]);
        $domain = $this->db->fetch("SELECT * FROM domains WHERE id = ? AND user_id = ?", [$domainId, $userId]);
        
        if (!$application || !$domain) {
            throw new Exception("Application or domain not found");
        }
        
        // Check requirements
        $this->checkRequirements($application);
        
        // Create installation record
        $installationId = $this->db->query(
            "INSERT INTO user_applications (user_id, application_id, domain_id, install_path, status) 
             VALUES (?, ?, ?, ?, 'installing')",
            [$userId, $applicationId, $domainId, $installPath]
        )->lastInsertId();
        
        try {
            // Prepare installation environment
            $fullInstallPath = $this->prepareInstallPath($domain, $installPath);
            
            // Download and extract application
            $this->downloadApplication($application, $fullInstallPath);
            
            // Create database if needed
            $databaseInfo = null;
            if ($this->requiresDatabase($application)) {
                $databaseInfo = $this->createApplicationDatabase($userId, $application, $options);
            }
            
            // Run installation script
            $installResult = $this->runInstallationScript($application, $fullInstallPath, $databaseInfo, $options);
            
            // Update installation record
            $this->db->query(
                "UPDATE user_applications SET 
                 app_url = ?, admin_username = ?, admin_password = ?, database_name = ?, 
                 version = ?, status = 'active' 
                 WHERE id = ?",
                [
                    $installResult['app_url'],
                    $installResult['admin_username'] ?? null,
                    $installResult['admin_password'] ?? null,
                    $databaseInfo['database_name'] ?? null,
                    $application['app_version'],
                    $installationId
                ]
            );
            
            return [
                'installation_id' => $installationId,
                'status' => 'success',
                'app_url' => $installResult['app_url'],
                'admin_username' => $installResult['admin_username'] ?? null,
                'admin_password' => $installResult['admin_password'] ?? null,
                'database_name' => $databaseInfo['database_name'] ?? null
            ];
            
        } catch (Exception $e) {
            // Mark installation as failed
            $this->db->query(
                "UPDATE user_applications SET status = 'failed' WHERE id = ?",
                [$installationId]
            );
            
            // Clean up partial installation
            $this->cleanupFailedInstallation($fullInstallPath ?? null, $databaseInfo ?? null);
            
            throw $e;
        }
    }
    
    /**
     * Check application requirements
     */
    private function checkRequirements($application) {
        $requirements = json_decode($application['requirements'], true);
        
        // Check PHP version
        if (isset($requirements['php'])) {
            $requiredVersion = str_replace('+', '', $requirements['php']);
            if (version_compare(PHP_VERSION, $requiredVersion, '<')) {
                throw new Exception("PHP version {$requiredVersion} or higher required");
            }
        }
        
        // Check PHP extensions
        if (isset($requirements['extensions'])) {
            foreach ($requirements['extensions'] as $extension) {
                if (!extension_loaded($extension)) {
                    throw new Exception("PHP extension '{$extension}' is required");
                }
            }
        }
        
        // Check MySQL version (simplified)
        if (isset($requirements['mysql'])) {
            $mysqlVersion = $this->getMySQLVersion();
            $requiredVersion = str_replace('+', '', $requirements['mysql']);
            
            if (version_compare($mysqlVersion, $requiredVersion, '<')) {
                throw new Exception("MySQL version {$requiredVersion} or higher required");
            }
        }
    }
    
    /**
     * Get MySQL version
     */
    private function getMySQLVersion() {
        $result = $this->db->fetch("SELECT VERSION() as version");
        return $result['version'] ?? '5.7.0';
    }
    
    /**
     * Prepare installation path
     */
    private function prepareInstallPath($domain, $installPath) {
        $basePath = rtrim($domain['document_root'], '/');
        $fullPath = $installPath ? $basePath . '/' . trim($installPath, '/') : $basePath;
        
        if (!is_dir($fullPath)) {
            if (!mkdir($fullPath, 0755, true)) {
                throw new Exception("Failed to create installation directory");
            }
        }
        
        return $fullPath;
    }
    
    /**
     * Download and extract application
     */
    private function downloadApplication($application, $installPath) {
        if (!$application['download_url']) {
            throw new Exception("No download URL available for this application");
        }
        
        $tempFile = tempnam(sys_get_temp_dir(), 'app_download_');
        
        // Download application archive
        $context = stream_context_create([
            'http' => [
                'timeout' => 300,
                'user_agent' => 'Admini Control Panel'
            ]
        ]);
        
        if (!copy($application['download_url'], $tempFile, $context)) {
            throw new Exception("Failed to download application");
        }
        
        // Extract archive
        $this->extractArchive($tempFile, $installPath);
        
        // Clean up temp file
        unlink($tempFile);
    }
    
    /**
     * Extract archive
     */
    private function extractArchive($archiveFile, $extractPath) {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($fileInfo, $archiveFile);
        finfo_close($fileInfo);
        
        switch ($mimeType) {
            case 'application/zip':
                $this->extractZip($archiveFile, $extractPath);
                break;
            case 'application/x-gzip':
            case 'application/gzip':
                $this->extractTarGz($archiveFile, $extractPath);
                break;
            default:
                throw new Exception("Unsupported archive format: {$mimeType}");
        }
    }
    
    /**
     * Extract ZIP archive
     */
    private function extractZip($zipFile, $extractPath) {
        $zip = new ZipArchive();
        
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($extractPath);
            $zip->close();
        } else {
            throw new Exception("Failed to extract ZIP archive");
        }
    }
    
    /**
     * Extract TAR.GZ archive
     */
    private function extractTarGz($tarGzFile, $extractPath) {
        $phar = new PharData($tarGzFile);
        $phar->extractTo($extractPath);
    }
    
    /**
     * Check if application requires database
     */
    private function requiresDatabase($application) {
        $requirements = json_decode($application['requirements'], true);
        return isset($requirements['mysql']) || isset($requirements['postgresql']);
    }
    
    /**
     * Create database for application
     */
    private function createApplicationDatabase($userId, $application, $options) {
        $databaseName = 'app_' . $application['app_name'] . '_' . uniqid();
        $username = substr($databaseName, 0, 16); // MySQL username limit
        $password = $this->generateSecurePassword();
        
        // Create database
        $this->db->query("CREATE DATABASE IF NOT EXISTS `{$databaseName}`");
        
        // Create database user
        $this->db->query(
            "CREATE USER '{$username}'@'localhost' IDENTIFIED BY '{$password}'"
        );
        
        // Grant privileges
        $this->db->query(
            "GRANT ALL PRIVILEGES ON `{$databaseName}`.* TO '{$username}'@'localhost'"
        );
        
        $this->db->query("FLUSH PRIVILEGES");
        
        // Record in databases table
        $this->db->query(
            "INSERT INTO databases (user_id, database_name, database_type) VALUES (?, ?, 'mysql')",
            [$userId, $databaseName]
        );
        
        return [
            'database_name' => $databaseName,
            'username' => $username,
            'password' => $password,
            'host' => 'localhost'
        ];
    }
    
    /**
     * Generate secure password
     */
    private function generateSecurePassword($length = 16) {
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*';
        $password = '';
        
        for ($i = 0; $i < $length; $i++) {
            $password .= $chars[random_int(0, strlen($chars) - 1)];
        }
        
        return $password;
    }
    
    /**
     * Run installation script
     */
    private function runInstallationScript($application, $installPath, $databaseInfo, $options) {
        $installScript = $application['install_script'];
        $configTemplate = json_decode($application['config_template'], true);
        
        // Generate admin credentials
        $adminUsername = $options['admin_username'] ?? 'admin';
        $adminPassword = $options['admin_password'] ?? $this->generateSecurePassword();
        $adminEmail = $options['admin_email'] ?? 'admin@example.com';
        
        // Prepare configuration variables
        $variables = [
            'INSTALL_PATH' => $installPath,
            'DATABASE_NAME' => $databaseInfo['database_name'] ?? '',
            'DATABASE_USER' => $databaseInfo['username'] ?? '',
            'DATABASE_PASSWORD' => $databaseInfo['password'] ?? '',
            'DATABASE_HOST' => $databaseInfo['host'] ?? 'localhost',
            'ADMIN_USERNAME' => $adminUsername,
            'ADMIN_PASSWORD' => $adminPassword,
            'ADMIN_EMAIL' => $adminEmail,
            'SITE_URL' => $options['site_url'] ?? '',
            'APP_NAME' => $options['app_name'] ?? $application['app_name']
        ];
        
        // Process install script
        if ($installScript) {
            $this->processInstallScript($installScript, $variables);
        }
        
        // Create configuration files
        if ($configTemplate) {
            $this->createConfigFiles($configTemplate, $variables, $installPath);
        }
        
        // Run specific application setup
        $this->runApplicationSpecificSetup($application['app_name'], $variables, $installPath);
        
        return [
            'app_url' => $variables['SITE_URL'],
            'admin_username' => $adminUsername,
            'admin_password' => $adminPassword
        ];
    }
    
    /**
     * Process installation script
     */
    private function processInstallScript($script, $variables) {
        // Replace variables in script
        foreach ($variables as $key => $value) {
            $script = str_replace('{' . $key . '}', $value, $script);
        }
        
        // Execute script commands
        $commands = explode("\n", $script);
        
        foreach ($commands as $command) {
            $command = trim($command);
            if (empty($command) || strpos($command, '#') === 0) {
                continue;
            }
            
            $output = shell_exec($command . ' 2>&1');
            
            // Log command execution
            error_log("Executed: {$command}");
            if ($output) {
                error_log("Output: {$output}");
            }
        }
    }
    
    /**
     * Create configuration files
     */
    private function createConfigFiles($configTemplate, $variables, $installPath) {
        foreach ($configTemplate as $filename => $content) {
            // Replace variables in content
            foreach ($variables as $key => $value) {
                $content = str_replace('{' . $key . '}', $value, $content);
            }
            
            $configFile = $installPath . '/' . $filename;
            $configDir = dirname($configFile);
            
            if (!is_dir($configDir)) {
                mkdir($configDir, 0755, true);
            }
            
            file_put_contents($configFile, $content);
            chmod($configFile, 0644);
        }
    }
    
    /**
     * Run application-specific setup
     */
    private function runApplicationSpecificSetup($appName, $variables, $installPath) {
        switch (strtolower($appName)) {
            case 'wordpress':
                $this->setupWordPress($variables, $installPath);
                break;
            case 'joomla':
                $this->setupJoomla($variables, $installPath);
                break;
            case 'drupal':
                $this->setupDrupal($variables, $installPath);
                break;
            case 'prestashop':
                $this->setupPrestaShop($variables, $installPath);
                break;
            case 'magento':
                $this->setupMagento($variables, $installPath);
                break;
            // Add more applications as needed
        }
    }
    
    /**
     * WordPress-specific setup
     */
    private function setupWordPress($variables, $installPath) {
        // Create wp-config.php
        $wpConfig = file_get_contents($installPath . '/wp-config-sample.php');
        
        $wpConfig = str_replace('database_name_here', $variables['DATABASE_NAME'], $wpConfig);
        $wpConfig = str_replace('username_here', $variables['DATABASE_USER'], $wpConfig);
        $wpConfig = str_replace('password_here', $variables['DATABASE_PASSWORD'], $wpConfig);
        $wpConfig = str_replace('localhost', $variables['DATABASE_HOST'], $wpConfig);
        
        // Add security keys
        $salts = file_get_contents('https://api.wordpress.org/secret-key/1.1/salt/');
        $wpConfig = preg_replace('/put your unique phrase here/i', $salts, $wpConfig, 8);
        
        file_put_contents($installPath . '/wp-config.php', $wpConfig);
        
        // Run WordPress installation via CLI if wp-cli is available
        if (shell_exec('which wp')) {
            $commands = [
                "cd {$installPath}",
                "wp core install --url='{$variables['SITE_URL']}' --title='{$variables['APP_NAME']}' --admin_user='{$variables['ADMIN_USERNAME']}' --admin_password='{$variables['ADMIN_PASSWORD']}' --admin_email='{$variables['ADMIN_EMAIL']}'"
            ];
            
            shell_exec(implode(' && ', $commands));
        }
    }
    
    /**
     * Joomla-specific setup
     */
    private function setupJoomla($variables, $installPath) {
        // Create configuration.php
        $joomlaConfig = "<?php\n";
        $joomlaConfig .= "class JConfig {\n";
        $joomlaConfig .= "\tpublic \$host = '{$variables['DATABASE_HOST']}';\n";
        $joomlaConfig .= "\tpublic \$user = '{$variables['DATABASE_USER']}';\n";
        $joomlaConfig .= "\tpublic \$password = '{$variables['DATABASE_PASSWORD']}';\n";
        $joomlaConfig .= "\tpublic \$db = '{$variables['DATABASE_NAME']}';\n";
        $joomlaConfig .= "\tpublic \$dbtype = 'mysqli';\n";
        $joomlaConfig .= "\tpublic \$secret = '" . $this->generateSecurePassword(32) . "';\n";
        $joomlaConfig .= "}";
        
        file_put_contents($installPath . '/configuration.php', $joomlaConfig);
    }
    
    /**
     * Drupal-specific setup
     */
    private function setupDrupal($variables, $installPath) {
        // Create settings.php
        $settingsDir = $installPath . '/sites/default';
        $settingsFile = $settingsDir . '/settings.php';
        
        if (!is_dir($settingsDir)) {
            mkdir($settingsDir, 0755, true);
        }
        
        $drupalSettings = "<?php\n";
        $drupalSettings .= "\$databases['default']['default'] = array (\n";
        $drupalSettings .= "  'database' => '{$variables['DATABASE_NAME']}',\n";
        $drupalSettings .= "  'username' => '{$variables['DATABASE_USER']}',\n";
        $drupalSettings .= "  'password' => '{$variables['DATABASE_PASSWORD']}',\n";
        $drupalSettings .= "  'host' => '{$variables['DATABASE_HOST']}',\n";
        $drupalSettings .= "  'port' => '',\n";
        $drupalSettings .= "  'driver' => 'mysql',\n";
        $drupalSettings .= "  'prefix' => '',\n";
        $drupalSettings .= ");\n";
        
        file_put_contents($settingsFile, $drupalSettings);
    }
    
    /**
     * PrestaShop-specific setup
     */
    private function setupPrestaShop($variables, $installPath) {
        // PrestaShop requires web-based installation
        // Create a helper file with database credentials
        $configFile = $installPath . '/install_config.php';
        
        $config = "<?php\n";
        $config .= "// Auto-generated installation config\n";
        $config .= "define('_DB_SERVER_', '{$variables['DATABASE_HOST']}');\n";
        $config .= "define('_DB_NAME_', '{$variables['DATABASE_NAME']}');\n";
        $config .= "define('_DB_USER_', '{$variables['DATABASE_USER']}');\n";
        $config .= "define('_DB_PASSWD_', '{$variables['DATABASE_PASSWORD']}');\n";
        
        file_put_contents($configFile, $config);
    }
    
    /**
     * Magento-specific setup
     */
    private function setupMagento($variables, $installPath) {
        // Magento requires composer and complex setup
        // Create env.php file
        $envConfig = [
            'db' => [
                'table_prefix' => '',
                'connection' => [
                    'default' => [
                        'host' => $variables['DATABASE_HOST'],
                        'dbname' => $variables['DATABASE_NAME'],
                        'username' => $variables['DATABASE_USER'],
                        'password' => $variables['DATABASE_PASSWORD'],
                        'model' => 'mysql4',
                        'engine' => 'innodb',
                        'initStatements' => 'SET NAMES utf8;',
                        'active' => '1'
                    ]
                ]
            ]
        ];
        
        $appEtcDir = $installPath . '/app/etc';
        if (!is_dir($appEtcDir)) {
            mkdir($appEtcDir, 0755, true);
        }
        
        file_put_contents($appEtcDir . '/env.php', "<?php\nreturn " . var_export($envConfig, true) . ";\n");
    }
    
    /**
     * Clean up failed installation
     */
    private function cleanupFailedInstallation($installPath, $databaseInfo) {
        // Remove installation directory
        if ($installPath && is_dir($installPath)) {
            $this->removeDirectory($installPath);
        }
        
        // Drop database if created
        if ($databaseInfo) {
            try {
                $this->db->query("DROP DATABASE IF EXISTS `{$databaseInfo['database_name']}`");
                $this->db->query("DROP USER IF EXISTS '{$databaseInfo['username']}'@'localhost'");
            } catch (Exception $e) {
                // Log error but don't throw
                error_log("Failed to cleanup database: " . $e->getMessage());
            }
        }
    }
    
    /**
     * Remove directory recursively
     */
    private function removeDirectory($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object)) {
                        $this->removeDirectory($dir . "/" . $object);
                    } else {
                        unlink($dir . "/" . $object);
                    }
                }
            }
            rmdir($dir);
        }
    }
    
    /**
     * Get user's installed applications
     */
    public function getUserApplications($userId) {
        return $this->db->fetchAll(
            "SELECT ua.*, a.app_name, a.category, d.domain_name 
             FROM user_applications ua 
             JOIN applications a ON ua.application_id = a.id 
             JOIN domains d ON ua.domain_id = d.id 
             WHERE ua.user_id = ? 
             ORDER BY ua.installed_at DESC",
            [$userId]
        );
    }
    
    /**
     * Uninstall application
     */
    public function uninstallApplication($installationId, $userId) {
        $installation = $this->db->fetch(
            "SELECT ua.*, d.document_root 
             FROM user_applications ua 
             JOIN domains d ON ua.domain_id = d.id 
             WHERE ua.id = ? AND ua.user_id = ?",
            [$installationId, $userId]
        );
        
        if (!$installation) {
            throw new Exception("Installation not found");
        }
        
        // Remove installation directory
        $installPath = rtrim($installation['document_root'], '/');
        if ($installation['install_path']) {
            $installPath .= '/' . trim($installation['install_path'], '/');
        }
        
        if (is_dir($installPath)) {
            $this->removeDirectory($installPath);
        }
        
        // Drop database if exists
        if ($installation['database_name']) {
            try {
                $this->db->query("DROP DATABASE IF EXISTS `{$installation['database_name']}`");
            } catch (Exception $e) {
                error_log("Failed to drop database: " . $e->getMessage());
            }
        }
        
        // Update installation record
        $this->db->query(
            "UPDATE user_applications SET status = 'removed' WHERE id = ?",
            [$installationId]
        );
        
        return true;
    }
    
    /**
     * Update application
     */
    public function updateApplication($installationId, $userId) {
        $installation = $this->db->fetch(
            "SELECT ua.*, a.* 
             FROM user_applications ua 
             JOIN applications a ON ua.application_id = a.id 
             WHERE ua.id = ? AND ua.user_id = ?",
            [$installationId, $userId]
        );
        
        if (!$installation) {
            throw new Exception("Installation not found");
        }
        
        // Check if update is available
        if (version_compare($installation['version'], $installation['app_version'], '>=')) {
            throw new Exception("No update available");
        }
        
        // Mark as updating
        $this->db->query(
            "UPDATE user_applications SET status = 'updating' WHERE id = ?",
            [$installationId]
        );
        
        try {
            // Perform application-specific update
            $this->performApplicationUpdate($installation);
            
            // Update version and status
            $this->db->query(
                "UPDATE user_applications SET version = ?, status = 'active' WHERE id = ?",
                [$installation['app_version'], $installationId]
            );
            
            return true;
            
        } catch (Exception $e) {
            // Revert status
            $this->db->query(
                "UPDATE user_applications SET status = 'active' WHERE id = ?",
                [$installationId]
            );
            
            throw $e;
        }
    }
    
    /**
     * Perform application-specific update
     */
    private function performApplicationUpdate($installation) {
        // This would contain application-specific update logic
        // For now, just simulate the update
        sleep(2);
    }
    
    /**
     * Get application statistics
     */
    public function getApplicationStatistics() {
        $stats = [];
        
        // Total installations
        $stats['total_installations'] = $this->db->fetch(
            "SELECT COUNT(*) as count FROM user_applications WHERE status != 'removed'"
        )['count'];
        
        // Installations by category
        $stats['by_category'] = $this->db->fetchAll(
            "SELECT a.category, COUNT(*) as count 
             FROM user_applications ua 
             JOIN applications a ON ua.application_id = a.id 
             WHERE ua.status != 'removed' 
             GROUP BY a.category"
        );
        
        // Popular applications
        $stats['popular_apps'] = $this->db->fetchAll(
            "SELECT a.app_name, COUNT(*) as installations 
             FROM user_applications ua 
             JOIN applications a ON ua.application_id = a.id 
             WHERE ua.status != 'removed' 
             GROUP BY a.id 
             ORDER BY installations DESC 
             LIMIT 10"
        );
        
        return $stats;
    }
}