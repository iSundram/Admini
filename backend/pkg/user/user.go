package user

import (
	"fmt"
	"os"
	"path/filepath"
	"strconv"
	"strings"
)

// User represents a user in DirectAdmin
type User struct {
	Username  string
	Level     string // admin, reseller, user
	Suspended bool
	Email     string
	Quota     int64
	Bandwidth int64
	Domains   []string
}

// Suspend suspends a user account
func Suspend(username string) error {
	if username == "" {
		return fmt.Errorf("username cannot be empty")
	}
	
	// Create suspension marker
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	suspensionFile := filepath.Join(userPath, "user.suspended")
	
	file, err := os.Create(suspensionFile)
	if err != nil {
		return fmt.Errorf("failed to create suspension file: %v", err)
	}
	file.Close()
	
	// Update user configuration
	return updateUserConfig(username, "suspended", "yes")
}

// Unsuspend unsuspends a user account
func Unsuspend(username string) error {
	if username == "" {
		return fmt.Errorf("username cannot be empty")
	}
	
	// Remove suspension marker
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	suspensionFile := filepath.Join(userPath, "user.suspended")
	
	if err := os.Remove(suspensionFile); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("failed to remove suspension file: %v", err)
	}
	
	// Update user configuration
	return updateUserConfig(username, "suspended", "no")
}

// Create creates a new user account
func Create(username, password, email string, quota int64) error {
	if username == "" || password == "" {
		return fmt.Errorf("username and password cannot be empty")
	}
	
	// Create user directory structure
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	if err := os.MkdirAll(userPath, 0755); err != nil {
		return fmt.Errorf("failed to create user directory: %v", err)
	}
	
	// Create subdirectories
	subdirs := []string{"domains", "backups", "imap", "logs", "php"}
	for _, subdir := range subdirs {
		if err := os.MkdirAll(filepath.Join(userPath, subdir), 0755); err != nil {
			return fmt.Errorf("failed to create user subdirectory %s: %v", subdir, err)
		}
	}
	
	// Create user configuration
	return createUserConfig(username, email, quota)
}

// Delete removes a user account
func Delete(username string) error {
	if username == "" {
		return fmt.Errorf("username cannot be empty")
	}
	
	// Remove user directory
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	if err := os.RemoveAll(userPath); err != nil {
		return fmt.Errorf("failed to remove user directory: %v", err)
	}
	
	return nil
}

// Get returns user information
func Get(username string) (*User, error) {
	if username == "" {
		return nil, fmt.Errorf("username cannot be empty")
	}
	
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	if _, err := os.Stat(userPath); os.IsNotExist(err) {
		return nil, fmt.Errorf("user %s does not exist", username)
	}
	
	user := &User{
		Username:  username,
		Level:     getUserLevel(username),
		Suspended: isUserSuspended(username),
		Email:     getUserEmail(username),
		Quota:     getUserQuota(username),
		Bandwidth: getUserBandwidth(username),
		Domains:   getUserDomains(username),
	}
	
	return user, nil
}

// List returns all users in the system
func List() ([]User, error) {
	usersPath := "/usr/local/directadmin/data/users"
	entries, err := os.ReadDir(usersPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read users directory: %v", err)
	}
	
	var users []User
	for _, entry := range entries {
		if entry.IsDir() {
			user, err := Get(entry.Name())
			if err != nil {
				continue // Skip invalid users
			}
			users = append(users, *user)
		}
	}
	
	return users, nil
}

// Update modifies user properties
func Update(username string, properties map[string]string) error {
	if username == "" {
		return fmt.Errorf("username cannot be empty")
	}
	
	for key, value := range properties {
		if err := updateUserConfig(username, key, value); err != nil {
			return fmt.Errorf("failed to update %s: %v", key, err)
		}
	}
	
	return nil
}

// ChangePassword changes user password
func ChangePassword(username, newPassword string) error {
	if username == "" || newPassword == "" {
		return fmt.Errorf("username and password cannot be empty")
	}
	
	// In a real implementation, this would hash the password and update system files
	return updateUserConfig(username, "passwd", newPassword)
}

func createUserConfig(username, email string, quota int64) error {
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	configFile := filepath.Join(userPath, "user.conf")
	
	configContent := fmt.Sprintf(`username=%s
email=%s
quota=%d
bandwidth=unlimited
suspended=no
level=user
created=%s
`, username, email, quota, "1234567890")
	
	return os.WriteFile(configFile, []byte(configContent), 0644)
}

func updateUserConfig(username, key, value string) error {
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	configFile := filepath.Join(userPath, "user.conf")
	
	// Read current config
	data, err := os.ReadFile(configFile)
	if err != nil {
		return err
	}
	
	lines := strings.Split(string(data), "\n")
	updated := false
	
	// Update existing key or add new one
	for i, line := range lines {
		if strings.HasPrefix(line, key+"=") {
			lines[i] = fmt.Sprintf("%s=%s", key, value)
			updated = true
			break
		}
	}
	
	if !updated {
		lines = append(lines, fmt.Sprintf("%s=%s", key, value))
	}
	
	// Write updated config
	return os.WriteFile(configFile, []byte(strings.Join(lines, "\n")), 0644)
}

func getUserLevel(username string) string {
	if username == "admin" {
		return "admin"
	}
	// Check user config for level
	return "user"
}

func isUserSuspended(username string) bool {
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	suspensionFile := filepath.Join(userPath, "user.suspended")
	_, err := os.Stat(suspensionFile)
	return err == nil
}

func getUserEmail(username string) string {
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	configFile := filepath.Join(userPath, "user.conf")
	
	data, err := os.ReadFile(configFile)
	if err != nil {
		return ""
	}
	
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "email=") {
			return strings.TrimPrefix(line, "email=")
		}
	}
	
	return ""
}

func getUserQuota(username string) int64 {
	userPath := filepath.Join("/usr/local/directadmin/data/users", username)
	configFile := filepath.Join(userPath, "user.conf")
	
	data, err := os.ReadFile(configFile)
	if err != nil {
		return 0
	}
	
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		if strings.HasPrefix(line, "quota=") {
			quotaStr := strings.TrimPrefix(line, "quota=")
			quota, _ := strconv.ParseInt(quotaStr, 10, 64)
			return quota
		}
	}
	
	return 0
}

func getUserBandwidth(username string) int64 {
	// Simplified implementation
	return 0
}

func getUserDomains(username string) []string {
	domainsPath := filepath.Join("/usr/local/directadmin/data/users", username, "domains")
	entries, err := os.ReadDir(domainsPath)
	if err != nil {
		return []string{}
	}
	
	var domains []string
	for _, entry := range entries {
		if entry.IsDir() {
			domains = append(domains, entry.Name())
		}
	}
	
	return domains
}