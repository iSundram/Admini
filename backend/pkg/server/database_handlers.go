package server

import (
	"net/http"
	"strconv"

	"admini/pkg/models"
	"github.com/gin-gonic/gin"
)

// Database handlers

func (s *Server) handleDatabasesAPI(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	databases, err := s.databaseManager.ListDatabases(username)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"databases": databases,
		"username": username,
	})
}

func (s *Server) handleCreateDatabase(c *gin.Context) {
	username := s.getCurrentUser(c)
	dbName := c.PostForm("name")
	dbUser := c.PostForm("user")
	dbPassword := c.PostForm("password")
	
	err := s.databaseManager.CreateDatabase(username, dbName, dbUser, dbPassword)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Database created successfully",
	})
}

func (s *Server) handleDatabaseUsers(c *gin.Context) {
	username := s.getCurrentUser(c)
	dbName := c.Query("database")
	
	// Get database privileges - this would be more complex in reality
	privileges, err := s.databaseManager.GetDatabasePrivileges(username, dbName)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"database": dbName,
		"users": []map[string]interface{}{
			{"username": username + "_" + dbName, "privileges": privileges},
		},
	})
}

func (s *Server) handleCreateDatabaseUser(c *gin.Context) {
	username := s.getCurrentUser(c)
	dbName := c.PostForm("database")
	dbUser := c.PostForm("user")
	dbPassword := c.PostForm("password")
	
	err := s.databaseManager.CreateDatabaseUser(username, dbName, dbUser, dbPassword)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Database user created successfully",
	})
}

func (s *Server) handleDatabasePrivileges(c *gin.Context) {
	username := s.getCurrentUser(c)
	dbUser := c.Query("user")
	
	privileges, err := s.databaseManager.GetDatabasePrivileges(username, dbUser)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"user": dbUser,
		"privileges": privileges,
	})
}

func (s *Server) handlePHPMyAdmin(c *gin.Context) {
	// Redirect to phpMyAdmin with auto-login
	c.JSON(http.StatusOK, gin.H{
		"title": "phpMyAdmin",
		"url": "/phpmyadmin/",
		"auto_login": true,
	})
}

func (s *Server) handlePMASignOn(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	// Generate phpMyAdmin auto-login token
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"token": "auto_login_token_" + username,
		"redirect_url": "/phpmyadmin/",
	})
}

// SSL handlers

func (s *Server) handleSSLAPI(c *gin.Context) {
	domain := c.Query("domain")
	
	c.JSON(http.StatusOK, gin.H{
		"domain": domain,
		"ssl_enabled": true,
		"certificate": map[string]interface{}{
			"issuer": "Let's Encrypt",
			"expires": "2024-12-31",
			"auto_renew": true,
		},
	})
}

func (s *Server) handleSSLUpload(c *gin.Context) {
	domain := c.PostForm("domain")
	_ = c.PostForm("certificate")
	_ = c.PostForm("private_key")
	_ = c.PostForm("ca")
	
	// In a real implementation, this would save the SSL certificate
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "SSL certificate uploaded successfully",
		"domain": domain,
	})
}

func (s *Server) handleLetsEncrypt(c *gin.Context) {
	domain := c.Query("domain")
	
	c.JSON(http.StatusOK, gin.H{
		"title": "Let's Encrypt SSL",
		"domain": domain,
		"available": true,
	})
}

func (s *Server) handleLetsEncryptCreate(c *gin.Context) {
	domain := c.PostForm("domain")
	email := c.PostForm("email")
	
	// In a real implementation, this would request a Let's Encrypt certificate
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Let's Encrypt certificate requested successfully",
		"domain": domain,
		"email": email,
	})
}

// Additional domain handlers

func (s *Server) handleAdditionalDomains(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"title": "Additional Domains",
		"username": username,
		"domains": []map[string]interface{}{
			{"domain": "example.com", "type": "addon", "status": "active"},
			{"domain": "test.org", "type": "parked", "status": "active"},
		},
	})
}

func (s *Server) handleAdditionalDomainsView(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"addon_domains": []string{"addon1.com", "addon2.net"},
		"parked_domains": []string{"parked1.org", "parked2.info"},
		"subdomains": []string{"sub1.example.com", "sub2.example.com"},
	})
}

func (s *Server) handleCreateAdditionalDomain(c *gin.Context) {
	domain := c.PostForm("domain")
	domainType := c.PostForm("type") // addon, parked, subdomain
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Additional domain created successfully",
		"domain": domain,
		"type": domainType,
	})
}

func (s *Server) handleCreateDomainPointer(c *gin.Context) {
	source := c.PostForm("source")
	destination := c.PostForm("destination")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Domain pointer created successfully",
		"source": source,
		"destination": destination,
	})
}

// DNS handlers

func (s *Server) handleDNSControl(c *gin.Context) {
	domain := c.Query("domain")
	
	// Mock DNS records
	records := []models.DNSRecord{
		{ID: 1, Domain: domain, Name: "@", Type: "A", Value: "192.168.1.100", TTL: 3600},
		{ID: 2, Domain: domain, Name: "www", Type: "CNAME", Value: domain, TTL: 3600},
		{ID: 3, Domain: domain, Name: "@", Type: "MX", Value: "mail." + domain, TTL: 3600, Priority: 10},
	}
	
	c.JSON(http.StatusOK, gin.H{
		"domain": domain,
		"records": records,
	})
}

func (s *Server) handleCreateDNSRecord(c *gin.Context) {
	domain := c.PostForm("domain")
	name := c.PostForm("name")
	recordType := c.PostForm("type")
	value := c.PostForm("value")
	ttlStr := c.PostForm("ttl")
	
	ttl, _ := strconv.Atoi(ttlStr)
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "DNS record created successfully",
		"record": map[string]interface{}{
			"domain": domain,
			"name": name,
			"type": recordType,
			"value": value,
			"ttl": ttl,
		},
	})
}

func (s *Server) handleDNSMX(c *gin.Context) {
	domain := c.Query("domain")
	
	c.JSON(http.StatusOK, gin.H{
		"domain": domain,
		"mx_records": []map[string]interface{}{
			{"priority": 10, "value": "mail." + domain},
			{"priority": 20, "value": "mail2." + domain},
		},
	})
}

// Statistics handlers

func (s *Server) handleBandwidthBreakdown(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"breakdown": map[string]interface{}{
			"http": "500MB",
			"ftp": "100MB",
			"mail": "50MB",
			"total": "650MB",
		},
	})
}

func (s *Server) handleDiskUsageBreakdown(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"breakdown": map[string]interface{}{
			"domains": "1.2GB",
			"mail": "300MB",
			"databases": "150MB",
			"total": "1.65GB",
		},
	})
}

func (s *Server) handlePublicStats(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"server_stats": map[string]interface{}{
			"domains_hosted": 150,
			"users_active": 85,
			"uptime": "99.9%",
		},
	})
}

// FTP handlers

func (s *Server) handleFTPSettings(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"ftp_enabled": true,
		"passive_ports": "35000-35999",
		"max_connections": 10,
	})
}

func (s *Server) handleFTPUsers(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"ftp_users": []models.FTPAccount{
			{Username: username, Domain: "example.com", Path: "/domains/example.com/public_html", Quota: 1073741824},
		},
	})
}

// Webmail handlers

func (s *Server) handleWebmail(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Webmail",
		"options": []map[string]string{
			{"name": "Roundcube", "url": "/roundcube/"},
			{"name": "SquirrelMail", "url": "/squirrelmail/"},
		},
	})
}

func (s *Server) handleRoundcube(c *gin.Context) {
	c.Redirect(http.StatusFound, "/roundcube/")
}

func (s *Server) handleSquirrelMail(c *gin.Context) {
	c.Redirect(http.StatusFound, "/squirrelmail/")
}

// Reseller handlers

func (s *Server) handleResellerIndex(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "AdminiReseller - Reseller Panel",
		"level": "reseller",
	})
}

func (s *Server) handleResellerStats(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Reseller Statistics",
		"users": 25,
		"domains": 45,
		"bandwidth_used": "2.5GB",
		"disk_used": "8.2GB",
	})
}

func (s *Server) handleResellerHistory(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Reseller History",
		"history": []map[string]interface{}{
			{"date": "2023-12-01", "action": "User created", "user": "client1"},
			{"date": "2023-11-30", "action": "Package modified", "package": "basic"},
		},
	})
}

func (s *Server) handlePluginsReseller(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Reseller Plugins",
		"plugins": []map[string]interface{}{
			{"name": "backup_plugin", "version": "1.0", "status": "active"},
		},
	})
}

func (s *Server) handleResellerStatsAPI(c *gin.Context) {
	s.handleResellerStats(c)
}

func (s *Server) handleResellerBackup(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Reseller Backup",
		"backups": []models.Backup{
			{ID: "reseller_backup1", Type: "reseller", Size: 5368709120, Filename: "reseller_backup.tar.gz"},
		},
	})
}

func (s *Server) handlePackages(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"packages": []models.Package{
			{Name: "basic", Bandwidth: 10737418240, Quota: 5368709120, Domains: 5, EmailAccounts: 10},
			{Name: "premium", Bandwidth: 53687091200, Quota: 21474836480, Domains: 25, EmailAccounts: 100},
		},
	})
}

func (s *Server) handleCreatePackage(c *gin.Context) {
	name := c.PostForm("name")
	bandwidthStr := c.PostForm("bandwidth")
	quotaStr := c.PostForm("quota")
	
	bandwidth, _ := strconv.ParseInt(bandwidthStr, 10, 64)
	quota, _ := strconv.ParseInt(quotaStr, 10, 64)
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Package created successfully",
		"package": map[string]interface{}{
			"name": name,
			"bandwidth": bandwidth,
			"quota": quota,
		},
	})
}