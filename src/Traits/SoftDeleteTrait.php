<?php

namespace BangronDB\Traits;

/**
 * Trait for soft deletes.
 */
trait SoftDeleteTrait
{
    /**
     * @var bool Whether soft deletes are enabled for this collection.
     */
    protected bool $softDeletesEnabled = false;

    /**
     * @var string The field used to store deletion timestamp.
     */
    protected string $deletedAtField = '_deleted_at';

    /**
     * Enable or disable soft deletes.
     *
     * @param bool $enabled
     * @return self
     */
    public function useSoftDeletes(bool $enabled = true): self
    {
        $this->softDeletesEnabled = $enabled;
        return $this;
    }

    /**
     * Check if soft deletes are enabled.
     *
     * @return bool
     */
    public function softDeletesEnabled(): bool
    {
        return $this->softDeletesEnabled;
    }

    /**
     * Get the field used for soft deletes.
     *
     * @return string
     */
    public function getDeletedAtField(): string
    {
        return $this->deletedAtField;
    }

    /**
     * Set the field used for soft deletes.
     *
     * @param string $field
     * @return self
     */
    public function setDeletedAtField(string $field): self
    {
        $this->deletedAtField = $field;
        return $this;
    }

    /**
     * Restore soft-deleted documents.
     *
     * @param array $criteria
     * @return int Number of restored documents
     */
    public function restore($criteria): int
    {
        return $this->update($criteria, ['$unset' => [$this->deletedAtField => 1]]);
    }

    /**
     * Apply soft delete filter to criteria.
     *
     * @param mixed $criteria
     * @param bool $withTrashed
     * @param bool $onlyTrashed
     * @return mixed Modified criteria
     */
    protected function applySoftDeleteFilter($criteria, bool $withTrashed = false, bool $onlyTrashed = false)
    {
        if (!$this->softDeletesEnabled || $withTrashed) {
            return $criteria;
        }

        if ($criteria === null) {
            $criteria = [];
        }

        if ($onlyTrashed) {
            $criteria[$this->deletedAtField] = ['$exists' => true];
        } else {
            // Default: exclude deleted
            $criteria[$this->deletedAtField] = ['$exists' => false];
        }

        return $criteria;
    }
}
