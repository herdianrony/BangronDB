# Migration & Upgrade Guide

Panduan lengkap untuk migrasi data dan upgrade BangronDB antar versi.

## Data Migration Strategies

### Export/Import Pattern

```php
<?php
class DataMigration {
    private $sourceDb;
    private $targetDb;

    public function __construct(Database $sourceDb, Database $targetDb) {
        $this->sourceDb = $sourceDb;
        $this->targetDb = $targetDb;
    }

    public function migrateCollection(string $collectionName, callable $transformer = null): array {
        $sourceCollection = $this->sourceDb->selectCollection($collectionName);
        $targetCollection = $this->targetDb->selectCollection($collectionName);

        $stats = [
            'processed' => 0,
            'successful' => 0,
            'failed' => 0,
            'errors' => []
        ];

        // Migrate in batches to avoid memory issues
        $batchSize = 1000;
        $cursor = $sourceCollection->find();

        while ($batch = $this->getNextBatch($cursor, $batchSize)) {
            foreach ($batch as $document) {
                $stats['processed']++;

                try {
                    // Apply transformation if provided
                    if ($transformer) {
                        $document = $transformer($document);
                        if ($document === null) {
                            continue; // Skip document
                        }
                    }

                    $targetCollection->insert($document);
                    $stats['successful']++;
                } catch (Exception $e) {
                    $stats['failed']++;
                    $stats['errors'][] = [
                        'document_id' => $document['_id'] ?? 'unknown',
                        'error' => $e->getMessage()
                    ];
                }
            }
        }

        return $stats;
    }

    private function getNextBatch($cursor, int $batchSize): array {
        $batch = [];
        for ($i = 0; $i < $batchSize && $cursor->valid(); $i++) {
            $batch[] = $cursor->current();
            $cursor->next();
        }
        return $batch;
    }

    public function migrateWithResume(string $collectionName, string $checkpointFile): array {
        $checkpoint = $this->loadCheckpoint($checkpointFile);
        $sourceCollection = $this->sourceDb->selectCollection($collectionName);
        $targetCollection = $this->targetDb->selectCollection($collectionName);

        // Resume from last checkpoint
        $cursor = $sourceCollection->find();
        if ($checkpoint['last_id']) {
            // Skip to resume point (this is simplified - real implementation needs proper skipping)
            while ($cursor->valid() && $cursor->current()['_id'] !== $checkpoint['last_id']) {
                $cursor->next();
            }
            $cursor->next(); // Skip the last processed document
        }

        return $this->migrateCollection($collectionName, null, $checkpoint);
    }

    private function loadCheckpoint(string $file): array {
        if (file_exists($file)) {
            return json_decode(file_get_contents($file), true);
        }
        return ['last_id' => null, 'processed' => 0];
    }

    private function saveCheckpoint(string $file, array $checkpoint): void {
        file_put_contents($file, json_encode($checkpoint));
    }
}

// Usage
$migrator = new DataMigration($oldDb, $newDb);

// Simple migration
$stats = $migrator->migrateCollection('users', function($user) {
    // Transform user data
    $user['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
    unset($user['first_name'], $user['last_name']);

    // Convert date format if needed
    if (isset($user['birth_date'])) {
        $user['birth_date'] = date('Y-m-d', strtotime($user['birth_date']));
    }

    return $user;
});

echo "Migration completed: {$stats['successful']} successful, {$stats['failed']} failed\n";

// Migration with resume capability
$stats = $migrator->migrateWithResume('large_collection', '/tmp/migration_checkpoint.json');
```

### Schema Evolution

```php
<?php
class SchemaEvolution {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function evolveCollection(string $collectionName, array $evolution): void {
        $collection = $this->db->selectCollection($collectionName);

        // Apply schema evolution
        foreach ($evolution as $change) {
            switch ($change['type']) {
                case 'add_field':
                    $this->addField($collection, $change);
                    break;
                case 'remove_field':
                    $this->removeField($collection, $change);
                    break;
                case 'rename_field':
                    $this->renameField($collection, $change);
                    break;
                case 'change_field_type':
                    $this->changeFieldType($collection, $change);
                    break;
                case 'add_index':
                    $this->addIndex($collection, $change);
                    break;
            }
        }
    }

    private function addField(Collection $collection, array $change): void {
        $fieldName = $change['field'];
        $defaultValue = $change['default'] ?? null;

        if ($defaultValue !== null) {
            // Update all existing documents
            $collection->update(
                [$fieldName => ['$exists' => false]],
                ['$set' => [$fieldName => $defaultValue]]
            );
        }
    }

    private function removeField(Collection $collection, array $change): void {
        $fieldName = $change['field'];

        // Remove field from all documents
        $documents = $collection->find()->toArray();
        foreach ($documents as $doc) {
            if (isset($doc[$fieldName])) {
                unset($doc[$fieldName]);
                $collection->update(['_id' => $doc['_id']], $doc, false);
            }
        }
    }

    private function renameField(Collection $collection, array $change): void {
        $oldName = $change['old_name'];
        $newName = $change['new_name'];

        $documents = $collection->find([$oldName => ['$exists' => true]])->toArray();
        foreach ($documents as $doc) {
            $doc[$newName] = $doc[$oldName];
            unset($doc[$oldName]);
            $collection->update(['_id' => $doc['_id']], $doc, false);
        }
    }

    private function changeFieldType(Collection $collection, array $change): void {
        $fieldName = $change['field'];
        $newType = $change['new_type'];
        $transformer = $change['transformer'] ?? null;

        $documents = $collection->find([$fieldName => ['$exists' => true]])->toArray();
        foreach ($documents as $doc) {
            $value = $doc[$fieldName];

            // Apply transformation
            if ($transformer) {
                $value = $transformer($value);
            } else {
                // Default type conversion
                $value = $this->convertValue($value, $newType);
            }

            $collection->update(
                ['_id' => $doc['_id']],
                ['$set' => [$fieldName => $value]]
            );
        }
    }

    private function convertValue($value, string $type) {
        switch ($type) {
            case 'string':
                return (string) $value;
            case 'int':
                return (int) $value;
            case 'float':
                return (float) $value;
            case 'bool':
                return (bool) $value;
            default:
                return $value;
        }
    }

    private function addIndex(Collection $collection, array $change): void {
        $field = $change['field'];
        $indexName = $change['name'] ?? null;

        $collection->createIndex($field, $indexName);
    }
}

// Usage
$evolution = new SchemaEvolution($db);

// Add new field with default value
$evolution->evolveCollection('users', [
    [
        'type' => 'add_field',
        'field' => 'last_login_ip',
        'default' => null
    ],
    [
        'type' => 'rename_field',
        'old_name' => 'phone_number',
        'new_name' => 'phone'
    ],
    [
        'type' => 'change_field_type',
        'field' => 'age',
        'new_type' => 'int',
        'transformer' => function($value) {
            return is_numeric($value) ? (int) $value : 0;
        }
    ],
    [
        'type' => 'add_index',
        'field' => 'email',
        'name' => 'idx_users_email'
    ]
]);
```

## Version Upgrade Guide

### Upgrade dari v1.0 ke v1.1

#### Fitur Baru di v1.1:

- Dynamic Configuration System
- Schema Validation
- Soft Deletes
- Change Notification
- Enhanced Security

#### Migration Steps:

1. **Backup Database**

```bash
cp /var/data/database.sqlite /var/data/database_backup.sqlite
```

2. **Update Dependencies**

```bash
composer update bangrondb/bangrondb
```

3. **Run Migration Script**

```php
<?php
// migration_v1.1.php

require_once 'vendor/autoload.php';

use BangronDB\Client;

$client = new Client('/var/data');
$db = $client->selectDB('app');

// Create new system tables
// (Migration will be handled automatically by Database constructor)

// Migrate existing collections to use new features
$collections = $db->getCollectionNames();

foreach ($collections as $collectionName) {
    $collection = $db->selectCollection($collectionName);

    // Add basic configuration for existing collections
    $collection->saveConfiguration();

    // Add basic schema validation if needed
    // $collection->setSchema([...]);

    // Enable soft deletes for certain collections
    if (in_array($collectionName, ['users', 'posts', 'comments'])) {
        $collection->useSoftDeletes(true);
        $collection->saveConfiguration();
    }

    // Add searchable fields for encrypted collections
    if ($collection->isEncrypted()) {
        $collection->setSearchableFields(['email', 'username'], true);
        $collection->saveConfiguration();
    }
}

echo "Migration to v1.1 completed!\n";
```

4. **Update Application Code**

```php
<?php
// Old code (v1.0)
$users = $db->users;
$user = $users->insert(['name' => 'John']);

// New code (v1.1) - with schema validation
$users = $db->users;
$users->setSchema([
    'name' => ['required' => true, 'type' => 'string', 'min' => 2],
    'email' => ['type' => 'string', 'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'age' => ['type' => 'int', 'min' => 0, 'max' => 150]
]);

try {
    $userId = $users->insert([
        'name' => 'John',
        'email' => 'john@example.com',
        'age' => 30
    ]);
} catch (Exception $e) {
    echo "Validation error: " . $e->getMessage();
}
```

### Breaking Changes in v1.1

#### 1. Collection Caching Removed

**Reason:** Memory leak issues in long-running processes

**Migration:**

```php
// Old code (no longer works)
$collection->clearCache();
$collection->flushCache();

// New code (not needed)
$collection->find()->toArray(); // Always fresh data
```

#### 2. Hook API Changes

**Reason:** More consistent return values

**Migration:**

```php
// Old code
$collection->on('beforeInsert', function($doc) {
    if (empty($doc['name'])) {
        return false; // Cancel
    }
    $doc['created_at'] = date('c');
    return $doc; // Modify
});

// New code (same)
$collection->on('beforeInsert', function($doc) {
    if (empty($doc['name'])) {
        return false; // Cancel
    }
    $doc['created_at'] = date('c');
    return $doc; // Modify
});
```

#### 3. Encryption Format Changes

**Reason:** Support for searchable fields

**Migration:** Automatic during first access with encryption

## Rollback Strategies

### Point-in-Time Recovery

```php
<?php
class RollbackManager {
    private $db;
    private $backupDir;

    public function __construct(Database $db, string $backupDir) {
        $this->db = $db;
        $this->backupDir = $backupDir;
    }

    public function createRestorePoint(string $name): void {
        $timestamp = date('Y-m-d_H-i-s');
        $backupPath = $this->backupDir . "/restore_point_{$name}_{$timestamp}";

        // Backup all databases
        $databases = glob($this->db->path . '/*.bangron');
        foreach ($databases as $dbPath) {
            $dbName = basename($dbPath, '.bangron');
            copy($dbPath, $backupPath . "/{$dbName}.bangron");
        }

        // Backup collection configurations
        $configs = [];
        foreach ($this->db->getCollectionNames() as $collectionName) {
            $configs[$collectionName] = $this->db->loadCollectionConfig($collectionName);
        }
        file_put_contents($backupPath . '/configs.json', json_encode($configs));
    }

    public function rollbackToPoint(string $restorePointName): void {
        $restorePoints = glob($this->backupDir . "/restore_point_{$restorePointName}_*");
        if (empty($restorePoints)) {
            throw new Exception("Restore point not found: {$restorePointName}");
        }

        $latestPoint = end($restorePoints);

        // Close current database connections
        Database::closeAll();

        // Restore database files
        $dbFiles = glob($latestPoint . '/*.bangron');
        foreach ($dbFiles as $dbFile) {
            $dbName = basename($dbFile, '.bangron');
            copy($dbFile, $this->db->path . "/{$dbName}.bangron");
        }

        // Restore configurations
        $configFile = $latestPoint . '/configs.json';
        if (file_exists($configFile)) {
            $configs = json_decode(file_get_contents($configFile), true);
            foreach ($configs as $collectionName => $config) {
                $this->db->saveCollectionConfig($collectionName, $config);
            }
        }
    }

    public function listRestorePoints(): array {
        $points = [];
        $files = glob($this->backupDir . '/restore_point_*');

        foreach ($files as $file) {
            $basename = basename($file);
            if (preg_match('/restore_point_(.+)_(.+)/', $basename, $matches)) {
                $points[] = [
                    'name' => $matches[1],
                    'timestamp' => $matches[2],
                    'path' => $file
                ];
            }
        }

        return $points;
    }
}

// Usage
$rollback = new RollbackManager($db, '/var/backups');

// Create restore point before major changes
$rollback->createRestorePoint('before_schema_change');

// If something goes wrong
$rollback->rollbackToPoint('before_schema_change');
```

### Selective Rollback

```php
<?php
class SelectiveRollback {
    private $db;

    public function __construct(Database $db) {
        $this->db = $db;
    }

    public function rollbackCollectionChanges(string $collectionName, int $targetVersion): void {
        $collection = $this->db->selectCollection($collectionName);
        $currentVersion = $collection->getLastModified()['version'];

        if ($targetVersion >= $currentVersion) {
            return; // Nothing to rollback
        }

        // This is a simplified example
        // In practice, you'd need a change log to rollback specific changes
        throw new Exception("Selective rollback not implemented for version-based rollback");
    }

    public function rollbackSchemaChanges(string $collectionName, array $oldSchema): void {
        $collection = $this->db->selectCollection($collectionName);

        // Remove new fields
        $documents = $collection->find()->toArray();
        foreach ($documents as $doc) {
            $modified = false;
            foreach ($doc as $field => $value) {
                if (!isset($oldSchema[$field])) {
                    unset($doc[$field]);
                    $modified = true;
                }
            }

            if ($modified) {
                $collection->update(['_id' => $doc['_id']], $doc, false);
            }
        }

        // Restore old schema
        $collection->setSchema($oldSchema);
        $collection->saveConfiguration();
    }
}
```

## Best Practices for Migration

### Pre-Migration Checklist

- [ ] Backup all databases
- [ ] Test migration scripts on staging environment
- [ ] Verify data integrity after migration
- [ ] Check application compatibility
- [ ] Prepare rollback plan
- [ ] Notify stakeholders about downtime

### Migration Testing

```php
<?php
class MigrationTest {
    private $sourceDb;
    private $targetDb;

    public function testDataIntegrity(): bool {
        // Compare record counts
        $sourceCounts = $this->getCollectionCounts($this->sourceDb);
        $targetCounts = $this->getCollectionCounts($this->targetDb);

        foreach ($sourceCounts as $collection => $count) {
            if (($targetCounts[$collection] ?? 0) !== $count) {
                return false;
            }
        }

        return true;
    }

    public function testDataConsistency(): bool {
        // Sample some records and verify they match
        $collections = $this->sourceDb->getCollectionNames();

        foreach ($collections as $collectionName) {
            $sourceCollection = $this->sourceDb->selectCollection($collectionName);
            $targetCollection = $this->targetDb->selectCollection($collectionName);

            $sourceSample = $sourceCollection->find()->limit(10)->toArray();
            $targetSample = $targetCollection->find()->limit(10)->toArray();

            // Compare samples (simplified)
            if (count($sourceSample) !== count($targetSample)) {
                return false;
            }
        }

        return true;
    }

    private function getCollectionCounts(Database $db): array {
        $counts = [];
        foreach ($db->getCollectionNames() as $collectionName) {
            $collection = $db->selectCollection($collectionName);
            $counts[$collectionName] = $collection->count();
        }
        return $counts;
    }
}
```

### Post-Migration Verification

```php
<?php
class PostMigrationVerifier {
    private $db;

    public function runVerificationSuite(): array {
        $results = [];

        $results['health_check'] = $this->verifyHealth();
        $results['data_integrity'] = $this->verifyDataIntegrity();
        $results['performance'] = $this->verifyPerformance();
        $results['configuration'] = $this->verifyConfiguration();

        return $results;
    }

    private function verifyHealth(): bool {
        $health = $this->db->getHealthReport();
        return $health['status'] === 'healthy';
    }

    private function verifyDataIntegrity(): bool {
        // Verify no orphaned records, proper relationships, etc.
        $issues = [];

        // Check for documents without required fields
        $collections = $this->db->getCollectionNames();
        foreach ($collections as $collectionName) {
            $collection = $this->db->selectCollection($collectionName);
            $config = $this->db->loadCollectionConfig($collectionName);

            if (isset($config['schema'])) {
                // Check schema compliance
                $documents = $collection->find()->limit(100)->toArray();
                foreach ($documents as $doc) {
                    try {
                        $collection->validate($doc);
                    } catch (Exception $e) {
                        $issues[] = "Schema validation failed for {$collectionName}:{$doc['_id']} - {$e->getMessage()}";
                    }
                }
            }
        }

        return empty($issues);
    }

    private function verifyPerformance(): bool {
        // Run performance benchmarks
        $start = microtime(true);

        // Test query performance
        foreach ($this->db->getCollectionNames() as $collectionName) {
            $collection = $this->db->selectCollection($collectionName);
            $collection->find()->limit(100)->toArray();
        }

        $duration = microtime(true) - $start;
        return $duration < 5.0; // Should complete within 5 seconds
    }

    private function verifyConfiguration(): bool {
        // Verify all collections have proper configurations
        foreach ($this->db->getCollectionNames() as $collectionName) {
            $config = $this->db->loadCollectionConfig($collectionName);
            if (empty($config)) {
                return false; // All collections should have config in v1.1+
            }
        }

        return true;
    }
}

// Run verification
$verifier = new PostMigrationVerifier($db);
$results = $verifier->runVerificationSuite();

if (array_sum($results) === count($results)) {
    echo "✅ Migration verification passed!\n";
} else {
    echo "❌ Migration verification failed!\n";
    print_r(array_filter($results, function($result) { return !$result; }));
}
```

Dengan mengikuti panduan ini, Anda dapat melakukan migrasi dan upgrade BangronDB dengan aman dan terstruktur, meminimalkan risiko kehilangan data dan downtime aplikasi.
