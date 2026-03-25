<?php

namespace App\Services;

class EncryptionService
{
    private $systemDb;
    private $config;
    private $masterKey;
    private $keyRotationSchedule;

    public function __construct()
    {
        $this->systemDb = (new SystemService())->systemDb();
        $this->config = include __DIR__.'/../config/security.php';
        $this->keyRotationSchedule = $this->config['encryption']['key_rotation_schedule'] ?? 30; // days
        $this->initializeMasterKey();
    }

    /**
     * Initialize master encryption key.
     */
    private function initializeMasterKey(): void
    {
        try {
            // Check if master key exists
            $masterKey = $this->systemDb->encryption_keys->findOne([
                'type' => 'master',
                'status' => 'active',
            ]);

            if (!$masterKey) {
                // Generate new master key
                $this->generateMasterKey();
            } else {
                $this->masterKey = $masterKey['key_material'];
            }
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to initialize master key: '.$e->getMessage());
            throw new Exception('Failed to initialize encryption system');
        }
    }

    /**
     * Generate master encryption key.
     */
    private function generateMasterKey(): void
    {
        try {
            // Use sodium for key generation if available
            if (extension_loaded('sodium')) {
                $key = sodium_crypto_secretbox_keygen();
            } else {
                // Fallback to OpenSSL
                $key = openssl_random_pseudo_bytes(32);
            }

            $keyData = [
                'type' => 'master',
                'key_id' => 'master_'.bin2hex(random_bytes(16)),
                'key_material' => base64_encode($key),
                'key_version' => 1,
                'algorithm' => extension_loaded('sodium') ? 'XSalsa20-Poly1305' : 'AES-256-GCM',
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => 'system',
                'rotation_count' => 0,
                'last_rotated' => null,
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + ($this->keyRotationSchedule * 24 * 60 * 60)) * 1000),
                'rotation_schedule' => $this->keyRotationSchedule,
                'description' => 'Master encryption key for all encrypted data',
            ];

            $this->systemDb->encryption_keys->insertOne($keyData);
            $this->masterKey = $keyData['key_material'];
        } catch (Exception $e) {
            error_log('Failed to generate master key: '.$e->getMessage());
            throw new Exception('Failed to generate encryption key');
        }
    }

    /**
     * Encrypt data using field-level encryption.
     */
    public function encryptField(string $data, string $collection, string $field, ?string $keyId = null): array
    {
        try {
            // Get appropriate key for this field
            $encryptionKey = $this->getEncryptionKey($collection, $field, $keyId);

            // Encrypt the data
            $encryptedData = $this->encryptData($data, $encryptionKey['key_material']);

            // Log encryption event
            $this->logEncryptionEvent($collection, $field, 'encrypt', strlen($data));

            return [
                'encrypted' => true,
                'data' => $encryptedData,
                'key_id' => $encryptionKey['key_id'],
                'algorithm' => $encryptionKey['algorithm'],
                'iv' => $encryptedData['iv'],
                'tag' => $encryptedData['tag'],
                'timestamp' => time(),
            ];
        } catch (Exception $e) {
            error_log('Failed to encrypt field: '.$e->getMessage());
            throw new Exception('Encryption failed');
        }
    }

    /**
     * Decrypt data using field-level encryption.
     */
    public function decryptField(array $encryptedData, ?string $keyId = null): string
    {
        try {
            // Get the appropriate key
            $encryptionKey = $this->getEncryptionKeyById($keyId);

            // Decrypt the data
            $decryptedData = $this->decryptData($encryptedData, $encryptionKey['key_material']);

            // Log decryption event
            $this->logEncryptionEvent('system', 'field', 'decrypt', strlen($decryptedData));

            return $decryptedData;
        } catch (Exception $e) {
            error_log('Failed to decrypt field: '.$e->getMessage());
            throw new Exception('Decryption failed');
        }
    }

    /**
     * Get encryption key for a specific field.
     */
    private function getEncryptionKey(string $collection, string $field, ?string $keyId = null): array
    {
        try {
            if ($keyId) {
                return $this->getEncryptionKeyById($keyId);
            }

            // Check if collection has specific key
            $collectionKey = $this->systemDb->encryption_keys->findOne([
                'type' => 'collection',
                'collection' => $collection,
                'status' => 'active',
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()],
            ]);

            if ($collectionKey) {
                return $collectionKey;
            }

            // Check if field has specific key
            $fieldKey = $this->systemDb->encryption_keys->findOne([
                'type' => 'field',
                'collection' => $collection,
                'field' => $field,
                'status' => 'active',
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()],
            ]);

            if ($fieldKey) {
                return $fieldKey;
            }

            // Use master key as fallback
            $masterKey = $this->systemDb->encryption_keys->findOne([
                'type' => 'master',
                'status' => 'active',
            ]);

            if (!$masterKey) {
                throw new Exception('No valid encryption key found');
            }

            return $masterKey;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get encryption key: '.$e->getMessage());
            throw new Exception('Failed to retrieve encryption key');
        }
    }

    /**
     * Get encryption key by ID.
     */
    private function getEncryptionKeyById(string $keyId): array
    {
        try {
            $key = $this->systemDb->encryption_keys->findOne([
                'key_id' => $keyId,
                'status' => 'active',
                'expires_at' => ['$gt' => new MongoDB\BSON\UTCDateTime()],
            ]);

            if (!$key) {
                throw new Exception('Encryption key not found or expired');
            }

            return $key;
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get encryption key by ID: '.$e->getMessage());
            throw new Exception('Failed to retrieve encryption key');
        }
    }

    /**
     * Encrypt data using appropriate algorithm.
     */
    private function encryptData(string $data, string $key): array
    {
        // Use sodium if available
        if (extension_loaded('sodium')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $encrypted = sodium_crypto_secretbox($data, $nonce, base64_decode($key));

            return [
                'algorithm' => 'XSalsa20-Poly1305',
                'iv' => base64_encode($nonce),
                'tag' => base64_encode($encrypted),
                'data' => base64_encode($encrypted),
            ];
        }

        // Fallback to OpenSSL
        $nonce = random_bytes(12); // 96-bit nonce for GCM
        $key = base64_decode($key);
        $encrypted = openssl_encrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

        return [
            'algorithm' => 'AES-256-GCM',
            'iv' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'data' => base64_encode($encrypted),
        ];
    }

    /**
     * Decrypt data using appropriate algorithm.
     */
    private function decryptData(array $encryptedData, string $key): string
    {
        $algorithm = $encryptedData['algorithm'];
        $iv = base64_decode($encryptedData['iv']);
        $tag = base64_decode($encryptedData['tag']);
        $data = base64_decode($encryptedData['data']);
        $key = base64_decode($key);

        // Use sodium if available
        if ($algorithm === 'XSalsa20-Poly1305' && extension_loaded('sodium')) {
            return sodium_crypto_secretbox_open($data, $iv, $key);
        }

        // Use OpenSSL for AES-256-GCM
        if ($algorithm === 'AES-256-GCM') {
            return openssl_decrypt($data, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
        }

        throw new Exception('Unsupported encryption algorithm');
    }

    /**
     * Log encryption event.
     */
    private function logEncryptionEvent(string $collection, string $field, string $action, int $dataSize): void
    {
        try {
            $event = [
                'collection' => $collection,
                'field' => $field,
                'action' => $action,
                'data_size' => $dataSize,
                'timestamp' => new MongoDB\BSON\UTCDateTime(),
                'user_id' => $_SESSION['user_id'] ?? null,
                'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            ];

            $this->systemDb->encryption_logs->insertOne($event);
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to log encryption event: '.$e->getMessage());
        }
    }

    /**
     * Generate collection-specific encryption key.
     */
    public function generateCollectionKey(string $collection, array $fields = []): array
    {
        try {
            // Generate key
            if (extension_loaded('sodium')) {
                $key = sodium_crypto_secretbox_keygen();
            } else {
                $key = openssl_random_pseudo_bytes(32);
            }

            $keyData = [
                'type' => 'collection',
                'key_id' => 'collection_'.bin2hex(random_bytes(16)),
                'key_material' => base64_encode($key),
                'key_version' => 1,
                'algorithm' => extension_loaded('sodium') ? 'XSalsa20-Poly1305' : 'AES-256-GCM',
                'status' => 'active',
                'collection' => $collection,
                'fields' => $fields,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id'] ?? 'system',
                'rotation_count' => 0,
                'last_rotated' => null,
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + ($this->keyRotationSchedule * 24 * 60 * 60)) * 1000),
                'rotation_schedule' => $this->keyRotationSchedule,
                'description' => "Collection-specific encryption key for {$collection}",
            ];

            $this->systemDb->encryption_keys->insertOne($keyData);

            // Log key generation
            $this->logEncryptionEvent($collection, 'key_generation', 'create', strlen($keyData['key_material']));

            return $keyData;
        } catch (Exception $e) {
            error_log('Failed to generate collection key: '.$e->getMessage());
            throw new Exception('Failed to generate collection encryption key');
        }
    }

    /**
     * Generate field-specific encryption key.
     */
    public function generateFieldKey(string $collection, string $field): array
    {
        try {
            // Generate key
            if (extension_loaded('sodium')) {
                $key = sodium_crypto_secretbox_keygen();
            } else {
                $key = openssl_random_pseudo_bytes(32);
            }

            $keyData = [
                'type' => 'field',
                'key_id' => 'field_'.bin2hex(random_bytes(16)),
                'key_material' => base64_encode($key),
                'key_version' => 1,
                'algorithm' => extension_loaded('sodium') ? 'XSalsa20-Poly1305' : 'AES-256-GCM',
                'status' => 'active',
                'collection' => $collection,
                'field' => $field,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id'] ?? 'system',
                'rotation_count' => 0,
                'last_rotated' => null,
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + ($this->keyRotationSchedule * 24 * 60 * 60)) * 1000),
                'rotation_schedule' => $this->keyRotationSchedule,
                'description' => "Field-specific encryption key for {$collection}.{$field}",
            ];

            $this->systemDb->encryption_keys->insertOne($keyData);

            // Log key generation
            $this->logEncryptionEvent($collection, $field, 'create', strlen($keyData['key_material']));

            return $keyData;
        } catch (Exception $e) {
            error_log('Failed to generate field key: '.$e->getMessage());
            throw new Exception('Failed to generate field encryption key');
        }
    }

    /**
     * Rotate encryption keys.
     */
    public function rotateKeys(array $keyIds = []): array
    {
        try {
            $results = [];

            if (empty($keyIds)) {
                // Rotate all expired keys
                $expiredKeys = $this->systemDb->encryption_keys->find([
                    'expires_at' => ['$lt' => new MongoDB\BSON\UTCDateTime()],
                    'status' => 'active',
                ])->toArray();

                foreach ($expiredKeys as $key) {
                    $results[] = $this->rotateKey($key['_id']);
                }
            } else {
                // Rotate specific keys
                foreach ($keyIds as $keyId) {
                    $results[] = $this->rotateKey($keyId);
                }
            }

            return $results;
        } catch (Exception $e) {
            error_log('Failed to rotate keys: '.$e->getMessage());
            throw new Exception('Key rotation failed');
        }
    }

    /**
     * Rotate specific key.
     */
    private function rotateKey($keyId): array
    {
        try {
            $key = $this->systemDb->encryption_keys->findOne(['_id' => $keyId]);

            if (!$key) {
                throw new Exception('Key not found');
            }

            // Generate new key
            if (extension_loaded('sodium')) {
                $newKey = sodium_crypto_secretbox_keygen();
            } else {
                $newKey = openssl_random_pseudo_bytes(32);
            }

            // Update key status
            $update = [
                'status' => 'rotated',
                'rotated_at' => new MongoDB\BSON\UTCDateTime(),
                'rotated_by' => $_SESSION['user_id'] ?? 'system',
            ];

            $this->systemDb->encryption_keys->updateOne(
                ['_id' => $keyId],
                ['$set' => $update]
            );

            // Create new key
            $newKeyData = [
                'type' => $key['type'],
                'key_id' => $key['key_id'].'_v'.($key['rotation_count'] + 1),
                'key_material' => base64_encode($newKey),
                'key_version' => $key['key_version'] + 1,
                'algorithm' => $key['algorithm'],
                'status' => 'active',
                'collection' => $key['collection'] ?? null,
                'field' => $key['field'] ?? null,
                'fields' => $key['fields'] ?? null,
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'created_by' => $_SESSION['user_id'] ?? 'system',
                'rotation_count' => $key['rotation_count'] + 1,
                'last_rotated' => new MongoDB\BSON\UTCDateTime(),
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + ($this->keyRotationSchedule * 24 * 60 * 60)) * 1000),
                'rotation_schedule' => $this->keyRotationSchedule,
                'description' => $key['description'].' (Rotated version '.($key['rotation_count'] + 1).')',
            ];

            $this->systemDb->encryption_keys->insertOne($newKeyData);

            // Log rotation
            $this->logEncryptionEvent(
                $key['collection'] ?? 'system',
                $key['field'] ?? 'key',
                'rotate',
                strlen($newKeyData['key_material'])
            );

            return [
                'old_key_id' => $key['key_id'],
                'new_key_id' => $newKeyData['key_id'],
                'rotation_count' => $newKeyData['rotation_count'],
                'status' => 'success',
            ];
        } catch (Exception $e) {
            error_log('Failed to rotate key: '.$e->getMessage());

            return [
                'key_id' => (string) $keyId,
                'status' => 'failed',
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get encryption keys status.
     */
    public function getEncryptionKeysStatus(): array
    {
        try {
            $totalKeys = $this->systemDb->encryption_keys->count();
            $activeKeys = $this->systemDb->encryption_keys->count(['status' => 'active']);
            $expiredKeys = $this->systemDb->encryption_keys->count([
                'status' => 'active',
                'expires_at' => ['$lt' => new MongoDB\BSON\UTCDateTime()],
            ]);
            $rotatedKeys = $this->systemDb->encryption_keys->count(['status' => 'rotated']);

            $keysByType = $this->systemDb->encryption_keys->aggregate([
                [
                    '$group' => [
                        '_id' => '$type',
                        'count' => ['$sum' => 1],
                    ],
                ],
            ])->toArray();

            $expiringSoon = $this->systemDb->encryption_keys->count([
                'status' => 'active',
                'expires_at' => [
                    '$gte' => new MongoDB\BSON\UTCDateTime(),
                    '$lte' => new MongoDB\BSON\UTCDateTime((time() + 7 * 24 * 60 * 60) * 1000),
                ],
            ]);

            return [
                'total_keys' => $totalKeys,
                'active_keys' => $activeKeys,
                'expired_keys' => $expiredKeys,
                'rotated_keys' => $rotatedKeys,
                'expiring_soon' => $expiringSoon,
                'keys_by_type' => array_map(function ($item) {
                    return [
                        'type' => $item['_id'],
                        'count' => $item['count'],
                    ];
                }, $keysByType),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        } catch (MongoDB\Driver\Exception\Exception $e) {
            error_log('Failed to get encryption keys status: '.$e->getMessage());

            return [
                'error' => $e->getMessage(),
                'last_updated' => date('Y-m-d H:i:s'),
            ];
        }
    }

    /**
     * Apply encryption to existing data.
     */
    public function applyEncryptionToCollection(string $collection, array $fields): array
    {
        try {
            $db = $this->systemDb->selectDatabase($collection);
            $collectionObj = $db->{$collection};

            $results = [
                'total_documents' => 0,
                'encrypted_documents' => 0,
                'failed_documents' => 0,
                'errors' => [],
            ];

            // Get all documents
            $documents = $collectionObj->find([])->toArray();
            $results['total_documents'] = count($documents);

            foreach ($documents as $document) {
                try {
                    $updateData = [];
                    $needsUpdate = false;

                    foreach ($fields as $field) {
                        if (isset($document[$field]) && !is_array($document[$field])) {
                            // Encrypt the field
                            $encrypted = $this->encryptField($document[$field], $collection, $field);
                            $updateData[$field] = $encrypted;
                            $needsUpdate = true;
                        }
                    }

                    if ($needsUpdate) {
                        $collectionObj->updateOne(
                            ['_id' => $document['_id']],
                            ['$set' => $updateData]
                        );
                        ++$results['encrypted_documents'];
                    }
                } catch (Exception $e) {
                    ++$results['failed_documents'];
                    $results['errors'][] = [
                        'document_id' => (string) $document['_id'],
                        'error' => $e->getMessage(),
                    ];
                }
            }

            return $results;
        } catch (Exception $e) {
            error_log('Failed to apply encryption to collection: '.$e->getMessage());
            throw new Exception('Failed to apply encryption to collection');
        }
    }

    /**
     * Create client-side encryption key.
     */
    public function createClientSideKey(string $userId, array $permissions = []): array
    {
        try {
            // Generate key pair for client-side encryption
            if (extension_loaded('sodium')) {
                $keyPair = sodium_crypto_box_keypair();
                $publicKey = sodium_crypto_box_publickey($keyPair);
                $secretKey = sodium_crypto_box_secretkey($keyPair);
            } else {
                // Fallback to OpenSSL key pair
                $config = [
                    'digest_alg' => 'sha256',
                    'private_key_bits' => 2048,
                    'private_key_type' => OPENSSL_KEYTYPE_RSA,
                ];
                $keyPair = openssl_pkey_new($config);
                openssl_pkey_export($keyPair, $secretKey);
                $publicKey = openssl_pkey_get_details($keyPair)['key'];
            }

            $keyData = [
                'type' => 'client_side',
                'key_id' => 'client_'.$userId.'_'.bin2hex(random_bytes(8)),
                'public_key' => base64_encode($publicKey),
                'secret_key' => base64_encode($secretKey),
                'user_id' => $userId,
                'permissions' => $permissions,
                'algorithm' => extension_loaded('sodium') ? 'Curve25519' : 'RSA-2048',
                'status' => 'active',
                'created_at' => new MongoDB\BSON\UTCDateTime(),
                'last_used' => null,
                'usage_count' => 0,
                'expires_at' => new MongoDB\BSON\UTCDateTime((time() + 90 * 24 * 60 * 60) * 1000), // 90 days
                'description' => "Client-side encryption key for user {$userId}",
            ];

            $this->systemDb->encryption_keys->insertOne($keyData);

            return $keyData;
        } catch (Exception $e) {
            error_log('Failed to create client-side key: '.$e->getMessage());
            throw new Exception('Failed to create client-side encryption key');
        }
    }

    /**
     * Mask sensitive data.
     */
    public function maskData(string $data, string $maskingType = 'partial'): string
    {
        switch ($maskingType) {
            case 'full':
                return str_repeat('*', strlen($data));

            case 'partial':
                if (strlen($data) <= 4) {
                    return str_repeat('*', strlen($data));
                }

                return substr($data, 0, 2).str_repeat('*', strlen($data) - 4).substr($data, -2);

            case 'email':
                if (filter_var($data, FILTER_VALIDATE_EMAIL)) {
                    list($local, $domain) = explode('@', $data);

                    return substr($local, 0, 2).str_repeat('*', strlen($local) - 2).'@'.$domain;
                }

                return $this->maskData($data, 'partial');

            case 'phone':
                // Remove all non-digit characters
                $digits = preg_replace('/[^0-9]/', '', $data);
                if (strlen($digits) >= 4) {
                    return substr($digits, 0, 2).str_repeat('*', strlen($digits) - 4).substr($digits, -2);
                }

                return str_repeat('*', strlen($digits));

            default:
                return $this->maskData($data, 'partial');
        }
    }
}
