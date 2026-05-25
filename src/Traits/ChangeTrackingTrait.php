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
     */
    public function notifyChange(): void
    {
        try {
            // Check if metadata already exists and get current version
            $stmt = $this->database->queryExecutor->executeQuery(
                "SELECT document FROM _meta WHERE json_extract(document, '$._id') = ?",
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
                // Update existing - need to get id first
                $stmt = $this->database->queryExecutor->executeQuery(
                    "SELECT id FROM _meta WHERE json_extract(document, '$._id') = ?",
                    [$this->name]
                );
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $this->database->queryExecutor->executeUpdate(
                        'UPDATE _meta SET document = ? WHERE id = ?',
                        [$document, $row['id']]
                    );
                }
            } else {
                // Insert new
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
