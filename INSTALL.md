# Admini Control Panel - Quick Start Guide

## Installation Methods

### Method 1: Automatic Installation (Recommended)

```bash
# Download and run the installation script
wget -O setup.sh https://raw.githubusercontent.com/iSundram/Admini/main/scripts/setup.sh
chmod +x setup.sh
sudo ./setup.sh --auto
```

### Method 2: Manual Installation

```bash
# 1. Clone the repository
git clone https://github.com/iSundram/Admini.git
cd Admini

# 2. Run the setup script
sudo ./scripts/setup.sh

# 3. Follow the installation prompts
```

### Method 3: Development Installation

```bash
# For developers who want to modify the code
sudo ./scripts/setup.sh --dev --port 2222
```

## Quick Setup Commands

```bash
# After installation, manage the service with:
sudo systemctl start admini      # Start the service
sudo systemctl stop admini       # Stop the service  
sudo systemctl restart admini    # Restart the service
sudo systemctl status admini     # Check service status
sudo journalctl -u admini -f     # View real-time logs
```

## Access Your Control Panel

Once installed, access your control panel at:
- **URL**: `http://your-server-ip:2222`
- **Default Admin**: `admin`
- **Password**: Generated during installation (displayed at end of setup)

## System Requirements

- **OS**: Ubuntu 20+, Debian 11+, CentOS 8+, Rocky Linux 8+, AlmaLinux 8+
- **RAM**: Minimum 1GB, Recommended 2GB+
- **Storage**: Minimum 2GB free space
- **Network**: Port 2222 accessible
- **Privileges**: Root access required for installation

## Architecture Overview

### UI Components
1. **AdminiCore** - Administrator interface (full system control)
2. **AdminiReseller** - Reseller interface (client management)  
3. **AdminiPanel** - User interface (cPanel-style hosting control)

### Backend API
- RESTful API endpoints (`/api/*`)
- AJAX handlers (`/ajax/*`)
- DirectAdmin-compatible commands (`/CMD_*`)

### Frontend Integration
- Modern responsive design
- Real-time JavaScript API client
- Form validation and notifications
- Dynamic content loading

## File Structure

```
/usr/local/admini/           # Installation directory
├── admini                   # Main binary
├── static/                  # CSS, JS, images
├── templates/               # HTML templates
├── conf/                    # Configuration files
└── data/                    # Application data

/etc/systemd/system/admini.service    # Service file
/var/log/admini.log                   # Log file
```

## Configuration

Edit `/usr/local/admini/conf/admini.conf` to customize:
- Port number
- SSL settings  
- Database configuration
- Security options
- Logging levels

## Security Features

- Session-based authentication
- CSRF protection
- Input validation
- Role-based access control
- SSL/TLS support
- Brute force protection

## Support & Documentation

- **Full Documentation**: `/system/readme.md`
- **Source Code**: https://github.com/iSundram/Admini
- **Issues**: https://github.com/iSundram/Admini/issues

## Troubleshooting

### Service won't start
```bash
# Check service status
sudo systemctl status admini

# Check logs
sudo journalctl -u admini -n 50

# Verify binary permissions
ls -la /usr/local/admini/admini
```

### Cannot access web interface
```bash
# Check if service is running
sudo systemctl is-active admini

# Verify port is open
sudo netstat -tulpn | grep :2222

# Check firewall
sudo ufw status
```

### Permission issues
```bash
# Fix file permissions
sudo chown -R root:root /usr/local/admini
sudo chmod +x /usr/local/admini/admini
sudo chmod 600 /usr/local/admini/conf/admini.conf
```

## Uninstallation

```bash
# Stop and disable service
sudo systemctl stop admini
sudo systemctl disable admini

# Remove files
sudo rm -rf /usr/local/admini
sudo rm /etc/systemd/system/admini.service
sudo systemctl daemon-reload
```

---

**Admini Control Panel** - A modern, lightweight alternative to DirectAdmin and cPanel, built with Go and modern web technologies.