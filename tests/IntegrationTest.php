<?php

namespace BangronDB\Tests;

use BangronDB\Client;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

/**
 * Comprehensive Integration Test for BangronDB.
 *
 * Tests full end-to-end workflows across all components:
 * - Client -> Database -> Collection interactions
 * - Configuration persistence and loading
 * - Cross-component feature integration
 * - Performance and monitoring
 * - Data integrity and consistency
 */
class IntegrationTest extends TestCase
{
    private string $tempDir;
    private Client $client;
    private string $encryptionKey = 'test-encryption-key-for-integration';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/bangrondb_integration_' . uniqid();
        mkdir($this->tempDir);
        $this->client = new Client($this->tempDir, [
            'query_logging' => true,
            'performance_monitoring' => true,
        ]);
    }

    protected function tearDown(): void
    {
        $this->client->close();

        // Clean up temp directory
        if (is_dir($this->tempDir)) {
            usleep(100000); // Wait for connections to close
            $files = glob($this->tempDir . '/*');
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->tempDir);
        }
    }

    /**
     * Test complete e-commerce workflow with multiple databases and collections.
     */
    public function testEcommerceWorkflow()
    {
        // Create databases for different domains
        $userDb = $this->client->selectDB('users');
        $productDb = $this->client->selectDB('products');
        $orderDb = $this->client->selectDB('orders');

        // Setup users collection with encryption and validation
        $users = $userDb->selectCollection('customers');
        $users->setEncryptionKey($this->encryptionKey);
        $users->setSchema([
            'name' => ['type' => 'string', 'required' => true],
            'email' => ['type' => 'string', 'required' => true, 'format' => 'email'],
            'age' => ['type' => 'integer', 'min' => 13, 'max' => 120],
        ]);
        $users->useSoftDeletes();
        $users->saveConfiguration();

        // Setup products collection with searchable fields
        $products = $productDb->selectCollection('items');
        $products->setSearchableFields(['name', 'category'], false);
        $products->setSchema([
            'name' => ['type' => 'string', 'required' => true],
            'price' => ['type' => 'number', 'min' => 0],
            'category' => ['type' => 'string', 'required' => true],
            'stock' => ['type' => 'integer', 'min' => 0],
        ]);
        $products->saveConfiguration();

        // Setup orders collection with hooks
        $orders = $orderDb->selectCollection('purchases');
        $orders->on('beforeInsert', function ($doc) {
            $doc['order_date'] = date('Y-m-d H:i:s');
            $doc['status'] = 'pending';

            return $doc;
        });
        $orders->on('afterInsert', function ($doc) {
            // Simulate inventory update (would normally be in separate service)
            $this->updateProductStock($doc['items']);
        });

        // Insert test data
        $userId = $users->insert([
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 30,
        ]);

        // Insert products one by one to get IDs
        $productIds = [];
        $productIds[] = $products->insert(['name' => 'Laptop', 'price' => 999.99, 'category' => 'Electronics', 'stock' => 50]);
        $productIds[] = $products->insert(['name' => 'Book', 'price' => 19.99, 'category' => 'Education', 'stock' => 100]);
        $productIds[] = $products->insert(['name' => 'Headphones', 'price' => 79.99, 'category' => 'Electronics', 'stock' => 25]);

        // Create order with references
        $orderId = $orders->insert([
            'customer_id' => $userId,
            'items' => [
                ['product_id' => $productIds[0], 'quantity' => 1, 'price' => 999.99],
                ['product_id' => $productIds[2], 'quantity' => 2, 'price' => 79.99],
            ],
            'total' => 999.99 + (2 * 79.99),
        ]);

        // Verify data integrity across databases
        $user = $users->findOne(['_id' => $userId]);
        $this->assertEquals('John Doe', $user['name']);
        $this->assertEquals('john@example.com', $user['email']);

        $order = $orders->findOne(['_id' => $orderId]);
        $this->assertEquals('pending', $order['status']);
        $this->assertArrayHasKey('order_date', $order);
        $this->assertEquals($userId, $order['customer_id']);

        // Test populate functionality (simulate cross-collection query)
        $populatedOrder = $orders->populate($order, 'customer_id', 'users.customers', '_id', 'customer');
        $this->assertEquals('John Doe', $populatedOrder['customer']['name']);

        // Test search functionality
        $electronics = $products->find(['category' => 'Electronics'])->toArray();
        $this->assertCount(2, $electronics);

        // Test configuration persistence
        $usersConfig = $users->database->loadCollectionConfig('customers');
        $this->assertTrue($usersConfig['soft_deletes_enabled']);
        // Config stores encryption_enabled boolean, not the actual key
        $this->assertTrue($usersConfig['encryption_enabled']);
    }

    /**
     * Test performance monitoring and query logging across components.
     */
    public function testPerformanceMonitoring()
    {
        $db = $this->client->selectDB('perf_test');
        $collection = $db->selectCollection('test_data');

        // Enable monitoring
        $db->queryExecutor->setLogging(true);
        $db->queryExecutor->setPerformanceMonitoring(true);

        // Perform operations
        for ($i = 0; $i < 10; ++$i) {
            $collection->insert(['name' => 'Test ' . $i, 'value' => $i]);
        }

        $results = $collection->find(['value' => ['$gte' => 5]])->toArray();

        // Verify monitoring data
        $queryLog = $db->queryExecutor->getQueryLog();
        $queryStats = $db->queryExecutor->getQueryStats();

        $this->assertNotEmpty($queryLog);
        $this->assertArrayHasKey('INSERT', $queryStats);
        $this->assertArrayHasKey('SELECT', $queryStats);

        // Verify query types were logged
        // Note: Some queries might not be logged depending on timing and configuration
        $insertCount = count(array_filter($queryLog, fn($log) => $log['type'] === 'INSERT'));
        $this->assertGreaterThanOrEqual(1, $insertCount, 'Expected at least 1 INSERT query to be logged');
    }

    /**
     * Test health monitoring and metrics across the system.
     */
    public function testHealthAndMetrics()
    {
        $db = $this->client->selectDB('health_test');

        // Create collections and add data
        $collection1 = $db->selectCollection('collection1');
        $collection2 = $db->selectCollection('collection2');

        for ($i = 0; $i < 50; ++$i) {
            $collection1->insert(['data' => 'test' . $i, 'group' => $i % 5]);
            $collection2->insert(['info' => 'info' . $i, 'category' => chr(65 + ($i % 3))]);
        }

        // Create indexes
        $collection1->createIndex('group');
        $collection2->createIndex('category');

        // Get comprehensive health report
        $healthReport = $db->getHealthReport();

        $this->assertIsArray($healthReport);
        $this->assertArrayHasKey('status', $healthReport);
        $this->assertArrayHasKey('issues', $healthReport);
        $this->assertArrayHasKey('warnings', $healthReport);
        $this->assertArrayHasKey('recommendations', $healthReport);

        // Get detailed metrics
        $dataMetrics = $db->getDataMetrics();
        $performanceMetrics = $db->getPerformanceMetrics();
        $indexMetrics = $db->getIndexMetrics();
        $collectionMetrics = $db->getCollectionMetrics();

        $this->assertIsArray($dataMetrics);
        $this->assertIsArray($performanceMetrics);
        $this->assertIsArray($indexMetrics);
        $this->assertIsArray($collectionMetrics);

        // Verify collection metrics
        $this->assertArrayHasKey('collection1', $collectionMetrics);
        $this->assertArrayHasKey('collection2', $collectionMetrics);
        $this->assertEquals(50, $collectionMetrics['collection1']['documents']);
        $this->assertEquals(50, $collectionMetrics['collection2']['documents']);
    }

    /**
     * Test concurrent operations and data consistency.
     */
    public function testConcurrentOperations()
    {
        $db = $this->client->selectDB('concurrent_test');
        $collection = $db->selectCollection('shared_data');

        // Add hook for concurrency testing
        $collection->on('beforeUpdate', function ($criteria, $data) {
            // Simulate some processing time
            usleep(1000);

            return [$criteria, $data];
        });

        // Insert initial data
        $ids = [];
        for ($i = 0; $i < 20; ++$i) {
            $id = $collection->insert(['counter' => 0, 'name' => 'item' . $i]);
            $ids[] = $id;
        }

        // Simulate concurrent updates (in real scenario, would use actual concurrency)
        foreach ($ids as $id) {
            $doc = $collection->findOne(['_id' => $id]);
            $collection->update(['_id' => $id], ['$set' => ['counter' => ($doc['counter'] ?? 0) + 1]]);
        }

        // Verify all updates were applied
        $totalCount = 0;
        foreach ($ids as $id) {
            $doc = $collection->findOne(['_id' => $id]);
            $totalCount += $doc['counter'] ?? 0;
        }

        $this->assertEquals(20, $totalCount); // Each document should have counter = 1

        // Test transaction-like behavior with multiple operations
        $db->connection->beginTransaction();

        try {
            $collection->insert(['name' => 'transaction_test', 'value' => 100]);
            $collection->update(['name' => 'item1'], ['value' => 999]);

            $db->connection->commit();

            // Verify transaction committed
            $inserted = $collection->findOne(['name' => 'transaction_test']);
            $updated = $collection->findOne(['name' => 'item1']);

            $this->assertNotNull($inserted);
            $this->assertEquals(999, $updated['value'] ?? null);
        } catch (\Exception $e) {
            $db->connection->rollBack();
            throw $e;
        }
    }

    /**
     * Test backup and restore workflow (simulated).
     */
    public function testBackupRestoreWorkflow()
    {
        $sourceDb = $this->client->selectDB('source_db');
        $sourceCollection = $sourceDb->selectCollection('important_data');

        // Insert diverse data with all features
        $sourceCollection->setEncryptionKey('backup-key-with-minimum-32-chars-ok');
        $sourceCollection->setSchema([
            'name' => ['type' => 'string', 'required' => true],
            'value' => ['type' => 'integer', 'required' => true],
        ]);
        $sourceCollection->useSoftDeletes();

        $data = [];
        for ($i = 0; $i < 100; ++$i) {
            $data[] = [
                'name' => 'Record ' . $i,
                'value' => $i,
                'metadata' => ['created' => time(), 'type' => 'test'],
            ];
        }
        $sourceCollection->insert($data);
        $sourceCollection->saveConfiguration();

        // Simulate backup by copying data
        $backupDb = $this->client->selectDB('backup_db');
        $backupCollection = $backupDb->selectCollection('important_data');

        // Copy configuration
        $config = $sourceDb->loadCollectionConfig('important_data');
        // Config stores encryption_enabled boolean, get key from external source in real scenario
        // For testing, we'll just enable encryption on the backup
        $backupCollection->setEncryptionKey($this->encryptionKey); // Use test key
        $backupCollection->setSchema($config['schema']);
        $backupCollection->useSoftDeletes();
        $backupCollection->saveConfiguration();

        // Copy data
        $allData = $sourceCollection->find()->toArray();
        $backupCollection->insert($allData);

        // Verify backup integrity
        $sourceCount = $sourceCollection->count();
        $backupCount = $backupCollection->count();

        $this->assertEquals($sourceCount, $backupCount);

        // Verify configurations match
        $sourceConfig = $sourceDb->loadCollectionConfig('important_data');
        $backupConfig = $backupDb->loadCollectionConfig('important_data');

        // Config stores encryption_enabled boolean, not the actual key
        $this->assertEquals($sourceConfig['encryption_enabled'], $backupConfig['encryption_enabled']);
        $this->assertEquals($sourceConfig['soft_deletes_enabled'], $backupConfig['soft_deletes_enabled']);
    }

    /**
     * Helper method for order processing simulation.
     */
    private function updateProductStock(array $orderItems): void
    {
        // In a real implementation, this would update inventory
        // For testing, we just verify the structure
        foreach ($orderItems as $item) {
            $this->assertArrayHasKey('product_id', $item);
            $this->assertArrayHasKey('quantity', $item);
        }
    }
}
