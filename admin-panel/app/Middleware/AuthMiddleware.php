<?php

namespace App\Middleware;

use App\Services\AuthService;
use App\Services\SetupService;

class AuthMiddleware
{
    public function handle(): void
    {
        $setup = new SetupService();
        if (!$setup->isInstalled()) {
            redirect('/setup');
        }

        $auth = new AuthService();
        if (!$auth->currentUser()) {
            redirect('/login');
        }
    }
}
