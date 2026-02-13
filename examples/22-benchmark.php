<?php

/**
 * BangronDB Performance Benchmark Script.
 *
 * Comprehensive benchmark untuk menguji performa BangronDB:
 * - Insert operations (single & bulk)
 * - Find operations (indexed & non-indexed)
 * - Update operations
 * - Delete operations
 * - Searchable fields performance
 * - Cross-database relationships
 * - Indexing impact
 * - Concurrency simulation
 *
 * Usage: php benchmark.php
 */

require_once __DIR__.'/../vendor/autoload.php';

use BangronDB\Client;
use BangronDB\Database;

class BangronDBBenchmark
{
    private Client $client;
    private string $testDbName;
    private array $results = [];
    private int $recordsPerTest;
    private int $iterations;

    public function __construct(string $dataPath = __DIR__.'/data/benchmark', int $recordsPerTest = 1000, int $iterations = 3)
    {
        $this->client = new Client($dataPath);
        $this->testDbName = 'benchmark_'.uniqid();
        $this->recordsPerTest = $recordsPerTest;
        $this->iterations = $iterations;
    }

    /**
     * Print formatted result.
     */
    private function printResult(string $testName, float $timeMs, int $records, int $iterations = 1): array
    {
        $avgTime = $timeMs / $iterations;
        $opsPerSec = $iterations > 0 ? ($records / ($timeMs / 1000)) : 0;
        $perRecordUs = $records > 0 ? ($timeMs * 1000 / $records) : 0;

        $result = [
            'test' => $testName,
            'total_time_ms' => round($timeMs, 3),
            'avg_time_ms' => round($avgTime, 3),
            'records' => $records,
            'ops_per_sec' => round($opsPerSec, 2),
            'us_per_record' => round($perRecordUs, 2),
        ];

        $this->results[$testName] = $result;

        printf(
            "  %-35s | %8.3f ms | %8d ops/s | %8.2f µs/rec\n",
            $testName,
            $avgTime,
            round($opsPerSec),
            $perRecordUs
        );

        return $result;
    }

    /**
     * Clean up test database.
     */
    private function cleanup(): void
    {
        try {
            $db = $this->client->selectDB($this->testDbName);
            $db->drop();
        } catch (Exception $e) {
            // Ignore cleanup errors
        }
        $this->client->close();
    }

    /**
     * Test 1: Single Insert Performance.
     */
    public function benchmarkSingleInsert(): void
    {
        echo "\n=== Test 1: Single Insert Performance ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < $this->recordsPerTest; ++$j) {
                $collection->insert([
                    'name' => "User $j",
                    'email' => "user$j@example.com",
                    'age' => rand(18, 80),
                    'status' => 'active',
                    'created_at' => date('c'),
                ]);
            }
            $totalTime += (microtime(true) - $start);
            $collection->remove([]);
        }

        $this->printResult('Single Insert', $totalTime * 1000, $this->recordsPerTest, $this->iterations);
    }

    /**
     * Test 2: Bulk Insert Performance.
     */
    public function benchmarkBulkInsert(): void
    {
        echo "\n=== Test 2: Bulk Insert Performance ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $batchSizes = [10, 50, 100, 500];

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        foreach ($batchSizes as $batchSize) {
            $totalTime = 0;
            $totalRecords = 0;

            for ($iter = 0; $iter < $this->iterations; ++$iter) {
                $collection->remove([]);

                $start = microtime(true);
                $batches = ceil($this->recordsPerTest / $batchSize);
                for ($b = 0; $b < $batches; ++$b) {
                    $documents = [];
                    $startIdx = $b * $batchSize;
                    $endIdx = min($startIdx + $batchSize, $this->recordsPerTest);
                    for ($j = $startIdx; $j < $endIdx; ++$j) {
                        $documents[] = [
                            'name' => "User $j",
                            'email' => "user$j@example.com",
                            'age' => rand(18, 80),
                            'status' => 'active',
                        ];
                    }
                    $collection->insert($documents);
                }
                $totalTime += (microtime(true) - $start);
                $totalRecords += $this->recordsPerTest;
            }

            $this->printResult("Bulk Insert (batch=$batchSize)", $totalTime * 1000, $totalRecords / $this->iterations, $this->iterations);
        }
    }

    /**
     * Test 3: Find Operations (Indexed vs Non-Indexed).
     */
    public function benchmarkFind(): void
    {
        echo "\n=== Test 3: Find Operations ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Insert test data
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => rand(18, 80),
                'status' => $i % 2 === 0 ? 'active' : 'inactive',
                'category' => 'category_'.($i % 10),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Data Setup', $insertTime);

        // Find without index
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find One (no index)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Create index
        $collection->createIndex('email');
        $collection->createIndex('status');
        $collection->createIndex('category');

        // Find with index
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find One (with index)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Find multiple with index (limited)
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['status' => 'active'])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find Multiple (indexed)', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // Count with index
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->count(['status' => 'active']);
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Count (indexed)', $totalTime * 1000, $this->iterations, $this->iterations);
    }

    /**
     * Test 4: Update Operations.
     */
    public function benchmarkUpdate(): void
    {
        echo "\n=== Test 4: Update Operations ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Setup: insert data with index
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => rand(18, 80),
                'views' => rand(0, 1000),
            ]);
        }
        $collection->createIndex('email');

        // Single update
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->update(
                    ['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com'],
                    ['$set' => ['updated_at' => date('c')]]
                );
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Update Single (indexed)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Bulk update
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->update(
                ['views' => ['$lt' => 500]],
                ['$inc' => ['views' => 1]]
            );
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Update Bulk (condition)', $totalTime * 1000, $this->iterations, $this->iterations);
    }

    /**
     * Test 5: Delete Operations.
     */
    public function benchmarkDelete(): void
    {
        echo "\n=== Test 5: Delete Operations ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Setup: insert data
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'status' => 'active',
            ]);
        }
        $collection->createIndex('email');

        // Single delete
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $collection->remove([]);
            for ($j = 0; $j < $this->recordsPerTest; ++$j) {
                $collection->insert([
                    'name' => "User $j",
                    'email' => "user$j@example.com",
                ]);
            }
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->remove(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Delete Single (indexed)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Bulk delete
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $collection->remove([]);
            for ($j = 0; $j < $this->recordsPerTest; ++$j) {
                $collection->insert([
                    'name' => "User $j",
                    'email' => "user$j@example.com",
                    'status' => 'temporary',
                ]);
            }
            $start = microtime(true);
            $collection->remove(['status' => 'temporary']);
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Delete Bulk (condition)', $totalTime * 1000, $this->iterations, $this->iterations);
    }

    /**
     * Test 6: Searchable Fields Performance.
     */
    public function benchmarkSearchableFields(): void
    {
        echo "\n=== Test 6: Searchable Fields Performance ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Insert test data
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'phone' => '555-'.str_pad($i, 6, '0', STR_PAD_LEFT),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Data Setup', $insertTime);

        // Find without searchable fields (json_extract)
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (no searchable)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Setup searchable fields
        $collection->setSearchableFields(['email', 'phone'], false);
        $collection->createIndex('si_email');
        $collection->createIndex('si_phone');

        // Find with searchable fields
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (with searchable)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Find with hashed searchable fields
        $collection->setSearchableFields(['email', 'phone'], true); // Hash mode

        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                // DON'T manual hash here! BangronDB handles hashing automatically based on configuration
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (hashed searchable)', $totalTime * 1000, $this->iterations * 100, $this->iterations);
    }

    /**
     * Test 7: Soft Deletes Performance.
     */
    public function benchmarkSoftDeletes(): void
    {
        echo "\n=== Test 7: Soft Deletes Performance ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;
        $collection->useSoftDeletes(true);

        // Setup: insert data
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Setup (Soft Deletes On)', $insertTime);

        // Soft delete
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 50; ++$j) {
                $collection->remove(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Soft Delete', $totalTime * 1000, $this->iterations * 50, $this->iterations);

        // Find active (auto-filter) - soft delete filters automatically
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['status' => 'active'])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find Active (auto-filter)', $totalTime * 1000, $this->iterations * 500, $this->iterations);
    }

    /**
     * Test 8: Cross-Database Relationships.
     */
    public function benchmarkCrossDatabase(): void
    {
        echo "\n=== Test 8: Cross-Database Relationships ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();

        // Setup multiple databases
        $masterDb = $this->client->selectDB($this->testDbName.'_master');
        $transDb = $this->client->selectDB($this->testDbName.'_trans');

        $users = $masterDb->users;
        $orders = $transDb->orders;
        $orderItems = $transDb->order_items;

        // Insert users
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $users->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Setup Users', $insertTime);

        // Insert orders with FK
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $orders->insert([
                'user_id' => 'user_'.($i % 100),
                'order_number' => "ORD-$i",
                'total' => rand(10, 1000),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Setup Orders', $insertTime);

        // Create indexes
        $users->createIndex('email');
        $orders->createIndex('user_id');

        // Find without populate
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 50; ++$j) {
                $order = $orders->findOne(['order_number' => 'ORD-'.rand(0, $this->recordsPerTest - 1)]);
                if ($order) {
                    $parts = explode('_', $order['user_id']);
                    $userIdx = end($parts);
                    $users->findOne(['email' => "user$userIdx@example.com"]);
                }
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (N+1 query)', $totalTime * 1000, $this->iterations * 50, $this->iterations);

        // Find with populate
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 50; ++$j) {
                $order = $orders->findOne(['order_number' => 'ORD-'.rand(0, $this->recordsPerTest - 1)]);
                if ($order) {
                    $orders->populate($order, 'user_id', $this->testDbName.'_master.users', '_id', 'user');
                }
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find with Populate', $totalTime * 1000, $this->iterations * 50, $this->iterations);

        // Bulk populate
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $ordersList = $orders->find(['order_number' => ['$regex' => 'ORD-']])->limit(100)->toArray();
            foreach ($ordersList as $order) {
                $orders->populate($order, 'user_id', $this->testDbName.'_master.users', '_id', 'user');
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Bulk Populate (100)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        // Cleanup
        @$masterDb->drop();
        @$transDb->drop();
    }

    /**
     * Test 9: Query Operators Performance.
     */
    public function benchmarkQueryOperators(): void
    {
        echo "\n=== Test 9: Query Operators ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Setup data
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'age' => rand(18, 80),
                'score' => rand(0, 100),
                'status' => ['pending', 'active', 'completed'][array_rand(['pending', 'active', 'completed'])],
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Data Setup', $insertTime);

        $collection->createIndex('age');
        $collection->createIndex('score');
        $collection->createIndex('status');

        // Equality query
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['status' => 'active'])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: Equality', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // Greater than
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['age' => ['$gte' => 30]])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: $gte', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // Range query
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['age' => ['$gte' => 18, '$lte' => 25]])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: Range [18-25]', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // IN query
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find(['status' => ['$in' => ['pending', 'completed']]])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: $in', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // AND query
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find([
                '$and' => [
                    ['age' => ['$gte' => 30]],
                    ['status' => 'active'],
                ],
            ])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: $and', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // OR query
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find([
                '$or' => [
                    ['age' => ['$lt' => 20]],
                    ['age' => ['$gt' => 70]],
                ],
            ])->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Query: $or', $totalTime * 1000, $this->iterations * 500, $this->iterations);
    }

    /**
     * Test 10: Pagination & Sorting.
     */
    public function benchmarkPagination(): void
    {
        echo "\n=== Test 10: Pagination & Sorting ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Setup data with indexes
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'created_at' => date('c', strtotime("-{$i} days")),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Data Setup', $insertTime);

        $collection->createIndex('created_at');

        // Simple find
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find()->limit(500)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find All', $totalTime * 1000, $this->iterations * 500, $this->iterations);

        // With limit
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find()->limit(20)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find + Limit(20)', $totalTime * 1000, $this->iterations * 20, $this->iterations);

        // With skip and limit (pagination)
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find()->skip(500)->limit(20)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find + Skip(500) + Limit(20)', $totalTime * 1000, $this->iterations * 20, $this->iterations);

        // Sort descending with limit
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $collection->find()->sort(['created_at' => -1])->limit(20)->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find + Sort(desc) + Limit', $totalTime * 1000, $this->iterations * 20, $this->iterations);

        // Full pagination
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            $page = ($i % 10) + 1;
            $skip = ($page - 1) * 20;
            $collection->find()->skip($skip)->limit(20)->sort(['created_at' => -1])->toArray();
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Full Pagination', $totalTime * 1000, $this->iterations * 20, $this->iterations);
    }

    /**
     * Test 11: Encryption Performance Impact.
     */
    public function benchmarkEncryption(): void
    {
        echo "\n=== Test 11: Encryption Performance ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Without encryption
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'data' => str_repeat('x', 100),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Insert (No Encryption)', $insertTime);

        // Find without encryption
        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (No Encryption)', $totalTime * 1000, $this->iterations * 100, $this->iterations);

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // With encryption (must be 32+ characters)
        $collection->setEncryptionKey('test-encryption-key-secure-32characters!');

        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert([
                'name' => "User $i",
                'email' => "user$i@example.com",
                'ssn' => "123-45-6789-$i",
                'data' => str_repeat('x', 100),
            ]);
        }
        $insertTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Insert (With Encryption)', $insertTime);

        // Find with encryption (must use searchable fields for indexed search)
        $collection->setSearchableFields(['email'], true);

        $totalTime = 0;
        for ($i = 0; $i < $this->iterations; ++$i) {
            $start = microtime(true);
            for ($j = 0; $j < 100; ++$j) {
                // Pass plain value, BangronDB will hash it automatically
                $collection->findOne(['email' => 'user'.rand(0, $this->recordsPerTest - 1).'@example.com']);
            }
            $totalTime += (microtime(true) - $start);
        }
        $this->printResult('Find (Encrypted + Indexed)', $totalTime * 1000, $this->iterations * 100, $this->iterations);
    }

    /**
     * Test 12: Concurrency Simulation.
     */
    public function benchmarkConcurrency(): void
    {
        echo "\n=== Test 12: Concurrency Simulation ===\n";
        printf("  %-35s | %9s | %11s | %12s\n", 'Test Name', 'Avg Time', 'Ops/Sec', 'µs/Record');
        echo str_repeat('-', 75)."\n";

        $this->cleanup();
        $db = $this->client->selectDB($this->testDbName);
        $collection = $db->users;

        // Sequential writes
        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert(['name' => "User $i"]);
        }
        $seqTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Sequential Writes', $seqTime);

        // Simulate concurrent writes (batched)
        $collection->remove([]);

        $start = microtime(true);
        $batchSize = 100;
        $batches = ceil($this->recordsPerTest / $batchSize);
        for ($b = 0; $b < $batches; ++$b) {
            $documents = [];
            for ($i = 0; $i < $batchSize; ++$i) {
                $documents[] = ['name' => 'User '.($b * $batchSize + $i)];
            }
            $collection->insert($documents);
        }
        $batchTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", "Batch Writes (size=$batchSize)", $batchTime);

        // Mixed operations
        $collection->remove([]);

        $start = microtime(true);
        for ($i = 0; $i < $this->recordsPerTest; ++$i) {
            $collection->insert(['op' => 'insert', 'index' => $i]);
            if ($i % 10 === 0 && $i > 0) {
                $collection->findOne(['index' => $i - 1]);
            }
            if ($i % 100 === 0 && $i > 0) {
                $collection->update(['index' => $i - 50], ['$set' => ['updated' => true]]);
            }
        }
        $mixedTime = (microtime(true) - $start) * 1000;
        printf("  %-35s | %9.3f ms\n", 'Mixed R/W Operations', $mixedTime);
    }

    /**
     * Print final summary.
     */
    public function printSummary(): void
    {
        echo "\n".str_repeat('=', 80);
        echo "\nBENCHMARK SUMMARY\n";
        echo str_repeat('=', 80)."\n\n";

        // Sort by ops per second (descending)
        usort($this->results, function ($a, $b) {
            return $b['ops_per_sec'] <=> $a['ops_per_sec'];
        });

        printf("%-40s | %10s | %12s\n", 'Test', 'Avg Time', 'Ops/Sec');
        echo str_repeat('-', 70)."\n";

        foreach ($this->results as $result) {
            printf(
                "%-40s | %8.3f ms | %10.0f ops/s\n",
                substr($result['test'], 0, 40),
                $result['avg_time_ms'],
                $result['ops_per_sec']
            );
        }

        echo "\n".str_repeat('=', 80);
        echo "\nTest Configuration:\n";
        echo sprintf("  - Records per test: %d\n", $this->recordsPerTest);
        echo sprintf("  - Iterations: %d\n", $this->iterations);
        echo sprintf("  - PHP Version: %s\n", PHP_VERSION);
        echo str_repeat('=', 80)."\n";
    }

    /**
     * Run all benchmarks.
     */
    public function runAll(): void
    {
        echo "\n".str_repeat('=', 80);
        echo ' BANGRONDB PERFORMANCE BENCHMARK';
        echo "\n".str_repeat('=', 80);
        echo sprintf("\nConfiguration:\n  - Records per test: %d\n  - Iterations: %d\n  - PHP Version: %s", $this->recordsPerTest, $this->iterations, PHP_VERSION);

        $this->benchmarkSingleInsert();
        $this->benchmarkBulkInsert();
        $this->benchmarkFind();
        $this->benchmarkUpdate();
        $this->benchmarkDelete();
        $this->benchmarkSearchableFields();
        $this->benchmarkSoftDeletes();
        $this->benchmarkCrossDatabase();
        $this->benchmarkQueryOperators();
        $this->benchmarkPagination();
        $this->benchmarkEncryption();
        $this->benchmarkConcurrency();

        $this->printSummary();

        $this->cleanup();
        $this->client->close();
    }
}

// Run benchmark
$benchmark = new BangronDBBenchmark(__DIR__.'/examples/data/benchmark', 1000, 3);
$benchmark->runAll();
