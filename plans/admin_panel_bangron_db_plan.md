# Arsitektur Production Admin Panel BangronDB (BangronDB Studio)

## 1. Tujuan Sistem
Admin panel berfungsi sebagai **Database Studio** untuk mengelola banyak database BangronDB secara aman, multi-tenant, dan enterprise‑ready.

Admin panel tidak menjadi bagian dari database aplikasi, tetapi bertindak sebagai **management layer**.

---

## 2. Arsitektur High Level

```
Browser
   ↓
Flight PHP Admin API
   ↓
Admin Services Layer
   ↓
System Database (admin.bangron)
   ↓
Managed Databases (app1.bangron, app2.bangron, dll)
```

---

## 3. Pemisahan Database

### System Database (admin.bangron)
Digunakan hanya untuk kebutuhan admin panel.

Collections:
- users (admin accounts)
- roles
- permissions
- role_permissions
- audit_logs
- database_registry
- settings

### Managed Databases
Database aplikasi yang dikelola admin panel:

```
/data/tenants/
   app1.bangron
   app2.bangron
   erp.bangron
   cms.bangron
```

---

## 4. Module Arsitektur Backend

### Auth Module
- Login admin
- Session / JWT
- Password hashing
- Role based access control (RBAC)

### Database Registry Module
- Menyimpan daftar database yang boleh diakses
- Metadata database
- Label / owner database

### Database Manager Module
- Create database
- Drop database
- Backup / restore database
- Health monitoring

### Collection Manager Module
- Create / rename / drop collection
- Manage schema
- Manage encryption
- Manage searchable fields
- Manage indexes

### Document Manager Module
- CRUD document
- Query builder
- Pagination / sorting
- JSON editor
- Bulk import / export

### Audit & Monitoring Module
- Audit setiap perubahan admin
- Activity logs
- Performance metrics
- Collection metrics

---

## 5. Backend Struktur Folder

```
admin-panel/
 ├─ app/
 │   ├─ Controllers/
 │   │   ├─ AuthController.php
 │   │   ├─ DashboardController.php
 │   │   ├─ DatabaseController.php
 │   │   ├─ CollectionController.php
 │   │   └─ DocumentController.php
 │   │
 │   ├─ Services/
 │   │   ├─ AuthService.php
 │   │   ├─ DatabaseService.php
 │   │   ├─ CollectionService.php
 │   │   ├─ DocumentService.php
 │   │   └─ AuditService.php
 │   │
 │   ├─ Middleware/
 │   │   ├─ AuthMiddleware.php
 │   │   └─ PermissionMiddleware.php
 │   │
 │   └─ routes.php
 │
 ├─ views/
 │   ├─ layouts/
 │   ├─ dashboard/
 │   ├─ databases/
 │   ├─ collections/
 │   └─ documents/
 │
 └─ public/index.php
```

---

## 6. RBAC Model

### Roles
- super_admin
- admin
- viewer

### Permissions Example
- database.create
- database.delete
- collection.manage
- document.write
- document.read

Permission dicek melalui middleware sebelum route dijalankan.

---

## 7. Runtime Flow

### Login Flow
Admin login → Auth dari **admin.bangron** → session dibuat

### Database Access Flow
1. Admin memilih database
2. Sistem memverifikasi permission
3. Service membuka database target
4. Operasi dilakukan pada database target
5. Audit log disimpan ke system database

---

## 8. Security Design

- System database tidak boleh bisa di‑drop melalui UI
- Encryption key tidak disimpan di database
- Semua operasi admin tercatat di audit_logs
- Middleware permission untuk setiap endpoint
- CSRF protection pada form

---

## 9. Roadmap Pengembangan

### Phase 1 — Core Studio
- Login admin
- Database registry
- Collection list
- Document CRUD

### Phase 2 — Advanced Tools
- Query builder visual
- Schema editor UI
- Index manager
- Searchable fields UI

### Phase 3 — Enterprise
- Monitoring dashboard
- Multi‑tenant isolation
- Audit viewer
- Backup manager

---

## 10. Vision

Arsitektur ini memungkinkan BangronDB memiliki **BangronDB Studio** setara MongoDB Compass atau PocketBase Admin, dan dapat digunakan:
- sebagai internal database manager
- sebagai SaaS database management tool
- sebagai bagian dari platform multi‑tenant BangronDB


---

## 11. System Database Schema (admin.bangron)

### users
Menyimpan akun admin panel.

Fields:
- _id (uuid)
- name
- email
- password_hash
- role_id
- status (active / suspended)
- created_at
- updated_at

---

### roles
Role global sistem.

Fields:
- _id
- name (super_admin, admin, viewer)
- description
- created_at

---

### permissions
Daftar permission sistem.

Fields:
- _id
- key (database.create, database.delete, collection.manage, document.write)
- description

---

### role_permissions
Mapping role ke permission.

Fields:
- _id
- role_id
- permission_key

---

### database_registry
Daftar database yang dikelola admin panel.

Fields:
- _id (database_name)
- label
- path
- owner_user_id
- created_at
- status

---

### database_permissions
Permission user terhadap database tertentu.

Fields:
- _id
- user_id
- database_name
- role (owner, admin, viewer)
- created_at

---

### audit_logs
Semua aktivitas admin tercatat di sini.

Fields:
- _id
- user_id
- action
- database_name
- collection_name
- document_id
- metadata (json)
- created_at

---

### settings
Konfigurasi global admin panel.

Fields:
- _id
- key
- value
- updated_at


---

## 12. Initial Setup Wizard (First Install)

Saat admin panel pertama kali dijalankan, sistem harus menampilkan **Setup Wizard** sebelum dapat digunakan.

### Tujuan Setup Wizard
- Membuat system database (`admin.bangron`)
- Membuat role default (`super_admin`)
- Membuat akun administrator pertama
- Membuat file `installed.lock` sebagai penanda sistem sudah ter‑install

### Setup Flow
1. User membuka `/setup`
2. Mengisi:
   - Administrator Name
   - Administrator Email
   - Administrator Password
3. Sistem membuat:
   - Collections system database (users, roles, permissions, dll)
   - Role `super_admin`
   - User admin pertama
4. Sistem membuat file:

```
/storage/installed.lock
```

5. Setelah selesai → redirect ke halaman login

### Runtime Check
Pada bootstrap aplikasi (`public/index.php`):

- Jika `installed.lock` belum ada → redirect ke `/setup`
- Jika sudah ada → aplikasi berjalan normal

### Security Notes
- Setup route hanya aktif jika belum ter‑install
- Setelah install, route `/setup` otomatis dinonaktifkan
- Password admin langsung di‑hash menggunakan bcrypt


---

## 13. Collection Schema Editor Flow (Admin UI)

Admin panel menyediakan **Schema Editor** untuk mengubah struktur collection secara visual.

### Tujuan
- Mengubah schema tanpa manual coding
- Mengatur searchable fields
- Mengatur encryption fields
- Menyimpan konfigurasi collection secara persistent

### UI Flow
1. Admin membuka halaman **Collection Settings**
2. Admin dapat:
   - Menambah / menghapus field
   - Mengubah tipe field
   - Mengaktifkan searchable field
   - Mengaktifkan encryption field
3. Admin menekan tombol **Save Configuration**

### Backend Flow
Saat tombol Save ditekan:

1. Sistem membuka database target
2. Load collection target
3. Update schema dan konfigurasi
4. Memanggil `saveConfiguration()`
5. Menulis log ke `audit_logs`

Pseudo flow:

```
collection->setSchema(newSchema)
collection->setSearchableFields(fields)
collection->setEncryptedFields(fields)
collection->saveConfiguration()
```

### Security Rules
- Hanya role dengan permission `collection.manage` yang boleh mengubah schema
- Semua perubahan schema wajib tercatat di audit_logs
- System collections (users, roles, dll) hanya boleh diubah oleh `super_admin`

### Production Notes
- Setelah save configuration, cache schema collection harus di-refresh
- UI harus menampilkan warning jika perubahan schema berpotensi menghapus data lama


---

## 14. Database Provisioning Flow (Create Database dari Admin UI)

Admin panel menyediakan fitur **Database Provisioning** untuk membuat database baru secara otomatis dari UI tanpa akses server langsung.

### Tujuan
- Mendukung multi-tenant provisioning
- Otomatis mencatat database ke registry sistem
- Otomatis memberikan permission owner kepada pembuat database

### UI Flow
1. Admin membuka halaman **Create Database**
2. Admin mengisi:
   - Database name
   - Label / description
   - Owner user
3. Admin menekan tombol **Create**

### Backend Flow
Saat tombol Create ditekan:

1. Sistem membuat folder database baru pada storage
2. Sistem menginisialisasi database BangronDB
3. Sistem menambahkan entry pada `database_registry`
4. Sistem menambahkan entry pada `database_permissions` dengan role `owner`
5. Sistem mencatat aktivitas ke `audit_logs`

Pseudo flow:

```
createDatabase(path)
insert database_registry
insert database_permissions (owner)
write audit_logs
```

### Security Rules
- Hanya user dengan permission `database.create` yang boleh membuat database
- Database system (`admin.bangron`) tidak dapat dibuat melalui UI
- Nama database harus unik dalam registry

### Production Notes
- Provisioning harus transactional (jika gagal, registry tidak boleh terisi)
- Database yang baru dibuat otomatis muncul pada dashboard user sesuai permission
- Owner database dapat menambahkan admin atau viewer melalui database permission manager


---

## 15. Database Backup & Restore Architecture

Admin panel menyediakan sistem **Backup & Restore** untuk memastikan keamanan data dan disaster recovery.

### Tujuan
- Membuat snapshot database secara manual maupun terjadwal
- Memungkinkan restore database ke kondisi sebelumnya
- Mendukung backup per database (tenant isolation)

### Backup Flow
1. Admin memilih database
2. Menekan tombol **Backup Now** atau menjadwalkan backup otomatis
3. Sistem membuat snapshot database ke folder backup storage
4. Sistem mencatat metadata backup pada `backup_registry`
5. Aktivitas dicatat pada `audit_logs`

Pseudo flow:

```
lock database
create snapshot file
store snapshot metadata
unlock database
write audit_logs
```

### Restore Flow
1. Admin memilih database
2. Memilih snapshot yang tersedia
3. Menekan tombol **Restore**
4. Sistem mengganti data database dengan snapshot terpilih
5. Aktivitas restore dicatat pada `audit_logs`

### backup_registry Schema
Fields:
- _id
- database_name
- file_path
- size
- created_by
- created_at
- type (manual / scheduled)

### Storage Structure

```
/backups/
   app1/
      2026-01-01.snapshot
      2026-01-02.snapshot
   app2/
      2026-01-05.snapshot
```

### Security Rules
- Hanya role dengan permission `database.backup` yang boleh membuat backup
- Hanya role dengan permission `database.restore` yang boleh melakukan restore
- Restore harus memerlukan konfirmasi ganda (confirmation step)

### Production Notes
- Backup scheduled dijalankan melalui cron / scheduler worker
- Restore harus membuat temporary safety snapshot sebelum overwrite
- Backup lama dapat dihapus otomatis berdasarkan retention policy

---

## 16. Multi-Tenant Isolation Strategy

BangronDB Studio dirancang untuk mendukung arsitektur **multi-tenant**, dimana setiap aplikasi atau pelanggan memiliki database terpisah namun dikelola dalam satu admin panel.

### Tujuan
- Isolasi data antar tenant
- Keamanan akses per database
- Mendukung SaaS provisioning otomatis

### Storage Isolation
Setiap tenant memiliki database file/folder terpisah:

```
/data/tenants/
   tenant_a.bangron
   tenant_b.bangron
   tenant_c.bangron
```

Tidak ada collection yang dibagi antar tenant kecuali **system database**.

### Access Isolation
Akses database dikontrol melalui tabel `database_permissions` pada system database.

Permission ditentukan berdasarkan:
- user_id
- database_name
- role (owner, admin, viewer)

Setiap request runtime:
1. Sistem memverifikasi user session
2. Sistem memeriksa permission database
3. Sistem membuka koneksi hanya ke database yang diizinkan

### Runtime Connection Strategy
Connection database tidak dibuat global, tetapi dibuat **on-demand berdasarkan database yang dipilih user**.

Pseudo flow:

```
validatePermission(user, database)
connect(database)
executeOperation()
```

### Security Rules
- User tidak dapat mengakses database yang tidak terdaftar pada registry
- Super admin dapat mengakses semua database
- Database system (`admin.bangron`) hanya dapat diakses oleh role `super_admin`

### Production Notes
- Tenant database dapat ditempatkan pada storage node berbeda untuk scaling
- Connection pooling dapat diterapkan untuk tenant dengan traffic tinggi
- Backup, monitoring, dan metrics dilakukan per tenant database
