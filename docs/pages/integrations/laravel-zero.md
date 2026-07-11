---
layout: doc
title: "Laravel Zero Integration"
description: "Integrasi BangronDB dengan Laravel Zero — CLI app untuk background job, import/export, dan automation."
permalink: /docs/integrations/laravel-zero/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/filament/
  title: "Filament Integration"
next:
  url: /docs/integrations/windwalker/
  title: "Windwalker Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Laravel Zero Integration

Laravel Zero adalah framework CLI berbasis komponen Laravel. BangronDB cocok digunakan sebagai penyimpanan data lokal untuk aplikasi command-line.

### Setup

```bash
composer create-project laravel-zero/laravel-zero my-cli
cd my-cli
composer require bangrondb/bangrondb
```

### Service Provider

```php
<?php
// app/Providers/BangronDBServiceProvider.php

namespace App\Providers;

use BangronDB\Client;
use Illuminate\Support\ServiceProvider;

class BangronDBServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Client::class, function ($app) {
            $path = $app['config']->get('bangrondb.path', getcwd() . '/data');

            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }

            return new Client($path, [
                'encryption_key'         => env('BANGRONDB_ENCRYPTION_KEY'),
                'encryption_key_version' => env('BANGRONDB_KEY_VERSION', 'v1'),
            ]);
        });
    }
}
```

### Command Contoh

```php
<?php
// app/Commands/NoteAddCommand.php

namespace App\Commands;

use BangronDB\Client;
use LaravelZero\Framework\Commands\Command;

class NoteAddCommand extends Command
{
    protected $signature = 'note:add {title} {content?}';
    protected $description = 'Tambah catatan baru';

    public function handle(Client $client): int
    {
        $db = $client->dbExists('notes')
            ? $client->selectDB('notes')
            : $client->createDB('notes');

        $notes = $db->collectionExists('notes')
            ? $db->selectCollection('notes')
            : $db->createCollection('notes');

        $notes
            ->setSchema([
                'title'   => ['type' => 'string', 'required' => true],
                'content' => ['type' => 'string'],
            ])
            ->saveConfiguration();

        $notes->on('beforeInsert', function ($doc) {
            $doc['created_at'] = date('c');
            return $doc;
        });

        $id = $notes->insert([
            'title'   => $this->argument('title'),
            'content' => $this->argument('content') ?? '',
        ]);

        $this->info("Catatan disimpan dengan ID: {$id}");
        return self::SUCCESS;
    }
}
```

```php
<?php
// app/Commands/NoteListCommand.php

namespace App\Commands;

use BangronDB\Client;
use LaravelZero\Framework\Commands\Command;

class NoteListCommand extends Command
{
    protected $signature = 'note:list {--limit=20}';
    protected $description = 'Tampilkan semua catatan';

    public function handle(Client $client): int
    {
        if (!$client->dbExists('notes')) {
            $this->warn('Belum ada database catatan. Jalankan note:add terlebih dahulu.');
            return self::SUCCESS;
        }

        $notes = $client->selectDB('notes')->selectCollection('notes');

        $all = $notes->find()
            ->sort(['created_at' => -1])
            ->limit((int) $this->option('limit'))
            ->toArray();

        if (empty($all)) {
            $this->info('Belum ada catatan.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Title', 'Created'],
            array_map(fn ($n) => [
                substr($n['_id'], 0, 8),
                $n['title'],
                $n['created_at'],
            ], $all),
        );

        return self::SUCCESS;
    }
}
```

---
