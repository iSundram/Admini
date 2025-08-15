# Admini Control Panel - Complete Documentation

## Table of Contents

1. [Introduction](#introduction)
2. [Features](#features)
3. [Installation](#installation)
4. [Configuration](#configuration)
5. [User Interfaces](#user-interfaces)
6. [API Documentation](#api-documentation)
7. [Administration](#administration)
8. [Troubleshooting](#troubleshooting)
9. [Migration from DirectAdmin](#migration-from-directadmin)
10. [Development](#development)

## Introduction

Admini is a comprehensive web hosting control panel system designed to provide users, resellers, and administrators with powerful tools for managing web hosting environments. Built with Go backend and featuring DirectAdmin-compatible skin system, Admini offers all the functionality of traditional control panels with enhanced performance and user experience.

### Key Components

- **AdminiCore**: Administrative interface using DirectAdmin skin system
- **AdminiReseller**: Reseller management panel with traditional DirectAdmin interface  
- **AdminiPanel**: User control panel with DirectAdmin-compatible skins (Evolution & Enhanced)

### System Requirements

- Linux (CentOS 7+, Ubuntu 18.04+, Debian 9+)
- 2GB RAM minimum (4GB recommended)
- 20GB free disk space minimum
- Root access
- Internet connection for initial setup

## Features

### Core Features

#### File Management
- **File Manager**: Web-based file browser with upload, download, edit capabilities
- **FTP Accounts**: Create and manage FTP accounts with quota controls
- **FTP Connections**: Monitor active FTP connections
- **Backup & Restore**: Automated backup scheduling and restoration
- **Disk Usage**: Real-time disk space monitoring and reporting
- **Web Disk**: WebDAV access to files

#### Database Management
- **MySQL Databases**: Complete MySQL database management
- **PostgreSQL Databases**: Full PostgreSQL support
- **phpMyAdmin**: Integrated web-based MySQL administration
- **phpPgAdmin**: Web-based PostgreSQL administration  
- **Remote Access**: Configure remote database connections
- **Database Users**: Granular user permission management

#### Domain Management
- **Subdomains**: Create and manage unlimited subdomains
- **Addon Domains**: Add additional domains to accounts
- **Parked Domains**: Domain parking functionality
- **Domain Redirects**: URL redirection management
- **DNS Zone Editor**: Complete DNS record management
- **Dynamic DNS**: DDNS support for dynamic IP addresses

#### Email Management
- **Email Accounts**: Unlimited email account creation
- **Email Forwarders**: Set up email forwarding rules
- **Autoresponders**: Automatic email response system
- **Mailing Lists**: Create and manage mailing lists
- **Email Filters**: Advanced email filtering system
- **Webmail Access**: Multiple webmail interfaces
- **Email Authentication**: SPF, DKIM, DMARC support
- **Email Encryption**: End-to-end email encryption

#### Security Features
- **SSL/TLS Management**: Complete SSL certificate management
- **Let's Encrypt Integration**: Free SSL certificate automation
- **IP Blocking**: Advanced IP-based access control
- **Two-Factor Authentication**: Enhanced account security
- **Password Protection**: Directory-level password protection
- **SSH Access**: Secure shell access management
- **Web Application Firewall**: Advanced threat protection

#### Software & Applications
- **Softaculous Integration**: 400+ one-click app installations
- **PHP Version Selector**: Multiple PHP version support
- **Node.js Support**: Node.js application hosting
- **Python Support**: Python application environment
- **Ruby Support**: Ruby on Rails hosting
- **Cron Jobs**: Scheduled task management
- **Git Integration**: Version control system support

#### Statistics & Monitoring
- **Visitor Statistics**: Comprehensive traffic analytics
- **Bandwidth Monitoring**: Real-time bandwidth usage
- **AWStats Integration**: Advanced web statistics
- **Error Log Monitoring**: System and application error tracking
- **Resource Usage**: CPU, memory, and I/O monitoring
- **Performance Optimization**: Automated performance tuning

### Advanced Features

#### Multi-User Management
- **User Accounts**: Unlimited user account creation
- **Reseller Accounts**: Multi-level reseller functionality
- **Package Management**: Customizable hosting packages
- **Resource Allocation**: Granular resource control
- **User Suspension**: Account suspension/unsuspension tools

#### API Integration
- **RESTful API**: Complete REST API for all functions
- **WebHooks**: Event-driven notifications
- **Third-party Integration**: Easy integration with external systems
- **Custom Applications**: SDK for custom application development

#### Customization
- **Custom Themes**: Fully customizable interface themes
- **White Label**: Complete branding customization
- **Plugin System**: Extensible plugin architecture
- **Custom Scripts**: User-defined automation scripts

## Installation

### Quick Installation

1. **Download the installer**:
   ```bash
   wget -O setup.sh https://raw.githubusercontent.com/iSundram/Admini/main/scripts/setup.sh
   chmod +x setup.sh
   ```

2. **Run the installation**:
   ```bash
   ./setup.sh
   ```

3. **Follow the installation wizard** which will:
   - Check system requirements
   - Install dependencies
   - Configure the database
   - Set up the web server
   - Create the admin account
   - Configure SSL certificates

### Manual Installation

#### Step 1: System Preparation

```bash
# Update system packages
yum update -y  # CentOS/RHEL
# or
apt update && apt upgrade -y  # Ubuntu/Debian

# Install required packages
yum install -y wget curl git  # CentOS/RHEL
# or  
apt install -y wget curl git  # Ubuntu/Debian
```

#### Step 2: Download Admini

```bash
cd /usr/local
git clone https://github.com/iSundram/Admini.git admini
cd admini
```

#### Step 3: Build the Application

```bash
cd backend
go build -o admini
chmod +x admini
```

#### Step 4: Configuration

```bash
# Copy configuration template
cp conf/admini.conf.template conf/admini.conf

# Edit configuration
nano conf/admini.conf
```

#### Step 5: Create System Service

```bash
cp scripts/admini.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable admini
systemctl start admini
```

### Post-Installation

1. **Access the web interface**: `https://your-server-ip:2222`
2. **Login with admin credentials** set during installation
3. **Complete initial configuration** through the setup wizard
4. **Configure DNS** to point to your server
5. **Set up SSL certificates** for the control panel

## Configuration

### Main Configuration File

The main configuration file is located at `/usr/local/admini/conf/admini.conf`:

```bash
# Server Configuration
server_name=your-server.com
port=2222
ssl_port=2222
ssl=1

# Admin Configuration  
admin=admin
admin_email=admin@your-server.com

# Database Configuration
mysql=1
mysql_host=localhost
mysql_port=3306
mysql_user=admini_admin
mysql_pass=secure_password

# Email Configuration
mail_server=dovecot
smtp_server=exim

# Security Configuration
secure_access_group=admini
password_check_script=scripts/password_check.php
enforce_difficult_passwords=1

# SSL Configuration
ssl_cert=/usr/local/admini/conf/cakey.pem
ssl_key=/usr/local/admini/conf/cakey.pem
ssl_ca=/usr/local/admini/conf/cacert.pem

# Feature Configuration
file_manager=1
webmail=1
squirrelmail=1
roundcube=1
phpmyadmin=1
```

### Environment Variables

Admini supports configuration via environment variables:

```bash
export ADMINI_PORT=2222
export ADMINI_SSL_PORT=2223
export ADMINI_ADMIN_USER=admin
export ADMINI_MYSQL_HOST=localhost
export ADMINI_MYSQL_PORT=3306
```

### Advanced Configuration

#### Custom Packages

Create custom hosting packages in `/usr/local/admini/data/packages/`:

```json
{
  "name": "Standard",
  "bandwidth": "10240",
  "quota": "1024", 
  "domains": "1",
  "subdomains": "unlimited",
  "email_accounts": "unlimited",
  "databases": "unlimited",
  "ftp_accounts": "unlimited",
  "ssl": true,
  "shell": false,
  "cgi": true,
  "php": true,
  "python": false,
  "nodejs": false
}
```

#### IP Configuration

Configure additional IPs in `/usr/local/admini/data/admin/ips.list`:

```
192.168.1.100
192.168.1.101
192.168.1.102
```

## User Interfaces

### AdminiCore (Administrator Interface)

AdminiCore is the primary administrative interface providing complete control over the hosting environment.

#### Dashboard Features
- **System Overview**: Real-time system status and statistics
- **User Management**: Create, modify, suspend/unsuspend users
- **Reseller Management**: Manage reseller accounts and permissions
- **Server Statistics**: Resource usage, performance metrics
- **System Configuration**: Global system settings
- **License Management**: License information and updates

#### User Account Management
- Create new user accounts with custom packages
- Modify existing user settings and limits
- Suspend/unsuspend user accounts
- View user statistics and resource usage
- Backup and restore user accounts

#### System Administration
- Server configuration management
- Service monitoring and control
- Log file management
- Security configuration
- Update management
- Plugin administration

### AdminiReseller (Reseller Interface)

AdminiReseller provides resellers with tools to manage their hosting business.

#### Reseller Dashboard
- **Client Overview**: List of all client accounts
- **Resource Usage**: Bandwidth, disk space, account limits
- **Package Management**: Create and modify hosting packages
- **Financial Tracking**: Revenue and usage statistics

#### Client Management
- Create new client accounts
- Modify client settings within limits
- Suspend/unsuspend client accounts
- View client resource usage
- Provide client support tools

### AdminiPanel (User Interface - cPanel Style)

AdminiPanel provides end-users with a familiar cPanel-style interface for managing their hosting account.

#### Main Dashboard
The main dashboard provides quick access to all account features organized by category:

##### Files & FTP
- **File Manager**: Web-based file management with drag-and-drop
- **FTP Accounts**: Create and manage FTP accounts
- **Backup**: Schedule and manage backups
- **Disk Usage**: View detailed disk usage statistics

##### Databases  
- **MySQL Databases**: Create and manage MySQL databases
- **PostgreSQL Databases**: PostgreSQL database management
- **phpMyAdmin**: Direct access to phpMyAdmin
- **Remote MySQL**: Configure remote access

##### Domains
- **Subdomains**: Create and manage subdomains
- **Addon Domains**: Add additional domains
- **Redirects**: Set up domain redirects
- **DNS Zone Editor**: Manage DNS records

##### Email
- **Email Accounts**: Create and manage email accounts
- **Forwarders**: Set up email forwarding
- **Autoresponders**: Configure automatic responses
- **Webmail**: Access webmail interfaces

##### Security
- **SSL/TLS**: Manage SSL certificates
- **Password Protect**: Protect directories
- **IP Blocker**: Block unwanted IPs

##### Software
- **Softaculous**: Install web applications
- **Cron Jobs**: Schedule automated tasks
- **PHP Selector**: Choose PHP version

#### Quick Actions Panel
The quick actions panel allows users to perform common tasks without navigating to specific pages:

- Create email accounts instantly
- Add new databases quickly
- Create subdomains on-the-fly
- Generate SSL certificates

#### Statistics Dashboard
Comprehensive statistics showing:
- Bandwidth usage over time
- Disk space utilization
- Email account usage
- Database sizes
- Visitor statistics

## API Documentation

Admini provides a comprehensive RESTful API for programmatic access to all control panel functions.

### Authentication

All API requests require authentication via API key:

```bash
curl -H "X-API-Key: your-api-key" https://your-server.com:2222/api/users
```

API keys can be generated through the admin interface or via CLI:

```bash
./admini api-key create --user=admin --name="My API Key"
```

### Endpoints

#### User Management

```bash
# Get all users
GET /api/users

# Get specific user
GET /api/users/{username}

# Create user
POST /api/users
{
  "username": "newuser",
  "password": "secure_password",
  "email": "user@example.com",
  "package": "standard"
}

# Update user
PUT /api/users/{username}
{
  "password": "new_password",
  "package": "premium"
}

# Delete user
DELETE /api/users/{username}

# Suspend user
POST /api/users/{username}/suspend

# Unsuspend user  
POST /api/users/{username}/unsuspend
```

#### Domain Management

```bash
# Get all domains
GET /api/domains

# Get user domains
GET /api/domains?user={username}

# Create domain
POST /api/domains
{
  "domain": "example.com",
  "user": "username",
  "document_root": "/domains/example.com/public_html"
}

# Update domain
PUT /api/domains/{domain}

# Delete domain
DELETE /api/domains/{domain}
```

#### Email Management

```bash
# Get email accounts
GET /api/email

# Create email account
POST /api/email
{
  "email": "user@example.com",
  "password": "secure_password",
  "quota": "1024"
}

# Update email account
PUT /api/email/{email}

# Delete email account
DELETE /api/email/{email}
```

#### Database Management

```bash
# Get databases
GET /api/databases

# Create database
POST /api/databases
{
  "name": "mydb",
  "user": "dbuser",
  "password": "dbpass"
}

# Delete database
DELETE /api/databases/{name}
```

### WebHooks

Admini supports webhooks for real-time notifications:

```bash
# Configure webhook
POST /api/webhooks
{
  "url": "https://your-app.com/webhook",
  "events": ["user.created", "domain.added", "email.created"],
  "secret": "webhook_secret"
}
```

Supported events:
- `user.created`, `user.updated`, `user.deleted`, `user.suspended`, `user.unsuspended`
- `domain.created`, `domain.updated`, `domain.deleted`
- `email.created`, `email.updated`, `email.deleted`
- `database.created`, `database.deleted`
- `backup.started`, `backup.completed`, `backup.failed`

## Administration

### Service Management

```bash
# Start Admini service
systemctl start admini

# Stop Admini service  
systemctl stop admini

# Restart Admini service
systemctl restart admini

# Check service status
systemctl status admini

# View service logs
journalctl -u admini -f
```

### Command Line Tools

#### User Management
```bash
# Create user
./admini user create --username=newuser --password=pass --email=user@example.com

# List users
./admini user list

# Suspend user
./admini user suspend --username=baduser

# Delete user
./admini user delete --username=olduser
```

#### Domain Management
```bash
# Add domain
./admini domain add --domain=example.com --user=username

# List domains
./admini domain list

# Remove domain
./admini domain remove --domain=example.com
```

#### System Operations
```bash
# Show system info
./admini info

# Show version
./admini version

# Update system
./admini update

# Run task queue
./admini taskq

# Generate license info
./admini license
```

### Backup and Restore

#### User Backups
```bash
# Create user backup
./admini backup user --username=user1 --destination=/backups/

# Restore user backup
./admini restore user --username=user1 --source=/backups/user1.tar.gz

# Schedule automatic backups
./admini backup schedule --frequency=daily --retention=30
```

#### System Backups
```bash
# Full system backup
./admini backup system --destination=/backups/system/

# Restore system
./admini restore system --source=/backups/system/admini-backup.tar.gz
```

### Monitoring and Logging

#### Log Files
- **Main Log**: `/var/log/admini/admini.log`
- **Access Log**: `/var/log/admini/access.log`
- **Error Log**: `/var/log/admini/error.log`
- **Task Queue Log**: `/var/log/admini/taskqueue.log`

#### Monitoring Commands
```bash
# Monitor system resources
./admini monitor resources

# Check service status
./admini monitor services

# View error logs
./admini monitor errors

# System health check
./admini health-check
```

### Security Management

#### SSL Certificates
```bash
# Generate self-signed certificate
./admini ssl generate-self-signed --domain=your-server.com

# Install Let's Encrypt certificate
./admini ssl letsencrypt --domain=your-server.com --email=admin@your-server.com

# Renew SSL certificates
./admini ssl renew --all
```

#### Access Control
```bash
# Add IP to whitelist
./admini security whitelist-ip --ip=192.168.1.100

# Block IP address
./admini security block-ip --ip=10.0.0.50

# Enable two-factor authentication
./admini security enable-2fa --user=admin
```

## Troubleshooting

### Common Issues

#### Installation Problems

**Issue**: "Permission denied" during installation
```bash
# Solution: Ensure you're running as root
sudo ./setup.sh
```

**Issue**: "Port 2222 already in use"
```bash
# Solution: Check what's using the port
netstat -tulpn | grep :2222
# Kill the process or change Admini port in config
```

#### Service Issues

**Issue**: Admini service won't start
```bash
# Check logs for errors
journalctl -u admini -n 50

# Check configuration syntax
./admini config validate

# Check file permissions
chown -R admini:admini /usr/local/admini
chmod +x /usr/local/admini/backend/admini
```

**Issue**: Web interface not accessible
```bash
# Check if service is running
systemctl status admini

# Check firewall settings
firewall-cmd --list-ports
firewall-cmd --add-port=2222/tcp --permanent
firewall-cmd --reload

# Check SELinux if applicable
setenforce 0  # Temporarily disable for testing
```

#### Database Issues

**Issue**: "Cannot connect to database"
```bash
# Check MySQL service
systemctl status mysql

# Verify database credentials
mysql -u admini_admin -p

# Reset database password
./admini database reset-password
```

#### SSL Certificate Issues

**Issue**: SSL certificate errors
```bash
# Check certificate validity
openssl x509 -in /usr/local/admini/conf/cakey.pem -text -noout

# Regenerate self-signed certificate
./admini ssl generate-self-signed --force

# Check certificate permissions
chmod 600 /usr/local/admini/conf/cakey.pem
```

### Performance Optimization

#### System Tuning
```bash
# Optimize database
./admini optimize database

# Clean temporary files
./admini cleanup temp-files

# Optimize web server
./admini optimize webserver

# Update system packages
./admini update system
```

#### Resource Monitoring
```bash
# Check memory usage
free -h

# Check disk space
df -h

# Monitor CPU usage
top

# Check I/O statistics
iostat -x 1
```

### Debug Mode

Enable debug mode for detailed logging:

```bash
# Enable debug mode
echo "debug=1" >> /usr/local/admini/conf/admini.conf

# Restart service
systemctl restart admini

# View debug logs
tail -f /var/log/admini/debug.log
```

## Migration from DirectAdmin

### Automated Migration

Admini includes tools to migrate from DirectAdmin installations:

```bash
# Run migration wizard
./admini migrate from-directadmin --source=/usr/local/directadmin

# Migrate specific components
./admini migrate users --source=/usr/local/directadmin/data/users
./admini migrate domains --source=/usr/local/directadmin/data/users  
./admini migrate email --source=/usr/local/directadmin/data/users
```

### Manual Migration Steps

#### 1. Backup DirectAdmin Data
```bash
# Create DirectAdmin backup
cd /usr/local/directadmin
tar -czf da-backup.tar.gz data/

# Copy to safe location
cp da-backup.tar.gz /root/migration/
```

#### 2. Install Admini
Follow the standard installation procedure, but don't create any users yet.

#### 3. Migrate User Accounts
```bash
# Extract user list from DirectAdmin
./admini migrate extract-users --directadmin-path=/usr/local/directadmin

# Import users to Admini
./admini migrate import-users --data-file=users.json
```

#### 4. Migrate Domain Configurations
```bash
# Export domain configurations
./admini migrate extract-domains --directadmin-path=/usr/local/directadmin

# Import domains
./admini migrate import-domains --data-file=domains.json
```

#### 5. Migrate Email Settings
```bash
# Export email configurations
./admini migrate extract-email --directadmin-path=/usr/local/directadmin

# Import email accounts
./admini migrate import-email --data-file=email.json
```

#### 6. Copy Website Files
```bash
# Copy website files (adjust paths as needed)
rsync -av /home/*/domains/*/public_html/ /home/*/domains/*/public_html/
rsync -av /home/*/imap/ /home/*/imap/
```

#### 7. Update DNS Records
Update DNS records to point to the new server or adjust configurations as needed.

#### 8. Verify Migration
```bash
# Check all users migrated
./admini user list

# Verify domains
./admini domain list

# Test email functionality
./admini test email --domain=example.com

# Check website accessibility
curl -I http://example.com
```

### Migration Verification Checklist

- [ ] All user accounts migrated with correct permissions
- [ ] All domains accessible and working
- [ ] Email sending and receiving functional
- [ ] Database connections working
- [ ] SSL certificates installed and valid
- [ ] Cron jobs migrated and functional
- [ ] FTP access working
- [ ] File permissions correct
- [ ] DNS zones configured properly
- [ ] Backups working

## Development

### Building from Source

#### Requirements
- Go 1.21 or higher
- Node.js 16+ (for frontend assets)
- Git

#### Build Process
```bash
# Clone repository
git clone https://github.com/iSundram/Admini.git
cd Admini

# Build backend
cd backend
go mod tidy
go build -o admini

# Build frontend assets (if applicable)
cd ../frontend
npm install
npm run build

# Run tests
go test ./...
```

### Plugin Development

Admini supports custom plugins for extending functionality:

#### Plugin Structure
```
my-plugin/
├── plugin.json
├── main.go
├── handlers/
│   └── api.go
├── templates/
│   └── dashboard.html
└── static/
    ├── css/
    └── js/
```

#### Plugin Configuration (`plugin.json`)
```json
{
  "name": "My Custom Plugin",
  "version": "1.0.0",
  "description": "Custom functionality for Admini",
  "author": "Your Name",
  "main": "main.go",
  "api_version": "1.0",
  "permissions": ["users.read", "domains.write"],
  "hooks": ["user.created", "domain.added"]
}
```

#### Plugin Example (`main.go`)
```go
package main

import (
    "github.com/gin-gonic/gin"
    "admini/pkg/plugin"
)

type MyPlugin struct{}

func (p *MyPlugin) Initialize() error {
    // Plugin initialization code
    return nil
}

func (p *MyPlugin) GetRoutes() []plugin.Route {
    return []plugin.Route{
        {
            Method: "GET",
            Path: "/my-plugin/dashboard",
            Handler: p.HandleDashboard,
        },
    }
}

func (p *MyPlugin) HandleDashboard(c *gin.Context) {
    c.HTML(200, "dashboard.html", gin.H{
        "title": "My Plugin Dashboard",
    })
}

func (p *MyPlugin) OnUserCreated(user plugin.User) error {
    // Handle user creation event
    return nil
}

// Plugin entry point
func NewPlugin() plugin.Plugin {
    return &MyPlugin{}
}
```

#### Installing Plugins
```bash
# Install plugin
./admini plugin install --path=/path/to/my-plugin

# Enable plugin
./admini plugin enable my-plugin

# List plugins
./admini plugin list

# Disable plugin
./admini plugin disable my-plugin
```

### API Development

#### Creating Custom API Endpoints

```go
// Add to pkg/server/custom_handlers.go
func (s *Server) setupCustomRoutes() {
    custom := s.router.Group("/api/custom")
    custom.Use(s.apiAuthMiddleware())
    {
        custom.GET("/stats", s.handleCustomStats)
        custom.POST("/action", s.handleCustomAction)
    }
}

func (s *Server) handleCustomStats(c *gin.Context) {
    stats := map[string]interface{}{
        "custom_metric": getCustomMetric(),
        "timestamp": time.Now().Unix(),
    }
    c.JSON(http.StatusOK, stats)
}
```

### Theme Development

#### Custom Theme Structure
```
themes/my-theme/
├── theme.json
├── css/
│   ├── admin.css
│   ├── reseller.css
│   └── user.css
├── js/
│   └── custom.js
├── images/
│   ├── logo.png
│   └── favicon.ico
└── templates/
    ├── login.html
    ├── dashboard.html
    └── layout.html
```

#### Theme Configuration (`theme.json`)
```json
{
  "name": "My Custom Theme",
  "version": "1.0.0",
  "description": "Custom theme for Admini",
  "author": "Your Name",
  "primary_color": "#3174c6",
  "secondary_color": "#f8fafc",
  "supports": ["admin", "reseller", "user"],
  "assets": {
    "css": ["css/admin.css", "css/user.css"],
    "js": ["js/custom.js"]
  }
}
```

### Contributing

#### Development Workflow

1. **Fork the repository** on GitHub
2. **Create a feature branch**: `git checkout -b feature/my-feature`
3. **Make changes** and add tests
4. **Run tests**: `go test ./...`
5. **Commit changes**: `git commit -am 'Add my feature'`
6. **Push to branch**: `git push origin feature/my-feature`
7. **Create Pull Request** on GitHub

#### Code Standards

- Follow Go coding conventions
- Write tests for new functionality
- Update documentation for API changes
- Use meaningful commit messages
- Ensure backward compatibility

#### Testing

```bash
# Run all tests
go test ./...

# Run tests with coverage
go test -cover ./...

# Run specific package tests
go test ./pkg/user

# Run integration tests
go test -tags=integration ./...
```

### Release Process

#### Version Management
Admini uses semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR**: Incompatible API changes
- **MINOR**: Backward-compatible functionality additions
- **PATCH**: Backward-compatible bug fixes

#### Creating Releases
```bash
# Tag release
git tag -a v1.2.0 -m "Release version 1.2.0"
git push origin v1.2.0

# Build release binaries
./scripts/build-release.sh v1.2.0

# Update documentation
./scripts/update-docs.sh
```

---

For additional support and documentation, visit:
- **Project Repository**: https://github.com/iSundram/Admini
- **Issue Tracker**: https://github.com/iSundram/Admini/issues
- **Community Forum**: https://community.admini.io
- **Documentation**: https://docs.admini.io

© 2024 Admini Control Panel. All rights reserved.