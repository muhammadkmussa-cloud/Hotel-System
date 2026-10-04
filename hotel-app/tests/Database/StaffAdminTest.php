<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class StaffAdminTest extends TestCase
{
    public function testStaffListingAndCreationAreScopedToOwnersAndManagers(): void
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
        $fixture = sys_get_temp_dir().'/hotel-admin-'.bin2hex(random_bytes(8));
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

            $ownerId = $run('create-staff', ['STAFF_EMAIL' => 'owner2@example.test', 'STAFF_ROLES' => 'owner'])['id'];
            $managerId = $run('create-staff', ['STAFF_EMAIL' => 'manager@example.test', 'STAFF_ROLES' => 'manager'])['id'];
            $waiterId = $run('create-staff', ['STAFF_EMAIL' => 'waiter@example.test', 'STAFF_ROLES' => 'waiter'])['id'];

            self::assertTrue($run('authorizer', ['STAFF_ID' => $ownerId, 'CAPABILITY' => 'staff.manage'])['allowed'], 'Owner can manage staff.');
            self::assertTrue($run('authorizer', ['STAFF_ID' => $managerId, 'CAPABILITY' => 'staff.manage'])['allowed'], 'Manager can manage staff.');
            self::assertFalse($run('authorizer', ['STAFF_ID' => $waiterId, 'CAPABILITY' => 'staff.manage'])['allowed'], 'Waiter cannot manage staff.');
            self::assertFalse($run('authorizer', ['STAFF_ID' => $ownerId, 'CAPABILITY' => 'settings.manage'])['allowed'] === false, 'Owner can manage settings.');

            $list = $run('list')['staff'];
            self::assertCount(3, $list);
            $byId = array_column($list, null, 'id');
            self::assertSame(['owner'], $byId[$ownerId]['roles']);
            self::assertSame(['manager'], $byId[$managerId]['roles']);

            self::assertSame('created', $run('create', ['STAFF_EMAIL' => 'new@example.test', 'STAFF_NAME' => 'New Staff', 'STAFF_PASSWORD' => 'fixture-password-123', 'STAFF_ROLES' => 'cashier'])['result']);
            self::assertSame('duplicate_email', $run('create', ['STAFF_EMAIL' => 'new@example.test', 'STAFF_NAME' => 'Dup', 'STAFF_PASSWORD' => 'fixture-password-123', 'STAFF_ROLES' => 'cashier'])['result']);
            self::assertSame('invalid_input', $run('create', ['STAFF_EMAIL' => 'not-an-email', 'STAFF_NAME' => 'X', 'STAFF_PASSWORD' => 'fixture-password-123', 'STAFF_ROLES' => 'cashier'])['result']);
            self::assertSame('invalid_input', $run('create', ['STAFF_EMAIL' => 'badrole@example.test', 'STAFF_NAME' => 'X', 'STAFF_PASSWORD' => 'fixture-password-123', 'STAFF_ROLES' => 'superadmin'])['result']);
            self::assertSame('invalid_input', $run('create', ['STAFF_EMAIL' => 'noroles@example.test', 'STAFF_NAME' => 'X', 'STAFF_PASSWORD' => 'fixture-password-123', 'STAFF_ROLES' => ''])['result']);
            self::assertCount(4, $run('list')['staff']);

            // A manager cannot escalate to owner; an owner can (controller guard).
            self::assertTrue($run('authorizer', ['STAFF_ID' => $managerId, 'CAPABILITY' => 'staff.manage'])['allowed']);
            self::assertFalse($run('create-as', ['ACTOR_ID' => $managerId, 'STAFF_EMAIL' => 'promoted@example.test', 'STAFF_ROLES' => 'owner'])['created'], 'A manager cannot grant the owner role.');
            $promoted2 = $run('create-as', ['ACTOR_ID' => $ownerId, 'STAFF_EMAIL' => 'promoted2@example.test', 'STAFF_ROLES' => 'owner']);
            self::assertTrue($promoted2['created'], 'An owner can grant the owner role.');

            // Role grants: no self-escalation, no last-owner removal.
            self::assertSame('granted', $run('grant', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_ROLES' => 'cashier'])['result'], 'Owner can grant a role to another member.');
            self::assertSame('refused_self', $run('grant', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $ownerId, 'STAFF_ROLES' => 'manager'])['result'], 'Self-grant is refused.');
            self::assertSame('refused_owner', $run('grant', ['ACTOR_ID' => $managerId, 'STAFF_ID' => $waiterId, 'STAFF_ROLES' => 'owner'])['result'], 'Only an owner can grant owner.');
            self::assertSame('invalid_input', $run('grant', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_ROLES' => 'superadmin'])['result'], 'Unknown role refused.');
            self::assertSame('revoked', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $promoted2['id'], 'STAFF_ROLES' => 'owner'])['result'], 'Owner can be revoked while another owner remains.');
            self::assertSame('refused_last_owner', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $ownerId, 'STAFF_ROLES' => 'owner'])['result'], 'The last active owner cannot be stripped.');
            self::assertSame('revoked', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_ROLES' => 'cashier'])['result'], 'A granted role can be revoked.');
            self::assertSame('refused_owner', $run('revoke', ['ACTOR_ID' => $managerId, 'STAFF_ID' => $ownerId, 'STAFF_ROLES' => 'owner'])['result'], 'Only an owner can revoke the owner role.');
            self::assertSame('invalid_input', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => '00000000-0000-0000-0000-000000000000', 'STAFF_ROLES' => 'cashier'])['result'], 'Revoking from an unknown staff member is refused.');
            self::assertSame('invalid_input', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_ROLES' => ''])['result'], 'Empty role selection is refused.');
            self::assertSame('invalid_input', $run('revoke', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => 'not-a-uuid', 'STAFF_ROLES' => 'cashier'])['result'], 'Invalid UUID is refused.');

            // Concurrent last-owner revokes: the lock must leave exactly one owner.
            $processes = [];
            foreach ([$ownerId, $promoted2['id']] as $target) {
                $process = new Process([PHP_BINARY, 'probe.php', 'revoke'], $fixture, array_merge($env, [
                    'ACTOR_ID' => $ownerId, 'STAFF_ID' => $target, 'STAFF_ROLES' => 'owner',
                ]), timeout: 60);
                $process->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $results[] = json_decode($process->getOutput(), true)['result'];
            }
            self::assertContains('refused_last_owner', $results, 'A concurrent last-owner revoke is refused.');
            // Deactivation revokes sessions immediately.
            self::assertTrue($run('resolve', ['STAFF_ID' => $waiterId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('w', 40)])['resolved'], 'Waiter session resolves before deactivation.');
            self::assertSame('deactivated', $run('deactivate', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId])['result'], 'Owner can deactivate a member.');
            self::assertFalse($run('resolve', ['STAFF_ID' => $waiterId, 'STAFF_SESSION_MODE' => 'active', 'STAFF_SESSION_ID' => str_repeat('w', 40)])['resolved'], 'Deactivated member loses access immediately.');
            self::assertSame('refused_self', $run('deactivate', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $ownerId])['result'], 'Self-deactivation is refused.');
            self::assertSame('invalid_input', $run('deactivate', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => '00000000-0000-0000-0000-000000000000'])['result'], 'Unknown staff member refused.');
            self::assertSame('refused_last_owner', $run('last-owner', ['ACTOR_ID' => $managerId, 'STAFF_ID' => $ownerId])['result'], 'The last active owner cannot be deactivated.');

            // Concurrent last-owner deactivations: exactly one succeeds.
            $owner3 = $run('create-as', ['ACTOR_ID' => $ownerId, 'STAFF_EMAIL' => 'owner3@example.test', 'STAFF_ROLES' => 'owner']);
            self::assertTrue($owner3['created'], 'A third owner is created.');
            $processes = [];
            foreach ([$ownerId, $owner3['id']] as $target) {
                $process = new Process([PHP_BINARY, 'probe.php', 'deactivate'], $fixture, array_merge($env, [
                    'ACTOR_ID' => $managerId, 'STAFF_ID' => $target,
                ]), timeout: 60);
                $process->start();
                $processes[] = $process;
            }
            $results = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $results[] = json_decode($process->getOutput(), true)['result'];
            }
            self::assertContains('refused_last_owner', $results, 'A concurrent last-owner deactivation is refused.');
            // Edit and activate.
            self::assertSame('updated', $run('update', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_NAME' => 'Waiter Renamed', 'STAFF_EMAIL' => 'waiter@example.test'])['result'], 'Name and email can be updated.');
            self::assertSame('duplicate_email', $run('update', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId, 'STAFF_NAME' => 'Waiter Renamed', 'STAFF_EMAIL' => 'owner2@example.test'])['result'], 'Duplicate email is refused.');
            self::assertSame('invalid_input', $run('update', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => '00000000-0000-0000-0000-000000000000', 'STAFF_EMAIL' => 'x@example.test'])['result'], 'Unknown staff member refused.');
            self::assertSame('activated', $run('activate', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId])['result'], 'A deactivated member can be activated.');
            self::assertSame('already_active', $run('activate', ['ACTOR_ID' => $ownerId, 'STAFF_ID' => $waiterId])['result'], 'Activating an active member is refused.');

            $remaining = $run('list')['staff'];
            $activeOwners = count(array_filter($remaining, static fn ($m) => $m['active'] && in_array('owner', $m['roles'], true)));
            self::assertSame(1, $activeOwners, 'Exactly one active owner remains after concurrent deactivations.');
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
