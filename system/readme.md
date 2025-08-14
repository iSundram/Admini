# Admini Control Panel - System Architecture & UI Documentation

## Overview

Admini is a comprehensive web hosting control panel system implemented in Go with a modern web-based interface. The system provides three distinct user interfaces: AdminiCore (administrator), AdminiReseller (reseller), and AdminiPanel (end-user cPanel-style interface).

## System Architecture

### Backend (Go Application)
- **Location**: `/backend/`
- **Main Entry**: `main.go` - CLI and web server entry point
- **Build Output**: `admini` binary (compatible with DirectAdmin API)
- **Framework**: Gin HTTP framework for web server and API endpoints

### Frontend/UI Structure

#### 1. Data Skins (`/data/skins/`) - **PRIMARY UI SYSTEM**
Traditional DirectAdmin-compatible skin system (main UI):
- `enhanced/` - Enhanced skin with traditional DirectAdmin interface
- `evolution/` - Modern evolution skin with Vue.js assets
- Includes language files, configuration, and localization
- **This is the primary UI system that users interact with**

#### 2. Backend Templates (`/backend/templates/`)
Legacy HTML templates (compatibility only):
- `login.html` - Fallback login interface
- `dashboard.html` - Legacy administrative dashboard
- `cpanel.html` - Legacy cPanel-style user interface
- **Note: These are kept for compatibility but not the primary UI**

#### 3. Static Assets (`/backend/static/`)
- `css/admini-theme.css` - Legacy theme and styling
- `css/cpanel-style.css` - Legacy cPanel-compatible styling
- `images/` - Logo, favicon, and UI images

## User Interfaces

### 1. AdminiCore (Administrator Interface)
**Access URL**: `http://server:2222/` (admin login)
**Features**:
- System overview and statistics
- User and reseller management
- Server configuration
- License management
- Backup and restore operations
- Security and monitoring tools

**Primary UI**: DirectAdmin skin system (`/data/skins/enhanced/admin/`)
**API Endpoints**: `/CMD_ADMIN*`, `/api/admin/*`

### 2. AdminiReseller (Reseller Interface)
**Access URL**: `http://server:2222/` (reseller login)
**Features**:
- Client management
- Package management
- Resource allocation
- Billing integration
- Support ticket management

**Primary UI**: DirectAdmin skin system (`/data/skins/enhanced/reseller/`)
**API Endpoints**: `/CMD_RESELLER*`, `/api/reseller/*`

### 3. AdminiPanel (User Interface - cPanel Style)
**Access URL**: `http://server:2222/` (user login)
**Features**:
- File management
- Email management
- Database operations
- Domain management
- SSL certificate management
- Statistics and monitoring

**Primary UI**: DirectAdmin skin system (`/data/skins/evolution/` or `/data/skins/enhanced/user/`)
**API Endpoints**: `/CMD_*`, `/api/user/*`

## Server Architecture

### Web Server
- **Port**: 2222 (default, configurable)
- **Protocol**: HTTP/HTTPS
- **Sessions**: Cookie-based authentication
- **Static Files**: Served from `/static/` path

### API Structure
```
/CMD_LOGIN          - Authentication
/CMD_ADMIN          - Admin dashboard
/CMD_SHOW_USERS     - User listing
/CMD_LOGOUT         - Session termination
/api/               - RESTful API endpoints
/ajax/              - AJAX handlers for dynamic content
```

### Database Integration
- File-based configuration system
- MySQL/MariaDB support for user data
- SQLite for session management

## Configuration

### Main Configuration
- **File**: `/backend/conf/admini.conf`
- **Template**: Available in `conf/` directory
- **Environment**: Configurable via environment variables

### Theme Configuration
- CSS variables for customization
- Theme switching support
- Responsive design for mobile/desktop

## Development Structure

### Backend Packages (`/backend/pkg/`)
- `server/` - HTTP server and handlers
- `config/` - Configuration management
- `user/` - User management
- `admin/` - Administrative functions
- `database/` - Database operations
- `ssl/` - SSL certificate management

### Command Structure (`/backend/cmd/`)
- `root.go` - CLI root command
- `server.go` - Web server command
- `admin.go` - Administrative commands
- `config.go` - Configuration commands
- `operations.go` - User/domain operations

## Installation Process

### Prerequisites
- Go 1.21+ (for building from source)
- Linux/Unix operating system
- Root or sudo access
- Network connectivity

### Build Process
```bash
cd backend
go mod tidy
go build -o admini
chmod +x admini
```

### Service Installation
```bash
# Copy binary to system location
cp admini /usr/local/bin/

# Install systemd service
cp scripts/admini.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable admini
systemctl start admini
```

## API Integration

### Frontend-Backend Communication
- **Authentication**: Form-based login with session cookies
- **API Calls**: AJAX requests to `/api/` and `/ajax/` endpoints
- **Response Format**: JSON for API, HTML for page navigation
- **Error Handling**: HTTP status codes with JSON error messages

### JavaScript Integration
```javascript
// Example API call
fetch('/api/users')
  .then(response => response.json())
  .then(data => console.log(data));

// AJAX form submission
$('#user-form').submit(function(e) {
  e.preventDefault();
  $.post('/api/users/create', $(this).serialize())
    .done(function(response) {
      // Handle success
    });
});
```

## Security Features

- CSRF protection
- Session management
- SSL/TLS support
- Input validation
- Role-based access control
- Brute force protection

## Monitoring & Logging

- System statistics
- User activity logs
- Error logging
- Performance monitoring
- Resource usage tracking

## Customization

### Theme Development
- Modify CSS variables in `admini-theme.css`
- Create custom templates
- Add custom JavaScript functionality
- Implement plugin system

### Plugin System
- API for extending functionality
- Custom handlers registration
- Database schema extensions
- UI component integration

## Compatibility

- **DirectAdmin API**: Compatible command structure
- **cPanel**: Similar user experience
- **Plesk**: Feature parity
- **Modern Browsers**: Chrome, Firefox, Safari, Edge
- **Mobile**: Responsive design for tablets and phones

## Performance

- **Caching**: Static asset caching
- **Compression**: Gzip compression enabled
- **CDN Ready**: Static assets can be served from CDN
- **Database**: Optimized queries and indexing
- **Memory**: Efficient Go runtime with low memory footprint

## Backup & Recovery

- **Configuration Backup**: Automated config backup
- **User Data Backup**: Full user account backup
- **System Restore**: Point-in-time recovery
- **Migration**: Easy migration between servers

This documentation provides a comprehensive overview of the Admini control panel system architecture, UI components, and integration points for developers and system administrators.