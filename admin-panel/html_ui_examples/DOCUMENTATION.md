# 📚 Dokumentasi Lengkap BangronDB Admin Panel

## Daftar Isi

1. [Overview](#overview)
2. [Struktur File](#struktur-file)
3. [Halaman Login](#1-halaman-login)
4. [Halaman Dashboard](#2-halaman-dashboard)
5. [Halaman Databases](#3-halaman-databases)
6. [Halaman Collections](#4-halaman-collections)
7. [Halaman Schema Builder](#5-halaman-schema-builder)
8. [Halaman Documents](#6-halaman-documents)
9. [Halaman Query Playground](#7-halaman-query-playground)
10. [Halaman Users](#8-halaman-users)
11. [Halaman Roles](#9-halaman-roles)
12. [Halaman Monitoring](#10-halaman-monitoring)
13. [Halaman Security](#11-halaman-security)
14. [Halaman Settings](#12-halaman-settings)
15. [Halaman Profile](#13-halaman-profile)
16. [Halaman Hooks & Events](#14-halaman-hooks--events)
17. [Halaman Backup & Restore](#15-halaman-backup--restore)
18. [Halaman Import/Export](#16-halaman-importexport)
19. [Halaman Logs](#17-halaman-logs)
20. [Halaman Notifications](#18-halaman-notifications)
21. [Halaman Terminal](#19-halaman-terminal)
22. [Halaman API Docs](#20-halaman-api-docs)
23. [Halaman Relationships](#21-halaman-relationships)
24. [Integrasi PHP](#integrasi-php)
25. [API Endpoints](#api-endpoints)

---

## Overview

BangronDB Admin Panel adalah antarmuka pengguna berbasis web untuk mengelola database BangronDB. Panel ini menyediakan:

- **Visual Database Management** - Kelola database dan collection tanpa kode
- **Schema Builder** - Desain schema dengan drag-and-drop
- **Document Editor** - CRUD dokumen dengan form builder
- **Query Playground** - Test query MongoDB-style
- **User Management** - Kelola pengguna dan permissions
- **Monitoring** - Pantau kesehatan dan performa database
- **Security** - Kelola enkripsi dan audit log

### Teknologi yang Digunakan

| Komponen | Teknologi |
|----------|-----------|
| CSS Framework | Tailwind CSS (CDN) |
| Icons | Lucide Icons (CDN) |
| JavaScript | Vanilla JS (ES6+) |
| Layout | CSS Grid & Flexbox |
| Theme | Dark Mode dengan Glass Morphism |

### Cara Menjalankan

```bash
# Buka langsung di browser
open html_templates/index.html

# Atau gunakan live server
npx serve html_templates
```

---

## Struktur File

```
html_templates/
├── DOCUMENTATION.md      # Dokumentasi ini
├── README.md             # Quick start guide
├── login.html            # Halaman login
├── index.html            # Dashboard utama
├── profile.html          # Profil pengguna
├── databases.html        # Manajemen database
├── collections.html      # Manajemen collection
├── schema-builder.html   # Visual schema designer
├── documents.html        # Document explorer
├── query-playground.html # Query testing
├── users.html            # User management
├── roles.html            # Role & permissions
├── monitoring.html       # System monitoring
├── security.html         # Security & encryption
├── settings.html         # System settings
├── hooks.html            # Hooks & events management
├── backup.html           # Backup & restore
├── import-export.html    # Data import/export
├── logs.html             # Logs & audit trail
├── notifications.html    # Notification center (NEW)
├── terminal.html         # Interactive terminal (NEW)
├── api-docs.html         # API documentation (NEW)
└── relationships.html    # Visual relationship diagram (NEW)
```

---

## 1. Halaman Login

**File:** `login.html`

### Deskripsi
Halaman autentikasi untuk masuk ke admin panel dengan tampilan modern dan animasi background.

### Komponen UI

| Komponen | Deskripsi |
|----------|-----------|
| Logo | Branding BangronDB di bagian atas |
| Email Input | Field untuk email dengan validasi format |
| Password Input | Field password dengan toggle show/hide |
| Remember Me | Checkbox untuk menyimpan session |
| Forgot Password | Link untuk reset password |
| Login Button | Tombol submit dengan loading state |
| Social Login | Opsi login via GitHub, Google, GitLab |
| Sign Up Link | Link ke halaman registrasi |
| Animated Background | Gradient orbs yang bergerak |

### Fungsi JavaScript

```javascript
// Toggle password visibility
function togglePassword() {
    const input = document.getElementById('password');
    const icon = document.getElementById('toggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.setAttribute('data-lucide', 'eye-off');
    } else {
        input.type = 'password';
        icon.setAttribute('data-lucide', 'eye');
    }
    lucide.createIcons();
}

// Handle form submit
function handleLogin(e) {
    e.preventDefault();
    const email = document.getElementById('email').value;
    const password = document.getElementById('password').value;
    
    // Show loading state
    const btn = document.getElementById('loginBtn');
    btn.innerHTML = '<span class="animate-spin">⏳</span> Signing in...';
    btn.disabled = true;
    
    // Simulate API call
    setTimeout(() => {
        // Redirect to dashboard
        window.location.href = 'index.html';
    }, 1500);
}
```

### Integrasi PHP

```php
// login.php
<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL);
    $password = $_POST['password'];
    
    // Validasi dengan database
    $db = new BangronDB\Client('/path/to/db');
    $users = $db->auth->users;
    
    $user = $users->findOne(['email' => $email]);
    
    if ($user && password_verify($password, $user['password'])) {
        $_SESSION['user_id'] = $user['_id'];
        $_SESSION['user_role'] = $user['role'];
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid email or password';
    }
}
?>
```

### Validasi Form

| Field | Validasi |
|-------|----------|
| Email | Required, format email valid |
| Password | Required, min 8 karakter |

### Event Handlers

| Event | Action |
|-------|--------|
| Form Submit | Validasi & kirim ke server |
| Toggle Password | Show/hide password |
| Enter Key | Submit form |
| Social Button Click | Redirect ke OAuth provider |

---

## 2. Halaman Dashboard

**File:** `index.html`

### Deskripsi
Halaman utama yang menampilkan overview sistem, statistik database, dan aktivitas terbaru.

### Komponen UI

#### A. Sidebar Navigation

| Item | Icon | Target |
|------|------|--------|
| Dashboard | LayoutDashboard | index.html |
| Databases | Database | databases.html |
| Collections | Folder | collections.html |
| Documents | FileText | documents.html |
| Query Playground | Terminal | query-playground.html |
| Schema Builder | Blocks | schema-builder.html |
| Users | Users | users.html |
| Roles | Shield | roles.html |
| Monitoring | Activity | monitoring.html |
| Security | Lock | security.html |
| Settings | Settings | settings.html |

#### B. Header

| Komponen | Fungsi |
|----------|--------|
| Sidebar Toggle | Collapse/expand sidebar |
| Search Bar | Global search |
| Notifications | Dropdown notifikasi |
| Profile Menu | User menu & logout |

#### C. Statistics Cards

| Card | Data | Icon |
|------|------|------|
| Total Databases | Jumlah file .bangron | Database |
| Total Collections | Jumlah tabel | Folder |
| Total Documents | Jumlah record | FileText |
| Storage Used | Ukuran penyimpanan | HardDrive |

#### D. Database Overview Table

| Kolom | Deskripsi |
|-------|-----------|
| Name | Nama database |
| Collections | Jumlah collection |
| Documents | Total dokumen |
| Size | Ukuran file |
| Encryption | Status enkripsi (badge) |
| Health | Status kesehatan (badge) |
| Actions | View, Settings |

#### E. System Health Panel

| Metric | Visual |
|--------|--------|
| CPU Usage | Progress bar + persentase |
| Memory Usage | Progress bar + persentase |
| Disk Usage | Progress bar + persentase |
| Database Connections | Progress bar + count |

#### F. Activity Feed

| Kolom | Deskripsi |
|-------|-----------|
| Time | Waktu aktivitas |
| User | Avatar + nama |
| Action | Tipe aksi (INSERT, UPDATE, DELETE, LOGIN) |
| Description | Detail aktivitas |
| Badge | Warna berdasarkan tipe |

### Fungsi JavaScript

```javascript
// Toggle sidebar collapse
function toggleSidebar() {
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    sidebar.classList.toggle('w-64');
    sidebar.classList.toggle('w-20');
    // Toggle text visibility
    document.querySelectorAll('.sidebar-text').forEach(el => {
        el.classList.toggle('hidden');
    });
}

// Refresh stats
async function refreshStats() {
    const response = await fetch('/api/stats');
    const data = await response.json();
    
    document.getElementById('totalDatabases').textContent = data.databases;
    document.getElementById('totalCollections').textContent = data.collections;
    document.getElementById('totalDocuments').textContent = data.documents;
    document.getElementById('storageUsed').textContent = formatBytes(data.storage);
}

// Format bytes to human readable
function formatBytes(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Update activity feed
function addActivity(activity) {
    const feed = document.getElementById('activityFeed');
    const item = createActivityItem(activity);
    feed.insertBefore(item, feed.firstChild);
    
    // Keep only last 10 items
    while (feed.children.length > 10) {
        feed.removeChild(feed.lastChild);
    }
}
```

### Integrasi PHP

```php
// dashboard.php
<?php
require_once 'vendor/autoload.php';

use BangronDB\Client;

$client = new Client('/var/www/data/databases');

// Get statistics
$stats = [
    'databases' => count($client->listDBs()),
    'collections' => 0,
    'documents' => 0,
    'storage' => 0
];

foreach ($client->listDBs() as $dbName) {
    $db = $client->selectDB($dbName);
    $metrics = $db->getHealthMetrics();
    
    $stats['collections'] += $metrics['metrics']['total_collections'];
    $stats['documents'] += $metrics['metrics']['total_documents'];
    $stats['storage'] += $metrics['metrics']['total_size_bytes'];
}

// Get recent activity from audit log
$auditDb = $client->selectDB('_audit');
$activities = $auditDb->logs->find()
    ->sort(['timestamp' => -1])
    ->limit(10)
    ->toArray();
?>
```

### Auto Refresh

| Data | Interval |
|------|----------|
| Stats Cards | 30 detik |
| Health Metrics | 10 detik |
| Activity Feed | 5 detik |

---

## 3. Halaman Databases

**File:** `databases.html`

### Deskripsi
Halaman untuk mengelola file database BangronDB (.bangron files).

### Komponen UI

#### A. Header Actions

| Tombol | Fungsi |
|--------|--------|
| Create Database | Buka modal create |
| Import Database | Upload file .bangron |
| Refresh | Reload daftar database |

#### B. Database Cards

| Elemen | Deskripsi |
|--------|-----------|
| Icon | Database icon dengan warna |
| Name | Nama database |
| Path | Lokasi file |
| Stats | Collections count, Documents count |
| Size | Ukuran file |
| Encryption Badge | 🔒 jika terenkripsi |
| Health Badge | Healthy/Warning/Error |
| Last Modified | Waktu modifikasi terakhir |
| Actions | Open, Settings, Backup, Delete |

#### C. Create Database Modal

| Field | Tipe | Validasi |
|-------|------|----------|
| Database Name | Text | Required, alphanumeric, 3-50 chars |
| Enable Encryption | Toggle | Optional |
| Encryption Key | Password | Required if encryption enabled, min 16 chars |
| Confirm Key | Password | Must match encryption key |

#### D. Database Settings Modal

| Tab | Konten |
|-----|--------|
| General | Name, path, created date |
| Encryption | Key status, rotate key, disable |
| Backup | Auto backup schedule, retention |
| Advanced | WAL mode, page size, cache size |

#### E. Import Database Modal

| Field | Deskripsi |
|-------|-----------|
| File Input | Accept .bangron, .sqlite |
| New Name | Optional rename |
| Encryption Key | If file is encrypted |

### Fungsi JavaScript

```javascript
// Create new database
async function createDatabase(formData) {
    const name = formData.get('name');
    const encrypted = formData.get('encrypted') === 'on';
    const key = formData.get('encryptionKey');
    
    const response = await fetch('/api/databases', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ name, encrypted, key })
    });
    
    if (response.ok) {
        showToast('Database created successfully', 'success');
        refreshDatabaseList();
        closeModal('createDbModal');
    } else {
        const error = await response.json();
        showToast(error.message, 'error');
    }
}

// Delete database with confirmation
function deleteDatabase(name) {
    showConfirmDialog({
        title: 'Delete Database',
        message: `Are you sure you want to delete "${name}"? This action cannot be undone.`,
        confirmText: 'Delete',
        confirmClass: 'bg-red-600',
        onConfirm: async () => {
            await fetch(`/api/databases/${name}`, { method: 'DELETE' });
            showToast('Database deleted', 'success');
            refreshDatabaseList();
        }
    });
}

// Backup database
async function backupDatabase(name) {
    const response = await fetch(`/api/databases/${name}/backup`, {
        method: 'POST'
    });
    
    if (response.ok) {
        const blob = await response.blob();
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `${name}_backup_${Date.now()}.bangron`;
        a.click();
        URL.revokeObjectURL(url);
    }
}

// Import database
async function importDatabase(file, newName, encryptionKey) {
    const formData = new FormData();
    formData.append('file', file);
    if (newName) formData.append('name', newName);
    if (encryptionKey) formData.append('key', encryptionKey);
    
    const response = await fetch('/api/databases/import', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        showToast('Database imported successfully', 'success');
        refreshDatabaseList();
    }
}
```

### Integrasi PHP

```php
// api/databases.php
<?php

use BangronDB\Client;
use BangronDB\Database;

$client = new Client('/var/www/data/databases');

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List all databases
        $databases = [];
        foreach ($client->listDBs() as $name) {
            $db = $client->selectDB($name);
            $metrics = $db->getHealthMetrics();
            $databases[] = [
                'name' => $name,
                'path' => $metrics['database']['path'],
                'encrypted' => $metrics['database']['encryption_enabled'],
                'collections' => $metrics['metrics']['total_collections'],
                'documents' => $metrics['metrics']['total_documents'],
                'size' => $metrics['metrics']['total_size_bytes'],
                'health' => $metrics['integrity']['status']
            ];
        }
        echo json_encode($databases);
        break;
        
    case 'POST':
        // Create new database
        $data = json_decode(file_get_contents('php://input'), true);
        $options = [];
        
        if ($data['encrypted'] && $data['key']) {
            $options['encryption_key'] = $data['key'];
        }
        
        $db = new Database(
            "/var/www/data/databases/{$data['name']}.bangron",
            $options
        );
        
        echo json_encode(['success' => true, 'name' => $data['name']]);
        break;
        
    case 'DELETE':
        // Delete database
        $name = $_GET['name'];
        $path = "/var/www/data/databases/{$name}.bangron";
        
        if (file_exists($path)) {
            unlink($path);
            echo json_encode(['success' => true]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Database not found']);
        }
        break;
}
?>
```

### Validasi

| Field | Rules |
|-------|-------|
| Database Name | Required, unique, alphanumeric + underscore, 3-50 chars |
| Encryption Key | Min 16 chars, must contain uppercase, lowercase, number |

---

## 4. Halaman Collections

**File:** `collections.html`

### Deskripsi
Halaman untuk mengelola collections dalam database, termasuk konfigurasi schema, indexing, dan global settings.

### Komponen UI

#### A. Database Selector

| Elemen | Fungsi |
|--------|--------|
| Dropdown | Pilih database aktif |
| Refresh | Reload collections |
| Database Settings | Buka modal settings database |

#### B. Collection Table

| Kolom | Deskripsi |
|-------|-----------|
| Name | Nama collection |
| Documents | Jumlah dokumen |
| ID Mode | Auto/Manual/Prefix |
| Encryption | Badge status |
| Soft Deletes | Badge status |
| Schema | Badge jika ada schema |
| Last Modified | Timestamp + version |
| Actions | View, Schema, Settings, Delete |

#### C. Collection Settings Modal

**Tab: General**

| Section | Fields |
|---------|--------|
| Basic Info | Name (readonly), Created At |
| ID Generation | Mode selector (Auto/Manual/Prefix), Prefix value |
| Timestamps | Auto timestamps toggle, Created field name, Updated field name |

**Tab: Global Preferences**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| Soft Deletes | Toggle | Enable/disable soft delete |
| Deleted At Field | Text | Nama field untuk soft delete (default: _deleted_at) |
| Encryption Key | Password | Override database encryption key |
| Auto Timestamps | Toggle | Otomatis tambah created_at/updated_at |

**Tab: Schema Validation**

| Komponen | Fungsi |
|----------|--------|
| Field List | Daftar field dengan tipe dan constraints |
| Add Field Button | Buka modal add field |
| Field Editor | Edit field inline |
| Import Schema | Import dari JSON |
| Export Schema | Export ke JSON |

**Tab: Indexes & Search**

| Section | Fields |
|---------|--------|
| Indexes | Daftar index, Create index, Drop index |
| Searchable Fields | Daftar searchable fields, Hashed toggle |

#### D. Add Field Modal (Schema)

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| Field Name | Text | Nama field |
| Type | Select | string, int, float, bool, date, enum, array, object, relation |
| Required | Toggle | Wajib diisi |
| Unique | Toggle | Nilai harus unik |
| Searchable | Toggle | Index untuk pencarian |
| Primary Key | Toggle | Primary key |
| Default Value | Dynamic | Berdasarkan tipe |

**Type-Specific Options:**

| Type | Options |
|------|---------|
| string | Min Length, Max Length, Regex Pattern |
| int/float | Min Value, Max Value |
| enum | Allowed Values (comma-separated) |
| array | Items Type, Min Items, Max Items |
| object | Nested Fields |
| relation | Ref Database, Ref Collection, Ref Field, On Delete |

#### E. Create Collection Modal

| Field | Validasi |
|-------|----------|
| Name | Required, alphanumeric + underscore |
| ID Mode | Auto (default) / Manual / Prefix |
| Prefix Value | If prefix mode selected |
| Enable Schema | Toggle |

### Fungsi JavaScript

```javascript
// Collection state
let currentDatabase = 'app';
let collections = [];
let selectedCollection = null;

// Load collections for database
async function loadCollections(dbName) {
    currentDatabase = dbName;
    const response = await fetch(`/api/databases/${dbName}/collections`);
    collections = await response.json();
    renderCollectionTable();
}

// Create collection
async function createCollection(data) {
    const response = await fetch(`/api/databases/${currentDatabase}/collections`, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (response.ok) {
        showToast('Collection created', 'success');
        loadCollections(currentDatabase);
        closeModal('createCollectionModal');
    }
}

// Save collection config
async function saveCollectionConfig(collectionName, config) {
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${collectionName}/config`,
        {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(config)
        }
    );
    
    if (response.ok) {
        showToast('Configuration saved', 'success');
    }
}

// Add schema field
function addSchemaField(field) {
    const schema = currentSchema || {};
    schema[field.name] = {
        type: field.type,
        required: field.required,
        unique: field.unique,
        ...field.options
    };
    
    updateSchemaPreview(schema);
}

// Handle relation field
function setupRelationField(fieldConfig) {
    return {
        type: 'relation',
        ref: fieldConfig.refDatabase 
            ? `${fieldConfig.refDatabase}.${fieldConfig.refCollection}`
            : fieldConfig.refCollection,
        refField: fieldConfig.refField || '_id',
        onDelete: fieldConfig.onDelete || 'restrict',
        as: fieldConfig.populateAs
    };
}
```

### Integrasi PHP

```php
// api/collections.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$dbName = $_GET['database'];
$db = $client->selectDB($dbName);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // List collections
        $collections = [];
        foreach ($db->getCollectionNames() as $name) {
            $col = $db->selectCollection($name);
            $config = $db->loadCollectionConfig($name);
            $lastModified = $col->getLastModified();
            
            $collections[] = [
                'name' => $name,
                'documents' => $col->count(),
                'id_mode' => $config['id_mode'] ?? 'auto',
                'encrypted' => !empty($config['encryption_key']),
                'soft_deletes' => $config['soft_deletes_enabled'] ?? false,
                'has_schema' => !empty($config['schema']),
                'last_modified' => $lastModified
            ];
        }
        echo json_encode($collections);
        break;
        
    case 'POST':
        // Create collection
        $data = json_decode(file_get_contents('php://input'), true);
        $col = $db->createCollection($data['name']);
        
        // Set ID mode
        switch ($data['id_mode']) {
            case 'manual':
                $col->setIdModeManual();
                break;
            case 'prefix':
                $col->setIdModePrefix($data['prefix']);
                break;
            default:
                $col->setIdModeAuto();
        }
        
        // Save config
        $col->saveConfiguration();
        
        echo json_encode(['success' => true]);
        break;
        
    case 'PUT':
        // Update collection config
        $colName = $_GET['collection'];
        $config = json_decode(file_get_contents('php://input'), true);
        
        $db->saveCollectionConfig($colName, $config);
        
        echo json_encode(['success' => true]);
        break;
}
?>
```

---

## 5. Halaman Schema Builder

**File:** `schema-builder.html`

### Deskripsi
Visual designer untuk membuat dan mengedit schema validation dengan drag-and-drop interface.

### Layout 3 Panel

```
┌─────────────────┬──────────────────────────────┬─────────────────────┐
│  FIELD TYPES    │      SCHEMA CANVAS           │     PREVIEW         │
│  (Drag from)    │      (Drop zone)             │     (Output)        │
├─────────────────┼──────────────────────────────┼─────────────────────┤
│ • String        │  ┌─────────────────────┐     │  Tabs:              │
│ • Integer       │  │ email (string)      │     │  • JSON Schema      │
│ • Float         │  │ REQ | UNIQUE        │     │  • PHP Code         │
│ • Boolean       │  └─────────────────────┘     │  • Test Validation  │
│ • Date          │  ┌─────────────────────┐     │  • Relations        │
│ • Enum          │  │ author_id (relation)│     │                     │
│ • Array         │  │ REF: users._id      │     │                     │
│ • Object        │  └─────────────────────┘     │                     │
│ • Relation      │                              │                     │
└─────────────────┴──────────────────────────────┴─────────────────────┘
```

### Komponen UI

#### A. Toolbar

| Tombol | Fungsi |
|--------|--------|
| Collection Selector | Pilih collection |
| Save Schema | Simpan ke database |
| Import | Import dari JSON |
| Export | Export ke JSON |
| Clear All | Hapus semua field |
| Undo/Redo | History navigation |

#### B. Field Types Palette

| Type | Icon | Color | Description |
|------|------|-------|-------------|
| String | Type | Blue | Text data |
| Integer | Hash | Green | Whole numbers |
| Float | Hash | Emerald | Decimal numbers |
| Boolean | ToggleLeft | Purple | True/false |
| Date | Calendar | Orange | Date/time values |
| Enum | List | Pink | Fixed set of values |
| Array | Brackets | Cyan | List of items |
| Object | Braces | Yellow | Nested structure |
| Relation | Link | Amber | Foreign key reference |

#### C. Schema Field Card

| Elemen | Deskripsi |
|--------|-----------|
| Drag Handle | Untuk reorder |
| Field Name | Nama field (editable) |
| Type Badge | Warna sesuai tipe |
| Constraint Badges | REQ, UNIQUE, SEARCH, PK |
| Relation Badge | REF: collection._id atau EXT: db.collection._id |
| Edit Button | Buka detail editor |
| Duplicate Button | Clone field |
| Delete Button | Hapus field |

#### D. Field Editor Panel

**Common Options:**

| Field | Tipe |
|-------|------|
| Field Name | Text input |
| Required | Toggle |
| Unique | Toggle |
| Searchable | Toggle |
| Primary Key | Toggle |
| Default Value | Dynamic |
| Description | Textarea |

**String Options:**

| Field | Tipe |
|-------|------|
| Min Length | Number |
| Max Length | Number |
| Regex Pattern | Text + Test button |
| Transform | lowercase/uppercase/trim |

**Number Options (Int/Float):**

| Field | Tipe |
|-------|------|
| Min Value | Number |
| Max Value | Number |
| Precision | Number (float only) |

**Enum Options:**

| Field | Tipe |
|-------|------|
| Values | Tag input (comma-separated) |
| Default | Select from values |

**Array Options:**

| Field | Tipe |
|-------|------|
| Items Type | Select (string, int, object) |
| Min Items | Number |
| Max Items | Number |
| Unique Items | Toggle |

**Object Options:**

| Field | Tipe |
|-------|------|
| Nested Fields | Recursive field builder |
| Additional Properties | Toggle allow/deny |

**Relation Options:**

| Field | Tipe | Deskripsi |
|-------|------|-----------|
| Reference Database | Select | Current DB atau external DB |
| Reference Collection | Select | Target collection |
| Reference Field | Text | Target field (default: _id) |
| On Delete | Select | restrict/cascade/set_null/no_action |
| Populate Alias | Text | Nama alias untuk populate |
| Eager Load | Toggle | Auto-populate saat query |

#### E. Preview Panel Tabs

**Tab: JSON Schema**

```json
{
    "name": {
        "type": "string",
        "required": true,
        "min": 3,
        "max": 100
    },
    "email": {
        "type": "string",
        "required": true,
        "unique": true,
        "regex": "^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$"
    },
    "role": {
        "type": "enum",
        "enum": ["admin", "user", "guest"],
        "default": "user"
    },
    "author_id": {
        "type": "relation",
        "ref": "users",
        "refField": "_id",
        "onDelete": "cascade"
    }
}
```

**Tab: PHP Code**

```php
$collection->setSchema([
    'name' => [
        'type' => 'string',
        'required' => true,
        'min' => 3,
        'max' => 100
    ],
    'email' => [
        'type' => 'string',
        'required' => true,
        'unique' => true,
        'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'
    ],
    'role' => [
        'type' => 'string',
        'enum' => ['admin', 'user', 'guest'],
        'default' => 'user'
    ]
]);

// Relationship populate
$results = $collection->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->toArray();
```

**Tab: Test Validation**

| Komponen | Fungsi |
|----------|--------|
| JSON Input | Textarea untuk test document |
| Validate Button | Run validation |
| Results Panel | Success/error messages per field |

**Tab: Relations Diagram**

| Visual | Deskripsi |
|--------|-----------|
| Collection Boxes | Kotak untuk setiap collection |
| Arrows | Garis relasi dengan label |
| Cross-DB Indicator | Warna berbeda untuk external DB |

### Fungsi JavaScript

```javascript
// Drag and drop
let draggedType = null;
let schemaFields = [];

// Handle drag start from palette
function handleDragStart(e, type) {
    draggedType = type;
    e.dataTransfer.effectAllowed = 'copy';
}

// Handle drop on canvas
function handleDrop(e) {
    e.preventDefault();
    if (draggedType) {
        openAddFieldModal(draggedType);
        draggedType = null;
    }
}

// Add field to schema
function addField(fieldData) {
    const field = {
        id: generateId(),
        name: fieldData.name,
        type: fieldData.type,
        required: fieldData.required || false,
        unique: fieldData.unique || false,
        searchable: fieldData.searchable || false,
        options: buildFieldOptions(fieldData)
    };
    
    schemaFields.push(field);
    renderCanvas();
    updatePreview();
}

// Build field options based on type
function buildFieldOptions(fieldData) {
    switch (fieldData.type) {
        case 'string':
            return {
                min: fieldData.minLength,
                max: fieldData.maxLength,
                regex: fieldData.regex
            };
        case 'int':
        case 'float':
            return {
                min: fieldData.minValue,
                max: fieldData.maxValue
            };
        case 'enum':
            return {
                enum: fieldData.values.split(',').map(v => v.trim()),
                default: fieldData.defaultValue
            };
        case 'array':
            return {
                items: { type: fieldData.itemsType },
                minItems: fieldData.minItems,
                maxItems: fieldData.maxItems
            };
        case 'relation':
            return {
                ref: fieldData.refDatabase 
                    ? `${fieldData.refDatabase}.${fieldData.refCollection}`
                    : fieldData.refCollection,
                refField: fieldData.refField || '_id',
                onDelete: fieldData.onDelete || 'restrict',
                as: fieldData.populateAs
            };
        default:
            return {};
    }
}

// Generate PHP code
function generatePHPCode() {
    let code = '$collection->setSchema([\n';
    
    schemaFields.forEach(field => {
        code += `    '${field.name}' => [\n`;
        code += `        'type' => '${field.type}',\n`;
        if (field.required) code += `        'required' => true,\n`;
        if (field.unique) code += `        'unique' => true,\n`;
        
        // Type-specific options
        Object.entries(field.options).forEach(([key, value]) => {
            if (value !== undefined && value !== null && value !== '') {
                if (Array.isArray(value)) {
                    code += `        '${key}' => [${value.map(v => `'${v}'`).join(', ')}],\n`;
                } else if (typeof value === 'string') {
                    code += `        '${key}' => '${value}',\n`;
                } else {
                    code += `        '${key}' => ${value},\n`;
                }
            }
        });
        
        code += `    ],\n`;
    });
    
    code += ']);';
    return code;
}

// Test validation
async function testValidation(document) {
    try {
        const doc = JSON.parse(document);
        const errors = [];
        
        schemaFields.forEach(field => {
            const value = doc[field.name];
            const fieldErrors = validateField(field, value);
            errors.push(...fieldErrors);
        });
        
        return {
            valid: errors.length === 0,
            errors
        };
    } catch (e) {
        return {
            valid: false,
            errors: [{ field: '_json', message: 'Invalid JSON: ' + e.message }]
        };
    }
}
```

### Integrasi PHP

```php
// api/schema.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$dbName = $_GET['database'];
$colName = $_GET['collection'];

$db = $client->selectDB($dbName);
$collection = $db->selectCollection($colName);

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        // Get current schema
        $schema = $collection->getSchema();
        echo json_encode($schema);
        break;
        
    case 'PUT':
        // Save schema
        $schema = json_decode(file_get_contents('php://input'), true);
        $collection->setSchema($schema);
        $collection->saveConfiguration();
        echo json_encode(['success' => true]);
        break;
        
    case 'POST':
        // Validate document against schema
        $document = json_decode(file_get_contents('php://input'), true);
        
        try {
            $valid = $collection->validate($document);
            echo json_encode(['valid' => true]);
        } catch (\Exception $e) {
            echo json_encode([
                'valid' => false,
                'errors' => [$e->getMessage()]
            ]);
        }
        break;
}
?>
```

---

## 6. Halaman Documents

**File:** `documents.html`

### Deskripsi
Document explorer untuk melihat, membuat, mengedit, dan menghapus dokumen dalam collection.

### Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│  Toolbar: [Collection ▼] [Filter ▼] [Search...] [+ Insert] [⚙]    │
├─────────────────────────────────┬───────────────────────────────────┤
│  DOCUMENT LIST                  │  DOCUMENT EDITOR                  │
│                                 │                                   │
│  ┌───────────────────────────┐  │  ┌───────────────────────────┐   │
│  │ { _id: "abc123" }         │  │  │ JSON Editor               │   │
│  │ name: "John Doe"          │  │  │                           │   │
│  │ email: "john@..."         │  │  │ {                         │   │
│  │ [ACTIVE] 2 mins ago       │  │  │   "_id": "abc123",        │   │
│  └───────────────────────────┘  │  │   "name": "John Doe",     │   │
│  ┌───────────────────────────┐  │  │   "email": "john@ex..."   │   │
│  │ { _id: "def456" }         │  │  │ }                         │   │
│  │ name: "Jane Smith"        │  │  │                           │   │
│  │ [DELETED] 5 mins ago      │  │  │ [Save] [Delete] [Clone]   │   │
│  └───────────────────────────┘  │  └───────────────────────────┘   │
│                                 │                                   │
│  [◀ Prev] Page 1 of 10 [Next ▶]│  Relations: [author ▶]           │
└─────────────────────────────────┴───────────────────────────────────┘
```

### Komponen UI

#### A. Toolbar

| Komponen | Fungsi |
|----------|--------|
| Database Selector | Dropdown pilih database |
| Collection Selector | Dropdown pilih collection |
| Soft Delete Filter | Active Only / Trashed / All |
| Search Input | Cari berdasarkan field |
| Insert Button | Buka modal insert |
| Refresh Button | Reload documents |
| Settings Button | Document display options |

#### B. Document List

| Elemen | Deskripsi |
|--------|-----------|
| Document Card | Preview dokumen dalam bentuk kartu |
| ID Badge | _id dokumen |
| Field Preview | 2-3 field utama |
| Status Badge | ACTIVE (green) atau DELETED (red) |
| Timestamp | Relative time (e.g., "2 mins ago") |
| Click Action | Select untuk edit |

#### C. Pagination

| Komponen | Fungsi |
|----------|--------|
| Prev/Next Buttons | Navigasi halaman |
| Page Info | "Page X of Y" |
| Items Per Page | Dropdown: 10, 25, 50, 100 |
| Jump to Page | Input number |

#### D. Document Editor (Right Panel)

| Tab | Konten |
|-----|--------|
| JSON | Raw JSON editor dengan syntax highlighting |
| Form | Form fields berdasarkan schema |
| Relations | Populated relations view |
| History | Document change history (jika audit enabled) |

#### E. Insert Document Modal

| Mode | Komponen |
|------|----------|
| JSON Mode | Textarea dengan JSON editor |
| Form Mode | Dynamic form dari schema |

**Form Mode Field Types:**

| Schema Type | Form Input |
|-------------|------------|
| string | Text input |
| string (regex email) | Email input |
| int | Number input (step=1) |
| float | Number input (step=any) |
| boolean | Toggle switch |
| date | Date picker |
| enum | Select dropdown |
| array | Multi-input / Tag input |
| object | Nested form group |
| relation | Searchable select dengan autocomplete |

#### F. Context Menu (Right Click)

| Action | Shortcut | Fungsi |
|--------|----------|--------|
| Edit | Enter | Buka editor |
| Clone | Ctrl+D | Duplicate document |
| Delete | Delete | Hapus (soft delete jika enabled) |
| Restore | - | Restore dari trash |
| Force Delete | Shift+Delete | Permanent delete |
| Copy ID | Ctrl+C | Copy _id ke clipboard |
| Copy JSON | Ctrl+Shift+C | Copy full JSON |
| View History | - | Buka history modal |

### Fungsi JavaScript

```javascript
// State
let currentDatabase = 'app';
let currentCollection = 'users';
let documents = [];
let selectedDocument = null;
let currentPage = 1;
let itemsPerPage = 25;
let softDeleteFilter = 'active'; // active, trashed, all
let schema = null;

// Load documents
async function loadDocuments() {
    let url = `/api/databases/${currentDatabase}/collections/${currentCollection}/documents`;
    url += `?page=${currentPage}&limit=${itemsPerPage}&filter=${softDeleteFilter}`;
    
    if (searchQuery) {
        url += `&search=${encodeURIComponent(searchQuery)}`;
    }
    
    const response = await fetch(url);
    const data = await response.json();
    
    documents = data.documents;
    totalPages = data.totalPages;
    
    renderDocumentList();
}

// Load schema for form builder
async function loadSchema() {
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${currentCollection}/schema`
    );
    schema = await response.json();
}

// Insert document
async function insertDocument(doc) {
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${currentCollection}/documents`,
        {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(doc)
        }
    );
    
    if (response.ok) {
        const result = await response.json();
        showToast(`Document created with ID: ${result._id}`, 'success');
        loadDocuments();
        closeModal('insertModal');
    }
}

// Update document
async function updateDocument(id, doc) {
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${currentCollection}/documents/${id}`,
        {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(doc)
        }
    );
    
    if (response.ok) {
        showToast('Document updated', 'success');
        loadDocuments();
    }
}

// Delete document (soft or hard)
async function deleteDocument(id, force = false) {
    const endpoint = force ? 'force-delete' : 'delete';
    
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${currentCollection}/documents/${id}/${endpoint}`,
        { method: 'DELETE' }
    );
    
    if (response.ok) {
        showToast(force ? 'Document permanently deleted' : 'Document moved to trash', 'success');
        loadDocuments();
    }
}

// Restore document
async function restoreDocument(id) {
    const response = await fetch(
        `/api/databases/${currentDatabase}/collections/${currentCollection}/documents/${id}/restore`,
        { method: 'POST' }
    );
    
    if (response.ok) {
        showToast('Document restored', 'success');
        loadDocuments();
    }
}

// Build form from schema
function buildFormFromSchema(schema, container) {
    container.innerHTML = '';
    
    Object.entries(schema).forEach(([fieldName, fieldConfig]) => {
        const fieldElement = createFormField(fieldName, fieldConfig);
        container.appendChild(fieldElement);
    });
}

// Create form field based on type
function createFormField(name, config) {
    const wrapper = document.createElement('div');
    wrapper.className = 'mb-4';
    
    const label = document.createElement('label');
    label.textContent = name + (config.required ? ' *' : '');
    label.className = 'block text-sm font-medium text-slate-300 mb-1';
    wrapper.appendChild(label);
    
    let input;
    
    switch (config.type) {
        case 'string':
            input = document.createElement('input');
            input.type = config.regex?.includes('@') ? 'email' : 'text';
            if (config.min) input.minLength = config.min;
            if (config.max) input.maxLength = config.max;
            break;
            
        case 'int':
            input = document.createElement('input');
            input.type = 'number';
            input.step = '1';
            if (config.min !== undefined) input.min = config.min;
            if (config.max !== undefined) input.max = config.max;
            break;
            
        case 'float':
            input = document.createElement('input');
            input.type = 'number';
            input.step = 'any';
            break;
            
        case 'boolean':
            input = document.createElement('input');
            input.type = 'checkbox';
            input.className = 'toggle';
            break;
            
        case 'date':
            input = document.createElement('input');
            input.type = 'datetime-local';
            break;
            
        case 'enum':
            input = document.createElement('select');
            config.enum.forEach(value => {
                const option = document.createElement('option');
                option.value = value;
                option.textContent = value;
                input.appendChild(option);
            });
            break;
            
        case 'relation':
            input = createRelationSelect(name, config);
            break;
            
        default:
            input = document.createElement('input');
            input.type = 'text';
    }
    
    input.name = name;
    input.required = config.required;
    input.className = 'w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-2 text-white';
    
    wrapper.appendChild(input);
    return wrapper;
}

// Create relation select with autocomplete
function createRelationSelect(name, config) {
    const wrapper = document.createElement('div');
    wrapper.className = 'relative';
    
    const input = document.createElement('input');
    input.type = 'text';
    input.placeholder = `Search ${config.ref}...`;
    input.className = 'w-full bg-slate-700/50 border border-slate-600 rounded-lg px-4 py-2 text-white';
    
    const dropdown = document.createElement('div');
    dropdown.className = 'absolute z-10 w-full mt-1 bg-slate-700 border border-slate-600 rounded-lg hidden';
    
    input.addEventListener('input', async (e) => {
        const query = e.target.value;
        if (query.length < 2) {
            dropdown.classList.add('hidden');
            return;
        }
        
        // Fetch related documents
        const [refDb, refCol] = config.ref.includes('.') 
            ? config.ref.split('.') 
            : [currentDatabase, config.ref];
            
        const response = await fetch(
            `/api/databases/${refDb}/collections/${refCol}/documents?search=${query}&limit=10`
        );
        const data = await response.json();
        
        dropdown.innerHTML = '';
        data.documents.forEach(doc => {
            const item = document.createElement('div');
            item.className = 'px-4 py-2 hover:bg-slate-600 cursor-pointer';
            item.textContent = doc[config.refField] + ' - ' + (doc.name || doc.title || doc._id);
            item.onclick = () => {
                input.value = doc[config.refField];
                input.dataset.value = doc[config.refField];
                dropdown.classList.add('hidden');
            };
            dropdown.appendChild(item);
        });
        
        dropdown.classList.remove('hidden');
    });
    
    wrapper.appendChild(input);
    wrapper.appendChild(dropdown);
    
    return wrapper;
}
```

### Integrasi PHP

```php
// api/documents.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$dbName = $_GET['database'];
$colName = $_GET['collection'];

$db = $client->selectDB($dbName);
$collection = $db->selectCollection($colName);

// Load config for soft deletes
$config = $db->loadCollectionConfig($colName);
if ($config['soft_deletes_enabled'] ?? false) {
    $collection->useSoftDeletes(true);
}

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 25);
        $filter = $_GET['filter'] ?? 'active';
        $search = $_GET['search'] ?? null;
        
        // Build cursor
        $criteria = [];
        if ($search) {
            $criteria['$or'] = [
                ['name' => ['$regex' => $search]],
                ['email' => ['$regex' => $search]],
                ['_id' => $search]
            ];
        }
        
        $cursor = $collection->find($criteria);
        
        // Apply soft delete filter
        switch ($filter) {
            case 'trashed':
                $cursor = $cursor->onlyTrashed();
                break;
            case 'all':
                $cursor = $cursor->withTrashed();
                break;
            // 'active' is default - no trashed
        }
        
        // Get total for pagination
        $total = $cursor->count();
        
        // Apply pagination
        $documents = $cursor
            ->skip(($page - 1) * $limit)
            ->limit($limit)
            ->toArray();
        
        echo json_encode([
            'documents' => $documents,
            'total' => $total,
            'page' => $page,
            'totalPages' => ceil($total / $limit)
        ]);
        break;
        
    case 'POST':
        $document = json_decode(file_get_contents('php://input'), true);
        
        // Validate against schema
        $schema = $collection->getSchema();
        if ($schema) {
            $collection->validate($document);
        }
        
        $id = $collection->insert($document);
        echo json_encode(['success' => true, '_id' => $id]);
        break;
        
    case 'PUT':
        $id = $_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        $collection->update(['_id' => $id], $data);
        echo json_encode(['success' => true]);
        break;
        
    case 'DELETE':
        $id = $_GET['id'];
        $force = isset($_GET['force']);
        
        if ($force) {
            $collection->forceDelete(['_id' => $id]);
        } else {
            $collection->remove(['_id' => $id]);
        }
        
        echo json_encode(['success' => true]);
        break;
}
?>

// Restore endpoint
// api/documents/restore.php
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = $_GET['id'];
    $collection->restore(['_id' => $id]);
    echo json_encode(['success' => true]);
}
?>
```

---

## 7. Halaman Query Playground

**File:** `query-playground.html`

### Deskripsi
Interactive playground untuk menulis dan menjalankan query MongoDB-style pada BangronDB.

### Layout

```
┌─────────────────────────────────────────────────────────────────────┐
│  Toolbar: [Database ▼] [Collection ▼] [Examples ▼] [Run ▶] [Clear] │
├─────────────────────────────────┬───────────────────────────────────┤
│  QUERY EDITOR                   │  RESULTS PANEL                    │
│                                 │                                   │
│  // Find active users           │  ┌─ Tabs ──────────────────────┐ │
│  $collection->find([            │  │ [Results] [JSON] [Explain]  │ │
│    'status' => 'active',        │  └─────────────────────────────┘ │
│    'age' => ['$gte' => 18]      │                                   │
│  ])                             │  Found 15 documents in 23ms       │
│  ->sort(['created_at' => -1])   │                                   │
│  ->limit(10)                    │  ┌─────────────────────────────┐ │
│  ->toArray();                   │  │ { "_id": "abc", ...}        │ │
│                                 │  │ { "_id": "def", ...}        │ │
│                                 │  │ ...                         │ │
│  [Line 5, Col 12]               │  └─────────────────────────────┘ │
└─────────────────────────────────┴───────────────────────────────────┘
│  Quick Examples:                                                     │
│  [Find All] [Find One] [Insert] [Update] [Delete] [Aggregate]       │
└─────────────────────────────────────────────────────────────────────┘
```

### Komponen UI

#### A. Toolbar

| Komponen | Fungsi |
|----------|--------|
| Database Selector | Pilih database target |
| Collection Selector | Pilih collection target |
| Examples Dropdown | Quick insert contoh query |
| Run Button | Execute query (Ctrl+Enter) |
| Clear Button | Bersihkan editor |
| Format Button | Format/prettify code |
| History Button | Riwayat query |

#### B. Query Editor

| Fitur | Deskripsi |
|-------|-----------|
| Syntax Highlighting | PHP/JSON highlighting |
| Line Numbers | Nomor baris |
| Error Indicators | Garis merah untuk error |
| Autocomplete | Suggest operators ($gt, $in, dll) |
| Keyboard Shortcuts | Ctrl+Enter = Run, Ctrl+/ = Comment |
| Status Bar | Line/column position |

#### C. Quick Examples

| Example | Query |
|---------|-------|
| Find All | `$collection->find()->toArray()` |
| Find One | `$collection->findOne(['_id' => 'xxx'])` |
| Find with Filter | `$collection->find(['status' => 'active'])` |
| Comparison | `$collection->find(['age' => ['$gte' => 18]])` |
| Logical OR | `$collection->find(['$or' => [...]])` |
| Regex | `$collection->find(['name' => ['$regex' => '^John']])` |
| Insert | `$collection->insert([...])` |
| Update | `$collection->update(['_id' => 'x'], ['$set' => [...]])` |
| Delete | `$collection->remove(['status' => 'inactive'])` |
| With Populate | `$collection->find()->populate('author_id', $db->users)` |
| Pagination | `$collection->find()->skip(10)->limit(10)` |
| Sorting | `$collection->find()->sort(['created_at' => -1])` |

#### D. Results Panel Tabs

**Tab: Results**

| Elemen | Deskripsi |
|--------|-----------|
| Execution Time | "Found X documents in Yms" |
| Document Cards | Collapsed view of each result |
| Expand Button | Lihat full document |
| Copy Button | Copy document JSON |

**Tab: JSON**

| Elemen | Deskripsi |
|--------|-----------|
| Raw JSON | Full JSON output |
| Copy All | Copy semua hasil |
| Download | Download sebagai .json file |

**Tab: Explain**

| Info | Deskripsi |
|------|-----------|
| Query Plan | Index yang digunakan |
| Scanned Documents | Jumlah dokumen di-scan |
| Execution Time | Breakdown waktu |
| Suggestions | Rekomendasi optimasi |

#### E. History Panel

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu eksekusi |
| Query | Preview query |
| Duration | Waktu eksekusi |
| Results | Jumlah hasil |
| Actions | Re-run, Copy |

### Fungsi JavaScript

```javascript
// Query history
let queryHistory = JSON.parse(localStorage.getItem('queryHistory') || '[]');

// Execute query
async function executeQuery() {
    const query = editor.getValue();
    const startTime = performance.now();
    
    try {
        const response = await fetch('/api/query/execute', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                database: currentDatabase,
                collection: currentCollection,
                query: query
            })
        });
        
        const endTime = performance.now();
        const duration = (endTime - startTime).toFixed(2);
        
        if (response.ok) {
            const result = await response.json();
            
            // Show results
            showResults(result.data, duration);
            
            // Add to history
            addToHistory(query, duration, result.data.length);
            
            // Show explain if available
            if (result.explain) {
                showExplain(result.explain);
            }
        } else {
            const error = await response.json();
            showError(error.message);
        }
    } catch (e) {
        showError(e.message);
    }
}

// Insert example query
function insertExample(type) {
    const examples = {
        findAll: `$collection->find()->toArray();`,
        
        findOne: `$collection->findOne([
    '_id' => 'document-id-here'
]);`,
        
        findWithFilter: `$collection->find([
    'status' => 'active',
    'age' => ['$gte' => 18]
])->toArray();`,
        
        logicalOr: `$collection->find([
    '$or' => [
        ['status' => 'active'],
        ['role' => 'admin']
    ]
])->toArray();`,
        
        regex: `$collection->find([
    'email' => ['$regex' => '@gmail\\.com$']
])->toArray();`,
        
        insert: `$collection->insert([
    'name' => 'New User',
    'email' => 'user@example.com',
    'status' => 'active'
]);`,
        
        update: `$collection->update(
    ['_id' => 'document-id'],
    [
        '$set' => ['status' => 'inactive'],
        '$unset' => ['temp_field' => '']
    ]
);`,
        
        delete: `$collection->remove([
    'status' => 'inactive'
]);`,
        
        populate: `$collection->find(['status' => 'active'])
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->populate('category_id', $db->categories, ['as' => 'category'])
    ->toArray();`,
        
        pagination: `$collection->find(['status' => 'active'])
    ->sort(['created_at' => -1])
    ->skip(0)
    ->limit(10)
    ->toArray();`,
        
        softDelete: `// Get only active documents
$collection->find()->toArray();

// Get trashed documents
$collection->find()->onlyTrashed()->toArray();

// Get all including trashed
$collection->find()->withTrashed()->toArray();

// Restore document
$collection->restore(['_id' => 'document-id']);

// Force delete (permanent)
$collection->forceDelete(['_id' => 'document-id']);`,
        
        aggregation: `// Custom function query
$collection->find([
    'score' => [
        '$func' => function($value) {
            return $value > 80;
        }
    ]
])->toArray();`
    };
    
    editor.setValue(examples[type]);
}

// Add to history
function addToHistory(query, duration, resultCount) {
    queryHistory.unshift({
        timestamp: new Date().toISOString(),
        query: query,
        duration: duration,
        results: resultCount
    });
    
    // Keep only last 50
    queryHistory = queryHistory.slice(0, 50);
    
    localStorage.setItem('queryHistory', JSON.stringify(queryHistory));
    renderHistory();
}
```

### Integrasi PHP

```php
// api/query/execute.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');

$input = json_decode(file_get_contents('php://input'), true);
$dbName = $input['database'];
$colName = $input['collection'];
$query = $input['query'];

$db = $client->selectDB($dbName);
$collection = $db->selectCollection($colName);

try {
    // Parse and execute query safely
    // WARNING: In production, use a proper query parser
    // Do NOT use eval() with user input!
    
    // Simple query parser for demo
    $result = parseAndExecuteQuery($query, $collection, $db);
    
    echo json_encode([
        'success' => true,
        'data' => $result,
        'count' => count($result)
    ]);
    
} catch (\Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function parseAndExecuteQuery($query, $collection, $db) {
    // This is a simplified parser - implement proper parsing for production
    
    if (preg_match('/->find\(\[(.*?)\]\)/s', $query, $matches)) {
        // Parse find criteria
        $criteriaStr = $matches[1];
        $criteria = parsePHPArray($criteriaStr);
        
        $cursor = $collection->find($criteria);
        
        // Check for chained methods
        if (strpos($query, '->sort(') !== false) {
            preg_match('/->sort\(\[(.*?)\]\)/s', $query, $sortMatch);
            $sort = parsePHPArray($sortMatch[1]);
            $cursor = $cursor->sort($sort);
        }
        
        if (strpos($query, '->limit(') !== false) {
            preg_match('/->limit\((\d+)\)/', $query, $limitMatch);
            $cursor = $cursor->limit((int)$limitMatch[1]);
        }
        
        if (strpos($query, '->skip(') !== false) {
            preg_match('/->skip\((\d+)\)/', $query, $skipMatch);
            $cursor = $cursor->skip((int)$skipMatch[1]);
        }
        
        return $cursor->toArray();
    }
    
    // Add more query type handlers...
    
    throw new \Exception('Unsupported query format');
}
?>
```

---

## 8. Halaman Users

**File:** `users.html`

### Deskripsi
Halaman manajemen pengguna admin panel dengan role-based access control.

### Komponen UI

#### A. Statistics Cards

| Card | Data |
|------|------|
| Total Users | Jumlah semua user |
| Active Users | User dengan status active |
| Administrators | User dengan role admin |
| Pending Invites | Undangan belum diterima |

#### B. User Table

| Kolom | Deskripsi |
|-------|-----------|
| Checkbox | Multi-select untuk bulk actions |
| Avatar | Foto profil atau initials |
| Name | Nama lengkap |
| Email | Email address |
| Role | Badge (Admin/Editor/Viewer) |
| Databases | Akses database (comma-separated) |
| Status | Active/Inactive/Pending |
| Last Login | Relative time |
| Actions | View, Edit, Permissions, Delete |

#### C. Create User Modal

| Field | Tipe | Validasi |
|-------|------|----------|
| Full Name | Text | Required, 2-100 chars |
| Email | Email | Required, unique |
| Role | Select | admin/editor/viewer |
| Password | Password | Required, min 8 chars |
| Confirm Password | Password | Must match |
| Send Invite Email | Checkbox | Optional |
| Database Access | Multi-select | Optional |

#### D. Edit User Modal

| Tab | Fields |
|-----|--------|
| Profile | Name, Email, Avatar upload |
| Security | Change password, 2FA settings |
| Permissions | Role, Database access |

#### E. User Permissions Modal

| Section | Permissions |
|---------|-------------|
| Databases | Read, Write, Admin per database |
| Collections | Create, Read, Update, Delete |
| Documents | Create, Read, Update, Delete |
| Users | View, Create, Edit, Delete |
| System | Settings, Monitoring, Security |

#### F. View User Modal

| Tab | Content |
|-----|---------|
| Profile | User info, created date, last login |
| Activity | Recent actions log |
| Sessions | Active sessions with revoke option |

#### G. Invite User Modal

| Field | Deskripsi |
|-------|-----------|
| Email | Email undangan |
| Role | Role yang akan diberikan |
| Databases | Akses database awal |
| Message | Pesan custom (optional) |
| Expiry | Waktu kadaluarsa undangan |

#### H. Bulk Actions

| Action | Deskripsi |
|--------|-----------|
| Delete Selected | Hapus user terpilih |
| Activate | Aktifkan user terpilih |
| Deactivate | Nonaktifkan user terpilih |
| Change Role | Ubah role massal |
| Export | Export ke CSV |

### Fungsi JavaScript

```javascript
// Load users
async function loadUsers(page = 1, filter = {}) {
    const params = new URLSearchParams({
        page,
        limit: 25,
        ...filter
    });
    
    const response = await fetch(`/api/users?${params}`);
    const data = await response.json();
    
    users = data.users;
    renderUserTable();
    renderPagination(data.totalPages);
}

// Create user
async function createUser(formData) {
    const response = await fetch('/api/users', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(formData)
    });
    
    if (response.ok) {
        showToast('User created successfully', 'success');
        loadUsers();
        closeModal('createUserModal');
    } else {
        const error = await response.json();
        showToast(error.message, 'error');
    }
}

// Update user permissions
async function updatePermissions(userId, permissions) {
    const response = await fetch(`/api/users/${userId}/permissions`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(permissions)
    });
    
    if (response.ok) {
        showToast('Permissions updated', 'success');
    }
}

// Bulk action
async function bulkAction(action, userIds) {
    const response = await fetch('/api/users/bulk', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action, userIds })
    });
    
    if (response.ok) {
        showToast(`${action} completed for ${userIds.length} users`, 'success');
        loadUsers();
        clearSelection();
    }
}

// Invite user
async function inviteUser(email, role, databases, message) {
    const response = await fetch('/api/users/invite', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ email, role, databases, message })
    });
    
    if (response.ok) {
        showToast('Invitation sent', 'success');
        closeModal('inviteUserModal');
    }
}
```

### Integrasi PHP

```php
// api/users.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$authDb = $client->selectDB('_auth');
$users = $authDb->users;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $page = (int)($_GET['page'] ?? 1);
        $limit = (int)($_GET['limit'] ?? 25);
        $role = $_GET['role'] ?? null;
        $status = $_GET['status'] ?? null;
        
        $criteria = [];
        if ($role) $criteria['role'] = $role;
        if ($status) $criteria['status'] = $status;
        
        $total = $users->count($criteria);
        $data = $users->find($criteria)
            ->sort(['created_at' => -1])
            ->skip(($page - 1) * $limit)
            ->limit($limit)
            ->toArray();
        
        // Remove passwords from response
        foreach ($data as &$user) {
            unset($user['password']);
        }
        
        echo json_encode([
            'users' => $data,
            'total' => $total,
            'totalPages' => ceil($total / $limit)
        ]);
        break;
        
    case 'POST':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate unique email
        $existing = $users->findOne(['email' => $data['email']]);
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Email already exists']);
            exit;
        }
        
        // Hash password
        $data['password'] = password_hash($data['password'], PASSWORD_DEFAULT);
        $data['created_at'] = date('c');
        $data['status'] = 'active';
        
        $id = $users->insert($data);
        
        // Send invite email if requested
        if ($data['send_invite'] ?? false) {
            sendInviteEmail($data['email'], $data['name']);
        }
        
        echo json_encode(['success' => true, '_id' => $id]);
        break;
        
    case 'PUT':
        $userId = $_GET['id'];
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Don't allow password update through this endpoint
        unset($data['password']);
        
        $data['updated_at'] = date('c');
        
        $users->update(['_id' => $userId], $data);
        echo json_encode(['success' => true]);
        break;
        
    case 'DELETE':
        $userId = $_GET['id'];
        
        // Soft delete user
        $users->update(['_id' => $userId], [
            'status' => 'deleted',
            'deleted_at' => date('c')
        ]);
        
        echo json_encode(['success' => true]);
        break;
}
?>
```

---

## 9. Halaman Roles

**File:** `roles.html`

### Deskripsi
Halaman manajemen role dan permissions untuk access control.

### Komponen UI

#### A. Role Cards

| Elemen | Deskripsi |
|--------|-----------|
| Icon | Icon role (Shield, User, Eye) |
| Name | Nama role |
| Description | Deskripsi singkat |
| User Count | Jumlah user dengan role ini |
| Permissions Summary | List permission utama |
| Actions | Edit, Clone, Delete |

#### B. Permissions Matrix

| Database/Feature | Admin | Editor | Viewer |
|------------------|-------|--------|--------|
| **Databases** | ✓ Full | ✓ Read/Write | ✓ Read |
| **Collections** | ✓ Full | ✓ CRUD | ✓ Read |
| **Documents** | ✓ Full | ✓ CRUD | ✓ Read |
| **Schema** | ✓ Full | ✓ Edit | ✗ None |
| **Users** | ✓ Full | ✗ None | ✗ None |
| **Settings** | ✓ Full | ✗ None | ✗ None |

#### C. Create/Edit Role Modal

| Tab | Content |
|-----|---------|
| Basic Info | Name, Description, Icon, Color |
| Permissions | Permission checkboxes per category |
| Quick Presets | Preset buttons (Admin, Editor, Viewer) |

**Permission Categories:**

| Category | Permissions |
|----------|-------------|
| Database | view, create, edit, delete, backup, restore |
| Collection | view, create, edit, delete, config |
| Document | view, create, edit, delete, export |
| Schema | view, edit |
| Query | execute, playground |
| User | view, create, edit, delete, permissions |
| Role | view, create, edit, delete |
| System | settings, monitoring, security, logs |

### Fungsi JavaScript

```javascript
// Roles state
let roles = [];

// Load roles
async function loadRoles() {
    const response = await fetch('/api/roles');
    roles = await response.json();
    renderRoleCards();
    renderPermissionMatrix();
}

// Create role
async function createRole(roleData) {
    const response = await fetch('/api/roles', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(roleData)
    });
    
    if (response.ok) {
        showToast('Role created', 'success');
        loadRoles();
        closeModal('roleModal');
    }
}

// Apply preset permissions
function applyPreset(preset) {
    const presets = {
        admin: {
            database: ['view', 'create', 'edit', 'delete', 'backup', 'restore'],
            collection: ['view', 'create', 'edit', 'delete', 'config'],
            document: ['view', 'create', 'edit', 'delete', 'export'],
            schema: ['view', 'edit'],
            query: ['execute', 'playground'],
            user: ['view', 'create', 'edit', 'delete', 'permissions'],
            role: ['view', 'create', 'edit', 'delete'],
            system: ['settings', 'monitoring', 'security', 'logs']
        },
        editor: {
            database: ['view'],
            collection: ['view', 'create', 'edit', 'delete'],
            document: ['view', 'create', 'edit', 'delete'],
            schema: ['view', 'edit'],
            query: ['execute', 'playground'],
            user: [],
            role: [],
            system: []
        },
        viewer: {
            database: ['view'],
            collection: ['view'],
            document: ['view'],
            schema: ['view'],
            query: ['execute'],
            user: [],
            role: [],
            system: []
        }
    };
    
    setPermissions(presets[preset]);
}

// Render permission matrix
function renderPermissionMatrix() {
    const matrix = document.getElementById('permissionMatrix');
    matrix.innerHTML = '';
    
    const categories = ['database', 'collection', 'document', 'schema', 'query', 'user', 'role', 'system'];
    
    // Header row
    const headerRow = document.createElement('tr');
    headerRow.innerHTML = '<th>Permission</th>';
    roles.forEach(role => {
        headerRow.innerHTML += `<th>${role.name}</th>`;
    });
    matrix.appendChild(headerRow);
    
    // Permission rows
    categories.forEach(category => {
        const row = document.createElement('tr');
        row.innerHTML = `<td class="font-medium capitalize">${category}</td>`;
        
        roles.forEach(role => {
            const perms = role.permissions[category] || [];
            const icon = perms.length > 0 ? '✓' : '✗';
            const color = perms.length > 0 ? 'text-green-400' : 'text-red-400';
            row.innerHTML += `<td class="${color}">${icon} ${perms.join(', ')}</td>`;
        });
        
        matrix.appendChild(row);
    });
}
```

### Integrasi PHP

```php
// api/roles.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$authDb = $client->selectDB('_auth');
$roles = $authDb->roles;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $data = $roles->find()->toArray();
        echo json_encode($data);
        break;
        
    case 'POST':
        $roleData = json_decode(file_get_contents('php://input'), true);
        
        // Validate unique name
        $existing = $roles->findOne(['name' => $roleData['name']]);
        if ($existing) {
            http_response_code(400);
            echo json_encode(['error' => 'Role name already exists']);
            exit;
        }
        
        $roleData['created_at'] = date('c');
        $id = $roles->insert($roleData);
        
        echo json_encode(['success' => true, '_id' => $id]);
        break;
        
    case 'PUT':
        $roleId = $_GET['id'];
        $roleData = json_decode(file_get_contents('php://input'), true);
        $roleData['updated_at'] = date('c');
        
        $roles->update(['_id' => $roleId], $roleData);
        echo json_encode(['success' => true]);
        break;
        
    case 'DELETE':
        $roleId = $_GET['id'];
        
        // Check if role is in use
        $usersWithRole = $authDb->users->count(['role' => $roleId]);
        if ($usersWithRole > 0) {
            http_response_code(400);
            echo json_encode(['error' => "Cannot delete role. $usersWithRole users have this role."]);
            exit;
        }
        
        $roles->remove(['_id' => $roleId]);
        echo json_encode(['success' => true]);
        break;
}
?>
```

---

## 10. Halaman Monitoring

**File:** `monitoring.html`

### Deskripsi
Dashboard real-time untuk monitoring kesehatan dan performa sistem database.

### Komponen UI

#### A. Health Status Cards

| Card | Metric | Visual |
|------|--------|--------|
| Database Status | Healthy/Warning/Critical | Color badge |
| Active Connections | Count | Number |
| Query Rate | Queries/sec | Number + trend |
| Error Rate | Errors/min | Number + trend |

#### B. Resource Usage

| Metric | Visual |
|--------|--------|
| CPU Usage | Progress bar + percentage |
| Memory Usage | Progress bar + percentage |
| Disk Usage | Progress bar + percentage |
| I/O Operations | Progress bar + ops/s |

#### C. Database Health Table

| Kolom | Deskripsi |
|-------|-----------|
| Database | Nama database |
| Status | Healthy/Warning/Error badge |
| Collections | Jumlah collection |
| Documents | Total dokumen |
| Size | Ukuran file |
| Connections | Active connections |
| Last Check | Timestamp |

#### D. Performance Metrics

| Chart | Data |
|-------|------|
| Query Latency | Line chart (avg response time) |
| Throughput | Bar chart (reads/writes per minute) |
| Error Rate | Line chart over time |

#### E. Live Log Stream

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu log |
| Level | INFO/WARN/ERROR badge |
| Source | Database/Collection name |
| Message | Log message |
| Details | Expandable details |

#### F. Alerts Panel

| Kolom | Deskripsi |
|-------|-----------|
| Time | Waktu alert |
| Severity | Critical/Warning/Info |
| Message | Alert description |
| Status | Active/Acknowledged/Resolved |
| Actions | Acknowledge, Resolve, Details |

### Fungsi JavaScript

```javascript
// Real-time updates
let metricsInterval = null;
let logsInterval = null;

// Start monitoring
function startMonitoring() {
    // Update metrics every 10 seconds
    metricsInterval = setInterval(fetchMetrics, 10000);
    
    // Fetch logs every 5 seconds
    logsInterval = setInterval(fetchLogs, 5000);
    
    // Initial fetch
    fetchMetrics();
    fetchLogs();
}

// Fetch metrics
async function fetchMetrics() {
    const response = await fetch('/api/monitoring/metrics');
    const data = await response.json();
    
    updateResourceBars(data.resources);
    updateHealthTable(data.databases);
    updateCharts(data.performance);
}

// Fetch live logs
async function fetchLogs() {
    const lastTimestamp = logs.length > 0 ? logs[0].timestamp : null;
    
    let url = '/api/monitoring/logs';
    if (lastTimestamp) {
        url += `?since=${encodeURIComponent(lastTimestamp)}`;
    }
    
    const response = await fetch(url);
    const newLogs = await response.json();
    
    if (newLogs.length > 0) {
        logs = [...newLogs, ...logs].slice(0, 100);
        renderLogs();
    }
}

// Update resource bars
function updateResourceBars(resources) {
    Object.entries(resources).forEach(([key, value]) => {
        const bar = document.getElementById(`${key}Bar`);
        const text = document.getElementById(`${key}Text`);
        
        bar.style.width = `${value}%`;
        text.textContent = `${value}%`;
        
        // Color based on usage
        bar.className = bar.className.replace(/bg-\w+-\d+/, '');
        if (value > 90) {
            bar.classList.add('bg-red-500');
        } else if (value > 70) {
            bar.classList.add('bg-yellow-500');
        } else {
            bar.classList.add('bg-green-500');
        }
    });
}

// Run health check
async function runHealthCheck(dbName) {
    const response = await fetch(`/api/databases/${dbName}/health-check`, {
        method: 'POST'
    });
    
    const result = await response.json();
    
    showToast(`Health check completed: ${result.status}`, 
        result.status === 'healthy' ? 'success' : 'warning');
    
    fetchMetrics();
}

// Stop monitoring
function stopMonitoring() {
    clearInterval(metricsInterval);
    clearInterval(logsInterval);
}

// Cleanup on page leave
window.addEventListener('beforeunload', stopMonitoring);
```

### Integrasi PHP

```php
// api/monitoring/metrics.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');

// Get system resources
$resources = [
    'cpu' => getCpuUsage(),
    'memory' => getMemoryUsage(),
    'disk' => getDiskUsage(),
    'io' => getIOStats()
];

// Get database health
$databases = [];
foreach ($client->listDBs() as $name) {
    $db = $client->selectDB($name);
    $metrics = $db->getHealthMetrics();
    $report = $db->getHealthReport();
    
    $databases[] = [
        'name' => $name,
        'status' => $report['status'],
        'collections' => $metrics['metrics']['total_collections'],
        'documents' => $metrics['metrics']['total_documents'],
        'size' => $metrics['metrics']['total_size_bytes'],
        'last_check' => date('c')
    ];
}

// Get performance metrics
$performance = [
    'query_latency' => getQueryLatencyHistory(),
    'throughput' => getThroughputHistory(),
    'error_rate' => getErrorRateHistory()
];

echo json_encode([
    'resources' => $resources,
    'databases' => $databases,
    'performance' => $performance
]);

// Helper functions
function getCpuUsage() {
    // Linux
    $load = sys_getloadavg();
    return min(100, $load[0] * 100 / 4); // Assuming 4 cores
}

function getMemoryUsage() {
    $free = shell_exec('free');
    $free = (string)trim($free);
    $free_arr = explode("\n", $free);
    $mem = explode(" ", $free_arr[1]);
    $mem = array_filter($mem, function($value) { return ($value !== null && $value !== false && $value !== ''); });
    $mem = array_merge($mem);
    return round($mem[2] / $mem[1] * 100, 2);
}

function getDiskUsage() {
    $total = disk_total_space('/var/www/data');
    $free = disk_free_space('/var/www/data');
    return round(($total - $free) / $total * 100, 2);
}
?>

// api/monitoring/logs.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$logsDb = $client->selectDB('_logs');
$logs = $logsDb->system_logs;

$since = $_GET['since'] ?? null;
$level = $_GET['level'] ?? null;

$criteria = [];
if ($since) {
    $criteria['timestamp'] = ['$gt' => $since];
}
if ($level) {
    $criteria['level'] = $level;
}

$data = $logs->find($criteria)
    ->sort(['timestamp' => -1])
    ->limit(50)
    ->toArray();

echo json_encode($data);
?>
```

---

## 11. Halaman Security

**File:** `security.html`

### Deskripsi
Halaman untuk mengelola enkripsi, audit log, dan keamanan sistem.

### Komponen UI

#### A. Encryption Status Cards

| Card | Data |
|------|------|
| Master Key Status | Active/Expired/Not Set |
| Encrypted Databases | Count |
| Key Rotation | Days until next rotation |
| Last Audit | Timestamp |

#### B. Encryption Keys Table

| Kolom | Deskripsi |
|-------|-----------|
| Key ID | Identifier unik |
| Type | Master/Collection |
| Database | Database terkait |
| Collection | Collection terkait (jika collection key) |
| Created | Tanggal pembuatan |
| Last Rotated | Tanggal rotasi terakhir |
| Status | Active/Pending Rotation/Expired |
| Actions | Rotate, Revoke, Export |

#### C. Key Management Actions

| Action | Deskripsi |
|--------|-----------|
| Generate Master Key | Buat master key baru |
| Rotate Key | Rotasi key yang dipilih |
| Import Key | Import key dari file |
| Export Key | Export key (encrypted) |
| Revoke Key | Cabut key (dengan re-encryption) |

#### D. Audit Log Table

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu event |
| User | User yang melakukan |
| Action | Tipe aksi |
| Resource | Database/Collection/Document affected |
| IP Address | IP address |
| Status | Success/Failed |
| Details | Expandable details |

#### E. Security Settings

| Setting | Deskripsi |
|---------|-----------|
| Password Policy | Min length, complexity |
| Session Timeout | Auto logout duration |
| 2FA | Enable/require 2FA |
| IP Whitelist | Allowed IP addresses |
| Rate Limiting | Request limits |

### Fungsi JavaScript

```javascript
// Load encryption keys
async function loadEncryptionKeys() {
    const response = await fetch('/api/security/keys');
    const keys = await response.json();
    renderKeysTable(keys);
}

// Rotate encryption key
async function rotateKey(keyId) {
    showConfirmDialog({
        title: 'Rotate Encryption Key',
        message: 'This will re-encrypt all data with a new key. This may take some time for large databases.',
        confirmText: 'Rotate Key',
        onConfirm: async () => {
            showLoading('Rotating key...');
            
            const response = await fetch(`/api/security/keys/${keyId}/rotate`, {
                method: 'POST'
            });
            
            hideLoading();
            
            if (response.ok) {
                showToast('Key rotated successfully', 'success');
                loadEncryptionKeys();
            } else {
                const error = await response.json();
                showToast(error.message, 'error');
            }
        }
    });
}

// Load audit logs
async function loadAuditLogs(filters = {}) {
    const params = new URLSearchParams(filters);
    const response = await fetch(`/api/security/audit-logs?${params}`);
    const logs = await response.json();
    renderAuditTable(logs);
}

// Export audit logs
async function exportAuditLogs(format = 'csv') {
    const response = await fetch(`/api/security/audit-logs/export?format=${format}`);
    const blob = await response.blob();
    
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `audit_log_${Date.now()}.${format}`;
    a.click();
    URL.revokeObjectURL(url);
}

// Update security settings
async function updateSecuritySettings(settings) {
    const response = await fetch('/api/security/settings', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(settings)
    });
    
    if (response.ok) {
        showToast('Security settings updated', 'success');
    }
}
```

### Integrasi PHP

```php
// api/security/keys.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$securityDb = $client->selectDB('_security');
$keys = $securityDb->encryption_keys;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $data = $keys->find()->toArray();
        
        // Hide actual key values
        foreach ($data as &$key) {
            $key['key_preview'] = substr($key['key'], 0, 8) . '...';
            unset($key['key']);
        }
        
        echo json_encode($data);
        break;
        
    case 'POST':
        // Generate new key
        $keyType = $_GET['type'] ?? 'collection';
        $database = $_GET['database'] ?? null;
        $collection = $_GET['collection'] ?? null;
        
        $newKey = bin2hex(random_bytes(32)); // 256-bit key
        
        $keyData = [
            'key' => $newKey,
            'type' => $keyType,
            'database' => $database,
            'collection' => $collection,
            'created_at' => date('c'),
            'status' => 'active'
        ];
        
        $id = $keys->insert($keyData);
        
        // Log to audit
        logAudit('key_created', [
            'key_id' => $id,
            'type' => $keyType,
            'database' => $database
        ]);
        
        echo json_encode([
            'success' => true,
            '_id' => $id,
            'key_preview' => substr($newKey, 0, 8) . '...'
        ]);
        break;
}
?>

// api/security/audit-logs.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$auditDb = $client->selectDB('_audit');
$logs = $auditDb->logs;

$page = (int)($_GET['page'] ?? 1);
$limit = (int)($_GET['limit'] ?? 50);
$user = $_GET['user'] ?? null;
$action = $_GET['action'] ?? null;
$dateFrom = $_GET['from'] ?? null;
$dateTo = $_GET['to'] ?? null;

$criteria = [];
if ($user) $criteria['user'] = $user;
if ($action) $criteria['action'] = $action;
if ($dateFrom || $dateTo) {
    $criteria['timestamp'] = [];
    if ($dateFrom) $criteria['timestamp']['$gte'] = $dateFrom;
    if ($dateTo) $criteria['timestamp']['$lte'] = $dateTo;
}

$total = $logs->count($criteria);
$data = $logs->find($criteria)
    ->sort(['timestamp' => -1])
    ->skip(($page - 1) * $limit)
    ->limit($limit)
    ->toArray();

echo json_encode([
    'logs' => $data,
    'total' => $total,
    'totalPages' => ceil($total / $limit)
]);
?>
```

---

## 12. Halaman Settings

**File:** `settings.html`

### Deskripsi
Halaman konfigurasi sistem dan preferences.

### Komponen UI

#### A. Settings Categories

| Tab | Content |
|-----|---------|
| General | Basic system settings |
| Performance | Cache, limits, optimization |
| Backup | Backup schedule, retention |
| Developer | Debug mode, API settings |

#### B. General Settings

| Setting | Type | Deskripsi |
|---------|------|-----------|
| Data Directory | Path | Lokasi file database |
| Default Database | Select | Database default |
| Log Level | Select | debug/info/warning/error |
| Timezone | Select | Server timezone |
| Language | Select | UI language |

#### C. Performance Settings

| Setting | Type | Deskripsi |
|---------|------|-----------|
| Query Timeout | Number | Max query duration (seconds) |
| Max Results | Number | Max documents per query |
| WAL Mode | Toggle | Write-Ahead Logging |
| Cache Size | Number | SQLite cache size (MB) |
| Auto Vacuum | Toggle | Automatic space reclaim |

#### D. Backup Settings

| Setting | Type | Deskripsi |
|---------|------|-----------|
| Auto Backup | Toggle | Enable scheduled backups |
| Backup Schedule | Select | Daily/Weekly/Monthly |
| Backup Time | Time | Waktu backup |
| Retention Days | Number | Berapa lama simpan backup |
| Backup Path | Path | Lokasi file backup |
| Compression | Toggle | Compress backup files |

#### E. Developer Settings

| Setting | Type | Deskripsi |
|---------|------|-----------|
| Debug Mode | Toggle | Enable debug output |
| Query Logging | Toggle | Log semua queries |
| API Rate Limit | Number | Requests per minute |
| CORS Origins | Text | Allowed origins |
| API Key | Text (masked) | API authentication key |

### Fungsi JavaScript

```javascript
// Load settings
async function loadSettings() {
    const response = await fetch('/api/settings');
    const settings = await response.json();
    
    populateForm(settings);
}

// Save settings
async function saveSettings(section, data) {
    const response = await fetch(`/api/settings/${section}`, {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (response.ok) {
        showToast('Settings saved', 'success');
    } else {
        const error = await response.json();
        showToast(error.message, 'error');
    }
}

// Test backup
async function testBackup() {
    showLoading('Running test backup...');
    
    const response = await fetch('/api/settings/backup/test', {
        method: 'POST'
    });
    
    hideLoading();
    
    if (response.ok) {
        const result = await response.json();
        showToast(`Test backup successful: ${result.file}`, 'success');
    } else {
        showToast('Test backup failed', 'error');
    }
}

// Reset to defaults
function resetToDefaults(section) {
    showConfirmDialog({
        title: 'Reset to Defaults',
        message: `Are you sure you want to reset ${section} settings to defaults?`,
        onConfirm: async () => {
            await fetch(`/api/settings/${section}/reset`, { method: 'POST' });
            loadSettings();
            showToast('Settings reset to defaults', 'success');
        }
    });
}
```

### Integrasi PHP

```php
// api/settings.php
<?php

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$configDb = $client->selectDB('_config');
$settings = $configDb->settings;

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $section = $_GET['section'] ?? null;
        
        if ($section) {
            $data = $settings->findOne(['section' => $section]);
        } else {
            $data = [];
            $allSettings = $settings->find()->toArray();
            foreach ($allSettings as $setting) {
                $data[$setting['section']] = $setting['values'];
            }
        }
        
        echo json_encode($data);
        break;
        
    case 'PUT':
        $section = $_GET['section'];
        $values = json_decode(file_get_contents('php://input'), true);
        
        // Validate settings
        $validated = validateSettings($section, $values);
        
        $settings->save([
            '_id' => $section,
            'section' => $section,
            'values' => $validated,
            'updated_at' => date('c')
        ]);
        
        // Apply settings that need immediate effect
        applySettings($section, $validated);
        
        echo json_encode(['success' => true]);
        break;
}

function validateSettings($section, $values) {
    $rules = [
        'general' => [
            'data_directory' => 'required|path',
            'log_level' => 'required|in:debug,info,warning,error'
        ],
        'performance' => [
            'query_timeout' => 'required|integer|min:1|max:300',
            'max_results' => 'required|integer|min:1|max:10000'
        ]
    ];
    
    // Validate and sanitize
    // ...
    
    return $values;
}

function applySettings($section, $values) {
    switch ($section) {
        case 'performance':
            if (isset($values['wal_mode'])) {
                // Apply WAL mode to all databases
            }
            break;
    }
}
?>
```

---

## 13. Halaman Profile

**File:** `profile.html`

### Deskripsi
Halaman profil dan pengaturan akun pengguna.

### Komponen UI

#### A. Profile Header

| Elemen | Deskripsi |
|--------|-----------|
| Avatar | Foto profil dengan upload button |
| Name | Nama pengguna |
| Email | Email address |
| Role Badge | Admin/Editor/Viewer |
| Member Since | Tanggal bergabung |

#### B. Profile Tabs

| Tab | Content |
|-----|---------|
| Personal Info | Edit nama, email, bio |
| Security | Password, 2FA |
| Notifications | Email/push preferences |
| Sessions | Active sessions |
| Activity | Recent activity log |

#### C. Personal Info Form

| Field | Type | Validasi |
|-------|------|----------|
| Full Name | Text | Required, 2-100 chars |
| Email | Email | Required, valid format |
| Phone | Tel | Optional |
| Bio | Textarea | Optional, max 500 chars |
| Timezone | Select | Required |
| Language | Select | Required |

#### D. Security Section

| Component | Deskripsi |
|-----------|-----------|
| Current Password | Required untuk perubahan |
| New Password | Min 8 chars, complexity |
| Confirm Password | Must match |
| 2FA Toggle | Enable/disable |
| 2FA Setup | QR code + backup codes |

#### E. Sessions Table

| Kolom | Deskripsi |
|-------|-----------|
| Device | Browser/OS info |
| IP Address | Login IP |
| Location | Geo location |
| Last Active | Timestamp |
| Status | Current/Active/Expired |
| Actions | Revoke |

#### F. Activity Log

| Kolom | Deskripsi |
|-------|-----------|
| Time | Timestamp |
| Action | Login, Update, etc |
| Details | Action description |
| IP | IP address |

### Fungsi JavaScript

```javascript
// Load profile data
async function loadProfile() {
    const response = await fetch('/api/profile');
    const profile = await response.json();
    
    populateProfileForm(profile);
    renderSessions(profile.sessions);
    renderActivity(profile.activity);
}

// Update profile
async function updateProfile(data) {
    const response = await fetch('/api/profile', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (response.ok) {
        showToast('Profile updated', 'success');
    }
}

// Upload avatar
async function uploadAvatar(file) {
    const formData = new FormData();
    formData.append('avatar', file);
    
    const response = await fetch('/api/profile/avatar', {
        method: 'POST',
        body: formData
    });
    
    if (response.ok) {
        const result = await response.json();
        document.getElementById('avatarImg').src = result.url;
        showToast('Avatar updated', 'success');
    }
}

// Change password
async function changePassword(currentPassword, newPassword) {
    const response = await fetch('/api/profile/password', {
        method: 'PUT',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ currentPassword, newPassword })
    });
    
    if (response.ok) {
        showToast('Password changed', 'success');
        document.getElementById('passwordForm').reset();
    } else {
        const error = await response.json();
        showToast(error.message, 'error');
    }
}

// Enable 2FA
async function enable2FA() {
    const response = await fetch('/api/profile/2fa/enable', {
        method: 'POST'
    });
    
    if (response.ok) {
        const result = await response.json();
        showQRCode(result.qrCode);
        showBackupCodes(result.backupCodes);
    }
}

// Revoke session
async function revokeSession(sessionId) {
    const response = await fetch(`/api/profile/sessions/${sessionId}`, {
        method: 'DELETE'
    });
    
    if (response.ok) {
        showToast('Session revoked', 'success');
        loadProfile();
    }
}
```

### Integrasi PHP

```php
// api/profile.php
<?php

session_start();

use BangronDB\Client;

$client = new Client('/var/www/data/databases');
$authDb = $client->selectDB('_auth');
$users = $authDb->users;

$userId = $_SESSION['user_id'];

switch ($_SERVER['REQUEST_METHOD']) {
    case 'GET':
        $user = $users->findOne(['_id' => $userId]);
        unset($user['password']);
        
        // Get sessions
        $sessions = $authDb->sessions->find(['user_id' => $userId])
            ->sort(['last_active' => -1])
            ->toArray();
        
        // Get activity
        $activity = $authDb->activity_logs->find(['user_id' => $userId])
            ->sort(['timestamp' => -1])
            ->limit(50)
            ->toArray();
        
        echo json_encode([
            'profile' => $user,
            'sessions' => $sessions,
            'activity' => $activity
        ]);
        break;
        
    case 'PUT':
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Remove protected fields
        unset($data['_id'], $data['password'], $data['role']);
        
        $data['updated_at'] = date('c');
        
        $users->update(['_id' => $userId], $data);
        
        echo json_encode(['success' => true]);
        break;
}
?>
```

---

## 14. Halaman Hooks & Events

**File:** `hooks.html`

### Deskripsi
Halaman untuk mengelola event hooks yang dijalankan sebelum/sesudah operasi database.

### Komponen UI

#### A. Statistics Cards

| Card | Data |
|------|------|
| Total Hooks | Jumlah semua hook |
| Active Hooks | Hook yang aktif |
| Executions Today | Jumlah eksekusi hari ini |
| Failed Executions | Hook yang gagal |

#### B. Event Types Overview

| Event | Deskripsi |
|-------|-----------|
| beforeInsert | Sebelum insert document |
| afterInsert | Setelah insert document |
| beforeUpdate | Sebelum update document |
| afterUpdate | Setelah update document |
| beforeRemove | Sebelum delete document |
| afterRemove | Setelah delete document |

#### C. Hooks Table

| Kolom | Deskripsi |
|-------|-----------|
| Status | Active/Inactive toggle |
| Name | Nama hook |
| Event | Event type badge |
| Collection | Target collection |
| Priority | Urutan eksekusi |
| Executions | Jumlah eksekusi |
| Errors | Jumlah error |
| Actions | Edit, Duplicate, Delete |

#### D. Create Hook Modal

| Field | Type | Deskripsi |
|-------|------|-----------|
| Hook Name | Text | Nama deskriptif |
| Event Type | Select | beforeInsert, afterInsert, dll |
| Database | Select | Target database |
| Collection | Select | Target collection |
| Priority | Number | Urutan eksekusi (1-100) |
| Active | Toggle | Aktifkan hook |
| Async | Toggle | Jalankan secara async |
| Code | Code Editor | PHP callback code |

#### E. Quick Templates

| Template | Deskripsi |
|----------|-----------|
| Auto Timestamps | Tambah created_at/updated_at |
| Send Email | Kirim email notification |
| Validation | Custom validation logic |
| Audit Log | Log perubahan ke audit |
| Slugify | Generate slug dari title |

### Integrasi PHP

```php
// Contoh hook registration
$collection->on('beforeInsert', function($document) {
    $document['created_at'] = date('Y-m-d H:i:s');
    $document['updated_at'] = $document['created_at'];
    return $document;
});

$collection->on('afterInsert', function($document, $insertId) {
    // Log ke audit
    logAudit('insert', $insertId, $document);
});

$collection->on('beforeRemove', function($document) {
    if ($document['protected'] ?? false) {
        return false; // Cancel removal
    }
});
```

---

## 15. Halaman Backup & Restore

**File:** `backup.html`

### Deskripsi
Halaman untuk mengelola backup database, schedule, dan restore.

### Komponen UI

#### A. Statistics Cards

| Card | Data |
|------|------|
| Total Backups | Jumlah file backup |
| Storage Used | Total ukuran backup |
| Last Backup | Waktu backup terakhir |
| Next Scheduled | Waktu backup berikutnya |

#### B. Active Backup Progress

| Elemen | Deskripsi |
|--------|-----------|
| Database Name | Database yang di-backup |
| Progress Bar | Persentase selesai |
| ETA | Estimasi waktu selesai |
| Speed | Transfer rate |
| Cancel Button | Batalkan backup |

#### C. Schedule Overview

| Schedule | Deskripsi |
|----------|-----------|
| Hourly | Backup setiap jam |
| Daily | Backup harian (configurable time) |
| Weekly | Backup mingguan (configurable day) |
| Monthly | Backup bulanan |

#### D. Backup History Table

| Kolom | Deskripsi |
|-------|-----------|
| Database | Nama database |
| Type | Full/Incremental/Manual |
| Size | Ukuran file backup |
| Created | Waktu pembuatan |
| Status | Complete/Failed/In Progress |
| Actions | Restore, Download, Delete |

#### E. Create Backup Modal

| Field | Type | Deskripsi |
|-------|------|-----------|
| Database | Select | Database to backup |
| Type | Select | Full/Incremental |
| Collections | Multi-select | Specific collections (optional) |
| Include Indexes | Toggle | Backup index definitions |
| Compress | Toggle | Compress backup file |
| Encrypt | Toggle | Encrypt backup |

#### F. Restore Modal

| Field | Type | Deskripsi |
|-------|------|-----------|
| Backup File | Select | Pilih backup file |
| Target Database | Text | Nama database tujuan |
| Restore Mode | Select | Overwrite/Merge/New |
| Confirm Checkbox | Checkbox | Konfirmasi restore |

### Integrasi PHP

```php
// Manual backup
copy('/path/to/database.bangron', '/backup/database-backup.bangron');

// Scheduled backup dengan cron
// 0 2 * * * php /path/to/backup-script.php

// Backup script
$client = new Client('/var/www/data/databases');
foreach ($client->listDBs() as $dbName) {
    $source = "/var/www/data/databases/{$dbName}.bangron";
    $dest = "/backup/{$dbName}_" . date('Y-m-d_His') . ".bangron";
    copy($source, $dest);
}
```

---

## 16. Halaman Import/Export

**File:** `import-export.html`

### Deskripsi
Halaman untuk import dan export data dalam berbagai format.

### Komponen UI

#### A. Tab Interface

| Tab | Fungsi |
|-----|--------|
| Import | Import data ke collection |
| Export | Export data dari collection |
| Migration | Migrasi data antar collection/database |

#### B. Import Tab

**Format Support:**

| Format | Deskripsi |
|--------|-----------|
| JSON | Array of documents atau NDJSON |
| CSV | Comma-separated values dengan header |
| SQL | SQL INSERT statements |
| MongoDB | MongoDB export format |

**Import Options:**

| Option | Deskripsi |
|--------|-----------|
| Target Database | Database tujuan |
| Target Collection | Collection tujuan |
| Import Mode | Insert Only / Upsert / Replace |
| Validate Schema | Validasi dengan schema collection |
| Generate ID | Auto-generate _id jika tidak ada |
| Skip Invalid | Skip dokumen yang tidak valid |
| Dry Run | Preview tanpa insert |

**CSV Options:**

| Option | Deskripsi |
|--------|-----------|
| Delimiter | , ; \t |
| Encoding | UTF-8, ISO-8859-1 |
| Header Row | First row is header |
| Skip Rows | Skip N rows from start |

#### C. Export Tab

**Export Options:**

| Option | Deskripsi |
|--------|-----------|
| Source Database | Database sumber |
| Collections | Pilih collections |
| Filter Query | MongoDB-style filter |
| Field Selection | Pilih fields untuk export |
| Format | JSON, CSV, SQL, Excel |
| Options | Pretty print, Include _id, Decrypt |

#### D. Migration Tab

| Panel | Konten |
|-------|--------|
| Source | Database, Collection selector |
| Transform | Field mapping, Transform script |
| Destination | Database, Collection, Mode |

### Integrasi PHP

```php
// Import JSON
$data = json_decode(file_get_contents('import.json'), true);
$collection->insert($data);

// Export to JSON
$documents = $collection->find()->toArray();
file_put_contents('export.json', json_encode($documents, JSON_PRETTY_PRINT));

// Migration
$oldCollection = $db->old_users;
$newCollection = $db->users;

foreach ($oldCollection->find() as $doc) {
    // Transform data
    $doc['migrated_at'] = date('Y-m-d H:i:s');
    $newCollection->insert($doc);
}
```

---

## 17. Halaman Logs

**File:** `logs.html`

### Deskripsi
Halaman untuk melihat system logs, audit trail, dan query logs.

### Komponen UI

#### A. Tab Interface

| Tab | Konten |
|-----|--------|
| System Logs | Server dan database logs |
| Audit Trail | User action audit |
| Query Logs | Query execution history |
| Error Logs | Error dan exception logs |

#### B. Filter Bar

| Filter | Options |
|--------|---------|
| Search | Full-text search |
| Level | All, Debug, Info, Warning, Error |
| Source | All, Database, Collection, System |
| User | All users / Specific user |
| Date Range | From - To date picker |

#### C. System Logs Tab

| Feature | Deskripsi |
|---------|-----------|
| Real-time viewer | Auto-scroll dengan new logs |
| Log levels | Color-coded by level |
| Line numbers | Nomor baris |
| Wrap toggle | Toggle line wrap |
| Live stream | WebSocket / Polling |

#### D. Audit Trail Tab

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu event |
| User | Avatar + nama |
| Action | LOGIN, CREATE, UPDATE, DELETE, dll |
| Resource | Database/Collection/Document affected |
| Details | Expandable detail |
| IP Address | Client IP |

#### E. Query Logs Tab

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu query |
| Query | Query preview |
| Duration | Execution time |
| Rows | Rows affected/returned |
| Slow Query | Warning jika slow |
| Index Used | Index yang digunakan |

#### F. Error Logs Tab

| Kolom | Deskripsi |
|-------|-----------|
| Timestamp | Waktu error |
| Severity | Error level |
| Message | Error message |
| Stack Trace | Expandable stack trace |
| Context | Request context |
| Status | Active/Resolved |

### Integrasi PHP

```php
// Enable query logging
$collection->on('afterInsert', function($doc, $id) {
    error_log("INSERT: " . $id);
});

$collection->on('afterUpdate', function($old, $new) {
    error_log("UPDATE: " . $new['_id']);
});

// Custom logging
function logAudit($action, $resource, $details = []) {
    $auditDb = $client->selectDB('_audit');
    $auditDb->logs->insert([
        'timestamp' => date('c'),
        'user_id' => $_SESSION['user_id'],
        'action' => $action,
        'resource' => $resource,
        'details' => $details,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
}
```

---

## 18. Halaman Notifications

**File:** `notifications.html`

### Deskripsi
Notification center untuk melihat dan mengelola notifikasi sistem.

### Komponen UI

#### A. Statistics Cards

| Card | Data |
|------|------|
| Unread | Notifikasi belum dibaca |
| Alerts | Notifikasi penting |
| Today | Notifikasi hari ini |
| Subscriptions | Jumlah subscription aktif |

#### B. Notification Tabs

| Tab | Filter |
|-----|--------|
| All | Semua notifikasi |
| Unread | Belum dibaca |
| Alerts | Alert dan warning |
| System | Sistem notifications |
| Changes | Database change notifications |

#### C. Notification Item

| Elemen | Deskripsi |
|--------|-----------|
| Icon | Berdasarkan tipe (alert, info, success) |
| Title | Judul notifikasi |
| Message | Pesan detail |
| Time | Relative time (e.g., "2m ago") |
| Unread Indicator | Blue dot untuk unread |
| Type Badges | Alert, Change, Security, dll |

#### D. Subscriptions Panel

| Subscription | Deskripsi |
|--------------|-----------|
| Database Subscription | Notif untuk specific database |
| Collection Subscription | Notif untuk specific collection |
| Security Alerts | Login attempts, permission changes |
| Backup Status | Backup success/failure |

#### E. Preferences Modal

| Category | Options |
|----------|---------|
| Database Changes | In-App, Email toggles |
| Security Alerts | In-App, Email toggles |
| Backup Status | In-App, Email toggles |
| System Health | In-App, Email toggles |
| Hook Executions | In-App, Email toggles |
| Quiet Hours | From - To time picker |
| Retention | Auto-delete after N days |

### Integrasi PHP

```php
// Get collection change info
$changeInfo = $users->getLastModified();
echo "Version: {$changeInfo['version']}";
echo "Last updated: {$changeInfo['last_updated']}";

// Notification system
function sendNotification($type, $title, $message, $userId = null) {
    $notifDb = $client->selectDB('_notifications');
    $notifDb->notifications->insert([
        'type' => $type,
        'title' => $title,
        'message' => $message,
        'user_id' => $userId,
        'read' => false,
        'created_at' => date('c')
    ]);
}

// Change notification hook
$collection->on('afterInsert', function($doc, $id) {
    $collection->notifyChange();
    sendNotification('change', 'New Document', "Document {$id} created");
});
```

---

## 19. Halaman Terminal

**File:** `terminal.html`

### Deskripsi
Interactive terminal untuk mengeksekusi perintah BangronDB langsung.

### Komponen UI

#### A. Terminal Tabs

| Tab | Fungsi |
|-----|--------|
| Terminal 1 | Tab terminal pertama |
| Terminal 2 | Tab terminal kedua |
| + New Tab | Buat tab baru |

#### B. Terminal Output

| Feature | Deskripsi |
|---------|-----------|
| Welcome Banner | ASCII art + version info |
| Command History | Riwayat perintah yang dijalankan |
| Colored Output | Syntax highlighting untuk results |
| Prompt | Database name + cursor |
| Auto-scroll | Scroll ke bawah otomatis |

#### C. Command Input

| Feature | Deskripsi |
|---------|-----------|
| Input Field | Command line input |
| Autocomplete | Tab completion |
| History Navigation | Up/Down arrow |
| Multiline | Support multiline commands |

#### D. Quick Reference Panel

| Section | Commands |
|---------|----------|
| Database | show databases, use, getHealthMetrics |
| Collection | show collections, createCollection, drop |
| CRUD | find, findOne, insert, update, remove |
| Query Operators | $gt, $in, $or, $regex, dll |
| Special | count, sort, limit, populate |
| Shortcuts | Enter, ↑/↓, Tab, Ctrl+L, Ctrl+C |

#### E. Saved Commands

| Feature | Deskripsi |
|---------|-----------|
| Star Command | Simpan command favorit |
| Quick Access | Klik untuk insert command |
| Manage | Edit/delete saved commands |

### Commands Support

```bash
# Database commands
show databases
use database_name
db.getHealthMetrics()
db.getHealthReport()
db.vacuum()

# Collection commands
show collections
db.createCollection("name")
db.collection.drop()
db.collection.count()

# CRUD operations
db.collection.find({})
db.collection.findOne({ _id: "xxx" })
db.collection.insert({ name: "John" })
db.collection.update({ _id: "xxx" }, { $set: { name: "Jane" } })
db.collection.remove({ status: "inactive" })

# Advanced queries
db.collection.find({ age: { $gt: 18 } }).sort({ name: 1 }).limit(10)
db.collection.find().populate("author_id", db.users).toArray()
db.collection.find().withTrashed().toArray()
```

---

## 20. Halaman API Docs

**File:** `api-docs.html`

### Deskripsi
Dokumentasi API interaktif dengan contoh kode dan testing.

### Komponen UI

#### A. Navigation Panel

| Section | Methods |
|---------|---------|
| Introduction | Overview, Authentication, Errors |
| Client | __construct, listDBs, selectDB, close |
| Database | selectCollection, createCollection, dropCollection, getHealthMetrics |
| Collection | insert, find, findOne, update, remove, count, save |
| Schema | setSchema, getSchema, validate |
| Soft Deletes | useSoftDeletes, restore, forceDelete, withTrashed |
| Encryption | setEncryptionKey, setSearchableFields |
| Hooks | on, off, Available Events |
| Relationships | populate, Cross-Database |
| Query Operators | Comparison, Logical, Array, Regex |

#### B. Method Documentation

| Section | Konten |
|---------|--------|
| Method Badge | GET/POST/PUT/DELETE style badge |
| Description | Deskripsi method |
| Returns | Return type dan deskripsi |
| Parameters | Table dengan name, type, required, description |
| Examples | Code samples dengan syntax highlighting |

#### C. Code Examples

| Language | Format |
|----------|--------|
| PHP | Syntax highlighted PHP code |

#### D. Try It Panel

| Feature | Deskripsi |
|---------|-----------|
| Input Fields | Parameter inputs |
| Execute Button | Run API call |
| Response | JSON response output |

### API Categories

**Client Methods:**
- `__construct($path, $options)`
- `listDBs()`
- `selectDB($name)`
- `close()`

**Collection Methods:**
- `insert($document)`
- `find($criteria, $projection)`
- `findOne($criteria, $projection)`
- `update($criteria, $data, $merge)`
- `remove($criteria)`
- `count($criteria)`

**Schema Methods:**
- `setSchema($schema)`
- `getSchema()`
- `validate($document)`

**Relationship Methods:**
- `populate($path, $collection, $options)`

---

## 21. Halaman Relationships

**File:** `relationships.html`

### Deskripsi
Visual diagram untuk melihat dan mengelola relationships antar collections.

### Komponen UI

#### A. Toolbar

| Tool | Fungsi |
|------|--------|
| Database Selector | Pilih database |
| Zoom In/Out | Zoom diagram |
| Fit to Screen | Auto-fit view |
| Auto Layout | Arrange nodes otomatis |
| Add Relation | Tambah relasi baru |
| Export | Export diagram sebagai gambar |

#### B. Diagram Canvas

| Element | Deskripsi |
|---------|-----------|
| Collection Nodes | Kotak untuk setiap collection |
| Field List | Daftar fields dalam collection |
| Primary Key | Highlighted dengan key icon |
| Foreign Key | Highlighted dengan link icon |
| Relation Lines | Garis penghubung antar collections |
| Cross-DB Indicator | Dashed line untuk external DB |
| Drag & Drop | Pindahkan nodes |

#### C. Legend Panel

| Symbol | Meaning |
|--------|---------|
| Solid Purple Line | Local relation (same database) |
| Dashed Amber Line | Cross-database relation |
| Yellow Badge | Primary Key |
| Purple Badge | Foreign Key |

#### D. Relations Overview Panel

| Info | Deskripsi |
|------|-----------|
| Collections Count | Jumlah collection |
| Relations Count | Jumlah relasi |
| Relations List | Daftar semua relasi |
| PHP Code Example | Contoh kode populate |

#### E. Relation Item

| Field | Deskripsi |
|-------|-----------|
| Source → Target | e.g., "orders → users" |
| Type Badge | Local / Cross-DB |
| Fields | source_field → target_field |

#### F. Add Relation Modal

| Field | Type |
|-------|------|
| Source Collection | Select |
| Source Field | Select |
| Target Database | Select (for cross-db) |
| Target Collection | Select |
| Target Field | Select |
| On Delete | Restrict/Cascade/Set Null |
| Populate Alias | Text |

#### G. Quick Actions

| Action | Fungsi |
|--------|--------|
| Generate Migration | Generate PHP migration code |
| Validate Relations | Check relation integrity |
| Find Orphans | Find orphan records |

### Integrasi PHP

```php
// Basic populate
$posts = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->toArray();

// Cross-database populate
$orders = $db->orders->find()
    ->populate(
        'company_id',
        'company_db.companies',
        ['as' => 'company']
    )
    ->toArray();

// Multiple populates
$posts = $db->posts->find()
    ->populate('author_id', $db->users, ['as' => 'author'])
    ->populate('category_id', $db->categories, ['as' => 'category'])
    ->toArray();
```

---

## Integrasi PHP

### Struktur Folder Rekomendasi

```
project/
├── public/
│   ├── index.php          # Entry point
│   ├── css/
│   │   └── style.css      # Custom styles
│   └── js/
│       └── app.js         # Custom scripts
├── views/
│   ├── layouts/
│   │   ├── header.php     # Header template
│   │   ├── sidebar.php    # Sidebar template
│   │   └── footer.php     # Footer template
│   ├── dashboard.php
│   ├── databases/
│   │   ├── index.php
│   │   └── create.php
│   ├── collections/
│   │   ├── index.php
│   │   └── config.php
│   ├── documents/
│   │   ├── index.php
│   │   └── edit.php
│   └── ...
├── api/
│   ├── databases.php
│   ├── collections.php
│   ├── documents.php
│   └── ...
├── includes/
│   ├── auth.php           # Authentication
│   ├── helpers.php        # Helper functions
│   └── middleware.php     # Middleware
└── config/
    └── app.php            # Configuration
```

### Contoh Layout Base

```php
<!-- views/layouts/base.php -->
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'BangronDB Admin' ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="bg-slate-900 text-white">
    <div class="flex h-screen">
        <?php include 'sidebar.php'; ?>
        
        <div class="flex-1 flex flex-col overflow-hidden">
            <?php include 'header.php'; ?>
            
            <main class="flex-1 overflow-auto p-6">
                <?= $content ?>
            </main>
        </div>
    </div>
    
    <script>
        lucide.createIcons();
    </script>
    <?php if (isset($scripts)): ?>
        <?= $scripts ?>
    <?php endif; ?>
</body>
</html>
```

---

## API Endpoints

### Databases

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/databases | List all databases |
| POST | /api/databases | Create database |
| GET | /api/databases/{name} | Get database info |
| DELETE | /api/databases/{name} | Delete database |
| POST | /api/databases/{name}/backup | Create backup |
| POST | /api/databases/import | Import database |

### Collections

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/databases/{db}/collections | List collections |
| POST | /api/databases/{db}/collections | Create collection |
| GET | /api/databases/{db}/collections/{col} | Get collection info |
| DELETE | /api/databases/{db}/collections/{col} | Delete collection |
| PUT | /api/databases/{db}/collections/{col}/config | Update config |
| GET | /api/databases/{db}/collections/{col}/schema | Get schema |
| PUT | /api/databases/{db}/collections/{col}/schema | Save schema |

### Documents

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/.../documents | List documents |
| POST | /api/.../documents | Insert document |
| GET | /api/.../documents/{id} | Get document |
| PUT | /api/.../documents/{id} | Update document |
| DELETE | /api/.../documents/{id} | Delete document |
| POST | /api/.../documents/{id}/restore | Restore document |
| DELETE | /api/.../documents/{id}/force | Force delete |

### Users

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/users | List users |
| POST | /api/users | Create user |
| PUT | /api/users/{id} | Update user |
| DELETE | /api/users/{id} | Delete user |
| PUT | /api/users/{id}/permissions | Update permissions |
| POST | /api/users/invite | Invite user |
| POST | /api/users/bulk | Bulk actions |

### Roles

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/roles | List roles |
| POST | /api/roles | Create role |
| PUT | /api/roles/{id} | Update role |
| DELETE | /api/roles/{id} | Delete role |

### Security

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/security/keys | List encryption keys |
| POST | /api/security/keys | Generate new key |
| POST | /api/security/keys/{id}/rotate | Rotate key |
| GET | /api/security/audit-logs | Get audit logs |
| GET | /api/security/audit-logs/export | Export logs |
| PUT | /api/security/settings | Update security settings |

### Monitoring

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/monitoring/metrics | Get metrics |
| GET | /api/monitoring/logs | Get live logs |
| POST | /api/databases/{db}/health-check | Run health check |

### Settings

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/settings | Get all settings |
| GET | /api/settings/{section} | Get section settings |
| PUT | /api/settings/{section} | Update section |
| POST | /api/settings/{section}/reset | Reset to defaults |
| POST | /api/settings/backup/test | Test backup |

### Profile

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| GET | /api/profile | Get current user profile |
| PUT | /api/profile | Update profile |
| POST | /api/profile/avatar | Upload avatar |
| PUT | /api/profile/password | Change password |
| POST | /api/profile/2fa/enable | Enable 2FA |
| DELETE | /api/profile/sessions/{id} | Revoke session |

### Query

| Method | Endpoint | Deskripsi |
|--------|----------|-----------|
| POST | /api/query/execute | Execute query |

---

## Changelog

### Version 1.0.0

- Initial release dengan semua halaman
- Login, Dashboard, Databases, Collections
- Schema Builder dengan visual designer
- Documents dengan form builder
- Query Playground
- User & Role Management
- Monitoring & Security
- Settings & Profile

---

**BangronDB Admin Panel** © 2024
