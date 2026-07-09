<?php

declare(strict_types=1);

namespace BangronDB\Security;

use BangronDB\Config;
use BangronDB\Database;

/**
 * Security auditor for BangronDB.
 *
 * Provides security configuration validation, encryption audit,
 * and actionable security recommendations. Part of Prioritas 4
 * from the BangronDB roadmap — strengthening existing security
 * features rather than adding new encryption algorithms.
 *
 * @example
 *   $audit = SecurityAuditor::audit($collection);
 *   if (!$audit['encryption']['is_secure']) {
 *       foreach ($audit['recommendations'] as $rec) {
 *           echo "Security issue: {$rec}\n";
 *       }
 *   }
 */
class SecurityAuditor
{
    /**
     * Minimum recommended key length in characters.
     */
    private const MIN_RECOMMENDED_KEY_LENGTH = 32;

    /**
     * Maximum recommended key age in seconds (90 days).
     */
    private const MAX_KEY_AGE_SECONDS = 90 * 24 * 3600;

    /**
     * Perform a full security audit on a collection.
     *
     * @param  \BangronDB\Collection $collection The collection to audit
     * @return array{encryption: array, configuration: array, recommendations: array, overall_score: int, score_label: string, audited_at: int}
     */
    public static function auditCollection(\BangronDB\Collection $collection): array
    {
        $encryptionAudit = self::auditEncryption($collection);
        $configAudit = self::auditConfiguration($collection);
        $recommendations = array_merge(
            $encryptionAudit['recommendations'],
            $configAudit['recommendations']
        );

        $score = self::calculateSecurityScore($encryptionAudit, $configAudit);

        return [
            'encryption' => $encryptionAudit,
            'configuration' => $configAudit,
            'recommendations' => $recommendations,
            'overall_score' => $score,
            'score_label' => self::getScoreLabel($score),
            'audited_at' => time(),
        ];
    }

    /**
     * Perform a full security audit on a database.
     *
     * @param  Database $database The database to audit
     * @return array{database: array, collections: array, recommendations: array, audited_at: int}
     */
    public static function auditDatabase(Database $database): array
    {
        $dbAudit = self::auditDatabaseLevel($database);
        $collectionAudits = [];
        $allRecommendations = $dbAudit['recommendations'] ?? [];

        foreach ($database->getCollectionNames() as $name) {
            $coll = $database->selectCollection($name);
            $audit = self::auditCollection($coll);
            $collectionAudits[$name] = $audit;
            $allRecommendations = array_merge($allRecommendations, $audit['recommendations'] ?? []);
        }

        return [
            'database' => $dbAudit,
            'collections' => $collectionAudits,
            'recommendations' => array_unique($allRecommendations),
            'audited_at' => time(),
        ];
    }

    /**
     * Audit encryption configuration and health.
     */
    private static function auditEncryption(\BangronDB\Collection $collection): array
    {
        $recommendations = [];
        $issues = [];
        $isSecure = true;

        $isEncrypted = $collection->isEncrypted();
        $keyVersion = $collection->getEncryptionKeyVersion();
        $dbEncrypted = $collection->database->isEncryptionEnabled();

        if (!$isEncrypted && !$dbEncrypted) {
            $recommendations[] = 'Enable encryption for this collection to protect sensitive data at rest. Use $collection->setEncryptionKey($key) or pass encryption_key in database options.';
            $isSecure = false;
        }

        if ($keyVersion === null && $isEncrypted) {
            $recommendations[] = 'Set an explicit encryption key version via setEncryptionKeyVersion() to enable safe key rotation tracking.';
            $isSecure = false;
        }

        // Check if collection has searchable fields but no encryption
        $searchableFields = $collection->getSearchableFields();
        if (!empty($searchableFields) && !$isEncrypted) {
            $issues[] = 'Searchable fields are configured but encryption is not enabled. Searchable field values are stored in plaintext alongside the document.';
            $recommendations[] = 'Enable encryption when using searchable fields to ensure blind index values are properly hashed.';
            $isSecure = false;
        }

        // Check for documents with mixed encryption (some encrypted, some not)
        $mixedEncryptionDetected = false;
        $sampleSize = 0;
        $encryptedCount = 0;
        foreach ($collection->find([])->limit(50) as $doc) {
            ++$sampleSize;
            $raw = $collection->database->queryExecutor->executeQuery(
                "SELECT document FROM " . $collection->database->quoteIdentifier($collection->name) . " WHERE json_extract(document, '$._id') = ?",
                [$doc['_id']]
            );
            $row = $raw->fetch(\PDO::FETCH_ASSOC);
            if ($row && isset($row['document'])) {
                $decoded = json_decode($row['document'], true);
                if (is_array($decoded) && isset($decoded['encrypted_data'])) {
                    ++$encryptedCount;
                }
            }
        }

        if ($sampleSize > 0 && $encryptedCount > 0 && $encryptedCount < $sampleSize) {
            $mixedEncryptionDetected = true;
            $issues[] = sprintf(
                'Mixed encryption detected: %d of %d sampled documents are encrypted. This may indicate incomplete key rotation or inconsistent encryption configuration.',
                $encryptedCount,
                $sampleSize
            );
            $recommendations[] = 'Run a key rotation audit. All documents should be consistently encrypted. Use rotateEncryptionKey() or reencryptAll() to fix mixed encryption states.';
            $isSecure = false;
        }

        return [
            'is_encrypted' => $isEncrypted || $dbEncrypted,
            'is_secure' => $isSecure,
            'key_version' => $keyVersion,
            'searchable_fields_count' => count($searchableFields),
            'mixed_encryption_detected' => $mixedEncryptionDetected,
            'issues' => $issues,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit collection-level security configuration.
     */
    private static function auditConfiguration(\BangronDB\Collection $collection): array
    {
        $recommendations = [];
        $checks = [];

        // Check if schema validation is enabled
        $schema = method_exists($collection, 'getSchema') ? $collection->getSchema() : null;
        if ($schema === null || empty($schema)) {
            $recommendations[] = 'Consider enabling schema validation to prevent malformed documents from being inserted. Use setSchema() to define expected document structure.';
            $checks['schema_validation'] = false;
        } else {
            $checks['schema_validation'] = true;
        }

        // Check for unique constraints
        $uniqueConstraints = method_exists($collection, 'getUniqueConstraints') ? $collection->getUniqueConstraints() : [];
        if (empty($uniqueConstraints)) {
            $checks['unique_constraints'] = false;
        } else {
            $checks['unique_constraints'] = true;
            $checks['unique_constraint_fields'] = array_keys($uniqueConstraints);
        }

        // Check for hooks (before/after) that might modify security behavior
        $hooks = method_exists($collection, 'getHooks') ? $collection->getHooks() : [];
        $hookCount = 0;
        foreach ($hooks as $event => $callbacks) {
            $hookCount += count($callbacks);
        }
        $checks['hooks_registered'] = $hookCount;

        // Check TTL configuration
        $ttlEnabled = method_exists($collection, 'isTtlEnabled') && $collection->isTtlEnabled();
        $checks['ttl_enabled'] = $ttlEnabled;

        return [
            'checks' => $checks,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Audit database-level security.
     */
    private static function auditDatabaseLevel(Database $database): array
    {
        $recommendations = [];
        $isMemory = $database->path === Database::DSN_PATH_MEMORY;

        if ($isMemory) {
            $recommendations[] = 'In-memory database detected. Data will be lost when the process ends. Use a file-based database path for persistent storage.';
        }

        $isEncrypted = $database->isEncryptionEnabled();
        if (!$isEncrypted && !$isMemory) {
            $recommendations[] = 'Database encryption is not enabled at the database level. Pass encryption_key in database options to enable AES-256-GCM encryption for all collections.';
        }

        // Check journal mode for data safety
        $journalMode = Config::get('journal_mode', 'WAL');
        $checks = [
            'path_type' => $isMemory ? 'memory' : 'file',
            'encryption_enabled' => $isEncrypted,
            'journal_mode' => $journalMode,
        ];

        if ($journalMode !== 'WAL' && !$isMemory) {
            $recommendations[] = "Journal mode is set to '{$journalMode}'. Consider using 'WAL' for better concurrent read performance and crash recovery.";
        }

        return [
            'checks' => $checks,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Calculate an overall security score (0-100).
     */
    private static function calculateSecurityScore(array $encryptionAudit, array $configAudit): int
    {
        $score = 50; // Base score

        if ($encryptionAudit['is_encrypted']) {
            $score += 20;
        }
        if ($encryptionAudit['is_secure']) {
            $score += 15;
        }
        if (!$encryptionAudit['mixed_encryption_detected']) {
            $score += 5;
        }
        if ($encryptionAudit['key_version'] !== null) {
            $score += 5;
        }

        if ($configAudit['checks']['schema_validation'] ?? false) {
            $score += 5;
        }

        return min(100, max(0, $score));
    }

    /**
     * Get a human-readable label for a security score.
     */
    private static function getScoreLabel(int $score): string
    {
        if ($score >= 90) {
            return 'excellent';
        }
        if ($score >= 75) {
            return 'good';
        }
        if ($score >= 50) {
            return 'fair';
        }
        if ($score >= 25) {
            return 'poor';
        }

        return 'critical';
    }
}