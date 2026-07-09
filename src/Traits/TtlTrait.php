<?php

declare(strict_types=1);

namespace BangronDB\Traits;

use BangronDB\Security\FieldValidator;

/**
 * Trait for Time-To-Live (TTL) document expiration.
 *
 * Enables automatic expiration of documents based on a timestamp field.
 * Unlike MongoDB's TTL index which runs as a background thread,
 * BangronDB's TTL is cleaned up explicitly via cleanExpired() or
 * automatically when querying with applyTtlFilter().
 *
 * Typical use cases:
 * - Session storage (auto-expire after inactivity)
 * - OTP / verification codes (expire after N seconds)
 * - Cache entries (TTL-based eviction)
 * - Temporary tokens
 *
 * @example
 *   $collection->enableTtl('expires_at');
 *   $collection->insert(['data' => 'temp', 'expires_at' => time() + 3600]);
 *   // ... later:
 *   $collection->cleanExpired(); // removes all documents past their TTL
 */
trait TtlTrait
{
    /**
     * Whether TTL is enabled for this collection.
     */
    protected bool $ttlEnabled = false;

    /**
     * The field name used to store the expiration timestamp.
     * Documents where this field is a unix timestamp <= current time
     * are considered expired.
     */
    protected string $ttlField = '_ttl_expires_at';

    /**
     * Default TTL in seconds. If set, documents without an explicit
     * TTL field will be assigned one at insert time.
     */
    protected ?int $defaultTtlSeconds = null;

    /**
     * Enable TTL for this collection.
     *
     * @param string   $field            The field name that holds the expiration timestamp (unix epoch)
     * @param int|null $defaultTtlSeconds Optional default TTL in seconds. If provided, documents
     *                                   inserted without this field will automatically get one.
     * @return self
     *
     * @throws \InvalidArgumentException If field name is invalid or TTL is negative
     *
     * @example
     *   // Use 'expires_at' field, no default TTL
     *   $collection->enableTtl('expires_at');
     *
     *   // Use 'expires_at' field with 3600s default
     *   $collection->enableTtl('expires_at', 3600);
     */
    public function enableTtl(string $field = '_ttl_expires_at', ?int $defaultTtlSeconds = null): self
    {
        FieldValidator::validateFieldName($field);

        if ($defaultTtlSeconds !== null && $defaultTtlSeconds < 1) {
            throw new \InvalidArgumentException(
                'Default TTL must be a positive integer (seconds). ' .
                "Got: {$defaultTtlSeconds}. Use null to disable default TTL."
            );
        }

        $this->ttlEnabled = true;
        $this->ttlField = $field;
        $this->defaultTtlSeconds = $defaultTtlSeconds;

        return $this;
    }

    /**
     * Disable TTL for this collection.
     *
     * @return self
     */
    public function disableTtl(): self
    {
        $this->ttlEnabled = false;
        $this->defaultTtlSeconds = null;

        return $this;
    }

    /**
     * Check if TTL is enabled.
     */
    public function isTtlEnabled(): bool
    {
        return $this->ttlEnabled;
    }

    /**
     * Get the TTL field name.
     */
    public function getTtlField(): string
    {
        return $this->ttlField;
    }

    /**
     * Get the default TTL in seconds.
     */
    public function getDefaultTtl(): ?int
    {
        return $this->defaultTtlSeconds;
    }

    /**
     * Set the default TTL in seconds.
     *
     * @param int|null $seconds Default TTL in seconds, or null to disable
     * @return self
     *
     * @throws \InvalidArgumentException If seconds is not a positive integer
     */
    public function setDefaultTtl(?int $seconds): self
    {
        if ($seconds !== null && $seconds < 1) {
            throw new \InvalidArgumentException(
                'Default TTL must be a positive integer (seconds). ' .
                "Got: {$seconds}. Use null to disable default TTL."
            );
        }

        $this->defaultTtlSeconds = $seconds;

        return $this;
    }

    /**
     * Apply TTL to a document before insert.
     *
     * If TTL is enabled and the document doesn't have the TTL field set,
     * and a default TTL is configured, this method adds the expiration timestamp.
     *
     * @param array $document The document to apply TTL to
     * @return array The document with TTL applied if applicable
     */
    public function applyTtlOnInsert(array $document): array
    {
        if (!$this->ttlEnabled || $this->defaultTtlSeconds === null) {
            return $document;
        }

        // Only apply default TTL if the field is not already set
        if (!isset($document[$this->ttlField])) {
            $document[$this->ttlField] = time() + $this->defaultTtlSeconds;
        }

        return $document;
    }

    /**
     * Remove all expired documents from the collection.
     *
     * A document is considered expired if its TTL field exists and
     * its value is less than or equal to the current time.
     *
     * @return int Number of expired documents removed
     *
     * @example
     *   $removed = $collection->cleanExpired();
     *   echo "Cleaned {$removed} expired documents";
     */
    public function cleanExpired(): int
    {
        if (!$this->ttlEnabled) {
            return 0;
        }

        $now = time();
        $ttlField = $this->ttlField;

        return $this->remove([
            $ttlField => ['$lte' => $now],
        ]);
    }

    /**
     * Get the count of expired (but not yet cleaned) documents.
     *
     * Useful for monitoring and deciding when to run cleanExpired().
     *
     * @return int Number of expired documents
     */
    public function expiredCount(): int
    {
        if (!$this->ttlEnabled) {
            return 0;
        }

        $now = time();

        return $this->count([
            $this->ttlField => ['$lte' => $now],
        ]);
    }

    /**
     * Get TTL statistics for the collection.
     *
     * Returns information about current TTL state including
     * expired count, time until next expiration, etc.
     *
     * @return array{TTL status information}
     *
     * @example
     *   $stats = $collection->ttlStats();
     *   echo "Expired: {$stats['expired_count']}";
     *   echo "Next expires in: {$stats['next_expires_in_seconds']}s";
     */
    public function ttlStats(): array
    {
        if (!$this->ttlEnabled) {
            return [
                'ttl_enabled' => false,
                'message' => 'TTL is not enabled for this collection. Call enableTtl() first.',
            ];
        }

        $now = time();
        $expiredCount = 0;
        $nextExpiry = PHP_INT_MAX;
        $totalWithTtl = 0;
        $totalWithoutTtl = 0;

        foreach ($this->find([]) as $doc) {
            if (isset($doc[$this->ttlField]) && is_numeric($doc[$this->ttlField])) {
                ++$totalWithTtl;
                $expiry = (int) $doc[$this->ttlField];

                if ($expiry <= $now) {
                    ++$expiredCount;
                } elseif ($expiry < $nextExpiry) {
                    $nextExpiry = $expiry;
                }
            } else {
                ++$totalWithoutTtl;
            }
        }

        return [
            'ttl_enabled' => true,
            'ttl_field' => $this->ttlField,
            'default_ttl_seconds' => $this->defaultTtlSeconds,
            'total_documents' => $totalWithTtl + $totalWithoutTtl,
            'documents_with_ttl' => $totalWithTtl,
            'documents_without_ttl' => $totalWithoutTtl,
            'expired_count' => $expiredCount,
            'active_count' => $totalWithTtl - $expiredCount,
            'next_expires_at' => $nextExpiry < PHP_INT_MAX ? $nextExpiry : null,
            'next_expires_in_seconds' => $nextExpiry < PHP_INT_MAX ? max(0, $nextExpiry - $now) : null,
            'checked_at' => $now,
        ];
    }
}