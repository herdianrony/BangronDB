<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for managing collection change notifications and version tracking.
 *
 * Provides methods to track changes to collections using a metadata table,
 * enabling clients to detect when data has been modified.
 */
trait ChangeTrackingTrait
{
    /**
     * Notify that the collection has changed.
     *
     * Increments the version counter in the _meta table for this collection,
     * allowing external systems to detect data modifications.
     *
     * Optimized to use 2 queries instead of the original 3:
     * 1. SELECT id + document in a single query
     * 2. UPDATE or INSERT based on result
     */
    public function notifyChange(): void
    {
        try {
            // Single query to get both id and current version
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT id, document FROM _meta WHERE json_extract(document, '$._id') = ?",
                [$this->name]
            );
            $existing = $stmt->fetch(\PDO::FETCH_ASSOC);

            $currentVersion = 0;
            if ($existing) {
                $doc = json_decode($existing['document'], true);
                $currentVersion = $doc['version'] ?? 0;
            }
            $newVersion = $currentVersion + 1;

            $document = json_encode([
                '_id' => $this->name,
                'version' => $newVersion,
                'last_updated' => date('c'),
            ]);

            if ($existing) {
                // Update existing row using its primary key
                $this->database->queryExecutor->executeUpdate(
                    'UPDATE _meta SET document = ? WHERE id = ?',
                    [$document, $existing['id']]
                );
            } else {
                // Insert new entry
                $this->database->queryExecutor->executeUpdate(
                    'INSERT INTO _meta (document) VALUES (?)',
                    [$document]
                );
            }
        } catch (\BangronDB\QueryExecutionException $e) {
            // Silently fail if metadata table isn't ready or other DB issues
        }
    }

    /**
     * Get the current version/timestamp of the collection.
     *
     * Returns the version number and last updated timestamp from the metadata table.
     *
     * @return array{version: int, last_updated: string|null}
     */
    public function getLastModified(): array
    {
        try {
            $stmt = $this->database->queryExecutor->executeQuery("
                SELECT document FROM _meta WHERE json_extract(document, '\$._id') = ?
            ", [$this->name]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$result) {
                return ['version' => 0, 'last_updated' => null];
            }

            $document = json_decode($result['document'], true);

            return [
                'version' => $document['version'] ?? 0,
                'last_updated' => $document['last_updated'] ?? null,
            ];
        } catch (\BangronDB\QueryExecutionException $e) {
            return ['version' => 0, 'last_updated' => null];
        }
    }
}
