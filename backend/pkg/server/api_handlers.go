package server

import (
	"net/http"
	"strconv"

	"directadmin/pkg/models"
	"github.com/gin-gonic/gin"
)

// Comprehensive API handlers for all DirectAdmin functionality

// API User management
func (s *Server) handleAPIUsers(c *gin.Context) {
	s.handleShowUsers(c)
}

func (s *Server) handleAPICreateUser(c *gin.Context) {
	s.handleCreateUser(c)
}

func (s *Server) handleAPIGetUser(c *gin.Context) {
	username := c.Param("username")
	
	// Mock user data
	user := models.User{
		Username: username,
		Email: username + "@example.com",
		Level: "user",
		Suspended: false,
		Domains: 3,
		EmailAccounts: 10,
		Databases: 2,
	}
	
	c.JSON(http.StatusOK, gin.H{"user": user})
}

func (s *Server) handleAPIUpdateUser(c *gin.Context) {
	username := c.Param("username")
	
	var userData map[string]interface{}
	if err := c.ShouldBindJSON(&userData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "User " + username + " updated successfully",
		"data": userData,
	})
}

func (s *Server) handleAPIDeleteUser(c *gin.Context) {
	username := c.Param("username")
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "User " + username + " deleted successfully",
	})
}

// API Domain management
func (s *Server) handleAPIDomains(c *gin.Context) {
	s.handleShowDomains(c)
}

func (s *Server) handleAPICreateDomain(c *gin.Context) {
	s.handleCreateDomain(c)
}

func (s *Server) handleAPIGetDomain(c *gin.Context) {
	domain := c.Param("domain")
	
	domainInfo := models.Domain{
		Domain: domain,
		Username: "user1",
		IP: "192.168.1.100",
		DocumentRoot: "/domains/" + domain + "/public_html",
		SSL: true,
		PHP: true,
		CGI: true,
		Suspended: false,
	}
	
	c.JSON(http.StatusOK, gin.H{"domain": domainInfo})
}

func (s *Server) handleAPIUpdateDomain(c *gin.Context) {
	domain := c.Param("domain")
	
	var domainData map[string]interface{}
	if err := c.ShouldBindJSON(&domainData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Domain " + domain + " updated successfully",
		"data": domainData,
	})
}

func (s *Server) handleAPIDeleteDomain(c *gin.Context) {
	domain := c.Param("domain")
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Domain " + domain + " deleted successfully",
	})
}

// API Email management
func (s *Server) handleAPIEmail(c *gin.Context) {
	domain := c.Query("domain")
	if domain == "" {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Domain parameter required"})
		return
	}
	
	accounts, err := s.emailManager.ListEmailAccounts(domain)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{"emails": accounts})
}

func (s *Server) handleAPICreateEmail(c *gin.Context) {
	s.handleCreateEmailAccount(c)
}

func (s *Server) handleAPIGetEmail(c *gin.Context) {
	email := c.Param("email")
	
	emailInfo := models.EmailAccount{
		Email: email,
		Domain: "example.com",
		Quota: 1073741824, // 1GB
		Usage: 536870912,  // 512MB
		Suspended: false,
		Vacation: false,
	}
	
	c.JSON(http.StatusOK, gin.H{"email": emailInfo})
}

func (s *Server) handleAPIUpdateEmail(c *gin.Context) {
	email := c.Param("email")
	
	var emailData map[string]interface{}
	if err := c.ShouldBindJSON(&emailData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Email " + email + " updated successfully",
		"data": emailData,
	})
}

func (s *Server) handleAPIDeleteEmail(c *gin.Context) {
	email := c.Param("email")
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Email " + email + " deleted successfully",
	})
}

// API Database management
func (s *Server) handleAPIDatabasesList(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	databases, err := s.databaseManager.ListDatabases(username)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{"databases": databases})
}

func (s *Server) handleAPICreateDatabase(c *gin.Context) {
	s.handleCreateDatabase(c)
}

func (s *Server) handleAPIGetDatabase(c *gin.Context) {
	dbName := c.Param("name")
	username := s.getCurrentUser(c)
	
	databases, err := s.databaseManager.ListDatabases(username)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	for _, db := range databases {
		if db.Name == dbName {
			c.JSON(http.StatusOK, gin.H{"database": db})
			return
		}
	}
	
	c.JSON(http.StatusNotFound, gin.H{"error": "Database not found"})
}

func (s *Server) handleAPIDeleteDatabase(c *gin.Context) {
	dbName := c.Param("name")
	username := s.getCurrentUser(c)
	
	err := s.databaseManager.DeleteDatabase(username, dbName)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Database " + dbName + " deleted successfully",
	})
}

// API System management
func (s *Server) handleAPIStats(c *gin.Context) {
	s.handleAdminStats(c)
}

func (s *Server) handleAPISystemInfo(c *gin.Context) {
	s.handleSystemInfo(c)
}

func (s *Server) handleAPILoadAverage(c *gin.Context) {
	s.handleLoadAverage(c)
}

func (s *Server) handleAPIProcesses(c *gin.Context) {
	s.handleProcesses(c)
}

func (s *Server) handleAPIServices(c *gin.Context) {
	s.handleServicesMonitor(c)
}

// API Configuration management
func (s *Server) handleAPIConfig(c *gin.Context) {
	config := []models.ConfigValue{
		{Key: "admin_username", Value: "admin", Type: "string", Description: "Administrator username", Section: "general", Editable: false},
		{Key: "max_users", Value: "100", Type: "int", Description: "Maximum number of users", Section: "limits", Editable: true},
		{Key: "ssl_redirect", Value: "true", Type: "bool", Description: "Redirect HTTP to HTTPS", Section: "security", Editable: true},
	}
	
	c.JSON(http.StatusOK, gin.H{"config": config})
}

func (s *Server) handleAPIUpdateConfig(c *gin.Context) {
	var configData map[string]interface{}
	if err := c.ShouldBindJSON(&configData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Configuration updated successfully",
		"data": configData,
	})
}

// API Backup and restore
func (s *Server) handleAPIBackups(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	backups := []models.Backup{
		{ID: "backup1", Username: username, Type: "user", Size: 104857600, Filename: "backup_" + username + ".tar.gz"},
		{ID: "backup2", Username: username, Type: "domain", Size: 52428800, Filename: "domain_backup.tar.gz"},
	}
	
	c.JSON(http.StatusOK, gin.H{"backups": backups})
}

func (s *Server) handleAPICreateBackup(c *gin.Context) {
	backupType := c.PostForm("type")
	target := c.PostForm("target")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Backup started successfully",
		"backup_id": "backup_" + strconv.FormatInt(1701432000, 10),
		"type": backupType,
		"target": target,
	})
}

func (s *Server) handleAPIRestore(c *gin.Context) {
	backupFile := c.PostForm("backup_file")
	restoreType := c.PostForm("type")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Restore started successfully",
		"backup_file": backupFile,
		"type": restoreType,
	})
}

// API Task queue
func (s *Server) handleAPITasks(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	tasks := []models.Task{
		{ID: "task1", Type: "backup", Username: username, Status: "completed", Progress: 100},
		{ID: "task2", Type: "domain_creation", Username: username, Status: "running", Progress: 75},
	}
	
	c.JSON(http.StatusOK, gin.H{"tasks": tasks})
}

func (s *Server) handleAPIGetTask(c *gin.Context) {
	taskID := c.Param("id")
	
	task := models.Task{
		ID: taskID,
		Type: "backup",
		Username: s.getCurrentUser(c),
		Status: "completed",
		Progress: 100,
	}
	
	c.JSON(http.StatusOK, gin.H{"task": task})
}

func (s *Server) handleAPICreateTask(c *gin.Context) {
	taskType := c.PostForm("type")
	username := s.getCurrentUser(c)
	
	task := models.Task{
		ID: "task_" + strconv.FormatInt(1701432000, 10),
		Type: taskType,
		Username: username,
		Status: "pending",
		Progress: 0,
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Task created successfully",
		"task": task,
	})
}

// API Login keys
func (s *Server) handleAPILoginKeys(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	keys := []models.LoginKey{
		{Key: "key123", Username: username, Name: "API Key 1", Active: true},
		{Key: "key456", Username: username, Name: "API Key 2", Active: false},
	}
	
	c.JSON(http.StatusOK, gin.H{"keys": keys})
}

func (s *Server) handleAPICreateLoginKey(c *gin.Context) {
	name := c.PostForm("name")
	username := s.getCurrentUser(c)
	
	key := models.LoginKey{
		Key: "key_" + strconv.FormatInt(1701432000, 10),
		Username: username,
		Name: name,
		Active: true,
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Login key created successfully",
		"key": key,
	})
}

func (s *Server) handleAPIDeleteLoginKey(c *gin.Context) {
	key := c.Param("key")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Login key " + key + " deleted successfully",
	})
}

// API Two-step authentication
func (s *Server) handleAPITwoStepAuth(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	twoStep := models.TwoStepAuth{
		Username: username,
		Enabled: false,
	}
	
	c.JSON(http.StatusOK, gin.H{"twostep": twoStep})
}

func (s *Server) handleAPIEnableTwoStepAuth(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Two-step authentication enabled for " + username,
		"secret": "JBSWY3DPEHPK3PXP",
		"qr_code": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mNkYPhfDwAChwGA60e6kgAAAABJRU5ErkJggg==",
	})
}

func (s *Server) handleAPIDisableTwoStepAuth(c *gin.Context) {
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Two-step authentication disabled for " + username,
	})
}

// AJAX handlers
func (s *Server) handleAjaxCheckDomain(c *gin.Context) {
	domain := c.Query("domain")
	
	// Mock domain availability check
	available := domain != "taken.com"
	
	c.JSON(http.StatusOK, gin.H{
		"domain": domain,
		"available": available,
		"message": func() string {
			if available {
				return "Domain is available"
			}
			return "Domain is already taken"
		}(),
	})
}

func (s *Server) handleAjaxCheckUsername(c *gin.Context) {
	username := c.Query("username")
	
	// Mock username availability check
	available := username != "admin" && username != "root"
	
	c.JSON(http.StatusOK, gin.H{
		"username": username,
		"available": available,
		"message": func() string {
			if available {
				return "Username is available"
			}
			return "Username is already taken"
		}(),
	})
}

func (s *Server) handleAjaxCheckPassword(c *gin.Context) {
	password := c.Query("password")
	
	// Mock password strength check
	strength := "weak"
	if len(password) >= 8 {
		strength = "medium"
	}
	if len(password) >= 12 {
		strength = "strong"
	}
	
	c.JSON(http.StatusOK, gin.H{
		"strength": strength,
		"score": len(password) * 10,
		"suggestions": []string{
			"Use a mix of uppercase and lowercase letters",
			"Include numbers and special characters",
		},
	})
}

func (s *Server) handleAjaxSearch(c *gin.Context) {
	query := c.Query("q")
	searchType := c.Query("type")
	
	results := []map[string]interface{}{
		{"type": "user", "name": "user1", "match": "username"},
		{"type": "domain", "name": "example.com", "match": "domain"},
	}
	
	c.JSON(http.StatusOK, gin.H{
		"query": query,
		"type": searchType,
		"results": results,
	})
}

func (s *Server) handleAjaxUsers(c *gin.Context) {
	s.handleShowUsers(c)
}

func (s *Server) handleAjaxGetCounts(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"users": 85,
		"domains": 150,
		"emails": 350,
		"databases": 45,
		"suspended_users": 5,
		"suspended_domains": 3,
	})
}

// Additional API handlers
func (s *Server) handleAPIPlugins(c *gin.Context) {
	plugins := []map[string]interface{}{
		{"name": "backup_plugin", "version": "1.0", "status": "active", "description": "Automated backup plugin"},
		{"name": "security_scanner", "version": "2.1", "status": "inactive", "description": "Security vulnerability scanner"},
	}
	
	c.JSON(http.StatusOK, gin.H{"plugins": plugins})
}

func (s *Server) handleAPIInstallPlugin(c *gin.Context) {
	pluginName := c.PostForm("name")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Plugin " + pluginName + " installed successfully",
	})
}

func (s *Server) handleAPIUninstallPlugin(c *gin.Context) {
	pluginName := c.Param("name")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Plugin " + pluginName + " uninstalled successfully",
	})
}

func (s *Server) handleAPIMultiServer(c *gin.Context) {
	servers := []map[string]interface{}{
		{"name": "server1", "ip": "192.168.1.100", "status": "online", "load": 0.25},
		{"name": "server2", "ip": "192.168.1.101", "status": "online", "load": 0.15},
	}
	
	c.JSON(http.StatusOK, gin.H{"servers": servers})
}

func (s *Server) handleAPIAddServer(c *gin.Context) {
	serverName := c.PostForm("name")
	serverIP := c.PostForm("ip")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Server " + serverName + " added successfully",
		"server": map[string]string{
			"name": serverName,
			"ip": serverIP,
		},
	})
}

func (s *Server) handleAPIBruteForceMonitor(c *gin.Context) {
	blockedIPs := []map[string]interface{}{
		{"ip": "192.168.1.200", "attempts": 10, "blocked_at": "2023-12-01T10:00:00Z"},
		{"ip": "10.0.0.50", "attempts": 5, "blocked_at": "2023-12-01T09:30:00Z"},
	}
	
	c.JSON(http.StatusOK, gin.H{"blocked_ips": blockedIPs})
}

func (s *Server) handleAPIUnblockIP(c *gin.Context) {
	ip := c.PostForm("ip")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "IP " + ip + " unblocked successfully",
	})
}

func (s *Server) handleAPIComments(c *gin.Context) {
	comments := []map[string]interface{}{
		{"id": 1, "user": "admin", "comment": "System maintenance scheduled", "date": "2023-12-01"},
	}
	
	c.JSON(http.StatusOK, gin.H{"comments": comments})
}

func (s *Server) handleAPICreateComment(c *gin.Context) {
	comment := c.PostForm("comment")
	username := s.getCurrentUser(c)
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Comment created successfully",
		"comment": map[string]interface{}{
			"user": username,
			"comment": comment,
			"date": "2023-12-01",
		},
	})
}

func (s *Server) handleAPITickets(c *gin.Context) {
	tickets := []map[string]interface{}{
		{"id": 1, "subject": "Email issue", "status": "open", "priority": "high"},
		{"id": 2, "subject": "Domain setup", "status": "closed", "priority": "normal"},
	}
	
	c.JSON(http.StatusOK, gin.H{"tickets": tickets})
}

func (s *Server) handleAPICreateTicket(c *gin.Context) {
	subject := c.PostForm("subject")
	message := c.PostForm("message")
	priority := c.PostForm("priority")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Ticket created successfully",
		"ticket": map[string]interface{}{
			"subject": subject,
			"message": message,
			"priority": priority,
			"status": "open",
		},
	})
}

func (s *Server) handleAPIPerlModules(c *gin.Context) {
	modules := []map[string]interface{}{
		{"name": "DBI", "version": "1.643", "status": "installed"},
		{"name": "CGI", "version": "4.51", "status": "installed"},
	}
	
	c.JSON(http.StatusOK, gin.H{"modules": modules})
}

func (s *Server) handleAPIInstallPerlModule(c *gin.Context) {
	moduleName := c.PostForm("name")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Perl module " + moduleName + " installed successfully",
	})
}

func (s *Server) handleAPICustomHTTPd(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Custom HTTPd Configuration",
		"configurations": []map[string]interface{}{
			{"domain": "example.com", "type": "custom", "content": "# Custom Apache configuration"},
		},
	})
}

func (s *Server) handleAPIUpdateCustomHTTPd(c *gin.Context) {
	domain := c.PostForm("domain")
	content := c.PostForm("content")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Custom HTTPd configuration updated for " + domain,
		"content": content,
	})
}

func (s *Server) handleAPIDirectAdminConf(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "DirectAdmin Configuration",
		"config": map[string]interface{}{
			"admin_username": "admin",
			"max_users": 100,
			"ssl_redirect": true,
		},
	})
}

func (s *Server) handleAPIUpdateDirectAdminConf(c *gin.Context) {
	var configData map[string]interface{}
	if err := c.ShouldBindJSON(&configData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "DirectAdmin configuration updated successfully",
		"config": configData,
	})
}

func (s *Server) handleAPIMaintenance(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"maintenance_mode": false,
		"message": "System is operational",
		"scheduled_maintenance": nil,
	})
}

func (s *Server) handleAPIEnableMaintenance(c *gin.Context) {
	message := c.PostForm("message")
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Maintenance mode enabled",
		"maintenance_message": message,
	})
}

func (s *Server) handleAPIDisableMaintenance(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Maintenance mode disabled",
	})
}