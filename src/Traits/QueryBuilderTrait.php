<?php

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
     */
    public function _buildJsonWhere(array $criteria): string
    {
        $parts = [];
        foreach ($criteria as $key => $value) {
            $expr = $this->buildExpressionForKey($key, $value);
            $condition = $this->buildConditionForValue($expr, $value);
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
     */
    private function buildConditionForValue(string $expr, $value): string
    {
        if (is_array($value)) {
            return $this->buildOperatorCondition($expr, $value);
        }

        return $this->buildEqualityCondition($expr, $value);
    }

    /**
     * Build condition for operators ($gt, $gte, $lt, $lte, $in, $nin, $exists).
     */
    private function buildOperatorCondition(string $expr, array $operators): string
    {
        $conditions = [];

        foreach ($operators as $op => $v) {
            $condition = $this->buildSingleOperatorCondition($expr, $op, $v);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * Build condition for a single operator.
     */
    private function buildSingleOperatorCondition(string $expr, string $op, $value): ?string
    {
        switch ($op) {
            case '$gt':
                return $this->buildComparisonCondition($expr, '>', $value);
            case '$gte':
                return $this->buildComparisonCondition($expr, '>=', $value);
            case '$lt':
                return $this->buildComparisonCondition($expr, '<', $value);
            case '$lte':
                return $this->buildComparisonCondition($expr, '<=', $value);
            case '$in':
                return $this->buildInCondition($expr, $value, false);
            case '$nin':
                return $this->buildInCondition($expr, $value, true);
            case '$exists':
                return $value ? "{$expr} IS NOT NULL" : "{$expr} IS NULL";
            default:
                // unsupported operator - fallback to strict equality check
                return $this->buildEqualityCondition($expr, $value);
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
     * Build comparison condition (>, >=, <, <=).
     */
    private function buildComparisonCondition(string $expr, string $operator, $value): string
    {
        // If this is a searchable field, normalize for case-insensitive search
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = hash('sha256', $value);
            }
        }

        $val = is_numeric($value) ? $value : $this->database->connection->quote((string) $value);

        return "{$expr} {$operator} {$val}";
    }

    /**
     * Build IN/NOT IN condition.
     */
    private function buildInCondition(string $expr, array $values, bool $notIn): ?string
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

        $vals = [];
        foreach ($values as $item) {
            $vals[] = is_numeric($item) ? $item : $this->database->connection->quote((string) $item);
        }

        $operator = $notIn ? 'NOT IN' : 'IN';

        return "{$expr} {$operator} (" . implode(',', $vals) . ')';
    }

    /**
     * Build equality condition.
     */
    private function buildEqualityCondition(string $expr, $value): string
    {
        // If this is a searchable field, normalize for case-insensitive search
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = hash('sha256', $value);
            }
        }

        if (is_int($value) || is_float($value) || (is_string($value) && is_numeric($value))) {
            $val = $value;
        } elseif (is_bool($value)) {
            // Use numeric boolean representation for comparison
            $val = $value ? '1' : '0';
        } else {
            $val = $this->database->connection->quote((string) $value);
        }

        return "{$expr} = {$val}";
    }
}
