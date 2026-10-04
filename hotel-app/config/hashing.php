<?php

declare(strict_types=1);

return [
    // Pinned so a framework upgrade cannot silently change the password posture.
    'driver' => 'bcrypt',
    'bcrypt' => [
        'rounds' => 12,
        'verify' => false,
        'limit' => 72,
    ],
    'argon' => [
        'memory' => 65536,
        'threads' => 1,
        'time' => 4,
        'verify' => false,
    ],
    'rehash_on_login' => true,
];
