<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class StaffAuthenticatorTest extends TestCase
{
    public function testCredentialsAreVerifiedWithoutLeakingWrongFieldOrInactiveAccounts(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'STAFF_PASSWORD' => 'fixture-password-123'];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-auth-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
        $run = null;
        try {
            foreach (['app', 'config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/staff-auth-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            self::assertTrue($run('empty')['empty'], 'Requires an otherwise empty isolated schema.');
            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            $run('create-staff', ['STAFF_EMAIL' => 'staff@example.test']);
            self::assertTrue($run('attempt', ['STAFF_EMAIL' => 'STAFF@EXAMPLE.TEST'])['found'], 'Email match must be case-insensitive.');
            self::assertFalse($run('attempt', ['STAFF_PASSWORD' => 'wrong-password-000'])['found']);
            self::assertFalse($run('attempt', ['STAFF_EMAIL' => 'missing@example.test'])['found']);

            $activeId = $run('create-staff', ['STAFF_EMAIL' => 'active@example.test'])['id'];
            self::assertTrue($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('a', 40)])['resolved'], 'Active staff with an active session resolve.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'revoked', 'STAFF_SESSION_ID' => str_repeat('b', 40)])['resolved'], 'A revoked server-side session must not resolve.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'expired', 'STAFF_SESSION_ID' => str_repeat('f', 40)])['resolved'], 'An expired server-side session must not resolve.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('g', 40), 'STAFF_ROW_SESSION_ID' => str_repeat('h', 40)])['resolved'], 'A cookie id that does not match the session row must not resolve.');
            $secondId = $run('create-staff', ['STAFF_EMAIL' => 'second-active@example.test'])['id'];
            self::assertFalse($run('resolve', ['STAFF_ID' => $secondId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('k', 40), 'STAFF_ROW_OWNER' => $activeId])['resolved'], 'A session row owned by another staff member must not resolve.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'none', 'STAFF_SESSION_ID' => str_repeat('c', 40)])['resolved'], 'A session id without a server-side row must not resolve.');
            $inactiveId = $run('create-staff', ['STAFF_EMAIL' => 'inactive@example.test', 'STAFF_ACTIVE' => 'false'])['id'];
            self::assertFalse($run('attempt', ['STAFF_EMAIL' => 'inactive@example.test'])['found'], 'Inactive staff must not sign in.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $inactiveId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('d', 40)])['resolved'], 'Inactive staff must not resolve a principal.');
            self::assertFalse($run('resolve', ['STAFF_ID' => '00000000-0000-0000-0000-000000000000', 'STAFF_SESSION_MODE' => 'none', 'STAFF_SESSION_ID' => str_repeat('e', 40)])['resolved'], 'Unknown id must not resolve.');

            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'idle', 'STAFF_SESSION_ID' => str_repeat('i', 40)])['resolved'], 'An idle session must be locked.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $activeId, 'STAFF_SESSION_MODE' => 'guest', 'STAFF_SESSION_ID' => str_repeat('j', 40)])['resolved'], 'A customer/guest session must not resolve a staff principal.');
            self::assertTrue($run('attempt', ['STAFF_EMAIL' => 'active@example.test'])['found'], 'Active staff can still authenticate.');
            self::assertTrue($run('idle-then-touch', ['STAFF_ID' => $activeId])['resolved'], 'Activity after idle re-enables the session.');
            self::assertTrue($run('confirm', ['STAFF_ID' => $activeId, 'STAFF_CONFIRM_PASSWORD' => 'fixture-password-123'])['confirmed'], 'confirmById accepts the correct password.');
            self::assertFalse($run('confirm', ['STAFF_ID' => $activeId, 'STAFF_CONFIRM_PASSWORD' => 'wrong-password-000'])['confirmed'], 'confirmById rejects a wrong password.');
            self::assertFalse($run('confirm', ['STAFF_ID' => $inactiveId, 'STAFF_CONFIRM_PASSWORD' => 'fixture-password-123'])['confirmed'], 'confirmById rejects an inactive staff member.');
            self::assertFalse($run('confirm', ['STAFF_ID' => '00000000-0000-0000-0000-000000000000', 'STAFF_CONFIRM_PASSWORD' => 'fixture-password-123'])['confirmed'], 'confirmById rejects an unknown id.');

            // An idle session locks; a successful unlock restores a usable principal.
            $unlock = $run('unlock-roundtrip', ['STAFF_ID' => $activeId]);
            self::assertTrue($unlock['locked'], 'An idle session must be locked before unlock.');
            self::assertTrue($unlock['unlocked'], 'A successful unlock must restore a usable principal.');

            // Controller-level proof: sign in, sign out, and an old cookie cannot regain access.
            $roundtrip = $run('roundtrip', ['STAFF_ID' => $activeId]);
            self::assertTrue($roundtrip['before'], 'Signed-in principal resolves before sign-out.');
            self::assertFalse($roundtrip['after'], 'Sign-out clears the local session.');
            self::assertFalse($roundtrip['replayed'], 'The revoked old cookie must not regain access.');

            // Audit events never store secrets or session tokens.
            $audit = $run('audit');
            $payload = json_encode($audit);
            self::assertStringContainsString('login_failed', $payload);
            self::assertStringNotContainsString('supersecret-value', $payload);
            self::assertStringNotContainsString('tok-abcdef', $payload);
            self::assertStringNotContainsString('sess-xyz', $payload);
            self::assertStringNotContainsString('row-xyz', $payload);
            self::assertStringNotContainsString('plain-session', $payload);
            self::assertStringNotContainsString('Bearer abc', $payload);
            self::assertStringNotContainsString('hotel_session=abc', $payload);
            self::assertStringNotContainsString('api-abc', $payload);
            self::assertStringNotContainsString('hunter2', $payload, 'Credential-shaped values are dropped even under a benign key.');
            self::assertStringContainsString('credentials', $payload, 'Non-secret context is retained.');
            $events = $run('audit-events')['events'];
            self::assertNotContains('not_an_allowed_event', $events, 'Unknown events are rejected.');
            self::assertContains('logout', $events, 'Sign-out records a logout audit event.');
            self::assertContains('unlock_succeeded', $events, 'A successful unlock records an audit event.');

            $before = $run('inspect');
            self::assertSame(4, $before['count']);
            self::assertFalse($before['plainLeaked'], 'Plaintext must never be stored.');

            // A weak hash is upgraded on successful sign-in.
            $run('create-staff', ['STAFF_EMAIL' => 'weak@example.test', 'STAFF_WEAK' => 'true']);
            self::assertTrue($run('attempt', ['STAFF_EMAIL' => 'weak@example.test'])['found']);
        } finally {
            try {
                if ($owned && $run !== null) {
                    self::assertTrue($run('cleanup')['cleaned']);
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
