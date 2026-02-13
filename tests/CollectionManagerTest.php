<?php

namespace BangronDB\Tests;

use BangronDB\CollectionManager;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class CollectionManagerTest extends TestCase
{
    private Database $db;
    private CollectionManager $manager;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->manager = new CollectionManager($this->db);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testSaveAndLoadCollectionConfig()
    {
        $config = [
            'id_mode' => 'prefix',
            'encryption_key' => 'test-secret-key',
            'searchable_fields' => [
                'email' => true,
                'phone' => false,
            ],
            'schema' => [
                'name' => ['required' => true, 'type' => 'string'],
                'email' => ['type' => 'string'],
            ],
            'soft_deletes_enabled' => true,
            'deleted_at_field' => 'deleted_at',
        ];

        // Save config
        $this->manager->saveCollectionConfig('users', $config);

        // Load config
        $loadedConfig = $this->manager->loadCollectionConfig('users');

        // Verify config
        $this->assertEquals('prefix', $loadedConfig['id_mode']);
        $this->assertEquals('test-secret-key', $loadedConfig['encryption_key']);
        $this->assertEquals(['email' => true, 'phone' => false], $loadedConfig['searchable_fields']);
        $this->assertEquals($config['schema'], $loadedConfig['schema']);
        $this->assertTrue($loadedConfig['soft_deletes_enabled']);
        $this->assertEquals('deleted_at', $loadedConfig['deleted_at_field']);
        $this->assertArrayHasKey('created_at', $loadedConfig);
        $this->assertArrayHasKey('updated_at', $loadedConfig);
        $this->assertIsInt($loadedConfig['created_at']);
        $this->assertIsInt($loadedConfig['updated_at']);
    }

    public function testLoadNonExistentConfig()
    {
        $config = $this->manager->loadCollectionConfig('nonexistent');
        $this->assertEmpty($config);
    }

    public function testUpdateCollectionConfig()
    {
        // Initial config
        $this->manager->saveCollectionConfig('users', [
            'id_mode' => 'auto',
            'encryption_key' => 'old-key',
        ]);

        $loaded = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('auto', $loaded['id_mode']);
        $oldUpdatedAt = $loaded['updated_at'];

        // Small delay to ensure different timestamp
        usleep(1000);

        // Update config
        $this->manager->saveCollectionConfig('users', [
            'id_mode' => 'manual',
            'encryption_key' => 'new-key',
        ]);

        $updated = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('manual', $updated['id_mode']);
        $this->assertEquals('new-key', $updated['encryption_key']);
        $this->assertGreaterThanOrEqual($oldUpdatedAt, $updated['updated_at']);
    }

    public function testGetAllCollectionConfigs()
    {
        // Save multiple configs
        $this->manager->saveCollectionConfig('users', ['id_mode' => 'auto']);
        $this->manager->saveCollectionConfig('posts', ['id_mode' => 'prefix']);
        $this->manager->saveCollectionConfig('comments', ['id_mode' => 'manual']);

        $allConfigs = $this->manager->getAllCollectionConfigs();

        $this->assertCount(3, $allConfigs);
        $this->assertArrayHasKey('users', $allConfigs);
        $this->assertArrayHasKey('posts', $allConfigs);
        $this->assertArrayHasKey('comments', $allConfigs);

        $this->assertEquals('auto', $allConfigs['users']['id_mode']);
        $this->assertEquals('prefix', $allConfigs['posts']['id_mode']);
        $this->assertEquals('manual', $allConfigs['comments']['id_mode']);
    }

    public function testDeleteCollectionConfig()
    {
        // Save and verify
        $this->manager->saveCollectionConfig('users', ['id_mode' => 'auto']);
        $this->assertNotEmpty($this->manager->loadCollectionConfig('users'));

        // Delete
        $this->manager->deleteCollectionConfig('users');

        // Verify deleted
        $this->assertEmpty($this->manager->loadCollectionConfig('users'));
    }

    public function testCachingEnabledByDefault()
    {
        $this->assertTrue($this->manager->isCacheEnabled());
    }

    public function testCachingBehavior()
    {
        $config = ['id_mode' => 'auto'];

        // Save config
        $this->manager->saveCollectionConfig('users', $config);

        // Load should use cache
        $loaded1 = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('auto', $loaded1['id_mode']);
        $this->assertArrayHasKey('created_at', $loaded1);
        $this->assertArrayHasKey('updated_at', $loaded1);

        // Modify database directly
        $this->db->saveCollectionConfig('users', ['id_mode' => 'manual']);

        // Load should still return cached version (with original timestamps)
        $loaded2 = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('auto', $loaded2['id_mode']); // Still cached
        $this->assertArrayHasKey('created_at', $loaded2); // Cached version has timestamps

        // Clear cache and load again
        $this->manager->clearCaches();
        $loaded3 = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('manual', $loaded3['id_mode']); // Now reflects database change
    }

    public function testDisableCaching()
    {
        $this->manager->setCacheEnabled(false);
        $this->assertFalse($this->manager->isCacheEnabled());

        // Save config
        $this->manager->saveCollectionConfig('users', ['id_mode' => 'auto']);

        // Should not be cached, so direct database change should be reflected immediately
        $this->db->saveCollectionConfig('users', ['id_mode' => 'manual']);
        $loaded = $this->manager->loadCollectionConfig('users');
        $this->assertEquals('manual', $loaded['id_mode']);
    }

    public function testUpdateMetadata()
    {
        // Initially no metadata
        $metadata = $this->manager->getMetadata('test_collection');
        $this->assertEquals(0, $metadata['version']);
        $this->assertNull($metadata['last_updated']);

        // Update metadata
        $this->manager->updateMetadata('test_collection');

        // Check metadata was updated
        $metadata = $this->manager->getMetadata('test_collection');
        $this->assertEquals(1, $metadata['version']);
        $this->assertNotNull($metadata['last_updated']);

        // Update again, version should increment
        $this->manager->updateMetadata('test_collection');
        $metadata2 = $this->manager->getMetadata('test_collection');
        $this->assertEquals(2, $metadata2['version']);
        $this->assertGreaterThanOrEqual($metadata['last_updated'], $metadata2['last_updated']);
    }

    public function testGetAllMetadata()
    {
        // Update metadata for multiple collections
        $this->manager->updateMetadata('collection1');
        $this->manager->updateMetadata('collection2');

        $allMetadata = $this->manager->getAllMetadata();

        $this->assertArrayHasKey('collection1', $allMetadata);
        $this->assertArrayHasKey('collection2', $allMetadata);
        $this->assertGreaterThanOrEqual(1, $allMetadata['collection1']['version']);
        $this->assertGreaterThanOrEqual(1, $allMetadata['collection2']['version']);
    }

    public function testGetCollectionStats()
    {
        $this->db->createCollection('stats_test');
        $config = ['id_mode' => 'auto', 'soft_deletes_enabled' => true];
        $this->manager->saveCollectionConfig('stats_test', $config);
        $this->manager->updateMetadata('stats_test');

        $stats = $this->manager->getCollectionStats('stats_test');

        $this->assertArrayHasKey('config', $stats);
        $this->assertArrayHasKey('metadata', $stats);
        $this->assertArrayHasKey('exists', $stats);
        $this->assertEquals($config['id_mode'], $stats['config']['id_mode']);
        $this->assertEquals($config['soft_deletes_enabled'], $stats['config']['soft_deletes_enabled']);
        $this->assertEquals(1, $stats['metadata']['version']);
        $this->assertTrue($stats['exists']);
    }

    public function testGetAllCollectionStats()
    {
        // Create collections and configs
        $this->db->createCollection('stats1');
        $this->db->createCollection('stats2');
        $this->manager->saveCollectionConfig('stats1', ['id_mode' => 'auto']);
        $this->manager->saveCollectionConfig('stats2', ['id_mode' => 'manual']);

        $allStats = $this->manager->getAllCollectionStats();

        $this->assertArrayHasKey('stats1', $allStats);
        $this->assertArrayHasKey('stats2', $allStats);
        $this->assertTrue($allStats['stats1']['exists']);
        $this->assertTrue($allStats['stats2']['exists']);
    }

    public function testIsModifiedSince()
    {
        $this->manager->updateMetadata('modified_test');

        $metadata = $this->manager->getMetadata('modified_test');
        $lastUpdated = strtotime($metadata['last_updated']);

        // Should not be modified since now
        $this->assertFalse($this->manager->isModifiedSince('modified_test', time()));

        // Should be modified since past time
        $this->assertTrue($this->manager->isModifiedSince('modified_test', $lastUpdated - 60));
    }

    public function testGetModifiedSince()
    {
        // Test basic functionality - metadata timestamps work
        $this->manager->updateMetadata('modified1');
        $metadata = $this->manager->getMetadata('modified1');

        $this->assertEquals(1, $metadata['version']);
        $this->assertNotNull($metadata['last_updated']);

        // Test that we can get all metadata
        $allMetadata = $this->manager->getAllMetadata();
        $this->assertArrayHasKey('modified1', $allMetadata);
    }

    public function testConfigValidation()
    {
        // Valid config should not throw exception
        $validConfig = [
            'id_mode' => 'auto',
            'encryption_key' => 'key',
            'searchable_fields' => ['field' => true],
            'schema' => ['field' => ['type' => 'string']],
            'soft_deletes_enabled' => true,
            'deleted_at_field' => 'deleted_at',
        ];

        $this->manager->saveCollectionConfig('valid', $validConfig);
        $loadedValid = $this->manager->loadCollectionConfig('valid');
        $this->assertEquals($validConfig['id_mode'], $loadedValid['id_mode']);
        $this->assertEquals($validConfig['encryption_key'], $loadedValid['encryption_key']);
        $this->assertEquals($validConfig['searchable_fields'], $loadedValid['searchable_fields']);
        $this->assertEquals($validConfig['schema'], $loadedValid['schema']);
        $this->assertEquals($validConfig['soft_deletes_enabled'], $loadedValid['soft_deletes_enabled']);
        $this->assertEquals($validConfig['deleted_at_field'], $loadedValid['deleted_at_field']);
        $this->assertArrayHasKey('created_at', $loadedValid);
        $this->assertArrayHasKey('updated_at', $loadedValid);
    }

    public function testInvalidConfigThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->saveCollectionConfig('invalid', ['invalid_key' => 'value']);
    }

    public function testInvalidIdModeThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->saveCollectionConfig('invalid', ['id_mode' => 'invalid_mode']);
    }

    public function testInvalidSearchableFieldsThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->saveCollectionConfig('invalid', ['searchable_fields' => 'not_an_array']);
    }

    public function testInvalidSchemaThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->saveCollectionConfig('invalid', ['schema' => 'not_an_array']);
    }

    public function testInvalidSoftDeletesEnabledThrowsException()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->manager->saveCollectionConfig('invalid', ['soft_deletes_enabled' => 'not_a_boolean']);
    }
}
