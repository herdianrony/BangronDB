---
layout: doc
title: "Laravel Integration"
description: "Integrasi BangronDB dengan Laravel — service provider, facade, Eloquent-style usage, queue, dan migration pattern."
permalink: /docs/integrations/laravel/
toc: true
edit_on_github: true
category: integrations

next:
  url: /docs/integrations/lumen/
  title: "Lumen Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Laravel Integration

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
        // Registrasi Client sebagai singleton
        $this->app->singleton(Client::class, function ($app) {
            $dataPath = $app['config']->get('bangrondb.path', database_path('bangrondb'));

            if (!is_dir($dataPath)) {
                mkdir($dataPath, 0755, true);
            }

            return new Client($dataPath, [
                'encryption_key'         => config('bangrondb.encryption_key'),
                'encryption_key_version' => config('bangrondb.encryption_key_version', 'v1'),
                'query_logging'          => config('bangrondb.query_logging', false),
            ]);
        });
    }

    public function boot(): void
    {
        // Cleanup saat aplikasi shutdown (opsional, __destruct sudah menangani)
        $this->app->terminating(function () {
            if ($this->app->resolved(Client::class)) {
                $this->app->make(Client::class)->close();
            }
        });
    }
}
```

### Konfigurasi

```php
<?php
// config/bangrondb.php

return [
    /*
    |------------------------------------------------------------------
    | Path penyimpanan database
    |------------------------------------------------------------------
    */
    'path' => env('BANGRONDB_PATH', database_path('bangrondb')),

    /*
    |------------------------------------------------------------------
    | Encryption key (>= 32 karakter). Null = tanpa enkripsi.
    |------------------------------------------------------------------
    */
    'encryption_key'         => env('BANGRONDB_ENCRYPTION_KEY'),

    /*
    |------------------------------------------------------------------
    | Versi kunci enkripsi, untuk tracking rotasi kunci.
    |------------------------------------------------------------------
    */
    'encryption_key_version' => env('BANGRONDB_KEY_VERSION', 'v1'),

    /*
    |------------------------------------------------------------------
    | Aktifkan query logging (hanya untuk development).
    |------------------------------------------------------------------
    */
    'query_logging'          => env('BANGRONDB_QUERY_LOGGING', false),
];
```

### Environment (.env)

```env
BANGRONDB_PATH=/path/to/storage/bangrondb
BANGRONDB_ENCRYPTION_KEY=your-very-secret-key-minimum-32-chars-long
BANGRONDB_KEY_VERSION=v1
BANGRONDB_QUERY_LOGGING=false
```

### Controller

```php
<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use BangronDB\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function __construct(
        private Client $client,
    ) {}

    public function index(): JsonResponse
    {
        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->find()
            ->sort(['created_at' => -1])
            ->limit(20)
            ->toArray();

        return response()->json(['data' => $users]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name'  => 'required|string|max:255',
            'email' => 'required|email',
            'age'   => 'nullable|integer|min:0',
        ]);

        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users');

        $id = $users->insert($validated);

        return response()->json(['id' => $id], 201);
    }

    public function show(string $id): JsonResponse
    {
        $user = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->findOne(['_id' => $id]);

        if (!$user) {
            return response()->json(['message' => 'Not found'], 404);
        }

        return response()->json(['data' => $user]);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'age'  => 'sometimes|integer|min:0',
        ]);

        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users');

        $modified = $users->update(
            ['_id' => $id],
            ['$set' => $validated],
        );

        return response()->json(['modified' => $modified]);
    }

    public function destroy(string $id): JsonResponse
    {
        $deleted = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->remove(['_id' => $id]);

        return response()->json(['deleted' => $deleted]);
    }
}
```

### Registrasi Service Provider

```php
// config/app.php (Laravel 10.x)
'providers' => [
    // ...
    App\Providers\BangronDBServiceProvider::class,
],
```

> **Laravel 11+:** Tidak perlu registrasi manual. Service Provider di `bootstrap/providers.php` secara otomatis terdeteksi.

### Artisan Command (Setup Awal)

```php
<?php
// app/Console/Commands/BangronDBSetup.php

namespace App\Console\Commands;

use BangronDB\Client;
use Illuminate\Console\Command;

class BangronDBSetupCommand extends Command
{
    protected $signature = 'bangrondb:setup {--db=myapp}';
    protected $description = 'Setup BangronDB collections dengan schema dan hooks';

    public function handle(Client $client): int
    {
        $dbName = $this->option('db');
        $db = $client->dbExists($dbName)
            ? $client->selectDB($dbName)
            : $client->createDB($dbName);

        // Setup collection users
        $users = $db->collectionExists('users')
            ? $db->selectCollection('users')
            : $db->createCollection('users');

        $users
            ->setIdModePrefix('USR')
            ->setSchema([
                'name'  => ['type' => 'string', 'required' => true, 'min' => 2, 'max' => 255],
                'email' => ['type' => 'string', 'required' => true, 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
                'age'   => ['type' => 'integer', 'min' => 0, 'max' => 200],
                'role'  => ['type' => 'string', 'enum' => ['admin', 'editor', 'viewer']],
            ])
            ->setSearchableFields(['email' => ['hash' => true]])
            ->useSoftDeletes()
            ->saveConfiguration();

        // Daftarkan hooks (harus setiap request, tapi di CLI cukup sekali)
        $users->on('beforeInsert', function ($document) {
            $document['created_at'] = date('c');
            $document['updated_at'] = date('c');
            return $document;
        });

        $users->on('beforeUpdate', function ($criteria, $data) {
            return [$criteria, array_merge($data, ['$set' => ['updated_at' => date('c')]])];
        });

        $this->info("BangronDB '{$dbName}' siap digunakan.");
        return self::SUCCESS;
    }
}
```

---
