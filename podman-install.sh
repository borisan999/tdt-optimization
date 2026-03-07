#!/bin/bash
# TDT Optimization System - Podman Installation Script
# This script creates and configures all necessary containers
set -e

# Configuration
CONTAINER_NAME="tdt-mysql"
APP_CONTAINER_NAME="tdt-app"
IMAGE_NAME="tdt-optimization"
DB_NAME="tdt_optimization"
DB_USER="newuser"
DB_PASS="C6oVYnXd26ByFhaoGZmcPWqhUiVcxl3EK3WOWEpP6yQ="
HOST_PORT=8080
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

# Check if podman is installed
if ! command -v podman &> /dev/null; then
    echo_error "Podman is not installed. Please install it first. sudo apt install podman -y"
    exit 1
fi

# Get script directory
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

echo_info "==================================================================="
echo_info "   TDT Optimization - Podman Installation - Ubuntu 24.04.4 LTS"
echo_info "==================================================================="
echo ""

# --- Phase 1: Cleanup existing containers ---
echo_info "Phase 1: Cleaning up existing containers..."
podman stop $CONTAINER_NAME 2>/dev/null || true
podman rm $CONTAINER_NAME 2>/dev/null || true
podman stop $APP_CONTAINER_NAME 2>/dev/null || true
podman rm $APP_CONTAINER_NAME 2>/dev/null || true

# --- Phase 2: Create Network ---
echo_info "Phase 2: Creating network..."
podman network create $NETWORK_NAME 2>/dev/null || true

# --- Phase 3: MySQL Container ---
echo_info "Phase 3: Starting MySQL container..."

podman run -d \
    --name $CONTAINER_NAME \
    --network $NETWORK_NAME \
    -e MYSQL_ROOT_PASSWORD=rootpass \
    -e MYSQL_DATABASE=$DB_NAME \
    -e MYSQL_USER=$DB_USER \
    -e MYSQL_PASSWORD=$DB_PASS \
    -v tdt-mysql-data:/var/lib/mysql \
    docker.io/library/mysql:8.0 \
    --default-authentication-plugin=mysql_native_password

# Wait for MySQL to be ready
echo_info "Waiting for MySQL to be ready..."
for i in {1..60}; do
    if podman exec $CONTAINER_NAME mysql -uroot -prootpass -e "SELECT 1" &>/dev/null; then
        echo_info "MySQL is ready!"
        break
    fi
    echo_info "Waiting... ($i/60)"
    sleep 2
done

# Extra wait for full initialization
sleep 5

# Create database user and grant privileges
echo_info "Creating database user..."
podman exec $CONTAINER_NAME mysql -uroot -prootpass -S /var/lib/mysql/mysql.sock <<EOF
CREATE USER IF NOT EXISTS '$DB_USER'@'%' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'%';
FLUSH PRIVILEGES;
EOF

# Import database schema
echo_info "Importing database schema..."

# Copy SQL file to container
podman cp "$SCRIPT_DIR/tdt_optimization.sql" $CONTAINER_NAME:/tmp/import.sql

# Import using SOURCE (required for proper execution)
podman exec $CONTAINER_NAME sh -c 'mysql -uroot -prootpass -S /var/lib/mysql/mysql.sock -e "SET FOREIGN_KEY_CHECKS=0; SOURCE /tmp/import.sql;" '$DB_NAME''

echo_info "Database imported successfully."

# --- Phase 4: Build Application Image ---
echo_info "Phase 4: Using already built Ubuntu image..."

# (Image was built manually with Containerfile.ubuntu)

# --- Phase 5: Run Application Container ---
echo_info "Phase 5: Running application container..."

# Get absolute path to project directory
PROJECT_DIR="$(cd "$SCRIPT_DIR" && pwd)"

podman run -d \
    --name $APP_CONTAINER_NAME \
    --network $NETWORK_NAME \
    -p $HOST_PORT:80 \
    -e DB_HOST=$CONTAINER_NAME \
    -e DB_NAME=$DB_NAME \
    -e DB_USER=$DB_USER \
    -e DB_PASS=$DB_PASS \
    -e PYTHON_DB_HOST=$CONTAINER_NAME \
    -v "$PROJECT_DIR/app:/var/www/html/app:ro" \
    -v "$PROJECT_DIR/public:/var/www/html/public:ro" \
    -v "$PROJECT_DIR/.env:/var/www/html/.env:ro" \
    -v "$PROJECT_DIR/scripts:/var/www/html/scripts:ro" \
    -v "$PROJECT_DIR/vendor:/var/www/html/vendor:ro" \
    -v "$PROJECT_DIR/storage:/var/www/html/storage:rw" \
    $IMAGE_NAME

# Wait for Apache to start
sleep 3

# --- Phase 6: Verification ---
echo ""
echo_info "=========================================="
echo_info "   Installation Complete!"
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
echo_info "To manage containers, use: podman-start.sh or podman-stop.sh"
