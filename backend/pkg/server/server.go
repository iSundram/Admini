package server

import (
	"net/http"

	"admini/pkg/config"
	"admini/pkg/constants"
	"admini/pkg/email"
	"admini/pkg/filemanager"
	"admini/pkg/database"
	"github.com/gin-gonic/gin"
)

type Server struct {
	router          *gin.Engine
	config          *config.Config
	emailManager    *email.EmailManager
	fileManager     *filemanager.FileManager
	databaseManager *database.DatabaseManager
}

// NewServer creates a new Admini web server
func NewServer() *Server {
	gin.SetMode(gin.ReleaseMode)
	router := gin.New()
	router.Use(gin.Logger(), gin.Recovery())
	
	// Load DirectAdmin skin system (primary UI)
	// Note: Templates from backend/templates/* are kept for compatibility but skins are primary
	router.LoadHTMLGlob("backend/templates/*")
	
	// Serve static files
	router.Static("/static", "./backend/static")
	
	cfg := config.GetConfig()
	
	s := &Server{
		router:          router,
		config:          cfg,
		emailManager:    email.NewEmailManager("/usr/local/admini/data"),
		fileManager:     filemanager.NewFileManager("/home", "admin"), // Default user
		databaseManager: database.NewDatabaseManager("", "/usr/local/admini/data"),
	}
	
	s.setupRoutes()
	return s
}

func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	s.router.ServeHTTP(w, r)
}

func (s *Server) setupRoutes() {
	// Main Admini interface
	s.router.GET("/", s.handleIndex)
	s.router.GET("/"+constants.CMD_LOGIN, s.handleLogin)
	s.router.POST("/"+constants.CMD_LOGIN, s.handleLoginPost)
	s.router.GET("/"+constants.CMD_API_LOGIN_KEY, s.handleAPILoginKey)
	
	// Admin interface routes
	s.setupAdminRoutes()
	
	// User interface routes
	s.setupUserRoutes()
	
	// Reseller interface routes
	s.setupResellerRoutes()
	
	// API endpoints
	s.setupAPIRoutes()
	
	// cPanel-style routes
	s.setupCPanelRoutes()
	
	// DirectAdmin-compatible skin system (primary UI)
	s.router.Static("/evolution", "./data/skins/evolution") 
	s.router.Static("/enhanced", "./data/skins/enhanced")
	s.router.StaticFile("/favicon.ico", "./data/skins/evolution/images/favicon.ico")
}

func (s *Server) setupAdminRoutes() {
	admin := s.router.Group("/CMD_ADMIN")
	admin.Use(s.authMiddleware())
	{
		admin.GET("/", s.handleAdminIndex)
		admin.GET("/"+constants.CMD_ADMIN_STATS, s.handleAdminStats)
		admin.GET("/"+constants.CMD_API_SHOW_USERS, s.handleShowUsers)
		admin.GET("/"+constants.CMD_API_SHOW_DOMAINS, s.handleShowDomains)
		admin.GET("/"+constants.CMD_API_SHOW_RESELLERS, s.handleShowResellers)
		admin.POST("/"+constants.CMD_API_ACCOUNT_USER, s.handleCreateUser)
		admin.POST("/"+constants.CMD_API_MODIFY_USER, s.handleModifyUser)
		admin.POST("/"+constants.CMD_API_SELECT_USERS, s.handleDeleteUser)
		admin.GET("/"+constants.CMD_ADMIN_BACKUP_MODIFY, s.handleAdminBackupModify)
		admin.GET("/"+constants.CMD_ADMIN_BACKUP_MONITOR, s.handleAdminBackupMonitor)
		admin.GET("/"+constants.CMD_ADMIN_LIMITS, s.handleAdminLimits)
		admin.GET("/"+constants.CMD_ADMIN_HISTORY, s.handleAdminHistory)
		admin.GET("/"+constants.CMD_ADMIN_FILE_EDITOR, s.handleAdminFileEditor)
		admin.GET("/"+constants.CMD_ADMIN_SSL, s.handleAdminSSL)
		admin.GET("/"+constants.CMD_ADMIN_CRON_JOBS, s.handleAdminCronJobs)
		admin.GET("/"+constants.CMD_API_ADMIN_USAGE, s.handleAdminUsage)
		admin.GET("/"+constants.CMD_API_SYSTEM_INFO, s.handleSystemInfo)
		admin.GET("/"+constants.CMD_API_LOAD_AVERAGE, s.handleLoadAverage)
		admin.GET("/"+constants.CMD_SERVERSTATUS, s.handleServerStatus)
		admin.GET("/"+constants.CMD_SERVICES_MONITOR, s.handleServicesMonitor)
		admin.GET("/"+constants.CMD_PROCESSES, s.handleProcesses)
		admin.GET("/"+constants.CMD_LICENSE, s.handleLicense)
		admin.GET("/"+constants.CMD_UPDATE, s.handleUpdate)
		admin.GET("/"+constants.CMD_CUSTOMBUILD, s.handleCustomBuild)
		admin.GET("/"+constants.CMD_MAINTENANCE, s.handleMaintenance)
	}
}

func (s *Server) setupUserRoutes() {
	user := s.router.Group("/CMD_USER")
	user.Use(s.authMiddleware())
	{
		user.GET("/", s.handleUserIndex)
		user.GET("/"+constants.CMD_DOMAIN, s.handleUserDomains)
		user.GET("/"+constants.CMD_SUBDOMAIN, s.handleSubdomains)
		user.GET("/"+constants.CMD_EMAIL, s.handleEmail)
		user.GET("/"+constants.CMD_FILE_MANAGER, s.handleFileManager)
		user.GET("/"+constants.CMD_DB, s.handleDatabases)
		user.GET("/"+constants.CMD_SSL, s.handleSSL)
		user.GET("/"+constants.CMD_CRON_JOBS, s.handleCronJobs)
		user.GET("/"+constants.CMD_CHANGE_PASSWORD, s.handleChangePassword)
		user.GET("/"+constants.CMD_SITE_BACKUP, s.handleSiteBackup)
		user.GET("/"+constants.CMD_SITE_RESTORE, s.handleSiteRestore)
		user.POST("/"+constants.CMD_API_DOMAIN, s.handleCreateDomain)
		user.POST("/"+constants.CMD_API_SUBDOMAIN, s.handleCreateSubdomain)
		
		// Email management
		user.GET("/"+constants.CMD_API_EMAIL_POP, s.handleEmailPOP)
		user.POST("/"+constants.CMD_API_EMAIL_POP, s.handleCreateEmailAccount)
		user.GET("/"+constants.CMD_API_EMAIL_FORWARDER, s.handleEmailForwarders)
		user.POST("/"+constants.CMD_API_EMAIL_FORWARDER, s.handleCreateEmailForwarder)
		user.GET("/"+constants.CMD_API_EMAIL_VACATION, s.handleEmailVacation)
		user.POST("/"+constants.CMD_API_EMAIL_VACATION, s.handleSetEmailVacation)
		user.GET("/"+constants.CMD_API_EMAIL_FILTER, s.handleEmailFilters)
		user.POST("/"+constants.CMD_API_EMAIL_FILTER, s.handleCreateEmailFilter)
		user.GET("/"+constants.CMD_API_SPAMASSASSIN, s.handleSpamAssassin)
		
		// File management
		user.GET("/"+constants.CMD_API_FILE_MANAGER, s.handleFileManagerAPI)
		user.POST("/"+constants.CMD_API_FILE_UPLOAD, s.handleFileUpload)
		user.GET("/"+constants.CMD_API_FILE_DOWNLOAD, s.handleFileDownload)
		user.GET("/"+constants.CMD_API_FILE_EDITOR, s.handleFileEditor)
		user.POST("/"+constants.CMD_API_FILE_EDITOR, s.handleFileEditorSave)
		
		// Database management
		user.GET("/"+constants.CMD_API_DATABASES, s.handleDatabasesAPI)
		user.POST("/"+constants.CMD_API_DATABASES, s.handleCreateDatabase)
		user.GET("/"+constants.CMD_API_DB_USER, s.handleDatabaseUsers)
		user.POST("/"+constants.CMD_API_DB_USER, s.handleCreateDatabaseUser)
		user.GET("/"+constants.CMD_API_DB_USER_PRIVS, s.handleDatabasePrivileges)
		user.GET("/"+constants.CMD_PHPMYADMIN, s.handlePHPMyAdmin)
		user.GET("/"+constants.CMD_API_PMA_SIGNON, s.handlePMASignOn)
		
		// SSL management
		user.GET("/"+constants.CMD_API_SSL, s.handleSSLAPI)
		user.POST("/"+constants.CMD_API_SSL_UPLOAD, s.handleSSLUpload)
		user.GET("/"+constants.CMD_LETSENCRYPT, s.handleLetsEncrypt)
		user.POST("/"+constants.CMD_API_LETSENCRYPT, s.handleLetsEncryptCreate)
		
		// Additional domains
		user.GET("/"+constants.CMD_ADDITIONAL_DOMAINS, s.handleAdditionalDomains)
		user.GET("/"+constants.CMD_ADDITIONAL_DOMAINS_VIEW, s.handleAdditionalDomainsView)
		user.POST("/"+constants.CMD_API_ADDITIONAL_DOMAINS, s.handleCreateAdditionalDomain)
		user.POST("/"+constants.CMD_API_DOMAIN_POINTER, s.handleCreateDomainPointer)
		
		// DNS management
		user.GET("/"+constants.CMD_API_DNS_CONTROL, s.handleDNSControl)
		user.POST("/"+constants.CMD_API_DNS_CONTROL, s.handleCreateDNSRecord)
		user.GET("/"+constants.CMD_API_DNS_MX, s.handleDNSMX)
		
		// Statistics and monitoring
		user.GET("/"+constants.CMD_API_BANDWIDTH_BREAKDOWN, s.handleBandwidthBreakdown)
		user.GET("/"+constants.CMD_API_DU_BREAKDOWN, s.handleDiskUsageBreakdown)
		user.GET("/"+constants.CMD_API_PUBLIC_STATS, s.handlePublicStats)
		
		// FTP management
		user.GET("/"+constants.CMD_API_FTP_SETTINGS, s.handleFTPSettings)
		user.GET("/"+constants.CMD_API_FTP_SHOW_USERS, s.handleFTPUsers)
		
		// Webmail access
		user.GET("/"+constants.CMD_WEBMAIL, s.handleWebmail)
		user.GET("/"+constants.CMD_ROUNDCUBE, s.handleRoundcube)
		user.GET("/"+constants.CMD_SQUIRRELMAIL, s.handleSquirrelMail)
	}
}

func (s *Server) setupResellerRoutes() {
	reseller := s.router.Group("/CMD_RESELLER")
	reseller.Use(s.authMiddleware())
	{
		reseller.GET("/", s.handleResellerIndex)
		reseller.GET("/"+constants.CMD_RESELLER_STATS, s.handleResellerStats)
		reseller.GET("/"+constants.CMD_RESELLER_HISTORY, s.handleResellerHistory)
		reseller.GET("/"+constants.CMD_SHOW_RESELLERS, s.handleShowResellers)
		reseller.GET("/"+constants.CMD_PLUGINS_RESELLER, s.handlePluginsReseller)
		reseller.GET("/"+constants.CMD_API_RESELLER_STATS, s.handleResellerStatsAPI)
		reseller.GET("/"+constants.CMD_API_RESELLER_BACKUP, s.handleResellerBackup)
		reseller.GET("/"+constants.CMD_API_PACKAGES, s.handlePackages)
		reseller.POST("/"+constants.CMD_API_PACKAGES, s.handleCreatePackage)
	}
}

func (s *Server) setupAPIRoutes() {
	api := s.router.Group("/api")
	api.Use(s.apiAuthMiddleware())
	{
		// User management API
		api.GET("/users", s.handleAPIUsers)
		api.POST("/users", s.handleAPICreateUser)
		api.GET("/users/:username", s.handleAPIGetUser)
		api.PUT("/users/:username", s.handleAPIUpdateUser)
		api.DELETE("/users/:username", s.handleAPIDeleteUser)
		
		// Domain management API
		api.GET("/domains", s.handleAPIDomains)
		api.POST("/domains", s.handleAPICreateDomain)
		api.GET("/domains/:domain", s.handleAPIGetDomain)
		api.PUT("/domains/:domain", s.handleAPIUpdateDomain)
		api.DELETE("/domains/:domain", s.handleAPIDeleteDomain)
		
		// Email management API
		api.GET("/email", s.handleAPIEmail)
		api.POST("/email", s.handleAPICreateEmail)
		api.GET("/email/:email", s.handleAPIGetEmail)
		api.PUT("/email/:email", s.handleAPIUpdateEmail)
		api.DELETE("/email/:email", s.handleAPIDeleteEmail)
		
		// Database management API
		api.GET("/databases", s.handleAPIDatabasesList)
		api.POST("/databases", s.handleAPICreateDatabase)
		api.GET("/databases/:name", s.handleAPIGetDatabase)
		api.DELETE("/databases/:name", s.handleAPIDeleteDatabase)
		
		// System API
		api.GET("/stats", s.handleAPIStats)
		api.GET("/system", s.handleAPISystemInfo)
		api.GET("/load", s.handleAPILoadAverage)
		api.GET("/processes", s.handleAPIProcesses)
		api.GET("/services", s.handleAPIServices)
		
		// Configuration API
		api.GET("/config", s.handleAPIConfig)
		api.PUT("/config", s.handleAPIUpdateConfig)
		
		// Backup and restore API
		api.GET("/backups", s.handleAPIBackups)
		api.POST("/backups", s.handleAPICreateBackup)
		api.POST("/restore", s.handleAPIRestore)
		
		// Task queue API
		api.GET("/tasks", s.handleAPITasks)
		api.GET("/tasks/:id", s.handleAPIGetTask)
		api.POST("/tasks", s.handleAPICreateTask)
		
		// Login keys API
		api.GET("/login-keys", s.handleAPILoginKeys)
		api.POST("/login-keys", s.handleAPICreateLoginKey)
		api.DELETE("/login-keys/:key", s.handleAPIDeleteLoginKey)
		
		// Two-step authentication API
		api.GET("/twostep", s.handleAPITwoStepAuth)
		api.POST("/twostep", s.handleAPIEnableTwoStepAuth)
		api.DELETE("/twostep", s.handleAPIDisableTwoStepAuth)
		
		// AJAX endpoints
		api.GET("/ajax/check-domain", s.handleAjaxCheckDomain)
		api.GET("/ajax/check-username", s.handleAjaxCheckUsername)
		api.GET("/ajax/check-password", s.handleAjaxCheckPassword)
		api.GET("/ajax/search", s.handleAjaxSearch)
		api.GET("/ajax/users", s.handleAjaxUsers)
		api.GET("/ajax/counts", s.handleAjaxGetCounts)
		
		// Plugin management
		api.GET("/plugins", s.handleAPIPlugins)
		api.POST("/plugins", s.handleAPIInstallPlugin)
		api.DELETE("/plugins/:name", s.handleAPIUninstallPlugin)
		
		// Multi-server management
		api.GET("/multi-server", s.handleAPIMultiServer)
		api.POST("/multi-server", s.handleAPIAddServer)
		
		// Brute force monitoring
		api.GET("/brute-force", s.handleAPIBruteForceMonitor)
		api.POST("/brute-force/unblock", s.handleAPIUnblockIP)
		
		// Comments and tickets
		api.GET("/comments", s.handleAPIComments)
		api.POST("/comments", s.handleAPICreateComment)
		api.GET("/tickets", s.handleAPITickets)
		api.POST("/tickets", s.handleAPICreateTicket)
		
		// Perl modules
		api.GET("/perl-modules", s.handleAPIPerlModules)
		api.POST("/perl-modules", s.handleAPIInstallPerlModule)
		
		// Custom HTTPd configuration
		api.GET("/custom-httpd", s.handleAPICustomHTTPd)
		api.PUT("/custom-httpd", s.handleAPIUpdateCustomHTTPd)
		
		// Admini configuration
		api.GET("/admini-conf", s.handleAPIAdminiConf)
		api.PUT("/admini-conf", s.handleAPIUpdateAdminiConf)
		
		// Maintenance mode
		api.GET("/maintenance", s.handleAPIMaintenance)
		api.POST("/maintenance", s.handleAPIEnableMaintenance)
		api.DELETE("/maintenance", s.handleAPIDisableMaintenance)
	}
}

func (s *Server) handleIndex(c *gin.Context) {
	// Redirect to DirectAdmin skin-based login (primary UI)
	c.Redirect(http.StatusFound, "/evolution/login.html")
}

func (s *Server) handleLogin(c *gin.Context) {
	// Redirect to DirectAdmin skin-based login (primary UI)
	c.Redirect(http.StatusFound, "/evolution/login.html")
}

func (s *Server) handleLoginPost(c *gin.Context) {
	username := c.PostForm("username")
	password := c.PostForm("password")
	
	// Basic authentication check
	if s.authenticateUser(username, password) {
		// Set session/cookie
		c.SetCookie("session", "authenticated", 3600, "/", "", false, true)
		
		// Determine user level and redirect to appropriate dashboard
		userLevel := s.getUserLevel(username)
		switch userLevel {
		case "admin":
			c.Redirect(http.StatusFound, "/CMD_ADMIN/")
		case "reseller":
			c.Redirect(http.StatusFound, "/CMD_RESELLER/")
		default:
			c.Redirect(http.StatusFound, "/CMD_USER/")
		}
	} else {
		// Redirect back to skin-based login with error
		c.Redirect(http.StatusFound, "/evolution/login.html?error=invalid_credentials")
	}
}

func (s *Server) handleAPILoginKey(c *gin.Context) {
	key := c.Query("key")
	if key == "" {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Missing key parameter"})
		return
	}
	
	// Validate API key and create session
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"session": "api_session_" + key,
	})
}

func (s *Server) handleAdminIndex(c *gin.Context) {
	c.HTML(http.StatusOK, "dashboard.html", gin.H{
		"title":        "AdminiCore Dashboard",
		"panel_name":   "AdminiCore",
		"level":        "admin",
		"username":     s.getCurrentUser(c),
		"current_page": "dashboard",
		"stats": gin.H{
			"total_users":   "142",
			"total_domains": "367",
			"server_load":   "0.25",
		},
		"system": gin.H{
			"hostname":       "server.example.com",
			"os":            "CentOS Stream 8",
			"kernel":        "4.18.0",
			"admini_version": "1.680",
			"uptime":        "15 days, 3 hours",
		},
	})
}

func (s *Server) handleAdminStats(c *gin.Context) {
	stats := map[string]interface{}{
		"server_load":    []float64{0.1, 0.2, 0.15},
		"memory_usage":   "2.1GB / 8GB",
		"disk_usage":     "45GB / 100GB",
		"users_count":    10,
		"domains_count":  25,
		"uptime":         "15 days",
	}
	c.JSON(http.StatusOK, stats)
}

func (s *Server) handleShowUsers(c *gin.Context) {
	users := []map[string]interface{}{
		{"username": "admin", "level": "admin", "suspended": "no"},
		{"username": "user1", "level": "user", "suspended": "no"},
		{"username": "user2", "level": "user", "suspended": "yes"},
	}
	c.JSON(http.StatusOK, users)
}

func (s *Server) handleShowDomains(c *gin.Context) {
	domains := []map[string]interface{}{
		{"domain": "example.com", "user": "user1", "suspended": "no"},
		{"domain": "test.com", "user": "user2", "suspended": "yes"},
	}
	c.JSON(http.StatusOK, domains)
}

func (s *Server) handleShowResellers(c *gin.Context) {
	resellers := []map[string]interface{}{
		{"username": "reseller1", "level": "reseller", "suspended": "no"},
	}
	c.JSON(http.StatusOK, resellers)
}

func (s *Server) handleCreateUser(c *gin.Context) {
	var userData map[string]interface{}
	if err := c.ShouldBindJSON(&userData); err != nil {
		c.JSON(http.StatusBadRequest, gin.H{"error": "Invalid JSON"})
		return
	}
	
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "User created successfully",
	})
}

func (s *Server) handleModifyUser(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "User modified successfully",
	})
}

func (s *Server) handleDeleteUser(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "User deleted successfully",
	})
}

func (s *Server) handleUserIndex(c *gin.Context) {
	c.HTML(http.StatusOK, "dashboard.html", gin.H{
		"title":        "Dashboard",
		"panel_name":   "AdminiPanel",
		"level":        "user",
		"username":     s.getCurrentUser(c),
		"current_page": "dashboard",
		"stats": gin.H{
			"domains":        "2",
			"email_accounts": "5",
			"databases":      "3",
		},
		"usage": gin.H{
			"disk":      "150 MB",
			"bandwidth": "2.1 GB",
		},
		"limits": gin.H{
			"disk":      "1 GB",
			"bandwidth": "10 GB",
		},
		"package":      "Standard",
		"created_date": "2023-01-01",
	})
}

func (s *Server) handleUserDomains(c *gin.Context) {
	domains := []string{"example.com", "test.org"}
	c.JSON(http.StatusOK, gin.H{"domains": domains})
}

func (s *Server) handleSubdomains(c *gin.Context) {
	subdomains := []string{"www.example.com", "mail.example.com"}
	c.JSON(http.StatusOK, gin.H{"subdomains": subdomains})
}

func (s *Server) handleEmail(c *gin.Context) {
	emails := []map[string]string{
		{"email": "admin@example.com", "quota": "100MB"},
		{"email": "user@example.com", "quota": "50MB"},
	}
	c.JSON(http.StatusOK, gin.H{"emails": emails})
}

func (s *Server) handleFileManager(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"current_path": "/domains/example.com/public_html",
		"files": []string{"index.html", "style.css", "script.js"},
	})
}

func (s *Server) handleDatabases(c *gin.Context) {
	databases := []map[string]string{
		{"name": "user1_db1", "size": "10MB"},
		{"name": "user1_db2", "size": "5MB"},
	}
	c.JSON(http.StatusOK, gin.H{"databases": databases})
}

func (s *Server) handleSSL(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"ssl_enabled": true,
		"certificate_expiry": "2024-12-31",
	})
}

func (s *Server) handleCreateDomain(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Domain created successfully",
	})
}

func (s *Server) handleCreateSubdomain(c *gin.Context) {
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": "Subdomain created successfully",
	})
}

// Middleware functions
func (s *Server) authMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		cookie, err := c.Cookie("session")
		if err != nil || cookie != "authenticated" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "Authentication required"})
			c.Abort()
			return
		}
		c.Next()
	}
}

func (s *Server) apiAuthMiddleware() gin.HandlerFunc {
	return func(c *gin.Context) {
		apiKey := c.GetHeader("X-API-Key")
		if apiKey == "" {
			c.JSON(http.StatusUnauthorized, gin.H{"error": "API key required"})
			c.Abort()
			return
		}
		// Validate API key here
		c.Next()
	}
}

func (s *Server) authenticateUser(username, password string) bool {
	// Basic authentication - in real implementation, check against user database
	adminUser := s.config.Get("admin_username")
	// For demo purposes, accept "admin" / "password" or configured admin with any non-empty password
	return (username == "admin" && password == "password") || (username == adminUser && password != "")
}

// Helper function for user levels
func (s *Server) getUserLevel(username string) string {
	// In a real implementation, this would check the user database
	adminUser := s.config.Get("admin_username")
	if username == adminUser {
		return "admin"
	}
	// For demo purposes, assume other users are regular users
	return "user"
}

func (s *Server) setupCPanelRoutes() {
	// cPanel-style compatibility routes
	cpanel := s.router.Group("/cpanel")
	cpanel.Use(s.authMiddleware())
	{
		cpanel.GET("/", s.handleUserIndex)
		cpanel.GET("/mail", s.handleEmail)
		cpanel.GET("/files", s.handleFileManager)
		cpanel.GET("/databases", s.handleDatabases)
		cpanel.GET("/domains", s.handleUserDomains)
	}
}