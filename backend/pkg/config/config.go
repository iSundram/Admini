package config

import (
	"fmt"
	"os"
	"path/filepath"
	"sync"

	"github.com/spf13/viper"
)

type Config struct {
	mu     sync.RWMutex
	values map[string]string
}

var (
	instance *Config
	once     sync.Once
)

// GetConfig returns the singleton config instance
func GetConfig() *Config {
	once.Do(func() {
		instance = &Config{
			values: make(map[string]string),
		}
		instance.loadDefaults()
		instance.loadFromFile()
	})
	return instance
}

func (c *Config) loadDefaults() {
	c.values["admin_username"] = "admin"
	c.values["port"] = "2222"
	c.values["ssl_port"] = "2222"
	c.values["servername"] = "localhost"
	c.values["ethernet_dev"] = "eth0"
	c.values["admin_email"] = "admin@localhost"
	c.values["mysql_host"] = "localhost"
	c.values["mysql_port"] = "3306"
	c.values["ftp_port"] = "21"
	c.values["ssh_port"] = "22"
	c.values["apache_port"] = "80"
	c.values["apache_ssl_port"] = "443"
	c.values["nginx_port"] = "80"
	c.values["nginx_ssl_port"] = "443"
	c.values["dovecot_conf"] = "/etc/dovecot/dovecot.conf"
	c.values["exim_conf"] = "/etc/exim/exim.conf"
	c.values["named_conf"] = "/etc/named.conf"
	c.values["ssl"] = "1"
	c.values["ssl_redirect_host"] = "OFF"
	c.values["apache_ver"] = "2.4"
	c.values["php1_release"] = "8.1"
	c.values["mysql_backup"] = "1"
	c.values["email_backup"] = "1"
	c.values["gzip"] = "1"
	c.values["backup_gzip"] = "1"
	c.values["backup_options"] = "email"
	c.values["license_key"] = ""
	c.values["uid"] = "1000"
	c.values["gid"] = "1000"
}

func (c *Config) loadFromFile() {
	// Try to load from /usr/local/admini/conf/directadmin.conf
	configFile := "/usr/local/admini/conf/directadmin.conf"
	if _, err := os.Stat(configFile); err == nil {
		viper.SetConfigFile(configFile)
		viper.SetConfigType("env")
		if err := viper.ReadInConfig(); err == nil {
			for key, value := range viper.AllSettings() {
				c.values[key] = fmt.Sprintf("%v", value)
			}
		}
	}
}

func (c *Config) Get(key string) string {
	c.mu.RLock()
	defer c.mu.RUnlock()
	return c.values[key]
}

func (c *Config) Set(key, value string) error {
	c.mu.Lock()
	defer c.mu.Unlock()
	c.values[key] = value
	
	// Save to file
	return c.saveToFile()
}

func (c *Config) saveToFile() error {
	configDir := "/usr/local/admini/conf"
	configFile := filepath.Join(configDir, "directadmin.conf")
	
	// Create directory if it doesn't exist
	if err := os.MkdirAll(configDir, 0755); err != nil {
		return err
	}
	
	// Write config file
	file, err := os.Create(configFile)
	if err != nil {
		return err
	}
	defer file.Close()
	
	for key, value := range c.values {
		fmt.Fprintf(file, "%s=%s\n", key, value)
	}
	
	return nil
}

func (c *Config) PrintAll() {
	c.mu.RLock()
	defer c.mu.RUnlock()
	
	fmt.Println("Admini Configuration:")
	fmt.Println("==========================")
	for key, value := range c.values {
		fmt.Printf("%s=%s\n", key, value)
	}
}