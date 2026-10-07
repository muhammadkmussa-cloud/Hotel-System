<?php

declare(strict_types=1);

return [
    'requests' => ['maximum' => (int) env('REQUEST_LIMIT_MAX', 600), 'seconds' => (int) env('REQUEST_LIMIT_SECONDS', 60)],
    'login' => ['maximum' => (int) env('LOGIN_LIMIT_MAX', 10), 'seconds' => (int) env('LOGIN_LIMIT_SECONDS', 60)],
    // Pairing codes are short-lived and single-use; throttle attempts per session.
    'pairing' => ['maximum' => (int) env('PAIRING_LIMIT_MAX', 10), 'seconds' => (int) env('PAIRING_LIMIT_SECONDS', 60)],
];
