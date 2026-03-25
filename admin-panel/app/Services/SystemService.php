<?php

namespace App\Services;

use BangronDB\Client;

class SystemService
{
    public function systemClient(): Client
    {
        static $client = null;
        if ($client instanceof Client) {
            return $client;
        }

        $options = [];
        if (!empty($_ENV['DB_ENCRYPTION_KEY'])) {
            $options['encryption_key'] = $_ENV['DB_ENCRYPTION_KEY'];
        }

        $client = new Client(admin_storage_path(), $options);

        return $client;
    }

    public function systemDb()
    {
        return $this->systemClient()->selectDB(config('system.db_name', 'admin'));
    }

    public function tenantClient(): Client
    {
        static $client = null;
        if ($client instanceof Client) {
            return $client;
        }

        $options = [];
        if (!empty($_ENV['DB_ENCRYPTION_KEY'])) {
            $options['encryption_key'] = $_ENV['DB_ENCRYPTION_KEY'];
        }

        $client = new Client(tenant_path(), $options);

        return $client;
    }
}
