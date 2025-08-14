#!/bin/bash

# Master test runner for Admini API Testing
# This script starts the server, runs all tests, and generates a comprehensive report

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Directories
BACKEND_DIR="/home/runner/work/Admini/Admini/backend"
TEST_DIR="/home/runner/work/Admini/Admini/test"
RESULTS_DIR="$TEST_DIR/results"
SCREENSHOTS_DIR="$TEST_DIR/screenshots"

# Log function
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

# Success log
success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

# Error log
error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

# Warning log
warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Start server
start_server() {
    log "Starting Admini server..."
    cd "$BACKEND_DIR"
    
    # Kill any existing server
    pkill -f "./admini" || true
    sleep 2
    
    # Start server in background
    ./admini server --port 2222 > "$RESULTS_DIR/server.log" 2>&1 &
    SERVER_PID=$!
    
    log "Server started with PID: $SERVER_PID"
    
    # Wait for server to be ready
    for i in {1..30}; do
        if curl -s "http://localhost:2222" >/dev/null 2>&1; then
            success "Server is ready!"
            return 0
        fi
        sleep 1
    done
    
    error "Server failed to start within 30 seconds"
    return 1
}

# Stop server
stop_server() {
    if [ ! -z "$SERVER_PID" ]; then
        log "Stopping server (PID: $SERVER_PID)..."
        kill $SERVER_PID 2>/dev/null || true
        wait $SERVER_PID 2>/dev/null || true
    fi
    
    # Also kill any remaining admini processes
    pkill -f "./admini" || true
}

# Cleanup on exit
cleanup() {
    log "Cleaning up..."
    stop_server
}
trap cleanup EXIT

# Generate HTML report
generate_html_report() {
    local html_file="$RESULTS_DIR/test_report.html"
    
    log "Generating HTML test report..."
    
    cat > "$html_file" << 'EOF'
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admini API Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: #333; }
        h1 { text-align: center; color: #0066cc; border-bottom: 3px solid #0066cc; padding-bottom: 10px; }
        .summary { background: #e8f4fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .test-section { margin: 20px 0; }
        .test-result { margin: 10px 0; padding: 10px; border-radius: 5px; }
        .pass { background: #d4edda; border-left: 4px solid #28a745; }
        .fail { background: #f8d7da; border-left: 4px solid #dc3545; }
        .endpoint { font-family: monospace; background: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
        .status-code { font-weight: bold; }
        .response { background: #f8f9fa; padding: 10px; border-radius: 5px; margin-top: 10px; max-height: 200px; overflow-y: auto; }
        pre { margin: 0; white-space: pre-wrap; word-wrap: break-word; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-card { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 2em; font-weight: bold; margin-bottom: 5px; }
        .screenshots { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin: 20px 0; }
        .screenshot { border: 1px solid #ddd; border-radius: 8px; overflow: hidden; }
        .screenshot img { width: 100%; height: auto; }
        .screenshot-caption { padding: 10px; background: #f8f9fa; font-weight: bold; text-align: center; }
        .toc { background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0; }
        .toc ul { list-style-type: none; padding-left: 0; }
        .toc li { padding: 5px 0; }
        .toc a { text-decoration: none; color: #0066cc; }
        .toc a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <div class="container">
        <h1>🚀 Admini DirectAdmin Recreation - API Test Report</h1>
        
        <div class="summary">
            <h2>📊 Test Summary</h2>
            <div class="stats">
                <div class="stat-card">
                    <div class="stat-number" id="total-tests">0</div>
                    <div>Total Tests</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="passed-tests">0</div>
                    <div>Passed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="failed-tests">0</div>
                    <div>Failed</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number" id="success-rate">0%</div>
                    <div>Success Rate</div>
                </div>
            </div>
        </div>

        <div class="toc">
            <h3>📋 Table of Contents</h3>
            <ul>
                <li><a href="#basic-endpoints">Basic Endpoints</a></li>
                <li><a href="#admin-apis">Admin APIs</a></li>
                <li><a href="#user-apis">User APIs</a></li>
                <li><a href="#modern-apis">Modern REST APIs</a></li>
                <li><a href="#ajax-endpoints">AJAX Endpoints</a></li>
                <li><a href="#cpanel-compatibility">cPanel Compatibility</a></li>
                <li><a href="#screenshots">Screenshots</a></li>
                <li><a href="#detailed-results">Detailed Results</a></li>
            </ul>
        </div>
EOF

    # Add test results dynamically
    echo "        <div id=\"test-results\">" >> "$html_file"
    
    # Process test results files
    local total=0
    local passed=0
    local failed=0
    
    # Count results
    for result_file in "$RESULTS_DIR"/*_result.txt; do
        if [ -f "$result_file" ]; then
            ((total++))
            if grep -q "PASS" "$result_file"; then
                ((passed++))
            else
                ((failed++))
            fi
        fi
    done
    
    # Update HTML with actual numbers
    sed -i "s/id=\"total-tests\">0/id=\"total-tests\">$total/" "$html_file"
    sed -i "s/id=\"passed-tests\">0/id=\"passed-tests\">$passed/" "$html_file"
    sed -i "s/id=\"failed-tests\">0/id=\"failed-tests\">$failed/" "$html_file"
    
    if [ $total -gt 0 ]; then
        local success_rate=$((passed * 100 / total))
        sed -i "s/id=\"success-rate\">0%/id=\"success-rate\">${success_rate}%/" "$html_file"
    fi
    
    # Add detailed results section
    cat >> "$html_file" << 'EOF'
            <h2 id="detailed-results">📝 Detailed Test Results</h2>
            <div class="test-section">
EOF
    
    # Add individual test results
    for result_file in "$RESULTS_DIR"/*_result.txt; do
        if [ -f "$result_file" ]; then
            local test_name=$(basename "$result_file" "_result.txt")
            local status_file="$RESULTS_DIR/${test_name}_status.txt"
            local response_file="$RESULTS_DIR/${test_name}_response.json"
            
            local result=$(cat "$result_file")
            local status=""
            local response=""
            
            if [ -f "$status_file" ]; then
                status=$(cat "$status_file")
            fi
            
            if [ -f "$response_file" ]; then
                response=$(cat "$response_file" | head -c 1000)
            fi
            
            local css_class="fail"
            if [[ "$result" == *"PASS"* ]]; then
                css_class="pass"
            fi
            
            cat >> "$html_file" << EOF
                <div class="test-result $css_class">
                    <h4>$test_name</h4>
                    <p><span class="endpoint">HTTP Status: $status</span></p>
                    <p><strong>Result:</strong> $result</p>
                    <div class="response">
                        <strong>Response:</strong>
                        <pre>$response</pre>
                    </div>
                </div>
EOF
        fi
    done
    
    # Close HTML
    cat >> "$html_file" << 'EOF'
            </div>
        </div>
    </div>
</body>
</html>
EOF
    
    success "HTML report generated: $html_file"
}

# Main execution
main() {
    log "=== Admini API Test Suite ==="
    log "Starting comprehensive testing..."
    
    # Create results directory
    mkdir -p "$RESULTS_DIR" "$SCREENSHOTS_DIR"
    
    # Start server
    if ! start_server; then
        error "Failed to start server"
        exit 1
    fi
    
    # Run basic API tests
    log "Running basic API tests..."
    "$TEST_DIR/test_all_apis.sh"
    
    # Run authenticated API tests
    log "Running authenticated API tests..."
    "$TEST_DIR/test_authenticated_apis.sh"
    
    # Generate comprehensive report
    generate_html_report
    
    # Display final summary
    echo ""
    log "=== TEST SUITE COMPLETE ==="
    success "All tests have been executed!"
    success "Results saved in: $RESULTS_DIR"
    success "HTML report: $RESULTS_DIR/test_report.html"
    
    # Show quick stats
    local total=$(find "$RESULTS_DIR" -name "*_result.txt" | wc -l)
    local passed=$(grep -l "PASS" "$RESULTS_DIR"/*_result.txt 2>/dev/null | wc -l)
    local failed=$((total - passed))
    
    log "Quick Stats:"
    log "  Total Tests: $total"
    log "  Passed: $passed"
    log "  Failed: $failed"
    
    if [ $failed -eq 0 ]; then
        success "🎉 All tests passed!"
    else
        warning "⚠️  Some tests failed. Check the detailed report."
    fi
}

# Run main function
main "$@"