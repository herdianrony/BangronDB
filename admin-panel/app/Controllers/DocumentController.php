<?php

namespace App\Controllers;

use App\Services\DocumentService;
use App\Services\AuditService;
use App\Services\CollectionService;
use Flight;

class DocumentController extends BaseController
{
    private DocumentService $documents;
    private AuditService $audit;
    private CollectionService $collections;

    public function __construct()
    {
        parent::__construct();
        $this->documents = new DocumentService();
        $this->audit = new AuditService();
        $this->collections = new CollectionService();
    }

    public function index(string $collection): void
    {
        $this->requirePermission('document.read');
        $dbName = trim((string) request_get('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }

        $this->requireDbAccess($dbName, 'viewer');

        $limit = max(1, min(100, (int) request_get('limit', 20)));
        $skip = max(0, (int) request_get('skip', 0));
        $status = trim((string) request_get('status', 'active'));
        $sort = trim((string) request_get('sort', '_id_desc'));
        $queryJson = trim((string) request_get('query_json', ''));

        $criteria = $this->parseCriteria($queryJson);
        $sortSpec = $this->parseSort($sort);

        $items = $this->documents->list($dbName, $collection, $criteria, $sortSpec, $limit, $skip, $status);
        $total = $this->documents->count($dbName, $collection, $criteria, $status);
        $config = $this->collections->getConfig($dbName, $collection);
        $schemaFields = $this->normalizeSchemaFields($config['schema'] ?? []);
        $displayFields = $this->buildDisplayFields($schemaFields, $items);

        render('documents/index', [
            'title' => 'Documents',
            'pageTitle' => 'Documents',
            'pageSubtitle' => $dbName . ' / ' . $collection,
            'navActive' => 'documents',
            'user' => $this->authService->currentUser(),
            'dbName' => $dbName,
            'collection' => $collection,
            'documents' => $items,
            'total' => $total,
            'limit' => $limit,
            'skip' => $skip,
            'status' => $status,
            'sort' => $sort,
            'queryJson' => $queryJson,
            'schemaFields' => $schemaFields,
            'displayFields' => $displayFields,
        ]);
    }

    public function create(string $collection): void
    {
        $this->requirePermission('document.write');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $json = trim((string) request_post('document_json'));
        $doc = [];
        if ($json !== '') {
            $doc = json_decode($json, true);
        } else {
            $config = $this->collections->getConfig($dbName, $collection);
            $schemaFields = $this->normalizeSchemaFields($config['schema'] ?? []);
            $doc = $this->buildDocumentFromForm($schemaFields);
        }
        if (!is_array($doc)) {
            flash('error', 'Invalid JSON.');
            redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
        }

        $id = $this->documents->create($dbName, $collection, $doc);
        $this->audit->log($this->authService->currentUser(), 'document.create', $dbName, $collection, $id);
        flash('success', 'Document created.');
        redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
    }

    public function update(string $collection): void
    {
        $this->requirePermission('document.write');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $id = trim((string) request_post('document_id'));
        $json = trim((string) request_post('document_json'));
        $data = json_decode($json, true);
        if ($id === '' || !is_array($data)) {
            flash('error', 'Document ID and valid JSON are required.');
            redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
        }

        $this->documents->update($dbName, $collection, $id, $data);
        $this->audit->log($this->authService->currentUser(), 'document.update', $dbName, $collection, $id);
        flash('success', 'Document updated.');
        redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
    }

    public function delete(string $collection): void
    {
        $this->requirePermission('document.write');
        verify_csrf();
        $dbName = trim((string) request_post('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $id = trim((string) request_post('document_id'));
        if ($id === '') {
            flash('error', 'Document ID required.');
            redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
        }

        $this->documents->delete($dbName, $collection, $id);
        $this->audit->log($this->authService->currentUser(), 'document.delete', $dbName, $collection, $id);
        flash('success', 'Document deleted.');
        redirect('/documents/' . $collection . '?db=' . urlencode($dbName));
    }

    public function export(string $collection): void
    {
        $this->requirePermission('document.read');
        $dbName = trim((string) request_get('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'viewer');

        $status = trim((string) request_get('status', 'active'));
        $sort = trim((string) request_get('sort', '_id_desc'));
        $queryJson = trim((string) request_get('query_json', ''));
        $criteria = $this->parseCriteria($queryJson);
        $sortSpec = $this->parseSort($sort);

        $payload = $this->documents->exportAsJson($dbName, $collection, $criteria, $sortSpec, $status);
        $fileName = $dbName . '_' . $collection . '_' . date('Ymd_His') . '.json';

        $this->audit->log($this->authService->currentUser(), 'document.export', $dbName, $collection, null, [
            'status' => $status,
        ]);

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');
        echo $payload;
    }

    public function import(string $collection): void
    {
        $this->requirePermission('document.write');
        verify_csrf();

        $dbName = trim((string) request_post('db'));
        if ($dbName === '') {
            Flight::halt(400, 'Missing database parameter');
        }
        $this->requireDbAccess($dbName, 'admin');

        $json = trim((string) request_post('documents_json'));
        if ($json === '' && isset($_FILES['documents_file']) && is_uploaded_file($_FILES['documents_file']['tmp_name'])) {
            $json = (string) file_get_contents($_FILES['documents_file']['tmp_name']);
        }

        if ($json === '') {
            flash('error', 'JSON data or file is required for import.');
            redirect('/documents/' . urlencode($collection) . '?db=' . urlencode($dbName));
        }

        $decoded = json_decode($json, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
            flash('error', 'Invalid JSON payload.');
            redirect('/documents/' . urlencode($collection) . '?db=' . urlencode($dbName));
        }

        $documents = array_keys($decoded) === range(0, count($decoded) - 1) ? $decoded : [$decoded];
        $upsert = request_post('upsert') === '1';
        $result = $this->documents->importFromJson($dbName, $collection, $documents, $upsert);

        $this->audit->log($this->authService->currentUser(), 'document.import', $dbName, $collection, null, $result);
        $message = 'Import selesai. inserted=' . $result['inserted'] . ', updated=' . $result['updated'] . ', skipped=' . $result['skipped'];
        if (!empty($result['errors'])) {
            $message .= ' (ada ' . count($result['errors']) . ' error)';
            $message .= ' contoh: ' . implode(' | ', array_slice($result['errors'], 0, 2));
        }
        flash('success', $message);
        redirect('/documents/' . urlencode($collection) . '?db=' . urlencode($dbName));
    }

    private function parseCriteria(string $queryJson): array
    {
        if ($queryJson === '') {
            return [];
        }

        $criteria = json_decode($queryJson, true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($criteria)) {
            flash('error', 'Filter JSON tidak valid. Menggunakan filter default.');
            return [];
        }

        return $criteria;
    }

    private function parseSort(string $sort): array
    {
        return match ($sort) {
            '_id_asc' => ['_id' => 1],
            'created_asc' => ['created_at' => 1],
            'created_desc' => ['created_at' => -1],
            'updated_asc' => ['updated_at' => 1],
            'updated_desc' => ['updated_at' => -1],
            default => ['_id' => -1],
        };
    }

    private function normalizeSchemaFields($schema): array
    {
        if (is_string($schema)) {
            $decoded = json_decode($schema, true);
            if (is_array($decoded)) {
                $schema = $decoded;
            } else {
                $schema = [];
            }
        }
        if (!is_array($schema)) {
            return [];
        }

        $fields = [];
        foreach ($schema as $name => $rule) {
            if (!is_string($name) || $name === '_id') {
                continue;
            }
            if (is_string($rule)) {
                $fields[] = ['name' => $name, 'type' => $rule, 'required' => false, 'enum' => []];
                continue;
            }
            if (is_array($rule)) {
                $fields[] = [
                    'name' => $name,
                    'type' => (string) ($rule['type'] ?? 'string'),
                    'required' => (bool) ($rule['required'] ?? false),
                    'enum' => isset($rule['enum']) && is_array($rule['enum']) ? array_values($rule['enum']) : [],
                ];
            }
        }

        return $fields;
    }

    private function buildDocumentFromForm(array $schemaFields): array
    {
        $doc = [];
        foreach ($schemaFields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name === '') {
                continue;
            }

            $raw = request_post('field_' . $name);
            $type = (string) ($field['type'] ?? 'string');
            $required = (bool) ($field['required'] ?? false);

            if ($raw === null || $raw === '') {
                if ($required) {
                    $doc[$name] = $type === 'boolean' ? false : '';
                }
                continue;
            }

            $doc[$name] = $this->castValue($raw, $type);
        }

        return $doc;
    }

    private function castValue($raw, string $type)
    {
        return match ($type) {
            'int', 'integer' => (int) $raw,
            'float', 'double', 'number' => (float) $raw,
            'boolean', 'bool' => in_array(strtolower((string) $raw), ['1', 'true', 'on', 'yes'], true),
            'array', 'object', 'json' => $this->decodeJsonFallback((string) $raw),
            default => (string) $raw,
        };
    }

    private function decodeJsonFallback(string $value)
    {
        $decoded = json_decode($value, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        return $value;
    }

    private function buildDisplayFields(array $schemaFields, array $documents): array
    {
        if (!empty($schemaFields)) {
            return array_values(array_map(function (array $field): array {
                return [
                    'name' => (string) ($field['name'] ?? ''),
                    'type' => (string) ($field['type'] ?? 'string'),
                ];
            }, array_filter($schemaFields, fn (array $f) => !empty($f['name']))));
        }

        if (empty($documents)) {
            return [];
        }

        $first = $documents[0];
        if (!is_array($first)) {
            return [];
        }

        $excluded = ['_id', 'created_at', 'updated_at', '_created_at', '_updated_at'];
        $keys = [];
        foreach (array_keys($first) as $key) {
            if (!is_string($key) || in_array($key, $excluded, true)) {
                continue;
            }
            $keys[] = $key;
            if (count($keys) >= 8) {
                break;
            }
        }

        return array_map(fn (string $k): array => ['name' => $k, 'type' => 'mixed'], $keys);
    }
}
