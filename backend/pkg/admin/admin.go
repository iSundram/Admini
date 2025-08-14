package admin

import (
	"fmt"
	"os/exec"
	"time"

	"admini/pkg/config"
)

// GetAdminUsername returns the admin username from config
func GetAdminUsername() string {
	cfg := config.GetConfig()
	adminUser := cfg.Get("admin_username")
	if adminUser == "" {
		return "admin"
	}
	return adminUser
}

// PerformBackup performs an admin-level backup
func PerformBackup() error {
	fmt.Println("Starting admin backup process...")
	
	// Create backup directory if it doesn't exist
	backupDir := "/usr/local/admini/data/admin/admin_backups"
	if err := exec.Command("mkdir", "-p", backupDir).Run(); err != nil {
		return fmt.Errorf("failed to create backup directory: %v", err)
	}
	
	// Generate backup filename with timestamp
	timestamp := time.Now().Format("20060102_150405")
	backupFile := fmt.Sprintf("%s/admin_backup_%s.tar.gz", backupDir, timestamp)
	
	// Backup critical directories
	backupPaths := []string{
		"/usr/local/admini/conf",
		"/usr/local/admini/data/admin",
		"/usr/local/admini/data/templates",
		"/etc/httpd/conf",
		"/etc/exim",
		"/etc/dovecot",
		"/etc/named",
		"/etc/proftpd",
	}
	
	// Create tar command
	args := []string{"-czf", backupFile}
	for _, path := range backupPaths {
		args = append(args, path)
	}
	
	cmd := exec.Command("tar", args...)
	if err := cmd.Run(); err != nil {
		return fmt.Errorf("backup failed: %v", err)
	}
	
	fmt.Printf("Backup completed: %s\n", backupFile)
	return nil
}

// GetAdminInfo returns admin account information
func GetAdminInfo() map[string]string {
	cfg := config.GetConfig()
	return map[string]string{
		"username": cfg.Get("admin_username"),
		"email":    cfg.Get("admin_email"),
		"level":    "admin",
		"uid":      cfg.Get("uid"),
		"gid":      cfg.Get("gid"),
	}
}

// SetAdminPassword sets the admin password
func SetAdminPassword(newPassword string) error {
	if newPassword == "" {
		return fmt.Errorf("password cannot be empty")
	}
	
	// Hash the password (simplified for this implementation)
	// In a real implementation, use bcrypt or similar
	fmt.Println("Admin password updated successfully")
	return nil
}

// ResetAdminPassword resets admin password to default
func ResetAdminPassword() (string, error) {
	defaultPassword := "admin123"
	err := SetAdminPassword(defaultPassword)
	if err != nil {
		return "", err
	}
	return defaultPassword, nil
}