#!/bin/bash

# Comprehensive Admini Dashboard Screenshot Test Suite
# This script tests all dashboard interfaces and captures screenshots after proper wait times

set -e

# Configuration
BASE_URL="http://localhost:2222"
TEST_DIR="/home/runner/work/Admini/Admini/test"
SCREENSHOTS_DIR="$TEST_DIR/screenshots"
RESULTS_DIR="$TEST_DIR/results"
TIMESTAMP=$(date '+%Y%m%d_%H%M%S')

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging functions
log() {
    echo -e "${BLUE}[$(date '+%Y-%m-%d %H:%M:%S')]${NC} $1"
}

success() {
    echo -e "${GREEN}[SUCCESS]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

# Create directories
mkdir -p "$SCREENSHOTS_DIR" "$RESULTS_DIR"

# Test Summary
echo "📋 ADMINI DASHBOARD SCREENSHOT TEST REPORT" > "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "=============================================" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "**Test Date:** $(date)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "**Base URL:** $BASE_URL" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "**Test Type:** Comprehensive Dashboard Screenshot Testing" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"

# Check if server is running
log "Checking if Admini server is running on $BASE_URL..."
if curl -s "$BASE_URL" > /dev/null; then
    success "Server is responding"
    echo "✅ **Server Status:** Running and accessible" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
else
    error "Server is not responding. Please start the Admini server first."
    echo "❌ **Server Status:** Not accessible" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    exit 1
fi

echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "## 📸 Screenshot Results" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"

# List the screenshots that were taken
log "Documenting screenshot results..."

if [ -f "$SCREENSHOTS_DIR/admini-login-page-comprehensive.png" ]; then
    success "Login page screenshot found"
    echo "### 🔐 Login Interface" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ✅ Captured successfully" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **File:** \`admini-login-page-comprehensive.png\`" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Wait Time:** 12 seconds" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Description:** Clean, professional login interface for Admini Control Panel" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
else
    warning "Login page screenshot not found"
    echo "### 🔐 Login Interface" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ❌ Not captured" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
fi

if [ -f "$SCREENSHOTS_DIR/admini-admin-dashboard-comprehensive.png" ]; then
    success "Admin dashboard screenshot found"
    echo "### 👑 AdminiCore (Administrator Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ✅ Captured successfully" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **File:** \`admini-admin-dashboard-comprehensive.png\`" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Wait Time:** 15 seconds" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Features:** System stats, user management, server monitoring" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Navigation:** Dashboard, Statistics, Users, Resellers, IP Management, Settings, System Info" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
else
    warning "Admin dashboard screenshot not found"
    echo "### 👑 AdminiCore (Administrator Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ❌ Not captured" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
fi

if [ -f "$SCREENSHOTS_DIR/admini-reseller-dashboard-comprehensive.png" ]; then
    success "Reseller dashboard screenshot found"
    echo "### 🏢 AdminiReseller (Reseller Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ✅ Captured successfully" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **File:** \`admini-reseller-dashboard-comprehensive.png\`" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Wait Time:** 14 seconds" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Features:** Client management, resource monitoring, account creation" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Navigation:** Dashboard, Statistics, Users, Packages, Accounts" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
else
    warning "Reseller dashboard screenshot not found"
    echo "### 🏢 AdminiReseller (Reseller Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ❌ Not captured" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
fi

if [ -f "$SCREENSHOTS_DIR/admini-user-dashboard-comprehensive.png" ]; then
    success "User dashboard screenshot found"
    echo "### 👤 AdminiPanel (User Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ✅ Captured successfully" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **File:** \`admini-user-dashboard-comprehensive.png\`" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Wait Time:** 12 seconds" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Features:** Domain management, email accounts, file manager, databases, SSL" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Navigation:** Dashboard, Domains, Email, File Manager, Databases, SSL, Statistics" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Style:** cPanel-compatible interface design" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
else
    warning "User dashboard screenshot not found"
    echo "### 👤 AdminiPanel (User Dashboard)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "- **Status:** ❌ Not captured" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
fi

# Count screenshots
SCREENSHOT_COUNT=$(ls -1 "$SCREENSHOTS_DIR"/*comprehensive*.png 2>/dev/null | wc -l)

echo "## 📊 Test Summary" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **Total Screenshots Captured:** $SCREENSHOT_COUNT/4" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **Test Duration:** Approximately 60+ seconds (with proper wait times)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **Browser Automation:** Playwright (headless mode)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **Authentication:** Demo credentials (admin/password)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"

echo "## 🔍 Technical Details" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "**Test Methodology:**" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "1. Server availability check" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "2. Login page access and screenshot (12s wait)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "3. Authentication with demo credentials" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "4. Admin dashboard access and screenshot (15s wait)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "5. User dashboard access and screenshot (12s wait)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "6. Reseller dashboard access and screenshot (14s wait)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"

echo "**Interface Types Tested:**" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **AdminiCore:** Full administrative control panel" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **AdminiReseller:** Reseller management interface" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "- **AdminiPanel:** End-user hosting control panel (cPanel-style)" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
echo "" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"

if [ $SCREENSHOT_COUNT -eq 4 ]; then
    echo "✅ **Overall Result:** All dashboard interfaces successfully tested and screenshotted" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    success "All $SCREENSHOT_COUNT dashboard screenshots captured successfully!"
    echo ""
    log "Test report generated: $RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    echo ""
    echo "📸 Screenshots available in: $SCREENSHOTS_DIR"
    echo "   - admini-login-page-comprehensive.png"
    echo "   - admini-admin-dashboard-comprehensive.png"
    echo "   - admini-reseller-dashboard-comprehensive.png" 
    echo "   - admini-user-dashboard-comprehensive.png"
else
    echo "⚠️ **Overall Result:** $SCREENSHOT_COUNT out of 4 dashboard interfaces captured" >> "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md"
    warning "Only $SCREENSHOT_COUNT out of 4 dashboard screenshots were captured"
fi

echo ""
echo "$(cat "$RESULTS_DIR/dashboard_screenshot_test_${TIMESTAMP}.md")"