package filemanager

import (
	"fmt"
	"io"
	"mime/multipart"
	"os"
	"path/filepath"
	"strings"
	"syscall"

	"admini/pkg/models"
)

// FileManager handles all file management operations
type FileManager struct {
	basePath string
	username string
}

// NewFileManager creates a new file manager instance
func NewFileManager(basePath, username string) *FileManager {
	return &FileManager{
		basePath: basePath,
		username: username,
	}
}

// ListDirectory lists files and directories in the given path
func (fm *FileManager) ListDirectory(path string) ([]models.FileManagerItem, error) {
	fullPath := fm.getFullPath(path)
	
	// Security check - ensure path is within allowed directory
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return nil, fmt.Errorf("access denied: path outside allowed directory")
	}

	entries, err := os.ReadDir(fullPath)
	if err != nil {
		return nil, fmt.Errorf("failed to read directory: %w", err)
	}

	var items []models.FileManagerItem
	for _, entry := range entries {
		info, err := entry.Info()
		if err != nil {
			continue
		}

		item := models.FileManagerItem{
			Name:        entry.Name(),
			Path:        filepath.Join(path, entry.Name()),
			Type:        fm.getFileType(entry),
			Size:        info.Size(),
			Permissions: fm.getPermissions(info),
			Owner:       fm.getOwner(info),
			Group:       fm.getGroup(info),
			Modified:    info.ModTime(),
			IsDirectory: entry.IsDir(),
			IsReadable:  fm.isReadable(fullPath, entry.Name()),
			IsWritable:  fm.isWritable(fullPath, entry.Name()),
			IsExecutable: fm.isExecutable(info),
		}

		items = append(items, item)
	}

	return items, nil
}

// CreateDirectory creates a new directory
func (fm *FileManager) CreateDirectory(path, name string) error {
	fullPath := fm.getFullPath(filepath.Join(path, name))
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.MkdirAll(fullPath, 0755)
}

// CreateFile creates a new file
func (fm *FileManager) CreateFile(path, name, content string) error {
	fullPath := fm.getFullPath(filepath.Join(path, name))
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.WriteFile(fullPath, []byte(content), 0644)
}

// ReadFile reads the content of a file
func (fm *FileManager) ReadFile(path string) (string, error) {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return "", fmt.Errorf("access denied: path outside allowed directory")
	}

	data, err := os.ReadFile(fullPath)
	if err != nil {
		return "", fmt.Errorf("failed to read file: %w", err)
	}

	return string(data), nil
}

// WriteFile writes content to a file
func (fm *FileManager) WriteFile(path, content string) error {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.WriteFile(fullPath, []byte(content), 0644)
}

// DeleteItem deletes a file or directory
func (fm *FileManager) DeleteItem(path string) error {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.RemoveAll(fullPath)
}

// RenameItem renames a file or directory
func (fm *FileManager) RenameItem(oldPath, newName string) error {
	oldFullPath := fm.getFullPath(oldPath)
	newFullPath := fm.getFullPath(filepath.Join(filepath.Dir(oldPath), newName))
	
	if !strings.HasPrefix(oldFullPath, fm.basePath) || !strings.HasPrefix(newFullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.Rename(oldFullPath, newFullPath)
}

// CopyItem copies a file or directory
func (fm *FileManager) CopyItem(srcPath, destPath string) error {
	srcFullPath := fm.getFullPath(srcPath)
	destFullPath := fm.getFullPath(destPath)
	
	if !strings.HasPrefix(srcFullPath, fm.basePath) || !strings.HasPrefix(destFullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return fm.copyRecursive(srcFullPath, destFullPath)
}

// MoveItem moves a file or directory
func (fm *FileManager) MoveItem(srcPath, destPath string) error {
	srcFullPath := fm.getFullPath(srcPath)
	destFullPath := fm.getFullPath(destPath)
	
	if !strings.HasPrefix(srcFullPath, fm.basePath) || !strings.HasPrefix(destFullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.Rename(srcFullPath, destFullPath)
}

// UploadFile handles file upload
func (fm *FileManager) UploadFile(path string, file multipart.File, header *multipart.FileHeader) error {
	fullPath := fm.getFullPath(filepath.Join(path, header.Filename))
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	// Create the destination file
	dst, err := os.Create(fullPath)
	if err != nil {
		return fmt.Errorf("failed to create destination file: %w", err)
	}
	defer dst.Close()

	// Copy the uploaded file to the destination
	_, err = io.Copy(dst, file)
	if err != nil {
		return fmt.Errorf("failed to copy file: %w", err)
	}

	return nil
}

// SetPermissions sets file permissions
func (fm *FileManager) SetPermissions(path string, mode os.FileMode) error {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return fmt.Errorf("access denied: path outside allowed directory")
	}

	return os.Chmod(fullPath, mode)
}

// GetFileInfo returns detailed information about a file
func (fm *FileManager) GetFileInfo(path string) (*models.FileManagerItem, error) {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return nil, fmt.Errorf("access denied: path outside allowed directory")
	}

	info, err := os.Stat(fullPath)
	if err != nil {
		return nil, fmt.Errorf("failed to get file info: %w", err)
	}

	return &models.FileManagerItem{
		Name:        filepath.Base(path),
		Path:        path,
		Type:        fm.getFileTypeFromInfo(info),
		Size:        info.Size(),
		Permissions: fm.getPermissions(info),
		Owner:       fm.getOwner(info),
		Group:       fm.getGroup(info),
		Modified:    info.ModTime(),
		IsDirectory: info.IsDir(),
		IsReadable:  fm.isReadable(filepath.Dir(fullPath), filepath.Base(fullPath)),
		IsWritable:  fm.isWritable(filepath.Dir(fullPath), filepath.Base(fullPath)),
		IsExecutable: fm.isExecutable(info),
	}, nil
}

// SearchFiles searches for files matching a pattern
func (fm *FileManager) SearchFiles(path, pattern string) ([]models.FileManagerItem, error) {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return nil, fmt.Errorf("access denied: path outside allowed directory")
	}

	var matches []models.FileManagerItem
	
	err := filepath.Walk(fullPath, func(walkPath string, info os.FileInfo, err error) error {
		if err != nil {
			return nil // Skip errors and continue
		}

		if matched, _ := filepath.Match(pattern, info.Name()); matched {
			relativePath, _ := filepath.Rel(fm.basePath, walkPath)
			
			item := models.FileManagerItem{
				Name:        info.Name(),
				Path:        relativePath,
				Type:        fm.getFileTypeFromInfo(info),
				Size:        info.Size(),
				Permissions: fm.getPermissions(info),
				Owner:       fm.getOwner(info),
				Group:       fm.getGroup(info),
				Modified:    info.ModTime(),
				IsDirectory: info.IsDir(),
				IsReadable:  fm.isReadable(filepath.Dir(walkPath), info.Name()),
				IsWritable:  fm.isWritable(filepath.Dir(walkPath), info.Name()),
				IsExecutable: fm.isExecutable(info),
			}
			
			matches = append(matches, item)
		}

		return nil
	})

	return matches, err
}

// Helper methods

func (fm *FileManager) getFullPath(path string) string {
	// Clean and join the path
	cleanPath := filepath.Clean(path)
	if strings.HasPrefix(cleanPath, "/") {
		cleanPath = cleanPath[1:]
	}
	return filepath.Join(fm.basePath, cleanPath)
}

func (fm *FileManager) getFileType(entry os.DirEntry) string {
	if entry.IsDir() {
		return "directory"
	}
	return "file"
}

func (fm *FileManager) getFileTypeFromInfo(info os.FileInfo) string {
	if info.IsDir() {
		return "directory"
	}
	return "file"
}

func (fm *FileManager) getPermissions(info os.FileInfo) string {
	return info.Mode().String()
}

func (fm *FileManager) getOwner(info os.FileInfo) string {
	if stat, ok := info.Sys().(*syscall.Stat_t); ok {
		return fmt.Sprintf("%d", stat.Uid)
	}
	return "unknown"
}

func (fm *FileManager) getGroup(info os.FileInfo) string {
	if stat, ok := info.Sys().(*syscall.Stat_t); ok {
		return fmt.Sprintf("%d", stat.Gid)
	}
	return "unknown"
}

func (fm *FileManager) isReadable(dir, name string) bool {
	path := filepath.Join(dir, name)
	_, err := os.Open(path)
	return err == nil
}

func (fm *FileManager) isWritable(dir, name string) bool {
	path := filepath.Join(dir, name)
	file, err := os.OpenFile(path, os.O_WRONLY, 0)
	if err == nil {
		file.Close()
		return true
	}
	return false
}

func (fm *FileManager) isExecutable(info os.FileInfo) bool {
	return info.Mode()&0111 != 0
}

func (fm *FileManager) copyRecursive(src, dst string) error {
	srcInfo, err := os.Stat(src)
	if err != nil {
		return err
	}

	if srcInfo.IsDir() {
		return fm.copyDirectory(src, dst)
	}
	return fm.copyFile(src, dst)
}

func (fm *FileManager) copyFile(src, dst string) error {
	srcFile, err := os.Open(src)
	if err != nil {
		return err
	}
	defer srcFile.Close()

	dstFile, err := os.Create(dst)
	if err != nil {
		return err
	}
	defer dstFile.Close()

	_, err = io.Copy(dstFile, srcFile)
	return err
}

func (fm *FileManager) copyDirectory(src, dst string) error {
	srcInfo, err := os.Stat(src)
	if err != nil {
		return err
	}

	if err := os.MkdirAll(dst, srcInfo.Mode()); err != nil {
		return err
	}

	entries, err := os.ReadDir(src)
	if err != nil {
		return err
	}

	for _, entry := range entries {
		srcPath := filepath.Join(src, entry.Name())
		dstPath := filepath.Join(dst, entry.Name())

		if err := fm.copyRecursive(srcPath, dstPath); err != nil {
			return err
		}
	}

	return nil
}

// GetDiskUsage calculates disk usage for a directory
func (fm *FileManager) GetDiskUsage(path string) (int64, error) {
	fullPath := fm.getFullPath(path)
	
	if !strings.HasPrefix(fullPath, fm.basePath) {
		return 0, fmt.Errorf("access denied: path outside allowed directory")
	}

	var size int64
	err := filepath.Walk(fullPath, func(_ string, info os.FileInfo, err error) error {
		if err != nil {
			return err
		}
		if !info.IsDir() {
			size += info.Size()
		}
		return nil
	})

	return size, err
}

// CompressDirectory creates a compressed archive of a directory
func (fm *FileManager) CompressDirectory(path, archiveName string) error {
	// Implementation would depend on the compression library used
	// This is a placeholder implementation
	return fmt.Errorf("compression not implemented")
}

// ExtractArchive extracts a compressed archive
func (fm *FileManager) ExtractArchive(archivePath, destPath string) error {
	// Implementation would depend on the compression library used
	// This is a placeholder implementation
	return fmt.Errorf("extraction not implemented")
}