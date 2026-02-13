# ❓ FAQ: Pertanyaan Umum Pemula

Jawaban untuk pertanyaan yang sering ditanyakan pemula.

---

## Instalasi & Setup

### Q: Apakah saya harus install BangronDB di server hosting?

**A:** Ya, BangronDB perlu diinstall di server Anda.

- Untuk lokal development: `composer require herdianrony/bangrondb`
- Untuk production server: Upload via Composer atau upload manual files

### Q: Apa bedanya file-based vs in-memory database?

**A:**

- **File-based** (recommended): Data disimpan di file `.bangron` di disk
  ```php
  $client = new Client('/path/to/data/'); // Data persist
  ```
- **In-memory**: Data hanya disimpan di RAM (hilang saat program selesai)
  ```php
  $client = new Client(':memory:'); // Untuk testing saja
  ```

### Q: Berapa ukuran file database yang optimal?

**A:** Tergantung data. Sebagai panduan:

- **< 1 GB**: BangronDB sangat cocok (file-based)
- **1-10 GB**: Masih OK, tapi pertimbangkan optimization
- **> 10 GB**: Pertimbangkan database server besar (PostgreSQL, MySQL)

### Q: Apakah BangronDB bisa digunakan di production?

**A:** **Ya**, BangronDB production-ready untuk:

- Small to medium projects (< 100k documents)
- Embedded applications
- Desktop applications
- Low-traffic websites

**Tidak cocok untuk**:

- Large-scale aplikasi (> 1M documents)
- High-traffic aplikasi (> 1000 requests/second)

---

## Data Structure

### Q: Apa itu "Document"?

**A:** Satu record data dalam koleksi. Mirip baris di tabel SQL.

```php
$doc = [
    '_id' => '123',
    'name' => 'John',
    'email' => 'john@example.com'
];

$users->insert($doc); // $doc adalah "document"
```

### Q: Berapa field maksimal dalam satu document?

**A:** Secara teknis unlimited, tapi praktisnya:

- Rekomendasi: < 100 fields per document
- Max SQLite: 2,000 columns (shared dengan internal)

### Q: Apakah string case-sensitive?

**A:** **Ya**, `'John'` ≠ `'john'`

Untuk case-insensitive search:

```php
// Simpan lowercase
$users->insert(['name' => strtolower('John')]); // 'john'

// Query juga lowercase
$user = $users->findOne(['name' => strtolower('JOHN')]);
```

---

## Query & Filtering

### Q: Kenapa query saya tidak return hasil?

**A:** Checklist:

1. ✅ Pastikan dokumen exist: `$users->count()`
2. ✅ Pastikan field name benar (case-sensitive)
3. ✅ Pastikan value type sama (string, int, dll)
4. ✅ Untuk encrypted data, pastikan field di-set sebagai searchable

```php
// ❌ SALAH
$users->find(['Name' => 'John']); // Field adalah 'name', bukan 'Name'

// ✅ BENAR
$users->find(['name' => 'John']);
```

### Q: Bagaimana query dengan nilai yang bisa null?

**A:** Gunakan `$exists` operator:

```php
// Email ada (tidak null)
$users->find(['email' => ['$exists' => true]]);

// Email tidak ada (null)
$users->find(['email' => ['$exists' => false]]);
```

### Q: Berapa limit maksimal jumlah row yang bisa di-query?

**A:** Tidak ada limit teknis, tapi untuk performa:

- Rekomendasi: Pakai `limit()` dan `skip()` untuk pagination
- Jangan query > 10,000 documents sekaligus

```php
// ❌ SALAH - Query semua
$users->find()->toArray(); // Batal jika terlalu banyak

// ✅ BENAR - Pakai pagination
$users->find()->limit(100)->skip(0)->toArray();
```

### Q: Bagaimana query dengan multiple conditions (AND)?

**A:**

```php
// Default adalah AND
$users->find([
    'status' => 'active',
    'age' => ['$gte' => 18],
    'role' => 'admin'
]);
// = status='active' AND age>=18 AND role='admin'

// Atau explicit dengan $and
$users->find([
    '$and' => [
        ['status' => 'active'],
        ['age' => ['$gte' => 18]],
        ['role' => 'admin']
    ]
]);
```

### Q: Bagaimana query dengan OR condition?

**A:**

```php
$users->find([
    '$or' => [
        ['role' => 'admin'],
        ['role' => 'editor']
    ]
]);
// = role='admin' OR role='editor'

// Cara lain (lebih sederhana)
$users->find(['role' => ['$in' => ['admin', 'editor']]]);
```

---

## Update & Delete

### Q: Update vs Save, apa bedanya?

**A:**

- **update()**: Ubah dokumen existing
- **save()**: Insert jika baru, update jika sudah ada (upsert)

```php
// Update (error jika tidak ada)
$collection->update(['_id' => '123'], ['name' => 'John']);

// Save (insert jika tidak ada)
$collection->save(['_id' => '123', 'name' => 'John']);
```

### Q: Bagaimana cara undo delete?

**A:** Gunakan **soft delete**:

```php
// Enable soft delete
$users->useSoftDeletes(true);

// Delete (sebenarnya cuma ditandai)
$users->remove(['_id' => '123']);

// Restore
$users->restore(['_id' => '123']);

// Force delete (permanent)
$users->forceDelete(['_id' => '123']);
```

### Q: Bagaimana update hanya beberapa field tertentu?

**A:** Gunakan `$set`:

```php
// Add/update hanya name, field lain tidak tersentuh
$users->update(
    ['_id' => '123'],
    ['$set' => ['name' => 'John']]
);

// Atau merge mode (default)
$users->update(['_id' => '123'], ['name' => 'John']);
// Sama hasilnya
```

---

## Encryption & Security

### Q: Apakah data otomatis ter-enkripsi?

**A:** **Tidak**. Anda harus explicitly enable:

```php
// Enable encryption untuk koleksi
$users->setEncryptionKey('kunci-rahasia-min-32-chars');

// Sekarang data ter-enkripsi
$users->insert(['password' => 'secret123']);
```

### Q: Bagaimana jika lupa encryption key?

**A:** **Data Anda tidak bisa dikembalikan**.

Selalu backup key di:

- Environment variable
- Password manager (1Password, LastPass)
- Secure vault (AWS Secrets Manager, HashiCorp Vault)

```php
// ✅ BENAR
$key = $_ENV['DB_ENCRYPTION_KEY'];
$users->setEncryptionKey($key);
```

### Q: Apakah query bisa dilakukan pada encrypted data?

**A:** **Terbatas**. For encrypted fields yang ingin di-query, gunakan **searchable fields**:

```php
// Enable searchable + encrypted
$users->setSearchableFields(['email'], true); // Hash untuk privacy

// Sekarang bisa query email meski ter-enkripsi
$user = $users->findOne(['email' => 'john@example.com']);
```

---

## Relationships

### Q: Bagaimana relationship one-to-one?

**A:** Gunakan foreign key:

```php
// User dan user details
$users = $db->users;
$profiles = $db->profiles;

// Insert user
$userId = $users->insert(['name' => 'John']);

// Insert profile dengan user_id
$profiles->insert([
    'user_id' => $userId,
    'bio' => 'I am John'
]);

// Query dengan populate
$profile = $profiles->find()
    ->populate('user_id', $users, ['as' => 'user'])
    ->toArray();
// Result: {user_id: 'xxx', bio: '...', user: {...}}
```

### Q: Bagaimana relationship one-to-many?

**A:**

```php
// Users dan Posts (1 user -> banyak posts)
$users = $db->users;
$posts = $db->posts;

// Insert user
$userId = $users->insert(['name' => 'John']);

// Insert multiple posts
$posts->insert([
    ['title' => 'Post 1', 'author_id' => $userId],
    ['title' => 'Post 2', 'author_id' => $userId]
]);

// Query posts dengan author info
$userPosts = $posts->find(['author_id' => $userId])
    ->populate('author_id', $users, ['as' => 'author'])
    ->toArray();
```

### Q: Bagaimana relationship many-to-many?

**A:** Gunakan junction table:

```php
// Students, Courses, Student_Courses (junction)
$students = $db->students;
$courses = $db->courses;
$enrollments = $db->enrollments;

// Insert enrollment (mapping)
$enrollments->insert([
    'student_id' => 'stu1',
    'course_id' => 'course1'
]);

// Query student's courses
$enrollment = $enrollments->find(['student_id' => 'stu1'])
    ->populate('course_id', $courses, ['as' => 'course'])
    ->toArray();
```

---

## Validation

### Q: Kapan validation dijalankan?

**A:** Automatically pada insert/update jika schema di-set:

```php
$users->setSchema([
    'email' => [
        'required' => true,
        'type' => 'string',
        'regex' => '/^[^\s@]+@[^\s@]+$/'
    ]
]);

// Validation otomatis saat insert
try {
    $users->insert(['name' => 'John']); // ERROR: email required
} catch (ValidationException $e) {
    echo $e->getMessage();
}
```

### Q: Bagaimana custom validation?

**A:** Gunakan hook:

```php
$users->on('beforeInsert', function($doc) {
    // Custom logic
    if (strlen($doc['password']) < 8) {
        throw new Exception('Password minimal 8 karakter');
    }
    return $doc;
});
```

---

## Performance

### Q: Apakah index benar-benar mempercepat query?

**A:** **Ya**, terutama untuk:

- Large datasets (> 10k documents)
- Frequently queried fields
- Sorted queries

```php
// Tanpa index: Slow
$users->find(['email' => 'john@example.com'])->toArray();

// Dengan index: Fast
$users->createIndex('email');
$users->find(['email' => 'john@example.com'])->toArray();
```

### Q: Bagaimana men-debug slow queries?

**A:**

```php
// Enable query logging
$collection->on('afterInsert', function($doc, $id) {
    error_log("INSERT: $id");
});

// Atau check health metrics
$report = $db->getHealthReport();
print_r($report); // Lihat issues dan recommendations
```

### Q: Apakah batch insert lebih cepat daripada satu-satu?

**A:** **Ya**, batch insert 10x lebih cepat:

```php
// ❌ LAMBAT (10 operasi)
foreach ($data as $item) {
    $users->insert($item);
}

// ✅ CEPAT (1 operasi)
$users->insert($data);
```

---

## Common Mistakes

### Q: Dokumentasi bilang "direkomendasikan Composer" - apakah bisa tanpa?

**A:** **Bisa**, tapi harus manual require semua files:

```php
// Manual include (banyak files)
require_once 'src/Client.php';
require_once 'src/Database.php';
// ... and many more

// Lebih baik pakai Composer IMO
composer require herdianrony/bangrondb
```

### Q: Kenapa update saya tidak bekerja?

**A:** Checklist:

1. ✅ Field yang di-update exist?
2. ✅ Kriteria pencarian match dokumen?
3. ✅ Tipe data sama?

```php
// ❌ SALAH - Kriteria tidak match
$users->update(['_id' => 'xxx'], ['age' => 31]); // xxx tidak ada

// ✅ BENAR - Pastikan ID exist
$doc = $users->findOne(['_id' => 'xxx']);
if ($doc) {
    $users->update(['_id' => 'xxx'], ['age' => 31]);
}
```

### Q: "toArray() exceeding safe limit" error?

**A:** Query return terlalu banyak dokumen. Gunakan limit:

```php
// ❌ SALAH - More than 10,000 docs
$all = $users->find()->toArray();

// ✅ BENAR - Dengan limit
$all = $users->find()->limit(100)->toArray();

// ✅ BENAR - Atau gunakan toArraySafe
$all = $users->find()->toArraySafe(50000); // Custom limit
```

---

## Migration dari SQL

### Q: Bagaimana migrasi dari MySQL/PostgreSQL ke BangronDB?

**A:**

```php
// 1. Query datanya dari

 SQL database
$sqlRows = sqlQuery('SELECT * FROM users');

// 2. Insert ke BangronDB
foreach ($sqlRows as $row) {
    $bangronUsers->insert([
        'name' => $row['name'],
        'email' => $row['email'],
        // ... map semua fields
    ]);
}

// 3. Verify
echo $bangronUsers->count() . ' documents migrated';
```

### Q: Bagaimana query SQL-like syntax?

**A:** BangronDB tidak support SQL. Gunakan array-based API:

```php
// SQL: SELECT * FROM users WHERE age > 18 ORDER BY name
// BangronDB:
$users->find(['age' => ['$gt' => 18]])
    ->sort(['name' => 1])
    ->toArray();
```

---

## General

### Q: Di mana saya bisa mendapat bantuan?

**A:**

1. 📖 Lihat dokumentasi di `docs/` folder
2. 💡 Baca contoh di `examples/` folder
3. 🐛 Check `docs/troubleshooting.md` untuk error solving
4. 📚 Lihat `GLOSSARY.md` untuk istilah yang tidak dimengerti
5. ⚡ Referensi cepat di `CHEAT_SHEET.md`

### Q: Apakah ada limitation yang saya perlu tahu?

**A:**

- **Max file size**: Tergantung disk (praktis unlimited)
- **Max fields per document**: ~2000 (shared dengan SQLite columns)
- **Max nested level**: Unlimited (dalam reason)
- **Concurrent writes**: WAL mode limit
- **Query speed**: Improve dengan indexing

### Q: Bagaimana saya bisa berkontribusi ke BangronDB?

**A:** Lihat `CONTRIBUTING.md` untuk guidelines lengkap!

---

**Masih ada pertanyaan? Baca dokumentasi lengkap atau buat issue di GitHub! 📝**
