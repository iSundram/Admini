package server

import (
	"fmt"
	"net/http"

	"directadmin/pkg/config"
	"github.com/gin-gonic/gin"
)

type Server struct {
	router *gin.Engine
	config *config.Config
}

// NewServer creates a new DirectAdmin web server
func NewServer() *Server {
	gin.SetMode(gin.ReleaseMode)
	router := gin.New()
	router.Use(gin.Logger(), gin.Recovery())
	
	s := &Server{
		router: router,
		config: config.GetConfig(),
	}
	
	s.setupRoutes()
	return s
}

func (s *Server) ServeHTTP(w http.ResponseWriter, r *http.Request) {
	s.router.ServeHTTP(w, r)
}

func (s *Server) setupRoutes() {
	// Main DirectAdmin interface
	s.router.GET("/", s.handleIndex)
	s.router.GET("/CMD_LOGIN", s.handleLogin)
	s.router.POST("/CMD_LOGIN", s.handleLoginPost)
	s.router.GET("/CMD_API_LOGIN_KEY", s.handleAPILoginKey)
	
	// Admin interface
	admin := s.router.Group("/CMD_ADMIN")
	admin.Use(s.authMiddleware())
	{
		admin.GET("/", s.handleAdminIndex)
		admin.GET("/CMD_ADMIN_STATS", s.handleAdminStats)
		admin.GET("/CMD_API_SHOW_USERS", s.handleShowUsers)
		admin.GET("/CMD_API_SHOW_DOMAINS", s.handleShowDomains)
		admin.GET("/CMD_API_SHOW_RESELLERS", s.handleShowResellers)
		admin.POST("/CMD_API_ACCOUNT_USER", s.handleCreateUser)
		admin.POST("/CMD_API_MODIFY_USER", s.handleModifyUser)
		admin.POST("/CMD_API_SELECT_USERS", s.handleDeleteUser)
	}
	
	// User interface
	user := s.router.Group("/CMD_USER")
	user.Use(s.authMiddleware())
	{
		user.GET("/", s.handleUserIndex)
		user.GET("/CMD_DOMAIN", s.handleUserDomains)
		user.GET("/CMD_SUBDOMAIN", s.handleSubdomains)
		user.GET("/CMD_EMAIL", s.handleEmail)
		user.GET("/CMD_FILE_MANAGER", s.handleFileManager)
		user.GET("/CMD_DB", s.handleDatabases)
		user.GET("/CMD_SSL", s.handleSSL)
		user.POST("/CMD_API_DOMAIN", s.handleCreateDomain)
		user.POST("/CMD_API_SUBDOMAIN", s.handleCreateSubdomain)
	}
	
	// API endpoints
	api := s.router.Group("/api")
	api.Use(s.apiAuthMiddleware())
	{
		api.GET("/users", s.handleAPIUsers)
		api.GET("/domains", s.handleAPIDomains)
		api.GET("/stats", s.handleAPIStats)
		api.POST("/users", s.handleAPICreateUser)
		api.DELETE("/users/:username", s.handleAPIDeleteUser)
	}
	
	// Static files
	s.router.Static("/evolution", "./data/skins/evolution")
	s.router.Static("/enhanced", "./data/skins/enhanced")
	s.router.StaticFile("/favicon.ico", "./data/skins/evolution/images/favicon.ico")
}

func (s *Server) handleIndex(c *gin.Context) {
	c.HTML(http.StatusOK, "login.html", gin.H{
		"title":   "DirectAdmin Login",
		"version": "1.680",
	})
}

func (s *Server) handleLogin(c *gin.Context) {
	c.HTML(http.StatusOK, "login.html", gin.H{
		"title": "DirectAdmin Login",
	})
}

func (s *Server) handleLoginPost(c *gin.Context) {
	username := c.PostForm("username")
	password := c.PostForm("password")
	
	// Basic authentication check
	if s.authenticateUser(username, password) {
		// Set session/cookie
		c.SetCookie("session", "authenticated", 3600, "/", "", false, true)
		c.Redirect(http.StatusFound, "/CMD_ADMIN/")
	} else {
		c.HTML(http.StatusUnauthorized, "login.html", gin.H{
			"error": "Invalid credentials",
		})
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
	c.JSON(http.StatusOK, gin.H{
		"title": "DirectAdmin - Administration",
		"user":  "admin",
		"level": "admin",
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
	c.JSON(http.StatusOK, gin.H{
		"title": "DirectAdmin - User Panel",
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

// API handlers
func (s *Server) handleAPIUsers(c *gin.Context) {
	s.handleShowUsers(c)
}

func (s *Server) handleAPIDomains(c *gin.Context) {
	s.handleShowDomains(c)
}

func (s *Server) handleAPIStats(c *gin.Context) {
	s.handleAdminStats(c)
}

func (s *Server) handleAPICreateUser(c *gin.Context) {
	s.handleCreateUser(c)
}

func (s *Server) handleAPIDeleteUser(c *gin.Context) {
	username := c.Param("username")
	c.JSON(http.StatusOK, gin.H{
		"status": "success",
		"message": fmt.Sprintf("User %s deleted successfully", username),
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
	return username == adminUser && password != ""
}