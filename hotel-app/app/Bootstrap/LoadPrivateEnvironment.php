<?php

declare(strict_types=1);

namespace App\Bootstrap;

use Dotenv\Exception\InvalidFileException;
use Illuminate\Foundation\Bootstrap\LoadEnvironmentVariables;
use Symfony\Component\HttpFoundation\Response;

final class LoadPrivateEnvironment extends LoadEnvironmentVariables
{
    // Dotenv parser exceptions can include raw secret values. Never render them.
    protected function writeErrorAndDie(InvalidFileException $e): never
    {
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Private environment file is invalid. Correct its syntax and retry.\n");
        } else {
            (new Response('The application is not configured. Please contact the operator.', 503, [
                'Content-Type' => 'text/plain; charset=UTF-8',
                'Cache-Control' => 'no-store',
            ]))->send();
        }

        exit(1);
    }
}
