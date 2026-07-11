---
layout: doc
title: "Filament Integration"
description: "Integrasi BangronDB dengan Filament admin panel — resource, page, dan widget untuk manajemen data."
permalink: /docs/integrations/filament/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/symfony/
  title: "Symfony Integration"
next:
  url: /docs/integrations/laravel-zero/
  title: "Laravel Zero Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Filament Integration

Filament adalah admin panel yang dibangun di atas Laravel. BangronDB bisa digunakan sebagai sumber data alternatif untuk resource kustom, terutama untuk data konfigurasi atau konten yang tidak perlu relasi SQL kompleks.

### Custom Page dengan BangronDB

```php
<?php
// app/Filament/Pages/BangronDBDashboard.php

namespace App\Filament\Pages;

use BangronDB\Client;
use Filament\Pages\Page;

class BangronDBDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-database';
    protected static string $view = 'filament.pages.bangrondb-dashboard';

    public function __construct(
        private Client $client,
    ) {
        parent::__construct();
    }

    public function getStats(): array
    {
        $db = $this->client->selectDB('myapp');

        return [
            'total_users'      => $db->selectCollection('users')->count(),
            'total_products'   => $db->selectCollection('products')->count(),
            'total_orders'     => $db->selectCollection('orders')->count(),
            'db_health'        => $db->getHealthReport(),
        ];
    }
}
```

### Menggunakan BangronDB untuk Konfigurasi/Setting

Pola umum: gunakan BangronDB untuk menyimpan konfigurasi dinamis (feature flags, tema, pengaturan user) yang sering berubah, sementara tetap menggunakan Eloquent/MySQL untuk data transaksional utama.

```php
<?php
// app/Services/SettingsService.php

namespace App\Services;

use BangronDB\Client;

class SettingsService
{
    public function __construct(
        private Client $client,
    ) {}

    public function get(string $key, mixed $default = null): mixed
    {
        $settings = $this->client
            ->selectDB('app_settings')
            ->selectCollection('settings')
            ->findOne(['key' => $key]);

        return $settings['value'] ?? $default;
    }

    public function set(string $key, mixed $value): void
    {
        $col = $this->client
            ->selectDB('app_settings')
            ->selectCollection('settings');

        $existing = $col->findOne(['key' => $key]);

        if ($existing) {
            $col->update(['key' => $key], ['$set' => ['value' => $value, 'updated_at' => date('c')]]);
        } else {
            $col->insert(['key' => $key, 'value' => $value, 'created_at' => date('c')]);
        }
    }
}
```

> **Catatan:** Filament resource bawaan mengharuskan Eloquent Model. Jika ingin full CRUD BangronDB di Filament, buat custom page dengan form manual atau gunakan Filament "Custom Resource" dengan mock Eloquent model yang mendelegasikan operasi ke BangronDB.

---
