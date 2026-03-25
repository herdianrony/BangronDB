<?php

namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\DocumentService;
use App\Services\SystemService;

class DashboardController extends BaseController
{
    public function index(): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();

        $dbService = new DatabaseService();
        $documentService = new DocumentService();
        $system = new SystemService();

        $databases = $dbService->listDatabasesForUser($user);
        $totalCollections = 0;
        $totalDocuments = 0;
        $databaseOverview = [];

        foreach ($databases as $db) {
            try {
                $dbName = (string) ($db['_id'] ?? '');
                if ($dbName === '') {
                    continue;
                }

                $collections = $dbService->listCollections($dbName);
                $dbDocuments = 0;
                $totalCollections += count($collections);

                foreach ($collections as $collectionName) {
                    try {
                        $count = $documentService->count($dbName, (string) $collectionName, [], 'active');
                        $dbDocuments += $count;
                        $totalDocuments += $count;
                    } catch (\Throwable $e) {
                    }
                }

                $fileSizeBytes = $this->databaseFileSize((string) ($db['path'] ?? ''));
                $databaseOverview[] = [
                    'name' => $dbName,
                    'label' => (string) ($db['label'] ?? $dbName),
                    'collections' => count($collections),
                    'documents' => $dbDocuments,
                    'size_bytes' => $fileSizeBytes,
                    'size_human' => $this->formatBytes($fileSizeBytes),
                    'usage_percent' => $this->usagePercent($fileSizeBytes),
                ];
            } catch (\Throwable $e) {
            }
        }

        usort($databaseOverview, function (array $a, array $b): int {
            return ($b['size_bytes'] ?? 0) <=> ($a['size_bytes'] ?? 0);
        });

        $users = $system->systemDb()->users;
        $activeUsers = (int) $users->count(['status' => 'active']);
        if ($activeUsers === 0) {
            $activeUsers = (int) $users->count();
        }

        $diskTotal = (float) (@disk_total_space(storage_path()) ?: 0);
        $diskFree = (float) (@disk_free_space(storage_path()) ?: 0);
        $diskUsedPct = $diskTotal > 0 ? (int) round((($diskTotal - $diskFree) / $diskTotal) * 100) : 0;

        $memoryLimitBytes = $this->toBytes((string) ini_get('memory_limit'));
        $memoryUsageBytes = memory_get_usage(true);
        $memoryUsagePct = $memoryLimitBytes > 0 ? (int) min(100, round(($memoryUsageBytes / $memoryLimitBytes) * 100)) : 0;

        $audit = new \App\Services\AuditService();
        $recentLogs = $audit->list(5);
        $auditSummary = $audit->getDashboardSummary();

        $queryStats = [
            'read_ops' => $this->sumActionCount($auditSummary['top_actions'] ?? [], ['read', 'find', 'query']),
            'write_ops' => $this->sumActionCount($auditSummary['top_actions'] ?? [], ['create', 'update', 'delete', 'write', 'insert', 'import']),
            'avg_latency' => 'n/a',
        ];

        render('dashboard/index', [
            'title' => 'Dashboard',
            'pageTitle' => 'Dashboard',
            'pageSubtitle' => 'Selamat datang kembali, ' . ($user['name'] ?? 'Admin'),
            'navActive' => 'dashboard',
            'user' => $user,
            'databases' => $databases,
            'databaseOverview' => $databaseOverview,
            'totalCollections' => $totalCollections,
            'totalDocuments' => $totalDocuments,
            'activeUsers' => $activeUsers,
            'systemHealth' => [
                'cpu_pct' => 0,
                'memory_pct' => $memoryUsagePct,
                'disk_pct' => $diskUsedPct,
                'memory_usage_human' => $this->formatBytes($memoryUsageBytes),
                'memory_limit_human' => $memoryLimitBytes > 0 ? $this->formatBytes($memoryLimitBytes) : 'unlimited',
            ],
            'queryStats' => $queryStats,
            'encryptionStatus' => [
                'enabled' => !empty($_ENV['DB_ENCRYPTION_KEY']),
                'encrypted_collections' => 0,
                'total_collections' => $totalCollections,
            ],
            'recentLogs' => $recentLogs,
        ]);
    }

    private function databaseFileSize(string $path): int
    {
        if ($path !== '' && is_file($path)) {
            $size = @filesize($path);
            return $size === false ? 0 : (int) $size;
        }

        return 0;
    }

    private function usagePercent(int $bytes): int
    {
        $quota = 5 * 1024 * 1024 * 1024;
        if ($bytes <= 0) {
            return 0;
        }

        return (int) min(100, round(($bytes / $quota) * 100));
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = (int) floor(log($bytes, 1024));
        $power = min($power, count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return number_format($value, $power === 0 ? 0 : 1) . ' ' . $units[$power];
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return 0;
        }

        $unit = strtolower(substr($value, -1));
        $number = (int) $value;
        if ($unit === 'g') {
            return $number * 1024 * 1024 * 1024;
        }
        if ($unit === 'm') {
            return $number * 1024 * 1024;
        }
        if ($unit === 'k') {
            return $number * 1024;
        }

        return $number;
    }

    private function sumActionCount(array $topActions, array $keywords): int
    {
        $sum = 0;
        foreach ($topActions as $row) {
            $action = strtolower((string) ($row['action'] ?? ''));
            foreach ($keywords as $keyword) {
                if (str_contains($action, $keyword)) {
                    $sum += (int) ($row['count'] ?? 0);
                    break;
                }
            }
        }

        return $sum;
    }
}