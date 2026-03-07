#!/bin/bash
# TDT Optimization System - Podman Start Script
# This script starts the existing containers

set -e

# Configuration
CONTAINER_NAME="tdt-mysql"
APP_CONTAINER_NAME="tdt-app"
HOST_PORT=8080

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
echo_info "   TDT Optimization - Start Containers"
echo_info "=========================================="
echo ""

# Check if containers exist
if ! podman ps -a --format '{{.Names}}' | grep -q "^${CONTAINER_NAME}$"; then
    echo_error "MySQL container '$CONTAINER_NAME' does not exist."
    echo_info "Please run podman-install.sh first."
    exit 1
fi

if ! podman ps -a --format '{{.Names}}' | grep -q "^${APP_CONTAINER_NAME}$"; then
    echo_error "Application container '$APP_CONTAINER_NAME' does not exist."
    echo_info "Please run podman-install.sh first."
    exit 1
fi

# Check if network exists
if ! podman network ls --format '{{.Name}}' | grep -q "tdt-network"; then
    echo_info "Creating network..."
    podman network create tdt-network
fi

# Connect MySQL to network if not connected
if ! podman inspect $CONTAINER_NAME --format '{{range .NetworkSettings.Networks}}{{.NetworkID}}{{end}}' | grep -q "tdt-network"; then
    echo_info "Connecting MySQL to network..."
    podman network connect tdt-network $CONTAINER_NAME 2>/dev/null || true
fi

# Start MySQL first
echo_info "Starting MySQL container..."
podman start $CONTAINER_NAME

# Wait for MySQL to be ready
echo_info "Waiting for MySQL to be ready..."
for i in {1..30}; do
    if podman exec $CONTAINER_NAME mysql -uroot -prootpass -e "SELECT 1" &>/dev/null; then
        echo_info "MySQL is ready!"
        break
    fi
    sleep 1
done

# Start Application
echo_info "Starting application container..."
podman start $APP_CONTAINER_NAME

# Wait for Apache
sleep 3

# Verify
echo ""
echo_info "=========================================="
echo_info "   Containers Started Successfully!"
echo_info "=========================================="
echo ""
echo_info "Application URL: http://localhost:$HOST_PORT/login"
echo_info "Login credentials:"
echo_info "  User: admin3"
echo_info "  Pass: 1234"
echo ""
echo_info "Container Status:"
podman ps --format "table {{.Names}}\t{{.Status}}\t{{.Ports}}"
echo ""
echo_info "To stop containers, run: podman-stop.sh"
