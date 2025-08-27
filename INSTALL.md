# Installation and Usage Guide

## Quick Start

1. **Clone the repository:**
```bash
git clone https://github.com/iSundram/Admini.git
cd Admini
```

2. **Run the installation script:**
```bash
cd scripts
sudo chmod +x install.sh
sudo ./install.sh
```

3. **Follow the prompts to configure:**
- Domain name for the control panel
- Admin password (or leave blank for auto-generated)
- Database credentials (auto-configured)

4. **Access the control panel:**
- URL: `https://your-domain.com`
- Default admin credentials will be displayed after installation

## Manual Installation

If you prefer manual installation:

1. **Install dependencies:**
```bash
# Ubuntu/Debian
sudo apt-get update
sudo apt-get install apache2 php8.1 php8.1-mysql mysql-server

# CentOS/RHEL
sudo yum install httpd php php-mysql mysql-server
```

2. **Configure database:**
```bash
mysql -u root -p < database/schema.sql
```

3. **Set up web server:**
```bash
sudo cp -r src/* /var/www/html/
sudo chown -R www-data:www-data /var/www/html/
sudo chmod -R 755 /var/www/html/
```

4. **Configure database connection:**
Edit `src/config/database.php` with your database credentials.

## Features Included

### ✅ **Admin Panel** (`/admin/`)
- **Dashboard**: System overview, statistics, recent activities
- **User Management**: Create, edit, suspend, delete users and resellers
- **DNS Management**: Zone management, DNS records (A, AAAA, CNAME, MX, TXT, etc.)
- **Package Management**: Create hosting packages with resource limits
- **System Monitoring**: Server stats, resource usage
- **Settings**: Global configuration

### ✅ **Reseller Panel** (`/reseller/`)
- **Dashboard**: Customer overview, resource usage monitoring
- **Customer Management**: Create and manage customer accounts
- **Package Management**: Create custom packages for customers
- **Resource Monitoring**: Track disk, bandwidth usage
- **Billing Integration**: Ready for billing system integration

### ✅ **User Panel** (`/user/`)
- **Dashboard**: Account overview, resource usage
- **Domain Management**: Add domains, subdomains
- **Email Management**: Create email accounts, forwarders, webmail access
- **File Manager**: Web-based file browser with upload/download
- **Database Management**: MySQL/PostgreSQL database creation
- **SSL Certificates**: Install and manage SSL certificates
- **Backup & Restore**: Account backup functionality
- **Cron Jobs**: Schedule automated tasks

### ✅ **API System** (`/api/`)
- **RESTful API**: Complete API for automation
- **Authentication**: API key-based authentication
- **Resources**: Users, domains, email, databases, statistics
- **Documentation**: Comprehensive API docs in `/docs/API.md`

### ✅ **Security Features**
- **Authentication**: Session-based login with role permissions
- **Rate Limiting**: Protection against brute force attacks
- **Input Validation**: SQL injection and XSS protection
- **Security Headers**: CSP, XSS protection, etc.
- **File Permissions**: Proper access controls

## Default Credentials

After installation, use these credentials:
- **Username**: `admin`
- **Password**: (displayed during installation)

## Screenshots

### Welcome Page
Modern, responsive welcome interface with feature overview.

### Admin Dashboard
Comprehensive admin panel with system statistics, user management, and quick actions.

### User Dashboard
Clean user interface with resource usage monitoring and management tools.

### Email Management
Complete email system with account creation, forwarders, and server configuration.

### File Manager
Web-based file browser with upload, download, and file operations.

### DNS Management
Professional DNS zone and record management interface.

## System Requirements

- **Operating System**: Ubuntu 18.04+, CentOS 7+, Debian 9+
- **Web Server**: Apache 2.4+ or Nginx 1.14+
- **PHP**: 8.0 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Memory**: 512MB+ RAM recommended
- **Disk Space**: 1GB+ free space

## API Usage Example

```bash
# Get all users
curl -X GET \
  https://your-domain.com/api/users \
  -H 'X-API-Key: your_api_key_here'

# Create a new user
curl -X POST \
  https://your-domain.com/api/users \
  -H 'Content-Type: application/json' \
  -H 'X-API-Key: your_api_key_here' \
  -d '{
    "username": "newuser",
    "email": "user@example.com",
    "password": "securepassword",
    "role": "user"
  }'
```

## Support

For issues and support:
1. Check the documentation in `/docs/`
2. Review the installation logs
3. Ensure all system requirements are met
4. Contact support or open an issue on GitHub

## License

This project is open source and available under the MIT License.