<?php

declare(strict_types=1);

namespace BangronDB\Traits;

use BangronDB\UtilArrayQuery;

/**
 * Trait for handling ID generation in collections.
 * Supports AUTO (UUID v4), MANUAL, and PREFIX modes.
 */
trait IdGeneratorTrait
{
    /** @var string ID generation mode */
    protected string $idMode = 'auto';

    protected ?string $idPrefix = null;
    protected ?string $idSuffix = null;
    protected int $idCounter = 0;

    public function setIdModeAuto(): self
    {
        $this->idMode = 'auto';
        $this->idPrefix = null;

        return $this;
    }

    public function setIdModeManual(): self
    {
        $this->idMode = 'manual';
        $this->idPrefix = null;

        return $this;
    }

    public function setIdModePrefix(string $prefix): self
    {
        $this->idMode = 'prefix';
        $this->idPrefix = $prefix;
        $this->_initializeCounter();

        return $this;
    }

    public function setPrefix(string $prefix): self
    {
        $this->idPrefix = $prefix;
        return $this;
    }

    public function setSuffix(string $suffix): self
    {
        $this->idSuffix = $suffix;
        return $this;
    }

    public function getIdMode(): string
    {
        return $this->idMode;
    }

    private function _initializeCounter(): void
    {
        if ($this->idPrefix) {
            $prefixPattern = $this->idPrefix . '-';
            $table = $this->database->quoteIdentifier($this->name);
            $sql = "SELECT document FROM {$table} ORDER BY id DESC LIMIT 1";

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
                $this->idCounter = 0;
            }
        }
    }

    protected function _generateId(): ?string
    {
        $id = null;

        switch ($this->idMode) {
            case 'prefix':
                $this->idCounter++;
                $id = $this->idPrefix . '-' . str_pad((string) $this->idCounter, 6, '0', STR_PAD_LEFT);
                break;
            case 'manual':
                return null;
            case 'auto':
            default:
                $id = UtilArrayQuery::generateId();
                break;
        }

        $prefix = ($this->idMode !== 'prefix') ? ($this->idPrefix ?? '') : '';
        $suffix = $this->idSuffix ?? '';

        return $prefix . $id . $suffix;
    }

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
