---
layout: doc
title: "Framework Integration"
description: "Integrasi dengan framework PHP."
toc: true
edit_on_github: true
prev:
  url: /security/
  title: "Security"
next:
  url: /api-reference/
  title: "API Reference"
---
# Integrasi Framework PHP

Panduan praktis mengintegrasikan BangronDB ke berbagai framework PHP. Setiap bagian mencakup pola Service Provider / DI Container, konfigurasi, dan contoh controller yang lengkap.

> **Prasyarat:** Pastikan Anda sudah memahami [Getting Started](getting-started.md) dan [Fitur Lanjutan](features.md) sebelum melanjutkan.
>
> **Composer package:** `bangrondb/bangrondb` (PHP 8.1+, PDO SQLite3)

---

## Daftar Isi

1. [Pola Umum Integrasi](#pola-umum-integrasi)
2. [Laravel](#laravel)
3. [Lumen](#lumen)
4. [Slim Framework](#slim-framework)
5. [Flight PHP](#flight-php)
6. [CodeIgniter 4](#codeigniter-4)
7. [Symfony](#symfony)
8. [Filament (Laravel Admin Panel)](#filament-laravel-admin-panel)
9. [Laravel Zero (CLI App)](#laravel-zero-cli-app)
10. [Windwalker (Laravel-free Framework)](#windwalker-laravel-free-framework)
11. [Custom / Vanilla PHP (Tanpa Framework)](#custom--vanilla-php-tanpa-framework)
12. [Tips Integrasi Umum](#tips-integrasi-umum)

---

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

## Laravel

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

## Lumen

Lumen adalah mikro-framework dari Laravel. Integrasi BangronDB hampir identik, tanpa facade dan config file yang lebih minimal.

### Bootstrap

```php
<?php
// bootstrap/app.php

require_once __DIR__ . '/../vendor/autoload.php';

use BangronDB\Client;

$app = new Laravel\Lumen\Application(
    realpath(__DIR__ . '/../')
);

// Singleton BangronDB
$app->singleton(Client::class, function () {
    $path = env('BANGRONDB_PATH', __DIR__ . '/../storage/bangrondb');
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return new Client($path, [
        'encryption_key'         => env('BANGRONDB_ENCRYPTION_KEY'),
        'encryption_key_version' => env('BANGRONDB_KEY_VERSION', 'v1'),
    ]);
});

// Cleanup
$app->terminating(function () use ($app) {
    if ($app->resolved(Client::class)) {
        $app->make(Client::class)->close();
    }
});

// Routes
$app->router->group([], function ($router) {
    require __DIR__ . '/../routes/web.php';
});

return $app;
```

### Route & Controller (Lumen)

```php
<?php
// routes/web.php

use BangronDB\Client;

// Helper untuk akses cepat
function db(): Client
{
    return app(Client::class);
}

$app->get('/users', function () {
    $users = db()->selectDB('myapp')->selectCollection('users')
        ->find()
        ->limit(50)
        ->toArray();
    return response()->json(['data' => $users]);
});

$app->post('/users', function (Illuminate\Http\Request $request) {
    $data = $request->json()->all();
    $id = db()->selectDB('myapp')->selectCollection('users')
        ->insert($data);
    return response()->json(['id' => $id], 201);
});

$app->get('/users/{id}', function ($id) {
    $user = db()->selectDB('myapp')->selectCollection('users')
        ->findOne(['_id' => $id]);
    if (!$user) {
        return response()->json(['error' => 'Not found'], 404);
    }
    return response()->json(['data' => $user]);
});
```

### Middleware (Auto-Timestamp via Hook)

```php
<?php
// app/Http/Middleware/RegisterBangronDBHooks.php

namespace App\Http\Middleware;

use BangronDB\Client;
use Closure;

class RegisterBangronDBHooks
{
    public function handle($request, Closure $next)
    {
        $db = app(Client::class);
        $dbName = env('BANGRONDB_DB', 'myapp');

        if ($db->dbExists($dbName)) {
            $collections = ['users', 'posts', 'comments'];
            foreach ($collections as $colName) {
                if ($db->collectionExists($colName)) {
                    $col = $db->selectCollection($colName);

                    $col->on('beforeInsert', function ($document) {
                        $document['created_at'] = date('c');
                        $document['updated_at'] = date('c');
                        return $document;
                    });

                    $col->on('beforeUpdate', function ($criteria, $data) {
                        return [$criteria, array_merge($data, ['$set' => ['updated_at' => date('c')]])];
                    });
                }
            }
        }

        return $next($request);
    }
}
```

> **Catatan:** Karena hooks tidak dipersist, pendekatan middleware ini memastikan hooks terdaftar di setiap HTTP request tanpa harus mengulang di setiap controller.

---

## Slim Framework

Slim 4 menggunakan PSR-11 DI Container. Berikut integrasi dengan PHP-DI.

### Setup dengan PHP-DI

```bash
composer require slim/slim:"4.*" slim/psr7 php-di/php-di
```

### Container Configuration

```php
<?php
// config/container.php

use BangronDB\Client;
use DI\ContainerBuilder;

$builder = new ContainerBuilder();

$builder->addDefinitions([
    Client::class => \DI\factory(function () {
        $path = getenv('BANGRONDB_PATH') ?: __DIR__ . '/../data/bangrondb';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        return new Client($path, [
            'encryption_key'         => getenv('BANGRONDB_ENCRYPTION_KEY') ?: null,
            'encryption_key_version' => getenv('BANGRONDB_KEY_VERSION') ?: 'v1',
        ]);
    }),
]);

return $builder->build();
```

### Index (Entry Point)

```php
<?php
// public/index.php

use DI\ContainerBuilder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Factory\AppFactory;

require __DIR__ . '/../vendor/autoload.php';

$container = require __DIR__ . '/../config/container.php';

AppFactory::setContainer($container);
$app = AppFactory::create();

// Middleware untuk registrasi hooks
$app->add(function (Request $request, $handler) use ($container) {
    $client = $container->get(\BangronDB\Client::class);
    if ($client->dbExists('myapp')) {
        $db = $client->selectDB('myapp');
        foreach (['users', 'products'] as $name) {
            if ($db->collectionExists($name)) {
                $col = $db->selectCollection($name);
                $col->on('beforeInsert', function ($doc) {
                    $doc['created_at'] = date('c');
                    $doc['updated_at'] = date('c');
                    return $doc;
                });
            }
        }
    }
    return $handler->handle($request);
});

// Routes
$app->get('/users', function (Request $request, Response $response) {
    $client = $this->get(\BangronDB\Client::class);
    $users = $client->selectDB('myapp')->selectCollection('users')
        ->find()
        ->toArray();

    $response->getBody()->write(json_encode(['data' => $users]));
    return $response->withHeader('Content-Type', 'application/json');
});

$app->post('/users', function (Request $request, Response $response) {
    $client = $this->get(\BangronDB\Client::class);
    $data = json_decode((string) $request->getBody(), true);
    $id = $client->selectDB('myapp')->selectCollection('users')->insert($data);

    $response->getBody()->write(json_encode(['id' => $id]));
    return $response->withStatus(201)->withHeader('Content-Type', 'application/json');
});

// Cleanup
register_shutdown_function(function () use ($container) {
    $container->get(\BangronDB\Client::class)->close();
});

$app->run();
```

> **Tanpa PHP-DI:** Jika tidak menggunakan container pihak ketiga, buat instance `Client` langsung di `index.php` dan teruskan ke closure route melalui `use ($client)`.

---

## Flight PHP

Flight PHP adalah mikro-framework minimalis. Integrasi sangat sederhana karena tidak ada DI container bawaan.

### Setup

```bash
composer require flightphp/core
```

### Konfigurasi

```php
<?php
// app.php

require 'vendor/autoload.php';

use flight\Engine;
use BangronDB\Client;

$app = new Engine();

// Inisialisasi BangronDB dan simpan di Flight registry
$app->register('bangrondb', function () {
    $path = __DIR__ . '/data/bangrondb';
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    $client = new Client($path, [
        'encryption_key'         => getenv('BANGRONDB_ENCRYPTION_KEY') ?: null,
        'encryption_key_version' => 'v1',
    ]);

    // Setup database dan collection pertama kali
    if (!$client->dbExists('myapp')) {
        $db = $client->createDB('myapp');
        $users = $db->createCollection('users');
        $users->setSchema([
            'name'  => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true, 'unique' => true],
        ])->saveConfiguration();
    }

    return $client;
});

// Helper function
function getCollection(string $name): \BangronDB\Collection
{
    return Flight::get('bangrondb')->selectDB('myapp')->selectCollection($name);
}

// ── Routes ──────────────────────────────────────────────

Flight::route('GET /users', function () {
    $users = getCollection('users')->find()->toArray();
    Flight::json(['data' => $users]);
});

Flight::route('POST /users', function () {
    $data = Flight::request()->data->getData();
    $id = getCollection('users')->insert((array) $data);
    Flight::json(['id' => $id], 201);
});

Flight::route('GET /users/@id', function ($id) {
    $user = getCollection('users')->findOne(['_id' => $id]);
    if (!$user) {
        Flight::json(['error' => 'Not found'], 404);
        return;
    }
    Flight::json(['data' => $user]);
});

Flight::route('PUT /users/@id', function ($id) {
    $data = Flight::request()->data->getData();
    $modified = getCollection('users')->update(
        ['_id' => $id],
        ['$set' => (array) $data],
    );
    Flight::json(['modified' => $modified]);
});

Flight::route('DELETE /users/@id', function ($id) {
    $deleted = getCollection('users')->remove(['_id' => $id]);
    Flight::json(['deleted' => $deleted]);
});

// Cleanup
register_shutdown_function(function () {
    if (Flight::has('bangrondb')) {
        Flight::get('bangrondb')->close();
    }
});

Flight::start();
```

---

## CodeIgniter 4

CodeIgniter 4 mendukung PSR-11 dan Service Provider sederhana.

### Service Definition

```php
<?php
// app/Config/Services.php — tambahkan method berikut

namespace App\Config;

use BangronDB\Client;
use Config\Services as BaseService;

class Services extends BaseService
{
    public static function bangrondb(bool $getShared = true): Client
    {
        if ($getShared) {
            return static::getSharedInstance('bangrondb');
        }

        $path = ENV('BANGRONDB.path') ?? WRITEPATH . 'bangrondb';
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        return new Client($path, [
            'encryption_key'         => ENV('BANGRONDB.encryption_key') ?? null,
            'encryption_key_version' => ENV('BANGRONDB.key_version') ?? 'v1',
        ]);
    }
}
```

### Environment (.env)

```env
BANGRONDB.path = /path/to/writable/bangrondb
BANGRONDB.encryption_key = your-secret-key-at-least-32-chars
BANGRONDB.key_version = v1
```

### Controller

```php
<?php
// app/Controllers/UserController.php

namespace App\Controllers;

use BangronDB\Client;

class UserController extends BaseController
{
    protected Client $client;

    public function initController(\CodeIgniter\HTTP\RequestInterface $request, \CodeIgniter\HTTP\ResponseInterface $response, \Psr\Log\LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);
        $this->client = service('bangrondb');
    }

    public function index(): \CodeIgniter\HTTP\Response
    {
        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->find()
            ->toArray();

        return $this->response->setJSON(['data' => $users]);
    }

    public function create(): \CodeIgniter\HTTP\Response
    {
        $json = $this->request->getJSON(true);
        $id = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->insert($json);

        return $this->response->setJSON(['id' => $id])->setStatusCode(201);
    }

    public function show(string $id): \CodeIgniter\HTTP\Response
    {
        $user = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->findOne(['_id' => $id]);

        if (!$user) {
            return $this->response->setJSON(['error' => 'Not found'])->setStatusCode(404);
        }

        return $this->response->setJSON(['data' => $user]);
    }
}
```

### Filter (Alternatif Middleware untuk Hooks)

```php
<?php
// app/Filters/BangronDBHooks.php

namespace App\Filters;

use BangronDB\Client;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class BangronDBHooks implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $client = service('bangrondb', false);
        if ($client->dbExists('myapp')) {
            $db = $client->selectDB('myapp');
            foreach (['users', 'posts'] as $colName) {
                if ($db->collectionExists($colName)) {
                    $col = $db->selectCollection($colName);
                    $col->on('beforeInsert', function ($doc) {
                        $doc['created_at'] = date('c');
                        return $doc;
                    });
                }
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Tidak perlu melakukan apa-apa
    }
}
```

```php
<?php
// app/Config/Filters.php — tambahkan alias

public $aliases = [
    // ...
    'bangrondb-hooks' => \App\Filters\BangronDBHooks::class,
];

public $globals = [
    'before' => ['bangrondb-hooks'],
];
```

---

## Symfony

Symfony menggunakan service container yang dikonfigurasi via YAML, XML, atau PHP.

### Service Configuration (YAML)

```yaml
# config/services.yaml

services:
    BangronDB\Client:
        class: BangronDB\Client
        arguments:
            $path: '%bangrondb.path%'
            $options:
                encryption_key: '%bangrondb.encryption_key%'
                encryption_key_version: '%bangrondb.key_version%'
        shared: true  # singleton (default di Symfony)
```

### Parameters

```yaml
# config/services.yaml (parameters section)

parameters:
    bangrondb.path: '%kernel.project_dir%/var/bangrondb'
    bangrondb.encryption_key: '%env(BANGRONDB_ENCRYPTION_KEY)%'
    bangrondb.key_version: '%env(default:v1:BANGRONDB_KEY_VERSION)%'
```

### Environment (.env)

```env
###> bangrondb ###
BANGRONDB_PATH=var/bangrondb
BANGRONDB_ENCRYPTION_KEY=your-secret-key-at-least-32-chars
BANGRONDB_KEY_VERSION=v1
###< bangrondb ###
```

### Controller

```php
<?php
// src/Controller/UserController.php

namespace App\Controller;

use BangronDB\Client;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class UserController extends AbstractController
{
    public function __construct(
        private Client $client,
    ) {}

    #[Route('/api/users', methods: ['GET'])]
    public function index(): JsonResponse
    {
        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->find()
            ->toArray();

        return $this->json(['data' => $users]);
    }

    #[Route('/api/users', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $id = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->insert($data);

        return $this->json(['id' => $id], 201);
    }

    #[Route('/api/users/{id}', methods: ['GET'])]
    public function show(string $id): JsonResponse
    {
        $user = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->findOne(['_id' => $id]);

        if (!$user) {
            return $this->json(['error' => 'Not found'], 404);
        }

        return $this->json(['data' => $user]);
    }
}
```

### EventSubscriber (Alternatif Middleware)

```php
<?php
// src/EventSubscriber/BangronDBHookSubscriber.php

namespace App\EventSubscriber;

use BangronDB\Client;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class BangronDBHookSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private Client $client,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST  => ['onKernelRequest', 10],
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        if ($this->client->dbExists('myapp')) {
            $db = $this->client->selectDB('myapp');
            foreach (['users', 'products'] as $name) {
                if ($db->collectionExists($name)) {
                    $db->selectCollection($name)
                        ->on('beforeInsert', function ($doc) {
                            $doc['created_at'] = date('c');
                            return $doc;
                        });
                }
            }
        }
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->client->close();
    }
}
```

---

## Filament (Laravel Admin Panel)

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

## Laravel Zero (CLI App)

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

## Windwalker (Laravel-free Framework)

Windwalker adalah framework PHP modern yang menggunakan dependency injection sendiri. Berikut pola integrasinya.

### Service Provider

```php
<?php
// src/Service/Provider/BangronDBServiceProvider.php

namespace App\Service\Provider;

use BangronDB\Client;
use Windwalker\Core\Runtime\Runtime;
use Windwalker\DI\Container;
use Windwalker\DI\ServiceProviderInterface;

class BangronDBServiceProvider implements ServiceProviderInterface
{
    public function register(Container $container): void
    {
        $container->prepareSharedObject(Client::class, function (Container $container) {
            $path = Runtime::getEnv('BANGRONDB_PATH') ?? WINDWALKER_CACHE . '/bangrondb';

            if (!is_dir($path)) {
                mkdir($path, 0755, true);
            }

            return new Client($path, [
                'encryption_key'         => Runtime::getEnv('BANGRONDB_ENCRYPTION_KEY') ?: null,
                'encryption_key_version' => Runtime::getEnv('BANGRONDB_KEY_VERSION') ?: 'v1',
            ]);
        });
    }
}
```

### Controller

```php
<?php
// src/Module/Admin/Controller/UserController.php

namespace App\Module\Admin\Controller;

use BangronDB\Client;
use Windwalker\Core\Controller\AbstractController;
use Windwalker\Core\Router\Navigator;
use Windwalker\DI\Attributes\Autowire;

class UserController extends AbstractController
{
    public function __construct(
        #[Autowire]
        private Client $client,
        private Navigator $nav,
    ) {}

    public function list(): array
    {
        $users = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->find()
            ->toArray();

        return ['users' => $users];
    }

    public function save(): array
    {
        $data = $this->input->toArray();
        $id = $this->client
            ->selectDB('myapp')
            ->selectCollection('users')
            ->insert($data);

        return ['id' => $id];
    }
}
```

---

## Custom / Vanilla PHP (Tanpa Framework)

Untuk proyek PHP tanpa framework, gunakan pola sederhana: satu file bootstrap yang menginisialisasi BangronDB dan mengekspos helper function.

### Bootstrap

```php
<?php
// bootstrap.php

require_once 'vendor/autoload.php';

use BangronDB\Client;

/**
 * Inisialisasi BangronDB dan kembalikan instance Client.
 *
 * Dipanggil sekali di awal aplikasi, lalu instance-nya dibagikan
 * ke seluruh bagian yang membutuhkan melalui parameter atau global.
 */
function initBangronDB(string $dataPath = __DIR__ . '/data'): Client
{
    static $client = null;

    if ($client !== null) {
        return $client;
    }

    if (!is_dir($dataPath)) {
        mkdir($dataPath, 0755, true);
    }

    $client = new Client($dataPath, [
        'encryption_key'         => getenv('BANGRONDB_ENCRYPTION_KEY') ?: null,
        'encryption_key_version' => getenv('BANGRONDB_KEY_VERSION') ?: 'v1',
    ]);

    // Cleanup saat script berakhir
    register_shutdown_function(function () use ($client) {
        $client->close();
    });

    return $client;
}

/**
 * Shortcut untuk mengakses collection tertentu.
 */
function db(string $db = 'myapp', string $collection = 'users'): \BangronDB\Collection
{
    return initBangronDB()->selectDB($db)->selectCollection($collection);
}
```

### Penggunaan

```php
<?php
// index.php

require_once 'bootstrap.php';

// Insert
$id = db('myapp', 'users')->insert([
    'name'  => 'Rony',
    'email' => 'rony@example.com',
]);

// Query
$user = db('myapp', 'users')->findOne(['_id' => $id]);
echo "Halo, {$user['name']}!\n";

// Update
db('myapp', 'users')->update(['_id' => $id], ['$set' => ['age' => 30]]);
```

### Pola Repository (Untuk Aplikasi Lebih Besar)

```php
<?php
// src/Repository/UserRepository.php

namespace App\Repository;

use BangronDB\Collection;

class UserRepository
{
    private Collection $collection;

    public function __construct(Collection $collection)
    {
        $this->collection = $collection;
    }

    public function findById(string $id): ?array
    {
        return $this->collection->findOne(['_id' => $id]);
    }

    public function findByEmail(string $email): ?array
    {
        return $this->collection->findOne(['email' => $email]);
    }

    public function create(array $data): string
    {
        return $this->collection->insert($data);
    }

    public function update(string $id, array $data): int
    {
        return $this->collection->update(['_id' => $id], ['$set' => $data]);
    }

    public function delete(string $id): int
    {
        return $this->collection->remove(['_id' => $id]);
    }

    public function findActive(int $limit = 20, int $offset = 0): array
    {
        return $this->collection->find(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->skip($offset)
            ->limit($limit)
            ->toArray();
    }
}
```

```php
<?php
// Menggunakan repository

require_once 'bootstrap.php';

use BangronDB\Client;

$client = initBangronDB();
$collection = $client->selectDB('myapp')->selectCollection('users');

$repo = new \App\Repository\UserRepository($collection);

$repo->create(['name' => 'Rony', 'email' => 'rony@example.com', 'status' => 'active']);
$activeUsers = $repo->findActive(10);
```

---

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