<?php

namespace App\Services;

class TerminalService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function allowedCommands(): array
    {
        return [
            'php_version',
            'disk_usage',
            'list_tenants',
            'health_report',
        ];
    }

    public function execute(string $command, ?string $dbName = null): array
    {
        if (!in_array($command, $this->allowedCommands(), true)) {
            throw new \RuntimeException('Command not allowed');
        }

        $output = match ($command) {
            'php_version' => ['version' => PHP_VERSION],
            'disk_usage' => $this->diskUsage(),
            'list_tenants' => $this->listTenants(),
            'health_report' => $this->healthReport($dbName),
            default => [],
        };

        $entry = [
            '_id' => uuid(),
            'command' => $command,
            'db_name' => $dbName,
            'output' => $output,
            'created_at' => date('c'),
        ];
        $this->system->systemDb()->terminal_logs->insert($entry);

        return $entry;
    }

    public function logs(int $limit = 50): array
    {
        return $this->system->systemDb()->terminal_logs->find()->sort(['created_at' => -1])->limit($limit)->toArray();
    }

    private function diskUsage(): array
    {
        $path = storage_path();
        $total = @disk_total_space($path) ?: 0;
        $free = @disk_free_space($path) ?: 0;
        $used = $total > 0 ? ($total - $free) : 0;

        return ['path' => $path, 'total' => $total, 'free' => $free, 'used' => $used];
    }

    private function listTenants(): array
    {
        $names = [];
        foreach (glob(tenant_path('*.bangron')) ?: [] as $file) {
            $names[] = pathinfo($file, PATHINFO_FILENAME);
        }

        return ['databases' => $names];
    }

    private function healthReport(?string $dbName): array
    {
        if (!$dbName) {
            return ['error' => 'db_name required'];
        }
        $db = $this->system->tenantClient()->selectDB($dbName);

        return $db->getHealthReport();
    }
}

