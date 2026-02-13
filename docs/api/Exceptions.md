# Exceptions

Dokumentasi untuk exception classes di BangronDB.

## Namespace

```php
namespace BangronDB\Exceptions;
```

## Hierarchy

```
\Throwable
    └── \Exception
        └── BangronDBException
            ├── DatabaseException
            ├── CollectionException
            └── ValidationException
```

---

## BangronDBException

Exception dasar untuk semua exception di BangronDB.

```php
class BangronDBException extends \Exception
```

**Metode:**

- Tidak ada metode tambahan, hanya mewarisi dari `\Exception`

**Contoh:**

```php
throw new BangronDBException('Something went wrong');
```

---

## DatabaseException

Exception untuk operasi database.

```php
class DatabaseException extends BangronDBException
```

### Metode Statis

### `invalidPath()`

Membuat exception untuk path yang tidak valid.

```php
public static function invalidPath(string $path, string $reason, array $context = []): self
```

**Parameter:**

- `string $path` - Path yang tidak valid
- `string $reason` - Alasan mengapa tidak valid
- `array $context` - Konteks tambahan

**Contoh:**

```php
throw DatabaseException::invalidPath(
    '/nonexistent/path',
    'Directory does not exist',
    ['directory' => '/nonexistent']
);
```

---

### `permissionDenied()`

Membuat exception untuk permission denied.

```php
public static function permissionDenied(string $path, string $operation, array $context = []): self
```

**Parameter:**

- `string $path` - Path yang tidak bisa diakses
- `string $operation` - Operasi yang dicoba (read/write)
- `array $context` - Konteks tambahan

**Contoh:**

```php
throw DatabaseException::permissionDenied(
    '/var/db/data.bangron',
    'write',
    ['directory' => '/var/db']
);
```

---

### `connectionFailed()`

Membuat exception untuk koneksi gagal.

```php
public static function connectionFailed(string $message, ?\Throwable $previous = null): self
```

**Parameter:**

- `string $message` - Pesan error
- `?\Throwable $previous` - Exception sebelumnya

**Contoh:**

```php
throw DatabaseException::connectionFailed('Unable to connect to database');
```

---

### `queryFailed()`

Membuat exception untuk query yang gagal.

```php
public static function queryFailed(string $sql, string $reason, array $params = []): self
```

**Parameter:**

- `string $sql` - Query yang gagal
- `string $reason` - Alasan kegagalan
- `array $params` - Parameter yang digunakan

**Contoh:**

```php
throw DatabaseException::queryFailed(
    'SELECT * FROM users',
    'Table not found',
    []
);
```

---

## CollectionException

Exception untuk operasi collection.

```php
class CollectionException extends BangronDBException
```

### Metode Statis

### `notFound()`

Membuat exception untuk collection yang tidak ditemukan.

```php
public static function notFound(string $collectionName): self
```

**Parameter:**

- `string $collectionName` - Nama collection

**Contoh:**

```php
throw CollectionException::notFound('users');
```

---

### `invalidName()`

Membuat exception untuk nama collection yang tidak valid.

```php
public static function invalidName(string $name, string $reason): self
```

**Parameter:**

- `string $name` - Nama yang tidak valid
- `string $reason` - Alasan

**Contoh:**

```php
throw CollectionException::invalidName('invalid-name', 'Contains invalid characters');
```

---

### `operationFailed()`

Membuat exception untuk operasi yang gagal.

```php
public static function operationFailed(string $operation, string $reason, array $context = []): self
```

**Parameter:**

- `string $operation` - Operasi yang gagal
- `string $reason` - Alasan kegagalan
- `array $context` - Konteks tambahan

**Contoh:**

```php
throw CollectionException::operationFailed(
    'insert',
    'Duplicate key',
    ['document' => ['_id' => '123']]
);
```

---

## ValidationException

Exception untuk validasi data yang gagal.

```php
class ValidationException extends BangronDBException
```

### Metode Statis

### `invalidField()`

Membuat exception untuk field yang tidak valid.

```php
public static function invalidField(string $field, string $message, mixed $value = null): self
```

**Parameter:**

- `string $field` - Nama field
- `string $message` - Pesan validasi
- `mixed $value` - Nilai yang tidak valid

**Contoh:**

```php
throw ValidationException::invalidField(
    'email',
    'Invalid email format',
    'invalid-email'
);
```

---

### `missingRequired()`

Membuat exception untuk field yang required tetapi kosong.

```php
public static function missingRequired(string $field): self
```

**Parameter:**

- `string $field` - Nama field yang required

**Contoh:**

```php
throw ValidationException::missingRequired('email');
```

---

### `invalidType()`

Membuat exception untuk tipe data yang tidak sesuai.

```php
public static function invalidType(string $field, string $expectedType, mixed $actual): self
```

**Parameter:**

- `string $field` - Nama field
- `string $expectedType` - Tipe yang diharapkan
- `mixed $actual` - Tipe aktual

**Contoh:**

```php
throw ValidationException::invalidType(
    'age',
    'integer',
    'twenty'
);
```

---

### `outOfRange()`

Membuat exception untuk nilai di luar range.

```php
public static function outOfRange(string $field, mixed $min, mixed $max, mixed $actual): self
```

**Parameter:**

- `string $field` - Nama field
- `mixed $min` - Nilai minimum
- `mixed $max` - Nilai maksimum
- `mixed $actual` - Nilai aktual

**Contoh:**

```php
throw ValidationException::outOfRange(
    'age',
    0,
    150,
    200
);
```

---

### `invalidEnum()`

Membuat exception untuk nilai enum yang tidak valid.

```php
public static function invalidEnum(string $field, array $allowed, mixed $actual): self
```

**Parameter:**

- `string $field` - Nama field
- `array $allowed` - Nilai yang diperbolehkan
- `mixed $actual` - Nilai yang diberikan

**Contoh:**

```php
throw ValidationException::invalidEnum(
    'role',
    ['admin', 'user', 'guest'],
    'superuser'
);
```

---

### `invalidNameFormat()`

Membuat exception untuk format nama yang tidak valid.

```php
public static function invalidNameFormat(string $name, string $pattern, string $type): self
```

**Parameter:**

- `string $name` - Nama yang tidak valid
- `string $pattern` - Pattern yang diharapkan
- `string $type` - Tipe nama (database/collection)

**Contoh:**

```php
throw ValidationException::invalidNameFormat(
    'My DB',
    '/^[a-z0-9_-]+$/i',
    'database'
);
```

---

## Penggunaan Exception

### Try-Catch Basic

```php
use BangronDB\Client;
use BangronDB\Exceptions\DatabaseException;
use BangronDB\Exceptions\CollectionException;
use BangronDB\Exceptions\ValidationException;

try {
    $client = new Client('/path/to/db');
    $db = $client->selectDB('app');
    $collection = $db->selectCollection('users');

    $collection->insert([
        'name' => 'John Doe',
        'email' => 'john@example.com',
    ]);

} catch (DatabaseException $e) {
    echo "Database error: " . $e->getMessage() . "\n";

} catch (CollectionException $e) {
    echo "Collection error: " . $e->getMessage() . "\n";

} catch (ValidationException $e) {
    echo "Validation error: " . $e->getMessage() . "\n";

} catch (\Exception $e) {
    echo "General error: " . $e->getMessage() . "\n";
}
```

### Validasi dengan Exception Handling

```php
use BangronDB\Collection;
use BangronDB\Exceptions\ValidationException;

function createUser(Collection $users, array $data): string
{
    // Set schema jika belum ada
    if (empty($users->getSchema())) {
        $users->setSchema([
            'name' => ['required' => true, 'type' => 'string', 'min' => 2],
            'email' => ['required' => true, 'type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
            'age' => ['type' => 'int', 'min' => 0, 'max' => 150],
            'role' => ['enum' => ['admin', 'user', 'guest']],
        ]);
    }

    try {
        return $users->insert($data);

    } catch (ValidationException $e) {
        // Log atau tangani validasi error
        error_log("Validation failed: " . $e->getMessage());

        // Re-throw dengan konteks tambahan
        throw new ValidationException(
            "Failed to create user: " . $e->getMessage(),
            0,
            $e
        );
    }
}

// Penggunaan
try {
    $id = createUser($users, [
        'name' => 'J',
        'email' => 'invalid-email',
        'age' => 200,
        'role' => 'superuser',
    ]);
} catch (ValidationException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
```

### Custom Exception Handler

```php
use BangronDB\Exceptions\BangronDBException;

class ExceptionHandler
{
    public static function handle(\Throwable $e): void
    {
        if ($e instanceof BangronDBException) {
            self::handleBangronDBException($e);
        } else {
            self::handleGenericException($e);
        }
    }

    private static function handleBangronDBException(BangronDBException $e): void
    {
        $type = (new \ReflectionClass($e))->getShortName();

        error_log(sprintf(
            "[%s] %s in %s:%d",
            $type,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine()
        ));

        // Tampilkan pesan user-friendly
        echo "Terjadi kesalahan: " . self::getUserMessage($e) . "\n";
    }

    private static function getUserMessage(BangronDBException $e): string
    {
        if ($e instanceof ValidationException) {
            return "Data yang输入 tidak valid. Silakan periksa kembali.";
        }

        if ($e instanceof DatabaseException) {
            return "Terjadi kesalahan database. Silakan coba lagi.";
        }

        return "Terjadi kesalahan. Silakan coba lagi.";
    }

    private static function handleGenericException(\Exception $e): void
    {
        error_log("Unexpected error: " . $e->getMessage());
        echo "Terjadi kesalahan yang tidak terduga.\n";
    }
}

// Daftarkan exception handler
set_exception_handler([ExceptionHandler::class, 'handle']);
```

---

## Best Practices

1. **Selalu tangkap exception spesifik:**

   ```php
   // ✅ Good - Tangkap exception spesifik
   try {
       $collection->insert($data);
   } catch (ValidationException $e) {
       // Tangani validasi error
   }

   // ❌ Bad - Tangkap semua exception
   try {
       $collection->insert($data);
   } catch (\Exception $e) {
       // Terlalu umum
   }
   ```

2. **Gunakan exception untuk alur kontrol:**

   ```php
   // ✅ Good - Gunakan exception untuk kasus exceptional
   if (!$collection->validate($data)) {
       throw new ValidationException('Invalid data');
   }
   ```

3. **Log exception untuk debugging:**

   ```php
   try {
       // Operation
   } catch (BangronDBException $e) {
       error_log($e->getMessage());
       throw $e;
   }
   ```

4. **Gunakan chaining untuk exception:**
   ```php
   try {
       // Operation
   } catch (\Exception $e) {
       throw new DatabaseException(
           'Failed to perform operation',
           0,
           $e
       );
   }
   ```
