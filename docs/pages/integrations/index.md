---
layout: doc
title: "Integrations"
description: "Integrasi BangronDB dengan micro-framework PHP: Flight, Slim, Lumen, dan Vanilla PHP. Ringan, embedded, tanpa server database."
permalink: /docs/integrations/
toc: true
edit_on_github: true
---

# Integrasi Micro-Framework PHP

BangronDB dirancang sebagai **embedded document database** — ringan, tanpa server, tanpa konfigurasi. Maka integrasinya pun sebaiknya dengan framework yang memiliki filosofi yang sama: **micro-framework**.

> **Kenapa micro-framework?** BangronDB tujuannya adalah kesederhanaan. Menggabungkannya dengan full-stack framework (Laravel, Symfony, dll) justru menghilangkan keunggulan "ringan & embedded". Micro-framework memberi struktur minimal (routing + middleware) tanpa memaksakan ORM, queue, atau service container yang berat.

## Daftar Micro-Framework

> Halaman ini di-generate otomatis dari file di `pages/integrations/`. Tambah file `.md` baru dengan `category: integrations`, dan halaman ini akan otomatis ter-update.

{% assign integration_pages = site.pages | where: "category", "integrations" | sort: "title" %}

<table>
<thead>
<tr><th>Framework</th><th>Deskripsi</th><th>Link</th></tr>
</thead>
<tbody>
{% for p in integration_pages %}
  <tr>
    <td><strong>{{ p.title }}</strong></td>
    <td>{{ p.description | default: "—" }}</td>
    <td><a href="{{ p.url | relative_url }}">{{ p.url }}</a></td>
  </tr>
{% endfor %}
</tbody>
</table>

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

- **Pemula / ingin cepat jalan**: [Flight PHP](/docs/integrations/flight/) — paling sederhana.
- **API-only service**: [Slim](/docs/integrations/slim/) — PSR-7/15 standards.
- **Sudah pakai Laravel ecosystem**: [Lumen](/docs/integrations/lumen/) — familiar Artisan.
- **Tidak butuh framework**: [Vanilla PHP](/docs/integrations/vanilla-php/) — paling minimal.

## Lihat Juga

- [Getting Started](/docs/getting-started/) — instalasi dan konsep dasar.
- [Project Scenarios](/docs/scenarios/) — implementasi BangronDB di ERP, CRM, SCM, HRIS, POS.
- [Modular Architecture](/docs/modular-architecture/) — strategi multi-database.
- [Security](/docs/security/) — encryption, blind index, key rotation.
