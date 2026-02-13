<?php

namespace BangronDB\Traits;

/**
 * Trait for handling event hooks in collections.
 * Supports beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove events.
 */
trait HooksTrait
{
    /**
     * Hooks storage: event name => list of callables.
     *
     * @var array<string,array<int,callable>>
     */
    protected array $hooks = [];

    /**
     * Get hooks configuration.
     */
    public function getHooks(): array
    {
        return $this->hooks;
    }

    /**
     * Register an event hook for this collection.
     * Events: beforeInsert, afterInsert, beforeUpdate, afterUpdate, beforeRemove, afterRemove.
     */
    public function on(string $event, callable $fn): void
    {
        if (!isset($this->hooks[$event])) {
            $this->hooks[$event] = [];
        }
        $this->hooks[$event][] = $fn;
    }

    /**
     * Remove hooks for an event. If $fn is null removes all listeners.
     */
    public function off(string $event, ?callable $fn = null): void
    {
        if (!isset($this->hooks[$event])) {
            return;
        }
        if ($fn === null) {
            unset($this->hooks[$event]);

            return;
        }
        foreach ($this->hooks[$event] as $k => $h) {
            if ($h === $fn) {
                unset($this->hooks[$event][$k]);
            }
        }
        $this->hooks[$event] = array_values($this->hooks[$event]);
    }

    /**
     * Apply hooks for a specific event.
     */
    protected function applyHooks(string $event, $data, $id = null): mixed
    {
        if (!empty($this->hooks[$event])) {
            foreach ($this->hooks[$event] as $hook) {
                try {
                    $result = $hook($data, $id);

                    if ($result === false) {
                        return false;
                    }

                    if (is_array($result)) {
                        $data = $result;
                    }
                } catch (\Throwable $e) {
                    // Log hook exception but don't let it prevent the operation
                    error_log("Hook exception in {$event}: " . $e->getMessage());
                    // Continue with other hooks
                }
            }
        }

        return $data;
    }

    /**
     * Apply before insert hooks to the document.
     */
    protected function applyBeforeInsertHooks(array $document): mixed
    {
        return $this->applyHooks('beforeInsert', $document);
    }

    /**
     * Apply after insert hooks with the document and insert ID.
     */
    protected function applyAfterInsertHooks(array $document, mixed $insertId): void
    {
        $this->applyHooks('afterInsert', $document, $insertId);
    }

    /**
     * Apply before update hooks to modify criteria/data.
     */
    protected function applyUpdateHooks(&$criteria, array &$data): void
    {
        if (!empty($this->hooks['beforeUpdate'])) {
            foreach ($this->hooks['beforeUpdate'] as $hook) {
                $ret = $hook($criteria, $data);
                if (is_array($ret)) {
                    if (isset($ret['criteria'])) {
                        $criteria = $ret['criteria'];
                    }
                    if (isset($ret['data'])) {
                        $data = $ret['data'];
                    }
                    // also support numeric-indexed [criteria,data]
                    if (isset($ret[0])) {
                        $criteria = $ret[0];
                    }
                    if (isset($ret[1])) {
                        $data = $ret[1];
                    }
                }
            }
        }
    }

    /**
     * Trigger after update hooks with original and updated document.
     */
    protected function triggerAfterUpdateHooks(array $originalDoc, array $updatedDocument): void
    {
        if (!empty($this->hooks['afterUpdate'])) {
            foreach ($this->hooks['afterUpdate'] as $hook) {
                try {
                    $hook($originalDoc, $updatedDocument);
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }
    }

    /**
     * Check if document should be removed based on before-remove hooks.
     */
    protected function shouldRemoveDocument(array $row): bool
    {
        $doc = $this->decodeStored($row['document']) ?: [];

        // Before remove hooks can veto by returning false
        if (!empty($this->hooks['beforeRemove'])) {
            foreach ($this->hooks['beforeRemove'] as $hook) {
                try {
                    $ret = $hook($doc);
                    if ($ret === false) {
                        return false;
                    }
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }

        return true;
    }

    /**
     * Trigger after remove hooks with the removed document.
     */
    protected function triggerAfterRemoveHooks(array $document): void
    {
        if (!empty($this->hooks['afterRemove'])) {
            foreach ($this->hooks['afterRemove'] as $hook) {
                try {
                    $hook($document);
                } catch (\Throwable $e) {
                    // Ignore hook errors
                }
            }
        }
    }
}
