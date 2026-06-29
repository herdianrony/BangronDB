<?php
declare(strict_types=1);

use BangronDB\Client;

/**
 * BangronDB Secure Client Factory
 * - Load encryption key dari .env, tidak hardcode
 * - Validasi key length >= 32 chars
 * - Disable query_logging di production
 * - Searchable fields allowlist - jangan over-expose
 */
class SecureClientFactory
{
    // ALLOWLIST searchable fields per collection
    // Hanya field yang benar-benar perlu di-query saat encrypted
    private const SEARCHABLE_ALLOWLIST = [
        'users' => [
            'email' => ['hash' => true],
            // 'phone' => ['hash' => true], // aktifkan hanya jika perlu
        ],
        // tambahkan collection lain di sini
        // 'orders' => ['order_number' => ['hash' => false]],
    ];

    public static function create(string $dataPath = null): Client
    {
        // Load .env sederhana jika vlucas/phpdotenv tidak ada
        if (file_exists(__DIR__ . '/.env')) {
            $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                if (str_starts_with(trim($line), '#')) continue;
                [$k, $v] = array_pad(explode('=', $line, 2), 2, '');
                if ($k && !getenv($k)) {
                    putenv("$k=$v");
                    $_ENV[$k] = $v;
                }
            }
        }

        $key = $_ENV['DB_ENCRYPTION_KEY'] ?? getenv('DB_ENCRYPTION_KEY') ?: null;
        $keyVersion = $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? getenv('DB_ENCRYPTION_KEY_VERSION') ?: 'v2-2026';

        if (!$key || strlen($key) < 32) {
            throw new RuntimeException(
                'DB_ENCRYPTION_KEY harus >= 32 karakter. Generate: openssl rand -base64 48'
            );
        }

        $dataPath = $dataPath ?? $_ENV['DB_DATA_PATH'] ?? __DIR__ . '/data';

        if (!is_dir($dataPath)) {
            mkdir($dataPath, 0700, true);
        }

        $isProd = ($_ENV['APP_ENV'] ?? 'production') === 'production';

        return new Client($dataPath, [
            'encryption_key' => $key,
            'encryption_key_version' => $keyVersion,  // v1.2.0
            'query_logging' => filter_var($_ENV['DB_QUERY_LOGGING'] ?? false, FILTER_VALIDATE_BOOLEAN) && !$isProd,
            'performance_monitoring' => filter_var($_ENV['DB_PERFORMANCE_MONITORING'] ?? false, FILTER_VALIDATE_BOOLEAN) && !$isProd,
        ]);
    }

    /**
     * Apply searchable fields dari allowlist, mencegah over-expose
     */
    public static function applySearchableFields(\BangronDB\Collection $collection, string $collectionName): void
    {
        $fields = self::SEARCHABLE_ALLOWLIST[$collectionName] ?? [];
        if (!empty($fields)) {
            $collection->setSearchableFields($fields);
            // JANGAN save encryption_key ke config yang di-persist
            // $collection->saveConfiguration(); // hanya jika config aman
        }
    }
}
