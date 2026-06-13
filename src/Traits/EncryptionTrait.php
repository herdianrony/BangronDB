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
     * Maximum number of derived keys to cache.
     */
    private const MAX_DERIVED_KEY_CACHE_SIZE = 16;

    /**
     * Maximum document nesting depth to prevent stack overflow.
     */
    private const MAX_DOCUMENT_DEPTH = 64;

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
     * @param array $document Document to validate
     * @param int $depth Current depth
     * @throws \RuntimeException If depth exceeds limit
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
     * Minimum encryption key length in characters.
     */
    private const MIN_KEY_LENGTH = 32;

    /**
     * Set per-collection encryption key (overrides Database->encryptionKey when set).
     *
     * @param string|null $key Encryption key (minimum 32 characters recommended for AES-256)
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
     * @param string $key Encryption key to validate
     *
     * @throws \InvalidArgumentException If key does not meet security requirements
     */
    private function validateEncryptionKey(string $key): void
    {
        $length = strlen($key);

        // Check minimum length
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

        // Check for obviously weak keys
        if ($this->isWeakKey($key)) {
            throw new \InvalidArgumentException(
                'Encryption key appears to be weak. Avoid using simple patterns, repeated characters, ' .
                'or common phrases. Use a cryptographically secure random string.'
            );
        }
    }

    /**
     * Check if a key exhibits common weak patterns.
     *
     * @param string $key Key to check
     *
     * @return bool True if key appears weak
     */
    private function isWeakKey(string $key): bool
    {
        // Check for repeated characters (e.g., "aaaaaaaaaaaa...")
        if (preg_match('/^(.)\1+$/', $key)) {
            return true;
        }

        // Check for simple sequential patterns
        if (preg_match('/^(0123456789|abcdefghij|qwertyuiop){3,}/', strtolower($key))) {
            return true;
        }

        // Check entropy - a strong key should have reasonable character variety
        $uniqueChars = count(array_unique(str_split($key)));
        $totalChars = strlen($key);

        // If less than 25% unique characters, likely weak
        if ($uniqueChars / $totalChars < 0.25) {
            return true;
        }

        return false;
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
     * Prevents storing excessively large documents that could cause memory issues.
     */
    protected int $maxDocumentSize = 10485760;

    /**
     * Set maximum document size in bytes.
     *
     * @param int $bytes Maximum document size (0 = unlimited)
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
     * Encode a document for storage. If encryption key is set, the
     * document (except `_id`) will be encrypted with AES-256-GCM and stored as
     * an object: { _id, encrypted_data, iv, tag }.
     *
     * Returns JSON string ready to be stored in `document` column.
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
     * Validates document size before encoding.
     */
    private function encodeJson(array $doc): string
    {
        // Validate document depth
        $this->validateDocumentDepth($doc);

        $json = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        // Validate document size
        if ($this->maxDocumentSize > 0 && strlen($json) > $this->maxDocumentSize) {
            throw new \RuntimeException(
                sprintf('Document size (%d bytes) exceeds maximum allowed size (%d bytes)',
                    strlen($json), $this->maxDocumentSize)
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
        ];

        return $this->encodeJson($store);
    }

    /**
     * Get or compute the PBKDF2-derived encryption key.
     * Caches the derived key to avoid expensive recomputation on every operation.
     *
     * @param string $key The original encryption key
     *
     * @return string The 32-byte derived key
     */
    private function getDerivedKey(string $key): string
    {
        $cacheKey = md5($key);

        if (isset(self::$derivedKeyCache[$cacheKey])) {
            return self::$derivedKeyCache[$cacheKey];
        }

        $rawKey = \hash_pbkdf2('sha256', $key, 'bangrondb_encryption_salt', 100000, 32, true);

        // Limit cache size to prevent memory leaks
        if (count(self::$derivedKeyCache) >= self::MAX_DERIVED_KEY_CACHE_SIZE) {
            array_shift(self::$derivedKeyCache);
        }

        self::$derivedKeyCache[$cacheKey] = $rawKey;

        return $rawKey;
    }

    /**
     * Encrypt data using AES-256-GCM.
     */
    private function encryptData(string $plain, string $key): array
    {
        $rawKey = $this->getDerivedKey($key);
        $ivLen = 16; // AES-256-GCM always uses 16-byte IV
        $iv = \random_bytes($ivLen);
        $tag = '';
        $cipher = \openssl_encrypt($plain, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);

        // Generate HMAC for additional integrity verification
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
     * If the stored value represents an encrypted payload and the database
     * has an `encryptionKey` configured, it will attempt to decrypt and
     * return the original document (including `_id`). Returns null on
     * parse/decrypt failure.
     */
    public function decodeStored(string $stored): ?array
    {
        $decoded = json_decode($stored, true);
        if ($decoded === null) {
            return null;
        }

        // If not encrypted format, assume it's the raw document
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
            // Cannot decrypt without key
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

        // Restore _id if present in wrapper
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

        $rawKey = $this->getDerivedKey($key);
        $cipher = \base64_decode($decoded['encrypted_data'] ?? '');
        $iv = \base64_decode($decoded['iv'] ?? '');
        $tag = \base64_decode($decoded['tag'] ?? '');
        if ($cipher === false || $iv === false || $tag === false) {
            return null;
        }

        // Verify HMAC if present (for tamper detection)
        if (isset($decoded['hmac'])) {
            $expectedHmac = \hash_hmac('sha256', $cipher . $iv, $rawKey);
            if (!\hash_equals($expectedHmac, $decoded['hmac'])) {
                return null; // Data has been tampered with
            }
        }

        return \openssl_decrypt($cipher, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
    }

    /**
     * Low-level encrypt helper that returns an array with base64-encoded
     * `encrypted_data` and `iv`. Uses the collection key if present else
     * falls back to Database key.
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
     * Low-level decrypt helper that accepts encrypted_data and iv (base64)
     * and returns the decrypted plaintext or null on failure.
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
        $rawKey = $this->getDerivedKey($key);
        $cipher = \base64_decode($encryptedBase64);
        $iv = \base64_decode($ivBase64);
        $tag = $tagBase64 !== null ? \base64_decode($tagBase64) : '';
        if ($cipher === false || $iv === false) {
            return null;
        }

        $plain = \openssl_decrypt($cipher, 'aes-256-gcm', $rawKey, OPENSSL_RAW_DATA, $iv, $tag);
        return $plain === false ? null : $plain;
    }
}
