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
    disk_quota BIGINT NOT NULL, -- in MB
    bandwidth_quota BIGINT NOT NULL, -- in MB
    domains_limit INT NOT NULL,
    subdomains_limit INT NOT NULL,
    email_accounts_limit INT NOT NULL,
    mysql_databases_limit INT NOT NULL,
    ftp_accounts_limit INT NOT NULL,
    price DECIMAL(10,2) DEFAULT 0.00,
    billing_cycle ENUM('monthly', 'quarterly', 'annually') DEFAULT 'monthly',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
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
INSERT INTO packages (name, description, disk_quota, bandwidth_quota, domains_limit, subdomains_limit, 
                     email_accounts_limit, mysql_databases_limit, ftp_accounts_limit, created_by) 
VALUES ('Basic Package', 'Default package for new users', 1024, 10240, 1, 10, 10, 5, 5, 1);

-- Update the foreign key reference
UPDATE packages SET created_by = 1 WHERE id = 1;