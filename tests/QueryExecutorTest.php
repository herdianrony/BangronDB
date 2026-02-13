<?php

namespace BangronDB\Tests;

use BangronDB\QueryExecutionException;
use BangronDB\QueryExecutor;
use PHPUnit\Framework\TestCase;

class QueryExecutorTest extends TestCase
{
    private ?\PDO $pdo = null;
    private ?QueryExecutor $executor = null;

    protected function setUp(): void
    {
        $this->pdo = new \PDO('sqlite::memory:');
        $this->executor = new QueryExecutor($this->pdo);

        // Create a test table
        $this->pdo->exec('CREATE TABLE test_table (id INTEGER PRIMARY KEY, name TEXT, value INTEGER)');
    }

    protected function tearDown(): void
    {
        $this->pdo = null;
        $this->executor = null;
    }

    public function testConstructor()
    {
        $this->assertInstanceOf(QueryExecutor::class, $this->executor);
    }

    public function testSetLogging()
    {
        $this->executor->setLogging(true);
        $this->assertEquals([], $this->executor->getQueryLog());

        $this->executor->setLogging(false);
        $this->assertEquals([], $this->executor->getQueryLog());
    }

    public function testSetPerformanceMonitoring()
    {
        $this->executor->setPerformanceMonitoring(true);
        $this->assertEquals([], $this->executor->getQueryStats());

        $this->executor->setPerformanceMonitoring(false);
        $this->assertEquals([], $this->executor->getQueryStats());
    }

    public function testExecuteQuery()
    {
        // Insert test data
        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['test', 42]);

        // Execute SELECT query
        $stmt = $this->executor->executeQuery('SELECT * FROM test_table WHERE id = ?', [1]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals('test', $result['name']);
        $this->assertEquals(42, $result['value']);
    }

    public function testExecuteQueryWithLogging()
    {
        $this->executor->setLogging(true);

        $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $log = $this->executor->getQueryLog();
        $this->assertCount(1, $log);
        $this->assertEquals('SELECT', $log[0]['type']);
        $this->assertEquals('SELECT COUNT(*) as count FROM test_table', $log[0]['sql']);
        $this->assertEquals([], $log[0]['params']);
    }

    public function testExecuteQueryWithPerformanceMonitoring()
    {
        $this->executor->setPerformanceMonitoring(true);

        $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');

        $stats = $this->executor->getQueryStats();
        $this->assertArrayHasKey('SELECT', $stats);
        $this->assertEquals(1, $stats['SELECT']['count']);
    }

    public function testExecuteUpdate()
    {
        $affected = $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['test', 42]);
        $this->assertEquals(1, $affected);

        // Verify insertion
        $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(1, $result['count']);
    }

    public function testExecuteUpdateWithLogging()
    {
        $this->executor->setLogging(true);

        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['test', 42]);

        $log = $this->executor->getQueryLog();
        $this->assertCount(1, $log);
        $this->assertEquals('INSERT', $log[0]['type']);
        $this->assertEquals(1, $log[0]['affected_rows']);
    }

    /**
     * @group legacy
     */
    public function testExecuteRaw()
    {
        // Suppress deprecation warning for legacy method test
        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);

        $stmt = $this->executor->executeRaw('SELECT COUNT(*) as count FROM test_table');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);

        $this->assertEquals(0, $result['count']);

        error_reporting($previousLevel);
    }

    /**
     * @group legacy
     */
    public function testExecuteRawUpdate()
    {
        // Suppress deprecation warning for legacy method test
        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);

        $affected = $this->executor->executeRawUpdate('INSERT INTO test_table (name, value) VALUES ("raw", 100)');
        $this->assertEquals(1, $affected);

        error_reporting($previousLevel);
    }

    public function testExecuteTransaction()
    {
        $queries = [
            ['sql' => 'INSERT INTO test_table (name, value) VALUES (?, ?)', 'params' => ['txn1', 1]],
            ['sql' => 'INSERT INTO test_table (name, value) VALUES (?, ?)', 'params' => ['txn2', 2]],
        ];

        $results = $this->executor->executeTransaction($queries);

        $this->assertCount(2, $results);
        $this->assertEquals(1, $results[0]);
        $this->assertEquals(1, $results[1]);

        // Verify both were inserted
        $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(2, $result['count']);
    }

    public function testExecuteTransactionRollback()
    {
        $queries = [
            ['sql' => 'INSERT INTO test_table (name, value) VALUES (?, ?)', 'params' => ['txn1', 1]],
            ['sql' => 'INSERT INTO invalid_table (name) VALUES (?)', 'params' => ['txn2']], // This will fail
        ];

        $this->expectException(\Throwable::class);

        try {
            $this->executor->executeTransaction($queries);
        } catch (\Throwable $e) {
            // Verify rollback - no records should be inserted
            $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);
            $this->assertEquals(0, $result['count']);
            throw $e;
        }
    }

    public function testGetLastInsertId()
    {
        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['id_test', 99]);
        $id = $this->executor->getLastInsertId();

        $this->assertIsString($id);
        $this->assertGreaterThan(0, (int) $id);
    }

    public function testQuote()
    {
        $quoted = $this->executor->quote("test'value");
        $this->assertEquals("'test''value'", $quoted);
    }

    public function testSanitizeIdentifier()
    {
        $sanitized = $this->executor->sanitizeIdentifier('valid_name');
        $this->assertEquals('valid_name', $sanitized);

        $this->expectException(\InvalidArgumentException::class);
        $this->executor->sanitizeIdentifier('123invalid');
    }

    public function testQuoteTable()
    {
        $quoted = $this->executor->quoteTable('test_table');
        $this->assertEquals('`test_table`', $quoted);
    }

    public function testTableExists()
    {
        $exists = $this->executor->tableExists('test_table');
        $this->assertTrue($exists);

        $exists = $this->executor->tableExists('nonexistent_table');
        $this->assertFalse($exists);
    }

    public function testExecuteQueryException()
    {
        $this->expectException(QueryExecutionException::class);
        $this->executor->executeQuery('SELECT * FROM nonexistent_table');
    }

    public function testExecuteUpdateException()
    {
        $this->expectException(QueryExecutionException::class);
        $this->executor->executeUpdate('INSERT INTO nonexistent_table VALUES (?)', ['test']);
    }

    /**
     * @group legacy
     */
    public function testExecuteRawException()
    {
        // Suppress deprecation warning for legacy method test
        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);

        $this->expectException(QueryExecutionException::class);
        $this->executor->executeRaw('SELECT * FROM nonexistent_table');

        error_reporting($previousLevel);
    }

    /**
     * @group legacy
     */
    public function testExecuteRawUpdateException()
    {
        // Suppress deprecation warning for legacy method test
        $previousLevel = error_reporting(E_ALL & ~E_USER_DEPRECATED);

        $this->expectException(QueryExecutionException::class);
        $this->executor->executeRawUpdate('INSERT INTO nonexistent_table VALUES ("test")');

        error_reporting($previousLevel);
    }

    public function testClearLogs()
    {
        $this->executor->setLogging(true);
        $this->executor->executeQuery('SELECT COUNT(*) FROM test_table');
        $this->assertCount(1, $this->executor->getQueryLog());

        $this->executor->clearLogs();
        $this->assertCount(0, $this->executor->getQueryLog());
    }

    public function testParameterBindingTypes()
    {
        // Test different parameter types
        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['string', 42]);
        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['bool', true]);
        $this->executor->executeUpdate('INSERT INTO test_table (name, value) VALUES (?, ?)', ['null', null]);

        $stmt = $this->executor->executeQuery('SELECT COUNT(*) as count FROM test_table');
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        $this->assertEquals(3, $result['count']);
    }

    public function testQueryTypeDetection()
    {
        $this->executor->setLogging(true);

        $this->executor->executeQuery('SELECT * FROM test_table');
        $this->executor->executeUpdate('INSERT INTO test_table (name) VALUES (?)', ['test']);
        $this->executor->executeUpdate('UPDATE test_table SET name = ? WHERE id = ?', ['updated', 1]);
        $this->executor->executeUpdate('DELETE FROM test_table WHERE id = ?', [1]);

        $log = $this->executor->getQueryLog();
        $this->assertEquals('SELECT', $log[0]['type']);
        $this->assertEquals('INSERT', $log[1]['type']);
        $this->assertEquals('UPDATE', $log[2]['type']);
        $this->assertEquals('DELETE', $log[3]['type']);
    }

    public function testPerformanceStatsAggregation()
    {
        $this->executor->setPerformanceMonitoring(true);

        // Execute multiple queries of same type
        $this->executor->executeQuery('SELECT COUNT(*) FROM test_table');
        $this->executor->executeQuery('SELECT COUNT(*) FROM test_table');
        $this->executor->executeQuery('SELECT COUNT(*) FROM test_table');

        $stats = $this->executor->getQueryStats();
        $this->assertEquals(3, $stats['SELECT']['count']);
        $this->assertGreaterThan(0, $stats['SELECT']['avg_time']);
        $this->assertGreaterThanOrEqual(0, $stats['SELECT']['min_time']);
        $this->assertGreaterThanOrEqual($stats['SELECT']['min_time'], $stats['SELECT']['max_time']);
    }
}
