<?php

namespace App\Controllers;

use App\Services\DatabaseService;
use App\Services\AuthService;
use App\Services\AuditService;
use App\Services\BackupService;
use Flight;

class DatabaseController extends BaseController
{
    private DatabaseService $dbService;
    private AuditService $audit;
    private BackupService $backup;

    public function __construct()
    {
        parent::__construct();
        $this->dbService = new DatabaseService();
        $this->audit = new AuditService();
        $this->backup = new BackupService();
    }

    public function index(): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        $databases = $this->dbService->listDatabasesForUser($user);

        render('databases/index', [
            'title' => 'Databases',
            'pageTitle' => 'Databases',
            'pageSubtitle' => 'Kelola database BangronDB Anda',
            'navActive' => 'databases',
            'user' => $user,
            'databases' => $databases,
        ]);
    }

    public function create(): void
    {
        $this->requirePermission('database.create');
        verify_csrf();
        $name = trim((string) request_post('name'));
        $label = trim((string) request_post('label'));
        $ownerEmail = trim((string) request_post('owner_email'));

        $owner = $this->authService->currentUser();
        if ($ownerEmail !== '') {
            $found = $this->authService->getUserByEmail($ownerEmail);
            if (!$found) {
                flash('error', 'Owner email not found.');
                redirect('/databases');
            }
            $owner = $found;
        }

        try {
            $entry = $this->dbService->create($name, $label, $owner['_id']);
            $this->audit->log($this->authService->currentUser(), 'database.create', $entry['_id']);
            flash('success', 'Database created.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }

        redirect('/databases');
    }

    public function show(string $name): void
    {
        $this->dbService->syncRegistry();
        $this->requireDbAccess($name, 'viewer');

        $db = $this->dbService->ensureRegistered($name);
        if (!$db) {
            Flight::halt(404, 'Database not found');
        }

        $collections = $this->dbService->listCollections($name);
        $user = $this->authService->currentUser();
        $backups = $this->backup->listBackups($name);
        $permissions = [];
        $recentActivity = $this->audit->list(10);

        render('databases/show', [
            'title' => 'Database: ' . $name,
            'pageTitle' => 'Database',
            'pageSubtitle' => $db['label'] ?? $name,
            'navActive' => 'databases',
            'db' => $db,
            'user' => $user,
            'collections' => $collections,
            'backups' => $backups,
            'permissions' => $permissions,
            'recent_activity' => array_map(static function ($log): array {
                return [
                    'icon' => 'activity',
                    'description' => $log['action'] ?? 'activity',
                    'time' => $log['created_at'] ?? '',
                    'collection' => $log['collection_name'] ?? 'Database',
                ];
            }, $recentActivity),
        ]);
    }

    public function updatePermissions(string $name): void
    {
        $this->requirePermission('database.create');
        verify_csrf();

        $role = trim((string) request_post('role'));
        $email = trim((string) request_post('email'));

        if (!in_array($role, ['owner', 'admin', 'viewer'], true)) {
            flash('error', 'Invalid role.');
            redirect('/databases/' . $name);
        }

        $user = $this->authService->getUserByEmail($email);
        if (!$user) {
            flash('error', 'User not found.');
            redirect('/databases/' . $name);
        }

        $this->dbService->setUserDbRole($user['_id'], $name, $role);
        $this->audit->log($this->authService->currentUser(), 'database.permission', $name, null, null, [
            'target_user' => $user['_id'],
            'role' => $role,
        ]);
        flash('success', 'Permission updated.');
        redirect('/databases/' . $name);
    }

    public function settings(string $name): void
    {
        $this->requireDbAccess($name, 'admin');
        $db = $this->dbService->ensureRegistered($name);
        if (!$db) {
            Flight::halt(404, 'Database not found');
        }

        $health = $this->dbService->getHealthReport($name);
        $backups = $this->backup->listBackups($name);

        render('databases/settings', [
            'title' => 'Database Settings',
            'pageTitle' => 'Database Settings',
            'pageSubtitle' => $name,
            'navActive' => 'databases',
            'db' => $db,
            'health' => $health,
            'backups' => $backups,
        ]);
    }

    public function saveSettings(string $name): void
    {
        $this->requireDbAccess($name, 'admin');
        verify_csrf();

        $status = trim((string) request_post('status', 'active'));
        $label = trim((string) request_post('label', $name));
        if (!in_array($status, ['active', 'disabled'], true)) {
            flash('error', 'Invalid status');
            redirect('/databases/' . $name . '/settings');
        }

        $this->dbService->updateMetadata($name, [
            'label' => $label,
            'status' => $status,
        ]);
        $this->audit->log($this->authService->currentUser(), 'database.settings', $name, null, null, [
            'label' => $label,
            'status' => $status,
        ]);
        flash('success', 'Database settings updated.');
        redirect('/databases/' . $name . '/settings');
    }

    public function createBackup(string $name): void
    {
        $this->requirePermission('database.backup');
        $this->requireDbAccess($name, 'admin');
        verify_csrf();
        try {
            $backup = $this->backup->createBackup($name, $this->authService->currentUser()['_id'] ?? 'system');
            $this->audit->log($this->authService->currentUser(), 'database.backup', $name, null, null, ['backup_id' => $backup['_id']]);
            flash('success', 'Backup created.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/databases/' . $name . '/settings');
    }

    public function restoreBackup(string $name): void
    {
        $this->requirePermission('database.restore');
        $this->requireDbAccess($name, 'owner');
        verify_csrf();
        $backupId = trim((string) request_post('backup_id'));
        if ($backupId === '') {
            flash('error', 'Backup ID is required');
            redirect('/databases/' . $name . '/settings');
        }
        try {
            $result = $this->backup->restoreBackup($backupId, $this->authService->currentUser()['_id'] ?? 'system');
            $this->audit->log($this->authService->currentUser(), 'database.restore', $name, null, null, $result);
            flash('success', 'Database restored from snapshot.');
        } catch (\Throwable $e) {
            flash('error', $e->getMessage());
        }
        redirect('/databases/' . $name . '/settings');
    }

    public function listBackups(string $name): void
    {
        $this->requirePermission('database.backup');
        $this->requireDbAccess($name, 'admin');
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['items' => $this->backup->listBackups($name)]);
    }

    public function pruneBackups(string $name): void
    {
        $this->requirePermission('database.backup');
        $this->requireDbAccess($name, 'admin');
        verify_csrf();
        $keep = (int) request_post('keep_latest', 10);
        $deleted = $this->backup->pruneBackups($name, $keep);
        $this->audit->log($this->authService->currentUser(), 'database.backup.prune', $name, null, null, ['deleted' => $deleted, 'keep' => $keep]);
        flash('success', 'Pruned backups: ' . $deleted);
        redirect('/databases/' . $name . '/settings');
    }
}
