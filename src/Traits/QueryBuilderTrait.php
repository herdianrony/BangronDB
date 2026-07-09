<?php

declare(strict_types=1);

namespace BangronDB\Traits;

use BangronDB\Security\FieldValidator;

/**
 * Trait for building SQL queries from MongoDB-like criteria.
 * Supports comparison operators ($gt, $gte, $lt, $lte, $in, $nin, $exists).
 */
trait QueryBuilderTrait
{
    /**
     * Determine if a criteria array can be translated to a JSON-based SQL WHERE clause.
     */
    public function _canTranslateToJsonWhere(mixed $criteria): bool
    {
        if (!is_array($criteria)) {
            return false;
        }

        $allowedOps = ['$gt', '$gte', '$lt', '$lte', '$in', '$nin', '$exists'];

        foreach ($criteria as $k => $v) {
            if (strpos((string) $k, '$') === 0) {
                return false;
            }

            FieldValidator::validateFieldName((string) $k);

            if (is_array($v)) {
                foreach ($v as $op => $_val) {
                    if (strpos((string) $op, '$') !== 0) {
                        return false;
                    }
                    if (!in_array($op, $allowedOps, true)) {
                        return false;
                    }
                    // For $in/$nin on a searchable field:
                    //  - HASHED scalar fields MUST use the SQL fast-path: it hashes
                    //    each value through the blind index (buildInCondition). The
                    //    in-memory fallback compares a plaintext query value against
                    //    the hashed stored value and would never match.
                    //  - NON-HASHED fields (which may store comma-joined arrays) keep
                    //    the in-memory fallback, which supports array membership.
                    if (
                        isset($this->searchableFields[(string) $k])
                        && in_array($op, ['$in', '$nin'], true)
                        && empty($this->searchableFields[(string) $k]['hash'])
                    ) {
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
     * @param array<string,mixed> $criteria
     * @param array<int,mixed>    $params
     */
    public function _buildJsonWhere(array $criteria, array &$params = []): string
    {
        $parts = [];
        foreach ($criteria as $key => $value) {
            $expr = $this->buildExpressionForKey((string) $key);
            $parts[] = $this->buildConditionForValue($expr, $value, $params);
        }

        return implode(' AND ', $parts);
    }

    /**
     * Build expression for a given key considering searchable fields.
     */
    private function buildExpressionForKey(string $key): string
    {
        FieldValidator::validateFieldName($key);
        $path = '$.' . str_replace("'", "''", $key);

        if (isset($this->searchableFields[$key])) {
            return '`' . str_replace('`', '``', $this->buildSearchableColumnName($key)) . '`';
        }

        return "json_extract(document, '" . $path . "')";
    }

    /**
     * @param array<int,mixed> $params
     */
    private function buildConditionForValue(string $expr, mixed $value, array &$params): string
    {
        if (is_array($value)) {
            return $this->buildOperatorCondition($expr, $value, $params);
        }

        return $this->buildEqualityCondition($expr, $value, $params);
    }

    /**
     * @param array<string,mixed> $operators
     * @param array<int,mixed>    $params
     */
    private function buildOperatorCondition(string $expr, array $operators, array &$params): string
    {
        $conditions = [];

        foreach ($operators as $op => $v) {
            $condition = $this->buildSingleOperatorCondition($expr, (string) $op, $v, $params);
            if ($condition) {
                $conditions[] = $condition;
            }
        }

        return implode(' AND ', $conditions);
    }

    /**
     * @param array<int,mixed> $params
     */
    private function buildSingleOperatorCondition(string $expr, string $op, mixed $value, array &$params): ?string
    {
        return match ($op) {
            '$gt' => $this->buildComparisonCondition($expr, '>', $value, $params),
            '$gte' => $this->buildComparisonCondition($expr, '>=', $value, $params),
            '$lt' => $this->buildComparisonCondition($expr, '<', $value, $params),
            '$lte' => $this->buildComparisonCondition($expr, '<=', $value, $params),
            '$in' => is_array($value) ? $this->buildInCondition($expr, $value, false, $params) : null,
            '$nin' => is_array($value) ? $this->buildInCondition($expr, $value, true, $params) : null,
            '$exists' => $value ? "{$expr} IS NOT NULL" : "{$expr} IS NULL",
            default => $this->buildEqualityCondition($expr, $value, $params),
        };
    }

    /**
     * Check if an expression refers to a searchable field and extract the field name.
     */
    private function isSearchableExpression(string $expr, ?string &$fieldName = null): bool
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
     * @param array<int,mixed> $params
     */
    private function buildComparisonCondition(string $expr, string $operator, mixed $value, array &$params): string
    {
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = $this->hashSearchableValue($value);
            }
        }

        $params[] = $value;

        return "{$expr} {$operator} ?";
    }

    /**
     * @param array<int,mixed> $values
     * @param array<int,mixed> $params
     */
    private function buildInCondition(string $expr, array $values, bool $notIn, array &$params): ?string
    {
        if (empty($values)) {
            return $notIn ? null : '0';
        }

        foreach ($values as $item) {
            if (is_array($item)) {
                throw new \InvalidArgumentException('$in/$nin values must not contain nested arrays');
            }
        }

        if ($this->isSearchableExpression($expr, $field)) {
            $values = array_map(function ($v) use ($field) {
                $normalized = strtolower((string) $v);

                return $this->searchableFields[$field]['hash'] ? $this->hashSearchableValue($normalized) : $normalized;
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
     * @param array<int,mixed> $params
     */
    private function buildEqualityCondition(string $expr, mixed $value, array &$params): string
    {
        if ($this->isSearchableExpression($expr, $field)) {
            $value = strtolower((string) $value);
            if ($this->searchableFields[$field]['hash']) {
                $value = $this->hashSearchableValue($value);
            }
        }

        if (is_bool($value)) {
            $value = $value ? 1 : 0;
        }

        $params[] = $value;

        return "{$expr} = ?";
    }
}
