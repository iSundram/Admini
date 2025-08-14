package database

import (
	"database/sql"
	"fmt"
	"os"
	"path/filepath"
	"strings"

	"admini/pkg/models"
	_ "github.com/go-sql-driver/mysql"
)

// DatabaseManager handles all database operations
type DatabaseManager struct {
	mysqlRootPassword string
	dataPath          string
}

// NewDatabaseManager creates a new database manager
func NewDatabaseManager(mysqlRootPassword, dataPath string) *DatabaseManager {
	return &DatabaseManager{
		mysqlRootPassword: mysqlRootPassword,
		dataPath:          dataPath,
	}
}

// CreateDatabase creates a new MySQL database
func (dm *DatabaseManager) CreateDatabase(username, dbName, dbUser, dbPassword string) error {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	// Create database
	fullDbName := fmt.Sprintf("%s_%s", username, dbName)
	_, err = db.Exec(fmt.Sprintf("CREATE DATABASE `%s`", fullDbName))
	if err != nil {
		return fmt.Errorf("failed to create database: %w", err)
	}

	// Create database user
	fullDbUser := fmt.Sprintf("%s_%s", username, dbUser)
	_, err = db.Exec(fmt.Sprintf("CREATE USER '%s'@'localhost' IDENTIFIED BY '%s'", fullDbUser, dbPassword))
	if err != nil {
		return fmt.Errorf("failed to create database user: %w", err)
	}

	// Grant privileges
	_, err = db.Exec(fmt.Sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'localhost'", fullDbName, fullDbUser))
	if err != nil {
		return fmt.Errorf("failed to grant privileges: %w", err)
	}

	// Flush privileges
	_, err = db.Exec("FLUSH PRIVILEGES")
	if err != nil {
		return fmt.Errorf("failed to flush privileges: %w", err)
	}

	// Create database info file
	dbInfoFile := filepath.Join(dm.dataPath, "mysql", fmt.Sprintf("%s_%s.conf", username, dbName))
	if err := os.MkdirAll(filepath.Dir(dbInfoFile), 0755); err != nil {
		return fmt.Errorf("failed to create database config directory: %w", err)
	}

	dbInfo := fmt.Sprintf("name=%s\nuser=%s\ncharset=utf8mb4\ncollation=utf8mb4_unicode_ci\n", fullDbName, fullDbUser)
	if err := os.WriteFile(dbInfoFile, []byte(dbInfo), 0644); err != nil {
		return fmt.Errorf("failed to create database info file: %w", err)
	}

	return nil
}

// ListDatabases returns all databases for a user
func (dm *DatabaseManager) ListDatabases(username string) ([]models.Database, error) {
	var databases []models.Database

	// Read database info files
	mysqlDir := filepath.Join(dm.dataPath, "mysql")
	if _, err := os.Stat(mysqlDir); os.IsNotExist(err) {
		return databases, nil
	}

	pattern := fmt.Sprintf("%s_*.conf", username)
	matches, err := filepath.Glob(filepath.Join(mysqlDir, pattern))
	if err != nil {
		return nil, fmt.Errorf("failed to list database files: %w", err)
	}

	for _, match := range matches {
		dbInfo, err := dm.readDatabaseInfo(match)
		if err != nil {
			continue // Skip invalid database files
		}

		// Get database size
		size, err := dm.getDatabaseSize(dbInfo.Name)
		if err != nil {
			size = 0
		}

		database := models.Database{
			Name:     dbInfo.Name,
			Username: username,
			Type:     "mysql",
			Size:     size,
			Users:    []string{dbInfo.Users},
			Charset:  dbInfo.Charset,
			Collation: dbInfo.Collation,
		}

		databases = append(databases, database)
	}

	return databases, nil
}

// DatabaseInfo represents database configuration
type DatabaseInfo struct {
	Name      string
	Users     string
	Charset   string
	Collation string
}

// readDatabaseInfo reads database information from config file
func (dm *DatabaseManager) readDatabaseInfo(configFile string) (*DatabaseInfo, error) {
	data, err := os.ReadFile(configFile)
	if err != nil {
		return nil, err
	}

	info := &DatabaseInfo{}
	lines := strings.Split(string(data), "\n")
	
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
		case "name":
			info.Name = value
		case "user":
			info.Users = value
		case "charset":
			info.Charset = value
		case "collation":
			info.Collation = value
		}
	}

	return info, nil
}

// DeleteDatabase removes a database and its user
func (dm *DatabaseManager) DeleteDatabase(username, dbName string) error {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	fullDbName := fmt.Sprintf("%s_%s", username, dbName)

	// Get database users before dropping
	users, err := dm.getDatabaseUsers(fullDbName)
	if err != nil {
		return fmt.Errorf("failed to get database users: %w", err)
	}

	// Drop database
	_, err = db.Exec(fmt.Sprintf("DROP DATABASE IF EXISTS `%s`", fullDbName))
	if err != nil {
		return fmt.Errorf("failed to drop database: %w", err)
	}

	// Drop users
	for _, user := range users {
		_, err = db.Exec(fmt.Sprintf("DROP USER IF EXISTS '%s'@'localhost'", user))
		if err != nil {
			// Log error but continue
			continue
		}
	}

	// Flush privileges
	_, err = db.Exec("FLUSH PRIVILEGES")
	if err != nil {
		return fmt.Errorf("failed to flush privileges: %w", err)
	}

	// Remove database info file
	dbInfoFile := filepath.Join(dm.dataPath, "mysql", fmt.Sprintf("%s_%s.conf", username, dbName))
	os.Remove(dbInfoFile) // Ignore errors

	return nil
}

// CreateDatabaseUser creates a new database user
func (dm *DatabaseManager) CreateDatabaseUser(username, dbName, dbUser, dbPassword string) error {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	fullDbName := fmt.Sprintf("%s_%s", username, dbName)
	fullDbUser := fmt.Sprintf("%s_%s", username, dbUser)

	// Create database user
	_, err = db.Exec(fmt.Sprintf("CREATE USER '%s'@'localhost' IDENTIFIED BY '%s'", fullDbUser, dbPassword))
	if err != nil {
		return fmt.Errorf("failed to create database user: %w", err)
	}

	// Grant privileges
	_, err = db.Exec(fmt.Sprintf("GRANT ALL PRIVILEGES ON `%s`.* TO '%s'@'localhost'", fullDbName, fullDbUser))
	if err != nil {
		return fmt.Errorf("failed to grant privileges: %w", err)
	}

	// Flush privileges
	_, err = db.Exec("FLUSH PRIVILEGES")
	if err != nil {
		return fmt.Errorf("failed to flush privileges: %w", err)
	}

	return nil
}

// ChangeDatabaseUserPassword changes a database user's password
func (dm *DatabaseManager) ChangeDatabaseUserPassword(username, dbUser, newPassword string) error {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	fullDbUser := fmt.Sprintf("%s_%s", username, dbUser)

	// Change password
	_, err = db.Exec(fmt.Sprintf("ALTER USER '%s'@'localhost' IDENTIFIED BY '%s'", fullDbUser, newPassword))
	if err != nil {
		return fmt.Errorf("failed to change password: %w", err)
	}

	// Flush privileges
	_, err = db.Exec("FLUSH PRIVILEGES")
	if err != nil {
		return fmt.Errorf("failed to flush privileges: %w", err)
	}

	return nil
}

// GetDatabasePrivileges returns privileges for a database user
func (dm *DatabaseManager) GetDatabasePrivileges(username, dbUser string) ([]string, error) {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return nil, fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	fullDbUser := fmt.Sprintf("%s_%s", username, dbUser)

	// Query privileges
	rows, err := db.Query("SHOW GRANTS FOR ?@'localhost'", fullDbUser)
	if err != nil {
		return nil, fmt.Errorf("failed to query privileges: %w", err)
	}
	defer rows.Close()

	var privileges []string
	for rows.Next() {
		var grant string
		if err := rows.Scan(&grant); err != nil {
			continue
		}
		privileges = append(privileges, grant)
	}

	return privileges, nil
}

// GetDatabaseSize returns the size of a database in bytes
func (dm *DatabaseManager) getDatabaseSize(dbName string) (int64, error) {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return 0, fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	var size int64
	query := `
		SELECT ROUND(SUM(data_length + index_length), 1) AS 'size' 
		FROM information_schema.tables 
		WHERE table_schema = ?
	`
	
	err = db.QueryRow(query, dbName).Scan(&size)
	if err != nil {
		return 0, fmt.Errorf("failed to get database size: %w", err)
	}

	return size, nil
}

// getDatabaseUsers returns all users for a database
func (dm *DatabaseManager) getDatabaseUsers(dbName string) ([]string, error) {
	// Connect to MySQL as root
	db, err := dm.connectAsRoot()
	if err != nil {
		return nil, fmt.Errorf("failed to connect to MySQL: %w", err)
	}
	defer db.Close()

	query := `
		SELECT DISTINCT grantee 
		FROM information_schema.schema_privileges 
		WHERE table_schema = ?
	`
	
	rows, err := db.Query(query, dbName)
	if err != nil {
		return nil, fmt.Errorf("failed to query database users: %w", err)
	}
	defer rows.Close()

	var users []string
	for rows.Next() {
		var grantee string
		if err := rows.Scan(&grantee); err != nil {
			continue
		}
		
		// Extract username from grantee format 'username'@'localhost'
		if strings.Contains(grantee, "@") {
			parts := strings.Split(grantee, "@")
			username := strings.Trim(parts[0], "'\"")
			users = append(users, username)
		}
	}

	return users, nil
}

// connectAsRoot connects to MySQL as root user
func (dm *DatabaseManager) connectAsRoot() (*sql.DB, error) {
	dsn := fmt.Sprintf("root:%s@tcp(localhost:3306)/", dm.mysqlRootPassword)
	return sql.Open("mysql", dsn)
}

// BackupDatabase creates a backup of a database
func (dm *DatabaseManager) BackupDatabase(username, dbName, backupPath string) error {
	_ = fmt.Sprintf("%s_%s", username, dbName)
	
	// This would typically use mysqldump command
	// For now, return a placeholder implementation
	return fmt.Errorf("database backup not implemented")
}

// RestoreDatabase restores a database from backup
func (dm *DatabaseManager) RestoreDatabase(username, dbName, backupPath string) error {
	_ = fmt.Sprintf("%s_%s", username, dbName)
	
	// This would typically use mysql command to restore from SQL dump
	// For now, return a placeholder implementation
	return fmt.Errorf("database restore not implemented")
}

// GetDatabaseStatistics returns database statistics
func (dm *DatabaseManager) GetDatabaseStatistics(username string) (map[string]interface{}, error) {
	databases, err := dm.ListDatabases(username)
	if err != nil {
		return nil, err
	}

	var totalSize int64
	for _, db := range databases {
		totalSize += db.Size
	}

	stats := map[string]interface{}{
		"total_databases": len(databases),
		"total_size":      totalSize,
		"databases":       databases,
	}

	return stats, nil
}