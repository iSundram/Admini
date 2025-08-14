package cpanel

import (
	"encoding/json"
	"fmt"
	"log"
	"net/http"
	"strconv"
	"time"

	"github.com/gin-gonic/gin"
)

// CPanel represents cPanel-like functionality
type CPanel struct {
	Features []Feature `json:"features"`
}

type Feature struct {
	Name        string `json:"name"`
	Icon        string `json:"icon"`
	Description string `json:"description"`
	URL         string `json:"url"`
	Category    string `json:"category"`
	Enabled     bool   `json:"enabled"`
}

// NewCPanel creates a new cPanel-like feature manager
func NewCPanel() *CPanel {
	return &CPanel{
		Features: getDefaultFeatures(),
	}
}

func getDefaultFeatures() []Feature {
	return []Feature{
		// Files & FTP
		{Name: "File Manager", Icon: "folder", Description: "Manage your website files", URL: "/CMD_FILE_MANAGER", Category: "files", Enabled: true},
		{Name: "FTP Accounts", Icon: "ftp", Description: "Create and manage FTP accounts", URL: "/CMD_FTP_ACCOUNTS", Category: "files", Enabled: true},
		{Name: "FTP Connections", Icon: "connect", Description: "View active FTP connections", URL: "/CMD_FTP_CONNECTIONS", Category: "files", Enabled: true},
		{Name: "Backup", Icon: "backup", Description: "Backup and restore your website", URL: "/CMD_BACKUP", Category: "files", Enabled: true},
		{Name: "Backup Wizard", Icon: "wizard", Description: "Easy backup creation", URL: "/CMD_BACKUP_WIZARD", Category: "files", Enabled: true},
		{Name: "Disk Usage", Icon: "disk", Description: "View disk space usage", URL: "/CMD_DISK_USAGE", Category: "files", Enabled: true},
		{Name: "Web Disk", Icon: "webdisk", Description: "Access files via Web Disk", URL: "/CMD_WEB_DISK", Category: "files", Enabled: true},

		// Databases
		{Name: "MySQL Databases", Icon: "mysql", Description: "Create and manage MySQL databases", URL: "/CMD_MYSQL", Category: "databases", Enabled: true},
		{Name: "PostgreSQL Databases", Icon: "postgresql", Description: "Create and manage PostgreSQL databases", URL: "/CMD_POSTGRESQL", Category: "databases", Enabled: true},
		{Name: "phpMyAdmin", Icon: "phpmyadmin", Description: "Web-based MySQL administration", URL: "/CMD_PHPMYADMIN", Category: "databases", Enabled: true},
		{Name: "phpPgAdmin", Icon: "phppgadmin", Description: "Web-based PostgreSQL administration", URL: "/CMD_PHPPGADMIN", Category: "databases", Enabled: true},
		{Name: "Remote MySQL", Icon: "remote", Description: "Allow remote MySQL connections", URL: "/CMD_REMOTE_MYSQL", Category: "databases", Enabled: true},

		// Domains
		{Name: "Subdomains", Icon: "subdomain", Description: "Create and manage subdomains", URL: "/CMD_SUBDOMAIN", Category: "domains", Enabled: true},
		{Name: "Addon Domains", Icon: "addon", Description: "Add additional domains", URL: "/CMD_ADDON_DOMAINS", Category: "domains", Enabled: true},
		{Name: "Parked Domains", Icon: "parked", Description: "Park domains on your account", URL: "/CMD_PARKED_DOMAINS", Category: "domains", Enabled: true},
		{Name: "Redirects", Icon: "redirect", Description: "Create domain redirects", URL: "/CMD_REDIRECTS", Category: "domains", Enabled: true},
		{Name: "DNS Zone Editor", Icon: "dns", Description: "Edit DNS zone records", URL: "/CMD_DNS_ZONE", Category: "domains", Enabled: true},
		{Name: "Dynamic DNS", Icon: "ddns", Description: "Configure dynamic DNS", URL: "/CMD_DYNAMIC_DNS", Category: "domains", Enabled: true},

		// Email
		{Name: "Email Accounts", Icon: "email", Description: "Create and manage email accounts", URL: "/CMD_EMAIL_ACCOUNTS", Category: "email", Enabled: true},
		{Name: "Forwarders", Icon: "forward", Description: "Set up email forwarding", URL: "/CMD_EMAIL_FORWARDERS", Category: "email", Enabled: true},
		{Name: "AutoResponders", Icon: "autorespond", Description: "Create automatic email responses", URL: "/CMD_EMAIL_AUTORESPONDERS", Category: "email", Enabled: true},
		{Name: "Default Address", Icon: "default", Description: "Set default email address", URL: "/CMD_EMAIL_DEFAULT", Category: "email", Enabled: true},
		{Name: "Mailing Lists", Icon: "mailinglist", Description: "Create and manage mailing lists", URL: "/CMD_MAILING_LISTS", Category: "email", Enabled: true},
		{Name: "Track Delivery", Icon: "track", Description: "Track email delivery", URL: "/CMD_EMAIL_TRACK", Category: "email", Enabled: true},
		{Name: "Global Email Filters", Icon: "filter", Description: "Set up email filters", URL: "/CMD_EMAIL_FILTERS", Category: "email", Enabled: true},
		{Name: "Authentication", Icon: "auth", Description: "Email authentication settings", URL: "/CMD_EMAIL_AUTH", Category: "email", Enabled: true},
		{Name: "Address Importer", Icon: "import", Description: "Import email addresses", URL: "/CMD_EMAIL_IMPORT", Category: "email", Enabled: true},
		{Name: "Calendars and Contacts", Icon: "calendar", Description: "Manage calendars and contacts", URL: "/CMD_EMAIL_CALENDAR", Category: "email", Enabled: true},
		{Name: "Encryption", Icon: "encrypt", Description: "Email encryption settings", URL: "/CMD_EMAIL_ENCRYPTION", Category: "email", Enabled: true},

		// Metrics
		{Name: "Visitors", Icon: "visitors", Description: "View website visitor statistics", URL: "/CMD_VISITORS", Category: "metrics", Enabled: true},
		{Name: "Bandwidth", Icon: "bandwidth", Description: "View bandwidth usage", URL: "/CMD_BANDWIDTH", Category: "metrics", Enabled: true},
		{Name: "Raw Access Logs", Icon: "logs", Description: "Download raw access logs", URL: "/CMD_ACCESS_LOGS", Category: "metrics", Enabled: true},
		{Name: "Errors", Icon: "errors", Description: "View error logs", URL: "/CMD_ERROR_LOGS", Category: "metrics", Enabled: true},
		{Name: "AWStats", Icon: "awstats", Description: "Advanced web statistics", URL: "/CMD_AWSTATS", Category: "metrics", Enabled: true},
		{Name: "Webalizer", Icon: "webalizer", Description: "Web server log analysis", URL: "/CMD_WEBALIZER", Category: "metrics", Enabled: true},
		{Name: "Webalizer FTP", Icon: "webalizer-ftp", Description: "FTP log analysis", URL: "/CMD_WEBALIZER_FTP", Category: "metrics", Enabled: true},
		{Name: "CPU and Concurrent Connection Usage", Icon: "cpu", Description: "View CPU and connection usage", URL: "/CMD_CPU_USAGE", Category: "metrics", Enabled: true},

		// Security
		{Name: "SSL/TLS", Icon: "ssl", Description: "Manage SSL certificates", URL: "/CMD_SSL_TLS", Category: "security", Enabled: true},
		{Name: "Let's Encrypt SSL", Icon: "letsencrypt", Description: "Free SSL certificates", URL: "/CMD_LETSENCRYPT", Category: "security", Enabled: true},
		{Name: "IP Blocker", Icon: "ipblock", Description: "Block IP addresses", URL: "/CMD_IP_BLOCKER", Category: "security", Enabled: true},
		{Name: "HotLink Protection", Icon: "hotlink", Description: "Prevent hotlinking", URL: "/CMD_HOTLINK_PROTECTION", Category: "security", Enabled: true},
		{Name: "Leech Protection", Icon: "leech", Description: "Prevent unauthorized access", URL: "/CMD_LEECH_PROTECTION", Category: "security", Enabled: true},
		{Name: "Password Protect Directories", Icon: "password", Description: "Password protect directories", URL: "/CMD_PASSWORD_PROTECT", Category: "security", Enabled: true},
		{Name: "Two-Factor Authentication", Icon: "2fa", Description: "Enable two-factor authentication", URL: "/CMD_TWO_FACTOR", Category: "security", Enabled: true},
		{Name: "SSH Access", Icon: "ssh", Description: "Manage SSH access", URL: "/CMD_SSH_ACCESS", Category: "security", Enabled: true},

		// Software
		{Name: "Softaculous Apps Installer", Icon: "softaculous", Description: "Install web applications", URL: "/CMD_SOFTACULOUS", Category: "software", Enabled: true},
		{Name: "PHP Selector", Icon: "php", Description: "Select PHP version", URL: "/CMD_PHP_SELECTOR", Category: "software", Enabled: true},
		{Name: "Node.js Selector", Icon: "nodejs", Description: "Select Node.js version", URL: "/CMD_NODEJS_SELECTOR", Category: "software", Enabled: true},
		{Name: "Python Selector", Icon: "python", Description: "Select Python version", URL: "/CMD_PYTHON_SELECTOR", Category: "software", Enabled: true},
		{Name: "Ruby Selector", Icon: "ruby", Description: "Select Ruby version", URL: "/CMD_RUBY_SELECTOR", Category: "software", Enabled: true},
		{Name: "Cron Jobs", Icon: "cron", Description: "Schedule automated tasks", URL: "/CMD_CRON_JOBS", Category: "software", Enabled: true},
		{Name: "Site Publisher", Icon: "publisher", Description: "Publish your website", URL: "/CMD_SITE_PUBLISHER", Category: "software", Enabled: true},
		{Name: "Optimize Website", Icon: "optimize", Description: "Optimize website performance", URL: "/CMD_OPTIMIZE_WEBSITE", Category: "software", Enabled: true},

		// Advanced
		{Name: "File and Directory Restoration", Icon: "restore", Description: "Restore files and directories", URL: "/CMD_FILE_RESTORE", Category: "advanced", Enabled: true},
		{Name: "Terminal", Icon: "terminal", Description: "Access command line terminal", URL: "/CMD_TERMINAL", Category: "advanced", Enabled: true},
		{Name: "Web Protection", Icon: "webprotect", Description: "Web application firewall", URL: "/CMD_WEB_PROTECTION", Category: "advanced", Enabled: true},
		{Name: "API Shell", Icon: "api", Description: "Access API interface", URL: "/CMD_API_SHELL", Category: "advanced", Enabled: true},
		{Name: "MIME Types", Icon: "mime", Description: "Manage MIME types", URL: "/CMD_MIME_TYPES", Category: "advanced", Enabled: true},
		{Name: "Apache Handlers", Icon: "apache", Description: "Configure Apache handlers", URL: "/CMD_APACHE_HANDLERS", Category: "advanced", Enabled: true},
		{Name: "Directory Privacy", Icon: "privacy", Description: "Configure directory privacy", URL: "/CMD_DIRECTORY_PRIVACY", Category: "advanced", Enabled: true},
		{Name: "Indexes", Icon: "indexes", Description: "Manage directory indexes", URL: "/CMD_INDEXES", Category: "advanced", Enabled: true},
		{Name: "Error Pages", Icon: "errorpages", Description: "Custom error pages", URL: "/CMD_ERROR_PAGES", Category: "advanced", Enabled: true},
		{Name: "MultiPHP Manager", Icon: "multiphp", Description: "Manage multiple PHP versions", URL: "/CMD_MULTIPHP_MANAGER", Category: "advanced", Enabled: true},
		{Name: "MultiPHP INI Editor", Icon: "phpini", Description: "Edit PHP configuration", URL: "/CMD_MULTIPHP_INI", Category: "advanced", Enabled: true},
	}
}

// HandleCPanelFeatures returns the list of available cPanel features
func (cp *CPanel) HandleCPanelFeatures(c *gin.Context) {
	category := c.Query("category")
	search := c.Query("search")

	features := cp.Features
	if category != "" {
		var filtered []Feature
		for _, f := range features {
			if f.Category == category {
				filtered = append(filtered, f)
			}
		}
		features = filtered
	}

	if search != "" {
		var filtered []Feature
		for _, f := range features {
			if contains(f.Name, search) || contains(f.Description, search) {
				filtered = append(filtered, f)
			}
		}
		features = filtered
	}

	c.JSON(http.StatusOK, gin.H{
		"features":   features,
		"categories": getCategoryCounts(cp.Features),
		"total":      len(features),
	})
}

// HandleCPanelDashboard returns the main cPanel dashboard
func (cp *CPanel) HandleCPanelDashboard(c *gin.Context) {
	categories := map[string][]Feature{
		"files":     {},
		"databases": {},
		"domains":   {},
		"email":     {},
		"metrics":   {},
		"security":  {},
		"software":  {},
		"advanced":  {},
	}

	for _, f := range cp.Features {
		if f.Enabled {
			categories[f.Category] = append(categories[f.Category], f)
		}
	}

	c.HTML(http.StatusOK, "cpanel.html", gin.H{
		"title":      "AdminiPanel - cPanel Style Interface",
		"categories": categories,
		"stats": gin.H{
			"disk_usage":      "150 MB / 1 GB",
			"bandwidth_usage": "2.1 GB / 10 GB",
			"email_accounts":  "5 / Unlimited",
			"databases":       "3 / Unlimited",
			"subdomains":      "2 / Unlimited",
			"addon_domains":   "1 / 5",
		},
		"recent_activity": []gin.H{
			{"action": "Email account created", "target": "support@example.com", "time": "2 hours ago"},
			{"action": "Database created", "target": "user_shop", "time": "1 day ago"},
			{"action": "Subdomain created", "target": "blog.example.com", "time": "3 days ago"},
		},
	})
}

// Helper functions
func contains(s, substr string) bool {
	return len(s) >= len(substr) && s[:len(substr)] == substr
}

func getCategoryCounts(features []Feature) map[string]int {
	counts := make(map[string]int)
	for _, f := range features {
		if f.Enabled {
			counts[f.Category]++
		}
	}
	return counts
}