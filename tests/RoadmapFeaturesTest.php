<?php

declare(strict_types=1);

namespace Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\Client;
use BangronDB\Config;

/**
 * Tests for Prioritas 3 roadmap features:
 * - Bulk Operations (insertMany, updateMany, deleteMany)
 * - Aggregation Pipeline
 * - Explain Query
 * - Cursor Streaming (stream)
 * - TTL Document
 */
class RoadmapFeaturesTest extends TestCase
{
    private Client $client;

    protected function setUp(): void
    {
        Config::reset();
        $this->client = new Client(['path' => ':memory:']);
    }

    protected function tearDown(): void
    {
        // Clean up static state
        Config::reset();
    }

    /* ================= BULK OPERATIONS ================= */

    public function testInsertManyReturnsInsertedIds(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('users');

        $result = $collection->insertMany([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
            ['name' => 'Charlie', 'age' => 35],
        ]);

        $this->assertEquals(3, $result['inserted_count']);
        $this->assertCount(3, $result['inserted_ids']);
        $this->assertEquals(3, $collection->count());
    }

    public function testInsertManyRollsBackOnHookRejection(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        // Register a beforeInsert hook that rejects the second document
        $rejectIndex = 1;
        $collection->addHook('beforeInsert', function ($doc) use (&$rejectIndex) {
            if (isset($doc['fail']) && $rejectIndex-- <= 0) {
                return false; // reject
            }
            return $doc;
        });

        // First insert succeeds, second is rejected by hook
        $result = $collection->insertMany([
            ['v' => 1],
            ['fail' => true, 'v' => 2],
            ['v' => 3],
        ]);

        // Only the first insert succeeded before the hook rejected the second
        $this->assertEquals(1, $result['inserted_count']);
    }

    public function testInsertManyThrowsOnEmptyArray(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty array');

        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insertMany([]);
    }

    public function testInsertManyThrowsOnNonArrayItems(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('not an array');

        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insertMany(['valid' => ['a' => 1], 'invalid' => 'not-array']);
    }

    public function testUpdateManyReturnsMatchedAndModifiedCounts(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insertMany([
            ['status' => 'pending', 'value' => 1],
            ['status' => 'pending', 'value' => 2],
            ['status' => 'done', 'value' => 3],
        ]);

        $result = $collection->updateMany(
            ['status' => 'pending'],
            ['$set' => ['status' => 'processed']]
        );

        $this->assertEquals(2, $result['matched_count']);
        $this->assertEquals(2, $result['modified_count']);
    }

    public function testDeleteManyReturnsDeletedCount(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insertMany([
            ['type' => 'temp', 'v' => 1],
            ['type' => 'temp', 'v' => 2],
            ['type' => 'keep', 'v' => 3],
        ]);

        $result = $collection->deleteMany(['type' => 'temp']);

        $this->assertEquals(2, $result['deleted_count']);
        $this->assertEquals(1, $collection->count());
    }

    /* ================= AGGREGATION PIPELINE ================= */

    private function seedSalesData(): \BangronDB\Collection
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('sales');

        $collection->insertMany([
            ['category' => 'A', 'amount' => 100, 'status' => 'completed'],
            ['category' => 'B', 'amount' => 200, 'status' => 'completed'],
            ['category' => 'A', 'amount' => 150, 'status' => 'pending'],
            ['category' => 'B', 'amount' => 300, 'status' => 'completed'],
            ['category' => 'A', 'amount' => 50, 'status' => 'cancelled'],
        ]);

        return $collection;
    }

    public function testAggregateMatch(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$match' => ['status' => 'completed']],
        ]);

        $this->assertCount(3, $results);
        foreach ($results as $doc) {
            $this->assertEquals('completed', $doc['status']);
        }
    }

    public function testAggregateGroupWithSum(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$group' => [
                '_id' => '$category',
                'total' => ['$sum' => '$amount'],
            ]],
        ]);

        $this->assertCount(2, $results);

        // Find category A result
        $catA = array_filter($results, fn($r) => $r['_id'] === 'A');
        $catA = reset($catA);
        $this->assertEquals(300, $catA['total']); // 100 + 150 + 50

        // Find category B result
        $catB = array_filter($results, fn($r) => $r['_id'] === 'B');
        $catB = reset($catB);
        $this->assertEquals(500, $catB['total']); // 200 + 300
    }

    public function testAggregateGroupWithCount(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$group' => [
                '_id' => '$category',
                'count' => ['$count' => null],
            ]],
        ]);

        $catA = array_filter($results, fn($r) => $r['_id'] === 'A');
        $catA = reset($catA);
        $this->assertEquals(3, $catA['count']);
    }

    public function testAggregateGroupWithAvg(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$group' => [
                '_id' => '$category',
                'avg_amount' => ['$avg' => '$amount'],
            ]],
        ]);

        // Category A: (100 + 150 + 50) / 3 = 100
        $catA = array_filter($results, fn($r) => $r['_id'] === 'A');
        $catA = reset($catA);
        $this->assertEquals(100.0, $catA['avg_amount']);

        // Category B: (200 + 300) / 2 = 250
        $catB = array_filter($results, fn($r) => $r['_id'] === 'B');
        $catB = reset($catB);
        $this->assertEquals(250.0, $catB['avg_amount']);
    }

    public function testAggregateGroupWithMinMax(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$group' => [
                '_id' => null,
                'max_amount' => ['$max' => '$amount'],
                'min_amount' => ['$min' => '$amount'],
            ]],
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals(300, $results[0]['max_amount']);
        $this->assertEquals(50, $results[0]['min_amount']);
    }

    public function testAggregateSort(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$sort' => ['amount' => -1]],
        ]);

        $this->assertTrue($results[0]['amount'] >= $results[1]['amount']);
        $this->assertTrue($results[1]['amount'] >= $results[2]['amount']);
    }

    public function testAggregateLimitAndSkip(): void
    {
        $collection = $this->seedSalesData();

        $allResults = $collection->aggregate([
            ['$sort' => ['amount' => 1]],
        ]);

        $limited = $collection->aggregate([
            ['$sort' => ['amount' => 1]],
            ['$skip' => 1],
            ['$limit' => 2],
        ]);

        $this->assertEquals(5, count($allResults));
        $this->assertCount(2, $limited);
        $this->assertEquals($allResults[1]['amount'], $limited[0]['amount']);
    }

    public function testAggregateProject(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$project' => ['category' => 1, 'amount' => 1]],
        ]);

        foreach ($results as $doc) {
            $this->assertArrayHasKey('category', $doc);
            $this->assertArrayHasKey('amount', $doc);
            $this->assertArrayNotHasKey('status', $doc);
            $this->assertArrayHasKey('_id', $doc); // _id always included
        }
    }

    public function testAggregateProjectRename(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$project' => ['cat' => '$category', 'amt' => '$amount']],
            ['$limit' => 1],
        ]);

        $this->assertArrayHasKey('cat', $results[0]);
        $this->assertArrayHasKey('amt', $results[0]);
        $this->assertArrayNotHasKey('category', $results[0]);
    }

    public function testAggregateCount(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$count' => 'completed_count'],
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals(3, $results[0]['completed_count']);
    }

    public function testAggregateUnset(): void
    {
        $collection = $this->seedSalesData();

        $results = $collection->aggregate([
            ['$unset' => ['status']],
            ['$limit' => 1],
        ]);

        $this->assertArrayNotHasKey('status', $results[0]);
        $this->assertArrayHasKey('category', $results[0]);
    }

    public function testAggregateComplexPipeline(): void
    {
        $collection = $this->seedSalesData();

        // Match completed sales, group by category, sort by total descending, take top 1
        $results = $collection->aggregate([
            ['$match' => ['status' => 'completed']],
            ['$group' => [
                '_id' => '$category',
                'total' => ['$sum' => '$amount'],
                'count' => ['$count' => null],
            ]],
            ['$sort' => ['total' => -1]],
            ['$limit' => 1],
        ]);

        $this->assertCount(1, $results);
        $this->assertEquals('B', $results[0]['_id']);
        $this->assertEquals(500, $results[0]['total']);
        $this->assertEquals(2, $results[0]['count']);
    }

    public function testAggregateThrowsOnEmptyPipeline(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('non-empty pipeline');

        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->aggregate([]);
    }

    public function testAggregateThrowsOnUnknownOperator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unknown pipeline operator');

        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insert(['a' => 1]);
        $collection->aggregate([['$unknown' => []]]);
    }

    /* ================= EXPLAIN QUERY ================= */

    public function testExplainReturnsQueryPlan(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('users');

        $collection->insertMany([
            ['name' => 'Alice', 'age' => 30],
            ['name' => 'Bob', 'age' => 25],
        ]);

        $explanation = $collection->explain(['age' => ['$gte' => 25]]);

        $this->assertArrayHasKey('query_plan', $explanation);
        $this->assertArrayHasKey('performance', $explanation);
        $this->assertArrayHasKey('suggestions', $explanation);
        $this->assertArrayHasKey('strategy', $explanation['query_plan']);
        $this->assertArrayHasKey('uses_index', $explanation['query_plan']);
        $this->assertArrayHasKey('documents_matched', $explanation['performance']);
        $this->assertArrayHasKey('execution_time_ms', $explanation['performance']);
    }

    public function testExplainNoFilter(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insert(['a' => 1]);

        $explanation = $collection->explain();

        $this->assertArrayHasKey('query_plan', $explanation);
        $this->assertStringContainsString('no filter', $explanation['performance']['criteria_summary']);
    }

    /* ================= CURSOR STREAMING ================= */

    public function testStreamYieldsDocuments(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insertMany([
            ['v' => 1],
            ['v' => 2],
            ['v' => 3],
        ]);

        $results = [];
        foreach ($collection->stream() as $doc) {
            $results[] = $doc;
        }

        $this->assertCount(3, $results);
    }

    public function testStreamWithCriteria(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insertMany([
            ['type' => 'a', 'v' => 1],
            ['type' => 'b', 'v' => 2],
            ['type' => 'a', 'v' => 3],
        ]);

        $results = [];
        foreach ($collection->stream(['type' => 'a']) as $doc) {
            $results[] = $doc;
        }

        $this->assertCount(2, $results);
        foreach ($results as $doc) {
            $this->assertEquals('a', $doc['type']);
        }
    }

    public function testStreamWithSortAndLimit(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insertMany([
            ['v' => 3],
            ['v' => 1],
            ['v' => 2],
        ]);

        $results = [];
        foreach ($collection->stream([], ['sort' => ['v' => 1], 'limit' => 2]) as $doc) {
            $results[] = $doc;
        }

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]['v']);
        $this->assertEquals(2, $results[1]['v']);
    }

    public function testStreamWithProjection(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('items');

        $collection->insert(['name' => 'Alice', 'password' => 'secret']);

        $results = [];
        foreach ($collection->stream([], ['projection' => ['password' => 0]]) as $doc) {
            $results[] = $doc;
        }

        $this->assertCount(1, $results);
        $this->assertArrayNotHasKey('password', $results[0]);
        $this->assertEquals('Alice', $results[0]['name']);
    }

    public function testStreamReturnsGenerator(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insert(['a' => 1]);

        $generator = $collection->stream();

        $this->assertInstanceOf(\Generator::class, $generator);
    }

    /* ================= TTL DOCUMENT ================= */

    public function testTtlEnableAndDisable(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('sessions');

        $this->assertFalse($collection->isTtlEnabled());

        $collection->enableTtl('expires_at');
        $this->assertTrue($collection->isTtlEnabled());
        $this->assertEquals('expires_at', $collection->getTtlField());

        $collection->disableTtl();
        $this->assertFalse($collection->isTtlEnabled());
    }

    public function testTtlDefaultExpiryOnInsert(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('otp');
        $collection->enableTtl('expires_at', 3600);

        $collection->insert(['code' => '123456']);

        $doc = $collection->findOne(['code' => '123456']);
        $this->assertNotNull($doc);
        $this->assertArrayHasKey('expires_at', $doc);
        $this->assertGreaterThan(time(), $doc['expires_at']);
        $this->assertLessThanOrEqual(time() + 3601, $doc['expires_at']);
    }

    public function testTtlDoesNotOverrideExplicitExpiry(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('otp');
        $collection->enableTtl('expires_at', 3600);

        $explicitExpiry = time() + 7200;
        $collection->insert(['code' => '654321', 'expires_at' => $explicitExpiry]);

        $doc = $collection->findOne(['code' => '654321']);
        $this->assertEquals($explicitExpiry, $doc['expires_at']);
    }

    public function testTtlCleanExpired(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('sessions');
        $collection->enableTtl('expires_at');

        // Insert one expired and one active document
        $collection->insertMany([
            ['session_id' => 'expired', 'expires_at' => time() - 100],
            ['session_id' => 'active', 'expires_at' => time() + 3600],
        ]);

        $this->assertEquals(2, $collection->count());

        $removed = $collection->cleanExpired();

        $this->assertEquals(1, $removed);
        $this->assertEquals(1, $collection->count());
        $this->assertNull($collection->findOne(['session_id' => 'expired']));
        $this->assertNotNull($collection->findOne(['session_id' => 'active']));
    }

    public function testTtlExpiredCount(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('cache');
        $collection->enableTtl('expires_at');

        $collection->insertMany([
            ['key' => 'expired1', 'expires_at' => time() - 100],
            ['key' => 'expired2', 'expires_at' => time() - 200],
            ['key' => 'active', 'expires_at' => time() + 3600],
        ]);

        $this->assertEquals(2, $collection->expiredCount());
    }

    public function testTtlStats(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('tokens');
        $collection->enableTtl('expires_at', 600);

        $collection->insertMany([
            ['token' => 't1', 'expires_at' => time() - 100],
            ['token' => 't2', 'expires_at' => time() + 600],
        ]);

        $stats = $collection->ttlStats();

        $this->assertTrue($stats['ttl_enabled']);
        $this->assertEquals('expires_at', $stats['ttl_field']);
        $this->assertEquals(600, $stats['default_ttl_seconds']);
        $this->assertEquals(2, $stats['documents_with_ttl']);
        $this->assertEquals(1, $stats['expired_count']);
        $this->assertEquals(1, $stats['active_count']);
        $this->assertNotNull($stats['next_expires_at']);
        $this->assertGreaterThanOrEqual(0, $stats['next_expires_in_seconds']);
    }

    public function testTtlStatsWhenDisabled(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');

        $stats = $collection->ttlStats();

        $this->assertFalse($stats['ttl_enabled']);
        $this->assertArrayHasKey('message', $stats);
    }

    public function testTtlCleanExpiredReturnsZeroWhenDisabled(): void
    {
        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->insert(['a' => 1]);

        $this->assertEquals(0, $collection->cleanExpired());
    }

    public function testTtlInvalidDefaultTtl(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $db = $this->client->selectDB('test');
        $collection = $db->selectCollection('test');
        $collection->enableTtl('expires_at', -1);
    }
}