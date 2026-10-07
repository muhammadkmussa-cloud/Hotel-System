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
    $action = $argv[1] ?? 'run';
    $owned = ['staff_sessions', 'staff_role_grants', 'installation_bootstrap', 'staff_users', 'roles', 'hotel_settings', 'idempotent_commands', 'probe_migrations'];
    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };

    if ($action === 'empty') {
        foreach ($owned as $table) {
            if ($schema->hasTable($table)) { echo json_encode(['empty' => false, 'table' => $table]); exit(0); }
        }
        echo json_encode(['empty' => true]);
        exit(0);
    }
    if ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
        exit(0);
    }
    if ($action === 'reset-staff') {
        // Clear identity rows without dropping schema, respecting FK order.
        foreach (['staff_sessions', 'staff_role_grants', 'installation_bootstrap', 'staff_users'] as $table) { $db->table($table)->delete(); }
        echo json_encode(['reset' => true]);
        exit(0);
    }
    if ($action === 'migrate') {
        $dropAll();
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
        exit(0);
    }
    if ($action === 'inspect') {
        $staff = $db->table('staff_users')->get(['id', 'email', 'password_hash', 'active'])->all();
        $grants = $db->table('staff_role_grants')->join('roles', 'roles.id', '=', 'staff_role_grants.role_id')->pluck('roles.key')->all();
        $bootstraps = $db->table('installation_bootstrap')->count();
        $plain = getenv('PROBE_OWNER_PASSWORD') ?: 'fixture-password-123';
        $hashedOk = count($staff) === 1 && Illuminate\Support\Facades\Hash::check($plain, $staff[0]->password_hash);
        echo json_encode([
            'staffCount' => count($staff),
            'grants' => $grants,
            'bootstraps' => $bootstraps,
            'hashedOk' => $hashedOk,
            'plainLeaked' => count($staff) === 1 && $staff[0]->password_hash === $plain,
        ], JSON_THROW_ON_ERROR);
        exit(0);
    }

    $output = new Symfony\Component\Console\Output\BufferedOutput;
    $code = $kernel->call('app:bootstrap-owner', [
        '--email' => getenv('PROBE_OWNER_EMAIL') ?: 'owner@example.test',
        '--name' => 'Fixture Owner',
        '--no-interaction' => true,
    ], $output);
    echo json_encode(['exit' => $code, 'output' => $output->fetch()]);
} catch (Throwable $error) {
    if (getenv('PROBE_DEBUG') === '1') { fwrite(STDERR, $error->getMessage()."\n"); } else { fwrite(STDERR, "Owner bootstrap fixture failed; private details withheld.\n"); }
    exit(1);
}
