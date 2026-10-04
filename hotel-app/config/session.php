<?php

declare(strict_types=1);

return [
    'driver' => 'file',
    'cookie' => 'hotel_session',
    'domain' => null,
    'secure' => ! str_starts_with((string) env('APP_URL', ''), 'http://'),
    'http_only' => true,
    'same_site' => 'lax',
];
