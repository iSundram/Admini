#!/bin/bash

# Authenticated API Test Suite for Admini
# This script tests APIs with authentication

BASE_URL="http://localhost:2222"
TEST_DIR="/home/runner/work/Admini/Admini/test"
RESULTS_DIR="$TEST_DIR/results"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Test result tracking
TOTAL_AUTH_TESTS=0
PASSED_AUTH_TESTS=0
FAILED_AUTH_TESTS=0

# Log function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Success log
success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
    ((PASSED_AUTH_TESTS++))
}

# Error log
error() {
    echo -e "${RED}[ERROR]${NC} $1"
    ((FAILED_AUTH_TESTS++))
}

# Test authenticated API endpoint
test_auth_api() {
    local endpoint="$1"
    local method="${2:-GET}"
    local data="$3"
    local expected_status="${4:-200}"
    local description="$5"
    local auth_header="$6"
    
    ((TOTAL_AUTH_TESTS++))
    
    log "Testing: $description"
    log "Endpoint: $method $endpoint"
    
    if [ "$method" = "GET" ]; then
        if [ -n "$auth_header" ]; then
            response=$(curl -s -w "\n%{http_code}" -H "$auth_header" "$BASE_URL$endpoint" 2>/dev/null)
        else
            response=$(curl -s -w "\n%{http_code}" -H "Cookie: session=authenticated" "$BASE_URL$endpoint" 2>/dev/null)
        fi
    elif [ "$method" = "POST" ]; then
        if [ -n "$auth_header" ]; then
            response=$(curl -s -w "\n%{http_code}" -X POST -H "$auth_header" -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint" 2>/dev/null)
        else
            response=$(curl -s -w "\n%{http_code}" -X POST -H "Cookie: session=authenticated" -H "Content-Type: application/json" -d "$data" "$BASE_URL$endpoint" 2>/dev/null)
        fi
    fi
    
    http_code=$(echo "$response" | tail -n1)
    response_body=$(echo "$response" | head -n -1)
    
    # Save response to file
    test_name=$(echo "$endpoint" | sed 's/[^a-zA-Z0-9]/_/g' | sed 's/__/_/g' | sed 's/^_//' | sed 's/_$//')
    echo "$response_body" > "$RESULTS_DIR/auth_${test_name}_response.json"
    echo "$http_code" > "$RESULTS_DIR/auth_${test_name}_status.txt"
    
    if [ "$http_code" = "$expected_status" ]; then
        success "$description - HTTP $http_code"
        echo "✅ PASS" > "$RESULTS_DIR/auth_${test_name}_result.txt"
    else
        error "$description - Expected HTTP $expected_status, got $http_code"
        echo "❌ FAIL - Expected HTTP $expected_status, got $http_code" > "$RESULTS_DIR/auth_${test_name}_result.txt"
    fi
    
    echo "---" >> "$RESULTS_DIR/auth_tests.log"
    echo "Test: $description" >> "$RESULTS_DIR/auth_tests.log"
    echo "Endpoint: $method $endpoint" >> "$RESULTS_DIR/auth_tests.log"
    echo "Expected Status: $expected_status" >> "$RESULTS_DIR/auth_tests.log"
    echo "Actual Status: $http_code" >> "$RESULTS_DIR/auth_tests.log"
    echo "Response: $response_body" >> "$RESULTS_DIR/auth_tests.log"
    echo "" >> "$RESULTS_DIR/auth_tests.log"
}

# Test login and get session
test_login() {
    log "Testing login functionality..."
    
    # Test login with correct credentials
    response=$(curl -s -w "\n%{http_code}" -X POST \
        -d "username=admin&password=password" \
        "$BASE_URL/CMD_LOGIN" 2>/dev/null)
    
    http_code=$(echo "$response" | tail -n1)
    
    if [ "$http_code" = "302" ] || [ "$http_code" = "200" ]; then
        success "Login test successful - HTTP $http_code"
        return 0
    else
        error "Login test failed - HTTP $http_code"
        return 1
    fi
}

# Initialize authenticated test results
echo "Admini Authenticated API Test Results - $(date)" > "$RESULTS_DIR/auth_tests.log"
echo "=================================================" >> "$RESULTS_DIR/auth_tests.log"
echo "" >> "$RESULTS_DIR/auth_tests.log"

log "Starting authenticated API test suite..."

# Test login first
test_login

log "Testing authenticated admin endpoints..."

# Test authenticated admin endpoints
test_auth_api "/CMD_ADMIN/" "GET" "" "200" "Authenticated Admin dashboard"
test_auth_api "/CMD_ADMIN/CMD_ADMIN_STATS" "GET" "" "200" "Authenticated Admin stats"
test_auth_api "/CMD_ADMIN/CMD_API_SHOW_USERS" "GET" "" "200" "Authenticated Show users"
test_auth_api "/CMD_ADMIN/CMD_API_SHOW_DOMAINS" "GET" "" "200" "Authenticated Show domains"

log "Testing authenticated user endpoints..."

# Test authenticated user endpoints
test_auth_api "/CMD_USER/" "GET" "" "200" "Authenticated User dashboard"
test_auth_api "/CMD_USER/CMD_DOMAIN" "GET" "" "200" "Authenticated User domains"
test_auth_api "/CMD_USER/CMD_EMAIL" "GET" "" "200" "Authenticated User email"
test_auth_api "/CMD_USER/CMD_DB" "GET" "" "200" "Authenticated User databases"

log "Testing API endpoints with API key..."

# Test with API key
test_auth_api "/api/users" "GET" "" "200" "API Users with key" "X-API-Key: test123"
test_auth_api "/api/domains" "GET" "" "200" "API Domains with key" "X-API-Key: test123"
test_auth_api "/api/stats" "GET" "" "200" "API Stats with key" "X-API-Key: test123"
test_auth_api "/api/config" "GET" "" "200" "API Config with key" "X-API-Key: test123"

log "Testing CRUD operations..."

# Test POST operations
test_auth_api "/api/users" "POST" '{"username":"testuser","email":"test@example.com"}' "200" "Create user via API" "X-API-Key: test123"
test_auth_api "/api/domains" "POST" '{"domain":"test.com","user":"testuser"}' "200" "Create domain via API" "X-API-Key: test123"

log "Authenticated test suite completed!"

# Generate summary
echo "" >> "$RESULTS_DIR/auth_tests.log"
echo "AUTHENTICATED TEST SUMMARY" >> "$RESULTS_DIR/auth_tests.log"
echo "=========================" >> "$RESULTS_DIR/auth_tests.log"
echo "Total Tests: $TOTAL_AUTH_TESTS" >> "$RESULTS_DIR/auth_tests.log"
echo "Passed: $PASSED_AUTH_TESTS" >> "$RESULTS_DIR/auth_tests.log"
echo "Failed: $FAILED_AUTH_TESTS" >> "$RESULTS_DIR/auth_tests.log"
echo "Success Rate: $(( PASSED_AUTH_TESTS * 100 / TOTAL_AUTH_TESTS ))%" >> "$RESULTS_DIR/auth_tests.log"

# Display summary
echo ""
log "===== AUTHENTICATED TEST SUMMARY ====="
log "Total Tests: $TOTAL_AUTH_TESTS"
log "Passed: $PASSED_AUTH_TESTS"
log "Failed: $FAILED_AUTH_TESTS"
log "Success Rate: $(( PASSED_AUTH_TESTS * 100 / TOTAL_AUTH_TESTS ))%"