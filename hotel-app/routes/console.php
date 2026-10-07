<?php

declare(strict_types=1);

use App\Support\DatabaseConnectionCheck;
use App\Support\DemoReset;
use App\Support\InstallationConfiguration;
use App\Support\OwnerBootstrap;
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


Artisan::command('app:bootstrap-owner {--email= : Owner email} {--name= : Owner display name}', function (OwnerBootstrap $bootstrap): int {
    // Secrets are never accepted as command-line options (they would leak into
    // process arguments). Automation may set the guarded environment values;
    // otherwise the operator is prompted with hidden input.
    $secret = (string) (env('OWNER_BOOTSTRAP_SECRET') ?: $this->secret('Installer secret'));
    $password = (string) (env('OWNER_BOOTSTRAP_PASSWORD') ?: $this->secret('Owner password'));
    $email = is_string($this->option('email')) && $this->option('email') !== '' ? $this->option('email') : (string) $this->ask('Owner email');
    $name = is_string($this->option('name')) && $this->option('name') !== '' ? $this->option('name') : (string) $this->ask('Owner display name');

    $result = $bootstrap->bootstrap($secret, $email, $name, $password);
    $messages = [
        'created' => 'One-time owner bootstrap completed. Remove the installer secret and disable setup access.',
        'refused_unconfigured' => 'Bootstrap refused: no valid private installer secret is configured.',
        'refused_secret' => 'Bootstrap refused: the installer secret did not match.',
        'refused_exists' => 'Bootstrap refused: this installation already has an owner or staff records.',
        'invalid_input' => 'Bootstrap refused: provide a valid email, a name, and a sufficiently long password.',
        'failed' => 'Bootstrap failed. Private details withheld; inspect the private database and configuration.',
    ];
    $this->{$result === 'created' ? 'info' : 'error'}($messages[$result] ?? $messages['failed']);

    return $result === 'created' ? 0 : 1;
})->purpose('Create the single owner principal once, guarded by a private installer secret');

Artisan::command('app:demo-reset {--confirm-database= : Exact isolated demo database name}', function (DemoReset $reset): int {
    if (! $reset->reset($this->option('confirm-database'))) {
        $this->error('Demo reset refused or failed. Verify isolated demo configuration and guard; private details withheld.');
        return 1;
    }
    $this->info('Isolated demo settings reset. This is not a live hotel.');
    return 0;
})->purpose('Reset only a separately provisioned and marked demo database');

Artisan::command('hotel:run-jobs {--loop : Keep running, ticking every few seconds} {--seconds=55 : Loop duration when --loop is set}', function (\App\Domain\Operations\JobRunner $jobs): int {
    $until = time() + max(1, (int) $this->option('seconds'));
    do {
        $summary = $jobs->tick(50);
        if ($this->output->isVerbose()) {
            $this->line(json_encode($summary));
        }
        if (! $this->option('loop')) {
            break;
        }
        sleep(3);
    } while (time() < $until);

    return 0;
})->purpose('Run background jobs: M-PESA reconciliation, fiscal submissions, kiosk expiry and print lease expiry');

Artisan::command('hotel:backup {--keep=14 : Number of backups to retain}', function (\App\Domain\Operations\BackupService $backups): int {
    $path = $backups->create('scheduled');
    $check = $backups->verify($path);
    if (! ($check['ok'] ?? false)) {
        $this->error('Backup written but verification failed: '.implode('; ', $check['problems'] ?? []));

        return 1;
    }
    $pruned = $backups->prune((int) $this->option('keep'));
    $this->info('Backup '.basename($path).' verified ('.$check['tables'].' tables, '.$check['rows'].' rows). Pruned '.$pruned.'.');

    return 0;
})->purpose('Create, verify and rotate a database backup');

Artisan::command('hotel:demo-seed', function (\App\Support\DemoSeeder $seeder): int {
    try {
        $result = $seeder->seed();
    } catch (\RuntimeException $e) {
        $this->error($e->getMessage());

        return 1;
    }
    $this->info('Demo data created (TEST MODE). Staff password for every account: '.\App\Support\DemoSeeder::PASSWORD);
    foreach ($result['staff'] as $s) {
        $this->line(sprintf('  %-14s %s', $s['role'], $s['email']));
    }
    $this->info('Device pairing codes (valid 15 minutes; issue new ones in Admin → Devices):');
    foreach ($result['devices'] as $d) {
        $this->line(sprintf('  %-20s %-10s %s', $d['name'], $d['mode'], $d['code']));
    }

    return 0;
})->purpose('Seed a demonstration installation (refuses if staff already exist)');

Artisan::command('hotel:restore {path : Backup zip path (absolute, or a file name in the backup directory)} {--force : Required; replaces ALL current data}', function (\App\Domain\Operations\BackupService $backups): int {
    $path = (string) $this->argument('path');
    if (! is_file($path)) {
        $candidate = storage_path('app/private/backups/'.basename($path));
        $path = is_file($candidate) ? $candidate : $path;
    }
    if (! is_file($path)) {
        $this->error('Backup file not found.');

        return 1;
    }
    $check = $backups->verify($path);
    if (! ($check['ok'] ?? false)) {
        $this->error('Backup failed verification: '.implode('; ', $check['problems'] ?? []));

        return 1;
    }
    $this->info('Backup verified: '.$check['tables'].' tables, '.$check['rows'].' rows.');
    if (! $this->option('force')) {
        $this->warn('Dry run only. Re-run with --force to replace ALL current data with this backup. Take a fresh backup first.');

        return 0;
    }
    $safety = $backups->create('pre-restore');
    $this->line('Safety backup of current data: '.basename($safety));
    try {
        $backups->restore($path);
    } catch (\Throwable $e) {
        $this->error('Restore failed: '.$e->getMessage());

        return 1;
    }
    $this->info('Restore complete. Sign in again on every staff device and re-check open visits.');

    return 0;
})->purpose('Verify and restore a backup (dry run unless --force; takes a safety backup first)');

\Illuminate\Support\Facades\Schedule::command('hotel:run-jobs --loop --seconds=55')->everyMinute()->withoutOverlapping();
\Illuminate\Support\Facades\Schedule::command('hotel:backup')->dailyAt('03:30');
