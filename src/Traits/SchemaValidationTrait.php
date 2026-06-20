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

            if (isset($rules['enum']) && !in_array($value, $rules['enum'], true)) {
                throw new ValidationException(
                    "Field '{$field}' must be one of: " . implode(', ', $rules['enum']),
                    'ENUM_VALIDATION_FAILED',
                    ['field' => $field, 'value' => $value, 'allowed' => $rules['enum']]
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
