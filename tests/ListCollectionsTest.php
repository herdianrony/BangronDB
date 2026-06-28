<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use BangronDB\Client;
use PHPUnit\Framework\TestCase;

/**
 * Tests for Client::listCollections() / listCollection().
 */
class ListCollectionsTest extends TestCase
{
    private string $dir;
    private Client $client;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bangron_listcol_' . bin2hex(random_bytes(4));
        mkdir($this->dir);
        $this->client = new Client($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir . '/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
    }

    public function testListCollectionsReturnsCreatedCollections(): void
    {
        $this->client->createCollection('shop', 'products');
        $this->client->createCollection('shop', 'orders');

        $names = $this->client->listCollections('shop');

        sort($names);
        $this->assertSame(['orders', 'products'], $names);
    }

    public function testListCollectionAliasMatches(): void
    {
        $this->client->createCollection('shop', 'products');

        $this->assertSame(
            $this->client->listCollections('shop'),
            $this->client->listCollection('shop')
        );
    }

    public function testListCollectionsEmptyForMissingDatabase(): void
    {
        $this->assertSame([], $this->client->listCollections('does_not_exist'));
    }

    public function testListCollectionsEmptyForFreshDatabase(): void
    {
        $this->client->createDB('empty_db');

        $this->assertSame([], $this->client->listCollections('empty_db'));
    }
}
