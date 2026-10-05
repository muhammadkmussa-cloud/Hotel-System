<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * P07.04 — visits with a MySQL-compatible active-table uniqueness guard.
 *
 * Requires the explicit disposable-schema opt-in and private HOTEL_TEST_DB_*
 * settings; it never reads the working .env and cleans up its own tables.
 */
final class VisitServiceTest extends TestCase
{
    public function testOpenIsIdempotentUnderConcurrencyAndCloseReleasesTheTable(): void
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
        $fixture = sys_get_temp_dir().'/hotel-visits-'.bin2hex(random_bytes(8));
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
            self::assertSame('created', $run('create-table', ['TABLE_LABEL' => 'T7'])['result']);
            $tableId = $run('table-id', ['TABLE_LABEL' => 'T7'])['id'];
            self::assertIsString($tableId);

            // Two waiters opening the same table at the same instant: the
            // generated-column unique index decides, and both callers agree.
            $barrier = $fixture.'/barrier-'.bin2hex(random_bytes(4));
            $processes = [];
            foreach ([$ownerId, $waiterId] as $actorId) {
                $process = new Process([PHP_BINARY, 'probe.php', 'open-visit'], $fixture, array_merge($env, [
                    'VISIT_TABLE_ID' => $tableId,
                    'ACTOR_ID' => $actorId,
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
                $results[] = $data;
            }

            $visitIds = array_unique(array_column($results, 'id'));
            self::assertCount(1, $visitIds, 'Concurrent opens must resolve to one active visit.');
            self::assertSame(1, count(array_filter($results, static fn (array $r): bool => $r['created'] === true)), 'Exactly one caller creates the visit.');
            $visitId = (string) $visitIds[0];

            $state = $run('visit-state', ['VISIT_ID' => $visitId]);
            self::assertSame('open', $state['state']);
            self::assertSame(1, $state['active']);

            // A stale expected version must not close the visit.
            $stale = $run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId, 'EXPECTED_VERSION' => '99']);
            self::assertFalse($stale['success']);
            self::assertSame('open', $run('visit-state', ['VISIT_ID' => $visitId])['state']);

            $closed = $run('close-visit', ['VISIT_ID' => $visitId, 'ACTOR_ID' => $ownerId]);
            self::assertTrue($closed['success']);
            $afterClose = $run('visit-state', ['VISIT_ID' => $visitId]);
            self::assertSame('closed', $afterClose['state']);
            self::assertNull($afterClose['active'], 'Closing releases the active-table slot.');

            // The released table can host a new visit with a new identity.
            $reopened = $run('open-visit', ['VISIT_TABLE_ID' => $tableId, 'ACTOR_ID' => $ownerId]);
            self::assertTrue($reopened['created']);
            self::assertNotSame($visitId, $reopened['id']);
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
