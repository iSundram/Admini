#!/usr/bin/env python3
"""
cPanel Installation Script
Extracts cpanel.zip, configures version 11.128.0.15, and sets up installation files
"""

import os
import sys
import zipfile
import shutil
import subprocess
import logging
from datetime import datetime
from pathlib import Path

# Configuration
CPANEL_VERSION = "11.128.0.15"
HTTPUPDATE_SOURCE = "httpupdate.cpanel.net"
CPANEL_BASE_DIR = "/usr/local/cpanel"
LOG_FILE = "cpanel_installation.log"

class CpanelInstaller:
    def __init__(self):
        self.setup_logging()
        self.repo_dir = Path(__file__).parent.absolute()
        self.cpanel_zip = self.repo_dir / "cpanel.zip"
        
    def setup_logging(self):
        """Set up logging to both file and console"""
        logging.basicConfig(
            level=logging.INFO,
            format='%(asctime)s - %(levelname)s - %(message)s',
            handlers=[
                logging.FileHandler(LOG_FILE),
                logging.StreamHandler(sys.stdout)
            ]
        )
        self.logger = logging.getLogger(__name__)
        
    def extract_cpanel_zip(self):
        """Extract cpanel.zip to a temporary directory"""
        if not self.cpanel_zip.exists():
            self.logger.error(f"cpanel.zip not found at {self.cpanel_zip}")
            return False
            
        extract_dir = self.repo_dir / "cpanel_extracted"
        if extract_dir.exists():
            shutil.rmtree(extract_dir)
            
        try:
            with zipfile.ZipFile(self.cpanel_zip, 'r') as zip_ref:
                zip_ref.extractall(extract_dir)
            self.logger.info(f"Successfully extracted cpanel.zip to {extract_dir}")
            return extract_dir
        except Exception as e:
            self.logger.error(f"Failed to extract cpanel.zip: {e}")
            return False
            
    def create_cpanel_directories(self):
        """Create necessary cPanel directory structure"""
        directories = [
            f"{CPANEL_BASE_DIR}/scripts",
            f"{CPANEL_BASE_DIR}/bin", 
            f"{CPANEL_BASE_DIR}/3rdparty/bin",
            "/var/cpanel",
            "/etc"
        ]
        
        for directory in directories:
            try:
                os.makedirs(directory, mode=0o755, exist_ok=True)
                self.logger.info(f"Created directory: {directory}")
            except PermissionError:
                self.logger.warning(f"Permission denied creating {directory}, trying with sudo")
                try:
                    subprocess.run(['sudo', 'mkdir', '-p', directory], check=True)
                    subprocess.run(['sudo', 'chmod', '755', directory], check=True)
                    self.logger.info(f"Created directory with sudo: {directory}")
                except subprocess.CalledProcessError as e:
                    self.logger.error(f"Failed to create directory {directory}: {e}")
                    return False
        return True
        
    def configure_cpanel_version(self):
        """Configure cPanel to use version 11.128.0.15"""
        config_files = {
            "/etc/cpupdate.conf": f"CPANEL={CPANEL_VERSION}\n",
            "/etc/cpsources.conf": f"HTTPUPDATE={HTTPUPDATE_SOURCE}\n"
        }
        
        for config_file, content in config_files.items():
            try:
                # Try to write directly first
                with open(config_file, 'w') as f:
                    f.write(content)
                self.logger.info(f"Created config file: {config_file}")
            except PermissionError:
                try:
                    # Create temp file in current directory and use sudo to move it
                    temp_file = f"{config_file.split('/')[-1]}.tmp"
                    with open(temp_file, 'w') as f:
                        f.write(content)
                    subprocess.run(['sudo', 'mv', temp_file, config_file], check=True)
                    subprocess.run(['sudo', 'chown', 'root:root', config_file], check=True)
                    subprocess.run(['sudo', 'chmod', '644', config_file], check=True)
                    self.logger.info(f"Created config file with sudo: {config_file}")
                except Exception as e:
                    self.logger.error(f"Failed to create config file {config_file}: {e}")
                    return False
        return True
        
    def copy_installer_files(self, extract_dir):
        """Copy installer files to appropriate locations"""
        if not extract_dir:
            return False
            
        cpanel_src_dir = extract_dir / "cpanel"
        if not cpanel_src_dir.exists():
            self.logger.error(f"cpanel directory not found in extracted files")
            return False
            
        # Key files to copy
        file_mappings = {
            "updatenow.static": f"{CPANEL_BASE_DIR}/scripts/updatenow",
            "install": f"{CPANEL_BASE_DIR}/scripts/install", 
            "cpanel_initial_install": f"{CPANEL_BASE_DIR}/scripts/cpanel_initial_install",
            "Installer.pm": f"{CPANEL_BASE_DIR}/Installer.pm",
            "CpanelConfig.pm": f"{CPANEL_BASE_DIR}/CpanelConfig.pm",
            "Common.pm": f"{CPANEL_BASE_DIR}/Common.pm",
            "CpanelLogger.pm": f"{CPANEL_BASE_DIR}/CpanelLogger.pm",
            "VERSION": f"{CPANEL_BASE_DIR}/VERSION"
        }
        
        for src_file, dest_file in file_mappings.items():
            src_path = cpanel_src_dir / src_file
            if src_path.exists():
                try:
                    # Create destination directory if needed
                    dest_dir = Path(dest_file).parent
                    dest_dir.mkdir(parents=True, exist_ok=True)
                    
                    shutil.copy2(src_path, dest_file)
                    
                    # Make scripts executable
                    if dest_file.endswith(('updatenow', 'install', 'cpanel_initial_install')):
                        os.chmod(dest_file, 0o755)
                        
                    self.logger.info(f"Copied {src_file} to {dest_file}")
                except PermissionError:
                    try:
                        subprocess.run(['sudo', 'cp', str(src_path), dest_file], check=True)
                        if dest_file.endswith(('updatenow', 'install', 'cpanel_initial_install')):
                            subprocess.run(['sudo', 'chmod', '755', dest_file], check=True)
                        self.logger.info(f"Copied with sudo {src_file} to {dest_file}")
                    except subprocess.CalledProcessError as e:
                        self.logger.error(f"Failed to copy {src_file}: {e}")
                        return False
            else:
                self.logger.warning(f"Source file not found: {src_file}")
                
        return True
        
    def create_update_script(self):
        """Create a script to run the updatenow process"""
        update_script = self.repo_dir / "run_cpanel_update.sh"
        script_content = f"""#!/bin/bash
# cPanel Update Script - Downloads and installs cPanel files
# Version: {CPANEL_VERSION}
# Source: {HTTPUPDATE_SOURCE}

set -e

echo "Starting cPanel update process..."
echo "Version: {CPANEL_VERSION}"
echo "Source: {HTTPUPDATE_SOURCE}/cpanelsync/"
echo "Log file: {LOG_FILE}"

# Ensure we're running as root
if [[ $EUID -ne 0 ]]; then
   echo "This script must be run as root" 
   exit 1
fi

# Check if updatenow script exists
if [ ! -f "{CPANEL_BASE_DIR}/scripts/updatenow" ]; then
    echo "Error: updatenow script not found at {CPANEL_BASE_DIR}/scripts/updatenow"
    echo "Please run the cpanel_installer.py first"
    exit 1
fi

# Set environment variables
export CPANEL_BASE_INSTALL=1
export HTTPUPDATE={HTTPUPDATE_SOURCE}
export CPANEL={CPANEL_VERSION}

# Run the updatenow script
echo "Executing updatenow script..."
cd {CPANEL_BASE_DIR}/scripts
./updatenow

echo "cPanel update process completed!"
echo "Check {LOG_FILE} for detailed logs"
"""
        
        try:
            with open(update_script, 'w') as f:
                f.write(script_content)
            os.chmod(update_script, 0o755)
            self.logger.info(f"Created update script: {update_script}")
            return True
        except Exception as e:
            self.logger.error(f"Failed to create update script: {e}")
            return False
            
    def run_installation(self):
        """Main installation process"""
        self.logger.info("Starting cPanel installation setup...")
        self.logger.info(f"Target version: {CPANEL_VERSION}")
        self.logger.info(f"Update source: {HTTPUPDATE_SOURCE}/cpanelsync/")
        
        # Step 1: Extract cpanel.zip
        extract_dir = self.extract_cpanel_zip()
        if not extract_dir:
            return False
            
        # Step 2: Create directory structure  
        if not self.create_cpanel_directories():
            return False
            
        # Step 3: Configure version and source
        if not self.configure_cpanel_version():
            return False
            
        # Step 4: Copy installer files
        if not self.copy_installer_files(extract_dir):
            return False
            
        # Step 5: Create update script
        if not self.create_update_script():
            return False
            
        self.logger.info("cPanel installation setup completed successfully!")
        self.logger.info(f"Files installed to: {CPANEL_BASE_DIR}")
        self.logger.info(f"Configuration files created in /etc/")
        self.logger.info(f"Run ./run_cpanel_update.sh to start the update process")
        
        return True

if __name__ == "__main__":
    installer = CpanelInstaller()
    success = installer.run_installation()
    sys.exit(0 if success else 1)