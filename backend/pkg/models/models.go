package models

import "time"

// User represents a DirectAdmin user account
type User struct {
	Username     string            `json:"username"`
	Password     string            `json:"password,omitempty"`
	Email        string            `json:"email"`
	Domain       string            `json:"domain"`
	Package      string            `json:"package"`
	Bandwidth    int64             `json:"bandwidth"`
	Quota        int64             `json:"quota"`
	Inodes       int64             `json:"inodes"`
	Domains      int               `json:"domains"`
	SubDomains   int               `json:"subdomains"`
	EmailAccounts int              `json:"email_accounts"`
	Databases    int               `json:"databases"`
	FTPAccounts  int               `json:"ftp_accounts"`
	Suspended    bool              `json:"suspended"`
	Level        string            `json:"level"` // user, reseller, admin
	IP           string            `json:"ip"`
	Creator      string            `json:"creator"`
	Created      time.Time         `json:"created"`
	LastLogin    time.Time         `json:"last_login"`
	Extra        map[string]string `json:"extra,omitempty"`
}

// Domain represents a domain in DirectAdmin
type Domain struct {
	Domain       string            `json:"domain"`
	Username     string            `json:"username"`
	IP           string            `json:"ip"`
	DocumentRoot string            `json:"document_root"`
	Bandwidth    int64             `json:"bandwidth"`
	SSL          bool              `json:"ssl"`
	PHP          bool              `json:"php"`
	CGI          bool              `json:"cgi"`
	Suspended    bool              `json:"suspended"`
	Created      time.Time         `json:"created"`
	Extra        map[string]string `json:"extra,omitempty"`
}

// EmailAccount represents an email account
type EmailAccount struct {
	Email     string `json:"email"`
	Username  string `json:"username"`
	Domain    string `json:"domain"`
	Password  string `json:"password,omitempty"`
	Quota     int64  `json:"quota"`
	Usage     int64  `json:"usage"`
	Forward   string `json:"forward,omitempty"`
	Vacation  bool   `json:"vacation"`
	Suspended bool   `json:"suspended"`
}

// Database represents a MySQL/PostgreSQL database
type Database struct {
	Name      string   `json:"name"`
	Username  string   `json:"username"`
	Type      string   `json:"type"` // mysql, postgresql
	Size      int64    `json:"size"`
	Users     []string `json:"users"`
	Charset   string   `json:"charset"`
	Collation string   `json:"collation"`
}

// FTPAccount represents an FTP account
type FTPAccount struct {
	Username  string `json:"username"`
	Domain    string `json:"domain"`
	Password  string `json:"password,omitempty"`
	Path      string `json:"path"`
	Quota     int64  `json:"quota"`
	Usage     int64  `json:"usage"`
	Suspended bool   `json:"suspended"`
}

// SSLCertificate represents an SSL certificate
type SSLCertificate struct {
	Domain      string    `json:"domain"`
	Certificate string    `json:"certificate"`
	PrivateKey  string    `json:"private_key"`
	CA          string    `json:"ca,omitempty"`
	Issuer      string    `json:"issuer"`
	ExpiryDate  time.Time `json:"expiry_date"`
	AutoRenew   bool      `json:"auto_renew"`
	LetsEncrypt bool      `json:"lets_encrypt"`
}

// CronJob represents a cron job
type CronJob struct {
	ID      int    `json:"id"`
	Command string `json:"command"`
	Minute  string `json:"minute"`
	Hour    string `json:"hour"`
	Day     string `json:"day"`
	Month   string `json:"month"`
	Weekday string `json:"weekday"`
	Enabled bool   `json:"enabled"`
}

// Backup represents a backup
type Backup struct {
	ID         string    `json:"id"`
	Username   string    `json:"username"`
	Type       string    `json:"type"` // user, domain, full
	Size       int64     `json:"size"`
	Filename   string    `json:"filename"`
	Created    time.Time `json:"created"`
	Compressed bool      `json:"compressed"`
}

// SystemStats represents system statistics
type SystemStats struct {
	LoadAverage   []float64         `json:"load_average"`
	MemoryUsage   MemoryStats       `json:"memory_usage"`
	DiskUsage     []DiskStats       `json:"disk_usage"`
	CPUUsage      float64           `json:"cpu_usage"`
	NetworkStats  NetworkStats      `json:"network_stats"`
	Uptime        time.Duration     `json:"uptime"`
	UsersCount    int               `json:"users_count"`
	DomainsCount  int               `json:"domains_count"`
	EmailsCount   int               `json:"emails_count"`
	DatabasesCount int              `json:"databases_count"`
	Services      map[string]string `json:"services"`
}

// MemoryStats represents memory usage statistics
type MemoryStats struct {
	Total     uint64  `json:"total"`
	Used      uint64  `json:"used"`
	Free      uint64  `json:"free"`
	Available uint64  `json:"available"`
	Percent   float64 `json:"percent"`
}

// DiskStats represents disk usage statistics
type DiskStats struct {
	Device     string  `json:"device"`
	Mountpoint string  `json:"mountpoint"`
	Total      uint64  `json:"total"`
	Used       uint64  `json:"used"`
	Free       uint64  `json:"free"`
	Percent    float64 `json:"percent"`
}

// NetworkStats represents network statistics
type NetworkStats struct {
	BytesIn  uint64 `json:"bytes_in"`
	BytesOut uint64 `json:"bytes_out"`
	PacketsIn uint64 `json:"packets_in"`
	PacketsOut uint64 `json:"packets_out"`
}

// Task represents a background task
type Task struct {
	ID          string                 `json:"id"`
	Type        string                 `json:"type"`
	Username    string                 `json:"username"`
	Status      string                 `json:"status"` // pending, running, completed, failed
	Progress    int                    `json:"progress"`
	Created     time.Time              `json:"created"`
	Started     *time.Time             `json:"started,omitempty"`
	Completed   *time.Time             `json:"completed,omitempty"`
	Error       string                 `json:"error,omitempty"`
	Parameters  map[string]interface{} `json:"parameters"`
	Result      map[string]interface{} `json:"result,omitempty"`
}

// Package represents a user package
type Package struct {
	Name          string `json:"name"`
	Bandwidth     int64  `json:"bandwidth"`
	Quota         int64  `json:"quota"`
	Inodes        int64  `json:"inodes"`
	Domains       int    `json:"domains"`
	SubDomains    int    `json:"subdomains"`
	EmailAccounts int    `json:"email_accounts"`
	Databases     int    `json:"databases"`
	FTPAccounts   int    `json:"ftp_accounts"`
	PHP           bool   `json:"php"`
	CGI           bool   `json:"cgi"`
	SSL           bool   `json:"ssl"`
	SSH           bool   `json:"ssh"`
	Cron          bool   `json:"cron"`
}

// License represents the DirectAdmin license
type License struct {
	LicenseID     string    `json:"license_id"`
	Type          string    `json:"type"`
	Users         int       `json:"users"`
	Domains       int       `json:"domains"`
	IPs           []string  `json:"ips"`
	ExpiryDate    time.Time `json:"expiry_date"`
	Valid         bool      `json:"valid"`
	Features      []string  `json:"features"`
}

// DNSRecord represents a DNS record
type DNSRecord struct {
	ID       int    `json:"id"`
	Domain   string `json:"domain"`
	Name     string `json:"name"`
	Type     string `json:"type"`
	Value    string `json:"value"`
	TTL      int    `json:"ttl"`
	Priority int    `json:"priority,omitempty"`
	Weight   int    `json:"weight,omitempty"`
	Port     int    `json:"port,omitempty"`
}

// FileManagerItem represents a file or directory
type FileManagerItem struct {
	Name        string      `json:"name"`
	Path        string      `json:"path"`
	Type        string      `json:"type"` // file, directory
	Size        int64       `json:"size"`
	Permissions string      `json:"permissions"`
	Owner       string      `json:"owner"`
	Group       string      `json:"group"`
	Modified    time.Time   `json:"modified"`
	IsDirectory bool        `json:"is_directory"`
	IsReadable  bool        `json:"is_readable"`
	IsWritable  bool        `json:"is_writable"`
	IsExecutable bool       `json:"is_executable"`
}

// ConfigValue represents a configuration value
type ConfigValue struct {
	Key         string `json:"key"`
	Value       string `json:"value"`
	Type        string `json:"type"` // string, int, bool, float
	Description string `json:"description"`
	Section     string `json:"section"`
	Editable    bool   `json:"editable"`
}

// APIResponse represents a standard API response
type APIResponse struct {
	Success bool        `json:"success"`
	Message string      `json:"message,omitempty"`
	Data    interface{} `json:"data,omitempty"`
	Error   string      `json:"error,omitempty"`
	Details interface{} `json:"details,omitempty"`
}

// LoginKey represents an API login key
type LoginKey struct {
	Key        string    `json:"key"`
	Username   string    `json:"username"`
	Name       string    `json:"name"`
	Created    time.Time `json:"created"`
	LastUsed   time.Time `json:"last_used"`
	Expires    time.Time `json:"expires"`
	Permissions []string `json:"permissions"`
	Active     bool      `json:"active"`
}

// TwoStepAuth represents two-step authentication settings
type TwoStepAuth struct {
	Username   string `json:"username"`
	Enabled    bool   `json:"enabled"`
	Secret     string `json:"secret,omitempty"`
	QRCode     string `json:"qr_code,omitempty"`
	BackupCodes []string `json:"backup_codes,omitempty"`
}