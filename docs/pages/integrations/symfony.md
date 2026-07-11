---
layout: doc
title: "Symfony Integration"
description: "Integrasi BangronDB dengan Symfony — service container, bundle, dan Doctrine alternative."
permalink: /docs/integrations/symfony/
toc: true
edit_on_github: true
category: integrations
prev:
  url: /docs/integrations/codeigniter-4/
  title: "CodeIgniter 4 Integration"
next:
  url: /docs/integrations/filament/
  title: "Filament Integration"
---

> Halaman ini adalah bagian dari [Integrations](/docs/integrations/). Lihat juga integrasi framework lain di sidebar kiri.
# Symfony Integration

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
