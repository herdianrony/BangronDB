<?php

namespace App\Controllers;

use App\Services\QueryService;

class QueryController extends BaseController
{
    private QueryService $queryService;

    public function __construct()
    {
        parent::__construct();
        $this->queryService = new QueryService();
    }

    public function playground(): void
    {
        $this->requirePermission('document.read');
        render('query-playground/index', [
            'title' => 'Query Playground',
            'pageTitle' => 'Query Playground',
            'pageSubtitle' => 'Run safe query against collection',
            'navActive' => 'query',
            'user' => $this->authService->currentUser(),
            'history' => $this->queryService->history($this->authService->currentUser()['_id'] ?? '', 20),
        ]);
    }

    public function run(): void
    {
        $this->requirePermission('document.read');
        verify_csrf();
        $user = $this->authService->currentUser();
        $dbName = trim((string) request_post('db'));
        $collection = trim((string) request_post('collection'));
        $criteriaJson = trim((string) request_post('criteria_json', '{}'));
        $sortJson = trim((string) request_post('sort_json', '{"_id":-1}'));
        $limit = (int) request_post('limit', 50);

        $criteria = json_decode($criteriaJson, true);
        $sort = json_decode($sortJson, true);
        if (!is_array($criteria) || !is_array($sort)) {
            flash('error', 'Invalid JSON criteria/sort.');
            redirect('/query-playground');
        }

        try {
            $results = $this->queryService->run($user, $dbName, $collection, $criteria, $sort, $limit);
            $this->queryService->saveHistory($user['_id'], [
                'db' => $dbName,
                'collection' => $collection,
                'criteria' => $criteria,
                'sort' => $sort,
                'limit' => $limit,
                'result_count' => count($results),
            ]);
            render('query-playground/index', [
                'title' => 'Query Playground',
                'pageTitle' => 'Query Playground',
                'pageSubtitle' => 'Result',
                'navActive' => 'query',
                'user' => $user,
                'history' => $this->queryService->history($user['_id'], 20),
                'results' => $results,
                'dbName' => $dbName,
                'collection' => $collection,
                'criteriaJson' => $criteriaJson,
                'sortJson' => $sortJson,
                'limit' => $limit,
            ]);
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
            redirect('/query-playground');
        }
    }

    public function history(): void
    {
        $this->requirePermission('document.read');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'items' => $this->queryService->history($this->authService->currentUser()['_id'] ?? '', 20),
        ]);
    }
}

