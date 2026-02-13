# Panduan Troubleshooting BangronDB

Panduan lengkap untuk mengatasi masalah umum saat menggunakan BangronDB.

## 🚀 Quick Start Issues

### Error: "Class BangronDB\Client not found"

**Symptom:** Fatal error ketika menjalankan script.

**Cause:** Autoloading tidak dikonfigurasi dengan benar atau file source tidak di-include.

**Solutions:**

1. **Gunakan autoloading dengan Composer:**

   ```bash
   composer require bangrondb/bangrondb
   ```

2. **Manual include semua file yang diperlukan:**

   ```php
   require_once 'path/to/bangrondb/src/Client.php';
   require_once 'path/to/bangrondb/src/Collection.php';
   require_once 'path/to/bangrondb/src/Database.php';
   require_once 'path/to/bangrondb/src/Cursor.php';
   require_once 'path/to/bangrondb/src/DatabaseMetrics.php';
   require_once 'path/to/bangrondb/src/UtilArrayQuery.php';
   require_once 'path/to/bangrondb/src/QueryExecutor.php';
   require_once 'path/to/bangrondb/src/Factory.php';
   require_once 'path/to/bangrondb/src/Config.php';
   require_once 'path/to/bangrondb/src/CollectionManager.php';

   // Include semua traits
   require_once 'path/to/bangrondb/src/Traits/EncryptionTrait.php';
   require_once 'path/to/bangrondb/src/Traits/HooksTrait.php';
   require_once 'path/to/bangrondb/src/Traits/IdGeneratorTrait.php';
   require_once 'path/to/bangrondb/src/Traits/QueryBuilderTrait.php';
   require_once 'path/to/bangrondb/src/Traits/SchemaValidationTrait.php';
   require_once 'path/to/bangrondb/src/Traits/SearchableFieldsTrait.php';
   require_once 'path/to/bangrondb/src/Traits/SoftDeleteTrait.php';
   ```

3. **Verifikasi path file:**
   ```bash
   # Pastikan file ada
   ls -la path/to/bangrondb/src/
   ```

### Error: "Database file not found" atau "Permission denied"

**Symptom:** Operasi database gagal dengan file system errors.

**Solutions:**

1. **Buat direktori data dengan permissions yang benar:**

   ```bash
   mkdir -p /path/to/database/directory
   chmod 755 /path/to/database/directory
   ```

2. **Untuk Windows:**

   ```cmd
   mkdir "C:\path\to\database\directory"
   # Pastikan directory tidak read-only
   ```

3. **Gunakan absolute path:**

   ```php
   $client = new BangronDB\Client('/absolute/path/to/database/');
   ```

4. **Check disk space:**
   ```bash
   df -h  # Linux/Mac
   # atau
   dir  # Windows
   ```

## 🔍 Query dan Data Issues

### Query tidak mengembalikan hasil yang diharapkan

**Debug Steps:**

1. **Verifikasi data ada:**

   ```php
   $count = $collection->count();
   echo "Total documents: $count\n";
   ```

2. **Check query syntax:**

   ```php
   // Correct
   $results = $collection->find(['status' => 'active']);

   // Wrong - case sensitive
   $results = $collection->find(['Status' => 'active']);
   ```

3. **Debug dengan logging:**

   ```php
   $collection->database->queryExecutor->setLogging(true);
   $results = $collection->find($criteria)->toArray();
   error_log("Query criteria: " . json_encode($criteria));
   error_log("Results count: " . count($results));
   $log = $collection->database->queryExecutor->getQueryLog();
   ```

4. **Check untuk soft deletes:**

   ```php
   // Jika soft deletes enabled, documents yang dihapus tidak akan muncul
   $collection->useSoftDeletes(true);

   // Lihat semua termasuk yang dihapus
   $trashed = $collection->find()->onlyTrashed()->toArray();
   ```

### Schema validation errors

**Common Issues:**

1. **Required field missing:**

   ```php
   // Error
   $collection->insert(['name' => 'John']);

   // Fix - add required fields
   $collection->insert([
       'name' => 'John',
       'email' => 'john@example.com',
       'age' => 30
   ]);
   ```

2. **Type mismatch:**

   ```php
   // Schema expects integer
   $collection->setSchema([
       'age' => ['type' => 'integer', 'required' => true]
   ]);

   // Wrong
   $collection->insert(['age' => '30']); // String instead of int

   // Correct
   $collection->insert(['age' => 30]); // Integer
   ```

3. **Enum validation:**

   ```php
   // Schema
   $collection->setSchema([
       'role' => ['type' => 'string', 'enum' => ['admin', 'user']]
   ]);

   // Wrong
   $collection->insert(['role' => 'superuser']);

   // Correct
   $collection->insert(['role' => 'admin']);
   ```

### Index tidak bekerja

**Symptoms:** Queries lambat, index tidak digunakan.

**Debug:**

1. **Check index exists:**

   ```php
   // Get collection metrics
   $metrics = new BangronDB\DatabaseMetrics($db);
   $collectionMetrics = $metrics->getHealthMetrics()['collections']['collection_name'];

   echo "Indexes: " . implode(', ', $collectionMetrics['indexes']);
   ```

2. **Verify searchable fields configuration:**

   ```php
   $searchableFields = $collection->getSearchableFields();
   print_r($searchableFields);
   ```

3. **Rebuild indexes:**

   ```php
   // Drop and recreate collection to rebuild indexes
   $collection->drop();
   $collection = $db->selectCollection('collection_name');

   // Recreate indexes
   $collection->createIndex('field_name');
   ```

## 🔐 Encryption Issues

### Data tidak ter-encrypt

**Check:**

1. **Encryption key set:**

   ```php
   $client = new BangronDB\Client($path, [
       'encryption_key' => 'your-32-char-secret-key-here'
   ]);

   // Atau per collection
   $collection->setEncryptionKey('your-secret-key');
   ```

2. **Key length:** Harus 32 karakter untuk AES-256.

3. **Encryption enabled per collection:**
   ```php
   echo "Encryption enabled: " . ($collection->isEncrypted() ? 'Yes' : 'No');
   ```

### Cannot decrypt data

**Causes:**

1. **Wrong key**
2. **Key changed after data inserted**
3. **Corrupted data**

**Recovery:**

```php
// Backup dulu
// Kemudian coba decrypt dengan key yang benar
$collection->setEncryptionKey('correct-key');

// Jika masih gagal, mungkin perlu manual recovery
```

## 🎣 Performance Issues

### Slow queries

**Optimization Steps:**

1. **Add indexes:**

   ```php
   $collection->createIndex('frequently_queried_field');
   ```

2. **Use searchable fields untuk text search:**

   ```php
   $collection->setSearchableFields([
       'name' => false, // Plain text search
       'category' => true // Hashed exact match
   ]);
   ```

3. **Limit result set:**

   ```php
   $results = $collection->find($criteria)->limit(100)->toArray();
   ```

4. **Enable performance monitoring:**

   ```php
   $db->queryExecutor->setPerformanceMonitoring(true);
   $stats = $db->queryExecutor->getQueryStats();
   ```

5. **Check query execution time:**

   ```php
   $start = microtime(true);
   $results = $collection->find($criteria)->toArray();
   $time = microtime(true) - $start;
   echo "Query time: {$time}s\n";
   ```

### High memory usage

**Solutions:**

1. **Process in batches:**

   ```php
   $batchSize = 1000;
   $offset = 0;

   do {
       $batch = $collection->find()
           ->limit($batchSize)
           ->skip($offset)
           ->toArray();

       // Process batch
       foreach ($batch as $document) {
           // ... process ...
       }

       $offset += $batchSize;
   } while (count($batch) == $batchSize);
   ```

2. **Use cursors untuk large datasets:**

   ```php
   $cursor = $collection->find($criteria);
   foreach ($cursor as $document) {
       // Process one at a time
   }
   ```

3. **Clear variables:**
   ```php
   unset($largeArray);
   gc_collect_cycles();
   ```

### Database file terlalu besar

**Maintenance:**

1. **Vacuum database:**

   ```php
   $db->vacuum();
   ```

   Atau via SQLite command line:

   ```bash
   sqlite3 database.bangron "VACUUM;"
   ```

2. **Check fragmentation:**

   ```php
   $metrics = new BangronDB\DatabaseMetrics($db);
   $perf = $metrics->getHealthMetrics()['performance'];

   echo "Fragmentation ratio: " . $perf['fragmentation_ratio'];
   ```

3. **Rebuild database:**

   ```php
   // Export all data
   $allData = $collection->find()->toArray();

   // Drop and recreate collection
   $collection->drop();
   $collection = $db->selectCollection('collection_name');

   // Re-import data in batches
   $batchSize = 1000;
   for ($i = 0; $i < count($allData); $i += $batchSize) {
       $batch = array_slice($allData, $i, $batchSize);
       $collection->insert($batch);
   }
   ```

## 🔄 Migration Issues

### Data tidak ter-migrate dengan benar

**Common Problems:**

1. **Field mapping salah:**

   ```php
   // Wrong
   $migration->mapField('old_field', 'newField');

   // Correct
   $migration->mapField('old_field', 'new_field');
   ```

2. **Type conversion issues:**

   ```php
   // Handle type conversions explicitly
   $migration->transform(function($doc) {
       $doc['age'] = (int) $doc['age']; // Ensure integer
       return $doc;
   });
   ```

3. **Duplicate key errors:** Pastikan ID generation konsisten.

### Collection migration fails

**Debug:**

1. **Check collection exists:**

   ```php
   $collections = $db->getCollectionNames();
   print_r($collections);
   ```

2. **Validate data sebelum migration:**

   ```php
   $invalidDocs = [];
   $cursor = $collection->find();
   foreach ($cursor as $doc) {
       if (!$collection->validate($doc)) {
           $invalidDocs[] = $doc;
       }
   }
   ```

3. **Run migration in small batches:**
   ```php
   $migration->setBatchSize(100);
   $migration->migrate();
   ```

## 🌐 Web Server Issues

### Examples tidak bisa dijalankan

**PHP Built-in Server Issues:**

1. **Port sudah digunakan:**

   ```bash
   # Check what's using the port
   lsof -i :3000  # Linux/Mac
   netstat -ano | findstr :3000  # Windows

   # Use different port
   php -S localhost:3001 index.php
   ```

2. **Directory permissions:**

   ```bash
   chmod -R 755 examples/
   chmod -R 755 examples/data/
   ```

3. **PHP extensions missing:**
   ```bash
   php -m | grep -E "(pdo|sqlite)"
   ```

### Admin panel tidak bisa diakses

**Debug Steps:**

1. **Check PHP server running:**

   ```bash
   ps aux | grep php
   ```

2. **Verify file paths in admin:**

   ```php
   // Check if files exist
   echo file_exists('path/to/admin/index.php') ? 'Yes' : 'No';
   ```

3. **Browser cache:** Hard refresh (Ctrl+F5)

4. **Check logs:**
   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ```

## 🔧 Development Issues

### IDE/Linter Errors

**PHPStan/PSALM Issues:**

1. **Ubah configuration:**

   ```neon
   # phpstan.neon
   parameters:
       level: 5
       paths:
           - src/
       ignoreErrors:
           - '#Undefined method#'
           - '#Undefined property#'
   ```

2. **Add type hints:**
   ```php
   /** @var BangronDB\Collection $collection */
   $collection = $db->selectCollection('users');
   ```

### Testing Issues

**PHPUnit Configuration:**

1. **Update phpunit.xml:**

   ```xml
   <phpunit>
       <testsuites>
           <testsuite name="BangronDB Tests">
               <directory>tests/</directory>
           </testsuite>
       </testsuites>

       <coverage>
           <include>
               <directory suffix=".php">src/</directory>
           </include>
       </coverage>
   </phpunit>
   ```

2. **Test database setup:**

   ```php
   protected function setUp(): void
   {
       $this->client = new Client(':memory:');
       $this->db = $this->client->selectDB('test');
   }

   protected function tearDown(): void
   {
       $this->client->close();
       Database::closeAll();
   }
   ```

## 📞 Getting Help

### Checklist Sebelum Meminta Bantuan

1. **Verifikasi versi PHP:**

   ```bash
   php --version
   ```

2. **Cek ekstensi yang terinstall:**

   ```bash
   php -m | grep -E "(pdo|sqlite|openssl)"
   ```

3. **Enable debug mode:**

   ```php
   error_reporting(E_ALL);
   ini_set('display_errors', 1);
   ini_set('log_errors', 1);
   ```

4. **Buat minimal reproducible example:**

   ```php
   <?php
   require_once 'vendor/autoload.php';

   use BangronDB\Client;

   // Simple test case
   $client = new Client(':memory:');
   $db = $client->selectDB('test');
   $collection = $db->test;

   // Insert
   $id = $collection->insert(['name' => 'Test']);

   // Find
   $result = $collection->findOne(['_id' => $id]);

   var_dump($result);
   ```

### Useful Debugging Tools

```php
// Enable query logging
$db->queryExecutor->setLogging(true);

// Check queries
$log = $db->queryExecutor->getQueryLog();

// Enable performance monitoring
$db->queryExecutor->setPerformanceMonitoring(true);

// Get stats
$stats = $db->queryExecutor->getQueryStats();

// Check database health
$metrics = $db->getHealthMetrics();

// Check collection stats
$collectionMetrics = $metrics['collections'] ?? [];
```

### Common Error Messages

| Error                     | Cause                  | Solution                        |
| ------------------------- | ---------------------- | ------------------------------- |
| "Class not found"         | Autoloading issue      | Run `composer dump-autoload`    |
| "Database file not found" | Path issue             | Check directory permissions     |
| "Validation failed"       | Schema mismatch        | Check schema configuration      |
| "Encryption failed"       | Invalid key            | Verify key length (32 chars)    |
| "Table not found"         | Collection not created | Call `createCollection()` first |

### Official Resources

- [GitHub Issues](https://github.com/bangrondb/bangrondb/issues) - Report bugs
- [Documentation](README.md) - Full documentation
- [Examples](../examples/) - Working examples
- [API Reference](api/) - API documentation
