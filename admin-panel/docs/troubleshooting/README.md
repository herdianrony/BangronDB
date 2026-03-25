# Troubleshooting Guide - BangronDB Admin Panel

Panduan pemecahan masalah lengkap untuk BangronDB Admin Panel. Panduan ini membantu Anda mengidentifikasi, mendiagnosis, dan memperbaiki berbagai masalah yang mungkin terjadi.

## 📋 Troubleshooting Overview

### Common Issue Categories

| Category          | Frequency | Impact   |
| ----------------- | --------- | -------- |
| **Installation**  | High      | Critical |
| **Configuration** | High      | High     |
| **Performance**   | Medium    | High     |
| **Security**      | Low       | Critical |
| **Data**          | Medium    | Critical |
| **Connectivity**  | High      | High     |

### Troubleshooting Methodology

1. **Identify the Problem**: Gather symptoms and error messages
2. **Check Logs**: Review application and system logs
3. **Reproduce**: Try to reproduce the issue consistently
4. **Isolate**: Determine the root cause
5. **Fix**: Apply appropriate solution
6. **Verify**: Confirm the fix works
7. **Document**: Record the solution for future reference

## 🚨 Common Issues

### Installation Problems

#### PHP Version Issues

**Symptoms**:

- Composer fails with PHP version errors
- Application won't start
- Extension loading errors

**Solutions**:

```bash
# Check PHP version
php --version

# Install correct PHP version
# Ubuntu/Debian
sudo apt update
sudo apt install php8.0 php8.0-fpm php8.0-sqlite3 php8.0-zip php8.0-gd php8.0-curl

# CentOS/RHEL
sudo yum install php80 php80-fpm php80-sqlite3 php80-zip php80-gd php80-curl

# Verify extensions
php -m | grep -E "(pdo|sqlite|zip|gd|curl)"
```

**Configuration**:

```ini
# /etc/php/8.0/cli/php.ini
extension=pdo_sqlite
extension=zip
extension=gd
extension=curl
```

#### Permission Issues

**Symptoms**:

- "Permission denied" errors
- Can't write to data directory
- Upload failures

**Solutions**:

```bash
# Check permissions
ls -la /var/www/bangrondb/admin-panel/

# Fix permissions
sudo chown -R www-data:www-data /var/www/bangrondb
sudo chmod -R 755 /var/www/bangrondb
sudo chmod -R 644 /var/www/bangrondb/admin-panel/public/*
sudo chmod -R 755 /var/www/bangrondb/admin-panel/storage
sudo chmod -R 644 /var/www/bangrondb/admin-panel/.env

# Create data directory with correct permissions
mkdir -p /var/www/bangrondb/admin-panel/data
chmod 755 /var/www/bangrondb/admin-panel/data
```

#### Database Connection Issues

**Symptoms**:

- "Database not found" errors
- SQLite file permission errors
- Connection timeout errors

**Solutions**:

```bash
# Check database file permissions
ls -la /var/www/bangrondb/admin-panel/data/

# Create database directory
mkdir -p /var/www/bangrondb/admin-panel/data
chmod 755 /var/www/bangrondb/admin-panel/data

# Check SQLite installation
sqlite3 --version

# Test database connection
php -r "
try {
    \$pdo = new PDO('sqlite:/var/www/bangrondb/admin-panel/data/bangrondb.db');
    echo 'Database connection successful';
} catch (Exception \$e) {
    echo 'Database connection failed: ' . \$e->getMessage();
}
"
```

### Configuration Problems

#### Environment Variables

**Symptoms**:

- Application uses default values
- Features not working as expected
- Security warnings

**Solutions**:

```bash
# Check environment file
cat /var/www/bangrondb/admin-panel/.env

# Validate environment variables
php artisan env:check

# Generate new encryption key
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;" > .env

# Set correct permissions
chmod 600 /var/www/bangrondb/admin-panel/.env
```

#### PHP Configuration

**Symptoms**:

- Memory limit exceeded
- Upload size limits
- Execution time exceeded

**Solutions**:

```ini
# /etc/php/8.0/fpm/php.ini
memory_limit = 256M
upload_max_filesize = 100M
post_max_size = 100M
max_execution_time = 300
max_input_time = 300

# Restart PHP-FPM
sudo systemctl restart php8.0-fpm
```

#### Web Server Configuration

**Symptoms**:

- 404 errors
- Permission denied
- PHP not processing

**Solutions**:

**Nginx**:

```nginx
# Test configuration
sudo nginx -t

# Fix common issues
location ~ \.php$ {
    include snippets/fastcgi-php.conf;
    fastcgi_pass unix:/run/php/php8.0-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
    include fastcgi_params;
}
```

**Apache**:

```apache
# Test configuration
sudo apache2ctl configtest

# Enable modules
sudo a2enmod rewrite
sudo a2enmod headers

# Fix .htaccess
<Directory /var/www/bangrondb/admin-panel/public>
    AllowOverride All
    Require all granted
</Directory>
```

## 📊 Performance Issues

### Slow Performance

**Symptoms**:

- Slow page loads
- High CPU usage
- Database queries taking too long

**Solutions**:

```bash
# Check system resources
top
htop
free -h
df -h

# Check PHP-FPM status
sudo systemctl status php8.0-fpm

# Optimize PHP-FPM
# /etc/php/8.0/fpm/pool.d/www.conf
pm = dynamic
pm.max_children = 50
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
pm.max_requests = 500

# Optimize SQLite
sqlite3 /path/to/database.db "PRAGMA journal_mode=WAL;"
sqlite3 /path/to/database.db "PRAGMA synchronous=NORMAL;"
sqlite3 /path/to/database.db "PRAGMA cache_size=-10000;"
```

### Memory Issues

**Symptoms**:

- Memory limit exceeded errors
- Application crashes
- Slow performance

**Solutions**:

```bash
# Check memory usage
free -h
cat /proc/meminfo

# Increase PHP memory limit
# /etc/php/8.0/cli/php.ini
memory_limit = 512M

# /etc/php/8.0/fpm/php.ini
memory_limit = 512M

# Optimize application
# Enable caching
# Use lazy loading
# Optimize database queries
```

### Database Performance

**Symptoms**:

- Slow queries
- High disk I/O
- Lock timeouts

**Solutions**:

```sql
-- Analyze database performance
sqlite3 /path/to/database.db ".schema"

-- Optimize database
VACUUM;
ANALYZE;

-- Check indexes
sqlite3 /path/to/database.db "PRAGMA index_list('collection_name');"

-- Add indexes for frequently queried fields
CREATE INDEX idx_email ON users(email);
CREATE INDEX idx_name ON users(name);
CREATE INDEX idx_created_at ON users(created_at);
```

## 🔒 Security Issues

### Authentication Problems

**Symptoms**:

- Login failures
- Session timeouts
- 401 unauthorized errors

**Solutions**:

```bash
# Check session configuration
# /var/www/bangrondb/admin-panel/.env
SESSION_LIFETIME=120
SESSION_DRIVER=file

# Clear session cache
rm -rf /var/www/bangrondb/admin-panel/storage/framework/sessions/*

# Check authentication logs
tail -f /var/www/bangrondb/admin-panel/logs/auth.log

# Reset user password
php artisan tinker
>>> User::where('email', 'admin@example.com')->first()->update(['password' => bcrypt('new-password')]);
>>> exit
```

### Security Headers

**Symptoms**:

- Browser security warnings
- SSL issues
- CORS errors

**Solutions**:

```nginx
# Nginx security headers
add_header X-Frame-Options "SAMEORIGIN" always;
add_header X-Content-Type-Options "nosniff" always;
add_header X-XSS-Protection "1; mode=block" always;
add_header Referrer-Policy "no-referrer-when-downgrade" always;
add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;

# SSL configuration
ssl_protocols TLSv1.2 TLSv1.3;
ssl_ciphers ECDHE-RSA-AES128-GCM-SHA256:ECDHE-RSA-AES256-GCM-SHA384;
ssl_prefer_server_ciphers off;
```

### File Permissions

**Symptoms**:

- Upload failures
- File write errors
- Directory listing errors

**Solutions**:

```bash
# Correct permissions
sudo chown -R www-data:www-data /var/www/bangrondb
sudo chmod -R 755 /var/www/bangrondb
sudo chmod -R 644 /var/www/bangrondb/admin-panel/public/*
sudo chmod -R 755 /var/www/bangrondb/admin-panel/storage
sudo chmod -R 644 /var/www/bangrondb/admin-panel/.env

# Secure sensitive files
chmod 600 /var/www/bangrondb/admin-panel/.env
chmod 600 /var/www/bangrondb/admin-panel/storage/*.key
```

## 🗄️ Data Issues

### Database Corruption

**Symptoms**:

- Database errors
- Missing data
- Application crashes

**Solutions**:

```bash
# Check database integrity
sqlite3 /path/to/database.db "PRAGMA integrity_check;"

# Repair database
cp /path/to/database.db /path/to/database.db.backup
sqlite3 /path/to/database.db "VACUUM;"
sqlite3 /path/to/database.db "REINDEX;"

# Restore from backup
cp /path/to/backup.db /path/to/database.db

# Check for corruption
sqlite3 /path/to/database.db ".schema"
```

### Data Recovery

**Solutions**:

```bash
# Create backup before recovery
cp /path/to/database.db /path/to/database.db.recovery

# Try to recover
sqlite3 /path/to/database.db.recovery ".backup /path/to/database.db.recovered"

# Check recovered data
sqlite3 /path/to/database.db.recovered "SELECT COUNT(*) FROM users;"
```

### Import/Export Issues

**Symptoms**:

- Import failures
- Export errors
- Data format issues

**Solutions**:

```bash
# Validate import file
cat /path/to/import.json | jq .  # For JSON validation

# Check file permissions
ls -la /path/to/import.json

# Fix import issues
php artisan db:import /path/to/import.json --validate --dry-run

# Check for encoding issues
file /path/to/import.json
```

## 🌐 Connectivity Issues

### Network Problems

**Symptoms**:

- Connection timeouts
- DNS resolution failures
- SSL certificate errors

**Solutions**:

```bash
# Check network connectivity
ping yourdomain.com
curl -I https://yourdomain.com

# Check DNS
nslookup yourdomain.com
dig yourdomain.com

# Check SSL certificate
openssl s_client -connect yourdomain.com:443

# Test port connectivity
telnet yourdomain.com 443
```

### Firewall Issues

**Symptoms**:

- Connection refused
- Port blocked
- Security group issues

**Solutions**:

```bash
# Check firewall status
sudo ufw status
sudo firewall-cmd --list-all

# Allow necessary ports
sudo ufw allow 22/tcp    # SSH
sudo ufw allow 80/tcp    # HTTP
sudo ufw allow 443/tcp   # HTTPS

# Check iptables rules
sudo iptables -L -n -v
```

### Load Balancer Issues

**Symptoms**:

- Inconsistent behavior
- Session issues
- Health check failures

**Solutions**:

```bash
# Check load balancer health
curl -I http://load-balancer/health

# Check backend servers
curl -I http://backend-server1/
curl -I http://backend-server2/

# Check session configuration
# Ensure sticky sessions are enabled
# Check session timeout settings
```

## 🛠️ Debug Tools

### System Debugging

```bash
# System information
uname -a
lscpu
free -h
df -h
uptime

# Process monitoring
ps aux | grep php
top -p $(pgrep -f php)

# Network monitoring
netstat -tulpn
ss -tulpn
```

### Application Debugging

```bash
# Enable debug mode
# .env
APP_DEBUG=true
LOG_LEVEL=debug

# Check application logs
tail -f /var/www/bangrondb/admin-panel/logs/application.log
tail -f /var/www/bangrondb/admin-panel/logs/error.log

# Run diagnostics
php artisan diagnose
php artisan config:cache
php artisan route:clear
php artisan view:clear
```

### Database Debugging

```bash
# Check database logs
tail -f /var/www/bangrondb/admin-panel/logs/database.log

# Query debugging
sqlite3 /path/to/database.db ".schema"

# Performance analysis
EXPLAIN SELECT * FROM users WHERE email = 'test@example.com';
```

## 🔧 Error Reference

### Common Error Codes

| Error Code       | Description           | Solution                             |
| ---------------- | --------------------- | ------------------------------------ |
| `E_CONN_REFUSED` | Connection refused    | Check service status, firewall       |
| `E_TIMEOUT`      | Request timeout       | Increase timeout, check network      |
| `E_PERM_DENIED`  | Permission denied     | Check file permissions               |
| `E_DB_CORRUPT`   | Database corruption   | Restore from backup                  |
| `E_MEMORY_LIMIT` | Memory limit exceeded | Increase memory limit, optimize code |
| `E_VALIDATION`   | Validation failed     | Check input data, schema             |

### Error Messages

#### Database Errors

```bash
# "SQLite3: unable to open database file"
# Solution: Check file permissions and path
chmod 755 /path/to/database/directory
chmod 644 /path/to/database.db

# "SQLite3: database disk image is malformed"
# Solution: Restore from backup
cp /path/to/backup.db /path/to/database.db
```

#### PHP Errors

```bash
# "Fatal error: Allowed memory size of X bytes exhausted"
# Solution: Increase memory limit
# /etc/php/8.0/fpm/php.ini
memory_limit = 512M

# "Warning: include_once(): Failed opening"
# Solution: Check include paths and file existence
```

#### Nginx Errors

```bash
# "502 Bad Gateway"
# Solution: Check PHP-FPM status
sudo systemctl restart php8.0-fpm

# "404 Not Found"
# Solution: Check root directory and file existence
```

## 📋 Troubleshooting Checklist

### Quick Checklist

- [ ] Check system resources (CPU, memory, disk)
- [ ] Review application logs
- [ ] Verify configuration files
- [ ] Check file permissions
- [ ] Test database connectivity
- [ ] Check network connectivity
- [ ] Verify SSL certificates
- [ ] Check service status

### Detailed Checklist

#### System Health

- [ ] CPU usage < 80%
- [ ] Memory usage < 80%
- [ ] Disk usage < 90%
- [ ] Network connectivity
- [ ] Service status

#### Application Health

- [ ] Application logs clean
- [ ] Error logs minimal
- [ ] Configuration valid
- [ ] Database accessible
- [ ] File permissions correct

#### Security Health

- [ ] SSL certificates valid
- [ ] Security headers present
- [ ] File permissions secure
- [ ] Authentication working
- [ ] Session management secure

## 📞 Support Resources

### Getting Help

1. **Check Documentation**: Review this guide and other documentation
2. **Search Issues**: Check GitHub issues for similar problems
3. **Community Forum**: Post questions on the community forum
4. **Contact Support**: Email support@bangrondb.io

### Bug Reporting

When reporting bugs, include:

```markdown
## Environment

- OS: [Ubuntu 20.04]
- PHP Version: [8.0.2]
- Browser: [Chrome 90]
- BangronDB Version: [2.0.0]

## Steps to Reproduce

1. [Step 1]
2. [Step 2]
3. [Step 3]

## Expected Behavior

[What should happen]

## Actual Behavior

[What actually happens]

## Error Messages
```

[Error message here]

```

## Additional Context
[Any other relevant information]
```

### Performance Monitoring

```bash
# Monitor system performance
htop
iotop
nethogs

# Monitor application performance
php artisan tinker
>>> app('db')->getPdo()->getAttribute(PDO::ATTR_SERVER_INFO);
>>> exit
```

---

**Tips**:

1. **Always backup before making changes**
2. **Test changes in a development environment first**
3. **Document all changes and fixes**
4. **Monitor system performance regularly**
5. **Keep software updated**
6. **Use monitoring tools proactively**
7. **Have a rollback plan ready**

For urgent issues, contact: emergency-support@bangrondb.io
