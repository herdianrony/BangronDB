<?php

declare(strict_types=1);

namespace BangronDB\Tests;

use PHPUnit\Framework\TestCase;

/**
 * Integration test that executes example scripts end-to-end.
 *
 * This catches syntax errors, runtime bugs, and regressions in the
 * examples/ directory — things that unit tests alone cannot detect
 * because examples are standalone scripts run via `php examples/xx.php`.
 *
 * Recommended in the v1.2.0 post-release review.
 */
class ExampleIntegrationTest extends TestCase
{
    /**
     * Run an example script with the FrankenPHP/PHP CLI and assert it
     * exits cleanly (exit code 0) with no PHP fatal/error output.
     */
    private function assertExampleRunsCleanly(string $exampleFile): void
    {
        $path = __DIR__ . '/../examples/' . $exampleFile;
        $this->assertFileExists($path, "Example file $exampleFile must exist");

        // Resolve the PHP CLI command prefix (binary + optional subcommand).
        // FrankenPHP exposes PHP via `frankenphp php-cli <file>`, while a
        // standard build uses `php <file>`.
        $phpCmd = $this->resolvePhpCommand();

        // Run in a clean temp working directory so examples that write to
        // examples/data_example_* do not pollute the repo permanently.
        $tmpWorkspace = sys_get_temp_dir() . '/bangrondb_example_' . uniqid();
        @mkdir($tmpWorkspace, 0700, true);

        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        // Note: FrankenPHP's `php-cli` subcommand does not accept `-d` ini
        // flags before the script path (it treats them as the file to run),
        // so we invoke the script directly and rely on the error-signature
        // scan below to catch emitted PHP errors.
        $cmd = sprintf('%s %s 2>&1', $phpCmd, escapeshellarg($path));

        $proc = @proc_open($cmd, $descriptors, $pipes, $tmpWorkspace);
        $this->assertIsResource($proc, "Failed to launch PHP for $exampleFile");

        $output = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        // Clean up workspace (best-effort)
        $this->recursiveRmdir($tmpWorkspace);

        $this->assertSame(
            0,
            $exitCode,
            sprintf(
                "Example %s exited with code %d.\n--- OUTPUT ---\n%s\n--- END ---",
                $exampleFile,
                $exitCode,
                $output
            )
        );

        // Scan for PHP error signatures that don't always set a non-zero exit code.
        $errorSignatures = [
            'Parse error',
            'Fatal error',
            'Uncaught',
            'Stack trace:',
            'Warning: Undefined',
            'TypeError:',
            'ArgumentCountError',
        ];
        foreach ($errorSignatures as $sig) {
            $this->assertStringNotContainsStringIgnoringCase(
                $sig,
                $output,
                "Example $exampleFile emitted a PHP error signature ($sig):\n$output"
            );
        }
    }

    /**
     * Resolve the PHP CLI command prefix as a shell-safe string.
     *
     * Detection order:
     *   1. FrankenPHP binary at /home/z/frankenphp  →  `frankenphp php-cli`
     *   2. System `php` on PATH                       →  `php`
     *   3. PHP_BINARY fallback                        →  the raw binary path
     */
    private function resolvePhpCommand(): string
    {
        $frankenphp = '/home/z/frankenphp';
        if (file_exists($frankenphp) && is_executable($frankenphp)) {
            return escapeshellarg($frankenphp) . ' php-cli';
        }

        $which = shell_exec('command -v php 2>/dev/null');
        if ($which !== null && trim($which) !== '') {
            return 'php';
        }

        return escapeshellarg(PHP_BINARY);
    }

    private function recursiveRmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir) ?: [];
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $full = $dir . '/' . $item;
            if (is_dir($full)) {
                $this->recursiveRmdir($full);
            } else {
                @unlink($full);
            }
        }
        @rmdir($dir);
    }

    /**
     * Example 16: Key Rotation – the v1.2.0 flagship example.
     * Catches: syntax errors, runtime bugs in rotateEncryptionKey(),
     * reencryptAll(), sensitive config blocking, and legacy IV decrypt.
     */
    public function testExample16KeyRotationRunsCleanly(): void
    {
        $this->assertExampleRunsCleanly('16-key-rotation.php');
    }

    /**
     * Example 15: Auth + Encryption – searchable encrypted fields for login.
     */
    public function testExample15AuthEncryptedRunsCleanly(): void
    {
        $this->assertExampleRunsCleanly('15-auth-encrypted.php');
    }

    /**
     * Example 03: Encryption + searchable fields basics.
     */
    public function testExample03EncryptionSearchableRunsCleanly(): void
    {
        $this->assertExampleRunsCleanly('03-encryption-searchable.php');
    }
}
