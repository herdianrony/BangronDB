# EncryptionTrait

Trait untuk mengelola enkripsi dan dekripsi dokumen menggunakan AES-256-CBC. Memungkinkan penyimpanan dokumen terenkripsi sambil tetap dapat melakukan query pada field tertentu.

## Properti

### `$encryptionKey`

- **Tipe**: `?string`
- **Deskripsi**: Kunci enkripsi per-koleksi (mengoverride kunci database level)

## Metode Utama

### `setEncryptionKey(?string $key): self`

Mengatur kunci enkripsi untuk koleksi ini.

**Parameter:**

- `$key` (string|null): Kunci enkripsi atau null untuk menonaktifkan

**Return:** Instance untuk chaining

### `isEncrypted(): bool`

Memeriksa apakah koleksi menggunakan enkripsi.

**Return:** True jika terenkripsi

### `encodeStored(array $doc): string`

Mengenkode dokumen untuk penyimpanan (dengan enkripsi jika aktif).

**Parameter:**

- `$doc` (array): Dokumen untuk dienkode

**Return:** JSON string siap disimpan

### `decodeStored(string $stored): ?array`

Mendekode dokumen dari penyimpanan (dengan dekripsi jika perlu).

**Parameter:**

- `$stored` (string): Data tersimpan

**Return:** Array dokumen atau null jika gagal

## Mekanisme Enkripsi

1. **Format Terenkripsi**: `{ _id, encrypted_data, iv }`
2. **Algoritma**: AES-256-CBC
3. **IV**: Initialization Vector unik per dokumen
4. **Key Derivation**: SHA-256 hash dari kunci

## Contoh Penggunaan

```php
use BangronDB\Collection;

$collection = $db->selectCollection('sensitive_data');

// Mengaktifkan enkripsi
$collection->setEncryptionKey('my-secret-key');

// Insert dokumen terenkripsi
$collection->insert([
    'ssn' => '123-45-6789',
    'credit_card' => '4111-1111-1111-1111'
]);

// Dokumen akan disimpan terenkripsi
// Tetapi masih dapat dicari menggunakan searchable fields
```

## Catatan Keamanan

- Kunci enkripsi harus dijaga kerahasiaannya
- Dokumen terenkripsi tidak dapat di-query kecuali field searchable
- Gunakan HTTPS untuk transmisi kunci
- Backup kunci terpisah dari database
