<?php

declare(strict_types=1);

use App\Bootstrap\LoadPrivateEnvironment;
use App\Http\Middleware\RequireInstallationConfiguration;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend(RequireInstallationConfiguration::class);
    })
    ->withExceptions()
    ->create();

$app->bind(LoadEnvironmentVariables::class, LoadPrivateEnvironment::class);

return $app;
