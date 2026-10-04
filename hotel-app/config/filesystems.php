<?php

declare(strict_types=1);

return [
    'default' => 'local',
    'disks' => [
        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            // Original uploads stay private; do not register Laravel's serving route.
            'serve' => false,
            'throw' => true,
        ],
    ],
];
