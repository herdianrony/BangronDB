<?php

namespace BangronDB\Exceptions;

/**
 * Exception thrown when validation fails.
 *
 * Used for schema validation, data validation, and input validation errors.
 */
class ValidationException extends BangronDBException
{
    /**
     * Create exception for schema validation failure.
     *
     * @param string $field   Field name that failed validation
     * @param string $rule    Validation rule that failed
     * @param mixed  $value   The invalid value
     * @param array  $context Additional context
     */
    public static function schemaValidationFailed(
        string $field,
        string $rule,
        $value,
        array $context = []
    ): self {
        $message = "Schema validation failed for field '{$field}': {$rule}";
        $context = array_merge($context, [
            'field' => $field,
            'rule' => $rule,
            'value' => $value,
        ]);

        return new self($message, 'SCHEMA_VALIDATION_FAILED', $context);
    }

    /**
     * Create exception for required field missing.
     *
     * @param string $field   Field name
     * @param array  $context Additional context
     */
    public static function requiredFieldMissing(string $field, array $context = []): self
    {
        $message = "Required field '{$field}' is missing";
        $context = array_merge($context, ['field' => $field]);

        return new self($message, 'REQUIRED_FIELD_MISSING', $context);
    }

    /**
     * Create exception for invalid type.
     *
     * @param string $field        Field name
     * @param string $expectedType Expected type
     * @param string $actualType   Actual type
     * @param array  $context      Additional context
     */
    public static function invalidType(
        string $field,
        string $expectedType,
        string $actualType,
        array $context = []
    ): self {
        $message = "Field '{$field}' must be of type '{$expectedType}', got '{$actualType}'";
        $context = array_merge($context, [
            'field' => $field,
            'expected_type' => $expectedType,
            'actual_type' => $actualType,
        ]);

        return new self($message, 'INVALID_TYPE', $context);
    }

    /**
     * Create exception for invalid name format.
     *
     * @param string $name    The invalid name
     * @param string $pattern Expected pattern
     * @param string $type    Type of name (database, collection, etc.)
     * @param array  $context Additional context
     */
    public static function invalidNameFormat(
        string $name,
        string $pattern,
        string $type = 'name',
        array $context = []
    ): self {
        $message = "Invalid {$type} '{$name}'. Must match pattern: {$pattern}";
        $context = array_merge($context, [
            'name' => $name,
            'pattern' => $pattern,
            'type' => $type,
        ]);

        return new self($message, 'INVALID_NAME_FORMAT', $context);
    }
}
