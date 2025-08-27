#!/bin/bash

# Admini Control Panel Installation Script
# Compatible with Ubuntu, CentOS, and Debian

set -e

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Default values
INSTALL_DIR="/usr/local/admini"
WEB_DIR="/var/www/admini"
DB_NAME="admini"
DB_USER="admini"
DB_PASS=""
ADMIN_USER="admin"
ADMIN_PASS=""
DOMAIN=""

# Print colored output
print_status() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

print_warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

print_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Check if running as root
check_root() {
    if [[ $EUID -ne 0 ]]; then
        print_error "This script must be run as root"
        exit 1
    fi
}

# Detect OS
detect_os() {
    if [[ -f /etc/os-release ]]; then
        . /etc/os-release
        OS=$ID
        OS_VERSION=$VERSION_ID
    else
        print_error "Cannot detect operating system"
        exit 1
    fi
    
    print_status "Detected OS: $OS $OS_VERSION"
}

# Install dependencies based on OS
install_dependencies() {
    print_status "Installing dependencies..."
    
    case $OS in
        ubuntu|debian)
            apt-get update
            apt-get install -y \
                apache2 \
                php8.1 \
                php8.1-mysql \
                php8.1-curl \
                php8.1-gd \
                php8.1-mbstring \
                php8.1-xml \
                php8.1-zip \
                php8.1-intl \
                php8.1-bcmath \
                mysql-server \
                curl \
                wget \
                unzip \
                git \
                certbot \
                python3-certbot-apache
            ;;
        centos|rhel|fedora)
            yum update -y
            yum install -y \
                httpd \
                php \
                php-mysql \
                php-curl \
                php-gd \
                php-mbstring \
                php-xml \
                php-zip \
                php-intl \
                php-bcmath \
                mysql-server \
                curl \
                wget \
                unzip \
                git \
                certbot \
                python3-certbot-apache
            ;;
        *)
            print_error "Unsupported operating system: $OS"
            exit 1
            ;;
    esac
}

# Configure database
setup_database() {
    print_status "Setting up database..."
    
    # Generate random password if not provided
    if [[ -z "$DB_PASS" ]]; then
        DB_PASS=$(openssl rand -base64 32)
    fi
    
    # Start MySQL service
    systemctl start mysql
    systemctl enable mysql
    
    # Create database and user
    mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
    mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
    mysql -e "FLUSH PRIVILEGES;"
    
    print_status "Database configured successfully"
}

# Install Admini files
install_admini() {
    print_status "Installing Admini files..."
    
    # Create directories
    mkdir -p $INSTALL_DIR
    mkdir -p $WEB_DIR
    
    # Copy files
    cp -r ../src/* $WEB_DIR/
    cp -r ../database $INSTALL_DIR/
    
    # Set permissions
    chown -R www-data:www-data $WEB_DIR
    chmod -R 755 $WEB_DIR
    chmod -R 777 $WEB_DIR/assets/uploads
    
    print_status "Files installed successfully"
}

# Configure Apache
configure_apache() {
    print_status "Configuring Apache..."
    
    # Create virtual host
    cat > /etc/apache2/sites-available/admini.conf << EOF
<VirtualHost *:80>
    ServerName $DOMAIN
    DocumentRoot $WEB_DIR
    
    <Directory $WEB_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/admini_error.log
    CustomLog \${APACHE_LOG_DIR}/admini_access.log combined
</VirtualHost>

<VirtualHost *:443>
    ServerName $DOMAIN
    DocumentRoot $WEB_DIR
    
    SSLEngine on
    SSLCertificateFile /etc/ssl/certs/admini.crt
    SSLCertificateKeyFile /etc/ssl/private/admini.key
    
    <Directory $WEB_DIR>
        AllowOverride All
        Require all granted
    </Directory>
    
    ErrorLog \${APACHE_LOG_DIR}/admini_ssl_error.log
    CustomLog \${APACHE_LOG_DIR}/admini_ssl_access.log combined
</VirtualHost>
EOF
    
    # Enable modules and site
    a2enmod rewrite
    a2enmod ssl
    a2ensite admini
    a2dissite 000-default
    
    systemctl restart apache2
    
    print_status "Apache configured successfully"
}

# Initialize database schema
init_database() {
    print_status "Initializing database schema..."
    
    # Create config file
    cat > $WEB_DIR/config/database.php << EOF
<?php
return [
    'host' => 'localhost',
    'database' => '$DB_NAME',
    'username' => '$DB_USER',
    'password' => '$DB_PASS',
    'charset' => 'utf8mb4',
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
];
EOF
    
    # Run database migrations
    mysql -u$DB_USER -p$DB_PASS $DB_NAME < $INSTALL_DIR/database/schema.sql
    
    print_status "Database initialized successfully"
}

# Create admin user
create_admin() {
    print_status "Creating admin user..."
    
    if [[ -z "$ADMIN_PASS" ]]; then
        ADMIN_PASS=$(openssl rand -base64 16)
    fi
    
    # Hash password
    ADMIN_PASS_HASH=$(php -r "echo password_hash('$ADMIN_PASS', PASSWORD_DEFAULT);")
    
    # Insert admin user
    mysql -u$DB_USER -p$DB_PASS $DB_NAME -e "
        INSERT INTO users (username, email, password, role, status, created_at) 
        VALUES ('$ADMIN_USER', 'admin@$DOMAIN', '$ADMIN_PASS_HASH', 'admin', 'active', NOW())
        ON DUPLICATE KEY UPDATE 
        password='$ADMIN_PASS_HASH', updated_at=NOW();
    "
    
    print_status "Admin user created successfully"
}

# Display final information
show_completion() {
    print_status "Installation completed successfully!"
    echo
    echo -e "${BLUE}=== Admini Installation Details ===${NC}"
    echo -e "Panel URL: ${GREEN}https://$DOMAIN${NC}"
    echo -e "Admin Username: ${GREEN}$ADMIN_USER${NC}"
    echo -e "Admin Password: ${GREEN}$ADMIN_PASS${NC}"
    echo -e "Database Name: ${GREEN}$DB_NAME${NC}"
    echo -e "Database User: ${GREEN}$DB_USER${NC}"
    echo -e "Database Password: ${GREEN}$DB_PASS${NC}"
    echo
    echo -e "${YELLOW}Please save these credentials in a secure location!${NC}"
    echo -e "${YELLOW}You can access the control panel at: https://$DOMAIN${NC}"
}

# Main installation function
main() {
    print_status "Starting Admini Control Panel installation..."
    
    # Get user input
    read -p "Enter domain name for the control panel (e.g., panel.yourdomain.com): " DOMAIN
    read -s -p "Enter admin password (leave empty for auto-generated): " ADMIN_PASS
    echo
    
    check_root
    detect_os
    install_dependencies
    setup_database
    install_admini
    configure_apache
    init_database
    create_admin
    show_completion
}

# Run main function
main "$@"