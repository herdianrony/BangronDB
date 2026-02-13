# IdGeneratorTrait

Trait untuk mengelola generasi ID dokumen dengan berbagai strategi. Mendukung UUID otomatis, ID manual, dan ID dengan prefix counter.

## Mode ID

### ID_MODE_AUTO ('auto')

- Generate UUID v4 secara otomatis
- Format: `xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx`
- Default untuk semua insert baru

### ID_MODE_MANUAL ('manual')

- Gunakan \_id yang disediakan user
- Tidak generate ID otomatis
- Cocok untuk import data dengan ID existing

### ID_MODE_PREFIX ('prefix')

- Generate ID dengan prefix dan counter
- Format: `PREFIX-XXXXXX` (6 digit padded)
- Counter bertambah otomatis per insert

## Properti

### `$idMode`

- **Tipe**: `string`
- **Default**: `'auto'`
- **Deskripsi**: Mode generasi ID saat ini

### `$idPrefix`

- **Tipe**: `?string`
- **Deskripsi**: Prefix untuk ID (opsional)

### `$idSuffix`

- **Tipe**: `?string`
- **Deskripsi**: Suffix untuk ID (opsional)

### `$idCounter`

- **Tipe**: `int`
- **Default**: `0`
- **Deskripsi**: Counter untuk mode prefix

## Metode Konfigurasi

### `setIdModeAuto(): self`

Mengatur mode ID ke auto (UUID v4).

**Return:** Instance untuk chaining

### `setIdModeManual(): self`

Mengatur mode ID ke manual (tidak generate otomatis).

**Return:** Instance untuk chaining

### `setIdModePrefix(string $prefix): self`

Mengatur mode ID ke prefix dengan counter.

**Parameter:**

- `$prefix` (string): Prefix untuk ID (contoh: 'USR', 'ORD')

**Return:** Instance untuk chaining

### `setPrefix(string $prefix): self`

Mengatur prefix umum untuk semua ID.

**Parameter:**

- `$prefix` (string): Prefix string

**Return:** Instance untuk chaining

### `setSuffix(string $suffix): self`

Mengatur suffix umum untuk semua ID.

**Parameter:**

- `$suffix` (string): Suffix string

**Return:** Instance untuk chaining

### `getIdMode(): string`

Mengembalikan mode ID saat ini.

**Return:** String mode ID

## Metode Internal

### `_generateId(): ?string`

Generate ID berdasarkan mode saat ini.

**Return:** Generated ID atau null untuk manual mode

### `ensureDocumentId(array $document): mixed`

Memastikan dokumen memiliki \_id yang valid.

**Parameter:**

- `$document` (array): Dokumen untuk diproses

**Return:** Dokumen dengan \_id atau false jika gagal

## Contoh Penggunaan

### Auto Mode (Default)

```php
$users = $db->selectCollection('users');
// Mode auto aktif by default

$user = $users->insert(['name' => 'John']);
// _id akan jadi: '550e8400-e29b-41d4-a716-446655440000'
```

### Manual Mode

```php
$users = $db->selectCollection('users');
$users->setIdModeManual();

$user = $users->insert(['_id' => 'custom-id-123', 'name' => 'John']);
// _id akan menggunakan 'custom-id-123'
```

### Prefix Mode

```php
$users = $db->selectCollection('users');
$users->setIdModePrefix('USR');

// Insert pertama
$user1 = $users->insert(['name' => 'John']);
// _id: 'USR-000001'

// Insert kedua
$user2 = $users->insert(['name' => 'Jane']);
// _id: 'USR-000002'
```

### Dengan Prefix/Suffix Umum

```php
$users = $db->selectCollection('users');
$users->setIdModeAuto();
$users->setPrefix('app-');
$users->setSuffix('-user');

$user = $users->insert(['name' => 'John']);
// _id: 'app-xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx-user'
```

## Counter Management

Untuk mode prefix, counter diinisialisasi dari ID tertinggi yang ada:

```php
// Jika database sudah memiliki USR-000005
$users->setIdModePrefix('USR');
// Counter akan di-set ke 5
// Insert berikutnya akan jadi USR-000006
```

## Tips Penggunaan

- **Auto mode**: Cocok untuk aplikasi baru tanpa ID khusus
- **Manual mode**: Untuk import data atau integrasi dengan sistem existing
- **Prefix mode**: Untuk ID readable dan sequential per jenis data
- **Prefix umum**: Untuk namespace global di aplikasi multi-tenant

## Migrasi Mode

Mode dapat diubah kapan saja, tetapi pertimbangkan:

- Manual ke auto: Insert baru akan dapat ID, existing tetap
- Auto ke manual: Insert tanpa \_id akan gagal
- Prefix ke auto: Counter di-reset, ID baru jadi UUID
