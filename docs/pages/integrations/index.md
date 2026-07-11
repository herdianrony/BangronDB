---
layout: doc
title: "Integrations"
description: "Daftar integrasi BangronDB dengan framework PHP populer: Laravel, Lumen, Slim, Flight, CodeIgniter, Symfony, Filament, Laravel Zero, Windwalker, dan Vanilla PHP."
permalink: /docs/integrations/
toc: true
edit_on_github: true
---

# Integrasi Framework PHP

BangronDB dapat diintegrasikan dengan hampir semua framework PHP. Karena BangronDB adalah library murni (tidak butuh server database), integrasinya sangat sederhana — biasanya cukup daftarkan sebagai service di container framework.

## Daftar Integrasi

Pilih framework yang Anda gunakan:

| Framework | Cocok untuk | Link |
|-----------|-------------|------|
| **Laravel** | Aplikasi full-stack dengan Eloquent, queue, dan ecosystem besar | [/docs/integrations/laravel/](/docs/integrations/laravel/) |
| **Lumen** | Micro-service dan API-only dengan footprint kecil | [/docs/integrations/lumen/](/docs/integrations/lumen/) |
| **Slim Framework** | API minimalis dengan middleware | [/docs/integrations/slim/](/docs/integrations/slim/) |
| **Flight PHP** | Micro-framework paling cocok untuk BangronDB (keduanya embedded) | [/docs/integrations/flight/](/docs/integrations/flight/) |
| **CodeIgniter 4** | Aplikasi dengan pola MVC tradisional | [/docs/integrations/codeigniter-4/](/docs/integrations/codeigniter-4/) |
| **Symfony** | Enterprise application dengan service container dan bundle | [/docs/integrations/symfony/](/docs/integrations/symfony/) |
| **Filament** | Admin panel untuk Laravel dengan resource management | [/docs/integrations/filament/](/docs/integrations/filament/) |
| **Laravel Zero** | CLI app untuk background job, import/export, automation | [/docs/integrations/laravel-zero/](/docs/integrations/laravel-zero/) |
| **Windwalker** | Alternatif Laravel-free dengan struktur modular | [/docs/integrations/windwalker/](/docs/integrations/windwalker/) |
| **Vanilla PHP** | Aplikasi tanpa framework — setup minimal | [/docs/integrations/vanilla-php/](/docs/integrations/vanilla-php/) |

## Pola Umum Integrasi

BangronDB bukanlah framework — ia adalah **library embedded database** yang dirancang untuk diinisialisasi sekali lalu dibagikan ke seluruh komponen aplikasi melalui **Dependency Injection (DI) Container** atau **Service Locator**.

### Tiga Langkah Inti

```php
// 1. INISIALISASI — sekali per request/process
use BangronDB\Client;

$client = new Client($dataPath, [
    'encryption_key'        => getenv('DB_ENCRYPTION_KEY') ?: null,
    'encryption_key_version' => 'v1',
]);

// 2. REGISTRASI — simpan di DI container sebagai singleton
$container->singleton(Client::class, fn() => $client);

// 3. GUNAKAN — inject ke controller/service
$users = $client->selectDB('myapp')->selectCollection('users');
$users->insert(['name' => 'Rony', 'email' => 'rony@example.com']);
```

### Prinsip Penting

| Prinsip | Penjelasan |
|---------|-----------|
| **Satu Client per proses** | `Client` mengelola cache koneksi database internal. Membuat beberapa instance untuk path yang sama membuang resource dan berisiko lock conflict pada SQLite. |
| **Encryption key dari env** | Kunci enkripsi **tidak pernah dipersist** ke database. Selalu inject dari environment variable atau secret manager. |
| **Hooks harus didaftarkan ulang** | Hooks (`on()`) tidak dipersist. Setiap kali request baru dimulai (atau setelah `createCollection`/`selectCollection`), hooks harus didaftarkan kembali. |
| **Shutdown cleanup** | Panggil `$client->close()` atau `Database::closeAll()` saat proses berakhir untuk melepaskan file lock dan koneksi PDO. `__destruct()` menangani ini, tapi eksplisit lebih aman. |
| **Konfigurasi collection persist** | Schema, searchable fields, ID mode, dan soft delete settings **otomatis dipersist** ke tabel `_config` saat `saveConfiguration()` dipanggil. Jadi hanya perlu konfigurasi sekali saat pertama kali pembuatan collection. |

---


## Tips Integrasi Umum

## Tips Integrasi Umum

### 1. Path Penyimpanan

Pilih path yang sesuai dengan konvensi framework:

| Framework | Path yang Direkomendasikan |
|-----------|--------------------------|
| Laravel | `storage_path('bangrondb')` atau `database_path('bangrondb')` |
| Lumen | `storage_path('bangrondb')` |
| Slim | `__DIR__ . '/../data/bangrondb'` |
| Flight | `__DIR__ . '/data/bangrondb'` |
| CodeIgniter 4 | `WRITEPATH . 'bangrondb'` |
| Symfony | `%kernel.project_dir%/var/bangrondb` |
| Laravel Zero | `getcwd() . '/data'` |
| Windwalker | `WINDWALKER_CACHE . '/bangrondb'` |

> **Penting:** Pastikan direktori tersebut **tidak termasuk dalam version control** (tambahkan ke `.gitignore`).

### 2. Encryption Key Management

**Jangan pernah** hardcode encryption key di source code. Gunakan salah satu metode berikut:

```bash
# .env file (paling umum)
BANGRONDB_ENCRYPTION_KEY=base64-encoded-key-minimum-32-chars

# Generate key baru:
openssl rand -base64 48
```

```php
// Dari secret manager (AWS Secrets Manager, HashiCorp Vault, dll.)
$key = $secretsManager->getSecret('bangrondb/encryption_key');
```

### 3. Hook Registration Strategy

Karena hooks **tidak dipersist**, pilih salah satu strategi berikut:

| Strategi | Cocok Untuk | Contoh |
|----------|-------------|--------|
| **Middleware / Filter** | Web framework (Laravel, Slim, CI4, Symfony) | Daftarkan hooks di middleware yang berjalan di setiap request |
| **Event Subscriber** | Symfony, Laravel (EventServiceProvider) | Daftarkan di event kernel.request |
| **Service Provider boot()** | Laravel, Lumen | Daftarkan di `boot()` method service provider |
| **Bootstrap file** | Vanilla PHP, Flight | Daftarkan di file bootstrap yang di-require di setiap halaman |
| **Command handle()** | Laravel Zero, Artisan command | Daftarkan di awal method `handle()` |
| **Pola Repository** | Semua framework | Enkapsulasi hook registration di dalam constructor repository |

### 4. Testing dengan In-Memory Database

Untuk unit/feature test, gunakan `:memory:` agar test berjalan cepat dan tidak meninggalkan file sisa:

```php
// Laravel TestCase
protected function setUp(): void
{
    parent::setUp();

    $this->app->singleton(Client::class, function () {
        return new Client(':memory:', [
            'encryption_key' => 'test-key-for-unit-testing-minimum-32-chars',
        ]);
    });
}
```

```php
// PHPUnit murni
class UserRepositoryTest extends \PHPUnit\Framework\TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        $this->client = new Client(':memory:');
        $db = $this->client->createDB('test');
        $users = $db->createCollection('users');
        $users->setSchema([
            'name'  => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true],
        ]);
    }

    public function testInsertAndFind(): void
    {
        $users = $this->client->selectDB('test')->selectCollection('users');
        $id = $users->insert(['name' => 'Test', 'email' => 'test@example.com']);

        $found = $users->findOne(['_id' => $id]);
        $this->assertEquals('Test', $found['name']);
    }

    protected function tearDown(): void
    {
        $this->client->close();
    }
}
```

### 5. Concurrent Access (SQLite Limitations)

SQLite mendukung concurrent read, tapi **hanya satu writer pada satu waktu**. Untuk aplikasi web dengan traffic tinggi, perhatikan:

```php
// Aktifkan WAL mode untuk read-concurrency yang lebih baik
use BangronDB\Config;

Config::set('journal_mode', 'WAL');
Config::set('synchronous', 'NORMAL');
Config::set('busy_timeout', 5000);  // via PDO attribute

// Aktifkan sebelum membuat Client
$client = new Client($path, [
    'encryption_key' => $key,
    // PDO attribute untuk busy timeout
    \PDO::ATTR_TIMEOUT => 5,
]);
```

> **Catatan:** BangronDB menggunakan `PRAGMA journal_mode=WAL` secara default. Tambahkan `busy_timeout` melalui opsi PDO jika diperlukan.

### 6. Health Monitoring

BangronDB menyediakan metode untuk monitoring kesehatan database. Integrasikan ke health check endpoint framework Anda:

```php
// Contoh: Laravel health check endpoint
$app->get('/health', function (Client $client) {
    $db = $client->selectDB('myapp');
    $health = $db->getHealthReport();

    return response()->json([
        'status'  => $health['fragmentation_percent'] > 50 ? 'warning' : 'healthy',
        'details' => $health,
    ]);
});
```

### 7. Database Per-Tenant (Multi-Tenancy Sederhana)

Untuk SaaS dengan data terpisah per tenant, gunakan database terpisah per tenant:

```php
// Laravel middleware
$client = app(Client::class);
$tenantId = $request->user()->tenant_id;
$dbName = "tenant_{$tenantId}";

if (!$client->dbExists($dbName)) {
    $client->createDB($dbName);
}

$db = $client->selectDB($dbName);
```

> **Catatan:** Pola ini aman karena setiap tenant memiliki file `.bangron` terpisah. Encryption key bisa sama atau berbeda per tenant tergantung kebutuhan isolasi.


## Pilih Framework Mana?

- **Pemula / ingin cepat jalan**: Flight PHP — paling sederhana, cocok untuk prototyping.
- **Tim dengan experience Laravel**: Laravel atau Lumen (tergantung skala).
- **Microservice / API-only**: Lumen atau Slim.
- **Enterprise dengan compliance**: Symfony.
- **CLI tool / automation**: Laravel Zero.
- **Admin dashboard existing**: Filament (di atas Laravel).
- **Tanpa framework**: Vanilla PHP — BangronDB tidak butuh framework untuk jalan.

## Lihat Juga

- [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar.
- [Project Scenarios: ERP](/docs/project-scenarios-erp/) — contoh implementasi ERP dengan Flight PHP.
- [Modular Architecture](/docs/modular-architecture/) — strategi multi-database untuk aplikasi modular.
