<?php

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;
use BangronDB\UtilArrayQuery;

class UtilArrayQueryTest extends TestCase
{
    public function testMatchSimpleEquality()
    {
        $criteria = ['name' => 'John'];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchNotEqual()
    {
        $criteria = ['name' => ['$ne' => 'Jane']];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchGreaterThan()
    {
        $criteria = ['age' => ['$gt' => 25]];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchLessThanOrEqual()
    {
        $criteria = ['age' => ['$lte' => 30]];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchInArray()
    {
        $criteria = ['tags' => ['$in' => ['php', 'sqlite']]];
        $document = ['name' => 'Project', 'tags' => ['php', 'database']];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchNotInArray()
    {
        $criteria = ['tags' => ['$nin' => ['java', 'mysql']]];
        $document = ['name' => 'Project', 'tags' => ['php', 'sqlite']];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchExists()
    {
        $criteria = ['email' => ['$exists' => true]];
        $document = ['name' => 'John', 'email' => 'john@example.com'];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchNotExists()
    {
        $criteria = ['email' => ['$exists' => false]];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchAnd()
    {
        $criteria = [
            '$and' => [
                ['age' => ['$gte' => 25]],
                ['name' => 'John'],
            ]
        ];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchOr()
    {
        $criteria = [
            '$or' => [
                ['name' => 'Jane'],
                ['age' => 30],
            ]
        ];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchRegex()
    {
        $criteria = ['name' => ['$regex' => '/^J/']];
        $document = ['name' => 'John', 'age' => 30];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchSize()
    {
        $criteria = ['tags' => ['$size' => 2]];
        $document = ['name' => 'Project', 'tags' => ['php', 'sqlite']];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testMatchAll()
    {
        $criteria = ['tags' => ['$all' => ['php', 'sqlite']]];
        $document = ['name' => 'Project', 'tags' => ['php', 'sqlite', 'database']];
        $this->assertTrue(UtilArrayQuery::match($criteria, $document));
    }

    public function testGenerateId()
    {
        $id1 = UtilArrayQuery::generateId();
        $id2 = UtilArrayQuery::generateId();
        $this->assertIsString($id1);
        $this->assertIsString($id2);
        $this->assertNotEquals($id1, $id2);
        $this->assertEquals(36, strlen($id1)); // UUID format
    }

    public function testFuzzySearch()
    {
        $score = UtilArrayQuery::fuzzy_search('test', 'testing', 3);
        $this->assertIsNumeric($score);
        $this->assertGreaterThan(0, $score);
        $this->assertLessThanOrEqual(1, $score);
    }

    public function testLevenshteinUtf8()
    {
        $distance = UtilArrayQuery::levenshtein_utf8('test', 'tost');
        $this->assertEquals(1, $distance);
    }
}
