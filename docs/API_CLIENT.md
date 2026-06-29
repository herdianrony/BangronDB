# Client API – BangronDB v1.1.0

`BangronDB\Client` – Mengelola multiple database dalam satu path.

---

## __construct

```php
public function __construct(string $path = ':memory:', array $options = [])
```

**Parameters**
- `path string` – Direktori data, atau `:memory:`
- `options array` – `encryption_key`, `encryption_key_version`, `query_logging`, `performance_monitoring`

**Example Request**
```php
$client = new Client(__DIR__.'/data', [
  'encryption_key' => $_ENV['DB_ENCRYPTION_KEY'],
  'encryption_key_version' => 'v2-2026',
  'query_logging' => false,
]);
```
**Response:** `Client` instance

---

## createDB

```php
public function createDB(string $name): Database
```

**Example Request**
```php
$db = $client->createDB('app');
```
**Response:** `Database` object – file: `./data/app.bangron`

---

## dbExists

```php
public function dbExists(string $name): bool
```
**Example**
```php
$client->dbExists('app'); // true | false
```

---

## selectDB

```php
public function selectDB(string $name): Database
```
Non-lazy – hanya untuk DB yang sudah ada.

**Example Request**
```php
$db = $client->selectDB('app');
```

**Error Response**
```
RuntimeException: Database 'app' does not exist. Use createDB() first.
```

---

## dropDB / renameDB

```php
public function dropDB(string $name): bool
public function renameDB(string $old, string $new): bool
```

**Example Response**
```php
true
```

---

## createCollection

```php
public function createCollection(string $dbName, string $collectionName): Collection
```

**Example**
```php
$client->createCollection('app', 'users');
```

---

## collectionExists / listCollections

```php
public function collectionExists(string $db, string $collection): bool
public function listCollections(string $db): array   // alias: listCollection()
```

**Example Response**
```php
$client->listCollections('app');
// ['users', 'orders', 'products']
```

---

## selectCollection

```php
public function selectCollection(string $dbName, string $collectionName): Collection
```

**Example**
```php
$users = $client->selectCollection('app', 'users');
```

---

## renameCollection / dropCollection

```php
public function renameCollection(string $db, string $old, string $new): bool
public function dropCollection(string $db, string $collection): bool
```

---

## listDBs

```php
public function listDBs(): array
```

**Response**
```php
['app', 'logs', 'cache']
```

---

## close

```php
public function close(): void
```
Tutup semua koneksi database.

---

## Magic getter

```php
$db = $client->app;           // sama dengan selectDB('app')
$users = $db->users;          // sama dengan selectCollection('app','users')
```
Hanya untuk resource yang sudah ada, jika tidak ada akan throw `RuntimeException`.
