---
layout: doc
title: "Integrations"
description: "Integrasi BangronDB dengan micro-framework PHP: Flight, Slim, Lumen, dan Vanilla PHP. Ringan, embedded, tanpa server database."
permalink: /docs/integrations/
toc: true
edit_on_github: true
---

# Integrasi Micro-Framework PHP

Saya sengaja cuma dukung micro-framework di dokumentasi ini. Bukan karena tidak bisa dipakai dengan Laravel/Symfony — teknisnya bisa saja. Tapi filosofinya beda.

BangronDB itu embedded dan ringan. Kalau saya pair dengan full-stack framework yang udah bawa ORM sendiri, queue system, service container, dan ratusan MB dependency — saya udah menghilangkan alasan utama pakai BangronDB di tempat pertama. Mending langsung pakai PostgreSQL + Eloquent kan?

Jadi di sini saya cuma bahas 4 micro-framework yang menurut saya cocok dengan filosofi BangronDB: ringan, embedded, no opinionated structure. Saya pakai Flight PHP di semua skenario docs karena paling minimal. Slim, Lumen, dan Vanilla PHP juga saya bahas kalau Anda lebih familiar dengan salah satunya.

## Daftar Micro-Framework

> Halaman ini di-generate otomatis dari file di `pages/integrations/`. Tambah file `.md` baru dengan `category: integrations`, dan halaman ini akan otomatis ter-update.

{% assign integration_pages = site.pages | where: "category", "integrations" | sort: "title" %}

<div class="doc-list">
{% for p in integration_pages %}
  <a href="{{ p.url | relative_url }}" class="doc-list-card">
    <h3>
      <span class="card-icon">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"/></svg>
      </span>
      {{ p.title }}
    </h3>
    <div class="card-desc">{{ p.description | default: "—" }}</div>
    <span class="card-link">{{ p.url }}</span>
  </a>
{% endfor %}
</div>

## Penjelasan Singkat

### Flight PHP

Micro-framework paling minimalis — hanya routing dan middleware. Tidak ada DI container, tidak ada ORM, tidak ada opinionated structure. Cocok untuk:

- Aplikasi kecil yang butuh cepat jalan
- Prototyping dan PoC
- Embedded application (POS, kiosk, IoT)
- Aplikasi yang di-deploy per-customer (appliance model)

**Kenapa paling cocok untuk BangronDB?** Keduanya sama-sama "embedded" — Flight PHP tidak butuh server, BangronDB tidak butuh server. Stack lengkap: Flight PHP + BangronDB + PHP bisa di-deploy dengan 1 file `.phar` atau 1 folder.

### Slim Framework

Micro-framework dengan fokus ke PSR-7 (HTTP message interface) dan PSR-15 (middleware). Lebih structured dari Flight, cocok untuk:

- REST API service
- Microservice architecture
- Aplikasi yang butuh banyak middleware (auth, logging, rate limit)
- Tim yang familiar dengan PSR standards

### Lumen

Micro-framework by Laravel — versi ringan Laravel tanpa view rendering, tanpa session, tanpa cookie. Cocok untuk:

- API yang sudah pakai Laravel ecosystem (queue, cache, events)
- Migration dari Laravel ke microservice
- Aplikasi yang butuh Artisan CLI commands

**Catatan:** Lumen secara official sudah deprecated (Laravel merekomendasikan pakai Laravel langsung dengan route caching). Tapi masih relevan untuk project existing.

### Vanilla PHP

Tanpa framework sama sekali. Cocok untuk:

- Scripts dan CLI tools (import, export, migration)
- Cron job dan background task
- Aplikasi single-file yang sangat minimal
- Learning purposes — pahami BangronDB tanpa abstraction framework

## Pola Umum Integrasi

Semua micro-framework pada dasarnya pakai pola yang sama:

```php
// 1. Bootstrap BangronDB (sekali di entry point)
require 'vendor/autoload.php';
use BangronDB\Client;
use BangronDB\Config;

Config::set('default_path', __DIR__ . '/data');
$client = new Client(__DIR__ . '/data');

// 2. Buat database & collection
$db = $client->createDB('myapp');
$users = $db->createCollection('users');

// 3. Pakai di route handler
$app->get('/users', function () use ($users) {
    return $users->find(['active' => true])->toArray();
});
```

Perbedaan hanya di **cara register BangronDB ke container framework** (kalau ada) — tapi karena micro-framework minim opinion, ini trivial.

## Tips Integrasi Umum

### Konfigurasi Encryption Key

Simpan di environment variable, jangan di code:

```bash
# .env
BANGRON_ENCRYPTION_KEY=change-me-to-32-char-random-string
```

```php
// Bootstrap
$encKey = $_ENV['BANGRON_ENCRYPTION_KEY'] ?? null;
if ($encKey === null || strlen($encKey) < 32) {
    throw new RuntimeException('BANGRON_ENCRYPTION_KEY must be set (min 32 chars)');
}
$client = new Client(__DIR__ . '/data', ['encryption_key' => $encKey]);
```

### Generate Encryption Key

```bash
# Generate 32-char random key
php -r "echo bin2hex(random_bytes(16));"
# Atau
openssl rand -hex 16
```

### Multi-Database Setup

Untuk aplikasi modular, pakai 1 database per modul:

```php
$core   = $client->createDB('core');     // users, settings
$sales  = $client->createDB('sales');    // orders, invoices
$hr     = $client->createDB('hr');       // employees, attendance
```

Lihat [Modular Architecture](/docs/modular-architecture/) untuk panduan lengkap.

### Database Path untuk Production

- **On-premise**: `/var/lib/myapp/data/` (chmod 750, owner = web user)
- **Docker**: volume mount ke `/data`
- **Shared hosting**: di luar `public_html/` (jangan world-readable)
- **Appliance**: `/opt/myapp/data/` dengan backup harian

## Pilih Micro-Framework Mana?

Saya pakai Flight PHP di semua docs skenario karena paling cocok sama saya — paling minimal, paling cepat jalan. Tapi tergantung situasi Anda:

- **Cepat jalan / pemula**: [Flight PHP](/docs/integrations/flight/) — saya pakai ini di semua docs skenario. Routing + middleware, selesai.
- **API service / microservice**: [Slim](/docs/integrations/slim/) — kalau Anda familiar dengan PSR-7/15 standards.
- **Sudah pakai Laravel ecosystem**: [Lumen](/docs/integrations/lumen/) — Artisan CLI familiar. Catatan: Lumen udah deprecated, tapi masih relevan untuk project existing.
- **Tidak butuh framework sama sekali**: [Vanilla PHP](/docs/integrations/vanilla-php/) — untuk scripts, CLI tools, cron jobs. Paling minimal.

## Lihat Juga

- [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar.
- [Project Scenarios](/docs/scenarios/) — implementasi BangronDB di ERP, CRM, SCM, HRIS, POS.
- [Modular Architecture](/docs/modular-architecture/) — strategi multi-database.
- [Security](/docs/security/) — encryption, blind index, key rotation.
