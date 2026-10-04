<?php

declare(strict_types=1);

return [
    'origin' => env('APP_URL'),
    // Private one-time bootstrap secret; never a default. Minimum length is enforced in code.
    'installer_secret' => env('INSTALLER_SECRET', ''),
    // First-install setup screen. Enable only during initial setup, then disable.
    'setup_enabled' => env('INSTALLATION_SETUP_ENABLED', false),
    // Staff sessions lock after this many idle minutes and require password re-verification.
    'staff_idle_minutes' => max(1, (int) env('STAFF_IDLE_MINUTES', 30)),
];
