---
layout: doc
title: "Vanilla PHP Integration"
description: "Pakai BangronDB tanpa framework — setup minimal, helper function, dan pola untuk aplikasi PHP murni."
permalink: /docs/integrations/vanilla-php/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/windwalker/
  title: "Windwalker Integration"

---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Vanilla PHP Integration

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
