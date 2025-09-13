#!/bin/bash

# Production Deployment Script for Laboratory ERP System
# Usage: ./deploy.sh [environment]

set -e

# Configuration
ENVIRONMENT=${1:-production}
APP_DIR="/var/www/lab-erp"
BACKUP_DIR="/var/backups/lab-erp"
LOG_FILE="/var/log/lab-erp-deploy.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a $LOG_FILE
}

error() {
    echo -e "${RED}[ERROR]${NC} $1" | tee -a $LOG_FILE
    exit 1
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1" | tee -a $LOG_FILE
}

# Check if running as root or with sudo
if [[ $EUID -eq 0 ]]; then
    error "This script should not be run as root for security reasons"
fi

# Check if application directory exists
if [ ! -d "$APP_DIR" ]; then
    error "Application directory $APP_DIR does not exist"
fi

log "Starting deployment for environment: $ENVIRONMENT"

# Create backup directory if it doesn't exist
mkdir -p $BACKUP_DIR

# Backup current application
log "Creating backup of current application..."
BACKUP_NAME="backup-$(date +%Y%m%d-%H%M%S)"
tar -czf "$BACKUP_DIR/$BACKUP_NAME.tar.gz" -C "$APP_DIR" . 2>/dev/null || warning "Failed to create backup"

# Navigate to application directory
cd $APP_DIR

# Enable maintenance mode
log "Enabling maintenance mode..."
php artisan down --message="Deployment in progress" --retry=60

# Pull latest code from repository
log "Pulling latest code from repository..."
git fetch origin
git reset --hard origin/main

# Install/Update Composer dependencies
log "Installing Composer dependencies..."
composer install --no-dev --optimize-autoloader --no-interaction

# Install/Update NPM dependencies (if frontend is in same repo)
if [ -f "package.json" ]; then
    log "Installing NPM dependencies..."
    npm ci --production
    npm run build
fi

# Clear and cache configuration
log "Optimizing application..."
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache
php artisan event:clear
php artisan event:cache

# Run database migrations
log "Running database migrations..."
php artisan migrate --force

# Clear application cache
log "Clearing application cache..."
php artisan cache:clear

# Optimize for production
log "Optimizing for production..."
php artisan optimize

# Set proper permissions
log "Setting proper permissions..."
sudo chown -R www-data:www-data $APP_DIR
sudo chmod -R 755 $APP_DIR
sudo chmod -R 775 $APP_DIR/storage
sudo chmod -R 775 $APP_DIR/bootstrap/cache

# Restart services
log "Restarting services..."
sudo systemctl reload php8.2-fpm
sudo systemctl reload nginx

# Clean up old backups (keep last 10)
log "Cleaning up old backups..."
cd $BACKUP_DIR
ls -t | tail -n +11 | xargs -r rm -f

# Disable maintenance mode
log "Disabling maintenance mode..."
php artisan up

# Run health check
log "Running health check..."
if curl -f -s http://localhost/health > /dev/null; then
    log "Health check passed"
else
    warning "Health check failed - please verify application manually"
fi

# Clean up expired tokens
log "Cleaning up expired tokens..."
php artisan tokens:cleanup

log "Deployment completed successfully!"

# Send notification (optional)
if command -v mail &> /dev/null; then
    echo "Deployment completed successfully at $(date)" | mail -s "Lab ERP Deployment Success" admin@your-domain.com
fi

log "Deployment script finished"






