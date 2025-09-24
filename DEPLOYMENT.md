# Production Deployment Guide

## Overview
This guide provides comprehensive instructions for deploying the Laboratory ERP System to production environments.

## Prerequisites

### Server Requirements
- **Operating System**: Ubuntu 20.04+ or CentOS 8+
- **PHP**: 8.2+ with extensions: BCMath, Ctype, cURL, DOM, Fileinfo, JSON, Mbstring, OpenSSL, PCRE, PDO, Tokenizer, XML, GD, Imagick
- **Web Server**: Nginx 1.18+ or Apache 2.4+
- **Database**: MySQL 8.0+ or PostgreSQL 13+
- **Cache**: Redis 6.0+ (recommended)
- **SSL Certificate**: Let's Encrypt or commercial certificate

### Software Installation

#### 1. Install PHP 8.2
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install software-properties-common
sudo add-apt-repository ppa:ondrej/php
sudo apt update
sudo apt install php8.2 php8.2-fpm php8.2-mysql php8.2-redis php8.2-gd php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath

# CentOS/RHEL
sudo dnf install epel-release
sudo dnf install https://rpms.remirepo.net/enterprise/remi-release-8.rpm
sudo dnf module enable php:remi-8.2
sudo dnf install php php-fpm php-mysqlnd php-redis php-gd php-mbstring php-xml php-curl php-zip php-bcmath
```

#### 2. Install Composer
```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

#### 3. Install Node.js and NPM
```bash
# Using NodeSource repository
curl -fsSL https://deb.nodesource.com/setup_18.x | sudo -E bash -
sudo apt-get install -y nodejs
```

#### 4. Install Redis
```bash
# Ubuntu/Debian
sudo apt install redis-server

# CentOS/RHEL
sudo dnf install redis
sudo systemctl enable redis
sudo systemctl start redis
```

#### 5. Install Nginx
```bash
# Ubuntu/Debian
sudo apt install nginx

# CentOS/RHEL
sudo dnf install nginx
sudo systemctl enable nginx
sudo systemctl start nginx
```

## Database Setup

### 1. Create Database and User
```sql
-- MySQL
CREATE DATABASE lab_erp_production CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'lab_erp_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON lab_erp_production.* TO 'lab_erp_user'@'localhost';
FLUSH PRIVILEGES;
```

### 2. Configure Database
Update your `.env` file with production database credentials:
```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=lab_erp_production
DB_USERNAME=lab_erp_user
DB_PASSWORD=secure_password_here
```

## Application Deployment

### 1. Clone Repository
```bash
cd /var/www
sudo git clone https://github.com/your-repo/lab-erp.git
sudo chown -R www-data:www-data lab-erp
cd lab-erp
```

### 2. Install Dependencies
```bash
# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Node.js dependencies (if frontend is in same repo)
npm ci --production
npm run build
```

### 3. Environment Configuration
```bash
# Copy production environment file
cp production.env.example .env

# Generate application key
php artisan key:generate

# Update environment variables
nano .env
```

### 4. Database Migration
```bash
# Run migrations
php artisan migrate --force

# Seed initial data
php artisan db:seed --force
```

### 5. Optimize Application
```bash
# Clear and cache configuration
php artisan config:clear
php artisan config:cache
php artisan route:clear
php artisan route:cache
php artisan view:clear
php artisan view:cache

# Optimize for production
php artisan optimize
```

### 6. Set Permissions
```bash
# Set proper ownership
sudo chown -R www-data:www-data /var/www/lab-erp

# Set proper permissions
sudo chmod -R 755 /var/www/lab-erp
sudo chmod -R 775 /var/www/lab-erp/storage
sudo chmod -R 775 /var/www/lab-erp/bootstrap/cache
```

## Web Server Configuration

### Nginx Configuration
Create `/etc/nginx/sites-available/lab-erp`:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    root /var/www/lab-erp/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/x-javascript application/xml+rss;

    # Handle requests
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP handling
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }

    # Cache static assets
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/lab-erp /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### SSL Configuration
```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## Security Configuration

### 1. Firewall Setup
```bash
# UFW (Ubuntu)
sudo ufw allow ssh
sudo ufw allow 'Nginx Full'
sudo ufw enable

# Firewalld (CentOS)
sudo firewall-cmd --permanent --add-service=ssh
sudo firewall-cmd --permanent --add-service=http
sudo firewall-cmd --permanent --add-service=https
sudo firewall-cmd --reload
```

### 2. PHP Security
Edit `/etc/php/8.2/fpm/php.ini`:
```ini
expose_php = Off
allow_url_fopen = Off
allow_url_include = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 30
memory_limit = 256M
```

### 3. Database Security
```sql
-- Remove test database
DROP DATABASE IF EXISTS test;

-- Remove anonymous users
DELETE FROM mysql.user WHERE User='';

-- Disable remote root login
DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Flush privileges
FLUSH PRIVILEGES;
```

## Monitoring and Logging

### 1. Log Rotation
Create `/etc/logrotate.d/lab-erp`:
```
/var/www/lab-erp/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 644 www-data www-data
    postrotate
        systemctl reload php8.2-fpm
    endscript
}
```

### 2. System Monitoring
```bash
# Install monitoring tools
sudo apt install htop iotop nethogs

# Monitor logs
sudo tail -f /var/log/nginx/access.log
sudo tail -f /var/log/nginx/error.log
sudo tail -f /var/www/lab-erp/storage/logs/laravel.log
```

### 3. Health Checks
The application includes health check endpoints:
- `GET /api/health` - Basic health check
- `GET /api/health/detailed` - Detailed system information

## Backup Strategy

### 1. Database Backup
Create `/usr/local/bin/backup-db.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/lab-erp"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="lab_erp_production"

mkdir -p $BACKUP_DIR

mysqldump -u lab_erp_user -p$DB_PASSWORD $DB_NAME > $BACKUP_DIR/db_backup_$DATE.sql
gzip $BACKUP_DIR/db_backup_$DATE.sql

# Keep only last 30 days
find $BACKUP_DIR -name "db_backup_*.sql.gz" -mtime +30 -delete
```

### 2. File Backup
Create `/usr/local/bin/backup-files.sh`:
```bash
#!/bin/bash
BACKUP_DIR="/var/backups/lab-erp"
DATE=$(date +%Y%m%d_%H%M%S)
APP_DIR="/var/www/lab-erp"

mkdir -p $BACKUP_DIR

tar -czf $BACKUP_DIR/files_backup_$DATE.tar.gz -C $APP_DIR storage bootstrap/cache

# Keep only last 30 days
find $BACKUP_DIR -name "files_backup_*.tar.gz" -mtime +30 -delete
```

### 3. Automated Backups
Add to crontab:
```bash
sudo crontab -e

# Daily database backup at 2 AM
0 2 * * * /usr/local/bin/backup-db.sh

# Daily file backup at 3 AM
0 3 * * * /usr/local/bin/backup-files.sh

# Weekly full backup on Sunday at 1 AM
0 1 * * 0 /usr/local/bin/backup-full.sh
```

## Performance Optimization

### 1. PHP-FPM Configuration
Edit `/etc/php/8.2/fpm/pool.d/www.conf`:
```ini
pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000
```

### 2. Redis Configuration
Edit `/etc/redis/redis.conf`:
```conf
maxmemory 256mb
maxmemory-policy allkeys-lru
save 900 1
save 300 10
save 60 10000
```

### 3. Nginx Optimization
Add to nginx configuration:
```nginx
# Worker processes
worker_processes auto;

# Connection limits
worker_connections 1024;

# Buffer sizes
client_body_buffer_size 128k;
client_max_body_size 10m;
client_header_buffer_size 1k;
large_client_header_buffers 4 4k;
output_buffers 1 32k;
postpone_output 1460;
```

## Deployment Script

Use the provided `deploy.sh` script for automated deployments:
```bash
# Make executable
chmod +x deploy.sh

# Run deployment
./deploy.sh production
```

## Maintenance Tasks

### 1. Regular Maintenance
```bash
# Clean up expired tokens
php artisan tokens:cleanup

# Clear cache
php artisan cache:clear

# Optimize application
php artisan optimize
```

### 2. Update Application
```bash
# Pull latest changes
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan optimize
```

## Troubleshooting

### Common Issues

1. **Permission Errors**
   ```bash
   sudo chown -R www-data:www-data /var/www/lab-erp
   sudo chmod -R 775 /var/www/lab-erp/storage
   ```

2. **Database Connection Issues**
   - Check database credentials in `.env`
   - Verify database server is running
   - Check firewall settings

3. **Cache Issues**
   ```bash
   php artisan cache:clear
   php artisan config:clear
   php artisan route:clear
   php artisan view:clear
   ```

4. **SSL Issues**
   - Verify certificate is valid
   - Check nginx configuration
   - Ensure port 443 is open

### Log Locations
- Application logs: `/var/www/lab-erp/storage/logs/`
- Nginx logs: `/var/log/nginx/`
- PHP-FPM logs: `/var/log/php8.2-fpm.log`
- System logs: `/var/log/syslog`

## Security Checklist

- [ ] SSL certificate installed and configured
- [ ] Firewall configured and enabled
- [ ] Database secured with strong passwords
- [ ] File permissions set correctly
- [ ] Security headers configured
- [ ] Regular backups scheduled
- [ ] Monitoring and logging enabled
- [ ] Updates scheduled and automated
- [ ] Rate limiting configured
- [ ] CSRF protection enabled

## Support

For deployment issues or questions:
1. Check application logs
2. Verify server configuration
3. Test health check endpoints
4. Review security configuration
5. Contact system administrator

This deployment guide ensures a secure, scalable, and maintainable production environment for the Laboratory ERP System.























