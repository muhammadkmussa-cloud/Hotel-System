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
        foreach (['staff_sessions', 'staff_role_grants', 'installation_bootstrap', 'staff_users', 'roles', 'hotel_settings', 'idempotent_commands', 'probe_migrations'] as $table) { $schema->dropIfExists($table); }
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
    if ($action === 'create-staff') {
        $weak = filter_var(getenv('STAFF_WEAK') ?: 'false', FILTER_VALIDATE_BOOLEAN);
        $hasher = $weak ? new Illuminate\Hashing\BcryptHasher(['rounds' => 4, 'verify' => false, 'limit' => 72]) : new Illuminate\Hashing\BcryptHasher(['rounds' => 12, 'verify' => false, 'limit' => 72]);
        $password = getenv('STAFF_PASSWORD') ?: 'fixture-password-123';
        $id = (string) Illuminate\Support\Str::uuid7();
        $db->table('staff_users')->insert([
            'id' => $id,
            'email' => strtolower(getenv('STAFF_EMAIL') ?: 'staff@example.test'),
            'name' => 'Fixture Staff',
            'password_hash' => $hasher->make($password),
            'active' => filter_var(getenv('STAFF_ACTIVE') ?: 'true', FILTER_VALIDATE_BOOLEAN) ? 1 : 0,
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
        echo json_encode(['created' => true, 'id' => $id]);
        exit(0);
    }
    if ($action === 'resolve') {
        $sessions = $app->make(App\Support\StaffSessions::class);
        $cookieId = getenv('STAFF_SESSION_ID') ?: str_repeat('a', 40);
        $rowSessionId = getenv('STAFF_ROW_SESSION_ID') ?: $cookieId;
        $session = new Illuminate\Session\Store('probe', new Illuminate\Session\ArraySessionHandler(120), $cookieId);
        $staffId = (string) (getenv('STAFF_ID') ?: '');
        $mode = getenv('STAFF_SESSION_MODE') ?: 'active';
        if ($mode === 'guest') {
            // A customer/guest session must not inherit staff privileges.
            $session->put('guest_id', $staffId);
        } else {
            $session->put('staff_user_id', $staffId);
        }
        if ($mode === 'none') {
            $session->put('staff_session_id', (string) Illuminate\Support\Str::uuid7());
        } else {
            $ownerForRow = getenv('STAFF_ROW_OWNER') ?: $staffId;
            $token = hash('sha256', $rowSessionId);
            $existing = $db->table('staff_sessions')->where('staff_user_id', $ownerForRow)->where('token_hash', $token)->value('id');
            $rowId = $existing ?? $sessions->start($ownerForRow, $rowSessionId, 120);
            if ($mode === 'revoked') { $sessions->revoke($rowId); }
            if ($mode === 'expired') { $db->table('staff_sessions')->where('id', $rowId)->update(['expires_at' => now('UTC')->subMinute()]); }
            if ($mode === 'idle') { $db->table('staff_sessions')->where('id', $rowId)->update(['last_seen_at' => now('UTC')->subMinutes(120)]); }
            if ($mode === 'touched') { $sessions->touch($rowId); }
            $session->put('staff_session_id', $rowId);
        }
        $request = Illuminate\Http\Request::create('/', 'GET');
        $request->setLaravelSession($session);
        $principal = $app->make(App\Security\SessionPrincipalResolver::class)->resolve($request);
        if (getenv('PROBE_DEBUG') === '1') { fwrite(STDERR, json_encode(['sid'=>$session->getId(),'row'=>$session->get('staff_session_id'),'staff'=>$session->get('staff_user_id'),'active'=>$sessions->active((string)$session->get('staff_session_id'), $session->getId(), (string)$session->get('staff_user_id'))])."\n"); }
        echo json_encode(['resolved' => $principal !== null, 'id' => $principal?->identifier()]);
        exit(0);
    }
    if ($action === 'confirm') {
        $authenticator = $app->make(App\Support\StaffAuthenticator::class);
        echo json_encode(['confirmed' => $authenticator->confirmById(
            (string) (getenv('STAFF_ID') ?: ''),
            (string) (getenv('STAFF_CONFIRM_PASSWORD') ?: ''),
        )]);
        exit(0);
    }
    if ($action === 'idle-then-touch') {
        $sessions = $app->make(App\Support\StaffSessions::class);
        $resolver = $app->make(App\Security\SessionPrincipalResolver::class);
        $cookieId = str_repeat('t', 40);
        $rowId = $sessions->start((string) (getenv('STAFF_ID') ?: ''), $cookieId, 120);
        $db->table('staff_sessions')->where('id', $rowId)->update(['last_seen_at' => now('UTC')->subMinutes(120)]);
        $sessions->touch($rowId);
        $store = new Illuminate\Session\Store('probe', new Illuminate\Session\ArraySessionHandler(120), $cookieId);
        $store->put('staff_user_id', (string) (getenv('STAFF_ID') ?: ''));
        $store->put('staff_session_id', $rowId);
        $request = Illuminate\Http\Request::create('/', 'GET');
        $request->setLaravelSession($store);
        echo json_encode(['resolved' => $resolver->resolve($request) !== null]);
        exit(0);
    }
    if ($action === 'unlock-roundtrip') {
        $sessions = $app->make(App\Support\StaffSessions::class);
        $authenticator = $app->make(App\Support\StaffAuthenticator::class);
        $resolver = $app->make(App\Security\SessionPrincipalResolver::class);
        $staffId = (string) (getenv('STAFF_ID') ?: '');
        $cookieId = str_repeat('u', 40);
        $rowId = $sessions->start($staffId, $cookieId, 120);
        $db->table('staff_sessions')->where('id', $rowId)->update(['last_seen_at' => now('UTC')->subMinutes(120)]);
        $store = new Illuminate\Session\Store('probe', new Illuminate\Session\ArraySessionHandler(120), $cookieId);
        $store->put('staff_user_id', $staffId);
        $store->put('staff_session_id', $rowId);
        $request = Illuminate\Http\Request::create('/', 'POST', ['password' => getenv('STAFF_PASSWORD') ?: 'fixture-password-123']);
        $request->setLaravelSession($store);
        $locked = $resolver->resolve($request) === null;
        $app->make(App\Http\Controllers\StaffUnlockController::class)->store($request, $authenticator, $sessions, $app->make(App\Support\SecurityAudit::class));
        $unlocked = $resolver->resolve($request) !== null;
        echo json_encode(['locked' => $locked, 'unlocked' => $unlocked]);
        exit(0);
    }
    if ($action === 'audit') {
        $audit = $app->make(App\Support\SecurityAudit::class);
        $audit->record('login_failed', null, '203.0.113.9', [
            'password' => 'supersecret-value',
            'token' => 'tok-abcdef',
            'session_id' => 'sess-xyz',
            'staff_session_id' => 'row-xyz',
            'session' => 'plain-session',
            'authorization' => 'Bearer abc',
            'cookie' => 'hotel_session=abc',
            'api_token' => 'api-abc',
            'reason' => 'credentials',
            'note' => 'password=hunter2',
        ]);
        $audit->record('not_an_allowed_event', null, '203.0.113.9', ['reason' => 'nope']);
        $rows = $db->table('audit_events')->get(['event', 'context', 'actor_staff_user_id', 'ip_address'])->all();
        echo json_encode(['rows' => array_map(static fn ($r) => (array) $r, $rows)]);
        exit(0);
    }
    if ($action === 'audit-events') {
        echo json_encode(['events' => $db->table('audit_events')->orderBy('created_at')->pluck('event')->all()]);
        exit(0);
    }
    if ($action === 'authorizer') {
        $authorizer = $app->make(App\Security\StaffCapabilityAuthorizer::class);
        $principal = new App\Security\SessionPrincipal((string) (getenv('STAFF_ID') ?: ''));
        $request = Illuminate\Http\Request::create('/', 'GET');
        echo json_encode(['allowed' => $authorizer->allows($principal, (string) (getenv('CAPABILITY') ?: 'staff.manage'), $request)]);
        exit(0);
    }
    if ($action === 'list') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        echo json_encode(['staff' => array_map(static fn ($row) => $row, $admin->list())]);
        exit(0);
    }
    if ($action === 'create') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        $result = $admin->create(
            (string) (getenv('STAFF_EMAIL') ?: ''),
            (string) (getenv('STAFF_NAME') ?: ''),
            (string) (getenv('STAFF_PASSWORD') ?: ''),
            array_values(array_filter(explode(',', (string) (getenv('STAFF_ROLES') ?: '')), static fn ($v) => $v !== '')),
        );
        echo json_encode(['result' => $result]);
        exit(0);
    }
    if ($action === 'create-as') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        $request = Illuminate\Http\Request::create('/admin/staff', 'POST', [
            'email' => (string) (getenv('STAFF_EMAIL') ?: ''),
            'name' => (string) (getenv('STAFF_NAME') ?: 'X'),
            'password' => (string) (getenv('STAFF_PASSWORD') ?: 'fixture-password-123'),
            'roles' => array_values(array_filter(explode(',', (string) (getenv('STAFF_ROLES') ?: '')), static fn ($v) => $v !== '')),
        ]);
        $request->attributes->set(App\Http\Middleware\RequirePrincipal::ATTRIBUTE, new App\Security\SessionPrincipal((string) (getenv('ACTOR_ID') ?: '')));
        $app->make(App\Http\Controllers\StaffAdminController::class)->store($request, $admin);
        $row = $db->table('staff_users')->where('email', strtolower((string) (getenv('STAFF_EMAIL') ?: '')))->first(['id']);
        echo json_encode(['created' => $row !== null, 'id' => $row?->id]);
        exit(0);
    }
    if ($action === 'grant' || $action === 'revoke') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        $result = $action === 'grant'
            ? $admin->grantRoles(
                (string) (getenv('STAFF_ID') ?: ''),
                array_values(array_filter(explode(',', (string) (getenv('STAFF_ROLES') ?: '')), static fn ($v) => $v !== '')),
                (string) (getenv('ACTOR_ID') ?: ''),
            )
            : $admin->revokeRoles(
                (string) (getenv('STAFF_ID') ?: ''),
                array_values(array_filter(explode(',', (string) (getenv('STAFF_ROLES') ?: '')), static fn ($v) => $v !== '')),
                (string) (getenv('ACTOR_ID') ?: ''),
            );
        echo json_encode(['result' => $result]);
        exit(0);
    }
    if ($action === 'deactivate') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        $result = $admin->deactivate((string) (getenv('STAFF_ID') ?: ''), (string) (getenv('ACTOR_ID') ?: ''));
        echo json_encode(['result' => $result]);
        exit(0);
    }
    if ($action === 'last-owner') {
        // Force exactly one active owner, then attempt to deactivate them.
        $staffId = (string) (getenv('STAFF_ID') ?: '');
        $admin = $app->make(App\Support\StaffAdmin::class);
        $ownerRoleId = $db->table('roles')->where('key', 'owner')->value('id');
        $db->table('staff_role_grants')->where('role_id', $ownerRoleId)->delete();
        $db->table('staff_users')->where('id', $staffId)->update(['active' => 1, 'updated_at' => now('UTC')]);
        $db->table('staff_role_grants')->insertOrIgnore([
            'id' => (string) Illuminate\Support\Str::uuid7(),
            'staff_user_id' => $staffId,
            'role_id' => $ownerRoleId,
            'granted_by' => (string) (getenv('ACTOR_ID') ?: ''),
            'granted_at' => now('UTC'),
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        echo json_encode(['result' => $admin->deactivate($staffId, (string) (getenv('ACTOR_ID') ?: ''))]);
        exit(0);
    }
    if ($action === 'update' || $action === 'activate') {
        $admin = $app->make(App\Support\StaffAdmin::class);
        $result = $action === 'update'
            ? $admin->update(
                (string) (getenv('STAFF_ID') ?: ''),
                (string) (getenv('STAFF_NAME') ?: ''),
                (string) (getenv('STAFF_EMAIL') ?: ''),
                (string) (getenv('ACTOR_ID') ?: ''),
            )
            : $admin->activate((string) (getenv('STAFF_ID') ?: ''), (string) (getenv('ACTOR_ID') ?: ''));
        echo json_encode(['result' => $result]);
        exit(0);
    }
    if ($action === 'roundtrip') {
        $sessions = $app->make(App\Support\StaffSessions::class);
        $resolver = $app->make(App\Security\SessionPrincipalResolver::class);
        $staffId = (string) (getenv('STAFF_ID') ?: '');
        $cookieId = str_repeat('z', 40);
        $store = new Illuminate\Session\Store('probe', new Illuminate\Session\ArraySessionHandler(120), $cookieId);
        $rowId = $sessions->start($staffId, $cookieId, 120);
        $store->put('staff_user_id', $staffId);
        $store->put('staff_session_id', $rowId);
        $request = Illuminate\Http\Request::create('/', 'GET');
        $request->setLaravelSession($store);
        $before = $resolver->resolve($request) !== null;
        $app->make(App\Http\Controllers\StaffSignOutController::class)->store($request, $sessions, $app->make(App\Support\SecurityAudit::class));
        $after = $resolver->resolve($request) !== null;
        // Replay the original cookie id after sign-out.
        $replay = new Illuminate\Session\Store('probe', new Illuminate\Session\ArraySessionHandler(120), $cookieId);
        $replay->put('staff_user_id', $staffId);
        $replay->put('staff_session_id', $rowId);
        $replayRequest = Illuminate\Http\Request::create('/', 'GET');
        $replayRequest->setLaravelSession($replay);
        $replayed = $resolver->resolve($replayRequest) !== null;
        echo json_encode(['before' => $before, 'after' => $after, 'replayed' => $replayed]);
        exit(0);
    }

    if ($action === 'attempt') {
        $authenticator = $app->make(App\Support\StaffAuthenticator::class);
        $staff = $authenticator->attempt(getenv('STAFF_EMAIL') ?: 'staff@example.test', getenv('STAFF_PASSWORD') ?: 'fixture-password-123');
        echo json_encode(['found' => $staff !== null, 'id' => $staff?->getKey()]);
        exit(0);
    }

    $rows = $db->table('staff_users')->get(['email', 'password_hash'])->all();
    echo json_encode([
        'count' => count($rows),
        'prefix' => $rows[0]->password_hash ?? null ? substr($rows[0]->password_hash, 0, 7) : null,
        'plainLeaked' => count($rows) === 1 && $rows[0]->password_hash === (getenv('STAFF_PASSWORD') ?: 'fixture-password-123'),
    ]);
} catch (Throwable $error) {
    if (getenv('PROBE_DEBUG') === '1') { fwrite(STDERR, $error->getMessage()."\n"); } else { fwrite(STDERR, "Staff auth fixture failed; private details withheld.\n"); }
    exit(1);
}
