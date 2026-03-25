<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\SetupService;
use App\Services\SystemService;

class AuthController
{
    private AuthService $auth;
    private SystemService $system;

    public function __construct()
    {
        $this->auth = new AuthService();
        $this->system = new SystemService();
    }

    public function showLogin(): void
    {
        $setup = new SetupService();
        if (!$setup->isInstalled()) {
            redirect('/setup');
        }

        if ($this->auth->currentUser()) {
            redirect('/dashboard');
        }

        render('auth/login', [
            'title' => 'Login',
            'pageTitle' => 'Login',
        ]);
    }

    public function login(): void
    {
        $setup = new SetupService();
        if (!$setup->isInstalled()) {
            redirect('/setup');
        }

        verify_csrf();
        $email = trim((string) request_post('email'));
        $password = (string) request_post('password');

        try {
            // Rate limiting check
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            if ($this->isRateLimited($ipAddress)) {
                throw new \Exception('Too many login attempts. Please try again later.');
            }

            $user = $this->auth->attempt($email, $password);
            if (!$user) {
                $this->recordFailedAttempt($ipAddress);
                flash('error', 'Invalid credentials. Please check your email and password.');
                redirect('/login');
            }

            // Clear failed attempts on successful login
            $this->clearFailedAttempts($user['_id']);

            $this->auth->login($user);

            // Log successful login
            $this->logUserActivity($user['_id'], 'login', [
                'ip_address' => $ipAddress,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            flash('success', 'Welcome back! You have been successfully logged in.');
            redirect('/dashboard');
        } catch (\Exception $e) {
            flash('error', $e->getMessage());
            redirect('/login');
        }
    }

    public function logout(): void
    {
        verify_csrf();

        $currentUser = $this->auth->currentUser();
        if ($currentUser) {
            // Log logout activity
            $this->logUserActivity($currentUser['_id'], 'logout', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'session_duration' => time() - ($_SESSION['login_time'] ?? time()),
            ]);
        }

        $this->auth->logout();
        flash('success', 'You have been successfully logged out.');
        redirect('/login');
    }

    /**
     * Show two-factor authentication verification page.
     */
    public function showTwoFactorVerify(): void
    {
        if (!isset($_SESSION['pending_2fa_user_id'])) {
            redirect('/login');
        }

        if (time() > ($_SESSION['2fa_expires_at'] ?? 0)) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['2fa_expires_at']);
            redirect('/login');
        }

        render('auth/2fa-verify', [
            'title' => 'Two-Factor Authentication',
            'pageTitle' => 'Two-Factor Authentication',
        ]);
    }

    /**
     * Verify two-factor authentication code.
     */
    public function verifyTwoFactor(): void
    {
        verify_csrf();

        if (!isset($_SESSION['pending_2fa_user_id'])) {
            redirect('/login');
        }

        if (time() > ($_SESSION['2fa_expires_at'] ?? 0)) {
            unset($_SESSION['pending_2fa_user_id'], $_SESSION['2fa_expires_at']);
            redirect('/login');
        }

        $code = trim((string) request_post('code'));
        $userId = $_SESSION['pending_2fa_user_id'];

        // Simple 2FA verification (in production, use proper 2FA library)
        $isValid = $this->verifyTwoFactorCode($userId, $code);

        if ($isValid) {
            $user = $this->auth->getUserById($userId);
            if ($user) {
                $this->auth->login($user);
                flash('success', 'Two-factor authentication verified successfully!');
                redirect('/dashboard');
            }
        }

        flash('error', 'Invalid two-factor authentication code. Please try again.');
        redirect('/2fa-verify');
    }

    /**
     * Simple 2FA code verification.
     */
    private function verifyTwoFactorCode(string $userId, string $code): bool
    {
        // In production, this would verify against Google Authenticator or similar
        // For demo purposes, accept any 6-digit code
        return preg_match('/^\d{6}$/', $code);
    }

    /**
     * Show password reset request page.
     */
    public function showForgotPassword(): void
    {
        if ($this->auth->currentUser()) {
            redirect('/dashboard');
        }

        render('auth/forgot-password', [
            'title' => 'Forgot Password',
            'pageTitle' => 'Forgot Password',
        ]);
    }

    /**
     * Handle password reset request.
     */
    public function requestPasswordReset(): void
    {
        verify_csrf();

        $email = trim((string) request_post('email'));

        if (empty($email)) {
            flash('error', 'Please enter your email address.');
            redirect('/forgot-password');
        }

        try {
            $user = $this->auth->getUserByEmail($email);

            if (!$user) {
                // Don't reveal if email exists or not for security
                flash('success', 'If an account with that email exists, you will receive a password reset link shortly.');
                redirect('/login');
            }

            // Generate password reset token
            $token = bin2hex(random_bytes(32));
            $expiry = time() + 3600; // 1 hour

            // Store token in database
            $db = $this->system->systemDb();
            $users = $db->users;

            $users->update(
                ['_id' => $user['_id']],
                [
                    '$set' => [
                        'password_reset_token' => $token,
                        'password_reset_expires' => $expiry,
                    ],
                ]
            );

            // Send reset email (in production, you would send an actual email)
            $resetLink = 'https://'.($_SERVER['HTTP_HOST'] ?? 'localhost').'/reset-password?token='.$token;

            // For demo purposes, just show the reset link
            flash('success', 'Password reset link generated: <a href="'.htmlspecialchars($resetLink).'" target="_blank">'.htmlspecialchars($resetLink).'</a>');
            flash('info', 'In a production environment, this link would be sent to your email address.');

            redirect('/login');
        } catch (\Exception $e) {
            flash('error', 'An error occurred. Please try again.');
            redirect('/forgot-password');
        }
    }

    /**
     * Show password reset page.
     */
    public function showResetPassword(): void
    {
        if ($this->auth->currentUser()) {
            redirect('/dashboard');
        }

        $token = $_GET['token'] ?? '';

        if (empty($token)) {
            flash('error', 'Invalid password reset request.');
            redirect('/forgot-password');
        }

        // Verify token exists and is valid
        try {
            $db = $this->system->systemDb();
            $users = $db->users;
            $user = $users->findOne([
                'password_reset_token' => $token,
                'password_reset_expires' => ['$gt' => time()],
            ]);

            if (!$user) {
                flash('error', 'Invalid or expired password reset token.');
                redirect('/forgot-password');
            }

            render('auth/reset-password', [
                'title' => 'Reset Password',
                'pageTitle' => 'Reset Password',
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            flash('error', 'Invalid password reset request.');
            redirect('/forgot-password');
        }
    }

    /**
     * Handle password reset.
     */
    public function resetPassword(): void
    {
        verify_csrf();

        $token = trim((string) request_post('token'));
        $password = (string) request_post('password');
        $confirmPassword = (string) request_post('confirm_password');

        if (empty($token)) {
            flash('error', 'Invalid password reset request.');
            redirect('/forgot-password');
        }

        if ($password !== $confirmPassword) {
            flash('error', 'Passwords do not match.');
            redirect('/reset-password?token='.urlencode($token));
        }

        // Validate password strength
        if (strlen($password) < 8) {
            flash('error', 'Password must be at least 8 characters long.');
            redirect('/reset-password?token='.urlencode($token));
        }

        try {
            $db = $this->system->systemDb();
            $users = $db->users;

            $user = $users->findOne([
                'password_reset_token' => $token,
                'password_reset_expires' => ['$gt' => time()],
            ]);

            if (!$user) {
                flash('error', 'Invalid or expired password reset token.');
                redirect('/forgot-password');
            }

            // Update password
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $users->update(
                ['_id' => $user['_id']],
                [
                    '$set' => [
                        'password_hash' => $hashedPassword,
                        'password_reset_token' => null,
                        'password_reset_expires' => null,
                    ],
                ]
            );

            // Log the password reset
            $this->logUserActivity($user['_id'], 'password_reset', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            flash('success', 'Your password has been successfully reset. You can now login with your new password.');
            redirect('/login');
        } catch (\Exception $e) {
            flash('error', 'An error occurred while resetting your password. Please try again.');
            redirect('/reset-password?token='.urlencode($token));
        }
    }

    /**
     * Show change password page.
     */
    public function showChangePassword(): void
    {
        $user = $this->auth->currentUser();
        if (!$user) {
            redirect('/login');
        }

        render('auth/change-password', [
            'title' => 'Change Password',
            'pageTitle' => 'Change Password',
        ]);
    }

    /**
     * Handle password change.
     */
    public function changePassword(): void
    {
        verify_csrf();

        $user = $this->auth->currentUser();
        if (!$user) {
            redirect('/login');
        }

        $currentPassword = (string) request_post('current_password');
        $newPassword = (string) request_post('new_password');
        $confirmPassword = (string) request_post('confirm_password');

        if (empty($currentPassword) || empty($newPassword)) {
            flash('error', 'Please fill in all password fields.');
            redirect('/change-password');
        }

        if ($newPassword !== $confirmPassword) {
            flash('error', 'New passwords do not match.');
            redirect('/change-password');
        }

        // Validate current password
        if (!$this->auth->attempt($user['email'], $currentPassword)) {
            flash('error', 'Current password is incorrect.');
            redirect('/change-password');
        }

        // Validate new password strength
        if (strlen($newPassword) < 8) {
            flash('error', 'New password must be at least 8 characters long.');
            redirect('/change-password');
        }

        try {
            // Update password
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $db = $this->system->systemDb();
            $users = $db->users;

            $users->update(
                ['_id' => $user['_id']],
                ['$set' => ['password_hash' => $hashedPassword]]
            );

            // Log the password change
            $this->logUserActivity($user['_id'], 'password_change', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            ]);

            flash('success', 'Your password has been successfully changed.');
            redirect('/dashboard');
        } catch (\Exception $e) {
            flash('error', 'An error occurred while changing your password. Please try again.');
            redirect('/change-password');
        }
    }

    /**
     * Show profile management page.
     */
    public function showProfile(): void
    {
        $user = $this->auth->currentUser();
        if (!$user) {
            redirect('/login');
        }

        render('auth/profile', [
            'title' => 'My Profile',
            'pageTitle' => 'My Profile',
            'user' => $user,
        ]);
    }

    /**
     * Handle profile update.
     */
    public function updateProfile(): void
    {
        verify_csrf();

        $user = $this->auth->currentUser();
        if (!$user) {
            redirect('/login');
        }

        $name = trim((string) request_post('name'));
        $email = trim((string) request_post('email'));

        if (empty($name) || empty($email)) {
            flash('error', 'Please fill in all required fields.');
            redirect('/profile');
        }

        // Validate email format
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a valid email address.');
            redirect('/profile');
        }

        // Check if email is already taken by another user
        $existingUser = $this->auth->getUserByEmail($email);
        if ($existingUser && $existingUser['_id'] !== $user['_id']) {
            flash('error', 'This email address is already in use by another account.');
            redirect('/profile');
        }

        try {
            $db = $this->system->systemDb();
            $users = $db->users;

            $users->update(
                ['_id' => $user['_id']],
                ['$set' => [
                    'name' => $name,
                    'email' => $email,
                ]]
            );

            // Log the profile update
            $this->logUserActivity($user['_id'], 'profile_update', [
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'changes' => ['name' => $name, 'email' => $email],
            ]);

            flash('success', 'Your profile has been successfully updated.');
            redirect('/profile');
        } catch (\Exception $e) {
            flash('error', 'An error occurred while updating your profile. Please try again.');
            redirect('/profile');
        }
    }

    /**
     * Rate limiting helpers.
     */
    private function isRateLimited(string $ipAddress): bool
    {
        $rateLimitFile = storage_path('rate_limits/'.md5($ipAddress).'.json');

        if (!file_exists($rateLimitFile)) {
            return false;
        }

        $data = json_decode(file_get_contents($rateLimitFile), true);
        if (!$data || time() - $data['first_attempt'] > 900) { // 15 minutes window
            return false;
        }

        return $data['attempts'] >= 5; // Max 5 attempts
    }

    private function recordFailedAttempt(string $ipAddress, ?string $userId = null): void
    {
        $rateLimitFile = storage_path('rate_limits/'.md5($ipAddress).'.json');

        if (!file_exists($rateLimitFile)) {
            $data = [
                'attempts' => 1,
                'first_attempt' => time(),
            ];
        } else {
            $data = json_decode(file_get_contents($rateLimitFile), true);
            ++$data['attempts'];
        }

        ensure_dir(storage_path('rate_limits'));
        file_put_contents($rateLimitFile, json_encode($data));

        // Lock user account if too many attempts
        if ($userId && $data['attempts'] >= 5) {
            $db = $this->system->systemDb();
            $users = $db->users;

            $users->update(
                ['_id' => $userId],
                [
                    '$set' => [
                        'status' => 'locked',
                        'locked_until' => time() + 900, // 15 minutes
                    ],
                ]
            );
        }
    }

    private function clearFailedAttempts(string $userId): void
    {
        $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $rateLimitFile = storage_path('rate_limits/'.md5($ipAddress).'.json');

        if (file_exists($rateLimitFile)) {
            unlink($rateLimitFile);
        }
    }

    /**
     * User activity logging.
     */
    private function logUserActivity(string $userId, string $action, array $data = []): void
    {
        try {
            $db = $this->system->systemDb();
            $userActivity = $db->user_activity;

            $userActivity->insert([
                'user_id' => $userId,
                'action' => $action,
                'ip_address' => $data['ip_address'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                'user_agent' => $data['user_agent'] ?? $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'data' => $data,
                'timestamp' => time(),
            ]);
        } catch (\Exception $e) {
            // Log activity should not break the application
            error_log('Failed to log user activity: '.$e->getMessage());
        }
    }
}
