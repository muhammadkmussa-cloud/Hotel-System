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
    $action = $argv[1] ?? 'probe';
    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };

    if ($action === 'empty') {
        foreach (['staff_users', 'roles', 'staff_role_grants', 'staff_sessions', 'installation_bootstrap', 'hotel_settings', 'idempotent_commands', 'probe_migrations'] as $table) {
            if ($schema->hasTable($table)) {
                echo json_encode(['empty' => false, 'table' => $table]);
                exit(0);
            }
        }
        echo json_encode(['empty' => true]);
        exit(0);
    }

    if ($action === 'cleanup') {
        $dropAll();
        echo json_encode(['cleaned' => true]);
        exit(0);
    }

    // This isolated disposable schema is reset to a known state so the test is
    // independent of whichever other disposable-schema tests ran before it.
    $dropAll();

    $output = new Symfony\Component\Console\Output\BufferedOutput;
    $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
    if ($status !== 0) {
        echo json_encode(['status' => $status]);
        exit(0);
    }

    $roles = $db->table('roles')->orderBy('key')->pluck('key')->all();
    $uuid = fn (): string => (string) Illuminate\Support\Str::uuid();

    $staffA = $uuid();
    $staffB = $uuid();
    $staffC = $uuid();
    $roleId = (string) $db->table('roles')->where('key', 'owner')->value('id');
    $now = date('Y-m-d H:i:s');

    $insertStaff = fn (string $id, string $email): bool => $db->table('staff_users')->insert([
        'id' => $id, 'email' => $email, 'name' => 'Fixture', 'password_hash' => password_hash('secret-fixture', PASSWORD_DEFAULT),
        'active' => 1, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $insertStaff($staffA, 'owner@example.test');
    $insertStaff($staffB, 'waiter@example.test');
    $insertStaff($staffC, 'granter@example.test');

    $duplicateEmail = false;
    try { $insertStaff($uuid(), 'owner@example.test'); } catch (Throwable) { $duplicateEmail = true; }

    $duplicateEmailCase = false;
    try { $insertStaff($uuid(), 'OWNER@EXAMPLE.TEST'); } catch (Throwable) { $duplicateEmailCase = true; }

    $token = $uuid();
    $db->table('staff_sessions')->insert([
        'id' => $uuid(), 'staff_user_id' => $staffA, 'token_hash' => hash('sha256', $token),
        'issued_at' => $now, 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
        'created_at' => $now, 'updated_at' => $now,
    ]);

    $duplicateToken = false;
    try {
        $db->table('staff_sessions')->insert([
            'id' => $uuid(), 'staff_user_id' => $staffB, 'token_hash' => hash('sha256', $token),
            'issued_at' => $now, 'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'created_at' => $now, 'updated_at' => $now,
        ]);
    } catch (Throwable) { $duplicateToken = true; }

    $db->table('staff_role_grants')->insert([
        'id' => $uuid(), 'staff_user_id' => $staffA, 'role_id' => $roleId, 'granted_by' => $staffC,
        'granted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
    ]);

    $granterDeleteBlocked = false;
    try { $db->table('staff_users')->where('id', $staffC)->delete(); } catch (Throwable) { $granterDeleteBlocked = true; }

    $duplicateGrant = false;
    try {
        $db->table('staff_role_grants')->insert([
            'id' => $uuid(), 'staff_user_id' => $staffA, 'role_id' => $roleId,
            'granted_at' => $now, 'created_at' => $now, 'updated_at' => $now,
        ]);
    } catch (Throwable) { $duplicateGrant = true; }

    $referencedDeleteBlocked = false;
    try { $db->table('staff_users')->where('id', $staffA)->delete(); } catch (Throwable) { $referencedDeleteBlocked = true; }

    $db->table('staff_users')->where('id', $staffA)->update(['active' => 0, 'deactivated_at' => $now, 'updated_at' => $now]);
    $preserved = (bool) $db->table('staff_users')->where('id', $staffA)->where('active', 0)->exists();

    $badRoleDeleteBlocked = false;
    try { $db->table('roles')->where('id', $roleId)->delete(); } catch (Throwable) { $badRoleDeleteBlocked = true; }

    echo json_encode([
        'status' => $status,
        'tables' => [
            'staff_users' => $schema->hasTable('staff_users'),
            'roles' => $schema->hasTable('roles'),
            'staff_role_grants' => $schema->hasTable('staff_role_grants'),
            'staff_sessions' => $schema->hasTable('staff_sessions'),
        ],
        'roles' => $roles,
        'duplicateEmail' => $duplicateEmail,
        'duplicateEmailCase' => $duplicateEmailCase,
        'duplicateToken' => $duplicateToken,
        'duplicateGrant' => $duplicateGrant,
        'referencedDeleteBlocked' => $referencedDeleteBlocked,
        'preserved' => $preserved,
        'granterDeleteBlocked' => $granterDeleteBlocked,
        'roleDeleteBlocked' => $badRoleDeleteBlocked,
    ], JSON_THROW_ON_ERROR);
} catch (Throwable $error) {
    if (getenv('STAFF_PROBE_DEBUG') === '1') {
        fwrite(STDERR, $error->getMessage()."\n".$error->getTraceAsString()."\n");
    } else {
        fwrite(STDERR, "Staff identity fixture failed; private details withheld.\n");
    }
    exit(1);
}
