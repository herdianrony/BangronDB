# BangronDB Hardening Kit – v1.2.0


3 rekomendasi audit yang Anda maksud sudah di-handle di sini:

1. **Migrate blind index lama**
   - `php migrate_blind_index.php`
   - Otomatis rehash `si_*` dari SHA-256 plain -> HMAC-SHA256
   - Edit array `$toMigrate` sesuai DB/collection Anda

2. **Jangan hardcode encryption key**
   - `SecureClientFactory::create()`
   - Key diambil dari `$_ENV['DB_ENCRYPTION_KEY']` / `.env`
   - Validasi >= 32 chars, throw jika lemah
   - Contoh `.env.example` disertakan

3. **Jangan over-expose searchable fields**
   - `SecureClientFactory::SEARCHABLE_ALLOWLIST`
   - Hanya `users.email` yang di-hash secara default, field lain di-comment
   - `applySearchableFields()` mencegah developer asal set searchable

Cara pakai:
```php
require 'SecureClientFactory.php';
$client = SecureClientFactory::create();
$users = $client->selectCollection('app','users');
SecureClientFactory::applySearchableFields($users, 'users');
```

Catatan: Ini adalah wrapper untuk **aplikasi consumer** BangronDB, bukan patch library inti. Library BangronDB 1.0.0 sendiri sudah aman.
