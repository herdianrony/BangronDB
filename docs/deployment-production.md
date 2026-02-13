# Deployment & Production Setup

Panduan lengkap untuk deploy BangronDB di production environment dengan high availability, monitoring, dan best practices.

## Server Requirements

### Minimum Requirements

- **OS**: Linux (Ubuntu 20.04+, CentOS 7+, Debian 10+)
- **PHP**: 8.0+ with extensions:
  - `pdo_sqlite`
  - `openssl`
  - `mbstring`
  - `json`
- **Storage**: 10GB+ SSD storage
- **RAM**: 512MB minimum, 1GB+ recommended
- **CPU**: 1 core minimum, 2+ cores recommended

### Recommended Production Setup

```bash
# Ubuntu/Debian setup
sudo apt update
sudo apt install php8.1 php8.1-sqlite3 php8.1-openssl php8.1-mbstring
sudo apt install sqlite3 nginx

# Verify installations
php --version
sqlite3 --version
```

## Directory Structure

### Production Layout

```
/var/www/myapp/
├── public/
│   ├── index.php
│   └── assets/
├── src/
│   ├── Models/
│   └── Services/
├── config/
│   ├── database.php
│   └── app.php
├── data/
│   ├── bangrondb/
│   │   ├── app.db
│   │   ├── users.db
│   │   └── logs.db
│   └── backups/
├── logs/
├── vendor/
└── composer.json
```

### Permission Setup

```bash
# Set proper permissions
sudo chown -R www-data:www-data /var/www/myapp/data
sudo chmod -R 755 /var/www/myapp/data
sudo chmod -R 777 /var/www/myapp/data/bangrondb  # For SQLite write access

# Create backup directory
sudo mkdir -p /var/www/myapp/data/backups
sudo chown www-data:www-data /var/www/myapp/data/backups
```

## Configuration

### Environment Variables

```bash
# .env file
BANGRONDB_PATH=/var/www/myapp/data/bangrondb
BANGRONDB_ENCRYPTION_KEY=your-super-secure-encryption-key-here
BANGRONDB_DEFAULT_DB=app
BANGRONDB_BACKUP_RETENTION=30
BANGRONDB_MAX_CONNECTIONS=100
```

### PHP Configuration

```ini
# php.ini production settings
memory_limit = 256M
max_execution_time = 300
upload_max_filesize = 50M
post_max_size = 50M

# SQLite optimizations
pdo_sqlite.default_fetch_mode = 2
```

### Application Config

```php
<?php
// config/database.php

return [
    'bangrondb' => [
        'path' => env('BANGRONDB_PATH', '/var/www/myapp/data/bangrondb'),
        'encryption_key' => env('BANGRONDB_ENCRYPTION_KEY'),
        'default_database' => env('BANGRONDB_DEFAULT_DB', 'app'),
        'options' => [
            'timeout' => 30,
            'persistent' => true,
        ],
        'health_check' => [
            'enabled' => true,
            'interval' => 60, // seconds
        ]
    ]
];
```

## Nginx Configuration

```nginx
# /etc/nginx/sites-available/myapp

server {
    listen 80;
    server_name myapp.com www.myapp.com;
    root /var/www/myapp/public;
    index index.php;

    # Security headers
    add_header X-Frame-Options DENY;
    add_header X-Content-Type-Options nosniff;
    add_header X-XSS-Protection "1; mode=block";

    # PHP handling
    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;

        # Additional security
        fastcgi_param PHP_VALUE "open_basedir=/var/www/myapp:/tmp";
    }

    # Static files
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }

    # Deny access to sensitive files
    location ~ /(data|config|logs)/ {
        deny all;
        return 404;
    }

    # Logs
    access_log /var/log/nginx/myapp_access.log;
    error_log /var/log/nginx/myapp_error.log;
}
```

## Database Initialization

### Automated Setup Script

```php
<?php
// scripts/setup-database.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use BangronDB\Client;

$config = require __DIR__ . '/../config/database.php';
$client = new Client($config['bangrondb']['path']);

// Create databases
$appDb = $client->selectDB('app');
$userDb = $client->selectDB('users');
$logDb = $client->selectDB('logs');

// Setup collections with configurations
setupAppDatabase($appDb);
setupUserDatabase($userDb);
setupLogDatabase($logDb);

echo "Database setup completed!\n";

function setupAppDatabase($db) {
    $products = $db->selectCollection('products');
    $products->setSchema([
        'name' => ['required' => true, 'type' => 'string', 'min' => 1, 'max' => 255],
        'price' => ['required' => true, 'type' => 'float', 'min' => 0],
        'category' => ['type' => 'string'],
        'stock' => ['type' => 'int', 'min' => 0],
    ]);
    $products->setSearchableFields(['name', 'category'], true);
    $products->useSoftDeletes(true);
    $products->saveConfiguration();

    $orders = $db->selectCollection('orders');
    $orders->setIdModePrefix('ORD');
    $orders->setSchema([
        'customer_id' => ['required' => true, 'type' => 'string'],
        'items' => ['required' => true, 'type' => 'array'],
        'total' => ['required' => true, 'type' => 'float'],
        'status' => ['enum' => ['pending', 'paid', 'shipped', 'delivered']]
    ]);
    $orders->saveConfiguration();
}

function setupUserDatabase($db) {
    $users = $db->selectCollection('users');
    $users->setIdModePrefix('USR');
    $users->setEncryptionKey('user-data-encryption-key');
    $users->setSchema([
        'username' => ['required' => true, 'type' => 'string', 'min' => 3, 'max' => 50],
        'email' => ['required' => true, 'type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
        'password_hash' => ['required' => true, 'type' => 'string'],
        'role' => ['enum' => ['user', 'admin', 'moderator']],
    ]);
    $users->setSearchableFields(['email'], true); // Encrypted search
    $users->useSoftDeletes(true);
    $users->saveConfiguration();
}

function setupLogDatabase($db) {
    $logs = $db->selectCollection('access_logs');
    $logs->setSchema([
        'user_id' => ['type' => 'string'],
        'action' => ['required' => true, 'type' => 'string'],
        'resource' => ['type' => 'string'],
        'ip_address' => ['type' => 'string'],
        'user_agent' => ['type' => 'string'],
        'timestamp' => ['required' => true, 'type' => 'string']
    ]);
    $logs->createIndex('timestamp');
    $logs->createIndex('user_id');
    $logs->saveConfiguration();
}
```

## Backup Strategy

### Automated Backup System

```php
<?php
// scripts/backup.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

use BangronDB\Client;

$config = require __DIR__ . '/../config/database.php';
$client = new Client($config['bangrondb']['path']);
$backupDir = __DIR__ . '/../data/backups';

class BackupManager {
    private $client;
    private $backupDir;
    private $retentionDays;

    public function __construct(Client $client, string $backupDir, int $retentionDays = 30) {
        $this->client = $client;
        $this->backupDir = $backupDir;
        $this->retentionDays = $retentionDays;
    }

    public function createFullBackup(): string {
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $this->backupDir . "/full_backup_{$timestamp}";

        if (!is_dir($backupPath)) {
            mkdir($backupPath, 0755, true);
        }

        // Get all databases
        $databases = glob($this->client->path . '/*.bangron');
        $databases = array_map('basename', $databases);
        $databases = array_map(function($db) {
            return str_replace('.bangron', '', $db);
        }, $databases);

        foreach ($databases as $dbName) {
            $this->backupDatabase($dbName, $backupPath);
        }

        $this->cleanupOldBackups();
        return $backupPath;
    }

    private function backupDatabase(string $dbName, string $backupPath): void {
        $sourcePath = $this->client->path . "/{$dbName}.bangron";
        $destPath = $backupPath . "/{$dbName}.bangron";

        if (file_exists($sourcePath)) {
            copy($sourcePath, $destPath);
            // Also backup collection configurations
            $this->backupCollectionConfigs($dbName, $backupPath);
        }
    }

    private function backupCollectionConfigs(string $dbName, string $backupPath): void {
        $db = $this->client->selectDB($dbName);
        $configs = $db->getAllCollectionConfigs();

        if (!empty($configs)) {
            $configPath = $backupPath . "/{$dbName}_configs.json";
            file_put_contents($configPath, json_encode($configs, JSON_PRETTY_PRINT));
        }
    }

    private function cleanupOldBackups(): void {
        $backups = glob($this->backupDir . '/full_backup_*');
        $cutoff = time() - ($this->retentionDays * 24 * 60 * 60);

        foreach ($backups as $backup) {
            if (filemtime($backup) < $cutoff) {
                $this->deleteDirectory($backup);
            }
        }
    }

    private function deleteDirectory(string $dir): void {
        if (!is_dir($dir)) return;

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}

// Run backup
$backupManager = new BackupManager($client, $backupDir, 30);
$backupPath = $backupManager->createFullBackup();

echo "Backup created: {$backupPath}\n";
```

### Cron Job untuk Backup

```bash
# crontab -e

# Daily backup at 2 AM
0 2 * * * /usr/bin/php /var/www/myapp/scripts/backup.php >> /var/log/bangrondb_backup.log 2>&1

# Health check every 5 minutes
*/5 * * * * /usr/bin/php /var/www/myapp/scripts/health-check.php >> /var/log/bangrondb_health.log 2>&1
```

## Monitoring & Health Checks

### Health Check Script

```php
<?php
// scripts/health-check.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/database.php';
$client = new Client($config['bangrondb']['path']);

$issues = [];
$warnings = [];

// Check database connectivity
try {
    $databases = ['app', 'users', 'logs'];
    foreach ($databases as $dbName) {
        $db = $client->selectDB($dbName);
        $health = $db->getHealthReport();

        if ($health['status'] === 'critical') {
            $issues[] = "Database {$dbName}: " . implode(', ', $health['issues']);
        } elseif ($health['status'] === 'warning') {
            $warnings[] = "Database {$dbName}: " . implode(', ', $health['warnings']);
        }
    }
} catch (Exception $e) {
    $issues[] = "Database connection failed: " . $e->getMessage();
}

// Check disk space
$diskFree = disk_free_space($config['bangrondb']['path']);
$diskTotal = disk_total_space($config['bangrondb']['path']);
$diskUsagePercent = (($diskTotal - $diskFree) / $diskTotal) * 100;

if ($diskUsagePercent > 90) {
    $issues[] = "Disk usage: {$diskUsagePercent}% (Critical)";
} elseif ($diskUsagePercent > 80) {
    $warnings[] = "Disk usage: {$diskUsagePercent}% (Warning)";
}

// Report status
if (!empty($issues)) {
    echo "CRITICAL: " . implode('; ', $issues) . "\n";
    exit(2);
} elseif (!empty($warnings)) {
    echo "WARNING: " . implode('; ', $warnings) . "\n";
    exit(1);
} else {
    echo "OK: All systems healthy\n";
    exit(0);
}
```

## Performance Optimization

### SQLite Tuning

```sql
-- PRAGMA settings for production
PRAGMA journal_mode = WAL;
PRAGMA synchronous = NORMAL;
PRAGMA cache_size = -2000; -- 2MB cache
PRAGMA temp_store = MEMORY;
PRAGMA mmap_size = 268435456; -- 256MB memory map
PRAGMA page_size = 4096;
```

### Connection Pooling

```php
<?php
// Connection pooling for high-traffic applications

class DatabasePool {
    private static $pools = [];
    private $connections = [];
    private $maxConnections;
    private $databasePath;

    public function __construct(string $databasePath, int $maxConnections = 10) {
        $this->databasePath = $databasePath;
        $this->maxConnections = $maxConnections;
    }

    public static function getPool(string $databasePath): self {
        if (!isset(self::$pools[$databasePath])) {
            self::$pools[$databasePath] = new self($databasePath);
        }
        return self::$pools[$databasePath];
    }

    public function getConnection(string $databaseName): Database {
        $key = $databaseName;

        if (!isset($this->connections[$key]) || count($this->connections[$key]) === 0) {
            return $this->createConnection($databaseName);
        }

        return array_pop($this->connections[$key]);
    }

    public function releaseConnection(Database $connection, string $databaseName): void {
        $key = $databaseName;

        if (count($this->connections[$key] ?? []) < $this->maxConnections) {
            $this->connections[$key][] = $connection;
        } else {
            $connection->close();
        }
    }

    private function createConnection(string $databaseName): Database {
        $client = new Client($this->databasePath);
        return $client->selectDB($databaseName);
    }
}
```

## Security Hardening

### File Permissions

```bash
# Secure file permissions
find /var/www/myapp/data -type f -name "*.bangron" -exec chmod 600 {} \;
find /var/www/myapp/data -type d -exec chmod 700 {} \;

# Encryption key security
# Store encryption keys in secure location
echo "BANGRONDB_ENCRYPTION_KEY=your-key-here" | sudo tee /etc/environment.d/bangrondb
sudo chmod 600 /etc/environment.d/bangrondb
```

### Network Security

```nginx
# Additional security for database endpoints
location /api/database/ {
    # IP whitelist
    allow 192.168.1.0/24;
    allow 10.0.0.0/8;
    deny all;

    # Rate limiting
    limit_req zone=api burst=10 nodelay;

    # Authentication required
    auth_basic "Database API";
    auth_basic_user_file /etc/nginx/.htpasswd;
}
```

## Scaling Strategies

### Read Replicas (SQLite Limitations)

SQLite tidak mendukung built-in replication. Untuk high availability:

1. **Application-level replication**
2. **Regular backups dengan failover**
3. **Load balancer dengan multiple app servers**
4. **Database sharding** (manual partitioning)

### Horizontal Scaling Approach

```php
<?php
// Sharding strategy for high-volume data

class ShardManager {
    private $shards = [];

    public function __construct() {
        // Define shards
        $this->shards = [
            'shard1' => ['host' => 'db1.example.com', 'range' => [0, 999999]],
            'shard2' => ['host' => 'db2.example.com', 'range' => [1000000, 1999999]],
            // ... more shards
        ];
    }

    public function getShardForId(string $id): string {
        // Simple hash-based sharding
        $hash = crc32($id);
        $shardIndex = $hash % count($this->shards);
        return array_keys($this->shards)[$shardIndex];
    }

    public function getConnection(string $id): Database {
        $shard = $this->getShardForId($id);
        return DatabasePool::getPool($this->shards[$shard]['host'])->getConnection('app');
    }
}
```

## Monitoring Dashboard

### Web-based Monitoring

```php
<?php
// public/monitoring.php

require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/../config/database.php';

$config = require __DIR__ . '/../config/database.php';
$client = new Client($config['bangrondb']['path']);

function getSystemStatus() {
    global $client;

    $status = [
        'timestamp' => date('c'),
        'databases' => [],
        'system' => [
            'disk_free' => disk_free_space($config['bangrondb']['path']),
            'disk_total' => disk_total_space($config['bangrondb']['path']),
            'load_average' => sys_getloadavg(),
        ]
    ];

    // Get database status
    $databases = glob($config['bangrondb']['path'] . '/*.bangron');
    foreach ($databases as $dbPath) {
        $dbName = basename($dbPath, '.bangron');
        $db = $client->selectDB($dbName);

        $status['databases'][$dbName] = [
            'health' => $db->getHealthReport(),
            'metrics' => $db->getDataMetrics(),
            'collections' => count($db->getCollectionNames())
        ];
    }

    return $status;
}

// JSON API endpoint
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    header('Content-Type: application/json');
    echo json_encode(getSystemStatus());
}
```

Dengan setup production yang tepat, BangronDB dapat menangani traffic tinggi dengan performa dan reliability yang excellent. Pastikan untuk selalu melakukan backup regular, monitoring kontinyu, dan update security secara berkala.
