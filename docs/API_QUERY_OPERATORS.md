# Query Operators – BangronDB v1.1.0

## Comparison
`$eq, $ne, $gt, $gte, $lt, $lte`
```php
$users->find(['age'=>['$gte'=>18,'$lt'=>65]])
// Response: [{"_id":"...","name":"John","age":30}]
```

## Membership
`$in, $nin`
```php
['role'=>['$in'=>['admin','editor']]]
```
`$in`/`$nin` pada encrypted searchable field di-hash otomatis.

## Logical
`$or, $nor, $not`
```php
['$or'=>[['role'=>'admin'],['age'=>['$lt'=>18]]]]
```

## Element
`$exists, $type`
```php
['email'=>['$exists'=>true]]
['age'=>['$type'=>'int']]
```

## String / Regex
`$regex, $like, $startsWith, $endsWith`
```php
['name'=>['$regex'=>'^John']]
```
ReDoS protection: max 500 chars, dangerous patterns auto-downgrade.

## Array
`$all, $size, $elemMatch`
```php
['tags'=>['$all'=>['php','db']]]
```

## Custom – Closure only (RCE prevention)
```php
['score'=>['$where'=>fn($doc)=>($doc['score']??0)>80]]
['name'=>['$func'=>fn($v)=>strtolower($v)==='john']]
```
Blocked:
```php
['x'=>['$where'=>'system']] // ValidationException
```

## Projection
```php
$users->find(['role'=>'admin'], ['name'=>1,'email'=>1])
// [{"_id":"...","name":"John","email":"john@example.com"}]
```

## Encrypted Searchable
```php
$users->setEncryptionKey($key,'v2');
$users->setSearchableFields(['email'=>['hash'=>true]]);
$users->findOne(['email'=>'john@example.com']);
// blind index: si_email = HMAC-SHA256(email, searchKey)
```

Full examples: `examples/02-query-operators.php`
