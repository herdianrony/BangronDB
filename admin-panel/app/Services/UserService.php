<?php

namespace App\Services;

use Ramsey\Uuid\Uuid;

class UserService
{
    private SystemService $system;
    private RoleService $roleService;
    private AuditService $auditService;

    public function __construct()
    {
        $this->system = new SystemService();
        $this->roleService = new RoleService();
        $this->auditService = new AuditService();
    }

    public function getAllUsers(): array
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $result = [];
        foreach ($users->find([]) as $user) {
            $role = $this->roleService->getRoleById($user['role_id'] ?? '');
            $user['role_name'] = $role['name'] ?? 'Unknown';
            $user['full_name'] = $user['first_name'].' '.$user['last_name'];
            $result[] = $user;
        }

        return $result;
    }

    public function getUserById(string $id): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['_id' => $id]);

        if ($user) {
            $role = $this->roleService->getRoleById($user['role_id'] ?? '');
            $user['role_name'] = $role['name'] ?? 'Unknown';
            $user['full_name'] = $user['first_name'].' '.$user['last_name'];
        }

        return $user ?: null;
    }

    public function getUserByEmail(string $email): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['email' => $email]);

        if ($user) {
            $role = $this->roleService->getRoleById($user['role_id'] ?? '');
            $user['role_name'] = $role['name'] ?? 'Unknown';
            $user['full_name'] = $user['first_name'].' '.$user['last_name'];
        }

        return $user ?: null;
    }

    public function getUserByUsername(string $username): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['username' => $username]);

        if ($user) {
            $role = $this->roleService->getRoleById($user['role_id'] ?? '');
            $user['role_name'] = $role['name'] ?? 'Unknown';
            $user['full_name'] = $user['first_name'].' '.$user['last_name'];
        }

        return $user ?: null;
    }

    public function createUser(array $data): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        // Check if email or username already exists
        if ($this->getUserByEmail($data['email'])) {
            throw new \Exception('Email already exists.');
        }

        if ($this->getUserByUsername($data['username'])) {
            throw new \Exception('Username already exists.');
        }

        $userId = Uuid::uuid4()->toString();
        $passwordHash = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;

        $user = [
            '_id' => $userId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $data['role_id'],
            'status' => $data['status'],
            'password_hash' => $passwordHash,
            'force_password_reset' => !empty($data['force_reset']),
            'email_verified' => false,
            'two_factor_enabled' => false,
            'two_factor_secret' => null,
            'last_login' => null,
            'login_attempts' => 0,
            'locked_until' => null,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'created_by' => $_SESSION['user_id'] ?? null,
        ];

        if ($users->insert($user)) {
            return $user;
        }

        return null;
    }

    public function updateUser(string $id, array $data): bool
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $user = $users->findOne(['_id' => $id]);
        if (!$user) {
            return false;
        }

        // Check if email or username already exists for other users
        if (isset($data['email']) && $data['email'] !== $user['email']) {
            if ($this->getUserByEmail($data['email'])) {
                throw new \Exception('Email already exists.');
            }
        }

        if (isset($data['username']) && $data['username'] !== $user['username']) {
            if ($this->getUserByUsername($data['username'])) {
                throw new \Exception('Username already exists.');
            }
        }

        $updateData = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'email' => $data['email'],
            'username' => $data['username'],
            'phone' => $data['phone'] ?? null,
            'role_id' => $data['role_id'],
            'status' => $data['status'],
            'updated_at' => date('Y-m-d H:i:s'),
        ];

        if (!empty($data['password'])) {
            $updateData['password_hash'] = password_hash($data['password'], PASSWORD_DEFAULT);
            $updateData['force_password_reset'] = false;
        }

        return $users->update(['_id' => $id], ['$set' => $updateData]);
    }

    public function deleteUser(string $id): bool
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        // Don't allow deletion of super admin
        $user = $users->findOne(['_id' => $id]);
        if ($user && ($user['role_id'] === 'super_admin' || $user['email'] === 'admin@bangrondb.io')) {
            throw new \Exception('Cannot delete super admin user.');
        }

        return $users->delete(['_id' => $id]);
    }

    public function updateUserStatus(string $id, string $status): bool
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        return $users->update(['_id' => $id], ['$set' => [
            'status' => $status,
            'updated_at' => date('Y-m-d H:i:s'),
        ]]);
    }

    public function validateUserData(array $data, ?string $excludeId = null): array
    {
        $errors = [];

        // Validate first name
        if (empty($data['first_name'])) {
            $errors[] = 'First name is required.';
        } elseif (strlen($data['first_name']) < 2) {
            $errors[] = 'First name must be at least 2 characters.';
        }

        // Validate last name
        if (empty($data['last_name'])) {
            $errors[] = 'Last name is required.';
        } elseif (strlen($data['last_name']) < 2) {
            $errors[] = 'Last name must be at least 2 characters.';
        }

        // Validate email
        if (empty($data['email'])) {
            $errors[] = 'Email is required.';
        } elseif (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Invalid email format.';
        } else {
            // Check if email already exists
            $existingUser = $this->getUserByEmail($data['email']);
            if ($existingUser && (!$excludeId || $existingUser['_id'] !== $excludeId)) {
                $errors[] = 'Email already exists.';
            }
        }

        // Validate username
        if (empty($data['username'])) {
            $errors[] = 'Username is required.';
        } elseif (strlen($data['username']) < 3) {
            $errors[] = 'Username must be at least 3 characters.';
        } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $data['username'])) {
            $errors[] = 'Username can only contain letters, numbers, and underscores.';
        } else {
            // Check if username already exists
            $existingUser = $this->getUserByUsername($data['username']);
            if ($existingUser && (!$excludeId || $existingUser['_id'] !== $excludeId)) {
                $errors[] = 'Username already exists.';
            }
        }

        // Validate role
        if (empty($data['role_id'])) {
            $errors[] = 'Role is required.';
        } else {
            $roleService = new RoleService();
            $role = $roleService->getRoleById($data['role_id']);
            if (!$role) {
                $errors[] = 'Invalid role selected.';
            }
        }

        // Validate password (required for new users, optional for updates)
        if (empty($excludeId) && empty($data['password'])) {
            $errors[] = 'Password is required.';
        } elseif (!empty($data['password']) && strlen($data['password']) < 8) {
            $errors[] = 'Password must be at least 8 characters long.';
        } elseif (!empty($data['password']) && !preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $data['password'])) {
            $errors[] = 'Password must contain at least one uppercase letter, one lowercase letter, one number, and one special character.';
        }

        // Validate status
        if (!in_array($data['status'], ['active', 'inactive', 'suspended'])) {
            $errors[] = 'Invalid status selected.';
        }

        // Validate phone (if provided)
        if (!empty($data['phone']) && !preg_match('/^\+?[\d\s\-\(\)]+$/', $data['phone'])) {
            $errors[] = 'Invalid phone number format.';
        }

        return $errors;
    }

    public function getUserPermissions(string $userId): array
    {
        $db = $this->system->systemDb();
        $userPermissions = $db->user_permissions;

        $permissions = [];
        foreach ($userPermissions->find(['user_id' => $userId]) as $permission) {
            $permissions[] = $permission;
        }

        return $permissions;
    }

    public function updateUserPermissions(string $userId, array $permissions, string $roleId): bool
    {
        $db = $this->system->systemDb();
        $userPermissions = $db->user_permissions;

        // Delete existing permissions
        $userPermissions->delete(['user_id' => $userId]);

        // Insert new permissions
        foreach ($permissions as $permission) {
            $userPermissions->insert([
                'user_id' => $userId,
                'permission_key' => $permission['key'],
                'granted' => $permission['granted'],
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        }

        // Update user role
        $db->users->update(['_id' => $userId], ['$set' => ['role_id' => $roleId]]);

        return true;
    }

    public function getUserSessions(string $userId): array
    {
        $db = $this->system->systemDb();
        $sessions = $db->user_sessions;

        $userSessions = [];
        foreach ($sessions->find(['user_id' => $userId]) as $session) {
            $userSessions[] = $session;
        }

        return $userSessions;
    }

    public function recordLoginAttempt(string $email, bool $success): void
    {
        $db = $this->system->systemDb();
        $loginAttempts = $db->login_attempts;

        $loginAttempts->insert([
            'email' => $email,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'success' => $success,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function incrementLoginAttempts(string $email): int
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $user = $users->findOne(['email' => $email]);
        if (!$user) {
            return 0;
        }

        $attempts = ($user['login_attempts'] ?? 0) + 1;
        $lockedUntil = null;

        // Lock account after 5 failed attempts
        if ($attempts >= 5) {
            $lockedUntil = date('Y-m-d H:i:s', strtotime('+30 minutes'));
        }

        $users->update(['email' => $email], ['$set' => [
            'login_attempts' => $attempts,
            'locked_until' => $lockedUntil,
        ]]);

        return $attempts;
    }

    public function resetLoginAttempts(string $email): void
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $users->update(['email' => $email], ['$set' => [
            'login_attempts' => 0,
            'locked_until' => null,
        ]]);
    }

    public function isAccountLocked(string $email): bool
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $user = $users->findOne(['email' => $email]);
        if (!$user) {
            return false;
        }

        $lockedUntil = $user['locked_until'] ?? null;
        if (!$lockedUntil) {
            return false;
        }

        return strtotime($lockedUntil) > time();
    }

    public function sendPasswordReset(array $user): bool
    {
        // Generate reset token
        $resetToken = bin2hex(random_bytes(32));
        $resetExpires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = $this->system->systemDb();
        $passwordResets = $db->password_resets;

        // Delete existing tokens
        $passwordResets->delete(['user_id' => $user['_id']]);

        // Insert new token
        $passwordResets->insert([
            'user_id' => $user['_id'],
            'token' => $resetToken,
            'expires_at' => $resetExpires,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send email (placeholder - implement actual email sending)
        $resetLink = 'https://'.$_SERVER['HTTP_HOST'].'/reset-password?token='.$resetToken;

        // TODO: Implement actual email sending
        // $this->emailService->sendPasswordReset($user['email'], $resetLink);

        return true;
    }

    public function sendInvitation(array $user): bool
    {
        // Generate invitation token
        $invitationToken = bin2hex(random_bytes(32));
        $invitationExpires = date('Y-m-d H:i:s', strtotime('+7 days'));

        $db = $this->system->systemDb();
        $userInvitations = $db->user_invitations;

        // Delete existing invitations
        $userInvitations->delete(['user_id' => $user['_id']]);

        // Insert new invitation
        $userInvitations->insert([
            'user_id' => $user['_id'],
            'token' => $invitationToken,
            'expires_at' => $invitationExpires,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        // Send email (placeholder - implement actual email sending)
        $invitationLink = 'https://'.$_SERVER['HTTP_HOST'].'/accept-invitation?token='.$invitationToken;

        // TODO: Implement actual email sending
        // $this->emailService->sendInvitation($user['email'], $invitationLink);

        return true;
    }

    public function getUserStats(): array
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $totalUsers = count($users->find([]));
        $activeUsers = count($users->find(['status' => 'active']));
        $inactiveUsers = count($users->find(['status' => 'inactive']));
        $suspendedUsers = count($users->find(['status' => 'suspended']));

        return [
            'total' => $totalUsers,
            'active' => $activeUsers,
            'inactive' => $inactiveUsers,
            'suspended' => $suspendedUsers,
        ];
    }

    public function searchUsers(string $query): array
    {
        $db = $this->system->systemDb();
        $users = $db->users;

        $regexQuery = new \MongoDB\BSON\Regex($query, 'i');

        $results = [];
        foreach ($users->find([
            '$or' => [
                ['first_name' => $regexQuery],
                ['last_name' => $regexQuery],
                ['email' => $regexQuery],
                ['username' => $regexQuery],
            ],
        ]) as $user) {
            $role = $this->roleService->getRoleById($user['role_id'] ?? '');
            $user['role_name'] = $role['name'] ?? 'Unknown';
            $user['full_name'] = $user['first_name'].' '.$user['last_name'];
            $results[] = $user;
        }

        return $results;
    }
}
