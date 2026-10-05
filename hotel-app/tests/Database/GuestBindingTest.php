<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P07.06 — staff-authorized device-to-guest binding.
 *
 * A tablet only acquires a guest identity through a server-side binding, and
 * any table/guest identifier supplied by the browser is ignored.
 */
final class GuestBindingTest extends TestCase
{
    public function testBindingsAreStaffAuthorisedAndIdentityComesFromTheDevice(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-bindings-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
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
            $files->copy($source.'/tests/Fixtures/visit-service-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld: '.$process->getErrorOutput());
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            $ownerId = $run('create-staff', ['STAFF_EMAIL' => 'owner@example.test', 'STAFF_ROLES' => 'owner'])['id'];
            $waiterId = $run('create-staff', ['STAFF_EMAIL' => 'waiter@example.test', 'STAFF_ROLES' => 'waiter'])['id'];
            self::assertSame('created', $run('create-table', ['TABLE_LABEL' => 'B7'])['result']);
            $tableId = $run('table-id', ['TABLE_LABEL' => 'B7'])['id'];
            $visitId = $run('open-visit', ['VISIT_TABLE_ID' => $tableId, 'ACTOR_ID' => $waiterId])['id'];

            $guestOne = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId])['id'];
            $guestTwo = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId])['id'];

            // Two tablets and one kiosk, each with a live session.
            $tabletA = $run('create-device', ['DEVICE_NAME' => 'Tablet A', 'DEVICE_MODE' => 'tablet', 'DEVICE_CREDENTIAL' => 'fixture-credential-a-0001'])['deviceId'];
            $tabletB = $run('create-device', ['DEVICE_NAME' => 'Tablet B', 'DEVICE_MODE' => 'tablet', 'DEVICE_CREDENTIAL' => 'fixture-credential-b-0001'])['deviceId'];
            $kiosk = $run('create-device', ['DEVICE_NAME' => 'Kiosk 1', 'DEVICE_MODE' => 'kiosk', 'DEVICE_CREDENTIAL' => 'fixture-credential-k-0001'])['deviceId'];
            $sessionA = $run('create-device-session', ['DEVICE_ID' => $tabletA, 'DEVICE_TOKEN' => 'fixture-token-a-0001'])['sessionId'];
            $sessionB = $run('create-device-session', ['DEVICE_ID' => $tabletB, 'DEVICE_TOKEN' => 'fixture-token-b-0001'])['sessionId'];
            $sessionKiosk = $run('create-device-session', ['DEVICE_ID' => $kiosk, 'DEVICE_TOKEN' => 'fixture-token-k-0001'])['sessionId'];
            self::assertIsString($sessionA);
            self::assertIsString($sessionB);
            self::assertIsString($sessionKiosk);

            // Only tablet sessions are offered for guest binding.
            $bindable = $run('bindable-sessions', ['DEVICE_MODE' => 'tablet'])['sessions'];
            $bindableIds = array_column($bindable, 'sessionId');
            self::assertContains($sessionA, $bindableIds);
            self::assertContains($sessionB, $bindableIds);
            self::assertNotContains($sessionKiosk, $bindableIds);

            // Bind tablet A to guest 1; resolution ignores a forged guest id.
            $bindingA = $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionA, 'ACTOR_ID' => $waiterId]);
            self::assertSame('created', $bindingA['result']);

            $resolved = $run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionA, 'SPOOF_GUEST_ID' => $guestTwo]);
            self::assertIsArray($resolved['resolved']);
            self::assertSame($guestOne, $resolved['resolved']['guestId']);
            self::assertSame($visitId, $resolved['resolved']['visitId']);
            self::assertSame('B7', $resolved['resolved']['tableLabel']);
            self::assertTrue($resolved['ignoredSpoofedIdentity'], 'A supplied guest identity must never be honoured.');

            // A second tablet may join the same guest.
            self::assertSame('created', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionB, 'ACTOR_ID' => $waiterId])['result']);
            self::assertCount(2, $run('list-bindings', ['GUEST_ID' => $guestOne])['bindings']);

            // Rebinding tablet B replaces its previous binding instead of
            // giving one tablet two guests.
            self::assertSame('created', $run('bind-guest', ['GUEST_ID' => $guestTwo, 'DEVICE_SESSION_ID' => $sessionB, 'ACTOR_ID' => $waiterId])['result']);
            $rows = $run('binding-rows')['rows'];
            $liveForB = array_values(array_filter($rows, static fn (array $row): bool => $row['device_session_id'] === $sessionB && $row['revoked_at'] === null));
            self::assertCount(1, $liveForB, 'A device session holds at most one live binding.');
            self::assertSame($guestTwo, $liveForB[0]['guest_id']);
            self::assertCount(1, $run('list-bindings', ['GUEST_ID' => $guestOne])['bindings']);
            self::assertCount(1, $run('list-bindings', ['GUEST_ID' => $guestTwo])['bindings']);

            // A kiosk cannot take a guest identity.
            self::assertSame('wrong_mode', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionKiosk, 'ACTOR_ID' => $waiterId])['result']);

            // Unknown identifiers are rejected.
            self::assertSame('invalid_input', $run('bind-guest', ['GUEST_ID' => 'not-a-uuid', 'DEVICE_SESSION_ID' => $sessionA, 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('guest_not_found', $run('bind-guest', ['GUEST_ID' => '00000000-0000-7000-8000-000000000000', 'DEVICE_SESSION_ID' => $sessionA, 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('device_session_not_found', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => '00000000-0000-7000-8000-000000000000', 'ACTOR_ID' => $waiterId])['result']);

            // Two waiters binding the same tablet at once still leave one binding.
            $barrier = $fixture.'/barrier-'.bin2hex(random_bytes(4));
            $processes = [];
            foreach ([$guestOne, $guestTwo] as $guestId) {
                $process = new Process([PHP_BINARY, 'probe.php', 'bind-guest'], $fixture, array_merge($env, [
                    'GUEST_ID' => $guestId,
                    'DEVICE_SESSION_ID' => $sessionA,
                    'ACTOR_ID' => $waiterId,
                    'BARRIER' => $barrier,
                ]), timeout: 60);
                $process->start();
                $processes[] = $process;
            }
            touch($barrier);
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);
                $results[] = $data['result'];
            }
            self::assertContains('created', $results);
            $liveForA = array_values(array_filter(
                $run('binding-rows')['rows'],
                static fn (array $row): bool => $row['device_session_id'] === $sessionA && $row['revoked_at'] === null,
            ));
            self::assertCount(1, $liveForA, 'Concurrent binds must not leave two live bindings for one tablet.');

            // A revoked tablet session cannot be bound.
            self::assertSame('revoked', $run('revoke-device-session', ['DEVICE_SESSION_ID' => $sessionA])['result']);
            self::assertSame('device_session_inactive', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionA, 'ACTOR_ID' => $waiterId])['result']);
            self::assertNull($run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionA])['resolved'], 'Revoking the device session ends guest access.');

            // An expired session is refused too, and disappears from the list.
            self::assertTrue($run('expire-device-session', ['DEVICE_SESSION_ID' => $sessionB])['expired']);
            self::assertSame('device_session_inactive', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionB, 'ACTOR_ID' => $waiterId])['result']);
            self::assertNotContains($sessionB, array_column($run('bindable-sessions', ['DEVICE_MODE' => 'tablet'])['sessions'], 'sessionId'));
            self::assertSame([], $run('list-bindings', ['GUEST_ID' => $guestTwo])['bindings']);

            // Revocation is idempotent and keeps orders/bills untouched.
            $liveBinding = $liveForA[0]['id'];
            self::assertSame('revoked', $run('revoke-binding', ['BINDING_ID' => $liveBinding, 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('already_revoked', $run('revoke-binding', ['BINDING_ID' => $liveBinding, 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('invalid_input', $run('revoke-binding', ['BINDING_ID' => 'not-a-uuid', 'ACTOR_ID' => $waiterId])['result']);

            // Closing the visit stops further binding; history is preserved.
            self::assertTrue($run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId])['success']);
            $tabletC = $run('create-device', ['DEVICE_NAME' => 'Tablet C', 'DEVICE_MODE' => 'tablet', 'DEVICE_CREDENTIAL' => 'fixture-credential-c-0001'])['deviceId'];
            $sessionC = $run('create-device-session', ['DEVICE_ID' => $tabletC, 'DEVICE_TOKEN' => 'fixture-token-c-0001'])['sessionId'];
            self::assertSame('visit_closed', $run('bind-guest', ['GUEST_ID' => $guestOne, 'DEVICE_SESSION_ID' => $sessionC, 'ACTOR_ID' => $waiterId])['result']);
            self::assertCount(2, $run('list-guests', ['VISIT_ID' => $visitId])['guests'], 'Revoking bindings never deletes guests or their orders.');
            self::assertNotEmpty($run('binding-rows')['rows'], 'Binding history is retained for audit.');
        } finally {
            try {
                if ($owned) {
                    $run('cleanup');
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }

    /**
     * P07.10 — replacing a tablet ends the old device session together with
     * its binding, and the guest keeps its number for the replacement.
     */
    public function testReplacingATabletEndsTheOldSessionAndKeepsTheGuest(): void
    {
        self::assertSame('1', getenv('HOTEL_TEST_DB_ALLOW_SCHEMA'), 'Disposable-schema opt-in required.');
        $env = ['PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1', 'APP_KEY' => 'base64:'.base64_encode(random_bytes(32))];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $key) {
            $value = getenv('HOTEL_TEST_DB_'.$key);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit disposable DB settings required.');
            $env['DB_'.$key] = $value;
        }
        $env = array_merge(array_fill_keys(array_keys(getenv()), false), $env);
        $source = dirname(__DIR__, 2);
        $fixture = sys_get_temp_dir().'/hotel-replace-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $owned = false;
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
            $files->copy($source.'/tests/Fixtures/visit-service-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $run = static function (string $action, array $overrides = []) use ($fixture, $env): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, array_merge($env, $overrides), timeout: 60);
                $process->run();
                self::assertTrue($process->getErrorOutput() === '', 'Private error output withheld: '.$process->getErrorOutput());
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);

                return $data;
            };

            $owned = true;
            self::assertSame(0, $run('migrate')['status']);

            $waiterId = $run('create-staff', ['STAFF_EMAIL' => 'waiter@example.test', 'STAFF_ROLES' => 'waiter'])['id'];
            self::assertSame('created', $run('create-table', ['TABLE_LABEL' => 'R1'])['result']);
            $tableId = $run('table-id', ['TABLE_LABEL' => 'R1'])['id'];
            $visitId = $run('open-visit', ['VISIT_TABLE_ID' => $tableId, 'ACTOR_ID' => $waiterId])['id'];
            $guestId = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId, 'GUEST_NAME' => 'Ada'])['id'];

            $tabletA = $run('create-device', ['DEVICE_NAME' => 'Tablet A', 'DEVICE_MODE' => 'tablet', 'DEVICE_CREDENTIAL' => 'fixture-credential-ra-0001'])['deviceId'];
            $sessionA = $run('create-device-session', ['DEVICE_ID' => $tabletA, 'DEVICE_TOKEN' => 'fixture-token-ra-0001'])['sessionId'];
            $binding = $run('bind-guest', ['GUEST_ID' => $guestId, 'DEVICE_SESSION_ID' => $sessionA, 'ACTOR_ID' => $waiterId]);
            self::assertSame('created', $binding['result']);
            self::assertSame($guestId, $run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionA])['resolved']['guestId']);

            // Replacing the tablet ends the binding *and* the old device
            // session, so a handed-over device cannot keep reading the guest.
            self::assertSame('revoked', $run('replace-binding', ['BINDING_ID' => $binding['id'], 'ACTOR_ID' => $waiterId])['result']);
            self::assertTrue($run('device-session-state', ['DEVICE_SESSION_ID' => $sessionA])['revoked'], 'The replaced tablet loses its session.');
            self::assertNull($run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionA])['resolved'], 'The old tablet no longer resolves any guest.');

            // The guest keeps its number; the replacement takes the old slot.
            $tabletB = $run('create-device', ['DEVICE_NAME' => 'Tablet B', 'DEVICE_MODE' => 'tablet', 'DEVICE_CREDENTIAL' => 'fixture-credential-rb-0001'])['deviceId'];
            $sessionB = $run('create-device-session', ['DEVICE_ID' => $tabletB, 'DEVICE_TOKEN' => 'fixture-token-rb-0001'])['sessionId'];
            self::assertSame('created', $run('bind-guest', ['GUEST_ID' => $guestId, 'DEVICE_SESSION_ID' => $sessionB, 'ACTOR_ID' => $waiterId])['result']);
            $resolved = $run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionB, 'SPOOF_GUEST_ID' => $guestId])['resolved'];
            self::assertIsArray($resolved);
            self::assertSame($guestId, $resolved['guestId']);
            self::assertSame('Guest 1', $resolved['guestLabel'], 'The guest keeps its number through a device change.');
            self::assertSame($visitId, $resolved['visitId']);
            self::assertSame('R1', $resolved['tableLabel']);

            $guests = $run('list-guests', ['VISIT_ID' => $visitId])['guests'];
            self::assertCount(1, $guests, 'Replacing a tablet never duplicates the guest.');
            self::assertSame('Ada', $guests[0]['name']);
            self::assertCount(1, $run('list-bindings', ['GUEST_ID' => $guestId])['bindings']);

            // The old binding stays in the audit history, already revoked.
            $rows = $run('binding-rows')['rows'];
            self::assertCount(2, $rows);
            $old = array_values(array_filter($rows, static fn (array $row): bool => $row['id'] === $binding['id']));
            self::assertCount(1, $old);
            self::assertNotNull($old[0]['revoked_at']);

            // Replacement is idempotent and refuses junk identifiers.
            self::assertSame('already_revoked', $run('replace-binding', ['BINDING_ID' => $binding['id'], 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('invalid_input', $run('replace-binding', ['BINDING_ID' => 'not-a-uuid', 'ACTOR_ID' => $waiterId])['result']);
            self::assertSame('invalid_input', $run('replace-binding', ['BINDING_ID' => $binding['id'], 'ACTOR_ID' => ''])['result']);
            self::assertSame('not_found', $run('replace-binding', ['BINDING_ID' => '00000000-0000-7000-8000-000000000000', 'ACTOR_ID' => $waiterId])['result']);
        } finally {
            try {
                if ($owned) {
                    $run('cleanup');
                }
            } finally {
                $files->deleteDirectory($fixture);
            }
        }
    }
}
