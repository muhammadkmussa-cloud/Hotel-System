<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P07.07 table overview and P07.08 visit detail reads.
 *
 * The overview reports table status and guest counts only: no balance or
 * kitchen field exists yet, so the screen can only show labelled placeholders.
 * Four guests and four tablets in one visit stay independent.
 */
final class VisitOverviewTest extends TestCase
{
    public function testOverviewShowsEveryTableStatusAndGuestCount(): void
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
        $fixture = sys_get_temp_dir().'/hotel-overview-'.bin2hex(random_bytes(8));
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

            foreach (['B1', 'A2', 'C3'] as $label) {
                self::assertSame('created', $run('create-table', ['TABLE_LABEL' => $label])['result']);
            }
            $tableB1 = $run('table-id', ['TABLE_LABEL' => 'B1'])['id'];
            $tableA2 = $run('table-id', ['TABLE_LABEL' => 'A2'])['id'];
            $tableC3 = $run('table-id', ['TABLE_LABEL' => 'C3'])['id'];

            // Empty board: every table is available, nobody is counted.
            $tables = $run('overview')['tables'];
            self::assertCount(3, $tables);
            self::assertSame(['A2', 'B1', 'C3'], array_column($tables, 'label'), 'Tables are ordered by label.');
            foreach ($tables as $table) {
                self::assertNull($table['visitId']);
                self::assertSame(0, $table['guestCount']);
                self::assertNull($table['openedAt']);
            }

            // The overview carries no balance or kitchen field, so the screen
            // cannot invent one: those screens arrive with P18/P19 and P16.
            self::assertSame(
                ['tableId', 'label', 'visitId', 'openedAt', 'guestCount', 'version'],
                array_keys($tables[0]),
            );

            // Open a visit and add guests; the count follows without a reload.
            $visitId = $run('open-visit', ['VISIT_TABLE_ID' => $tableB1, 'ACTOR_ID' => $waiterId])['id'];
            $guestIds = [];
            for ($index = 0; $index < 4; $index++) {
                $guestIds[] = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId])['id'];
            }

            $tables = $run('overview')['tables'];
            $byLabel = array_column($tables, null, 'label');
            self::assertSame($visitId, $byLabel['B1']['visitId']);
            self::assertSame(4, $byLabel['B1']['guestCount'], 'Guest count is the live count for the open visit.');
            self::assertNotNull($byLabel['B1']['openedAt']);
            self::assertNull($byLabel['A2']['visitId']);
            self::assertNull($byLabel['C3']['visitId']);

            // Detail read: table label, owning waiter, version and state.
            $visit = $run('visit-detail', ['VISIT_ID' => $visitId])['visit'];
            self::assertIsArray($visit);
            self::assertSame('B1', $visit['tableLabel']);
            self::assertSame('open', $visit['state']);
            self::assertSame($waiterId, $visit['ownerWaiterId'], 'The opening waiter owns the visit.');
            self::assertSame(1, $visit['version']);
            self::assertNotNull($visit['ownerWaiterName']);

            // P07.08 — four guests, four tablets, one table: independent.
            foreach (['T1', 'T2', 'T3', 'T4'] as $index => $deviceName) {
                $deviceId = $run('create-device', [
                    'DEVICE_NAME' => $deviceName,
                    'DEVICE_MODE' => 'tablet',
                    'DEVICE_CREDENTIAL' => 'fixture-credential-overview-'.$index,
                ])['deviceId'];
                $sessionId = $run('create-device-session', [
                    'DEVICE_ID' => $deviceId,
                    'DEVICE_TOKEN' => 'fixture-token-overview-'.$index,
                ])['sessionId'];
                self::assertSame('created', $run('bind-guest', [
                    'GUEST_ID' => $guestIds[$index],
                    'DEVICE_SESSION_ID' => $sessionId,
                    'ACTOR_ID' => $waiterId,
                ])['result']);

                $resolved = $run('resolve-binding', ['DEVICE_SESSION_ID' => $sessionId])['resolved'];
                self::assertIsArray($resolved);
                self::assertSame($guestIds[$index], $resolved['guestId'], 'Each tablet resolves its own guest.');
                self::assertSame($visitId, $resolved['visitId']);
                self::assertSame('B1', $resolved['tableLabel']);
            }
            self::assertSame(4, $run('overview')['tables'][1]['guestCount'], 'Binding tablets never changes the guest count.');

            // An inactive table leaves the board; its visit is not hidden from
            // history, but no waiter can be sent to a removed table.
            self::assertSame('deactivated', $run('deactivate-table', ['TABLE_ID' => $tableC3])['result']);
            self::assertSame(['A2', 'B1'], array_column($run('overview')['tables'], 'label'));

            // Closed visits free the table again.
            self::assertTrue($run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId])['success']);
            $byLabel = array_column($run('overview')['tables'], null, 'label');
            self::assertNull($byLabel['B1']['visitId'], 'A closed visit frees its table.');
            self::assertSame(0, $byLabel['B1']['guestCount']);
            self::assertSame('closed', $run('visit-detail', ['VISIT_ID' => $visitId])['visit']['state']);

            // Unknown or malformed visit identifiers read as "no such visit".
            self::assertNull($run('visit-detail', ['VISIT_ID' => 'not-a-uuid'])['visit']);
            self::assertNull($run('visit-detail', ['VISIT_ID' => '00000000-0000-7000-8000-000000000000'])['visit']);
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
