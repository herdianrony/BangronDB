<?php

namespace App\Controllers;

use App\Services\CollectionService;

class SchemaBuilderController extends BaseController
{
    private CollectionService $collectionService;

    public function __construct()
    {
        parent::__construct();
        $this->collectionService = new CollectionService();
    }

    public function index(string $collection): void
    {
        $this->requirePermission('collection.manage');
        $dbName = trim((string) request_get('db'));
        if ($dbName === '') {
            \Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');
        $config = $this->collectionService->getConfig($dbName, $collection);
        $config['schema'] = $this->normalizeSchema($config['schema'] ?? []);
        $config['searchable_fields'] = $this->normalizeStringList($config['searchable_fields'] ?? []);
        $config['custom_config']['encrypted_fields'] = $this->normalizeStringList($config['custom_config']['encrypted_fields'] ?? []);

        render('schema-builder/index', [
            'title' => 'Schema Builder',
            'pageTitle' => 'Schema Builder',
            'pageSubtitle' => $dbName . ' / ' . $collection,
            'navActive' => 'collections',
            'user' => $this->authService->currentUser(),
            'dbName' => $dbName,
            'collection' => $collection,
            'config' => $config,
        ]);
    }

    public function save(string $collection): void
    {
        $this->requirePermission('collection.manage');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        $schema = $this->normalizeSchema(json_decode((string) request_post('schema_json', '{}'), true));
        $searchable = $this->normalizeStringList(json_decode((string) request_post('searchable_json', '[]'), true));
        $encrypted = $this->normalizeStringList(json_decode((string) request_post('encrypted_json', '[]'), true));
        if ($dbName === '' || !is_array($schema) || !is_array($searchable) || !is_array($encrypted)) {
            flash('error', 'Invalid schema payload');
            redirect('/collections/' . urlencode($collection) . '/schema-builder?db=' . urlencode($dbName));
        }
        $this->requireDbAccess($dbName, 'admin');
        $this->collectionService->saveConfig($dbName, $collection, [
            'schema' => $schema,
            'searchable_fields' => $searchable,
            'custom_config' => ['encrypted_fields' => $encrypted],
        ]);

        flash('success', 'Schema saved.');
        redirect('/collections/' . urlencode($collection) . '/schema-builder?db=' . urlencode($dbName));
    }

    public function test(string $collection): void
    {
        $this->requirePermission('collection.manage');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        $schema = json_decode((string) request_post('schema_json', '{}'), true);
        $document = json_decode((string) request_post('document_json', '{}'), true);

        $result = ['valid' => true, 'errors' => []];
        if (!is_array($schema) || !is_array($document)) {
            $result = ['valid' => false, 'errors' => ['Invalid JSON']];
        } else {
            foreach ($schema as $field => $rule) {
                if (is_array($rule) && !empty($rule['required']) && !array_key_exists($field, $document)) {
                    $result['valid'] = false;
                    $result['errors'][] = "Missing required field: {$field}";
                }
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($result);
    }

    private function normalizeSchema($schema): array
    {
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (is_array($decoded)) {
                $schema = $decoded;
            } else {
                return [];
            }
        }
        if (!is_array($schema)) {
            return [];
        }

        $normalized = [];
        foreach ($schema as $field => $rule) {
            if (is_int($field)) {
                continue;
            }
            $fieldName = trim((string) $field);
            if ($fieldName === '') {
                continue;
            }

            if (is_string($rule)) {
                $normalized[$fieldName] = ['type' => $rule];
                continue;
            }

            if (is_array($rule)) {
                $row = [
                    'type' => (string) ($rule['type'] ?? 'string'),
                    'required' => (bool) ($rule['required'] ?? false),
                ];
                if (isset($rule['enum']) && is_array($rule['enum'])) {
                    $row['enum'] = array_values(array_filter(array_map('strval', $rule['enum']), fn ($v) => $v !== ''));
                }
                $normalized[$fieldName] = $row;
            }
        }

        return $normalized;
    }

    private function normalizeStringList($value): array
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
            if (is_string($k) && is_bool($v)) {
                if ($v) {
                    $list[] = $k;
                }
                continue;
            }
            if (is_string($v) && $v !== '') {
                $list[] = $v;
            }
        }

        return array_values(array_unique($list));
    }
}
