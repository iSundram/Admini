#!/bin/bash

# Enterprise Worker Management Script
# Manages background workers for Admini Enterprise

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_ROOT="$(dirname "$SCRIPT_DIR")"
WORKERS_DIR="$PROJECT_ROOT/src/workers"
LOGS_DIR="/var/log/admini/workers"
PIDS_DIR="/var/run/admini"

# Ensure directories exist
mkdir -p "$LOGS_DIR"
mkdir -p "$PIDS_DIR"

# Configuration
MAX_WORKERS=10
WORKER_TIMEOUT=300
PHP_BINARY=$(which php)

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

log_error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Check if a worker is running
is_worker_running() {
    local worker_name=$1
    local pid_file="$PIDS_DIR/${worker_name}.pid"
    
    if [[ -f "$pid_file" ]]; then
        local pid=$(cat "$pid_file")
        if kill -0 "$pid" 2>/dev/null; then
            return 0
        else
            rm -f "$pid_file"
            return 1
        fi
    fi
    return 1
}

# Start a worker
start_worker() {
    local worker_name=$1
    local worker_script=$2
    
    if is_worker_running "$worker_name"; then
        log_warning "Worker $worker_name is already running"
        return 1
    fi
    
    log "Starting worker: $worker_name"
    
    nohup "$PHP_BINARY" "$worker_script" > "$LOGS_DIR/${worker_name}.log" 2>&1 &
    local pid=$!
    
    echo "$pid" > "$PIDS_DIR/${worker_name}.pid"
    
    # Give it a moment to start
    sleep 2
    
    if is_worker_running "$worker_name"; then
        log_success "Worker $worker_name started successfully (PID: $pid)"
        return 0
    else
        log_error "Failed to start worker $worker_name"
        return 1
    fi
}

# Stop a worker
stop_worker() {
    local worker_name=$1
    local pid_file="$PIDS_DIR/${worker_name}.pid"
    
    if ! is_worker_running "$worker_name"; then
        log_warning "Worker $worker_name is not running"
        return 1
    fi
    
    log "Stopping worker: $worker_name"
    
    local pid=$(cat "$pid_file")
    
    # Try graceful shutdown first
    kill -TERM "$pid" 2>/dev/null || true
    
    # Wait up to 10 seconds for graceful shutdown
    local count=0
    while kill -0 "$pid" 2>/dev/null && [[ $count -lt 10 ]]; do
        sleep 1
        ((count++))
    done
    
    # Force kill if still running
    if kill -0 "$pid" 2>/dev/null; then
        log_warning "Force killing worker $worker_name"
        kill -KILL "$pid" 2>/dev/null || true
    fi
    
    rm -f "$pid_file"
    log_success "Worker $worker_name stopped"
}

# Restart a worker
restart_worker() {
    local worker_name=$1
    local worker_script=$2
    
    stop_worker "$worker_name"
    sleep 2
    start_worker "$worker_name" "$worker_script"
}

# Get worker status
get_worker_status() {
    local worker_name=$1
    
    if is_worker_running "$worker_name"; then
        local pid=$(cat "$PIDS_DIR/${worker_name}.pid")
        local memory=$(ps -o rss= -p "$pid" 2>/dev/null | tr -d ' ')
        local cpu=$(ps -o %cpu= -p "$pid" 2>/dev/null | tr -d ' ')
        local uptime=$(ps -o etime= -p "$pid" 2>/dev/null | tr -d ' ')
        
        echo "RUNNING (PID: $pid, Memory: ${memory}KB, CPU: ${cpu}%, Uptime: $uptime)"
    else
        echo "STOPPED"
    fi
}

# Start all workers
start_all() {
    log "Starting all enterprise workers..."
    
    # Worker Manager
    start_worker "worker_manager" "$WORKERS_DIR/worker_manager.php"
    
    # Monitoring System
    start_worker "monitoring" "$WORKERS_DIR/monitoring_worker.php"
    
    # Security Scanner
    start_worker "security_scanner" "$WORKERS_DIR/security_worker.php"
    
    # Analytics Processor
    start_worker "analytics" "$WORKERS_DIR/analytics_worker.php"
    
    # Integration Sync
    start_worker "integration_sync" "$WORKERS_DIR/integration_worker.php"
    
    # Workflow Engine
    start_worker "workflow_engine" "$WORKERS_DIR/workflow_worker.php"
    
    # Event Stream Processor
    start_worker "event_processor" "$WORKERS_DIR/event_worker.php"
    
    # Notification Sender
    start_worker "notifications" "$WORKERS_DIR/notification_worker.php"
    
    log_success "All workers started"
}

# Stop all workers
stop_all() {
    log "Stopping all workers..."
    
    for pid_file in "$PIDS_DIR"/*.pid; do
        if [[ -f "$pid_file" ]]; then
            local worker_name=$(basename "$pid_file" .pid)
            stop_worker "$worker_name"
        fi
    done
    
    log_success "All workers stopped"
}

# Show status of all workers
status() {
    echo "=== Admini Enterprise Workers Status ==="
    echo ""
    
    local workers=(
        "worker_manager"
        "monitoring"
        "security_scanner"
        "analytics"
        "integration_sync"
        "workflow_engine"
        "event_processor"
        "notifications"
    )
    
    for worker in "${workers[@]}"; do
        local status=$(get_worker_status "$worker")
        printf "%-20s: %s\n" "$worker" "$status"
    done
}

# Monitor workers and restart if needed
monitor() {
    log "Starting worker monitoring..."
    
    while true; do
        local workers=(
            "worker_manager:$WORKERS_DIR/worker_manager.php"
            "monitoring:$WORKERS_DIR/monitoring_worker.php"
            "security_scanner:$WORKERS_DIR/security_worker.php"
            "analytics:$WORKERS_DIR/analytics_worker.php"
            "integration_sync:$WORKERS_DIR/integration_worker.php"
            "workflow_engine:$WORKERS_DIR/workflow_worker.php"
            "event_processor:$WORKERS_DIR/event_worker.php"
            "notifications:$WORKERS_DIR/notification_worker.php"
        )
        
        for worker_info in "${workers[@]}"; do
            IFS=':' read -r worker_name worker_script <<< "$worker_info"
            
            if ! is_worker_running "$worker_name"; then
                log_warning "Worker $worker_name is down, restarting..."
                start_worker "$worker_name" "$worker_script"
            fi
        done
        
        sleep 30
    done
}

# Show logs for a worker
logs() {
    local worker_name=$1
    local lines=${2:-50}
    
    if [[ -z "$worker_name" ]]; then
        log_error "Please specify a worker name"
        exit 1
    fi
    
    local log_file="$LOGS_DIR/${worker_name}.log"
    
    if [[ ! -f "$log_file" ]]; then
        log_error "Log file for worker $worker_name not found"
        exit 1
    fi
    
    tail -n "$lines" "$log_file"
}

# Follow logs for a worker
follow_logs() {
    local worker_name=$1
    
    if [[ -z "$worker_name" ]]; then
        log_error "Please specify a worker name"
        exit 1
    fi
    
    local log_file="$LOGS_DIR/${worker_name}.log"
    
    if [[ ! -f "$log_file" ]]; then
        log_error "Log file for worker $worker_name not found"
        exit 1
    fi
    
    tail -f "$log_file"
}

# Cleanup old log files
cleanup_logs() {
    local days=${1:-7}
    
    log "Cleaning up log files older than $days days..."
    
    find "$LOGS_DIR" -name "*.log" -mtime +"$days" -delete
    
    log_success "Log cleanup completed"
}

# Performance stats
performance() {
    echo "=== Worker Performance Statistics ==="
    echo ""
    
    for pid_file in "$PIDS_DIR"/*.pid; do
        if [[ -f "$pid_file" ]]; then
            local worker_name=$(basename "$pid_file" .pid)
            local pid=$(cat "$pid_file")
            
            if kill -0 "$pid" 2>/dev/null; then
                echo "Worker: $worker_name"
                echo "  PID: $pid"
                echo "  CPU: $(ps -o %cpu= -p "$pid" 2>/dev/null | tr -d ' ')%"
                echo "  Memory: $(ps -o rss= -p "$pid" 2>/dev/null | tr -d ' ')KB"
                echo "  Uptime: $(ps -o etime= -p "$pid" 2>/dev/null | tr -d ' ')"
                echo "  Threads: $(ps -o nlwp= -p "$pid" 2>/dev/null | tr -d ' ')"
                echo ""
            fi
        fi
    done
}

# Main command handling
case "$1" in
    start)
        if [[ -n "$2" ]]; then
            # Start specific worker
            case "$2" in
                worker_manager) start_worker "$2" "$WORKERS_DIR/worker_manager.php" ;;
                monitoring) start_worker "$2" "$WORKERS_DIR/monitoring_worker.php" ;;
                security_scanner) start_worker "$2" "$WORKERS_DIR/security_worker.php" ;;
                analytics) start_worker "$2" "$WORKERS_DIR/analytics_worker.php" ;;
                integration_sync) start_worker "$2" "$WORKERS_DIR/integration_worker.php" ;;
                workflow_engine) start_worker "$2" "$WORKERS_DIR/workflow_worker.php" ;;
                event_processor) start_worker "$2" "$WORKERS_DIR/event_worker.php" ;;
                notifications) start_worker "$2" "$WORKERS_DIR/notification_worker.php" ;;
                *) log_error "Unknown worker: $2" ;;
            esac
        else
            start_all
        fi
        ;;
    stop)
        if [[ -n "$2" ]]; then
            stop_worker "$2"
        else
            stop_all
        fi
        ;;
    restart)
        if [[ -n "$2" ]]; then
            case "$2" in
                worker_manager) restart_worker "$2" "$WORKERS_DIR/worker_manager.php" ;;
                monitoring) restart_worker "$2" "$WORKERS_DIR/monitoring_worker.php" ;;
                security_scanner) restart_worker "$2" "$WORKERS_DIR/security_worker.php" ;;
                analytics) restart_worker "$2" "$WORKERS_DIR/analytics_worker.php" ;;
                integration_sync) restart_worker "$2" "$WORKERS_DIR/integration_worker.php" ;;
                workflow_engine) restart_worker "$2" "$WORKERS_DIR/workflow_worker.php" ;;
                event_processor) restart_worker "$2" "$WORKERS_DIR/event_worker.php" ;;
                notifications) restart_worker "$2" "$WORKERS_DIR/notification_worker.php" ;;
                *) log_error "Unknown worker: $2" ;;
            esac
        else
            stop_all
            sleep 2
            start_all
        fi
        ;;
    status)
        status
        ;;
    monitor)
        monitor
        ;;
    logs)
        logs "$2" "$3"
        ;;
    follow)
        follow_logs "$2"
        ;;
    cleanup)
        cleanup_logs "$2"
        ;;
    performance)
        performance
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|monitor|logs|follow|cleanup|performance} [worker_name] [options]"
        echo ""
        echo "Commands:"
        echo "  start [worker_name]    Start all workers or specific worker"
        echo "  stop [worker_name]     Stop all workers or specific worker"
        echo "  restart [worker_name]  Restart all workers or specific worker"
        echo "  status                 Show status of all workers"
        echo "  monitor                Monitor workers and restart if needed"
        echo "  logs worker_name [n]   Show last n lines of worker logs (default: 50)"
        echo "  follow worker_name     Follow worker logs in real-time"
        echo "  cleanup [days]         Clean up log files older than n days (default: 7)"
        echo "  performance            Show worker performance statistics"
        echo ""
        echo "Available workers:"
        echo "  worker_manager, monitoring, security_scanner, analytics,"
        echo "  integration_sync, workflow_engine, event_processor, notifications"
        exit 1
        ;;
esac