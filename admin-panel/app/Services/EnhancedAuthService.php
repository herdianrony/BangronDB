<?php

namespace App\Services;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use PragmaRX\Google2FA\Google2FA;

class EnhancedAuthService
{
    private SystemService $system;
    private Google2FA $google2fa;
    private array $config;
    private array $rateLimit = [];
    
    public function __construct()
    {
        $this->system = new SystemService();
        $this->google2fa = new Google2FA();
        $this->config = [
            'jwt_secret' => $_ENV['JWT_SECRET'] ?? bin2hex(random_bytes(32)),
            'jwt_algorithm' => 'HS256',
            'session_lifetime' => 3600, // 1 hour
            'remember_me_lifetime' => 2592000, // 30 days
            'max_login_attempts' => 5,
            'lockout_duration' => 900, // 15 minutes
            'require_2fa' => false,
            'password_min_length' => 8,
            'password_require_uppercase' => true,
            'password_require_lowercase' => true,
            'password_require_numbers' => true,
            'password_require_special' => true,
            'session_timeout' => 1800, // 30 minutes
            'max_concurrent_sessions' => 3,
        ];
    }
    
    /**
     * Get current user with enhanced session management
     */
    public function currentUser(): ?array
    {
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        
        // Check session timeout
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > $this->config['session_timeout'])) {
            $this->logout();
            return null;
        }
        
        // Update last activity
        $_SESSION['last_activity'] = time();
        
        $user = $this->getUserById($_SESSION['user_id']);
        if (!$user) {
            $this->logout();
            return null;
        }
        
        // Check if user is still active
        if (($user['status'] ?? 'active') !== 'active') {
            $this->logout();
            return null;
        }
        
        // Add session information
        $user['session_id'] = session_id();
        $user['ip_address'] = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $user['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $user['last_activity'] = $_SESSION['last_activity'];
        
        return $user;
    }
    
    /**
     * Get user by ID with caching
     */
    public function getUserById(string $id): ?array
    {
        static $userCache = [];
        
        if (isset($userCache[$id])) {
            return $userCache[$id];
        }
        
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['_id' => $id]);
        
        if ($user) {
            // Remove sensitive data
            unset($user['password_hash'], $user['password_reset_token'], $user['two_factor_secret']);
            $userCache[$id] = $user;
        }
        
        return $user;
    }
    
    /**
     * Get user by email
     */
    public function getUserByEmail(string $email): ?array
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        $user = $users->findOne(['email' => $email]);
        
        return $user ?: null;
    }
    
    /**
     * Enhanced authentication with rate limiting and security checks
     */
    public function attempt(string $email, string $password, string $ipAddress = null): ?array
    {
        $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        // Check rate limiting
        if ($this->isRateLimited($ipAddress)) {
            throw new \Exception('Too many login attempts. Please try again later.');
        }
        
        // Check if IP is blocked
        if ($this->isIpBlocked($ipAddress)) {
            throw new \Exception('IP address is blocked. Please contact administrator.');
        }
        
        // Validate input
        if (empty($email) || empty($password)) {
            $this->recordFailedAttempt($ipAddress);
            return null;
        }
        
        $user = $this->getUserByEmail($email);
        if (!$user) {
            $this->recordFailedAttempt($ipAddress);
            return null;
        }
        
        // Check user status
        if (($user['status'] ?? 'active') !== 'active') {
            $this->recordFailedAttempt($ipAddress);
            return null;
        }
        
        // Check if account is locked
        if (isset($user['locked_until']) && $user['locked_until'] > time()) {
            throw new \Exception('Account is locked. Please try again later.');
        }
        
        // Verify password
        if (!password_verify($password, $user['password_hash'] ?? '')) {
            $this->recordFailedAttempt($ipAddress, $user['_id']);
            return null;
        }
        
        // Check if password needs rehashing
        if (password_needs_rehash($user['password_hash'] ?? '', PASSWORD_DEFAULT)) {
            $this->updatePasswordHash($user['_id'], $password);
        }
        
        // Check 2FA if enabled
        if ($this->requiresTwoFactor($user)) {
            $this->initTwoFactorVerification($user);
            return ['requires_2fa' => true, 'user_id' => $user['_id']];
        }
        
        // Clear failed attempts on successful login
        $this->clearFailedAttempts($user['_id']);
        
        // Log successful login
        $this->logUserActivity($user['_id'], 'login', [
            'ip_address' => $ipAddress,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'device_info' => $this->getDeviceInfo(),
        ]);
        
        return $user;
    }
    
    /**
     * Complete login process after 2FA verification
     */
    public function completeLogin(array $user, string $ipAddress = null): void
    {
        $ipAddress = $ipAddress ?? ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        
        // Create session
        $_SESSION['user_id'] = $user['_id'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();
        $_SESSION['ip_address'] = $ipAddress;
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        
        // Update user login info
        $this->updateLoginInfo($user['_id'], $ipAddress);
        
        // Check concurrent sessions
        $this->manageConcurrentSessions($user['_id']);
        
        // Regenerate session ID for security
        session_regenerate_id(true);
        
        // Set session security headers
        $this->setSessionSecurity();
    }
    
    /**
     * Login with enhanced security features
     */
    public function login(array $user, bool $rememberMe = false): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        
        if ($rememberMe) {
            $_SESSION['remember_me'] = true;
            $_SESSION['expires_at'] = time() + $this->config['remember_me_lifetime'];
        }
        
        $this->completeLogin($user, $ipAddress);
    }
    
    /**
     * Enhanced logout with cleanup
     */
    public function logout(): void
    {
        if (!empty($_SESSION['user_id'])) {
            // Log logout activity
            $this->logUserActivity($_SESSION['user_id'], 'logout', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
            ]);
        }
        
        // Destroy session
        $_SESSION = [];
        
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params['path'], $params['domain'], $params['secure'], $params['httponly']
            );
        }
        
        session_destroy();
        
        // Clear rate limiting for this IP
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        unset($this->rateLimit[$ipAddress]);
    }
    
    /**
     * Check if user has permission with inheritance support
     */
    public function hasPermission(?array $user, string $permission): bool
    {
        if (!$user) {
            return false;
        }
        
        // Super admin has all permissions
        if (($user['role_id'] ?? '') === 'super_admin') {
            return true;
        }
        
        // Check direct permission
        if ($this->hasDirectPermission($user, $permission)) {
            return true;
        }
        
        // Check inherited permissions
        return $this->hasInheritedPermission($user, $permission);
    }
    
    /**
     * Check if user has direct permission
     */
    private function hasDirectPermission(array $user, string $permission): bool
    {
        $db = $this->system->systemDb();
        $rolePermissions = $db->role_permissions;
        $roleId = $user['role_id'] ?? '';
        
        $match = $rolePermissions->findOne([
            'role_id' => $roleId,
            'permission_key' => $permission,
            'granted' => true,
        ]);
        
        return (bool) $match;
    }
    
    /**
     * Check if user has inherited permission
     */
    private function hasInheritedPermission(array $user, string $permission): bool
    {
        $db = $this->system->systemDb();
        $roles = $db->roles;
        $roleHierarchy = $db->role_hierarchy;
        
        $roleId = $user['role_id'] ?? '';
        $currentRole = $roles->findOne(['_id' => $roleId]);
        
        if (!$currentRole) {
            return false;
        }
        
        // Get all parent roles
        $parentRoles = [];
        $currentParentId = $currentRole['parent_id'] ?? null;
        
        while ($currentParentId) {
            $parentRole = $roles->findOne(['_id' => $currentParentId]);
            if (!$parentRole) {
                break;
            }
            
            $parentRoles[] = $currentParentId;
            $currentParentId = $parentRole['parent_id'] ?? null;
        }
        
        // Check permissions for all parent roles
        foreach ($parentRoles as $parentRoleId) {
            if ($this->hasDirectPermission(['role_id' => $parentRoleId], $permission)) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Generate JWT token for API authentication
     */
    public function generateJwtToken(array $user): string
    {
        $payload = [
            'sub' => $user['_id'],
            'email' => $user['email'],
            'role' => $user['role_id'],
            'iat' => time(),
            'exp' => time() + $this->config['session_lifetime'],
            'jti' => bin2hex(random_bytes(16)), // JWT ID
        ];
        
        return JWT::encode($payload, $this->config['jwt_secret'], $this->config['jwt_algorithm']);
    }
    
    /**
     * Verify JWT token
     */
    public function verifyJwtToken(string $token): ?array
    {
        try {
            $decoded = JWT::decode($token, new Key($this->config['jwt_secret'], $this->config['jwt_algorithm']));
            
            // Check if user still exists and is active
            $user = $this->getUserById($decoded->sub);
            if (!$user || ($user['status'] ?? 'active') !== 'active') {
                return null;
            }
            
            return [
                'user_id' => $decoded->sub,
                'email' => $decoded->email,
                'role' => $decoded->role,
                'token' => $token,
            ];
        } catch (ExpiredException $e) {
            return null;
        } catch (\Exception $e) {
            return null;
        }
    }
    
    /**
     * Rate limiting
     */
    private function isRateLimited(string $ipAddress): bool
    {
        if (!isset($this->rateLimit[$ipAddress])) {
            $this->rateLimit[$ipAddress] = [
                'attempts' => 0,
                'first_attempt' => time(),
            ];
        }
        
        $attempts = &$this->rateLimit[$ipAddress]['attempts'];
        $firstAttempt = $this->rateLimit[$ipAddress]['first_attempt'];
        
        // Reset counter if window has passed
        if (time() - $firstAttempt > $this->config['lockout_duration']) {
            $attempts = 0;
            $firstAttempt = time();
        }
        
        return $attempts >= $this->config['max_login_attempts'];
    }
    
    private function recordFailedAttempt(string $ipAddress, string $userId = null): void
    {
        if (!isset($this->rateLimit[$ipAddress])) {
            $this->rateLimit[$ipAddress] = [
                'attempts' => 0,
                'first_attempt' => time(),
            ];
        }
        
        $this->rateLimit[$ipAddress]['attempts']++;
        
        // Lock user account if too many attempts
        if ($userId && $this->rateLimit[$ipAddress]['attempts'] >= $this->config['max_login_attempts']) {
            $this->lockUserAccount($userId, time() + $this->config['lockout_duration']);
        }
    }
    
    private function clearFailedAttempts(string $userId): void
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        
        $users->updateOne(
            ['_id' => $userId],
            ['$unset' => ['failed_attempts' => 1, 'locked_until' => 1]]
        );
    }
    
    /**
     * Two-factor authentication
     */
    private function requiresTwoFactor(array $user): bool
    {
        return !empty($user['two_factor_enabled']) && $user['two_factor_enabled'] === true;
    }
    
    private function initTwoFactorVerification(array $user): void
    {
        $_SESSION['pending_2fa_user_id'] = $user['_id'];
        $_SESSION['2fa_expires_at'] = time() + 300; // 5 minutes
    }
    
    public function verifyTwoFactorCode(string $userId, string $code): bool
    {
        $user = $this->getUserById($userId);
        if (!$user || !$this->requiresTwoFactor($user)) {
            return false;
        }
        
        // Check if 2FA session is valid
        if (!isset($_SESSION['pending_2fa_user_id']) || $_SESSION['pending_2fa_user_id'] !== $userId) {
            return false;
        }
        
        if (time() > $_SESSION['2fa_expires_at']) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['2fa_expires_at']);
            return false;
        }
        
        // Verify the code
        $secret = $user['two_factor_secret'];
        if (empty($secret)) {
            return false;
        }
        
        $isValid = $this->google2fa->verifyKey($secret, $code);
        
        if ($isValid) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['2fa_expires_at']);
            $this->completeLogin($user);
        }
        
        return $isValid;
    }
    
    /**
     * Password management
     */
    public function validatePasswordStrength(string $password): array
    {
        $errors = [];
        
        if (strlen($password) < $this->config['password_min_length']) {
            $errors[] = "Password must be at least {$this->config['password_min_length']} characters long";
        }
        
        if ($this->config['password_require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            $errors[] = "Password must contain at least one uppercase letter";
        }
        
        if ($this->config['password_require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            $errors[] = "Password must contain at least one lowercase letter";
        }
        
        if ($this->config['password_require_numbers'] && !preg_match('/[0-9]/', $password)) {
            $errors[] = "Password must contain at least one number";
        }
        
        if ($this->config['password_require_special'] && !preg_match('/[^A-Za-z0-9]/', $password)) {
            $errors[] = "Password must contain at least one special character";
        }
        
        return $errors;
    }
    
    public function hashPassword(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT, ['cost' => 12]);
    }
    
    private function updatePasswordHash(string $userId, string $password): void
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        
        $users->updateOne(
            ['_id' => $userId],
            ['$set' => ['password_hash' => $this->hashPassword($password)]]
        );
    }
    
    /**
     * Session management
     */
    private function manageConcurrentSessions(string $userId): void
    {
        $db = $this->system->systemDb();
        $userSessions = $db->user_sessions;
        
        // Get current sessions
        $sessions = $userSessions->find(['user_id' => $userId]);
        $sessionList = iterator_to_array($sessions);
        
        // Remove oldest sessions if limit exceeded
        if (count($sessionList) >= $this->config['max_concurrent_sessions']) {
            usort($sessionList, function($a, $b) {
                return $a['created_at'] <=> $b['created_at'];
            });
            
            $sessionsToRemove = array_slice($sessionList, 0, count($sessionList) - $this->config['max_concurrent_sessions'] + 1);
            
            foreach ($sessionsToRemove as $session) {
                $userSessions->deleteOne(['_id' => $session['_id']]);
            }
        }
        
        // Record current session
        $userSessions->insertOne([
            'user_id' => $userId,
            'session_id' => session_id(),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'created_at' => time(),
            'last_activity' => time(),
            'expires_at' => time() + $this->config['session_lifetime'],
        ]);
    }
    
    private function setSessionSecurity(): void
    {
        // Set secure session parameters
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        ini_set('session.use_only_cookies', 1);
        ini_set('session.cookie_path', '/');
    }
    
    /**
     * User activity logging
     */
    private function logUserActivity(string $userId, string $action, array $data = []): void
    {
        try {
            $db = $this->system->systemDb();
            $userActivity = $db->user_activity;
            
            $userActivity->insertOne([
                'user_id' => $userId,
                'action' => $action,
                'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'device_info' => $data['device_info'] ?? $this->getDeviceInfo(),
                'data' => $data,
                'timestamp' => time(),
            ]);
        } catch (\Exception $e) {
            // Log activity should not break the application
            error_log('Failed to log user activity: ' . $e->getMessage());
        }
    }
    
    /**
     * Account management
     */
    private function lockUserAccount(string $userId, int $unlockTime): void
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        
        $users->updateOne(
            ['_id' => $userId],
            [
                '$set' => [
                    'locked_until' => $unlockTime,
                    'status' => 'locked',
                ],
                '$inc' => ['failed_attempts' => 1],
            ]
        );
    }
    
    private function updateLoginInfo(string $userId, string $ipAddress): void
    {
        $db = $this->system->systemDb();
        $users = $db->users;
        
        $users->updateOne(
            ['_id' => $userId],
            [
                '$set' => [
                    'last_login' => time(),
                    'last_login_ip' => $ipAddress,
                    'last_login_user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                ],
                '$unset' => ['failed_attempts' => 1, 'locked_until' => 1],
            ]
        );
    }
    
    /**
     * Security helpers
     */
    private function isIpBlocked(string $ipAddress): bool
    {
        // Implement IP blocking logic
        // This could check against a database of blocked IPs
        return false;
    }
    
    private function getDeviceInfo(): array
    {
        return [
            'browser' => $this->getBrowser(),
            'os' => $this->getOperatingSystem(),
            'device' => $this->getDevice(),
        ];
    }
    
    private function getBrowser(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Firefox') !== false) return 'Firefox';
        if (strpos($userAgent, 'Chrome') !== false) return 'Chrome';
        if (strpos($userAgent, 'Safari') !== false) return 'Safari';
        if (strpos($userAgent, 'Edge') !== false) return 'Edge';
        if (strpos($userAgent, 'Opera') !== false) return 'Opera';
        
        return 'Unknown';
    }
    
    private function getOperatingSystem(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Windows') !== false) return 'Windows';
        if (strpos($userAgent, 'Mac') !== false) return 'macOS';
        if (strpos($userAgent, 'Linux') !== false) return 'Linux';
        if (strpos($userAgent, 'Android') !== false) return 'Android';
        if (strpos($userAgent, 'iOS') !== false) return 'iOS';
        
        return 'Unknown';
    }
    
    private function getDevice(): string
    {
        $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        
        if (strpos($userAgent, 'Mobile') !== false) return 'Mobile';
        if (strpos($userAgent, 'Tablet') !== false) return 'Tablet';
        
        return 'Desktop';
    }
}
