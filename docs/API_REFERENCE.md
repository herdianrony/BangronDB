# BangronDB API Reference v1.1.0

Document database SQLite dengan API MongoDB-like untuk PHP 8.1+

**Repo:** https://github.com/herdianrony/BangronDB  
**License:** MIT

## Daftar Isi
1. [Client API](API_CLIENT.md)
2. [Database API](API_DATABASE.md)
3. [Collection API](API_COLLECTION.md)
4. [Cursor API](API_CURSOR.md)
5. [Query Operators](API_QUERY_OPERATORS.md)
6. [Security API](API_SECURITY.md)

## Quick Start
```php
use BangronDB\Client;
$client = new Client(__DIR__.'/data');
$client->createDB('app');
$client->createCollection('app', 'users');
$users = $client->selectCollection('app', 'users');
$id = $users->insert(['name'=>'Rony','email'=>'rony@example.com']);
$user = $users->findOne(['_id'=>$id]);
```

## Changelog v1.1.0
- Encryption IV 16-byte → 12-byte NIST, enc_v=2, key_v support
- `rotateEncryptionKey()`, `reencryptAll()`
- `setCustomConfig()` block sensitive keys: encryption_key, password, secret, token, api_key, etc.
- CollectionManager: remove encryption_key from persisted config

Lihat file terpisah untuk detail lengkap.
