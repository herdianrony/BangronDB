# BangronDB API Reference v1.2.0

SQLite-based NoSQL document database dengan API MongoDB-like untuk PHP 8.1+

**Repository:** https://github.com/herdianrony/BangronDB  
**License:** MIT  
**PHP:** >= 8.1

---

## Daftar Isi

1. [Client API](API_CLIENT.md) – Mengelola multiple database
2. [Database API](API_DATABASE.md) – Connection, config, health metrics
3. [Collection API](API_COLLECTION.md) – CRUD, schema, hooks, encryption v2
4. [Cursor API](API_CURSOR.md) – find(), sort, limit, pagination
5. [Query Operators](API_QUERY_OPERATORS.md) – 30+ operator dengan contoh request/response
6. [Security API](API_SECURITY.md) – Encryption AES-256-GCM v2, blind index, FieldValidator, key rotation

---

## Instalasi

```bash
composer require herdianrony/bangrondb
```

Kebutuhan: PHP >= 8.1, ext-pdo_sqlite, ext-openssl

---

## Konsep Dasar

```
Client -> Database (.bangron / :memory:) -> Collection -> Document
```

```php
use BangronDB\Client;

$client = new Client(__DIR__.'/data');
$client->createDB('app');
$client->createCollection('app', 'users');

$users = $client->selectCollection('app', 'users');

$id = $users->insert(['name'=>'Rony', 'email'=>'rony@example.com']);
$user = $users->findOne(['_id' => $id]);
// Response: ['_id'=>'550e8400-...', 'name'=>'Rony', 'email'=>'rony@example.com']
```

---

## Contoh E2E

`/examples/` – 16 contoh:
01 CRUD, 02 query operators, 03 encryption searchable, 04 schema validation, 05 soft deletes, 06 hooks, 07 relationships populate, 08 transactions, 09 indexing health monitoring, 10 dynamic configuration, 11 multiple databases, 12 id modes, 13 security features, 14 ecommerce app, 15 auth encrypted, **16 key rotation – v1.2.0**

---

## Changelog v1.2.0

- Encryption: IV 16-byte → **12-byte NIST GCM**, `enc_v = 2`, `key_v`
- `Collection::setEncryptionKey($key, $keyVersion = null)`
- `Database::setEncryptionKey($key, $keyVersion = null)`
- `Collection::rotateEncryptionKey($newKey, $newKeyVersion): int`
- `Collection::reencryptAll(): int`
- `setCustomConfig()` block sensitive keys: `encryption_key, password, secret, token, api_key, private_key, credential` → throw `InvalidArgumentException`
- `CollectionManager`: `encryption_key` dihapus dari validKeys config persist
- Backward compatible: dokumen v1.0 (IV 16-byte) tetap bisa di-decrypt
