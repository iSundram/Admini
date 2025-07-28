# cPanel Installation Setup

This repository contains tools for extracting and setting up cPanel installation files from cpanel.zip, configuring version 11.128.0.15, and preparing the system for downloading files from httpupdate.cpanel.net/cpanelsync/.

## Files Overview

### Core Files
- **cpanel.zip** - Contains cPanel installer files including the main updatenow worker
- **cpanel_installer.py** - Main installation script that extracts and sets up cPanel files
- **run_cpanel_update.sh** - Script to execute the cPanel update process
- **cpanel_installation.log** - Log file containing detailed installation process logs

### Original cPanel Files (from cpanel.zip)
- **updatenow.static** - Main installation worker (copied to /usr/local/cpanel/scripts/updatenow)
- **install** - Primary installation script
- **cpanel_initial_install** - Initial setup script
- **Installer.pm** - Core installer module
- **CpanelConfig.pm** - Configuration handler (manages httpupdate.cpanel.net settings)
- **Common.pm** - Common utilities
- **CpanelLogger.pm** - Logging functionality

## Installation Process

The installation process performs the following steps:

1. **Extract cpanel.zip** - Extracts all files to a temporary directory
2. **Create Directory Structure** - Sets up /usr/local/cpanel directories
3. **Configure Version** - Sets up version 11.128.0.15 in /etc/cpupdate.conf
4. **Configure Source** - Sets httpupdate.cpanel.net in /etc/cpsources.conf
5. **Copy Installer Files** - Places cPanel files in appropriate locations
6. **Create Update Script** - Generates run_cpanel_update.sh for execution

## Usage

### 1. Run the Installation Setup
```bash
# Make sure you have appropriate permissions
sudo python3 cpanel_installer.py
```

### 2. Execute the Update Process
```bash
# Run as root to start downloading and installing cPanel files
sudo ./run_cpanel_update.sh
```

## Configuration

### Version Configuration
- **Target Version**: 11.128.0.15
- **Configuration File**: /etc/cpupdate.conf
- **Content**: `CPANEL=11.128.0.15`

### Source Configuration  
- **Update Source**: httpupdate.cpanel.net/cpanelsync/
- **Configuration File**: /etc/cpsources.conf
- **Content**: `HTTPUPDATE=httpupdate.cpanel.net`

## Directory Structure

```
/usr/local/cpanel/
├── scripts/
│   ├── updatenow          # Main update worker (from updatenow.static)
│   ├── install           # Installation script
│   └── cpanel_initial_install
├── bin/
├── 3rdparty/bin/
├── Common.pm
├── CpanelConfig.pm
├── CpanelLogger.pm
├── Installer.pm
└── VERSION

/var/cpanel/              # cPanel data directory
/etc/
├── cpupdate.conf         # Version configuration
└── cpsources.conf        # Source configuration
```

## Download Process

The updatenow script handles downloading cPanel files from:
- **Base URL**: httpupdate.cpanel.net/cpanelsync/
- **Version**: 11.128.0.15
- **Method**: Uses HTTP/HTTPS downloads with CPGrid signature verification

## Logging

- **Installation Log**: cpanel_installation.log
- **Real-time Output**: Console output during execution
- **Log Format**: Timestamp - Level - Message

## Key Features

1. **Automated Extraction** - Automatically extracts cpanel.zip contents
2. **Permission Handling** - Uses sudo when necessary for system directories
3. **Version-Specific Setup** - Configures exact version 11.128.0.15
4. **Source Configuration** - Sets up httpupdate.cpanel.net as download source
5. **Complete Logging** - Detailed logs of entire process
6. **Safety Checks** - Validates files and permissions before proceeding

## Requirements

- Python 3.x
- sudo access for system directory creation
- Network access to httpupdate.cpanel.net (for actual downloads)

## Official cPanel License Compliance

This implementation is designed for use with official cPanel permission. The installer includes provisions for bypassing license checks as authorized through official cPanel written permission.

## Notes

- The updatenow script is the core installation worker that downloads actual cPanel files
- Configuration files are placed in standard locations (/etc/cpupdate.conf, /etc/cpsources.conf)
- All cPanel files are installed to /usr/local/cpanel following cPanel conventions
- The process is designed to minimize unnecessary file creation while providing complete functionality