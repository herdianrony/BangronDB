# Publishing BangronDB to Packagist

Panduan step-by-step untuk publish BangronDB ke Packagist.

## ✅ Pre-requisites

Sebelum mulai, pastikan Anda sudah memiliki:

- [ ] GitHub account (https://github.com)
- [ ] Packagist account (https://packagist.org)
- [ ] Local repository sudah siap dengan git tags

## 📋 Step 1: Create GitHub Repository

### 1.1 Buat repository baru di GitHub

1. Login ke GitHub (https://github.com)
2. Click **New** (atau pergi ke https://github.com/new)
3. Isi form:
   - **Repository name**: `BangronDB`
   - **Description**: `SQLite-based NoSQL document database with MongoDB-like API`
   - **Public** (pilih Public agar bisa di-Packagist)
   - Jangan inisialisasi dengan README/LICENSE/gitignore (kita sudah punya)
4. Click **Create repository**

### 1.2 Salin HTTPS URL

Akan ada instruksi untuk push repository lokal. Catat URL-nya, contohnya:

```
https://github.com/herdianrony/BangronDB.git
```

## 🚀 Step 2: Push ke GitHub

### 2.1 Add remote dan push branch

```powershell
cd E:\BangronDB

# Add remote origin
git remote add origin https://github.com/herdianrony/BangronDB.git

# Rename branch ke main (Packagist lebih prefer main)
git branch -M main

# Push branch ke GitHub
git push -u origin main

# Push tags
git push origin --tags
```

### 2.2 Verifikasi di GitHub

1. Buka https://github.com/herdianrony/BangronDB
2. Pastikan semua files ada
3. Pastikan branch main visible
4. Click pada **Releases** - seharusnya ada v1.0.0

Jika tidak ada releases, buat dari tags:

- Klik **Releases**
- Click **Draft a new release**
- Tag version: `v1.0.0`
- Release title: `BangronDB v1.0.0`
- Description: Copy dari CHANGELOG.md bagian v1.0.0
- Click **Publish release**

## 📦 Step 3: Register di Packagist

### 3.1 Create Packagist account

1. Pergi ke https://packagist.org
2. Click **Sign Up** (atau klik logo di kanan atas)
3. Fill form:
   - **Username**: Pilih username
   - **Email**: Email Anda
   - **Password**: Password yang kuat
4. Click **Register** dan verify email Anda

### 3.2 Submit repository

Setelah login ke Packagist:

1. Klik **Submit** di navigation
2. Paste GitHub repository URL:
   ```
   https://github.com/herdianrony/BangronDB.git
   ```
3. Click **Check**
4. Jika valid, akan muncul tombol **Submit**
5. Click **Submit**

Packagist akan:

- ✅ Analyze composer.json
- ✅ Create package page
- ✅ Setup GitHub webhook untuk auto-update

## 🔗 Step 4: Setup GitHub Webhook (Optional tapi Recommended)

Supaya Packagist otomatis update ketika ada push ke GitHub:

### 4.1 Manual setup di Packagist

1. Login ke Packagist
2. Buka package Anda: https://packagist.org/packages/herdianrony/bangrondb
3. Klik **Edit**
4. Akan ada **GitHub Service Hook URL**
5. Copy URL tersebut

### 4.2 Setup webhook di GitHub

1. Buka repository GitHub: https://github.com/herdianrony/BangronDB
2. Settings → **Webhooks**
3. Click **Add webhook**
4. Paste payload URL dari Packagist
5. Content type: **application/json**
6. Click **Add webhook**

Sekarang setiap kali push ke GitHub, Packagist otomatis update!

## 📊 Step 5: Verify Package is Live

### 5.1 Search package

1. Pergi ke https://packagist.org
2. Search box: `bangrondb` atau `herdianrony/bangrondb`
3. Seharusnya package muncul dengan detail:
   - Latest version
   - License (MIT)
   - Description
   - Downloads (akan 0 dulu)

### 5.2 Test installation

```powershell
# Create test directory
mkdir E:\BangronDB-Test
cd E:\BangronDB-Test

# Init composer project
composer init --no-interaction

# Install BangronDB
composer require herdianrony/bangrondb

# Verify
ls vendor/herdianrony/bangrondb/
```

## 📝 Step 6: Complete Package Metadata

### 6.1 Update README dengan badge

Add ke README.md bagian atas:

```markdown
# 📚 BangronDB

[![Packagist](https://img.shields.io/packagist/v/herdianrony/bangrondb.svg?style=flat-square)](https://packagist.org/packages/herdianrony/bangrondb)
[![Packagist Downloads](https://img.shields.io/packagist/dm/herdianrony/bangrondb.svg?style=flat-square)](https://packagist.org/packages/herdianrony/bangrondb)
[![License](https://img.shields.io/badge/license-MIT-green.svg?style=flat-square)](LICENSE)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D%208.0-blue.svg?style=flat-square)](https://www.php.net)
```

### 6.2 Add installation badge di README

````markdown
## 🚀 Instalasi Cepat

**Via Composer (Recommended):**

```bash
composer require herdianrony/bangrondb
```
````

````

Push perubahan README:

```powershell
cd E:\BangronDB
git add README.md
git commit -m "docs: add Packagist badges"
git push origin main
````

## 🔐 Security & Best Practices

### Setelah publish:

- [ ] Monitor package page untuk issues
- [ ] Set up GitHub Actions untuk automated tests
- [ ] Create branch protection rules
- [ ] Require PR reviews sebelum merge

### Update strategy:

Untuk release baru, gunakan Semantic Versioning:

```powershell
# Create release branch
git checkout -b develop

# Make changes...
git add .
git commit -m "feat: add new feature"

# Merge ke main
git checkout main
git merge develop

# Create tag
git tag -a v1.1.0 -m "Version 1.1.0 - Add new features"

# Push
git push origin main --tags
```

## 🎯 Final Checklist

- [ ] GitHub repository created
- [ ] Local code pushed to GitHub
- [ ] v1.0.0 tag exists dan di-push
- [ ] Packagist account created
- [ ] Package submitted ke Packagist
- [ ] Package findable di Packagist search
- [ ] Composer install test berhasil
- [ ] GitHub webhook configured
- [ ] README updated dengan badges

## 📚 Useful Links

- **Packagist Home**: https://packagist.org
- **Composer Docs**: https://getcomposer.org/doc/
- **Semantic Versioning**: https://semver.org/
- **Packagist Docs**: https://packagist.org/about
- **GitHub Webhooks**: https://docs.github.com/en/developers/webhooks-and-events/webhooks/creating-webhooks

## 🆘 Troubleshooting

### Package tidak muncul di Packagist

**Solutions:**

1. Pastikan URL repository benar
2. Pastikan composer.json valid
3. Pastikan punya LICENSE file
4. Try re-submit package

### Composer install gagal

```powershell
# Clear Packagist cache
composer clear-cache

# Try install lagi
composer require herdianrony/bangrondb
```

### Need help?

- Packagist: https://github.com/composer/packagist/issues
- Composer: https://github.com/composer/composer/issues

---

**Selamat! BangronDB sekarang ready untuk dipublish ke Packagist! 🎉**
