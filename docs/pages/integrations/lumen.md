---
layout: doc
title: "Lumen Integration"
description: "Integrasi BangronDB dengan Lumen micro-framework — bootstrap ringan untuk API service."
permalink: /docs/integrations/lumen/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/laravel/
  title: "Laravel Integration"
next:
  url: /docs/integrations/slim/
  title: "Slim Framework Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Lumen Integration

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
