-- Admini Control Panel Database Schema

-- Users table (Admins, Resellers, End Users)
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role ENUM('admin', 'reseller', 'user') NOT NULL DEFAULT 'user',
    status ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
    reseller_id INT NULL,
    package_id INT NULL,
    disk_quota BIGINT DEFAULT 0, -- in MB
    disk_used BIGINT DEFAULT 0,
    bandwidth_quota BIGINT DEFAULT 0, -- in MB
    bandwidth_used BIGINT DEFAULT 0,
    domains_limit INT DEFAULT 1,
    subdomains_limit INT DEFAULT 10,
    email_accounts_limit INT DEFAULT 10,
    mysql_databases_limit INT DEFAULT 5,
    ftp_accounts_limit INT DEFAULT 5,
    contact_name VARCHAR(100) NULL,
    contact_phone VARCHAR(20) NULL,
    contact_address TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    FOREIGN KEY (reseller_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Packages table
CREATE TABLE packages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    disk_quota BIGINT NOT NULL DEFAULT -1, -- in MB, -1 for unlimited
    bandwidth_quota BIGINT NOT NULL DEFAULT -1, -- in MB, -1 for unlimited
    email_accounts INT NOT NULL DEFAULT -1, -- -1 for unlimited
    databases INT NOT NULL DEFAULT -1, -- -1 for unlimited
    subdomains INT NOT NULL DEFAULT -1, -- -1 for unlimited
    addon_domains INT NOT NULL DEFAULT 0,
    ftp_accounts INT NOT NULL DEFAULT -1, -- -1 for unlimited
    ssl_certificates INT NOT NULL DEFAULT -1, -- -1 for unlimited
    price DECIMAL(10,2) DEFAULT 0.00,
    features JSON, -- Additional features as JSON
    billing_cycle ENUM('monthly', 'quarterly', 'annually') DEFAULT 'monthly',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Domains table
CREATE TABLE domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_name VARCHAR(255) UNIQUE NOT NULL,
    domain_type ENUM('main', 'addon', 'subdomain', 'parked') NOT NULL DEFAULT 'main',
    parent_domain_id INT NULL,
    document_root VARCHAR(500) NOT NULL,
    status ENUM('active', 'suspended', 'pending') DEFAULT 'active',
    ssl_enabled BOOLEAN DEFAULT FALSE,
    ssl_cert TEXT NULL,
    ssl_key TEXT NULL,
    ssl_ca TEXT NULL,
    ssl_auto_renew BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- DNS Zones table
CREATE TABLE dns_zones (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    zone_name VARCHAR(255) NOT NULL,
    ttl INT DEFAULT 3600,
    serial_number BIGINT NOT NULL,
    refresh_interval INT DEFAULT 3600,
    retry_interval INT DEFAULT 1800,
    expire_time INT DEFAULT 1209600,
    minimum_ttl INT DEFAULT 86400,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- DNS Records table
CREATE TABLE dns_records (
    id INT AUTO_INCREMENT PRIMARY KEY,
    zone_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'PTR', 'SRV') NOT NULL,
    value TEXT NOT NULL,
    ttl INT DEFAULT 3600,
    priority INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (zone_id) REFERENCES dns_zones(id) ON DELETE CASCADE
);

-- Email Accounts table
CREATE TABLE email_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    email VARCHAR(320) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    quota BIGINT DEFAULT 0, -- in MB, 0 = unlimited
    quota_used BIGINT DEFAULT 0,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    pop3_enabled BOOLEAN DEFAULT TRUE,
    imap_enabled BOOLEAN DEFAULT TRUE,
    smtp_enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Email Forwarders table
CREATE TABLE email_forwarders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    source_email VARCHAR(320) NOT NULL,
    destination_email VARCHAR(320) NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Databases table
CREATE TABLE databases (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    database_name VARCHAR(64) UNIQUE NOT NULL,
    database_type ENUM('mysql', 'postgresql') DEFAULT 'mysql',
    size BIGINT DEFAULT 0, -- in bytes
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Database Users table
CREATE TABLE database_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    database_id INT NOT NULL,
    username VARCHAR(50) NOT NULL,
    password VARCHAR(255) NOT NULL,
    privileges TEXT, -- JSON encoded privileges
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (database_id) REFERENCES databases(id) ON DELETE CASCADE
);

-- FTP Accounts table
CREATE TABLE ftp_accounts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    home_directory VARCHAR(500) NOT NULL,
    status ENUM('active', 'suspended', 'inactive') DEFAULT 'active',
    quota BIGINT DEFAULT 0, -- in MB, 0 = unlimited
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Cron Jobs table
CREATE TABLE cron_jobs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    command TEXT NOT NULL,
    minute VARCHAR(20) DEFAULT '*',
    hour VARCHAR(20) DEFAULT '*',
    day VARCHAR(20) DEFAULT '*',
    month VARCHAR(20) DEFAULT '*',
    weekday VARCHAR(20) DEFAULT '*',
    status ENUM('active', 'inactive') DEFAULT 'active',
    last_run TIMESTAMP NULL,
    next_run TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Backups table
CREATE TABLE backups (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    backup_name VARCHAR(100) NOT NULL,
    backup_type ENUM('full', 'files', 'databases', 'emails') NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_size BIGINT DEFAULT 0,
    status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- SSL Certificates table
CREATE TABLE ssl_certificates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    certificate_authority VARCHAR(100) NOT NULL,
    certificate TEXT NOT NULL,
    private_key TEXT NOT NULL,
    certificate_chain TEXT NULL,
    status ENUM('active', 'expired', 'revoked') DEFAULT 'active',
    issue_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expiry_date TIMESTAMP NOT NULL,
    auto_renew BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Statistics table
CREATE TABLE statistics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    stat_type ENUM('bandwidth', 'disk_usage', 'email_usage', 'database_size') NOT NULL,
    value BIGINT NOT NULL,
    date DATE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_stat_date (user_id, stat_type, date)
);

-- System Settings table
CREATE TABLE system_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT,
    description TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Activity Logs table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    description TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default system settings
INSERT INTO system_settings (setting_key, setting_value, description) VALUES
('site_name', 'Admini Control Panel', 'Name of the control panel'),
('site_url', 'https://panel.yourdomain.com', 'URL of the control panel'),
('admin_email', 'admin@yourdomain.com', 'Administrator email address'),
('default_package_id', '1', 'Default package for new users'),
('max_login_attempts', '5', 'Maximum login attempts before lockout'),
('session_timeout', '3600', 'Session timeout in seconds'),
('backup_retention_days', '30', 'Number of days to keep backups'),
('ssl_letsencrypt_enabled', '1', 'Enable Let\'s Encrypt SSL certificates'),
('email_notifications', '1', 'Enable email notifications'),
('maintenance_mode', '0', 'Enable maintenance mode');

-- Insert default package
INSERT INTO packages (name, description, disk_quota, bandwidth_quota, email_accounts, databases, 
                     subdomains, addon_domains, ftp_accounts, ssl_certificates, features) 
VALUES ('Basic Package', 'Default package for new users', 1024, 10240, 10, 5, 10, 0, 5, 1, '["PHP Support", "MySQL Support", "SSL Support"]');

-- IP Addresses table
CREATE TABLE ip_addresses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) UNIQUE NOT NULL,
    netmask VARCHAR(45) DEFAULT '255.255.255.0',
    gateway VARCHAR(45) NULL,
    assigned_to INT NULL,
    status ENUM('available', 'assigned', 'reserved') DEFAULT 'available',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Mail Queue table
CREATE TABLE mail_queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    from_address VARCHAR(320) NOT NULL,
    to_address VARCHAR(320) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    headers TEXT,
    status ENUM('pending', 'sent', 'failed', 'deferred') DEFAULT 'pending',
    attempts INT DEFAULT 0,
    last_attempt TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    sent_at TIMESTAMP NULL
);

-- System Services table
CREATE TABLE system_services (
    id INT AUTO_INCREMENT PRIMARY KEY,
    service_name VARCHAR(50) UNIQUE NOT NULL,
    display_name VARCHAR(100) NOT NULL,
    status ENUM('running', 'stopped', 'failed') DEFAULT 'stopped',
    auto_start BOOLEAN DEFAULT TRUE,
    description TEXT,
    last_checked TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Security Rules table
CREATE TABLE security_rules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rule_type ENUM('ip_block', 'country_block', 'user_agent_block') NOT NULL,
    rule_value VARCHAR(255) NOT NULL,
    action ENUM('block', 'allow') NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Subdomain table
CREATE TABLE subdomains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    subdomain VARCHAR(100) NOT NULL,
    domain_id INT NOT NULL,
    document_root VARCHAR(500) NOT NULL,
    status ENUM('active', 'suspended') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_subdomain (subdomain, domain_id)
);

-- Insert default services
INSERT INTO system_services (service_name, display_name, description) VALUES
('apache2', 'Apache Web Server', 'HTTP/HTTPS web server'),
('mysql', 'MySQL Database Server', 'MySQL database service'),
('postfix', 'Postfix Mail Server', 'SMTP mail transfer agent'),
('dovecot', 'Dovecot IMAP/POP3', 'IMAP and POP3 mail server'),
('bind9', 'BIND DNS Server', 'Domain Name System server'),
('fail2ban', 'Fail2Ban Security', 'Intrusion prevention system'),
('clamav', 'ClamAV Antivirus', 'Antivirus scanning service'),
('pure-ftpd', 'Pure-FTPd Server', 'FTP server daemon');

-- Two-Factor Authentication table
CREATE TABLE user_2fa (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    secret VARCHAR(32) NOT NULL,
    backup_codes JSON,
    enabled BOOLEAN DEFAULT FALSE,
    last_used TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- API Keys table for advanced API management
CREATE TABLE api_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    key_name VARCHAR(100) NOT NULL,
    api_key VARCHAR(64) UNIQUE NOT NULL,
    permissions JSON, -- API permissions
    rate_limit_per_minute INT DEFAULT 60,
    last_used TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    status ENUM('active', 'inactive', 'expired') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Security Scan Results
CREATE TABLE security_scans (
    id INT AUTO_INCREMENT PRIMARY KEY,
    scan_type ENUM('malware', 'vulnerability', 'integrity', 'blacklist') NOT NULL,
    target_type ENUM('server', 'domain', 'user', 'file') NOT NULL,
    target_id VARCHAR(255) NOT NULL,
    scan_status ENUM('pending', 'running', 'completed', 'failed') DEFAULT 'pending',
    threats_found INT DEFAULT 0,
    results JSON,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP NULL,
    next_scan TIMESTAMP NULL
);

-- Advanced Backup Management
CREATE TABLE backup_schedules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    schedule_name VARCHAR(100) NOT NULL,
    backup_type ENUM('full', 'incremental', 'differential') NOT NULL,
    includes JSON, -- What to backup
    frequency ENUM('daily', 'weekly', 'monthly') NOT NULL,
    retention_days INT DEFAULT 30,
    storage_type ENUM('local', 's3', 'google_cloud', 'ftp') NOT NULL,
    storage_config JSON,
    status ENUM('active', 'paused', 'inactive') DEFAULT 'active',
    last_backup TIMESTAMP NULL,
    next_backup TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- System Monitoring
CREATE TABLE system_monitoring (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_id VARCHAR(50) DEFAULT 'local',
    metric_type ENUM('cpu', 'memory', 'disk', 'network', 'load') NOT NULL,
    metric_value DECIMAL(10,4) NOT NULL,
    threshold_warning DECIMAL(10,4) DEFAULT 80.0,
    threshold_critical DECIMAL(10,4) DEFAULT 95.0,
    status ENUM('normal', 'warning', 'critical') DEFAULT 'normal',
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Application Installer
CREATE TABLE applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    app_name VARCHAR(100) NOT NULL,
    app_version VARCHAR(20) NOT NULL,
    category ENUM('cms', 'ecommerce', 'forum', 'blog', 'framework', 'tools') NOT NULL,
    description TEXT,
    requirements JSON, -- PHP version, extensions, etc.
    download_url VARCHAR(500),
    install_script TEXT,
    config_template JSON,
    status ENUM('active', 'deprecated', 'beta') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- User Application Installations
CREATE TABLE user_applications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    application_id INT NOT NULL,
    domain_id INT NOT NULL,
    install_path VARCHAR(500) NOT NULL,
    app_url VARCHAR(500),
    admin_username VARCHAR(50),
    admin_password VARCHAR(255),
    database_name VARCHAR(64),
    version VARCHAR(20),
    auto_update BOOLEAN DEFAULT FALSE,
    status ENUM('installing', 'active', 'updating', 'failed', 'removed') DEFAULT 'installing',
    installed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (application_id) REFERENCES applications(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Email Security (DKIM, SPF, DMARC)
CREATE TABLE email_security (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    dkim_enabled BOOLEAN DEFAULT FALSE,
    dkim_selector VARCHAR(50) DEFAULT 'default',
    dkim_private_key TEXT,
    dkim_public_key TEXT,
    spf_record TEXT,
    dmarc_policy ENUM('none', 'quarantine', 'reject') DEFAULT 'none',
    dmarc_record TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Staging Environments
CREATE TABLE staging_sites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    staging_subdomain VARCHAR(100) NOT NULL,
    staging_path VARCHAR(500) NOT NULL,
    sync_type ENUM('manual', 'auto') DEFAULT 'manual',
    last_sync TIMESTAMP NULL,
    status ENUM('active', 'syncing', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Load Balancers
CREATE TABLE load_balancers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    algorithm ENUM('round_robin', 'least_connections', 'ip_hash') DEFAULT 'round_robin',
    health_check_url VARCHAR(500),
    health_check_interval INT DEFAULT 30,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Load Balancer Backends
CREATE TABLE load_balancer_backends (
    id INT AUTO_INCREMENT PRIMARY KEY,
    load_balancer_id INT NOT NULL,
    server_ip VARCHAR(45) NOT NULL,
    server_port INT DEFAULT 80,
    weight INT DEFAULT 1,
    status ENUM('up', 'down', 'maintenance') DEFAULT 'up',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (load_balancer_id) REFERENCES load_balancers(id) ON DELETE CASCADE
);

-- Webhooks
CREATE TABLE webhooks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    event_type VARCHAR(50) NOT NULL, -- user.created, domain.added, etc.
    url VARCHAR(500) NOT NULL,
    secret VARCHAR(100),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Webhook Deliveries
CREATE TABLE webhook_deliveries (
    id INT AUTO_INCREMENT PRIMARY KEY,
    webhook_id INT NOT NULL,
    event_data JSON,
    response_status INT,
    response_body TEXT,
    delivered_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (webhook_id) REFERENCES webhooks(id) ON DELETE CASCADE
);

-- Servers Management
CREATE TABLE servers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    server_name VARCHAR(100) NOT NULL,
    hostname VARCHAR(255) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    ssh_port INT DEFAULT 22,
    ssh_username VARCHAR(50),
    ssh_key TEXT,
    server_type ENUM('web', 'database', 'mail', 'dns', 'loadbalancer') NOT NULL,
    status ENUM('online', 'offline', 'maintenance') DEFAULT 'offline',
    last_check TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Billing and Invoices
CREATE TABLE invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    invoice_number VARCHAR(50) UNIQUE NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    tax_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) NOT NULL,
    status ENUM('draft', 'sent', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
    due_date DATE NOT NULL,
    paid_date DATE NULL,
    items JSON, -- Invoice line items
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Payment Methods
CREATE TABLE payment_methods (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    method_type ENUM('credit_card', 'paypal', 'bank_transfer') NOT NULL,
    provider VARCHAR(50), -- stripe, paypal, etc.
    provider_id VARCHAR(100),
    is_default BOOLEAN DEFAULT FALSE,
    status ENUM('active', 'expired', 'invalid') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Support Tickets
CREATE TABLE support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    subject VARCHAR(255) NOT NULL,
    department ENUM('technical', 'billing', 'sales', 'abuse') DEFAULT 'technical',
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'pending', 'resolved', 'closed') DEFAULT 'open',
    assigned_to INT NULL, -- Admin user ID
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Support Ticket Messages
CREATE TABLE support_ticket_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT NOT NULL,
    message TEXT NOT NULL,
    is_staff BOOLEAN DEFAULT FALSE,
    attachments JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Resource Usage Tracking
CREATE TABLE resource_usage (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    resource_type ENUM('cpu', 'memory', 'disk_io', 'network_in', 'network_out') NOT NULL,
    usage_value DECIMAL(15,6) NOT NULL,
    unit VARCHAR(10) NOT NULL, -- MB, GB, %, etc.
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert popular applications
INSERT INTO applications (app_name, app_version, category, description, requirements, status) VALUES
('WordPress', '6.4', 'cms', 'Popular content management system', '{"php": "7.4+", "mysql": "5.7+", "extensions": ["curl", "gd", "mbstring"]}', 'active'),
('Joomla', '5.0', 'cms', 'Flexible content management system', '{"php": "8.0+", "mysql": "5.7+", "extensions": ["curl", "gd", "mbstring", "xml"]}', 'active'),
('Drupal', '10.0', 'cms', 'Enterprise content management system', '{"php": "8.1+", "mysql": "5.7+", "extensions": ["curl", "gd", "mbstring", "xml"]}', 'active'),
('PrestaShop', '8.1', 'ecommerce', 'Open source e-commerce platform', '{"php": "7.4+", "mysql": "5.7+", "extensions": ["curl", "gd", "mbstring", "zip"]}', 'active'),
('Magento', '2.4', 'ecommerce', 'Professional e-commerce platform', '{"php": "8.1+", "mysql": "5.7+", "extensions": ["curl", "gd", "mbstring", "soap"]}', 'active'),
('Laravel', '10.0', 'framework', 'PHP web application framework', '{"php": "8.1+", "mysql": "5.7+", "extensions": ["curl", "mbstring", "openssl"]}', 'active'),
('phpMyAdmin', '5.2', 'tools', 'Web-based MySQL administration tool', '{"php": "7.4+", "mysql": "5.7+", "extensions": ["curl", "mbstring", "mysqli"]}', 'active');

-- Container Management tables
CREATE TABLE containers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    app_id INT NULL,
    container_type ENUM('nodejs', 'python', 'docker', 'static') NOT NULL,
    container_name VARCHAR(100) NOT NULL,
    port INT NOT NULL,
    status ENUM('stopped', 'running', 'failed', 'building') DEFAULT 'stopped',
    config JSON,
    resource_limits JSON, -- CPU, memory limits
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (app_id) REFERENCES user_applications(id) ON DELETE SET NULL,
    UNIQUE KEY unique_user_container (user_id, container_name)
);

-- Container Logs table
CREATE TABLE container_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    container_id INT NOT NULL,
    log_level ENUM('info', 'warning', 'error', 'debug') DEFAULT 'info',
    message TEXT NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    INDEX idx_container_timestamp (container_id, timestamp)
);

-- Git Repositories table
CREATE TABLE git_repositories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    container_id INT NULL,
    repo_name VARCHAR(100) NOT NULL,
    repo_url VARCHAR(500) NOT NULL,
    branch VARCHAR(100) DEFAULT 'main',
    deploy_key TEXT,
    auto_deploy BOOLEAN DEFAULT FALSE,
    last_commit VARCHAR(40),
    last_deployed TIMESTAMP NULL,
    status ENUM('connected', 'failed', 'deploying') DEFAULT 'connected',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE SET NULL
);

-- Environment Variables table
CREATE TABLE environment_variables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    container_id INT NOT NULL,
    variable_name VARCHAR(100) NOT NULL,
    variable_value TEXT NOT NULL,
    is_secret BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (container_id) REFERENCES containers(id) ON DELETE CASCADE,
    UNIQUE KEY unique_container_variable (container_id, variable_name)
);

-- Performance Cache Configuration
CREATE TABLE cache_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    cache_type ENUM('redis', 'memcached', 'file', 'opcache') NOT NULL,
    config JSON NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- CDN Configuration
CREATE TABLE cdn_configuration (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    domain_id INT NOT NULL,
    provider ENUM('cloudflare', 'aws_cloudfront', 'maxcdn', 'keycdn') NOT NULL,
    config JSON NOT NULL,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Performance Metrics
CREATE TABLE performance_metrics (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    metric_type ENUM('page_load_time', 'ttfb', 'dom_content_loaded', 'largest_contentful_paint') NOT NULL,
    metric_value DECIMAL(10,3) NOT NULL,
    url VARCHAR(500),
    user_agent VARCHAR(200),
    recorded_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    INDEX idx_domain_type_time (domain_id, metric_type, recorded_at)
);

-- SSL Certificate Management Enhanced
CREATE TABLE ssl_orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    certificate_authority ENUM('letsencrypt', 'sectigo', 'digicert', 'custom') NOT NULL,
    order_status ENUM('pending', 'processing', 'issued', 'failed', 'expired') DEFAULT 'pending',
    challenge_type ENUM('http-01', 'dns-01', 'tls-alpn-01') DEFAULT 'http-01',
    order_url VARCHAR(500),
    challenge_token VARCHAR(100),
    expires_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE
);

-- Advanced User Permissions
CREATE TABLE user_permissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    permission_type ENUM('api_access', 'container_management', 'ssl_management', 'backup_management', 'monitoring_access') NOT NULL,
    permission_value JSON, -- Detailed permissions
    granted_by INT NOT NULL,
    granted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (granted_by) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_permission (user_id, permission_type)
);

-- Notification System
CREATE TABLE notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    notification_type ENUM('security_alert', 'backup_complete', 'ssl_expiry', 'resource_warning', 'system_update') NOT NULL,
    title VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'critical') DEFAULT 'medium',
    is_read BOOLEAN DEFAULT FALSE,
    action_url VARCHAR(500),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user_read_created (user_id, is_read, created_at)
);

-- Audit Trail Enhanced
CREATE TABLE audit_trail (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NULL,
    action_type VARCHAR(50) NOT NULL,
    resource_type VARCHAR(50) NOT NULL,
    resource_id VARCHAR(50),
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    session_id VARCHAR(128),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user_action_time (user_id, action_type, created_at),
    INDEX idx_resource_time (resource_type, resource_id, created_at)
);