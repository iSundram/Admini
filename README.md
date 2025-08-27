# Admini - Web Hosting Control Panel

![Admini Logo](https://img.shields.io/badge/Admini-Control%20Panel-blue?style=for-the-badge)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple?style=for-the-badge&logo=php)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-orange?style=for-the-badge&logo=mysql)
![License](https://img.shields.io/badge/License-MIT-green?style=for-the-badge)

Admini is a comprehensive PHP-based web hosting control panel that combines the best features of **cPanel/WHM**, **DirectAdmin**, and **Webuzo**. It provides a modern, responsive solution for managing web hosting accounts, domains, emails, databases, and server resources.

## 🚀 Features

### 👨‍💼 Administrator Level
- ✅ **User Management**: Create and manage users, resellers, and administrators
- ✅ **DNS Administration**: Complete zone and record management (A, AAAA, CNAME, MX, TXT, NS, PTR, SRV)
- ✅ **Package Management**: Create hosting packages with resource limits
- ✅ **System Monitoring**: Real-time server statistics and resource usage
- ✅ **Security**: Advanced security features and access controls
- 🔄 **SSL Management**: Let's Encrypt integration (planned)
- 🔄 **Backup System**: Automated backup and restore (planned)

### 🏢 Reseller Level
- ✅ **Customer Management**: Create and manage customer accounts
- ✅ **Resource Monitoring**: Track disk space, bandwidth usage
- ✅ **Package Creation**: Custom packages for customers
- ✅ **Statistics Dashboard**: Complete overview of reseller usage
- 🔄 **Billing Integration**: Ready for billing system integration
- 🔄 **Branding**: Custom themes and branding options

### 👤 User Level
- ✅ **Email Management**: Create accounts, forwarders, webmail access
- ✅ **File Manager**: Web-based file browser with upload/download
- ✅ **Domain Management**: Add domains and subdomains
- ✅ **Database Management**: MySQL and PostgreSQL support
- ✅ **Statistics**: Detailed account usage statistics
- 🔄 **SSL Certificates**: Install and manage SSL certificates
- 🔄 **Cron Jobs**: Schedule automated tasks
- 🔄 **Application Installer**: One-click app installations

### 🔌 API & Integration
- ✅ **RESTful API**: Complete API for automation
- ✅ **Authentication**: Secure API key-based authentication
- ✅ **Documentation**: Comprehensive API documentation
- ✅ **Multi-format**: JSON responses with proper error handling

## 📦 Installation

### Quick Install (Recommended)
```bash
git clone https://github.com/iSundram/Admini.git
cd Admini/scripts
sudo chmod +x install.sh
sudo ./install.sh
```

### Manual Installation
See [INSTALL.md](INSTALL.md) for detailed manual installation instructions.

## 🖥️ Screenshots & Demo

### 🏠 Welcome Interface
Modern welcome page with feature overview and easy access to login.

### 📊 Admin Dashboard
- System statistics and monitoring
- User management interface
- Quick actions panel
- Recent activity logs

### 👥 User Management
- Complete CRUD operations
- Package assignment
- Resource allocation
- Status management

### 🌐 DNS Management
- Zone creation and management
- All DNS record types supported
- Serial number auto-increment
- Real-time updates

### 📧 Email System
- Email account creation
- Forwarder management
- Server configuration guides
- Webmail integration ready

### 📁 File Manager
- Web-based file browser
- Upload/download functionality
- Folder operations
- Permission management

## 🔧 System Requirements

- **OS**: Ubuntu 18.04+, CentOS 7+, Debian 9+
- **Web Server**: Apache 2.4+ or Nginx 1.14+
- **PHP**: 8.0 or higher with extensions:
  - `php-mysql`, `php-curl`, `php-gd`, `php-mbstring`
  - `php-xml`, `php-zip`, `php-intl`, `php-bcmath`
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Memory**: 512MB+ RAM recommended
- **Disk**: 1GB+ free space

## 🚀 API Usage

```bash
# Get system statistics
curl -X GET \
  https://your-domain.com/api/statistics \
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

See [API Documentation](docs/API.md) for complete API reference.

## 🏗️ Project Structure

```
Admini/
├── 📁 scripts/          # Installation scripts
├── 📁 src/              # Main application code
│   ├── 📁 admin/        # Admin panel
│   ├── 📁 reseller/     # Reseller panel  
│   ├── 📁 user/         # User panel
│   ├── 📁 api/          # RESTful API
│   ├── 📁 includes/     # Core classes
│   ├── 📁 config/       # Configuration
│   └── 📁 assets/       # CSS, JS, images
├── 📁 database/         # Database schema
└── 📁 docs/            # Documentation
```

## 🔐 Default Credentials

After installation:
- **Username**: `admin`
- **Password**: (displayed during installation)
- **URL**: `https://your-domain.com`

## 🛣️ Roadmap

- [x] **Core System**: Authentication, user management, basic UI
- [x] **Multi-panel**: Admin, Reseller, User interfaces
- [x] **DNS Management**: Complete DNS zone and record management
- [x] **Email System**: Email accounts and forwarder management
- [x] **File Manager**: Web-based file operations
- [x] **API System**: RESTful API with authentication
- [ ] **SSL Management**: Let's Encrypt integration
- [ ] **Database Tools**: phpMyAdmin integration
- [ ] **Backup System**: Automated backups
- [ ] **Application Installer**: Softaculous alternative
- [ ] **Statistics Engine**: Advanced reporting
- [ ] **Mobile App**: Native mobile applications

## 🤝 Contributing

We welcome contributions! Please see our contributing guidelines and:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Submit a pull request

## 📋 Feature Comparison

| Feature | cPanel/WHM | DirectAdmin | Webuzo | **Admini** |
|---------|------------|-------------|---------|------------|
| **Multi-level Users** | ✅ | ✅ | ✅ | ✅ |
| **DNS Management** | ✅ | ✅ | ✅ | ✅ |
| **Email Management** | ✅ | ✅ | ✅ | ✅ |
| **File Manager** | ✅ | ✅ | ✅ | ✅ |
| **API Access** | ✅ | ✅ | ✅ | ✅ |
| **Open Source** | ❌ | ❌ | ❌ | ✅ |
| **Cost** | Paid | Paid | Paid | **Free** |
| **Modern UI** | ⚠️ | ⚠️ | ✅ | ✅ |

## 📄 License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.

## 🆘 Support

- 📖 **Documentation**: Check the `/docs` directory
- 🐛 **Issues**: Report bugs on GitHub Issues
- 💬 **Discussions**: Join our community discussions
- 📧 **Email**: Contact support team

---

**Built with ❤️ by the Admini Team**