<?php

namespace App\Services;

class SecurityNativeService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function status(): array
    {
        $settings = $this->system->systemDb()->settings;
        $key = $settings->findOne(['key' => 'security.active_key']);
        $policies = $this->system->systemDb()->security_policies->count();

        return [
            'has_active_key' => (bool) $key,
            'active_key_updated_at' => $key['updated_at'] ?? null,
            'policies' => $policies,
        ];
    }

    public function rotateKey(string $rotatedBy): array
    {
        $newKey = base64_encode(random_bytes(32));
        $settings = $this->system->systemDb()->settings;
        $existing = $settings->findOne(['key' => 'security.active_key']);

        $record = [
            'key' => 'security.active_key',
            'value' => $newKey,
            'updated_at' => date('c'),
            'updated_by' => $rotatedBy,
        ];

        if ($existing) {
            $settings->update(['_id' => $existing['_id']], $record);
        } else {
            $record['_id'] = uuid();
            $settings->insert($record);
        }

        return ['rotated' => true, 'updated_at' => $record['updated_at']];
    }

    public function savePolicy(string $name, array $rules, string $userId): array
    {
        $policies = $this->system->systemDb()->security_policies;
        $existing = $policies->findOne(['name' => $name]);

        $payload = [
            'name' => $name,
            'rules' => $rules,
            'updated_at' => date('c'),
            'updated_by' => $userId,
        ];

        if ($existing) {
            $policies->update(['_id' => $existing['_id']], $payload);
            $payload['_id'] = $existing['_id'];
        } else {
            $payload['_id'] = uuid();
            $payload['created_at'] = date('c');
            $policies->insert($payload);
        }

        return $payload;
    }
}

