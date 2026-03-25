<?php

namespace App\Services;

class AuthService
{
    private SystemService $system;

    public function __construct()
    {
        $this->system = new SystemService();
    }

    public function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }

        return $this->getUserById($_SESSION['user_id']);
    }

    public function getUserById(string $id): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['_id' => $id]);

        return $user ?: null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['email' => $email]);

        return $user ?: null;
    }

    public function attempt(string $email, string $password): ?array
    {
        $user = $this->getUserByEmail($email);
        if (!$user) {
            return null;
        }
        if (($user['status'] ?? 'active') !== 'active') {
            return null;
        }
        if (!password_verify($password, $user['password_hash'] ?? '')) {
            return null;
        }

        return $user;
    }

    public function login(array $user): void
    {
        $_SESSION['user_id'] = $user['_id'];
    }

    public function logout(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        session_destroy();
    }

    public function hasPermission(?array $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }
        if (($user['role_id'] ?? '') === 'super_admin') {
            return true;
        }

        $db = $this->system->systemDb();
        $rolePermissions = $db->role_permissions;
        $roleId = $user['role_id'] ?? '';
        $match = $rolePermissions->findOne([
            'role_id' => $roleId,
            'permission_key' => $permission,
        ]);

        return (bool) $match;
    }
}
