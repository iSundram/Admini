# Admini Control Panel - Go Implementation

This directory contains the Go source code implementation of Admini, a comprehensive web hosting control panel system.

## Structure

```
backend/
├── main.go                 # Main entry point
├── go.mod                  # Go module file
├── cmd/                    # Command implementations
│   ├── root.go            # Root command and CLI setup
│   ├── server.go          # Web server command
│   ├── config.go          # Configuration commands
│   ├── admin.go           # Admin commands
│   ├── license.go         # License commands
│   ├── operations.go      # User/domain operations
│   ├── misc.go            # Miscellaneous commands
│   └── version.go         # Version commands
└── pkg/                   # Core packages
    ├── config/            # Configuration management
    ├── server/            # Web server implementation
    ├── admin/             # Admin functionality
    ├── license/           # License management
    ├── domain/            # Domain management
    ├── user/              # User management
    └── taskqueue/         # Task queue processor
```

## Features Implemented

### Command Line Interface
- All major commands from the original binary
- Help system with command documentation
- Flag and argument parsing

### Core Functionality
- **Web Server**: HTTP/HTTPS server with DirectAdmin UI
- **Configuration**: System configuration management
- **User Management**: Create, modify, suspend/unsuspend users
- **Domain Management**: Domain operations and SSL
- **Admin Functions**: Admin tools and backup operations
- **License Management**: License validation and updates
- **Task Queue**: Background task processing system

### API Endpoints
- User management API
- Domain management API
- Configuration API
- Statistics and monitoring

### Web Interface
- Login system
- Admin panel
- User control panel
- File manager interface
- Email management
- Database operations
- SSL certificate management

## Usage

### Build the Binary
```bash
go build -o directadmin
```

### Run Commands
```bash
# Show version
./directadmin version

# Show help
./directadmin --help

# Start web server
./directadmin server --port 2222

# Admin operations
./directadmin admin
./directadmin config
./directadmin license

# User operations
./directadmin suspend-user username
./directadmin unsuspend-user username

# Domain operations
./directadmin suspend-domain domain.com
./directadmin unsuspend-domain domain.com

# Task queue
./directadmin taskq
```

## Configuration

The application reads configuration from:
- `/usr/local/directadmin/conf/directadmin.conf`
- Environment variables
- Command line flags

## Architecture

The implementation follows the same architectural patterns as the original DirectAdmin:

1. **CLI Layer**: Command parsing and routing
2. **Service Layer**: Business logic implementation
3. **Data Layer**: File-based configuration and data storage
4. **Web Layer**: HTTP server and API endpoints

## Compatibility

This Go implementation maintains compatibility with:
- Admini configuration files
- Task queue format
- API endpoints
- CLI command structure
- File system layout

## Development

### Adding New Commands
1. Create command handler in `cmd/` directory
2. Add to root command in `cmd/root.go`
3. Implement business logic in appropriate `pkg/` package

### Adding New Features
1. Create package in `pkg/` directory
2. Implement core functionality
3. Add web endpoints in `pkg/server/`
4. Add CLI commands as needed

## Testing

```bash
# Run tests
go test ./...

# Test specific command
./directadmin version
./directadmin config
./directadmin --help
```dmin --help
```