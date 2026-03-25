# Getting Started Guide - BangronDB Admin Panel

Panduan ini akan membantu Anda memulai penggunaan BangronDB Admin Panel dari awal. Ikuti langkah-langkah dengan seksama untuk memastikan instalasi dan konfigurasi berjalan lancar.

## 📋 Prerequisites Checklist

Sebelum memulai, pastikan Anda telah memenuhi persyaratan berikut:

### System Requirements

- **Operating System**: Windows 10+, macOS 10.14+, atau Linux (Ubuntu 18.04+)
- **PHP**: 8.0 atau lebih tinggi
- **Composer**: 2.0 atau lebih tinggi
- **Web Server**: Apache 2.4+ atau Nginx 1.18+ (opsional, untuk production)
- **Database**: SQLite 3 (tersedia di hampir semua sistem)
- **Memory**: Minimal 512MB RAM (1GB direkomendasikan)

### PHP Extensions

Pastikan ekstensi PHP berikut diaktifkan:

```bash
# Check PHP extensions
php -m | grep -E "(pdo|json|openssl|mbstring|curl|session)"

# Aktifkan jika belum tersedia (contoh Ubuntu)
sudo apt-get install php8.0-pdo php8.0-pdo-sqlite php8.0-json php8.0-openssl php8.0-mbstring php8.0-curl
```

### Development Tools

- **Code Editor**: VS Code, PhpStorm, atau editor pilihan Anda
- **Git**: Untuk manajemen versi
- **Browser**: Chrome 90+, Firefox 88+, Safari 14+, atau Edge 90+

## 🚀 Installation Process

### Method 1: Via Composer (Recommended)

```bash
# Install BangronDB
composer require bangrondb/bangrondb

# Install admin panel
composer create-project bangrondb/admin-panel my-admin-panel

# Masuk ke direktori
cd my-admin-panel
```

### Method 2: Manual Installation

```bash
# Clone repository
git clone https://github.com/bangrondb/bangrondb.git
cd bangrondb

# Copy admin panel
cp -r admin-panel my-admin-panel
cd my-admin-panel

# Install dependencies
composer install
```

### Method 3: Download ZIP

1. Download [latest release](https://github.com/bangrondb/bangrondb/releases/latest)
2. Extract ZIP file
3. Navigate to `admin-panel` directory
4. Run `composer install`

## ⚙️ Initial Setup

### 1. Environment Configuration

```bash
# Copy environment template
cp .env.example .env

# Edit environment file
nano .env  # atau gunakan editor pilihan Anda
```

Edit file `.env` dengan konfigurasi berikut:

```env
# Application Configuration
APP_NAME="BangronDB Admin Panel"
APP_ENV=development
APP_DEBUG=true
APP_URL=http://localhost:8080

# Database Configuration
DB_PATH=/var/data/bangrondb
DB_ENCRYPTION_KEY=your-super-secret-key-here

# Security Configuration
SESSION_LIFETIME=120
ENCRYPTION_CIPHER=AES-256-CBC

# Admin User
ADMIN_EMAIL=admin@bangrondb.local
ADMIN_PASSWORD=your-secure-password

# Optional: External API
API_BASE_URL=https://api.bangrondb.com
ANALYTICS_ENABLED=false
```

### 2. Generate Encryption Key

```bash
# Generate secure encryption key
php -r "echo bin2hex(random_bytes(32)) . PHP_EOL;" >> .env
```

### 3. Create Data Directory

```bash
# Create data directory
mkdir -p /var/data/bangrondb
chmod 755 /var/data/bangrondb

# Atau untuk development
mkdir -p ./data
chmod 755 ./data
```

### 4. Database Initialization

```bash
# Run setup command
php setup.php

# Atau akses browser dan ikuti setup wizard
```

## 🔐 First Login Setup

### 1. Access Admin Panel

Buka browser dan akses:

- Development: `http://localhost:8080`
- Production: `https://your-domain.com`

### 2. Setup Wizard

Ikuti wizard setup yang akan muncul:

1. **Welcome Screen**: Klik "Get Started"
2. **System Check**: Pastikan semua requirements terpenuhi
3. **Database Configuration**: Konfigurasi path database
4. **Admin User**: Buat akun administrator
5. **Security Settings**: Konfigurasi keamanan dasar
6. **Final Setup**: Klik "Complete Setup"

### 3. Initial Configuration

Setelah login pertama, lakukan konfigurasi awal:

```php
// Konfigurasi dasar di app/Config.php
return [
    'database' => [
        'path' => '/var/data/bangrondb',
        'encryption_key' => 'your-encryption-key'
    ],
    'security' => [
        'session_lifetime' => 120,
        'max_login_attempts' => 5
    ],
    'features' => [
        'enable_audit_logging' => true,
        'enable_realtime_updates' => true
    ]
];
```

## 🎯 Basic Navigation

### Dashboard Overview

Setelah login, Anda akan melihat dashboard utama dengan:

1. **System Overview**: Status kesehatan sistem
2. **Database Metrics**: Penggunaan database
3. **Recent Activity**: Aktivitas terbaru
4. **Quick Actions**: Akses cepat ke fitur utama

### Main Navigation Menu

| Menu            | Deskripsi          | Fitur                          |
| --------------- | ------------------ | ------------------------------ |
| **Dashboard**   | Overview sistem    | Metrics, charts, notifications |
| **Databases**   | Manajemen database | CRUD databases, import/export  |
| **Collections** | Manajemen koleksi  | Schema, documents, queries     |
| **Documents**   | Manajemen dokumen  | Editor, search, bulk ops       |
| **Users**       | Manajemen pengguna | Roles, permissions, 2FA        |
| **Security**    | Keamanan sistem    | Audit logs, encryption         |
| **Monitoring**  | Monitoring sistem  | Performance, alerts            |
| **Settings**    | Pengaturan sistem  | Configuration, backup          |

### Basic Workflow

1. **Create Database**: Dashboard → Databases → Create Database
2. **Create Collection**: Databases → Select DB → Collections → Create
3. **Insert Documents**: Collections → Select → Documents → Insert
4. **Query Data**: Documents → Search/Filter → Export

## 🔧 First Configuration

### 1. Database Setup

```bash
# Create first database
php artisan db:create myapp

# Create first collection
php artisan collection:create users --schema="name,email,age"
```

### 2. Sample Data Import

```json
// Import sample data
{
  "users": [
    { "name": "John Doe", "email": "john@example.com", "age": 30 },
    { "name": "Jane Smith", "email": "jane@example.com", "age": 25 }
  ]
}
```

### 3. Basic Queries

```php
// Query MongoDB-like
$users = $collection->find([
  'age' => ['$gt' => 20, '$lt' => 35],
  'email' => ['$regex' => '@example\.com$']
]);
```

## 📱 Mobile Setup

### Responsive Design

BangronDB Admin Panel dirancang responsif untuk:

- **Desktop**: Full layout dengan sidebar
- **Tablet**: Compact layout dengan collapsible menu
- **Mobile**: Mobile-first layout dengan hamburger menu

### Mobile Features

- Touch-friendly interface
- Swipe gestures
- Mobile-optimized charts
- Offline capabilities (experimental)

## 🔄 Update & Migration

### Update Process

```bash
# Update BangronDB
composer update bangrondb/bangrondb

# Run migrations
php artisan migrate

# Clear cache
php artisan cache:clear
```

### Migration Guide

Untuk update versi, lihat [Migration Guide](../migration-upgrade.md).

## 🚀 Production Deployment

Untuk deployment ke production, lihat [Production Deployment Guide](../deployment/production.md).

## 📞 Support & Resources

### Getting Help

1. **Documentation**: Lihat dokumentasi lengkap di sini
2. **Community Forum**: [forum.bangrondb.io](https://forum.bangrondb.io)
3. **GitHub Issues**: [github.com/bangrondb/bangrondb/issues](https://github.com/bangrondb/bangrondb/issues)
4. **Email Support**: support@bangrondb.io

### Common Issues

- **Permission Errors**: Pastikan web server memiliki write permission ke data directory
- **PHP Extensions**: Pastikan semua required extensions diaktifkan
- **Database Path**: Pastikan path database valid dan writable

## 🎉 Next Steps

Setelah completing setup awal:

1. **Explore Features**: Jelajahi fitur-fitur yang tersedia
2. **Read User Manual**: Lihat [User Manual](user-manual.md) untuk panduan lengkap
3. **Learn Advanced Features**: Baca [Advanced Features](../advanced.md)
4. **Join Community**: Bergabung dengan komunitas kami

---

**Selamat menggunakan BangronDB Admin Panel!** Jika Anda mengalami kesulitan, jangan ragu untuk mencari bantuan melalui saluran support yang tersedia.
