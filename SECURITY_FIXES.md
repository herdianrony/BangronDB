# BangronDB — Security Audit Results & Fixes

Audit of the crypto/query layer requested by the Arcade Living app audit
(`AUDIT_REPORT.md` items M1/M2/M3). All 315 unit tests pass and PHPStan reports
no errors after these changes.

## Audit verdict

| Item | Area | Verdict |
|------|------|---------|
| **M2** | AES-256-GCM key & nonce handling | ✅ **Already strong — no change needed** |
| **M3** | SQL / NoSQL injection in the query layer | ✅ **Already safe — no change needed** |
| **M1** | Searchable email "hash" | ⚠️ **Was a real weakness — FIXED** |
| (bonus) | `$in`/`$nin` on hashed searchable fields | 🐛 **Pre-existing correctness bug — FIXED** |

### M2 — Encryption (verified strong)
`src/Traits/EncryptionTrait.php`
- Key is **derived with PBKDF2-SHA256, 100k iterations → 32 bytes**, so a 32-char
  passphrase becomes a genuine 256-bit key (the audit's "32 chars ≠ 32 bytes"
  concern does not apply).
- **Fresh random IV per encryption** (`random_bytes(16)`) — verified empirically
  that two records never share an IV (no GCM nonce reuse).
- **GCM auth tag stored and required**, plus an extra **HMAC-SHA256 over
  cipher+iv verified with `hash_equals`** (constant-time) before decryption.
  Tampering with the ciphertext is rejected (decrypt returns `null`).
- Per-database random KDF salt, persisted; legacy-salt fallback for old data.
- Minor (non-blocking) notes: GCM IV is 16 bytes (12 is the conventional size,
  but 16 is accepted and safe with random generation); no explicit key-version
  field for rotation.

### M3 — Query layer (verified safe)
`src/Traits/QueryBuilderTrait.php`, `src/Security/FieldValidator.php`, `src/Database.php`
- Field names are whitelisted (`FieldValidator::validateFieldName`: only
  `[A-Za-z0-9_.-]`, explicit forbidden-char list incl. quotes/`;`/backtick).
- All **values are bound as `?` placeholders** — never concatenated.
- Identifiers quoted via `quoteIdentifier()` (regex-checked, backtick-doubled).
- Mongo-style operators dispatched through a `match()` of known ops, so
  operator-injection (e.g. `{"$ne": null}` style) cannot reach raw SQL.

## Fixes applied

### M1 — Keyed HMAC blind index (replaces unkeyed SHA-256)
**Problem:** `si_*` columns for hashed searchable fields stored a plain,
unsalted `SHA-256(strtolower(value))`. For low-entropy values like emails this
is brute-forceable / rainbow-table-able if a `.bangron` file leaks, and the same
value hashes identically across databases (correlation).

**Fix** (`src/Traits/SearchableFieldsTrait.php`, `src/Traits/QueryBuilderTrait.php`):
- New `hashSearchableValue()` centralizes index hashing. When an encryption key
  is present it returns `HMAC-SHA256(value, searchKey)` where `searchKey` is
  derived from the encryption key via PBKDF2 with a dedicated `searchindex:`
  salt (domain-separated from the data key, cached).
- Without an encryption key it falls back to the legacy plain SHA-256
  (backward-compatible; those values aren't secret).
- All write- and query-path hashing now routes through this one method
  (`_computeSearchIndexValues`, `buildEqualityCondition`,
  `buildComparisonCondition`, `buildInCondition`).
- Added `rehashSearchableField(string $field)` to migrate existing rows from the
  old scheme to the keyed index.

### `$in`/`$nin` on hashed searchable fields (correctness/bypass bug)
**Problem:** `_canTranslateToJsonWhere()` excluded `$in`/`$nin` on searchable
fields from the SQL fast-path, sending them to the in-memory matcher — which
compared the **plaintext** query value against the **hashed** stored value, so
those queries silently returned **nothing**.

**Fix:** Route `$in`/`$nin` on **hashed** searchable fields through the SQL
fast-path (where `buildInCondition` hashes each value with the blind index).
Non-hashed fields (which may store comma-joined arrays) keep the in-memory
fallback so array-membership semantics still work.

## Tests
- `tests/BlindIndexTest.php` (new, 4 tests): index is keyed (not plain SHA-256),
  equality + `$in` search still resolve, different keys yield different indexes
  (no cross-DB correlation), non-encrypted collections keep plain hashing.
- Full suite: **315 tests / 897 assertions, OK**. PHPStan: **no errors**.

## Action for the consuming app
After deploying this version, run a one-time migration for encrypted
collections that use hashed searchable fields (e.g. users):

```php
$users->setEncryptionKey($key);
$users->setSearchableFields(['email' => ['hash' => true]]);
$users->rehashSearchableField('email'); // upgrade old SHA-256 → keyed HMAC
```

Old (plain-SHA-256) `si_email` values won't match keyed-index lookups until
re-hashed, so existing user logins/searches require this migration once.
