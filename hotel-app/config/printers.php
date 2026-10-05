<?php

declare(strict_types=1);

return [
    // Comma-separated allowlist of printer bridge hosts (HTTPS only).
    'bridge_hosts' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('PRINTER_BRIDGE_HOSTS', 'bridge.local'))
    ))),
];
