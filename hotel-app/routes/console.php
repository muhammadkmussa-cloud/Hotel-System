<?php

declare(strict_types=1);

use App\Support\InstallationConfiguration;
use Illuminate\Support\Facades\Artisan;

Artisan::command('app:check-config', function (InstallationConfiguration $configuration): int {
    $errors = $configuration->errors();
    foreach ($errors as $error) {
        $this->error($error);
    }
    if ($errors !== []) {
        return 1;
    }

    $this->info('Installation configuration is valid. Database and provider connectivity have not been checked.');

    return 0;
})->purpose('Check private installation settings without displaying their values');
