<?php

declare(strict_types=1);

/**
 * Fixture probe for the visits/guests surface (P07.04/P07.05).
 *
 * Actions are driven by tests/Database/VisitServiceTest.php and
 * tests/Database/GuestServiceTest.php against a disposable MySQL schema.
 * Concurrency actions accept BARRIER: a file path that all racing processes
 * wait for before touching the database.
 */

require __DIR__.'/vendor/autoload.php';

try {
    $app = require __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
    $app['config']->set('database.migrations.table', 'probe_migrations');
    $db = $app['db']->connection('mysql');
    $schema = $db->getSchemaBuilder();
    $action = $argv[1] ?? 'inspect';

    // Children before parents; every table this probe may create.
    $owned = [
        'guest_bindings', 'guests', 'device_sessions', 'device_pairing_codes', 'devices', 'visits',
        'tables', 'stations', 'printer_destinations', 'staff_sessions', 'staff_role_grants',
        'installation_bootstrap', 'staff_users', 'roles', 'hotel_settings',
        'idempotent_commands', 'audit_events', 'probe_migrations',
    ];

    // The disposable schema is reset by dropping every base table (FK checks
    // off) rather than a hardcoded subset, so newly added migrations with
    // foreign keys onto owned tables cannot strand the schema.
    $dropAll = static function () use ($db, $schema): void {
        $db->statement('SET FOREIGN_KEY_CHECKS=0');
        foreach ($db->select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = DATABASE() AND table_type = 'BASE TABLE'") as $row) {
            $schema->dropIfExists((string) $row->name);
        }
        $db->statement('SET FOREIGN_KEY_CHECKS=1');
    };

    $waitForBarrier = static function (): void {
        $barrier = (string) (getenv('BARRIER') ?: '');
        if ($barrier === '') {
            return;
        }
        $deadline = microtime(true) + 20.0;
        while (! file_exists($barrier)) {
            if (microtime(true) > $deadline) {
                fwrite(STDERR, "Barrier timeout; private details withheld.\n");
                exit(1);
            }
            usleep(1000);
        }
    };

    if ($action === 'empty') {
        foreach ($owned as $table) {
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

    if ($action === 'migrate') {
        $dropAll();
        $output = new Symfony\Component\Console\Output\BufferedOutput;
        $status = $kernel->call('app:migrate', ['--no-interaction' => true], $output);
        echo json_encode(['status' => $status]);
        exit(0);
    }

    if ($action === 'create-staff') {
        $hasher = new Illuminate\Hashing\BcryptHasher(['rounds' => 4, 'verify' => false, 'limit' => 72]);
        $id = (string) Illuminate\Support\Str::uuid7();
        $db->table('staff_users')->insert([
            'id' => $id,
            'email' => strtolower(getenv('STAFF_EMAIL') ?: 'staff@example.test'),
            'name' => 'Fixture Staff',
            'password_hash' => $hasher->make(getenv('STAFF_PASSWORD') ?: 'fixture-password-123'),
            'active' => 1,
            'created_at' => now('UTC'), 'updated_at' => now('UTC'),
        ]);
        foreach (array_filter(explode(',', (string) (getenv('STAFF_ROLES') ?: '')), static fn ($v) => $v !== '') as $roleKey) {
            $roleId = $db->table('roles')->where('key', $roleKey)->value('id');
            if ($roleId !== null) {
                $db->table('staff_role_grants')->insert([
                    'id' => (string) Illuminate\Support\Str::uuid7(),
                    'staff_user_id' => $id,
                    'role_id' => $roleId,
                    'granted_at' => now('UTC'),
                    'created_at' => now('UTC'), 'updated_at' => now('UTC'),
                ]);
            }
        }
        echo json_encode(['id' => $id]);
        exit(0);
    }

    if ($action === 'create-table') {
        $tables = $app->make(App\Support\TableConfig::class);
        echo json_encode(['result' => $tables->create((string) (getenv('TABLE_LABEL') ?: 'T1'))]);
        exit(0);
    }

    if ($action === 'table-id') {
        $id = $db->table('tables')->where('label', (string) (getenv('TABLE_LABEL') ?: 'T1'))->value('id');
        echo json_encode(['id' => $id === null ? null : (string) $id]);
        exit(0);
    }

    if ($action === 'open-visit') {
        $waitForBarrier();
        $visits = $app->make(App\Support\VisitService::class);
        $result = $visits->open((string) (getenv('VISIT_TABLE_ID') ?: ''), (string) (getenv('ACTOR_ID') ?: ''));
        echo json_encode([
            'created' => $result['created'],
            'id' => $result['id'],
        ]);
        exit(0);
    }

    if ($action === 'visit-state') {
        $row = $db->table('visits')->where('id', (string) (getenv('VISIT_ID') ?: ''))->first(['id', 'state', 'version', 'active_flag']);
        echo json_encode($row === null ? ['found' => false] : [
            'found' => true,
            'state' => (string) $row->state,
            'version' => (int) $row->version,
            'active' => $row->active_flag === null ? null : (int) $row->active_flag,
        ]);
        exit(0);
    }

    if ($action === 'close-visit') {
        $visits = $app->make(App\Support\VisitService::class);
        $expected = getenv('EXPECTED_VERSION');
        $result = $visits->close(
            (string) (getenv('VISIT_ID') ?: ''),
            (string) (getenv('ACTOR_ID') ?: ''),
            $expected === false || $expected === '' ? null : (int) $expected,
        );
        echo json_encode(['success' => $result['success'], 'version' => $result['version']]);
        exit(0);
    }

    if ($action === 'add-guest') {
        $waitForBarrier();
        $guests = $app->make(App\Support\GuestService::class);
        $raw = getenv('GUEST_NAME');
        $result = $guests->add(
            (string) (getenv('VISIT_ID') ?: ''),
            (string) (getenv('ACTOR_ID') ?: ''),
            $raw === false || $raw === '' ? null : (string) $raw,
        );
        echo json_encode($result);
        exit(0);
    }

    if ($action === 'add-guest-nested') {
        // Guest creation must run as a top-level transaction: it refuses to be
        // folded into a caller's transaction, and nothing is written.
        $connection = $app->make(App\Support\GuestService::class)->connection();
        $visitId = (string) (getenv('VISIT_ID') ?: '');
        $rejected = false;
        try {
            $connection->transaction(static function () use ($app, $visitId): void {
                $app->make(App\Support\GuestService::class)->add(
                    $visitId,
                    (string) (getenv('ACTOR_ID') ?: ''),
                    null,
                );
            });
        } catch (Throwable $error) {
            $rejected = $error instanceof LogicException;
        }
        echo json_encode([
            'rejected' => $rejected,
            'count' => $db->table('guests')->where('visit_id', $visitId)->count(),
        ]);
        exit(0);
    }

    if ($action === 'list-guests') {
        $rows = $db->table('guests')->where('visit_id', (string) (getenv('VISIT_ID') ?: ''))
            ->orderBy('display_number')->get(['id', 'display_number', 'label', 'name', 'state', 'version'])->all();
        echo json_encode(['guests' => array_map(static fn ($row) => (array) $row, $rows)]);
        exit(0);
    }

    if ($action === 'find-guest') {
        $row = $db->table('guests')->where('id', (string) (getenv('GUEST_ID') ?: ''))
            ->first(['id', 'visit_id', 'display_number', 'label', 'name', 'state']);
        echo json_encode($row === null ? ['found' => false] : array_merge(['found' => true], (array) $row));
        exit(0);
    }

    if ($action === 'drop-device-sessions') {
        // Simulates every tablet being replaced: guest identity must survive.
        $deleted = $db->table('device_sessions')->delete();
        echo json_encode(['deleted' => $deleted]);
        exit(0);
    }

    if ($action === 'create-device') {
        $devices = $app->make(App\Support\DeviceRegistry::class);
        echo json_encode($devices->enroll(
            (string) (getenv('DEVICE_NAME') ?: 'Tablet 1'),
            (string) (getenv('DEVICE_MODE') ?: 'tablet'),
            (string) (getenv('DEVICE_CREDENTIAL') ?: 'fixture-device-credential-1'),
        ));
        exit(0);
    }

    if ($action === 'create-device-session') {
        $devices = $app->make(App\Support\DeviceRegistry::class);
        $token = (string) (getenv('DEVICE_TOKEN') ?: 'fixture-device-token-1');
        $result = $devices->createSession(
            (string) (getenv('DEVICE_ID') ?: ''),
            $token,
            (int) (getenv('DEVICE_TTL') ?: 120),
        );
        $sessionId = $db->table('device_sessions')->where('token_digest', hash('sha256', $token))->value('id');
        echo json_encode(['result' => $result, 'sessionId' => $sessionId === null ? null : (string) $sessionId]);
        exit(0);
    }

    if ($action === 'revoke-device-session') {
        $devices = $app->make(App\Support\DeviceRegistry::class);
        echo json_encode(['result' => $devices->revokeSession((string) (getenv('DEVICE_SESSION_ID') ?: ''))]);
        exit(0);
    }

    if ($action === 'expire-device-session') {
        // Forces the session past its expiry without touching revoked_at.
        $affected = $db->table('device_sessions')->where('id', (string) (getenv('DEVICE_SESSION_ID') ?: ''))
            ->update(['expires_at' => now('UTC')->subMinute(), 'updated_at' => now('UTC')]);
        echo json_encode(['expired' => $affected === 1]);
        exit(0);
    }

    if ($action === 'bindable-sessions') {
        $devices = $app->make(App\Support\DeviceRegistry::class);
        echo json_encode(['sessions' => $devices->activeSessions(getenv('DEVICE_MODE') ?: null)]);
        exit(0);
    }

    if ($action === 'bind-guest') {
        $waitForBarrier();
        $bindings = $app->make(App\Support\GuestBindingService::class);
        $ttl = getenv('TTL_MINUTES');
        echo json_encode($bindings->bind(
            (string) (getenv('GUEST_ID') ?: ''),
            (string) (getenv('DEVICE_SESSION_ID') ?: ''),
            (string) (getenv('ACTOR_ID') ?: ''),
            $ttl === false || $ttl === '' ? null : (int) $ttl,
        ));
        exit(0);
    }

    if ($action === 'revoke-binding') {
        $bindings = $app->make(App\Support\GuestBindingService::class);
        echo json_encode(['result' => $bindings->revoke(
            (string) (getenv('BINDING_ID') ?: ''),
            (string) (getenv('ACTOR_ID') ?: ''),
        )]);
        exit(0);
    }

    if ($action === 'resolve-binding') {
        // Resolution takes only the device session: any guest identity supplied
        // by the caller is ignored by construction.
        $bindings = $app->make(App\Support\GuestBindingService::class);
        $resolved = $bindings->resolveForDeviceSession((string) (getenv('DEVICE_SESSION_ID') ?: ''));
        $spoofed = (string) (getenv('SPOOF_GUEST_ID') ?: '');
        echo json_encode([
            'resolved' => $resolved,
            'ignoredSpoofedIdentity' => $resolved === null || $spoofed === '' || $resolved['guestId'] !== $spoofed,
        ]);
        exit(0);
    }

    if ($action === 'list-bindings') {
        $bindings = $app->make(App\Support\GuestBindingService::class);
        echo json_encode(['bindings' => $bindings->activeForGuest((string) (getenv('GUEST_ID') ?: ''))]);
        exit(0);
    }

    if ($action === 'binding-rows') {
        $rows = $db->table('guest_bindings')->get(['id', 'guest_id', 'device_session_id', 'revoked_at'])->all();
        echo json_encode(['rows' => array_map(static fn ($row) => (array) $row, $rows)]);
        exit(0);
    }

    if ($action === 'overview') {
        $visits = $app->make(App\Support\VisitService::class);
        echo json_encode(['tables' => $visits->overview()]);
        exit(0);
    }

    if ($action === 'visit-detail') {
        $visits = $app->make(App\Support\VisitService::class);
        echo json_encode(['visit' => $visits->visit((string) (getenv('VISIT_ID') ?: ''))]);
        exit(0);
    }

    if ($action === 'transfer') {
        $waitForBarrier();
        $visits = $app->make(App\Support\VisitService::class);
        $table = getenv('TABLE_ID');
        $waiter = getenv('WAITER_ID');
        $expected = getenv('EXPECTED_VERSION');
        echo json_encode($visits->transfer(
            (string) (getenv('VISIT_ID') ?: ''),
            $table === false || $table === '' ? null : (string) $table,
            $waiter === false || $waiter === '' ? null : (string) $waiter,
            (string) (getenv('ACTOR_ID') ?: ''),
            $expected === false || $expected === '' ? null : (int) $expected,
        ));
        exit(0);
    }

    if ($action === 'replace-binding') {
        $bindings = $app->make(App\Support\GuestBindingService::class);
        echo json_encode(['result' => $bindings->revokeWithDeviceSession(
            (string) (getenv('BINDING_ID') ?: ''),
            (string) (getenv('ACTOR_ID') ?: ''),
        )]);
        exit(0);
    }

    if ($action === 'device-session-state') {
        $row = $db->table('device_sessions')->where('id', (string) (getenv('DEVICE_SESSION_ID') ?: ''))
            ->first(['id', 'revoked_at', 'expires_at']);
        echo json_encode($row === null ? ['found' => false] : [
            'found' => true,
            'revoked' => $row->revoked_at !== null,
            'expiresAt' => (string) $row->expires_at,
        ]);
        exit(0);
    }

    if ($action === 'audit-events') {
        echo json_encode(['events' => $db->table('audit_events')->orderBy('created_at')->pluck('event')->all()]);
        exit(0);
    }

    if ($action === 'deactivate-table') {
        $db->table('tables')->where('id', (string) (getenv('TABLE_ID') ?: ''))->update(['active' => 0]);
        echo json_encode(['result' => 'deactivated']);
        exit(0);
    }

    if ($action === 'audit-record') {
        $audit = $app->make(App\Support\SecurityAudit::class);
        $audit->record((string) (getenv('EVENT_NAME') ?: ''), (string) (getenv('ACTOR_ID') ?: ''), '127.0.0.1', ['probe' => 'fixture']);
        $count = $db->table('audit_events')->where('event', (string) (getenv('EVENT_NAME') ?: ''))->count();
        echo json_encode(['recorded' => $count >= 1]);
        exit(0);
    }

    if ($action === 'deactivate-staff') {
        $db->table('staff_users')->where('id', (string) (getenv('STAFF_ID') ?: ''))->update(['active' => 0]);
        echo json_encode(['result' => 'deactivated']);
        exit(0);
    }

    fwrite(STDERR, "Unknown fixture action; private details withheld.\n");
    exit(1);
} catch (Throwable) {
    fwrite(STDERR, "Visit fixture failed; private details withheld.\n");
    exit(1);
}
