---
layout: doc
title: "Slim Framework Integration"
description: "Integrasi BangronDB dengan Slim Framework — middleware, dependency injection, dan route handler."
permalink: /docs/integrations/slim/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/lumen/
  title: "Lumen Integration"
next:
  url: /docs/integrations/flight/
  title: "Flight PHP Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Slim Framework Integration

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
