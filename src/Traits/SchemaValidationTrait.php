<?php

declare(strict_types=1);

namespace BangronDB\Traits;

use BangronDB\Exceptions\ValidationException;
use BangronDB\Security\FieldValidator;

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
     */
    public function setSchema(array $schema): self
    {
        $this->schema = $this->sanitizeSchemaRules($schema);
        return $this;
    }

    /**
     * Get defined schema rules.
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Validate a document against the schema.
     *
     * @throws \Exception
     */
    public function validate(array $document): bool
    {
        if (empty($this->schema)) {
            return true;
        }

        foreach ($this->schema as $field => $rules) {
            $value = $document[$field] ?? null;

            if (($rules['required'] ?? false) && !isset($document[$field])) {
                throw ValidationException::requiredFieldMissing($field);
            }

            if (!isset($document[$field])) {
                continue;
            }

            if (isset($rules['type'])) {
                $this->validateType($field, $value, $rules['type']);
            }

            // enum support: 'enum' or 'options' (SSOT enhanced)
            $enum = $rules['enum'] ?? $rules['options'] ?? null;
            if (is_array($enum) && !in_array($value, $enum, true)) {
                throw new ValidationException(
                    "Field '{$field}' must be one of: " . implode(', ', $enum),
                    'ENUM_VALIDATION_FAILED',
                    ['field' => $field, 'value' => $value, 'allowed' => $enum]
                );
            }

            $this->validateRange($field, $value, $rules);

            if (isset($rules['regex']) && is_string($value) && !preg_match($rules['regex'], $value)) {
                throw new ValidationException(
                    "Field '{$field}' does not match pattern.",
                    'PATTERN_VALIDATION_FAILED',
                    ['field' => $field, 'pattern' => $rules['regex']]
                );
            }
        }

        return true;
    }

    /**
     * Validate `unique` schema constraints against existing documents.
     *
     * Unlike validate(), this is collection-aware (it queries the collection),
     * so it lives separately and is invoked by the insert/update paths. For
     * each field declared with `'unique' => true`, it checks that no OTHER
     * document already holds the same value.
     *
     * @param array<string, mixed> $document  The document being inserted/updated.
     * @param string|null          $excludeId  _id to ignore (the document being updated).
     *
     * @throws ValidationException If a unique value already exists.
     */
    public function validateUnique(array $document, ?string $excludeId = null): bool
    {
        if (empty($this->schema)) {
            return true;
        }

        foreach ($this->schema as $field => $rules) {
            if (!is_array($rules) || empty($rules['unique'])) {
                continue;
            }

            // Only check fields actually present in the document (partial
            // updates that don't touch a unique field can't create a duplicate).
            if (!array_key_exists($field, $document)) {
                continue;
            }

            $value = $document[$field];
            // Null/absent values are not subject to uniqueness (SQL-like NULL).
            if ($value === null) {
                continue;
            }

            $existing = $this->findOne([$field => $value]);
            if ($existing !== null && ($excludeId === null || ($existing['_id'] ?? null) !== $excludeId)) {
                throw ValidationException::uniqueConstraintViolation($field, $value);
            }
        }

        return true;
    }

    /**
     * Sanitize schema rules before use.
     */
    protected function sanitizeSchemaRules(array $schema): array
    {
        foreach ($schema as $_field => &$rules) {
            if (!is_array($rules)) {
                continue;
            }

            if (isset($rules['regex']) && is_string($rules['regex'])) {
                $rules['regex'] = FieldValidator::sanitizeSchemaRegexPattern($rules['regex']);
            }
        }
        unset($rules);

        return $schema;
    }

    /**
     * Validate field type.
     */
    protected function validateType(string $field, $value, string $type): void
    {
        // Normalize enhanced UI types to native validation
        $type = strtolower($type);
        $isValid = match ($type) {
            // string family – UI enhanced types
            'string', 'text', 'email', 'password', 'url', 'slug',
            'date', 'datetime', 'time',
            'relation', 'enum' => is_string($value),
            // int family
            'int', 'integer' => is_int($value),
            // float family
            'float', 'double', 'number', 'decimal' => is_float($value) || is_int($value),
            // bool family
            'bool', 'boolean', 'checkbox', 'switch' => is_bool($value),
            // array family – tags is array of strings
            'array', 'tags' => is_array($value),
            // object family
            'object', 'json' => is_object($value) || (is_array($value) && (array_keys($value) !== range(0, count($value) - 1))),
            // unknown / custom – allow pass-through (for forward compatibility with UI metadata types)
            default => true,
        };

        if (!$isValid) {
            $actualType = gettype($value);
            throw ValidationException::invalidType($field, $type, $actualType);
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
            throw new ValidationException(
                "Field '{$field}' {$msg}",
                'RANGE_VALIDATION_FAILED',
                ['field' => $field, 'value' => $value, 'min' => $rules['min']]
            );
        }

        if (isset($rules['max']) && $checkValue > $rules['max']) {
            $msg = is_numeric($value) ? "must be at most {$rules['max']}." : "length must be at most {$rules['max']}.";
            throw new ValidationException(
                "Field '{$field}' {$msg}",
                'RANGE_VALIDATION_FAILED',
                ['field' => $field, 'value' => $value, 'max' => $rules['max']]
            );
        }
    }
}
