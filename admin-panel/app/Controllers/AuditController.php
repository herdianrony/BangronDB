<?php

namespace App\Controllers;

use App\Services\AuditService;

class AuditController extends BaseController
{
    private AuditService $auditService;

    public function __construct()
    {
        parent::__construct();
        $this->auditService = new AuditService();
    }

    public function showDashboard(): void
    {
        $this->requirePermission('audit.read');
        $summary = $this->auditService->getDashboardSummary();
        $securityEvents = $this->auditService->getSecurityEvents(10);
        $topActions = $summary['top_actions'] ?? [];
        $loginStats = [];

        render('audit/index', [
            'title' => 'Audit',
            'pageTitle' => 'Audit Dashboard',
            'pageSubtitle' => 'Ringkasan aktivitas sistem',
            'navActive' => 'audit',
            'user' => $this->authService->currentUser(),
            'summary' => $summary,
            'securityEvents' => $securityEvents,
            'topActions' => $topActions,
            'loginStats' => $loginStats,
        ]);
    }

    public function showUserActivity(): void
    {
        $this->requirePermission('audit.read');
        $currentUser = $this->authService->currentUser();
        $filters = [
            'action' => $_GET['action'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $logs = $this->auditService->getUserActivity($currentUser['_id'], 50, 0, $filters);

        render('audit/user-activity', [
            'title' => 'User Activity',
            'pageTitle' => 'User Activity',
            'pageSubtitle' => 'Aktivitas akun saat ini',
            'navActive' => 'audit',
            'user' => $currentUser,
            'targetUser' => $currentUser,
            'logs' => $logs,
            'filters' => $filters,
            'canViewAll' => false,
        ]);
    }

    public function showSystemActivity(): void
    {
        $this->requirePermission('audit.read');
        $filters = [
            'action' => $_GET['action'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $logs = $this->auditService->getSystemActivity(100, 0, $filters);

        render('audit/system-activity', [
            'title' => 'System Activity',
            'pageTitle' => 'System Activity',
            'pageSubtitle' => 'Semua aktivitas sistem',
            'navActive' => 'audit',
            'user' => $this->authService->currentUser(),
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }

    public function showSecurityEvents(): void
    {
        $this->requirePermission('audit.read');
        $filters = [
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];
        $events = $this->auditService->getSecurityEvents(50, $filters);

        render('audit/security-events', [
            'title' => 'Security Events',
            'pageTitle' => 'Security Events',
            'pageSubtitle' => 'Event keamanan',
            'navActive' => 'audit',
            'user' => $this->authService->currentUser(),
            'events' => $events,
            'filters' => $filters,
        ]);
    }

    public function exportActivity(): void
    {
        $this->requirePermission('audit.read');
        $filters = [
            'action' => $_GET['action'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ];

        $csv = $this->auditService->exportActivityLogs($filters);
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="activity_logs_' . date('Y-m-d') . '.csv"');
        header('Content-Length: ' . strlen($csv));
        echo $csv;
    }

    public function getUserActivityData(): void
    {
        $this->requirePermission('audit.read');
        $user = $this->authService->currentUser();
        $logs = $this->auditService->getUserActivity($user['_id'], 50, 0, [
            'action' => $_GET['action'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'logs' => $logs, 'total' => count($logs)]);
    }

    public function getSystemActivityData(): void
    {
        $this->requirePermission('audit.read');
        $logs = $this->auditService->getSystemActivity(100, 0, [
            'action' => $_GET['action'] ?? '',
            'user_id' => $_GET['user_id'] ?? '',
            'date_from' => $_GET['date_from'] ?? '',
            'date_to' => $_GET['date_to'] ?? '',
        ]);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'logs' => $logs, 'total' => count($logs)]);
    }

    public function getDashboardSummaryData(): void
    {
        $this->requirePermission('audit.read');
        $summary = $this->auditService->getDashboardSummary();
        $securityEvents = $this->auditService->getSecurityEvents(5);

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'summary' => $summary,
            'securityEvents' => $securityEvents,
            'topActions' => $summary['top_actions'] ?? [],
        ]);
    }
}
