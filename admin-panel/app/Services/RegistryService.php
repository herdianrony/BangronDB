<?php

namespace App\Services;

class RegistryService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function scanTenants(string $path): array
    {
        $names = [];
        if (!is_dir($path)) {
            return $names;
        }

        $iterator = new \DirectoryIterator($path);
        foreach ($iterator as $fileInfo) {
            if (!$fileInfo->isFile()) {
                continue;
            }
            if ($fileInfo->getExtension() !== 'bangron') {
                continue;
            }
            $filename = $fileInfo->getFilename();
            $names[] = substr($filename, 0, -8);
        }

        return $names;
    }

    public function sync(array $dbNames): void
    {
        $registry = $this->system->systemDb()->database_registry;
        foreach ($dbNames as $name) {
            if ($name === config('system.db_name', 'admin')) {
                continue;
            }
            $existing = $registry->findOne(['_id' => $name]);
            if ($existing) {
                continue;
            }
            $registry->insert([
                '_id' => $name,
                'label' => $name,
                'path' => tenant_path($name . '.bangron'),
                'owner_user_id' => null,
                'created_at' => date('c'),
                'status' => 'active',
                'source' => 'auto',
            ]);
        }
    }
}
