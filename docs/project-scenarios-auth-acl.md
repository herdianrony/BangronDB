# Tips & Trick BangronDB: Modul Auth & ACL dengan Custom Config

> Dokumen ini menjawab dua pertanyaan: (1) Di mana menaruh fitur otentikasi (login, register, lupa password) pada arsitektur modular BangronDB? (2) Bagaimana implementasi ACL per-collection menggunakan `setCustomConfig()`?

## Daftar Isi

1. [Posisi Modul Auth dalam Arsitektur Modular](#1-posisi-modul-auth-dalam-arsitektur-modular)
2. [Schema Design untuk Modul Auth](#2-schema-design-untuk-modul-auth)
3. [Implementasi Register, Login, Logout](#3-implementasi-register-login-logout)
4. [Implementasi Lupa Password & Reset](#4-implementasi-lupa-password--reset)
5. [Session Management dengan JWT + Refresh Token](#5-session-management-dengan-jwt--refresh-token)
6. [ACL per Collection dengan setCustomConfig](#6-acl-per-collection-dengan-setcustomconfig)
7. [Hook Enforcement ACL otomatis](#7-hook-enforcement-acl-otomatis)
8. [Transaction Safety: atomic multi-step operasi](#8-transaction-safety-atomic-multi-step-operasi)
9. [Multi-Tenant Auth](#9-multi-tenant-auth)

---

## 1. Posisi Modul Auth dalam Arsitektur Modular

**Rekomendasi: Buat modul terpisah `auth.bangron`** — jangan ditaruh di modul bisnis manapun.

```
data/
├── auth.bangron              # ← Modul auth terpisah
│   ├── users
│   ├── user_sessions
│   ├── password_resets
│   ├── email_verifications
│   ├── login_audit_log
│   └── refresh_tokens
├── shared.bangron            # Reference: roles, permissions, role_permissions
│   ├── roles
│   ├── permissions
│   └── role_permissions
├── erp_core.bangron          # Modul bisnis ERP
├── crm.bangron               # Modul bisnis CRM
└── ...
```

### Alasan Pemisahan

| Alasan | Penjelasan |
|--------|------------|
| **Single Responsibility** | Auth punya lifecycle sendiri (session, token, reset) berbeda dari modul bisnis |
| **Encryption key berbeda** | Password hash & token wajib encryption key terpisah dari data bisnis |
| **Multi-modul consumer** | ERP, CRM, SCM, HRIS, POS semuanya butuh auth — coupling tinggi kalau ditaruh di salah satu |
| **Backup lebih sering** | Sessions/tokens berubah cepat, butuh backup per jam (beda dari master data ERP) |
| **Compliance & audit** | Log login/logout wajib dipisah dari log bisnis untuk audit regulasi (ISO 27001, SOC 2) |
| **TTL berbeda** | Password reset token butuh TTL 1 jam, session 7 hari, audit log 1 tahun — sulit kalau campur |

### Kapan Boleh Tidak Pisah?

- **Aplikasi kecil** dengan <5 endpoint dan 1 role — taruh di `shared.bangron` saja.
- **HRIS sudah ada employee data** — di HRIS scenario, `employees` collection bisa jadi user identity, tapi tetap pisahkan `users` (credentials) ke `auth.bangron`. Employee ≠ user. Vendor/supplier/partner bisa jadi user tanpa employee record.

---

## 2. Schema Design untuk Modul Auth

### 2.1 Users Collection (Credentials)

```php
coll('auth', 'users')->setSchema([
    'user_id'       => ['type' => 'string', 'required' => true, 'unique' => true],
    'username'      => ['type' => 'string', 'required' => true, 'unique' => true,
                         'min' => 3, 'max' => 50,
                         'regex' => '/^[a-zA-Z0-9_\.\-]+$/'],
    'email'         => ['type' => 'string', 'required' => true, 'unique' => true,
                         'regex' => '/^[^\s@]+@[^\s@]+\.[^\s@]+$/'],
    'phone'         => ['type' => 'string', 'regex' => '/^\+?[0-9]{8,15}$/'],
    'password_hash' => ['type' => 'string', 'required' => true],   // bcrypt, tidak di-encrypt lagi
    'pin_hash'      => ['type' => 'string'],                       // untuk POS kasir
    'display_name'  => ['type' => 'string', 'max' => 100],
    'avatar_url'    => ['type' => 'string'],
    'locale'        => ['type' => 'string', 'default' => 'id_ID'],
    'timezone'      => ['type' => 'string', 'default' => 'Asia/Jakarta'],
    'is_active'     => ['type' => 'bool', 'default' => true],
    'is_verified'   => ['type' => 'bool', 'default' => false],     // email verified
    'is_locked'     => ['type' => 'bool', 'default' => false],
    'failed_login_count' => ['type' => 'int', 'default' => 0],
    'locked_until'  => ['type' => 'string'],
    'last_login_at' => ['type' => 'string'],
    'last_login_ip' => ['type' => 'string'],
    'password_changed_at' => ['type' => 'string'],
    'mfa_secret'    => ['type' => 'string'],   // TOTP secret (encrypted)
    'mfa_enabled'   => ['type' => 'bool', 'default' => false],
    'created_at'    => ['type' => 'string', 'required' => true],
    'updated_at'    => ['type' => 'string'],
    'created_by'    => ['type' => 'string'],
])->saveConfiguration();

// Searchable fields untuk login lookup cepat
coll('auth', 'users')->setSearchableFields([
    'username'  => ['hash' => false],
    'email'     => ['hash' => true],   // blind index untuk PII
    'is_active' => ['hash' => false],
])->saveConfiguration();
```

**Catatan penting:**

- `password_hash` **tidak perlu di-encrypt** di BangronDB karena sudah bcrypt-hashed. Tapi `mfa_secret` (TOTP secret) WAJIB di-encrypt — kalau bocor, attacker bisa generate OTP valid.
- `email` di blind index (`hash: true`) agar bisa di-query untuk lookup user by email, tapi tidak plaintext di storage.
- `failed_login_count` + `locked_until` untuk brute-force protection.

### 2.2 Password Reset Tokens (dengan TTL)

```php
coll('auth', 'password_resets')->setSchema([
    'token_hash'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'user_id'       => ['type' => 'string', 'required' => true],
    'requested_at'  => ['type' => 'string', 'required' => true],
    'expires_at'    => ['type' => 'string', 'required' => true],
    'used_at'       => ['type' => 'string'],
    'requested_ip'  => ['type' => 'string'],
    'user_agent'    => ['type' => 'string'],
])->saveConfiguration();

// Auto-expire token setelah 1 jam
coll('auth', 'password_resets')->setTTL(60 * 60);
coll('auth', 'password_resets')->saveConfiguration();
```

### 2.3 Refresh Tokens (untuk JWT rotation)

```php
coll('auth', 'refresh_tokens')->setSchema([
    'token_hash'    => ['type' => 'string', 'required' => true, 'unique' => true],
    'user_id'       => ['type' => 'string', 'required' => true],
    'issued_at'     => ['type' => 'string', 'required' => true],
    'expires_at'    => ['type' => 'string', 'required' => true],
    'revoked_at'    => ['type' => 'string'],
    'device_info'   => ['type' => 'string'],
    'ip_address'    => ['type' => 'string'],
])->saveConfiguration();

// Refresh token TTL 30 hari
coll('auth', 'refresh_tokens')->setTTL(60 * 60 * 24 * 30);
coll('auth', 'refresh_tokens')->saveConfiguration();
```

### 2.4 Login Audit Log

```php
coll('auth', 'login_audit_log')->setSchema([
    'event_type'    => ['type' => 'string', 'required' => true,
                         'enum' => ['login_success', 'login_failed', 'logout',
                                    'password_reset_request', 'password_reset_success',
                                    'account_locked', 'mfa_challenge', 'mfa_success']],
    'user_id'       => ['type' => 'string'],     // null jika login failed sebelum identify user
    'username_attempt' => ['type' => 'string'],  // input yang dicoba
    'ip_address'    => ['type' => 'string', 'required' => true],
    'user_agent'    => ['type' => 'string'],
    'occurred_at'   => ['type' => 'string', 'required' => true],
    'metadata'      => ['type' => 'array'],      // extra context
])->saveConfiguration();

// Audit log retain 1 tahun (regulasi ISO 27001)
coll('auth', 'login_audit_log')->setTTL(60 * 60 * 24 * 365);
coll('auth', 'login_audit_log')->saveConfiguration();
```

---

## 3. Implementasi Register, Login, Logout

### 3.1 Register

```php
class AuthService
{
    private \BangronDB\Collection $users;
    private \BangronDB\Collection $auditLog;

    public function __construct()
    {
        $this->users    = coll('auth', 'users');
        $this->auditLog = coll('auth', 'login_audit_log');
    }

    public function register(array $data): array
    {
        // Validasi input
        $errors = $this->validateRegisterInput($data);
        if (!empty($errors)) {
            throw new \InvalidArgumentException(implode('; ', $errors));
        }

        // Cek email/username sudah dipakai (manfaatkan unique constraint di schema)
        $existing = $this->users->findOne([
            '$or' => [
                ['email' => $data['email']],
                ['username' => $data['username']],
            ],
        ]);
        if ($existing) {
            throw new \RuntimeException('Email or username already registered');
        }

        // Hash password (bcrypt, cost 12)
        $passwordHash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);

        // Generate user_id
        $userId = 'USR-' . strtoupper(bin2hex(random_bytes(8)));

        // TRANSACTION WAJIB: insert user + audit log harus atomic.
        // Kalau audit log gagal tapi user berhasil insert → data inkonsisten
        // (user ada tapi tidak ada record siapa yang create).
        $conn = $this->users->database->connection;
        $conn->beginTransaction();
        try {
            // Insert user
            $this->users->insert([
                'user_id'        => $userId,
                'username'       => $data['username'],
                'email'          => $data['email'],
                'phone'          => $data['phone'] ?? null,
                'password_hash'  => $passwordHash,
                'display_name'   => $data['display_name'] ?? $data['username'],
                'locale'         => $data['locale'] ?? 'id_ID',
                'timezone'       => $data['timezone'] ?? 'Asia/Jakarta',
                'is_active'      => true,
                'is_verified'    => false,    // butuh email verification
                'is_locked'      => false,
                'failed_login_count' => 0,
                'password_changed_at' => date('c'),
                'created_at'     => date('c'),
                'created_by'     => 'system',
            ]);

            // Audit log dalam transaction yang sama
            $this->auditLog->insert([
                'event_type'    => 'register',
                'user_id'       => $userId,
                'ip_address'    => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent'    => $_SERVER['HTTP_USER_AGENT'] ?? '',
                'occurred_at'   => date('c'),
                'metadata'      => ['email' => $data['email']],
            ]);

            $conn->commit();
        } catch (\Throwable $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            throw $e;
        }

        // Kirim email verification DI LUAR transaction
        // (jangan block user creation kalau email service down)
        try {
            $this->sendEmailVerification($userId, $data['email']);
        } catch (\Throwable $e) {
            // Log error tapi jangan fail registration
            error_log("Email verification failed for {$userId}: " . $e->getMessage());
        }

        return ['user_id' => $userId, 'message' => 'Registration successful. Check email for verification.'];
    }

    private function validateRegisterInput(array $data): array
    {
        $errors = [];
        if (empty($data['username'])) $errors[] = 'Username required';
        if (empty($data['email'])) $errors[] = 'Email required';
        if (empty($data['password'])) $errors[] = 'Password required';
        if (strlen($data['password'] ?? '') < 8) $errors[] = 'Password min 8 chars';
        if (!preg_match('/[A-Z]/', $data['password'] ?? '')) $errors[] = 'Password must contain uppercase';
        if (!preg_match('/[0-9]/', $data['password'] ?? '')) $errors[] = 'Password must contain digit';
        return $errors;
    }
}
```

### 3.2 Login (dengan Brute-Force Protection)

```php
public function login(string $identifier, string $password): array
{
    // Cari user by email atau username
    $user = $this->users->findOne([
        '$or' => [
            ['email' => $identifier],
            ['username' => $identifier],
        ],
    ]);

    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

    // Always run password_verify bahkan jika user not found, untuk prevent timing attack
    $validPassword = $user
        ? password_verify($password, $user['password_hash'])
        : password_verify($password, '$2y$12$dummyHashToPreventTimingAttackXXXXXXXXXXXXXXXXXXXX');

    if (!$user || !$validPassword) {
        // Audit log failed login
        $this->auditLog->insert([
            'event_type'       => 'login_failed',
            'user_id'          => $user['_id'] ?? null,
            'username_attempt' => $identifier,
            'ip_address'       => $ip,
            'user_agent'       => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'occurred_at'      => date('c'),
        ]);

        // Increment failed count jika user ada
        if ($user) {
            $newCount = ($user['failed_login_count'] ?? 0) + 1;
            $updates = ['$set' => ['failed_login_count' => $newCount]];

            // Lock account setelah 5 percobaan gagal
            if ($newCount >= 5) {
                $updates['$set']['is_locked'] = true;
                $updates['$set']['locked_until'] = date('c', time() + 1800); // 30 menit
            }

            $this->users->update(['_id' => $user['_id']], $updates);
        }

        throw new \RuntimeException('Invalid credentials');
    }

    // Cek account active & tidak locked
    if (!$user['is_active']) {
        throw new \RuntimeException('Account deactivated');
    }
    if ($user['is_locked'] && strtotime($user['locked_until'] ?? '') > time()) {
        throw new \RuntimeException('Account locked. Try again after ' . $user['locked_until']);
    }

    // Reset failed count & update last login
    $this->users->update(['_id' => $user['_id']], ['$set' => [
        'failed_login_count' => 0,
        'is_locked'          => false,
        'locked_until'       => null,
        'last_login_at'      => date('c'),
        'last_login_ip'      => $ip,
    ]]);

    // Generate JWT access token (15 menit) + refresh token (30 hari)
    $accessToken  = $this->generateJwt($user, 900);
    $refreshToken = $this->issueRefreshToken($user['_id']);

    // Audit log success
    $this->auditLog->insert([
        'event_type'  => 'login_success',
        'user_id'     => $user['_id'],
        'ip_address'  => $ip,
        'user_agent'  => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'occurred_at' => date('c'),
    ]);

    return [
        'access_token'  => $accessToken,
        'refresh_token' => $refreshToken,
        'token_type'    => 'Bearer',
        'expires_in'    => 900,
        'user'          => [
            'user_id'      => $user['user_id'],
            'username'     => $user['username'],
            'email'        => $user['email'],
            'display_name' => $user['display_name'],
            'roles'        => $this->getUserRoles($user['_id']),
        ],
    ];
}
```

### 3.3 Logout

```php
public function logout(string $refreshToken): void
{
    // Revoke refresh token
    $tokenHash = hash('sha256', $refreshToken);
    coll('auth', 'refresh_tokens')->update(
        ['token_hash' => $tokenHash],
        ['$set' => ['revoked_at' => date('c')]]
    );

    // Audit log
    $userId = $_SESSION['user_id'] ?? null;
    $this->auditLog->insert([
        'event_type'  => 'logout',
        'user_id'     => $userId,
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        'occurred_at' => date('c'),
    ]);
}
```

---

## 4. Implementasi Lupa Password & Reset

### 4.1 Request Reset Password

```php
public function requestPasswordReset(string $email): array
{
    // Cari user by email (blind index lookup)
    $user = $this->users->findOne(['email' => $email]);

    // Selalu return success bahkan jika email tidak ada, untuk prevent user enumeration
    if (!$user) {
        return ['message' => 'If the email exists, a reset link has been sent.'];
    }

    // Generate token random 32 byte
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);  // simpan hash saja, bukan token plaintext

    // Simpan token di password_resets (TTL 1 jam sudah di-set di schema)
    coll('auth', 'password_resets')->insert([
        'token_hash'   => $tokenHash,
        'user_id'      => $user['_id'],
        'requested_at' => date('c'),
        'expires_at'   => date('c', time() + 3600),
        'requested_ip' => $_SERVER['REMOTE_ADDR'] ?? '',
        'user_agent'   => $_SERVER['HTTP_USER_AGENT'] ?? '',
    ]);

    // Kirim email dengan link: https://app.com/reset-password?token=XXXX
    $this->sendResetEmail($user['email'], $token);

    // Audit log
    $this->auditLog->insert([
        'event_type'  => 'password_reset_request',
        'user_id'     => $user['_id'],
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        'occurred_at' => date('c'),
    ]);

    return ['message' => 'If the email exists, a reset link has been sent.'];
}
```

### 4.2 Reset Password dengan Token

```php
public function resetPassword(string $token, string $newPassword): array
{
    // Validasi password baru
    if (strlen($newPassword) < 8) {
        throw new \InvalidArgumentException('Password must be at least 8 characters');
    }
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        throw new \InvalidArgumentException('Password must contain uppercase and digit');
    }

    // Hash token & cari di collection
    $tokenHash = hash('sha256', $token);
    $resetRecord = coll('auth', 'password_resets')->findOne([
        'token_hash' => $tokenHash,
        'used_at'    => null,  // belum dipakai
    ]);

    if (!$resetRecord) {
        throw new \RuntimeException('Invalid or already used token');
    }

    // Cek expiry
    if (strtotime($resetRecord['expires_at']) < time()) {
        throw new \RuntimeException('Token expired. Please request a new reset link.');
    }

    // ============================================================
    // TRANSACTION WAJIB — 4 operasi yang HARUS atomic:
    //   1. Update password user
    //   2. Tandai token sebagai used (anti replay)
    //   3. Revoke semua refresh token user (force re-login)
    //   4. Audit log
    //
    // Skenario buruk tanpa transaction:
    //   - Password berhasil diupdate, tapi token belum ditandai used
    //     → attacker bisa pakai token lagi (replay attack)
    //   - Password diupdate, refresh token tidak di-revoke
    //     → attacker yang punya refresh token tetap bisa akses
    //   - Semua berhasil tapi audit log gagal
    //     → tidak ada bukti reset untuk compliance
    // ============================================================
    $conn = $this->users->database->connection;
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $conn->beginTransaction();
    try {
        // 1. Update password user
        $this->users->update(
            ['_id' => $resetRecord['user_id']],
            ['$set' => [
                'password_hash'        => $newHash,
                'password_changed_at'  => date('c'),
            ]]
        );

        // 2. Tandai token sebagai used (anti replay)
        coll('auth', 'password_resets')->update(
            ['_id' => $resetRecord['_id']],
            ['$set' => ['used_at' => date('c')]]
        );

        // 3. Revoke semua refresh token user (force re-login di device lain)
        coll('auth', 'refresh_tokens')->update(
            ['user_id' => $resetRecord['user_id'], 'revoked_at' => null],
            ['$set' => ['revoked_at' => date('c'), 'revoke_reason' => 'password_reset']]
        );

        // 4. Audit log
        $this->auditLog->insert([
            'event_type'  => 'password_reset_success',
            'user_id'     => $resetRecord['user_id'],
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'occurred_at' => date('c'),
        ]);

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        // Audit log kegagalan (di luar transaction utama, jadi pasti tersimpan)
        $this->auditLog->insert([
            'event_type'  => 'password_reset_failed',
            'user_id'     => $resetRecord['user_id'],
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'occurred_at' => date('c'),
            'metadata'    => ['error' => $e->getMessage()],
        ]);
        throw $e;
    }

    return ['message' => 'Password reset successful. Please login with new password.'];
}
```

---

## 5. Session Management dengan JWT + Refresh Token

### 5.1 Generate JWT

```php
private function generateJwt(array $user, int $ttlSeconds): string
{
    $header = ['alg' => 'HS256', 'typ' => 'JWT'];
    $payload = [
        'sub'        => $user['_id'],
        'username'   => $user['username'],
        'email'      => $user['email'],
        'roles'      => $this->getUserRoles($user['_id']),
        'iat'        => time(),
        'exp'        => time() + $ttlSeconds,
        'iss'        => 'erp-app',
        'aud'        => 'erp-clients',
    ];

    $encodedHeader  = $this->base64UrlEncode(json_encode($header));
    $encodedPayload = $this->base64UrlEncode(json_encode($payload));
    $signature      = hash_hmac('sha256', "{$encodedHeader}.{$encodedPayload}", $_ENV['JWT_SECRET'], true);

    return "{$encodedHeader}.{$encodedPayload}.{$this->base64UrlEncode($signature)}";
}

private function verifyJwt(string $jwt): ?array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) return null;

    [$header, $payload, $signature] = $parts;
    $expectedSignature = $this->base64UrlEncode(
        hash_hmac('sha256', "{$header}.{$payload}", $_ENV['JWT_SECRET'], true)
    );

    if (!hash_equals($expectedSignature, $signature)) return null;

    $data = json_decode($this->base64UrlDecode($payload), true);
    if (!$data || $data['exp'] < time()) return null;

    return $data;
}
```

### 5.2 Refresh Token Issue & Rotate

```php
private function issueRefreshToken(string $userId): string
{
    $token = bin2hex(random_bytes(32));
    $tokenHash = hash('sha256', $token);

    coll('auth', 'refresh_tokens')->insert([
        'token_hash'  => $tokenHash,
        'user_id'     => $userId,
        'issued_at'   => date('c'),
        'expires_at'  => date('c', time() + 60 * 60 * 24 * 30), // 30 hari
        'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);

    return $token;
}

public function refreshAccessToken(string $refreshToken): array
{
    $tokenHash = hash('sha256', $refreshToken);
    $record = coll('auth', 'refresh_tokens')->findOne([
        'token_hash' => $tokenHash,
        'revoked_at' => null,
    ]);

    if (!$record) {
        throw new \RuntimeException('Invalid refresh token');
    }
    if (strtotime($record['expires_at']) < time()) {
        throw new \RuntimeException('Refresh token expired');
    }

    $user = $this->users->findOne(['_id' => $record['user_id']]);
    if (!$user || !$user['is_active']) {
        throw new \RuntimeException('User not found or inactive');
    }

    // ============================================================
    // TRANSACTION WAJIB — token rotation harus atomic:
    //   1. Revoke old refresh token
    //   2. Issue new refresh token
    //
    // Tanpa transaction: kalau revoke berhasil tapi insert new gagal,
    // user logout tanpa token baru → UX buruk & harus login ulang.
    // Lebih buruk lagi: kalau insert berhasil tapi revoke gagal,
    // OLD TOKEN TETAP VALID → token theft defense gagal.
    // ============================================================
    $conn = $this->users->database->connection;
    $conn->beginTransaction();
    try {
        // 1. Revoke old token
        coll('auth', 'refresh_tokens')->update(
            ['_id' => $record['_id']],
            ['$set' => ['revoked_at' => date('c'), 'revoke_reason' => 'rotated']]
        );

        // 2. Issue new refresh token (atomic dengan revoke)
        $newRefreshToken = bin2hex(random_bytes(32));
        $newTokenHash    = hash('sha256', $newRefreshToken);
        coll('auth', 'refresh_tokens')->insert([
            'token_hash'  => $newTokenHash,
            'user_id'     => $user['_id'],
            'issued_at'   => date('c'),
            'expires_at'  => date('c', time() + 60 * 60 * 24 * 30),
            'device_info' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
        ]);

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    // Generate JWT di luar transaction (tidak menyentuh DB)
    $newAccessToken = $this->generateJwt($user, 900);

    return [
        'access_token'  => $newAccessToken,
        'refresh_token' => $newRefreshToken,
        'expires_in'    => 900,
    ];
}
```

### 5.3 Flight Middleware untuk Auth

```php
// Middleware: cek JWT di setiap request
Flight::before('start', function (array $params) {
    $path = Flight::request()->url;

    // Public routes (skip auth)
    $publicPaths = ['/api/auth/login', '/api/auth/register', '/api/auth/reset-password'];
    foreach ($publicPaths as $p) {
        if (str_starts_with($path, $p)) return;
    }

    // Get token from Authorization header
    $authHeader = Flight::request()->header('Authorization') ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/', $authHeader, $m)) {
        Flight::halt(401, json_encode(['error' => 'Missing or invalid token']));
    }

    $payload = (new AuthService())->verifyJwt($m[1]);
    if (!$payload) {
        Flight::halt(401, json_encode(['error' => 'Invalid or expired token']));
    }

    // Set user context di Flight
    Flight::set('current_user', $payload);
    $_SESSION['user_id'] = $payload['sub'];
    $_SESSION['roles']   = $payload['roles'];
});
```

---

## 6. ACL per Collection dengan setCustomConfig

BangronDB sudah punya mekanisme built-in untuk menyimpan config per-collection via `setCustomConfig()`. Inilah cara terbaik implementasi ACL dinamis.

### 6.1 Setup ACL per Collection

```php
// Collection "sales_orders" — admin full access, sales rep create+read sendiri, viewer read-only
coll('erp_sales', 'sales_orders')->setCustomConfig('acl', [
    'admin'     => ['create', 'read', 'update', 'delete', 'export'],
    'sales_rep' => ['create', 'read_own', 'update_own'],
    'manager'   => ['create', 'read', 'update', 'export', 'approve'],
    'viewer'    => ['read'],
])->saveConfiguration();

// Collection "payslips" — sangat restriktif
coll('hris', 'payslips')->setCustomConfig('acl', [
    'admin'        => ['read', 'export'],
    'hr_manager'   => ['read', 'export', 'approve'],
    'employee'     => ['read_own'],          // hanya payslip sendiri
    'finance'      => ['read', 'export'],
])->saveConfiguration();

// Collection "products" — public read, admin write
coll('erp_core', 'products')->setCustomConfig('acl', [
    'admin'      => ['create', 'read', 'update', 'delete'],
    'sales_rep'  => ['read'],
    'cashier'    => ['read'],
    'viewer'     => ['read'],
    'public'     => ['read'],  // untuk API publik (mis. product catalog)
])->saveConfiguration();
```

### 6.2 Read ACL dari Collection

```php
$acl = coll('erp_sales', 'sales_orders')->getCustomConfig('acl', []);
// $acl = ['admin' => [...], 'sales_rep' => [...], ...]

// Cek permission untuk role tertentu
function can(string $role, string $collection, string $module, string $action): bool
{
    $acl = coll($module, $collection)->getCustomConfig('acl', []);
    $allowed = $acl[$role] ?? [];
    return in_array($action, $allowed, true) || in_array('*', $allowed, true);
}

// Contoh penggunaan
if (!can($_SESSION['role'], 'sales_orders', 'erp_sales', 'create')) {
    Flight::halt(403, json_encode(['error' => 'Forbidden: insufficient permission']));
}
```

### 6.3 Permission Granular dengan `_own` Suffix

Pola `_own` artinya user hanya boleh operasi pada dokumen yang dimilikinya:

```php
// Sales rep bisa update SO yang dia sendiri yang buat
function canUpdateOwn(string $userId, string $role, array $doc): bool
{
    $acl = coll('erp_sales', 'sales_orders')->getCustomConfig('acl', []);
    $allowed = $acl[$role] ?? [];

    // Cek "update" atau "update_own"
    if (in_array('update', $allowed, true)) return true;
    if (in_array('update_own', $allowed, true)) {
        return ($doc['sales_rep_id'] ?? null) === $userId;
    }
    return false;
}

// Pakai di controller
Flight::route('PUT /api/sales-orders/@id', function ($id) use ($userId) {
    $so = coll('erp_sales', 'sales_orders')->findOne(['_id' => $id]);
    if (!$so) Flight::halt(404, 'Not found');

    if (!canUpdateOwn($userId, $_SESSION['role'], $so)) {
        Flight::halt(403, 'Forbidden: you can only update your own SO');
    }
    // ... update logic
});
```

### 6.4 Dynamic ACL Update (tanpa restart)

Karena ACL disimpan di database via `saveConfiguration()`, HR/Admin bisa update tanpa redeploy:

```php
function updateAcl(string $module, string $collection, string $role, array $permissions): void
{
    $coll = coll($module, $collection);
    $acl = $coll->getCustomConfig('acl', []);
    $acl[$role] = $permissions;
    $coll->setCustomConfig('acl', $acl);
    $coll->saveConfiguration();  // WAJIB save agar persisten

    // Audit log perubahan ACL
    coll('auth', 'acl_change_log')->insert([
        'module'        => $module,
        'collection'    => $collection,
        'role'          => $role,
        'old_permissions' => $acl[$role] ?? [],
        'new_permissions' => $permissions,
        'changed_by'    => $_SESSION['user_id'],
        'changed_at'    => date('c'),
    ]);
}

// Contoh: tambah permission "approve" ke role manager
updateAcl('erp_sales', 'sales_orders', 'manager', ['create', 'read', 'update', 'export', 'approve']);
```

---

## 7. Hook Enforcement ACL otomatis

Agar ACL otomatis di-enforce di setiap operasi CRUD tanpa harus cek manual di controller, gunakan hook.

### 7.1 Centralized ACL Hook Registration

```php
class AclEnforcer
{
    public static function register(string $module, string $collectionName): void
    {
        $coll = coll($module, $collectionName);

        // Hook: cek permission sebelum insert
        $coll->on('beforeInsert', function (array $doc) use ($module, $collectionName) {
            $userId = $_SESSION['user_id'] ?? null;
            $role   = $_SESSION['role'] ?? 'public';

            if (!self::can($role, $module, $collectionName, 'create')) {
                self::logDenial($userId, $role, $module, $collectionName, 'create');
                return false;  // veto operasi
            }
            return $doc;
        });

        // Hook: cek permission sebelum update (dengan _own support)
        $coll->on('beforeUpdate', function (array $criteria, array $data) use ($module, $collectionName, $userId) {
            $role = $_SESSION['role'] ?? 'public';

            // Cek apakah user punya "update" atau "update_own"
            if (self::can($role, $module, $collectionName, 'update')) {
                return $data;  // full access
            }

            if (self::can($role, $module, $collectionName, 'update_own')) {
                // Tambah filter criteria: hanya dokumen milik user
                // Ini trick: modify criteria untuk restrict scope
                $criteria['_owner_id'] = $userId;
                return [$criteria, $data];  // return modified
            }

            self::logDenial($userId, $role, $module, $collectionName, 'update');
            return false;
        });

        // Hook: cek permission sebelum remove
        $coll->on('beforeRemove', function (array $criteria) use ($module, $collectionName) {
            $userId = $_SESSION['user_id'] ?? null;
            $role   = $_SESSION['role'] ?? 'public';

            if (!self::can($role, $module, $collectionName, 'delete')) {
                self::logDenial($userId, $role, $module, $collectionName, 'delete');
                return false;
            }
            return $criteria;
        });
    }

    private static function can(string $role, string $module, string $collection, string $action): bool
    {
        $acl = coll($module, $collection)->getCustomConfig('acl', []);
        $allowed = $acl[$role] ?? [];
        return in_array($action, $allowed, true) || in_array('*', $allowed, true);
    }

    private static function logDenial(?string $userId, string $role, string $module, string $collection, string $action): void
    {
        coll('auth', 'acl_denial_log')->insert([
            'user_id'    => $userId,
            'role'       => $role,
            'module'     => $module,
            'collection' => $collection,
            'action'     => $action,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'occurred_at'=> date('c'),
        ]);
    }
}

// Register ACL enforcement untuk semua collection di semua modul
$enforcementMap = [
    'erp_core'   => ['products', 'customers', 'suppliers', 'users'],
    'erp_sales'  => ['sales_orders', 'invoices', 'payments'],
    'erp_finance'=> ['journal_entries', 'coa'],
    'hris'       => ['employees', 'attendance', 'payslips'],
    'scm'        => ['purchase_orders', 'goods_receipts', 'stock_movements'],
    'pos_central'=> ['transactions', 'cash_sessions'],
];
foreach ($enforcementMap as $module => $collections) {
    foreach ($collections as $col) {
        AclEnforcer::register($module, $col);
    }
}
```

### 7.2 Read Enforcement (Wrapper Pattern)

Karena BangronDB tidak punya `beforeFind` hook, bungkus `find()` dengan wrapper:

```php
class AclAwareCollection
{
    public function __construct(
        private \BangronDB\Collection $collection,
        private string $module,
        private string $name,
    ) {}

    public function find(array $criteria = [], array $projection = [], array $options = []): \BangronDB\Cursor
    {
        $role   = $_SESSION['role'] ?? 'public';
        $userId = $_SESSION['user_id'] ?? null;

        $acl = $this->collection->getCustomConfig('acl', []);
        $allowed = $acl[$role] ?? [];

        if (in_array('read', $allowed, true) || in_array('*', $allowed, true)) {
            // Full read access
            return $this->collection->find($criteria, $projection, $options);
        }

        if (in_array('read_own', $allowed, true)) {
            // Restrict ke dokumen milik user
            $criteria['_owner_id'] = $userId;
            return $this->collection->find($criteria, $projection, $options);
        }

        // No read permission
        throw new \RuntimeException("Forbidden: role '{$role}' cannot read {$this->module}.{$this->name}");
    }

    public function findOne(array $criteria, array $projection = []): ?array
    {
        $role   = $_SESSION['role'] ?? 'public';
        $userId = $_SESSION['user_id'] ?? null;

        $acl = $this->collection->getCustomConfig('acl', []);
        $allowed = $acl[$role] ?? [];

        if (in_array('read_own', $allowed, true) && !in_array('read', $allowed, true)) {
            $criteria['_owner_id'] = $userId;
        } elseif (!in_array('read', $allowed, true) && !in_array('*', $allowed, true)) {
            throw new \RuntimeException("Forbidden: cannot read");
        }

        return $this->collection->findOne($criteria, $projection);
    }
}

// Helper function
function aclColl(string $module, string $name): AclAwareCollection
{
    return new AclAwareCollection(coll($module, $name), $module, $name);
}

// Pakai di controller
$sos = aclColl('erp_sales', 'sales_orders')->find(['status' => 'confirmed']);
// Otomatis filter sesuai role user
```

### 7.3 Sensitive Config Protection

BangronDB otomatis memblokir kunci config sensitif agar tidak persisten:

```php
// Ini akan throw InvalidArgumentException
coll('auth', 'users')->setCustomConfig('encryption_key', 'my-secret');
coll('auth', 'users')->setCustomConfig('password', 'admin123');
coll('auth', 'users')->setCustomConfig('api_key', 'XXXX');

// Daftar kunci yang diblokir (di ConfigurationPersistenceTrait):
// encryption_key, encryptionkey, password, passwd, secret, token,
// api_key, apikey, private_key, credential
```

Jadi ACL config aman — tidak akan ter-timpa dengan kredensial sensitif.

---

## 8. Transaction Safety: atomic multi-step operasi

BangronDB menyimpan data di SQLite, yang mendukung ACID transaction. Untuk operasi multi-step di modul auth, transaction **WAJIB** dipakai untuk mencegah inconsistency. Akses PDO connection via `$collection->database->connection`.

### 8.1 Kapan WAJIB Pakai Transaction

| Skenario | Langkah Atomic | Konsekuensi Tanpa Transaction |
|----------|----------------|-------------------------------|
| **Register** | insert user + insert audit log | User ada tanpa audit trail |
| **Login** (failed) | increment failed_count + audit log | Count salah, brute-force protection gagal |
| **Login** (success) | reset failed_count + update last_login + audit log | Count tidak reset, audit log hilang |
| **Logout** | revoke refresh token + audit log | Token masih valid, audit log hilang |
| **Password Reset** | update password + mark token used + revoke refresh tokens + audit log | **REPLAY ATTACK** kalau token tidak di-mark |
| **Refresh Token Rotation** | revoke old + insert new | Old token tetap valid → token theft defense gagal |
| **Change Password** (logged in) | update password + revoke all refresh tokens + audit log | Old session masih valid setelah password berubah |
| **Account Lock** | set is_locked + locked_until + audit log | Lock tidak konsisten |
| **Email Verification** | set is_verified + mark token used + audit log | Token bisa dipakai berkali-kali |
| **ACL Update** | setCustomConfig + saveConfiguration + audit log | Config inconsistent antara memory & DB |
| **User Deactivate** | set is_active=false + revoke all sessions + audit log | Session masih valid padahal user sudah non-aktif |

### 8.2 Pola Dasar Transaction

```php
// Akses PDO connection dari Collection
$conn = coll('auth', 'users')->database->connection;

$conn->beginTransaction();
try {
    // ... multiple Collection operations (insert, update, remove) ...
    // Semua dalam transaction yang sama → atomic

    $conn->commit();
} catch (\Throwable $e) {
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    throw $e;
}
```

**Aturan penting:**

1. **Cek `inTransaction()` sebelum `rollBack()`** — kalau PDO auto-rollback (misalnya karena nested transaction), panggil `rollBack()` akan throw.
2. **Re-throw exception setelah rollback** — jangan swallow, biar caller tahu operasi gagal.
3. **Side effects di luar transaction** — kirim email, panggil API eksternal, dll SETELAH commit, bukan di dalam. Kalau rollback, side effect sudah terlanjur terjadi.
4. **Transaction cross-collection dalam database yang sama = OK** — SQLite transaction mencakup semua tabel di database.
5. **Transaction cross-DATABASE TIDAK didukung** — modul `auth.bangron` dan `erp_core.bangron` punya connection terpisah, tidak bisa satu transaction. Untuk kasus ini, pakai [Saga Pattern](modular-architecture.md#83-strategi-mitigasi-inconsistency).

### 8.3 Contoh: Change Password (Logged In)

Operasi change password saat user sudah login — wajib revoke semua session lain:

```php
public function changePassword(string $userId, string $oldPassword, string $newPassword): array
{
    // 1. Verifikasi password lama
    $user = $this->users->findOne(['_id' => $userId]);
    if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
        // Audit log failed attempt
        $this->auditLog->insert([
            'event_type'  => 'password_change_failed',
            'user_id'     => $userId,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'occurred_at' => date('c'),
            'metadata'    => ['reason' => 'wrong_old_password'],
        ]);
        throw new \RuntimeException('Current password incorrect');
    }

    // 2. Validasi password baru
    if (strlen($newPassword) < 8) {
        throw new \InvalidArgumentException('Password must be at least 8 characters');
    }
    if (!preg_match('/[A-Z]/', $newPassword) || !preg_match('/[0-9]/', $newPassword)) {
        throw new \InvalidArgumentException('Password must contain uppercase and digit');
    }
    // Cek password tidak sama dengan lama
    if (password_verify($newPassword, $user['password_hash'])) {
        throw new \InvalidArgumentException('New password must be different from current');
    }

    // 3. TRANSACTION: update password + revoke all sessions + audit
    $conn = $this->users->database->connection;
    $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

    $conn->beginTransaction();
    try {
        // Update password
        $this->users->update(
            ['_id' => $userId],
            ['$set' => [
                'password_hash'        => $newHash,
                'password_changed_at'  => date('c'),
            ]]
        );

        // Revoke semua refresh token (force re-login di semua device)
        coll('auth', 'refresh_tokens')->update(
            ['user_id' => $userId, 'revoked_at' => null],
            ['$set' => ['revoked_at' => date('c'), 'revoke_reason' => 'password_change']]
        );

        // Audit log
        $this->auditLog->insert([
            'event_type'  => 'password_change_success',
            'user_id'     => $userId,
            'ip_address'  => $_SERVER['REMOTE_ADDR'] ?? '',
            'occurred_at' => date('c'),
        ]);

        $conn->commit();
    } catch (\Throwable $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        throw $e;
    }

    return [
        'message' => 'Password changed. All other sessions have been logged out.',
        'must_relogin' => true,
    ];
}
```

### 8.4 Alternatif: `executeTransaction()` di QueryExecutor

Untuk operasi yang fully SQL-based (tanpa hooks), BangronDB juga menyediakan helper `executeTransaction()` di `QueryExecutor`:

```php
// Pola ini cocok untuk SQL raw queries
db('auth')->queryExecutor->executeTransaction([
    ['sql' => 'UPDATE users SET password_hash = ?, password_changed_at = ? WHERE _id = ?',
     'params' => [$newHash, date('c'), $userId]],
    ['sql' => 'UPDATE refresh_tokens SET revoked_at = ?, revoke_reason = ? WHERE user_id = ? AND revoked_at IS NULL',
     'params' => [date('c'), 'password_change', $userId]],
    ['sql' => 'INSERT INTO login_audit_log (event_type, user_id, ip_address, occurred_at) VALUES (?, ?, ?, ?)',
     'params' => ['password_change_success', $userId, $_SERVER['REMOTE_ADDR'] ?? '', date('c')]],
]);
// Auto-commit jika semua sukses, auto-rollback kalau ada yang gagal
```

**Pilih yang mana?**

| Pola | Kapan Pakai |
|------|-------------|
| `$conn->beginTransaction()` manual | Operasi melibatkan `Collection::insert/update/remove` (karena hooks tetap jalan) |
| `executeTransaction()` | Operasi SQL raw tanpa hooks, lebih ringkas |

Untuk modul auth, **selalu pakai `$conn->beginTransaction()` manual** karena:
- Hooks (`beforeInsert`/`afterInsert`) tetap berjalan → ACL enforcement & audit otomatis
- Bisa mix Collection operations dengan logic PHP di tengah (mis. generate JWT)

### 8.5 Anti-Pattern Transaction

```php
// ❌ WRONG: side effect di dalam transaction
$conn->beginTransaction();
try {
    $this->users->insert([...]);
    $this->auditLog->insert([...]);
    $this->sendWelcomeEmail($user);  // ← JANGAN! Email service lambat, transaction lock lama
    $conn->commit();
} catch (\Throwable $e) {
    $conn->rollBack();
    throw $e;
}

// ✅ CORRECT: side effect setelah commit
$conn->beginTransaction();
try {
    $this->users->insert([...]);
    $this->auditLog->insert([...]);
    $conn->commit();
} catch (\Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    throw $e;
}
$this->sendWelcomeEmail($user);  // ← setelah commit, tidak block transaction

// ❌ WRONG: swallow exception tanpa re-throw
$conn->beginTransaction();
try {
    // ... operations
    $conn->commit();
} catch (\Throwable $e) {
    if ($conn->inTransaction()) $conn->rollBack();
    // TIDAK ada throw → caller mengira sukses padahal rollback!
    return false;
}

// ❌ WRONG: nested transaction tanpa savepoint
$conn->beginTransaction();
$this->users->insert([...]);  // ini sebenarnya masih dalam outer transaction
$conn->beginTransaction();    // SQLite tidak support nested → error atau auto-commit
$conn->commit();
$conn->commit();              // double commit, behavior tidak terdefinisi

// ✅ CORRECT: cek inTransaction sebelum begin
if (!$conn->inTransaction()) {
    $conn->beginTransaction();
    $shouldCommit = true;
}
try {
    // ... operations
    if ($shouldCommit ?? false) $conn->commit();
} catch (\Throwable $e) {
    if (($shouldCommit ?? false) && $conn->inTransaction()) $conn->rollBack();
    throw $e;
}
```

### 8.6 Transaction di Hook

Hook `beforeInsert`/`afterInsert` berjalan dalam transaction yang sama dengan operasi trigger-nya. Jadi kalau insert SO di transaction, hook `afterInsert` SO juga dalam transaction tersebut.

```php
// Hook ini otomatis dalam transaction yang sama dengan insert invoice
coll('erp_sales', 'invoices')->on('afterInsert', function (array $invoice) {
    // Insert JE — kalau gagal, invoice insert juga di-rollback (atomic!)
    coll('erp_finance', 'journal_entries')->insert([
        // ...
    ]);
});

// Caller:
$conn->beginTransaction();
try {
    coll('erp_sales', 'invoices')->insert($invoice);  // → trigger hook → insert JE
    $conn->commit();  // invoice + JE atomic
} catch (\Throwable $e) {
    $conn->rollBack();  // invoice + JE both rolled back
    throw $e;
}
```

**Tapi hati-hati:** hook cross-DATABASE (insert ke `erp_finance` dari hook `erp_sales`) TIDAK dalam transaction yang sama karena connection berbeda. Lihat [Modular Architecture → Strategi Mitigasi Inconsistency](modular-architecture.md#82-strategi-mitigasi-inconsistency).

---

## 9. Multi-Tenant Auth

Untuk SaaS multi-tenant, gabungkan pola di dokumen [Modular Architecture](modular-architecture.md#6-multi-tenant-dengan-strategi-yang-sama) dengan auth:

```php
class TenantAuthService
{
    public function login(string $tenantId, string $identifier, string $password): array
    {
        // Resolve tenant database
        $tmm = new TenantModuleManager(__DIR__ . '/../data');
        $auth = $tmm->forTenant($tenantId)->db('auth');

        $user = $auth->collection('users')->findOne([
            '$or' => [
                ['email' => $identifier],
                ['username' => $identifier],
            ],
        ]);

        // ... rest of login logic, tapi pakai $auth->collection() bukan coll('auth', ...)
    }
}

// JWT payload include tenant_id
$payload = [
    'sub'       => $user['_id'],
    'tenant_id' => $tenantId,
    'roles'     => $this->getUserRoles($user['_id']),
    // ...
];
```

---

## Ringkasan Pola Auth + ACL BangronDB

| Komponen | Lokasi | Catatan |
|----------|--------|---------|
| `users` (credentials) | `auth.bangron` | bcrypt password hash, blind index email |
| `password_resets` | `auth.bangron` | TTL 1 jam, simpan hash bukan plaintext |
| `refresh_tokens` | `auth.bangron` | TTL 30 hari, rotate on use |
| `login_audit_log` | `auth.bangron` | TTL 1 tahun (regulasi) |
| `roles`/`permissions` | `shared.bangron` | Master data, dibaca semua tenant |
| ACL per collection | `setCustomConfig('acl', [...])` | Persistent, dynamic, tidak butuh restart |
| ACL enforcement | Hooks `beforeInsert/Update/Remove` | Otomatis untuk CUD |
| Read enforcement | Wrapper class `AclAwareCollection` | Manual karena no `beforeFind` hook |
| Sensitive key blocker | Built-in `ConfigurationPersistenceTrait` | Anti foot-gun untuk password/api_key |

### Best Practices

1. **Pisahkan auth ke modul sendiri** — `auth.bangron`, jangan campur dengan bisnis.
2. **Encryption key terpisah untuk auth** — `$_ENV['AUTH_KEY']`, beda dari `ERP_KEY` dll.
3. **Password selalu bcrypt** — jangan MD5/SHA1, jangan di-encrypt lagi di BangronDB (sudah hashed).
4. **MFA secret WAJIB di-encrypt** — pakai `setEncryptionKey()` di collection `users`.
5. **Token simpan hash, bukan plaintext** — kalau database bocor, attacker tetap tidak bisa pakai token.
6. **ACL per-collection via setCustomConfig** — dinamis, persistent, tidak perlu restart.
7. **Hook untuk enforcement CUD** — sekali register, otomatis untuk semua operasi.
8. **Wrapper untuk enforcement Read** — BangronDB belum punya `beforeFind` hook, bungkus manual.
9. **Audit log semua perubahan ACL** — siapa, kapan, dari role apa ke role apa.
10. **Brute-force protection** — lock account setelah 5 percobaan gagal, unlock 30 menit.
11. **Transaction WAJIB untuk operasi multi-step** — register, login, password reset, token rotation, change password, account lock, dll. Akses PDO via `$coll->database->connection->beginTransaction()`. Side effects (email, API call) di luar transaction.
12. **Re-throw exception setelah rollback** — jangan swallow, biar caller tahu operasi gagal.
13. **Hook berjalan dalam transaction yang sama** — insert + hook afterInsert atomic, tapi cross-database hook TIDAK atomic (pakai Saga Pattern).

---

## Referensi

- [Modular Architecture](modular-architecture.md) — strategi multi-database, tempat modul `auth` berada.
- [HRIS Scenario](project-scenarios-hris.md) — pola encryption PII yang sama relevan untuk auth.
- [Security](security.md) — encryption, blind index, key rotation.
- [Hook Patterns](hook-patterns.md) — pola hook untuk enforcement logic.
- [examples/20-auth-encrypted.php](../examples/20-auth-encrypted.php) — contoh auth dengan encryption.
- [examples/22-rbac-users-roles-permissions.php](../examples/22-rbac-users-roles-permissions.php) — RBAC implementation.
- [examples/24-dynamic-acl-per-collection.php](../examples/24-dynamic-acl-per-collection.php) — ACL via `setCustomConfig`.
