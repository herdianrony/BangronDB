<?php

namespace App\Services;

class DocumentService
{
    private SystemService $system;
    private DatabaseService $databaseService;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->databaseService = new DatabaseService();
    }

    public function list(
        string $dbName,
        string $collection,
        array $criteria = [],
        array $sort = ['_id' => -1],
        int $limit = 20,
        int $skip = 0,
        string $status = 'all'
    ): array
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);
        $cursor = $this->buildCursor($coll, $criteria, $sort, $limit, $skip, $status);

        return $cursor->toArray();
    }

    public function count(string $dbName, string $collection, array $criteria = [], string $status = 'all'): int
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);
        $cursor = $this->buildCursor($coll, $criteria, ['_id' => -1], null, 0, $status);

        return $cursor->count();
    }

    public function create(string $dbName, string $collection, array $document): string
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);

        return (string) $coll->insert($document);
    }

    public function update(string $dbName, string $collection, string $id, array $data): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);
        $coll->update(['_id' => $id], $data);
    }

    public function delete(string $dbName, string $collection, string $id): void
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);
        $coll->remove(['_id' => $id]);
    }

    public function findById(string $dbName, string $collection, string $id): ?array
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);

        return $coll->findOne(['_id' => $id]);
    }

    public function exportAsJson(
        string $dbName,
        string $collection,
        array $criteria = [],
        array $sort = ['_id' => -1],
        string $status = 'all'
    ): string {
        $documents = $this->list($dbName, $collection, $criteria, $sort, 5000, 0, $status);

        return json_encode($documents, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    public function importFromJson(string $dbName, string $collection, array $documents, bool $upsert = true): array
    {
        $this->databaseService->assertNotSystem($dbName);
        $coll = $this->collection($dbName, $collection);

        $inserted = 0;
        $updated = 0;
        $skipped = 0;
        $errors = [];

        foreach ($documents as $idx => $doc) {
            if (!is_array($doc)) {
                ++$skipped;
                $errors[] = 'Index ' . $idx . ': document must be object';
                continue;
            }

            try {
                if ($upsert && isset($doc['_id'])) {
                    $existing = $coll->findOne(['_id' => $doc['_id']]);
                    if ($existing) {
                        $coll->update(['_id' => $doc['_id']], $doc, false);
                        ++$updated;
                        continue;
                    }
                }

                $coll->insert($doc);
                ++$inserted;
            } catch (\Throwable $e) {
                ++$skipped;
                $errors[] = 'Index ' . $idx . ': ' . $e->getMessage();
            }
        }

        return [
            'inserted' => $inserted,
            'updated' => $updated,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }

    private function collection(string $dbName, string $collection)
    {
        return $this->system->tenantClient()->selectDB($dbName)->selectCollection($collection);
    }

    private function buildCursor($coll, array $criteria, array $sort, ?int $limit, int $skip, string $status)
    {
        if (!in_array($status, ['all', 'active', 'deleted'], true)) {
            $status = 'all';
        }

        $cursor = $coll->find($criteria);

        if ($status === 'deleted') {
            $cursor = $cursor->onlyTrashed();
        } elseif ($status === 'all') {
            $cursor = $cursor->withTrashed();
        }

        if ($sort) {
            $cursor = $cursor->sort($sort);
        }

        if ($skip > 0) {
            $cursor = $cursor->skip($skip);
        }

        if ($limit !== null && $limit > 0) {
            $cursor = $cursor->limit($limit);
        }

        return $cursor;
    }
}
