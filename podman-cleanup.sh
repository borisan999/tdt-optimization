#!/bin/bash
# TDT Optimization System - Podman Cleanup Script
# This script removes all containers, volumes, and networks

set -e

# Configuration
CONTAINER_NAME="tdt-mysql"
APP_CONTAINER_NAME="tdt-app"
NETWORK_NAME="tdt-network"

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

echo ""
echo_warn "=========================================="
echo_warn "   WARNING: This will remove ALL data!"
echo_warn "=========================================="
echo ""
read -p "Are you sure you want to continue? (yes/no): " confirm

if [ "$confirm" != "yes" ]; then
    echo_info "Cleanup cancelled."
    exit 0
fi

echo_info "Stopping containers..."
podman stop $CONTAINER_NAME 2>/dev/null || true
podman stop $APP_CONTAINER_NAME 2>/dev/null || true

echo_info "Removing containers..."
podman rm $CONTAINER_NAME 2>/dev/null || true
podman rm $APP_CONTAINER_NAME 2>/dev/null || true

echo_info "Removing volumes..."
podman volume rm tdt-mysql-data 2>/dev/null || true

echo_info "Removing network..."
podman network rm $NETWORK_NAME 2>/dev/null || true

echo ""
echo_info "=========================================="
echo_info "   Cleanup Complete!"
echo_info "=========================================="
echo ""
echo_info "To reinstall, run: ./podman-install.sh"
