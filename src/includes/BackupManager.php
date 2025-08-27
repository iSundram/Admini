<?php
/**
 * Advanced Backup Manager for Enterprise Features
 */

class BackupManager {
    private $db;
    
    public function __construct() {
        $this->db = Database::getInstance();
    }
    
    /**
     * Create backup schedule
     */
    public function createBackupSchedule($userId, $scheduleData) {
        $sql = "INSERT INTO backup_schedules (user_id, schedule_name, backup_type, includes, frequency, 
                retention_days, storage_type, storage_config, next_backup) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $nextBackup = $this->calculateNextBackup($scheduleData['frequency']);
        
        $scheduleId = $this->db->query($sql, [
            $userId,
            $scheduleData['schedule_name'],
            $scheduleData['backup_type'],
            json_encode($scheduleData['includes']),
            $scheduleData['frequency'],
            $scheduleData['retention_days'] ?? 30,
            $scheduleData['storage_type'],
            json_encode($scheduleData['storage_config']),
            $nextBackup
        ])->lastInsertId();
        
        return $scheduleId;
    }
    
    /**
     * Calculate next backup time
     */
    private function calculateNextBackup($frequency) {
        $now = new DateTime();
        
        switch ($frequency) {
            case 'daily':
                $now->add(new DateInterval('P1D'));
                break;
            case 'weekly':
                $now->add(new DateInterval('P7D'));
                break;
            case 'monthly':
                $now->add(new DateInterval('P1M'));
                break;
        }
        
        return $now->format('Y-m-d H:i:s');
    }
    
    /**
     * Execute backup
     */
    public function executeBackup($scheduleId) {
        $schedule = $this->db->fetch("SELECT * FROM backup_schedules WHERE id = ?", [$scheduleId]);
        
        if (!$schedule) {
            throw new Exception("Backup schedule not found");
        }
        
        $backupName = $schedule['schedule_name'] . '_' . date('Y-m-d_H-i-s');
        $backupId = $this->db->query(
            "INSERT INTO backups (user_id, backup_name, backup_type, status) VALUES (?, ?, ?, 'running')",
            [$schedule['user_id'], $backupName, $schedule['backup_type']]
        )->lastInsertId();
        
        try {
            $includes = json_decode($schedule['includes'], true);
            $storageConfig = json_decode($schedule['storage_config'], true);
            
            $backupPath = $this->createBackup($schedule['user_id'], $schedule['backup_type'], $includes);
            $fileSize = filesize($backupPath);
            
            // Upload to storage
            $finalPath = $this->uploadToStorage($backupPath, $schedule['storage_type'], $storageConfig);
            
            // Update backup record
            $this->db->query(
                "UPDATE backups SET file_path = ?, file_size = ?, status = 'completed', completed_at = NOW() WHERE id = ?",
                [$finalPath, $fileSize, $backupId]
            );
            
            // Update schedule
            $nextBackup = $this->calculateNextBackup($schedule['frequency']);
            $this->db->query(
                "UPDATE backup_schedules SET last_backup = NOW(), next_backup = ? WHERE id = ?",
                [$nextBackup, $scheduleId]
            );
            
            // Clean up old backups
            $this->cleanupOldBackups($schedule['user_id'], $schedule['retention_days']);
            
            // Clean up local temp file if uploaded to cloud
            if ($schedule['storage_type'] !== 'local') {
                unlink($backupPath);
            }
            
            return [
                'backup_id' => $backupId,
                'status' => 'completed',
                'file_path' => $finalPath,
                'file_size' => $fileSize
            ];
            
        } catch (Exception $e) {
            $this->db->query(
                "UPDATE backups SET status = 'failed', completed_at = NOW() WHERE id = ?",
                [$backupId]
            );
            
            throw $e;
        }
    }
    
    /**
     * Create actual backup
     */
    private function createBackup($userId, $backupType, $includes) {
        $user = $this->db->fetch("SELECT username FROM users WHERE id = ?", [$userId]);
        $backupDir = "/tmp/backups/{$user['username']}";
        
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $timestamp = date('Y-m-d_H-i-s');
        $backupFile = "{$backupDir}/backup_{$backupType}_{$timestamp}.tar.gz";
        
        $filesToBackup = [];
        
        switch ($backupType) {
            case 'full':
                $filesToBackup = $this->getFullBackupFiles($userId, $includes);
                break;
            case 'incremental':
                $filesToBackup = $this->getIncrementalBackupFiles($userId, $includes);
                break;
            case 'differential':
                $filesToBackup = $this->getDifferentialBackupFiles($userId, $includes);
                break;
        }
        
        // Create tar.gz archive
        $this->createTarGzArchive($filesToBackup, $backupFile);
        
        return $backupFile;
    }
    
    /**
     * Get files for full backup
     */
    private function getFullBackupFiles($userId, $includes) {
        $files = [];
        
        // Get user's domains and their document roots
        $domains = $this->db->fetchAll("SELECT * FROM domains WHERE user_id = ?", [$userId]);
        
        if (in_array('files', $includes)) {
            foreach ($domains as $domain) {
                if (is_dir($domain['document_root'])) {
                    $files[] = $domain['document_root'];
                }
            }
        }
        
        if (in_array('databases', $includes)) {
            $files = array_merge($files, $this->exportDatabases($userId));
        }
        
        if (in_array('emails', $includes)) {
            $files = array_merge($files, $this->exportEmails($userId));
        }
        
        return $files;
    }
    
    /**
     * Get files for incremental backup
     */
    private function getIncrementalBackupFiles($userId, $includes) {
        // Get last backup date
        $lastBackup = $this->db->fetch(
            "SELECT MAX(completed_at) as last_backup FROM backups WHERE user_id = ? AND status = 'completed'",
            [$userId]
        );
        
        $lastBackupDate = $lastBackup['last_backup'] ?? '1970-01-01';
        
        return $this->getModifiedFilesSince($userId, $includes, $lastBackupDate);
    }
    
    /**
     * Get files for differential backup
     */
    private function getDifferentialBackupFiles($userId, $includes) {
        // Get last full backup date
        $lastFullBackup = $this->db->fetch(
            "SELECT MAX(completed_at) as last_backup FROM backups WHERE user_id = ? AND backup_type = 'full' AND status = 'completed'",
            [$userId]
        );
        
        $lastBackupDate = $lastFullBackup['last_backup'] ?? '1970-01-01';
        
        return $this->getModifiedFilesSince($userId, $includes, $lastBackupDate);
    }
    
    /**
     * Get files modified since date
     */
    private function getModifiedFilesSince($userId, $includes, $since) {
        $files = [];
        $sinceTimestamp = strtotime($since);
        
        if (in_array('files', $includes)) {
            $domains = $this->db->fetchAll("SELECT * FROM domains WHERE user_id = ?", [$userId]);
            
            foreach ($domains as $domain) {
                if (is_dir($domain['document_root'])) {
                    $iterator = new RecursiveIteratorIterator(
                        new RecursiveDirectoryIterator($domain['document_root'])
                    );
                    
                    foreach ($iterator as $file) {
                        if ($file->isFile() && $file->getMTime() > $sinceTimestamp) {
                            $files[] = $file->getPathname();
                        }
                    }
                }
            }
        }
        
        if (in_array('databases', $includes)) {
            $files = array_merge($files, $this->exportDatabases($userId));
        }
        
        if (in_array('emails', $includes)) {
            $files = array_merge($files, $this->exportEmails($userId));
        }
        
        return $files;
    }
    
    /**
     * Export databases
     */
    private function exportDatabases($userId) {
        $exportFiles = [];
        $databases = $this->db->fetchAll("SELECT * FROM databases WHERE user_id = ?", [$userId]);
        
        foreach ($databases as $database) {
            $exportFile = "/tmp/db_export_{$database['database_name']}_" . date('Y-m-d_H-i-s') . ".sql";
            
            // Execute mysqldump
            $command = sprintf(
                "mysqldump -u%s -p%s %s > %s",
                escapeshellarg($GLOBALS['db_config']['username']),
                escapeshellarg($GLOBALS['db_config']['password']),
                escapeshellarg($database['database_name']),
                escapeshellarg($exportFile)
            );
            
            exec($command, $output, $returnCode);
            
            if ($returnCode === 0 && file_exists($exportFile)) {
                $exportFiles[] = $exportFile;
            }
        }
        
        return $exportFiles;
    }
    
    /**
     * Export emails
     */
    private function exportEmails($userId) {
        $emailFiles = [];
        $emailAccounts = $this->db->fetchAll("SELECT * FROM email_accounts WHERE user_id = ?", [$userId]);
        
        foreach ($emailAccounts as $account) {
            // In a real implementation, this would export mailbox files
            // For now, we'll create a placeholder file with account info
            $emailExportFile = "/tmp/email_export_{$account['email']}_" . date('Y-m-d_H-i-s') . ".json";
            
            $accountData = [
                'email' => $account['email'],
                'quota' => $account['quota'],
                'quota_used' => $account['quota_used'],
                'created_at' => $account['created_at']
            ];
            
            file_put_contents($emailExportFile, json_encode($accountData, JSON_PRETTY_PRINT));
            $emailFiles[] = $emailExportFile;
        }
        
        return $emailFiles;
    }
    
    /**
     * Create tar.gz archive
     */
    private function createTarGzArchive($files, $outputFile) {
        $phar = new PharData($outputFile);
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $phar->addFile($file, basename($file));
            } elseif (is_dir($file)) {
                $this->addDirectoryToPhar($phar, $file);
            }
        }
        
        $phar->compress(Phar::GZ);
        unset($phar);
        
        // Remove uncompressed version
        if (file_exists($outputFile)) {
            unlink($outputFile);
        }
    }
    
    /**
     * Add directory to phar archive
     */
    private function addDirectoryToPhar($phar, $directory) {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS)
        );
        
        foreach ($iterator as $file) {
            $relativePath = substr($file->getPathname(), strlen($directory) + 1);
            
            if ($file->isFile()) {
                $phar->addFile($file->getPathname(), $relativePath);
            }
        }
    }
    
    /**
     * Upload backup to storage
     */
    private function uploadToStorage($backupPath, $storageType, $storageConfig) {
        switch ($storageType) {
            case 'local':
                return $backupPath;
                
            case 's3':
                return $this->uploadToS3($backupPath, $storageConfig);
                
            case 'google_cloud':
                return $this->uploadToGoogleCloud($backupPath, $storageConfig);
                
            case 'ftp':
                return $this->uploadToFTP($backupPath, $storageConfig);
                
            default:
                throw new Exception("Unsupported storage type: {$storageType}");
        }
    }
    
    /**
     * Upload to Amazon S3
     */
    private function uploadToS3($backupPath, $config) {
        // In a real implementation, use AWS SDK
        // For now, simulate the upload
        
        $key = $config['prefix'] . '/' . basename($backupPath);
        
        // Simulate successful upload
        return "s3://{$config['bucket']}/{$key}";
    }
    
    /**
     * Upload to Google Cloud Storage
     */
    private function uploadToGoogleCloud($backupPath, $config) {
        // In a real implementation, use Google Cloud SDK
        // For now, simulate the upload
        
        $key = $config['prefix'] . '/' . basename($backupPath);
        
        return "gs://{$config['bucket']}/{$key}";
    }
    
    /**
     * Upload to FTP
     */
    private function uploadToFTP($backupPath, $config) {
        $connection = ftp_connect($config['host'], $config['port'] ?? 21);
        
        if (!$connection) {
            throw new Exception("Failed to connect to FTP server");
        }
        
        if (!ftp_login($connection, $config['username'], $config['password'])) {
            ftp_close($connection);
            throw new Exception("Failed to login to FTP server");
        }
        
        ftp_pasv($connection, true);
        
        $remoteFile = $config['path'] . '/' . basename($backupPath);
        
        if (!ftp_put($connection, $remoteFile, $backupPath, FTP_BINARY)) {
            ftp_close($connection);
            throw new Exception("Failed to upload backup to FTP server");
        }
        
        ftp_close($connection);
        
        return "ftp://{$config['host']}{$remoteFile}";
    }
    
    /**
     * Clean up old backups
     */
    private function cleanupOldBackups($userId, $retentionDays) {
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$retentionDays} days"));
        
        $oldBackups = $this->db->fetchAll(
            "SELECT * FROM backups WHERE user_id = ? AND created_at < ? AND status = 'completed'",
            [$userId, $cutoffDate]
        );
        
        foreach ($oldBackups as $backup) {
            // Delete backup file
            if (file_exists($backup['file_path'])) {
                unlink($backup['file_path']);
            } elseif (strpos($backup['file_path'], 's3://') === 0) {
                // Delete from S3
                $this->deleteFromS3($backup['file_path']);
            } elseif (strpos($backup['file_path'], 'gs://') === 0) {
                // Delete from Google Cloud
                $this->deleteFromGoogleCloud($backup['file_path']);
            }
            
            // Remove backup record
            $this->db->query("DELETE FROM backups WHERE id = ?", [$backup['id']]);
        }
    }
    
    /**
     * Delete from S3
     */
    private function deleteFromS3($s3Path) {
        // Implement S3 deletion
    }
    
    /**
     * Delete from Google Cloud
     */
    private function deleteFromGoogleCloud($gsPath) {
        // Implement Google Cloud deletion
    }
    
    /**
     * Restore backup
     */
    public function restoreBackup($backupId, $restoreOptions = []) {
        $backup = $this->db->fetch("SELECT * FROM backups WHERE id = ?", [$backupId]);
        
        if (!$backup) {
            throw new Exception("Backup not found");
        }
        
        // Download backup if stored remotely
        $localPath = $this->downloadBackup($backup['file_path']);
        
        // Extract backup
        $extractPath = "/tmp/restore_" . uniqid();
        mkdir($extractPath, 0755, true);
        
        $this->extractBackup($localPath, $extractPath);
        
        // Perform restoration based on options
        if ($restoreOptions['restore_files'] ?? true) {
            $this->restoreFiles($backup['user_id'], $extractPath);
        }
        
        if ($restoreOptions['restore_databases'] ?? true) {
            $this->restoreDatabases($backup['user_id'], $extractPath);
        }
        
        if ($restoreOptions['restore_emails'] ?? true) {
            $this->restoreEmails($backup['user_id'], $extractPath);
        }
        
        // Clean up
        $this->removeDirectory($extractPath);
        
        if ($localPath !== $backup['file_path']) {
            unlink($localPath);
        }
        
        return true;
    }
    
    /**
     * Download backup from remote storage
     */
    private function downloadBackup($backupPath) {
        if (file_exists($backupPath)) {
            return $backupPath;
        }
        
        $localPath = "/tmp/download_" . basename($backupPath);
        
        if (strpos($backupPath, 's3://') === 0) {
            $this->downloadFromS3($backupPath, $localPath);
        } elseif (strpos($backupPath, 'gs://') === 0) {
            $this->downloadFromGoogleCloud($backupPath, $localPath);
        } elseif (strpos($backupPath, 'ftp://') === 0) {
            $this->downloadFromFTP($backupPath, $localPath);
        }
        
        return $localPath;
    }
    
    /**
     * Extract backup archive
     */
    private function extractBackup($backupPath, $extractPath) {
        $phar = new PharData($backupPath);
        $phar->extractTo($extractPath);
    }
    
    /**
     * Restore files
     */
    private function restoreFiles($userId, $extractPath) {
        $domains = $this->db->fetchAll("SELECT * FROM domains WHERE user_id = ?", [$userId]);
        
        foreach ($domains as $domain) {
            $domainBackupPath = $extractPath . '/' . basename($domain['document_root']);
            
            if (is_dir($domainBackupPath)) {
                $this->copyDirectory($domainBackupPath, $domain['document_root']);
            }
        }
    }
    
    /**
     * Restore databases
     */
    private function restoreDatabases($userId, $extractPath) {
        $sqlFiles = glob($extractPath . '/db_export_*.sql');
        
        foreach ($sqlFiles as $sqlFile) {
            $databaseName = $this->extractDatabaseNameFromFile($sqlFile);
            
            $command = sprintf(
                "mysql -u%s -p%s %s < %s",
                escapeshellarg($GLOBALS['db_config']['username']),
                escapeshellarg($GLOBALS['db_config']['password']),
                escapeshellarg($databaseName),
                escapeshellarg($sqlFile)
            );
            
            exec($command);
        }
    }
    
    /**
     * Restore emails
     */
    private function restoreEmails($userId, $extractPath) {
        $emailFiles = glob($extractPath . '/email_export_*.json');
        
        foreach ($emailFiles as $emailFile) {
            $accountData = json_decode(file_get_contents($emailFile), true);
            
            // Restore email account configuration
            // In a real implementation, this would restore actual mailbox files
        }
    }
    
    /**
     * Utility methods
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
    
    private function copyDirectory($src, $dst) {
        if (is_dir($src)) {
            if (!is_dir($dst)) {
                mkdir($dst, 0755, true);
            }
            
            $objects = scandir($src);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($src . "/" . $object)) {
                        $this->copyDirectory($src . "/" . $object, $dst . "/" . $object);
                    } else {
                        copy($src . "/" . $object, $dst . "/" . $object);
                    }
                }
            }
        }
    }
    
    private function extractDatabaseNameFromFile($filename) {
        preg_match('/db_export_(.+?)_\d{4}-\d{2}-\d{2}/', basename($filename), $matches);
        return $matches[1] ?? '';
    }
    
    /**
     * Get backup schedules
     */
    public function getBackupSchedules($userId) {
        return $this->db->fetchAll("SELECT * FROM backup_schedules WHERE user_id = ? ORDER BY created_at DESC", [$userId]);
    }
    
    /**
     * Get backups
     */
    public function getBackups($userId, $limit = 50) {
        return $this->db->fetchAll("SELECT * FROM backups WHERE user_id = ? ORDER BY created_at DESC LIMIT ?", [$userId, $limit]);
    }
    
    /**
     * Delete backup schedule
     */
    public function deleteBackupSchedule($scheduleId) {
        $this->db->query("DELETE FROM backup_schedules WHERE id = ?", [$scheduleId]);
    }
    
    /**
     * Download from various storage types (stub implementations)
     */
    private function downloadFromS3($s3Path, $localPath) {
        // Implement S3 download
    }
    
    private function downloadFromGoogleCloud($gsPath, $localPath) {
        // Implement Google Cloud download
    }
    
    private function downloadFromFTP($ftpPath, $localPath) {
        // Implement FTP download
    }
}