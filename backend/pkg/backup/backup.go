package backup

import (
	"fmt"
	"os"
	"os/exec"
	"path/filepath"
	"time"
)

// BackupOptions defines backup configuration
type BackupOptions struct {
	IncludeEmails    bool
	IncludeDatabases bool
	IncludeDomains   bool
	Compression      bool
	Destination      string
}

// CreateUserBackup creates a backup for a specific user
func CreateUserBackup(username string, options BackupOptions) (string, error) {
	if username == "" {
		return "", fmt.Errorf("username cannot be empty")
	}

	// Create backup directory
	backupDir := "/usr/local/admini/data/admin/admin_backups"
	if err := os.MkdirAll(backupDir, 0755); err != nil {
		return "", fmt.Errorf("failed to create backup directory: %v", err)
	}

	// Generate backup filename
	timestamp := time.Now().Format("20060102_150405")
	var backupFile string
	if options.Compression {
		backupFile = filepath.Join(backupDir, fmt.Sprintf("user_%s_%s.tar.gz", username, timestamp))
	} else {
		backupFile = filepath.Join(backupDir, fmt.Sprintf("user_%s_%s.tar", username, timestamp))
	}

	// Prepare backup paths
	userPath := filepath.Join("/usr/local/admini/data/users", username)
	var backupPaths []string

	// Always include user configuration
	backupPaths = append(backupPaths, userPath)

	// Include domains if requested
	if options.IncludeDomains {
		domainsPath := filepath.Join(userPath, "domains")
		if _, err := os.Stat(domainsPath); err == nil {
			backupPaths = append(backupPaths, domainsPath)
		}
	}

	// Include emails if requested
	if options.IncludeEmails {
		emailPath := filepath.Join(userPath, "imap")
		if _, err := os.Stat(emailPath); err == nil {
			backupPaths = append(backupPaths, emailPath)
		}
	}

	// Create tar command
	var args []string
	if options.Compression {
		args = []string{"-czf", backupFile}
	} else {
		args = []string{"-cf", backupFile}
	}
	args = append(args, backupPaths...)

	// Execute backup
	cmd := exec.Command("tar", args...)
	if err := cmd.Run(); err != nil {
		return "", fmt.Errorf("backup failed: %v", err)
	}

	return backupFile, nil
}

// RestoreUserBackup restores a user from backup
func RestoreUserBackup(backupFile, username string) error {
	if backupFile == "" || username == "" {
		return fmt.Errorf("backup file and username cannot be empty")
	}

	// Check if backup file exists
	if _, err := os.Stat(backupFile); os.IsNotExist(err) {
		return fmt.Errorf("backup file does not exist: %s", backupFile)
	}

	// Create restore directory
	restoreDir := filepath.Join("/usr/local/admini/data/users", username)
	if err := os.MkdirAll(restoreDir, 0755); err != nil {
		return fmt.Errorf("failed to create restore directory: %v", err)
	}

	// Extract backup
	var cmd *exec.Cmd
	if filepath.Ext(backupFile) == ".gz" {
		cmd = exec.Command("tar", "-xzf", backupFile, "-C", "/")
	} else {
		cmd = exec.Command("tar", "-xf", backupFile, "-C", "/")
	}

	if err := cmd.Run(); err != nil {
		return fmt.Errorf("restore failed: %v", err)
	}

	return nil
}

// ListBackups returns available backups
func ListBackups() ([]map[string]string, error) {
	backupDir := "/usr/local/admini/data/admin/admin_backups"
	entries, err := os.ReadDir(backupDir)
	if err != nil {
		return nil, fmt.Errorf("failed to read backup directory: %v", err)
	}

	var backups []map[string]string
	for _, entry := range entries {
		if !entry.IsDir() && (filepath.Ext(entry.Name()) == ".tar" || filepath.Ext(entry.Name()) == ".gz") {
			info, err := entry.Info()
			if err != nil {
				continue
			}

			backup := map[string]string{
				"name":     entry.Name(),
				"path":     filepath.Join(backupDir, entry.Name()),
				"size":     fmt.Sprintf("%d", info.Size()),
				"modified": info.ModTime().Format("2006-01-02 15:04:05"),
			}
			backups = append(backups, backup)
		}
	}

	return backups, nil
}

// DeleteBackup removes a backup file
func DeleteBackup(backupName string) error {
	if backupName == "" {
		return fmt.Errorf("backup name cannot be empty")
	}

	backupDir := "/usr/local/admini/data/admin/admin_backups"
	backupPath := filepath.Join(backupDir, backupName)

	if err := os.Remove(backupPath); err != nil {
		return fmt.Errorf("failed to delete backup: %v", err)
	}

	return nil
}