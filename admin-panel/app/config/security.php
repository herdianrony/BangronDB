<?php

return [
    'encryption' => [
        'key_rotation_schedule' => 30,
        'encryption_method' => 'AES-256-GCM',
        'key_derivation' => 'PBKDF2',
        'salt_length' => 16,
        'iterations' => 100000,
    ],
];
