<?php

declare(strict_types=1);

namespace BangronDB\Traits;

/**
 * Trait for handling document encryption and decryption.
 * Provides AES-256-GCM authenticated encryption with per-collection or database-level keys.
 */
trait EncryptionTrait
{
    /**
     * Optional per-collection encryption key. If set, it takes precedence
     * over `Database->encryptionKey` for encoding/decoding stored documents.
     */
    protected ?string $encryptionKey = null;

    /**
     * Cached derived key to avoid expensive PBKDF2 recomputation.
     * Keyed by a hash of the original key + salt.
     *
     * @var array<string, string>
     */
    private static array $derivedKeyCache = [];

    /**
     * Encryption constants are declared in the Collection class
     * because PHP < 8.3 does not support constants in traits.
     */

    /**
     * Prevent encryption key from being exposed via var_dump/print_r.
     * This is called by Collection's __debugInfo.
     */
    protected function getDebugEncryptionInfo(): array
    {
        return [
            'encryptionEnabled' => $this->encryptionKey !== null,
            'encryptionKeyLength' => $this->encryptionKey !== null ? strlen($this->encryptionKey) : 0,
        ];
    }

    /**
     * Validate document nesting depth.
     *
     * @throws \RuntimeException
     */
    private function validateDocumentDepth(array $document, int $depth = 0): void
    {
        if ($depth > self::MAX_DOCUMENT_DEPTH) {
            throw new \RuntimeException(
                sprintf('Document nesting depth exceeds maximum allowed depth of %d', self::MAX_DOCUMENT_DEPTH)
            );
        }

        foreach ($document as $value) {
            if (is_array($value)) {
                $this->validateDocumentDepth($value, $depth + 1);
            }
        }
    }

    /**
     * Clear the derived key cache (e.g., when encryption key changes).
     */
    public static function clearDerivedKeyCache(): void
    {
        self::$derivedKeyCache = [];
    }

    /**
     * Set per-collection encryption key (overrides Database->encryptionKey when set).
     *
     * @throws \InvalidArgumentException If key is too weak
     */
    public function setEncryptionKey(?string $key): self
    {
        if ($key !== null) {
            $this->validateEncryptionKey($key);
        }

        $this->encryptionKey = $key;

        return $this;
    }

    /**
     * Validate encryption key strength.
     *
     * @throws \InvalidArgumentException If key does not meet security requirements
     */
    private function validateEncryptionKey(string $key): void
    {
        $length = strlen($key);

        if ($length < self::MIN_KEY_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Encryption key must be at least %d characters long. Provided key is only %d characters. ' .
                    'For AES-256 encryption, use a strong random key of at least 32 characters.',
                    self::MIN_KEY_LENGTH,
                    $length
                )
            );
        }

        if ($this->isWeakKey($key)) {
            throw new \InvalidArgumentException(
                'Encryption key appears to be weak. Avoid using simple patterns, repeated characters, ' .
                'or common phrases. Use a cryptographically secure random string.'
            );
        }
    }

    /**
     * Check if a key exhibits common weak patterns.
     */
    private function isWeakKey(string $key): bool
    {
        if (preg_match('/^(.)\1+$/', $key)) {
            return true;
        }

        if (preg_match('/^(0123456789|abcdefghij|qwertyuiop){3,}/', strtolower($key))) {
            return true;
        }

        $uniqueChars = count(array_unique(str_split($key)));
        $totalChars = strlen($key);

        return ($uniqueChars / $totalChars) < 0.25;
    }

    /**
     * Check if collection has encryption enabled.
     */
    public function isEncrypted(): bool
    {
        return $this->encryptionKey !== null;
    }

    /**
     * Maximum document size in bytes (default 10MB).
     */
    protected int $maxDocumentSize = 10485760;

    /**
     * Set maximum document size in bytes.
     */
    public function setMaxDocumentSize(int $bytes): self
    {
        $this->maxDocumentSize = $bytes;
        return $this;
    }

    /**
     * Get maximum document size.
     */
    public function getMaxDocumentSize(): int
    {
        return $this->maxDocumentSize;
    }

    /**
     * Encode a document for storage.
     */
    protected function encodeStored(array $doc): string
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;

        if (empty($key)) {
            return $this->encodeJson($doc);
        }

        return $this->encodeEncrypted($doc, $key);
    }

    /**
     * Encode document as JSON (no encryption).
     */
    private function encodeJson(array $doc): string
    {
        $this->validateDocumentDepth($doc);

        $json = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        if ($this->maxDocumentSize > 0 && strlen($json) > $this->maxDocumentSize) {
            throw new \RuntimeException(
                sprintf('Document size (%d bytes) exceeds maximum allowed size (%d bytes)', strlen($json), $this->maxDocumentSize)
            );
        }

        return $json;
    }

    /**
     * Encode document with AES-256-GCM encryption.
     */
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
        ];

        return $this->encodeJson($store);
    }

    /**
     * Get or compute the PBKDF2-derived encryption key.
     */
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

    /**
     * Resolve the PBKDF2 salt for the current database context.
     */
    private function resolveKdfSalt(): string
    {
        return isset($this->database)
            ? $this->database->getEncryptionSalt()
            : self::LEGACY_PBKDF2_SALT;
    }

    /**
     * Encrypt data using AES-256-GCM.
     */
    private function encryptData(string $plain, string $key): array
    {
        $rawKey = $this->getDerivedKey($key, $this->resolveKdfSalt());
        $iv = \random_bytes(16);
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

    /**
     * Decode a stored document string from the database into an array.
     */
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

    /**
     * Check if decoded data represents an encrypted document.
     */
    private function isEncryptedFormat(array $decoded): bool
    {
        return \is_array($decoded) && isset($decoded['encrypted_data']) && isset($decoded['tag']);
    }

    /**
     * Decrypt an encrypted document.
     */
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

    /**
     * Decrypt encrypted data using the provided key.
     */
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

    /**
     * Low-level encrypt helper.
     */
    private function _encryptPlaintext(string $plain): array
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            throw new \RuntimeException('No encryption key available');
        }

        return $this->encryptData($plain, $key);
    }

    /**
     * Low-level decrypt helper that accepts encrypted_data and iv (base64).
     */
    private function _decryptToPlaintext(string $encryptedBase64, string $ivBase64, ?string $tagBase64 = null): ?string
    {
        $key = $this->encryptionKey ?? $this->database->getEncryptionKey() ?? null;
        if (empty($key)) {
            return null;
        }

        return $this->decryptDataString($encryptedBase64, $ivBase64, $key, $tagBase64);
    }

    /**
     * Decrypt data using encrypted string and IV.
     */
    private function decryptDataString(string $encryptedBase64, string $ivBase64, string $key, ?string $tagBase64 = null): ?string
    {
        $cipher = \base64_decode($encryptedBase64);
        $iv = \base64_decode($ivBase64);
        $tag = $tagBase64 !== null ? \base64_decode($tagBase64) : '';
        if ($cipher === false || $iv === false) {
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
}
