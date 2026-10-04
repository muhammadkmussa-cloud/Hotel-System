<?php

declare(strict_types=1);

use App\Http\ApplicationRequest;

define('LARAVEL_START', microtime(true));

$maintenance = dirname(__DIR__) . '/storage/framework/maintenance.php';
if (is_file($maintenance)) {
    require $maintenance;
}

require dirname(__DIR__) . '/vendor/autoload.php';
$app = require dirname(__DIR__) . '/bootstrap/app.php';
$app->handleRequest(ApplicationRequest::capture());
