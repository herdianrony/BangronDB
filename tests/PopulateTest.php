<?php

namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class PopulateTest extends TestCase
{
    private Database $db;
    private Collection $users;
    private Collection $posts;

    protected function setUp(): void
    {
        $this->db = new Database(':memory:');
        $this->users = $this->db->selectCollection('users');
        $this->posts = $this->db->selectCollection('posts');
    }

    protected function tearDown(): void
    {
        $this->db->close();
    }

    public function testPopulateWithoutClient()
    {
        // Test that populate throws exception when no client is available
        $postData = [
            'title' => 'Test Post',
            'user_id' => 'some_id',
        ];

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Client not available for populate');

        $this->posts->populate([$postData], 'user_id', 'users', '_id');
    }

    public function testPopulateReturnsOriginalDataWhenNoClient()
    {
        $postData = [
            'title' => 'Test Post',
            'user_id' => null,
        ];

        // Should return original data when reference is null
        $result = $this->posts->populate([$postData], 'user_id', 'users', '_id');
        $this->assertEquals([$postData], $result);
    }

    public function testPopulateSingleDocumentReturnType()
    {
        $postData = [
            'title' => 'Single Post',
            'user_id' => null,
        ];

        // Test single document return (not array)
        $result = $this->posts->populate($postData, 'user_id', 'users', '_id');
        $this->assertIsArray($result);
        $this->assertEquals('Single Post', $result['title']);
    }

    public function testPopulateEmptyKeys()
    {
        $postsData = [
            ['title' => 'Post 1', 'user_id' => null],
            ['title' => 'Post 2', 'user_id' => null],
        ];

        $result = $this->posts->populate($postsData, 'user_id', 'users', '_id');
        $this->assertCount(2, $result);
        $this->assertEquals('Post 1', $result[0]['title']);
        $this->assertEquals('Post 2', $result[1]['title']);
    }

    public function testPopulateWithArrayReferenceField()
    {
        // Test that array references trigger exception when no client
        $postData = [
            'title' => 'Post with multiple users',
            'user_ids' => ['user1', 'user2', 'user3'],
        ];

        $this->expectException(\RuntimeException::class);
        $this->posts->populate([$postData], 'user_ids', 'users', '_id');
    }

    public function testPopulateWithCustomAsField()
    {
        $postData = [
            'title' => 'Test Post',
            'author_id' => null,
        ];

        $result = $this->posts->populate([$postData], 'author_id', 'users', '_id', 'author');
        $this->assertCount(1, $result);
        $this->assertArrayNotHasKey('users', $result[0]); // Should not populate default field
    }

    public function testPopulateDifferentDatabaseNotation()
    {
        // Test parsing of "db.collection" notation (even without client)
        $postData = [
            'title' => 'Cross DB Post',
            'category_id' => null,
        ];

        $result = $this->posts->populate([$postData], 'category_id', 'blog.categories', '_id');
        $this->assertCount(1, $result);
        $this->assertEquals('Cross DB Post', $result[0]['title']);
    }

    public function testPopulateWithNonArrayDocument()
    {
        // Test that non-null references trigger exception when no client
        $postData = [
            'title' => 'Post with string ref',
            'user_id' => 'string_id',
        ];

        $this->expectException(\RuntimeException::class);
        $this->posts->populate([$postData], 'user_id', 'users', '_id');
    }

    public function testPopulateMethodExists()
    {
        // Basic smoke test that the method exists
        $this->assertTrue(method_exists($this->posts, 'populate'));
    }

    public function testPopulateHandlesEmptyDocuments()
    {
        $result = $this->posts->populate([], 'user_id', 'users', '_id');
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
