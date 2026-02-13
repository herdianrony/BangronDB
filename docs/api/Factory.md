# Factory Class

`BangronDB\Factory` provides centralized creation of Client, Database, and Collection objects with configuration management and comprehensive validation.

## Overview

```php
use BangronDB\Factory;
use BangronDB\Config;

Config::set('default_path', '/var/data/bangrondb');

$client = Factory::createClient();
$database = Factory::createDatabase('/var/data', 'mydb');
$collection = Factory::createCollection('/var/data', 'mydb', 'users');
```

## Static Methods

### Create Client

```php
public static function createClient(?string $path = null, array $options = []): Client
```

Creates a new Client instance with optional path and options.

```php
// Use default path from config
$client = Factory::createClient();

// Use custom path
$client = Factory::createClient('/custom/path');

// With options
$client = Factory::createClient('/path', [
    'encryption_key' => 'your-key',
    'query_logging' => true,
]);
```

**Throws:** `DatabaseException` if path is invalid or not accessible

### Create Database

```php
public static function createDatabase(string $path, string $name, array $options = []): Database
```

Creates a new Database instance within the specified path.

```php
$database = Factory::createDatabase('/var/data', 'mydb');
$database = Factory::createDatabase('/var/data', 'mydb', ['encryption_key' => 'key']);
```

**Throws:** `DatabaseException` if path or name is invalid

### Create Collection

```php
public static function createCollection(
    string $path,
    string $databaseName,
    string $collectionName,
    array $options = []
): Collection
```

Creates a new Collection instance.

```php
$collection = Factory::createCollection(
    '/var/data',
    'mydb',
    'users'
);
```

**Throws:** `DatabaseException` if path or names are invalid

### Create Collection from Database Instance

```php
public static function createCollectionFromDatabase(Database $database, string $collectionName): Collection
```

Creates a Collection from an existing Database instance.

```php
$database = Factory::createDatabase('/var/data', 'mydb');
$collection = Factory::createCollectionFromDatabase($database, 'posts');
```

## Internal Methods

### Normalize Path

```php
private static function normalizePath(string $path): string
```

Normalizes a path by removing trailing slashes and resolving relative paths.

```php
// Internally used
$normalized = self::normalizePath('/path/to/database/'); // '/path/to/database'
```

### Validate Path

```php
 private static function validatePath(string $path): void
```

Validates that a path exists, is readable, and is writable.

```php
// Internally used - throws DatabaseException if invalid
self::validatePath('/var/data/mydb.bangron');
```

## Usage Examples

### Basic Setup

```php
use BangronDB\Factory;
use BangronDB\Config;

// Set default configuration
Config::set('default_path', __DIR__ . '/data');
Config::set('encryption_key', 'default-encryption-key');
Config::set('journal_mode', 'WAL');

// Create client using defaults
$client = Factory::createClient();

// Or create with specific options
$client = Factory::createClient(null, [
    'encryption_key' => 'custom-key',
    'performance_monitoring' => true,
]);
```

### Multi-Tenant Setup

```php
// Create databases for different tenants
$tenants = ['tenant_a', 'tenant_b', 'tenant_c'];

foreach ($tenants as $tenant) {
    $database = Factory::createDatabase('/var/data', $tenant);

    // Setup tenant collections
    $database->selectCollection('users');
    $database->selectCollection('orders');
    $database->selectCollection('products');
}
```

### Collection with Configuration

```php
// Create collection with full configuration
$collection = Factory::createCollection('/var/data', 'app', 'users');

$collection->setSchema([
    'email' => ['required' => true, 'type' => 'string'],
    'name' => ['required' => true, 'type' => 'string'],
]);

$collection->setEncryptionKey('user-encryption-key');
$collection->setSearchableFields(['email'], true);
$collection->useSoftDeletes(true);
$collection->saveConfiguration();
```

### Error Handling

```php
use BangronDB\Factory;
use BangronDB\Exceptions\DatabaseException;

try {
    $client = Factory::createClient('/invalid/path');
} catch (DatabaseException $e) {
    echo "Error code: " . $e->getCode() . "\n";
    echo "Message: " . $e->getMessage() . "\n";

    // Handle specific error types
    if ($e instanceof DatabaseException) {
        // Handle database-specific errors
    }
}
```

## Integration with Config

Factory integrates with the Config class for default settings:

```php
use BangronDB\Factory;
use BangronDB\Config;

// Configure defaults
Config::set('default_path', '/var/bangrondb');
Config::set('journal_mode', 'WAL');
Config::set('synchronous', 'NORMAL');
Config::set('page_size', 4096);

// Factory uses these defaults
$client = Factory::createClient(); // Uses default_path
```

## Best Practices

1. **Use Factory for consistent object creation**
2. **Configure defaults via Config class**
3. **Handle DatabaseException appropriately**
4. **Reuse Client instance for multiple databases**
5. **Close connections when done**

```php
// Proper usage pattern
$client = Factory::createClient();

try {
    $db = $client->selectDB('app');
    $collection = $db->users;
    // ... operations
} finally {
    $client->close();
}
```
