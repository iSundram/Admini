<?php
// Application configuration
return [
    'app_name' => 'Admini Control Panel',
    'app_version' => '1.0.0',
    'app_url' => 'https://panel.yourdomain.com',
    'timezone' => 'UTC',
    'debug' => false,
    
    // Security settings
    'session_timeout' => 3600, // 1 hour
    'max_login_attempts' => 5,
    'password_min_length' => 8,
    'csrf_protection' => true,
    
    // File upload settings
    'max_upload_size' => '50M',
    'allowed_file_types' => ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'txt', 'zip'],
    
    // Email settings
    'smtp_host' => 'localhost',
    'smtp_port' => 587,
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'Admini Control Panel',
    
    // Default limits
    'default_disk_quota' => 1024, // MB
    'default_bandwidth_quota' => 10240, // MB
    'default_domains_limit' => 1,
    'default_subdomains_limit' => 10,
    'default_email_accounts_limit' => 10,
    'default_mysql_databases_limit' => 5,
    'default_ftp_accounts_limit' => 5,
    
    // Features
    'features' => [
        'dns_management' => true,
        'email_management' => true,
        'ftp_management' => true,
        'database_management' => true,
        'ssl_certificates' => true,
        'backup_restore' => true,
        'cron_jobs' => true,
        'file_manager' => true,
        'statistics' => true,
        'api_access' => true,
    ]
];