<?php

/**
 * Contoh 07: Relationships & Population.
 *
 * Demonstrasi relasi antar collections dengan populate.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 07: Relationships & Population ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('relationship_demo');
$db = $client->selectDB('app');

$users = $db->users;
$posts = $db->posts;
$comments = $db->comments;

echo "1. Insert sample data dengan relasi\n";
echo "------------------------------------\n";

// Insert users
$user1Id = $users->insert([
    'name' => 'John Doe',
    'email' => 'john@example.com',
]);

$user2Id = $users->insert([
    'name' => 'Jane Smith',
    'email' => 'jane@example.com',
]);

// Insert posts dengan author_id (foreign key)
$post1Id = $posts->insert([
    'title' => 'First Post',
    'content' => 'Hello World!',
    'author_id' => $user1Id,
    'created_at' => date('c'),
]);

$post2Id = $posts->insert([
    'title' => 'Second Post',
    'content' => 'Another post',
    'author_id' => $user1Id,
    'created_at' => date('c'),
]);

$post3Id = $posts->insert([
    'title' => 'Jane\'s Post',
    'content' => 'From Jane',
    'author_id' => $user2Id,
    'created_at' => date('c'),
]);

// Insert comments
$comment1Id = $comments->insert([
    'text' => 'Great post!',
    'post_id' => $post1Id,
    'user_id' => $user2Id,
]);

$comment2Id = $comments->insert([
    'text' => 'Thanks!',
    'post_id' => $post1Id,
    'user_id' => $user1Id,
]);

echo "Data inserted:\n";
echo "- Users: 2\n";
echo "- Posts: 3\n";
echo "- Comments: 2\n\n";

echo "2. Basic Population - Single relation\n";
echo "--------------------------------------\n";

// Get posts dengan author populated
$postsList = $posts->find()->toArray();
$populatedPosts = $posts->populate($postsList, 'author_id', 'users', '_id', 'author');

echo "Posts with populated author:\n";
print_r($populatedPosts);

echo "\n3. Population - Multiple relations\n";
echo "------------------------------------\n";

// Get comments dengan post dan user
$commentsList = $comments->find()->toArray();
$populatedComments = $comments->populate($commentsList, 'post_id', 'posts', '_id', 'post');
$populatedComments = $comments->populate($populatedComments, 'user_id', 'users', '_id', 'author');

echo "Comments with populated post and author:\n";
print_r($populatedComments);

echo "\n4. Self-referencing population\n";
echo "-------------------------------\n";

$users->setSearchableFields(['name']);

$users->insert([
    'name' => 'Admin User',
    'email' => 'admin@example.com',
    'manager_id' => $user1Id,
]);

// Populate manager
$usersList = $users->find()->toArray();
$populatedUsers = $users->populate($usersList, 'manager_id', 'users', '_id', 'manager');

echo "Users with populated manager:\n";
print_r($populatedUsers);

echo "\n5. Cross-database population\n";
echo "-----------------------------\n";

// Buat database kedua
$profilesDb = $client->selectDB('profiles');
$profiles = $profilesDb->profiles;

$profileId = $profiles->insert([
    'user_id' => $user1Id,
    'bio' => 'Software Developer',
    'location' => 'Jakarta',
]);

// Get dari database berbeda
$profilesList = $profiles->find()->toArray();
// Cross-database: gunakan "db.collection" notation
// Database utama bernama 'app', jadi gunakan 'app.users'
$populatedProfiles = $profiles->populate($profilesList, 'user_id', 'app.users', '_id', 'user');

echo "Profiles with populated user (cross-database):\n";
print_r($populatedProfiles);

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
