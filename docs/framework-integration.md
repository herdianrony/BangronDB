# Integrasi dengan Framework PHP

Panduan integrasi BangronDB dengan framework PHP populer termasuk full-stack framework (Laravel, Symfony, CodeIgniter) dan micro framework (Flight PHP, Slim, Lumen).

## Laravel Integration

### Setup Service Provider

```php
<?php
// app/Providers/BangronDBServiceProvider.php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use BangronDB\Client;

class BangronDBServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->app->singleton('BangronDB', function ($app) {
            $databasePath = config('BangronDB.path', storage_path('BangronDB'));
            return new Client($databasePath);
        });
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/BangronDB.php' => config_path('BangronDB.php'),
        ], 'BangronDB-config');
    }
}
```

### Konfigurasi Laravel

```php
<?php
// config/BangronDB.php

return [
    'path' => env('BANGRONDB_PATH', storage_path('BangronDB')),
    'encryption_key' => env('BANGRONDB_ENCRYPTION_KEY'),
    'default_database' => env('BANGRONDB_DEFAULT_DB', 'app'),
];
```

### Model Laravel dengan BangronDB

```php
<?php
// app/Models/BangronDBModel.php

namespace App\Models;

use Illuminate\Support\Facades\App;
use BangronDB\Collection;

abstract class BangronDBModel
{
    protected $collection;
    protected $attributes = [];

    public function __construct(array $attributes = [])
    {
        $this->collection = App::make('BangronDB')
            ->selectDB($this->getDatabaseName())
            ->selectCollection($this->getCollectionName());

        $this->fill($attributes);
    }

    abstract protected function getDatabaseName(): string;
    abstract protected function getCollectionName(): string;

    public function fill(array $attributes)
    {
        $this->attributes = array_merge($this->attributes, $attributes);
        return $this;
    }

    public function save()
    {
        if (isset($this->attributes['_id'])) {
            // Update
            $this->collection->update(
                ['_id' => $this->attributes['_id']],
                $this->attributes,
                false // replace
            );
            return $this->attributes['_id'];
        } else {
            // Insert
            $id = $this->collection->insert($this->attributes);
            $this->attributes['_id'] = $id;
            return $id;
        }
    }

    public static function find($id)
    {
        $instance = new static();
        $data = $instance->collection->findOne(['_id' => $id]);
        return $data ? new static($data) : null;
    }

    public static function where($criteria)
    {
        $instance = new static();
        return $instance->collection->find($criteria);
    }

    public function toArray()
    {
        return $this->attributes;
    }

    public function __get($key)
    {
        return $this->attributes[$key] ?? null;
    }

    public function __set($key, $value)
    {
        $this->attributes[$key] = $value;
    }
}
```

### Penggunaan Model

```php
<?php
// app/Models/User.php

namespace App\Models;

class User extends BangronDBModel
{
    protected function getDatabaseName(): string
    {
        return 'app';
    }

    protected function getCollectionName(): string
    {
        return 'users';
    }

    public function posts()
    {
        return $this->collection->database->posts->find(['author_id' => $this->_id]);
    }
}

// Controller
class UserController extends Controller
{
    public function index()
    {
        $users = User::where(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->limit(20)
            ->toArray();

        return view('users.index', compact('users'));
    }

    public function store(Request $request)
    {
        $user = new User($request->validated());
        $user->status = 'active';
        $user->save();

        return redirect()->route('users.index');
    }
}
```

## Symfony Integration

### Bundle Setup

```php
<?php
// src/BangronDBBundle/BangronDBBundle.php

namespace App\BangronDBBundle;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class BangronDBBundle extends Bundle
{
    // Bundle configuration
}
```

### Service Configuration

```yaml
# config/services.yaml
services:
  App\Service\BangronDBService:
    arguments:
      $databasePath: "%BangronDB.database_path%"
      $encryptionKey: "%BangronDB.encryption_key%"

  # Repository pattern
  App\Repository\UserRepository:
    arguments:
      $bangronService: '@App\Service\BangronDBService'
```

### Symfony Controller

```php
<?php
// src/Controller/UserController.php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use App\Service\BangronDBService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class UserController extends AbstractController
{
    private $bangrondb;

    public function __construct(BangronDBService $bangrondb)
    {
        $this->bangrondb = $bangrondb;
    }

    public function index(): Response
    {
        $users = $this->bangrondb->getCollection('users')
            ->find(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->toArray();

        return $this->render('user/index.html.twig', [
            'users' => $users,
        ]);
    }

    public function create(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $userData = [
                'name' => $request->request->get('name'),
                'email' => $request->request->get('email'),
                'created_at' => date('c')
            ];

            $this->bangrondb->getCollection('users')->insert($userData);

            return $this->redirectToRoute('user_index');
        }

        return $this->render('user/create.html.twig');
    }
}
```

## CodeIgniter 4 Integration

### Library Setup

```php
<?php
// app/Libraries/BangronDB.php

namespace App\Libraries;

use BangronDB\Client;

class BangronDB
{
    private $client;

    public function __construct()
    {
        $this->client = new Client(WRITEPATH . 'BangronDB');
    }

    public function getClient()
    {
        return $this->client;
    }

    public function getDatabase($name = 'default')
    {
        return $this->client->$name;
    }

    public function getCollection($database, $collection)
    {
        return $this->getDatabase($database)->$collection;
    }
}
```

### Model CodeIgniter

```php
<?php
// app/Models/UserModel.php

namespace App\Models;

use CodeIgniter\Model;
use App\Libraries\BangronDB;

class UserModel extends Model
{
    protected $bangrondb;
    protected $collection;

    public function __construct()
    {
        parent::__construct();
        $this->bangrondb = new BangronDB();
        $this->collection = $this->bangrondb->getCollection('app', 'users');
    }

    public function getUsers($limit = 10, $offset = 0)
    {
        return $this->collection->find(['status' => 'active'])
            ->sort(['created_at' => -1])
            ->limit($limit)
            ->skip($offset)
            ->toArray();
    }

    public function createUser($data)
    {
        $data['created_at'] = date('c');
        $data['status'] = 'active';

        return $this->collection->insert($data);
    }

    public function getUser($id)
    {
        return $this->collection->findOne(['_id' => $id]);
    }
}
```

## Generic Framework Integration

### Service Container Pattern

```php
<?php
// Generic service container integration

use BangronDB\Client;

class DatabaseManager
{
    private static $instances = [];
    private $client;

    public function __construct($databasePath, $options = [])
    {
        $this->client = new Client($databasePath, $options);
    }

    public static function getInstance($name = 'default')
    {
        if (!isset(self::$instances[$name])) {
            // Load from config
            $config = self::loadConfig($name);
            self::$instances[$name] = new self($config['path'], $config['options']);
        }

        return self::$instances[$name];
    }

    public function getDatabase($name)
    {
        return $this->client->selectDB($name);
    }

    public function getCollection($database, $collection)
    {
        return $this->getDatabase($database)->selectCollection($collection);
    }

    private static function loadConfig($name)
    {
        // Load from your framework's config system
        return [
            'path' => '/var/data/BangronDB',
            'options' => [
                'encryption_key' => getenv('BANGRONDB_KEY')
            ]
        ];
    }
}

// Usage in any framework
$db = DatabaseManager::getInstance();
$users = $db->getCollection('app', 'users');
```

## Middleware & Authentication

### Laravel Middleware

```php
<?php
// app/Http/Middleware/BangronDBTransaction.php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\DB;

class BangronDBTransaction
{
    public function handle($request, Closure $next)
    {
        $bangrondb = app('BangronDB');
        $database = $bangrondb->selectDB('app');

        // Begin transaction if needed
        if ($this->needsTransaction($request)) {
            $database->connection->beginTransaction();
        }

        try {
            $response = $next($request);

            if ($this->needsTransaction($request)) {
                $database->connection->commit();
            }

            return $response;
        } catch (\Exception $e) {
            if ($this->needsTransaction($request)) {
                $database->connection->rollBack();
            }
            throw $e;
        }
    }

    private function needsTransaction($request)
    {
        return in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE']);
    }
}
```

## Dependency Injection Container

### PSR-11 Compatible

```php
<?php
// PSR-11 Container implementation

use Psr\Container\ContainerInterface;
use BangronDB\Client;

class BangronDBContainer implements ContainerInterface
{
    private $services = [];

    public function get($id)
    {
        if (!isset($this->services[$id])) {
            $this->services[$id] = $this->createService($id);
        }

        return $this->services[$id];
    }

    public function has($id): bool
    {
        return isset($this->services[$id]) || $this->canCreateService($id);
    }

    private function createService($id)
    {
        switch ($id) {
            case 'BangronDB.client':
                return new Client(getenv('BANGRONDB_PATH') ?: '/tmp/BangronDB');

            case 'BangronDB.database.app':
                return $this->get('BangronDB.client')->selectDB('app');

            case 'BangronDB.collection.users':
                return $this->get('BangronDB.database.app')->selectCollection('users');

            default:
                throw new ContainerException("Service not found: {$id}");
        }
    }

    private function canCreateService($id): bool
    {
        return in_array($id, [
            'BangronDB.client',
            'BangronDB.database.app',
            'BangronDB.collection.users',
        ]);
    }
}
```

## CakePHP Integration

### Datasource Configuration

```php
<?php
// config/app.php

return [
    'Datasources' => [
        'bangrondb' => [
            'className' => 'BangronDB\Database',
            'path' => ROOT . '/data/BangronDB',
            'encryption_key' => env('BANGRONDB_KEY'),
        ],
    ],
];
```

### Model

```php
<?php
// src/Model/Table/UsersTable.php

namespace App\Model\Table;

use Cake\ORM\Table;

class UsersTable extends Table
{
    private $bangrondb;

    public function initialize(array $config): void
    {
        parent::initialize($config);
        $this->bangrondb = new \BangronDB\Client($config['path'] ?? ROOT . '/data/BangronDB');
    }

    public function findActive($query, $options)
    {
        $collection = $this->bangrondb->selectDB('app')->users;
        return $collection->find(['status' => 'active']);
    }
}
```

## Yii2 Integration

### Component Configuration

```php
<?php
// config/web.php

return [
    'components' => [
        'bangrondb' => [
            'class' => 'App\Components\BangronDBComponent',
            'path' => '@app/data/BangronDB',
            'encryptionKey' => getenv('BANGRONDB_KEY'),
        ],
    ],
];
```

### Component

```php
<?php
// components/BangronDBComponent.php

namespace App\Components;

use yii\base\Component;
use BangronDB\Client;

class BangronDBComponent extends Component
{
    public $path;
    public $encryptionKey;

    private $client;

    public function init()
    {
        parent::init();
        $this->client = new Client($this->path);
    }

    public function getDb($name = 'app')
    {
        return $this->client->selectDB($name);
    }

    public function getCollection($db, $collection)
    {
        return $this->getDb($db)->$collection;
    }
}
```

## Best Practices

### 1. Service Container Registration

```php
// Register once in bootstrap
$container->singleton('BangronDB', function() {
    return new Client(config('bangrondb.path'));
});

// Use throughout application
$users = container('BangronDB')->selectDB('app')->users;
```

### 2. Connection Management

```php
// Close connections in shutdown handler
register_shutdown_function(function() {
    \BangronDB\Database::closeAll();
});
```

### 3. Transaction Support

```php
// Use transactions for multi-operation workflows
$db = $client->selectDB('app');
$db->connection->beginTransaction();

try {
    $db->users->insert([...]);
    $db->posts->insert([...]);
    $db->comments->insert([...]);

    $db->connection->commit();
} catch (\Exception $e) {
    $db->connection->rollBack();
    throw $e;
}
```

### 4. Error Handling

```php
// Centralized error handling
try {
    $collection->insert($data);
} catch (\BangronDB\Exceptions\ValidationException $e) {
    // Handle validation errors
    return response()->json(['error' => $e->getMessage()], 422);
} catch (\Exception $e) {
    // Handle other errors
    return response()->json(['error' => 'Internal server error'], 500);
}
```
