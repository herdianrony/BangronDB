---
layout: doc
title: "Windwalker Integration"
description: "Integrasi BangronDB dengan Windwalker framework — alternatif Laravel-free untuk struktur modular."
permalink: /docs/integrations/windwalker/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/laravel-zero/
  title: "Laravel Zero Integration"
next:
  url: /docs/integrations/vanilla-php/
  title: "Vanilla PHP Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Windwalker Integration

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
