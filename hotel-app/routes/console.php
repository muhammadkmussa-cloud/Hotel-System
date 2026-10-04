<?php

declare(strict_types=1);

use App\Support\DatabaseConnectionCheck;
use App\Support\DemoReset;
use App\Support\InstallationConfiguration;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Output\NullOutput;

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

Artisan::command('app:check-database', function (InstallationConfiguration $configuration, DatabaseConnectionCheck $database): int {
    if ($configuration->errors() !== [] || ! $database->passes()) {
        $this->error('Database check failed. Verify private installation and MySQL settings.');

        return 1;
    }

    $this->info('MySQL connection is available. Schema and application data have not been checked.');

    return 0;
})->purpose('Check MySQL connectivity without displaying credentials or driver errors');

Artisan::command('app:migrate {--force : Permit an explicitly authorized production migration}', function (InstallationConfiguration $configuration, DatabaseConnectionCheck $database): int {
    if (app()->environment('production') && ! $this->option('force')) {
        $this->error('Production migrations require an authorized operator and --force.');

        return 1;
    }
    if ($configuration->errors() !== [] || ! $database->passes()) {
        $this->error('Migration preflight failed. Verify private installation and MySQL settings.');

        return 1;
    }

    try {
        $status = Artisan::call('migrate', [
            '--database' => 'mysql',
            '--force' => (bool) $this->option('force'),
            '--no-interaction' => true,
        ], new NullOutput);
    } catch (Throwable) {
        $status = 1;
    }

    if ($status !== 0) {
        $this->error('Migration failed. Stop and inspect the private schema and migration history before retrying.');

        return 1;
    }

    $this->info('Migration run completed. Previously recorded migrations were not repeated.');

    return 0;
})->purpose('Apply pending MySQL migrations with preflight checks and redacted output');

Artisan::command('app:demo-reset {--confirm-database= : Exact isolated demo database name}', function (DemoReset $reset): int {
    if (! $reset->reset($this->option('confirm-database'))) {
        $this->error('Demo reset refused or failed. Verify isolated demo configuration and guard; private details withheld.');
        return 1;
    }
    $this->info('Isolated demo settings reset. This is not a live hotel.');
    return 0;
})->purpose('Reset only a separately provisioned and marked demo database');
