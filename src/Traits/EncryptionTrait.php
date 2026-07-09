<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for handling document encryption and decryption.
 * Provides AES-256-GCM authenticated encryption with per-collection or database-level keys.
 */
trait EncryptionTrait
{
    protected ?string $encryptionKey = null;
    protected ?string $encryptionKeyVersion = null;
    /** @var array<string, string> cache of PBKDF2-derived keys keyed by sha256(key+salt) */
    private static array $derivedKeyCache = [];

    protected function getDebugEncryptionInfo(): array
    {
        return [
            'encryptionEnabled' => $this->encryptionKey !== null,
            'encryptionKeyLength' => $this->encryptionKey !== null ? strlen($this->encryptionKey) : 0,
            'keyVersion' => $this->encryptionKeyVersion,
        ];
    }

    private function validateDocumentDepth(array $document, int $depth = 0): void
    {
        if ($depth > self::MAX_DOCUMENT_DEPTH) {
            throw new \RuntimeException(sprintf('Document nesting depth exceeds maximum allowed depth of %d', self::MAX_DOCUMENT_DEPTH));
        }
        foreach ($document as $value) {
            if (is_array($value)) {
                $this->validateDocumentDepth($value, $depth + 1);
            }
        }
    }

    public static function clearDerivedKeyCache(): void
    {
        self::$derivedKeyCache = [];
    }

    public function setEncryptionKey(?string $key, ?string $keyVersion = null): self
    {
        if ($key !== null) {
            $this->validateEncryptionKey($key);
        }
        $this->encryptionKey = $key;
        // Consistent string casting – mirrors Database::__construct() behaviour
        $this->encryptionKeyVersion = $keyVersion === null ? null : (string) $keyVersion;
        // Invalidate derived-key cache so the new key takes effect immediately
        // (mirrors Database::setEncryptionKey() which calls Collection::clearDerivedKeyCache())
        self::clearDerivedKeyCache();
        return $this;
    }

    public function getEncryptionKeyVersion(): ?string
    {
        return $this->encryptionKeyVersion;
    }

    /**
     * Set only the encryption key version without changing the key material.
     * Mirrors Database::setEncryptionKeyVersion() for API consistency.
     */
    public function setEncryptionKeyVersion(?string $version): self
    {
        $this->encryptionKeyVersion = $version === null ? null : (string) $version;
        return $this;
    }

    private function validateEncryptionKey(string $key): void
    {
        $length = strlen($key);
        if ($length < self::MIN_KEY_LENGTH) {
            throw new \InvalidArgumentException(sprintf('Encryption key must be at least %d characters long. Provided key is only %d characters. For AES-256 encryption, use a strong random key of at least 32 characters.', self::MIN_KEY_LENGTH, $length));
        }
        if ($this->isWeakKey($key)) {
            throw new \InvalidArgumentException('Encryption key appears to be weak. Avoid using simple patterns, repeated characters, or common phrases. Use a cryptographically secure random string.');
        }
    }

    private function isWeakKey(string $key): bool
    {
        if (preg_match('/^(.)\\1+$/', $key)) {
            return true;
        }
        if (preg_match('/^(0123456789|abcdefghij|qwertyuiop){3,}/', strtolower($key))) {
            return true;
        }
        $uniqueChars = count(array_unique(str_split($key)));
        $totalChars = strlen($key);
        return ($uniqueChars / $totalChars) < 0.25;
    }

    public function isEncrypted(): bool
    {
        return $this->encryptionKey !== null;
    }

    protected int $maxDocumentSize = 10485760;

    public function setMaxDocumentSize(int $bytes): self
    {
        $this->maxDocumentSize = $bytes;
        return $this;
    }

    public function getMaxDocumentSize(): int
    {
        return $this->maxDocumentSize;
    }

    protected function encodeStored(array $doc): string
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            return $this->encodeJson($doc);
        }
        return $this->encodeEncrypted($doc, $key);
    }

    private function encodeJson(array $doc): string
    {
        $this->validateDocumentDepth($doc);
        $json = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
        }
        if ($this->maxDocumentSize > 0 && strlen($json) > $this->maxDocumentSize) {
            throw new \RuntimeException(sprintf('Document size (%d bytes) exceeds maximum allowed size (%d bytes)', strlen($json), $this->maxDocumentSize));
        }
        return $json;
    }

    private function encodeEncrypted(array $doc, string $key): string
    {
        $id = $doc['_id'] ?? null;
        $payload = $doc;
        if ($id !== null) {
            unset($payload['_id']);
        }
        $plain = $this->encodeJson($payload);
        $encryptionData = $this->encryptData($plain, $key);
        $store = [
            '_id' => $id,
            'encrypted_data' => $encryptionData['encrypted_data'],
            'iv' => $encryptionData['iv'],
            'tag' => $encryptionData['tag'],
            'hmac' => $encryptionData['hmac'],
            'enc_v' => self::ENCRYPTION_VERSION,
        ];
        $keyVersion = $this->encryptionKeyVersion ?? ($this->database->getEncryptionKeyVersion() ?? null);
        if ($keyVersion !== null) {
            $store['key_v'] = $keyVersion;
        }
        return $this->encodeJson($store);
    }

    private function getDerivedKey(string $key, string $salt): string
    {
        $cacheKey = hash('sha256', $key . "\0" . $salt);
        if (isset(self::$derivedKeyCache[$cacheKey])) {
            return self::$derivedKeyCache[$cacheKey];
        }
        $rawKey = \hash_pbkdf2('sha256', $key, $salt, 100000, 32, true);
        if (count(self::$derivedKeyCache) >= self::MAX_DERIVED_KEY_CACHE_SIZE) {
            array_shift(self::$derivedKeyCache);
        }
        self::$derivedKeyCache[$cacheKey] = $rawKey;
        return $rawKey;
    }

    private function resolveKdfSalt(): string
    {
        return isset($this->database) ? $this->database->getEncryptionSalt() : self::LEGACY_PBKDF2_SALT;
    }

    private function encryptData(string $plain, string $key): array
    {
        $rawKey = $this->getDerivedKey($key, $this->resolveKdfSalt());
        $iv = \random_bytes(12);
        $tag = '';
        $cipher = \openssl_encrypt($plain, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
        $hmac = \hash_hmac('sha256', $cipher . $iv, $rawKey);
        return [
            'encrypted_data' => \base64_encode($cipher),
            'iv' => \base64_encode($iv),
            'tag' => \base64_encode($tag),
            'hmac' => $hmac,
        ];
    }

    public function decodeStored(string $stored): ?array
    {
        $decoded = json_decode($stored, true);
        if ($decoded === null) {
            return null;
        }
        if (!$this->isEncryptedFormat($decoded)) {
            return $decoded;
        }
        return $this->decryptDocument($decoded);
    }

    private function isEncryptedFormat(array $decoded): bool
    {
        return \is_array($decoded) && isset($decoded['encrypted_data']) && isset($decoded['tag']);
    }

    private function decryptDocument(array $decoded): ?array
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            return null;
        }
        $decryptionResult = $this->decryptData($decoded);
        if ($decryptionResult === null) {
            return null;
        }
        $payload = json_decode($decryptionResult, true);
        if (!is_array($payload)) {
            return null;
        }
        if (isset($decoded['_id'])) {
            $payload['_id'] = $decoded['_id'];
        }
        return $payload;
    }

    private function decryptData(array $decoded): ?string
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            return null;
        }
        $cipher = \base64_decode($decoded['encrypted_data'] ?? '');
        $iv = \base64_decode($decoded['iv'] ?? '');
        $tag = \base64_decode($decoded['tag'] ?? '');
        if ($cipher === false || $iv === false || $tag === false) {
            return null;
        }
        $ivLen = strlen($iv);
        if ($ivLen !== 12 && $ivLen !== 16) {
            return null;
        }
        $saltCandidates = [$this->resolveKdfSalt()];
        if ($saltCandidates[0] !== self::LEGACY_PBKDF2_SALT) {
            $saltCandidates[] = self::LEGACY_PBKDF2_SALT;
        }
        foreach ($saltCandidates as $salt) {
            $rawKey = $this->getDerivedKey($key, $salt);
            if (isset($decoded['hmac'])) {
                $expectedHmac = \hash_hmac('sha256', $cipher . $iv, $rawKey);
                if (!\hash_equals($expectedHmac, $decoded['hmac'])) {
                    continue;
                }
            }
            $plain = \openssl_decrypt($cipher, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain !== false) {
                return $plain;
            }
        }
        return null;
    }

    private function _encryptPlaintext(string $plain): array
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            throw new \RuntimeException('No encryption key available');
        }
        return $this->encryptData($plain, $key);
    }

    private function _decryptToPlaintext(string $encryptedBase64, string $ivBase64, ?string $tagBase64 = null): ?string
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            return null;
        }
        return $this->decryptDataString($encryptedBase64, $ivBase64, $key, $tagBase64);
    }

    private function decryptDataString(string $encryptedBase64, string $ivBase64, string $key, ?string $tagBase64 = null): ?string
    {
        $cipher = \base64_decode($encryptedBase64);
        $iv = \base64_decode($ivBase64);
        $tag = $tagBase64 !== null ? \base64_decode($tagBase64) : '';
        if ($cipher === false || $iv === false) {
            return null;
        }
        $ivLen = strlen($iv);
        if ($ivLen !== 12 && $ivLen !== 16) {
            return null;
        }
        $salts = [$this->resolveKdfSalt()];
        if ($salts[0] !== self::LEGACY_PBKDF2_SALT) {
            $salts[] = self::LEGACY_PBKDF2_SALT;
        }
        foreach ($salts as $salt) {
            $rawKey = $this->getDerivedKey($key, $salt);
            $plain = \openssl_decrypt($cipher, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
            if ($plain !== false) {
                return $plain;
            }
        }
        return null;
    }

    public function rotateEncryptionKey(string $newKey, ?string $newKeyVersion = null): int
    {
        $currentKey = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($currentKey)) {
            throw new \RuntimeException('Current encryption key not set - cannot rotate');
        }
        $this->validateEncryptionKey($newKey);

        // 1. Fetch & decrypt ALL documents with the CURRENT (old) key BEFORE switching.
        //    This is critical: once we call setEncryptionKey($newKey), decodeStored()
        //    would use the new key and fail to decrypt data that is still encrypted
        //    with the old key in the database (causing data loss).
        $documents = [];
        foreach ($this->find([]) as $doc) {
            if (is_array($doc) && isset($doc['_id'])) {
                $documents[] = $doc;
            }
        }

        // 2. Switch to the new key (any future read/write uses the new key).
        $this->setEncryptionKey($newKey, $newKeyVersion);

        // 3. Re-encrypt each decrypted document with the new key.
        //    If an exception occurs mid-rotation, the already-rotated documents
        //    will be readable with the new key, and un-rotated ones with the old key.
        //    Callers can retry rotateEncryptionKey() after verifying the old key is
        //    still configured for fallback decrypt (decryptData tries both salts).
        $rotated = 0;
        foreach ($documents as $doc) {
            $this->update(['_id' => $doc['_id']], ['$set' => $doc]);
            $rotated++;
        }

        return $rotated;
    }

    public function reencryptAll(): int
    {
        $count = 0;
        foreach ($this->find([]) as $doc) {
            $id = $doc['_id'] ?? null;
            if ($id) {
                $this->update(['_id' => $id], ['$set' => $doc]);
                $count++;
            }
        }
        return $count;
    }
}
