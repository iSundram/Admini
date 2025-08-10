package license

import (
	"fmt"
	"io/ioutil"
	"os"
	"strings"
	"time"

	"directadmin/pkg/config"
)

type License struct {
	Key        string
	ServerID   string
	ExpiryDate time.Time
	MaxUsers   int
	MaxDomains int
	Valid      bool
}

const licenseFile = "/usr/local/directadmin/conf/license.key"

// GetLicense returns the current license information
func GetLicense() *License {
	license := &License{
		Key:        "DEMO-LICENSE-KEY",
		ServerID:   "localhost",
		ExpiryDate: time.Now().AddDate(1, 0, 0), // 1 year from now
		MaxUsers:   10,
		MaxDomains: 50,
		Valid:      true,
	}
	
	// Try to read from license file
	if data, err := ioutil.ReadFile(licenseFile); err == nil {
		license.parseFromFile(string(data))
	}
	
	return license
}

// SetLicense sets a new license key
func SetLicense(key string) error {
	if key == "" {
		return fmt.Errorf("license key cannot be empty")
	}
	
	// Validate license key format (simplified)
	if len(key) < 10 {
		return fmt.Errorf("invalid license key format")
	}
	
	// Create license directory if it doesn't exist
	licenseDir := "/usr/local/directadmin/conf"
	if err := os.MkdirAll(licenseDir, 0755); err != nil {
		return fmt.Errorf("failed to create license directory: %v", err)
	}
	
	// Write license key to file
	err := ioutil.WriteFile(licenseFile, []byte(key), 0600)
	if err != nil {
		return fmt.Errorf("failed to write license file: %v", err)
	}
	
	// Update config
	cfg := config.GetConfig()
	cfg.Set("license_key", key)
	
	return nil
}

// ValidateLicense validates the current license
func ValidateLicense() bool {
	license := GetLicense()
	
	// Check if license is expired
	if time.Now().After(license.ExpiryDate) {
		return false
	}
	
	// Check if license key is valid (simplified validation)
	if license.Key == "" || license.Key == "DEMO-LICENSE-KEY" {
		return true // Demo license is always valid for testing
	}
	
	return license.Valid
}

// Print displays license information
func (l *License) Print() {
	fmt.Println("DirectAdmin License Information:")
	fmt.Println("================================")
	fmt.Printf("License Key: %s\n", l.maskKey())
	fmt.Printf("Server ID: %s\n", l.ServerID)
	fmt.Printf("Expiry Date: %s\n", l.ExpiryDate.Format("2006-01-02"))
	fmt.Printf("Max Users: %d\n", l.MaxUsers)
	fmt.Printf("Max Domains: %d\n", l.MaxDomains)
	fmt.Printf("Status: %s\n", l.getStatus())
}

func (l *License) maskKey() string {
	if len(l.Key) <= 8 {
		return l.Key
	}
	return l.Key[:4] + "****" + l.Key[len(l.Key)-4:]
}

func (l *License) getStatus() string {
	if !l.Valid {
		return "Invalid"
	}
	if time.Now().After(l.ExpiryDate) {
		return "Expired"
	}
	return "Valid"
}

func (l *License) parseFromFile(data string) {
	lines := strings.Split(data, "\n")
	for _, line := range lines {
		line = strings.TrimSpace(line)
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		
		parts := strings.SplitN(line, "=", 2)
		if len(parts) != 2 {
			continue
		}
		
		key := strings.TrimSpace(parts[0])
		value := strings.TrimSpace(parts[1])
		
		switch key {
		case "key":
			l.Key = value
		case "server_id":
			l.ServerID = value
		case "expiry":
			if t, err := time.Parse("2006-01-02", value); err == nil {
				l.ExpiryDate = t
			}
		}
	}
}