<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use BangronDB\Client;
use PHPUnit\Framework\TestCase;

/**
 * Regression tests for the keyed HMAC "blind index" used for searchable hashed
 * fields on encrypted collections (replaces unkeyed SHA-256).
 */
class BlindIndexTest extends TestCase
{
    private string $dir;
    private Client $client;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/bangron_blind_' . bin2hex(random_bytes(4));
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

    public function testEncryptedSearchableHashIsKeyedNotPlainSha256(): void
    {
        $db = $this->client->createDB('blind1');
        $col = $db->createCollection('users');
        $col->setEncryptionKey('this-is-a-32-char-random-keymatl!!');
        $col->setSearchableFields(['email' => ['hash' => true]]);

        $col->insert(['_id' => 'u1', 'email' => 'JOHN@example.com', 'name' => 'John']);

        $pdo = $db->connection;
        $stored = $pdo->query('SELECT si_email FROM users LIMIT 1')->fetch(\PDO::FETCH_ASSOC)['si_email'];

        // Must NOT equal the legacy unkeyed SHA-256 of the normalized email.
        $this->assertNotSame(hash('sha256', 'john@example.com'), $stored);
        // Still a 64-char hex digest (HMAC-SHA256 output).
        $this->assertSame(64, strlen($stored));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $stored);
    }

    public function testBlindIndexSearchStillFindsDocument(): void
    {
        $db = $this->client->createDB('blind2');
        $col = $db->createCollection('users');
        $col->setEncryptionKey('this-is-a-32-char-random-keymatl!!');
        $col->setSearchableFields(['email' => ['hash' => true]]);

        $col->insert(['_id' => 'u1', 'email' => 'jane@example.com', 'name' => 'Jane']);

        // Equality, case-insensitive, must resolve through the same HMAC.
        $found = iterator_to_array($col->find(['email' => 'JANE@example.com']));
        $this->assertCount(1, $found);
        $this->assertSame('Jane', $found[0]['name']);

        // $in operator path uses the same hashing.
        $foundIn = iterator_to_array($col->find(['email' => ['$in' => ['jane@example.com']]]));
        $this->assertCount(1, $foundIn);
    }

    public function testDifferentKeysProduceDifferentIndexes(): void
    {
        $dbA = $this->client->createDB('blindA');
        $colA = $dbA->createCollection('users');
        $colA->setEncryptionKey('Ka1-Zx9Qw3Lm7Tp2Vk8Rn4Bc6Hd1Fg5J');
        $colA->setSearchableFields(['email' => ['hash' => true]]);
        $colA->insert(['_id' => 'a', 'email' => 'same@example.com']);

        $dbB = $this->client->createDB('blindB');
        $colB = $dbB->createCollection('users');
        $colB->setEncryptionKey('Kb2-Yw8Pn3Lm7Tq2Vj9Rc4Bd6He1Gf5K');
        $colB->setSearchableFields(['email' => ['hash' => true]]);
        $colB->insert(['_id' => 'b', 'email' => 'same@example.com']);

        $hashA = $dbA->connection->query('SELECT si_email FROM users LIMIT 1')->fetch(\PDO::FETCH_ASSOC)['si_email'];
        $hashB = $dbB->connection->query('SELECT si_email FROM users LIMIT 1')->fetch(\PDO::FETCH_ASSOC)['si_email'];

        // Same plaintext, different keys → different index (no cross-DB correlation).
        $this->assertNotSame($hashA, $hashB);
    }

    public function testNonEncryptedCollectionKeepsPlainHash(): void
    {
        $db = $this->client->createDB('blind3');
        $col = $db->createCollection('tags');
        // No encryption key set.
        $col->setSearchableFields(['slug' => ['hash' => true]]);

        $col->insert(['_id' => 't1', 'slug' => 'Hello']);

        $stored = $db->connection->query('SELECT si_slug FROM tags LIMIT 1')->fetch(\PDO::FETCH_ASSOC)['si_slug'];
        // Backward-compatible: legacy plain SHA-256 when there is no secret.
        $this->assertSame(hash('sha256', 'hello'), $stored);
    }
}
