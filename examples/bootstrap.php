<?php

/**
 * Bootstrap file untuk semua contoh BangronDB.
 * Menyediakan autoloading dan helper functions.
 */

require_once dirname(__DIR__) . '/vendor/autoload.php';

use BangronDB\Client;
use BangronDB\Config;

// Gunakan direktori data lokal untuk examples
$exampleDataPath = __DIR__ . '/data';
if (!is_dir($exampleDataPath)) {
    mkdir($exampleDataPath, 0755, true);
}
Config::set('default_path', $exampleDataPath);

/**
 * Buat client dengan database terisolasi (unik per pemanggilan).
 */
function createIsolatedClient(string $name = 'test', array $options = []): Client
{
    global $exampleDataPath;
    $path = $exampleDataPath . '/' . $name . '_' . uniqid();
    if (!is_dir($path)) {
        mkdir($path, 0755, true);
    }
    return new Client($path, $options);
}

/**
 * Helper: print separator.
 */
function sep(string $title): void
{
    echo "\n" . str_repeat('=', 60) . "\n";
    echo "  {$title}\n";
    echo str_repeat('=', 60) . "\n\n";
}

/**
 * Helper: print sub-section.
 */
function sub(string $title): void
{
    echo "\n--- {$title} ---\n";
}

/**
 * Helper: print value (string or array/anything printable).
 * Used by examples that need pretty output of mixed values.
 *
 * @param mixed $value Value to print
 */
function p($value): void
{
    if (is_array($value) || is_object($value)) {
        echo json_encode($value, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo $value . "\n";
    }
}
