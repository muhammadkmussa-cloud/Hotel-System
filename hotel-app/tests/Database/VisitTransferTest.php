<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P07.09 — locked table and waiter transfers.
 *
 * A transfer moves the visit; it never renumbers guests, drops tablet bindings
 * or re-points orders. The visit row is locked, the destination table is
 * checked while locked, and a stale version is refused.
 */
final class VisitTransferTest extends TestCase
{
    public function testTransfersMoveTheVisitWithoutTouchingGuestsOrBindings(): void
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
        $fixture = sys_get_temp_dir().'/hotel-transfer-'.bin2hex(random_bytes(8));
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
            $secondWaiterId = $run('create-staff', ['STAFF_EMAIL' => 'waiter2@example.test', 'STAFF_ROLES' => 'waiter'])['id'];
            $deactivatedId = $run('create-staff', ['STAFF_EMAIL' => 'gone@example.test', 'STAFF_ROLES' => 'waiter'])['id'];
            self::assertSame('deactivated', $run('deactivate-staff', ['STAFF_ID' => $deactivatedId])['result']);

            foreach (['T1', 'T2', 'T3'] as $label) {
                self::assertSame('created', $run('create-table', ['TABLE_LABEL' => $label])['result']);
            }
            $tableOne = $run('table-id', ['TABLE_LABEL' => 'T1'])['id'];
            $tableTwo = $run('table-id', ['TABLE_LABEL' => 'T2'])['id'];
            $tableThree = $run('table-id', ['TABLE_LABEL' => 'T3'])['id'];

            $visitId = $run('open-visit', ['VISIT_TABLE_ID' => $tableOne, 'ACTOR_ID' => $waiterId])['id'];
            $guestIds = [];
            foreach (['Ada', 'Bo'] as $name) {
                $guestIds[] = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId, 'GUEST_NAME' => $name])['id'];
            }
            $sessionIds = [];
            foreach ($guestIds as $index => $guestId) {
                $deviceId = $run('create-device', [
                    'DEVICE_NAME' => 'Transfer tablet '.$index,
                    'DEVICE_MODE' => 'tablet',
                    'DEVICE_CREDENTIAL' => 'fixture-credential-transfer-'.$index,
                ])['deviceId'];
                $sessionIds[$index] = $run('create-device-session', [
                    'DEVICE_ID' => $deviceId,
                    'DEVICE_TOKEN' => 'fixture-token-transfer-'.$index,
                ])['sessionId'];
                self::assertSame('created', $run('bind-guest', [
                    'GUEST_ID' => $guestId,
                    'DEVICE_SESSION_ID' => $sessionIds[$index],
                    'ACTOR_ID' => $waiterId,
                ])['result']);
            }

            // Waiter transfer: the owner changes, nothing else does.
            $transfer = $run('transfer', ['VISIT_ID' => $visitId, 'WAITER_ID' => $secondWaiterId, 'ACTOR_ID' => $ownerId]);
            self::assertSame('transferred', $transfer['result']);
            $visit = $run('visit-detail', ['VISIT_ID' => $visitId])['visit'];
            self::assertSame($secondWaiterId, $visit['ownerWaiterId']);
            self::assertSame('T1', $visit['tableLabel']);
            self::assertSame(2, $visit['version']);
            self::assertSame(['Ada', 'Bo'], array_column($run('list-guests', ['VISIT_ID' => $visitId])['guests'], 'name'), 'Guests keep their identity through a transfer.');

            // Table move: guests, bindings and resolution follow the visit.
            $transfer = $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => $tableTwo, 'ACTOR_ID' => $ownerId]);
            self::assertSame('transferred', $transfer['result']);
            $visit = $run('visit-detail', ['VISIT_ID' => $visitId])['visit'];
            self::assertSame('T2', $visit['tableLabel']);
            self::assertSame(3, $visit['version']);
            foreach ($sessionIds as $index => $sessionId) {
                $resolved = $run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionId, 'SPOOF_GUEST_ID' => $guestIds[0]])['resolved'];
                self::assertIsArray($resolved);
                self::assertSame($guestIds[$index], $resolved['guestId'], 'Tablets keep their guest after a table move.');
                self::assertSame('T2', $resolved['tableLabel'], 'A moved visit resolves to its new table.');
                self::assertSame($visitId, $resolved['visitId']);
            }
            $byLabel = array_column($run('overview')['tables'], null, 'label');
            self::assertNull($byLabel['T1']['visitId'], 'The old table is free again.');
            self::assertSame($visitId, $byLabel['T2']['visitId']);
            self::assertSame(2, $byLabel['T2']['guestCount']);

            // Both at once, from a stale version, is refused.
            self::assertSame('version_conflict', $run('transfer', [
                'VISIT_ID' => $visitId, 'TABLE_ID' => $tableThree, 'WAITER_ID' => $waiterId,
                'EXPECTED_VERSION' => 2, 'ACTOR_ID' => $ownerId,
            ])['result']);
            self::assertSame('T2', $run('visit-detail', ['VISIT_ID' => $visitId])['visit']['tableLabel']);

            // A stale-but-current version still moves both fields at once.
            self::assertSame('transferred', $run('transfer', [
                'VISIT_ID' => $visitId, 'TABLE_ID' => $tableThree, 'WAITER_ID' => $waiterId,
                'EXPECTED_VERSION' => 3, 'ACTOR_ID' => $ownerId,
            ])['result']);
            $visit = $run('visit-detail', ['VISIT_ID' => $visitId])['visit'];
            self::assertSame('T3', $visit['tableLabel']);
            self::assertSame($waiterId, $visit['ownerWaiterId']);
            self::assertSame(4, $visit['version']);

            // Occupied, missing and impossible destinations are refused.
            $otherVisit = $run('open-visit', ['VISIT_TABLE_ID' => $tableOne, 'ACTOR_ID' => $waiterId])['id'];
            self::assertSame('destination_occupied', $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => $tableOne, 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('destination_not_found', $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => '00000000-0000-7000-8000-000000000000', 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('waiter_not_found', $run('transfer', ['VISIT_ID' => $visitId, 'WAITER_ID' => '00000000-0000-7000-8000-000000000000', 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('waiter_not_found', $run('transfer', ['VISIT_ID' => $visitId, 'WAITER_ID' => $deactivatedId, 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('invalid_input', $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => 'not-a-uuid', 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('invalid_input', $run('transfer', ['VISIT_ID' => 'not-a-uuid', 'TABLE_ID' => $tableOne, 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('invalid_input', $run('transfer', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId])['result'], 'A transfer must name a destination.');
            self::assertSame('not_found', $run('transfer', ['VISIT_ID' => '00000000-0000-7000-8000-000000000000', 'TABLE_ID' => $tableTwo, 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame(4, $run('visit-detail', ['VISIT_ID' => $visitId])['visit']['version'], 'Refusals never bump the version.');

            // Repeating the current destination is a no-op, not a transfer.
            self::assertSame('unchanged', $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => $tableThree, 'WAITER_ID' => $waiterId, 'ACTOR_ID' => $ownerId])['result']);

            // Two managers moving the same visit into the same free table:
            // the row lock serialises them, so exactly one applies the move and
            // the loser observes the committed move and returns a no-op
            // ('unchanged') rather than stacking a second visit on the table.
            // (Two *different* visits into one table is the destination_occupied
            // case, asserted above.)
            $barrier = $fixture.'/barrier-'.bin2hex(random_bytes(4));
            $processes = [];
            foreach ([$tableTwo, $tableTwo] as $destination) {
                $process = new Process([PHP_BINARY, 'probe.php', 'transfer'], $fixture, array_merge($env, [
                    'VISIT_ID' => $visitId,
                    'TABLE_ID' => $destination,
                    'ACTOR_ID' => $ownerId,
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
            sort($results);
            self::assertSame(['transferred', 'unchanged'], $results, 'A contested move applies exactly once; the other caller sees it as a no-op.');
            self::assertSame(5, $run('visit-detail', ['VISIT_ID' => $visitId])['visit']['version'], 'Exactly one of the two moves bumped the version.');
            $finalLabel = $run('visit-detail', ['VISIT_ID' => $visitId])['visit']['tableLabel'];
            self::assertSame('T2', $finalLabel);
            $board = array_column($run('overview')['tables'], null, 'label');
            self::assertSame($visitId, $board[$finalLabel]['visitId']);
            self::assertSame(1, count(array_filter($board, static fn (array $table): bool => $table['visitId'] === $visitId)), 'The visit occupies exactly one table.');
            self::assertSame(2, $board[$finalLabel]['guestCount'], 'Guests survive a contested transfer.');

            // A visit that is still open moves into the table just freed.
            self::assertSame('transferred', $run('transfer', ['VISIT_ID' => $otherVisit, 'TABLE_ID' => $tableThree, 'ACTOR_ID' => $ownerId])['result']);
            self::assertSame('T3', $run('visit-detail', ['VISIT_ID' => $otherVisit])['visit']['tableLabel']);

            // Closure ends transfers, and the audit allowlist accepts the event.
            self::assertTrue($run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId])['success']);
            self::assertSame('visit_closed', $run('transfer', ['VISIT_ID' => $visitId, 'TABLE_ID' => $tableTwo, 'ACTOR_ID' => $ownerId])['result']);

            self::assertTrue($run('audit-record', ['EVENT_NAME' => 'visit_transferred', 'ACTOR_ID' => $ownerId])['recorded'], 'Transfers must be auditable.');
            self::assertFalse($run('audit-record', ['EVENT_NAME' => 'not_an_allowed_event', 'ACTOR_ID' => $ownerId])['recorded'], 'Unknown events are dropped.');
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
