<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\BackupService;
use App\Services\NotificationService;
use App\Services\TerminalService;

class StudioController extends BaseController
{
    private NotificationService $notificationService;
    private AuditService $auditService;
    private TerminalService $terminalService;
    private BackupService $backupService;

    public function __construct()
    {
        parent::__construct();
        $this->notificationService = new NotificationService();
        $this->auditService = new AuditService();
        $this->terminalService = new TerminalService();
        $this->backupService = new BackupService();
    }

    public function notifications(): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        render('studio/notifications', [
            'title' => 'Notifications',
            'pageTitle' => 'Notifications',
            'pageSubtitle' => 'Inbox notifications',
            'navActive' => 'notifications',
            'user' => $user,
            'items' => $this->notificationService->listForUser($user['_id'] ?? null, 50),
        ]);
    }

    public function logs(): void
    {
        $this->requirePermission('audit.read');
        render('studio/logs', [
            'title' => 'Logs',
            'pageTitle' => 'Logs',
            'pageSubtitle' => 'Recent system logs',
            'navActive' => 'logs',
            'user' => $this->authService->currentUser(),
            'logs' => $this->auditService->list(100),
        ]);
    }

    public function backup(): void
    {
        $this->requirePermission('database.backup');
        $dbName = trim((string) request_get('db'));
        render('studio/backup', [
            'title' => 'Backup',
            'pageTitle' => 'Backup',
            'pageSubtitle' => $dbName ?: 'Select database',
            'navActive' => 'databases',
            'user' => $this->authService->currentUser(),
            'dbName' => $dbName,
            'backups' => $dbName ? $this->backupService->listBackups($dbName) : [],
        ]);
    }

    public function terminal(): void
    {
        $this->requirePermission('audit.read');
        render('studio/terminal', [
            'title' => 'Terminal',
            'pageTitle' => 'Diagnostics Terminal',
            'pageSubtitle' => 'Read-only diagnostics',
            'navActive' => 'monitoring',
            'user' => $this->authService->currentUser(),
            'allowed' => $this->terminalService->allowedCommands(),
            'logs' => $this->terminalService->logs(100),
        ]);
    }

    public function terminalRun(): void
    {
        $this->requirePermission('audit.read');
        verify_csrf();
        $command = trim((string) request_post('command'));
        $dbName = trim((string) request_post('db_name'));
        try {
            $this->terminalService->execute($command, $dbName === '' ? null : $dbName);
            flash('success', 'Command executed.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/terminal');
    }

    public function settings(): void
    {
        $this->requirePermission('audit.read');
        $settings = (new \App\Services\SystemService())->systemDb()->settings->find()->limit(100)->toArray();
        render('studio/settings', [
            'title' => 'Settings',
            'pageTitle' => 'Settings',
            'pageSubtitle' => 'System settings',
            'navActive' => 'settings',
            'user' => $this->authService->currentUser(),
            'settings' => $settings,
        ]);
    }

    public function apiDocs(): void
    {
        $this->requireLogin();
        render('studio/api-docs', [
            'title' => 'API Docs',
            'pageTitle' => 'API Docs',
            'pageSubtitle' => 'Available JSON endpoints',
            'navActive' => 'docs',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function hooks(): void
    {
        $this->requirePermission('collection.manage');
        render('studio/hooks', [
            'title' => 'Hooks',
            'pageTitle' => 'Hooks',
            'pageSubtitle' => 'Collection hooks configuration',
            'navActive' => 'collections',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function relationships(): void
    {
        $this->requirePermission('collection.manage');
        render('studio/relationships', [
            'title' => 'Relationships',
            'pageTitle' => 'Relationships',
            'pageSubtitle' => 'Collection relationship mapping',
            'navActive' => 'collections',
            'user' => $this->authService->currentUser(),
        ]);
    }

    public function importExport(): void
    {
        $this->requirePermission('document.read');
        render('studio/import-export', [
            'title' => 'Import Export',
            'pageTitle' => 'Import Export',
            'pageSubtitle' => 'Bulk data operations',
            'navActive' => 'query',
            'user' => $this->authService->currentUser(),
        ]);
    }
}
