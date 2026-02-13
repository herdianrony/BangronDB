<?php

/**
 * Contoh 08: Transactions.
 *
 * Demonstrasi transaksi untuk atomic operations.
 */

require_once __DIR__.'/bootstrap.php';

echo "=== Contoh 08: Transactions ===\n\n";

// Buat client dengan database isolated
$client = createIsolatedClient('transaction_demo');
$db = $client->selectDB('app');
$accounts = $db->accounts;
$transactions = $db->transactions;

echo "1. Setup accounts\n";
echo "------------------\n";

$account1 = $accounts->insert([
    'name' => 'Account 1',
    'balance' => 1000.00,
]);

$account2 = $accounts->insert([
    'name' => 'Account 2',
    'balance' => 500.00,
]);

echo "Account 1 balance: 1000.00\n";
echo "Account 2 balance: 500.00\n\n";

echo "2. Transfer dengan transaction\n";
echo "-----------------------------\n";

// Mulai transaction
$db->connection->beginTransaction();

try {
    // Kurangi dari account 1
    $amount = 300.00;
    $accounts->update(
        ['_id' => $account1],
        ['$set' => ['balance' => 700.00]]
    );

    // Tambah ke account 2
    $accounts->update(
        ['_id' => $account2],
        ['$set' => ['balance' => 800.00]]
    );

    // Record transaksi
    $transactions->insert([
        'from' => $account1,
        'to' => $account2,
        'amount' => $amount,
        'status' => 'completed',
    ]);

    // Commit transaction
    $db->connection->commit();
    echo "Transfer completed!\n";
} catch (Exception $e) {
    // Rollback jika ada error
    $db->connection->rollBack();
    echo 'Transfer failed: '.$e->getMessage()."\n";
}

echo "\nAccount 1 balance: ".$accounts->findOne(['_id' => $account1])['balance']."\n";
echo 'Account 2 balance: '.$accounts->findOne(['_id' => $account2])['balance']."\n";

echo "\n3. Transfer yang gagal (rollback)\n";
echo "------------------------------------\n";

$db->connection->beginTransaction();

try {
    // Kurangi dari account 2
    $amount = 1000.00;
    $accounts->update(
        ['_id' => $account2],
        ['$set' => ['balance' => -200.00]] // Saldo negatif
    );

    // Tambah ke account 1
    $accounts->update(
        ['_id' => $account1],
        ['$set' => ['balance' => 1700.00]]
    );

    // Check saldo - jika negatif, throw exception
    $account2Data = $accounts->findOne(['_id' => $account2]);
    if ($account2Data['balance'] < 0) {
        throw new Exception('Insufficient balance!');
    }

    $db->connection->commit();
    echo "Transfer completed!\n";
} catch (Exception $e) {
    $db->connection->rollBack();
    echo 'Transfer failed: '.$e->getMessage()."\n";
    echo "Rolling back...\n";
}

echo "\nAccount 1 balance: ".$accounts->findOne(['_id' => $account1])['balance']."\n";
echo 'Account 2 balance: '.$accounts->findOne(['_id' => $account2])['balance']."\n";

echo "\n4. Batch insert dengan transaction\n";
echo "-------------------------------------\n";

$db->connection->beginTransaction();

try {
    for ($i = 1; $i <= 100; ++$i) {
        $accounts->insert([
            'name' => "Batch Account $i",
            'balance' => 100.00,
        ]);
    }

    $db->connection->commit();
    echo "Batch insert completed!\n";
} catch (Exception $e) {
    $db->connection->rollBack();
    echo 'Batch insert failed: '.$e->getMessage()."\n";
}

$totalAccounts = $accounts->count();
echo "Total accounts: $totalAccounts\n";

echo "\n=== Cleanup ===\n";
@$db->drop();
$client->close();
echo "Database dibersihkan.\n";
