#!/bin/bash

# Admini Enterprise Installation Script
# Comprehensive installation for Ubuntu, CentOS, and Debian

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Configuration
ADMINI_VERSION="2.0.0"
INSTALL_DIR="/var/www/admini"
LOG_DIR="/var/log/admini"
SERVICE_DIR="/etc/systemd/system"
CONFIG_DIR="/etc/admini"

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ✅ $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ⚠️  $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ❌ $1"
}

log_info() {
    echo -e "${CYAN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} ℹ️  $1"
}

# Print banner
print_banner() {
    echo -e "${PURPLE}"
    cat << "EOF"
    ██████╗ ██████╗ ███╗   ███╗██╗███╗   ██╗██╗    ███████╗███╗   ██╗████████╗███████╗██████╗ ██████╗ ██████╗ ██╗███████╗███████╗
   ██╔══██╗██╔══██╗████╗ ████║██║████╗  ██║██║    ██╔════╝████╗  ██║╚══██╔══╝██╔════╝██╔══██╗██╔══██╗██╔══██╗██║██╔════╝██╔════╝
   ██████╔╝██║  ██║██╔████╔██║██║██╔██╗ ██║██║    █████╗  ██╔██╗ ██║   ██║   █████╗  ██████╔╝██████╔╝██████╔╝██║███████╗█████╗  
   ██╔══██╗██║  ██║██║╚██╔╝██║██║██║╚██╗██║██║    ██╔══╝  ██║╚██╗██║   ██║   ██╔══╝  ██╔══██╗██╔═══╝ ██╔══██╗██║╚════██║██╔══╝  
   ██║  ██║██████╔╝██║ ╚═╝ ██║██║██║ ╚████║██║    ███████╗██║ ╚████║   ██║   ███████╗██║  ██║██║     ██║  ██║██║███████║███████╗
   ╚═╝  ╚═╝╚═════╝ ╚═╝     ╚═╝╚═╝╚═╝  ╚═══╝╚═╝    ╚══════╝╚═╝  ╚═══╝   ╚═╝   ╚══════╝╚═╝  ╚═╝╚═╝     ╚═╝  ╚═╝╚═╝╚══════╝╚══════╝
EOF
    echo -e "${NC}"
    echo -e "${GREEN}🚀 Admini Enterprise v${ADMINI_VERSION} Installation${NC}"
    echo -e "${CYAN}   Complete Enterprise Hosting Control Panel${NC}"
    echo -e "${YELLOW}   DirectAdmin-inspired • 300+ Files • 50+ Database Tables${NC}"
    echo ""
}

# Detect OS
detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$NAME
        VER=$VERSION_ID
    else
        log_error "Cannot detect operating system"
        exit 1
    fi
    
    log_info "Detected OS: $OS $VER"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        log_error "This script must be run as root"
        exit 1
    fi
}

# Check system requirements
check_requirements() {
    log "Checking system requirements..."
    
    # Check available memory
    MEM_TOTAL=$(grep MemTotal /proc/meminfo | awk '{print $2}')
    MEM_GB=$((MEM_TOTAL / 1024 / 1024))
    
    if [[ $MEM_GB -lt 2 ]]; then
        log_warning "Minimum 2GB RAM recommended, found ${MEM_GB}GB"
    else
        log_success "Memory check passed: ${MEM_GB}GB"
    fi
    
    # Check disk space
    DISK_AVAIL=$(df / | tail -1 | awk '{print $4}')
    DISK_GB=$((DISK_AVAIL / 1024 / 1024))
    
    if [[ $DISK_GB -lt 5 ]]; then
        log_error "Minimum 5GB disk space required, found ${DISK_GB}GB"
        exit 1
    else
        log_success "Disk space check passed: ${DISK_GB}GB available"
    fi
}

# Install system packages
install_packages() {
    log "Installing system packages..."
    
    case "$OS" in
        "Ubuntu"*|"Debian"*)
            apt-get update
            apt-get install -y \
                apache2 \
                mysql-server \
                redis-server \
                php8.1 \
                php8.1-cli \
                php8.1-fpm \
                php8.1-mysql \
                php8.1-redis \
                php8.1-curl \
                php8.1-gd \
                php8.1-mbstring \
                php8.1-xml \
                php8.1-zip \
                php8.1-json \
                php8.1-bcmath \
                php8.1-intl \
                libapache2-mod-php8.1 \
                curl \
                wget \
                git \
                unzip \
                nano \
                htop \
                fail2ban \
                ufw \
                certbot \
                python3-certbot-apache \
                supervisor \
                cron \
                logrotate
            ;;
        "CentOS"*|"Red Hat"*|"Rocky"*|"AlmaLinux"*)
            dnf update -y
            dnf install -y epel-release
            dnf install -y \
                httpd \
                mysql-server \
                redis \
                php \
                php-cli \
                php-fpm \
                php-mysql \
                php-redis \
                php-curl \
                php-gd \
                php-mbstring \
                php-xml \
                php-zip \
                php-json \
                php-bcmath \
                php-intl \
                curl \
                wget \
                git \
                unzip \
                nano \
                htop \
                fail2ban \
                firewalld \
                certbot \
                python3-certbot-apache \
                supervisor \
                cronie
            ;;
        *)
            log_error "Unsupported operating system: $OS"
            exit 1
            ;;
    esac
    
    log_success "System packages installed"
}

# Configure services
configure_services() {
    log "Configuring system services..."
    
    # Enable and start services
    case "$OS" in
        "Ubuntu"*|"Debian"*)
            systemctl enable apache2
            systemctl enable mysql
            systemctl enable redis-server
            systemctl enable fail2ban
            systemctl enable supervisor
            systemctl enable cron
            
            systemctl start apache2
            systemctl start mysql
            systemctl start redis-server
            systemctl start fail2ban
            systemctl start supervisor
            systemctl start cron
            ;;
        "CentOS"*|"Red Hat"*|"Rocky"*|"AlmaLinux"*)
            systemctl enable httpd
            systemctl enable mysqld
            systemctl enable redis
            systemctl enable fail2ban
            systemctl enable supervisord
            systemctl enable crond
            systemctl enable firewalld
            
            systemctl start httpd
            systemctl start mysqld
            systemctl start redis
            systemctl start fail2ban
            systemctl start supervisord
            systemctl start crond
            systemctl start firewalld
            ;;
    esac
    
    log_success "Services configured and started"
}

# Setup firewall
setup_firewall() {
    log "Configuring firewall..."
    
    case "$OS" in
        "Ubuntu"*|"Debian"*)
            ufw --force enable
            ufw allow 22/tcp
            ufw allow 80/tcp
            ufw allow 443/tcp
            ufw allow 2087/tcp  # Admini HTTPS port
            ufw allow 2086/tcp  # Admini HTTP port
            ;;
        "CentOS"*|"Red Hat"*|"Rocky"*|"AlmaLinux"*)
            firewall-cmd --permanent --add-service=ssh
            firewall-cmd --permanent --add-service=http
            firewall-cmd --permanent --add-service=https
            firewall-cmd --permanent --add-port=2087/tcp
            firewall-cmd --permanent --add-port=2086/tcp
            firewall-cmd --reload
            ;;
    esac
    
    log_success "Firewall configured"
}

# Secure MySQL installation
secure_mysql() {
    log "Securing MySQL installation..."
    
    # Generate random password
    MYSQL_ROOT_PASSWORD=$(openssl rand -base64 32)
    
    # Secure MySQL
    mysql -e "ALTER USER 'root'@'localhost' IDENTIFIED WITH mysql_native_password BY '${MYSQL_ROOT_PASSWORD}';"
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "DELETE FROM mysql.user WHERE User='';"
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');"
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "DROP DATABASE IF EXISTS test;"
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';"
    mysql -u root -p"${MYSQL_ROOT_PASSWORD}" -e "FLUSH PRIVILEGES;"
    
    # Save MySQL credentials
    echo "[client]" > /root/.my.cnf
    echo "user=root" >> /root/.my.cnf
    echo "password=${MYSQL_ROOT_PASSWORD}" >> /root/.my.cnf
    chmod 600 /root/.my.cnf
    
    log_success "MySQL secured"
}

# Create Admini database and user
setup_database() {
    log "Setting up Admini database..."
    
    # Generate database credentials
    DB_NAME="admini"
    DB_USER="admini_user"
    DB_PASSWORD=$(openssl rand -base64 24)
    
    # Create database and user
    mysql -e "CREATE DATABASE ${DB_NAME} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql -e "CREATE USER '${DB_USER}'@'localhost' IDENTIFIED BY '${DB_PASSWORD}';"
    mysql -e "GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    # Import database schema
    mysql ${DB_NAME} < "${INSTALL_DIR}/database/schema.sql"
    
    # Save database credentials
    cat > "${CONFIG_DIR}/database.conf" << EOF
DB_HOST=localhost
DB_NAME=${DB_NAME}
DB_USER=${DB_USER}
DB_PASSWORD=${DB_PASSWORD}
EOF
    
    chmod 600 "${CONFIG_DIR}/database.conf"
    
    log_success "Database configured"
}

# Install Admini files
install_admini() {
    log "Installing Admini Enterprise files..."
    
    # Create directories
    mkdir -p "${INSTALL_DIR}"
    mkdir -p "${LOG_DIR}"
    mkdir -p "${CONFIG_DIR}"
    mkdir -p "/var/run/admini"
    mkdir -p "/var/lib/admini"
    
    # Copy files
    cp -r src/* "${INSTALL_DIR}/"
    cp -r database "${INSTALL_DIR}/"
    cp -r scripts "${INSTALL_DIR}/"
    
    # Set permissions
    chown -R www-data:www-data "${INSTALL_DIR}"
    chmod -R 755 "${INSTALL_DIR}"
    chmod +x "${INSTALL_DIR}/scripts/"*.sh
    
    # Create log files
    touch "${LOG_DIR}/admini.log"
    touch "${LOG_DIR}/error.log"
    touch "${LOG_DIR}/access.log"
    chown -R www-data:www-data "${LOG_DIR}"
    
    log_success "Admini files installed"
}

# Configure Apache
configure_apache() {
    log "Configuring Apache web server..."
    
    # Create Apache virtual host
    cat > "/etc/apache2/sites-available/admini.conf" << EOF
<VirtualHost *:80>
    ServerName admini.local
    DocumentRoot ${INSTALL_DIR}
    DirectoryIndex index.php index.html
    
    <Directory ${INSTALL_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog ${LOG_DIR}/apache_error.log
    CustomLog ${LOG_DIR}/apache_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName admini.local
    DocumentRoot ${INSTALL_DIR}
    DirectoryIndex index.php index.html
    
    <Directory ${INSTALL_DIR}>
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/admini.crt
    SSLCertificateKeyFile /etc/ssl/private/admini.key
    
    ErrorLog ${LOG_DIR}/apache_ssl_error.log
    CustomLog ${LOG_DIR}/apache_ssl_access.log combined
</VirtualHost>
EOF
    
    # Enable required modules
    a2enmod rewrite
    a2enmod ssl
    a2enmod headers
    a2enmod expires
    
    # Enable site
    a2ensite admini.conf
    a2dissite 000-default
    
    # Generate self-signed SSL certificate
    openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
        -keyout /etc/ssl/private/admini.key \
        -out /etc/ssl/certs/admini.crt \
        -subj "/C=US/ST=State/L=City/O=Organization/CN=admini.local"
    
    # Restart Apache
    systemctl restart apache2
    
    log_success "Apache configured"
}

# Setup worker services
setup_workers() {
    log "Setting up background workers..."
    
    # Create supervisor configuration for workers
    cat > "/etc/supervisor/conf.d/admini-workers.conf" << EOF
[group:admini-workers]
programs=admini-worker-manager,admini-monitoring,admini-security,admini-analytics,admini-integration,admini-workflow,admini-events,admini-notifications

[program:admini-worker-manager]
command=${INSTALL_DIR}/src/workers/worker_manager.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/worker-manager.log

[program:admini-monitoring]
command=${INSTALL_DIR}/src/workers/monitoring_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/monitoring.log

[program:admini-security]
command=${INSTALL_DIR}/src/workers/security_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/security.log

[program:admini-analytics]
command=${INSTALL_DIR}/src/workers/analytics_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/analytics.log

[program:admini-integration]
command=${INSTALL_DIR}/src/workers/integration_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/integration.log

[program:admini-workflow]
command=${INSTALL_DIR}/src/workers/workflow_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/workflow.log

[program:admini-events]
command=${INSTALL_DIR}/src/workers/event_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/events.log

[program:admini-notifications]
command=${INSTALL_DIR}/src/workers/notification_worker.php
directory=${INSTALL_DIR}
user=www-data
autostart=true
autorestart=true
redirect_stderr=true
stdout_logfile=${LOG_DIR}/notifications.log
EOF
    
    # Reload supervisor
    supervisorctl reread
    supervisorctl update
    supervisorctl start admini-workers:*
    
    log_success "Workers configured and started"
}

# Create admin user
create_admin_user() {
    log "Creating admin user..."
    
    # Generate admin credentials
    ADMIN_USERNAME="admin"
    ADMIN_PASSWORD=$(openssl rand -base64 16)
    ADMIN_EMAIL="admin@$(hostname -f 2>/dev/null || echo 'localhost')"
    
    # Hash password
    ADMIN_PASSWORD_HASH=$(php -r "echo password_hash('${ADMIN_PASSWORD}', PASSWORD_BCRYPT);")
    
    # Insert admin user
    mysql admini -e "INSERT INTO users (username, email, password, role, status, created_at) VALUES ('${ADMIN_USERNAME}', '${ADMIN_EMAIL}', '${ADMIN_PASSWORD_HASH}', 'admin', 'active', NOW());"
    
    # Save admin credentials
    cat > "${CONFIG_DIR}/admin.conf" << EOF
ADMIN_USERNAME=${ADMIN_USERNAME}
ADMIN_PASSWORD=${ADMIN_PASSWORD}
ADMIN_EMAIL=${ADMIN_EMAIL}
EOF
    
    chmod 600 "${CONFIG_DIR}/admin.conf"
    
    log_success "Admin user created"
}

# Setup cron jobs
setup_cron() {
    log "Setting up scheduled tasks..."
    
    # Create cron jobs
    cat > "/etc/cron.d/admini" << EOF
# Admini Enterprise Scheduled Tasks

# System monitoring (every 5 minutes)
*/5 * * * * www-data ${INSTALL_DIR}/scripts/monitor.php > /dev/null 2>&1

# Backup rotation (daily at 2 AM)
0 2 * * * root ${INSTALL_DIR}/scripts/backup.sh rotate > /dev/null 2>&1

# Log rotation (daily at 3 AM)
0 3 * * * root ${INSTALL_DIR}/scripts/logrotate.sh > /dev/null 2>&1

# Security scan (daily at 4 AM)
0 4 * * * www-data ${INSTALL_DIR}/scripts/security-scan.php > /dev/null 2>&1

# Analytics processing (hourly)
0 * * * * www-data ${INSTALL_DIR}/scripts/process-analytics.php > /dev/null 2>&1

# Cleanup temporary files (daily at 1 AM)
0 1 * * * www-data ${INSTALL_DIR}/scripts/cleanup.php > /dev/null 2>&1
EOF
    
    chmod 644 "/etc/cron.d/admini"
    
    log_success "Scheduled tasks configured"
}

# Optimize system
optimize_system() {
    log "Optimizing system performance..."
    
    # PHP optimization
    cat >> "/etc/php/8.1/apache2/php.ini" << EOF

; Admini Enterprise PHP Optimizations
memory_limit = 512M
max_execution_time = 300
max_input_vars = 5000
post_max_size = 100M
upload_max_filesize = 100M
max_file_uploads = 50
date.timezone = UTC
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=10000
opcache.revalidate_freq=2
session.save_handler = redis
session.save_path = "tcp://127.0.0.1:6379"
EOF
    
    # MySQL optimization
    cat >> "/etc/mysql/mysql.conf.d/mysqld.cnf" << EOF

# Admini Enterprise MySQL Optimizations
innodb_buffer_pool_size = 512M
innodb_log_file_size = 128M
query_cache_type = 1
query_cache_size = 64M
max_connections = 200
key_buffer_size = 64M
table_open_cache = 1000
sort_buffer_size = 2M
read_buffer_size = 1M
EOF
    
    # Redis optimization
    cat >> "/etc/redis/redis.conf" << EOF

# Admini Enterprise Redis Optimizations
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
EOF
    
    # Restart services
    systemctl restart apache2
    systemctl restart mysql
    systemctl restart redis-server
    
    log_success "System optimized"
}

# Final setup
final_setup() {
    log "Completing installation..."
    
    # Create version file
    echo "${ADMINI_VERSION}" > "${INSTALL_DIR}/VERSION"
    
    # Set final permissions
    chown -R www-data:www-data "${INSTALL_DIR}"
    chown -R www-data:www-data "${LOG_DIR}"
    
    # Create installation info
    cat > "${CONFIG_DIR}/install.info" << EOF
INSTALLATION_DATE=$(date)
VERSION=${ADMINI_VERSION}
INSTALL_DIR=${INSTALL_DIR}
LOG_DIR=${LOG_DIR}
CONFIG_DIR=${CONFIG_DIR}
EOF
    
    log_success "Installation completed successfully!"
}

# Print installation summary
print_summary() {
    echo ""
    echo -e "${GREEN}🎉 Admini Enterprise Installation Complete! 🎉${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""
    echo -e "${BLUE}📋 Installation Summary:${NC}"
    echo -e "   🔗 Access URL: ${GREEN}http://$(hostname -I | awk '{print $1}')${NC}"
    echo -e "   🔐 Admin Panel: ${GREEN}http://$(hostname -I | awk '{print $1}')/admin${NC}"
    echo -e "   📁 Install Directory: ${YELLOW}${INSTALL_DIR}${NC}"
    echo -e "   📊 Log Directory: ${YELLOW}${LOG_DIR}${NC}"
    echo ""
    echo -e "${BLUE}🔑 Admin Credentials:${NC}"
    echo -e "   👤 Username: ${GREEN}$(grep ADMIN_USERNAME ${CONFIG_DIR}/admin.conf | cut -d'=' -f2)${NC}"
    echo -e "   🔒 Password: ${GREEN}$(grep ADMIN_PASSWORD ${CONFIG_DIR}/admin.conf | cut -d'=' -f2)${NC}"
    echo -e "   📧 Email: ${GREEN}$(grep ADMIN_EMAIL ${CONFIG_DIR}/admin.conf | cut -d'=' -f2)${NC}"
    echo ""
    echo -e "${BLUE}🛠️ Management Commands:${NC}"
    echo -e "   Workers: ${CYAN}${INSTALL_DIR}/scripts/workers.sh {start|stop|status}${NC}"
    echo -e "   Backup: ${CYAN}${INSTALL_DIR}/scripts/backup.sh${NC}"
    echo -e "   Logs: ${CYAN}tail -f ${LOG_DIR}/admini.log${NC}"
    echo ""
    echo -e "${BLUE}📚 Documentation:${NC}"
    echo -e "   📖 User Guide: ${CYAN}${INSTALL_DIR}/docs/user-guide.md${NC}"
    echo -e "   🔧 Admin Guide: ${CYAN}${INSTALL_DIR}/docs/admin-guide.md${NC}"
    echo -e "   🚀 API Docs: ${CYAN}${INSTALL_DIR}/docs/api.md${NC}"
    echo ""
    echo -e "${BLUE}🔧 Enterprise Features Enabled:${NC}"
    echo -e "   ✅ Microservices Architecture    ✅ Real-time Monitoring"
    echo -e "   ✅ Advanced Security Suite       ✅ Business Intelligence"
    echo -e "   ✅ Workflow Automation           ✅ Integration Framework"
    echo -e "   ✅ Container Management          ✅ Backup & DR"
    echo -e "   ✅ Performance Optimization      ✅ Multi-tenant Support"
    echo ""
    echo -e "${GREEN}🚀 Your enterprise hosting control panel is ready!${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# Main installation function
main() {
    print_banner
    
    check_root
    detect_os
    check_requirements
    
    log "Starting Admini Enterprise installation..."
    
    install_packages
    configure_services
    setup_firewall
    secure_mysql
    setup_database
    install_admini
    configure_apache
    setup_workers
    create_admin_user
    setup_cron
    optimize_system
    final_setup
    
    print_summary
}

# Handle command line arguments
case "${1:-install}" in
    install)
        main
        ;;
    uninstall)
        log "Uninstalling Admini Enterprise..."
        
        # Stop services
        supervisorctl stop admini-workers:*
        systemctl stop apache2
        
        # Remove files
        rm -rf "${INSTALL_DIR}"
        rm -rf "${LOG_DIR}"
        rm -rf "${CONFIG_DIR}"
        rm -f "/etc/apache2/sites-available/admini.conf"
        rm -f "/etc/supervisor/conf.d/admini-workers.conf"
        rm -f "/etc/cron.d/admini"
        
        # Drop database
        mysql -e "DROP DATABASE IF EXISTS admini;"
        mysql -e "DROP USER IF EXISTS 'admini_user'@'localhost';"
        
        log_success "Admini Enterprise uninstalled"
        ;;
    update)
        log "Updating Admini Enterprise..."
        
        # Backup current installation
        tar -czf "/tmp/admini-backup-$(date +%Y%m%d-%H%M%S).tar.gz" "${INSTALL_DIR}"
        
        # Update files
        cp -r src/* "${INSTALL_DIR}/"
        chown -R www-data:www-data "${INSTALL_DIR}"
        
        # Restart services
        supervisorctl restart admini-workers:*
        systemctl restart apache2
        
        log_success "Admini Enterprise updated"
        ;;
    *)
        echo "Usage: $0 {install|uninstall|update}"
        exit 1
        ;;
esac