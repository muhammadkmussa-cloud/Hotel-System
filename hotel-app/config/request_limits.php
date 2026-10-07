<?php

declare(strict_types=1);

return [
    'requests' => ['maximum' => (int) env('REQUEST_LIMIT_MAX', 600), 'seconds' => (int) env('REQUEST_LIMIT_SECONDS', 60)],
    'login' => ['maximum' => (int) env('LOGIN_LIMIT_MAX', 10), 'seconds' => (int) env('LOGIN_LIMIT_SECONDS', 60)],
];
