<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for building SQL queries from MongoDB-like criteria.
 * Supports comparison operators ($gt, $gte, $lt, $lte, $in, $nin, $exists).
 */
trait QueryBuilderTrait
{

    /**
     * Detect simple equality criteria (no $ operators).
     */
    private function _isSimpleEqualityCriteria($criteria): bool
    {
        if (!is_array($criteria)) {
            return false;
        }
        foreach ($criteria as $k => $v) {
            if (strpos($k, '$') === 0) {
                return false;
            }
            if (is_array($v)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determine if a criteria array can be translated to a JSON-based SQL WHERE clause.
     * Supports simple equality and a limited set of operators: $gt, $gte, $lt, $lte, $in, $nin, $exists.
     */
    public function _canTranslateToJsonWhere($criteria): bool
    {
        if (!is_array($criteria)) {
            return false;
        }

        $allowedOps = ['$gt', '$gte', '$lt', '$lte', '$exists'];

        foreach ($criteria as $k => $v) {
            if (strpos($k, '$') === 0) {
                return false;
            } // top-level logical operators not supported here

            if (is_array($v)) {
                // operator-style value expected
                foreach ($v as $op => $val) {
                    if (strpos($op, '$') !== 0) {
                        return false;
                    }
                    if (!in_array($op, $allowedOps, true)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * Build SQL WHERE clause using json_extract for simple equality criteria.
     *
     * @param array $criteria Query criteria
     * @param array &$params  Parameters collected for prepared statements
     *
     * @return string SQL WHERE clause with ? placeholders
     */
    public function _buildJsonWhere(array $criteria, array &$params = []): string
    {
        $parts = [];
        foreach ($criteria as $key => $value) {
            $expr = $this->buildExpressionForKey($key, $value);
            $condition = $this->buildConditionForValue($expr, $value, $params);
            $parts[] = $condition;
        }

        return implode(' AND ', $parts);
    }

    /**
     * Build expression for a given key considering searchable fields.
     */
    private function buildExpressionForKey(string $key, $value): string
    {
        $path = '$.' . str_replace("'", "\\'", $key);

        // If this key is configured as searchable, use the searchable column
        if (isset($this->searchableFields[$key])) {
            return '`' . $this->getSearchablePrefix() . $key . '`';
        }

        return "json_extract(document, '" . $path . "')";
    }

    /**
     * Build condition for a given expression and value.
     *
     * @param string $expr   SQL expression for the field
     * @param mixed  $value  Condition value or operator array
     * @param array  &$params Parameters collected for prepared statements
     */
    private function buildConditionForValue(string $expr, $value, array &$params): string
    {
        if (is_array($value)) {
            return $this->buildOperatorCondition($expr, $value, $params);
        }

        return $this->buildEqualityCondition($expr, $value, $params);
    }

    /**
     * Build condition for operators ($gt, $gte, $lt, $lte, $in, $nin, $exists).
     *
     * @param string $expr      SQL expression for the field
     * @param array  $operators Operator-value pairs
     * @param array  &$params   Parameters collected for prepared statements
     */
    private function buildOperatorCondition(string $expr, array $operators, array &$params): string
    {
        $conditions = [];

        foreach ($operators as $op => $v) {
            $condition = $this->buildSingleOperatorCondition($expr, $op, $v, $params);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Build condition for a single operator.
     *
     * @param string $expr   SQL expression for the field
     * @param string $op     Operator name (e.g. '$gt')
     * @param mixed  $value  Operator value
     * @param array  &$params Parameters collected for prepared statements
     */
    private function buildSingleOperatorCondition(string $expr, string $op, $value, array &$params): ?string
    {
        switch ($op) {
            case '$gt':
                return $this->buildComparisonCondition($expr, '>', $value, $params);
            case '$gte':
                return $this->buildComparisonCondition($expr, '>=', $value, $params);
            case '$lt':
                return $this->buildComparisonCondition($expr, '<', $value, $params);
            case '$lte':
                return $this->buildComparisonCondition($expr, '<=', $value, $params);
            case '$in':
                return $this->buildInCondition($expr, $value, false, $params);
            case '$nin':
                return $this->buildInCondition($expr, $value, true, $params);
            case '$exists':
                return $value ? "{$expr} IS NOT NULL" : "{$expr} IS NULL";
            default:
                // unsupported operator - fallback to strict equality check
                return $this->buildEqualityCondition($expr, $value, $params);
        }
    }

    /**
     * Check if an expression refers to a searchable field and extract the field name.
     */
    private function isSearchableExpression(string $expr, &$fieldName = null): bool
    {
        $prefix = $this->getSearchablePrefix();
        $clean = trim($expr, '`');
        if (strpos($clean, $prefix) === 0) {
            $fieldName = substr($clean, strlen($prefix));

            return isset($this->searchableFields[$fieldName]);
        }

        return false;
    }

    /**
     * Build comparison condition (>, >=, <, <=) using prepared statement placeholder.
     *
     * @param string $expr     SQL expression for the field
     * @param string $operator Comparison operator
     * @param mixed  $value    Value to compare against
     * @param array  &$params  Parameters collected for prepared statements
     */
    private function buildComparisonCondition(string $expr, string $operator, $value, array &$params): string
    {
        // If this is a searchable field, normalize for case-insensitive search
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = hash('sha256', $value);
            }
        }

        $params[] = $value;

        return "{$expr} {$operator} ?";
    }

    /**
     * Build IN/NOT IN condition using prepared statement placeholders.
     *
     * @param string $expr   SQL expression for the field
     * @param array  $values Values to match against
     * @param bool   $notIn  Whether to use NOT IN instead of IN
     * @param array  &$params Parameters collected for prepared statements
     */
    private function buildInCondition(string $expr, array $values, bool $notIn, array &$params): ?string
    {
        if (empty($values)) {
            return $notIn ? null : '0'; // Return false condition for empty IN
        }

        // If this is a searchable field with hashing, hash the values
        if ($this->isSearchableExpression($expr, $field)) {
            $values = array_map(function ($v) use ($field) {
                if (is_array($v)) {
                    return $v;
                }
                $normalized = strtolower((string) $v);

                return $this->searchableFields[$field]['hash'] ? hash('sha256', $normalized) : $normalized;
            }, $values);
        }

        $placeholders = [];
        foreach ($values as $item) {
            $params[] = $item;
            $placeholders[] = '?';
        }

        $operator = $notIn ? 'NOT IN' : 'IN';

        return "{$expr} {$operator} (" . implode(',', $placeholders) . ')';
    }

    /**
     * Build equality condition using prepared statement placeholder.
     *
     * @param string $expr   SQL expression for the field
     * @param mixed  $value  Value to compare against
     * @param array  &$params Parameters collected for prepared statements
     */
    private function buildEqualityCondition(string $expr, $value, array &$params): string
    {
        // If this is a searchable field, normalize for case-insensitive search
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = hash('sha256', $value);
            }
        }

        // Convert booleans to integer for SQL comparison
        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $params[] = $value;

        return "{$expr} = ?";
    }
}
