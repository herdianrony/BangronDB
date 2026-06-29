# Client API – BangronDB v1.1.0

`BangronDB\Client`

## __construct(string $path = ':memory:', array $options = [])
```php
$client = new Client(__DIR__.'/data', [
  'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'],
  'encryption_key_version' => 'v2-2026',
]);
```

## createDB(string $name): Database
```php
$db = $client->createDB('app');
```

## dbExists(string $name): bool
## selectDB(string $name): Database
## dropDB(string $name): bool
## renameDB(string $old, string $new): bool

## createCollection(string $db, string $collection): Collection
## collectionExists(string $db, string $collection): bool
## selectCollection(string $db, string $collection): Collection
## listCollections(string $db): array
// Response: ['users','orders']

## renameCollection(string $db, string $old, string $new): bool
## dropCollection(string $db, string $collection): bool

## listDBs(): array
// Response: ['app','logs']

## close(): void

Magic getter: `$client->app`, `$db->users`
