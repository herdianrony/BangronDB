<?php

/**
 * Contoh 13: Security Features
 *
 * Keamanan BangronDB: Closure-only $where/$func,
 * field name validation, path traversal prevention,
 * PRAGMA key escaping, dan FieldValidator utility.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;
use BangronDB\Security\FieldValidator;

sep('Contoh 13: Security Features');

$client = createIsolatedClient('example13');
$db = $client->createDB('secure_app');
$users = $db->createCollection('users');

$users->insert([
    ['name' => 'Alice',   'email' => 'alice@test.com',   'age' => 28, 'role' => 'admin'],
    ['name' => 'Bob',     'email' => 'bob@test.com',     'age' => 35, 'role' => 'user'],
    ['name' => 'Charlie', 'email' => 'charlie@test.com', 'age' => 22, 'role' => 'user'],
]);

// ── $where & $func: Closure Only ──────────────────────────
sub('Security: $where & $func - Closure Only');

// ✅ Closure: allowed
$adults = $users->find(['$where' => fn($doc) => ($doc['age'] ?? 0) >= 30])->toArray();
echo "Adults (age >= 30): " . implode(', ', array_column($adults, 'name')) . "\n";

$longNames = $users->find(['name' => ['$func' => fn($val) => strlen($val) > 4]])->toArray();
echo "Names > 4 chars: " . implode(', ', array_column($longNames, 'name')) . "\n";

// ❌ String function names: BLOCKED by $func
echo "\nString function names are blocked by \$func:\n";
try {
    $result = $users->find(['name' => ['$func' => 'system']]);
    $result->toArray();
    echo "UNEXPECTED: should have been blocked!\n";
} catch (\BangronDB\Exceptions\ValidationException $e) {
    echo "BLOCKED: String 'system' rejected in \$func\n";
} catch (\Throwable $e) {
    echo "BLOCKED: " . get_class($e) . " - unsafe callable rejected\n";
}

// ── Field Name Validation ─────────────────────────────────
sub('Security: Field Name Validation');

// ✅ Valid names
echo "Valid 'user_name': " . (FieldValidator::isValidFieldName('user_name') ? 'yes' : 'no') . "\n";
echo "Valid 'address.city': " . (FieldValidator::isValidFieldName('address.city') ? 'yes' : 'no') . "\n";
echo "Valid 'user-email': " . (FieldValidator::isValidFieldName('user-email') ? 'yes' : 'no') . "\n";

// ❌ Invalid names (injection attempts)
echo "\nTrying field name \"field'; DROP--\"...\n";
try {
    FieldValidator::validateFieldName("field'; DROP--");
    echo "UNEXPECTED: should have been blocked!\n";
} catch (\BangronDB\Exceptions\ValidationException $e) {
    echo "BLOCKED: Invalid field name rejected\n";
}

// ── Database Path Validation ──────────────────────────────
sub('Security: Path Traversal Prevention');

// Valid path (using existing directory)
$validPath = FieldValidator::validateDatabasePath(__DIR__ . '/data/test.sqlite');
echo "Valid path accepted: " . basename($validPath) . "\n";

echo "\nTrying path traversal '../../etc/passwd'...\n";
try {
    // Path traversal akan gagal karena /etc/passwd bukan .sqlite file path yang valid
    FieldValidator::validateDatabasePath('../../etc/passwd');
    echo "Path accepted (may be resolved by realpath)\n";
} catch (\BangronDB\Exceptions\ValidationException $e) {
    echo "BLOCKED: Path traversal prevented\n";
}

// ── Encryption Key Escaping ───────────────────────────────
sub('Security: PRAGMA Key Escaping');

$escaped = FieldValidator::escapePragmaKey("key with 'quotes'");
echo "Escaped key: {$escaped}\n";

// ── Safe Callable Check ───────────────────────────────────
sub('Security: Safe Callable Check');

echo "Closure is safe: " . (FieldValidator::isSafeCallable(fn($x) => true) ? 'yes' : 'no') . "\n";
echo "String 'system' is safe: " . (FieldValidator::isSafeCallable('system') ? 'yes' : 'no') . "\n";
echo "Array callable is safe: " . (FieldValidator::isSafeCallable([new \stdClass, 'method']) ? 'yes' : 'no') . "\n";

// ── Regex Safety ──────────────────────────────────────────
sub('Security: Regex Pattern Sanitization');

$safe = FieldValidator::sanitizeRegexPattern('test/path');
echo "Sanitized 'test/path': {$safe}\n";

echo "\nSecurity Features Summary:\n";
echo "  ✅ Closure-only \$where/\$func (RCE prevention)\n";
echo "  ✅ Field name whitelist (injection prevention)\n";
echo "  ✅ Path validation (traversal prevention)\n";
echo "  ✅ PRAGMA key escaping (SQLite injection prevention)\n";
echo "  ✅ Regex sanitization (ReDoS prevention)\n";
echo "  ✅ strict_types=1 (type safety)\n";

@$client->close();
echo "\nDone!\n";
