# BangronDB Admin Panel - HTML Templates

Template HTML murni untuk Admin Panel BangronDB yang siap dikonversi ke PHP.

## 📁 Struktur File

```
html_templates/
├── index.html          # Dashboard utama
├── login.html          # Halaman login
├── profile.html        # Profil pengguna
├── databases.html      # Manajemen database
├── collections.html    # Manajemen collection & schema
├── schema-builder.html # Visual schema designer (NEW!)
├── documents.html      # Browse & edit dokumen
├── query-playground.html # Query testing
├── users.html          # Manajemen pengguna
├── roles.html          # Manajemen role & permissions
├── monitoring.html     # Health monitoring
├── security.html       # Keamanan & enkripsi
├── settings.html       # Pengaturan sistem
└── README.md           # Dokumentasi ini
```

## 🎨 Fitur UI/UX

### 1. **Dashboard** (`index.html`)
- Statistik database (total DB, collections, documents, users)
- Database overview dengan storage usage
- System health monitoring (CPU, Memory, Disk)
- Recent activity feed
- Quick actions

### 2. **Login** (`login.html`)
- Form login dengan animated background
- Social login buttons (Google, GitHub)
- Remember me & forgot password
- Password visibility toggle

### 3. **Profile** (`profile.html`)
- Tab-based navigation (Profile, Security, Notifications, Activity, Sessions)
- Personal information form
- Change password
- Two-factor authentication settings
- Notification preferences
- Active sessions management
- Activity log

### 4. **Databases** (`databases.html`)
- Database cards dengan status enkripsi
- Create database modal
- Database settings modal (WAL mode, metadata)
- Storage usage indicators

### 5. **Schema Builder** (`schema-builder.html`) ⭐ NEW!
- **Visual Schema Designer** - Drag & drop fields dari palette
- **Field Types lengkap** dengan opsi spesifik per tipe:
  - `string` - Regex, Min/Max Length, Default
  - `int` - Min/Max Value, Default
  - `float` - Min/Max Value, Precision
  - `boolean` - Default true/false
  - `date` - Format, Auto value (now/update)
  - `enum` - Allowed values, Default
  - `array` - Items type, Min/Max items
  - `object` - Nested fields definition
  - `relation` - Cross-database, On Delete, Populate Alias
- **Preview Tabs**:
  - JSON Schema - Copy-paste ready
  - PHP Code - Generated BangronDB implementation
  - Test Validation - Live document testing
  - Relations Diagram - Visual relationship map
- **Import/Export** - JSON file support
- **Field Management** - Duplicate, delete, reorder

### 6. **Collections** (`collections.html`)
- Collections table dengan stats
- **Schema Validation UI** dengan semua tipe data:
  - `string` - Regex, Min/Max Length
  - `int/float` - Min/Max Value
  - `boolean` - Toggle
  - `date` - Date picker
  - `enum` - Allowed values
  - `array` - Items type
  - `relation` - **Cross-database support**
- Global Preferences (Soft Deletes, Timestamps, Encryption)
- Searchable fields configuration

### 6. **Documents** (`documents.html`)
- Document list dengan pagination
- **Soft Delete filter** (Active, Trashed, All)
- JSON editor sidebar
- **Form-based insert** berdasarkan schema
- Toggle antara JSON view dan Form view

### 7. **Query Playground** (`query-playground.html`)
- MongoDB-style query editor
- Quick query examples
- Results panel
- Query history

### 8. **Users** (`users.html`)
- User table dengan avatar, role, status
- Create user modal (lengkap dengan database access)
- Edit user modal
- View user profile modal
- Permissions modal
- Invite user modal
- Role overview cards

### 9. **Roles** (`roles.html`)
- Role cards (Super Admin, Administrator, Editor, Viewer, API User)
- **Permissions Matrix** - Visual overview semua permissions per role
- Create/Edit role modal dengan permission groups
- Quick permission presets

### 10. **Monitoring** (`monitoring.html`)
- Real-time health checks
- Database metrics
- Live log stream

### 11. **Security** (`security.html`)
- Encryption key management
- Key rotation status
- Audit log table

### 12. **Settings** (`settings.html`)
- Server configuration
- Performance settings
- Backup settings
- Developer options

## 🚀 Cara Penggunaan

### Buka Langsung di Browser
1. Double-click file HTML manapun
2. Semua file menggunakan **Tailwind CSS via CDN** dan **Lucide Icons via CDN**
3. Tidak perlu build process

### Integrasi ke PHP

1. **Copy Sidebar** ke `includes/sidebar.php`:
```php
<?php
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
?>
<aside class="fixed left-0 top-0 h-full w-64 glass z-50">
    <!-- Copy dari <aside> di HTML -->
</aside>
```

2. **Copy Header** ke `includes/header.php`:
```php
<header class="glass sticky top-0 z-40 px-6 py-4">
    <!-- Copy dari <header> di HTML -->
</header>
```

3. **Buat Layout** di `layouts/main.php`:
```php
<!DOCTYPE html>
<html lang="id" class="dark">
<head>
    <meta charset="UTF-8">
    <title><?= $pageTitle ?> - BangronDB Admin</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- Copy CSS dari <style> di HTML -->
</head>
<body class="bg-dark-950 text-gray-100 min-h-screen">
    <?php include 'includes/sidebar.php'; ?>
    <div class="ml-64 min-h-screen">
        <?php include 'includes/header.php'; ?>
        <main class="p-6">
            <?= $content ?>
        </main>
    </div>
    <script>lucide.createIcons();</script>
</body>
</html>
```

4. **Buat View** di `views/dashboard.php`:
```php
<?php
$pageTitle = 'Dashboard';
ob_start();
?>

<!-- Copy konten <main> dari index.html -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6">
    <!-- Stats cards -->
</div>

<?php
$content = ob_get_clean();
include 'layouts/main.php';
?>
```

## 🔧 Customization

### Mengubah Warna
Edit Tailwind config di dalam `<script>`:
```javascript
tailwind.config = {
    theme: {
        extend: {
            colors: {
                dark: {
                    800: '#1e293b',  // Ubah warna
                    900: '#0f172a',
                    950: '#020617'
                }
            }
        }
    }
}
```

### Mengubah Glass Effect
Edit CSS class `.glass` dan `.glass-card`:
```css
.glass {
    background: rgba(30, 41, 59, 0.5);
    backdrop-filter: blur(12px);
    border: 1px solid rgba(255,255,255,0.1);
}
```

## 📋 Dependencies

Semua dependencies di-load via CDN:

- **Tailwind CSS**: `https://cdn.tailwindcss.com`
- **Lucide Icons**: `https://unpkg.com/lucide@latest`
- **Inter Font**: `https://fonts.googleapis.com/css2?family=Inter`

## 📱 Responsive Design

Semua halaman sudah responsive dengan:
- Mobile-first approach
- Collapsible sidebar
- Grid system yang adaptif
- Touch-friendly buttons

## 🔒 Fitur Keamanan UI

1. **Role-based visibility** - Contoh di users.html
2. **Permission indicators** - Badge dan icons
3. **Session management** - Di profile.html
4. **Audit logging** - Di security.html

---

**BangronDB Admin Panel** © 2024
