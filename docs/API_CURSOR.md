# Cursor API – BangronDB v1.1.0

`BangronDB\Cursor` – returned by `Collection::find()`

## sort(array $sort): self
`$users->find()->sort(['created_at'=>-1,'name'=>1])`

## limit(int $limit): self
## skip(int $skip): self
Pagination:
```php
$users->find(['role'=>'user'])->sort(['created_at'=>-1])->skip(20)->limit(10)->toArray();
```

## toArray(): array
## toJson(int $options = 0): string
## toArraySafe(): array

## count(): int

## each(callable $callback): void

## Iterator
`foreach ($cursor as $doc) { ... }`
`rewind(), current(), key(), next(), valid()`

## Debug
`getSql(): string`
`getParams(): array`
