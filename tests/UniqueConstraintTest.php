<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use BangronDB\Exceptions\ValidationException;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the `unique` schema constraint.
 */
class UniqueConstraintTest extends TestCase
{
    private Database $db;
    private Collection $users;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->users = $this->db->createCollection('users');
        $this->users->setSchema([
            'email' => ['type' => 'string', 'required' => true, 'unique' => true],
            'name'  => ['type' => 'string'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testInsertDuplicateThrows(): void
    {
        $this->users->insert(['email' => 'a@example.com', 'name' => 'A']);

        $this->expectException(ValidationException::class);
        $this->users->insert(['email' => 'a@example.com', 'name' => 'B']);
    }

    public function testInsertDistinctValuesSucceeds(): void
    {
        $this->users->insert(['email' => 'a@example.com', 'name' => 'A']);
        $this->users->insert(['email' => 'b@example.com', 'name' => 'B']);

        $this->assertSame(2, $this->users->count());
    }

    public function testBatchInsertWithDuplicateRollsBack(): void
    {
        try {
            $this->users->insert([
                ['email' => 'x@example.com', 'name' => 'X'],
                ['email' => 'x@example.com', 'name' => 'X2'], // duplicate within batch
            ]);
            $this->fail('Expected ValidationException for duplicate in batch');
        } catch (ValidationException $e) {
            // The transaction must have rolled back: no rows committed.
            $this->assertSame(0, $this->users->count());
        }
    }

    public function testUpdateToExistingValueThrows(): void
    {
        $this->users->insert(['_id' => 'u1', 'email' => 'a@example.com', 'name' => 'A']);
        $this->users->insert(['_id' => 'u2', 'email' => 'b@example.com', 'name' => 'B']);

        $this->expectException(ValidationException::class);
        // Try to change u2's email to u1's email.
        $this->users->update(['_id' => 'u2'], ['email' => 'a@example.com']);
    }

    public function testUpdatingSameDocumentKeepingItsValueSucceeds(): void
    {
        $this->users->insert(['_id' => 'u1', 'email' => 'a@example.com', 'name' => 'A']);

        // Updating the same doc (email unchanged) must NOT trip the unique check.
        $changed = $this->users->update(['_id' => 'u1'], ['name' => 'A-renamed']);

        $this->assertSame(1, $changed);
        $this->assertSame('A-renamed', $this->users->findOne(['_id' => 'u1'])['name']);
    }

    public function testNoSchemaMeansNoUniqueEnforcement(): void
    {
        $plain = $this->db->createCollection('plain');
        $plain->insert(['email' => 'dup@example.com']);
        $plain->insert(['email' => 'dup@example.com']);

        $this->assertSame(2, $plain->count());
    }
}
