<?php

namespace App\Middleware;

use App\Services\AuthService;
use Flight;

class PermissionMiddleware
{
    public function require(string $permission): void
    {
        $auth = new AuthService();
        $user = $auth->currentUser();
        if (!$user || !$auth->hasPermission($user, $permission)) {
            Flight::halt(403, 'Permission denied');
        }
    }
}
