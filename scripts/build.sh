#!/bin/bash

###############################################################################
# build.sh - Build Admini Control Panel from source
# 
# This script builds the Admini binary and sets up necessary files
# for production deployment.
#
###############################################################################

set -e

# Colors for output
color_green=$(printf '\033[32m')
color_red=$(printf '\033[31m')
color_blue=$(printf '\033[34m')
color_reset=$(printf '\033[0m')

echogreen() {
    echo "${color_green}[build.sh] $*${color_reset}"
}

echored() {
    echo "${color_red}[build.sh] $*${color_reset}"
}

echoblue() {
    echo "${color_blue}[build.sh] $*${color_reset}"
}

# Check if Go is installed
if ! command -v go &> /dev/null; then
    echored "Go is not installed. Please install Go 1.21 or higher."
    echored "Visit: https://golang.org/doc/install"
    exit 1
fi

# Check Go version
GO_VERSION=$(go version | awk '{print $3}' | sed 's/go//')
REQUIRED_VERSION="1.21"

if [ "$(printf '%s\n' "$REQUIRED_VERSION" "$GO_VERSION" | sort -V | head -n1)" != "$REQUIRED_VERSION" ]; then
    echored "Go version $GO_VERSION is too old. Please upgrade to Go $REQUIRED_VERSION or higher."
    exit 1
fi

echoblue "Building Admini Control Panel..."

# Navigate to backend directory
cd "$(dirname "$0")/backend" || exit 1

# Download dependencies
echogreen "Downloading dependencies..."
go mod tidy

# Build the binary
echogreen "Building binary..."
CGO_ENABLED=0 GOOS=linux go build -a -installsuffix cgo -ldflags="-w -s" -o admini .

# Make binary executable
chmod +x admini

# Verify binary
if [[ ! -f "admini" ]]; then
    echored "Build failed: binary not found"
    exit 1
fi

echogreen "Build completed successfully!"

# Display binary info
echoblue "Binary information:"
ls -lh admini
echo ""

# Test binary
echoblue "Testing binary..."
./admini version
echo ""

echogreen "Build and test completed successfully!"
echogreen "Binary location: $(pwd)/admini"
echogreen "Ready for installation or deployment."
echo ""
echoblue "To install system-wide, run:"
echo "  sudo cp admini /usr/local/bin/"
echo "  sudo chmod +x /usr/local/bin/admini"
echo ""
echoblue "To run development server:"
echo "  ./admini server --port 8080"