# Contributing to BangronDB

Terima kasih telah tertarik untuk berkontribusi pada BangronDB! Kami sangat menghargai setiap kontribusi dari komunitas.

## Cara Berkontribusi

### 1. Fork Repository

Mulai dengan fork repository ini ke akun GitHub Anda.

```bash
# Clone fork Anda
git clone https://github.com/YOUR_USERNAME/BangronDB.git
cd BangronDB

# Tambahkan upstream remote
git remote add upstream https://github.com/herdianrony/BangronDB.git
```

### 2. Buat Branch untuk Fitur/Fix Anda

```bash
# Update branch main lokal Anda
git fetch upstream
git checkout main
git merge upstream/main

# Buat branch baru untuk fitur/fix
git checkout -b feature/your-amazing-feature
# atau untuk bug fix:
git checkout -b bugfix/issue-description
```

### 3. Develop & Test

```bash
# Install dependencies
composer install

# Run tests untuk memastikan tidak ada yang break
php vendor/bin/phpunit

# Jalankan contoh untuk testing manual
php examples/01-basic-crud.php
```

### 4. Commit dengan Conventional Commits

Gunakan format conventional commits:

```bash
git commit -m "feat: add new query operator $between"
git commit -m "fix: resolve encryption issue with special characters"
git commit -m "docs: update README with new examples"
git commit -m "refactor: improve performance of find() method"
git commit -m "test: add tests for collection rename feature"
```

Format:

- `feat:` - Fitur baru
- `fix:` - Bug fixes
- `docs:` - Documentation changes
- `refactor:` - Code refactoring
- `test:` - Test additions/updates
- `chore:` - Build, dependencies, dll

### 5. Push & Create Pull Request

```bash
# Push branch Anda
git push origin feature/your-amazing-feature

# Buka pull request di GitHub
```

## Code Standards

### PSR-12 Compliance

Pastikan kode Anda mengikuti [PSR-12](https://www.php-fig.org/psr/psr-12/):

```php
<?php

namespace BangronDB;

/**
 * Gunakan doc blocks untuk class dan public methods
 */
class MyClass
{
    /**
     * Constructor example.
     *
     * @param string $name
     * @param int    $value
     */
    public function __construct(string $name, int $value)
    {
        // Implementation
    }

    /**
     * Public method example.
     *
     * @return boolean
     */
    public function doSomething(): bool
    {
        return true;
    }
}
```

### Best Practices

- ✅ Gunakan type declarations untuk arguments dan return types
- ✅ Tulis descriptive doc blocks
- ✅ Gunakan meaningful variable names
- ✅ Keep methods focused dan singkat (< 50 lines ideal)
- ✅ Add null coalescing dibanding isset checks
- ✅ Gunakan constant untuk magic strings/numbers

### Kode yang HARUS dihindari

```php
// ❌ TIDAK BOLEH - No type declarations
public function insert($document)

// ❌ TIDAK BOLEH - Magic numbers/strings
$limit = 10000;

// ❌ TIDAK BOLEH - Unclear variable names
$x = $data['a']['b'];

// ❌ TIDAK BOLEH - Tidak ada doc blocks
public function complexMethod()
```

## Testing Requirements

Setiap kontribusi **HARUS** menyertakan tests:

### 1. Unit Tests untuk Fitur Baru

```php
// tests/MyNewFeatureTest.php
namespace BangronDB\Tests;

use BangronDB\Collection;
use BangronDB\Database;
use PHPUnit\Framework\TestCase;

class MyNewFeatureTest extends TestCase
{
    private Collection $collection;

    protected function setUp(): void
    {
        $db = new Database(':memory:');
        $this->collection = $db->selectCollection('test');
    }

    public function testFeatureWorksCorrectly(): void
    {
        // Arrange
        $document = ['name' => 'test'];

        // Act
        $result = $this->collection->insert($document);

        // Assert
        $this->assertNotNull($result);
    }
}
```

### 2. Run Tests Sebelum Submit PR

```bash
# Jalankan semua tests
php vendor/bin/phpunit

# Jalankan test tertentu
php vendor/bin/phpunit tests/CollectionTest.php

# Dengan code coverage (optional)
php vendor/bin/phpunit --coverage-html coverage/
```

### 3. Test Coverage Expectations

- Minimum 80% code coverage untuk new code
- All public methods harus memiliki tests
- Edge cases dan error conditions harus di-test

## Documentation Requirements

Jika Anda menambahkan fitur baru:

1. **Update README.md** dengan contoh penggunaan
2. **Tambahkan ke docs folder** jika fiturnya significant
3. **Update CHANGELOG.md** (akan dilakukan saat release)
4. **Tambahkan inline comments** untuk logic yang kompleks

### Contoh Documentation

```markdown
## Fitur Baru: Custom Query Operators

### Usage

\`\`\`php
$collection->find([
    'age' => ['$between' => [18, 65]]
]);
\`\`\`

### API Reference

**$between** - Match values within range

- Syntax: `['$between' => [min, max]]`
- Returns: Documents where value >= min AND value <= max
```

## Pull Request Guidelines

### Deskripsi PR yang Baik

```markdown
## Description

Menjelaskan apa yang diubah dan mengapa.

## Type of Change

- [ ] Bug fix
- [ ] New feature
- [ ] Breaking change
- [ ] Documentation update

## Testing

- [ ] Tests added/updated
- [ ] All tests passing
- [ ] Code coverage maintained

## Checklist

- [ ] Code follows PSR-12
- [ ] Documentation updated
- [ ] No breaking changes (unless intended)
- [ ] CHANGELOG considered
```

### PR yang TIDAK akan di-accept

❌ PR tanpa tests  
❌ PR yang break existing tests  
❌ PR dengan code style violations  
❌ PR tanpa clear description  
❌ PR dengan merge conflicts yang tidak resolved

## Melaporkan Issues

Ketika menemukan bug, buat issue dengan detail:

```markdown
## Bug Description

Penjelasan singkat tentang bug

## Steps to Reproduce

1. Buat collection dengan encryption
2. Insert document dengan special characters
3. Try to decrypt

## Expected Behavior

Data harus decrypt dengan benar

## Actual Behavior

Decryption gagal dengan error: ...

## Environment

- PHP Version: 8.3
- Operating System: Windows 10
- BangronDB Version: 1.0.0

## Error Log

\`\`\`
[Error stack trace]
\`\`\`
```

## Feature Requests

Untuk feature requests, jelaskan:

```markdown
## Feature Description

Apa fitur yang Anda inginkan?

## Use Case

Bagaimana fitur ini akan digunakan?

## Example API

Bagaimana API ideal dari fitur ini?

## Alternatives

Apakah ada alternatif yang sudah ada?
```

## Review Process

1. **Automated Checks**
   - PHPUnit tests harus pass
   - Code standard checks

2. **Code Review**
   - Maintainer akan review kode
   - Feedback akan diberikan jika ada perubahan diminta

3. **Merge**
   - PR akan di-merge setelah approval
   - Contributor akan di-credit di CHANGELOG

## Development Setup

### Requirements

- PHP 8.0+
- Composer
- Git

### Quick Setup

```bash
# Clone & install
git clone https://github.com/herdianrony/BangronDB.git
cd BangronDB
composer install

# Verify setup
php vendor/bin/phpunit --version
php --version
```

## Getting Help

- **Documentation**: Lihat [README.md](README.md)
- **API Reference**: Lihat [docs/api/](docs/api/)
- **Examples**: Lihat [examples/](examples/)
- **Issues**: Cek issue tracker untuk pertanyaan serupa

## Code of Conduct

- Berbicara dengan respectful
- Tidak ada harassment atau discrimination
- Focus pada code, bukan pada person
- Welcome komentar konstruktif

## Questions?

Jika ada pertanyaan tentang contribution process, buat discussion atau issue dengan label `question`.

---

**Terima kasih telah menjadi bagian dari BangronDB! 🎉**
