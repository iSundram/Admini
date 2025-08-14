package taskqueue

import (
	"bufio"
	"fmt"
	"io/ioutil"
	"log"
	"os"
	"strings"
	"time"
)

// Task represents a task in the queue
type Task struct {
	Action string
	Value  string
	Time   time.Time
}

const (
	taskQueueFile = "/usr/local/admini/data/task.queue"
	taskQueueDir  = "/usr/local/admini/data"
)

// Run starts the task queue processor
func Run() error {
	log.Println("Starting DirectAdmin task queue processor...")
	
	// Create task queue directory if it doesn't exist
	if err := os.MkdirAll(taskQueueDir, 0755); err != nil {
		return fmt.Errorf("failed to create task queue directory: %v", err)
	}
	
	// Create task queue file if it doesn't exist
	if _, err := os.Stat(taskQueueFile); os.IsNotExist(err) {
		file, err := os.Create(taskQueueFile)
		if err != nil {
			return fmt.Errorf("failed to create task queue file: %v", err)
		}
		file.Close()
	}
	
	// Process tasks continuously
	for {
		tasks, err := readTasks()
		if err != nil {
			log.Printf("Error reading tasks: %v", err)
			time.Sleep(5 * time.Second)
			continue
		}
		
		if len(tasks) > 0 {
			log.Printf("Processing %d tasks...", len(tasks))
			
			for _, task := range tasks {
				if err := processTask(task); err != nil {
					log.Printf("Error processing task %s=%s: %v", task.Action, task.Value, err)
				} else {
					log.Printf("Completed task: %s=%s", task.Action, task.Value)
				}
			}
			
			// Clear processed tasks
			if err := clearTasks(); err != nil {
				log.Printf("Error clearing tasks: %v", err)
			}
		}
		
		// Wait before checking for new tasks
		time.Sleep(10 * time.Second)
	}
}

// AddTask adds a new task to the queue
func AddTask(action, value string) error {
	task := fmt.Sprintf("action=%s&value=%s\n", action, value)
	
	file, err := os.OpenFile(taskQueueFile, os.O_APPEND|os.O_WRONLY|os.O_CREATE, 0644)
	if err != nil {
		return fmt.Errorf("failed to open task queue file: %v", err)
	}
	defer file.Close()
	
	_, err = file.WriteString(task)
	if err != nil {
		return fmt.Errorf("failed to write task: %v", err)
	}
	
	log.Printf("Added task to queue: %s=%s", action, value)
	return nil
}

func readTasks() ([]Task, error) {
	file, err := os.Open(taskQueueFile)
	if err != nil {
		return nil, err
	}
	defer file.Close()
	
	var tasks []Task
	scanner := bufio.NewScanner(file)
	
	for scanner.Scan() {
		line := strings.TrimSpace(scanner.Text())
		if line == "" {
			continue
		}
		
		task := parseTaskLine(line)
		if task.Action != "" {
			tasks = append(tasks, task)
		}
	}
	
	return tasks, scanner.Err()
}

func parseTaskLine(line string) Task {
	task := Task{Time: time.Now()}
	
	// Parse action=value&param=value format
	pairs := strings.Split(line, "&")
	for _, pair := range pairs {
		parts := strings.SplitN(pair, "=", 2)
		if len(parts) != 2 {
			continue
		}
		
		key := strings.TrimSpace(parts[0])
		value := strings.TrimSpace(parts[1])
		
		switch key {
		case "action":
			task.Action = value
		case "value":
			task.Value = value
		}
	}
	
	return task
}

func processTask(task Task) error {
	switch task.Action {
	case "tally":
		return processTallyTask(task.Value)
	case "reset":
		return processResetTask(task.Value)
	case "backup":
		return processBackupTask(task.Value)
	case "restore":
		return processRestoreTask(task.Value)
	case "email":
		return processEmailTask(task.Value)
	case "suspend":
		return processSuspendTask(task.Value)
	case "unsuspend":
		return processUnsuspendTask(task.Value)
	case "ssl":
		return processSSLTask(task.Value)
	case "letsencrypt":
		return processLetsEncryptTask(task.Value)
	case "dns":
		return processDNSTask(task.Value)
	default:
		return fmt.Errorf("unknown task action: %s", task.Action)
	}
}

func processTallyTask(value string) error {
	log.Printf("Processing tally task for: %s", value)
	
	// Tally bandwidth and disk usage
	if value == "all" {
		// Process all users
		usersDir := "/usr/local/admini/data/users"
		entries, err := os.ReadDir(usersDir)
		if err != nil {
			return err
		}
		
		for _, entry := range entries {
			if entry.IsDir() {
				log.Printf("Tallying usage for user: %s", entry.Name())
				// In real implementation, calculate bandwidth and disk usage
			}
		}
	} else {
		// Process specific user
		log.Printf("Tallying usage for specific user: %s", value)
	}
	
	return nil
}

func processResetTask(value string) error {
	log.Printf("Processing reset task for: %s", value)
	
	// Reset bandwidth counters
	if value == "all" {
		// Reset all user bandwidth
		log.Println("Resetting bandwidth for all users")
	} else {
		// Reset specific user bandwidth
		log.Printf("Resetting bandwidth for user: %s", value)
	}
	
	return nil
}

func processBackupTask(value string) error {
	log.Printf("Processing backup task for: %s", value)
	
	// Create backup
	backupDir := "/usr/local/admini/data/admin/admin_backups"
	timestamp := time.Now().Format("20060102_150405")
	backupFile := fmt.Sprintf("%s/backup_%s_%s.tar.gz", backupDir, value, timestamp)
	
	log.Printf("Creating backup: %s", backupFile)
	
	return nil
}

func processRestoreTask(value string) error {
	log.Printf("Processing restore task for: %s", value)
	
	// Restore from backup
	log.Printf("Restoring from backup: %s", value)
	
	return nil
}

func processEmailTask(value string) error {
	log.Printf("Processing email task for: %s", value)
	
	// Email-related operations
	log.Printf("Processing email operations for: %s", value)
	
	return nil
}

func processSuspendTask(value string) error {
	log.Printf("Processing suspend task for: %s", value)
	
	// Suspend user or domain
	log.Printf("Suspending: %s", value)
	
	return nil
}

func processUnsuspendTask(value string) error {
	log.Printf("Processing unsuspend task for: %s", value)
	
	// Unsuspend user or domain
	log.Printf("Unsuspending: %s", value)
	
	return nil
}

func processSSLTask(value string) error {
	log.Printf("Processing SSL task for: %s", value)
	
	// SSL certificate operations
	log.Printf("Processing SSL for domain: %s", value)
	
	return nil
}

func processLetsEncryptTask(value string) error {
	log.Printf("Processing Let's Encrypt task for: %s", value)
	
	// Let's Encrypt certificate operations
	log.Printf("Processing Let's Encrypt for domain: %s", value)
	
	return nil
}

func processDNSTask(value string) error {
	log.Printf("Processing DNS task for: %s", value)
	
	// DNS operations
	log.Printf("Processing DNS for domain: %s", value)
	
	return nil
}

func clearTasks() error {
	// Clear the task queue file
	return ioutil.WriteFile(taskQueueFile, []byte(""), 0644)
}

// GetQueueStatus returns the current status of the task queue
func GetQueueStatus() (map[string]interface{}, error) {
	tasks, err := readTasks()
	if err != nil {
		return nil, err
	}
	
	status := map[string]interface{}{
		"queue_file":    taskQueueFile,
		"pending_tasks": len(tasks),
		"last_check":    time.Now().Format(time.RFC3339),
	}
	
	if len(tasks) > 0 {
		status["next_task"] = map[string]string{
			"action": tasks[0].Action,
			"value":  tasks[0].Value,
		}
	}
	
	return status, nil
}