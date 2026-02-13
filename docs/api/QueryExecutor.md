# QueryExecutor Class

Dokumentasi API untuk class `QueryExecutor` yang menangani eksekusi query SQL dengan fitur enhanced.

## Namespace

```php
namespace BangronDB;
```

## Deskripsi

`QueryExecutor` menangani eksekusi query SQL dengan fitur tambahan termasuk prepared statements, error handling, logging, dan performance monitoring. Class ini menyediakan layer keamanan dan debug untuk operasi database.

## Constructor

```php
public function __construct(\PDO $connection)
```

**Parameter:**

- `\PDO $connection` - Koneksi PDO

**Contoh:**

```php
$executor = new QueryExecutor($db->connection);
```

---

## Konfigurasi

### `setLogging()`

Mengaktifkan atau menonaktifkan query logging.

```php
public function setLogging(bool $enabled): self
```

**Parameter:**

- `bool $enabled` - `true` untuk aktifkan logging

**Nilai Kembali:**

- `self` - Untuk method chaining

**Contoh:**

```php
$executor->setLogging(true);
```

---

### `setPerformanceMonitoring()`

Mengaktifkan atau menonaktifkan monitoring performa.

```php
public function setPerformanceMonitoring(bool $enabled): self
```

**Parameter:**

- `bool $enabled` - `true` untuk aktifkan monitoring

**Nilai Kembali:**

- `self` - Untuk method chaining

**Contoh:**

```php
$executor->setPerformanceMonitoring(true);
```

---

## Eksekusi Query

### `executeQuery()`

Menjalankan query SELECT dan mengembalikan PDOStatement.

```php
public function executeQuery(string $sql, array $params = []): \PDOStatement
```

**Parameter:**

- `string $sql` - Query SQL dengan placeholders
- `array $params` - Array parameter untuk binding

**Nilai Kembali:**

- `\PDOStatement` - Statement yang sudah dieksekusi

**Throws:**

- `QueryExecutionException` - Jika eksekusi gagal

**Contoh:**

```php
$stmt = $executor->executeQuery(
    'SELECT * FROM users WHERE email = ?',
    ['john@example.com']
);
$users = $stmt->fetchAll(\PDO::FETCH_ASSOC);
```

---

### `executeUpdate()`

Menjalankan query non-SELECT (INSERT, UPDATE, DELETE).

```php
public function executeUpdate(string $sql, array $params = []): int
```

**Parameter:**

- `string $sql` - Query SQL dengan placeholders
- `array $params` - Array parameter untuk binding

**Nilai Kembali:**

- `int` - Jumlah baris yang terpengaruh

**Throws:**

- `QueryExecutionException` - Jika eksekusi gagal

**Contoh:**

```php
$affected = $executor->executeUpdate(
    'UPDATE users SET name = ? WHERE id = ?',
    ['John Doe', 1]
);
echo "Updated $affected rows\n";
```

---

### `executeTransaction()`

Menjalankan multiple query dalam satu transaction.

```php
public function executeTransaction(array $queries): array
```

**Parameter:**

- `array $queries` - Array dengan format `[ ['sql' => string, 'params' => array], ... ]`

**Nilai Kembali:**

- `array` - Hasil dari setiap query

**Throws:**

- `QueryExecutionException` - Jika transaction gagal

**Contoh:**

```php
$results = $executor->executeTransaction([
    [
        'sql' => 'INSERT INTO users (name) VALUES (?)',
        'params' => ['User1']
    ],
    [
        'sql' => 'INSERT INTO posts (title, user_id) VALUES (?, ?)',
        'params' => ['Post 1', 1]
    ],
]);
```

---

## Metode Deprecated

### `executeRaw()` ⚠️

**Deprecated:** Gunakan `executeQuery()` dengan prepared statements.

Menjalankan query raw tanpa parameters.

```php
public function executeRaw(string $sql): \PDOStatement|false
```

---

### `executeRawUpdate()` ⚠️

**Deprecated:** Gunakan `executeUpdate()` dengan prepared statements.

Menjalankan query update raw tanpa parameters.

```php
public function executeRawUpdate(string $sql): int
```

---

## Utility Methods

### `getLastInsertId()`

Mendapatkan ID dari insert terakhir.

```php
public function getLastInsertId(): string
```

**Nilai Kembali:**

- `string` - ID dari insert terakhir

**Contoh:**

```php
$executor->executeUpdate('INSERT INTO users (name) VALUES (?)', ['John']);
$id = $executor->getLastInsertId();
```

---

### `quote()`

Meng-quote string untuk penggunaan SQL yang aman.

```php
public function quote(string $string): string
```

**Parameter:**

- `string $string` - String untuk di-quote

**Nilai Kembali:**

- `string` - String yang sudah di-quote

**Contoh:**

```php
$safeString = $executor->quote("John's email");
```

---

### `tableExists()`

Memeriksa apakah tabel ada.

```php
public function tableExists(string $tableName): bool
```

**Parameter:**

- `string $tableName` - Nama tabel

**Nilai Kembali:**

- `true` jika tabel ada, `false` jika tidak

**Contoh:**

```php
if ($executor->tableExists('users')) {
    echo "Table users exists\n";
}
```

---

### `sanitizeIdentifier()`

Men-sanitize identifier (nama tabel/kolom).

```php
public function sanitizeIdentifier(string $identifier): string
```

**Parameter:**

- `string $identifier` - Identifier untuk di-sanitize

**Nilai Kembali:**

- `string` - Identifier yang sudah disanitize

**Throws:**

- `\InvalidArgumentException` - Jika identifier tidak valid

---

### `quoteTable()`

Meng-quote nama tabel dengan aman.

```php
public function quoteTable(string $tableName): string
```

**Parameter:**

- `string $tableName` - Nama tabel

**Nilai Kembali:**

- `string` - Nama tabel yang sudah di-quote

**Contoh:**

```php
$quoted = $executor->quoteTable('users');
// Output: `users`
```

---

## Logging dan Monitoring

### `getQueryLog()`

Mendapatkan log query.

```php
public function getQueryLog(): array
```

**Nilai Kembali:**

```php
[
    [
        'timestamp' => 1704067200.1234,
        'sql' => 'SELECT * FROM users WHERE email = ?',
        'params' => ['john@example.com'],
        'execution_time' => 1.5, // milliseconds
        'affected_rows' => 1,
        'type' => 'SELECT',
    ],
    ...
]
```

**Contoh:**

```php
$log = $executor->getQueryLog();
foreach ($log as $entry) {
    echo "SQL: {$entry['sql']}\n";
    echo "Time: {$entry['execution_time']}ms\n";
}
```

---

### `getQueryStats()`

Mendapatkan statistik eksekusi query.

```php
public function getQueryStats(): array
```

**Nilai Kembali:**

```php
[
    'SELECT' => [
        'count' => 100,
        'total_time' => 150.5, // total milliseconds
        'avg_time' => 1.505,
        'min_time' => 0.5,
        'max_time' => 10.0,
    ],
    'INSERT' => [...],
    ...
]
```

**Contoh:**

```php
$stats = $executor->getQueryStats();
foreach ($stats as $type => $data) {
    echo "Type: $type\n";
    echo "Count: {$data['count']}\n";
    echo "Avg Time: {$data['avg_time']}ms\n";
}
```

---

### `clearLogs()`

Menghapus log dan statistik.

```php
public function clearLogs(): void
```

**Contoh:**

```php
$executor->clearLogs();
```

---

## QueryExecutionException

Exception yang dilempar ketika eksekusi query gagal.

### Properties

```php
public string $sql;      // Query SQL yang gagal
public array $params;    // Parameter yang digunakan
```

### Constructor

```php
public function __construct(string $message, string $sql, array $params = [], ?\Throwable $previous = null)
```

### Method

```php
public function getSql(): string
```

**Nilai Kembali:**

- `string` - Query SQL yang gagal

**Contoh:**

```php
try {
    $executor->executeQuery('INVALID SQL', []);
} catch (QueryExecutionException $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "SQL: " . $e->getSql() . "\n";
    echo "Params: " . print_r($e->params, true);
}
```

---

## Contoh Penggunaan Lengkap

```php
use BangronDB\Database;
use BangronDB\QueryExecutor;

$db = new Database('/path/to/db.bangron');
$executor = new QueryExecutor($db->connection);

// Aktifkan logging dan monitoring
$executor->setLogging(true)
         ->setPerformanceMonitoring(true);

// Insert data
$executor->executeUpdate(
    'INSERT INTO users (name, email) VALUES (?, ?)',
    ['John Doe', 'john@example.com']
);

// Select data
$stmt = $executor->executeQuery(
    'SELECT * FROM users WHERE email = ?',
    ['john@example.com']
);
$user = $stmt->fetch(\PDO::FETCH_ASSOC);

// Update data
$executor->executeUpdate(
    'UPDATE users SET name = ? WHERE id = ?',
    ['Jane Doe', $user['id']]
);

// Delete data
$executor->executeUpdate(
    'DELETE FROM users WHERE id = ?',
    [$user['id']]
);

// Transaction
$executor->executeTransaction([
    ['sql' => 'INSERT INTO accounts (balance) VALUES (?)', 'params' => [1000]],
    ['sql' => 'INSERT INTO transactions (amount) VALUES (?)', 'params' => [-100]],
]);

// Get statistics
$stats = $executor->getQueryStats();
$log = $executor->getQueryLog();

// Handle errors
try {
    $executor->executeQuery('SELECT * FROM nonexistent', []);
} catch (QueryExecutionException $e) {
    echo "Query failed: " . $e->getMessage() . "\n";
    echo "SQL: " . $e->getSql() . "\n";
}
```

---

## Best Practices

1. **Selalu gunakan prepared statements:**

   ```php
   // ✅ Good
   $executor->executeQuery('SELECT * FROM users WHERE id = ?', [$id]);

   // ❌ Bad - rentan SQL injection
   $executor->executeRaw("SELECT * FROM users WHERE id = $id");
   ```

2. **Aktifkan logging untuk development:**

   ```php
   $executor->setLogging(true);
   ```

3. **Gunakan monitoring untuk optimisasi:**

   ```php
   $executor->setPerformanceMonitoring(true);
   $stats = $executor->getQueryStats();
   ```

4. **Gunakan transaction untuk operasi multiple:**
   ```php
   $executor->executeTransaction([
       ['sql' => 'INSERT INTO orders ...', 'params' => [...]],
       ['sql' => 'UPDATE inventory ...', 'params' => [...]],
   ]);
   ```
