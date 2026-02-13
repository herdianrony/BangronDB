# HooksTrait

Trait untuk mengelola event hooks pada koleksi. Mendukung hooks untuk before/after insert, update, dan remove operations.

## Konstanta Event

- `HOOK_BEFORE_INSERT` = 'beforeInsert'
- `HOOK_AFTER_INSERT` = 'afterInsert'
- `HOOK_BEFORE_UPDATE` = 'beforeUpdate'
- `HOOK_AFTER_UPDATE` = 'afterUpdate'
- `HOOK_BEFORE_REMOVE` = 'beforeRemove'
- `HOOK_AFTER_REMOVE` = 'afterRemove'

## Properti

### `$hooks`

- **Tipe**: `array<string,array<int,callable>>`
- **Deskripsi**: Array hooks per event

## Metode Utama

### `on(string $event, callable $fn): void`

Mendaftarkan hook untuk event tertentu.

**Parameter:**

- `$event` (string): Nama event
- `$fn` (callable): Fungsi hook

### `off(string $event, ?callable $fn = null): void`

Menghapus hook untuk event.

**Parameter:**

- `$event` (string): Nama event
- `$fn` (callable|null): Fungsi spesifik atau null untuk semua

### `getHooks(): array`

Mengembalikan semua hooks yang terdaftar.

**Return:** Array hooks

## Metode Hook Internal

### `applyBeforeInsertHooks(array $document): mixed`

Menerapkan hooks before insert.

### `applyAfterInsertHooks(array $document, mixed $insertId): void`

Menerapkan hooks after insert.

### `applyUpdateHooks(&$criteria, array &$data): void`

Menerapkan hooks before update.

### `triggerAfterUpdateHooks(array $originalDoc, array $updatedDocument): void`

Menerapkan hooks after update.

### `shouldRemoveDocument(array $row): bool`

Memeriksa apakah dokumen boleh dihapus (hooks before remove).

### `triggerAfterRemoveHooks(array $document): void`

Menerapkan hooks after remove.

## Contoh Penggunaan

```php
use BangronDB\Collection;

$users = $db->selectCollection('users');

// Hook untuk validasi sebelum insert
$users->on(Collection::HOOK_BEFORE_INSERT, function($doc) {
    if (empty($doc['email'])) {
        throw new Exception('Email required');
    }
    // Modifikasi dokumen
    $doc['created_at'] = time();
    return $doc;
});

// Hook untuk logging setelah update
$users->on(Collection::HOOK_AFTER_UPDATE, function($original, $updated) {
    error_log("User updated: {$updated['_id']}");
});

// Hook untuk validasi sebelum delete
$users->on(Collection::HOOK_BEFORE_REMOVE, function($doc) {
    if ($doc['role'] === 'admin') {
        return false; // Blokir penghapusan admin
    }
    return true;
});

// Hook dengan parameter modifikasi
$users->on(Collection::HOOK_BEFORE_UPDATE, function($criteria, $data) {
    // Tambah timestamp update
    $data['$set']['updated_at'] = time();
    return ['criteria' => $criteria, 'data' => $data];
});
```

## Aturan Hook

- **beforeInsert**: Dapat memodifikasi dokumen atau return false untuk membatalkan
- **afterInsert**: Hanya untuk logging/side effects
- **beforeUpdate**: Dapat memodifikasi criteria dan data
- **afterUpdate**: Dapat melihat dokumen sebelum dan sesudah
- **beforeRemove**: Return false untuk membatalkan penghapusan
- **afterRemove**: Cleanup setelah penghapusan

## Error Handling

- Exception dalam hook akan dicatat tapi tidak menghentikan operasi
- Return false dari before hooks akan membatalkan operasi
- Hooks dieksekusi dalam urutan pendaftaran
