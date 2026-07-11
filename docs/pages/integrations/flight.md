---
layout: doc
title: "Flight PHP Integration"
description: "Integrasi BangronDB dengan Flight PHP — micro-framework paling cocok untuk BangronDB karena keduanya embedded."
permalink: /docs/integrations/flight/
toc: true
edit_on_github: true
category: integrations
next:
  url: /docs/integrations/slim/
  title: "Slim Framework Integration"
---

# Flight PHP Integration

> Bagian dari [Integrations](/docs/integrations/) — daftar micro-framework yang cocok untuk BangronDB.

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
