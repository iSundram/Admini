#!/bin/bash

###############################################################################
# setup.sh - Admini Control Panel Installation Script
# 
# This script automates the installation of Admini Control Panel.
# Usage: ./setup.sh [options]
#
# Options:
#   --help         Show this help message
#   --port PORT    Set custom port (default: 2222)
#   --auto         Automatic installation with defaults
#   --dev          Development mode installation
#
# Environment variables:
#   ADMINI_PORT         : Port to run the server (default: 2222)
#   ADMINI_ADMIN_USER   : Admin username (default: admin)
#   ADMINI_ADMIN_PASS   : Admin password (auto-generated if not set)
#   ADMINI_EMAIL        : Admin email address
#   ADMINI_HOSTNAME     : Server hostname (auto-detected)
#   ADMINI_INSTALL_DIR  : Installation directory (default: /usr/local/admini)
#
###############################################################################

set -e

# Colors for output
color_reset=$(printf '\033[0m')
color_green=$(printf '\033[32m')
color_red=$(printf '\033[31m')
color_yellow=$(printf '\033[33m')
color_blue=$(printf '\033[34m')

echogreen() {
    echo "[setup.sh] ${color_green}$*${color_reset}"
}

echored() {
    echo "[setup.sh] ${color_red}$*${color_reset}"
}

echoyellow() {
    echo "[setup.sh] ${color_yellow}$*${color_reset}"
}

echoblue() {
    echo "[setup.sh] ${color_blue}$*${color_reset}"
}

# Default configuration
ADMINI_PORT=${ADMINI_PORT:-2222}
ADMINI_ADMIN_USER=${ADMINI_ADMIN_USER:-admin}
ADMINI_ADMIN_PASS=${ADMINI_ADMIN_PASS:-$(openssl rand -base64 12)}
ADMINI_EMAIL=${ADMINI_EMAIL:-admin@$(hostname -f)}
ADMINI_HOSTNAME=${ADMINI_HOSTNAME:-$(hostname -f)}
ADMINI_INSTALL_DIR=${ADMINI_INSTALL_DIR:-/usr/local/admini}
AUTO_INSTALL=false
DEV_MODE=false

# Parse command line arguments
while [[ $# -gt 0 ]]; do
    case $1 in
        --help|help|-h)
            echo ""
            echo "Usage: $0 [options]"
            echo ""
            echo "Options:"
            echo "  --help         Show this help message"
            echo "  --port PORT    Set custom port (default: 2222)"
            echo "  --auto         Automatic installation with defaults"
            echo "  --dev          Development mode installation"
            echo ""
            echo "Environment variables:"
            echo "  ADMINI_PORT         : Port to run the server (default: 2222)"
            echo "  ADMINI_ADMIN_USER   : Admin username (default: admin)"
            echo "  ADMINI_ADMIN_PASS   : Admin password (auto-generated if not set)"
            echo "  ADMINI_EMAIL        : Admin email address"
            echo "  ADMINI_HOSTNAME     : Server hostname (auto-detected)"
            echo "  ADMINI_INSTALL_DIR  : Installation directory (default: /usr/local/admini)"
            echo ""
            exit 0
            ;;
        --port)
            ADMINI_PORT="$2"
            shift 2
            ;;
        --auto)
            AUTO_INSTALL=true
            shift
            ;;
        --dev)
            DEV_MODE=true
            shift
            ;;
        *)
            echored "Unknown option: $1"
            echo "Use --help for usage information"
            exit 1
            ;;
    esac
done

# System requirements check
check_system_requirements() {
    echoblue "Checking system requirements..."
    
    # Check if running as root
    if [[ $EUID -ne 0 ]]; then
        echored "This script must be run as root"
        exit 1
    fi
    
    # Check OS compatibility
    if [ ! -f /etc/os-release ]; then
        echored "Cannot detect operating system"
        exit 1
    fi
    
    source /etc/os-release
    
    case "$ID" in
        ubuntu|debian)
            PKG_MANAGER="apt"
            INSTALL_CMD="apt install -y"
            UPDATE_CMD="apt update && apt upgrade -y"
            ;;
        centos|rhel|rocky|almalinux|cloudlinux)
            PKG_MANAGER="yum"
            INSTALL_CMD="yum install -y"
            UPDATE_CMD="yum update -y"
            ;;
        *)
            echored "Unsupported operating system: $ID"
            echored "Supported: Ubuntu, Debian, CentOS, RHEL, Rocky Linux, AlmaLinux"
            exit 1
            ;;
    esac
    
    # Check for required commands
    for cmd in curl wget git; do
        if ! command -v $cmd &> /dev/null; then
            echoyellow "$cmd not found, will install during setup"
            MISSING_PACKAGES="$MISSING_PACKAGES $cmd"
        fi
    done
    
    # Check Go installation for development mode
    if [ "$DEV_MODE" = true ]; then
        if ! command -v go &> /dev/null; then
            echoyellow "Go not found, will install during setup"
            MISSING_PACKAGES="$MISSING_PACKAGES golang"
        fi
    fi
    
    echogreen "System requirements check completed"
}

# Install system dependencies
install_dependencies() {
    echoblue "Installing system dependencies..."
    
    # Update system packages
    $UPDATE_CMD
    
    # Install base packages
    if [ "$PKG_MANAGER" = "apt" ]; then
        $INSTALL_CMD curl wget git build-essential
        if [ "$DEV_MODE" = true ]; then
            $INSTALL_CMD golang-go
        fi
    else
        $INSTALL_CMD curl wget git gcc gcc-c++ make
        if [ "$DEV_MODE" = true ]; then
            $INSTALL_CMD golang
        fi
    fi
    
    echogreen "Dependencies installed successfully"
}

# Download and setup Admini
setup_admini() {
    echoblue "Setting up Admini Control Panel..."
    
    # Create installation directory
    mkdir -p $ADMINI_INSTALL_DIR
    cd $ADMINI_INSTALL_DIR
    
    if [ "$DEV_MODE" = true ]; then
        # Development installation - clone repository
        echoblue "Cloning Admini repository..."
        if [ -d "Admini" ]; then
            rm -rf Admini
        fi
        git clone https://github.com/iSundram/Admini.git
        cd Admini/backend
        
        # Build from source
        echoblue "Building Admini from source..."
        go mod tidy
        go build -o admini
        chmod +x admini
        
        # Copy binary to install directory
        cp admini $ADMINI_INSTALL_DIR/
        cd $ADMINI_INSTALL_DIR
    else
        # Production installation - download binary
        echoblue "Downloading Admini binary..."
        # For now, we'll build from source until binary releases are available
        git clone https://github.com/iSundram/Admini.git
        cd Admini/backend
        
        # Check if Go is available, if not install it
        if ! command -v go &> /dev/null; then
            echoyellow "Go not found, installing..."
            if [ "$PKG_MANAGER" = "apt" ]; then
                apt install -y golang-go
            else
                yum install -y golang
            fi
        fi
        
        go mod tidy
        go build -o admini
        chmod +x admini
        
        # Copy binary and necessary files
        cp admini $ADMINI_INSTALL_DIR/
        cp -r static $ADMINI_INSTALL_DIR/
        cp -r templates $ADMINI_INSTALL_DIR/
        cp -r conf $ADMINI_INSTALL_DIR/
        cd $ADMINI_INSTALL_DIR
    fi
    
    echogreen "Admini setup completed"
}

# Configure Admini
configure_admini() {
    echoblue "Configuring Admini..."
    
    # Create configuration file
    cat > $ADMINI_INSTALL_DIR/conf/admini.conf << EOF
# Admini Control Panel Configuration
port=$ADMINI_PORT
hostname=$ADMINI_HOSTNAME
admin_user=$ADMINI_ADMIN_USER
admin_email=$ADMINI_EMAIL

# Security settings
session_timeout=3600
csrf_protection=true
ssl_redirect=false

# Database settings
db_type=file
data_dir=/usr/local/admini/data

# Logging
log_level=info
log_file=/var/log/admini.log
EOF
    
    # Set permissions
    chmod 600 $ADMINI_INSTALL_DIR/conf/admini.conf
    
    # Create data directory
    mkdir -p /usr/local/admini/data
    mkdir -p /var/log
    
    echogreen "Configuration completed"
}

# Create systemd service
create_service() {
    echoblue "Creating systemd service..."
    
    cat > /etc/systemd/system/admini.service << EOF
[Unit]
Description=Admini Control Panel
After=network.target

[Service]
Type=simple
User=root
WorkingDirectory=$ADMINI_INSTALL_DIR
ExecStart=$ADMINI_INSTALL_DIR/admini server --port $ADMINI_PORT
Restart=always
RestartSec=10

[Install]
WantedBy=multi-user.target
EOF
    
    # Reload systemd and enable service
    systemctl daemon-reload
    systemctl enable admini
    
    echogreen "Systemd service created and enabled"
}

# Setup firewall
setup_firewall() {
    echoblue "Configuring firewall..."
    
    # Try to open port using available firewall tools
    if command -v ufw &> /dev/null; then
        ufw allow $ADMINI_PORT/tcp
    elif command -v firewall-cmd &> /dev/null; then
        firewall-cmd --permanent --add-port=$ADMINI_PORT/tcp
        firewall-cmd --reload
    elif command -v iptables &> /dev/null; then
        iptables -A INPUT -p tcp --dport $ADMINI_PORT -j ACCEPT
        # Try to save iptables rules
        if command -v iptables-save &> /dev/null; then
            iptables-save > /etc/iptables/rules.v4 2>/dev/null || true
        fi
    else
        echoyellow "No firewall tool found. Please manually open port $ADMINI_PORT"
    fi
    
    echogreen "Firewall configuration completed"
}

# Main installation function
main_install() {
    echo ""
    echogreen "==================================="
    echogreen "  Admini Control Panel Installer"
    echogreen "==================================="
    echo ""
    
    echoblue "Installation configuration:"
    echo "  Port:             $ADMINI_PORT"
    echo "  Admin User:       $ADMINI_ADMIN_USER"
    echo "  Admin Email:      $ADMINI_EMAIL"
    echo "  Hostname:         $ADMINI_HOSTNAME"
    echo "  Install Dir:      $ADMINI_INSTALL_DIR"
    echo "  Development Mode: $DEV_MODE"
    echo ""
    
    if [ "$AUTO_INSTALL" = false ]; then
        echo "Press Enter to continue or Ctrl+C to cancel..."
        read -r
    fi
    
    check_system_requirements
    install_dependencies
    setup_admini
    configure_admini
    create_service
    setup_firewall
    
    # Start the service
    echoblue "Starting Admini service..."
    systemctl start admini
    
    echo ""
    echogreen "==================================="
    echogreen "  Installation Completed!"
    echogreen "==================================="
    echo ""
    echogreen "Admini Control Panel has been successfully installed!"
    echo ""
    echo "Access your control panel at:"
    echo "  http://$ADMINI_HOSTNAME:$ADMINI_PORT"
    echo "  or"
    echo "  http://$(hostname -I | awk '{print $1}'):$ADMINI_PORT"
    echo ""
    echo "Admin Credentials:"
    echo "  Username: $ADMINI_ADMIN_USER"
    echo "  Password: $ADMINI_ADMIN_PASS"
    echo ""
    echoyellow "Please save these credentials in a secure location!"
    echo ""
    echo "Service Management:"
    echo "  Start:   systemctl start admini"
    echo "  Stop:    systemctl stop admini"
    echo "  Restart: systemctl restart admini"
    echo "  Status:  systemctl status admini"
    echo "  Logs:    journalctl -u admini -f"
    echo ""
    echogreen "Installation completed successfully!"
}

# Run main installation
main_install
