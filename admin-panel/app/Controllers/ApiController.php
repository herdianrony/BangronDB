<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\DatabaseService;
use App\Services\NotificationService;

class ApiController extends BaseController
{
    private AuditService $auditService;
    private NotificationService $notificationService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
        $this->notificationService = new NotificationService();
    }

    public function dashboardSummary(): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        $databases = $this->databaseService->listDatabasesForUser($user);
        $summary = [
            'databases' => count($databases),
            'collections' => array_sum(array_map(function ($db) {
                try {
                    return count($this->databaseService->listCollections($db['_id']));
                } catch (\Throwable $e) {
                    return 0;
                }
            }, $databases)),
            'recent_activity' => $this->auditService->list(10),
        ];

        $this->json($summary);
    }

    public function monitoringRealtime(): void
    {
        $this->requireLogin();
        $data = [
            'timestamp' => date('c'),
            'memory_usage' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage(),
            'disk_total' => @disk_total_space(storage_path()) ?: 0,
            'disk_free' => @disk_free_space(storage_path()) ?: 0,
        ];
        $this->json($data);
    }

    public function auditRecent(): void
    {
        $this->requirePermission('audit.read');
        $this->json(['logs' => $this->auditService->list(20)]);
    }

    public function notifications(): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        $this->json(['items' => $this->notificationService->listForUser($user['_id'] ?? null, 30)]);
    }

    private function json(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload);
    }
}
