# Admini - Web Hosting Control Panel

Admini is a PHP-based web hosting control panel that combines the best features of cPanel/WHM, DirectAdmin, and Webuzo. It provides a comprehensive solution for managing web hosting accounts, domains, emails, databases, and server resources.

## Features

### Administrator Level
- Create and manage users, resellers, and administrators
- DNS administration and zone management
- IP address management and allocation
- Mail queue administration
- SSL certificate management (Let's Encrypt integration)
- Multi-PHP version selector
- Complete usage statistics and monitoring
- Backup and restore functionality
- Server service management
- Security and firewall configuration

### Reseller Level
- Account creation and management
- Package and plan management
- Resource allocation and monitoring
- Reseller statistics and reporting
- Custom branding and themes
- IP assignment to customers
- Billing integration support

### User Level
- Email account management (POP/IMAP, forwarding, autoresponders)
- FTP account management
- DNS record management
- File manager with web interface
- MySQL and PostgreSQL database management
- Subdomain management
- Statistics and analytics
- SSL certificate installation
- Cron job management
- Application installer (WordPress, Joomla, etc.)

## Installation

1. Run the installation script:
```bash
cd scripts
sudo ./install.sh
```

2. Access the control panel at: `https://your-server-ip:2087`

## Requirements

- PHP 8.0 or higher
- MySQL 5.7 or higher / MariaDB 10.3 or higher
- Apache 2.4 or Nginx
- Linux-based server (Ubuntu, CentOS, Debian)
- SSL certificate for secure access

## License

This project is licensed under the MIT License.