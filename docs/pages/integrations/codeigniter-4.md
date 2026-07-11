---
layout: doc
title: "CodeIgniter 4 Integration"
description: "Integrasi BangronDB dengan CodeIgniter 4 — model, controller, dan config setup."
permalink: /docs/integrations/codeigniter-4/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/flight/
  title: "Flight PHP Integration"
next:
  url: /docs/integrations/symfony/
  title: "Symfony Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# CodeIgniter 4 Integration

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
