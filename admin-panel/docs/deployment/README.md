# Deployment Guide - BangronDB Admin Panel

Panduan lengkap untuk deployment BangronDB Admin Panel ke berbagai lingkungan. Panduan ini mencakup development, staging, dan production deployment dengan best practices untuk keamanan dan performa.

## 📋 Deployment Overview

### Environments

| Environment     | Purpose             | Features                    |
| --------------- | ------------------- | --------------------------- |
| **Development** | Local development   | Debug mode, hot reload      |
| **Staging**     | Testing environment | Production-like setup       |
| **Production**  | Live environment    | Optimized, secure, scalable |

### Deployment Methods

| Method     | Best For                             | Complexity |
| ---------- | ------------------------------------ | ---------- |
| **Manual** | Small teams, simple setups           | Low        |
| **Docker** | Consistent environments, scalability | Medium     |
| **CI/CD**  | Automated deployments, large teams   | High       |

## 🚀 Development Deployment

### Local Development Setup

#### Prerequisites

```bash
# System requirements
- PHP 8.0+
- Composer 2.0+
- SQLite 3
- Git

# Install dependencies
composer install
npm install

# Create environment file
cp .env.example .env

# Generate encryption key
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;" >> .env
```

#### Development Server

```bash
# Start PHP development server
php -S localhost:8080 -t public

# Or use artisan serve
php artisan serve --host=0.0.0.0 --port=8080
```

#### Development Configuration

```env
# .env development settings
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database
DB_PATH=./data
DB_ENCRYPTION_KEY=your-dev-key

# Security
SESSION_LIFETIME=1440
LOG_LEVEL=debug

# Features
ENABLE_DEBUG_BAR=true
ENABLE_QUERY_LOG=true
```

### Development Workflow

#### Git Setup

```bash
# Initialize git repository
git init

# Add .gitignore
echo "/data
/vendor
/.env
*.log
.DS_Store
node_modules/" > .gitignore

# Initial commit
git add .
git commit -m "Initial commit"
```

#### Hot Reload Development

```javascript
// Enable hot reload in webpack.mix.js
mix
  .js("resources/js/app.js", "public/js")
  .sass("resources/sass/app.scss", "public/css")
  .sourceMaps(); // Enable source maps for debugging
```

## 🐳 Docker Deployment

### Docker Setup

#### Dockerfile

```dockerfile
# Development Dockerfile
FROM php:8.0-fpm

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libzip-dev \
    unzip \
    git \
    curl

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite zip gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Expose port
EXPOSE 8080

# Start PHP server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

#### docker-compose.yml

```yaml
version: "3.8"

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile
    ports:
      - "8080:8080"
    volumes:
      - ./data:/var/www/data
      - ./.env:/var/www/.env
    environment:
      - APP_ENV=production
      - DB_PATH=/var/www/data
    depends_on:
      - db

  db:
    image: alpine:latest
    volumes:
      - sqlite_data:/var/lib/sqlite
    command: tail -f /dev/null

volumes:
  sqlite_data:
```

### Docker Production Setup

#### Production Dockerfile

```dockerfile
# Production Dockerfile
FROM php:8.0-fpm-alpine

# Install dependencies
RUN apk add --no-cache \
    libpng-dev \
    libzip-dev \
    unzip \
    git

# Install PHP extensions
RUN docker-php-ext-install pdo pdo_sqlite zip gd

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . .

# Install production dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Optimize PHP
RUN php -r "opcache_compile_file('vendor/autoload.php');"

# Create non-root user
RUN addgroup -g 1000 -S app && \
    adduser -S app -u 1000

# Change ownership
RUN chown -R app:app /var/www/html
USER app

# Expose port
EXPOSE 8080

# Start PHP server
CMD ["php", "-S", "0.0.0.0:8080", "-t", "public"]
```

#### Production docker-compose.yml

```yaml
version: "3.8"

services:
  app:
    build:
      context: .
      dockerfile: Dockerfile.prod
    ports:
      - "8080:8080"
    volumes:
      - ./data:/var/www/data:ro
      - ./logs:/var/www/logs
    environment:
      - APP_ENV=production
      - APP_DEBUG=false
      - DB_PATH=/var/www/data
      - ENCRYPTION_KEY=${ENCRYPTION_KEY}
    restart: unless-stopped
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:8080/api/v1/system/health"]
      interval: 30s
      timeout: 10s
      retries: 3

  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
      - "443:443"
    volumes:
      - ./nginx.conf:/etc/nginx/nginx.conf:ro
      - ./ssl:/etc/nginx/ssl:ro
      - ./public:/var/www/public:ro
    depends_on:
      - app
    restart: unless-stopped

volumes:
  sqlite_data:
```

### Docker Commands

```bash
# Build and start containers
docker-compose up -d

# View logs
docker-compose logs -f app

# Stop containers
docker-compose down

# Rebuild containers
docker-compose build --no-cache

# Execute command in container
docker-compose exec app bash
```

## 🏭 Production Deployment

### Server Requirements

#### Hardware Requirements

| Component   | Minimum  | Recommended |
| ----------- | -------- | ----------- |
| **CPU**     | 2 cores  | 4+ cores    |
| **RAM**     | 4GB      | 8GB+        |
| **Storage** | 50GB SSD | 100GB+ SSD  |
| **Network** | 100Mbps  | 1Gbps       |

#### Software Requirements

- **OS**: Ubuntu 20.04+ / CentOS 8+ / Debian 10+
- **PHP**: 8.0+ with required extensions
- **Web Server**: Nginx 1.18+ or Apache 2.4+
- **Database**: SQLite 3 (included)
- **SSL**: Let's Encrypt or commercial certificate

### Production Installation

#### Step 1: Server Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx php8.0 php8.0-fpm php8.0-sqlite3 php8.0-zip php8.0-gd php8.0-curl

# Create application user
sudo useradd -m -s /bin/bash bangrondb
sudo usermod -aG www-data bangrondb
```

#### Step 2: Application Setup

```bash
# Clone repository
sudo git clone https://github.com/bangrondb/bangrondb.git /var/www/bangrondb
sudo chown -R bangrondb:bangrondb /var/www/bangrondb

# Switch to application user
sudo -u bangrondb bash

# Navigate to admin panel
cd /var/www/bangrondb/admin-panel

# Install dependencies
composer install --no-dev --optimize-autoloader --no-scripts

# Create directories
mkdir -p /var/www/bangrondb/admin-panel/data
mkdir -p /var/www/bangrondb/admin-panel/logs
chmod 755 /var/www/bangrondb/admin-panel/data
chmod 755 /var/www/bangrondb/admin-panel/logs
```

#### Step 3: Environment Configuration

```bash
# Copy environment file
cp .env.example .env

# Edit environment file
nano .env
```

Production `.env`:

```env
# Application
APP_NAME="BangronDB Admin Panel"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://yourdomain.com

# Database
DB_PATH=/var/www/bangrondb/admin-panel/data
DB_ENCRYPTION_KEY=${ENCRYPTION_KEY}

# Security
SESSION_LIFETIME=120
ENCRYPTION_CIPHER=AES-256-CBC

# Performance
CACHE_DRIVER=file
QUEUE_DRIVER=sync

# Monitoring
ENABLE_METRICS=true
LOG_LEVEL=error
```

#### Step 4: Web Server Configuration

### Nginx Configuration

```nginx
# /etc/nginx/sites-available/bangrondb
server {
    listen 80;
    server_name yourdomain.com www.yourdomain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;

    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;

    # Security headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

    # Path to application
    root /var/www/bangrondb/admin-panel/public;
    index index.php index.html;

    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.0-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Security - deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static files caching
    location ~* \.(css|js|ico|png|jpg|jpeg|gif|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_proxied any;
    gzip_comp_level 6;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;
}
```

### Apache Configuration

```apache
# /etc/apache2/sites-available/bangrondb.conf
<VirtualHost *:80>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com
    Redirect permanent / https://yourdomain.com/
</VirtualHost>

<VirtualHost *:443>
    ServerName yourdomain.com
    ServerAlias www.yourdomain.com

    # SSL Configuration
    SSLEngine on
    SSLCertificateFile /etc/letsencrypt/live/yourdomain.com/fullchain.pem
    SSLCertificateKeyFile /etc/letsencrypt/live/yourdomain.com/privkey.pem

    # Document root
    DocumentRoot /var/www/bangrondb/admin-panel/public

    <Directory /var/www/bangrondb/admin-panel/public>
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>

    # PHP configuration
    <FilesMatch \.php$>
        SetHandler "proxy:fcgi://127.0.0.1:9000"
    </FilesMatch>

    # Security headers
    Header always set X-Frame-Options "SAMEORIGIN"
    Header always set X-Content-Type-Options "nosniff"
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "no-referrer-when-downgrade"
</VirtualHost>
```

### SSL Certificate Setup

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

## 🔧 CI/CD Pipeline

### GitHub Actions Example

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]
  pull_request:
    branches: [main]

jobs:
  test:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v2

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: "8.0"
          extensions: pdo_sqlite, zip, gd, curl

      - name: Install dependencies
        run: composer install --no-dev --optimize-autoloader

      - name: Run tests
        run: php vendor/bin/phpunit

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'

    steps:
      - uses: actions/checkout@v2

      - name: Deploy to production
        uses: appleboy/ssh-action@v0.1.3
        with:
          host: ${{ secrets.PRODUCTION_HOST }}
          username: ${{ secrets.PRODUCTION_USER }}
          key: ${{ secrets.PRODUCTION_SSH_KEY }}
          script: |
            cd /var/www/bangrondb/admin-panel
            git pull origin main
            composer install --no-dev --optimize-autoloader
            php artisan optimize:clear
            sudo systemctl restart nginx
```

### Jenkins Pipeline

```groovy
// Jenkinsfile
pipeline {
    agent any

    environment {
        APP_PATH = '/var/www/bangrondb/admin-panel'
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Install Dependencies') {
            steps {
                sh 'composer install --no-dev --optimize-autoloader'
            }
        }

        stage('Test') {
            steps {
                sh 'php vendor/bin/phpunit'
            }
        }

        stage('Deploy') {
            steps {
                sh 'git pull origin main'
                sh 'composer install --no-dev --optimize-autoloader'
                sh 'php artisan optimize:clear'
                sh 'sudo systemctl restart nginx'
            }
        }
    }

    post {
        always {
            echo 'Pipeline completed'
        }
        success {
            echo 'Pipeline succeeded'
        }
        failure {
            echo 'Pipeline failed'
            emailext (
                subject: "Pipeline Failed: ${env.JOB_NAME} - ${env.BUILD_NUMBER}",
                body: "Pipeline failed. Please check the logs.",
                to: "${env.CHANGE_AUTHOR_EMAIL}, admin@yourdomain.com"
            )
        }
    }
}
```

## 🔒 Security Hardening

### File Permissions

```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/bangrondb
sudo chmod -R 755 /var/www/bangrondb
sudo chmod -R 644 /var/www/bangrondb/admin-panel/public/*
sudo chmod -R 755 /var/www/bangrondb/admin-panel/storage
sudo chmod -R 644 /var/www/bangrondb/admin-panel/.env
```

### Firewall Configuration

```bash
# UFW Firewall
sudo ufw enable
sudo ufw allow ssh
sudo ufw allow http
sudo ufw allow https
sudo ufw deny 8080
```

### PHP Security Configuration

```ini
# /etc/php/8.0/fpm/php.ini
display_errors = Off
log_errors = On
error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT
allow_url_fopen = Off
allow_url_include = Off
expose_php = Off
max_execution_time = 30
max_input_time = 30
memory_limit = 256M
post_max_size = 100M
upload_max_filesize = 50M
```

## 📊 Monitoring & Maintenance

### Health Checks

```bash
# System health check
curl -s https://yourdomain.com/api/v1/system/health

# Database health check
curl -s https://yourdomain.com/api/v1/databases/health

# Performance metrics
curl -s https://yourdomain.com/api/v1/system/metrics
```

### Log Management

```bash
# Configure log rotation
sudo nano /etc/logrotate.d/bangrondb

# Log rotation configuration
/var/www/bangrondb/admin-panel/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 www-data www-data
}
```

### Backup Strategy

```bash
# Daily backup script
#!/bin/bash
BACKUP_DIR="/var/backups/bangrondb"
DATE=$(date +%Y%m%d_%H%M%S)

# Create backup
mkdir -p $BACKUP_DIR
tar -czf $BACKUP_DIR/bangrondb_$DATE.tar.gz \
    --exclude="data/*.tmp" \
    --exclude="data/*.lock" \
    /var/www/bangrondb/admin-panel/data

# Keep only last 7 days
find $BACKUP_DIR -name "bangrondb_*.tar.gz" -mtime +7 -delete

# Upload to cloud (optional)
aws s3 sync $BACKUP_DIR s3://your-backup-bucket/bangrondb/
```

## 🚀 Performance Optimization

### Caching Configuration

```php
// Enable caching in configuration
return [
    'cache' => [
        'driver' => 'redis',
        'redis' => [
            'host' => '127.0.0.1',
            'password' => null,
            'port' => 6379,
            'database' => 0
        ]
    ]
];
```

### Database Optimization

```sql
-- Optimize SQLite database
VACUUM;
ANALYZE;
PRAGMA journal_mode=WAL;
PRAGMA synchronous=NORMAL;
PRAGMA cache_size=-10000;
PRAGMA temp_store=MEMORY;
```

### PHP-FPM Optimization

```ini
# /etc/php/8.0/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500
```

## 🔄 Update & Maintenance

### Update Process

```bash
# Backup before update
./backup.sh

# Pull latest changes
git pull origin main

# Update dependencies
composer install --no-dev --optimize-autoloader

# Clear cache
php artisan optimize:clear

# Run migrations
php artisan migrate

# Restart services
sudo systemctl restart nginx
sudo systemctl restart php8.0-fpm
```

### Maintenance Mode

```bash
# Enable maintenance mode
php artisan down

# Disable maintenance mode
php artisan up

# Custom maintenance page
php artisan down --message="We'll be back soon!" --retry=60
```

## 📋 Deployment Checklist

### Pre-Deployment

- [ ] Backup all databases
- [ ] Test in staging environment
- [ ] Review code changes
- [ ] Update documentation
- [ ] Check security settings
- [ ] Verify SSL certificates

### Deployment

- [ ] Deploy to staging first
- [ ] Run full test suite
- [ ] Check application health
- [ ] Monitor performance
- [ ] Verify all features work
- [ ] Check error logs

### Post-Deployment

- [ ] Monitor application performance
- [ ] Check error logs
- [ ] Verify backups
- [ ] Update monitoring dashboards
- [ ] Document changes
- [ ] Notify stakeholders

---

**Best Practices**:

1. **Always backup before deployment**
2. **Test in staging first**
3. **Use automated deployments**
4. **Monitor application health**
5. **Keep software updated**
6. **Regular security audits**
7. **Document all changes**
8. **Have rollback plan**

For support, contact: deployment-support@bangrondb.io
