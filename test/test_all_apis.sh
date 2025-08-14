#!/bin/bash

# Comprehensive API Test Suite for Admini DirectAdmin Recreation
# This script tests all available APIs and documents the results

BASE_URL="http://localhost:2222"
TEST_DIR="/home/runner/work/Admini/Admini/test"
RESULTS_DIR="$TEST_DIR/results"
API_DIR="$TEST_DIR/api"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test result tracking
TOTAL_TESTS=0
PASSED_TESTS=0
FAILED_TESTS=0

# Log function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Success log
success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    ((PASSED_TESTS++))
}

# Error log
error() {
    echo -e "${RED}[ERROR]${NC} $1"
    ((FAILED_TESTS++))
}

# Warning log
warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Test API endpoint
test_api() {
    local endpoint="$1"
    local method="${2:-GET}"
    local data="$3"
    local expected_status="${4:-200}"
    local description="$5"
    
    ((TOTAL_TESTS++))
    
    log "Testing: $description"
    log "Endpoint: $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        response=$(curl -s -w "\n%{http_code}" "$BASE_URL$endpoint" 2>/dev/null)
    elif [ "$method" = "POST" ]; then
        if [ -z "$data" ]; then
            response=$(curl -s -w "\n%{http_code}" -X POST "$BASE_URL$endpoint" 2>/dev/null)
        else
            response=$(curl -s -w "\n%{http_code}" -X POST -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint" 2>/dev/null)
        fi
    elif [ "$method" = "PUT" ]; then
        response=$(curl -s -w "\n%{http_code}" -X PUT -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint" 2>/dev/null)
    elif [ "$method" = "DELETE" ]; then
        response=$(curl -s -w "\n%{http_code}" -X DELETE "$BASE_URL$endpoint" 2>/dev/null)
    fi
    
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | head -n -1)
    
    # Save response to file
    test_name=$(echo "$endpoint" | sed 's/[^a-zA-Z0-9]/_/g' | sed 's/__/_/g' | sed 's/^_//' | sed 's/_$//')
    echo "$response_body" > "$RESULTS_DIR/${test_name}_response.json"
    echo "$http_code" > "$RESULTS_DIR/${test_name}_status.txt"
    
    if [ "$http_code" = "$expected_status" ]; then
        success "$description - HTTP $http_code"
        echo "✅ PASS" > "$RESULTS_DIR/${test_name}_result.txt"
    else
        error "$description - Expected HTTP $expected_status, got $http_code"
        echo "❌ FAIL - Expected HTTP $expected_status, got $http_code" > "$RESULTS_DIR/${test_name}_result.txt"
    fi
    
    echo "---" >> "$RESULTS_DIR/all_tests.log"
    echo "Test: $description" >> "$RESULTS_DIR/all_tests.log"
    echo "Endpoint: $method $endpoint" >> "$RESULTS_DIR/all_tests.log"
    echo "Expected Status: $expected_status" >> "$RESULTS_DIR/all_tests.log"
    echo "Actual Status: $http_code" >> "$RESULTS_DIR/all_tests.log"
    echo "Response: $response_body" >> "$RESULTS_DIR/all_tests.log"
    echo "" >> "$RESULTS_DIR/all_tests.log"
}

# Wait for server to be ready
wait_for_server() {
    log "Waiting for server to be ready..."
    for i in {1..30}; do
        if curl -s "$BASE_URL" >/dev/null 2>&1; then
            success "Server is ready!"
            return 0
        fi
        sleep 1
    done
    error "Server failed to start within 30 seconds"
    return 1
}

# Initialize test results
echo "Admini API Test Results - $(date)" > "$RESULTS_DIR/all_tests.log"
echo "======================================" >> "$RESULTS_DIR/all_tests.log"
echo "" >> "$RESULTS_DIR/all_tests.log"

log "Starting comprehensive API test suite for Admini..."

# Wait for server
if ! wait_for_server; then
    exit 1
fi

log "Testing main endpoints..."

# Basic endpoints
test_api "/" "GET" "" "200" "Main page / Login page"
test_api "/CMD_LOGIN" "GET" "" "200" "Login page"

log "Testing authentication endpoints..."

# Authentication tests
test_api "/CMD_API_LOGIN_KEY?key=test123" "GET" "" "200" "API Login Key endpoint"

log "Testing admin API endpoints..."

# Note: Most endpoints require authentication, but we'll test what we can
# Admin API endpoints (these will return 401 but we verify they exist)
test_api "/CMD_ADMIN/" "GET" "" "401" "Admin dashboard (auth required)"
test_api "/CMD_ADMIN/CMD_ADMIN_STATS" "GET" "" "401" "Admin stats (auth required)"
test_api "/CMD_ADMIN/CMD_API_SHOW_USERS" "GET" "" "401" "Show users API (auth required)"
test_api "/CMD_ADMIN/CMD_API_SHOW_DOMAINS" "GET" "" "401" "Show domains API (auth required)"
test_api "/CMD_ADMIN/CMD_API_SHOW_RESELLERS" "GET" "" "401" "Show resellers API (auth required)"

log "Testing user API endpoints..."

# User API endpoints
test_api "/CMD_USER/" "GET" "" "401" "User dashboard (auth required)"
test_api "/CMD_USER/CMD_DOMAIN" "GET" "" "401" "User domains (auth required)"
test_api "/CMD_USER/CMD_EMAIL" "GET" "" "401" "User email (auth required)"
test_api "/CMD_USER/CMD_DB" "GET" "" "401" "User databases (auth required)"
test_api "/CMD_USER/CMD_FILE_MANAGER" "GET" "" "401" "File manager (auth required)"

log "Testing reseller API endpoints..."

# Reseller API endpoints
test_api "/CMD_RESELLER/" "GET" "" "401" "Reseller dashboard (auth required)"
test_api "/CMD_RESELLER/CMD_RESELLER_STATS" "GET" "" "401" "Reseller stats (auth required)"

log "Testing modern API endpoints..."

# Modern API endpoints (these require API key authentication)
test_api "/api/users" "GET" "" "401" "API Users list (auth required)"
test_api "/api/domains" "GET" "" "401" "API Domains list (auth required)"
test_api "/api/email" "GET" "" "401" "API Email list (auth required)"
test_api "/api/databases" "GET" "" "401" "API Databases list (auth required)"
test_api "/api/stats" "GET" "" "401" "API System stats (auth required)"
test_api "/api/system" "GET" "" "401" "API System info (auth required)"
test_api "/api/config" "GET" "" "401" "API Configuration (auth required)"
test_api "/api/backups" "GET" "" "401" "API Backups list (auth required)"
test_api "/api/tasks" "GET" "" "401" "API Tasks list (auth required)"

log "Testing AJAX endpoints..."

# AJAX endpoints
test_api "/api/ajax/check-domain?domain=test.com" "GET" "" "401" "AJAX Domain check (auth required)"
test_api "/api/ajax/check-username?username=testuser" "GET" "" "401" "AJAX Username check (auth required)"
test_api "/api/ajax/check-password?password=testpass" "GET" "" "401" "AJAX Password check (auth required)"
test_api "/api/ajax/search?q=test" "GET" "" "401" "AJAX Search (auth required)"

log "Testing cPanel compatibility endpoints..."

# cPanel compatibility
test_api "/cpanel/" "GET" "" "401" "cPanel dashboard (auth required)"
test_api "/cpanel/mail" "GET" "" "401" "cPanel mail (auth required)"
test_api "/cpanel/files" "GET" "" "401" "cPanel files (auth required)"

log "Testing additional API endpoints..."

# Additional management endpoints
test_api "/api/plugins" "GET" "" "401" "API Plugins (auth required)"
test_api "/api/multi-server" "GET" "" "401" "API Multi-server (auth required)"
test_api "/api/brute-force" "GET" "" "401" "API Brute force monitoring (auth required)"
test_api "/api/comments" "GET" "" "401" "API Comments (auth required)"
test_api "/api/tickets" "GET" "" "401" "API Tickets (auth required)"
test_api "/api/perl-modules" "GET" "" "401" "API Perl modules (auth required)"
test_api "/api/custom-httpd" "GET" "" "401" "API Custom HTTPd (auth required)"
test_api "/api/admini-conf" "GET" "" "401" "API Admini config (auth required)"
test_api "/api/maintenance" "GET" "" "401" "API Maintenance (auth required)"

log "Testing static file serving..."

# Static files
test_api "/static/" "GET" "" "404" "Static files directory (not found expected)"

log "Test suite completed!"

# Generate summary
echo "" >> "$RESULTS_DIR/all_tests.log"
echo "TEST SUMMARY" >> "$RESULTS_DIR/all_tests.log"
echo "============" >> "$RESULTS_DIR/all_tests.log"
echo "Total Tests: $TOTAL_TESTS" >> "$RESULTS_DIR/all_tests.log"
echo "Passed: $PASSED_TESTS" >> "$RESULTS_DIR/all_tests.log"
echo "Failed: $FAILED_TESTS" >> "$RESULTS_DIR/all_tests.log"
echo "Success Rate: $(( PASSED_TESTS * 100 / TOTAL_TESTS ))%" >> "$RESULTS_DIR/all_tests.log"

# Display summary
echo ""
log "===== TEST SUMMARY ====="
log "Total Tests: $TOTAL_TESTS"
log "Passed: $PASSED_TESTS"
log "Failed: $FAILED_TESTS"
log "Success Rate: $(( PASSED_TESTS * 100 / TOTAL_TESTS ))%"

if [ $FAILED_TESTS -eq 0 ]; then
    success "All tests completed successfully!"
    exit 0
else
    warning "Some tests failed. Check logs in $RESULTS_DIR/"
    exit 1
fi