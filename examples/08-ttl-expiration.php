<?php

/**
 * Contoh 08: TTL (Time-To-Live)
 *
 * Demonstrasi dokumen yang otomatis kedaluwarsa.
 * Cocok untuk: OTP, session, cache, temporary token.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 08: TTL (Time-To-Live)');

$client = createIsolatedClient('example08');
$db = $client->createDB('ttl_demo');

// ── Contoh 1: OTP dengan TTL ────────────────────────
sub('1. OTP — Auto-Expire 5 Menit');

$otp = $db->createCollection('otp');
$otp->enableTtl('expires_at', 300);  // 5 menit default

$otp->insert(['code' => '123456', 'user_id' => 'u1']);
$otp->insert(['code' => '654321', 'user_id' => 'u2']);
$otp->insert([
    'code'       => '999999',
    'user_id'    => 'u3',
    'expires_at' => time() + 7200,  // override: 2 jam
]);

$all = $otp->find()->toArray();
foreach ($all as $doc) {
    $remaining = ($doc['expires_at'] - time());
    echo "  OTP {$doc['code']}: expires in {$remaining}s\n";
}

// ── Contoh 2: Session ───────────────────────────────
sub('2. Session — Manual Expiry');

$sessions = $db->createCollection('sessions');
$sessions->enableTtl('expires_at');  // tanpa default

$sessions->insert([
    'session_id' => 'sess_abc',
    'user_id'    => 'u1',
    'expires_at' => time() + 3600,  // 1 jam
]);

$sessions->insert([
    'session_id' => 'sess_expired',
    'user_id'    => 'u2',
    'expires_at' => time() - 100,   // sudah expired
]);

// ── Contoh 3: cleanExpired ─────────────────────────
sub('3. cleanExpired — Bersihkan Dokumen Expired');

$before = $sessions->count();
$expiredBefore = $sessions->expiredCount();
echo "Total: $before, Expired: $expiredBefore\n";

$removed = $sessions->cleanExpired();
echo "Cleaned: $removed documents\n";

$after = $sessions->count();
echo "Remaining: $after\n";

// ── Contoh 4: ttlStats ─────────────────────────────
sub('4. ttlStats — Statistik TTL');

$cache = $db->createCollection('cache');
$cache->enableTtl('expires_at', 600);

// Insert beberapa cache entry
$cache->insertMany([
    ['key' => 'user:1', 'data' => '...', 'expires_at' => time() + 500],
    ['key' => 'user:2', 'data' => '...', 'expires_at' => time() - 100],  // expired
    ['key' => 'user:3', 'data' => '...', 'expires_at' => time() + 300],
    ['key' => 'user:4', 'data' => '...', 'expires_at' => time() - 200],  // expired
]);

$stats = $cache->ttlStats();
p([
    'ttl_enabled'     => $stats['ttl_enabled'],
    'ttl_field'       => $stats['ttl_field'],
    'default_ttl'     => $stats['default_ttl_seconds'] . 's',
    'documents'       => $stats['documents_with_ttl'],
    'expired'         => $stats['expired_count'],
    'active'          => $stats['active_count'],
    'next_expires_in' => $stats['next_expires_in_seconds'] . 's',
]);

// ── Contoh 5: Disable TTL ──────────────────────────
sub('5. Disable TTL');

$noTtl = $db->createCollection('permanent');
$noTtl->insert(['key' => 'config', 'value' => 'permanent data']);

$stats = $noTtl->ttlStats();
echo "TTL enabled: " . ($stats['ttl_enabled'] ? 'yes' : 'no') . "\n";

$cleaned = $noTtl->cleanExpired();
echo "cleanExpired when disabled: $cleaned\n";

// ── Invalid TTL ────────────────────────────────────
sub('6. Validasi TTL');

try {
    $bad = $db->createCollection('bad');
    $bad->enableTtl('expires_at', -1);  // negative → error
} catch (\InvalidArgumentException $e) {
    echo "✓ Ditolak: {$e->getMessage()}\n";
}

@$client->close();
echo "\nDone!\n";