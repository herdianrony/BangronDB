<?php

namespace App\Controllers;

use App\Services\CollectionService;
use App\Services\AuditService;
use Flight;

class CollectionController extends BaseController
{
    private CollectionService $collections;
    private AuditService $audit;

    public function __construct()
    {
        parent::__construct();
        $this->collections = new CollectionService();
        $this->audit = new AuditService();
    }

    public function create(string $dbName): void
    {
        $this->requirePermission('collection.manage');
        $this->requireDbAccess($dbName, 'admin');
        verify_csrf();

        $name = trim((string) request_post('collection_name'));
        if ($name === '') {
            flash('error', 'Collection name required.');
            redirect('/databases/' . $dbName);
        }

        try {
            $this->collections->create($dbName, $name);
            $this->audit->log($this->authService->currentUser(), 'collection.create', $dbName, $name);
            flash('success', 'Collection created.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/databases/' . $dbName);
    }

    public function rename(string $dbName): void
    {
        $this->requirePermission('collection.manage');
        $this->requireDbAccess($dbName, 'admin');
        verify_csrf();

        $current = trim((string) request_post('current_name'));
        $newName = trim((string) request_post('new_name'));
        if ($current === '' || $newName === '') {
            flash('error', 'Both collection names are required.');
            redirect('/databases/' . $dbName);
        }

        try {
            $this->collections->rename($dbName, $current, $newName);
            $this->audit->log($this->authService->currentUser(), 'collection.rename', $dbName, $current, null, [
                'new_name' => $newName,
            ]);
            flash('success', 'Collection renamed.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/databases/' . $dbName);
    }

    public function drop(string $dbName): void
    {
        $this->requirePermission('collection.manage');
        $this->requireDbAccess($dbName, 'admin');
        verify_csrf();

        $name = trim((string) request_post('collection_name'));
        if ($name === '') {
            flash('error', 'Collection name required.');
            redirect('/databases/' . $dbName);
        }

        try {
            $this->collections->drop($dbName, $name);
            $this->audit->log($this->authService->currentUser(), 'collection.drop', $dbName, $name);
            flash('success', 'Collection dropped.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/databases/' . $dbName);
    }

    public function settings(string $collection): void
    {
        $this->requirePermission('collection.manage');
        $dbName = trim((string) request_get('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $config = $this->collections->getConfig($dbName, $collection);
        $schemaFields = $this->extractSchemaFields($config['schema'] ?? []);
        $searchableList = $this->normalizeList($config['searchable_fields'] ?? []);
        $encryptedList = $this->normalizeList($config['custom_config']['encrypted_fields'] ?? []);
        $indexList = is_array($config['indexes'] ?? null) ? array_values($config['indexes']) : [];

        render('collections/settings', [
            'title' => 'Collection Settings',
            'pageTitle' => 'Collection Settings',
            'pageSubtitle' => $dbName . ' / ' . $collection,
            'navActive' => 'collections',
            'dbName' => $dbName,
            'collection' => $collection,
            'config' => $config,
            'schemaFields' => $schemaFields,
            'searchableList' => $searchableList,
            'encryptedList' => $encryptedList,
            'indexList' => $indexList,
        ]);
    }

    public function saveSettings(string $collection): void
    {
        $this->requirePermission('collection.manage');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $config = [
            'id_mode' => request_post('id_mode', 'auto'),
            'id_prefix' => request_post('id_prefix', 'ID'),
            'encryption_enabled' => request_post('encryption_enabled') === '1',
            'soft_deletes_enabled' => request_post('soft_deletes_enabled') === '1',
            'deleted_at_field' => trim((string) request_post('deleted_at_field', '_deleted_at')),
        ];

        $schema = $this->decodeJson(request_post('schema_json'));
        $searchable = request_post('searchable_fields', []);
        $indexesCsv = trim((string) request_post('indexes_csv', ''));
        $indexes = $this->parseCommaSeparated($indexesCsv);
        $indexesJson = $this->decodeJson(request_post('indexes_json'));
        if (is_array($indexesJson)) {
            $indexes = $indexesJson;
        }
        $encryptedFields = request_post('encrypted_fields', []);
        $custom = $this->decodeJson(request_post('custom_json')) ?? [];
        if (!is_array($custom)) {
            $custom = [];
        }

        if ($schema !== null) {
            $config['schema'] = $schema;
        }
        $config['searchable_fields'] = $this->normalizeList($searchable);
        $custom['encrypted_fields'] = $this->normalizeList($encryptedFields);
        $config['custom_config'] = $custom;

        try {
            $this->collections->saveConfig($dbName, $collection, $config, $indexes);
            $this->audit->log($this->authService->currentUser(), 'collection.settings', $dbName, $collection);
            flash('success', 'Settings saved.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/collections/' . urlencode($collection) . '/settings?db=' . urlencode($dbName));
    }

    private function decodeJson($value): ?array
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        $decoded = json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            flash('error', 'Invalid JSON: ' . json_last_error_msg());
            return null;
        }

        return $decoded;
    }

    private function extractSchemaFields($schema): array
    {
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            $schema = is_array($decoded) ? $decoded : [];
        }
        if (!is_array($schema)) {
            return [];
        }

        $fields = [];
        foreach ($schema as $name => $rule) {
            if (!is_string($name) || $name === '_id') {
                continue;
            }
            $type = is_array($rule) ? (string) ($rule['type'] ?? 'string') : (string) $rule;
            $fields[] = ['name' => $name, 'type' => $type];
        }

        return $fields;
    }

    private function normalizeList($value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (is_array($decoded)) {
                $value = $decoded;
            } else {
                return [];
            }
        }
        if (!is_array($value)) {
            return [];
        }

        $list = [];
        foreach ($value as $k => $v) {
            if (is_string($k) && is_bool($v) && $v) {
                $list[] = $k;
                continue;
            }
            if (is_string($v) && $v !== '') {
                $list[] = $v;
            }
        }

        return array_values(array_unique($list));
    }

    private function parseCommaSeparated(string $value): array
    {
        if ($value === '') {
            return [];
        }

        $parts = array_map('trim', explode(',', $value));
        $parts = array_values(array_filter($parts, fn ($v) => $v !== ''));

        return array_values(array_unique($parts));
    }
}
