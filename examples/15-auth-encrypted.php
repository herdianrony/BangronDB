<?php

/**
 * Contoh 15: Autentikasi dengan Data Terenkripsi
 *
 * MASALAH: Saat data user terenkripsi, Anda tidak bisa melakukan:
 *   $users->findOne(['email' => 'user@email.com'])
 * karena email di database sudah terenkripsi!
 *
 * SOLUSI: Gunakan Searchable Fields - BangronDB membuat kolom
 * indeks terpisah (si_email) yang menyimpan nilai hash/plain
 * sehingga bisa dicari TANPA mendekripsi seluruh database.
 *
 * Contoh ini mendemonstrasikan:
 * 1. Registrasi user dengan data terenkripsi
 * 2. Login menggunakan searchable hashed email
 * 3. Perbandingan: hash vs plain searchable fields
 * 4. Password verification yang aman
 * 5. Update data user yang terenkripsi
 * 6. Multi-factor searchable fields (email + username)
 */

require_once __DIR__ . '/bootstrap.php';

use BangronDB\Client;

sep('Contoh 15: Autentikasi dengan Data Terenkripsi');

// ═══════════════════════════════════════════════════════════
// BAGIAN 1: MASALAH - Login Gagal karena Data Terenkripsi
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 1: Masalah - Tanpa Searchable Fields');

$encKey = $_ENV['DB_ENCRYPTION_KEY'] ?? 'super-secret-encryption-key-32ch!!'; // v1.2.0: use $_ENV

$client = createIsolatedClient('example15');
$db = $client->createDB('auth_demo');
$users = $db->createCollection('users_no_searchable');

// Enkripsi TANPA searchable fields
$users->setEncryptionKey($encKey, $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026');

$users->insert([
    'name'     => 'Alice',
    'email'    => 'alice@example.com',
    'password' => password_hash('secret123', PASSWORD_BCRYPT),
    'role'     => 'admin',
]);

// ❌ PROBLEM: findOne TIDAK bisa menemukan user karena email terenkripsi!
$found = $users->findOne(['email' => 'alice@example.com']);
echo "Cari email TANPA searchable fields: " . ($found ? 'DITEMUKAN' : 'TIDAK DITEMUKAN ❌') . "\n";

// ⚠️ Catatan: $where juga TIDAK bisa karena data terdekripsi per-dokumen
// saat iterasi, tapi findOne() dengan kriteria TIDAK mendekripsi dulu.
// Jadi baik findOne() maupun $where tetap gagal untuk data terenkripsi
// tanpa searchable fields.
echo "\n⚠️  findOne() dan \$where TIDAK BISA mencari di data terenkripsi!\n";
echo "   Karena email di database sudah terenkripsi, tidak bisa dicocokkan.\n";
echo "   Solusi: gunakan Searchable Fields (lihat BAGIAN 2).\n";

// Lihat data mentah di DB - semua terenkripsi
$stmt = $db->connection->query("SELECT document FROM users_no_searchable LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$raw = json_decode($row['document'], true);
echo "Data di DB: " . (isset($raw['encrypted_data']) ? 'TERENKRIPSI ✅' : 'plain text') . "\n";
echo "Kolom di tabel: " . implode(', ', array_keys($raw)) . "\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 2: SOLUSI - Searchable Fields dengan Hashing
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 2: Solusi - Searchable Fields + Hashing');

$authUsers = $db->createCollection('auth_users');

// ✅ Set searchable fields SEBELUM insert
// 'email' => hash: true  → disimpan sebagai keyed HMAC blind index (v1.2.0, aman, tidak bisa di-reverse)
// 'username' => hash: false → disimpan sebagai lowercase plain text
$authUsers->setSearchableFields([
    'email'    => ['hash' => true],   // Keyed HMAC blind index (v1.2.0): privasi terjaga
    'username' => ['hash' => false],  // Plain text: bisa di-search langsung
]);

// Set encryption key
$authUsers->setEncryptionKey($encKey);

// ── Registrasi User ──────────────────────────────────────
echo "\n📝 Registrasi users...\n";

$users_data = [
    [
        'name'     => 'Alice Admin',
        'email'    => 'alice@example.com',
        'username' => 'alice_admin',
        'password' => password_hash('secret123', PASSWORD_BCRYPT),
        'role'     => 'admin',
        'phone'    => '+62812345678',
    ],
    [
        'name'     => 'Bob User',
        'email'    => 'bob@example.com',
        'username' => 'bob_user',
        'password' => password_hash('password456', PASSWORD_BCRYPT),
        'role'     => 'user',
        'phone'    => '+62898765432',
    ],
    [
        'name'     => 'Charlie Editor',
        'email'    => 'charlie@example.com',
        'username' => 'charlie_editor',
        'password' => password_hash('mypass789', PASSWORD_BCRYPT),
        'role'     => 'editor',
        'phone'    => '+62811223344',
    ],
];

foreach ($users_data as $user) {
    $id = $authUsers->insert($user);
    echo "  ✅ Registered: {$user['name']} ({$user['email']}) → {$id}\n";
}

// ── Lihat struktur tabel ─────────────────────────────────
echo "\n📊 Struktur tabel setelah searchable fields:\n";
$stmt = $db->connection->query("PRAGMA table_info(auth_users)");
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $col) {
    echo "  - {$col['name']} ({$col['type']})\n";
}

// ── Lihat data di kolom searchable ───────────────────────
echo "\n🔍 Isi kolom searchable (si_email = keyed HMAC blind index v1.2.0):\n";
$stmt = $db->connection->query("SELECT si_email, si_username FROM auth_users LIMIT 3");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($rows as $i => $row) {
    echo "  User " . ($i + 1) . ":\n";
    echo "    si_email (hashed): {$row['si_email']}\n";
    echo "    si_username (plain): {$row['si_username']}\n";
}

echo "\n💡 Perhatikan:\n";
echo "   - si_email = keyed HMAC-SHA256 blind index (v1.2.0, tidak bisa di-reverse)\n";
echo "   - si_username = lowercase plain text (bisa dibaca langsung)\n";
echo "   - Data asli di kolom 'document' TETAP terenkripsi!\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 3: LOGIN - Autentikasi Lengkap
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 3: Login dengan Searchable Encrypted Fields');

/**
 * Fungsi login yang aman menggunakan searchable fields.
 *
 * Cara kerja:
 * 1. BangronDB meng-hash email input dengan SHA-256
 * 2. Mencocokkan hash dengan si_email di database
 * 3. Jika ditemukan, dekripsi dokumen
 * 4. Verifikasi password dengan password_verify()
 */
function login(\BangronDB\Collection $users, string $email, string $password): array
{
    // ✅ Langsung cari by email - BangronDB otomatis hash & cari di si_email
    $user = $users->findOne(['email' => $email]);

    if (!$user) {
        return ['success' => false, 'error' => 'Email tidak ditemukan'];
    }

    // Verifikasi password
    if (!password_verify($password, $user['password'])) {
        return ['success' => false, 'error' => 'Password salah'];
    }

    // Login berhasil
    return [
        'success' => true,
        'user' => [
            'id'       => $user['_id'],
            'name'     => $user['name'],
            'email'    => $user['email'],
            'username' => $user['username'],
            'role'     => $user['role'],
            'phone'    => $user['phone'],
        ],
    ];
}

// ── Test Login: Berhasil ─────────────────────────────────
echo "🔑 Test Login:\n\n";

$result1 = login($authUsers, 'alice@example.com', 'secret123');
echo "Alice login (correct password):\n";
if ($result1['success']) {
    echo "  ✅ BERHASIL! Role: {$result1['user']['role']}\n";
    echo "  Name: {$result1['user']['name']}\n";
    echo "  Phone: {$result1['user']['phone']}\n";
} else {
    echo "  ❌ GAGAL: {$result1['error']}\n";
}

// ── Test Login: Password Salah ───────────────────────────
echo "\n";
$result2 = login($authUsers, 'alice@example.com', 'wrongpassword');
echo "Alice login (wrong password):\n";
if ($result2['success']) {
    echo "  ❌ UNEXPECTED: Should have failed!\n";
} else {
    echo "  ✅ DITOLAK: {$result2['error']}\n";
}

// ── Test Login: Email Tidak Ada ──────────────────────────
echo "\n";
$result3 = login($authUsers, 'nonexistent@example.com', 'anypassword');
echo "Unknown email login:\n";
if ($result3['success']) {
    echo "  ❌ UNEXPECTED: Should have failed!\n";
} else {
    echo "  ✅ DITOLAK: {$result3['error']}\n";
}

// ── Test Login: User Lain ────────────────────────────────
echo "\n";
$result4 = login($authUsers, 'bob@example.com', 'password456');
echo "Bob login (correct password):\n";
if ($result4['success']) {
    echo "  ✅ BERHASIL! Role: {$result4['user']['role']}\n";
} else {
    echo "  ❌ GAGAL: {$result4['error']}\n";
}

// ═══════════════════════════════════════════════════════════
// BAGIAN 4: Login dengan Username (Plain Searchable)
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 4: Login dengan Username (Plain Text Searchable)');

echo "🔑 Login by username (plain searchable):\n\n";

// Cari by username - langsung tanpa hash karena hash: false
$byUsername = $authUsers->findOne(['username' => 'charlie_editor']);
if ($byUsername) {
    echo "  ✅ Found by username: {$byUsername['name']}\n";
    $pwdOk = password_verify('mypass789', $byUsername['password']);
    echo "  Password verify: " . ($pwdOk ? '✅ MATCH' : '❌ NO MATCH') . "\n";
}

// ═══════════════════════════════════════════════════════════
// BAGIAN 5: Update Data User Terenkripsi
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 5: Update Data User Terenkripsi');

// Update password - cari by email, lalu update
$authUsers->update(
    ['email' => 'alice@example.com'],
    ['$set' => [
        'password' => password_hash('newsecret456', PASSWORD_BCRYPT),
        'phone'    => '+62899999999',
    ]]
);

// Verifikasi update
$updated = $authUsers->findOne(['email' => 'alice@example.com']);
echo "Alice setelah update:\n";
echo "  Phone: {$updated['phone']}\n";
echo "  New password works: " . (password_verify('newsecret456', $updated['password']) ? '✅ YES' : '❌ NO') . "\n";
echo "  Old password works: " . (password_verify('secret123', $updated['password']) ? '❌ YES (BUG!)' : '✅ NO (correct)') . "\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 6: Perbandingan Hash vs Plain Searchable
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 6: Hash vs Plain Searchable - Kapan Pakai Masing-masing');

echo "┌─────────────────┬──────────────────────┬──────────────────────┐\n";
echo "│ Aspek           │ Hash (SHA-256)       │ Plain (lowercase)    │\n";
echo "├─────────────────┼──────────────────────┼──────────────────────┤\n";
echo "│ Privasi         │ ✅ Tinggi            │ ⚠️ Rendah            │\n";
echo "│ Reversibility   │ ✅ Tidak bisa reverse│ ❌ Bisa dibaca langsung│\n";
echo "│ Pencarian       │ ✅ Exact match saja  │ ✅ Exact match       │\n";
echo "│ Kebocoran data  │ ✅ Aman jika bocor   │ ❧ Data terpapar     │\n";
echo "│ Cocok untuk     │ Email, NIK, SSN      │ Username, kategori   │\n";
echo "└─────────────────┴──────────────────────┴──────────────────────┘\n";

echo "\n💡 REKOMENDASI UNTUK AUTENTIKASI:\n";
echo "   - email    → hash: true  (tidak bocor jika DB diakses)\n";
echo "   - username → hash: false (perlu ditampilkan di UI)\n";
echo "   - password → JANGAN jadikan searchable! Simpan sebagai bcrypt hash\n";
echo "   - phone    → hash: true  (data sensitif)\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 7: Keamanan Tambahan
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 7: Keamanan Tambahan');

// 1. Verifikasi: data di document column tetap terenkripsi
echo "1️⃣  Verifikasi enkripsi data:\n";
$stmt = $db->connection->query("SELECT document FROM auth_users LIMIT 1");
$row = $stmt->fetch(PDO::FETCH_ASSOC);
$raw = json_decode($row['document'], true);
if (isset($raw['encrypted_data'])) {
    echo "  ✅ Data di kolom 'document' terenkripsi (AES-256-GCM)\n";
    echo "  ✅ _id tetap bisa dibaca: {$raw['_id']}\n";
} else {
    echo "  ❌ Data tidak terenkripsi!\n";
}

// 2. Searchable field hash tidak bisa di-reverse (v1.2.0: keyed HMAC blind index)
echo "\n2️⃣  Keyed HMAC blind index (v1.2.0) tidak bisa di-reverse:\n";
echo "  Plain SHA-256 dari 'alice@example.com': " . hash('sha256', strtolower('alice@example.com')) . "\n";
echo "  Hash di DB (si_email, keyed HMAC):      {$rows[0]['si_email']}\n";
echo "  Cocok dengan plain SHA-256? " . (hash('sha256', strtolower('alice@example.com')) === $rows[0]['si_email'] ? '✅ YES' : '❌ NO (good!)') . "\n";
echo "  ⚠️  v1.2.0: si_email sekarang keyed HMAC, BUKAN plain SHA-256 lagi!\n";
echo "  ⚠️  Seseorang yang akses DB hanya lihat keyed hash, TIDAK bisa tau email aslinya!\n";
echo "  ✅ Bahkan jika 2 DB punya user email yang sama, hash-nya berbeda (anti-correlation).\n";

// 3. Password aman dengan bcrypt
echo "\n3️⃣  Password storage:\n";
$alice = $authUsers->findOne(['email' => 'alice@example.com']);
echo "  Password hash: " . substr($alice['password'], 0, 20) . "...\n";
echo "  ✅ Menggunakan bcrypt (password_hash), bukan plain text\n";
echo "  ✅ BangronDB enkripsi MELINDUNGI data at-rest\n";
echo "  ✅ Bcrypt MELINDUNGI password bahkan jika dekripsi berhasil\n";

// ═══════════════════════════════════════════════════════════
// BAGIAN 8: Alur Lengkap Registrasi + Login
// ═══════════════════════════════════════════════════════════

sub('BAGIAN 8: Alur Lengkap - Registrasi + Login');

/**
 * Contoh implementasi lengkap class AuthService.
 */
class AuthService
{
    private \BangronDB\Collection $users;
    private string $encryptionKey;

    public function __construct(\BangronDB\Collection $users, string $encryptionKey)
    {
        $this->users = $users;
        $this->encryptionKey = $encryptionKey;

        // Setup: searchable fields + encryption
        $users->setSearchableFields([
            'email' => ['hash' => true],    // SHA-256 untuk privasi
        ]);
        $users->setEncryptionKey($encryptionKey, $_ENV['DB_ENCRYPTION_KEY_VERSION'] ?? 'v2-2026');
    }

    /**
     * Registrasi user baru.
     */
    public function register(string $name, string $email, string $password, string $role = 'user'): array
    {
        // 1. Cek apakah email sudah terdaftar
        $existing = $this->users->findOne(['email' => $email]);
        if ($existing) {
            return ['success' => false, 'error' => 'Email sudah terdaftar'];
        }

        // 2. Hash password dengan bcrypt
        $hashedPassword = password_hash($password, PASSWORD_BCRYPT);

        // 3. Insert user (otomatis terenkripsi + searchable index)
        $id = $this->users->insert([
            'name'     => $name,
            'email'    => $email,
            'password' => $hashedPassword,
            'role'     => $role,
            'created_at' => time(),
        ]);

        return ['success' => true, 'id' => $id];
    }

    /**
     * Login user.
     */
    public function login(string $email, string $password): array
    {
        // 1. Cari user by email (menggunakan searchable field)
        $user = $this->users->findOne(['email' => $email]);

        if (!$user) {
            return ['success' => false, 'error' => 'Email atau password salah'];
        }

        // 2. Verifikasi password
        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Email atau password salah'];
        }

        // 3. Update last login
        $this->users->update(
            ['email' => $email],
            ['$set' => ['last_login' => time()]]
        );

        return [
            'success' => true,
            'user' => [
                'id'   => $user['_id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ],
        ];
    }

    /**
     * Update profil user (data tetap terenkripsi).
     */
    public function updateProfile(string $email, array $data): bool
    {
        // Jika ganti email, perlu update searchable index juga
        // BangronDB otomatis update si_email saat update dilakukan
        $result = $this->users->update(['email' => $email], ['$set' => $data]);
        return $result > 0;
    }

    /**
     * Ganti password.
     */
    public function changePassword(string $email, string $oldPassword, string $newPassword): array
    {
        $user = $this->users->findOne(['email' => $email]);

        if (!$user) {
            return ['success' => false, 'error' => 'User tidak ditemukan'];
        }

        if (!password_verify($oldPassword, $user['password'])) {
            return ['success' => false, 'error' => 'Password lama salah'];
        }

        $this->users->update(
            ['email' => $email],
            ['$set' => ['password' => password_hash($newPassword, PASSWORD_BCRYPT)]]
        );

        return ['success' => true];
    }

    /**
     * Hapus akun (soft delete jika diaktifkan).
     */
    public function deleteAccount(string $email, string $password): array
    {
        $user = $this->users->findOne(['email' => $email]);

        if (!$user) {
            return ['success' => false, 'error' => 'User tidak ditemukan'];
        }

        if (!password_verify($password, $user['password'])) {
            return ['success' => false, 'error' => 'Password salah'];
        }

        $this->users->remove(['email' => $email]);
        return ['success' => true];
    }
}

// ── Demo AuthService ─────────────────────────────────────

echo "\n🏗️  Membuat AuthService baru...\n";
$secureDb = $client->createDB('auth_secure');
$secureUsers = $secureDb->createCollection('secure_users');
$auth = new AuthService($secureUsers, $encKey);

// Register
echo "\n📝 Register:\n";
$reg1 = $auth->register('Diana Secure', 'diana@secure.com', 'diana123');
echo "  Diana: " . ($reg1['success'] ? "✅ ID: {$reg1['id']}" : "❌ {$reg1['error']}") . "\n";

$reg2 = $auth->register('Eve Admin', 'eve@secure.com', 'eve456', 'admin');
echo "  Eve: " . ($reg2['success'] ? "✅ ID: {$reg2['id']}" : "❌ {$reg2['error']}") . "\n";

// Duplicate email
$reg3 = $auth->register('Diana Lagi', 'diana@secure.com', 'another');
echo "  Diana (duplicate): " . ($reg3['success'] ? "❌ Should fail!" : "✅ Ditolak: {$reg3['error']}") . "\n";

// Login
echo "\n🔑 Login:\n";
$login1 = $auth->login('diana@secure.com', 'diana123');
echo "  Diana (correct): " . ($login1['success'] ? "✅ Role: {$login1['user']['role']}" : "❌ {$login1['error']}") . "\n";

$login2 = $auth->login('diana@secure.com', 'wrong');
echo "  Diana (wrong pwd): " . ($login2['success'] ? "❌ Should fail!" : "✅ Ditolak: {$login2['error']}") . "\n";

// Change password
echo "\n🔄 Change Password:\n";
$chg = $auth->changePassword('diana@secure.com', 'diana123', 'newdiana789');
echo "  Change: " . ($chg['success'] ? "✅ Berhasil" : "❌ {$chg['error']}") . "\n";

// Login dengan password baru
$login3 = $auth->login('diana@secure.com', 'newdiana789');
echo "  Login new pwd: " . ($login3['success'] ? "✅ Berhasil" : "❌ Gagal") . "\n";

// Update profile
echo "\n✏️  Update Profile:\n";
$upd = $auth->updateProfile('diana@secure.com', ['name' => 'Diana Updated', 'phone' => '+628111222']);
echo "  Update: " . ($upd ? "✅ Berhasil" : "❌ Gagal") . "\n";
$check = $auth->login('diana@secure.com', 'newdiana789');
echo "  Name setelah update: {$check['user']['name']}\n";

// ═══════════════════════════════════════════════════════════
// RINGKASAN
// ═══════════════════════════════════════════════════════════

sub('RINGKASAN');

echo "🔐 Autentikasi + Enkripsi di BangronDB:\n\n";

echo "MASALAH:\n";
echo "  ❌ Data terenkripsi → tidak bisa findOne(['email' => 'xxx'])\n";
echo "  ❌ Scan semua data dengan \$where → sangat lambat\n\n";

echo "SOLUSI (Searchable Fields):\n";
echo "  ✅ Email → hash: true  → SHA-256 di kolom si_email\n";
echo "  ✅ Username → hash: false → lowercase di kolom si_username\n";
echo "  ✅ findOne() OTOMATIS cari di kolom searchable\n";
echo "  ✅ Data document TETAP terenkripsi (AES-256-GCM)\n";
echo "  ✅ Searchable hash TIDAK bisa di-reverse ke data asli\n\n";

echo "ALUR LOGIN:\n";
echo "  1. User input email + password\n";
echo "  2. BangronDB hash email → SHA-256\n";
echo "  3. Cari di si_email → cocokkan hash\n";
echo "  4. Jika ketemu → dekripsi document\n";
echo "  5. password_verify() → cocokkan password\n";
echo "  6. ✅ Login berhasil!\n\n";

echo "TIPS KEAMANAN:\n";
echo "  • Email/NIK/SSN   → searchable + hash: true\n";
echo "  • Username/role   → searchable + hash: false\n";
echo "  • Password        → JANGAN searchable! Gunakan bcrypt\n";
echo "  • Encryption key  → simpan di .env, JANGAN di code\n";
echo "  • Selalu gunakan password_hash() + password_verify()\n";

@$client->close();
echo "\nDone!\n";
