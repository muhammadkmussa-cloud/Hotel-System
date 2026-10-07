<?php

declare(strict_types=1);

namespace Tests\Database;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ReportLedgerTest extends TestCase
{
    public function testReportsAndCashSourcesReconcileFromAttributedLedgerRows(): void
    {
        self::assertTrue(getenv('HOTEL_TEST_DB_ALLOW_SCHEMA') === '1', 'Explicit disposable-schema opt-in is required.');
        $database = [];
        foreach (['HOST', 'PORT', 'DATABASE', 'USERNAME', 'PASSWORD'] as $name) {
            $value = getenv('HOTEL_TEST_DB_'.$name);
            self::assertTrue(is_string($value) && $value !== '', 'Explicit isolated MySQL settings are required.');
            $database['DB_'.$name] = $value;
        }
        $source = dirname(__DIR__, 2);
        $suffix = bin2hex(random_bytes(12));
        $fixture = sys_get_temp_dir().'/hotel-report-'.$suffix;
        $files = new Filesystem;
        $files->makeDirectory($fixture, 0700);
        $run = null;
        try {
            foreach (['config', 'routes', 'database/migrations'] as $directory) {
                $files->copyDirectory($source.'/'.$directory, $fixture.'/'.$directory);
            }
            foreach (['bootstrap/cache', 'storage/logs'] as $directory) {
                $files->makeDirectory($fixture.'/'.$directory, 0700, true);
            }
            foreach (['bootstrap/app.php', 'bootstrap/providers.php', 'artisan'] as $file) {
                $files->copy($source.'/'.$file, $fixture.'/'.$file);
            }
            $files->copy($source.'/tests/Fixtures/report-ledger-probe.php', $fixture.'/probe.php');
            symlink($source.'/vendor', $fixture.'/vendor');
            $environment = array_merge(array_fill_keys(array_keys(getenv()), false), [
                'PATH' => getenv('PATH'), 'APP_ENV' => 'testing', 'APP_URL' => 'http://127.0.0.1',
                'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)), 'REPORT_TEST_PREFIX' => 'rl_'.$suffix.'_',
            ], $database);
            $run = static function (string $action) use ($fixture, $environment): array {
                $process = new Process([PHP_BINARY, 'probe.php', $action], $fixture, $environment, timeout: 60);
                $process->run();
                self::assertSame(0, $process->getExitCode(), 'Isolated report fixture failed; output withheld.');
                self::assertSame('', $process->getErrorOutput(), 'Isolated report fixture wrote stderr; output withheld.');
                $decoded = json_decode($process->getOutput(), true, 512, JSON_THROW_ON_ERROR);
                self::assertIsArray($decoded);
                return $decoded;
            };
            self::assertSame(0, $run('migrate')['status']);
            $result = $run('exercise');

            self::assertSame('posted', $result['states']['01990000-0000-7000-8000-000000000012'], 'Historical posted cancellation was not repaired.');
            self::assertSame('voided', $result['states']['01990000-0000-7000-8000-000000000013'], 'Never-posted demand must remain voided.');
            self::assertSame('voided', $result['states']['01990000-0000-7000-8000-000000000014'], 'Cancelling provisional demand must void it.');
            self::assertSame('posted', $result['states']['01990000-0000-7000-8000-000000000015'], 'Cancelling a posted charge must preserve gross history.');
            self::assertSame(35000, $result['summary']['gross']);
            self::assertSame(1000, $result['summary']['discounts']);
            self::assertSame(25000, $result['summary']['cancellations']);
            self::assertSame(9000, $result['summary']['net']);
            self::assertSame(9000, $result['summary']['collected']);
            self::assertSame(500, $result['summary']['refunds']);
            self::assertSame(10000, $result['summary']['openBalances'], 'Shared allocations must not multiply gross sales.');
            self::assertSame([['date' => '2026-10-07', 'gross' => 35000, 'lines' => 3]], $result['summary']['daily']);
            $sales = array_slice($result['sales'], 1);
            self::assertCount(6, $sales, 'Sales export must contain three posted sales and three adjustment events.');
            $eventCounts = array_count_values(array_column($sales, 6));
            ksort($eventCounts);
            self::assertSame(['Cancellation' => 2, 'Discount' => 1, 'Sale' => 3], $eventCounts);
            self::assertEqualsWithDelta(350.00, array_sum(array_map('floatval', array_column($sales, 7))), 0.001);
            self::assertEqualsWithDelta(10.00, array_sum(array_map('floatval', array_column($sales, 8))), 0.001);
            self::assertEqualsWithDelta(250.00, array_sum(array_map('floatval', array_column($sales, 9))), 0.001);
            self::assertEqualsWithDelta(90.00, array_sum(array_map('floatval', array_column($sales, 10))), 0.001);
            self::assertNotContains('NeverPosted', array_column($sales, 4));
            self::assertNotContains('Proposed', array_column($sales, 4));
            self::assertCount(2, $result['payments']);
            self::assertSame('5.00', $result['payments'][1][7]);
            self::assertSame(1000, $result['bill']['discountsMinor'], 'A discount must be counted once for its owning allocation.');
            self::assertTrue($result['cash']['insufficientBlocked']);
            self::assertSame(['drawer', '01990000-0000-7000-8000-000000000007'], $result['cash']['drawerRefundSource']);
            self::assertSame(4000, $result['cash']['custodyBefore']['amountMinor']);
            self::assertSame(1000, $result['cash']['custodyBefore']['refundsMinor']);
            self::assertSame(0, $result['cash']['custodyAfter']['amountMinor']);
            self::assertSame(18000, $result['cash']['drawerAfter']['expected']);
            self::assertSame(1000, $result['cash']['drawerAfter']['refunds']);
            self::assertSame($result['cash']['handoverId'], $result['cash']['custodyRefundHandover']);
            self::assertSame(1, $result['cash']['unattributed'], 'Historical payouts must remain visible for manual reconciliation.');
        } finally {
            if ($run !== null) {
                try { $run('drop'); } catch (\Throwable) { /* Preserve the primary assertion failure. */ }
            }
            $files->deleteDirectory($fixture);
        }
    }
}
