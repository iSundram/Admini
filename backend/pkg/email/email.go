package email

import (
	"fmt"
	"os"
	"path/filepath"
	"strings"
	"time"

	"directadmin/pkg/models"
)

// EmailManager handles all email-related operations
type EmailManager struct {
	dataPath string
}

// NewEmailManager creates a new email manager
func NewEmailManager(dataPath string) *EmailManager {
	return &EmailManager{
		dataPath: dataPath,
	}
}

// CreateEmailAccount creates a new email account
func (em *EmailManager) CreateEmailAccount(username, domain, email, password string, quota int64) error {
	// Create email account directory structure
	emailPath := filepath.Join(em.dataPath, "mail", domain, email)
	if err := os.MkdirAll(emailPath, 0755); err != nil {
		return fmt.Errorf("failed to create email directory: %w", err)
	}

	// Create subdirectories for mailbox
	subdirs := []string{"cur", "new", "tmp", ".Sent", ".Trash", ".Drafts", ".Spam"}
	for _, subdir := range subdirs {
		if err := os.MkdirAll(filepath.Join(emailPath, subdir), 0755); err != nil {
			return fmt.Errorf("failed to create mailbox subdir %s: %w", subdir, err)
		}
	}

	// Create password file
	passwdFile := filepath.Join(em.dataPath, "passwd", fmt.Sprintf("%s_%s", domain, email))
	if err := os.WriteFile(passwdFile, []byte(password), 0600); err != nil {
		return fmt.Errorf("failed to create password file: %w", err)
	}

	// Create quota file
	quotaFile := filepath.Join(em.dataPath, "quota", fmt.Sprintf("%s_%s", domain, email))
	quotaContent := fmt.Sprintf("%d", quota)
	if err := os.WriteFile(quotaFile, []byte(quotaContent), 0644); err != nil {
		return fmt.Errorf("failed to create quota file: %w", err)
	}

	return nil
}

// ListEmailAccounts returns all email accounts for a domain
func (em *EmailManager) ListEmailAccounts(domain string) ([]models.EmailAccount, error) {
	var accounts []models.EmailAccount

	mailDir := filepath.Join(em.dataPath, "mail", domain)
	if _, err := os.Stat(mailDir); os.IsNotExist(err) {
		return accounts, nil
	}

	entries, err := os.ReadDir(mailDir)
	if err != nil {
		return nil, fmt.Errorf("failed to read mail directory: %w", err)
	}

	for _, entry := range entries {
		if entry.IsDir() {
			email := entry.Name()
			account, err := em.getEmailAccountInfo(domain, email)
			if err != nil {
				continue // Skip accounts with errors
			}
			accounts = append(accounts, *account)
		}
	}

	return accounts, nil
}

// getEmailAccountInfo retrieves information about a specific email account
func (em *EmailManager) getEmailAccountInfo(domain, email string) (*models.EmailAccount, error) {
	// Read quota
	quotaFile := filepath.Join(em.dataPath, "quota", fmt.Sprintf("%s_%s", domain, email))
	quotaData, err := os.ReadFile(quotaFile)
	var quota int64 = 0
	if err == nil {
		fmt.Sscanf(string(quotaData), "%d", &quota)
	}

	// Calculate usage
	mailPath := filepath.Join(em.dataPath, "mail", domain, email)
	usage, err := em.calculateDirectorySize(mailPath)
	if err != nil {
		usage = 0
	}

	return &models.EmailAccount{
		Email:     email,
		Domain:    domain,
		Quota:     quota,
		Usage:     usage,
		Suspended: false, // TODO: Check suspension status
		Vacation:  false, // TODO: Check vacation status
	}, nil
}

// calculateDirectorySize calculates the total size of a directory
func (em *EmailManager) calculateDirectorySize(path string) (int64, error) {
	var size int64
	err := filepath.Walk(path, func(_ string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if !info.IsDir() {
			size += info.Size()
		}
		return nil
	})
	return size, err
}

// DeleteEmailAccount removes an email account
func (em *EmailManager) DeleteEmailAccount(domain, email string) error {
	// Remove mail directory
	mailPath := filepath.Join(em.dataPath, "mail", domain, email)
	if err := os.RemoveAll(mailPath); err != nil {
		return fmt.Errorf("failed to remove mail directory: %w", err)
	}

	// Remove password file
	passwdFile := filepath.Join(em.dataPath, "passwd", fmt.Sprintf("%s_%s", domain, email))
	os.Remove(passwdFile) // Ignore errors

	// Remove quota file
	quotaFile := filepath.Join(em.dataPath, "quota", fmt.Sprintf("%s_%s", domain, email))
	os.Remove(quotaFile) // Ignore errors

	return nil
}

// ChangeEmailPassword changes the password for an email account
func (em *EmailManager) ChangeEmailPassword(domain, email, newPassword string) error {
	passwdFile := filepath.Join(em.dataPath, "passwd", fmt.Sprintf("%s_%s", domain, email))
	return os.WriteFile(passwdFile, []byte(newPassword), 0600)
}

// SetEmailQuota sets the quota for an email account
func (em *EmailManager) SetEmailQuota(domain, email string, quota int64) error {
	quotaFile := filepath.Join(em.dataPath, "quota", fmt.Sprintf("%s_%s", domain, email))
	quotaContent := fmt.Sprintf("%d", quota)
	return os.WriteFile(quotaFile, []byte(quotaContent), 0644)
}

// CreateEmailForwarder creates an email forwarder
func (em *EmailManager) CreateEmailForwarder(domain, source, destination string) error {
	forwardersFile := filepath.Join(em.dataPath, "forwarders", domain)
	
	// Read existing forwarders
	content := ""
	if data, err := os.ReadFile(forwardersFile); err == nil {
		content = string(data)
	}

	// Add new forwarder
	forwarder := fmt.Sprintf("%s: %s\n", source, destination)
	content += forwarder

	// Create directory if it doesn't exist
	if err := os.MkdirAll(filepath.Dir(forwardersFile), 0755); err != nil {
		return fmt.Errorf("failed to create forwarders directory: %w", err)
	}

	return os.WriteFile(forwardersFile, []byte(content), 0644)
}

// ListEmailForwarders returns all email forwarders for a domain
func (em *EmailManager) ListEmailForwarders(domain string) (map[string]string, error) {
	forwarders := make(map[string]string)
	
	forwardersFile := filepath.Join(em.dataPath, "forwarders", domain)
	data, err := os.ReadFile(forwardersFile)
	if err != nil {
		if os.IsNotExist(err) {
			return forwarders, nil
		}
		return nil, fmt.Errorf("failed to read forwarders file: %w", err)
	}

	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		
		parts := strings.SplitN(line, ":", 2)
		if len(parts) == 2 {
			source := strings.TrimSpace(parts[0])
			destination := strings.TrimSpace(parts[1])
			forwarders[source] = destination
		}
	}

	return forwarders, nil
}

// SetVacationMessage sets vacation auto-responder for an email account
func (em *EmailManager) SetVacationMessage(domain, email, message string, enabled bool) error {
	vacationDir := filepath.Join(em.dataPath, "vacation", domain)
	if err := os.MkdirAll(vacationDir, 0755); err != nil {
		return fmt.Errorf("failed to create vacation directory: %w", err)
	}

	vacationFile := filepath.Join(vacationDir, email)
	
	if enabled {
		vacationContent := fmt.Sprintf("enabled=1\nmessage=%s\n", message)
		return os.WriteFile(vacationFile, []byte(vacationContent), 0644)
	} else {
		return os.Remove(vacationFile)
	}
}

// GetEmailFilters returns email filters for an account
func (em *EmailManager) GetEmailFilters(domain, email string) ([]map[string]string, error) {
	filters := []map[string]string{}
	
	filtersFile := filepath.Join(em.dataPath, "filters", domain, email)
	data, err := os.ReadFile(filtersFile)
	if err != nil {
		if os.IsNotExist(err) {
			return filters, nil
		}
		return nil, fmt.Errorf("failed to read filters file: %w", err)
	}

	// Parse filter file (simplified parsing)
	lines := strings.Split(string(data), "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		
		// Parse filter rule (simplified)
		filter := map[string]string{
			"rule": line,
			"type": "forward", // Default type
		}
		filters = append(filters, filter)
	}

	return filters, nil
}

// CreateEmailFilter creates a new email filter
func (em *EmailManager) CreateEmailFilter(domain, email, condition, action string) error {
	filtersDir := filepath.Join(em.dataPath, "filters", domain)
	if err := os.MkdirAll(filtersDir, 0755); err != nil {
		return fmt.Errorf("failed to create filters directory: %w", err)
	}

	filtersFile := filepath.Join(filtersDir, email)
	
	// Read existing filters
	content := ""
	if data, err := os.ReadFile(filtersFile); err == nil {
		content = string(data)
	}

	// Add new filter
	filter := fmt.Sprintf("# Filter created at %s\nif %s then %s\n", time.Now().Format(time.RFC3339), condition, action)
	content += filter

	return os.WriteFile(filtersFile, []byte(content), 0644)
}

// GetEmailStatistics returns email statistics for a domain
func (em *EmailManager) GetEmailStatistics(domain string) (map[string]interface{}, error) {
	stats := map[string]interface{}{
		"total_accounts": 0,
		"total_usage":    int64(0),
		"total_quota":    int64(0),
	}

	accounts, err := em.ListEmailAccounts(domain)
	if err != nil {
		return stats, err
	}

	var totalUsage, totalQuota int64
	for _, account := range accounts {
		totalUsage += account.Usage
		totalQuota += account.Quota
	}

	stats["total_accounts"] = len(accounts)
	stats["total_usage"] = totalUsage
	stats["total_quota"] = totalQuota

	return stats, nil
}