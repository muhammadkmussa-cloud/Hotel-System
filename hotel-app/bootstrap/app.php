<?php

declare(strict_types=1);

use App\Bootstrap\LoadPrivateEnvironment;
use App\Http\Middleware\RequireInstallationConfiguration;
use App\Http\ApiExceptionResponse;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__ . '/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->prepend([RequireInstallationConfiguration::class, \App\Http\Middleware\ParseJsonInput::class]);
        $middleware->api(prepend: [
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \App\Http\Middleware\LimitRequests::class,
            \App\Http\Middleware\RequireCsrfToken::class,
        ]);
        $middleware->alias([
            'limit' => \App\Http\Middleware\LimitRequests::class,
            'principal' => \App\Http\Middleware\RequirePrincipal::class,
            'capability' => \App\Http\Middleware\RequireCapability::class,
            'media.upload' => \App\Http\Middleware\BoundMediaUpload::class,
        ]);
        $isApi = fn (Request $request): bool => $request->is('api/v1', 'api/v1/*');
        $middleware->trimStrings(except: [$isApi]);
        $middleware->convertEmptyStringsToNull(except: [$isApi]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (Throwable $error, Request $request) {
            return ApiExceptionResponse::render($request, $error);
        });
        // Also sanitize exceptions with their own render()/Responsable implementation.
        $exceptions->respond(function (Response $response, Throwable $error, Request $request) {
            return ApiExceptionResponse::render($request, $error) ?? $response;
        });
    })
    ->create();

$app->singleton(\Illuminate\Contracts\Debug\ExceptionHandler::class, \App\Http\ExceptionHandler::class);
$app->bind(LoadEnvironmentVariables::class, LoadPrivateEnvironment::class);
$app->singleton(\App\Support\MediaUploadLimits::class);
$app->singleton(\App\Support\RasterLimits::class, fn () => \App\Support\RasterLimits::fromConfig());
$app->singleton(\App\Support\RasterValidator::class);
$app->singleton(\App\Support\MediaStorage::class);

return $app;
