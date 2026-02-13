<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class DynamicConfigTest extends TestCase
{
    private Database $db;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testSaveAndLoadCollectionConfig()
    {
        $config = [
            'id_mode' => 'prefix',
            // Note: encryption_key is NOT stored in config - only encryption_enabled boolean
            // Encryption key comes from external source (.env, vault)
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
        $this->db->saveCollectionConfig('users', $config);

        // Load config
        $loadedConfig = $this->db->loadCollectionConfig('users');

        // Verify config
        $this->assertEquals('prefix', $loadedConfig['id_mode']);
        // Config should NOT contain encryption_key - only encryption_enabled boolean
        $this->assertArrayNotHasKey('encryption_key', $loadedConfig);
        // Since we didn't set an encryption key, encryption_enabled should be false
        $this->assertFalse($loadedConfig['encryption_enabled']);
        $this->assertEquals(['email' => true, 'phone' => false], $loadedConfig['searchable_fields']);
        $this->assertEquals($config['schema'], $loadedConfig['schema']);
        $this->assertTrue($loadedConfig['soft_deletes_enabled']);
        $this->assertEquals('deleted_at', $loadedConfig['deleted_at_field']);
        $this->assertArrayHasKey('created_at', $loadedConfig);
        $this->assertArrayHasKey('updated_at', $loadedConfig);
    }

    public function testLoadNonExistentConfig()
    {
        $config = $this->db->loadCollectionConfig('nonexistent');
        $this->assertEmpty($config);
    }

    public function testUpdateCollectionConfig()
    {
        // Initial config
        $this->db->saveCollectionConfig('users', [
            'id_mode' => 'auto',
            'encryption_key' => 'old-key',
        ]);

        $loaded = $this->db->loadCollectionConfig('users');
        $this->assertEquals('auto', $loaded['id_mode']);
        $oldUpdatedAt = $loaded['updated_at'];

        // Small delay to ensure different timestamp
        usleep(1000);

        // Update config
        $this->db->saveCollectionConfig('users', [
            'id_mode' => 'manual',
            // Note: encryption_key is NOT stored in config - only encryption_enabled boolean
            // The key itself comes from external source (.env, vault)
        ]);

        $updated = $this->db->loadCollectionConfig('users');
        $this->assertEquals('manual', $updated['id_mode']);
        // Config should NOT contain encryption_key - only the enabled status
        $this->assertArrayNotHasKey('encryption_key', $updated);
        $this->assertGreaterThanOrEqual($oldUpdatedAt, $updated['updated_at']);
    }

    public function testGetAllCollectionConfigs()
    {
        // Save multiple configs
        $this->db->saveCollectionConfig('users', ['id_mode' => 'auto']);
        $this->db->saveCollectionConfig('posts', ['id_mode' => 'prefix']);
        $this->db->saveCollectionConfig('comments', ['id_mode' => 'manual']);

        $allConfigs = $this->db->getAllCollectionConfigs();

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
        $this->db->saveCollectionConfig('users', ['id_mode' => 'auto']);
        $this->assertNotEmpty($this->db->loadCollectionConfig('users'));

        // Delete
        $this->db->deleteCollectionConfig('users');

        // Verify deleted
        $this->assertEmpty($this->db->loadCollectionConfig('users'));
    }

    public function testCollectionAutoLoadsConfiguration()
    {
        // Pre-save configuration
        $this->db->saveCollectionConfig('users', [
            'id_mode' => 'prefix',
            'encryption_key' => 'auto-loaded-key',
            'schema' => [
                'name' => ['required' => true, 'type' => 'string'],
            ],
            'soft_deletes_enabled' => true,
        ]);

        // Create collection (should auto-load config)
        $users = $this->db->selectCollection('users');

        // Verify configuration was loaded (by testing behavior)
        $this->assertTrue($users->softDeletesEnabled());

        // Test that schema validation works
        $this->expectException(\Exception::class);
        $users->insert(['email' => 'test@example.com']); // Missing required 'name'
    }

    public function testCollectionSaveConfiguration()
    {
        $users = $this->db->selectCollection('users');

        // Configure collection
        $users->setIdModePrefix('USR');
        $users->setEncryptionKey('runtime-key-with-32-chars-minimum-sec');
        $users->setSchema(['name' => ['required' => true]]);
        $users->useSoftDeletes(true);

        // Save configuration
        $users->saveConfiguration();

        // Load and verify
        $config = $this->db->loadCollectionConfig('users');
        $this->assertEquals('USR', $config['id_mode']);
        // Config should NOT contain encryption_key - only encryption_enabled boolean
        $this->assertTrue($config['encryption_enabled']);
        $this->assertTrue($config['soft_deletes_enabled']);
        $this->assertEquals(['name' => ['required' => true]], $config['schema']);
    }

    public function testCollectionWithoutConfigUsesDefaults()
    {
        // Collection without saved config should work normally
        $posts = $this->db->selectCollection('posts');

        // Should use defaults
        $this->assertFalse($posts->softDeletesEnabled());
        $id = $posts->insert(['title' => 'Test Post']);
        $this->assertIsString($id);

        // Should be UUID v4 (auto mode default)
        $this->assertMatchesRegularExpression('/^[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/', $id);
    }

    public function testConfigWithComplexSchema()
    {
        $complexConfig = [
            'id_mode' => 'auto',
            'schema' => [
                'user' => [
                    'type' => 'object',
                    'required' => true,
                ],
                'tags' => [
                    'type' => 'array',
                    'max' => 10,
                ],
                'metadata' => [
                    'type' => 'object',
                ],
                'priority' => [
                    'enum' => ['low', 'medium', 'high'],
                    'required' => true,
                ],
            ],
            'searchable_fields' => [
                'user.name' => true,
                'priority' => false,
            ],
            'soft_deletes_enabled' => true,
        ];

        $this->db->saveCollectionConfig('complex', $complexConfig);
        $loaded = $this->db->loadCollectionConfig('complex');

        $this->assertEquals($complexConfig['schema'], $loaded['schema']);
        $this->assertEquals($complexConfig['searchable_fields'], $loaded['searchable_fields']);
        $this->assertTrue($loaded['soft_deletes_enabled']);
    }
}
