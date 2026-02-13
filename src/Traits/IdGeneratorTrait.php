<?php

namespace BangronDB\Traits;

use BangronDB\UtilArrayQuery;

/**
 * Trait for handling ID generation in collections.
 * Supports AUTO (UUID v4), MANUAL, and PREFIX modes.
 */
trait IdGeneratorTrait
{
    /**
     * @var string ID generation mode
     */
    protected $idMode = 'auto';

    /**
     * @var string|null ID prefix
     */
    protected ?string $idPrefix = null;

    /**
     * @var string|null ID suffix
     */
    protected ?string $idSuffix = null;

    /**
     * @var int Auto-increment counter (for prefix mode)
     */
    protected int $idCounter = 0;

    /**
     * Set ID generation mode to AUTO (UUID v4).
     */
    public function setIdModeAuto(): self
    {
        $this->idMode = 'auto';
        $this->idPrefix = null;

        return $this;
    }

    /**
     * Set ID generation mode to MANUAL (use provided _id).
     */
    public function setIdModeManual(): self
    {
        $this->idMode = 'manual';
        $this->idPrefix = null;

        return $this;
    }

    /**
     * Set ID generation mode to PREFIX (auto with counter).
     *
     * @param string $prefix Prefix for the counter (e.g., 'USR', 'ORD')
     */
    public function setIdModePrefix(string $prefix): self
    {
        $this->idMode = 'prefix';
        $this->idPrefix = $prefix;
        $this->_initializeCounter();

        return $this;
    }

    /**
     * Set a general prefix for all generated IDs.
     */
    public function setPrefix(string $prefix): self
    {
        $this->idPrefix = $prefix;
        return $this;
    }

    /**
     * Set a general suffix for all generated IDs.
     */
    public function setSuffix(string $suffix): self
    {
        $this->idSuffix = $suffix;
        return $this;
    }

    /**
     * Get current ID mode.
     */
    public function getIdMode(): string
    {
        return $this->idMode;
    }

    /**
     * Initialize counter for prefix mode.
     */
    private function _initializeCounter(): void
    {
        // Get highest number from existing IDs with this prefix
        if ($this->idPrefix) {
            $prefixPattern = $this->idPrefix . '-';
            $sql = "SELECT document FROM {$this->name} ORDER BY id DESC LIMIT 1";

            try {
                $stmt = $this->database->connection->query($sql);
                $result = $stmt->fetch(\PDO::FETCH_ASSOC);

                if ($result) {
                    $doc = $this->decodeStored($result['document']);
                    if (isset($doc['_id']) && strpos($doc['_id'], $prefixPattern) === 0) {
                        $parts = explode('-', $doc['_id']);
                        $lastNum = (int) end($parts);
                        $this->idCounter = $lastNum;
                    }
                }
            } catch (\Exception $e) {
                // Table might not exist yet, initialize to 0
                $this->idCounter = 0;
            }
        }
    }

    /**
     * Generate ID based on current mode.
     */
    protected function _generateId(): ?string
    {
        $id = null;

        switch ($this->idMode) {
            case 'prefix':
                $this->idCounter++;
                $id = $this->idPrefix . '-' . str_pad($this->idCounter, 6, '0', STR_PAD_LEFT);
                break;

            case 'manual':
                return null;

            case 'auto':
            default:
                $id = UtilArrayQuery::generateId();
                break;
        }

        // Apply general prefix (if not in prefix mode where it's already part of the ID)
        // Actually, if someone sets ModePrefix AND setPrefix, they might want both.
        // But let's assume if they use setPrefix/setSuffix, it wraps the result.

        $prefix = ($this->idMode !== 'prefix') ? ($this->idPrefix ?? '') : '';
        $suffix = $this->idSuffix ?? '';

        return $prefix . $id . $suffix;
    }

    /**
     * Ensure document has proper _id based on current mode.
     */
    protected function ensureDocumentId(array $document): mixed
    {
        if (!isset($document['_id'])) {
            $generatedId = $this->_generateId();

            if ($this->idMode === 'manual' && $generatedId === null) {
                return false;
            }

            $document['_id'] = $generatedId;
        }

        return $document;
    }
}
