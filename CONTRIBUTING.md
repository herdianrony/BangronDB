# Contributing to BangronDB

Terima kasih sudah tertarik berkontribusi ke BangronDB.

## Persiapan Cepat

```bash
git clone https://github.com/herdianrony/BangronDB.git
cd BangronDB
composer install
vendor/bin/phpunit
```

Jika Anda memakai FrankenPHP:

```bash
frankenphp php-cli vendor/bin/phpunit
```

## Kebutuhan Development

- PHP 8.1+
- `pdo_sqlite`
- `openssl`
- Composer

## Workflow yang Disarankan

1. Fork repository ini.
2. Buat branch baru dari branch aktif utama.
   - `feature/nama-fitur`
   - `fix/nama-perbaikan`
   - `docs/nama-perubahan`
   - `refactor/nama-refactor`
3. Lakukan perubahan sekecil dan sejelas mungkin.
4. Tambahkan atau perbarui test bila ada perubahan perilaku.
5. Jalankan test suite sebelum commit.
6. Buka Pull Request dengan deskripsi yang jelas.

## Standar Commit

Gunakan pesan commit yang ringkas dan konsisten. Conventional commits sangat dianjurkan:

- `feat:` fitur baru
- `fix:` bug fix
- `docs:` perubahan dokumentasi
- `refactor:` refactor tanpa mengubah behavior publik
- `test:` perubahan atau penambahan test
- `chore:` pekerjaan maintenance

Contoh:

```text
fix: keep searchable fields after collection reload
refactor: centralize collection metadata handling
docs: sync README with current examples
```

## Standar Kode

- Ikuti [PSR-12](https://www.php-fig.org/psr/psr-12/)
- Gunakan `declare(strict_types=1);`
- Gunakan type declaration untuk parameter dan return value
- Tambahkan docblock pada class dan public method yang relevan
- Utamakan method kecil dan fokus pada satu tanggung jawab
- Hindari menambah API publik baru tanpa alasan kuat
- Jaga backward compatibility jika memungkinkan

## Dependency Policy

BangronDB diperlakukan sebagai **library**, jadi `composer.lock` sengaja tidak di-commit ke repository.

Implikasinya:

- gunakan `composer install` / `composer update` sesuai kebutuhan lokal
- jangan tambahkan `composer.lock` ke commit kecuali kebijakan project berubah di masa depan
- fokus review dependency pada `composer.json`

## Testing

Setiap perubahan yang memengaruhi behavior sebaiknya disertai test.

Perintah yang umum dipakai:

```bash
vendor/bin/phpunit
composer test
composer test-coverage
composer lint
```

Checklist minimum sebelum membuka PR:

- Semua test lulus
- Tidak ada syntax error
- Dokumentasi ikut diperbarui jika behavior berubah

## Area yang Perlu Perhatian Khusus

Saat mengubah area berikut, mohon tambahkan regression test:

- upsert / `save()`
- rename collection
- searchable fields
- encryption & key handling
- change notification / metadata
- hooks lifecycle

## Melaporkan Bug

Saat membuat issue, sertakan:

1. deskripsi bug
2. langkah reproduksi
3. expected vs actual behavior
4. versi PHP dan OS
5. potongan kode minimal untuk reproduksi
6. error message / stack trace jika ada

## Pull Request Checklist

- [ ] Perubahan fokus dan tidak terlalu besar
- [ ] Test ditambahkan/diperbarui bila diperlukan
- [ ] Semua test lulus
- [ ] Dokumentasi diperbarui bila ada perubahan perilaku
- [ ] Tidak ada file sementara / debug yang ikut ter-commit

Terima kasih sudah membantu menjaga BangronDB tetap rapi dan stabil.
