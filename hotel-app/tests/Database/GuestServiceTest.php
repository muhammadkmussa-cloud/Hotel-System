<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P07.05 — guest creation with unique labels inside a visit.
 *
 * A guest is an identity of its own: it must survive tablet replacement, and
 * its number/label is chosen by the server, never by the browser.
 */
final class GuestServiceTest extends TestCase
{
    public function testGuestsGetUniqueServerNumbersAndSurviveDeviceReplacement(): void
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
        $fixture = sys_get_temp_dir().'/hotel-guests-'.bin2hex(random_bytes(8));
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
            self::assertSame('created', $run('create-table', ['TABLE_LABEL' => 'G7'])['result']);
            $tableId = $run('table-id', ['TABLE_LABEL' => 'G7'])['id'];
            $visitId = $run('open-visit', ['VISIT_TABLE_ID' => $tableId, 'ACTOR_ID' => $waiterId])['id'];
            self::assertIsString($visitId);

            // Sequential adds: server assigns 1 then 2; the name is optional.
            $first = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId, 'GUEST_NAME' => 'Amina']);
            self::assertSame('created', $first['result']);
            self::assertSame('Guest 1', $first['label']);
            self::assertSame(1, $first['displayNumber']);

            $second = $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId]);
            self::assertSame('created', $second['result']);
            self::assertSame('Guest 2', $second['label']);

            // Invalid names are rejected without creating a row.
            self::assertSame('invalid_input', $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId, 'GUEST_NAME' => '   '])['result']);
            self::assertSame('invalid_input', $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId, 'GUEST_NAME' => str_repeat('x', 151)])['result']);
            self::assertCount(2, $run('list-guests', ['VISIT_ID' => $visitId])['guests']);

            // Two waiters adding at the same instant still get distinct numbers.
            $barrier = $fixture.'/barrier-'.bin2hex(random_bytes(4));
            $processes = [];
            foreach ([$ownerId, $waiterId] as $actorId) {
                $process = new Process([PHP_BINARY, 'probe.php', 'add-guest'], $fixture, array_merge($env, [
                    'VISIT_ID' => $visitId,
                    'ACTOR_ID' => $actorId,
                    'BARRIER' => $barrier,
                ]), timeout: 60);
                $process->start();
                $processes[] = $process;
            }
            touch($barrier);
            $numbers = [];
            foreach ($processes as $process) {
                $process->wait();
                self::assertSame(0, $process->getExitCode(), 'Private fixture output withheld.');
                $data = json_decode($process->getOutput(), true);
                self::assertIsArray($data);
                self::assertSame('created', $data['result']);
                $numbers[] = (int) $data['displayNumber'];
            }
            sort($numbers);
            self::assertSame([3, 4], $numbers, 'Concurrent adds must not collide on one guest number.');

            $guests = $run('list-guests', ['VISIT_ID' => $visitId])['guests'];
            self::assertCount(4, $guests);
            self::assertSame([1, 2, 3, 4], array_map(static fn (array $g): int => (int) $g['display_number'], $guests));
            $labels = array_map(static fn (array $g): string => (string) $g['label'], $guests);
            self::assertSame($labels, array_unique($labels), 'Labels are unique inside the visit.');

            // Identity survives tablet replacement: no device row is involved.
            $before = $run('find-guest', ['GUEST_ID' => $first['id']]);
            $run('drop-device-sessions');
            $after = $run('find-guest', ['GUEST_ID' => $first['id']]);
            self::assertTrue($after['found']);
            self::assertSame($before['id'], $after['id']);
            self::assertSame($before['label'], $after['label']);
            self::assertSame($before['display_number'], $after['display_number']);
            self::assertSame('Amina', $after['name']);
            self::assertSame('active', $after['state']);

            // Guest creation must be a top-level transaction: it refuses to be
            // folded into a caller's transaction and writes nothing.
            $nested = $run('add-guest-nested', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId]);
            self::assertTrue($nested['rejected']);
            self::assertSame(4, $nested['count']);
            self::assertCount(4, $run('list-guests', ['VISIT_ID' => $visitId])['guests']);

            // A closed visit accepts no new guests.
            self::assertTrue($run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId])['success']);
            self::assertSame('visit_closed', $run('add-guest', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $waiterId])['result']);
            self::assertCount(4, $run('list-guests', ['VISIT_ID' => $visitId])['guests'], 'History is preserved after closure.');

            // A second visit reuses the number space without colliding.
            $secondTable = 'G8';
            self::assertSame('created', $run('create-table', ['TABLE_LABEL' => $secondTable])['result']);
            $otherTableId = $run('table-id', ['TABLE_LABEL' => $secondTable])['id'];
            $otherVisitId = $run('open-visit', ['VISIT_TABLE_ID' => $otherTableId, 'ACTOR_ID' => $waiterId])['id'];
            $otherGuest = $run('add-guest', ['VISIT_ID' => $otherVisitId, 'ACTOR_ID' => $waiterId]);
            self::assertSame(1, $otherGuest['displayNumber'], 'Guest numbers are scoped to their visit.');
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
