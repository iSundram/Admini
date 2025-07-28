#!/bin/bash
# cPanel Update Script - Downloads and installs cPanel files
# Version: 11.128.0.15
# Source: httpupdate.cpanel.net

set -e

echo "Starting cPanel update process..."
echo "Version: 11.128.0.15"
echo "Source: httpupdate.cpanel.net/cpanelsync/"
echo "Log file: cpanel_installation.log"

# Ensure we're running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 
   exit 1
fi

# Check if updatenow script exists
if [ ! -f "/usr/local/cpanel/scripts/updatenow" ]; then
    echo "Error: updatenow script not found at /usr/local/cpanel/scripts/updatenow"
    echo "Please run the cpanel_installer.py first"
    exit 1
fi

# Set environment variables
export CPANEL_BASE_INSTALL=1
export HTTPUPDATE=httpupdate.cpanel.net
export CPANEL=11.128.0.15

# Run the updatenow script
echo "Executing updatenow script..."
cd /usr/local/cpanel/scripts
./updatenow

echo "cPanel update process completed!"
echo "Check cpanel_installation.log for detailed logs"
