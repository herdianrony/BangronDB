<?php

namespace BangronDB\Traits;

/**
 * Trait for schema validation.
 */
trait SchemaValidationTrait
{
    /**
     * @var array Defined schema rules.
     */
    protected array $schema = [];

    /**
     * Set schema validation rules.
     *
     * @param array $schema
     * @return self
     */
    public function setSchema(array $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    /**
     * Get defined schema rules.
     *
     * @return array
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Validate a document against the schema.
     *
     * @param array $document
     * @throws \Exception
     * @return bool
     */
    public function validate(array $document): bool
    {
        if (empty($this->schema)) {
            return true;
        }

        foreach ($this->schema as $field => $rules) {
            $value = $document[$field] ?? null;

            // Check required
            if (($rules['required'] ?? false) && !isset($document[$field])) {
                throw new \Exception("Field '{$field}' is required.");
            }

            if (!isset($document[$field])) {
                continue;
            }

            // Check type
            if (isset($rules['type'])) {
                $this->validateType($field, $value, $rules['type']);
            }

            // Check enum
            if (isset($rules['enum']) && !in_array($value, $rules['enum'])) {
                throw new \Exception("Field '{$field}' must be one of: " . implode(', ', $rules['enum']));
            }

            // Check min/max
            $this->validateRange($field, $value, $rules);

            // Check regex
            if (isset($rules['regex']) && is_string($value) && !preg_match($rules['regex'], $value)) {
                throw new \Exception("Field '{$field}' does not match pattern.");
            }
        }

        return true;
    }

    /**
     * Validate field type.
     */
    protected function validateType(string $field, $value, string $type): void
    {
        $isValid = match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value) || (is_array($value) && (array_keys($value) !== range(0, count($value) - 1))),
            default => true,
        };

        if (!$isValid) {
            throw new \Exception("Field '{$field}' must be of type '{$type}'.");
        }
    }

    /**
     * Validate numeric range or string/array length.
     */
    protected function validateRange(string $field, $value, array $rules): void
    {
        $checkValue = $value;
        if (is_string($value)) {
            $checkValue = strlen($value);
        } elseif (is_array($value)) {
            $checkValue = count($value);
        }

        if (isset($rules['min']) && $checkValue < $rules['min']) {
            $msg = is_numeric($value) ? "must be at least {$rules['min']}." : "length must be at least {$rules['min']}.";
            throw new \Exception("Field '{$field}' {$msg}");
        }

        if (isset($rules['max']) && $checkValue > $rules['max']) {
            $msg = is_numeric($value) ? "must be at most {$rules['max']}." : "length must be at most {$rules['max']}.";
            throw new \Exception("Field '{$field}' {$msg}");
        }
    }
}
