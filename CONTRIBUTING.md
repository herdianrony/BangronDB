# Contributing to BangronDB

Terima kasih telah tertarik untuk berkontribusi!

## Quick Setup

```bash
git clone https://github.com/herdianrony/BangronDB.git
cd BangronDB
composer install
php vendor/bin/phpunit   # Pastikan semua test pass
```

## Workflow

1. **Fork** repositori ini
2. **Buat branch**: `git checkout -b feature/nama-fitur` atau `bugfix/deskripsi-fix`
3. **Develop & test**: Pastikan `vendor/bin/phpunit` pass
4. **Commit** menggunakan conventional commits:
   - `feat:` fitur baru
   - `fix:` bug fix
   - `docs:` perubahan dokumentasi
   - `refactor:` refactoring
   - `test:` penambahan/pembaruan test
5. **Push & buat Pull Request**

## Code Standards

- Ikuti [PSR-12](https://www.php-fig.org/psr/psr-12/)
- Gunakan type declarations untuk arguments dan return types
- Tulis doc blocks untuk class dan public methods
- Gunakan `declare(strict_types=1);` di semua file
- Jaga method tetap singkat (< 50 lines)

## Testing

- Setiap kontribusi **HARUS** menyertakan test
- Minimum 80% code coverage untuk kode baru
- Semua public methods harus memiliki test
- Jalankan `vendor/bin/phpunit` sebelum submit PR

## Melaporkan Bug

Buat issue dengan detail:

1. **Deskripsi** bug
2. **Langkah reproduksi**
3. **Expected behavior** vs **Actual behavior**
4. **Environment** (PHP version, OS, BangronDB version)
5. **Error log / stack trace**

## PR Checklist

- [ ] Tests ditambahkan/diperbarui
- [ ] Semua test pass
- [ ] Kode mengikuti PSR-12
- [ ] Dokumentasi diperbarui jika perlu

---

Terima kasih telah menjadi bagian dari BangronDB!
