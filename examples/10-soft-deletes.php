<?php

/**
 * Contoh 05: Soft Deletes
 *
 * Soft delete, restore, force delete, withTrashed, onlyTrashed.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 05: Soft Deletes');

$client = createIsolatedClient('example05');
$db = $client->createDB('blog');
$posts = $db->createCollection('posts');

// ── Enable soft deletes ───────────────────────────────────
sub('Setup & Insert');

$posts->useSoftDeletes(true);

$p1 = $posts->insert(['title' => 'Post 1', 'status' => 'published']);
$p2 = $posts->insert(['title' => 'Post 2', 'status' => 'draft']);
$p3 = $posts->insert(['title' => 'Post 3', 'status' => 'published']);
$p4 = $posts->insert(['title' => 'Post 4', 'status' => 'archived']);

echo "Inserted 4 posts. Total: " . $posts->count() . "\n";

// ── Soft Delete ───────────────────────────────────────────
sub('Soft Delete');

$removed = $posts->remove(['_id' => $p2]);
echo "Soft deleted: {$removed} post(s)\n";
echo "Active posts: " . $posts->count() . "\n";
echo "Total with trashed: " . $posts->find()->withTrashed()->count() . "\n";

// ── View Trashed ──────────────────────────────────────────
sub('View Trashed');

$onlyTrashed = $posts->find()->onlyTrashed()->toArray();
echo "Only trashed: " . implode(', ', array_column($onlyTrashed, 'title')) . "\n";

$withTrashed = $posts->find()->withTrashed()->toArray();
echo "With trashed: " . count($withTrashed) . " posts total\n";

// ── Restore ───────────────────────────────────────────────
sub('Restore');

$restored = $posts->restore(['_id' => $p2]);
echo "Restored: {$restored} post(s)\n";
echo "Active after restore: " . $posts->count() . "\n";

// ── Force Delete ──────────────────────────────────────────
sub('Force Delete');

$forceDeleted = $posts->forceDelete(['_id' => $p4]);
echo "Force deleted: {$forceDeleted} post(s)\n";

$allWithTrashed = $posts->find()->withTrashed()->toArray();
echo "With trashed after force delete: " . count($allWithTrashed) . " (permanen hilang)\n";

// ── Soft Delete by Criteria ───────────────────────────────
sub('Soft Delete by Criteria');

$removed = $posts->remove(['status' => 'archived']);
echo "Soft deleted archived: {$removed} post(s)\n";

// ── Active vs Trashed ─────────────────────────────────────
sub('Summary');

echo "Active: " . $posts->count() . "\n";
echo "Trashed: " . $posts->find()->onlyTrashed()->count() . "\n";
echo "All: " . $posts->find()->withTrashed()->count() . "\n";

@$client->close();
echo "\nDone!\n";
