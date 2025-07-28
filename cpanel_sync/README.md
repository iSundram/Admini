# cPanel Sync Files for Version 11.128.0.15

This directory contains files that would normally be downloaded from httpupdate.cpanel.net/cpanelsync/11.128.0.15/ during a cPanel installation or update process.

## Directory Structure

- `scripts/` - Contains core cPanel scripts including:
  - `fix-cpanel-perl.xz` - Bootstrap perl fixing script
  - `updatenow.static.bz2` - Main update script (static version)

- `install/` - Installation packages:
  - `common/` - Common installation files
    - `locale.tar.xz.cpanelsync.nodecompress` - Locale data
    - `cpanel.tar.xz.cpanelsync.nodecompress` - Core cpanel files
  - `themes/` - Theme files
    - `jupiter.tar.xz.cpanelsync.nodecompress` - Jupiter theme package

- `binaries/linux-u22-x86_64/` - Platform-specific binaries for Ubuntu 22 x86_64:
  - `bin/` - Core executable binaries
  - `cgi-sys/` - CGI system files
  - `libexec/` - Library executables
  - `whostmgr/` - WHM (Web Host Manager) binaries

- `ubuntu_pool/` - Debian packages for Ubuntu distribution

## Download Sources

All files are sourced from:
- Base URL: http://httpupdate.cpanel.net/cpanelsync/11.128.0.15/
- Ubuntu packages: http://httpupdate.cpanel.net/ubuntu/pool/

## Analysis Results

Based on the installation logs, this version downloads:
- 917 total files identified in the installation process
- Core Perl modules and dependencies
- cPanel-specific binaries and tools
- Web interface themes and assets
- System utilities and daemons

This represents the essential files needed for a complete cPanel installation.