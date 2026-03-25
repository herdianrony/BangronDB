<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\SetupService;
use App\Services\DatabaseService;
use Flight;

class BaseController
{
    protected AuthService $authService;
    protected DatabaseService $databaseService;

    public function __construct()
    {
        $this->authService = new AuthService();
        $this->databaseService = new DatabaseService();
    }

    protected function requireInstalled(): void
    {
        $setup = new SetupService();
        if (!$setup->isInstalled()) {
            redirect('/setup');
        }
    }

    protected function requireLogin(): void
    {
        $this->requireInstalled();
        if (!$this->authService->currentUser()) {
            redirect('/login');
        }
    }

    protected function requirePermission(string $permission): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        if (!$this->authService->hasPermission($user, $permission)) {
            Flight::halt(403, 'Permission denied');
        }
    }

    protected function requireDbAccess(string $dbName, string $minRole = 'viewer'): void
    {
        $this->requireLogin();
        $user = $this->authService->currentUser();
        if (!$this->databaseService->userCanAccess($user, $dbName, $minRole)) {
            Flight::halt(403, 'Database access denied');
        }
    }
}
