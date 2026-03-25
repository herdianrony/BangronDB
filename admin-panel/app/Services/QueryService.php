<?php

namespace App\Services;

class QueryService
{
    private SystemService $system;
    private DatabaseService $databaseService;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->databaseService = new DatabaseService();
    }

    public function run(array $user, string $dbName, string $collection, array $criteria, array $sort, int $limit = 50): array
    {
        $this->databaseService->assertNotSystem($dbName);
        if (!$this->databaseService->userCanAccess($user, $dbName, 'viewer')) {
            throw new \RuntimeException('Database access denied');
        }

        $limit = max(1, min(200, $limit));
        $coll = $this->system->tenantClient()->selectDB($dbName)->selectCollection($collection);
        $results = $coll->find($criteria)->sort($sort)->limit($limit)->toArray();

        return $results;
    }

    public function saveHistory(string $userId, array $payload): void
    {
        $this->system->systemDb()->query_history->insert([
            '_id' => uuid(),
            'user_id' => $userId,
            'payload' => $payload,
            'created_at' => date('c'),
        ]);
    }

    public function history(string $userId, int $limit = 20): array
    {
        return $this->system->systemDb()->query_history
            ->find(['user_id' => $userId])
            ->sort(['created_at' => -1])
            ->limit($limit)
            ->toArray();
    }
}

