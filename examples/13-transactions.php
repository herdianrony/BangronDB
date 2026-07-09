<?php

/**
 * Contoh 08: Transactions & Batch Operations
 *
 * Transaksi untuk atomic operations, batch insert,
 * dan error handling dengan rollback.
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 08: Transactions & Batch Operations');

$client = createIsolatedClient('example08');
$db = $client->createDB('bank');
$accounts = $db->createCollection('accounts');
$transfers = $db->createCollection('transfers');

// ── Setup Accounts ────────────────────────────────────────
sub('Setup Accounts');

$a1 = $accounts->insert(['owner' => 'Alice', 'balance' => 1000.00]);
$a2 = $accounts->insert(['owner' => 'Bob',   'balance' => 500.00]);
echo "Alice: 1000.00, Bob: 500.00\n";

// ── Successful Transaction ────────────────────────────────
sub('Successful Transfer (Commit)');

$db->connection->beginTransaction();

try {
    $amount = 300.00;

    // Kurangi Alice
    $accounts->update(['_id' => $a1], ['$set' => ['balance' => 700.00]]);

    // Tambah Bob
    $accounts->update(['_id' => $a2], ['$set' => ['balance' => 800.00]]);

    // Record transfer
    $transfers->insert([
        'from'   => $a1,
        'to'     => $a2,
        'amount' => $amount,
        'status' => 'completed',
    ]);

    $db->connection->commit();
    echo "Transfer committed: 300 from Alice to Bob\n";
} catch (Exception $e) {
    $db->connection->rollBack();
    echo "Transfer failed: {$e->getMessage()}\n";
}

echo "Alice: " . $accounts->findOne(['_id' => $a1])['balance'] . "\n";
echo "Bob: " . $accounts->findOne(['_id' => $a2])['balance'] . "\n";

// ── Failed Transaction (Rollback) ─────────────────────────
sub('Failed Transfer (Rollback)');

$db->connection->beginTransaction();

try {
    // Mencoba transfer yang akan gagal
    $accounts->update(['_id' => $a2], ['$set' => ['balance' => -200.00]]);

    // Validasi saldo
    $bobAccount = $accounts->findOne(['_id' => $a2]);
    if ($bobAccount['balance'] < 0) {
        throw new RuntimeException('Insufficient balance! Transfer cancelled.');
    }

    $db->connection->commit();
} catch (Exception $e) {
    $db->connection->rollBack();
    echo "Rollback: {$e->getMessage()}\n";
}

// Verifikasi saldo tidak berubah setelah rollback
echo "Alice after rollback: " . $accounts->findOne(['_id' => $a1])['balance'] . "\n";
echo "Bob after rollback: " . $accounts->findOne(['_id' => $a2])['balance'] . "\n";

// ── Batch Insert with Transaction ─────────────────────────
sub('Batch Insert with Transaction');

// Insert batch one by one (batch insert uses internal transaction)
$db->connection->beginTransaction();

try {
    for ($i = 1; $i <= 50; $i++) {
        $accounts->insert(['owner' => "Customer {$i}", 'balance' => 100.00]);
    }

    $db->connection->commit();
    echo "Batch committed: 50 accounts inserted\n";
} catch (Exception $e) {
    $db->connection->rollBack();
    echo "Batch failed: {$e->getMessage()}\n";
}

echo "Total accounts: " . $accounts->count() . "\n";
echo "Total transfers: " . $transfers->count() . "\n";

@$client->close();
echo "\nDone!\n";
