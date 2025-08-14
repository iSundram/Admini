package domain

import (
	"fmt"
	"os"
	"path/filepath"
)

// Domain represents a domain in DirectAdmin
type Domain struct {
	Name      string
	User      string
	Suspended bool
	Path      string
	SSL       bool
}

// Suspend suspends a domain
func Suspend(domainName string) error {
	if domainName == "" {
		return fmt.Errorf("domain name cannot be empty")
	}
	
	// Create suspended directory if it doesn't exist
	suspendedDir := "/usr/local/admini/data/templates/suspended"
	if err := os.MkdirAll(suspendedDir, 0755); err != nil {
		return fmt.Errorf("failed to create suspended directory: %v", err)
	}
	
	// Create suspension marker file
	suspensionFile := filepath.Join(suspendedDir, domainName+".suspended")
	file, err := os.Create(suspensionFile)
	if err != nil {
		return fmt.Errorf("failed to create suspension file: %v", err)
	}
	file.Close()
	
	// Update domain configuration to point to suspended page
	return updateDomainConfig(domainName, true)
}

// Unsuspend unsuspends a domain
func Unsuspend(domainName string) error {
	if domainName == "" {
		return fmt.Errorf("domain name cannot be empty")
	}
	
	// Remove suspension marker file
	suspensionFile := filepath.Join("/usr/local/admini/data/templates/suspended", domainName+".suspended")
	if err := os.Remove(suspensionFile); err != nil && !os.IsNotExist(err) {
		return fmt.Errorf("failed to remove suspension file: %v", err)
	}
	
	// Update domain configuration to restore normal operation
	return updateDomainConfig(domainName, false)
}

// Create creates a new domain
func Create(domainName, username string) error {
	if domainName == "" || username == "" {
		return fmt.Errorf("domain name and username cannot be empty")
	}
	
	// Create domain directory structure
	domainPath := filepath.Join("/usr/local/admini/data/users", username, "domains", domainName)
	publicHTMLPath := filepath.Join(domainPath, "public_html")
	
	if err := os.MkdirAll(publicHTMLPath, 0755); err != nil {
		return fmt.Errorf("failed to create domain directory: %v", err)
	}
	
	// Create basic index.html
	indexFile := filepath.Join(publicHTMLPath, "index.html")
	indexContent := fmt.Sprintf(`<!DOCTYPE html>
<html>
<head>
    <title>Welcome to %s</title>
</head>
<body>
    <h1>Welcome to %s</h1>
    <p>This domain is hosted by DirectAdmin.</p>
</body>
</html>`, domainName, domainName)
	
	if err := os.WriteFile(indexFile, []byte(indexContent), 0644); err != nil {
		return fmt.Errorf("failed to create index file: %v", err)
	}
	
	// Create domain configuration
	return createDomainConfig(domainName, username)
}

// Delete removes a domain
func Delete(domainName, username string) error {
	if domainName == "" || username == "" {
		return fmt.Errorf("domain name and username cannot be empty")
	}
	
	// Remove domain directory
	domainPath := filepath.Join("/usr/local/admini/data/users", username, "domains", domainName)
	if err := os.RemoveAll(domainPath); err != nil {
		return fmt.Errorf("failed to remove domain directory: %v", err)
	}
	
	// Remove domain configuration
	return removeDomainConfig(domainName, username)
}

// List returns all domains for a user
func List(username string) ([]Domain, error) {
	if username == "" {
		return nil, fmt.Errorf("username cannot be empty")
	}
	
	domainsPath := filepath.Join("/usr/local/admini/data/users", username, "domains")
	entries, err := os.ReadDir(domainsPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read domains directory: %v", err)
	}
	
	var domains []Domain
	for _, entry := range entries {
		if entry.IsDir() {
			domain := Domain{
				Name:      entry.Name(),
				User:      username,
				Suspended: isDomainSuspended(entry.Name()),
				Path:      filepath.Join(domainsPath, entry.Name()),
				SSL:       hasSSL(entry.Name()),
			}
			domains = append(domains, domain)
		}
	}
	
	return domains, nil
}

// GetAllDomains returns all domains in the system
func GetAllDomains() ([]Domain, error) {
	usersPath := "/usr/local/admini/data/users"
	entries, err := os.ReadDir(usersPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read users directory: %v", err)
	}
	
	var allDomains []Domain
	for _, entry := range entries {
		if entry.IsDir() {
			domains, err := List(entry.Name())
			if err != nil {
				continue // Skip users with no domains
			}
			allDomains = append(allDomains, domains...)
		}
	}
	
	return allDomains, nil
}

func updateDomainConfig(domainName string, suspended bool) error {
	// In a real implementation, this would update Apache/Nginx configuration
	configPath := filepath.Join("/usr/local/admini/data/users", "*/domains", domainName, "domain.conf")
	fmt.Printf("Updating domain config for %s (suspended: %v) at %s\n", domainName, suspended, configPath)
	return nil
}

func createDomainConfig(domainName, username string) error {
	// Create domain configuration file
	configPath := filepath.Join("/usr/local/admini/data/users", username, "domains", domainName, "domain.conf")
	configDir := filepath.Dir(configPath)
	
	if err := os.MkdirAll(configDir, 0755); err != nil {
		return err
	}
	
	configContent := fmt.Sprintf(`domain=%s
user=%s
DocumentRoot=/usr/local/admini/data/users/%s/domains/%s/public_html
suspended=no
ssl=no
`, domainName, username, username, domainName)
	
	return os.WriteFile(configPath, []byte(configContent), 0644)
}

func removeDomainConfig(domainName, username string) error {
	configPath := filepath.Join("/usr/local/admini/data/users", username, "domains", domainName, "domain.conf")
	return os.Remove(configPath)
}

func isDomainSuspended(domainName string) bool {
	suspensionFile := filepath.Join("/usr/local/admini/data/templates/suspended", domainName+".suspended")
	_, err := os.Stat(suspensionFile)
	return err == nil
}

func hasSSL(domainName string) bool {
	// Check if SSL certificate exists
	sslPath := filepath.Join("/usr/local/admini/data/users/*/domains", domainName, "*.cert")
	fmt.Printf("Checking SSL for domain %s at %s\n", domainName, sslPath)
	return false // Simplified for this implementation
}