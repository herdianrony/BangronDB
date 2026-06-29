# Query Operators – BangronDB v1.2.0

Semua operator di-pass sebagai array criteria ke `find()`, `findOne()`, `update()`, `remove()`, `count()`.

---

## Comparison

| Operator | Contoh Request | Match |
|---|---|---|
| `$eq` | `['age'=>['$eq'=>25]]` | = 25 |
| `$ne` | `['status'=>['$ne'=>'deleted']]` | != |
| `$gt` | `['age'=>['$gt'=>18]]` | > |
| `$gte` | `['age'=>['$gte'=>18]]` | >= |
| `$lt` | `['price'=>['$lt'=>100]]` | < |
| `$lte` | `['price'=>['$lte'=>100]]` | <= |

**Request**
```php
$users->find(['age'=>['$gte'=>18, '$lt'=>65]])->toArray();
```

**Response**
```json
[
  {"_id":"550e8400-...", "name":"John", "age":30},
  {"_id":"660f9511-...", "name":"Ana", "age":25}
]
```

---

## Membership

| Operator | Request |
|---|---|
| `$in` | `['role'=>['$in'=>['admin','editor']]]` |
| `$nin` | `['role'=>['$nin'=>['banned']]]` |

**Response**
```json
[{"_id":"...", "role":"admin"}, {"_id":"...", "role":"editor"}]
```

> `$in`/`$nin` pada searchable encrypted field di-hash otomatis via blind index (fix v1.0).

---

## Logical

```php
// $and (implisit)
['age'=>['$gte'=>18], 'status'=>'active']

// $or – Request
['$or' => [
  ['role'=>'admin'],
  ['age'=>['$lt'=>18]]
]]

// $nor
['$nor' => [ ['status'=>'banned'], ['deleted'=>true] ]]

// $not
['age' => ['$not' => ['$gt'=>65]]]
```

**Response `$or` example**
```json
[
  {"_id":"...", "role":"admin", "age":30},
  {"_id":"...", "role":"user", "age":16}
]
```

---

## Element / Existence

| Operator | Request | Keterangan |
|---|---|---|
| `$exists` | `['email'=>['$exists'=>true]]` | field ada |
| `$type` | `['age'=>['$type'=>'int']]` | `string,int,float,bool,array,null` |

---

## String / Regex

```php
// $regex – Request
['name' => ['$regex' => '^John']]

// $like (SQL LIKE)
['name' => ['$like' => '%Doe%']]

// $startsWith / $endsWith
['email' => ['$endsWith' => '@example.com']]
```

**ReDoS protection:** max 500 chars, dangerous patterns (nested quantifiers, backref numerik, recursion, lookbehind) auto-downgrade ke literal match.

**Response**
```json
[{"_id":"550e8400-...", "name":"John Doe", "email":"john@example.com"}]
```

---

## Array operators

| Operator | Request |
|---|---|
| `$all` | `['tags'=>['$all'=>['php','db']]]` |
| `$size` | `['tags'=>['$size'=>3]]` |
| `$elemMatch` | `['scores'=>['$elemMatch'=>['$gte'=>80]]]` |

---

## Full-text / Fuzzy

```php
['name' => ['$fuzzy' => 'Jhon', '$distance' => 2]]
['bio' => ['$text' => 'database sqlite']]
```

**Response**
```json
[{"_id":"...", "name":"John", "score":0.92}]
```

---

## Custom / JS-like

```php
// $where – HANYA Closure, string ditolak (RCE prevention)
$users->find([
  'score' => ['$where' => fn($doc) => ($doc['score'] ?? 0) > 80]
]);

// $func – untuk transform value sebelum compare
['name' => ['$func' => fn($v) => strtolower($v) === 'john']]
```

**Blocked – Security – Request**
```php
// DITOLAK – RCE prevention
['x' => ['$where' => 'system']]
['x' => ['$func' => 'exec']]
```

**Response – Error**
```
ValidationException: The 'where' operator only accepts Closure objects (anonymous functions). String function names like 'system', 'exec', etc. are not allowed.
```

---

## Projection

```php
// include only – Request
$users->find(
  ['role'=>'admin'],
  ['name'=>1, 'email'=>1]
);
```

**Response**
```json
[{"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John", "email":"john@example.com"}]
```

```php
// exclude – Request
$users->find([], ['password'=>0]);
```

---

## Sorting + Pagination – contoh lengkap

**Request**
```php
$cursor = $products->find(
  ['price'=>['$gte'=>10, '$lte'=>100], 'stock'=>['$gt'=>0]],
  ['name'=>1, 'price'=>1, 'stock'=>1]
)->sort(['price'=>1])->skip(20)->limit(10);

$result = $cursor->toArray();
$count = $cursor->count(); // total match tanpa limit
```

**Response `$result`**
```json
[
  {"_id":"prod_abc123", "name":"USB Cable", "price":12.5, "stock": 45},
  {"_id":"prod_def456", "name":"Mouse Pad", "price":15.0, "stock": 12}
]
```

**Response `$count`**
```
87
```

---

## Encrypted Searchable Fields

```php
$users->setEncryptionKey($key, 'v2');
$users->setSearchableFields(['email'=>['hash'=>true]]);

$user = $users->findOne(['email'=>'john@example.com']);
```

**Response**
```json
{"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John Doe", "email":"john@example.com"}
```

**Stored di DB**
```json
{
  "_id":"550e8400-e29b-41d4-a716-446655440000",
  "encrypted_data":"...",
  "iv":"...",
  "tag":"...",
  "hmac":"...",
  "enc_v":2,
  "key_v":"v2-2026",
  "si_email":"hmac_sha256_abc123..."
}
```

Query value otomatis di-hash dengan HMAC blind index.

---

Lihat `examples/02-query-operators.php` untuk 60+ contoh query live.
