package server

import (
	"net/http"
	"strconv"

	"admini/pkg/models"
	"admini/pkg/filemanager"
	"github.com/gin-gonic/gin"
)

// Admin handlers

func (s *Server) handleAdminBackupModify(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin Backup Modify",
		"message": "Configure backup settings",
	})
}

func (s *Server) handleAdminBackupMonitor(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin Backup Monitor",
		"backups": []map[string]interface{}{
			{"id": "backup1", "user": "user1", "status": "completed", "size": "100MB"},
			{"id": "backup2", "user": "user2", "status": "running", "progress": 75},
		},
	})
}

func (s *Server) handleAdminLimits(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin Limits",
		"limits": map[string]interface{}{
			"max_users": 100,
			"max_domains": 1000,
			"max_email_accounts": 5000,
			"max_databases": 500,
		},
	})
}

func (s *Server) handleAdminHistory(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin History",
		"history": []map[string]interface{}{
			{"date": "2023-12-01", "action": "User created", "user": "user1"},
			{"date": "2023-12-01", "action": "Domain added", "domain": "example.com"},
		},
	})
}

func (s *Server) handleAdminFileEditor(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin File Editor",
		"message": "File editor interface",
	})
}

func (s *Server) handleAdminSSL(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin SSL Management",
		"certificates": []map[string]interface{}{
			{"domain": "example.com", "issuer": "Let's Encrypt", "expires": "2024-12-01"},
		},
	})
}

func (s *Server) handleAdminCronJobs(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin Cron Jobs",
		"jobs": []map[string]interface{}{
			{"id": 1, "command": "/usr/bin/backup.sh", "schedule": "0 2 * * *", "enabled": true},
		},
	})
}

func (s *Server) handleAdminUsage(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Admin Usage Statistics",
		"usage": map[string]interface{}{
			"bandwidth_total": "1.5TB",
			"disk_total": "500GB",
			"users_active": 85,
			"domains_active": 150,
		},
	})
}

func (s *Server) handleSystemInfo(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"system": map[string]interface{}{
			"hostname": "server.example.com",
			"os": "CentOS 8",
			"kernel": "4.18.0",
			"admini_version": "1.680",
			"php_version": "8.1.0",
			"mysql_version": "8.0.30",
			"apache_version": "2.4.53",
		},
	})
}

func (s *Server) handleLoadAverage(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"load_average": []float64{0.15, 0.25, 0.20},
		"cpu_count": 4,
		"timestamp": "2023-12-01T12:00:00Z",
	})
}

func (s *Server) handleServerStatus(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "online",
		"uptime": "15 days, 3 hours",
		"services": map[string]string{
			"httpd": "running",
			"mysql": "running",
			"named": "running",
			"proftpd": "running",
			"dovecot": "running",
		},
	})
}

func (s *Server) handleServicesMonitor(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"services": []map[string]interface{}{
			{"name": "httpd", "status": "running", "pid": 1234, "memory": "50MB"},
			{"name": "mysql", "status": "running", "pid": 1235, "memory": "200MB"},
			{"name": "named", "status": "running", "pid": 1236, "memory": "30MB"},
		},
	})
}

func (s *Server) handleProcesses(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"processes": []map[string]interface{}{
			{"pid": 1234, "name": "httpd", "cpu": 5.2, "memory": "50MB", "user": "apache"},
			{"pid": 1235, "name": "mysqld", "cpu": 2.1, "memory": "200MB", "user": "mysql"},
		},
	})
}

func (s *Server) handleLicense(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"license": map[string]interface{}{
			"id": "DA-123456",
			"type": "Standard",
			"users": 10,
			"domains": 100,
			"expires": "2024-12-31",
			"valid": true,
		},
	})
}

func (s *Server) handleUpdate(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"current_version": "1.680",
		"latest_version": "1.681",
		"update_available": true,
		"update_url": "/admin/update-process",
	})
}

func (s *Server) handleCustomBuild(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "CustomBuild 2.0",
		"software": map[string]interface{}{
			"php": "8.1.0",
			"apache": "2.4.53",
			"mysql": "8.0.30",
			"nginx": "1.20.2",
		},
	})
}

func (s *Server) handleMaintenance(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"maintenance_mode": false,
		"message": "System is operational",
	})
}

// User interface handlers

func (s *Server) handleCronJobs(c *gin.Context) {
	username := s.getCurrentUser(c)
	c.JSON(http.StatusOK, gin.H{
		"title": "Cron Jobs",
		"username": username,
		"jobs": []models.CronJob{
			{ID: 1, Command: "php /home/user/script.php", Minute: "0", Hour: "2", Day: "*", Month: "*", Weekday: "*", Enabled: true},
		},
	})
}

func (s *Server) handleChangePassword(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Change Password",
		"form_action": "/CMD_USER/CMD_CHANGE_PASSWORD",
	})
}

func (s *Server) handleSiteBackup(c *gin.Context) {
	username := s.getCurrentUser(c)
	c.JSON(http.StatusOK, gin.H{
		"title": "Site Backup",
		"username": username,
		"backups": []models.Backup{
			{ID: "backup1", Username: username, Type: "user", Size: 104857600, Filename: "backup_user1.tar.gz"},
		},
	})
}

func (s *Server) handleSiteRestore(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Site Restore",
		"message": "Upload and restore backup files",
	})
}

// Email handlers

func (s *Server) handleEmailPOP(c *gin.Context) {
	username := s.getCurrentUser(c)
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
	
	c.JSON(http.StatusOK, gin.H{
		"accounts": accounts,
		"domain": domain,
		"username": username,
	})
}

func (s *Server) handleCreateEmailAccount(c *gin.Context) {
	username := s.getCurrentUser(c)
	domain := c.PostForm("domain")
	email := c.PostForm("email")
	password := c.PostForm("password")
	quotaStr := c.PostForm("quota")
	
	quota, _ := strconv.ParseInt(quotaStr, 10, 64)
	
	err := s.emailManager.CreateEmailAccount(username, domain, email, password, quota)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Email account created successfully",
	})
}

func (s *Server) handleEmailForwarders(c *gin.Context) {
	domain := c.Query("domain")
	
	forwarders, err := s.emailManager.ListEmailForwarders(domain)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"forwarders": forwarders,
		"domain": domain,
	})
}

func (s *Server) handleCreateEmailForwarder(c *gin.Context) {
	domain := c.PostForm("domain")
	source := c.PostForm("source")
	destination := c.PostForm("destination")
	
	err := s.emailManager.CreateEmailForwarder(domain, source, destination)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Email forwarder created successfully",
	})
}

func (s *Server) handleEmailVacation(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "Email Vacation Auto-Responder",
		"enabled": false,
		"message": "",
	})
}

func (s *Server) handleSetEmailVacation(c *gin.Context) {
	domain := c.PostForm("domain")
	email := c.PostForm("email")
	message := c.PostForm("message")
	enabled := c.PostForm("enabled") == "1"
	
	err := s.emailManager.SetVacationMessage(domain, email, message, enabled)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Vacation message updated successfully",
	})
}

func (s *Server) handleEmailFilters(c *gin.Context) {
	domain := c.Query("domain")
	email := c.Query("email")
	
	filters, err := s.emailManager.GetEmailFilters(domain, email)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"filters": filters,
		"domain": domain,
		"email": email,
	})
}

func (s *Server) handleCreateEmailFilter(c *gin.Context) {
	domain := c.PostForm("domain")
	email := c.PostForm("email")
	condition := c.PostForm("condition")
	action := c.PostForm("action")
	
	err := s.emailManager.CreateEmailFilter(domain, email, condition, action)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Email filter created successfully",
	})
}

func (s *Server) handleSpamAssassin(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"title": "SpamAssassin Configuration",
		"enabled": true,
		"score_required": 5.0,
		"score_discard": 10.0,
	})
}

// File Manager handlers

func (s *Server) handleFileManagerAPI(c *gin.Context) {
	path := c.Query("path")
	if path == "" {
		path = "/"
	}
	
	username := s.getCurrentUser(c)
	fm := s.getFileManagerForUser(username)
	
	items, err := fm.ListDirectory(path)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"path": path,
		"items": items,
	})
}

func (s *Server) handleFileUpload(c *gin.Context) {
	path := c.PostForm("path")
	file, header, err := c.Request.FormFile("file")
	if err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "No file uploaded"})
		return
	}
	defer file.Close()
	
	username := s.getCurrentUser(c)
	fm := s.getFileManagerForUser(username)
	
	err = fm.UploadFile(path, file, header)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "File uploaded successfully",
	})
}

func (s *Server) handleFileDownload(c *gin.Context) {
	path := c.Query("path")
	if path == "" {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Path parameter required"})
		return
	}
	
	username := s.getCurrentUser(c)
	fm := s.getFileManagerForUser(username)
	
	content, err := fm.ReadFile(path)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.Header("Content-Disposition", "attachment; filename=\""+path+"\"")
	c.Data(http.StatusOK, "application/octet-stream", []byte(content))
}

func (s *Server) handleFileEditor(c *gin.Context) {
	path := c.Query("path")
	if path == "" {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Path parameter required"})
		return
	}
	
	username := s.getCurrentUser(c)
	fm := s.getFileManagerForUser(username)
	
	content, err := fm.ReadFile(path)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"path": path,
		"content": content,
	})
}

func (s *Server) handleFileEditorSave(c *gin.Context) {
	path := c.PostForm("path")
	content := c.PostForm("content")
	
	username := s.getCurrentUser(c)
	fm := s.getFileManagerForUser(username)
	
	err := fm.WriteFile(path, content)
	if err != nil {
		c.JSON(http.StatusInternalServerError, gin.H{"error": err.Error()})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "File saved successfully",
	})
}

// Helper methods

func (s *Server) getCurrentUser(c *gin.Context) string {
	// In a real implementation, this would extract the user from the session/token
	return "admin"
}

func (s *Server) getFileManagerForUser(username string) *filemanager.FileManager {
	// Create file manager with user-specific base path
	basePath := "/home/" + username
	return filemanager.NewFileManager(basePath, username)
}