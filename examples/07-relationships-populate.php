<?php

/**
 * Contoh 07: Relationships & Populate
 *
 * Relasi antar collection: single, nested, array references,
 * cross-database populate.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 07: Relationships & Populate');

$client = createIsolatedClient('example07');
$db = $client->createDB('blog');

$users     = $db->createCollection('users');
$posts     = $db->createCollection('posts');
$comments  = $db->createCollection('comments');
$tags      = $db->createCollection('tags');

// ── Setup Data ────────────────────────────────────────────
sub('Setup Data');

$author1 = $users->insert(['name' => 'John',   'email' => 'john@blog.com']);
$author2 = $users->insert(['name' => 'Jane',   'email' => 'jane@blog.com']);

$tag1 = $tags->insert(['name' => 'PHP',       'slug' => 'php']);
$tag2 = $tags->insert(['name' => 'Database',  'slug' => 'database']);
$tag3 = $tags->insert(['name' => 'Tutorial',  'slug' => 'tutorial']);

$post1 = $posts->insert([
    'title'    => 'Getting Started with BangronDB',
    'content'  => 'A comprehensive guide...',
    'author_id' => $author1,
    'tag_ids'  => [$tag1, $tag2],
]);

$post2 = $posts->insert([
    'title'    => 'Advanced Query Operators',
    'content'  => 'Deep dive into queries...',
    'author_id' => $author2,
    'tag_ids'  => [$tag1, $tag3],
]);

$comment1 = $comments->insert(['text' => 'Great article!', 'post_id' => $post1, 'user_id' => $author2]);
$comment2 = $comments->insert(['text' => 'Very helpful!',  'post_id' => $post1, 'user_id' => $author1]);

echo "Setup: 2 users, 3 tags, 2 posts, 2 comments\n";

// ── Single Populate: Post → Author ────────────────────────
sub('Single Populate: Post → Author');

$postsList = $posts->find()->toArray();
$withAuthor = $posts->populate($postsList, 'author_id', 'users', '_id', 'author');

foreach ($withAuthor as $p) {
    echo "Post: {$p['title']}\n";
    echo "  Author: {$p['author']['name']}\n";
}

// ── Cursor-based Populate ─────────────────────────────────
sub('Cursor-based Populate (fluent API)');

$populated = $posts->find()
    ->populate('author_id', $users, ['as' => 'author'])
    ->toArray();

echo "Fluent populate count: " . count($populated) . "\n";

// ── Array References: Post → Tags ─────────────────────────
sub('Array References: Post → Tags');

$postsWithTags = $posts->populate($withAuthor, 'tag_ids', 'tags', '_id', 'tags');

foreach ($postsWithTags as $p) {
    $tags = $p['tags'] ?? [];
    $tagNames = is_array($tags) ? array_column($tags, 'name') : [];
    echo "Post: {$p['title']} → Tags: " . implode(', ', $tagNames) . "\n";
}

// ── Multi-level Populate ──────────────────────────────────
sub('Multi-level: Comment → Post → Author');

$commentsList = $comments->find()->toArray();
$withPost = $comments->populate($commentsList, 'post_id', 'posts', '_id', 'post');
$withPostAndUser = $comments->populate($withPost, 'user_id', 'users', '_id', 'commenter');

foreach ($withPostAndUser as $c) {
    echo "Comment: \"{$c['text']}\"\n";
    echo "  By: {$c['commenter']['name']} on \"{$c['post']['title']}\"\n";
}

// ── Cross-Database Populate ───────────────────────────────
sub('Cross-Database Populate');

// Buat database kedua untuk profiles
$profilesDb = $client->createDB('profiles');
$profiles = $profilesDb->createCollection('profiles');

$profiles->insert([
    'user_id'  => $author1,
    'bio'      => 'Full-stack developer',
    'location' => 'Jakarta',
]);

$profiles->insert([
    'user_id'  => $author2,
    'bio'      => 'Data engineer',
    'location' => 'Bandung',
]);

$profilesList = $profiles->find()->toArray();
// Cross-DB: gunakan "database.collection" notation
$withUser = $profiles->populate($profilesList, 'user_id', 'blog.users', '_id', 'user');

foreach ($withUser as $p) {
    echo "Profile: {$p['bio']} → User: {$p['user']['name']} ({$p['user']['email']})\n";
}

@$client->close();
echo "\nDone!\n";
