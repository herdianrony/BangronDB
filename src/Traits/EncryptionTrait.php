<?php

namespace BangronDB\Traits;

/**
 * Trait for handling document encryption and decryption.
 * Provides AES-256-CBC encryption with per-collection or database-level keys.
 */
trait EncryptionTrait
{
    /**
     * Optional per-collection encryption key. If set, it takes precedence
     * over `Database->encryptionKey` for encoding/decoding stored documents.
     */
    protected ?string $encryptionKey = null;

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
     * Encode a document for storage. If encryption key is set, the
     * document (except `_id`) will be encrypted with AES-256-CBC and stored as
     * an object: { _id, encrypted_data, iv }.
     *
     * Returns JSON string ready to be stored in `document` column.
     */
    protected function encodeStored(array $doc): string
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;

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
        $json = \json_encode($doc, JSON_UNESCAPED_UNICODE);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('JSON encode error: ' . json_last_error_msg());
        }

        return $json;
    }

    /**
     * Encode document with AES-256-CBC encryption.
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
        ];

        return $this->encodeJson($store);
    }

    /**
     * Encrypt data using AES-256-CBC.
     */
    private function encryptData(string $plain, string $key): array
    {
        $rawKey = \hash('sha256', $key, true);
        $ivLen = openssl_cipher_iv_length('AES-256-CBC');
        $iv = openssl_random_pseudo_bytes($ivLen);
        $cipher = openssl_encrypt($plain, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);

        return [
            'encrypted_data' => base64_encode($cipher),
            'iv' => base64_encode($iv),
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
        return is_array($decoded) && isset($decoded['encrypted_data']);
    }

    /**
     * Decrypt an encrypted document.
     */
    private function decryptDocument(array $decoded): ?array
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
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
        $rawKey = hash('sha256', $this->encryptionKey ?? $this->database->encryptionKey ?? '', true);

        $cipher = base64_decode($decoded['encrypted_data'] ?? '');
        $iv = base64_decode($decoded['iv'] ?? '');
        if ($cipher === false || $iv === false) {
            return null;
        }

        return openssl_decrypt($cipher, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Low-level encrypt helper that returns an array with base64-encoded
     * `encrypted_data` and `iv`. Uses the collection key if present else
     * falls back to Database key.
     */
    private function _encryptPlaintext(string $plain): array
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
        if (empty($key)) {
            throw new \RuntimeException('No encryption key available');
        }

        return $this->encryptData($plain, $key);
    }

    /**
     * Low-level decrypt helper that accepts encrypted_data and iv (base64)
     * and returns the decrypted plaintext or null on failure.
     */
    private function _decryptToPlaintext(string $encryptedBase64, string $ivBase64): ?string
    {
        $key = $this->encryptionKey ?? $this->database->encryptionKey ?? null;
        if (empty($key)) {
            return null;
        }

        return $this->decryptDataString($encryptedBase64, $ivBase64, $key);
    }

    /**
     * Decrypt data using encrypted string and IV.
     */
    private function decryptDataString(string $encryptedBase64, string $ivBase64, string $key): ?string
    {
        $rawKey = hash('sha256', $key, true);
        $cipher = base64_decode($encryptedBase64);
        $iv = base64_decode($ivBase64);
        if ($cipher === false || $iv === false) {
            return null;
        }

        $plain = openssl_decrypt($cipher, 'AES-256-CBC', $rawKey, OPENSSL_RAW_DATA, $iv);

        return $plain === false ? null : $plain;
    }
}
