#!/bin/bash
# TDT Optimization System - Podman Stop Script
# This script stops the running containers

set -e

# Configuration
CONTAINER_NAME="tdt-mysql"
APP_CONTAINER_NAME="tdt-app"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

echo_info() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

echo_warn() {
    echo -e "${YELLOW}[WARN]${NC} $1"
}

echo_error() {
    echo -e "${RED}[ERROR]${NC} $1"
}

echo_info "=========================================="
echo_info "   TDT Optimization - Stop Containers"
echo_info "=========================================="
echo ""

# Stop Application first
echo_info "Stopping application container..."
podman stop $APP_CONTAINER_NAME 2>/dev/null || true

# Stop MySQL
echo_info "Stopping MySQL container..."
podman stop $CONTAINER_NAME 2>/dev/null || true

echo ""
echo_info "=========================================="
echo_info "   Containers Stopped Successfully!"
echo_info "=========================================="
echo ""
echo_info "Container Status:"
podman ps -a --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo_info "To start containers, run: podman-start.sh"
