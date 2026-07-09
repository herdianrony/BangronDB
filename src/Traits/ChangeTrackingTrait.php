<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for managing collection change notifications and version tracking.
 */
trait ChangeTrackingTrait
{
    /**
     * Notify that the collection has changed.
     */
    public function notifyChange(): void
    {
        try {
            $this->database->touchCollectionMetadata($this->name);
        } catch (\BangronDB\QueryExecutionException | \RuntimeException | \InvalidArgumentException $e) {
            // Silently fail if metadata table isn't ready or other DB issues
        }
    }

    /**
     * Get the current version/timestamp of the collection.
     *
     * @return array{version:int,last_updated:string|null}
     */
    public function getLastModified(): array
    {
        try {
            return $this->database->getCollectionMetadata($this->name);
        } catch (\BangronDB\QueryExecutionException | \RuntimeException | \InvalidArgumentException $e) {
            return ['version' => 0, 'last_updated' => null];
        }
    }
}
