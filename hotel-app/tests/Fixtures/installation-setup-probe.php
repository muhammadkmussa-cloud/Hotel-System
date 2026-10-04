<?php

declare(strict_types=1);

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.migrations.table', 'probe_migrations');
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $action = $argv[1] ?? 'inspect';
    $owned = ['staff_sessions', 'staff_role_grants', 'installation_bootstrap', 'staff_users', 'roles', 'hotel_settings', 'idempotent_commands', 'probe_migrations'];

    if ($action === 'empty') {
        foreach ($owned as $table) { if ($schema->hasTable($table)) { echo json_encode(['empty' => false, 'table' => $table]); exit(0); } }
        echo json_encode(['empty' => true]);
        exit(0);
    }
    if ($action === 'cleanup') {
        foreach ($owned as $table) { $schema->dropIfExists($table); }
        echo json_encode(['cleaned' => true]);
        exit(0);
    }
    if ($action === 'migrate') {
        foreach ($owned as $table) { $schema->dropIfExists($table); }
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
        exit(0);
    }
    if ($action === 'create') {
        $setup = new App\Support\InstallationSetup($app['config'], $app['db']);
        $result = $setup->create(
            getenv('SETUP_NAME') !== false ? (string) getenv('SETUP_NAME') : 'Fixture Hotel',
            getenv('SETUP_TIMEZONE') !== false ? (string) getenv('SETUP_TIMEZONE') : 'Africa/Nairobi',
            getenv('SETUP_TEST_MODE') ?: false,
        );
        echo json_encode(['result' => $result]);
        exit(0);
    }

    $row = $db->table('hotel_settings')->first(['name', 'timezone', 'currency', 'test_mode']);
    echo json_encode([
        'count' => $db->table('hotel_settings')->count(),
        'name' => $row->name ?? null,
        'timezone' => $row->timezone ?? null,
        'currency' => $row->currency ?? null,
        'testMode' => isset($row->test_mode) ? (int) $row->test_mode : null,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    if (getenv('PROBE_DEBUG') === '1') { fwrite(STDERR, $error->getMessage()."\n"); } else { fwrite(STDERR, "Setup fixture failed; private details withheld.\n"); }
    exit(1);
}
