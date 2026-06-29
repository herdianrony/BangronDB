# Cursor API – BangronDB v1.2.0

`BangronDB\Cursor` – Returned by `Collection::find()`

Implements `Iterator`, `Countable`

---

## sort

```php
public function sort(array $sort): self
```

**Request**
```php
$users->find()->sort(['created_at' => -1, 'name' => 1])
```
`-1 = DESC`, `1 = ASC`

**Response:** `$this` (fluent)

---

## limit / skip

```php
public function limit(int $limit): self
public function skip(int $skip): self
```

**Pagination – Request**
```php
$page = 2; $perPage = 20;
$rows = $users->find(['role'=>'user'])
  ->sort(['created_at'=>-1])
  ->skip(($page-1)*$perPage)
  ->limit($perPage)
  ->toArray();
```

**Response**
```json
[
  {"_id":"550e8400-e29b-41d4-a716-446655440021", "name":"User 21", "role":"user"},
  {"_id":"550e8400-e29b-41d4-a716-446655440022", "name":"User 22", "role":"user"}
]
```

---

## toArray / toJson

```php
public function toArray(): array
public function toJson(int $options = 0): string
public function toArraySafe(): array  // skip dokumen yang gagal decrypt
```

**Example Request**
```php
$cursor = $users->find(['role'=>'admin']);
$array = $cursor->toArray();
$json = $cursor->toJson(JSON_PRETTY_PRINT);
```

**Response `toArray()`**
```json
[
  {"_id":"550e8400-e29b-41d4-a716-446655440000", "name":"John", "role":"admin"},
  {"_id":"660f9511-f39c-52e5-b827-557766551111", "name":"Ana", "role":"admin"}
]
```

**Response `toJson()`**
```json
[
    {
        "_id": "550e8400-e29b-41d4-a716-446655440000",
        "name": "John",
        "role": "admin"
    }
]
```

---

## count

```php
public function count(): int
```
Count dengan filter cursor saat ini (tanpa limit/skip).

**Request**
```php
$count = $users->find(['role'=>'user'])->count();
```

**Response**
```
87
```

---

## each

```php
public function each(callable $callback): void
```

**Example Request**
```php
$users->find()->each(function($doc){
  echo $doc['name']."\n";
});
```
**Output**
```
John
Ana
Bob
```

---

## Iterator

```php
$cursor = $users->find();
foreach ($cursor as $doc) {
  // $doc = ['_id'=>..., 'name'=>...]
}
```

Methods: `rewind(), current(), key(), next(), valid()`

---

## getSql / getParams

```php
public function getSql(): string
public function getParams(): array
```
Untuk debugging query yang di-generate.

**Example Request**
```php
$cursor = $users->find(['role'=>'user'])->sort(['created_at'=>-1])->limit(20)->skip(20);
$sql = $cursor->getSql();
$params = $cursor->getParams();
```

**Response `getSql()`**
```sql
SELECT document FROM `users` WHERE json_extract(document, '$.role') = ? ORDER BY json_extract(document, '$.created_at') DESC LIMIT 20 OFFSET 20
```

**Response `getParams()`**
```json
["user"]
```
