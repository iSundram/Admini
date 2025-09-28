#!/bin/bash
## Script to clear caches after static file changes. Useful for development and testing.
## All credit belongs to Usman Nasir
## To use make it executable
## chmod +x /usr/local/core/upgrade.sh
## Then run it like below.
## /usr/local/core/upgrade.sh

# Check if virtual environment exists
if [[ ! -f /usr/local/core/bin/python ]]; then
    echo "Error: CyberPanel virtual environment not found at /usr/local/core/bin/python"
    echo "Please ensure CyberPanel is properly installed."
    exit 1
fi

cd /usr/local/core && /usr/local/core/bin/python manage.py collectstatic --no-input
rm -rf /usr/local/core/public/static/*
cp -R  /usr/local/core/static/* /usr/local/core/public/static/
# CSF support removed - discontinued on August 31, 2025
# mkdir /usr/local/core/public/static/csf/
find /usr/local/core -type d -exec chmod 0755 {} \;
find /usr/local/core -type f -exec chmod 0644 {} \;
chmod -R 755 /usr/local/core/bin
chown -R root:root /usr/local/core
chown -R lscpd:lscpd /usr/local/core/public/phpmyadmin/tmp
systemctl restart lscpd
